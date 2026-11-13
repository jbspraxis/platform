<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\People;
use App\Support\ProjectAccess;
use App\Support\Chat\ProjectChat;

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
    // One project's tickets (?project=<id>, from its project page); the visibility rule still applies
    #[\Livewire\Attributes\Url(as: 'project', except: '')]
    public string $filterProject = '';
    // The list itself is read when the page draws (tickets()), never kept in the page's state

    // Offcanvas Modal State: loaded on the server only (the browser must not swap in another ticket)
    #[Locked]
    public $selectedTicket = null;
    #[Locked]
    public $ticketComments = [];
    #[Locked]
    public $ticketAttachments = [];

    // Interaction State
    public $newCommentText = '';
    public $isInternalOnly = false;
    public $newAttachment;
    #[Locked]
    public $activeTimerId = null;

    public function mount()
    {
        $this->canWork = Auth::user()->can('qa.edit');
        $this->canResolve = Auth::user()->can('qa.approve');

        // NEW: Auto-open ticket if teleporting from the Sprint Board
        if (request()->has('ticket')) {
            $this->openTicket(request()->query('ticket'));
        }
    }

    // --- 1. THE GLOBAL FIREHOSE ---
    /**
     * The tickets this person may see: QA approvers, managers and auditors see every ticket; everyone else
     * the tickets on projects they can see (the sprint board rule, App\Support\ProjectAccess) and those they reported.
     */
    private function ticketQuery(bool $forList = false)
    {
        // The list shows a few columns of each ticket (not its description); an opened ticket shows all of them
        $columns = $forList
            ? ['qa_tickets.id', 'qa_tickets.title', 'qa_tickets.severity', 'qa_tickets.status', 'qa_tickets.task_id', 'qa_tickets.submitted_by_type', 'qa_tickets.created_at']
            : ['qa_tickets.*'];
        $query = DB::table('qa_tickets')
            ->join('projects', 'qa_tickets.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->leftJoin('tasks', 'qa_tickets.task_id', '=', 'tasks.id')
            ->select([
                ...$columns,
                'projects.title as project_title',
                'organizations.name as client_name',
                'tasks.title as task_title',
            ])
            ->orderByRaw("FIELD(qa_tickets.severity, 'Critical', 'Medium', 'Low')") // Prioritize Critical
            ->orderBy('qa_tickets.created_at', 'desc');
        People::joinAuthor($query, 'qa_tickets.submitted_by', 'qa_tickets.submitted_by_type', 'submitter', ['email' => 'submitter_email']);

        if (! Auth::user()->can('qa.approve') && ! ProjectAccess::seesEveryProject()) {
            $query->where(fn ($q) => $q->whereIn('qa_tickets.project_id', ProjectAccess::assignedProjectIds())
                ->orWhere(fn ($own) => $own->where('qa_tickets.submitted_by', Auth::id())->where('qa_tickets.submitted_by_type', 'staff')));
        }

        return $query;
    }

    /** SLA fields for one ticket row */
    private function withSla($ticket): array
    {
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
    }

    /** The ticket as it is now in the database, for someone who may see QA tickets (qa.view) and this one, or null */
    private function freshTicket($ticketId): ?array
    {
        if (! Auth::user()->can('qa.view')) return null;
        $ticket = $this->ticketQuery()->where('qa_tickets.id', $ticketId)->first();

        return $ticket ? $this->withSla($ticket) : null;
    }

    /** The listed tickets for the filters, read once per request (render) */
    #[Computed]
    public function tickets(): array
    {
        $query = $this->ticketQuery(forList: true);

        if ($this->filterStatus !== 'All') {
            if ($this->filterStatus === 'Open') {
                $query->whereIn('qa_tickets.status', ['Open', 'Investigating']);
            } else {
                $query->where('qa_tickets.status', $this->filterStatus);
            }
        }
        if ($this->filterProject !== '') {
            $query->where('qa_tickets.project_id', $this->filterProject);
        }

        return $query->get()->map(fn ($ticket) => $this->withSla($ticket))->toArray();
    }

    public function showEveryProject(): void
    {
        $this->filterProject = '';
    }

    // --- 2. OFFCANVAS TICKET TRIAGE ---
    public function openTicket($ticketId)
    {
        // The id comes from the browser: load the ticket from the database and re-check access
        $this->selectedTicket = $this->freshTicket($ticketId);
        $this->activeTimerId = null;

        if (!$this->selectedTicket) return;
        $ticketId = $this->selectedTicket['id'];

        // The issue's replies: the messages about it in the client's conversation (docs/CHAT.md), internal notes too
        $comments = DB::table('chat_messages')
            ->where('chat_messages.qa_ticket_id', $ticketId)->where('chat_messages.kind', 'text')->whereNull('chat_messages.deleted_at')
            ->select('chat_messages.id', 'chat_messages.body as comment_text', 'chat_messages.is_internal as is_internal_only', 'chat_messages.created_at')
            ->orderBy('chat_messages.created_at')->orderBy('chat_messages.id');
        $this->ticketComments = People::joinAuthor($comments, 'chat_messages.author_id', 'chat_messages.author_type')->get()->toArray();

        // Its files: those of its card and of the messages about it (a name, a kind and a link each)
        $this->ticketAttachments = DB::table('chat_attachments')
            ->join('chat_messages', 'chat_messages.id', '=', 'chat_attachments.message_id')
            ->where('chat_messages.qa_ticket_id', $ticketId)->whereNull('chat_messages.deleted_at')
            ->orderByDesc('chat_attachments.id')
            ->get(['chat_attachments.id', 'chat_attachments.file_name', 'chat_attachments.kind', 'chat_messages.is_internal'])->toArray();

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
            // The client who reported it hears that someone is on it (docs/PWA.md), and the conversation says so
            \App\Support\Pwa\ClientNotify::ticket($ticketId, 'ticket_investigating');
            ProjectChat::event($ticketId, 'investigating', 'staff', Auth::id());
            unset($this->tickets); // the list shows the new status
            $this->selectedTicket['status'] = 'Investigating';
        }
        
        $this->dispatch('open-offcanvas'); // Triggers Alpine.js UI
    }

    /**
     * The selected ticket, re-read from the database, when it can still be worked on (Open or Investigating).
     * Flashes an error and returns null otherwise.
     */
    private function workableTicket(): ?array
    {
        $ticket = $this->selectedTicket ? $this->freshTicket($this->selectedTicket['id']) : null;
        if (! $ticket) {
            session()->flash('error', 'We couldn’t find that issue. Refresh the list and open it again.');
            return null;
        }
        if (! in_array($ticket['status'], ['Open', 'Investigating'], true)) {
            session()->flash('error', 'This issue is already resolved, so it can’t be changed.');
            $this->selectedTicket = $ticket;
            return null;
        }

        return $this->selectedTicket = $ticket;
    }

    // --- 3. COMMUNICATION & EVIDENCE ---
    public function postComment()
    {
        abort_unless(Auth::user()->can('qa.edit'), 403);
        if (! $this->workableTicket()) return;

        $this->validate(['newCommentText' => 'required|string|min:2'], [], ['newCommentText' => 'reply']);

        // A reply in the client's conversation, answering the issue's card; the client's accounts hear of it
        // unless it is an internal note (ProjectChat::post)
        ProjectChat::post($this->selectedTicket['project_id'], 'staff', Auth::id(), [
            'body' => $this->newCommentText,
            'ticket' => $this->selectedTicket['id'],
            'reply_to' => ProjectChat::cardFor($this->selectedTicket['id']),
            'internal' => (bool) $this->isInternalOnly,
        ]);

        $this->newCommentText = '';
        $this->isInternalOnly = false;
        $this->openTicket($this->selectedTicket['id']); // Refresh
    }

    public function uploadEvidence()
    {
        abort_unless(Auth::user()->can('qa.edit'), 403);
        if (! $this->workableTicket()) return;

        $this->validate([
            'newAttachment' => 'required|file|max:10240' // Max 10MB (Selection 4-B)
        ], [], ['newAttachment' => 'file']);

        // A file in the client's conversation, answering the issue's card (the client sees it, as before)
        ProjectChat::post($this->selectedTicket['project_id'], 'staff', Auth::id(), [
            'files' => [ProjectChat::store($this->newAttachment, $this->selectedTicket['project_id'])],
            'ticket' => $this->selectedTicket['id'],
            'reply_to' => ProjectChat::cardFor($this->selectedTicket['id']),
        ]);

        $this->newAttachment = null;
        $this->openTicket($this->selectedTicket['id']); // Refresh
    }

    // --- 4. COPQ REWORK TIMER ---
    public function toggleReworkTimer()
    {
        abort_unless(Auth::user()->can('qa.edit'), 403);
        if (! $this->workableTicket()) return;

        if (!$this->selectedTicket['task_id']) {
            session()->flash('error', 'We can’t track rework time on this issue because it isn’t linked to a task.');
            return;
        }

        if ($this->activeTimerId) {
            // Stop Timer (only this user’s own open log)
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
            session()->flash('success', 'Rework timer paused. The time is added to the task.');

        } else {
            // Start Timer
            DB::table('task_time_logs')->insert([
                'task_id' => $this->selectedTicket['task_id'],
                'user_id' => Auth::id(),
                'start_time' => now(),
                'created_at' => now()
            ]);
            $this->openTicket($this->selectedTicket['id']); // Reload to grab the new Timer ID
            session()->flash('success', 'Rework timer started.');
        }
    }

    // --- 5. GATEKEEPER RESOLUTION (Rule 5: B & 9: A) ---
    public function markResolved()
    {
        // Only QA approvers (qa.approve) can officially resolve a QA Ticket
        if (! Auth::user()->can('qa.approve')) {
            session()->flash('error', 'You don’t have permission to resolve issues. Ask an administrator to change your role.');
            return;
        }
        if (! $this->workableTicket()) return;

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
                    'title' => 'Issue resolved',
                    'message' => "The issue you reported, ‘{$this->selectedTicket['title']}’, is resolved. See it in Quality and issues.",
                    'type' => 'QA_Update',
                    'created_at' => now()
                ]);
            }
        });

        \App\Support\Pwa\ClientNotify::ticket($this->selectedTicket['id'], 'ticket_resolved');
        ProjectChat::event($this->selectedTicket['id'], 'resolved', 'staff', Auth::id());

        // Close offcanvas and refresh grid
        $this->dispatch('close-offcanvas');
        unset($this->tickets); // the list shows the new status
        $this->selectedTicket = null;
        session()->flash('success', 'Issue resolved. The person who reported it now sees it as resolved.');
    }

    public function render()
    {
        return view('livewire.hq.operations.qa-hub', ['tickets' => $this->tickets]);
    }
}