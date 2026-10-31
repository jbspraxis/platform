<?php

namespace Tests\Feature\Opportunities;

use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Google\MeetingRequest;
use App\Support\Google\Transcript;
use App\Support\Integrations as Settings;
use App\Support\Monitor\HealthChecks;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * The Google client (docs/OPPORTUNITIES.md 6.1 and 8.2) against faked Google APIs: the meeting request Calendar
 * receives, token refresh and its cache, revoked access, quota, both transcript routes and free/busy. And the
 * FakeGoogle that later PRs test with.
 */
class GoogleWorkspaceTest extends TestCase
{
    use UsesThrowawayDatabase;

    private function connected(): GoogleWorkspace
    {
        Settings::set('google.client_id', '123-abc.apps.googleusercontent.com');
        Settings::set('google.client_secret', 'GOCSPX-test');
        Settings::set('google.refresh_token', '1//test-refresh');
        Settings::set('google.account_email', 'praxisjbs@gmail.com');
        Settings::set('google.status', 'connected');

        return app(GoogleWorkspace::class);
    }

    private function token(): array
    {
        return ['oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599])];
    }

    public function test_create_meeting_sends_what_the_runbook_says(): void
    {
        $google = $this->connected();
        Http::fake($this->token() + [
            'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'id' => 'evt123', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij', 'htmlLink' => 'https://calendar.google.com/event?eid=1',
                'conferenceData' => ['conferenceId' => 'abc-defg-hij', 'createRequest' => ['status' => ['statusCode' => 'success']]],
            ]),
        ]);

        $event = $google->createMeeting(new MeetingRequest(
            title: 'Discovery: Acme', startsAt: CarbonImmutable::parse('2026-11-05 09:30', 'UTC'), endsAt: CarbonImmutable::parse('2026-11-05 10:30', 'UTC'),
            attendees: [['email' => 'ada@acme.test', 'name' => 'Ada Obi'], ['email' => 'lead@jbspraxis.com']], description: "Agenda\nThis meeting is transcribed.",
        ));
        $google->createMeeting(new MeetingRequest('Internal', CarbonImmutable::now(), CarbonImmutable::now()->addHour(), notify: false));

