<?php

namespace App\Support\Mail\Sync;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailOutbox;
use App\Support\Mail\Imap\BodyStructure;
use App\Support\Mail\Imap\ImapSession;
use App\Support\Mail\Mime\Decoder;
use App\Support\Mail\Mime\Headers;
use App\Support\Mail\Mime\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns FETCH summaries into mail_messages rows (with attachments and a thread), then reads their bodies
 * in batches. A message that cannot be read still gets a row, so sync never retries it for ever.
 */
final class MessageWriter
{
    /**
     * @param  list<array>  $summaries  from ImapSession::fetchSummaries()
     * @return list<MailMessage> the stored messages
     */
    public static function store(MailAccount $account, MailFolder $folder, array $summaries, ImapSession $session): array
    {
        $stored = [];
        foreach ($summaries as $summary) {
            try {
                $stored[] = self::one($account, $folder, $summary);
            } catch (Throwable $e) {
                Log::warning('Mail: a message could not be read', ['account' => $account->id, 'folder' => $folder->id, 'uid' => $summary['uid'] ?? null, 'error' => $e->getMessage()]);
                $stored[] = self::placeholder($account, $folder, $summary);
            }
        }

        self::bodies($session, array_filter($stored, fn (MailMessage $m) => $m->body_state === 'pending'));

        return $stored;
    }

    /** Read and store the bodies of these messages (all in the folder that is open on $session) */
    public static function bodies(ImapSession $session, array $messages): void
    {
        $max = (int) config('mail_client.sync.max_body_bytes', 1_000_000);

        // Messages whose bodies sit in the same sections are fetched together
        $groups = [];
        foreach ($messages as $m) {
            $parts = $m->parts ?? [];
            $sections = [];
            $tooBig = false;
            foreach (['text', 'html'] as $kind) {
                if (! empty($parts[$kind])) {
                    $sections[] = $parts[$kind]['section'];
                    // base64 grows by a third; be generous before calling it too big
                    $tooBig = $tooBig || ($parts[$kind]['size'] ?? 0) > $max * 1.4;
                }
            }
            if ($tooBig) {
                $m->forceFill(['body_state' => 'remote'])->save();
                continue;
            }
            if (! $sections) {
                $m->forceFill(['body_state' => 'cached', 'body_text' => '', 'snippet' => $m->snippet ?? ''])->save();
                continue;
            }
            $groups[implode('|', $sections)][] = $m;
        }

        foreach ($groups as $key => $group) {
            $sections = explode('|', $key);
            foreach (array_chunk($group, 25) as $chunk) {
                try {
                    $raw = $session->fetchSectionsBatch(array_map(fn ($m) => (int) $m->uid, $chunk), $sections);
                } catch (Throwable $e) {
                    Log::warning('Mail: bodies could not be read', ['error' => $e->getMessage()]);
                    foreach ($chunk as $m) {
                        $m->forceFill(['body_state' => 'remote'])->save();
                    }
                    continue;
                }
                foreach ($chunk as $m) {
                    self::fillBody($m, $raw[(int) $m->uid] ?? []);
                }
            }
        }
    }

    /** Decode fetched sections into body_text / body_html and the snippet */
    public static function fillBody(MailMessage $m, array $raw): void
    {
        $parts = $m->parts ?? [];
        $text = null;
        $html = null;
        if (! empty($parts['text']) && array_key_exists($parts['text']['section'], $raw)) {
            $text = Decoder::decode($raw[$parts['text']['section']], $parts['text']['encoding'] ?? null, $parts['text']['charset'] ?? null);
        }
        if (! empty($parts['html']) && array_key_exists($parts['html']['section'], $raw)) {
            $html = Decoder::decode($raw[$parts['html']['section']], $parts['html']['encoding'] ?? null, $parts['html']['charset'] ?? null);
        }
        if ($text === null && $html === null) {
            $m->forceFill(['body_state' => 'remote'])->save();

            return;
        }
        if (($text === null || trim($text) === '') && $html !== null) {
            $text = Text::fromHtml($html);
        }

        $m->forceFill([
            'body_text' => $text,
            'body_html' => $html,
            'body_state' => 'cached',
            'snippet' => Text::snippet((string) $text) ?: $m->snippet,
        ])->save();
    }

