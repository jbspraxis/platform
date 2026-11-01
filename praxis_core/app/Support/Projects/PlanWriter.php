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
     * Returns the new milestones' ids, in order. $notify false: no bell per task (the caller rings each person once,
     * tellLeads()).
     *
     * @return list<string>
     */
    public static function write(string $projectId, array $milestones, bool $notify = true): array
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
                ], fn ($v) => $v !== null), $notify);
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

        // In each person's language (Tasks::localeFor), not the screen language of whoever saved the plan
        foreach (Access::staffWith('projects.edit') as $memberId) {
            $locale = Tasks::localeFor($memberId);
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $memberId,
                'title' => __('projects.bell.plan_title', [], $locale),
                'message' => __('projects.bell.plan', ['project' => $projectTitle], $locale),
                'link_url' => route('hq.ops.project', ['project' => $projectId, 'tab' => 'milestones']),
                'type' => 'Project', 'created_at' => now(),
            ]);
        }
    }

    /**
     * One bell per person for the tasks of a new plan they lead ($leadIds: one entry per task, nulls ignored), opening
     * the project's backlog filtered to them; never the person who saved the plan
     */
    public static function tellLeads(string $projectId, string $projectTitle, array $leadIds): void
    {
        $counts = array_count_values(array_filter($leadIds, fn ($id) => is_string($id) && $id !== '' && $id !== Auth::id()));
        $active = DB::table('users')->whereIn('id', array_keys($counts))->where('status', 'Active')->whereNull('deleted_at')->pluck('id');
        foreach ($active as $userId) {
            $locale = Tasks::localeFor($userId);
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $userId,
                'title' => __('projects.bell.tasks_title', [], $locale),
                'message' => trans_choice('projects.bell.tasks', $counts[$userId], ['count' => $counts[$userId], 'project' => $projectTitle], $locale),
                'link_url' => route('hq.ops.project', ['project' => $projectId, 'tab' => 'backlog', 'lead' => $userId]),
                'type' => 'Project', 'created_at' => now(),
            ]);
        }
    }
}
