<?php

namespace App\Support\Mail\Read;

/**
 * A plain-text message as safe HTML for the reader: escaped, web and mail addresses made into links, and the
 * quoted earlier message ("On … wrote:", "> " lines, Outlook's "-----Original Message-----") folded.
 */
final class TextFormatter
{
    public static function toHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", rtrim($text));
        $lines = explode("\n", $text);
        $cut = self::quoteStart($lines);

        $own = implode("\n", $cut === null ? $lines : array_slice($lines, 0, $cut));
        $html = '<div class="mr-text">' . self::links(rtrim($own)) . '</div>';
        if ($cut !== null) {
            $html .= '<details class="hq-quote"><summary>Show quoted text</summary><div class="mr-text">'
                . self::links(implode("\n", array_slice($lines, $cut))) . '</div></details>';
        }

        return $html;
    }

    /** Index of the first line of the quoted part, or null (never the whole message) */
    private static function quoteStart(array $lines): ?int
    {
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            $header = preg_match('/^(On\b.{4,200}\bwrote:|Le\b.{4,200}\ba écrit\s?:|-{2,}\s*Original Message\s*-{2,}|_{20,})$/iu', $line)
                // "On Tue, 3 Nov 2026 at 10:00, Ada <ada@x.com>" wraps onto a second line before "wrote:"
                || (preg_match('/^On\b/', $line) && isset($lines[$i + 1]) && preg_match('/\bwrote:$/', trim($lines[$i + 1])));
            $quoted = str_starts_with($line, '>') && self::restQuoted($lines, $i);
            if (($header || $quoted) && self::hasText(array_slice($lines, 0, $i))) {
                return $i;
            }
        }

        return null;
    }

    /** From here to the end, every non-empty line is quoted */
    private static function restQuoted(array $lines, int $from): bool
    {
        foreach (array_slice($lines, $from) as $line) {
            if (trim($line) !== '' && ! str_starts_with(ltrim($line), '>')) {
                return false;
            }
        }

        return true;
    }

    /** Some words of the person's own (not quoted lines) */
    private static function hasText(array $lines): bool
    {
        foreach ($lines as $line) {
            if (trim($line) !== '' && ! str_starts_with(ltrim($line), '>')) {
                return true;
            }
        }

        return false;
    }

    /** Escape, then link http(s) addresses and email addresses */
    public static function links(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return preg_replace_callback(
            '~\bhttps?://(?:(?!&gt;|&lt;)[^\s"\'])+(?<![.,;:!?)\]])|\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b~i',
            function ($m) {
                $value = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $href = str_contains($value, '://') ? $value : 'mailto:' . $value;

                return '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer nofollow">' . $m[0] . '</a>';
            },
            $escaped,
        ) ?? $escaped;
    }
}
