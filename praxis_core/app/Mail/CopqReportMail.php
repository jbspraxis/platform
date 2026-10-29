<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CopqReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $assessment;

    public function __construct($assessment)
    {
        $this->assessment = $assessment;
    }

    public function build()
    {
        return $this->subject('CONFIDENTIAL: Strategic Diagnostic Report (COPQ) | JBS Praxis')
                    ->view('emails.copq-report');
    }
}