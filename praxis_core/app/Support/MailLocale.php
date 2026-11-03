<?php

namespace App\Support;

use Illuminate\Support\Str;

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

    /**
     * Marketing email (docs/CAMPAIGNS.md 4.3): the person's own preference, else their organisation's, else French
     * for a French-speaking country, else English.
     */
    public static function forContact(?string $own, ?string $organisation = null, ?string $country = null): string
    {
        return self::parse($own) ?? self::parse($organisation) ?? self::fromCountry($country) ?? 'en';
    }

    /** 'fr' for a country where business is done in French (a name in English or French, or its ISO code); else null */
    public static function fromCountry(?string $country): ?string
    {
        $key = trim((string) preg_replace('/[^a-z]+/', ' ', strtolower(Str::ascii((string) $country))));

        return $key !== '' && in_array($key, self::FRANCOPHONE, true) ? 'fr' : null;
    }

    /** French-speaking countries, as fromCountry() reads them: ISO codes, English and French names, without accents */
    private const FRANCOPHONE = [
        'cm', 'cameroon', 'cameroun', 'ci', 'cote d ivoire', 'ivory coast', 'sn', 'senegal', 'bj', 'benin', 'tg', 'togo',
        'bf', 'burkina faso', 'ml', 'mali', 'ne', 'niger', 'td', 'chad', 'tchad', 'cf', 'central african republic',
        'republique centrafricaine', 'centrafrique', 'ga', 'gabon', 'cg', 'congo', 'republic of the congo', 'congo brazzaville',
        'cd', 'dr congo', 'drc', 'democratic republic of the congo', 'republique democratique du congo', 'rdc', 'congo kinshasa',
        'gn', 'guinea', 'guinee', 'gq', 'equatorial guinea', 'guinee equatoriale', 'mg', 'madagascar', 'bi', 'burundi',
        'dj', 'djibouti', 'km', 'comoros', 'comores', 'fr', 'france', 'be', 'belgium', 'belgique', 'lu', 'luxembourg',
        'mc', 'monaco', 'ht', 'haiti', 'ma', 'morocco', 'maroc', 'dz', 'algeria', 'algerie', 'tn', 'tunisia', 'tunisie',
        'mr', 'mauritania', 'mauritanie',
    ];
}
