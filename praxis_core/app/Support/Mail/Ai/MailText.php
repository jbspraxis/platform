<?php

namespace App\Support\Mail\Ai;

/** Plain text of a message for the AI: quoted earlier messages and signatures' separators cut off. */
final class MailText
{
    public static function withoutQuotes(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Everything from the first "On … wrote:" / "-----Original Message-----" / Outlook "From: … Sent:" block on
        $cuts = [
            '/^\s*On .{0,200}wrote:\s*$/mi',
            '/^\s*Le .{0,200}a écrit\s*:\s*$/mi',
            '/^-{2,}\s*Original Message\s*-{2,}/mi',
            '/^-{2,}\s*Forwarded message\s*-{2,}/mi',
            '/^_{10,}\s*$/m',
            '/^From:\s.+\n(?:.+\n){0,3}?(?:Sent|Date):\s/mi',
        ];
        foreach ($cuts as $pattern) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE) && $m[0][1] > 0) {
                $text = substr($text, 0, $m[0][1]);
            }
        }

        // Any remaining "> quoted" lines
        $lines = array_filter(explode("\n", $text), fn ($l) => ! str_starts_with(ltrim($l), '>'));
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        return trim($text);
    }

    public static function fromHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<blockquote\b[^>]*>.*?</blockquote>#is', ' ', $html);
        $html = preg_replace('#<(br|/p|/div|/li|/tr|/h\d)\b[^>]*>#i', "\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function clip(string $text, int $chars): string
    {
        return mb_strlen($text) > $chars ? rtrim(mb_substr($text, 0, $chars)) . ' …' : $text;
    }
}
