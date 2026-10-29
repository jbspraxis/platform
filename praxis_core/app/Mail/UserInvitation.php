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

    public function __construct($firstName, $token)
    {
        $this->firstName = $firstName;
        $this->token = $token;
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