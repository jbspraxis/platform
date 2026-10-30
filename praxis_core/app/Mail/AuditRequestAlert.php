<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;

/** The team's copy of a free audit request (the site's /schedule form, through the site API), to approve in HQ. Internal: English only, replies go to the visitor. */
class AuditRequestAlert extends BrandedMail
{
    public $fullName;
    public $jobTitle;
    public $visitorEmail;
    public $phone;
    public $company;
    public $topic;
    public $start;
    public $approveUrl;

    /** $start is the requested slot as Lagos wall-clock time (WAT), as the booking form stores it. */
    public function __construct($fullName, $jobTitle, $visitorEmail, $phone, $company, $topic, Carbon $start)
    {
        $this->fullName = $fullName;
        $this->jobTitle = $jobTitle;
        $this->visitorEmail = $visitorEmail;
        $this->phone = $phone;
        $this->company = $company;
        $this->topic = $topic;
        $this->start = $start->copy();
        $this->approveUrl = route('hq.crm.consult');
    }

    protected function subjectLine(): string
    {
        return __('emails.audit_request.alert.subject', [
            'company' => $this->company,
            'date' => AuditRequested::slotDate($this->start, 'en'),
        ], 'en');
    }

    protected function replyToAddresses(): array
    {
        return [new Address($this->visitorEmail, $this->fullName)];
    }

    public function content(): Content
    {
        // Team alerts are always English, whatever locale the mail is rendered in
        $when = AuditRequested::slotText($this->start, 'en');
        $facts = [];
        foreach ([
            'name' => $this->fullName,
            'job_title' => $this->jobTitle,
            'email' => $this->visitorEmail,
            'phone' => $this->phone,
            'company' => $this->company,
            'topic' => AuditRequested::topicLabel($this->topic, 'en'),
            'when' => $when,
        ] as $key => $value) {
            $facts[__('emails.audit_request.alert.facts.' . $key, [], 'en')] = $value;
        }

        return new Content(view: 'emails.audit-request-alert', with: ['when' => $when, 'facts' => $facts]);
    }

    /** Internal team alert: English only. */
    public static function locales(): array
    {
        return ['en'];
    }

    public static function samples(): array
    {
        return [
            'default' => new static(
                'Ada Okafor',
                'Finance director',
                'ada.okafor@example.com',
                '+234 800 000 0000',
                'Atlantic Pharma (demo)',
                'Data Architecture',
                Carbon::parse('2026-11-05 10:30'),
            ),
        ];
    }
}
