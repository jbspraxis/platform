<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Mail\Mailables\Content;

/** The visitor's copy after requesting a free audit and demo (the site's /schedule form). The team confirms it later from HQ. */
class AuditRequested extends BrandedMail
{
    public $firstName;
    public $topic;
    public $start;

    /** $start is the requested slot as Lagos wall-clock time (WAT), as the booking form stores it. */
    public function __construct($firstName, $topic, Carbon $start)
    {
        $this->firstName = $firstName;
        $this->topic = $topic;
        $this->start = $start->copy();
    }

    protected function subjectLine(): string
    {
        return __('emails.audit_request.subject');
    }

    public function content(): Content
    {
        $locale = app()->getLocale();

        return new Content(view: 'emails.audit-requested', with: [
            'when' => self::slotText($this->start, $locale),
            'topicLabel' => self::topicLabel($this->topic, $locale),
        ]);
    }

    /** "Thursday 5 November 2026, 10:30 (WAT)" / "jeudi 5 novembre 2026, 10 h 30 (WAT)". */
    public static function slotText(Carbon $start, string $locale): string
    {
        $at = $start->copy()->locale($locale);

        return __('emails.audit_request.when', [
            'date' => self::slotDate($start, $locale),
            'time' => $at->isoFormat(__('emails.audit_request.time_format', [], $locale)),
        ], $locale);
    }

    /** "Thursday 5 November 2026" / "jeudi 5 novembre 2026". */
    public static function slotDate(Carbon $start, string $locale): string
    {
        return $start->copy()->locale($locale)->isoFormat(__('emails.common.date_format', [], $locale));
    }

    /** The booking form's topic in the reader's language; a topic the visitor typed is shown as written. */
    public static function topicLabel(?string $topic, string $locale): string
    {
        $topics = __('emails.audit_request.topics', [], $locale);

        return is_array($topics) && isset($topics[$topic]) ? $topics[$topic] : (string) $topic;
    }

    public static function samples(): array
    {
        return [
            'listed-topic' => new static('Ada', 'Performance Audit', Carbon::parse('2026-11-05 10:30')),
            'own-topic' => new static('Ada', 'Cash flow reporting', Carbon::parse('2026-11-02 09:00')),
        ];
    }
}
