<?php

namespace App\Support\Leads;

/**
 * Countries a lead can be in (organizations.country holds the name, as Master data and the opportunity form do),
 * the language its emails start in, and the dialling codes the website's contact form sends instead of a country.
 */
final class Countries
{
    /** Where the firm's leads are, Nigeria and Cameroon first; "Another country" lets the form type any other */
    public const NAMES = [
        'Nigeria', 'Cameroon', 'Benin', 'Central African Republic', 'Chad', 'Congo', 'Côte d’Ivoire', 'DR Congo', 'Equatorial Guinea',
        'Gabon', 'Ghana', 'Kenya', 'Rwanda', 'Senegal', 'South Africa', 'Togo', 'France', 'United Arab Emirates', 'United Kingdom', 'United States',
    ];

    /** French by default for emails (the person adding the lead can change it) */
    public const FRENCH = ['Cameroon', 'Benin', 'Central African Republic', 'Chad', 'Congo', 'Côte d’Ivoire', 'DR Congo', 'Gabon', 'Senegal', 'Togo', 'France'];

    public const DIAL_CODES = [
        '+234' => 'Nigeria', '+237' => 'Cameroon', '+229' => 'Benin', '+236' => 'Central African Republic', '+235' => 'Chad',
        '+242' => 'Congo', '+243' => 'DR Congo', '+225' => 'Côte d’Ivoire', '+240' => 'Equatorial Guinea', '+241' => 'Gabon',
        '+233' => 'Ghana', '+254' => 'Kenya', '+250' => 'Rwanda', '+221' => 'Senegal', '+27' => 'South Africa', '+228' => 'Togo',
        '+33' => 'France', '+971' => 'United Arab Emirates', '+44' => 'United Kingdom', '+1' => 'United States',
    ];

    /** The country of a dialling code (+234 → Nigeria), else null */
    public static function fromDialCode(?string $code): ?string
    {
        $code = preg_replace('/\s+/', '', (string) $code);

        return $code !== '' ? (self::DIAL_CODES[str_starts_with($code, '+') ? $code : '+' . ltrim($code, '0')] ?? null) : null;
    }

    /** EN or FR (organizations.preferred_language) for a lead in $country */
    public static function language(?string $country): string
    {
        return in_array($country, self::FRENCH, true) ? 'FR' : 'EN';
    }
}
