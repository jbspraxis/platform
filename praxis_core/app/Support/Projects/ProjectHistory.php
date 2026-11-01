<?php

namespace App\Support\Projects;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/**
 * History for the drawers and the Overview's recent activity, read from system_audit_logs (ProjectRules::log). Each
 * line is shown in the reader's language from projects.history.<ACTION>, with the status or date it recorded;
 * an action without a phrase shows the description written at the time.
 */
final class ProjectHistory
{
    /** One record's lines, newest first */
    public static function for(string $table, string $id, int $limit = 20): Collection
    {
        return self::query()->where('target_table', $table)->where('target_id', $id)->limit($limit)->get()
            ->map(fn ($row) => self::present($row));
    }

    /** The project's latest lines across its milestones, tasks and change reports, each naming its record */
    public static function activity(string $projectId, Collection $milestones, int $limit = 10): Collection
    {
        $tasks = DB::table('tasks')->whereIn('phase_id', $milestones->pluck('id'))->pluck('title', 'id');
        $reports = DB::table('change_reports')->where('project_id', $projectId)->get(['id', 'number'])->mapWithKeys(fn ($r) => [$r->id => ChangeReports::label($r)]);
        $names = [
            'projects' => collect([$projectId => null]),
            'project_phases' => $milestones->mapWithKeys(fn ($m) => [$m->id => $m->code . ' ' . $m->name]),
            'tasks' => $tasks,
            'change_reports' => $reports,
        ];

        $rows = self::query()->where(function ($q) use ($names) {
            foreach ($names as $table => $ids) {
                if ($ids->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('target_table', $table)->whereIn('target_id', $ids->keys()->all()));
                }
            }
        })->limit($limit)->get();

        return $rows->map(fn ($row) => self::present($row, $names[$row->target_table]->get($row->target_id)));
    }

    private static function query()
    {
        return DB::table('system_audit_logs')->leftJoin('users', 'users.id', '=', 'system_audit_logs.user_id')
            ->orderByDesc('system_audit_logs.created_at')->orderByDesc('system_audit_logs.id')
            ->select('system_audit_logs.id', 'system_audit_logs.action', 'system_audit_logs.description', 'system_audit_logs.target_table',
                'system_audit_logs.target_id', 'system_audit_logs.new_values', 'system_audit_logs.created_at', 'users.first_name', 'users.last_name');
    }

    /** {text, who, when, subject} */
    private static function present(object $row, ?string $subject = null): object
    {
        $key = 'projects.history.' . $row->action;
        $new = json_decode((string) $row->new_values, true) ?: [];
        $status = $new['status'] ?? null;
        $statusLabel = match ($row->target_table) {
            'tasks' => $status ? __('projects.task_statuses.' . $status) : '',
            'project_phases' => $status && ($column = array_search($status, ProjectPlan::STATUS_FOR, true)) ? __('projects.columns.' . $column) : '',
            'change_reports' => $status ? __('projects.cr_statuses.' . $status) : '',
            default => '',
        };
        $date = $new['due_date'] ?? $new['proposed_due_date'] ?? $new['decided_on'] ?? $new['accepted_at'] ?? null;

        return (object) [
            'text' => Lang::has($key) ? __($key, ['status' => $statusLabel, 'date' => $date ? ProjectPlan::day($date) : '']) : $row->description,
            'who' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: __('projects.history_system'),
            'when' => $row->created_at,
            'subject' => $subject,
        ];
    }
}
