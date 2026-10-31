<?php

namespace App\Mail;

use App\Mail\Concerns\FormatsMeetingTimes;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Support\Opportunities\PortalLinks;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Content;
use InvalidArgumentException;

/**
 * A client meeting arranged in HQ or booked in the portal (docs/OPPORTUNITIES.md decision 3): sent to everyone
 * invited who isn't staff, next to Google's own calendar invitation, in the opportunity's language. Variants:
 * invite (new meeting), moved (new time), cancelled. Invitations carry the transcription notice; every variant
 * links to the client's meetings page to book another time.
 */
class MeetingInvitationMail extends BrandedMail
{
    use FormatsMeetingTimes;

    public const VARIANTS = ['invite', 'moved', 'cancelled'];

    public function __construct(public Meeting $meeting, public string $variant = 'invite', public ?string $firstName = null)
    {
        if (! in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException("Unknown invitation '{$variant}'");
        }
    }

    protected function subjectLine(): string
    {
        $tz = $this->meeting->time_zone ?: 'Africa/Lagos';

        return __("emails.client_meeting.{$this->variant}.subject", [
            'title' => $this->meeting->title,
            'date' => $this->meetingDay($this->meeting->starts_at, $tz),
            'time' => $this->meetingClock($this->meeting->starts_at, $tz),
        ]);
    }

    public function content(): Content
    {
        $tz = $this->meeting->time_zone ?: 'Africa/Lagos';
        $contact = $this->meeting->attendees->first(fn ($a) => $a->client_user_id !== null)?->client_user_id;

        return new Content(view: 'emails.meeting-invitation', with: [
            'company' => $this->meeting->opportunity?->organization?->name ?? '',
            'meetingDate' => $this->meetingDay($this->meeting->starts_at, $tz),
            'meetingTime' => $this->meetingRange($this->meeting->starts_at, $this->meeting->ends_at, $tz),
            'bookingUrl' => $this->meeting->opportunity ? PortalLinks::meetings($this->meeting->opportunity, $contact) : null,
        ]);
    }

    public static function samples(): array
    {
        $organisation = (new Organization)->forceFill(['id' => '7f6c2a52-1b1e-4a8e-9a7a-0c0d1e2f3a4b', 'name' => 'Acme Ltd (demo)']);
        $opportunity = (new Opportunity)->forceFill(['id' => '0b5c8d1e-2f3a-4b5c-8d6e-7f8091a2b3c4', 'title' => 'Finance system', 'language' => 'en'])
            ->setRelation('organization', $organisation);
        $meeting = (new Meeting)->forceFill([
            'id' => '3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f',
            'kind' => 'client',
            'title' => 'Discovery: finance system',
            'agenda' => "How month-end closing runs today\nWhere the team loses time\nWhat a first phase could cover",
            'starts_at' => CarbonImmutable::parse('2026-11-05 09:30:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-11-05 10:30:00', 'UTC'),
            'time_zone' => 'Africa/Lagos',
            'status' => 'scheduled',
            'meet_link' => 'https://meet.google.com/abc-defg-hij',
        ])->setRelation('opportunity', $opportunity)
            ->setRelation('attendees', collect([new MeetingAttendee(['email' => 'ada@acme.example', 'role' => 'client'])]));

        return [
            'invite' => new static($meeting, 'invite', 'Ada'),
            'moved' => new static($meeting, 'moved', 'Ada'),
            'cancelled' => new static($meeting, 'cancelled'),
        ];
    }
}
