<?php

namespace App\Support\Leads;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Leads numbers (owner's decisions 17 and 18, docs/LEADS.md section 9): the summary strip above the list, its
 * per-owner view, and the lists behind My follow-ups. Every figure is for an owner scope:
 *
 *   'mine'  the person looking (the list's default: decision 8's Mine filter)
 *   'all'   everyone's leads
 *   'none'  leads nobody owns
 *   <id>    one person's leads
 *
 * and, where it says so, a period on the Lagos calendar (PERIODS). Leads are the Leads list's organisations
 * (LeadAccess::STATUSES, not deleted).
 */
final class LeadNumbers
{
    public const PERIODS = ['week' => 'This week', 'month' => 'This month', 'quarter' => 'This quarter', 'year' => 'This year', 'all' => 'All time'];

    /** Proposal versions that no longer count for an opportunity's value */
    private const SET_ASIDE = ['Superseded', 'Rejected', 'Expired'];

    /** An open lead with no next step: nothing to do, or no date for it (as NextStep stores them: trimmed, null when empty) */
    private const NO_STEP = "(organizations.next_step IS NULL OR TRIM(organizations.next_step) = '' OR organizations.next_step_due IS NULL)";

    /** The value of leads with no open opportunity */
    private const NO_VALUE = ['totals' => [], 'open' => 0, 'priced' => 0];

    /** The period's start (UTC; null for all time), counted from the start of the day, week, month… in Lagos */
    public static function since(string $period): ?Carbon
    {
        $now = Carbon::now(NextStep::TZ);

        return match ($period) {
            'week' => $now->startOfWeek(Carbon::MONDAY)->utc(),
            'quarter' => $now->firstOfQuarter()->startOfDay()->utc(),
            'year' => $now->startOfYear()->utc(),
            'all' => null,
            default => $now->startOfMonth()->utc(),
        };
    }

    /** "this month", "all time": for captions */
    public static function periodText(string $period): string
    {
        return mb_strtolower(self::PERIODS[$period] ?? self::PERIODS['month']);
    }

    /** The Leads list's organisations, with the owner scope applied */
    public static function leads(string $owner, User $me): Builder
    {
        $q = DB::table('organizations')->whereIn('organizations.status', LeadAccess::STATUSES)->whereNull('organizations.deleted_at')
            ->whereNotNull('organizations.lead_status');

        return match ($owner) {
            'all' => $q,
            'none' => $q->whereNull('organizations.assigned_to'),
            'mine' => $q->where('organizations.assigned_to', $me->id),
            default => $q->where('organizations.assigned_to', $owner),
        };
    }

    /**
     * The summary strip:
     *   new         ['week', 'month', 'period']: leads added (created) in those spans
     *   status      lead_status => count, of the leads added in the period (every lead for all time)
     *   conversion  ['clients', 'leads', 'rate' (a percentage, null without leads)]: of the leads added in the period,
     *               those that are clients now; clients from before the revamp (client_via existing) are left out
     *   value       ['totals' => [currency => amount], 'open' => open opportunities, 'priced' => those with a proposal]:
     *               each open opportunity's current proposal, before VAT
     *   overdue     open leads whose next step's date has passed; noStep: open leads without one
     */
    public static function summary(string $owner, string $period, User $me): array
    {
        $rows = self::counted(self::leads($owner, $me), $period, 'organizations.lead_status');
        $status = $rows->mapWithKeys(fn ($r) => [$r->grp => (int) $r->added]);

        return [
            'new' => [
                'week' => (int) $rows->sum('new_week'),
                'month' => (int) $rows->sum('new_month'),
                'period' => array_sum($status->all()),
            ],
            'status' => collect(LeadStatus::LABELS)->map(fn ($label, $key) => $status[$key] ?? 0)->all(),
            'conversion' => self::conversion((int) $rows->sum('clients'), (int) $rows->sum('cohort')),
            'value' => self::openValue(self::leads($owner, $me))[''] ?? self::NO_VALUE,
            'overdue' => (int) $rows->sum('overdue'),
            'noStep' => (int) $rows->sum('no_step'),
        ];
    }

    /**
     * The per-owner view: one row per person who owns leads (and one for leads nobody owns), with the same figures
     * as the strip: new in the period, open, overdue, no next step, conversion, open opportunity value. Counted in one
     * query grouped by owner, and the opportunities' value in one more.
     */
    public static function byOwner(string $period, User $me): Collection
    {
        $rows = self::counted(self::leads('all', $me)->leftJoin('users', 'users.id', '=', 'organizations.assigned_to')
            ->groupBy('users.first_name', 'users.last_name', 'users.avatar_path')
            ->addSelect('users.first_name', 'users.last_name', 'users.avatar_path'), $period, 'organizations.assigned_to');
        $values = self::openValue(self::leads('all', $me), 'organizations.assigned_to');

        return $rows->map(function ($o) use ($values) {
            $id = $o->grp;

            return (object) [
                'id' => $id, 'scope' => $id ?? 'none', 'name' => $id ? trim($o->first_name . ' ' . $o->last_name) : 'No owner',
                'first_name' => $o->first_name, 'last_name' => $o->last_name, 'avatar_path' => $o->avatar_path,
                'new' => (int) $o->added, 'open' => (int) $o->open_leads, 'overdue' => (int) $o->overdue, 'noStep' => (int) $o->no_step,
                'conversion' => self::conversion((int) $o->clients, (int) $o->cohort), 'value' => $values[(string) $id] ?? self::NO_VALUE,
            ];
        })->sortBy(fn ($o) => [$o->id === null ? 1 : 0, $o->name])->values();
    }

    /**
     * The strip's figures for these leads in one query, a row per $group (lead_status or the owner): added in the
     * period, the cohort (added in the period, not a client from before the revamp) and the clients among it, new this
     * week and this month, open, overdue and with no next step. Each figure keeps its definition from summary().
     */
    private static function counted(Builder $leads, string $period, string $group): Collection
    {
        $since = self::since($period);
        $open = 'organizations.lead_status IN (' . implode(', ', array_fill(0, count(LeadStatus::OPEN), '?')) . ')';
        $added = $since ? 'organizations.created_at >= ?' : '1 = 1';
        $cohort = "{$added} AND (organizations.client_via IS NULL OR organizations.client_via != 'existing')";
        $sum = fn (string $when) => "SUM(CASE WHEN {$when} THEN 1 ELSE 0 END)";
        $at = $since ? [$since] : [];

        return $leads->groupBy($group)->addSelect(DB::raw("{$group} as grp"))
            ->selectRaw($sum($added) . ' as added', $at)
            ->selectRaw($sum($cohort) . ' as cohort', $at)
            ->selectRaw($sum("{$cohort} AND organizations.lead_status = 'client'") . ' as clients', $at)
            ->selectRaw($sum('organizations.created_at >= ?') . ' as new_week', [self::since('week')])
            ->selectRaw($sum('organizations.created_at >= ?') . ' as new_month', [self::since('month')])
            ->selectRaw($sum($open) . ' as open_leads', LeadStatus::OPEN)
            ->selectRaw($sum("{$open} AND organizations.next_step_due < ?") . ' as overdue', [...LeadStatus::OPEN, self::today()])
            ->selectRaw($sum("{$open} AND (organizations.next_step IS NULL OR organizations.next_step = '')") . ' as no_step', LeadStatus::OPEN)
            ->get();
    }

    /** Of the leads added in the period (not clients from before the revamp), those that are clients now */
    private static function conversion(int $clients, int $leads): array
    {
        return ['clients' => $clients, 'leads' => $leads, 'rate' => $leads ? round($clients * 100 / $leads, 1) : null];
    }

    /**
     * My follow-ups: the open leads in the scope, by when their next step is due (Lagos):
     *   overdue (oldest first), today, week (the next 7 days), later, none (no next step: flagged)
     * Overdue, today and week are always whole; later and none stop at $limits[group] leads when given (followUpCounts()
     * says how many each has). The main contact is read for the leads listed only.
     */
    public static function followUps(string $owner, User $me, array $limits = []): array
    {
        [$today, $week] = [self::today(), Carbon::now(NextStep::TZ)->addDays(7)->format('Y-m-d')];
        $rows = fn (string $where, array $bindings, ?int $limit) => self::open($owner, $me)->whereRaw($where, $bindings)
            ->leftJoin('users as owner', 'owner.id', '=', 'organizations.assigned_to')
            ->orderByRaw('organizations.next_step_due IS NULL, organizations.next_step_due')->orderBy('organizations.created_at')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get(['organizations.id', 'organizations.name', 'organizations.lead_status', 'organizations.next_step', 'organizations.next_step_due', 'organizations.industry',
                'organizations.country', 'organizations.phone', 'organizations.email', 'organizations.last_activity_at', 'organizations.created_at', 'organizations.assigned_to',
                'owner.first_name as owner_first', 'owner.last_name as owner_last', 'owner.avatar_path as owner_avatar']);

        $soon = $rows('NOT ' . self::NO_STEP . ' AND organizations.next_step_due <= ?', [$week], null);
        $groups = [
            'overdue' => $soon->filter(fn ($r) => $r->next_step_due < $today)->values(),
            'today' => $soon->filter(fn ($r) => $r->next_step_due === $today)->values(),
            'week' => $soon->filter(fn ($r) => $r->next_step_due > $today)->values(),
            'later' => $rows('NOT ' . self::NO_STEP . ' AND organizations.next_step_due > ?', [$week], $limits['later'] ?? null),
            'none' => $rows(self::NO_STEP, [], $limits['none'] ?? null),
        ];

        $all = collect($groups)->flatten(1);
        $contacts = DB::table('client_users')->whereIn('organization_id', $all->pluck('id'))->whereNull('deleted_at')
            ->orderByDesc('is_main_contact')->orderBy('created_at')->get(['organization_id', 'first_name', 'last_name', 'email', 'phone', 'job_title'])
            ->groupBy('organization_id')->map->first();
        $all->each(function ($r) use ($contacts) {
            $c = $contacts[$r->id] ?? null;
            $r->contact_name = $c ? trim($c->first_name . ' ' . $c->last_name) : null;
            $r->contact_title = $c->job_title ?? null;
            $r->contact_phone = $c->phone ?? $r->phone;
            $r->contact_email = $c->email ?? $r->email;
        });

        return $groups;
    }

    /** How many open leads in the scope each group of My follow-ups has, in one query */
    public static function followUpCounts(string $owner, User $me): array
    {
        [$today, $week] = [self::today(), Carbon::now(NextStep::TZ)->addDays(7)->format('Y-m-d')];
        $dated = 'NOT ' . self::NO_STEP . ' AND organizations.next_step_due';
        $sum = fn (string $when) => "SUM(CASE WHEN {$when} THEN 1 ELSE 0 END)";
        $row = self::open($owner, $me)
            ->selectRaw($sum("{$dated} < ?") . ' as n_overdue', [$today])
            ->selectRaw($sum("{$dated} = ?") . ' as n_today', [$today])
            ->selectRaw($sum("{$dated} > ? AND organizations.next_step_due <= ?") . ' as n_week', [$today, $week])
            ->selectRaw($sum("{$dated} > ?") . ' as n_later', [$week])
            ->selectRaw($sum(self::NO_STEP) . ' as n_none')
            ->first();

        return collect(['overdue', 'today', 'week', 'later', 'none'])->mapWithKeys(fn ($k) => [$k => (int) ($row->{'n_' . $k} ?? 0)])->all();
    }

    /** Open leads (LeadStatus::OPEN) in the scope */
    public static function open(string $owner, User $me): Builder
    {
        return self::leads($owner, $me)->whereIn('organizations.lead_status', LeadStatus::OPEN);
    }

    /** Today in Lagos, Y-m-d (next_step_due is a Lagos date) */
    public static function today(): string
    {
        return Carbon::now(NextStep::TZ)->format('Y-m-d');
    }

    /**
     * The open opportunities of these leads and what their current proposals are worth, before VAT, by currency:
     * ['' => figures] for all of them, or per value of $by (the organisation's owner) when it is given
     */
    private static function openValue(Builder $leads, ?string $by = null): array
    {
        $open = DB::table('opportunities')->join('organizations', 'organizations.id', '=', 'opportunities.organization_id')
            ->whereNull('opportunities.deleted_at')->whereNotIn('opportunities.stage', \App\Models\Opportunity::CLOSED)
            ->whereIn('opportunities.organization_id', $leads->select('organizations.id'))
            ->get(['opportunities.id', DB::raw(($by ?? "''") . ' as grp')])->pluck('grp', 'id');
        // each opportunity's current proposal: its latest version not set aside
        $current = $open->isEmpty() ? collect() : DB::table('proposals')->whereIn('opportunity_id', $open->keys())->whereNull('deleted_at')
            ->whereNotIn('status', self::SET_ASIDE)->orderByDesc('version')->orderByDesc('created_at')
            ->get(['opportunity_id', 'currency', 'subtotal'])->unique('opportunity_id');
        $priced = $current->groupBy(fn ($p) => (string) $open[$p->opportunity_id]);

        return $open->groupBy(fn ($grp) => (string) $grp, true)->map(fn ($ids, $grp) => [
            'totals' => ($priced[$grp] ?? collect())->groupBy('currency')->map(fn ($rows) => (float) $rows->sum('subtotal'))->sortDesc()->all(),
            'open' => $ids->count(),
            'priced' => ($priced[$grp] ?? collect())->count(),
        ])->all();
    }
}
