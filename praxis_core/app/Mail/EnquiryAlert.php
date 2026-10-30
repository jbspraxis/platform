<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;

/** The team's copy of a website enquiry (ContactHub). Internal: English only, replies go to the visitor. */
class EnquiryAlert extends BrandedMail
{
    public $fullName;
    public $visitorEmail;
    public $company;
    public $enquiry;
    public $details;
    public $leadsUrl;

    /**
     * @param  array<string, ?string>  $details  job_title, phone, city, language, budget, start_timing, stage, bottleneck, systems, governance (blank ones are left out)
     */
    public function __construct($fullName, $visitorEmail, $company, $enquiry, array $details = [])
    {
        $this->fullName = $fullName;
        $this->visitorEmail = $visitorEmail;
        $this->company = $company;
        $this->enquiry = $enquiry;
        $this->details = $details;
        $this->leadsUrl = route('hq.crm.leads');
    }

    protected function subjectLine(): string
    {
        return __('emails.enquiry.alert.subject', ['company' => $this->company], 'en');
    }

    protected function replyToAddresses(): array
    {
        return [new Address($this->visitorEmail, $this->fullName)];
    }

    /** Label => value rows in the order the team reads them, labels from lang/en/emails/enquiry.php. */
    public function facts(): array
    {
        $values = [
            'name' => $this->fullName,
            'email' => $this->visitorEmail,
            'job_title' => $this->details['job_title'] ?? null,
            'phone' => $this->details['phone'] ?? null,
            'company' => $this->company,
            'city' => $this->details['city'] ?? null,
            'language' => $this->details['language'] ?? null,
            'budget' => $this->details['budget'] ?? null,
            'start_timing' => $this->details['start_timing'] ?? null,
            'stage' => $this->details['stage'] ?? null,
            'bottleneck' => $this->details['bottleneck'] ?? null,
            'systems' => $this->details['systems'] ?? null,
            'governance' => $this->details['governance'] ?? null,
        ];

        $rows = [];
        foreach ($values as $key => $value) {
            $rows[__('emails.enquiry.alert.facts.' . $key, [], 'en')] = $value;
        }

        return $rows;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.enquiry-alert', with: ['facts' => $this->facts()]);
    }

    /** Internal team alert: English only. */
    public static function locales(): array
    {
        return ['en'];
    }

    public static function samples(): array
    {
        return [
            'diagnostic' => new static(
                'Ada Okafor',
                'ada.okafor@example.com',
                'Atlantic Pharma (demo)',
                "We close the month in about three weeks and rebuild most of the numbers by hand.\nWe would like to see how HQ could help our finance team.",
                [
                    'job_title' => 'Finance director',
                    'phone' => '+234 800 000 0000',
                    'city' => 'Lagos',
                    'language' => 'English (EN)',
                    'budget' => '$15,000 - $50,000',
                    'stage' => 'Scaling',
                    'bottleneck' => 'Month-end close',
                    'systems' => 'Spreadsheets',
                    'governance' => 'Board in place',
                ],
            ),
            'message' => new static(
                'Jean Mbarga',
                'jean.mbarga@example.com',
                'Acme Ltd (demo)',
                'Could you tell us more about your work with OHADA accounting?',
                ['language' => 'English (EN)'],
            ),
        ];
    }
}
