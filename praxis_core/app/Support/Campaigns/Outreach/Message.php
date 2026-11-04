<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Support\Campaigns\RichText;
use App\Support\Mail\Compose\HtmlCleaner;
use App\Support\Mail\Compose\Signatures;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The outreach email as it leaves the sender's mailbox: a personal email, not a designed one. The approved words,
 * the sender's own HQ Mail signature (full on the first email, short on the follow-up), then one quiet line to
 * unsubscribe in the prospect's language, with List-Unsubscribe and List-Unsubscribe-Post (RFC 8058) so inboxes show
 * their own button. The follow-up is a reply to the first (Re: subject, In-Reply-To, References, the first quoted).
 * From is set by MailTransport (the mailbox).
 */
final class Message
{
    private const BODY_STYLE = "font-family:'Plus Jakarta Sans',Arial,sans-serif;font-size:14px;line-height:22px;color:#0C142B;";

    private const STYLES = [
        'p' => 'margin:0 0 12px;',
        'ul' => 'margin:0 0 12px;padding-left:22px;',
        'ol' => 'margin:0 0 12px;padding-left:22px;',
        'li' => 'margin:0 0 4px;',
        'h2' => 'margin:16px 0 8px;font-size:17px;line-height:24px;font-weight:600;',
        'h3' => 'margin:12px 0 6px;font-size:15px;line-height:22px;font-weight:600;',
        'a' => 'color:#08707C;text-decoration:underline;',
    ];

    public static function subject(OutreachEmail $email, ?OutreachEmail $first = null): string
    {
        if ($email->isFirst()) {
            return (string) $email->subject;
        }
        $subject = (string) ($first?->subject ?? '');

        return preg_match('/^re\s*:/i', $subject) ? $subject : 'Re: ' . $subject;
    }

    public static function unsubscribeUrl(OutreachEmail $email): string
    {
        return route('email.unsubscribe.outreach', ['token' => $email->token]);
    }

    /** $first: the first email, for a follow-up (its subject, Message-ID and words, quoted) */
    public static function build(OutreachEmail $email, OutreachProspect $prospect, User $sender, MailAccount $account, ?OutreachEmail $first = null): Email
    {
        $locale = $prospect->language === 'fr' ? 'fr' : 'en';
        $unsubscribe = self::unsubscribeUrl($email);
        $message = (new Email())
            ->to(new Address($prospect->email, $prospect->fullName()))
            ->subject(self::subject($email, $first));

        $headers = $message->getHeaders();
        if ($email->message_id) {
            $headers->addIdHeader('Message-ID', $email->message_id);
        }
        if (! $email->isFirst() && $first?->message_id) {
            $headers->addIdHeader('In-Reply-To', $first->message_id);
            $headers->addIdHeader('References', [$first->message_id]);
        }
        $headers->addTextHeader('List-Unsubscribe', '<' . $unsubscribe . '>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $style = $email->isFirst() ? 'full' : 'slim';
        $line = __('outreach.unsubscribe_line', [], $locale);
        $link = __('outreach.unsubscribe_link', [], $locale);
        $quote = ! $email->isFirst() && $first ? self::quote($first, $sender, $locale) : ['html' => '', 'text' => ''];

        $html = '<div style="' . self::BODY_STYLE . '">' . self::styled((string) $email->body_html) . '</div>'
            . Signatures::html($sender, $account, $style)
            . '<p style="margin:20px 0 0;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:12px;line-height:18px;color:#5E6679;">'
            . e($line) . ' <a href="' . e($unsubscribe) . '" style="color:#5E6679;text-decoration:underline;">' . e($link) . '</a>.</p>'
            . $quote['html'];
        $message->html('<!DOCTYPE html><html lang="' . $locale . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;padding:0;">' . $html . '</body></html>');

        $text = HtmlCleaner::toText((string) $email->body_html);
        if (($signature = Signatures::text($sender, $account, $style)) !== '') {
            $text .= "\n\n" . $signature;
        }
        $text .= "\n\n" . $line . ' ' . $link . ': ' . $unsubscribe;
        if ($quote['text'] !== '') {
            $text .= "\n\n" . $quote['text'];
        }
        $message->text($text . "\n");

        return $message;
    }

    /** "On 4 Nov 2026, Gerald Nwoke wrote:" and the first email, as mail apps quote a reply */
    private static function quote(OutreachEmail $first, User $sender, string $locale): array
    {
        $at = Limits::local($first->sent_at ?? now());
        $date = $locale === 'fr' ? $at->locale('fr')->translatedFormat('j M Y') : $at->format('j M Y');
        $intro = __('outreach.quote_intro', ['date' => $date, 'name' => Signatures::person($sender)['name']], $locale);
        $words = (string) $first->body_html;

        return [
            'html' => '<div style="margin-top:24px;' . self::BODY_STYLE . 'color:#4A5468;">' . e($intro) . '</div>'
                . '<blockquote style="margin:8px 0 0;padding:0 0 0 12px;border-left:2px solid #D5DBE5;' . self::BODY_STYLE . 'color:#4A5468;">' . self::styled($words) . '</blockquote>',
            'text' => $intro . "\n" . implode("\n", array_map(fn ($l) => '> ' . $l, explode("\n", HtmlCleaner::toText($words)))),
        ];
    }

    /** Cleaned rich text with the inline styles mail apps need */
    private static function styled(string $html): string
    {
        $html = RichText::clean($html);

        return preg_replace_callback('~<(p|ul|ol|li|h2|h3|a)\b([^>]*)>~i', fn ($m) => '<' . $m[1] . ' style="' . self::STYLES[strtolower($m[1])] . '"' . $m[2] . '>', $html) ?? $html;
    }
}
