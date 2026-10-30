<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

/** The Praxis Method guide (PDF), sent to the address a visitor gives on the Services page. Sent straight away: no queue worker runs on this host. */
class PraxisFrameworkDispatch extends BrandedMail
{
    /**
     * The guide in each language: its file in resources/pdf (built by brand/build/build_method.js) and the name the
     * visitor sees on the attachment. Any other locale gets the English edition.
     */
    public const EDITIONS = [
        'en' => ['file' => 'the-praxis-method-en.pdf', 'name' => 'JBS-Praxis-Method.pdf'],
        'fr' => ['file' => 'the-praxis-method-fr.pdf', 'name' => 'JBS-Praxis-Methode.pdf'],
    ];

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

    /** The edition in this mail's language (set with ->locale()), English otherwise */
    public function edition(): array
    {
        return self::EDITIONS[$this->locale] ?? self::EDITIONS['en'];
    }

    public function attachments(): array
    {
        $edition = $this->edition();

        return [
            Attachment::fromPath(resource_path('pdf/' . $edition['file']))
                    ->as($edition['name'])
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
