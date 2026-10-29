<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/** Account setup link: staff for HQ, or a client's first portal account (RoleManagement, app:invite-owner, a signed proposal). */
class UserInvitation extends BrandedMail
{
    public $firstName;
    public $token;
    public $setupUrl;
    public $forClient;
    public $signedProject;

    /**
     * $forClient picks the portal setup page; staff set up their password on HQ.
     * $signedProject (clients only): the proposal just signed, which adds a thank-you line (Portal\ProposalGateway).
     */
    public function __construct($firstName, $token, bool $forClient = false, $signedProject = null)
    {
        $this->firstName = $firstName;
        $this->token = $token;
        $this->forClient = $forClient;
        $this->signedProject = $forClient ? trim((string) $signedProject) : '';
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
            'client-signed' => new static('Ada', 'sample-token-signed', true, 'Month-end close redesign (demo)'),
        ];
    }
}
