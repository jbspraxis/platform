<?php

namespace App\Support\Mail\Compose;

use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailOutbox;
use App\Models\User;
use App\Support\Mail\MailFetch;
use App\Support\Mail\MailSendException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Turns an outbox row into the message that goes out (docs/MAIL.md section 12, "PR 3 payload"): the person's
 * text, then their signature (full or short, added here, never in the editor), then the quoted original; an
 * HTML and a plain-text version; In-Reply-To and References on replies; attachments (uploaded files, and the
 * original's when forwarding, read from the server through MailFetch). The From line is set by MailTransport.
 *
 * A problem that sending again will not fix (an attachment gone) throws MailSendException, permanent.
 */
final class MessageBuilder
{
    private const BODY_STYLE = "font-family:'Plus Jakarta Sans',Arial,sans-serif;font-size:14px;line-height:22px;color:#0C142B;";

    /** The message to send ($forDraft: the copy kept in the server's Drafts: no signature, Bcc kept) */
    public static function build(MailOutbox $outbox, User $user, bool $forDraft = false): Email
    {
        $p = $outbox->payload ?? [];
        $account = $outbox->account;
        $email = new Email();

        foreach (['to', 'cc', 'bcc'] as $field) {
            $addresses = array_map(fn ($a) => new Address($a['email'], (string) ($a['name'] ?? '')), $p[$field] ?? []);
            if ($addresses) {
                $email->{$field}(...$addresses);
            }
        }
        $email->subject((string) ($p['subject'] ?? ''));

        $headers = $email->getHeaders();
        if ($outbox->message_id) {
            $headers->addIdHeader('Message-ID', $outbox->message_id);
        }
        if (! empty($p['in_reply_to'])) {
            $headers->addIdHeader('In-Reply-To', $p['in_reply_to']);
        }
        if (! empty($p['references'])) {
            $headers->addIdHeader('References', preg_split('/\s+/', trim($p['references'])));
        }

        $style = $forDraft ? 'none' : ($p['signature'] ?? 'full');
        $body = (string) ($p['html'] ?? '');
        $quote = ! empty($p['quote']);

        $html = '<div style="' . self::BODY_STYLE . '">' . self::styled($body) . '</div>'
            . Signatures::html($user, $account, $style)
            . ($quote && ! empty($p['quote_html']) ? '<div style="margin-top:24px;' . self::BODY_STYLE . '">' . $p['quote_html'] . '</div>' : '');
        $email->html('<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;padding:0;">' . $html . '</body></html>');

        $text = HtmlCleaner::toText($body);
        if (($signature = Signatures::text($user, $account, $style)) !== '') {
            $text .= "\n\n" . $signature;
        }
        if ($quote && ! empty($p['quote_text'])) {
            $text .= "\n\n" . $p['quote_text'];
        }
        $email->text($text . "\n");

        foreach ($p['attachments'] ?? [] as $a) {
            [$content, $name, $mime] = self::attachment($a, $user);
            $email->attach($content, $name, $mime);
        }

        return $email;
    }

    /**
     * The original, quoted below a reply or forwarded, as HTML and plain text (prepared when the composer
     * opens and kept in the payload, so the message can still go if the original moves).
     *
     * @param  array{text: string, html: ?string}  $body
     * @return array{html: string, text: string}
     */
    public static function quote(string $kind, MailMessage $original, array $body): array
    {
        $text = trim((string) ($body['text'] ?? ''));
        if ($text === '' && ! empty($body['html'])) {
            $text = HtmlCleaner::toText($body['html']);
        }
        $text = mb_substr($text, 0, 100_000);
        $when = self::when($original->sent_at ?? $original->received_at);
        $from = Recipients::format(['name' => $original->from_name, 'email' => (string) $original->from_email]);

        if ($kind === 'forward') {
            $lines = array_filter([
                'From: ' . $from,
                $when ? 'Date: ' . $when : null,
                'Subject: ' . ($original->subject ?? ''),
                ($to = implode(', ', array_map([Recipients::class, 'format'], $original->to ?? []))) !== '' ? 'To: ' . $to : null,
                ($cc = implode(', ', array_map([Recipients::class, 'format'], $original->cc ?? []))) !== '' ? 'Cc: ' . $cc : null,
            ]);
            $head = "---------- Forwarded message ----------\n" . implode("\n", $lines);

            return [
                'html' => '<p style="margin:0 0 12px;color:#5E6679;">' . nl2br(e($head), false) . '</p>' . self::styled(HtmlCleaner::fromText($text)),
                'text' => $head . "\n\n" . $text,
            ];
        }

        $intro = ($when ? "On {$when}, " : '') . "{$from} wrote:";

        return [
            'html' => '<p style="margin:0 0 8px;color:#5E6679;">' . e($intro) . '</p>'
                . '<blockquote style="margin:0 0 0 4px;padding:0 0 0 12px;border-left:2px solid #D8DEE6;color:#4A5468;">' . self::styled(HtmlCleaner::fromText($text)) . '</blockquote>',
            'text' => $intro . "\n" . preg_replace('/^/m', '> ', $text),
        ];
    }

    /** Paragraph spacing written into the tags (mail apps ignore most style sheets) */
    private static function styled(string $html): string
    {
        return str_replace(
            ['<p>', '<blockquote>', '<ul>', '<ol>'],
            ['<p style="margin:0 0 12px;">', '<blockquote style="margin:0 0 12px 4px;padding:0 0 0 12px;border-left:2px solid #D8DEE6;">', '<ul style="margin:0 0 12px;padding-left:24px;">', '<ol style="margin:0 0 12px;padding-left:24px;">'],
            $html,
        );
    }

    /** @return array{0: string, 1: string, 2: string} content, file name, MIME type */
    private static function attachment(array $a, User $user): array
    {
        if (($a['source'] ?? '') === 'upload') {
            $disk = Storage::disk('local');
            if (! $disk->exists($a['path'] ?? '')) {
                throw new MailSendException("The attached file {$a['name']} is no longer in HQ. Remove it and attach it again.", 'rejected', true);
            }

            return [$disk->get($a['path']), $a['name'], $a['mime'] ?: 'application/octet-stream'];
        }

        $attachment = MailAttachment::find($a['attachment_id'] ?? 0);
        if (! $attachment) {
            throw new MailSendException("The attachment {$a['name']} from the original message is no longer in HQ. Remove it and send again.", 'rejected', true);
        }
        try {
            $file = MailFetch::make()->attachment($user, $attachment);
        } catch (HttpExceptionInterface) {
            throw new MailSendException("The attachment {$a['name']} from the original message can't be sent from HQ. Remove it and send again.", 'rejected', true);
        } catch (Throwable) {
            throw new MailSendException("The attachment {$a['name']} could not be read from the mail server. Try again in a minute.", 'temporary', false);
        }

        return [$file['content'], $file['filename'] ?: $a['name'], $file['mime'] ?: 'application/octet-stream'];
    }

    private static function when(?CarbonInterface $at): ?string
    {
        return $at?->copy()->timezone((string) config('mail_compose.timezone', 'Africa/Lagos'))->format('j M Y \a\t H:i');
    }
}
