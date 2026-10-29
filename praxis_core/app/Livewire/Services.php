<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PraxisFrameworkDispatch;
use App\Support\MailLocale;

class Services extends Component
{
    public $email;
    public $isWhitepaperSuccess = false;
    public $emailSent = false;

    public function processWhitepaper()
    {
        $this->validate([
            'email' => 'required|email|max:100',
        ]);

        DB::table('whitepaper_downloads')->insert([
            'email_captured' => $this->email,
            'document_name' => 'The Praxis Framework',
        ]);

        $this->isWhitepaperSuccess = true;
    }

    public function dispatchEmail()
    {
        // Prevent sending multiple times if they spam the button
        if ($this->emailSent) {
            return;
        }

        // The address comes from the visitor, so cap sends per IP (3 an hour) to stop abuse of our mail server
        $this->validate(['email' => 'required|email|max:100']);
        $rateKey = 'whitepaper-mail:' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 3)) {
            session()->flash('error', 'Too many requests. Please try again later or use the direct link.');
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 3600);

        try {
            // Send the method PDF to the address they provided, in the language of the page they used
            Mail::to($this->email)
                ->locale(MailLocale::forVisitor())
                ->send(new PraxisFrameworkDispatch());

            // Trigger the UI success text
            $this->emailSent = true;

        } catch (\Exception $e) {
            // If the SMTP fails, log the error safely and optionally alert the user
            \Illuminate\Support\Facades\Log::error('Whitepaper email failed', ['message' => $e->getMessage()]);
            session()->flash('error', 'Transmission failed. Please check your network connection or try the direct link.');
        }
    }

    public function render()
    {
        return view('livewire.services')->layout('layouts.app', ['title' => 'JBS PRAXIS | The Praxis Framework']);
    }
}