<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use App\Models\Tracker;
use App\Models\TrackerMilestone;
use App\Models\TrackerTask;
use App\Models\TrackerMessage;
use App\Models\TrackerUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.client-light')]
class TrackerMatrix extends Component
{
    use WithFileUploads;

    // ==========================================================================
    // CORE STATE
    // ==========================================================================
    // Set in mount after the access check; the browser must not switch to another tracker
    #[Locked]
    public $trackerId;
    
    // ==========================================================================
    // INJECTION & EDITING STATES
    // ==========================================================================
    
    // Milestone State
    public $newMilestoneTitle = '';
    public $editingMilestoneId = null;
    public $editingMilestoneTitle = '';

    // Task State
    public $newTaskTitle = '';
    public $newTaskAssignee = '';
    public $newTaskDueDate = '';
    
    public $activeTaskId = null;
    public $editTaskTitle = '';
    public $editTaskAssignee = '';
    public $editTaskDueDate = '';

    // Reassignment State
    public $newAssigneeId = '';

    // Communication State
    public $chatMessage = '';
    public $mediaUpload;

    // How many of the latest messages the conversation shows; "Show earlier messages" adds another page
    public const MESSAGES_PAGE = 200;
    #[Locked]
    public int $messageLimit = self::MESSAGES_PAGE;

    // ==========================================================================
    // INITIALIZATION & SECURITY PERIMETER
    // ==========================================================================

    public function mount($id)
    {
        $this->trackerId = $id;
        $user = Auth::guard('tracker')->user();
        
        // Strict Isolation Matrix: Consultants only see explicitly assigned projects
        if ($user->role === 'consultant') {
            $hasAccess = DB::table('tracker_user_access')
                           ->where('tracker_id', $id)
                           ->where('user_id', $user->id)
                           ->exists();
            if (!$hasAccess) abort(403, 'You don’t have access to this project tracker. Ask your project lead to add you to it.');
        }
    }

    // ==========================================================================
    // MILESTONE ARCHITECTURE (CRUD)
    // ==========================================================================

    public function injectMilestone()
    {
        $this->authorizeAdmin();
        
        $this->validate(['newMilestoneTitle' => 'required|string|max:255'], [], ['newMilestoneTitle' => 'milestone name']);

        try {
            DB::transaction(function () {
                $tracker = Tracker::findOrFail($this->trackerId);

                TrackerMilestone::create([
                    'tracker_id' => $this->trackerId,
                    'title' => $this->newMilestoneTitle,
                    'sort_order' => $tracker->milestones()->count() + 1
                ]);
            });

            $this->newMilestoneTitle = '';
            $this->recalculateProgress();
            
            $this->dispatch('system-alert', ['type' => 'success', 'message' => 'Milestone added.']);
            $this->dispatch('modal-close', ['modal' => 'milestone']);
            
        } catch (\Exception $e) {
            Log::error('Milestone Injection Failed: ' . $e->getMessage());
            $this->dispatch('system-alert', ['type' => 'error', 'message' => 'We couldn’t add the milestone. Try again.']);
        }
    }

    public function updateMilestoneTitle()
    {
        $this->authorizeAdmin();
        $this->validate(['editingMilestoneTitle' => 'required|string|max:255'], [], ['editingMilestoneTitle' => 'milestone name']);

        $milestone = TrackerMilestone::findOrFail($this->editingMilestoneId);
        $milestone->update(['title' => $this->editingMilestoneTitle]);

        $this->editingMilestoneId = null;
        $this->editingMilestoneTitle = '';
        $this->dispatch('system-alert', ['type' => 'success', 'message' => 'Milestone renamed.']);
    }

    public function deleteMilestone($id)
    {
        $this->authorizeAdmin();

        try {
            DB::transaction(function () use ($id) {
                $milestone = TrackerMilestone::where('tracker_id', $this->trackerId)->findOrFail($id);
                
                // Cascade delete associated tasks and their messages to prevent database orphans
                $taskIds = $milestone->tasks()->pluck('id');
                TrackerMessage::whereIn('task_id', $taskIds)->delete();
                $milestone->tasks()->delete();
                $milestone->delete();
            });

            $this->recalculateProgress();
            $this->dispatch('system-alert', ['type' => 'success', 'message' => 'Milestone deleted, with its tasks and their messages.']);
            
        } catch (\Exception $e) {
            Log::error('Milestone Purge Failed: ' . $e->getMessage());
            $this->dispatch('system-alert', ['type' => 'error', 'message' => 'We couldn’t delete the milestone. Try again.']);
        }
    }

