<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use App\Mail\SigningCode;
use App\Models\StatementOfWork;
use App\Support\MailLocale;
use App\Support\Opportunities\PortalLinks;
use App\Support\Opportunities\Proposals;
use App\Support\Opportunities\SowPdf;
use App\Support\Opportunities\Sows;
use App\Support\Signing\SigningCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One version of the statement of work, as the client reads and signs it (docs/OPPORTUNITIES.md decision 9), opened
 * from the signed link in the SOW email (PortalLinks::sow). They read it (with HQ's signature already on it), download
 * the PDF, and either sign it, or ask for changes. Signing: name, title, an email address that is an active contact of
 * the organisation, a drawn signature, then the one-time code emailed to that address (SigningCodes: 3 codes per
 * version and per address, 5 wrong codes per version, every 15 minutes). The right code signs it (Sows::sign: the
 * deposit invoice, the opportunity Won, the project draft). Only a version that was sent opens here.
 */
#[Layout('components.layouts.portal')]
class SowReview extends Component
{
    use WithGatewayLanguage;

    public const MAX_UNKNOWN = 5;

    #[Locked]
    public string $sowId;

    #[Locked]
    public ?string $contactId = null;

    /** '' | sign | changes */
    #[Locked]
    public string $mode = '';

    /** details (name, title, signature) | code (the one-time code) */
    #[Locked]
    public string $step = 'details';

    public string $name = '';

    public string $email = '';

    public string $jobTitle = '';

    public string $signature = '';

    public string $code = '';

    public string $changeNote = '';

    /** The address this page asked a code for: shown on the code step only to whoever asked, never on a reload */
    #[Locked]
    public string $codeSentTo = '';

    #[Locked]
    public int $linkExpiresAt = 0;

    public function mount(string $sow): void
    {
        $s = StatementOfWork::with('opportunity')->whereNotNull('sent_at')->find($sow);
        abort_unless($s && $s->opportunity, 404);
        $this->useGatewayLanguage($s->opportunity->language);
        $this->sowId = $s->id;
        // The route checked the signature; later requests (Livewire updates) check this instead
        $this->linkExpiresAt = (int) request()->query('expires') ?: (int) $s->validUntil()?->getTimestamp();

        $contact = request()->query('c');
        $contact = is_string($contact) && Str::isUuid($contact)
            ? DB::table('client_users')->where('id', $contact)->where('organization_id', $s->opportunity->organization_id)->whereNull('deleted_at')->first(['id', 'first_name', 'last_name', 'email', 'job_title'])
            : null;
        if ($contact) {
            $this->contactId = $contact->id;
            $this->name = trim($contact->first_name . ' ' . $contact->last_name);
            $this->email = (string) $contact->email;
            $this->jobTitle = (string) $contact->job_title;
        }
        // A code is on its way (the page was reloaded): ask for it
        if ($s->status === StatementOfWork::SENT && $s->auth_otp && $s->otp_expires_at?->isFuture()) {
            $this->mode = 'sign';
            $this->step = 'code';
        }
    }

    private function sow(): StatementOfWork
    {
        return StatementOfWork::with(['opportunity.organization', 'proposal'])->findOrFail($this->sowId);
    }

    public function choose(string $mode): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['sign', 'changes'], true) ? $mode : '';
        if ($this->mode === 'sign' && ! $this->contactMaySign($this->sow())) {
            $this->mode = '';
        }
        $this->step = 'details';
    }

    /** Whoever the link was sent to signs for the organisation (D5); a link without a contact can't tell yet */
    private function contactMaySign(StatementOfWork $s): bool
    {
        $email = $this->contactId ? DB::table('client_users')->where('id', $this->contactId)->value('email') : null;

        return ! $email || Proposals::isApprover($s->opportunity, (string) $email);
    }

    /** Name, title, address and signature checked, then a code is emailed to that address */
    public function requestCode(): void
    {
        $s = $this->sow();
        if (! $this->open($s)) {
            return;
        }
        $this->person([
            'jobTitle' => ['required', 'string', 'max:150'],
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:400000'],
        ]);
        if (! ($person = $this->known($s))) {
            return;
        }
        // Only the organisation's approvers sign (D5), and only they are sent a code; others read and ask for changes
        if (! Proposals::isApprover($s->opportunity, $person['email'])) {
            $error = Proposals::isActiveContact($s->opportunity, $person['email']) ? 'not_approver' : 'not_contact';
            $this->addError('email', __('sow.portal.errors.' . $error, ['company' => $s->opportunity->organization?->name]));

            return;
        }
        if ($refused = SigningCodes::claimSend('sow', $s->id, $person['email'])) {
            $this->addError('code', __('sow.portal.errors.' . $refused[0], ['wait' => $this->minutes($refused[1])]));

            return;
        }
        $code = SigningCodes::newCode();
        if (! Sows::holdSignature($s, ['name' => $this->name, 'title' => $this->jobTitle, 'email' => $person['email']], $this->signature, $code)) {
            $this->addError('mode', __('sow.portal.errors.closed'));

            return;
        }
        try {
            $locale = session()->has('locale') ? MailLocale::forVisitor() : MailLocale::forRecipient($s->opportunity->language);
            // The email says who the code signs for, so a code can't be used for someone else's details unnoticed
            Mail::to($person['email'])->locale($locale)->send(new SigningCode($code, SigningCode::SOW, $s->title ?: $s->reference, explode(' ', trim($this->name))[0] ?: null,
                signer: trim($this->name) . ', ' . trim($this->jobTitle)));
        } catch (\Throwable $e) {
            // Never log the code itself; the unsent code is cancelled, so it can't be asked for
            Log::error('SOW signing code email failed', ['sow_id' => $s->id, 'message' => $e->getMessage()]);
            StatementOfWork::whereKey($s->id)->update(['auth_otp' => null, 'otp_expires_at' => null]);
            $this->addError('email', __('sow.portal.errors.email_failed'));

            return;
        }
        $this->code = '';
        $this->codeSentTo = $person['email'];
        $this->step = 'code';
    }

    /** The code: the right one signs */
    public function verify(): void
    {
        $s = $this->sow();
        if (! $this->open($s)) {
            return;
        }
        $this->code = trim($this->code);
        $this->validate(['code' => ['required', 'digits:6']], ['code.*' => __('sow.portal.errors.code_format')]);
        $refused = SigningCodes::check('sow', $s->id, $s->auth_otp ? (string) $s->auth_otp : null, $s->otp_expires_at, $this->code);
        if ($refused) {
            if ($refused[0] === 'cancel') {
                StatementOfWork::whereKey($s->id)->update(['auth_otp' => null, 'otp_expires_at' => null]);
            }
            $this->addError('code', $refused[0] === 'wrong' ? __('sow.portal.errors.code_invalid') : __('sow.portal.errors.too_many_attempts', ['wait' => $this->minutes($refused[1])]));

            return;
        }
        // Checked again under the lock, with the signature it was issued for (a newer request replaces both)
        if (! Sows::sign($s, $this->code, request()->ip())) {
            $this->addError('code', __('sow.portal.errors.closed'));

            return;
        }
        $this->mode = '';
        $this->step = 'details';
        $this->code = '';
        $this->signature = '';
        session()->flash('success', __('sow.portal.done_signed'));
    }

    /** Back to the details: a new code, or another signer */
    public function startAgain(): void
    {
        $this->resetErrorBag();
        $this->step = 'details';
        $this->code = '';
    }

    public function requestChanges(): void
    {
        $s = $this->sow();
        if (! $this->open($s)) {
            return;
        }
        $this->changeNote = trim($this->changeNote);
        $this->person(['changeNote' => ['required', 'string', 'max:' . Sows::MAX_NOTE]]);
        if (! ($person = $this->known($s))) {
            return;
        }
        if (! Sows::requestChanges($s, ['name' => $this->name, 'email' => $person['email']], $this->changeNote)) {
            $this->addError('mode', __('sow.portal.errors.closed'));

            return;
        }
        $this->mode = '';
        $this->changeNote = '';
        session()->flash('success', __('sow.portal.done_changes'));
    }

    /** The link still works (its expiry kept from mount) and the version waits for the client's signature */
    private function open(StatementOfWork $s): bool
    {
        $this->resetErrorBag();
        if ($this->linkExpiresAt > 0 && now()->getTimestamp() > $this->linkExpiresAt) {
            $this->addError('mode', __('sow.portal.errors.link_expired'));

            return false;
        }
        if ($s->hasExpired()) {
            $this->addError('mode', __('sow.portal.errors.expired'));

            return false;
        }
        if (! Sows::awaitsSignature($s)) {
            $this->addError('mode', __('sow.portal.errors.closed'));

            return false;
        }

        return true;
    }

    private function person(array $rules): void
    {
        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $this->jobTitle = trim($this->jobTitle);
        $this->validate($rules + [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
        ], [
            'name.required' => __('proposals.portal.errors.name'),
            'name.max' => __('proposals.portal.errors.name_long'),
            'email.*' => __('proposals.portal.errors.email'),
            'jobTitle.required' => __('proposals.portal.errors.job_title'),
            'jobTitle.max' => __('proposals.portal.errors.job_title_long'),
            'signature.*' => __('sow.portal.errors.signature'),
            'changeNote.required' => __('sow.portal.errors.note'),
            'changeNote.max' => __('proposals.portal.errors.body_long', ['max' => Sows::MAX_NOTE]),
        ]);
    }

    public static function unknownKey(string $sowId, ?string $ip): string
    {
        return 'portal-sow-unknown:' . $sowId . ':' . sha1((string) $ip);
    }

    /** Someone HQ knows for this work; addresses it doesn't know count against this version and the visitor's IP (only misses) */
    private function known(StatementOfWork $s): ?array
    {
        $key = self::unknownKey($s->id, request()->ip());
        if (RateLimiter::tooManyAttempts($key, self::MAX_UNKNOWN)) {
            $this->addError('email', __('proposals.portal.errors.wait'));

            return null;
        }
        $person = Sows::knows($s, $this->email);
        if (! $person) {
            RateLimiter::hit($key, 900);
            $this->addError('email', __('proposals.portal.errors.unknown_email'));
        }

        return $person;
    }

    private function minutes(int $minutes): string
    {
        return trans_choice('gateway.proposal.wait_minutes', $minutes, ['count' => $minutes]);
    }

    public function render()
    {
        $s = $this->sow();
        $o = $s->opportunity;
        $newer = $s->status === StatementOfWork::SUPERSEDED
            ? StatementOfWork::where('opportunity_id', $o->id)->whereNotNull('sent_at')->where('version', '>', $s->version)->orderByDesc('version')->first()
            : null;

        return view('livewire.portal.sow-review', [
            's' => $s,
            'o' => $o,
            'party' => Sows::partyName($s),
            'fees' => SowPdf::fees($s),
            'pdfUrl' => PortalLinks::sowPdf($s),
            'newerUrl' => $newer ? PortalLinks::sow($newer, $this->contactId) : null,
            'meetingsUrl' => $o->stage !== 'lost' ? PortalLinks::meetings($o, $this->contactId) : null,
            'signing' => Sows::awaitsSignature($s),
            'maySign' => $this->contactMaySign($s),
        ])->title(__('sow.portal.page_title', ['title' => $s->title]));
    }
}
