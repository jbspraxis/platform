<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * A client admin invites a colleague to their organisation's Client Portal (ClientOs\Dashboard).
 * The setup link has no time limit: it stops working once a password is chosen.
 */
class TeamMemberInvitation extends BrandedMail
{
    public $firstName;
    public $lastName;
    public $inviterName;
    public $company;
    public $token;
    public $setupUrl;

    public function __construct($firstName, $lastName, $inviterName, $company, $token)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->inviterName = trim((string) $inviterName);
        $this->company = trim((string) $company);
        $this->token = $token;
        $this->setupUrl = route('client.setup', ['token' => $token]);
    }

    protected function subjectLine(): string
    {
        return __('emails.team_invitation.subject', ['name' => $this->inviterName, 'company' => $this->company]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.team-member-invitation');
    }

    public static function samples(): array
    {
        return [
            'member' => new static('Daniel', 'Mbarga', 'Ada Okafor', 'Acme Ltd (demo)', 'sample-token-team'),
        ];
    }
}
