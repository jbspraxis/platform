<?php

namespace App\Livewire\ClientOs;

use App\Livewire\Concerns\ProjectChatRoom;
use App\Support\Access;
use App\Support\Chat\ProjectChat;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The client's conversation with its delivery team, one per project (docs/CHAT.md): the floating button with its
 * unread count, and the chat itself (a panel on computers, the whole screen on phones). Mounted once in the
 * dashboard, keyed by project, so switching sections never redraws it. What both sides share is in
 * ProjectChatRoom; here: who the client is, and issues (report one, approve a fix, close one and approve its task).
 */
class DeliveryChat extends Component
{
    use ProjectChatRoom;
    use WithFileUploads;

    #[Locked]
    public bool $canWrite = false;
    #[Locked]
    public bool $canApprove = false;
    public bool $startOpen = false;

    // Reporting an issue from the chat
    public bool $issueMode = false;
    public string $issueTaskId = '';
    public string $issueTitle = '';
    public string $issueSeverity = 'Medium';

    private ?object $client = null;

    /** $locale: the language the dashboard worked out for this page */
    public function mount(string $projectId, ?string $locale = null, bool $startOpen = false): void
    {
        if (in_array($locale, ['en', 'fr'], true)) {
            App::setLocale($locale);
        }
        $this->projectId = $projectId;
        $client = $this->chatPerson();
        $this->canWrite = Access::allows($client, 'portal.qa.create');
        $this->canApprove = Access::allows($client, 'portal.projects.approve');
        $this->startOpen = $startOpen;
        $this->signature = ProjectChat::signature($projectId, 'client');
        $this->lastReadAtOpen = $this->lastRead();
    }

    protected function chatParty(): string
    {
        return 'client';
    }

    /**
     * The signed-in portal account (active), on a project of its organisation it isn't kept away from, with the
     * portal's Quality and issues (and, to write, its create permission). Read again on every request.
     */
    protected function chatPerson(bool $write = false): object
    {
        $client = $this->client ??= Auth::guard('client')->user();
        abort_unless($client && $client->status === 'Active' && Access::allows($client, 'portal.qa.view'), 403);
        abort_unless(DB::table('projects')->where('id', $this->projectId)->where('organization_id', $client->organization_id)->whereNull('deleted_at')
            ->when($client->restricted_project_id, fn ($q) => $q->where('id', $client->restricted_project_id))->exists(), 403);
        abort_if($write && ! Access::allows($client, 'portal.qa.create'), 403);

        return $client;
    }

    public function send(): void
    {
        $this->issueMode ? $this->reportIssue() : $this->sendMessage();
    }

    // ---- Issues --------------------------------------------------------------------------------------------------

    private function reportIssue(): void
    {
        $client = $this->chatPerson(true);
        $this->validate([
            'issueTitle' => 'required|string|max:150',
            'draft' => 'required|string|max:' . ProjectChat::BODY_MAX,
            'issueSeverity' => 'required|in:Low,Medium,Critical',
            'files' => 'array|max:' . ProjectChat::MAX_FILES,
            'files.*' => 'file|max:' . ProjectChat::MAX_FILE_KB . '|mimes:' . ProjectChat::FILE_MIMES,
        ]);
        if ($this->issueTaskId !== '' && ! $this->clientTasks()->where('tasks.id', $this->issueTaskId)->exists()) {
            $this->addError('issueTaskId', __('chat.validation.task'));

            return;
        }
        $stored = array_map(fn ($file) => ProjectChat::store($file, $this->projectId), $this->files);
        ProjectChat::reportIssue($this->projectId, $client, $this->issueTaskId ?: null, trim($this->issueTitle), $this->issueSeverity, $this->draft, $stored);
        $this->reset(['issueMode', 'issueTaskId', 'issueTitle', 'issueSeverity']);
        $this->afterSend();
        session()->flash('success', __('chat.issue.reported'));
    }

