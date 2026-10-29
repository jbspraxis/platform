<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\People;

#[Layout('components.layouts.hq')]
class QaHub extends Component
{
    use WithFileUploads;

    // What this user may do here (set in mount, see config/access.php)
    #[Locked]
    public bool $canWork = false;    // qa.edit: comment, upload evidence, rework timer
    #[Locked]
    public bool $canResolve = false; // qa.approve: verify and close tickets

    // Firehose State
    public $filterStatus = 'Open'; // Default to Open/Investigating
    public $tickets = [];
    
    // Offcanvas Modal State
    public $selectedTicket = null;
    public $ticketComments = [];
    public $ticketAttachments = [];
    
    // Interaction State
    public $newCommentText = '';
    public $isInternalOnly = false;
    public $newAttachment;
    public $activeTimerId = null;

    public function mount()
    {
        $this->canWork = Auth::user()->can('qa.edit');
        $this->canResolve = Auth::user()->can('qa.approve');
        $this->loadFirehose();

        // NEW: Auto-open ticket if teleporting from the Sprint Board
        if (request()->has('ticket')) {
            $this->openTicket(request()->query('ticket'));
        }
    }

    // --- 1. THE GLOBAL FIREHOSE ---
    public function loadFirehose()
    {
        $query = DB::table('qa_tickets')
            ->join('projects', 'qa_tickets.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->leftJoin('tasks', 'qa_tickets.task_id', '=', 'tasks.id')
            ->select(
                'qa_tickets.*',
                'projects.title as project_title',
                'organizations.name as client_name',
                'tasks.title as task_title'
            )
            ->orderByRaw("FIELD(qa_tickets.severity, 'Critical', 'Medium', 'Low')") // Prioritize Critical
            ->orderBy('qa_tickets.created_at', 'desc');
        People::joinAuthor($query, 'qa_tickets.submitted_by', 'qa_tickets.submitted_by_type', 'submitter', ['email' => 'submitter_email']);

        if ($this->filterStatus !== 'All') {
            if ($this->filterStatus === 'Open') {
                $query->whereIn('qa_tickets.status', ['Open', 'Investigating']);
            } else {
                $query->where('qa_tickets.status', $this->filterStatus);
            }
        }

        $this->tickets = $query->get()->map(function($ticket) {
            // Dynamic SLA Calculation (Question 8: B)
            $created = Carbon::parse($ticket->created_at);
            $slaHours = match($ticket->severity) {
                'Critical' => 4,
                'Medium' => 24,
                'Low' => 72,
                default => 24
            };
            
            $ticket->sla_deadline = $created->copy()->addHours($slaHours);
            $ticket->is_breached = $ticket->sla_deadline->isPast() && in_array($ticket->status, ['Open', 'Investigating']);
            $ticket->time_remaining = $ticket->sla_deadline->diffForHumans(['parts' => 2, 'short' => true]);
            
            return (array) $ticket;
        })->toArray();
    }

    public function updatedFilterStatus()
    {
        $this->loadFirehose();
    }

    // --- 2. OFFCANVAS TICKET TRIAGE ---
    public function openTicket($ticketId)
    {
        $this->selectedTicket = collect($this->tickets)->firstWhere('id', $ticketId);
        
        if (!$this->selectedTicket) return;

        // Load Native Threading (Comments)
        $comments = DB::table('qa_comments')
            ->where('qa_comments.ticket_id', $ticketId)
            ->select('qa_comments.*')
            ->orderBy('qa_comments.created_at', 'asc');
        $this->ticketComments = People::joinAuthor($comments, 'qa_comments.user_id', 'qa_comments.author_type')->get()->toArray();

        // Load Evidence Gallery (Attachments)
        $attachments = DB::table('qa_attachments')
            ->where('qa_attachments.ticket_id', $ticketId)
            ->select('qa_attachments.*')
            ->orderBy('qa_attachments.created_at', 'desc');
        $this->ticketAttachments = People::joinAuthor($attachments, 'qa_attachments.uploaded_by', 'qa_attachments.uploader_type', 'uploader')->get()->toArray();

        // Check for Active COPQ Timer
        if ($this->selectedTicket['task_id']) {
            $openLog = DB::table('task_time_logs')
                ->where('task_id', $this->selectedTicket['task_id'])
                ->where('user_id', Auth::id())
                ->whereNull('end_time')
                ->orderBy('start_time', 'desc')
                ->first();
                
            $this->activeTimerId = $openLog ? $openLog->id : null;
        }

        // Auto-Update Status to Investigating if it was Open (only for someone who works tickets: qa.edit)
        if ($this->selectedTicket['status'] === 'Open' && Auth::user()->can('qa.edit')) {
            DB::table('qa_tickets')->where('id', $ticketId)->update(['status' => 'Investigating', 'updated_at' => now()]);
            $this->loadFirehose();
            $this->selectedTicket['status'] = 'Investigating';
        }
        
        $this->dispatch('open-offcanvas'); // Triggers Alpine.js UI
    }

    // --- 3. COMMUNICATION & EVIDENCE ---
    public function postComment()
    {
        abort_unless(Auth::user()->can('qa.edit'), 403);

        $this->validate(['newCommentText' => 'required|string|min:2']);

        DB::table('qa_comments')->insert([
            'id' => (string) Str::uuid(),
            'ticket_id' => $this->selectedTicket['id'],
            'user_id' => Auth::id(),
            'comment_text' => $this->newCommentText,
            'is_internal_only' => $this->isInternalOnly ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->newCommentText = '';
        $this->isInternalOnly = false;
        $this->openTicket($this->selectedTicket['id']); // Refresh
    }

    public function uploadEvidence()
    {
        abort_unless(Auth::user()->can('qa.edit'), 403);

        $this->validate([
            'newAttachment' => 'required|file|max:10240' // Max 10MB (Selection 4-B)
        ]);

        $path = $this->newAttachment->store('qa_evidence', 'local');

        DB::table('qa_attachments')->insert([
            'id' => (string) Str::uuid(),
            'ticket_id' => $this->selectedTicket['id'],
            'uploaded_by' => Auth::id(),
            'file_name' => $this->newAttachment->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $this->newAttachment->getMimeType(),
            'size_bytes' => $this->newAttachment->getSize(),
            'created_at' => now()
        ]);

        $this->newAttachment = null;
        $this->openTicket($this->selectedTicket['id']); // Refresh
    }

    // --- 4. COPQ REWORK TIMER ---
    public function toggleReworkTimer()
    {
        abort_unless(Auth::user()->can('qa.edit'), 403);

        if (!$this->selectedTicket['task_id']) {
            session()->flash('error', 'Cannot track rework time: Ticket is not linked to a specific Task.');
            return;
        }

        if ($this->activeTimerId) {
            // Stop Timer (only this user's own open log; activeTimerId comes from the browser)
            $openLog = DB::table('task_time_logs')->where('id', $this->activeTimerId)
                ->where('user_id', Auth::id())->whereNull('end_time')->first();
            if ($openLog) {
                $endTime = now();
                $durationMinutes = Carbon::parse($openLog->start_time)->diffInMinutes($endTime);
                
                $rate = DB::table('compensation_history')->where('user_id', Auth::id())->orderBy('effective_from', 'desc')->value('hourly_rate') ?? 50.00;
                $costCalculated = ($durationMinutes / 60) * $rate;

                DB::table('task_time_logs')->where('id', $openLog->id)->update([
                    'end_time' => $endTime,
                    'duration_minutes' => $durationMinutes,
                    'cost_calculated' => $costCalculated
                ]);

                DB::table('tasks')->where('id', $this->selectedTicket['task_id'])->update([
                    'actual_hours_logged' => DB::raw("actual_hours_logged + " . round($durationMinutes / 60, 2))
                ]);
            }
            $this->activeTimerId = null;
            session()->flash('success', 'Rework COPQ Timer Paused.');

        } else {
            // Start Timer
            DB::table('task_time_logs')->insert([
                'task_id' => $this->selectedTicket['task_id'],
                'user_id' => Auth::id(),
                'start_time' => now(),
                'created_at' => now()
            ]);
            $this->openTicket($this->selectedTicket['id']); // Reload to grab the new Timer ID
            session()->flash('success', 'Rework COPQ Timer Engaged. Recording...');
        }
    }

    // --- 5. GATEKEEPER RESOLUTION (Rule 5: B & 9: A) ---
    public function markResolved()
    {
        // Only QA approvers (qa.approve) can officially resolve a QA Ticket
        if (! Auth::user()->can('qa.approve')) {
            session()->flash('error', 'GATEKEEPER LOCK: Only Level 4/5 Command can verify and close vulnerabilities.');
            return;
        }

        DB::transaction(function () {
            DB::table('qa_tickets')->where('id', $this->selectedTicket['id'])->update([
                'status' => 'Resolved',
                'resolved_at' => now(),
                'resolved_by' => Auth::id(),
                'updated_at' => now()
            ]);

            // Notifications are for staff only; a client sees the new status on their QA list
            if (($this->selectedTicket['submitted_by_type'] ?? 'client') === 'staff') {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $this->selectedTicket['submitted_by'],
                    'title' => 'VULNERABILITY RESOLVED',
                    'message' => "HQ has successfully patched ticket: {$this->selectedTicket['title']}.",
                    'type' => 'QA_Update',
                    'created_at' => now()
                ]);
            }
        });

        // Close offcanvas and refresh grid
        $this->dispatch('close-offcanvas');
        $this->loadFirehose();
        $this->selectedTicket = null;
        session()->flash('success', 'Vulnerability Patched. Client Notified.');
    }

    public function render()
    {
        return view('livewire.hq.operations.qa-hub');
    }
}