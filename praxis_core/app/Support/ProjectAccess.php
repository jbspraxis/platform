<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Which projects a staff member sees in Operations (sprint boards, QA tickets). Managers and auditors
 * (projects.approve or projects.export) see every project; everyone else only the projects where they
 * have an assigned task. Sprint boards, their list and the QA hub all use this one rule.
 */
class ProjectAccess
{
    public static function seesEveryProject(): bool
    {
        $user = Auth::user();

        return $user && ($user->can('projects.approve') || $user->can('projects.export'));
    }

    /** Projects where the signed-in person has an assigned task (tasks has no project_id; bridge through project_phases) */
    public static function assignedProjectIds(): array
    {
        return DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('tasks.assigned_to', Auth::id())
            ->distinct()
            ->pluck('project_phases.project_id')
            ->all();
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
