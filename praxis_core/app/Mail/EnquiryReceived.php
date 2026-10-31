<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** The visitor's copy after the website contact form, with the free audit and demo as the next step. */
class EnquiryReceived extends BrandedMail
{
    public $firstName;

    public function __construct($firstName)
    {
        $this->firstName = $firstName;
    }

    protected function subjectLine(): string
    {
        return __('emails.enquiry.subject');
    }

    public function content(): Content
    {
        // The booking page in the email's language: /schedule, or /fr/schedule on the site
        $book = route('schedule');
        if (app()->getLocale() === 'fr') {
            $book = preg_replace('~/schedule$~', '/fr/schedule', $book);
        }

        return new Content(view: 'emails.enquiry-received', with: ['bookUrl' => $book]);
    }

    public static function samples(): array
    {
        return [
            'default' => new static('Ada'),
        ];
    }
}
