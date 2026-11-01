<?php

namespace App\Support\Finance;

use App\Support\WebsiteSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * VAT on what JBS Praxis bills (owner, 1 Nov 2026: "We are not billing VAT until we fully register JBS Praxis").
 * Every VAT decision goes through here.
 *
 * - Which company issues a document: the billing company chosen on its opportunity (opportunities.billing_entity_id,
 *   an active legal entity in Master data › Legal entities), else Nigeria's (issuer()). That covers documents without
 *   an opportunity, legacy ones and invoices.
 * - VAT applies when that company charges VAT (its switch, off until it is registered) and the document's date (in
 *   Lagos) is on or after its VAT start date, at the company's country rate in Master data › Tax compliance. A company
 *   that charges VAT with no active rate for its country bills none (HQ never invents a rate).
 * - Across a border (owner, 1 Nov 2026): when the company charges VAT and the client organisation's country isn't the
 *   company's, the service is exported: VAT at 0%, zero-rated, and the document says so with the client's VAT or tax
 *   number (note()). While the switch is off there is neither a VAT line nor a note.
 * - Each document keeps the answer it was issued with (vat_applied, vat_rate, billing_entity_id): a proposal when it
 *   is sent, a SOW when HQ signs it, an invoice when it is raised. Drafts follow today's answer until then; a later
 *   change to the switch or to the opportunity's billing company never changes what was issued (recorded()).
 */
final class Vat
{
    /** Nigeria's company bills whatever no other company was chosen for */
    public const HOME = 'NG';

    /** Country names people type that aren't in WebsiteSettings::COUNTRIES (lower case => ISO code) */
    private const ALIASES = ['cameroun' => 'CM', 'nigéria' => 'NG', 'uk' => 'GB', 'royaume-uni' => 'GB', 'usa' => 'US', 'états-unis' => 'US', 'emirats arabes unis' => 'AE'];

    /**
     * The VAT on a document for $organisation (model or row with its country and tax number, or its id) dated $date
     * (default: now), issued by $entityId (the opportunity's billing company; null: Nigeria's). rate is 0 when VAT
     * doesn't apply, and why says why not: off (the company doesn't charge VAT), not_yet (before its start date),
     * no_rate, no_company. zero_rated: VAT applies at 0% because the client is abroad.
     *
     * @return array{applies: bool, rate: float, zero_rated: bool, why: ?string, entity_id: ?string, region: ?string, company: ?string, vat_number: ?string, starts_on: ?string, client_tax_id: ?string}
     */
    public static function for(object|string|null $organisation, CarbonInterface|string|null $date = null, ?string $entityId = null): array
    {
        $organisation = self::organisation($organisation);
        $issuer = self::issuer($entityId);
        $day = ($date === null ? Carbon::now('Africa/Lagos') : Carbon::parse($date)->setTimezone('Africa/Lagos'))->toDateString();
        $startsOn = $issuer && $issuer->charges_vat && $issuer->vat_starts_on ? substr((string) $issuer->vat_starts_on, 0, 10) : null;
        $rate = $startsOn !== null && $day >= $startsOn ? self::countryRate($issuer->region_code) : null;
        $client = self::countryCode($organisation);
        $abroad = $rate !== null && $client !== null && $client !== $issuer->region_code;

        return [
            'applies' => $rate !== null,
            'rate' => $abroad ? 0.0 : ($rate ?? 0.0),
            'zero_rated' => $abroad,
            'why' => match (true) {
                $rate !== null => null,
                $issuer === null => 'no_company',
                $startsOn === null => 'off',
                $day < $startsOn => 'not_yet',
                default => 'no_rate',
            },
            'entity_id' => $issuer?->id,
            'region' => $issuer?->region_code,
            'company' => $issuer?->legal_name,
            'vat_number' => $startsOn !== null ? $issuer->vat_number : null,
            'starts_on' => $startsOn,
            'client_tax_id' => self::clientTaxId($organisation),
        ];
    }

