<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** The visitor's copy after the website contact form (ContactHub), with the free audit and demo as the next step. */
class EnquiryReceived extends BrandedMail
{
    public $firstName;
    public $bookUrl;

    public function __construct($firstName)
    {
        $this->firstName = $firstName;
        $this->bookUrl = route('schedule');
    }

    protected function subjectLine(): string
    {
        return __('emails.enquiry.subject');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.enquiry-received');
    }

    public static function samples(): array
    {
        return [
            'default' => new static('Ada'),
        ];
    }
}
