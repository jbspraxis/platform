<?php

namespace App\Support\Projects;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Change reports (docs/PROJECTS.md, decision 7; SOW clause 7.1): work that changes the price is drafted here with its
 * price, effect on the timeline and the milestone and tasks it would add, and recorded with the client's decision.
 *
 * Approved, it joins the plan: its milestone and tasks are created, the contract total grows by its price, the target
 * end date moves by its days, and the milestones' weights are recalculated over the new total so each keeps its
 * amount (weight × total) and the plan still adds up to 100%. Declined, it is kept as declined and nothing else changes.
 */
final class ChangeReports
{
    public const FIELDS = ['title', 'description', 'price', 'timeline_days', 'plan'];

    public const STATUSES = ['draft', 'sent', 'approved', 'declined'];

    public static function find(string $projectId, ?string $id): ?object
    {
        return $id ? DB::table('change_reports')->where('project_id', $projectId)->where('id', $id)->whereNull('deleted_at')->first() : null;
    }

    /** CR-01 */
    public static function label(object|int $report): string
    {
        return 'CR-' . str_pad((string) (is_object($report) ? $report->number : $report), 2, '0', STR_PAD_LEFT);
    }

    public static function isOpen(object $report): bool
    {
        return in_array($report->status, ['draft', 'sent'], true);
    }

