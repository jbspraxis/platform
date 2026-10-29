<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $firstName;
    public $token;
    public $setupUrl;

    /** $forClient picks the portal setup page; staff set up their key on HQ. */
    public function __construct($firstName, $token, bool $forClient = false)
    {
        $this->firstName = $firstName;
        $this->token = $token;
        $this->setupUrl = route($forClient ? 'client.setup' : 'hq.setup', ['token' => $token]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[ACTION REQUIRED] JBS Praxis Cryptographic Key Init',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invitation',
        );
    }
}