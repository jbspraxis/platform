<?php

namespace App\Support\Finance;

use App\Support\WebsiteSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * VAT on what JBS Praxis bills (owner, 1 Nov 2026: "We are not billing VAT until we fully register JBS Praxis").
 * Every VAT decision goes through here.
 *
 * - Which company issues a document: the legal entity (Master data › Legal entities, active) whose region_code is the
 *   client organisation's country, else Nigeria's (issuer()). Nothing else in billing picks a company.
 * - VAT applies when that company charges VAT (its switch, off until it is registered) and the document's date is on
 *   or after its VAT start date; the rate is then the company's country rate in Master data › Tax compliance. A
 *   company that charges VAT with no active rate for its country bills none (HQ never invents a rate).
 * - Each document keeps the answer it was issued with (vat_applied, vat_rate): a proposal when it is sent, a SOW when
 *   HQ signs it, an invoice when it is raised. Drafts follow today's answer until then; a later change to the switch
 *   never changes what was issued.
 */
final class Vat
{
    /** Nigeria's company issues whatever no other company's country matches */
    public const HOME = 'NG';

    /** Country names people type that aren't in WebsiteSettings::COUNTRIES (lower case => ISO code) */
    private const ALIASES = ['cameroun' => 'CM', 'nigéria' => 'NG', 'uk' => 'GB', 'royaume-uni' => 'GB', 'usa' => 'US', 'états-unis' => 'US', 'emirats arabes unis' => 'AE'];

    /**
     * The VAT on a document for $organisation dated $date (default: today in Lagos). $organisation is an organisation
     * (model or row with its country) or its id. rate is 0 when VAT doesn't apply, and why says why not: off (the
     * company doesn't charge VAT), not_yet (the date is before its VAT start date), no_rate, no_company.
     *
     * @return array{applies: bool, rate: float, why: ?string, region: ?string, company: ?string, vat_number: ?string, starts_on: ?string}
     */
    public static function for(object|string|null $organisation, CarbonInterface|string|null $date = null): array
    {
        $issuer = self::issuer($organisation);
        $day = ($date === null ? Carbon::now('Africa/Lagos') : Carbon::parse($date)->setTimezone('Africa/Lagos'))->toDateString();
        $startsOn = $issuer && $issuer->charges_vat && $issuer->vat_starts_on ? substr((string) $issuer->vat_starts_on, 0, 10) : null;
        $rate = $startsOn !== null && $day >= $startsOn ? self::countryRate($issuer->region_code) : null;

        return [
            'applies' => $rate !== null,
            'rate' => $rate ?? 0.0,
            'why' => match (true) {
                $rate !== null => null,
                $issuer === null => 'no_company',
                $startsOn === null => 'off',
                $day < $startsOn => 'not_yet',
                default => 'no_rate',
            },
            'region' => $issuer?->region_code,
            'company' => $issuer?->legal_name,
            'vat_number' => $startsOn !== null ? $issuer->vat_number : null,
            'starts_on' => $startsOn,
        ];
    }

    /** The legal entity that issues documents to the organisation: its country's, else Nigeria's; null when neither is active */
    public static function issuer(object|string|null $organisation): ?object
    {
        $code = self::countryCode($organisation);
        $entities = DB::table('mdm_legal_entities')->where('is_active', 1)
            ->whereIn('region_code', array_values(array_unique(array_filter([$code, self::HOME]))))
            ->get()->keyBy('region_code');

        return $entities->get($code) ?? $entities->get(self::HOME);
    }

    /** The ISO code of the organisation's country ("Nigeria", "NG", "Cameroun", "Nigeria (FIRS)"), or null when unknown */
    public static function countryCode(object|string|null $organisation): ?string
    {
        if (is_string($organisation)) {
            $organisation = DB::table('organizations')->where('id', $organisation)->first(['country']);
        }
        $country = mb_strtolower(trim((string) ($organisation->country ?? '')));
        if ($country === '') {
            return null;
        }
        if (isset(WebsiteSettings::COUNTRIES[strtoupper($country)])) {
            return strtoupper($country);
        }
        foreach (WebsiteSettings::COUNTRIES as $code => $name) {
            if (mb_strtolower($name) === $country) {
                return $code;
            }
        }
        if (isset(self::ALIASES[$country])) {
            return self::ALIASES[$country];
        }
        $row = DB::table('mdm_tax_compliance')->get(['region_name', 'country_code'])
            ->first(fn ($r) => mb_strtolower((string) $r->country_code) === $country || str_starts_with(mb_strtolower((string) $r->region_name), $country));

        return $row ? strtoupper((string) $row->country_code) : null;
    }

    /** The country's VAT rate in Master data › Tax compliance (active rules only), or null when it has none */
    public static function countryRate(?string $code): ?float
    {
        $rate = $code ? DB::table('mdm_tax_compliance')->where('is_active', 1)->where('country_code', $code)->value('vat_rate') : null;

        return $rate === null ? null : (float) $rate;
    }

    /** Whether a document recorded VAT: its vat_applied, or for one issued before the switch existed, a rate above 0 */
    public static function applied(mixed $applied, mixed $rate): bool
    {
        return $applied === null ? (float) $rate > 0 : (bool) $applied;
    }

    /** "7.5" / "19,25": the rate as the VAT line shows it */
    public static function percent(float $rate, string $locale): string
    {
        return rtrim(rtrim(number_format($rate, 2, $locale === 'fr' ? ',' : '.', ''), '0'), '.,');
    }

    /**
     * What HQ says about an answer of for(), in the current language: "VAT at 7.5%: JBS Praxis Nigeria Ltd charges VAT
     * from 1 December 2026 (VAT no. 123)." or "No VAT: JBS Praxis Nigeria Ltd doesn't charge VAT yet."
     */
    public static function describe(array $vat): string
    {
        $locale = app()->getLocale();
        $values = [
            'company' => (string) ($vat['company'] ?? ''),
            'rate' => self::percent((float) $vat['rate'], $locale),
            'date' => $vat['starts_on'] ? self::date($vat['starts_on'], $locale) : '',
            'number' => (string) ($vat['vat_number'] ?? ''),
        ];

        return __('vat.describe.' . ($vat['applies'] ? 'applies' : $vat['why']), $values);
    }

    /** "1 December 2026" / "1 décembre 2026" */
    public static function date(string $day, string $locale): string
    {
        return Carbon::parse($day)->locale($locale)->translatedFormat('j F Y');
    }
}
