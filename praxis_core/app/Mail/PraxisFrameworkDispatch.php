<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class PraxisFrameworkDispatch extends Mailable // sent straight away: no queue worker runs on this host
{
    use Queueable, SerializesModels;

    public function __construct()
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[SECURE CLEARANCE] The Predictive Praxis Framework™',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.framework-dispatch',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(storage_path('app/Praxis_Framework.pdf'))
                    ->as('The_Predictive_Praxis_Framework.pdf')
                    ->withMime('application/pdf'),
        ];
    }
}