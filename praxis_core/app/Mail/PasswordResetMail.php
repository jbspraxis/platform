<?php

namespace App\Mail;

use App\Support\SignIn\PasswordResets;
use Illuminate\Mail\Mailables\Content;

/** A link to choose a new password: HQ staff, or a Client Portal account (App\Support\SignIn\PasswordResets). */
class PasswordResetMail extends BrandedMail
{
    public $firstName;
    public $resetUrl;
    public $forClient;
    public $minutes;

    /** $forClient picks the portal's reset page; staff reset their password on HQ. */
    public function __construct($firstName, $token, bool $forClient = false)
    {
        $this->firstName = $firstName;
        $this->forClient = $forClient;
        $this->minutes = PasswordResets::MINUTES;
        $this->resetUrl = route($forClient ? 'client.password.reset' : 'hq.password.reset', ['token' => $token]);
    }

    protected function subjectLine(): string
    {
        return __($this->forClient ? 'emails.password_reset.client_subject' : 'emails.password_reset.staff_subject');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }

    public static function samples(): array
    {
        return [
            'staff' => new static('Ada', 'sample-token-staff', false),
            'client' => new static('Ada', 'sample-token-client', true),
        ];
    }
}