    // ==========================================================================
    // TASK NODE ENGINEERING (CRUD)
    // ==========================================================================

    public function injectTask($milestoneId)
    {
        $this->authorizeAdmin();
        
        $this->validate([
            'newTaskTitle' => 'required|string|max:255',
            'newTaskAssignee' => 'nullable|exists:tracker_users,id',
            'newTaskDueDate' => 'nullable|date'
        ], [], ['newTaskTitle' => 'task name', 'newTaskAssignee' => 'person', 'newTaskDueDate' => 'due date']);

        try {
            DB::transaction(function () use ($milestoneId) {
                // Ensure milestone belongs to active tracker
                TrackerMilestone::where('tracker_id', $this->trackerId)->findOrFail($milestoneId);

                TrackerTask::create([
                    'milestone_id' => $milestoneId,
                    'title' => $this->newTaskTitle,
                    'assigned_to' => $this->newTaskAssignee ?: null,
                    'due_date' => $this->newTaskDueDate ?: null,
                    'status' => 'todo'
                ]);
            });

            $this->reset(['newTaskTitle', 'newTaskAssignee', 'newTaskDueDate']);
            $this->recalculateProgress();
            
            $this->dispatch('system-alert', ['type' => 'success', 'message' => 'Task added.']);
            $this->dispatch('modal-close', ['modal' => 'task']);

        } catch (\Exception $e) {
            Log::error('Task Deployment Failed: ' . $e->getMessage());
            $this->dispatch('system-alert', ['type' => 'error', 'message' => 'We couldn’t add the task. Try again.']);
        }
    }

    public function updateTaskStatus($taskId, $newStatus)
    {
        $task = TrackerTask::whereHas('milestone', function($q) {
            $q->where('tracker_id', $this->trackerId);
        })->findOrFail($taskId);
        
        $user = Auth::guard('tracker')->user();

        // Strict Consultant Matrix: Can only update their explicitly assigned nodes.
        if ($user->role === 'consultant' && $task->assigned_to !== $user->id) {
            $this->dispatch('system-alert', ['type' => 'error', 'message' => 'This task is assigned to someone else, so you can’t change its status.']);
            return;
        }

        $task->update(['status' => $newStatus]);
        $this->recalculateProgress();
        $this->dispatch('system-alert', ['type' => 'success', 'message' => 'Task status updated.']);
    }

    public function loadTaskForEditing($taskId)
    {
        $this->authorizeAdmin();
        
        $task = TrackerTask::findOrFail($taskId);
        $this->activeTaskId = $task->id;
        $this->editTaskTitle = $task->title;
        $this->editTaskAssignee = $task->assigned_to;
        $this->editTaskDueDate = $task->due_date;
        
        $this->dispatch('open-modal', ['modal' => 'editTask']);
    }

    public function updateTaskDetails()
    {
        $this->authorizeAdmin();
        
        $this->validate([
            'editTaskTitle' => 'required|string|max:255',
            'editTaskAssignee' => 'nullable|exists:tracker_users,id',
            'editTaskDueDate' => 'nullable|date'
        ], [], ['editTaskTitle' => 'task name', 'editTaskAssignee' => 'person', 'editTaskDueDate' => 'due date']);

        $task = TrackerTask::findOrFail($this->activeTaskId);
        $task->update([
            'title' => $this->editTaskTitle,
            'assigned_to' => $this->editTaskAssignee ?: null,
            'due_date' => $this->editTaskDueDate ?: null,
        ]);

        $this->reset(['activeTaskId', 'editTaskTitle', 'editTaskAssignee', 'editTaskDueDate']);
        $this->dispatch('system-alert', ['type' => 'success', 'message' => 'Task updated.']);
        $this->dispatch('modal-close', ['modal' => 'editTask']);
    }

    public function deleteTask($taskId)
    {
        $this->authorizeAdmin();

        try {
            DB::transaction(function () use ($taskId) {
                $task = TrackerTask::findOrFail($taskId);
                // Purge associated comms
                TrackerMessage::where('task_id', $task->id)->delete();
                $task->delete();
            });

            $this->recalculateProgress();
            $this->dispatch('system-alert', ['type' => 'success', 'message' => 'Task deleted.']);
            
        } catch (\Exception $e) {
            Log::error('Task Purge Failed: ' . $e->getMessage());
            $this->dispatch('system-alert', ['type' => 'error', 'message' => 'We couldn’t delete the task. Try again.']);
        }
    }

