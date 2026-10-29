<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** Account setup link: staff for HQ, or a client's first portal account (RoleManagement, app:invite-owner). */
class UserInvitation extends BrandedMail
{
    public $firstName;
    public $token;
    public $setupUrl;
    public $forClient;

    /** $forClient picks the portal setup page; staff set up their password on HQ. */
    public function __construct($firstName, $token, bool $forClient = false)
    {
        $this->firstName = $firstName;
        $this->token = $token;
        $this->forClient = $forClient;
        $this->setupUrl = route($forClient ? 'client.setup' : 'hq.setup', ['token' => $token]);
    }

    protected function subjectLine(): string
    {
        return __($this->forClient ? 'emails.invitation.client_subject' : 'emails.invitation.staff_subject');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.user-invitation');
    }

    public static function samples(): array
    {
        return [
            'staff' => new static('Ada', 'sample-token-staff', false),
            'client' => new static('Ada', 'sample-token-client', true),
        ];
    }
}