    /**
     * What an issued document recorded, in the shape of for(): applied at its rate by its company (null: Nigeria's,
     * or issued before the switch existed). A VAT-charging company never has a 0% rate of its own (Tax compliance
     * refuses it), so applied at 0% is a zero-rated export.
     */
    public static function recorded(mixed $applied, mixed $rate, ?string $entityId, object|string|null $organisation = null): array
    {
        $applies = self::applied($applied, $rate);
        $entity = $entityId ? DB::table('mdm_legal_entities')->where('id', $entityId)->first() : null;

        return [
            'applies' => $applies,
            'rate' => $applies ? (float) $rate : 0.0,
            'zero_rated' => $applies && (float) $rate == 0.0,
            'why' => $applies ? null : 'off',
            'entity_id' => $entityId,
            'region' => $entity?->region_code,
            'company' => $entity?->legal_name,
            'vat_number' => $applies ? $entity?->vat_number : null,
            'starts_on' => null,
            'client_tax_id' => $applies ? self::clientTaxId(self::organisation($organisation)) : null,
        ];
    }

    /** The company that issues: the chosen one when it is active, else Nigeria's; null when neither is */
    public static function issuer(?string $entityId): ?object
    {
        $chosen = $entityId ? DB::table('mdm_legal_entities')->where('id', $entityId)->where('is_active', 1)->first() : null;

        return $chosen ?? DB::table('mdm_legal_entities')->where('region_code', self::HOME)->where('is_active', 1)->first();
    }

    /** The company's legal name ("JBS Praxis Nigeria Ltd"), or null when there is none */
    public static function companyName(?string $entityId): ?string
    {
        return $entityId ? (DB::table('mdm_legal_entities')->where('id', $entityId)->value('legal_name') ?: null) : null;
    }

    /** The companies an opportunity can be billed by: the active legal entities, Nigeria's first */
    public static function companies(): Collection
    {
        return DB::table('mdm_legal_entities')->where('is_active', 1)
            ->orderByRaw('region_code = ? desc', [self::HOME])->orderBy('legal_name')->get(['id', 'region_code', 'legal_name']);
    }

    /** A project's billing company: its opportunity's (projects.opportunity_id, else the opportunity pointing at it) */
    public static function projectEntity(string $projectId): ?string
    {
        $opportunityId = DB::table('projects')->where('id', $projectId)->value('opportunity_id');

        return $opportunityId
            ? DB::table('opportunities')->where('id', $opportunityId)->value('billing_entity_id')
            : DB::table('opportunities')->where('project_id', $projectId)->value('billing_entity_id');
    }

    /** The ISO code of the organisation's country ("Nigeria", "NG", "Cameroun", "Nigeria (FIRS)"), or null when unknown */
    public static function countryCode(object|string|null $organisation): ?string
    {
        $organisation = self::organisation($organisation);
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
        return rtrim(rtrim(number_format($rate, 2, $locale === 'fr' ? ',' : '.', ''), '0'), '.,') ?: '0';
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

        return __('vat.describe.' . (($vat['zero_rated'] ?? false) ? 'zero_rated' : ($vat['applies'] ? 'applies' : $vat['why'])), $values);
    }

    /** The zero-rating note a document carries for a client abroad (with their VAT or tax number when HQ has it), else null */
    public static function note(array $vat): ?string
    {
        if (! ($vat['zero_rated'] ?? false)) {
            return null;
        }

        return trim(__('vat.zero_rated') . (filled($vat['client_tax_id'] ?? null) ? ' ' . __('vat.client_tax', ['number' => $vat['client_tax_id']]) : ''));
    }

    /** "1 December 2026" / "1 décembre 2026" */
    public static function date(string $day, string $locale): string
    {
        return Carbon::parse($day)->locale($locale)->translatedFormat('j F Y');
    }

    private static function organisation(object|string|null $organisation): ?object
    {
        return is_string($organisation) ? DB::table('organizations')->where('id', $organisation)->first(['country', 'tax_identification_number']) : $organisation;
    }

    private static function clientTaxId(?object $organisation): ?string
    {
        return trim((string) ($organisation->tax_identification_number ?? '')) ?: null;
    }
}