    public function reassignTask()
    {
        $this->authorizeAdmin();
        
        $this->validate(['newAssigneeId' => 'required|exists:tracker_users,id'], [], ['newAssigneeId' => 'person']);

        TrackerTask::where('id', $this->activeTaskId)->update(['assigned_to' => $this->newAssigneeId]);
        
        $this->reset(['activeTaskId', 'newAssigneeId']);
        
        $this->dispatch('system-alert', ['type' => 'success', 'message' => 'Task reassigned.']);
        $this->dispatch('modal-close', ['modal' => 'reassign']);
    }

    // ==========================================================================
    // ALGORITHMIC PROGRESSION
    // ==========================================================================
    
    private function recalculateProgress()
    {
        // The tracker's tasks and how many are done, in one query
        $counts = DB::table('tracker_tasks')
            ->join('tracker_milestones', 'tracker_milestones.id', '=', 'tracker_tasks.milestone_id')
            ->where('tracker_milestones.tracker_id', $this->trackerId)
            ->selectRaw("COUNT(*) as total, COALESCE(SUM(CASE WHEN tracker_tasks.status = 'done' THEN 1 ELSE 0 END), 0) as done")
            ->first();
        $totalTasks = (int) $counts->total;

        if ($totalTasks === 0) {
            Tracker::where('id', $this->trackerId)->update(['progress_percentage' => 0]);
            return;
        }

        $completedTasks = (int) $counts->done;

        $percentage = ($completedTasks / $totalTasks) * 100;

        Tracker::where('id', $this->trackerId)->update(['progress_percentage' => round($percentage, 2)]);
    }

    // ==========================================================================
    // UNIFIED COMMUNICATIONS & MEDIA
    // ==========================================================================

    public function transmitMessage($taskId = null)
    {
        $this->validate([
            'chatMessage' => 'required_without:mediaUpload|string|nullable|max:2000',
            'mediaUpload' => 'nullable|image|max:10240' // 10MB Max Image Size
        ], [], ['chatMessage' => 'message', 'mediaUpload' => 'image']);

        $user = Auth::guard('tracker')->user();
        $mediaPath = null;

        if ($this->mediaUpload) {
            $mediaPath = $this->mediaUpload->store('tracker_media', 'public');
        }

        TrackerMessage::create([
            'tracker_id' => $this->trackerId,
            'task_id' => $taskId,
            'sender_type' => 'staff',
            'sender_user_id' => $user->id,
            'body' => $this->chatMessage ?: null,
            'media_path' => $mediaPath,
            'media_type' => $this->mediaUpload ? 'image' : null,
        ]);

        $this->reset(['chatMessage', 'mediaUpload']);
        
        // Trigger Alpine.js to auto-scroll to the newest message natively
        $this->dispatch('message-transmitted');
    }

    /** "Show earlier messages": the conversation shows another page of older messages */
    public function showEarlierMessages(): void
    {
        $this->messageLimit += self::MESSAGES_PAGE;
    }

    // ==========================================================================
    // SECURITY UTILITIES
    // ==========================================================================
    
    private function authorizeAdmin()
    {
        if (Auth::guard('tracker')->user()->role === 'consultant') {
            abort(403, 'Only the project lead can change milestones and tasks.');
        }
    }

    // ==========================================================================
    // DATA HYDRATION & RENDER
    // ==========================================================================

    public function render()
    {
        // Eager load with specific ordering to prevent chaotic UI rendering
        $activeTracker = Tracker::with([
            'milestones' => function($q) { $q->orderBy('sort_order', 'asc'); },
            'milestones.tasks' => function($q) { $q->orderBy('created_at', 'asc'); },
            'milestones.tasks.assignee'
        ])->findOrFail($this->trackerId);
        
        // The latest messages only (one more than shown, to know whether earlier ones exist), oldest first on screen
        $latest = TrackerMessage::with(['sender', 'task'])
            ->where('tracker_id', $this->trackerId)
            ->orderBy('created_at', 'desc')->orderBy('id', 'desc')
            ->limit($this->messageLimit + 1)
            ->get();
        $hasEarlierMessages = $latest->count() > $this->messageLimit;
        $messages = $latest->take($this->messageLimit)->reverse()->values();

        // Filter out archived/deleted users to prevent ghost assignments; only what the pickers show
        $consultants = TrackerUser::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'role']);

        return view('livewire.hq.operations.tracker-matrix', [
            'activeTracker' => $activeTracker,
            'milestones' => $activeTracker->milestones,
            'messages' => $messages,
            'hasEarlierMessages' => $hasEarlierMessages,
            'consultants' => $consultants
        ]);
    }
}