<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Support\Access;
use App\Support\ProjectAccess;

#[Layout('components.layouts.hq')]
class SprintBoard extends Component
{
    // Set in mount after the access check; the browser must not switch the board to another project
    #[Locked]
    public $projectId;
    #[Locked]
    public $project;

    // Task statuses a card can be dropped into (database values)
    private const COLUMNS = ['To-Do', 'In-Progress', 'QA_Review', 'Failed', 'Done'];

    // What this user may do here (set in mount, see config/access.php)
    #[Locked]
    public bool $canMoveOwn = false; // projects.edit: check out and move own tasks
    #[Locked]
    public bool $canMoveAny = false; // projects.approve: move anyone’s task

    // On the project page's Sprints tab (docs/PROJECTS.md): the page has the header and the task drawer
    #[Locked]
    public bool $embedded = false;

    // Board State
    public $activeSprintId = null;
    public $sprints = [];
    public $tasks = [
        'To-Do' => [],
        'In-Progress' => [],
        'QA_Review' => [],
        'Failed' => [],
        'Done' => []
    ];

    // Telemetry State
    public $teamCapacity = []; // Tracks burnout risk

    public function mount($id, bool $embedded = false)
    {
        $this->embedded = $embedded;
        $this->canMoveOwn = Auth::user()->can('projects.edit');
        $this->canMoveAny = Auth::user()->can('projects.approve');
        $project = DB::table('projects')->where('id', $id)->first();
        if (!$project) abort(404);

        // Same rule as the sprint boards list: managers and auditors see every board,
        // everyone else only the boards of projects where they have an assigned task
        abort_unless(self::maySeeBoard($id), 403, 'You don’t have permission to open this sprint board. Ask a project lead to assign you a task on this project, or an administrator to change your role.');

        $this->projectId = $id;
        $this->project = $project;

        $this->sprints = DB::table('sprints')->where('project_id', $this->projectId)->orderBy('start_date', 'asc')->get()->toArray();
        
        // Auto-select the currently active sprint, or default to the first one
        $activeSprint = collect($this->sprints)->where('status', 'Active')->first() ?? collect($this->sprints)->first();
        $this->activeSprintId = $activeSprint ? $activeSprint->id : null;

        $this->loadBoardData();
    }

    /** The sprint boards list (SprintBoardDirectory) and the QA hub use the same rule (App\Support\ProjectAccess) */
    public static function maySeeBoard($projectId): bool
    {
        return ProjectAccess::maySee($projectId);
    }

    /** Checked again on every change: access can end while the board is open (a task reassigned, a role changed) */
    private function authoriseBoard(): void
    {
        abort_unless(self::maySeeBoard($this->projectId), 403, 'You no longer have access to this sprint board. Ask a project lead to assign you a task on this project, or an administrator to change your role.');
    }

