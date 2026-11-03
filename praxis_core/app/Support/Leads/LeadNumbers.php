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
        $since = self::since($period);
        $added = fn () => self::leads($owner, $me)->when($since, fn ($q, $s) => $q->where('organizations.created_at', '>=', $s));

        $status = $added()->groupBy('organizations.lead_status')->selectRaw('organizations.lead_status as s, COUNT(*) as n')->pluck('n', 's')->map(fn ($n) => (int) $n);
        $cohort = $added()->where(fn ($q) => $q->whereNull('organizations.client_via')->orWhere('organizations.client_via', '!=', 'existing'));
        $leads = (clone $cohort)->count();
        $clients = (clone $cohort)->where('organizations.lead_status', 'client')->count();

        return [
            'new' => [
                'week' => self::leads($owner, $me)->where('organizations.created_at', '>=', self::since('week'))->count(),
                'month' => self::leads($owner, $me)->where('organizations.created_at', '>=', self::since('month'))->count(),
                'period' => array_sum($status->all()),
            ],
            'status' => collect(LeadStatus::LABELS)->map(fn ($label, $key) => $status[$key] ?? 0)->all(),
            'conversion' => ['clients' => $clients, 'leads' => $leads, 'rate' => $leads ? round($clients * 100 / $leads, 1) : null],
            'value' => self::openValue(self::leads($owner, $me)),
            'overdue' => self::open($owner, $me)->where('organizations.next_step_due', '<', self::today())->count(),
            'noStep' => self::open($owner, $me)->where(fn ($q) => $q->whereNull('organizations.next_step')->orWhere('organizations.next_step', ''))->count(),
        ];
    }

    /**
     * The per-owner view: one row per person who owns leads (and one for leads nobody owns), with the same figures
     * as the strip: new in the period, open, overdue, no next step, conversion, open opportunity value.
     */
    public static function byOwner(string $period, User $me): Collection
    {
        $owners = self::leads('all', $me)->leftJoin('users', 'users.id', '=', 'organizations.assigned_to')
            ->groupBy('organizations.assigned_to', 'users.first_name', 'users.last_name', 'users.avatar_path')
            ->orderBy('users.first_name')
            ->get(['organizations.assigned_to as id', 'users.first_name', 'users.last_name', 'users.avatar_path']);

        return $owners->map(function ($o) use ($period, $me) {
            $scope = $o->id ?? 'none';
            $s = self::summary($scope, $period, $me);

            return (object) [
                'id' => $o->id, 'scope' => $scope, 'name' => $o->id ? trim($o->first_name . ' ' . $o->last_name) : 'No owner',
                'first_name' => $o->first_name, 'last_name' => $o->last_name, 'avatar_path' => $o->avatar_path,
                'new' => $s['new']['period'], 'open' => self::open($scope, $me)->count(), 'overdue' => $s['overdue'], 'noStep' => $s['noStep'],
                'conversion' => $s['conversion'], 'value' => $s['value'],
            ];
        })->sortBy(fn ($o) => [$o->id === null ? 1 : 0, $o->name])->values();
    }

    /**
     * My follow-ups: the open leads in the scope, by when their next step is due (Lagos):
     *   overdue (oldest first), today, week (the next 7 days), later, none (no next step: flagged)
     */
    public static function followUps(string $owner, User $me): array
    {
        $today = self::today();
        $week = Carbon::now(NextStep::TZ)->addDays(7)->format('Y-m-d');
        $rows = self::open($owner, $me)->leftJoin('users as owner', 'owner.id', '=', 'organizations.assigned_to')
            ->orderByRaw('organizations.next_step_due IS NULL, organizations.next_step_due')->orderBy('organizations.created_at')
            ->get(['organizations.id', 'organizations.name', 'organizations.lead_status', 'organizations.next_step', 'organizations.next_step_due', 'organizations.industry',
                'organizations.country', 'organizations.phone', 'organizations.email', 'organizations.last_activity_at', 'organizations.created_at', 'organizations.assigned_to',
                'owner.first_name as owner_first', 'owner.last_name as owner_last', 'owner.avatar_path as owner_avatar']);

        $contacts = DB::table('client_users')->whereIn('organization_id', $rows->pluck('id'))->whereNull('deleted_at')
            ->orderByDesc('is_main_contact')->orderBy('created_at')->get(['organization_id', 'first_name', 'last_name', 'email', 'phone', 'job_title'])
            ->groupBy('organization_id')->map->first();
        $rows->each(function ($r) use ($contacts) {
            $c = $contacts[$r->id] ?? null;
            $r->contact_name = $c ? trim($c->first_name . ' ' . $c->last_name) : null;
            $r->contact_title = $c->job_title ?? null;
            $r->contact_phone = $c->phone ?? $r->phone;
            $r->contact_email = $c->email ?? $r->email;
        });

        $none = fn ($r) => trim((string) $r->next_step) === '' || ! $r->next_step_due;

        return [
            'overdue' => $rows->filter(fn ($r) => ! $none($r) && $r->next_step_due < $today)->values(),
            'today' => $rows->filter(fn ($r) => ! $none($r) && $r->next_step_due === $today)->values(),
            'week' => $rows->filter(fn ($r) => ! $none($r) && $r->next_step_due > $today && $r->next_step_due <= $week)->values(),
            'later' => $rows->filter(fn ($r) => ! $none($r) && $r->next_step_due > $week)->values(),
            'none' => $rows->filter($none)->values(),
        ];
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

    /** The open opportunities of these leads and what their current proposals are worth, before VAT, by currency */
    private static function openValue(Builder $leads): array
    {
        $open = DB::table('opportunities')->whereNull('deleted_at')->whereNotIn('stage', \App\Models\Opportunity::CLOSED)
            ->whereIn('organization_id', $leads->select('organizations.id'))->pluck('id');
        // each opportunity's current proposal: its latest version not set aside
        $current = $open->isEmpty() ? collect() : DB::table('proposals')->whereIn('opportunity_id', $open)->whereNull('deleted_at')
            ->whereNotIn('status', self::SET_ASIDE)->orderByDesc('version')->orderByDesc('created_at')
            ->get(['opportunity_id', 'currency', 'subtotal'])->unique('opportunity_id');

        return [
            'totals' => $current->groupBy('currency')->map(fn ($rows) => (float) $rows->sum('subtotal'))->sortDesc()->all(),
            'open' => $open->count(),
            'priced' => $current->count(),
        ];
    }
}
