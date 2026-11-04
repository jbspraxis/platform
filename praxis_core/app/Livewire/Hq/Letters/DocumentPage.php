<?php

namespace App\Livewire\Hq\Letters;

use App\Models\Letters\LetterDocument;
use App\Support\Ai\Ai;
use App\Support\Ai\AiUnavailable;
use App\Support\Campaigns\RichText;
use App\Support\Letters\Letterhead;
use App\Support\Letters\Letters;
use App\Support\Letters\LetterTypes;
use App\Support\Letters\Signatures;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * HQ › Documents › one document (docs/DOCUMENTS.md section 4 and 5). A draft is filled in here, the preview
 * beside it is the real PDF, and the same screen signs it and issues it.
 *
 * Signing needs three things at once: the letters.sign permission, a signature of your own, and a sign-in less
 * than ten minutes old (App\Support\SignIn\SignIn::isFresh, docs/SIGN_IN.md section 2), so a session left open
 * on someone's desk cannot put their signature on a document. Typing the password again opens that window.
 */
#[Layout('components.layouts.hq')]
class DocumentPage extends Component
{
    #[Locked]
    public string $documentId = '';

    public string $title = '';

    public string $documentDate = '';

    public string $addressedTo = '';

    public string $addressedToAddress = '';

    /** The template's own fields, by key */
    public array $fields = [];

    public string $body = '';

    public bool $showQr = false;

    public bool $copyPublic = false;

    /** The one sentence Claude drafts from */
    public string $brief = '';

    public bool $drafting = false;

    public bool $revoking = false;

    public string $revokeReason = '';

    /** Typing the password again when the sign-in is no longer fresh */
    public string $confirmPassword = '';

    public bool $needsPassword = false;

    #[Locked]
    public ?string $flash = null;

    /** Bumped after every save so the preview reloads the PDF rather than the browser's copy of it */
    #[Locked]
    public int $previewVersion = 0;

    public function mount(string $document): void
    {
        $this->documentId = $document;
        $record = $this->document();
        $this->fill([
            'title' => (string) $record->title,
            'documentDate' => $record->document_date?->toDateString() ?? Letters::today()->toDateString(),
            'addressedTo' => (string) $record->addressed_to,
            'addressedToAddress' => (string) $record->addressed_to_address,
            'fields' => $this->normalise((array) $record->fields, $record),
            'body' => (string) $record->body,
            'showQr' => (bool) $record->show_qr,
            'copyPublic' => (bool) $record->copy_public,
        ]);
    }

    private function document(): LetterDocument
    {
        return LetterDocument::with('signatures', 'replacedBy', 'replaces')->findOrFail($this->documentId);
    }

    /** Every field of the type is present, and a "people" field always has at least one empty row */
    private function normalise(array $fields, LetterDocument $record): array
    {
        $definitions = LetterTypes::fields($record->type, $record->subtype) + LetterTypes::common();
        foreach ($definitions as $key => $definition) {
            if (($definition['type'] ?? 'text') === 'people') {
                $rows = array_values(array_filter((array) ($fields[$key] ?? []), 'is_array'));
                $fields[$key] = $rows ?: [['name' => '', 'title' => '']];
            } else {
                $fields[$key] = (string) ($fields[$key] ?? '');
            }
        }

        return $fields;
    }

    // ------------------------------------------------------------------ writing the draft

    public function addPerson(string $key): void
    {
        $this->authorize('letters.create');
        $this->fields[$key][] = ['name' => '', 'title' => ''];
    }

    public function removePerson(string $key, int $index): void
    {
        $this->authorize('letters.create');
        unset($this->fields[$key][$index]);
        $this->fields[$key] = array_values($this->fields[$key]) ?: [['name' => '', 'title' => '']];
    }

    /** The rich-text editor sends the browser's HTML; the server cleans it before anything is kept */
    public function setBody(string $html): void
    {
        $this->body = RichText::clean($html);
    }

