<?php

namespace App\Support\CompanyProfile;

/**
 * The typographic preparation every string gets before it reaches a page (docs/BRAND.md sections 4 and 8).
 *
 * Two jobs. French spacing: a colon, semicolon, exclamation or question mark takes a space before it and
 * a guillemet takes one inside it, and none of those spaces may ever fall at the end of a line. The brand
 * writes them as a narrow no-break space (U+202F), which neither Newsreader nor Plus Jakarta Sans carries
 * a glyph for, so dompdf would draw a .notdef box; they become a no-break space (U+00A0) here, which both
 * families have. Widows: a last line holding one word reads as a mistake at this size, so the space
 * before the last word of a paragraph, a heading or a cell becomes a no-break space too.
 */
final class Typography
{
    public const NBSP = "\u{00A0}";

    /** Every string in the tree, prepared in place. */
    public static function prepare(array $data, string $locale): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::prepare($value, $locale);
            } elseif (is_string($value) && ! self::isUrlish($key)) {
                $data[$key] = self::text($value, $locale);
            }
        }

        return $data;
    }

    /** One string: French spacing, then the widow guard. */
    public static function text(string $value, string $locale): string
    {
        $value = str_replace(["\u{202F}", "\u{2009}"], self::NBSP, $value);

        if ($locale === 'fr') {
            // A plain space before the four high punctuation marks, and inside the guillemets.
            $value = preg_replace('/ +([;:!?»])/u', self::NBSP.'$1', $value);
            $value = preg_replace('/« +/u', '«'.self::NBSP, $value);
        }

        return self::guardWidow($value);
    }

    /**
     * Bind the last two words of each sentence-ending run, so no line is left holding a single word.
     * Only the final space of the whole string is bound: binding more would push words around.
     */
    public static function guardWidow(string $value): string
    {
        $trimmed = rtrim($value);
        if ($trimmed === '' || mb_substr_count($trimmed, ' ') < 3) {
            return $value;
        }

        $position = mb_strrpos($trimmed, ' ');
        if ($position === false) {
            return $value;
        }

        // Leave a no-break space alone; it is already bound.
        $last = mb_substr($trimmed, $position + 1);
        if ($last === '' || mb_strlen($last) > 14) {
            return $value;
        }

        return mb_substr($trimmed, 0, $position).self::NBSP.$last.mb_substr($value, mb_strlen($trimmed));
    }

    /**
     * The cover headline as its sentences: "Run everything. Ask anything." sets as two lines, which is
     * how the line was written, and the French equivalent breaks the same way.
     *
     * @return list<string>
     */
    public static function headlineLines(string $headline): array
    {
        $headline = str_replace(self::NBSP, ' ', $headline);
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($headline)) ?: [];
        $lines = array_values(array_filter(array_map('trim', $parts), fn ($line) => $line !== ''));

        return $lines === [] ? [trim($headline)] : $lines;
    }

    /** Keys that hold a URL or a path, which must not be touched. */
    private static function isUrlish(string|int $key): bool
    {
        return in_array($key, ['cta_url', 'path', 'image', 'website', 'email', 'sales_email', 'reference'], true);
    }
}
