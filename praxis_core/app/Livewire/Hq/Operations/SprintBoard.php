<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class SprintBoard extends Component
{
    public $projectId;
    public $project;
    public $userClearance;

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

    public function mount($id)
    {
        $this->userClearance = Auth::user()->role->clearance_level ?? 0;
        $this->projectId = $id;
        $this->project = DB::table('projects')->where('id', $id)->first();

        if (!$this->project) abort(404);

        $this->sprints = DB::table('sprints')->where('project_id', $this->projectId)->orderBy('start_date', 'asc')->get()->toArray();
        
        // Auto-select the currently active sprint, or default to the first one
        $activeSprint = collect($this->sprints)->where('status', 'Active')->first() ?? collect($this->sprints)->first();
        $this->activeSprintId = $activeSprint ? $activeSprint->id : null;

        $this->loadBoardData();
    }

    public function switchSprint($sprintId)
    {
        $this->activeSprintId = $sprintId;
        $this->loadBoardData();
    }

    // Triggers every 10 seconds via wire:poll in the UI
    public function loadBoardData()
    {
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
    }

    // --- CHECKOUT SYSTEM (Rule 8: C) ---
    public function assignToMe($taskId)
    {
        DB::table('tasks')->where('id', $taskId)->update([
            'assigned_to' => Auth::id(),
            'updated_at' => now()
        ]);
        
        $this->logAudit('TASK_CHECKOUT', "Engineer checked out Task ID: {$taskId}");
        $this->loadBoardData();
    }

    // --- KANBAN DRAG & DROP + SILENT TIMER LOGIC ---
    public function updateTaskStatus($taskId, $newStatus)
    {
        $task = DB::table('tasks')->where('id', $taskId)->first();
        if (!$task || $task->status === $newStatus) return;

        // Security Gate: You cannot move someone else's task unless you are Level 4+
        if ($task->assigned_to !== Auth::id() && $this->userClearance < 4) {
            session()->flash('error', 'ACCESS DENIED: You cannot alter a Battlespace assigned to another engineer.');
            return;
        }

        DB::transaction(function () use ($task, $newStatus) {
            $oldStatus = $task->status;
            $updateData = ['status' => $newStatus, 'updated_at' => now()];

            // 1. The 5-Hour Client QA SLA (Rule 6)
            if ($newStatus === 'QA_Review') {
                $updateData['qa_sla_expires_at'] = now()->addHours(5);
                $this->notifyAdmins("Task '{$task->title}' moved to QA. 5-Hour SLA clock initiated.");
            }

            // 2. Clear SLA if pulled out of QA
            if ($oldStatus === 'QA_Review' && $newStatus !== 'QA_Review' && $newStatus !== 'Done') {
                $updateData['qa_sla_expires_at'] = null;
            }

            DB::table('tasks')->where('id', $task->id)->update($updateData);

            // ==========================================
            // THE SILENT TIMER ENGINE
            // ==========================================
            
            // TIMER START: Dragged INTO 'In-Progress'
            if ($newStatus === 'In-Progress' && $oldStatus !== 'In-Progress') {
                DB::table('task_time_logs')->insert([
                    'task_id' => $task->id,
                    'user_id' => Auth::id(),
                    'start_time' => now(),
                    'created_at' => now()
                ]);
            } 
            // TIMER STOP: Dragged OUT OF 'In-Progress'
            elseif ($oldStatus === 'In-Progress' && $newStatus !== 'In-Progress') {
                
                // Find the open timer
                $openLog = DB::table('task_time_logs')
                    ->where('task_id', $task->id)
                    ->where('user_id', Auth::id())
                    ->whereNull('end_time')
                    ->orderBy('start_time', 'desc')
                    ->first();

                if ($openLog) {
                    $endTime = now();
                    $durationMinutes = Carbon::parse($openLog->start_time)->diffInMinutes($endTime);
                    
                    // Fetch Engineer's Hourly Rate for Live Margin Deduction (Fallback to $50/hr if unset)
                    $rate = DB::table('compensation_history')
                                ->where('user_id', Auth::id())
                                ->orderBy('effective_from', 'desc')
                                ->value('hourly_rate') ?? 50.00;
                    
                    $costCalculated = ($durationMinutes / 60) * $rate;

                    // Close the log and stamp the cost
                    DB::table('task_time_logs')->where('id', $openLog->id)->update([
                        'end_time' => $endTime,
                        'duration_minutes' => $durationMinutes,
                        'cost_calculated' => $costCalculated
                    ]);

                    // Add elapsed time to total task actuals
                    DB::table('tasks')->where('id', $task->id)->update([
                        'actual_hours_logged' => DB::raw("actual_hours_logged + " . round($durationMinutes / 60, 2))
                    ]);
                }
            }
        });

        $this->loadBoardData();
    }

    private function notifyAdmins($message)
    {
        $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')
                    ->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
        
        $inserts = [];
        foreach($admins as $admin) {
            $inserts[] = [
                'id' => (string) Str::uuid(), 'user_id' => $admin->id,
                'title' => 'QA SLA TRIGGERED', 'message' => $message,
                'type' => 'Project', 'created_at' => now()
            ];
        }
        if(!empty($inserts)) DB::table('notifications')->insert($inserts);
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
        session()->flash('error', 'SYSTEM IDLE DETECTED: You failed the 45-minute presence check. All active timers paused and tasks reverted to To-Do.');
    }

    public function render()
    {
        return view('livewire.hq.operations.sprint-board');
    }
}