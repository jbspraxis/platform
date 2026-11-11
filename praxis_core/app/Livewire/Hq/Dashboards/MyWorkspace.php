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
    // Nothing is kept in the page's state: the lists are read when the page draws (render), with only the columns it
    // shows, so the landing page and each Start or Stop timer carry no task or appointment rows back and forth

    /** The person's running timer, with its task's title, or null */
    private function openTimer(string $userId): ?object
    {
        return DB::table('task_time_logs')
            ->join('tasks', 'task_time_logs.task_id', '=', 'tasks.id')
            ->where('task_time_logs.user_id', $userId)
            ->whereNull('task_time_logs.end_time')
            ->select('task_time_logs.id', 'task_time_logs.task_id', 'task_time_logs.start_time', 'tasks.title as task_title')
            ->first();
    }

    /** Everything the page shows */
    private function workspaceData(): array
    {
        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c'; // Fallback to your ID if session edge-case

        $data = [
            'activeTasks' => collect([]),
            'activeTimer' => null,
            'pendingApprovals' => collect([]),
            'upcomingAppointments' => collect([]),
            'recentNotifications' => collect([]),
            'stats' => [
                'tasks_open' => 0,
                'hours_this_week' => 0,
                'approvals_count' => 0,
            ],
        ];

        try {
            $startOfWeek = Carbon::now()->startOfWeek();

            // 1. Personal Stats (a deleted task is not open work)
            $data['stats']['tasks_open'] = DB::table('tasks')
                ->where('assigned_to', $userId)
                ->whereIn('status', ['To-Do', 'In-Progress', 'Failed'])
                ->whereNull('deleted_at')
                ->count();

            $minutesThisWeek = DB::table('task_time_logs')
                ->where('user_id', $userId)
                ->where('start_time', '>=', $startOfWeek)
                ->sum('duration_minutes');
            $data['stats']['hours_this_week'] = round($minutesThisWeek / 60, 1);

            // 2. Active Tasks (Backlog), without deleted tasks
            $data['activeTasks'] = DB::table('tasks')
                ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                ->join('projects', 'project_phases.project_id', '=', 'projects.id')
                ->where('tasks.assigned_to', $userId)
                ->whereIn('tasks.status', ['To-Do', 'In-Progress', 'Failed'])
                ->whereNull('tasks.deleted_at')
                ->select('tasks.id', 'tasks.title', 'tasks.status', 'projects.title as project_title', 'project_phases.title as phase_title')
                ->orderBy('tasks.created_at', 'desc')
                ->get();

            // 3. Active Timer
            $data['activeTimer'] = $this->openTimer($userId);

            // 4. Proposals awaiting client signature (overview-level widget: dashboard.view)
            if (Auth::user()->can('dashboard.view')) {
                $data['pendingApprovals'] = DB::table('proposals')
                    ->join('organizations', 'proposals.organization_id', '=', 'organizations.id')
                    ->where('proposals.status', 'Pending Auth')
                    ->select('proposals.id', 'proposals.title', 'proposals.total_value', 'proposals.currency', 'organizations.name as client_name', DB::raw("'Proposal' as type"))
                    ->orderBy('proposals.created_at', 'desc')
                    ->get();

                $data['stats']['approvals_count'] = count($data['pendingApprovals']);
            }

            // 5. Upcoming Appointments (Calendar Sync)
            // Appointment times are stored as Lagos wall-clock time (WAT), so compare with Lagos time, not UTC
            $data['upcomingAppointments'] = DB::table('appointments')
                ->leftJoin('organizations', 'appointments.organization_id', '=', 'organizations.id')
                ->where('appointments.start_time', '>=', Carbon::now('Africa/Lagos')->format('Y-m-d H:i:s'))
                ->whereIn('appointments.status', ['Scheduled'])
                // Assuming you might be the host, or you want to see all executive appointments
                ->select('appointments.title', 'appointments.start_time', 'organizations.name as client_name')
                ->orderBy('appointments.start_time', 'asc')
                ->limit(4)
                ->get();

            // 6. Recent Notifications
            $data['recentNotifications'] = DB::table('notifications')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['type', 'title', 'message', 'created_at']);

        } catch (\Exception $e) {
            $data['activeTasks'] = collect([]);
            $data['activeTimer'] = null;
            $data['pendingApprovals'] = collect([]);
            $data['upcomingAppointments'] = collect([]);
            $data['recentNotifications'] = collect([]);
        }

        return $data;
    }

    // --- INTERACTIVE WORKSPACE ACTIONS ---

    public function startTimer($taskId)
    {
        $userId = Auth::id();

        // Your own assigned tasks, or any task if you may log time
        $ownTask = DB::table('tasks')->where('id', $taskId)->where('assigned_to', $userId)->exists();
        if (! $ownTask && ! Auth::user()->can('timesheets.create')) {
            session()->flash('error', 'You can only log time on tasks assigned to you.');
            return;
        }

        // Prevent starting if a timer is already running (read now, so one started in another tab counts too)
        if ($this->openTimer($userId)) {
            session()->flash('error', 'A timer is already running. Stop it before you start another.');
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
    }

    public function stopTimer()
    {
        $userId = Auth::id();

        // Your own running timer, read now
        $timer = $this->openTimer($userId);
        if (! $timer) return;

        $startTime = Carbon::parse($timer->start_time);
        $endTime = now();
        $durationMinutes = $startTime->diffInMinutes($endTime);

        DB::table('task_time_logs')
            ->where('id', $timer->id)
            ->where('user_id', $userId) // only your own timer
            ->update([
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes
            ]);
    }

    public function render()
    {
        return view('livewire.hq.dashboards.my-workspace', $this->workspaceData());
    }
}
