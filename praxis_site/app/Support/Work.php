<?php

namespace App\Support;

/**
 * The proof pages (/work, /work/{slug}, /industries): which work exists, its sector, the domains it covers and
 * what it relates to. Words are in lang/{en,fr}/work.php under the same keys. Clients are named only from the
 * approved list (docs/BRAND.md section 1, Proof); every other client is described, never named.
 * Domains are the five of pages.domains, in their order, so the sector coverage on /industries is computed
 * from the work itself and never typed by hand.
 */
final class Work
{
    /** The five domains, in the order of pages.domains (Money, People, Operations, Risk and governance, Data and AI). */
    public const DOMAINS = ['money', 'people', 'operations', 'risk', 'data'];

    /** Sectors, in the order /industries shows them. A sector is listed only while some work below is in it. */
    public const SECTORS = ['logistics', 'consumer-goods', 'retail', 'pharma', 'oil-gas', 'digital', 'small-business', 'education-faith'];

    /**
     * Case studies with their own page, in the order /work and the home page list them (docs/LAUNCH_COPY.md).
     * group: clients or products (our own); stage: live or build; related: two other case studies.
     */
    public const CASES = [
        'smart-logistics' => ['group' => 'clients', 'sector' => 'logistics', 'stage' => 'live',
            'domains' => ['money', 'people', 'operations', 'risk', 'data'], 'related' => ['fmcg-distributor-cameroon', 'pixie-girl-global']],
        'mycity-app' => ['group' => 'clients', 'sector' => 'digital', 'stage' => 'build',
            'domains' => ['money', 'people', 'risk', 'data'], 'related' => ['pharmaceutical-manufacturer', 'smart-logistics']],
        'pixie-girl-global' => ['group' => 'clients', 'sector' => 'retail', 'stage' => 'live',
            'domains' => ['money', 'people', 'operations', 'risk', 'data'], 'related' => ['orika-living', 'smart-logistics']],
        'orika-living' => ['group' => 'clients', 'sector' => 'retail', 'stage' => 'live',
            'domains' => ['money', 'people', 'operations', 'risk', 'data'], 'related' => ['pixie-girl-global', 'fmcg-distributor-cameroon']],
        'pharmaceutical-manufacturer' => ['group' => 'clients', 'sector' => 'pharma', 'stage' => 'build',
            'domains' => ['money', 'operations', 'risk', 'data'], 'related' => ['mycity-app', 'fmcg-distributor-cameroon']],
        'fmcg-distributor-cameroon' => ['group' => 'clients', 'sector' => 'consumer-goods', 'stage' => 'live',
            'domains' => ['money', 'people', 'operations', 'risk', 'data'], 'related' => ['smart-logistics', 'orika-living']],
        'nfelia' => ['group' => 'products', 'sector' => 'small-business', 'stage' => 'live',
            'domains' => ['money', 'operations'], 'related' => ['orika-living', 'pixie-girl-global']],
    ];

    /** Work described by sector only, as short entries on /work (anchors #<key>), never with a page of its own. */
    public const OTHER = [
        'oil-and-gas' => ['sector' => 'oil-gas', 'domains' => ['operations', 'risk']],
        'fresh-food' => ['sector' => 'consumer-goods', 'domains' => ['money', 'operations']],
        'education' => ['sector' => 'education-faith', 'domains' => ['money', 'operations']],
        'church' => ['sector' => 'education-faith', 'domains' => ['money', 'operations', 'risk']],
    ];

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::CASES);
    }

    /** @return array<string, array<string, mixed>> the case studies of one group, in order */
    public static function group(string $group): array
    {
        return array_filter(self::CASES, fn (array $case) => $case['group'] === $group);
    }

    /**
     * The work in one sector: case studies first, then the sector-only entries.
     *
     * @return array{cases: list<string>, other: list<string>}
     */
    public static function inSector(string $sector): array
    {
        return [
            'cases' => array_keys(array_filter(self::CASES, fn (array $case) => $case['sector'] === $sector)),
            'other' => array_keys(array_filter(self::OTHER, fn (array $entry) => $entry['sector'] === $sector)),
        ];
    }

    /** @return list<string> the sectors that have work in them, in order */
    public static function sectors(): array
    {
        return array_values(array_filter(self::SECTORS, function (string $sector) {
            $work = self::inSector($sector);

            return $work['cases'] !== [] || $work['other'] !== [];
        }));
    }

    /** @return list<string> the domains the work in a sector covers, in the order of DOMAINS */
    public static function coverage(string $sector): array
    {
        $covered = [];
        foreach ([...array_values(self::CASES), ...array_values(self::OTHER)] as $entry) {
            if ($entry['sector'] === $sector) {
                $covered = [...$covered, ...$entry['domains']];
            }
        }

        return array_values(array_intersect(self::DOMAINS, $covered));
    }

    /** The domain's position in pages.domains, for its name and icon. */
    public static function domainIndex(string $domain): int
    {
        return (int) array_search($domain, self::DOMAINS, true);
    }
}
