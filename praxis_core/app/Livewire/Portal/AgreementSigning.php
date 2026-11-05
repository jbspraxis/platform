<?php

namespace App\Livewire\Portal;

use App\Models\Letters\LetterCounterparty;
use App\Models\Letters\LetterDocument;
use App\Support\Letters\Agreements;
use App\Support\Letters\LetterBody;
use App\Support\Letters\Letterhead;
use App\Support\Letters\Letters;
use App\Support\Signing\SigningCodes;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The page the other party to an agreement signs (docs/DOCUMENTS.md section 12), opened from the private link in
 * the email JBS Praxis sent them. Nobody is signed in here: the link's own token is the only key, and it carries
 * an expiry of its own (Agreements::DAYS).
 *
 * They read the whole agreement — the same blocks the PDF prints, and the PDF itself beside it — then type their
 * full name, draw or upload a signature, and ask for a code. The code goes to the address JBS Praxis addressed
 * the agreement to, never to one typed here, so a forwarded link cannot be signed by somebody else. The code is
 * limited exactly as a proposal's is (App\Support\Signing\SigningCodes): three codes and five wrong tries every
 * fifteen minutes.
 *
 * The right code applies the signature to a new PDF with a signature record page; the issued file is untouched.
 */
#[Layout('components.layouts.portal')]
class AgreementSigning extends Component
{
    #[Locked]
    public string $token = '';

    /** details (name and signature) | code (the one emailed) */
    #[Locked]
    public string $step = 'details';

    public string $signedName = '';

    /** data:image/jpeg;base64,… from the pad or the picture they chose */
    public string $signature = '';

    public string $signatureKind = 'drawn';

    public string $code = '';

    /** True once this visit has signed it */
    #[Locked]
    public bool $done = false;

    public function mount(string $token): void
    {
        $party = Agreements::findByToken($token);
        abort_unless($party, 404);
        $this->token = $token;

        $this->signedName = (string) ($party->signed_name ?: $party->name);
        // A code is already on its way (they reloaded the page): ask for it rather than starting again
        if ($party->status === LetterCounterparty::SENT && $party->otp && $party->otp_expires_at?->isFuture()) {
            $this->step = 'code';
        }
    }

    private function party(): LetterCounterparty
    {
        $party = Agreements::findByToken($this->token);
        abort_unless($party, 404);

        return $party;
    }

    /** The pad and the file picker both end here, exactly as they do on HQ's My signature */
    public function setImage(string $dataUri, string $kind, int $width, int $height): void
    {
        $this->signature = $dataUri;
        $this->signatureKind = $kind === 'uploaded' ? 'uploaded' : 'drawn';
        $this->resetErrorBag('signature');
    }

    public function clearImage(): void
    {
        $this->signature = '';
    }

    /** Name and signature checked, then a code is emailed to the address the agreement names */
    public function requestCode(): void
    {
        $this->resetErrorBag();
        $party = $this->party();
        $document = LetterDocument::findOrFail($party->document_id);
        if (! $this->open($party, $document)) {
            return;
        }

        $this->signedName = trim($this->signedName);
        $this->validate([
            'signedName' => ['required', 'string', 'max:150'],
        ], [
            'signedName.required' => __('agreements.portal.errors.name'),
            'signedName.max' => __('agreements.portal.errors.name_long'),
        ]);

        try {
            $image = Agreements::readSignature($this->signature);
        } catch (Throwable $e) {
            $this->addError('signature', __('agreements.portal.errors.signature'));

            return;
        }

        if ($refused = SigningCodes::claimSend(Agreements::KIND, $party->id, $party->email)) {
            $this->addError('code', __('agreements.portal.errors.' . $refused[0], ['wait' => $this->minutes($refused[1])]));

            return;
        }

        $code = SigningCodes::newCode();
        if (! Agreements::holdSignature($party, $this->signedName, $image, $code)) {
            $this->addError('signedName', __('agreements.portal.errors.closed'));

            return;
        }

        try {
            Agreements::mailCode($party->fresh(), $document, $code);
        } catch (Throwable $e) {
            // The code itself is never logged, and an unsent one is cancelled so it cannot be asked for
            Log::error('Agreement signing code email failed', ['counterparty_id' => $party->id, 'message' => $e->getMessage()]);
            Agreements::forgetCode($party);
            $this->addError('signedName', __('agreements.portal.errors.email_failed'));

            return;
        }

        Letters::audit('AGREEMENT_CODE_SENT', 'A signing code was sent for ' . $document->reference . ' to ' . $party->email, $document);

        $this->code = '';
        $this->step = 'code';
    }

    /** The code: the right one signs */
    public function verify(): void
    {
        $this->resetErrorBag();
        $party = $this->party();
        $document = LetterDocument::findOrFail($party->document_id);
        if (! $this->open($party, $document)) {
            return;
        }

        $this->code = trim($this->code);
        $this->validate(['code' => ['required', 'digits:6']], ['code.*' => __('agreements.portal.errors.code_format')]);

        $refused = SigningCodes::check(Agreements::KIND, $party->id, $party->otp ? (string) $party->otp : null, $party->otp_expires_at, $this->code);
        if ($refused) {
            if ($refused[0] === 'cancel') {
                Agreements::forgetCode($party);
            }
            $this->addError('code', $refused[0] === 'wrong'
                ? __('agreements.portal.errors.code_invalid')
                : __('agreements.portal.errors.too_many_attempts', ['wait' => $this->minutes($refused[1])]));

            return;
        }

        if (! Agreements::countersign($party, $this->code, request()->ip())) {
            $this->addError('code', __('agreements.portal.errors.closed'));

            return;
        }

        $this->code = '';
        $this->signature = '';
        $this->step = 'details';
        $this->done = true;
    }

    /** Back to the name and the signature: a new code, or a correction */
    public function startAgain(): void
    {
        $this->resetErrorBag();
        $this->step = 'details';
        $this->code = '';
    }

    /** The link still works and this party may still sign */
    private function open(LetterCounterparty $party, LetterDocument $document): bool
    {
        $refusal = Agreements::cannotSign($party, $document);
        if ($refusal !== null) {
            $this->addError('signedName', __('agreements.portal.errors.' . $refusal));

            return false;
        }

        return true;
    }

    private function minutes(int $minutes): string
    {
        return trans_choice('gateway.proposal.wait_minutes', $minutes, ['count' => $minutes]);
    }

    public function render()
    {
        $party = $this->party();
        $document = LetterDocument::with('signatures', 'counterparties')->findOrFail($party->document_id);
        $company = Letterhead::company($document->legal_entity_id) ?? Letterhead::defaultCompany() ?? [];
        $details = Letterhead::details($company);

        return view('livewire.portal.agreement-signing', [
            'document' => $document,
            'party' => $party,
            'details' => $details,
            'blocks' => LetterBody::blocks($document, $details),
            'pdfUrl' => route('portal.agreement.pdf', ['token' => $this->token]),
            'signedByUs' => $document->signatures->first(),
            'refusal' => Agreements::cannotSign($party, $document),
            'alreadySigned' => $party->hasSigned(),
        ])->title(__('agreements.portal.page_title', ['title' => $document->title ?: $document->reference]));
    }
}
