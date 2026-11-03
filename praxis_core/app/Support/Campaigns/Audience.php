<?php

namespace App\Support\Campaigns;

use App\Support\MailLocale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who a broadcast reaches (docs/CAMPAIGNS.md 4.2): the people a segment's rules pick, one per address, minus the
 * do-not-email list. Only people who already know us: contacts of clients and of leads, Kaizen writers and website
 * subscribers. Outreach prospects (C2) live in their own table and are never part of an audience.
 *
 * Rules: {"who": ["clients", "leads", "subscribers"], "filters": {"industry": ["Banking"], "language": ["fr"], …}}.
 * "subscribers" covers the subscriber list and Kaizen writers (owner, 3 Nov 2026).
 *
 * Always left out: test organisations, JBS Praxis itself and suspended clients, deleted or suspended contacts and
 * contacts without an email. An address that unsubscribed as a contact or a subscriber, or is on the do-not-email
 * list, is left out of every group (summary()'s left_out counts them).
 */
final class Audience
{
    public const WHO = ['clients' => 'Clients', 'leads' => 'Leads', 'subscribers' => 'Subscribers'];

    /** Organisation statuses of each group */
    private const STATUSES = ['clients' => ['Active Client', 'Past Client'], 'leads' => ['Lead']];

    /** When one address is in two groups, the first here wins (it decides the footer's "why you got this") */
    private const ORDER = ['client' => 0, 'lead' => 1, 'kaizen' => 2, 'subscriber' => 3];

    /** The rules in their one stored form: known groups and filters only, values as lists of strings */
    public static function rules(?array $rules): array
    {
        $who = array_values(array_intersect(array_keys(self::WHO), array_map('strval', (array) ($rules['who'] ?? []))));
        $filters = [];
        foreach (array_keys(SegmentFilters::definitions()) as $key) {
            $values = array_values(array_unique(array_filter(array_map('strval', (array) ($rules['filters'][$key] ?? [])), fn ($v) => $v !== '')));
            if ($values) {
                $filters[$key] = $values;
            }
        }

        return ['who' => $who, 'filters' => $filters];
    }

    /**
     * Everyone the rules reach, one row per address, as arrays: email, first_name, last_name, company, kind
     * (client, lead, kaizen, subscriber), contact_id, subscriber_id, locale.
     */
    public static function recipients(?array $rules): Collection
    {
        return self::resolve(self::rules($rules))['recipients'];
    }

    /** Live numbers for HQ: total, per kind, per language, and how many addresses the do-not-email list left out */
    public static function summary(?array $rules): array
    {
        $resolved = self::resolve(self::rules($rules));
        $people = $resolved['recipients'];

        return [
            'total' => $people->count(),
            'by_kind' => $people->countBy('kind')->all(),
            'by_language' => ['en' => $people->where('locale', 'en')->count(), 'fr' => $people->where('locale', 'fr')->count()],
            'left_out' => $resolved['suppressed'],
        ];
    }

    /** The rules in words: "Clients and leads · Industry: Banking · Language: French" */
    public static function describe(?array $rules): string
    {
        $rules = self::rules($rules);
        $who = array_map(fn ($w) => self::WHO[$w], $rules['who']);
        $parts = [$who ? ucfirst(mb_strtolower(implode(count($who) > 2 ? ', ' : ' and ', $who))) : 'Nobody chosen yet'];
        if (count($who) > 2) {
            $parts[0] = 'Clients, leads and subscribers';
        }
        $definitions = SegmentFilters::definitions();
        foreach ($rules['filters'] as $key => $values) {
            $options = SegmentFilters::options($key);
            $parts[] = $definitions[$key]['label'] . ': ' . implode(', ', array_map(fn ($v) => $options[$v] ?? $v, $values));
        }

        return implode(' · ', $parts);
    }

    /** @return array{recipients: Collection, suppressed: int} */
    private static function resolve(array $rules): array
    {
        $who = $rules['who'];
        $filters = $rules['filters'];
        $rows = collect();

        foreach (['clients', 'leads'] as $group) {
            if (in_array($group, $who, true)) {
                $rows = $rows->merge(self::contacts($group, $filters));
            }
        }
        if (in_array('subscribers', $who, true)) {
            $rows = $rows->merge(self::contacts('kaizen', $filters))->merge(self::subscribers($filters));
        }

        // Language is worked out per person, so it filters here
        if ($languages = $filters['language'] ?? []) {
            $rows = $rows->filter(fn ($r) => in_array($r['locale'], $languages, true));
        }

        // An address that unsubscribed anywhere (a contact, a subscriber, the do-not-email list) is out everywhere;
        // then one email per address
        $rows = $rows->filter(fn ($r) => Marketing::isValid($r['email']));
        $optedOut = self::suppressedAmong($rows->pluck('email'));
        foreach ($rows->where('opted_out', true) as $r) {
            $optedOut[$r['email']] = true;
        }
        $recipients = $rows->reject(fn ($r) => isset($optedOut[$r['email']]))
            ->sortBy(fn ($r) => self::ORDER[$r['kind']])
            ->unique('email')
            ->sortBy([['kind_order', 'asc'], ['last_name', 'asc'], ['first_name', 'asc']])
            ->map(fn ($r) => array_diff_key($r, ['kind_order' => 1, 'opted_out' => 1]))
            ->values();

        return ['recipients' => $recipients, 'suppressed' => count($optedOut)];
    }

    /** Contacts of one group: clients, leads, or Kaizen writers (contacts filed under the Kaizen contributors) */
    private static function contacts(string $group, array $filters): Collection
    {
        $query = DB::table('client_users as cu')
            ->join('organizations as o', 'o.id', '=', 'cu.organization_id')
            ->whereNull('cu.deleted_at')->whereNull('o.deleted_at')
            ->where('cu.status', 'Active')
            ->whereNotNull('cu.email')->where('cu.email', '<>', '');
        if (self::hasTestFlag()) {
            $query->where('o.is_test', 0);
        }

        if ($group === 'kaizen') {
            $query->where('cu.lifecycle_stage', 'Public Contributor')->where('o.status', 'Internal');
        } else {
            $query->whereIn('o.status', self::STATUSES[$group])->where('cu.lifecycle_stage', '<>', 'Public Contributor');
        }
        if (! SegmentFilters::apply($query, $group, $filters)) {
            return collect();
        }

        $kind = ['clients' => 'client', 'leads' => 'lead', 'kaizen' => 'kaizen'][$group];

        return $query->get(['cu.id', 'cu.email', 'cu.first_name', 'cu.last_name', 'cu.is_subscribed', 'o.name as company', 'o.preferred_language', 'o.country'])
            ->map(fn ($r) => [
                'email' => Marketing::normalize($r->email),
                'first_name' => (string) $r->first_name,
                'last_name' => (string) $r->last_name,
                'company' => $kind === 'kaizen' ? '' : (string) $r->company,
                'kind' => $kind,
                'kind_order' => self::ORDER[$kind],
                'contact_id' => $r->id,
                'subscriber_id' => null,
                // Kaizen writers share one organisation, whose language and country say nothing about them
                'locale' => $kind === 'kaizen' ? 'en' : MailLocale::forContact(null, $r->preferred_language, $r->country),
                'opted_out' => ! $r->is_subscribed,
            ]);
    }

    private static function subscribers(array $filters): Collection
    {
        $query = DB::table('marketing_subscribers as s');
        if (! SegmentFilters::apply($query, 'subscribers', $filters)) {
            return collect();
        }

        return $query->get()->map(fn ($r) => [
            'email' => Marketing::normalize($r->email),
            'first_name' => (string) $r->first_name,
            'last_name' => (string) $r->last_name,
            'company' => (string) $r->organisation,
            'kind' => 'subscriber',
            'kind_order' => self::ORDER['subscriber'],
            'contact_id' => null,
            'subscriber_id' => $r->id,
            'locale' => MailLocale::forContact($r->language, null, $r->country),
            'opted_out' => $r->unsubscribed_at !== null,
        ]);
    }

    /** The addresses among these that are on the do-not-email list, as a set */
    private static function suppressedAmong(Collection $emails): array
    {
        $found = [];
        foreach ($emails->map(fn ($e) => Marketing::normalize($e))->chunk(1000) as $chunk) {
            foreach (DB::table('marketing_suppressions')->whereIn('email', $chunk->values()->all())->pluck('email') as $email) {
                $found[Marketing::normalize($email)] = true;
            }
        }

        return $found;
    }

    private static function hasTestFlag(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('organizations', 'is_test');
    }
}
