<?php

namespace App\Support\Letters;

use App\Support\Hosts;
use App\Support\WebsiteSettings;
use Illuminate\Support\Facades\DB;

/**
 * The company's letterhead (docs/DOCUMENTS.md section 3): one per company in Master data › Legal entities
 * (mdm_legal_entities), so it follows the company's own details and is never hard-coded here.
 *
 * Header: the vector logo and the office's details. Footer, on every page: the legal name, the registration
 * number and tax number, the registered office, then the phone, the email address and the website, over a fine
 * rule in brand teal, with "Page 1 of 2" on documents longer than a page.
 *
 * The logo is resources/brand/letterhead-logo.svg, read as a data URI: dompdf draws SVG with php-svg-lib and
 * needs no gd extension, which the live PHP has not got. It is the brand kit horizontal logo with its nested
 * <svg> elements flattened into groups, which is the one part of SVG php-svg-lib does not place correctly.
 * Its outlines were then thinned (Douglas-Peucker at 0.05 of a unit, about 0.01 pt at the 186 pt it is drawn,
 * coordinates to two decimals): 1,405 points instead of 13,932 and 19 KB instead of 210 KB, the same logo at
 * 600 dpi, and about 140 ms less of dompdf drawing it on every letter.
 */
final class Letterhead
{
    /** Brand teal (docs/BRAND.md section 7): the rule under the header and above the footer */
    public const TEAL = '#0C8B9B';

    public const INK = '#0C142B';

    public const MUTED = '#5E6679';

    public const SLATE = '#4A5468';

    /** Every company that can issue a document: the active legal entities, Nigeria first */
    public static function companies(): array
    {
        return DB::table('mdm_legal_entities')->where('is_active', 1)
            ->orderByRaw("CASE WHEN region_code = 'NG' THEN 0 ELSE 1 END")->orderBy('legal_name')
            ->get()->map(fn ($row) => (array) $row)->all();
    }

    public static function company(?string $id): ?array
    {
        $row = $id ? DB::table('mdm_legal_entities')->where('id', $id)->first() : null;

        return $row ? (array) $row : null;
    }

    /** The company whose letterhead a new document carries by default: the Nigerian entity */
    public static function defaultCompany(): ?array
    {
        return self::companies()[0] ?? null;
    }

    /**
     * Everything the letterhead prints, from the legal entity and Website settings.
     *
     * @return array{name:string, registration:?string, tax:?string, address:?string, phone:?string, email:string,
     *               website:string, region:string, office_lines:array<int,string>, footer_legal:string, footer_contact:string}
     */
    public static function details(array $company): array
    {
        $name = trim((string) ($company['legal_name'] ?? ''));
        $region = strtoupper((string) ($company['region_code'] ?? ''));
        $registration = self::clean($company['registration_number'] ?? null);
        $tax = self::clean($company['tax_identification_number'] ?? null);
        $address = self::clean($company['registered_address'] ?? null);
        $phone = self::clean($company['contact_phone'] ?? null);
        $email = self::clean($company['contact_email'] ?? null);
        if ($phone === null || $email === null) {
            // Website settings fill what the legal entity leaves out: read once for both
            $contact = (array) WebsiteSettings::get('contact', []);
            $phone ??= self::settingsPhone($region, (array) ($contact['phones'] ?? []));
            $email ??= (string) ($contact['email'] ?? 'info@jbspraxis.com');
        }
        $website = Hosts::site();

        // "RC 9985884" in Nigeria, "RC/RCCM" in Cameroon: the label the papers use, then the tax number
        $registrationLabel = $region === 'CM' ? 'RCCM' : 'RC';
        $taxLabel = $region === 'CM' ? 'NIU' : 'TIN';

        $legal = array_filter([
            $name,
            $registration ? $registrationLabel . ' ' . $registration : null,
            $tax ? $taxLabel . ' ' . $tax : null,
            $address ? 'Registered office: ' . self::oneLine($address) : null,
        ]);

        return [
            'name' => $name,
            'registration' => $registration,
            'registration_label' => $registrationLabel,
            'tax' => $tax,
            'tax_label' => $taxLabel,
            'address' => $address,
            'phone' => $phone,
            'email' => $email,
            'website' => $website,
            'region' => $region,
            'representative' => self::clean($company['legal_representative_name'] ?? null),
            'representative_title' => self::clean($company['legal_representative_title'] ?? null),
            'office_lines' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $address)))),
            'footer_legal' => implode(' · ', $legal),
            'footer_contact' => implode(' · ', array_filter([$phone, $email, $website])),
        ];
    }

    /** The logo as a data URI, so nothing is read from the filesystem while the PDF renders */
    public static function logoDataUri(): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::logoSvg());
    }

    public static function logoSvg(): string
    {
        return (string) file_get_contents(self::logoPath());
    }

    public static function logoPath(): string
    {
        return resource_path('brand/letterhead-logo.svg');
    }

    /** The same logo as a PNG, for the Word letterhead (Word cannot be given an SVG on its own) */
    public static function logoPngPath(): string
    {
        return resource_path('brand/jbs-praxis-horizontal-colour-1600.png');
    }

    /** A phone number from Website settings for the company's country, when the legal entity has none */
    private static function settingsPhone(string $region, array $phones): ?string
    {
        $labels = ['NG' => 'Nigeria', 'CM' => 'Cameroon'];
        foreach ($phones as $row) {
            $number = self::clean($row['number'] ?? null);
            if ($number && (! isset($labels[$region]) || trim((string) ($row['label'] ?? '')) === $labels[$region])) {
                return $number;
            }
        }

        return null;
    }

    private static function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private static function oneLine(string $address): string
    {
        return trim(preg_replace('/\s*(\r\n|\r|\n)\s*/', ', ', $address));
    }
}
