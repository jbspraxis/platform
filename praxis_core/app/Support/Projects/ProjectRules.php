<?php

namespace App\Support\Projects;

use App\Support\Access;
use App\Support\ProjectAccess;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Who may do what on a project page (docs/PROJECTS.md, decision 8 and section 5). Seeing the page and its drawers
 * needs projects.view and the board rule (App\Support\ProjectAccess: managers and auditors see every project,
 * everyone else the projects they lead or have work on). The project's lead and holders of projects.create edit
 * everything; a team member with projects.edit works on the tasks they lead or share (status, steps, comments,
 * due-date proposals). projects.approve still moves anyone's card, as it did on the board before.
 * Every change is written to system_audit_logs through log(), which feeds the drawers' history.
 */
final class ProjectRules
{
    public static function canView(?string $projectId): bool
    {
        $user = Auth::user();

        return $projectId && $user && $user->can('projects.view') && ProjectAccess::maySee($projectId);
    }

    public static function isLead(string $projectId): bool
    {
        return Auth::check() && DB::table('projects')->where('id', $projectId)->where('lead_user_id', Auth::id())->exists();
    }

    /** Milestones, any task, change reports */
    public static function canManage(?string $projectId): bool
    {
        return self::canView($projectId) && (Auth::user()->can('projects.create') || self::isLead($projectId));
    }

    /** Leads the task (tasks.assigned_to) or shares it */
    public static function isOnTask(object $task): bool
    {
        return Auth::check() && ($task->assigned_to === Auth::id()
            || DB::table('task_people')->where('task_id', $task->id)->where('user_id', Auth::id())->where('role', 'sharing')->exists());
    }

    /** Status, steps, comments and due-date proposals on one task */
    public static function canWorkOn(object $task, string $projectId): bool
    {
        return self::canManage($projectId)
            || (self::canView($projectId) && Auth::user()->can('projects.edit') && self::isOnTask($task));
    }

    /** Drag a card or change its status */
    public static function canMove(object $task, string $projectId): bool
    {
        return self::canWorkOn($task, $projectId) || (self::canView($projectId) && Auth::user()->can('projects.approve'));
    }

    /** canMove for many cards at once (the milestone board), with the lookups done once */
    public static function mover(string $projectId): Closure
    {
        $user = Auth::user();
        $any = self::canManage($projectId) || ($user && $user->can('projects.approve') && self::canView($projectId));
        $own = $user && $user->can('projects.edit') && self::canView($projectId);
        $sharing = $own ? DB::table('task_people')->where('user_id', $user->id)->where('role', 'sharing')->pluck('task_id')->flip() : collect();

        return fn ($task) => $any || ($own && (data_get($task, 'assigned_to') === $user->id || $sharing->has(data_get($task, 'id'))));
    }

    /** The contract total, prices and invoice amounts: finance readers and the people who run the project */
    public static function canSeeMoney(string $projectId): bool
    {
        return (bool) Auth::user()?->can('finance.view') || self::canManage($projectId);
    }

    /** Who can lead, share or watch a task: staff with project rights, and anyone already on the project */
    public static function team(string $projectId): Collection
    {
        $taskIds = DB::table('tasks')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
            ->where('project_phases.project_id', $projectId)->pluck('tasks.id');
        $ids = collect(Access::staffWith('projects.edit'))
            ->merge(Access::staffWith('projects.create'))
            ->merge(DB::table('tasks')->whereIn('id', $taskIds)->whereNotNull('assigned_to')->pluck('assigned_to'))
            ->merge(DB::table('task_people')->whereIn('task_id', $taskIds)->pluck('user_id'))
            ->push(DB::table('projects')->where('id', $projectId)->value('lead_user_id'))
            ->filter()->unique()->values();

        return DB::table('users')->whereIn('id', $ids)->where('status', 'Active')->whereNull('deleted_at')
            ->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']);
    }

    /** One line in the audit log; target_table and target_id let the drawers show a record's history */
    public static function log(string $action, string $description, string $table, string $id, array $old = [], array $new = []): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => mb_substr($description, 0, 2000),
            'target_table' => $table,
            'target_id' => $id,
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /** The changed fields only, old and new, for log() */
    public static function diff(array $old, array $new): array
    {
        $changed = array_keys(array_filter($new, fn ($value, $key) => (string) ($old[$key] ?? '') !== (string) ($value ?? ''), ARRAY_FILTER_USE_BOTH));

        return [array_intersect_key($old, array_flip($changed)), array_intersect_key($new, array_flip($changed))];
    }
}
