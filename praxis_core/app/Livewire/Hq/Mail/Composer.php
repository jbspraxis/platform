<?php

namespace App\Livewire\Hq\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailOutbox;
use App\Support\Mail\Compose\HtmlCleaner;
use App\Support\Mail\Compose\Outbox;
use App\Support\Mail\Compose\OutboxSender;
use App\Support\Mail\Compose\Recipients;
use App\Support\Mail\Compose\Signatures;
use App\Support\Mail\Compose\WriteWithAi;
use App\Support\Mail\MailAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The composer (docs/MAIL.md, PR 3): new mail, reply, reply all, forward and drafts. Mounted once on a Mail page;
 * opens on the browser event `mail-compose` {mode, accountId, messageId?, outboxId?, to?}. A dialog on desktop,
 * full screen on phones.
 *
 * Everything is saved as the person types (mail_outbox, Outbox), with a copy in the server's Drafts when the
 * composer closes and every minute while typing. Send waits 10 seconds (Undo), then this component sends it
 * (or mail:send-due within a minute, if the page has gone). Signature, quote and From line are added at send.
 * Raises `mail-sent` {accountId, threadId, outboxId} and, on replies, `mail-composing` {threadId, state}.
 */
class Composer extends Component
{
    use WithFileUploads;

    public bool $open = false;

    #[Locked]
    public ?string $outboxId = null;
    #[Locked]
    public string $kind = 'new';
    #[Locked]
    public ?int $threadId = null;

    public string $accountId = '';
    /** @var list<array{name: ?string, email: string}> */
    public array $to = [];
    public array $cc = [];
    public array $bcc = [];
    public bool $showCc = false;
    public string $subject = '';
    public string $signature = 'full';
    public bool $quote = false;
    public bool $showQuote = false;

    /** Files being uploaded (Livewire's temporary uploads; moved into the draft as they arrive) */
    public $uploads = [];

    public bool $scheduleOpen = false;
    public string $scheduleAt = '';

    public bool $aiOpen = false;
    public string $aiPrompt = '';
    #[Locked]
    public ?string $aiDraft = null;

    #[Locked]
    public ?string $savedAt = null;
    #[Locked]
    public ?string $error = null;
    /** Addresses that could not be read, by field */
    #[Locked]
    public array $invalid = [];

    /** The message bar under the page: {type: undo|scheduled|sent|retry|failed|info, outboxId?, text, seconds?} */
    #[Locked]
    public ?array $toast = null;

    #[Locked]
    public int $serverDraftAt = 0;

    #[On('mail-compose')]
    public function compose(string $mode = 'new', ?string $accountId = null, ?int $messageId = null, ?string $outboxId = null, ?string $to = null): void
    {
        $user = Auth::user();
        abort_unless(MailAccess::canUse($user), 403);
        if ($this->open && $this->outboxId) {
            $this->persist(); // keep what was being written; it stays a draft
            $this->leaveThread();
        }
        $this->resetComposer();

        try {
            $outbox = match ($mode) {
                'draft' => $outboxId
                    ? Outbox::make()->open($user, MailOutbox::findOrFail($outboxId))
                    : Outbox::make()->fromServerDraft($user, MailMessage::findOrFail((int) $messageId)),
                'reply', 'reply_all', 'forward' => Outbox::make()->start($user, $mode, null, MailMessage::findOrFail((int) $messageId)),
                default => Outbox::make()->start($user, 'new', $this->sendableAccount($accountId), null, $to),
            };
        } catch (ValidationException $e) {
            $this->toast = ['type' => 'info', 'text' => collect($e->errors())->flatten()->first()];

            return;
        } catch (HttpExceptionInterface $e) {
            $this->toast = ['type' => 'info', 'text' => $e->getStatusCode() === 403
                ? 'You can read this mailbox but not send from it. Ask one of its managers for Read and send.'
                : 'This message is no longer here. Refresh the page and try again.'];

            return;
        } catch (Throwable $e) {
            report($e);
            $this->toast = ['type' => 'info', 'text' => 'The composer couldn’t open this message. Try again in a minute.'];

            return;
        }

        $this->load($outbox);
    }

