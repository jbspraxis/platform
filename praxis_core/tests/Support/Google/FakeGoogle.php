<?php

namespace Tests\Support\Google;

use App\Support\Google\CalendarEvent;
use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Google\MeetingRequest;
use App\Support\Google\Transcript;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use PHPUnit\Framework\Assert;

/**
 * Google for tests (docs/OPPORTUNITIES.md section 10): nothing leaves the machine. Records the meetings HQ creates,
 * changes and cancels, returns Meet links, and answers free/busy and transcripts as the test scripts them.
 *
 *   $google = FakeGoogle::install();
 *   $google->busy('praxisjbs@gmail.com', [['2026-11-05 09:00', '2026-11-05 10:00']]);
 *   $google->transcript('meet_api', 'abc-defg-hij', new Transcript('meet_api', 'Ada: hello'));
 *   $google->fail('revoked');                 // every call; ->fail('quota', 'createMeeting') for one method
 *   $google->assertMeetingCreated(fn (MeetingRequest $m) => $m->title === 'Discovery');
 */
class FakeGoogle extends GoogleWorkspace
{
    /** @var list<array{event: CalendarEvent, request: MeetingRequest}> */
    public array $created = [];

    /** @var list<array{eventId: string, request: MeetingRequest}> */
    public array $updated = [];

    /** @var list<string> */
    public array $cancelled = [];

    /** @var list<array{route: string, key: string}> transcript lookups, in order */
    public array $lookups = [];

    private array $busy = [];
    private array $events = [];

    private array $transcripts = ['meet_api' => [], 'calendar_doc' => []];

    /** method => reason ('*' for every method) */
    private array $failures = [];

    private bool $connected = true;

    private string $email = 'praxisjbs@gmail.com';

    public static function install(): self
    {
        $fake = new self;
        app()->instance(GoogleWorkspace::class, $fake);

        return $fake;
    }

    public function fail(string $reason, string $method = '*'): self
    {
        Assert::assertContains($reason, GoogleUnavailable::REASONS, "FakeGoogle: unknown failure '{$reason}'");
        $this->failures[$method] = $reason;

        return $this;
    }

    public function disconnected(): self
    {
        $this->connected = false;

        return $this;
    }

