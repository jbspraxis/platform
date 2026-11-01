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

/**
 * The milestone board (docs/PROJECTS.md): one milestone's tasks by status, on the project page's Milestone board tab.
 * Sprints were folded into milestones (owner, 1 Nov 2026), so the board takes a milestone and reads its tasks through
 * tasks.phase_id; the class keeps its old name. Timers, the 45-minute check and the client review window are unchanged.
 */
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

    // Board State: the milestone shown (the Milestone menu) and the project's milestones
    public $activeMilestoneId = null;
    public $milestones = [];
    public $tasks = [
        'To-Do' => [],
        'In-Progress' => [],
        'QA_Review' => [],
        'Failed' => [],
        'Done' => []
    ];

    // Telemetry State
    public $teamCapacity = []; // Tracks burnout risk

    /** $milestone: the one to open on (a kanban card's link); else the one running now (ProjectPlan::boardDefault) */
    public function mount($id, ?string $milestone = null)
    {
        $this->canMoveOwn = Auth::user()->can('projects.edit');
        $this->canMoveAny = Auth::user()->can('projects.approve');
        $project = DB::table('projects')->where('id', $id)->first();
        if (!$project) abort(404);

        // Same rule as the milestone boards list: managers and auditors see every board,
        // everyone else only the boards of projects they lead or have a task on
        abort_unless(self::maySeeBoard($id), 403, __('projects.errors.no_access'));

        $this->projectId = $id;
        $this->project = $project;

        $this->loadMilestones();
        $this->activeMilestoneId = $milestone;
        $this->updatedActiveMilestoneId($milestone);
    }

    /** The project's milestones for the menu, in order, with their dates */
    private function loadMilestones(): void
    {
        $this->milestones = \App\Support\Projects\ProjectPlan::milestones($this->projectId)
            ->map(fn ($m) => (object) ['id' => $m->id, 'code' => $m->code, 'name' => $m->name, 'column' => $m->column,
                'start_date' => $m->start_date, 'due_date' => $m->due_date])
            ->all();
    }

    /** The milestone boards list (SprintBoardDirectory) and the QA hub use the same rule (App\Support\ProjectAccess) */
    public static function maySeeBoard($projectId): bool
    {
        return ProjectAccess::maySee($projectId);
    }

    /** Checked again on every change: access can end while the board is open (a task reassigned, a role changed) */
    private function authoriseBoard(): void
    {
        abort_unless(self::maySeeBoard($this->projectId), 403, __('projects.errors.no_access'));
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

    /** A drawer on the project page changed a milestone or a task: the menu and the cards follow */
    #[\Livewire\Attributes\On('project-changed')]
    public function refreshFromProject(): void
    {
        $this->authoriseBoard();
        $this->loadMilestones();
        $this->updatedActiveMilestoneId($this->activeMilestoneId);
    }

    public function switchMilestone($milestoneId)
    {
        $this->activeMilestoneId = $milestoneId;
        $this->updatedActiveMilestoneId($milestoneId);
    }

    /**
     * The Milestone menu (wire:model.live="activeMilestoneId"): the board follows at once. Only this project's
     * milestones; anything else falls back to the one running now.
     */
    public function updatedActiveMilestoneId($milestoneId): void
    {
        $milestones = collect($this->milestones);
        if (! $milestones->contains(fn ($m) => data_get($m, 'id') === $milestoneId)) {
            $this->activeMilestoneId = data_get(\App\Support\Projects\ProjectPlan::boardDefault($milestones), 'id');
        }
        $this->loadBoardData();
    }

    // Runs when the page opens, when the milestone changes and after every move, so access is checked again here too
    public function loadBoardData()
    {
        $this->authoriseBoard();
        if (!$this->activeMilestoneId) return;

        $rawTasks = DB::table('tasks')
            ->leftJoin('users', 'tasks.assigned_to', '=', 'users.id')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->projectId)
            ->where('tasks.phase_id', $this->activeMilestoneId)
            ->whereNull('tasks.deleted_at')
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