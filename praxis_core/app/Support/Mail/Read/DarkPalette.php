<?php

namespace App\Support\Mail\Read;

/**
 * Takes the dark half out of a sender's CSS (docs/MAIL.md, section 14).
 *
 * The reader is a white page and HQ has no dark theme, but `prefers-color-scheme` inside its frame follows the
 * person's device. A sender that repaints for a dark canvas the mail client would have supplied (Google
 * Calendar: `#e8eaed` text under `@media (prefers-color-scheme: dark)`) therefore lands light text on white.
 * Pinning `color-scheme` on the frame does not change that media query, so the rules have to go.
 *
 * Two things are removed: media queries that match only a dark device, and `color-scheme` declarations (the
 * opt-in that makes a page follow the device). Everything else is returned as written, including queries that
 * are true on a light device (`not (prefers-color-scheme: dark)`) and the other half of a list such as
 * `(prefers-color-scheme: dark), print`.
 */
final class DarkPalette
{
    public static function strip(string $css): string
    {
        // Strings out of the way first: a `}` or `;` inside content:"…" is not CSS structure. HtmlSanitizer::css()
        // has already removed NUL bytes, so \0 can't collide with the input.
        $strings = [];
        $css = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/s', function (array $m) use (&$strings) {
            $strings[] = $m[0];

            return "\0" . (count($strings) - 1) . "\0";
        }, $css) ?? '';

        $css = preg_replace('/(?<![\w-])(?:-webkit-|-moz-)?(?:supported-)?color-schemes?\s*:[^;}]*;?/i', '', $css) ?? '';
        $css = self::withoutDarkMedia($css);

        return preg_replace_callback('/\0(\d+)\0/', fn (array $m) => $strings[(int) $m[1]], $css) ?? '';
    }

    private static function withoutDarkMedia(string $css): string
    {
        $from = 0;
        while (preg_match('/@media\b([^{;]*)\{/i', $css, $m, PREG_OFFSET_CAPTURE, $from)) {
            $at = $m[0][1];
            $open = $at + strlen($m[0][0]) - 1;
            $queries = array_values(array_filter(array_map('trim', explode(',', $m[1][0])), 'strlen'));
            $kept = array_values(array_filter($queries, fn (string $q) => ! self::onlyDark($q)));

            if (count($kept) === count($queries)) {
                $from = $open + 1;      // nothing to take out, but a dark block may be nested inside this one

                continue;
            }

            if ($kept !== []) {         // `(dark), print`: the print half stays
                $css = substr($css, 0, $at) . '@media ' . implode(', ', $kept) . '{' . substr($css, $open + 1);
                $from = $at + 1;

                continue;
            }

            $close = self::blockEnd($css, $open);
            // Braces that never balance: the rest of the sheet sits inside this block, so it goes with it
            $css = substr($css, 0, $at) . ($close === null ? '' : substr($css, $close + 1));
            $from = $at;
        }

        return $css;
    }

    /** A query that is true on a dark device and false on a light one */
    private static function onlyDark(string $query): bool
    {
        return preg_match('/prefers-color-scheme\s*:\s*dark/i', $query) === 1
            && preg_match('/^not\b|\bor\b|\bnot\s*\(/i', $query) === 0;
    }

    /** The `}` that closes the block opening at $open, or null when the braces never balance */
    private static function blockEnd(string $css, int $open): ?int
    {
        for ($depth = 0, $i = $open, $len = strlen($css); $i < $len; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }
}