        $this->assertSame(['evt123', 'https://meet.google.com/abc-defg-hij', 'abc-defg-hij'], [$event->eventId, $event->meetLink, $event->meetCode]);
        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), '/calendars/primary/events') || $r['summary'] !== 'Discovery: Acme') {
                return false;
            }
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $r->method() === 'POST' && $q === ['conferenceDataVersion' => '1', 'sendUpdates' => 'all']
                && $r->hasHeader('Authorization', 'Bearer ya29.test')
                && $r['start'] === ['dateTime' => '2026-11-05T10:30:00+01:00', 'timeZone' => 'Africa/Lagos']
                && $r['end']['timeZone'] === 'Africa/Lagos'
                && $r['attendees'] === [['email' => 'ada@acme.test', 'displayName' => 'Ada Obi'], ['email' => 'lead@jbspraxis.com']]
                && $r['conferenceData']['createRequest']['conferenceSolutionKey'] === ['type' => 'hangoutsMeet']
                && strlen($r['conferenceData']['createRequest']['requestId']) === 36;
        });
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sendUpdates=none') && $r['summary'] === 'Internal');
        // One token refresh for both calls: the access token is cached (encrypted)
        Http::assertSentCount(3);
    }

    public function test_reschedule_and_cancel(): void
    {
        $google = $this->connected();
        Http::fake($this->token() + [
            'www.googleapis.com/calendar/v3/calendars/primary/events/evt1*' => Http::sequence()
                ->push(['id' => 'evt1', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij'])
                ->push(null, 204),
            'www.googleapis.com/calendar/v3/calendars/primary/events/gone*' => Http::response(['error' => ['code' => 410]], 410),
        ]);

        $google->updateMeeting('evt1', new MeetingRequest('Moved', CarbonImmutable::now(), CarbonImmutable::now()->addHour()));
        $google->cancelMeeting('evt1');
        $google->cancelMeeting('gone'); // already deleted: fine

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_contains($r->url(), 'sendUpdates=all') && $r['summary'] === 'Moved');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/evt1?sendUpdates=all'));
    }

    public function test_revoked_access_stops_everything_and_turns_system_health_red(): void
    {
        $google = $this->connected();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);

        try {
            $google->test();
            $this->fail('Expected GoogleUnavailable');
        } catch (GoogleUnavailable $e) {
            $this->assertSame('revoked', $e->reason);
            $this->assertSame('Google withdrew HQ’s access. Reconnect it in Settings and access › Integrations.', $e->getMessage());
        }
        $this->assertTrue($google->needsReconnect());
        $this->assertFalse($google->isConnected());

        $check = collect(HealthChecks::all())->firstWhere('key', 'integrations');
        $this->assertSame('bad', $check['state']);
        $this->assertStringContainsString('Connect Google', $check['fix']);

        // Further calls don't even try
        Http::fake();
        $this->expectExceptionObject(new GoogleUnavailable('revoked'));
        $google->createMeeting(new MeetingRequest('x', CarbonImmutable::now(), CarbonImmutable::now()->addHour()));
    }

    public function test_quota_and_missing_permissions(): void
    {
        $google = $this->connected();
        Http::fake($this->token() + [
            'www.googleapis.com/calendar/v3/freeBusy' => Http::sequence()
                ->push(['error' => ['code' => 403, 'errors' => [['reason' => 'rateLimitExceeded']]]], 403)
                ->push(['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'errors' => [['reason' => 'insufficientPermissions']]]], 403),
        ]);

        foreach (['quota', 'scopes'] as $expected) {
            try {
                $google->freeBusy(['primary'], CarbonImmutable::now(), CarbonImmutable::now()->addHour());
                $this->fail('Expected GoogleUnavailable');
            } catch (GoogleUnavailable $e) {
                $this->assertSame($expected, $e->reason);
            }
        }
        $this->assertFalse($google->needsReconnect(), 'A quota error is not a revoked connection');
    }

    public function test_free_busy(): void
    {
        $google = $this->connected();
        Http::fake($this->token() + ['www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => [
            'primary' => ['busy' => [['start' => '2026-11-05T09:00:00Z', 'end' => '2026-11-05T10:00:00Z']]],
            'staff@jbspraxis.com' => ['errors' => [['domain' => 'global', 'reason' => 'notFound']]],
        ]])]);

        $busy = $google->freeBusy(['primary', 'staff@jbspraxis.com'], CarbonImmutable::parse('2026-11-05 08:00'), CarbonImmutable::parse('2026-11-05 18:00'));

        $this->assertSame('2026-11-05 09:00', $busy['primary'][0]['start']->format('Y-m-d H:i'));
        $this->assertNull($busy['staff@jbspraxis.com'], 'A calendar not shared with the company account is unknown, not free');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/freeBusy') && $r['items'] === [['id' => 'primary'], ['id' => 'staff@jbspraxis.com']]
            && $r['timeMin'] === '2026-11-05T08:00:00+00:00');
    }

    public function test_meet_api_transcript_with_speakers(): void
    {
        $google = $this->connected();
        Http::fake($this->token() + [
            'meet.googleapis.com/v2/conferenceRecords?*' => Http::response(['conferenceRecords' => [['name' => 'conferenceRecords/rec1', 'startTime' => '2026-11-05T09:31:00Z']]]),
            'meet.googleapis.com/v2/conferenceRecords/rec1/transcripts' => Http::response(['transcripts' => [
                ['name' => 'conferenceRecords/rec1/transcripts/t1', 'state' => 'FILE_GENERATED', 'docsDestination' => ['document' => 'doc-1']],
            ]]),
            'meet.googleapis.com/v2/conferenceRecords/rec1/participants*' => Http::response(['participants' => [
                ['name' => 'conferenceRecords/rec1/participants/p1', 'signedinUser' => ['displayName' => 'Ada Obi']],
                ['name' => 'conferenceRecords/rec1/participants/p2', 'anonymousUser' => ['displayName' => 'Guest']],
            ]]),
            'meet.googleapis.com/v2/conferenceRecords/rec1/transcripts/t1/entries*' => Http::sequence()
                ->push(['transcriptEntries' => [
                    ['participant' => 'conferenceRecords/rec1/participants/p1', 'text' => 'Bonjour à tous.', 'languageCode' => 'fr-FR', 'startTime' => '2026-11-05T09:32:00Z'],
                ], 'nextPageToken' => 'page2'])
                ->push(['transcriptEntries' => [
                    ['participant' => 'conferenceRecords/rec1/participants/p2', 'text' => 'Merci.', 'languageCode' => 'fr-FR', 'startTime' => '2026-11-05T09:33:00Z'],
                ]]),
        ]);

        $t = $google->conferenceTranscript('ABC-DEFG-HIJ', CarbonImmutable::parse('2026-11-05 09:00'), CarbonImmutable::parse('2026-11-05 11:00'));

        $this->assertInstanceOf(Transcript::class, $t);
        $this->assertSame(['meet_api', 'fr', 'doc-1'], [$t->source, $t->language, $t->googleDocId]);
        $this->assertSame("Ada Obi (10:32): Bonjour à tous.\nGuest (10:33): Merci.", $t->text);
        $this->assertSame('Ada Obi', $t->entries[0]['speaker']);
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'filter=space.meeting_code = "abc-defg-hij" AND start_time >= "2026-11-05T09:00:00Z"'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'pageToken=page2'));
    }

    public function test_meet_api_returns_nothing_while_the_transcript_is_still_being_written(): void
    {
        $google = $this->connected();
        Http::fake($this->token() + [
            'meet.googleapis.com/v2/conferenceRecords?*' => Http::response(['conferenceRecords' => [['name' => 'conferenceRecords/rec1']]]),
            'meet.googleapis.com/v2/conferenceRecords/rec1/transcripts' => Http::response(['transcripts' => [['name' => 'conferenceRecords/rec1/transcripts/t1', 'state' => 'STARTED']]]),
        ]);

        $this->assertNull($google->conferenceTranscript('abc-defg-hij', CarbonImmutable::now()->subHour(), CarbonImmutable::now()));
    }

    public function test_calendar_attachment_reads_the_transcript_tab(): void
    {
        $google = $this->connected();
        $paragraph = fn (string $text) => ['paragraph' => ['elements' => [['textRun' => ['content' => $text]]]]];
        Http::fake($this->token() + [
            'www.googleapis.com/calendar/v3/calendars/primary/events/evt9*' => Http::response(['id' => 'evt9', 'attachments' => [
                ['title' => 'Discovery – Notes by Gemini', 'mimeType' => 'application/vnd.google-apps.document', 'fileId' => 'doc-9'],
                ['title' => 'slides.pdf', 'mimeType' => 'application/pdf', 'fileId' => 'pdf-1'],
            ]]),
            'docs.googleapis.com/v1/documents/doc-9*' => Http::response(['tabs' => [
                ['tabProperties' => ['title' => 'Notes'], 'documentTab' => ['body' => ['content' => [$paragraph("Summary\n")]]]],
                ['tabProperties' => ['title' => 'Transcript'], 'documentTab' => ['body' => ['content' => [$paragraph("Ada Obi: Hello\n"), $paragraph("Lead: Welcome\n")]]]],
            ]]),
        ]);

        $t = $google->eventAttachmentText('evt9');

        $this->assertSame(['calendar_doc', 'doc-9', "Ada Obi: Hello\nLead: Welcome"], [$t->source, $t->googleDocId, $t->text]);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'documents/doc-9?includeTabsContent=true'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'pdf-1'));
    }

    public function test_not_connected(): void
    {
        Http::fake();
        $google = app(GoogleWorkspace::class);

        $this->assertFalse($google->isConnected());
        $this->expectExceptionObject(new GoogleUnavailable('not_connected'));
        $google->test();
    }

    public function test_the_fake(): void
    {
        $google = FakeGoogle::install();
        $this->assertSame($google, app(GoogleWorkspace::class));

        $event = app(GoogleWorkspace::class)->createMeeting(new MeetingRequest('Discovery', CarbonImmutable::now(), CarbonImmutable::now()->addHour()));
        $this->assertStringStartsWith('https://meet.google.com/', $event->meetLink);
        $google->assertMeetingCreated(fn (MeetingRequest $m) => $m->title === 'Discovery');

        $google->busy('praxisjbs@gmail.com', [['2026-11-05 09:00', '2026-11-05 10:00']])->busy('staff@jbspraxis.com', null);
        $busy = $google->freeBusy(['praxisjbs@gmail.com', 'staff@jbspraxis.com'], CarbonImmutable::now(), CarbonImmutable::now()->addDay());
        $this->assertCount(1, $busy['praxisjbs@gmail.com']);
        $this->assertNull($busy['staff@jbspraxis.com']);

        $google->transcript('meet_api', $event->meetCode, new Transcript('meet_api', 'Ada: hello'));
        $this->assertSame('Ada: hello', $google->conferenceTranscript($event->meetCode, CarbonImmutable::now(), CarbonImmutable::now())->text);
        $this->assertNull($google->eventAttachmentText($event->eventId));

        $google->fail('quota', 'createMeeting');
        try {
            $google->createMeeting(new MeetingRequest('Again', CarbonImmutable::now(), CarbonImmutable::now()->addHour()));
            $this->fail('Expected GoogleUnavailable');
        } catch (GoogleUnavailable $e) {
            $this->assertSame('quota', $e->reason);
        }
        $google->fail('revoked');
        $this->assertTrue($google->needsReconnect());
    }
}
