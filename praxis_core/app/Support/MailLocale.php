<?php

namespace App\Support;

/**
 * Which language an email goes out in (docs/BRAND.md section 13): the recipient's stated preference,
 * else the language they are using on the website, else English. Only 'en' and 'fr' exist.
 */
final class MailLocale
{
    /** A preference such as 'fr', 'French (FR)', 'Français' or 'English (EN)'; null when unclear ('Bilingual'). */
    public static function parse(?string $preference): ?string
    {
        $p = strtolower(trim((string) $preference));

        return match (true) {
            $p === '' => null,
            (bool) preg_match('/^(fr|french|fran[cç]ais)\b/u', $p) => 'fr',
            (bool) preg_match('/^(en|english|anglais)\b/u', $p) => 'en',
            default => null,
        };
    }

    /** A visitor on the website: their stated preference, else the language of the page they used. */
    public static function forVisitor(?string $preference = null): string
    {
        return self::parse($preference) ?? (app()->getLocale() === 'fr' ? 'fr' : 'en');
    }

    /** Someone emailed from HQ, the scheduler or the command line: their stored preference, else English (never the sender's own screen language). */
    public static function forRecipient(?string $preference = null): string
    {
        return self::parse($preference) ?? 'en';
    }
}
