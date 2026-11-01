<?php

namespace App\Support\Projects;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a project page reads (docs/PROJECTS.md, decisions 2, 3 and 6): milestones with their kanban column, progress
 * and invoice, and overdue work. A milestone is also the unit of work with its own task board (sprints were folded
 * into milestones on 1 Nov 2026; the sprints table is history only).
 *
 * One weight: a milestone's weight_percentage is its share of the contract. A milestone's progress is its done tasks
 * over its tasks (one with no tasks counts as done once accepted); the project's is the sum of weight × progress.
 * The Invoiced and Paid columns follow the milestone's invoice, the other four its status. Overdue: a due date
 * before today in Lagos on a task not Done or a milestone not yet accepted.
 */
final class ProjectPlan
{
    public const TIME_ZONE = 'Africa/Lagos';

    public const COLUMNS = ['not_started', 'in_progress', 'client_review', 'accepted', 'invoiced', 'paid'];

    /** The four columns a milestone's status decides (project_phases.status) */
    public const STATUS_FOR = ['not_started' => 'Pending', 'in_progress' => 'Active', 'client_review' => 'QA', 'accepted' => 'Completed'];

    /** Accepted, invoiced or paid: delivered, so not overdue and done for "waits on" */
    public const DELIVERED = ['accepted', 'invoiced', 'paid'];

    public const TASK_STATUSES = ['To-Do', 'In-Progress', 'QA_Review', 'Failed', 'Done'];

    public static function today(): string
    {
        return now(self::TIME_ZONE)->toDateString();
    }

