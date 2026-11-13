<?php

namespace App\Livewire\Hq\ClientMessages;

use App\Support\Chat\ProjectChat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Client messages (docs/CHAT.md): every client conversation this person may open, unread first, and the one chosen.
 * On a phone the list fills the screen and a conversation opens over it (Back returns to the list).
 */
#[Layout('components.layouts.hq')]
#[Title('Client messages')]
class Inbox extends Component
{
    #[Url(as: 'project', except: '')]
    public string $project = '';

    public string $search = '';

    public function mount(): void
    {
        // A conversation this person can't open is not chosen
        if ($this->project !== '' && ! ProjectChat::staffMaySee($this->project)) {
            $this->project = '';
        }
    }

    public function open(string $projectId): void
    {
        $this->project = ProjectChat::staffMaySee($projectId) ? $projectId : '';
    }

    public function back(): void
    {
        $this->project = '';
    }

    /** The list's unread counts and last messages, checked every 15 seconds */
    public function refreshList(): void
    {
        // Rendering reads the list again
    }

    public function render()
    {
        $chats = ProjectChat::inbox((string) Auth::id(), $this->search);
        // On a computer the first conversation opens with the page
        $selected = $this->project !== '' && ProjectChat::staffMaySee($this->project) ? $this->project : '';

        return view('livewire.hq.client-messages.inbox', ['chats' => $chats, 'selected' => $selected]);
    }
}
