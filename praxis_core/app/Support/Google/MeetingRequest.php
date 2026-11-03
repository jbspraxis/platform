<?php

namespace App\Support\Google;

use Carbon\CarbonInterface;

/**
 * A meeting to create or change in praxisjbs@gmail.com's calendar. Attendees: [['email' => …, 'name' => …], …].
 * notify: Google emails the invitations and changes (sendUpdates=all); false sends nothing. location: an address, for
 * a meeting in person; withMeet: false creates the event without a Meet link (a lead meeting in person, docs/LEADS.md).
 */
final class MeetingRequest
{
    public function __construct(
        public readonly string $title,
        public readonly CarbonInterface $startsAt,
        public readonly CarbonInterface $endsAt,
        public readonly array $attendees = [],
        public readonly ?string $description = null,
        public readonly string $timeZone = GoogleWorkspace::TIME_ZONE,
        public readonly bool $notify = true,
        public readonly ?string $location = null,
        public readonly bool $withMeet = true,
    ) {}

    /** The Calendar API event body (without the conference request) */
    public function toEvent(): array
    {
        $time = fn (CarbonInterface $t) => ['dateTime' => $t->copy()->setTimezone($this->timeZone)->toRfc3339String(), 'timeZone' => $this->timeZone];

        return array_filter([
            'summary' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start' => $time($this->startsAt),
            'end' => $time($this->endsAt),
            'attendees' => array_values(array_map(
                fn (array $a) => array_filter(['email' => $a['email'], 'displayName' => $a['name'] ?? null]),
                $this->attendees,
            )),
            'guestsCanModify' => false,
        ], fn ($v) => $v !== null);
    }
}