    private static function one(MailAccount $account, MailFolder $folder, array $summary): MailMessage
    {
        $h = Headers::parse($summary['headers'] ?? '');
        $raw = fn (string $name) => $h[$name][0] ?? null;

        $from = Headers::addresses($raw('from'))[0] ?? null;
        $messageId = Headers::messageIds($raw('message-id'))[0] ?? null;
        $inReplyTo = Headers::messageIds($raw('in-reply-to'));
        $references = array_slice(Headers::messageIds($raw('references')), -30);
        $sentAt = Headers::date($raw('date'));
        $receivedAt = Headers::date($summary['internaldate'] ?? null) ?? $sentAt ?? now();
        $flags = array_map('strtolower', $summary['flags'] ?? []);
        $structure = BodyStructure::summarise($summary['structure'] ?? []);
        $to = Headers::addresses($raw('to'));
        $cc = Headers::addresses($raw('cc'));
        $subject = Headers::first($h, 'subject');

        $fromEmail = $from['email'] ?? null;
        $outbound = ($fromEmail !== null && strcasecmp($fromEmail, $account->email) === 0)
            || in_array($folder->role, ['sent', 'drafts'], true);

        $hash = MailMessage::hashId($messageId);
        $sentBy = null;
        if ($outbound && $hash) {
            $sentBy = MailOutbox::where('account_id', $account->id)->where('message_id_hash', $hash)
                ->whereIn('status', ['sending', 'sent'])->value('user_id');
        }

        $nonInline = array_filter($structure['attachments'], fn ($a) => ! $a['is_inline']);

        return DB::transaction(function () use ($account, $folder, $summary, $messageId, $hash, $inReplyTo, $references, $subject, $from, $to, $cc, $h, $sentAt, $receivedAt, $flags, $structure, $nonInline, $outbound, $sentBy, $raw) {
            $message = MailMessage::create([
                'account_id' => $account->id,
                'folder_id' => $folder->id,
                'uid' => (int) $summary['uid'],
                'message_id' => $messageId !== null ? mb_substr($messageId, 0, 255) : null,
                'message_id_hash' => $hash,
                'in_reply_to' => $inReplyTo ? mb_substr(end($inReplyTo), 0, 255) : null,
                'references' => $references ? implode(' ', $references) : null,
                'subject' => $subject !== null ? mb_substr($subject, 0, 500) : null,
                'from_name' => $from['name'] ?? null,
                'from_email' => $from['email'] ?? null,
                'to' => $to,
                'cc' => $cc,
                'bcc' => Headers::addresses($raw('bcc')),
                'reply_to' => Headers::addresses($raw('reply-to')),
                'to_text' => self::addressText(array_merge($to, $cc)),
                'sent_at' => $sentAt,
                'received_at' => $receivedAt,
                'size' => (int) ($summary['size'] ?? 0),
                'is_seen' => in_array('\\seen', $flags, true),
                'is_flagged' => in_array('\\flagged', $flags, true),
                'is_answered' => in_array('\\answered', $flags, true),
                'is_draft' => in_array('\\draft', $flags, true),
                'has_attachments' => count($nonInline) > 0,
                'is_outbound' => $outbound,
                'body_state' => 'pending',
                'parts' => ['text' => $structure['text'], 'html' => $structure['html']],
                'list_unsubscribe' => ($lu = Headers::first($h, 'list-unsubscribe')) !== null ? mb_substr($lu, 0, 500) : null,
                'sent_by' => $sentBy,
            ]);

            if ($structure['attachments']) {
                DB::table('mail_attachments')->insert(array_map(fn ($a) => [
                    'message_id' => $message->id,
                    'section' => $a['section'],
                    'filename' => mb_substr($a['filename'], 0, 255),
                    'mime' => mb_substr($a['mime'], 0, 127),
                    'encoding' => $a['encoding'],
                    'size' => $a['size'],
                    'content_id' => $a['content_id'] !== null ? mb_substr($a['content_id'], 0, 255) : null,
                    'is_inline' => $a['is_inline'],
                ], $structure['attachments']));
            }

            Threader::assign($message);

            return $message;
        });
    }

    private static function placeholder(MailAccount $account, MailFolder $folder, array $summary): MailMessage
    {
        $existing = MailMessage::where('folder_id', $folder->id)->where('uid', (int) ($summary['uid'] ?? 0))->first();
        if ($existing) {
            return $existing;
        }

        $message = MailMessage::create([
            'account_id' => $account->id,
            'folder_id' => $folder->id,
            'uid' => (int) ($summary['uid'] ?? 0),
            'subject' => '(This message could not be read in HQ)',
            'received_at' => Headers::date($summary['internaldate'] ?? null) ?? now(),
            'size' => (int) ($summary['size'] ?? 0),
            'is_seen' => in_array('\\seen', array_map('strtolower', $summary['flags'] ?? []), true),
            'body_state' => 'error',
        ]);
        Threader::assign($message);

        return $message;
    }

    private static function addressText(array $addresses): string
    {
        return mb_substr(implode(' ', array_map(fn ($a) => trim(($a['name'] ?? '') . ' ' . $a['email']), $addresses)), 0, 5000);
    }
}
