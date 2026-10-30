<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Mail\Mailables\Content;

/**
 * Free audit request declined: sent when a staff member declines a request in HQ (Free audits) and keeps "Email
 * the client" on. Polite and short: the time asked for, an invitation to choose another. The team's reason is
 * never in it.
 */
class FreeAuditDeclinedMail extends BrandedMail
{
    public $appointment;
    public $companyName;
    public $firstName;

    /**
     * $appointment is the appointments row: start_time holds the Lagos (WAT) wall-clock time the visitor asked for.
     * $firstName greets the contact in English when known (French greetings never use the first name alone).
     */
    public function __construct($appointment, $companyName, $firstName = null)
    {
        $this->appointment = $appointment;
        $this->companyName = $companyName;
        $this->firstName = $firstName;
    }

    protected function subjectLine(): string
    {
        return __('emails.audit_declined.subject', ['date' => $this->requestedDate()]);
    }

    public function content(): Content
    {
        // The booking page in the email's language: /schedule, or /fr/schedule on the site
        $book = route('schedule');
        if (app()->getLocale() === 'fr') {
            $book = preg_replace('~/schedule$~', '/fr/schedule', $book);
        }

        return new Content(view: 'emails.free-audit-declined', with: [
            'requestedDate' => $this->requestedDate(),
            'requestedTime' => __('emails.audit_declined.time', ['time' => $this->clock($this->appointment->start_time)]),
            'bookUrl' => $book,
        ]);
    }

    public static function samples(): array
    {
        $appointment = (object) [
            'id' => 'sample-appointment',
            'organization_id' => 'sample-organisation',
            'title' => 'Free audit and demo',
            'start_time' => '2026-11-05 10:30:00',
            'end_time' => '2026-11-05 11:30:00',
            'status' => 'Cancelled',
        ];

        return [
            'named' => new static($appointment, 'Acme Ltd (demo)', 'Ada'),
            'no-name' => new static((object) array_merge((array) $appointment, ['start_time' => '2026-11-09 16:00:00']), 'Acme Ltd (demo)'),
        ];
    }

    /** "Thursday 5 November 2026" / "jeudi 5 novembre 2026" (docs/BRAND.md section 4). */
    private function requestedDate(): string
    {
        return Carbon::parse($this->appointment->start_time)->locale(app()->getLocale())->isoFormat(__('emails.common.date_format'));
    }

    /** "10:30" / "10 h 30". */
    private function clock($value): string
    {
        $fr = app()->getLocale() === 'fr';

        return Carbon::parse($value)->isoFormat($fr ? "H[\u{202F}h\u{202F}]mm" : 'HH:mm');
    }
}
