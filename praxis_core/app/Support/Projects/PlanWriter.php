<?php

namespace App\Support\Projects;

use App\Support\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes a plan into a project: its milestones, each with its tasks (every task has a milestone, docs/PROJECTS.md
 * decision 9), through Milestones::create and Tasks::create, so each is logged and each task's lead gets the bell.
 * Extracted from ScrumMaster::commitBacklogToDatabase for PR 5's Create project (docs/OPPORTUNITIES.md section 4,
 * row 5); both call it. Numbers follow the project's existing milestones. Runs inside the caller's transaction.
 */
final class PlanWriter
{
    /**
     * $milestones: [{title, goal, description, weight_percentage, amount (null: weight × total), start_date, due_date,
     * tasks: [{title, description, assigned_to, estimated_hours, due_date}]}]; optional keys may be left out.
     * Returns the new milestones' ids, in order.
     *
     * @return list<string>
     */
    public static function write(string $projectId, array $milestones): array
    {
        $number = Milestones::nextNumber($projectId);
        $ids = [];
        foreach ($milestones as $milestone) {
            $phaseId = Milestones::create($projectId, array_filter([
                'phase_number' => $number++,
                'title' => Str::limit(trim((string) ($milestone['title'] ?? '')) ?: 'Milestone', 140, ''),
                'description' => trim((string) ($milestone['description'] ?? '')) ?: null,
                'goal' => trim((string) ($milestone['goal'] ?? '')) ?: null,
                'weight_percentage' => (float) ($milestone['weight_percentage'] ?? 0),
                'amount' => isset($milestone['amount']) ? number_format((float) $milestone['amount'], 2, '.', '') : null,
                'status' => 'Pending',
                'start_date' => $milestone['start_date'] ?? null,
                'due_date' => $milestone['due_date'] ?? null,
            ], fn ($v) => $v !== null));
            $ids[] = $phaseId;

            foreach ((array) ($milestone['tasks'] ?? []) as $task) {
                if (trim((string) ($task['title'] ?? '')) === '') {
                    continue;
                }
                Tasks::create($projectId, array_filter([
                    'phase_id' => $phaseId,
                    'title' => Str::limit(trim((string) $task['title']), 255, ''),
                    'description' => (string) ($task['description'] ?? ''),
                    'assigned_to' => $task['assigned_to'] ?? null,
                    'estimated_hours' => isset($task['estimated_hours']) && $task['estimated_hours'] !== '' ? (float) $task['estimated_hours'] : null,
                    'due_date' => $task['due_date'] ?? null,
                ], fn ($v) => $v !== null));
            }
        }

        return $ids;
    }

    /** The audit line for the whole plan and the "Project plan ready" bell for the delivery team (projects.edit) */
    public static function announce(string $projectId, string $projectTitle, string $action = 'BACKLOG_ENGINEERED'): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => "Milestones and tasks saved for project: {$projectTitle}",
            'target_table' => 'projects',
            'target_id' => $projectId,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        foreach (Access::staffWith('projects.edit') as $memberId) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $memberId,
                'title' => 'Project plan ready',
                'message' => "The milestones for ‘{$projectTitle}’ are saved. Tasks can now be assigned on their boards.",
                'link_url' => route('hq.ops.project', ['project' => $projectId, 'tab' => 'milestones']),
                'type' => 'Project', 'created_at' => now(),
            ]);
        }
    }
}
