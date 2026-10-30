<?php

namespace App\Livewire\Hq\Mail;

use Livewire\Component;

/**
 * The composer: new mail, reply, reply all, forward, drafts, schedule, signature, Write with AI. Mounted once on the Mail page; opens on the browser event mail-compose.
 * PR 3 owns this file and its view and replaces this placeholder (docs/MAIL.md, "Mount points").
 */
class Composer extends Component
{

    public function render()
    {
        return view('livewire.hq.mail.composer');
    }
}
