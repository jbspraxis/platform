<?php

namespace App\Support\Mail\Compose;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailOutbox;
use App\Models\User;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailFetch;
use App\Support\Mail\MailServer;
use App\Support\Mail\MailTransport;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Mail being written (mail_outbox; payload keys in docs/MAIL.md section 12). A row belongs to the person who
 * writes it: only they open, change, send or discard it, and only while they can send from its mailbox.
 *
 *   draft      being written; HQ saves it as they type and keeps a copy in the server's Drafts folder
 *   queued     waiting to go: send_at is now + 10 s (undo send) or the time chosen (scheduled)
 *   sending    taken by OutboxSender; sent / failed once SMTP answers
 *
 * The Message-ID is chosen when the row is made, so the server's Drafts copy, the message sent and its copy in
 * Sent carry the same one, and sync credits the sender (mail_messages.sent_by) from message_id_hash.
 */
final class Outbox
{
    public const KINDS = ['new', 'reply', 'reply_all', 'forward'];

    public function __construct(private readonly MailServer $server)
    {
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /** A new draft: new mail (from $account), or a reply, reply all or forward of $original */
    public function start(User $user, string $kind, ?MailAccount $account = null, ?MailMessage $original = null, ?string $to = null): MailOutbox
    {
        abort_unless(in_array($kind, self::KINDS, true), 422);
        abort_if($kind !== 'new' && ! $original, 422);
        if ($original) {
            abort_unless(MailAccess::canMessage($user, $original, 'read'), 403);
            $account = $original->account;

            // Carry on with an unfinished draft of the same answer rather than start another
            $open = MailOutbox::where('user_id', $user->id)->where('reply_to_message_id', $original->id)->where('kind', $kind)
                ->where('status', 'draft')->latest('updated_at')->first();
            if ($open && ! empty($open->payload['touched'])) {
                return $open;
            }
        }
        $account ??= MailAccess::accounts($user, 'send')->first();
        abort_unless($account && MailAccess::can($user, $account, 'send'), 403);

        $payload = self::emptyPayload($kind);
        if ($to !== null && $to !== '') {
            $payload['to'] = Recipients::parse($to)['valid'];
        }
        if ($original) {
            $payload = $this->answer($user, $kind, $account, $original) + $payload;
        }

        return $this->create($user, $account, $kind, $payload, $original?->thread_id, $original?->id);
    }

    /** Open one of the person's own drafts (or a scheduled or failed message, which goes back to draft) */
    public function open(User $user, MailOutbox $outbox): MailOutbox
    {
        $this->own($user, $outbox, ['draft', 'queued', 'failed']);
        if ($outbox->status !== 'draft') {
            $payload = $outbox->payload;
            unset($payload['scheduled']);
            $outbox->forceFill(['status' => 'draft', 'send_at' => null, 'payload' => $payload])->save();
        }

        return $outbox;
    }

    /** A message in the server's Drafts: HQ's own draft row when it wrote it, else a new draft made from it */
    public function fromServerDraft(User $user, MailMessage $draft): MailOutbox
    {
        abort_unless(MailAccess::canMessage($user, $draft, 'read'), 403);
        $account = $draft->account;
        abort_unless(MailAccess::can($user, $account, 'send'), 403);

        if ($draft->message_id_hash) {
            $mine = MailOutbox::where('user_id', $user->id)->where('account_id', $account->id)->where('message_id_hash', $draft->message_id_hash)
                ->whereIn('status', ['draft', 'queued', 'failed'])->first();
            if ($mine) {
                return $this->open($user, $mine);
            }
        }

        $body = rescue(fn () => MailFetch::make()->body($user, $draft), ['text' => (string) $draft->snippet, 'html' => null], false);
        $original = $draft->in_reply_to
            ? MailMessage::where('account_id', $account->id)->where('message_id_hash', MailMessage::hashId($draft->in_reply_to))->where('id', '!=', $draft->id)->first()
            : null;

        $payload = self::emptyPayload($draft->in_reply_to ? 'reply' : 'new');
        $payload['signature'] = 'none'; // a draft written elsewhere may already hold its signature
        $payload['to'] = Recipients::parse($draft->to ?? [])['valid'];
        $payload['cc'] = Recipients::parse($draft->cc ?? [])['valid'];
        $payload['bcc'] = Recipients::parse($draft->bcc ?? [])['valid'];
        $payload['subject'] = (string) $draft->subject;
        $payload['html'] = ! empty($body['html']) ? HtmlCleaner::clean($body['html']) : HtmlCleaner::fromText($body['text']);
        $payload['in_reply_to'] = $draft->in_reply_to;
        $payload['references'] = $draft->references;
        $payload['attachments'] = $draft->attachments()->where('is_inline', false)->get()
            ->map(fn ($a) => ['key' => 'o' . $a->id, 'name' => $a->filename, 'mime' => $a->mime, 'size' => (int) $a->size, 'source' => 'original', 'attachment_id' => $a->id])->all();
        $payload['server_draft'] = ['path' => $draft->folder->path, 'uid' => (int) $draft->uid];
        $payload['touched'] = true;

        return $this->create($user, $account, $original ? 'reply' : 'new', $payload, $draft->thread_id, $original?->id);
    }

    /**
     * Save what the person wrote (as they type). $fields: account_id, to, cc, bcc (lists of addresses or
     * "Name <a@b>" strings), subject, html, signature (full|slim|none), quote (bool). Fields left out keep
     * their value. @return list<string> what could not be read as an address (not kept)
     */
    public function save(User $user, MailOutbox $outbox, array $fields): array
    {
        $this->own($user, $outbox, ['draft']);
        $p = $outbox->payload;
        $invalid = [];

        if (isset($fields['account_id']) && $fields['account_id'] !== $outbox->account_id) {
            $account = MailAccount::find($fields['account_id']);
            abort_unless($account && MailAccess::can($user, $account, 'send'), 403);
            $this->removeServerDraft($outbox);
            $p = $outbox->fresh()->payload;
            $messageId = MailTransport::newMessageId($account);
            $outbox->forceFill(['account_id' => $account->id, 'message_id' => $messageId, 'message_id_hash' => MailMessage::hashId($messageId)]);
            $outbox->unsetRelation('account');
        }
        foreach (['to', 'cc', 'bcc'] as $field) {
            if (array_key_exists($field, $fields)) {
                $parsed = Recipients::parse((array) $fields[$field]);
                $p[$field] = $parsed['valid'];
                $invalid = [...$invalid, ...$parsed['invalid']];
            }
        }
        if (array_key_exists('subject', $fields)) {
            $p['subject'] = mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string) $fields['subject'])), 0, 250);
        }
        if (array_key_exists('html', $fields)) {
            $p['html'] = HtmlCleaner::clean(mb_substr((string) $fields['html'], 0, 2_000_000));
        }
        if (isset($fields['signature']) && array_key_exists($fields['signature'], Signatures::STYLES)) {
            $p['signature'] = $fields['signature'];
        }
        if (array_key_exists('quote', $fields)) {
            $p['quote'] = (bool) $fields['quote'] && ! empty($p['quote_html']);
        }
        $p['touched'] = true;
        $outbox->payload = $p;
        $outbox->save();

        return $invalid;
    }

    /**
     * Send it: in 10 seconds (the undo window), or at $sendAt (scheduled). Throws ValidationException when it
     * can't go as it is (no recipient, too many, attachments too large, a time in the past).
     */
    public function queue(User $user, MailOutbox $outbox, ?CarbonInterface $sendAt = null): MailOutbox
    {
        $this->own($user, $outbox, ['draft']);
        $p = $outbox->payload;
        $count = count($p['to'] ?? []) + count($p['cc'] ?? []) + count($p['bcc'] ?? []);
        if ($count === 0) {
            throw ValidationException::withMessages(['to' => 'Add at least one recipient.']);
        }
        if ($count > (int) config('mail_compose.max_recipients', 100)) {
            throw ValidationException::withMessages(['to' => 'One message can go to at most ' . config('mail_compose.max_recipients', 100) . ' people. Split the list into several messages.']);
        }
        if (self::attachmentBytes($p) > self::maxBytes()) {
            throw ValidationException::withMessages(['attachments' => 'The attachments come to more than ' . config('mail_compose.max_attachments_mb', 20) . ' MB. Remove some, or share a link instead.']);
        }
        if ($sendAt !== null && ($sendAt->lt(now()->addMinute()) || $sendAt->gt(now()->addYear()))) {
            throw ValidationException::withMessages(['send_at' => 'Choose a time at least a minute from now and within a year.']);
        }

        $p['scheduled'] = $sendAt !== null;
        $outbox->forceFill([
            'status' => 'queued',
            'payload' => $p,
            'send_at' => $sendAt ?? now()->addSeconds((int) config('mail_compose.undo_seconds', 10)),
            'attempts' => 0,
            'last_error' => null,
        ])->save();

        return $outbox;
    }

    /** Undo send, or cancel a scheduled message: back to a draft, if it hasn't started going. @return bool done */
    public function undo(User $user, MailOutbox $outbox): bool
    {
        $this->own($user, $outbox, ['queued', 'sending', 'sent', 'draft', 'failed']);
        $p = $outbox->payload;
        unset($p['scheduled']);
        $done = MailOutbox::where('id', $outbox->id)->where('status', 'queued')
            ->update(['status' => 'draft', 'send_at' => null, 'payload' => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now()]);
        $outbox->refresh();

        return $done > 0;
    }

    /** Throw the draft away (HQ, its files, and its copy in the server's Drafts) */
    public function discard(User $user, MailOutbox $outbox): void
    {
        $this->own($user, $outbox, ['draft', 'failed', 'queued']);
        if ($outbox->status === 'queued' && ! $this->undo($user, $outbox)) {
            return; // it has started going
        }
        $this->removeServerDraft($outbox);
        $this->removeFiles($outbox);
        $outbox->delete();
    }

    /** Attach an uploaded file. Throws ValidationException when the files would be too large. */
    public function addUpload(User $user, MailOutbox $outbox, UploadedFile $file): array
    {
        $this->own($user, $outbox, ['draft']);
        $p = $outbox->payload;
        $size = (int) $file->getSize();
        if (self::attachmentBytes($p) + $size > self::maxBytes()) {
            throw ValidationException::withMessages(['uploads' => 'The attachments would come to more than ' . config('mail_compose.max_attachments_mb', 20) . ' MB. Share a link to large files instead.']);
        }
        $key = 'u' . Str::lower(Str::random(10));
        $name = self::fileName($file->getClientOriginalName());
        $path = $file->storeAs(config('mail_compose.upload_dir', 'mail-outbox') . '/' . $outbox->id, $key . '-' . Str::slug(pathinfo($name, PATHINFO_FILENAME)) . '.' . (Str::lower($file->getClientOriginalExtension()) ?: 'bin'), 'local');
        $attachment = ['key' => $key, 'name' => $name, 'mime' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $size, 'source' => 'upload', 'path' => $path];
        $p['attachments'][] = $attachment;
        $p['touched'] = true;
        $outbox->forceFill(['payload' => $p])->save();

        return $attachment;
    }

    public function removeAttachment(User $user, MailOutbox $outbox, string $key): void
    {
        $this->own($user, $outbox, ['draft']);
        $p = $outbox->payload;
        foreach ($p['attachments'] ?? [] as $i => $a) {
            if ($a['key'] === $key) {
                if (($a['source'] ?? '') === 'upload' && ! empty($a['path'])) {
                    Storage::disk('local')->delete($a['path']);
                }
                unset($p['attachments'][$i]);
            }
        }
        $p['attachments'] = array_values($p['attachments'] ?? []);
        $outbox->forceFill(['payload' => $p])->save();
    }

    /**
     * Keep the draft in the server's Drafts folder too (so other mail apps see it): the new copy goes in,
     * then the previous one is removed. Best effort: HQ keeps the draft whatever the server says.
     */
    public function saveServerDraft(User $user, MailOutbox $outbox): bool
    {
        $this->own($user, $outbox, ['draft']);
        $account = $outbox->account;
        $drafts = $account->folder('drafts');
        if (! $drafts || ! $this->hasContent($outbox->payload)) {
            return false;
        }

        try {
            $email = MessageBuilder::build($outbox, $user, forDraft: true);
            $email->from(new Address($account->email, (string) $account->display_name));
            $email->date(now());
            $raw = $email->toString();

            $old = $outbox->payload['server_draft'] ?? null;
            $session = $this->server->open($account);
            try {
                $uid = $session->append($drafts->path, $raw, ['\\Draft', '\\Seen']);
                if ($old && ! empty($old['uid'])) {
                    $session->select($old['path']);
                    $session->expunge([(int) $old['uid']]);
                }
            } finally {
                rescue(fn () => $session->logout(), null, false);
            }
        } catch (ImapException|Throwable $e) {
            Log::warning('Mail: draft not saved on the server', ['outbox' => $outbox->id, 'error' => $e->getMessage()]);

            return false;
        }

        $p = $outbox->fresh()->payload;
        $p['server_draft'] = $uid ? ['path' => $drafts->path, 'uid' => $uid] : null;
        $p['server_draft_at'] = now()->toIso8601String();
        $outbox->forceFill(['payload' => $p])->save();

        return true;
    }

    /** Remove the draft's copy from the server's Drafts (after sending, or when discarded). Best effort. */
    public function removeServerDraft(MailOutbox $outbox): void
    {
        $old = $outbox->payload['server_draft'] ?? null;
        if (! $old || empty($old['uid'])) {
            return;
        }
        try {
            $session = $this->server->open($outbox->account);
            try {
                $session->select($old['path']);
                $session->expunge([(int) $old['uid']]);
            } finally {
                rescue(fn () => $session->logout(), null, false);
            }
            // HQ's copy of it goes now rather than at the next sync
            $folder = $outbox->account->folders()->where('path', $old['path'])->first();
            if ($folder) {
                MailMessage::where('folder_id', $folder->id)->where('uid', (int) $old['uid'])->delete();
            }
        } catch (Throwable $e) {
            Log::warning('Mail: old draft not removed from the server', ['outbox' => $outbox->id, 'error' => $e->getMessage()]);
        }
        $p = $outbox->payload;
        $p['server_draft'] = null;
        $outbox->forceFill(['payload' => $p])->save();
    }

    public function removeFiles(MailOutbox $outbox): void
    {
        Storage::disk('local')->deleteDirectory(config('mail_compose.upload_dir', 'mail-outbox') . '/' . $outbox->id);
    }

    /** Anything worth keeping: a recipient, a subject, some text or a file */
    public function hasContent(array $p): bool
    {
        return ! empty($p['to']) || ! empty($p['cc']) || ! empty($p['bcc']) || trim((string) ($p['subject'] ?? '')) !== ''
            || ! HtmlCleaner::isBlank($p['html'] ?? '') || array_filter($p['attachments'] ?? [], fn ($a) => ($a['source'] ?? '') === 'upload');
    }

    /** A draft to keep when the composer closes: new mail with anything in it; an answer only with text or a file of its own */
    public function worthKeeping(MailOutbox $outbox): bool
    {
        $p = $outbox->payload ?? [];
        if ($outbox->kind === 'new') {
            return $this->hasContent($p);
        }

        return ! HtmlCleaner::isBlank($p['html'] ?? '') || (bool) array_filter($p['attachments'] ?? [], fn ($a) => ($a['source'] ?? '') === 'upload');
    }

    /** Drafts opened and never written in (the page was left): removed after a day. @return int how many */
    public function pruneUntouched(): int
    {
        $rows = MailOutbox::where('status', 'draft')->where('updated_at', '<', now()->subDay())
            ->where(fn ($q) => $q->whereNull('payload->touched')->orWhere('payload->touched', false))->get();
        foreach ($rows as $row) {
            $this->removeFiles($row);
            $row->delete();
        }

        return $rows->count();
    }

    /** The person's own row, in one of these states, from a mailbox they can still send from */
    public function own(User $user, MailOutbox $outbox, array $statuses): void
    {
        abort_unless($outbox->user_id === $user->id, 403);
        abort_unless($outbox->account && MailAccess::can($user, $outbox->account, 'send'), 403);
        if (! in_array($outbox->status, $statuses, true)) {
            throw ValidationException::withMessages(['outbox' => match ($outbox->status) {
                'sending', 'sent' => 'This message has already gone.',
                'queued' => 'This message is waiting to go. Undo it first to change it.',
                default => 'This message can’t be changed now.',
            }]);
        }
    }

    public static function attachmentBytes(array $p): int
    {
        return (int) array_sum(array_map(fn ($a) => (int) ($a['size'] ?? 0), $p['attachments'] ?? []));
    }

    public static function maxBytes(): int
    {
        return (int) config('mail_compose.max_attachments_mb', 20) * 1024 * 1024;
    }

    public static function emptyPayload(string $kind): array
    {
        return [
            'to' => [], 'cc' => [], 'bcc' => [], 'subject' => '', 'html' => '',
            'signature' => in_array($kind, ['reply', 'reply_all'], true) ? 'slim' : 'full',
            'quote' => false, 'quote_html' => null, 'quote_text' => null,
            'in_reply_to' => null, 'references' => null,
            'attachments' => [], 'server_draft' => null, 'touched' => false,
        ];
    }

    private function create(User $user, MailAccount $account, string $kind, array $payload, ?int $threadId, ?int $originalId): MailOutbox
    {
        $messageId = MailTransport::newMessageId($account);

        return MailOutbox::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'kind' => $kind,
            'status' => 'draft',
            'thread_id' => $threadId,
            'reply_to_message_id' => $originalId,
            'payload' => $payload,
            'message_id' => $messageId,
            'message_id_hash' => MailMessage::hashId($messageId),
        ]);
    }

    /** Recipients, subject, threading headers, quote and (forward) attachments of an answer */
    private function answer(User $user, string $kind, MailAccount $account, MailMessage $original): array
    {
        $own = [$account->email];
        $sender = $original->reply_to ?: array_filter([['name' => $original->from_name, 'email' => (string) $original->from_email]], fn ($a) => $a['email'] !== '');
        $to = $cc = [];
        if ($kind === 'reply' || $kind === 'reply_all') {
            $to = $original->is_outbound ? ($original->to ?? []) : $sender;
            $to = Recipients::parse($to)['valid'];
            if ($kind === 'reply_all') {
                $others = Recipients::parse(array_merge($original->is_outbound ? [] : ($original->to ?? []), $original->cc ?? []))['valid'];
                $cc = Recipients::without(Recipients::without($others, $to), $own);
            }
            $without = Recipients::without($to, $own);
            $to = $without ?: $to; // writing back to yourself stays possible
        }

        $subject = trim((string) $original->subject);
        $subject = match ($kind) {
            'forward' => preg_match('/^(fwd?|tr)\s*:/i', $subject) ? $subject : 'Fwd: ' . $subject,
            default => preg_match('/^(re|aw|réf?)\s*:/iu', $subject) ? $subject : 'Re: ' . $subject,
        };

        $body = rescue(fn () => MailFetch::make()->body($user, $original), ['text' => (string) $original->snippet, 'html' => null], false);
        $quote = MessageBuilder::quote($kind, $original, $body);

        $references = $original->referenceIds();
        if ($original->message_id) {
            $references[] = $original->message_id;
        }
        $references = array_slice(array_values(array_unique($references)), -20);

        $attachments = $kind === 'forward'
            ? $original->attachments()->where('is_inline', false)->get()
                ->map(fn ($a) => ['key' => 'o' . $a->id, 'name' => $a->filename, 'mime' => $a->mime, 'size' => (int) $a->size, 'source' => 'original', 'attachment_id' => $a->id])->all()
            : [];

        return [
            'to' => $to,
            'cc' => $cc,
            'subject' => mb_substr($subject, 0, 250),
            'quote' => true,
            'quote_html' => $quote['html'],
            'quote_text' => $quote['text'],
            'in_reply_to' => $kind === 'forward' ? null : $original->message_id,
            'references' => $kind === 'forward' ? null : (implode(' ', $references) ?: null),
            'attachments' => $attachments,
        ];
    }

    private static function fileName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', $name));

        return mb_substr($name !== '' ? $name : 'attachment', 0, 150);
    }
}
