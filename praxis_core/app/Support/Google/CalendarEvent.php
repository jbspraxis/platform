<?php

namespace App\Support\Google;

/** A Calendar event HQ created or changed: what meetings store (google_event_id, meet_link, meet_code) */
final class CalendarEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly ?string $meetLink,
        public readonly ?string $meetCode,
        public readonly ?string $htmlLink = null,
    ) {}

    public static function fromGoogle(array $event): self
    {
        $video = collect($event['conferenceData']['entryPoints'] ?? [])->firstWhere('entryPointType', 'video');

        return new self(
            (string) $event['id'],
            $event['hangoutLink'] ?? $video['uri'] ?? null,
            $event['conferenceData']['conferenceId'] ?? null,
            $event['htmlLink'] ?? null,
        );
    }
}
