<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

/** The Praxis Method PDF, sent to the address a visitor gives on the Services page. Sent straight away: no queue worker runs on this host. */
class PraxisFrameworkDispatch extends BrandedMail
{
    /** The name the visitor sees on the attachment (the file on disk keeps its old name). */
    public const ATTACHMENT_NAME = 'JBS-Praxis-Method.pdf';

    public $bookUrl;

    public function __construct()
    {
        $this->bookUrl = route('schedule');
    }

    protected function subjectLine(): string
    {
        return __('emails.method.subject');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.framework-dispatch');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(storage_path('app/Praxis_Framework.pdf'))
                    ->as(self::ATTACHMENT_NAME)
                    ->withMime('application/pdf'),
        ];
    }

    public static function samples(): array
    {
        return [
            'default' => new static(),
        ];
    }
}
