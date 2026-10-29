<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MeetingApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $companyName;
    public $meetingLink;

    public function __construct($appointment, $companyName, $meetingLink)
    {
        $this->appointment = $appointment;
        $this->companyName = $companyName;
        $this->meetingLink = $meetingLink;
    }

    public function build()
    {
        return $this->subject('CONFIRMED: JBS Praxis Systems Consultation')
                    ->view('emails.meeting-approved');
    }
}