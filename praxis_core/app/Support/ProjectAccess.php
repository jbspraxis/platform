<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Which projects a staff member sees in Operations (project pages, sprint boards, QA tickets). Managers and
 * auditors (projects.approve or projects.export) see every project; everyone else only the projects they lead
 * or have a task on (leading, sharing or watching it). The project page, sprint boards, their list and the QA
 * hub all use this one rule.
 */
class ProjectAccess
{
    public static function seesEveryProject(): bool
    {
        $user = Auth::user();

        return $user && ($user->can('projects.approve') || $user->can('projects.export'));
    }

    /**
     * Projects where the signed-in person leads a task, shares or watches one, or leads the project itself
     * (docs/PROJECTS.md). tasks has no project_id: bridge through project_phases.
     */
    public static function assignedProjectIds(): array
    {
        $onTask = DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where(fn ($q) => $q->where('tasks.assigned_to', Auth::id())
                ->orWhereIn('tasks.id', DB::table('task_people')->where('user_id', Auth::id())->select('task_id')))
            ->distinct()
            ->pluck('project_phases.project_id');

        return $onTask->merge(DB::table('projects')->where('lead_user_id', Auth::id())->pluck('id'))->unique()->values()->all();
    }

    public static function maySee($projectId): bool
    {
        if (! Auth::check()) {
            return false;
        }
        if (self::seesEveryProject()) {
            return true;
        }

        return in_array((string) $projectId, self::assignedProjectIds(), true);
    }
}
