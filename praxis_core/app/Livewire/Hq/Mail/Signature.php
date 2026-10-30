<?php

namespace App\Livewire\Hq\Mail;

use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * HQ → Mail → Signature: each person's signature as it sends (one company design built from HR records, owner
 * decision 5), their own numbers, and the company settings for those who may change them. PR 3 owns this file
 * and its view and replaces this placeholder (docs/MAIL.md).
 */
#[Layout('components.layouts.hq')]
#[Title('Signature')]
class Signature extends Component
{
    public function mount(): void
    {
        abort_unless(MailAccess::canUse(Auth::user()), 403);
    }

    public function render()
    {
        return view('livewire.hq.mail.signature');
    }
}
