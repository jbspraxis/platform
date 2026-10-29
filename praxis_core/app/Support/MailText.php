<?php

namespace App\Support;

/**
 * Turns a branded HTML email into its plain-text part. The mail components mark what to do:
 * <!--text:skip-->…<!--/text:skip--> is left out (preheader, button fallback line),
 * data-text="button" links become "Label: URL", and <!--text:sep--> separates a label from its value.
 */
final class MailText
{
    public static function fromHtml(string $html): string
    {
        $colon = app()->getLocale() === 'fr' ? "\u{00A0}: " : ': '; // French puts a no-break space before the colon
        $t = preg_replace('~<head\b.*?</head>~is', '', $html);
        $t = preg_replace('~<!--text:skip-->.*?<!--/text:skip-->~s', '', $t);
        $t = preg_replace('~<!--\[if mso\]>.*?<!\[endif\]-->~s', '', $t);   // Outlook-only copies
        $t = str_replace('<!--text:sep-->', "\u{1F}", $t);
        $t = preg_replace('~<!--.*?-->~s', '', $t);

        $t = preg_replace_callback('~<a\b([^>]*)>(.*?)</a>~is', function ($m) use ($colon) {
            $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $href = preg_match('~href="([^"]*)"~i', $m[1], $h) ? html_entity_decode($h[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
            if ($label === '') {
                return ''; // an image link (the logo)
            }
            if ($href === '' || str_starts_with($href, 'mailto:') && $label === substr($href, 7) || $label === $href) {
                return $label;
            }
            return str_contains($m[1], 'data-text="button"') ? "{$label}{$colon}{$href}" : "{$label} ({$href})";
        }, $t);

        $t = preg_replace('~<br\s*/?>~i', "\n", $t);
        $t = preg_replace('~<li\b[^>]*>~i', "\n- ", $t);
        $t = preg_replace('~</(p|h[1-6]|blockquote|ul|ol)>~i', "\n\n", $t);
        $t = preg_replace('~</(tr|div|li)>~i', "\n", $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("~[ \t]*\x{1F}[ \t]*~u", $colon, $t);

        // Spacer cells leave lines of no-break spaces; trim those too
        $lines = array_map(fn ($l) => preg_replace('~^[\s\x{00A0}]+|[\s\x{00A0}]+$~u', '', preg_replace('~[ \t]+~', ' ', $l)), explode("\n", $t));
        $t = preg_replace("~\n{3,}~", "\n\n", implode("\n", $lines));

        return trim($t) . "\n";
    }
}