    public function save(): void
    {
        $this->authorize('letters.create');
        $record = $this->document();
        abort_unless($record->isDraft(), 422, 'An issued document cannot be changed.');

        // A draft may be saved half-written; the title is what the issued document is called, so it is
        // required when it goes out, not before (issue() checks it)
        $this->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'documentDate' => ['required', 'date'],
            'addressedTo' => ['nullable', 'string', 'max:200'],
            'addressedToAddress' => ['nullable', 'string', 'max:500'],
        ], [], ['documentDate' => 'date', 'addressedTo' => 'addressed to']);

        $record->forceFill([
            'title' => trim($this->title),
            'document_date' => $this->documentDate,
            'addressed_to' => trim($this->addressedTo) ?: null,
            'addressed_to_address' => trim($this->addressedToAddress) ?: null,
            'fields' => $this->cleanFields($record),
            'body' => LetterTypes::isFreeBody($record->type) ? RichText::clean($this->body) : null,
            'show_qr' => $this->showQr,
            'copy_public' => Auth::user()->can('letters.manage') ? $this->copyPublic : $record->copy_public,
        ])->save();

        $this->previewVersion++;
        $this->flash = 'Saved.';
    }

    private function cleanFields(LetterDocument $record): array
    {
        $definitions = LetterTypes::fields($record->type, $record->subtype) + LetterTypes::common();
        $out = [];
        foreach ($definitions as $key => $definition) {
            if (($definition['type'] ?? 'text') === 'people') {
                $rows = [];
                foreach ((array) ($this->fields[$key] ?? []) as $row) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name !== '') {
                        $rows[] = ['name' => $name, 'title' => trim((string) ($row['title'] ?? ''))];
                    }
                }
                $out[$key] = $rows;
            } else {
                $out[$key] = trim((string) ($this->fields[$key] ?? ''));
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Claude's draft

    public function draft(): void
    {
        $this->authorize('letters.create');
        $record = $this->document();
        abort_unless($record->isDraft() && LetterTypes::isFreeBody($record->type), 422);

        $brief = trim($this->brief);
        if ($brief === '') {
            $this->addError('brief', 'Say in one sentence what the letter is for.');

            return;
        }

        $key = 'letter-draft:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('brief', 'That is a lot of drafts. Try again in a few minutes.');

            return;
        }
        RateLimiter::hit($key, 600);

        $company = Letterhead::company($record->legal_entity_id) ?? Letterhead::defaultCompany() ?? [];
        $details = Letterhead::details($company);
        $this->drafting = true;

        try {
            $run = Ai::now('letter_draft', $record, [
                'instructions' => $brief,
                'material' => "THE COMPANY WRITING THE LETTER: {$details['name']}, {$details['footer_legal']}."
                    . "\nWHO IT IS ADDRESSED TO: " . (trim($this->addressedTo) !== '' ? trim($this->addressedTo) : 'not said')
                    . "\nTHE DATE ON THE LETTER: " . $this->documentDate,
            ]);
        } catch (AiUnavailable $e) {
            $this->drafting = false;
            $this->addError('brief', 'Claude is not available at the moment. Write the letter yourself, or try again later.');

            return;
        } catch (Throwable $e) {
            $this->drafting = false;
            $this->addError('brief', 'The draft could not be written. Write the letter yourself, or try again later.');

            return;
        }

        $this->drafting = false;
        $output = (array) $run->output;
        if ($run->status !== 'done' || ! isset($output['body'])) {
            $this->addError('brief', 'The draft could not be written. Write the letter yourself, or try again later.');

            return;
        }

        if (trim((string) ($output['title'] ?? '')) !== '' && trim($this->title) === '') {
            $this->title = trim((string) $output['title']);
        }
        $this->body = RichText::fromPlain((string) $output['body']);
        $record->forceFill(['ai_run_id' => $run->id])->save();
        $this->flash = 'Claude wrote a draft. Read it, change what you want, then save.';
    }

    // ------------------------------------------------------------------ signing

    public function sign(): void
    {
        $this->authorize('letters.sign');
        $user = Auth::user();

        if (! Signatures::forUser($user)) {
            $this->flash = null;
            $this->addError('signature', 'Save your own signature first, on My signature.');

            return;
        }
        if (! SignIn::isFresh()) {
            $this->needsPassword = true;

            return;
        }

        Letters::sign($this->document(), $user);
        $this->previewVersion++;
        $this->flash = 'Signed.';
    }

    public function removeSignature(): void
    {
        $this->authorize('letters.sign');
        Letters::unsign($this->document(), Auth::user());
        $this->previewVersion++;
        $this->flash = 'Your signature is off this document.';
    }

    /** The same password check My profile uses before a passkey or a PIN is set up */
    public function confirmIdentity(): void
    {
        $user = Auth::user();
        $key = 'letters-confirm:' . $user->id;
        $this->resetErrorBag();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('confirmPassword', __('sign_in.pin.password_too_many', ['minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60))]));

            return;
        }
        if ($this->confirmPassword === '') {
            $this->addError('confirmPassword', __('sign_in.pin.password_required'));

            return;
        }
        if (! Hash::check($this->confirmPassword, (string) $user->getAuthPassword())) {
            RateLimiter::hit($key, 600);
            $this->confirmPassword = '';
            $this->addError('confirmPassword', __('sign_in.pin.password_wrong'));

            return;
        }

        RateLimiter::clear($key);
        SignIn::markFresh();
        $this->confirmPassword = '';
        $this->needsPassword = false;
        $this->sign();
    }

    public function cancelConfirm(): void
    {
        $this->needsPassword = false;
        $this->confirmPassword = '';
        $this->resetErrorBag();
    }

    // ------------------------------------------------------------------ issuing and what comes after

    public function issue(): void
    {
        $this->authorize('letters.manage');
        // Whatever is on screen goes out, so it is saved first — but only by someone who may write it.
        // Someone with letters.manage alone issues what is already stored.
        if (Auth::user()->can('letters.create')) {
            $this->save();
        }
        $record = $this->document();

        if (trim((string) $record->title) === '') {
            $this->addError('title', 'Give the document a title before issuing it.');

            return;
        }

        Letters::issue($record, Auth::user());
        Letters::markReplaced($record->fresh());
        $this->previewVersion++;
        $this->flash = 'Issued as ' . $record->reference . '. The PDF and its fingerprint are kept as they are from now on.';
    }

    public function revoke(): void
    {
        $this->authorize('letters.manage');
        $reason = trim($this->revokeReason);
        if ($reason === '') {
            $this->addError('revokeReason', 'Say why it is being revoked. Anyone who scans it will see this date.');

            return;
        }

        Letters::revoke($this->document(), $reason, Auth::user());
        $this->revoking = false;
        $this->revokeReason = '';
        $this->flash = 'Revoked. Anyone who checks it now sees that it no longer stands.';
    }

    /** Keeps the issued PDF with the company's other papers (Master data › Company documents) */
    public function fileAway(): void
    {
        $this->authorize('mdm.create');
        try {
            Letters::fileInCompanyDocuments($this->document(), Auth::user());
        } catch (Throwable $e) {
            $this->addError('filing', $e->getMessage());

            return;
        }
        $this->flash = 'Filed in Company documents, under Master data.';
    }

    public function replace()
    {
        $this->authorize('letters.manage');
        $copy = Letters::replace($this->document(), Auth::user());

        return $this->redirectRoute('hq.letters.document', ['document' => $copy->id], navigate: true);
    }

    public function render()
    {
        $record = $this->document();
        $company = Letterhead::company($record->legal_entity_id) ?? Letterhead::defaultCompany() ?? [];
        $user = Auth::user();

        return view('livewire.hq.letters.document', [
            'document' => $record,
            'details' => Letterhead::details($company),
            'definitions' => LetterTypes::fields($record->type, $record->subtype),
            'common' => LetterTypes::common(),
            'freeBody' => LetterTypes::isFreeBody($record->type),
            'kind' => LetterTypes::label($record->type, $record->subtype),
            'mySignature' => Signatures::forUser($user),
            'signedByMe' => $record->signatures->firstWhere('user_id', $user->id),
            'canEdit' => $record->isDraft() && $user->can('letters.create'),
            'canSign' => $record->isDraft() && $user->can('letters.sign'),
            'canManage' => $user->can('letters.manage'),
            'canFile' => $user->can('mdm.create'),
        ])->title($record->reference);
    }
}
