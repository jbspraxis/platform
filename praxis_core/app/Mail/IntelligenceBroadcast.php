<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class IntelligenceBroadcast extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectLine;
    public $htmlBody;

    public function __construct($subjectLine, $htmlBody)
    {
        $this->subjectLine = $subjectLine;
        $this->htmlBody = $htmlBody;
    }

    public function envelope(): Envelope
    {
        // Sent from the system mailbox like every other site email; replies go to the Intelligence Desk
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            replyTo: [new Address('intelligence@jbspraxis.com', 'JBS Praxis Intelligence Desk')],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.broadcast',
        );
    }
}