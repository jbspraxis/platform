<?php

namespace App\Livewire\Hq\Mail;

use Livewire\Component;

/**
 * Summarise with Praxis AI: a button and the summary card above a conversation. Mounted in the reader with :thread-id.
 * PR 4 owns this file and its view and replaces this placeholder (docs/MAIL.md, "Mount points").
 */
class ThreadSummary extends Component
{
    public ?int $threadId = null;

    public function render()
    {
        return view('livewire.hq.mail.thread-summary');
    }
}
