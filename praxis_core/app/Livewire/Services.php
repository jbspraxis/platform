<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PraxisFrameworkDispatch;

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

        try {
            // Send the actual email to the address they provided
            Mail::to($this->email)->send(new PraxisFrameworkDispatch());
            
            // Trigger the UI success text
            $this->emailSent = true;
            
        } catch (\Exception $e) {
            // If the SMTP fails, log the error safely and optionally alert the user
            session()->flash('error', 'Transmission failed. Please check your network connection or try the direct link.');
        }
    }

    public function render()
    {
        return view('livewire.services')->layout('layouts.app', ['title' => 'JBS PRAXIS | The Praxis Framework']);
    }
}