<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\User;
use App\Support\Mail\Imap\BodyStructure;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\Imap\ImapSession;
use App\Support\Mail\Mime\Decoder;
use App\Support\Mail\Mime\Headers;
use App\Support\Mail\Sync\MessageWriter;
use Carbon\CarbonImmutable;

/**
 * Reading from the server what HQ doesn't keep (owner decision 8): bodies too large to copy, attachments, and
 * mail older than 12 months. Nothing read here for older mail is stored. Access is checked on every call.
 *
 * A live message (older than 12 months) has the same keys a stored one shows: uid, subject, from_name,
 * from_email, to, cc, reply_to, message_id, in_reply_to, references, sent_at, received_at, is_seen, is_flagged,
 * has_attachments, size, and from message() also text, html and attachments [section, filename, mime, size,
 * content_id, is_inline].
 */
final class MailFetch
{
    /** Largest attachment HQ hands out in one piece (bytes) */
    public const MAX_ATTACHMENT = 30 * 1024 * 1024;

    public function __construct(private readonly MailServer $server)
    {
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /** @return array{text: string, html: ?string} the stored copy, else read from the server (and kept, unless too large) */
    public function body(User $user, MailMessage $message): array
    {
        abort_unless(MailAccess::canMessage($user, $message, 'read'), 403);

        if ($message->body_state === 'cached') {
            return ['text' => (string) $message->body_text, 'html' => $message->body_html];
        }

        $parts = $message->parts ?? [];
        $sections = array_values(array_filter([$parts['text']['section'] ?? null, $parts['html']['section'] ?? null]));
        if (! $sections) {
            return ['text' => '', 'html' => null];
        }

        $raw = $this->inFolder($message->folder, fn (ImapSession $s) => $s->fetchSections((int) $message->uid, $sections));

        if ($message->body_state === 'pending') {
            MessageWriter::fillBody($message, $raw);
            $message->refresh();

            return ['text' => (string) $message->body_text, 'html' => $message->body_html];
        }

        $text = isset($parts['text']['section'], $raw[$parts['text']['section']])
            ? Decoder::decode($raw[$parts['text']['section']], $parts['text']['encoding'] ?? null, $parts['text']['charset'] ?? null) : '';
        $html = isset($parts['html']['section'], $raw[$parts['html']['section']])
            ? Decoder::decode($raw[$parts['html']['section']], $parts['html']['encoding'] ?? null, $parts['html']['charset'] ?? null) : null;

        return ['text' => $text, 'html' => $html];
    }

    /** @return array{filename: string, mime: string, content: string} decoded bytes */
    public function attachment(User $user, MailAttachment $attachment): array
    {
        $message = $attachment->message;
        abort_unless($message && MailAccess::canMessage($user, $message, 'read'), 403);
        abort_if($attachment->size > self::MAX_ATTACHMENT, 413, 'This attachment is too large to open in HQ. Open it in another mail app.');

        $raw = $this->inFolder($message->folder, fn (ImapSession $s) => $s->fetchSections((int) $message->uid, [$attachment->section]));

        return [
            'filename' => $attachment->filename,
            'mime' => $attachment->mime,
            'content' => Decoder::transfer($raw[$attachment->section] ?? '', $attachment->encoding),
        ];
    }

    /** An inline picture by its Content-ID (cid: in the HTML) */
    public function inline(User $user, MailMessage $message, string $contentId): ?array
    {
        $attachment = $message->attachments()->where('content_id', trim($contentId, '<> '))->first();

        return $attachment ? $this->attachment($user, $attachment) : null;
    }

    /**
     * Mail older than HQ's copy, newest first, a page at a time. $beforeUid: the last UID of the previous page.
     *
     * @return array{items: list<array>, next: ?int}
     */
    public function older(User $user, MailFolder $folder, ?int $beforeUid = null, int $limit = 50): array
    {
        abort_unless(MailAccess::can($user, $folder->account, 'read'), 403);
        $cutoff = CarbonImmutable::now()->subMonths((int) config('mail_client.sync.months', 12))->startOfDay();

        return $this->inFolder($folder, function (ImapSession $s) use ($cutoff, $beforeUid, $limit) {
            $uids = $s->search('BEFORE ' . $cutoff->format('j-M-Y'));
            if ($beforeUid !== null) {
                $uids = array_values(array_filter($uids, fn ($u) => $u < $beforeUid));
            }
            rsort($uids);
            $page = array_slice($uids, 0, $limit);
            $items = array_map([self::class, 'liveSummary'], $s->fetchSummaries($page));
            usort($items, fn ($a, $b) => $b['uid'] <=> $a['uid']);

            return ['items' => $items, 'next' => count($uids) > $limit ? (int) end($page) : null];
        });
    }

    /** Search the server's whole folder (all dates) for text; newest first, at most $limit */
    public function searchServer(User $user, MailFolder $folder, string $query, int $limit = 50): array
    {
        abort_unless(MailAccess::can($user, $folder->account, 'read'), 403);
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return $this->inFolder($folder, function (ImapSession $s) use ($query, $limit) {
            $uids = $s->searchText($query);
            rsort($uids);
            $items = array_map([self::class, 'liveSummary'], $s->fetchSummaries(array_slice($uids, 0, $limit)));
            usort($items, fn ($a, $b) => $b['uid'] <=> $a['uid']);

            return $items;
        });
    }

    /** One live message with its bodies and attachment list (not stored) */
    public function message(User $user, MailFolder $folder, int $uid): ?array
    {
        abort_unless(MailAccess::can($user, $folder->account, 'read'), 403);

        return $this->inFolder($folder, function (ImapSession $s) use ($uid) {
            $summary = $s->fetchSummaries([$uid])[0] ?? null;
            if (! $summary) {
                return null;
            }
            $item = self::liveSummary($summary);
            $structure = BodyStructure::summarise($summary['structure']);
            $sections = array_values(array_filter([$structure['text']['section'] ?? null, $structure['html']['section'] ?? null]));
            $raw = $sections ? $s->fetchSections($uid, $sections) : [];
            $item['text'] = $structure['text'] && isset($raw[$structure['text']['section']])
                ? Decoder::decode($raw[$structure['text']['section']], $structure['text']['encoding'], $structure['text']['charset']) : '';
            $item['html'] = $structure['html'] && isset($raw[$structure['html']['section']])
                ? Decoder::decode($raw[$structure['html']['section']], $structure['html']['encoding'], $structure['html']['charset']) : null;
            $item['attachments'] = $structure['attachments'];

            return $item;
        });
    }

    /** An attachment of a live message, by section */
    public function liveAttachment(User $user, MailFolder $folder, int $uid, string $section): array
    {
        $message = $this->message($user, $folder, $uid);
        $part = collect($message['attachments'] ?? [])->firstWhere('section', $section);
        abort_unless($part, 404);
        abort_if($part['size'] > self::MAX_ATTACHMENT, 413);

        $raw = $this->inFolder($folder, fn (ImapSession $s) => $s->fetchSections($uid, [$section]));

        return ['filename' => $part['filename'], 'mime' => $part['mime'], 'content' => Decoder::transfer($raw[$section] ?? '', $part['encoding'])];
    }

    public static function liveSummary(array $summary): array
    {
        $h = Headers::parse($summary['headers'] ?? '');
        $raw = fn (string $name) => $h[$name][0] ?? null;
        $from = Headers::addresses($raw('from'))[0] ?? null;
        $flags = array_map('strtolower', $summary['flags'] ?? []);
        $structure = BodyStructure::summarise($summary['structure'] ?? []);

        return [
            'uid' => (int) $summary['uid'],
            'subject' => Headers::first($h, 'subject'),
            'from_name' => $from['name'] ?? null,
            'from_email' => $from['email'] ?? null,
            'to' => Headers::addresses($raw('to')),
            'cc' => Headers::addresses($raw('cc')),
            'reply_to' => Headers::addresses($raw('reply-to')),
            'message_id' => Headers::messageIds($raw('message-id'))[0] ?? null,
            'in_reply_to' => ($ids = Headers::messageIds($raw('in-reply-to'))) ? end($ids) : null,
            'references' => implode(' ', Headers::messageIds($raw('references'))),
            'sent_at' => Headers::date($raw('date')),
            'received_at' => Headers::date($summary['internaldate'] ?? null) ?? Headers::date($raw('date')),
            'is_seen' => in_array('\\seen', $flags, true),
            'is_flagged' => in_array('\\flagged', $flags, true),
            'has_attachments' => (bool) array_filter($structure['attachments'], fn ($a) => ! $a['is_inline']),
            'size' => (int) ($summary['size'] ?? 0),
        ];
    }

    private function inFolder(MailFolder $folder, callable $work): mixed
    {
        $session = $this->server->open($folder->account);
        try {
            $session->examine($folder->path);

            return $work($session);
        } finally {
            try {
                $session->logout();
            } catch (ImapException) {
                // closing anyway
            }
        }
    }
}