    /** Report an issue, maybe about a task (Project progress: "Report an issue") */
    #[On('chat-start-issue')]
    public function startIssue(?string $taskId = null): void
    {
        $this->chatPerson(true);
        $this->issueMode = true;
        $this->replyTo = null;
        $task = $taskId ? $this->clientTasks()->where('tasks.id', $taskId)->first(['tasks.id', 'tasks.title']) : null;
        $this->issueTaskId = $task->id ?? '';
        if ($task && trim($this->issueTitle) === '') {
            $this->issueTitle = Str::limit(__('portal.qa.prefill', ['task' => $task->title]), 150, '');
        }
        $this->dispatch('chat-focus', id: $this->getId());
    }

    public function cancelIssue(): void
    {
        $this->reset(['issueMode', 'issueTaskId', 'issueTitle', 'issueSeverity']);
        $this->resetErrorBag();
    }

    /** A ticket of this project the client may act on */
    private function ticket(string $ticketId): ?object
    {
        return DB::table('qa_tickets')->where('id', $ticketId)->where('project_id', $this->projectId)->whereNull('deleted_at')->first(['id', 'task_id', 'status', 'title']);
    }

    /** "Approve the fix": a resolved issue is closed (and its task, if the client sees it, done) */
    public function approveFix(string $ticketId): void
    {
        $client = $this->chatPerson();
        abort_unless(Access::allows($client, 'portal.projects.approve'), 403);
        $ticket = $this->ticket($ticketId);
        if (! $ticket || $ticket->status !== 'Resolved') {
            return;
        }
        DB::table('qa_tickets')->where('id', $ticket->id)->update(['status' => 'Closed', 'updated_at' => now()]);
        if ($ticket->task_id && $this->clientTasks()->where('tasks.id', $ticket->task_id)->exists()) {
            DB::table('tasks')->where('id', $ticket->task_id)->update(['status' => 'Done', 'updated_at' => now()]);
        }
        ProjectChat::event($ticket->id, 'fix_approved', 'client', $client->id);
        session()->flash('success', __('chat.issue.fix_approved'));
    }

    /** "Close issue and approve task": the issue is closed and its task marked done */
    public function approveTask(string $ticketId): void
    {
        $client = $this->chatPerson();
        abort_unless(Access::allows($client, 'portal.projects.approve'), 403);
        $ticket = $this->ticket($ticketId);
        if (! $ticket || ! $ticket->task_id || $ticket->status === 'Closed' || ! $this->clientTasks()->where('tasks.id', $ticket->task_id)->exists()) {
            return;
        }
        DB::table('qa_tickets')->where('id', $ticket->id)->update(['status' => 'Closed', 'updated_at' => now()]);
        DB::table('tasks')->where('id', $ticket->task_id)->update(['status' => 'Done', 'updated_at' => now()]);
        DB::table('task_comments')->insert(['task_id' => $ticket->task_id, 'user_id' => $client->id, 'author_type' => 'client',
            'comment_text' => __('portal.tasks.approved_from_issue_comment', [], 'en'), 'created_at' => now()]);
        ProjectChat::event($ticket->id, 'task_approved', 'client', $client->id);
        session()->flash('success', __('chat.issue.task_approved'));
    }

    /** The project's tasks the client sees (docs/PROJECTS.md: shown to the client, not deleted) */
    private function clientTasks()
    {
        return DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->projectId)->where('tasks.is_client_visible', 1)
            ->whereNull('tasks.deleted_at')->whereNull('project_phases.deleted_at');
    }

    public function render()
    {
        $client = $this->chatPerson();
        [$messages, $more] = ProjectChat::messages($this->projectId, 'client', $this->limit);
        [$seen, $typing] = ProjectChat::state($this->projectId, 'client', $client->id);

        return view('livewire.client-os.delivery-chat', [
            'client' => $client,
            'messages' => $messages,
            'more' => $more,
            'seen' => $seen,
            'typing' => $typing,
            'unread' => ProjectChat::unread($this->projectId, 'client', $client->id),
            'quote' => ProjectChat::quote($this->projectId, 'client', $this->replyTo),
            'tasks' => $this->issueMode ? $this->clientTasks()->orderBy('project_phases.phase_number')->orderBy('tasks.title')
                ->get(['tasks.id', 'tasks.title', 'project_phases.phase_number'])->all() : [],
            'project' => DB::table('projects')->where('id', $this->projectId)->first(['title']),
        ]);
    }
}
