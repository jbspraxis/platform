<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadNumbers;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\NextStep;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The morning bell of My follow-ups (owner's decision 17, docs/LEADS.md section 9): on working days at 07:30 Lagos
 * time, each person who owns open leads with an overdue next step, one due today, or none at all gets one bell
 * (LeadOwner::bell, so also a phone push) that opens My follow-ups. Once a day at most: running it again rings
 * nobody twice. Owners without Leads access (crm.view) or whose account isn't active are skipped.
 */
class LeadsMorningBell extends Command
{
    protected $signature = 'leads:morning-bell {--dry-run : List who would be rung, and ring nobody}';

    protected $description = 'Ring each lead owner about overdue and today’s follow-ups and open leads with no next step';

    public const TITLE = 'Your follow-ups for today';

    public function handle(): int
    {
        $today = LeadNumbers::today();
        $dayStart = Carbon::now(NextStep::TZ)->startOfDay()->utc();
        $rows = DB::table('organizations')->whereIn('status', LeadAccess::STATUSES)->whereNull('deleted_at')
            ->whereIn('lead_status', \App\Support\Leads\LeadStatus::OPEN)->whereNotNull('assigned_to')
            ->orderByRaw('next_step_due IS NULL, next_step_due')->orderBy('created_at')
            ->get(['id', 'name', 'assigned_to', 'next_step', 'next_step_due']);

        $rung = 0;
        foreach ($rows->groupBy('assigned_to') as $ownerId => $leads) {
            $isNone = fn ($l) => trim((string) $l->next_step) === '' || ! $l->next_step_due;
            $none = $leads->filter($isNone);
            $overdue = $leads->reject($isNone)->filter(fn ($l) => $l->next_step_due < $today);
            $dueToday = $leads->reject($isNone)->filter(fn ($l) => $l->next_step_due === $today);
            if ($overdue->isEmpty() && $dueToday->isEmpty() && $none->isEmpty()) {
                continue;
            }
            $owner = LeadOwner::activeStaff($ownerId);
            if (! $owner || ! LeadAccess::canView($owner)) {
                continue;
            }
            if (DB::table('notifications')->where('user_id', $ownerId)->where('title', self::TITLE)->where('created_at', '>=', $dayStart)->exists()) {
                continue; // rung already today
            }

            $parts = array_filter([
                $overdue->count() ? $overdue->count() . ' overdue' : null,
                $dueToday->count() ? $dueToday->count() . ' due today' : null,
                $none->count() ? $none->count() . ' ' . ($none->count() === 1 ? 'lead' : 'leads') . ' with no next step' : null,
            ]);
            $first = $overdue->first() ?? $dueToday->first() ?? $none->first();
            $message = ucfirst(self::sentence($parts)) . '. '
                . ($first->next_step ? "First: {$first->name}, {$first->next_step} (by " . NextStep::day($first->next_step_due) . ').' : "First: give {$first->name} a next step.");

            $this->line(($this->option('dry-run') ? '[dry run] ' : '') . LeadOwner::name($owner) . ': ' . $message);
            if (! $this->option('dry-run')) {
                LeadOwner::bell($ownerId, self::TITLE, $message, $first->id, route('hq.crm.leads.follow-ups', [], false));
                $rung++;
            }
        }
        $this->info($this->option('dry-run') ? 'Dry run: nobody was rung.' : "Rang {$rung} " . ($rung === 1 ? 'person' : 'people') . '.');

        return self::SUCCESS;
    }

    /** ['2 overdue', '1 due today', '3 leads…'] → "2 overdue, 1 due today and 3 leads…" */
    private static function sentence(array $parts): string
    {
        $parts = array_values($parts);
        $last = array_pop($parts);

        return $parts ? implode(', ', $parts) . ' and ' . $last : (string) $last;
    }
}
