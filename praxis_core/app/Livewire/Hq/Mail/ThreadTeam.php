<?php

namespace App\Livewire\Hq\Mail;

use Livewire\Component;

/**
 * Teamwork on a conversation in a shared mailbox: assignment, Open / Done, internal notes, who is viewing or replying. Mounted in the reader with :thread-id.
 * PR 4 owns this file and its view and replaces this placeholder (docs/MAIL.md, "Mount points").
 */
class ThreadTeam extends Component
{
    public ?int $threadId = null;

    public function render()
    {
        return view('livewire.hq.mail.thread-team');
    }
}
