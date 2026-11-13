<?php

namespace App\Livewire\Hq\ClientMessages;

use App\Support\Chat\ProjectChat;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The floating "Client messages" button on every HQ page (owner, 13 Nov 2026; docs/CHAT.md), with the number of
 * unread client messages. It opens a panel with the conversations, then the one chosen, without leaving the page.
 * Kept across page changes (@persist in the HQ layout); hidden on the Client messages page itself.
 */
class Launcher extends Component
{
    public bool $open = false;
    public string $project = '';
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('qa.view'), 403);
    }

    public function show(): void
    {
        $this->open = true;
    }

    public function hide(): void
    {
        $this->open = false;
    }

    public function choose(string $projectId): void
    {
        $this->project = ProjectChat::staffMaySee($projectId) ? $projectId : '';
    }

    public function back(): void
    {
        $this->project = '';
    }

    /** The badge, checked every 30 seconds (and the list, while it is open) */
    public function refresh(): void
    {
        // Rendering counts again
    }

    public function render()
    {
        $userId = (string) Auth::id();

        return view('livewire.hq.client-messages.launcher', [
            'unread' => ProjectChat::staffUnreadTotal($userId),
            'chats' => $this->open && $this->project === '' ? ProjectChat::inbox($userId, $this->search, 30) : [],
        ]);
    }
}
