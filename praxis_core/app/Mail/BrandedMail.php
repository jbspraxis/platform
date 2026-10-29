<?php

namespace App\Mail;

use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

/**
 * Every email the site sends (docs/BRAND.md section 13): from system@, replies to the team,
 * one branded HTML layout (<x-mail.layout>) and a plain-text part generated from the same view.
 * Wording lives in lang/{en,fr}/emails/*.php; send with ->locale(MailLocale::...) to pick the language.
 */
abstract class BrandedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Subject line, in the mail's locale (call __() here, not in the constructor). */
    abstract protected function subjectLine(): string;

    /**
     * Named examples with fictional data, for previews and test sends (php artisan app:email-samples).
     *
     * @return array<string, static>
     */
    abstract public static function samples(): array;

    /** The languages this email is written in. Internal team alerts are English only. */
    public static function locales(): array
    {
        return ['en', 'fr'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            replyTo: $this->replyToAddresses(),
            subject: $this->subjectLine(),
        );
    }

    /** Client-facing email: replies reach the team. Internal alerts return the visitor instead. */
    protected function replyToAddresses(): array
    {
        return [new Address(config('mail.brand.reply_to.address'), config('mail.brand.reply_to.name'))];
    }

    /** Renders the HTML view once and derives the plain-text part from it, so the two never drift. */
    protected function buildView()
    {
        $html = view($this->view, $this->buildViewData())->render();

        return [
            'html' => new HtmlString($html),
            'text' => new HtmlString(MailText::fromHtml($html)),
        ];
    }

    /** Subject, HTML and plain text in this mail's locale, for previews and tests. Use a fresh instance to send. */
    public function renderParts(): array
    {
        return $this->withLocale($this->locale, function () {
            $this->prepareMailableForDelivery();
            $views = $this->buildView();

            return ['subject' => $this->subject, 'html' => $views['html']->toHtml(), 'text' => $views['text']->toHtml()];
        });
    }
}
