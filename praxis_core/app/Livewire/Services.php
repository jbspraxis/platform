<?php

namespace App\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;
use App\Services\Site\LimitReached;
use App\Services\Site\WhitepaperService;

class Services extends Component
{
    public $email;
    public $isWhitepaperSuccess = false;
    public $emailSent = false;

    // The address that passed the limits in processWhitepaper(): the only one dispatchEmail() sends to
    #[Locked]
    public ?string $recordedEmail = null;

    public function processWhitepaper()
    {
        $this->validate(WhitepaperService::rules());

        // The limits (per visitor, per email, everyone) are the service's, shared with the site API
        try {
            app(WhitepaperService::class)->record($this->email, request()->ip());
        } catch (LimitReached $e) {
            $this->addError('email', 'Too many requests. Please try again later or use the direct link.');
            return;
        }

        $this->recordedEmail = $this->email;
        $this->isWhitepaperSuccess = true;
    }

    public function dispatchEmail()
    {
        // Prevent sending multiple times if they spam the button
        if ($this->emailSent) {
            return;
        }

        // Only to the address recorded above (it passed the limits); each address gets 3 visitor emails a day at most
        if (!$this->recordedEmail) {
            return;
        }

        // Send the method PDF to the address they provided, in the language of the page they used
        if (app(WhitepaperService::class)->send($this->recordedEmail)) {
            // Trigger the UI success text
            $this->emailSent = true;
        } else {
            // The email could not be sent (logged by the service)
            session()->flash('error', 'Transmission failed. Please check your network connection or try the direct link.');
        }
    }

    public function render()
    {
        return view('livewire.services')->layout('layouts.app', ['title' => 'JBS PRAXIS | The Praxis Framework']);
    }
}