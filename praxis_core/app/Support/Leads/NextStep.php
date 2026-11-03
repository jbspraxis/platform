<?php

namespace App\Support\Leads;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every open lead has a next step: what, by when (owner's decision 10, docs/LEADS.md section 6). PR 1 stores, shows
 * and edits it; PR 3 builds "My follow-ups" (overdue first, in red) and the morning bell on these two columns:
 * organizations.next_step and next_step_due (a date, Lagos). Open leads are LeadStatus::OPEN.
 */
final class NextStep
{
    public const TZ = 'Africa/Lagos';

    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}next_step" => ['nullable', 'string', 'max:255'],
            "{$prefix}next_step_due" => ['nullable', 'date_format:Y-m-d', "required_with:{$prefix}next_step"],
        ];
    }

    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}next_step.max" => 'Keep the next step under 255 characters.',
            "{$prefix}next_step_due.date_format" => 'Choose a date.',
            "{$prefix}next_step_due.required_with" => 'Choose by when.',
        ];
    }

    /** Set or clear (both empty) the next step, with a timeline line. False when nothing changed. */
    public static function set(Organization $lead, ?string $what, ?string $due, User $by): bool
    {
        $what = trim((string) $what) ?: null;
        $due = $what ? (trim((string) $due) ?: null) : null;
        if ($what && ! $due) {
            throw new LeadRuleBroken('Choose by when.', 'nextStepDue');
        }
        $before = [$lead->next_step, $lead->next_step_due?->format('Y-m-d')];
        if ($before === [$what, $due]) {
            return false;
        }
        DB::table('organizations')->where('id', $lead->id)->update(['next_step' => $what, 'next_step_due' => $due, 'updated_by' => $by->id, 'updated_at' => now()]);
        LeadTimeline::record($lead->id, 'next_step_set', $what ? "Next step: {$what}, by " . self::day($due) . '.' : 'Next step cleared.', [
            'actor' => $by, 'payload' => ['from' => ['what' => $before[0], 'due' => $before[1]], 'to' => ['what' => $what, 'due' => $due]],
        ]);
        $lead->refresh();

        return true;
    }

    /** An open lead whose next step's date has passed (Lagos) */
    public static function isOverdue(object $lead): bool
    {
        $due = $lead->next_step_due ?? null;

        return $due && LeadStatus::isOpen($lead->lead_status ?? null)
            && Carbon::parse($due, self::TZ)->startOfDay()->lt(Carbon::now(self::TZ)->startOfDay());
    }

    /** What the Add a lead form suggests: first contact, by the next working day */
    public static function suggestion(): array
    {
        $day = Carbon::now(self::TZ)->addDay();
        while ($day->isWeekend()) {
            $day->addDay();
        }

        return ['what' => 'Make first contact', 'due' => $day->format('Y-m-d')];
    }

    /** "Tue 4 Nov" (this year) or "4 Nov 2027" */
    public static function day($date): string
    {
        if (! $date) {
            return '';
        }
        $d = Carbon::parse($date, self::TZ);

        return $d->year === Carbon::now(self::TZ)->year ? $d->format('D j M') : $d->format('j M Y');
    }
}
