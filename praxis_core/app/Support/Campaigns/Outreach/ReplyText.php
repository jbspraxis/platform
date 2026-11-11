<?php

namespace App\Support\Campaigns\Outreach;

use App\Livewire\Hq\Mail\Inbox;
use App\Models\Campaigns\OutreachReply;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\User;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailFetch;
use App\Support\Mail\Read\HtmlSanitizer;
use App\Support\Mail\Read\TextFormatter;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A reply's words, always through HQ Mail's own contract (docs/MAIL.md section 7): a copy the person may read
 * (MailAccess), its body from HQ's copy or read from the server (MailFetch::body), and Mail's reader to show it
 * (Inbox::present: the sanitiser, a sandboxed frame without scripts or remote pictures). Personal mailboxes stay
 * private: someone who can't read the mailbox a reply is in sees Claude's label and reason, not the text.
 */
final class ReplyText
{
    /** Most characters of a reply Claude reads */
    public const MAX_FOR_AI = 4000;

    /**
     * The copy of the reply this person may read, the one in the campaign sender's mailbox first; null when they
     * can read none (another person's own mailbox). Without its bodies (up to 1 MB each, on every render of the
     * dialog): present() reads them for the one copy it shows.
     */
    public static function copyFor(?User $user, OutreachReply $reply): ?MailMessage
    {
        if (! $user || ! MailAccess::canUse($user)) {
            return null;
        }
        $ids = MailAccess::accountIds($user, 'read');
        if (! $ids) {
            return null;
        }
        $q = MailMessage::query()->withoutBodies()->whereIn('account_id', $ids);
        str_starts_with($reply->message_key, 'id:')
            ? $q->whereKey((int) substr($reply->message_key, 3))
            : $q->where(fn ($q) => $q->where('message_id_hash', $reply->message_key)->orWhere('id', $reply->mail_message_id));

        return $q->with('folder')->get()
            ->sortBy(fn (MailMessage $m) => [$m->account_id === $reply->mail_account_id ? 0 : 1, in_array($m->folder?->role, ['trash', 'spam'], true) ? 1 : 0, -$m->id])
            ->first();
    }

    /**
     * What the dialog shows, as Mail's reader does: ['kind' => 'html', 'doc' => …] for a sandboxed frame,
     * ['kind' => 'text', 'html' => …], 'empty', or 'error' when the server didn't send the text.
     */
    public static function present(User $user, MailMessage $message): array
    {
        abort_unless(MailAccess::canMessage($user, $message, 'read'), 403);
        try {
            // Mail's reader keeps a body read from the server for ten minutes under the same key
            $body = $message->body_state === 'cached'
                ? self::storedBody($message)
                : Cache::remember('mail-read-body:' . $message->id, 600, fn () => MailFetch::make()->body($user, $message));
        } catch (ImapException) {
            return ['kind' => 'error'];
        }

        // Pictures stay hidden and cid: pictures are left out: the full message is one click away in Mail
        return Inbox::present((string) ($body['text'] ?? ''), $body['html'] ?? null, false, fn () => null);
    }

    /** HQ's copy of the body: from the model when it was loaded with it, else read now (copyFor() leaves it out) */
    private static function storedBody(MailMessage $message): array
    {
        $row = array_key_exists('body_text', $message->getAttributes()) ? $message
            : MailMessage::whereKey($message->id)->first(['id', 'body_text', 'body_html']);

        return ['text' => (string) $row?->body_text, 'html' => $row?->body_html];
    }

    /**
     * The words for HQ's own jobs (sorting, notices): the stored copy, else read from the server as someone who may
     * read that mailbox (its owner for a personal one). Null when it can't be read now.
     *
     * @return array{text: string, html: ?string}|null
     */
    public static function body(MailMessage $message): ?array
    {
        if ($message->body_state === 'cached') {
            return ['text' => (string) $message->body_text, 'html' => $message->body_html];
        }
        $reader = self::reader($message);
        if (! $reader) {
            return null;
        }
        try {
            return MailFetch::make()->body($reader, $message);
        } catch (Throwable) {
            return null; // the server is away: the snippet will do
        }
    }

    /** Someone who may read the message's mailbox: the owner of a personal one, else a reader of the shared one */
    public static function reader(MailMessage $message): ?User
    {
        $account = MailAccount::find($message->account_id);
        if (! $account) {
            return null;
        }
        $ids = $account->kind === 'personal' ? [$account->owner_user_id] : MailAccess::readers($account);
        foreach (array_filter($ids) as $id) {
            $user = User::where('id', $id)->where('status', 'Active')->first();
            if ($user && MailAccess::can($user, $account, 'read')) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Plain text: the person's own words, with the quoted earlier message cut as Mail's reader folds it, blank lines
     * squeezed, at most $max characters.
     */
    public static function plain(?array $body, int $max = self::MAX_FOR_AI): string
    {
        $text = trim((string) ($body['text'] ?? ''));
        if ($text !== '') {
            $html = TextFormatter::toHtml($text);
        } elseif (trim((string) ($body['html'] ?? '')) !== '') {
            $html = HtmlSanitizer::clean((string) $body['html'])['html'];
            $html = preg_replace('~<(br|/p|/div|/li|/tr|/h[1-6]|/blockquote)\b[^>]*>~i', "$0\n", $html) ?? $html;
        } else {
            return '';
        }
        $html = preg_replace('~<details\b[^>]*class="[^"]*hq-quote[^"]*"[^>]*>.*?</details>~is', '', $html) ?? $html;
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+\n/', "\n", str_replace(["\r\n", "\r"], "\n", $plain))));

        return mb_strlen($plain) > $max ? mb_substr($plain, 0, $max) . '…' : $plain;
    }

    /** The whole text a notice carries (not cut at a quote: a bounce quotes the email it is about) */
    public static function full(?array $body): string
    {
        $text = (string) ($body['text'] ?? '');
        if (trim($text) === '' && trim((string) ($body['html'] ?? '')) !== '') {
            $text = html_entity_decode(strip_tags(preg_replace('~<(br|/p|/div|/tr)\b[^>]*>~i', "$0\n", (string) $body['html'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return mb_substr($text, 0, 50000);
    }
}
