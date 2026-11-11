<?php

namespace App\Support\Campaigns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The filters a segment can use (docs/CAMPAIGNS.md 4.2). Each one says which groups of people it narrows and how:
 * a filter on something a group doesn't have (a subscriber has no industry or owner) leaves that group out while
 * it is set, so a segment never reaches people it can't vouch for.
 *
 * To add a filter (for example the lead source from Leads PR 1), add an entry to definitions(): its label, the
 * groups it narrows, its options and the column it reads. Rules saved with an unknown key are ignored.
 */
final class SegmentFilters
{
    /** Groups: contacts of clients, contacts of leads, Kaizen writers, website subscribers (Audience::WHO) */
    private const CONTACT_GROUPS = ['clients', 'leads'];

    public static function definitions(): array
    {
        $filters = [
            'client_status' => [
                'label' => 'Client status',
                'groups' => ['clients'],
                'narrows_only' => true, // other groups are not affected
                'column' => 'o.status',
                'options' => fn () => ['Active Client' => 'Active client', 'Past Client' => 'Past client'],
            ],
        ];

        // Leads PR 1 adds organizations.lead_status (New, Contacted, Qualified, Client, Lost); until then, the stage
        if (self::hasLeadStatus()) {
            $filters['lead_status'] = [
                'label' => 'Lead status',
                'groups' => ['leads'],
                'narrows_only' => true,
                'column' => 'o.lead_status',
                'options' => fn () => ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'client' => 'Client', 'lost' => 'Lost'],
            ];
        } else {
            $filters['lead_stage'] = [
                'label' => 'Lead stage',
                'groups' => ['leads'],
                'narrows_only' => true,
                'column' => 'o.pipeline_stage',
                'values_are_labels' => true,
                'options' => fn () => self::distinct('pipeline_stage', fn ($q) => $q->where('status', 'Lead')),
            ];
        }

        return $filters + [
            'industry' => [
                'label' => 'Industry',
                'groups' => self::CONTACT_GROUPS,
                'column' => 'o.industry',
                'values_are_labels' => true,
                'options' => fn () => self::distinct('industry'),
            ],
            'country' => [
                'label' => 'Country',
                'groups' => [...self::CONTACT_GROUPS, 'subscribers'],
                'column' => 'o.country',
                'subscriber_column' => 's.country',
                'values_are_labels' => true,
                'options' => fn () => self::countries(),
            ],
            'language' => [
                'label' => 'Language',
                'groups' => [...self::CONTACT_GROUPS, 'subscribers', 'kaizen'],
                'column' => null, // worked out per person (MailLocale::forContact), so applied after the query
                'options' => fn () => ['en' => 'English', 'fr' => 'French'],
            ],
            'owner' => [
                'label' => 'Owner',
                'groups' => self::CONTACT_GROUPS,
                'column' => 'o.assigned_to',
                'options' => fn () => self::owners(),
            ],
        ];
    }

    public static function options(string $key): array
    {
        $definition = self::definitions()[$key] ?? null;

        return $definition ? ($definition['options'])() : [];
    }

    /** Applies the rules' filters to one group's query; false when a filter leaves the whole group out */
    public static function apply(Builder $query, string $group, array $filters): bool
    {
        foreach (self::definitions() as $key => $definition) {
            $values = $filters[$key] ?? [];
            if ($values === [] || $definition['column'] === null) {
                continue;
            }
            if (! in_array($group, $definition['groups'], true)) {
                if (! empty($definition['narrows_only'])) {
                    continue;
                }

                return false;
            }
            $column = $group === 'subscribers' ? ($definition['subscriber_column'] ?? null) : $definition['column'];
            if ($column === null) {
                return false;
            }
            $query->whereIn($column, $values);
        }

        return true;
    }

    private static ?bool $hasLeadStatus = null;

    /** organizations.lead_status is there once Leads PR 1's migration has run (it has, everywhere): no schema query per request */
    public static function hasLeadStatus(): bool
    {
        return self::$hasLeadStatus ?? true;
    }

    /** Tests: pretend the column is there (true), isn't (false), or as it is (null) */
    public static function assumeLeadStatus(?bool $has): void
    {
        self::$hasLeadStatus = $has;
    }

    /** value => value, for organisations that can receive broadcasts */
    private static function distinct(string $column, ?\Closure $where = null): array
    {
        $query = DB::table('organizations')->whereNull('deleted_at')->whereNotNull($column)->where($column, '<>', '')
            ->whereIn('status', ['Lead', 'Active Client', 'Past Client']);
        if ($where) {
            $where($query);
        }
        $values = $query->distinct()->orderBy($column)->pluck($column)->all();

        return array_combine($values, $values);
    }

    private static function countries(): array
    {
        $values = collect(self::distinct('country'))
            ->merge(DB::table('marketing_subscribers')->whereNotNull('country')->where('country', '<>', '')->distinct()->pluck('country'))
            ->unique()->sort()->values()->all();

        return array_combine($values, $values);
    }

    /** Staff who own at least one client or lead: id => name */
    private static function owners(): array
    {
        return DB::table('users')->whereIn('id', DB::table('organizations')->whereNull('deleted_at')->whereNotNull('assigned_to')
            ->whereIn('status', ['Lead', 'Active Client', 'Past Client'])->select('assigned_to'))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)])->all();
    }
}
