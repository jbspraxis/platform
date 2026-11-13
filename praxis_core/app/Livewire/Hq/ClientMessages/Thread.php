<?php

namespace App\Livewire\Hq\ClientMessages;

use App\Livewire\Concerns\ProjectChatRoom;
use App\Models\ClientUser;
use App\Support\Chat\ProjectChat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * HQ's side of a client's conversation with its delivery team (docs/CHAT.md): the same thread the client sees,
 * plus internal notes (JBS Praxis only) and a link from each issue to Quality and issues. Shown on the Client
 * messages page and in the floating panel on every HQ page. Reading needs qa.view on a project this person can
 * see (QA approvers and managers: every project); writing needs qa.edit.
 */
class Thread extends Component
{
    use ProjectChatRoom;
    use WithFileUploads;

    #[Locked]
    public bool $canWrite = false;
    #[Locked]
    public bool $inLauncher = false;
    public bool $internal = false;

    public function mount(string $projectId, bool $inLauncher = false): void
    {
        $this->projectId = $projectId;
        $this->inLauncher = $inLauncher;
        $this->chatPerson();
        $this->canWrite = Auth::user()->can('qa.edit');
        $this->signature = ProjectChat::signature($projectId, 'staff');
        $this->lastReadAtOpen = $this->lastRead();
    }

    protected function chatParty(): string
    {
        return 'staff';
    }

    protected function chatPerson(bool $write = false): object
    {
        $user = Auth::user();
        abort_unless($user && ProjectChat::staffMaySee($this->projectId), 403);
        abort_if($write && ! $user->can('qa.edit'), 403);

        return $user;
    }

    public function send(): void
    {
        $internal = $this->internal;
        $this->sendMessage(['internal' => $internal]);
        if (! $this->getErrorBag()->any()) {
            $this->internal = false; // each internal note is chosen on purpose, as in Quality and issues
        }
    }

    protected function voiceExtra(): array
    {
        $extra = ['internal' => $this->internal];
        $this->internal = false;

        return $extra;
    }

    public function render()
    {
        $user = $this->chatPerson();
        [$messages, $more] = ProjectChat::messages($this->projectId, 'staff', $this->limit);
        [$seen, $typing] = ProjectChat::state($this->projectId, 'staff', $user->id);
        $project = DB::table('projects')->join('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->where('projects.id', $this->projectId)->first(['projects.id', 'projects.title', 'projects.status', 'organizations.name as client']);
        // Who at the client reads this conversation (Client Portal accounts with Quality and issues, on this project)
        $readers = ClientUser::whereIn('id', ProjectChat::clientAudience($this->projectId))->orderByDesc('is_primary_client')->orderBy('first_name')
            ->get(['first_name', 'last_name'])->map(fn ($c) => trim($c->first_name . ' ' . $c->last_name))->all();

        return view('livewire.hq.client-messages.thread', [
            'user' => $user,
            'messages' => $messages,
            'more' => $more,
            'seen' => $seen,
            'typing' => $typing,
            'unread' => ProjectChat::unread($this->projectId, 'staff', $user->id),
            'quote' => ProjectChat::quote($this->projectId, 'staff', $this->replyTo),
            'project' => $project,
            'readers' => $readers,
        ]);
    }
}
