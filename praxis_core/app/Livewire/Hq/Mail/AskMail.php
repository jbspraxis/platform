<?php

namespace App\Livewire\Hq\Mail;

use Livewire\Component;

/**
 * Ask Praxis AI about your mail: a question box in the Mail header that answers from the mail the person can read, with links to the messages. Mounted with :account-id (the open mailbox; it may search all the person's mailboxes).
 * PR 4 owns this file and its view and replaces this placeholder (docs/MAIL.md, "Mount points").
 */
class AskMail extends Component
{
    public ?string $accountId = null;

    public function render()
    {
        return view('livewire.hq.mail.ask-mail');
    }
}
