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
use App\Support\Opportunities\Handovers;
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

    // Task statuses a card can be dropped into (database values)
    private const COLUMNS = ['To-Do', 'In-Progress', 'QA_Review', 'Failed', 'Done'];

    // What this user may do here (set in mount, see config/access.php)
    #[Locked]
    public bool $canMoveOwn = false; // projects.edit: check out and move own tasks
    #[Locked]
    public bool $canMoveAny = false; // projects.approve: move anyone’s task
    #[Locked]
    public bool $canAssign = false; // the project's lead and managers: give an unassigned task to anyone on the team

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

    // Worked out once per request and kept in private properties, which Livewire never sends to the browser nor keeps
    // between requests: nothing the board changes can end this person's own access within the request
    private ?bool $maySee = null;
    private ?bool $canView = null;
    private ?bool $canManage = null;
    private ?\Illuminate\Support\Collection $team = null;

    /** $milestone: the one to open on (a kanban card's link); else the one running now (ProjectPlan::boardDefault) */
    public function mount($id, ?string $milestone = null)
    {
        $this->canMoveOwn = Auth::user()->can('projects.edit');
        $this->canMoveAny = Auth::user()->can('projects.approve');
        if (! DB::table('projects')->where('id', $id)->exists()) abort(404);

        // Same rule as the milestone boards list: managers and auditors see every board,
        // everyone else only the boards of projects they lead or have a task on
        abort_unless($this->maySee = self::maySeeBoard($id), 403, __('projects.errors.no_access'));

        $this->projectId = $id;
        $this->canAssign = $this->canManage();

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
        abort_unless($this->maySee ??= self::maySeeBoard($this->projectId), 403, __('projects.errors.no_access'));
    }

    private function canView(): bool
    {
        return $this->canView ??= \App\Support\Projects\ProjectRules::canView($this->projectId, $this->maySee);
    }

    private function canManage(): bool
    {
        return $this->canManage ??= \App\Support\Projects\ProjectRules::canManage($this->projectId, $this->canView());
    }

    private function team(): \Illuminate\Support\Collection
    {
        return $this->team ??= \App\Support\Projects\ProjectRules::team($this->projectId);
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

    /**
     * A drawer on the project page changed a milestone or a task: the cards follow, and the menu too when a milestone
     * changed ('milestone'). A step, a comment, watching, a due-date proposal ('activity') or a change report not yet
     * decided ('report') shows nothing here.
     */
    #[\Livewire\Attributes\On('project-changed')]
    public function refreshFromProject(string $kind = 'task'): void
    {
        $this->authoriseBoard();
        if (in_array($kind, ['activity', 'report'], true)) {
            $this->skipRender();

            return;
        }
        if ($kind === 'milestone') {
            $this->loadMilestones();
        }
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

        // Only what a card and the capacity line read (not the description): the cards stay in the board's state
        $rawTasks = DB::table('tasks')
            ->leftJoin('users', 'tasks.assigned_to', '=', 'users.id')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->projectId)
            ->where('tasks.phase_id', $this->activeMilestoneId)
            ->whereNull('tasks.deleted_at')
            ->select(
                'tasks.id', 'tasks.title', 'tasks.status', 'tasks.due_date', 'tasks.assigned_to',
                'tasks.estimated_hours', 'tasks.story_points', 'tasks.qa_sla_expires_at',
                'users.first_name', 'users.last_name'
            )
            ->orderBy('tasks.created_at', 'desc')
            ->get();

        $activeQaTickets = DB::table('qa_tickets')
            ->where('project_id', $this->projectId)
            ->whereIn('status', ['Open', 'Investigating'])
            ->whereNotNull('task_id')
            ->whereNull('deleted_at')
            ->select('id', 'task_id', 'title', 'description', 'severity')
            ->orderByRaw("FIELD(severity, 'Critical', 'Medium', 'Low')")
            ->orderBy('created_at')
            ->get()
            ->groupBy('task_id'); // a task can have several (a handover's points): the most severe shows first

        $this->tasks = ['To-Do' => [], 'In-Progress' => [], 'QA_Review' => [], 'Failed' => [], 'Done' => []];
        $capacityTracker = [];

        foreach ($rawTasks as $task) {
            $taskData = (array) $task;
            
            // Inject the rich QA data if a vulnerability exists
            $qaTicket = $activeQaTickets->get($task->id)?->first();
            $taskData['has_active_qa'] = $qaTicket !== null;
            $taskData['qa_ticket_count'] = $activeQaTickets->get($task->id)?->count() ?? 0;
            
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
        $mayMove = \App\Support\Projects\ProjectRules::mover($this->projectId, $this->canManage(), $this->canView());
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
        $task = $this->boardTask($taskId);
        if (! $task) return;

        // Only an unassigned task, unless the user may move anyone’s task; the new lead stops sharing or watching it
        \App\Support\Projects\Tasks::assignToMe($task, Auth::user()->can('projects.approve'));
        $this->loadBoardData();
    }

    /**
     * The card's "Unassigned" picker (owner, 2 Nov 2026; it replaced the card's Assign to me button): anyone with
     * projects.edit may take an unassigned task (Me); the project's lead and managers may also give it to anyone on the
     * project team, as in the task drawer. A task that already has a lead is changed in the drawer, never from a card.
     */
    public function assignTo($taskId, $userId)
    {
        $this->authoriseBoard();
        $manager = $this->canManage();
        abort_unless($manager || Auth::user()->can('projects.edit'), 403);

        $task = $this->boardTask($taskId);
        if (! $task) return;

        if ($task->assigned_to) {
            // Someone took it while this board was open
            session()->flash('error', __('projects.task.already_led'));
        } elseif ((string) $userId === (string) Auth::id()) {
            \App\Support\Projects\Tasks::assignToMe($task, false);
        } else {
            abort_unless($manager, 403);
            if ($this->team()->contains('id', (string) $userId)) {
                // The drawer's save: the audit log, the new lead stops sharing or watching it, and their bell
                \App\Support\Projects\Tasks::update($this->projectId, $task, ['assigned_to' => (string) $userId]);
            }
        }
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
        $canWork = \App\Support\Projects\ProjectRules::canWorkOn($task, $this->projectId, $this->canManage(), $this->canView());
        if (! \App\Support\Projects\ProjectRules::canMove($task, $this->projectId, $canWork, $this->canView())) {
            session()->flash('error', __('projects.task.move_own'));
            return;
        }

        // The client review window, the timers and the notices (moved to App\Support\Projects\Tasks::move, which the
        // project page's task drawer uses too)
        \App\Support\Projects\Tasks::move($this->projectId, $task, $newStatus);
        $this->dispatch('timers-changed'); // the page-wide 45-minute check follows (App\Livewire\Hq\TimerPresence)

        $this->loadBoardData();
        $this->dispatch('project-changed')->to(\App\Livewire\Hq\Projects\ProjectPage::class); // the page's header and counts
    }

    // The 45-minute presence check runs on every HQ page now (App\Livewire\Hq\TimerPresence, in the HQ layout), so a
    // timer started from the task drawer or any other tab is checked too; its pause is Tasks::pauseOpenTimers().

    /**
     * The handover of the milestone this board shows (PR 6): Handovers::forMilestone(), the entry point any milestone
     * screen uses, shown by partials/milestone-handover.blade.php; null while the project has no milestone
     */
    private function handover(): ?array
    {
        return $this->activeMilestoneId ? Handovers::forMilestone($this->projectId, $this->activeMilestoneId, Auth::user()) : null;
    }

    public function render()
    {
        // The card picker opens from an unassigned card only
        $unassigned = collect($this->tasks)->flatten(1)->contains(fn ($task) => empty($task['assigned_to']));

        return view('livewire.hq.operations.sprint-board', [
            'handover' => $this->handover(),
            // The card picker's people (managers only; everyone else's menu holds just "Me")
            'team' => $this->canAssign && $unassigned ? $this->team() : collect(),
        ]);
    }
}