    public function as(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /** @param  list<array{0: string, 1: string}>|null  $periods  busy [start, end] pairs in UTC; null = calendar not shared */
    public function busy(string $email, ?array $periods): self
    {
        $this->busy[$email] = $periods;

        return $this;
    }

    /**
     * Calendar events listEvents() returns: id, title, start and end ('2026-10-28 14:00', Africa/Lagos), and
     * optionally meet_code, attendees ([['email' => …, 'name' => …]]) and docs (attached Google Docs)
     */
    public function events(array $events): self
    {
        foreach ($events as $e) {
            $this->events[] = [
                'id' => $e['id'],
                'title' => $e['title'],
                'start' => CarbonImmutable::parse($e['start'], self::TIME_ZONE),
                'end' => CarbonImmutable::parse($e['end'], self::TIME_ZONE),
                'meet_code' => $e['meet_code'] ?? null,
                'meet_link' => isset($e['meet_code']) ? 'https://meet.google.com/' . $e['meet_code'] : null,
                'attendees' => array_map(fn ($a) => $a + ['name' => null, 'response' => null], $e['attendees'] ?? []),
                'docs' => $e['docs'] ?? 1,
            ];
        }

        return $this;
    }

    /** $route meet_api (keyed by Meet code) or calendar_doc (keyed by event id); null = not ready yet */
    public function transcript(string $route, string $key, ?Transcript $transcript): self
    {
        $this->transcripts[$route][$key] = $transcript;

        return $this;
    }

    // ---- GoogleWorkspace ------------------------------------------------------------------------------------

    public function hasClient(): bool
    {
        return true;
    }

    public function isConnected(): bool
    {
        return $this->connected && ($this->failures['*'] ?? null) !== 'revoked';
    }

    public function needsReconnect(): bool
    {
        return ($this->failures['*'] ?? null) === 'revoked';
    }

    public function accountEmail(): ?string
    {
        return $this->connected ? $this->email : null;
    }

    public function grantedScopes(): array
    {
        return $this->connected ? self::SCOPES : [];
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.test/consent?state=' . urlencode($state);
    }

    public function connect(string $code): string
    {
        $this->guard('connect');
        $this->connected = true;

        return $this->email;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function test(): string
    {
        $this->guard('test');

        return $this->email;
    }

    public function createMeeting(MeetingRequest $meeting): CalendarEvent
    {
        $this->guard('createMeeting');
        $n = count($this->created) + 1;
        $code = sprintf('abc-%s-xyz', substr(str_repeat(chr(96 + min(26, $n)), 4), 0, 4));
        // In person (withMeet false): an event without a Meet link, as Google returns it
        $event = $meeting->withMeet
            ? new CalendarEvent("fake_event_{$n}", "https://meet.google.com/{$code}", $code, "https://calendar.google.test/event/{$n}")
            : new CalendarEvent("fake_event_{$n}", null, null, "https://calendar.google.test/event/{$n}");
        $this->created[] = ['event' => $event, 'request' => $meeting];

        return $event;
    }

    public function updateMeeting(string $eventId, MeetingRequest $meeting): CalendarEvent
    {
        $this->guard('updateMeeting');
        $this->updated[] = ['eventId' => $eventId, 'request' => $meeting];
        $created = collect($this->created)->first(fn ($c) => $c['event']->eventId === $eventId);

        return $created['event'] ?? new CalendarEvent($eventId, null, null);
    }

    public function cancelMeeting(string $eventId, bool $notify = true): void
    {
        $this->guard('cancelMeeting');
        $this->cancelled[] = $eventId;
    }

    public function freeBusy(array $emails, CarbonInterface $from, CarbonInterface $to): array
    {
        $this->guard('freeBusy');
        $out = [];
        foreach ($emails as $email) {
            $periods = array_key_exists($email, $this->busy) ? $this->busy[$email] : [];
            $out[$email] = $periods === null ? null : array_map(fn ($p) => [
                'start' => \Carbon\CarbonImmutable::parse($p[0], 'UTC'),
                'end' => \Carbon\CarbonImmutable::parse($p[1], 'UTC'),
            ], $periods);
        }

        return $out;
    }

    public function conferenceTranscript(string $meetCode, CarbonInterface $startedAfter, CarbonInterface $endedBefore): ?Transcript
    {
        $this->guard('conferenceTranscript');
        $this->lookups[] = ['route' => 'meet_api', 'key' => $meetCode];

        return $this->transcripts['meet_api'][$meetCode] ?? null;
    }

    public function listEvents(CarbonInterface $from, CarbonInterface $to): array
    {
        $this->guard('listEvents');

        return array_values(array_filter($this->events, fn ($e) => $e['start']->betweenIncluded($from, $to)));
    }

    public function eventAttachmentText(string $eventId): ?Transcript
    {
        $this->guard('eventAttachmentText');
        $this->lookups[] = ['route' => 'calendar_doc', 'key' => $eventId];

        return $this->transcripts['calendar_doc'][$eventId] ?? null;
    }

    // ---- Assertions -------------------------------------------------------------------------------------------

    public function assertMeetingCreated(?callable $check = null): void
    {
        Assert::assertNotEmpty($this->created, 'No Google meeting was created');
        if ($check) {
            Assert::assertTrue(collect($this->created)->contains(fn ($c) => $check($c['request'], $c['event'])), 'No created Google meeting matched');
        }
    }

    public function assertNothingCreated(): void
    {
        Assert::assertSame([], $this->created, 'A Google meeting was created');
    }

    private function guard(string $method): void
    {
        $reason = $this->failures[$method] ?? $this->failures['*'] ?? null;
        if ($reason !== null) {
            throw new GoogleUnavailable($reason);
        }
        if (! $this->connected && $method !== 'connect') {
            throw new GoogleUnavailable('not_connected');
        }
    }
}
