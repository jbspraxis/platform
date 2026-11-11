<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class SilentTimers extends Component
{
    // What this user may do here (set in mount, see config/access.php)
    #[Locked]
    public bool $canSeeAll = false;   // timesheets.approve or timesheets.export (Auditor): everyone’s time, live burn, anomalies
    #[Locked]
    public bool $canManage = false;   // timesheets.approve: corrections queue
    #[Locked]
    public bool $canInject = false;   // timesheets.approve + payroll.approve: God Mode (creates billable, payable time)
    #[Locked]
    public bool $canSeeMoney = false; // finance.view: margin cost columns

    // View State
    public $activeTab = 'live_burn'; // live_burn, anomalies, ledger, corrections
    public $ledgerGroupBy = 'engineer'; // engineer or project

    // The tab's rows are read when the page draws (render), for the open tab only, and never kept in the page's state

    // Manual time entry state (timesheets.approve + payroll.approve, see $canInject)
    public $godModeUserId;
    public $godModeTaskId;
    public $godModeStartTime;
    public $godModeEndTime;

    public function mount()
    {
        $this->canManage = Auth::user()->can('timesheets.approve');
        $this->canInject = $this->canManage && Auth::user()->can('payroll.approve');
        $this->canSeeAll = $this->canManage || Auth::user()->can('timesheets.export');
        $this->canSeeMoney = Auth::user()->can('finance.view');

        if (! $this->canSeeAll) {
            $this->activeTab = 'ledger';
        }
    }

    // --- 1. LIVE BURN TELEMETRY ---
    private function loadLiveBurn(): array
    {
        // FIX: Bridged through project_phases to reach projects
        $logs = DB::table('task_time_logs')
            ->join('users', 'task_time_logs.user_id', '=', 'users.id')
            ->join('tasks', 'task_time_logs.task_id', '=', 'tasks.id')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->join('projects', 'project_phases.project_id', '=', 'projects.id')
            ->whereNull('task_time_logs.end_time')
            ->select(
                'task_time_logs.id', 'task_time_logs.user_id', 'task_time_logs.start_time', 'task_time_logs.is_rework',
                'users.first_name', 'users.last_name',
                'tasks.title as task_title', 'projects.title as project_title'
            )->get();

        // Each person's hourly rate (their latest compensation row; US$50 an hour when unset), read once for everyone
        // running a timer: the rate their time is costed at when the timer stops (App\Support\Projects\Tasks)
        $rates = $this->canSeeMoney && $logs->isNotEmpty()
            ? DB::table('compensation_history')->whereIn('user_id', $logs->pluck('user_id')->unique()->values())
                ->orderBy('effective_from', 'desc')->get(['user_id', 'hourly_rate'])->unique('user_id')->pluck('hourly_rate', 'user_id')
            : collect();

        return $logs->map(function ($log) use ($rates) {
            $elapsedMinutes = Carbon::parse($log->start_time)->diffInMinutes(now());
            $rate = $rates->get($log->user_id) ?? 50.00;

            $log->live_duration = intdiv($elapsedMinutes, 60) . 'h ' . ($elapsedMinutes % 60) . 'm';
            $log->live_cost = $this->canSeeMoney ? ($elapsedMinutes / 60) * $rate : null;
            return (array) $log;
        })->toArray();
    }

    // --- 2. ANOMALY DETECTOR ---
    private function loadAnomalies(): array
    {
        return DB::table('tasks')
            ->join('users', 'tasks.assigned_to', '=', 'users.id')
            ->whereNotNull('tasks.assigned_to')
            ->where(function($query) {
                $query->whereRaw('tasks.actual_hours_logged > (COALESCE(tasks.estimated_hours, tasks.story_points * 3) * 1.5)')
                      ->orWhereExists(function ($sub) {
                          $sub->select(DB::raw(1))
                              ->from('task_time_logs')
                              ->whereColumn('task_time_logs.task_id', 'tasks.id')
                              ->where('task_time_logs.duration_minutes', '>', 240);
                      });
            })
            // Only what the table shows (not each task's description)
            ->select('tasks.title', 'tasks.estimated_hours', 'tasks.story_points', 'tasks.actual_hours_logged', 'users.first_name', 'users.last_name')
            ->get()->toArray();
    }

    // --- 3. THE MASTER LEDGER ---
    private function loadLedger(): array
    {
        // FIX: Bridged through project_phases to reach projects
        $query = DB::table('task_time_logs')
            ->join('users', 'task_time_logs.user_id', '=', 'users.id')
            ->join('tasks', 'task_time_logs.task_id', '=', 'tasks.id')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->join('projects', 'project_phases.project_id', '=', 'projects.id')
            ->whereNotNull('task_time_logs.end_time')
            ->select(
                'task_time_logs.start_time', 'task_time_logs.end_time', 'task_time_logs.duration_minutes', 'task_time_logs.cost_calculated',
                'task_time_logs.is_rework', 'task_time_logs.is_system_terminated',
                'users.first_name', 'users.last_name',
                'tasks.title as task_title', 'projects.title as project_title'
            )
            ->orderBy('task_time_logs.end_time', 'desc')
            ->limit(100);

        if (! $this->canSeeAll) {
            $query->where('task_time_logs.user_id', Auth::id());
        }

        $rawLogs = $query->get();

        // Cost stays on the server without finance.view
        if (! $this->canSeeMoney) {
            $rawLogs->each(fn ($log) => $log->cost_calculated = null);
        }

        if ($this->ledgerGroupBy === 'engineer') {
            return $rawLogs->groupBy(function($item) {
                return $item->first_name . ' ' . $item->last_name;
            })->toArray();
        }

        return $rawLogs->groupBy('project_title')->toArray();
    }

    // --- 4. TIME CORRECTION QUEUE ---
    private function loadCorrectionRequests(): array
    {
        return DB::table('time_correction_requests')
            ->join('users', 'time_correction_requests.user_id', '=', 'users.id')
            ->join('tasks', 'time_correction_requests.task_id', '=', 'tasks.id')
            ->where('time_correction_requests.status', 'Pending')
            ->select(
                'time_correction_requests.id', 'time_correction_requests.requested_start_time', 'time_correction_requests.requested_end_time',
                'time_correction_requests.reason_for_correction',
                'users.first_name', 'users.last_name',
                'tasks.title as task_title'
            )->get()->toArray();
    }

    // --- 5. APPROVE CORRECTION (THE PENALTY ENGINE) ---
    public function approveCorrection($requestId)
    {
        if (! Auth::user()->can('timesheets.approve')) return;

        $request = DB::table('time_correction_requests')->where('id', $requestId)->first();
        if (!$request || $request->status !== 'Pending') return;

        DB::transaction(function () use ($request) {
            $strikesThisMonth = DB::table('time_correction_requests')
                ->where('user_id', $request->user_id)
                ->where('status', 'Approved')
                ->whereMonth('created_at', now()->month)
                ->count();

            $penaltyMultiplier = 1.0; 
            $penaltyFlag = 0;
            
            if ($strikesThisMonth >= 10) {
                $penaltyMultiplier = 0.90; 
                $penaltyFlag = 1;
            } elseif ($strikesThisMonth >= 5) {
                $penaltyMultiplier = 0.95; 
                $penaltyFlag = 1;
            }

            $start = Carbon::parse($request->requested_start_time);
            $end = Carbon::parse($request->requested_end_time);
            $durationMinutes = $start->diffInMinutes($end);
            
            $baseRate = DB::table('compensation_history')->where('user_id', $request->user_id)->orderBy('effective_from', 'desc')->value('hourly_rate') ?? 50.00;
            $penalizedRate = $baseRate * $penaltyMultiplier;
            $costCalculated = ($durationMinutes / 60) * $penalizedRate;

            $newLogId = DB::table('task_time_logs')->insertGetId([
                'task_id' => $request->task_id,
                'user_id' => $request->user_id,
                'start_time' => $start,
                'end_time' => $end,
                'duration_minutes' => $durationMinutes,
                'cost_calculated' => $costCalculated,
                'created_at' => now()
            ]);

            DB::table('tasks')->where('id', $request->task_id)->update([
                'actual_hours_logged' => DB::raw("actual_hours_logged + " . round($durationMinutes / 60, 2))
            ]);

            DB::table('time_correction_requests')->where('id', $request->id)->update([
                'status' => 'Approved',
                'time_log_id' => $newLogId,
                'penalty_applied' => $penaltyFlag,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $request->user_id,
                'title' => 'Time correction approved',
                'message' => $penaltyFlag ? "Your time correction was approved. It is costed at a reduced rate because you have made 5 or more corrections this month." : "Your time correction was approved and the time is added to the task.",
                'type' => 'Financial', 'created_at' => now()
            ]);
        });

        session()->flash('success', 'Correction approved. The time is added to the task.');
    }

    public function rejectCorrection($requestId)
    {
        if (! Auth::user()->can('timesheets.approve')) return;
        
        DB::table('time_correction_requests')->where('id', $requestId)->update([
            'status' => 'Rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now()
        ]);
        session()->flash('success', 'Correction rejected. The requested time is not added.');
    }

    // --- 6. GOD MODE INJECTION (timesheets.approve + payroll.approve: Owner and Partner by default) ---
    public function executeGodMode()
    {
        if (! Auth::user()->can('timesheets.approve') || ! Auth::user()->can('payroll.approve')) {
            session()->flash('error', 'You don’t have permission to add time manually. Ask an administrator to change your role.');
            return;
        }

        $this->validate([
            'godModeUserId' => 'required',
            'godModeTaskId' => 'required',
            'godModeStartTime' => 'required|date',
            'godModeEndTime' => 'required|date|after:godModeStartTime',
        ], [], [
            'godModeUserId' => 'staff member', 'godModeTaskId' => 'task',
            'godModeStartTime' => 'start', 'godModeEndTime' => 'end',
        ]);

        DB::transaction(function () {
            $start = Carbon::parse($this->godModeStartTime);
            $end = Carbon::parse($this->godModeEndTime);
            $durationMinutes = $start->diffInMinutes($end);
            
            $rate = DB::table('compensation_history')->where('user_id', $this->godModeUserId)->orderBy('effective_from', 'desc')->value('hourly_rate') ?? 50.00;
            $costCalculated = ($durationMinutes / 60) * $rate;

            $logId = DB::table('task_time_logs')->insertGetId([
                'task_id' => $this->godModeTaskId,
                'user_id' => $this->godModeUserId,
                'start_time' => $start,
                'end_time' => $end,
                'duration_minutes' => $durationMinutes,
                'cost_calculated' => $costCalculated,
                'created_at' => now()
            ]);

            DB::table('tasks')->where('id', $this->godModeTaskId)->update([
                'actual_hours_logged' => DB::raw("actual_hours_logged + " . round($durationMinutes / 60, 2))
            ]);

            DB::table('time_correction_requests')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->godModeUserId,
                'task_id' => $this->godModeTaskId,
                'time_log_id' => $logId,
                'requested_start_time' => $start,
                'requested_end_time' => $end,
                'reason_for_correction' => 'Manual time entry by an approver',
                'status' => 'Approved',
                'is_god_mode' => 1,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'created_at' => now()
            ]);
        });

        $this->godModeUserId = ''; $this->godModeTaskId = ''; $this->godModeStartTime = ''; $this->godModeEndTime = '';

        session()->flash('success', 'Time added. The entry is approved and shows in the time log.');
    }

    public function render()
    {
        // Only the open tab's rows, with the same rights the view checks before it shows a tab
        $tab = $this->activeTab;
        $liveBurnData = $tab === 'live_burn' && $this->canSeeAll ? $this->loadLiveBurn() : [];
        $anomaliesData = $tab === 'anomalies' && $this->canSeeAll ? $this->loadAnomalies() : [];
        $ledgerData = $tab === 'ledger' ? $this->loadLedger() : [];
        $correctionRequests = $tab === 'corrections' && $this->canManage ? $this->loadCorrectionRequests() : [];

        // The manual time entry's lists, on its tab only, with only the columns they show
        $manualEntry = $tab === 'corrections' && $this->canInject;
        $allUsers = $manualEntry ? DB::table('users')->where('status', 'Active')->select('id', 'first_name', 'last_name')->get() : [];
        $allTasks = $manualEntry ? DB::table('tasks')->whereIn('status', ['To-Do', 'In-Progress', 'QA_Review'])->get(['id', 'title']) : [];

        return view('livewire.hq.operations.silent-timers', compact('liveBurnData', 'anomaliesData', 'ledgerData', 'correctionRequests', 'allUsers', 'allTasks'));
    }
}