<?php

namespace App\Livewire\Hq\Dashboards;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class MyWorkspace extends Component
{
    public $activeTasks = [];
    public $activeTimer = null;
    public $pendingApprovals = [];
    public $upcomingAppointments = [];
    public $recentNotifications = [];
    
    public $stats = [
        'tasks_open' => 0,
        'hours_this_week' => 0,
        'approvals_count' => 0,
    ];

    public function mount()
    {
        $this->loadWorkspaceData();
    }

    public function loadWorkspaceData()
    {
        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c'; // Fallback to your ID if session edge-case
        $clearanceLevel = Auth::user()->role->clearance_level ?? 5; // Defaulting to your Super Admin/Partner level

        try {
            $now = Carbon::now();
            $startOfWeek = Carbon::now()->startOfWeek();

            // 1. Personal Stats
            $this->stats['tasks_open'] = DB::table('tasks')
                ->where('assigned_to', $userId)
                ->whereIn('status', ['To-Do', 'In-Progress', 'Failed'])
                ->count();

            $minutesThisWeek = DB::table('task_time_logs')
                ->where('user_id', $userId)
                ->where('start_time', '>=', $startOfWeek)
                ->sum('duration_minutes');
            $this->stats['hours_this_week'] = round($minutesThisWeek / 60, 1);

            // 2. Active Tasks (Backlog)
            $this->activeTasks = DB::table('tasks')
                ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                ->join('projects', 'project_phases.project_id', '=', 'projects.id')
                ->where('tasks.assigned_to', $userId)
                ->whereIn('tasks.status', ['To-Do', 'In-Progress', 'Failed'])
                ->select('tasks.*', 'projects.title as project_title', 'project_phases.title as phase_title')
                ->orderBy('tasks.created_at', 'desc')
                ->get();

            // 3. Active Timer
            $this->activeTimer = DB::table('task_time_logs')
                ->join('tasks', 'task_time_logs.task_id', '=', 'tasks.id')
                ->where('task_time_logs.user_id', $userId)
                ->whereNull('task_time_logs.end_time')
                ->select('task_time_logs.*', 'tasks.title as task_title')
                ->first();

            // 4. Pending Approvals (For Executive / Level 4+)
            if ($clearanceLevel >= 4) {
                $this->pendingApprovals = DB::table('proposals')
                    ->join('organizations', 'proposals.organization_id', '=', 'organizations.id')
                    ->where('proposals.status', 'Pending Auth')
                    ->select('proposals.id', 'proposals.title', 'proposals.total_value', 'proposals.currency', 'organizations.name as client_name', DB::raw("'Proposal' as type"))
                    ->orderBy('proposals.created_at', 'desc')
                    ->get();
                
                $this->stats['approvals_count'] = count($this->pendingApprovals);
            }

            // 5. Upcoming Appointments (Calendar Sync)
            $this->upcomingAppointments = DB::table('appointments')
                ->leftJoin('organizations', 'appointments.organization_id', '=', 'organizations.id')
                ->where('appointments.start_time', '>=', $now)
                ->whereIn('appointments.status', ['Scheduled'])
                // Assuming you might be the host, or you want to see all executive appointments
                ->select('appointments.*', 'organizations.name as client_name')
                ->orderBy('appointments.start_time', 'asc')
                ->limit(4)
                ->get();

            // 6. Recent Notifications
            $this->recentNotifications = DB::table('notifications')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

        } catch (\Exception $e) {
            $this->activeTasks = collect([]);
            $this->activeTimer = null;
            $this->pendingApprovals = collect([]);
            $this->upcomingAppointments = collect([]);
            $this->recentNotifications = collect([]);
        }
    }

    // --- INTERACTIVE WORKSPACE ACTIONS ---

    public function startTimer($taskId)
    {
        $userId = Auth::id();
        
        // Prevent starting if a timer is already running
        if ($this->activeTimer) {
            session()->flash('error', 'A chronometer is already active. Halt it first.');
            return;
        }

        DB::table('task_time_logs')->insert([
            'task_id' => $taskId,
            'user_id' => $userId,
            'start_time' => now(),
            'created_at' => now()
        ]);

        // Update task status to In-Progress
        DB::table('tasks')->where('id', $taskId)->update(['status' => 'In-Progress']);

        $this->loadWorkspaceData();
    }

    public function stopTimer()
    {
        $userId = Auth::id();
        
        if (!$this->activeTimer) return;

        $startTime = Carbon::parse($this->activeTimer->start_time);
        $endTime = now();
        $durationMinutes = $startTime->diffInMinutes($endTime);

        DB::table('task_time_logs')
            ->where('id', $this->activeTimer->id)
            ->update([
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes
            ]);

        $this->loadWorkspaceData();
    }

    public function render()
    {
        return view('livewire.hq.dashboards.my-workspace');
    }
}