    /** A task on this board’s project, or null (task ids come from the browser) */
    private function boardTask($taskId)
    {
        return DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->projectId)
            ->where('tasks.id', $taskId)
            ->select('tasks.*')
            ->first();
    }

    /** A drawer on the project page changed a sprint or a task: the menu and the cards follow */
    #[\Livewire\Attributes\On('project-changed')]
    public function refreshFromProject(): void
    {
        $this->authoriseBoard();
        $this->sprints = DB::table('sprints')->where('project_id', $this->projectId)->whereNull('deleted_at')->orderBy('start_date', 'asc')->get()->toArray();
        $this->updatedActiveSprintId($this->activeSprintId);
    }

    public function switchSprint($sprintId)
    {
        $this->activeSprintId = $sprintId;
        $this->updatedActiveSprintId($sprintId);
    }

    /**
     * The Sprint menu (wire:model.live="activeSprintId"): the board follows at once. Only this project's sprints;
     * anything else falls back to the active one. Without this the menu changed but the tasks stayed on the
     * sprint chosen when the page opened.
     */
    public function updatedActiveSprintId($sprintId): void
    {
        $sprints = collect($this->sprints);
        if (! $sprints->contains(fn ($s) => data_get($s, 'id') === $sprintId)) {
            $fallback = $sprints->first(fn ($s) => data_get($s, 'status') === 'Active') ?? $sprints->first();
            $this->activeSprintId = data_get($fallback, 'id');
        }
        $this->loadBoardData();
    }

    // Runs when the page opens, when the sprint changes and after every move, so access is checked again here too
    public function loadBoardData()
    {
        $this->authoriseBoard();
        if (!$this->activeSprintId) return;

        $rawTasks = DB::table('tasks')
            ->leftJoin('users', 'tasks.assigned_to', '=', 'users.id')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->projectId) 
            ->where('tasks.sprint_id', $this->activeSprintId)
            ->select(
                'tasks.*', 
                'users.first_name', 'users.last_name', 
                'project_phases.title as phase_title'
            )
            ->orderBy('tasks.created_at', 'desc')
            ->get();

        $activeQaTickets = DB::table('qa_tickets')
            ->where('project_id', $this->projectId)
            ->whereIn('status', ['Open', 'Investigating'])
            ->whereNotNull('task_id')
            ->select('id', 'task_id', 'title', 'description', 'severity')
            ->get()
            ->keyBy('task_id');

        $this->tasks = ['To-Do' => [], 'In-Progress' => [], 'QA_Review' => [], 'Failed' => [], 'Done' => []];
        $capacityTracker = [];

        foreach ($rawTasks as $task) {
            $taskData = (array) $task;
            
            // Inject the rich QA data if a vulnerability exists
            $qaTicket = $activeQaTickets->get($task->id);
            $taskData['has_active_qa'] = $qaTicket !== null;
            
            if ($qaTicket) {
                $taskData['qa_ticket_id'] = $qaTicket->id;
                $taskData['qa_ticket_title'] = $qaTicket->title;
                $taskData['qa_ticket_description'] = Str::limit($qaTicket->description, 100); // Limit for tooltip
            }

            $col = in_array($taskData['status'], array_keys($this->tasks)) ? $taskData['status'] : 'To-Do';
            $this->tasks[$col][] = $taskData;

            if ($task->assigned_to && in_array($task->status, ['To-Do', 'In-Progress', 'Failed'])) {
                $hours = $task->estimated_hours ?: ($task->story_points * 3);
                if (!isset($capacityTracker[$task->assigned_to])) {
                    $capacityTracker[$task->assigned_to] = [
                        'name' => substr($task->first_name, 0, 1) . '. ' . $task->last_name,
                        'hours_assigned' => 0
                    ];
                }
                $capacityTracker[$task->assigned_to]['hours_assigned'] += $hours;
            }
        }

        $this->teamCapacity = $capacityTracker;
        $this->decorateCards();
    }

    /**
     * What a card shows since the project page (docs/PROJECTS.md): due date and Overdue, the open tasks it waits on
     * (a warning only), how many share it, and whether this person may drag it (lead or sharing it, or a manager).
     */
    private function decorateCards(): void
    {
        $ids = collect($this->tasks)->flatten(1)->pluck('id')->all();
        $waits = \App\Support\Projects\ProjectPlan::openWaits($ids);
        $sharing = $ids ? DB::table('task_people')->whereIn('task_id', $ids)->where('role', 'sharing')->groupBy('task_id')->selectRaw('task_id, COUNT(*) AS n')->pluck('n', 'task_id') : collect();
        $mayMove = \App\Support\Projects\ProjectRules::mover($this->projectId);
        foreach ($this->tasks as $column => $cards) {
            foreach ($cards as $i => $card) {
                $this->tasks[$column][$i] += [
                    'is_overdue' => \App\Support\Projects\ProjectPlan::taskOverdue($card),
                    'waits_on' => collect($waits->get($card['id'], []))->pluck('title')->all(),
                    'sharing_count' => (int) ($sharing[$card['id']] ?? 0),
                    'can_move' => $mayMove($card),
                ];
            }
        }
    }

    // --- CHECKOUT SYSTEM (Rule 8: C) ---
    public function assignToMe($taskId)
    {
        abort_unless(Auth::user()->can('projects.edit'), 403);
        $this->authoriseBoard();

        // Only a task on this board
        if (! $this->boardTask($taskId)) return;

        // Only an unassigned task, unless the user may move anyone’s task
        $updated = DB::table('tasks')->where('id', $taskId)
            ->when(! Auth::user()->can('projects.approve'), fn ($q) => $q->whereNull('assigned_to'))
            ->update([
                'assigned_to' => Auth::id(),
                'updated_at' => now()
            ]);

        if ($updated) $this->logAudit('TASK_CHECKOUT', "Staff member assigned task {$taskId} to themselves");
        $this->loadBoardData();
    }

    // --- KANBAN DRAG & DROP + SILENT TIMER LOGIC ---
    public function updateTaskStatus($taskId, $newStatus)
    {
        $this->authoriseBoard();

        // Only a task on this board, and only into one of the board’s columns
        $task = $this->boardTask($taskId);
        if (!$task || $task->status === $newStatus || ! in_array($newStatus, self::COLUMNS, true)) return;

        // Security gate: the lead or someone sharing the task (projects.edit), the project's lead or a manager, or
        // projects.approve for anyone's (App\Support\Projects\ProjectRules)
        if (! \App\Support\Projects\ProjectRules::canMove($task, $this->projectId)) {
            session()->flash('error', __('projects.task.move_own'));
            return;
        }

        // The client review window, the timers and the notices (moved to App\Support\Projects\Tasks::move, which the
        // project page's task drawer uses too)
        \App\Support\Projects\Tasks::move($this->projectId, $task, $newStatus);

        $this->loadBoardData();
        $this->dispatch('project-changed')->to(\App\Livewire\Hq\Projects\ProjectPage::class); // the page's header and counts
    }

    private function logAudit($action, $description)
    {
        DB::table('system_audit_logs')->insert([
            // Removed UUID insertion to allow DB Auto-Increment
            'user_id' => Auth::id(),
            'action' => $action, 
            'description' => $description,
            'ip_address' => request()->ip(), 
            'created_at' => now()
        ]);
    }
    
    // --- PRESENCE MONITOR KILLSWITCH ---
    public function forcePauseTimers()
    {
        $openLogs = DB::table('task_time_logs')
            ->where('user_id', Auth::id())
            ->whereNull('end_time')
            ->get();

        if ($openLogs->isEmpty()) return;

        $rate = DB::table('compensation_history')->where('user_id', Auth::id())->orderBy('effective_from', 'desc')->value('hourly_rate') ?? 50.00;

        foreach ($openLogs as $log) {
            $endTime = now();
            $durationMinutes = \Carbon\Carbon::parse($log->start_time)->diffInMinutes($endTime);
            $costCalculated = ($durationMinutes / 60) * $rate;

            DB::table('task_time_logs')->where('id', $log->id)->update([
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'cost_calculated' => $costCalculated
            ]);

            DB::table('tasks')->where('id', $log->task_id)->update([
                'status' => 'To-Do', // Kick it out of In-Progress
                'actual_hours_logged' => DB::raw("actual_hours_logged + " . round($durationMinutes / 60, 2)),
                'updated_at' => now()
            ]);
        }

        $this->loadBoardData();
        session()->flash('error', 'We paused your timers because the 45-minute check wasn’t answered. Your tasks in progress are back in To do; move one into In progress to start again.');
    }

    public function render()
    {
        return view('livewire.hq.operations.sprint-board');
    }
}