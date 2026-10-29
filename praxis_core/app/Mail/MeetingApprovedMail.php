<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Mail\Mailables\Content;

/** Free audit confirmed: sent when a staff member approves a meeting in HQ (Live consultation). Date, time (WAT) and the meeting link. */
class MeetingApprovedMail extends BrandedMail
{
    public $appointment;
    public $companyName;
    public $meetingLink;
    public $firstName;

    /**
     * $appointment is the appointments row: start_time and end_time hold the Lagos (WAT) wall-clock time the visitor booked.
     * $firstName greets the contact in English when known (French greetings never use the first name alone).
     */
    public function __construct($appointment, $companyName, $meetingLink, $firstName = null)
    {
        $this->appointment = $appointment;
        $this->companyName = $companyName;
        $link = trim((string) $meetingLink);
        $this->meetingLink = $link !== '' && !preg_match('~^https?://~i', $link) ? 'https://' . ltrim($link, '/') : $link;
        $this->firstName = $firstName;
    }

    protected function subjectLine(): string
    {
        return __('emails.meeting.subject', [
            'date' => $this->meetingDate(),
            'time' => $this->clock($this->appointment->start_time),
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.meeting-approved', with: [
            'meetingDate' => $this->meetingDate(),
            'meetingTime' => $this->meetingTime(),
        ]);
    }

    public static function samples(): array
    {
        $appointment = (object) [
            'id' => 'sample-appointment',
            'organization_id' => 'sample-organisation',
            'title' => 'Free audit and demo',
            'meeting_link' => 'https://meet.example.com/acme-demo',
            'start_time' => '2026-11-05 10:30:00',
            'end_time' => '2026-11-05 11:30:00',
            'status' => 'Scheduled',
        ];

        return [
            'named' => new static($appointment, 'Acme Ltd (demo)', $appointment->meeting_link, 'Ada'),
            'no-name' => new static((object) array_merge((array) $appointment, ['start_time' => '2026-11-09 09:00:00', 'end_time' => null]), 'Acme Ltd (demo)', $appointment->meeting_link),
        ];
    }

    /** "Thursday 5 November 2026" / "jeudi 5 novembre 2026" (docs/BRAND.md section 4). */
    private function meetingDate(): string
    {
        return Carbon::parse($this->appointment->start_time)->locale(app()->getLocale())->isoFormat(__('emails.common.date_format'));
    }

    /** "10:30" / "10 h 30". */
    private function clock($value): string
    {
        $fr = app()->getLocale() === 'fr';

        return Carbon::parse($value)->isoFormat($fr ? "H[\u{202F}h\u{202F}]mm" : 'HH:mm');
    }

    /** "10:30 to 11:30 (WAT)" when the end time is known, else "10:30 (WAT)". */
    private function meetingTime(): string
    {
        $start = Carbon::parse($this->appointment->start_time);
        $end = empty($this->appointment->end_time) ? null : Carbon::parse($this->appointment->end_time);

        return $end && $end->gt($start)
            ? __('emails.meeting.time_range', ['start' => $this->clock($start), 'end' => $this->clock($end)])
            : __('emails.meeting.time_single', ['time' => $this->clock($start)]);
    }
}