    public static function project(string $projectId): ?object
    {
        return DB::table('projects')
            ->leftJoin('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->leftJoin('users as lead', 'lead.id', '=', 'projects.lead_user_id')
            ->where('projects.id', $projectId)->whereNull('projects.deleted_at')
            ->select('projects.*', 'organizations.name as client_name', 'lead.first_name as lead_first_name', 'lead.last_name as lead_last_name')
            ->first();
    }

    /** M4: the phase number is the milestone's code */
    public static function code(object $phase): string
    {
        return 'M' . $phase->phase_number;
    }

    /** "M4 · Recruitment End to End" (as imported) reads "Recruitment End to End" next to its code */
    public static function title(object $phase): string
    {
        return preg_replace('/^M\d+\s*[·:\-–]\s*/u', '', (string) $phase->title) ?: (string) $phase->title;
    }

    public static function column(object $phase, ?object $invoice = null): string
    {
        if ($invoice) {
            return $invoice->status === 'Paid' ? 'paid' : 'invoiced';
        }

        return array_search($phase->status, self::STATUS_FOR, true) ?: 'not_started';
    }

    public static function taskOverdue(object|array $task): bool
    {
        $due = data_get($task, 'due_date');

        return $due && substr((string) $due, 0, 10) < self::today() && data_get($task, 'status') !== 'Done';
    }

    /**
     * The project's milestones in order, each with: code, name, column, tasks_total, tasks_done, progress (0–100),
     * overdue, invoice (the latest not cancelled, or null) and waits_on [{id, code, name, done}].
     */
    public static function milestones(string $projectId): Collection
    {
        $phases = DB::table('project_phases')->where('project_id', $projectId)->whereNull('deleted_at')
            ->orderBy('phase_number')->orderBy('created_at')->get();
        $ids = $phases->pluck('id');

        $counts = DB::table('tasks')->whereIn('phase_id', $ids)->whereNull('deleted_at')->groupBy('phase_id')
            ->selectRaw("phase_id, COUNT(*) AS total, SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) AS done")
            ->get()->keyBy('phase_id');
        $invoices = DB::table('invoices')->whereIn('phase_id', $ids)->whereNull('deleted_at')->where('status', '!=', 'Cancelled')
            ->orderByDesc('issued_at')->orderByDesc('created_at')
            ->get(['id', 'phase_id', 'invoice_number', 'status', 'total_amount', 'currency', 'due_date', 'paid_at'])
            ->groupBy('phase_id')->map->first();
        $links = DB::table('phase_dependencies')->whereIn('phase_id', $ids)->get()->groupBy('phase_id');
        $today = self::today();

        $milestones = $phases->map(function ($phase) use ($counts, $invoices, $today) {
            $c = $counts->get($phase->id);
            $phase->code = self::code($phase);
            $phase->name = self::title($phase);
            $phase->invoice = $invoices->get($phase->id);
            $phase->column = self::column($phase, $phase->invoice);
            $phase->tasks_total = (int) ($c->total ?? 0);
            $phase->tasks_done = (int) ($c->done ?? 0);
            $phase->progress = $phase->tasks_total > 0
                ? round($phase->tasks_done / $phase->tasks_total * 100, 1)
                : (in_array($phase->column, self::DELIVERED, true) ? 100.0 : 0.0);
            $phase->overdue = $phase->due_date && $phase->due_date < $today && ! in_array($phase->column, self::DELIVERED, true);

            return $phase;
        })->keyBy('id');

        foreach ($milestones as $phase) {
            $phase->waits_on = collect($links->get($phase->id, []))->map(fn ($link) => $milestones->get($link->depends_on_phase_id))->filter()
                ->map(fn ($other) => (object) ['id' => $other->id, 'code' => $other->code, 'name' => $other->name, 'done' => in_array($other->column, self::DELIVERED, true)])
                ->values();
        }

        return $milestones->values();
    }

    /** The sum of each milestone's weight × its progress, as a percentage */
    public static function progress(Collection $milestones): float
    {
        return round($milestones->sum(fn ($m) => (float) $m->weight_percentage * $m->progress / 100), 1);
    }

    public static function weightTotal(Collection $milestones): float
    {
        return round((float) $milestones->sum(fn ($m) => (float) $m->weight_percentage), 2);
    }

    /** The milestones being worked on now: in progress or in client review, or running today by their dates */
    public static function current(Collection $milestones): Collection
    {
        $today = self::today();

        return $milestones->filter(fn ($m) => in_array($m->column, ['in_progress', 'client_review'], true)
            || ($m->column === 'not_started' && $m->start_date && $m->start_date <= $today && (! $m->due_date || $m->due_date >= $today)))->values();
    }

    /**
     * The milestone a board opens on: one running today, else the in-progress one due soonest that isn't past due,
     * else any in progress, else the first not yet delivered, else the first.
     */
    public static function boardDefault(Collection $milestones): ?object
    {
        $today = self::today();
        $open = $milestones->reject(fn ($m) => in_array($m->column, self::DELIVERED, true));

        return $open->first(fn ($m) => $m->start_date && $m->due_date && $m->start_date <= $today && $m->due_date >= $today)
            ?? $open->where('column', 'in_progress')->filter(fn ($m) => $m->due_date && $m->due_date >= $today)->sortBy('due_date')->first()
            ?? $open->firstWhere('column', 'in_progress')
            ?? $open->first()
            ?? $milestones->first();
    }

    /** The next milestone due: not yet accepted, with the earliest due date */
    public static function nextDue(Collection $milestones): ?object
    {
        return $milestones->filter(fn ($m) => $m->due_date && ! in_array($m->column, self::DELIVERED, true))->sortBy('due_date')->first();
    }

    /** The project's open tasks past their due date, earliest first */
    public static function overdueTasks(string $projectId): Collection
    {
        return DB::table('tasks')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
            ->leftJoin('users', 'users.id', '=', 'tasks.assigned_to')
            ->where('project_phases.project_id', $projectId)->whereNull('tasks.deleted_at')
            ->whereNotNull('tasks.due_date')->where('tasks.due_date', '<', self::today())->where('tasks.status', '!=', 'Done')
            ->orderBy('tasks.due_date')
            ->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.due_date', 'tasks.assigned_to', 'project_phases.phase_number', 'users.first_name', 'users.last_name']);
    }

    public static function openQaCount(string $projectId): int
    {
        return DB::table('qa_tickets')->where('project_id', $projectId)->whereNull('deleted_at')->whereIn('status', ['Open', 'Investigating'])->count();
    }

    /** Open prerequisites per task: [task id => [{id, title, status}]] for the tasks given */
    public static function openWaits(array $taskIds): Collection
    {
        if (! $taskIds) {
            return collect();
        }

        return DB::table('task_dependencies')->join('tasks', 'tasks.id', '=', 'task_dependencies.depends_on_task_id')
            ->whereIn('task_dependencies.task_id', $taskIds)->where('tasks.status', '!=', 'Done')->whereNull('tasks.deleted_at')
            ->get(['task_dependencies.task_id', 'tasks.id', 'tasks.title', 'tasks.status'])
            ->groupBy('task_id');
    }

    /**
     * A date in the reader's language, e.g. 13 November 2026 / 13 novembre 2026. A plain date (a due date) is a Lagos
     * day as it stands; a timestamp (stored in UTC, like created_at) is shown in Lagos time.
     */
    public static function day($date, string $format = 'date', ?string $locale = null): string
    {
        if (! $date) {
            return '';
        }
        $locale ??= app()->getLocale();
        $at = strlen((string) $date) === 10 ? Carbon::parse($date, self::TIME_ZONE) : Carbon::parse($date, 'UTC')->setTimezone(self::TIME_ZONE);

        return $at->locale($locale)->translatedFormat(__('projects.formats.' . $format, [], $locale));
    }

    /** ₦ 3,700,000 / 3 700 000 NGN, as the opportunity pages write money */
    public static function money($amount, ?string $currency): string
    {
        $currency = $currency ?: 'NGN';

        return app()->getLocale() === 'fr'
            ? number_format((float) $amount, 0, ',', "\u{202F}") . "\u{00A0}" . $currency
            : $currency . "\u{00A0}" . number_format((float) $amount, 0);
    }

    public static function percent($value): string
    {
        $value = round((float) $value, 1);
        $text = fmod($value, 1.0) == 0.0 ? number_format($value, 0) : number_format($value, 1, app()->getLocale() === 'fr' ? ',' : '.', '');

        return app()->getLocale() === 'fr' ? $text . "\u{202F}%" : $text . '%';
    }
}