    public static function create(string $projectId, array $data): string
    {
        $id = (string) Str::uuid();
        $project = DB::table('projects')->where('id', $projectId)->first(['currency']);
        DB::transaction(function () use ($id, $projectId, $project, $data) {
            $number = (int) DB::table('change_reports')->where('project_id', $projectId)->lockForUpdate()->max('number') + 1;
            DB::table('change_reports')->insert(self::row($data) + [
                'id' => $id, 'project_id' => $projectId, 'number' => $number, 'currency' => $project->currency ?: 'NGN', 'status' => 'draft',
                'created_by' => Auth::id(), 'updated_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        $report = DB::table('change_reports')->where('id', $id)->first();
        ProjectRules::log('CHANGE_REPORT_CREATED', 'Drafted ' . self::label($report) . " “{$report->title}”.", 'change_reports', $id, [], ['price' => $report->price, 'timeline_days' => $report->timeline_days]);

        return $id;
    }

    public static function update(object $report, array $data): void
    {
        $row = self::row($data);
        // MySQL rewrites JSON as it stores it: compare the plan by its content, not its text
        if (array_key_exists('plan', $row) && json_decode((string) $report->plan, true) == $data['plan']) {
            unset($row['plan']);
        }
        [$before, $after] = ProjectRules::diff((array) $report, $row);
        if (! $after) {
            return;
        }
        DB::table('change_reports')->where('id', $report->id)->update($after + ['updated_by' => Auth::id(), 'updated_at' => now()]);
        ProjectRules::log('CHANGE_REPORT_UPDATED', 'Changed ' . self::label($report) . ': ' . implode(', ', array_keys($after)) . '.', 'change_reports', $report->id,
            array_diff_key($before, ['plan' => 1]), array_diff_key($after, ['plan' => 1]));
    }

    public static function markSent(object $report): void
    {
        DB::table('change_reports')->where('id', $report->id)->where('status', 'draft')->update(['status' => 'sent', 'updated_by' => Auth::id(), 'updated_at' => now()]);
        ProjectRules::log('CHANGE_REPORT_SENT', 'Marked ' . self::label($report) . ' as sent to the client.', 'change_reports', $report->id, ['status' => 'draft'], ['status' => 'sent']);
    }

    /**
     * Record the client's decision. Approval adds the milestone and its tasks, the price to the contract total and the
     * days to the target end date, all at once. Returns the new milestone's id when approved.
     */
    public static function decide(object $report, bool $approved, string $on, string $name, ?string $title): ?string
    {
        $decision = ['status' => $approved ? 'approved' : 'declined', 'decided_on' => $on, 'decided_by_name' => $name, 'decided_by_title' => $title ?: null];

        return DB::transaction(function () use ($report, $approved, $decision) {
            $fresh = DB::table('change_reports')->where('id', $report->id)->lockForUpdate()->first();
            if (! $fresh || ! self::isOpen($fresh)) {
                return null;
            }
            $phaseId = $approved ? self::joinPlan($fresh) : null;
            DB::table('change_reports')->where('id', $fresh->id)->update($decision + ['phase_id' => $phaseId, 'updated_by' => Auth::id(), 'updated_at' => now()]);
            ProjectRules::log($approved ? 'CHANGE_REPORT_APPROVED' : 'CHANGE_REPORT_DECLINED',
                self::label($fresh) . " “{$fresh->title}” " . ($approved ? 'approved' : 'declined') . " by {$decision['decided_by_name']} on {$decision['decided_on']}.",
                'change_reports', $fresh->id, ['status' => $fresh->status], $decision + ['phase_id' => $phaseId]);

            return $phaseId;
        });
    }

    /** What approving would do to the weights: [new milestone's weight, factor applied to the others, new total] */
    public static function preview(object $report): array
    {
        $project = DB::table('projects')->where('id', $report->project_id)->first(['total_budget']);
        $old = (float) $project->total_budget;
        $new = $old + (float) $report->price;

        return [
            'weight' => $new > 0 ? round((float) $report->price / $new * 100, 2) : 0.0,
            'factor' => $new > 0 && $old > 0 ? $old / $new : 1.0,
            'total' => $new,
        ];
    }

    /** Inside decide()'s transaction: the milestone, its tasks, the total, the dates and the weights */
    private static function joinPlan(object $report): string
    {
        $project = DB::table('projects')->where('id', $report->project_id)->lockForUpdate()->first();
        $preview = self::preview($report);
        $plan = json_decode((string) $report->plan, true) ?: [];
        $milestone = $plan['milestone'] ?? [];

        // Every milestone keeps its amount: its share shrinks by old total / new total
        $phases = DB::table('project_phases')->where('project_id', $project->id)->whereNull('deleted_at')->get(['id', 'weight_percentage']);
        $before = round((float) $phases->sum('weight_percentage'), 2);
        $after = 0.0;
        if ((float) $project->total_budget > 0) {
            foreach ($phases as $phase) {
                $weight = round((float) $phase->weight_percentage * $preview['factor'], 2);
                $after += $weight;
                DB::table('project_phases')->where('id', $phase->id)->update(['weight_percentage' => $weight, 'updated_by' => Auth::id(), 'updated_at' => now()]);
            }
        }
        // A plan that added up to 100% still does: the rounding goes to the new milestone
        $weight = abs($before - 100) < 0.01 && (float) $project->total_budget > 0 ? round(100 - $after, 2) : $preview['weight'];

        $phaseId = Milestones::create($project->id, [
            'phase_number' => Milestones::nextNumber($project->id),
            'title' => trim($milestone['title'] ?? '') ?: $report->title,
            'description' => trim($milestone['description'] ?? '') ?: ($report->description ?: null),
            'weight_percentage' => max(0, $weight),
            'status' => 'Pending',
            'due_date' => ($milestone['due_date'] ?? null) ?: null,
            'notes' => self::label($report) . ', approved ' . now(ProjectPlan::TIME_ZONE)->toDateString() . '.',
        ]);
        foreach ($plan['tasks'] ?? [] as $task) {
            if (trim($task['title'] ?? '') !== '') {
                Tasks::create($project->id, ['title' => Str::limit(trim($task['title']), 252), 'phase_id' => $phaseId]);
            }
        }

        $total = round((float) $project->total_budget + (float) $report->price, 2);
        $end = $project->target_end_date && (int) $report->timeline_days !== 0
            ? Carbon::parse($project->target_end_date)->addDays((int) $report->timeline_days)->toDateString()
            : $project->target_end_date;
        DB::table('projects')->where('id', $project->id)->update(['total_budget' => $total, 'target_end_date' => $end, 'updated_by' => Auth::id(), 'updated_at' => now()]);
        ProjectRules::log('PROJECT_CHANGED_BY_REPORT', self::label($report) . " added to the plan: contract total {$project->total_budget} → {$total}"
            . ($end !== $project->target_end_date ? ", target end {$project->target_end_date} → {$end}" : '') . '; milestone weights recalculated over the new total.',
            'projects', $project->id, ['total_budget' => $project->total_budget, 'target_end_date' => $project->target_end_date], ['total_budget' => $total, 'target_end_date' => $end]);

        return $phaseId;
    }

    private static function row(array $data): array
    {
        $row = array_intersect_key($data, array_flip(self::FIELDS));
        if (array_key_exists('plan', $row)) {
            $row['plan'] = json_encode($row['plan'], JSON_UNESCAPED_UNICODE);
        }

        return $row;
    }
}