    /** Recipients changed in the browser (chips). @return list<string> what isn't an address */
    public function setRecipients(string $field, array $list): array
    {
        abort_unless(in_array($field, ['to', 'cc', 'bcc'], true), 422);
        $parsed = Recipients::parse(array_map(fn ($a) => is_array($a) ? $a : ['email' => (string) $a], $list));
        $this->{$field} = $parsed['valid'];
        $this->invalid[$field] = $parsed['invalid'];
        $this->persist();

        return $parsed['invalid'];
    }

    /** Suggestions while typing an address */
    #[Renderless]
    public function suggest(string $query): array
    {
        return Recipients::suggest(Auth::user(), $query);
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['subject', 'signature', 'quote', 'accountId'], true)) {
            $this->persist();
        }
    }

    /** The editor's text, sent by the browser shortly after the person stops typing */
    public function autosave(string $html): void
    {
        $this->persist($html);
        if ($this->outboxId && time() - $this->serverDraftAt >= (int) config('mail_compose.server_draft_seconds', 60)) {
            $this->saveServerDraft();
        }
    }

    public function updatedUploads(): void
    {
        $outbox = $this->outbox();
        if (! $outbox) {
            return;
        }
        $this->validate(['uploads.*' => ['file', 'max:12288']], ['uploads.*.max' => 'Each file can be up to 12 MB. Share a link to larger files instead.']);
        try {
            foreach ((array) $this->uploads as $file) {
                Outbox::make()->addUpload(Auth::user(), $outbox, $file);
                $outbox->refresh();
            }
        } catch (ValidationException $e) {
            $this->addError('uploads', collect($e->errors())->flatten()->first());
        } finally {
            foreach ((array) $this->uploads as $file) {
                rescue(fn () => $file->delete(), null, false);
            }
            $this->uploads = [];
        }
        $this->savedAt = now()->toIso8601String();
    }

    public function removeAttachment(string $key): void
    {
        if ($outbox = $this->outbox()) {
            Outbox::make()->removeAttachment(Auth::user(), $outbox, $key);
        }
    }

    /** Send now: it waits 10 seconds for Undo */
    public function send(?string $html = null): void
    {
        $outbox = $this->persist($html);
        if (! $outbox) {
            return;
        }
        try {
            Outbox::make()->queue(Auth::user(), $outbox);
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first();

            return;
        }
        $this->finish($outbox, ['type' => 'undo', 'outboxId' => $outbox->id, 'text' => 'Sending…', 'seconds' => (int) config('mail_compose.undo_seconds', 10)]);
    }

    /** Schedule: at the time picked, or a quick choice (tomorrow, monday) at 08:00 */
    public function schedule(?string $html = null, ?string $quick = null): void
    {
        $tz = (string) config('mail_compose.timezone', 'Africa/Lagos');
        $at = match ($quick) {
            'tomorrow' => CarbonImmutable::now($tz)->addDay()->setTime(8, 0),
            'monday' => CarbonImmutable::now($tz)->next(CarbonImmutable::MONDAY)->setTime(8, 0),
            default => rescue(fn () => $this->scheduleAt !== '' ? CarbonImmutable::parse($this->scheduleAt, $tz) : null, null, false),
        };
        if (! $at) {
            $this->addError('scheduleAt', 'Choose a day and time.');

            return;
        }
        $outbox = $this->persist($html);
        if (! $outbox) {
            return;
        }
        try {
            Outbox::make()->queue(Auth::user(), $outbox, $at->utc());
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['send_at'])) {
                $this->addError('scheduleAt', $errors['send_at'][0]);
            } else {
                $this->error = collect($errors)->flatten()->first();
            }

            return;
        }
        $this->finish($outbox, ['type' => 'scheduled', 'outboxId' => $outbox->id, 'text' => 'Scheduled for ' . $at->timezone($tz)->format('D j M, H:i') . '.']);
    }

    /** End of the undo window (the browser calls it): send now if it is still waiting */
    public function deliver(string $outboxId): void
    {
        $outbox = MailOutbox::find($outboxId);
        if (! $outbox || $outbox->user_id !== Auth::id()) {
            $this->toast = null;

            return;
        }
        $outbox = OutboxSender::make()->send($outbox, early: 2);
        $this->toast = match ($outbox->status) {
            'sent' => ['type' => 'sent', 'outboxId' => $outbox->id, 'text' => 'Sent.'],
            'failed' => ['type' => 'failed', 'outboxId' => $outbox->id, 'text' => 'Not sent: ' . $outbox->last_error],
            'queued' => ['type' => 'retry', 'outboxId' => $outbox->id, 'text' => 'Not sent yet: ' . ($outbox->last_error ?: 'the mail server is busy.') . ' HQ will try again by itself.'],
            default => null, // already on its way (mail:send-due took it)
        };
        $this->dispatch('mail-sent', accountId: $outbox->account_id, threadId: $outbox->thread_id, outboxId: $outbox->id);
    }

    /** Undo send, or cancel a schedule: back in the composer */
    public function undo(string $outboxId): void
    {
        $outbox = MailOutbox::find($outboxId);
        if (! $outbox || $outbox->user_id !== Auth::id()) {
            return;
        }
        if (! Outbox::make()->undo(Auth::user(), $outbox)) {
            $this->toast = ['type' => 'info', 'text' => 'Too late to undo: the message has gone.'];

            return;
        }
        $this->toast = null;
        $this->resetComposer();
        $this->load($outbox->fresh());
        $this->dispatch('mail-sent', accountId: $outbox->account_id, threadId: $outbox->thread_id, outboxId: $outbox->id);
    }

    /** Reopen a message that didn't go */
    public function reopen(string $outboxId): void
    {
        $this->compose('draft', outboxId: $outboxId);
    }

    /** Close: the draft is kept (in HQ and in the server's Drafts); an untouched one is thrown away */
    public function close(?string $html = null): void
    {
        $outbox = $this->persist($html);
        if ($outbox && ! Outbox::make()->worthKeeping($outbox)) {
            Outbox::make()->discard(Auth::user(), $outbox);
            $this->finish(null, null);

            return;
        }
        if ($outbox) {
            $this->saveServerDraft();
        }
        $this->finish(null, $outbox ? ['type' => 'info', 'outboxId' => $outbox->id, 'text' => 'Draft saved.'] : null);
    }

    public function discard(): void
    {
        if ($outbox = $this->outbox()) {
            Outbox::make()->discard(Auth::user(), $outbox);
        }
        $this->finish(null, ['type' => 'info', 'text' => 'Draft discarded.']);
    }

    public function toggleAi(): void
    {
        $this->aiOpen = ! $this->aiOpen;
        $this->aiDraft = null;
        $this->resetErrorBag('aiPrompt');
    }

    /** Write with AI (and Try again): a draft to read, never sent by itself */
    public function writeWithAi(?string $html = null): void
    {
        $outbox = $this->persist($html);
        if (! $outbox) {
            return;
        }
        $this->resetErrorBag('aiPrompt');
        try {
            $this->aiDraft = app(WriteWithAi::class)->draft(Auth::user(), $outbox, $this->aiPrompt);
        } catch (ValidationException $e) {
            $this->addError('aiPrompt', collect($e->errors())->flatten()->first());
        }
    }

    public function useAiDraft(): void
    {
        if ($this->aiDraft === null) {
            return;
        }
        $html = HtmlCleaner::fromText($this->aiDraft);
        $this->persist($html);
        $this->dispatch('mail-editor-load', html: $html);
        $this->aiDraft = null;
        $this->aiOpen = false;
        $this->aiPrompt = '';
    }

    public function dismissToast(): void
    {
        $this->toast = null;
    }

    public function render()
    {
        $user = Auth::user();
        $outbox = $this->open ? $this->outbox() : null;
        $account = $outbox?->account;

        return view('livewire.hq.mail.composer', [
            'accounts' => $this->open ? MailAccess::accounts($user, 'send') : collect(),
            'account' => $account,
            'attachments' => $outbox->payload['attachments'] ?? [],
            'quoteHtml' => $outbox->payload['quote_html'] ?? null,
            'signatureHtml' => $account && $this->signature !== 'none' ? Signatures::html($user, $account, $this->signature) : '',
            'editorHtml' => $outbox->payload['html'] ?? '',
            'title' => ['reply' => 'Reply', 'reply_all' => 'Reply all', 'forward' => 'Forward'][$this->kind] ?? 'New message',
            'tz' => (string) config('mail_compose.timezone', 'Africa/Lagos'),
            'maxMb' => (int) config('mail_compose.max_attachments_mb', 20),
        ]);
    }

    // ------------------------------------------------------------------

    private function load(MailOutbox $outbox): void
    {
        $p = $outbox->payload;
        $this->outboxId = $outbox->id;
        $this->kind = $outbox->kind;
        $this->threadId = $outbox->thread_id;
        $this->accountId = $outbox->account_id;
        $this->to = $p['to'] ?? [];
        $this->cc = $p['cc'] ?? [];
        $this->bcc = $p['bcc'] ?? [];
        $this->showCc = ! empty($this->cc) || ! empty($this->bcc);
        $this->subject = (string) ($p['subject'] ?? '');
        $this->signature = $p['signature'] ?? 'full';
        $this->quote = (bool) ($p['quote'] ?? false);
        $this->savedAt = ! empty($p['touched']) ? $outbox->updated_at?->toIso8601String() : null;
        $this->serverDraftAt = time();
        $this->open = true;
        $this->dispatch('mail-editor-load', html: (string) ($p['html'] ?? ''));
        if ($this->threadId && in_array($this->kind, ['reply', 'reply_all'], true)) {
            $this->dispatch('mail-composing', threadId: $this->threadId, state: 'replying');
        }
    }

    /** Save the fields into the draft. @return ?MailOutbox the draft, or null when it can't be changed now */
    private function persist(?string $html = null): ?MailOutbox
    {
        $outbox = $this->outbox();
        if (! $outbox || $outbox->status !== 'draft') {
            return null;
        }
        $fields = [
            'to' => $this->to, 'cc' => $this->cc, 'bcc' => $this->bcc, 'subject' => $this->subject,
            'signature' => $this->signature, 'quote' => $this->quote,
        ];
        if ($html !== null) {
            $fields['html'] = $html;
        }
        if ($this->accountId !== '' && $this->accountId !== $outbox->account_id) {
            $fields['account_id'] = $this->accountId;
        }
        try {
            Outbox::make()->save(Auth::user(), $outbox, $fields);
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first();

            return null;
        }
        $this->error = null;
        $this->savedAt = now()->toIso8601String();
        $outbox->refresh();
        $this->accountId = $outbox->account_id;

        return $outbox;
    }

    private function saveServerDraft(): void
    {
        if ($outbox = $this->outbox()) {
            $this->serverDraftAt = time();
            rescue(fn () => Outbox::make()->saveServerDraft(Auth::user(), $outbox), null, false);
        }
    }

    private function outbox(): ?MailOutbox
    {
        if (! $this->outboxId) {
            return null;
        }
        $outbox = MailOutbox::with('account')->find($this->outboxId);

        return $outbox && $outbox->user_id === Auth::id() ? $outbox : null;
    }

    private function sendableAccount(?string $accountId): ?MailAccount
    {
        $account = $accountId ? MailAccount::find($accountId) : null;

        return $account && MailAccess::can(Auth::user(), $account, 'send') ? $account : null;
    }

    private function finish(?MailOutbox $sent, ?array $toast): void
    {
        $this->leaveThread();
        if ($sent) {
            $this->dispatch('mail-sent', accountId: $sent->account_id, threadId: $sent->thread_id, outboxId: $sent->id);
        }
        $this->resetComposer();
        $this->toast = $toast;
    }

    private function leaveThread(): void
    {
        if ($this->threadId && in_array($this->kind, ['reply', 'reply_all'], true)) {
            $this->dispatch('mail-composing', threadId: $this->threadId, state: 'stopped');
        }
    }

    private function resetComposer(): void
    {
        $this->reset(['open', 'outboxId', 'kind', 'threadId', 'accountId', 'to', 'cc', 'bcc', 'showCc', 'subject', 'signature', 'quote', 'showQuote',
            'uploads', 'scheduleOpen', 'scheduleAt', 'aiOpen', 'aiPrompt', 'aiDraft', 'savedAt', 'error', 'invalid']);
        $this->resetErrorBag();
    }
}
