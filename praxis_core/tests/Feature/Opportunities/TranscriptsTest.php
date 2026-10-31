<?php

namespace Tests\Feature\Opportunities;

use App\Livewire\Hq\Opportunities\MeetingsTab;
use App\Livewire\Hq\Opportunities\TranscriptList;
use App\Mail\MeetingMinutesMail;
use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Support\Ai\AiRequest;
use App\Support\Google\Transcript;
use App\Support\Opportunities\Meetings;
use App\Support\Opportunities\Transcripts;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * Transcripts (docs/OPPORTUNITIES.md 6.1): meetings:collect-transcripts (Meet API, then the Gemini doc in Calendar;
 * every 10 minutes until 6 hours after the end, then Not found), adding one by hand (.vtt, paste), the AI's notes and
 * minutes (Haiku, through FakeAi), sending the minutes, and the list of every transcript together.
 */
class TranscriptsTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private const NOTES = ['summary' => 'Payroll runs on spreadsheets.', 'decisions' => ['Start with the Lagos office'], 'needs' => ['One payroll for 3 entities'],
        'risks' => ['Year-end freeze'], 'open_questions' => ['Who signs off pay runs?'], 'next_steps' => [['action' => 'Send last month’s payslips', 'owner' => 'Client HR', 'due' => 'Friday']]];

    private const MINUTES = ['subject' => 'Our meeting', 'summary' => 'We looked at how payroll runs today.', 'decisions' => ['Start with the Lagos office'],
        'next_steps' => [['action' => 'Send last month’s payslips', 'owner' => 'Your HR team', 'due' => 'Friday']]];

    private FakeGoogle $google;

    private FakeAi $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
        $this->google = FakeGoogle::install();
        $this->ai = FakeAi::install()->respond('meeting_notes', self::NOTES, 9000, 600)->respond('client_minutes', self::MINUTES, 9000, 300);
    }

    public function test_the_collector_files_meets_transcript_and_the_ai_writes_notes_and_minutes(): void
    {
        [$o, $lead, $m] = $this->meetingThatEnded(minutesAgo: 25);
        $this->google->transcript('meet_api', $m->meet_code, new Transcript('meet_api', 'Ada Obi (10:02): We pay 300 people by hand.',
            [['speaker' => 'Ada Obi', 'time' => '2026-11-05T09:02:00Z', 'text' => 'We pay 300 people by hand.']], 'en', 'doc123'));

        $this->artisan('meetings:collect-transcripts')->expectsOutputToContain('filed 1')->assertSuccessful();

        $m->refresh();
        $this->assertSame(['held', 'ready', null], [$m->status, $m->transcript_status, $m->transcript_next_try_at]);
        $t = $m->transcripts()->sole();
        $this->assertSame(['meet_api', 'doc123', 'en'], [$t->source, $t->google_doc_id, $t->language]);
        $this->assertSame('Ada Obi', $t->entries[0]['speaker']);
        $this->assertSame(['meet_api'], array_column($this->google->lookups, 'route'));   // route 1 answered: route 2 not needed

        // Haiku wrote the notes and the minutes (the transcript is the material; the brief and profile are cached)
        $this->assertEquals(self::NOTES, $t->notes);   // MySQL keeps JSON keys in its own order
        $this->assertStringContainsString("Décisions\n- Start with the Lagos office", $t->minutes);   // headings in the opportunity's language
        $this->assertStringContainsString('- Send last month’s payslips (Your HR team, Friday)', $t->minutes);
        $this->ai->assertRan('meeting_notes', fn (AiRequest $r) => $r->model === 'claude-haiku-5-5' && str_contains($r->prompt, 'We pay 300 people by hand')
            && str_contains($r->prompt, 'Attendees:') && str_contains($r->prompt, '(JBS Praxis)'));
        $this->assertSame(2, AiRun::where('subject_type', 'meeting_transcript')->where('subject_id', $t->id)->where('status', 'done')->count());

        // The lead hears about it, in the bell, with a link to the meeting
        $bell = DB::table('notifications')->where('user_id', $lead->id)->sole();
        $this->assertSame('Transcript filed', $bell->title);
        $this->assertStringContainsString("tab=meetings&meeting={$m->id}", $bell->link_url);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'TRANSCRIPT_COLLECTED', 'target_id' => $o->id]);
    }

    public function test_the_gemini_notes_in_the_calendar_event_are_the_second_route(): void
    {
        [, , $m] = $this->meetingThatEnded(minutesAgo: 25);
        $this->google->transcript('calendar_doc', $m->google_event_id, new Transcript('calendar_doc', "Transcript\nAda: hello", null, null, 'gdoc9'));

        $this->artisan('meetings:collect-transcripts')->assertSuccessful();

        $this->assertSame(['meet_api', 'calendar_doc'], array_column($this->google->lookups, 'route'));
        $this->assertSame(['calendar_doc', 'gdoc9'], [$m->transcripts()->sole()->source, $m->transcripts()->sole()->google_doc_id]);
    }

    public function test_not_before_20_minutes_then_every_10_minutes_then_not_found_after_6_hours(): void
    {
        [$o, $lead, $m] = $this->meetingThatEnded(minutesAgo: 10);

        $this->artisan('meetings:collect-transcripts')->assertSuccessful();
        $this->assertSame(['held', 'waiting'], [$m->fresh()->status, $m->fresh()->transcript_status]);
        $this->assertSame([], $this->google->lookups);   // Google takes 15–20 minutes

        $this->travel(11)->minutes();
        $this->artisan('meetings:collect-transcripts')->assertSuccessful();
        $m->refresh();
        $this->assertSame(['collecting', 1], [$m->transcript_status, $m->transcript_tries]);
        $this->assertSame(now()->addMinutes(10)->format('Y-m-d H:i'), $m->transcript_next_try_at->format('Y-m-d H:i'));
        $this->assertCount(2, $this->google->lookups);

        // Not due yet: no lookup
        $this->travel(5)->minutes();
        $this->artisan('meetings:collect-transcripts')->assertSuccessful();
        $this->assertCount(2, $this->google->lookups);

        $this->travelTo($m->ends_at->copy()->addHours(6));
        $this->artisan('meetings:collect-transcripts')->expectsOutputToContain('not found 1')->assertSuccessful();
        $this->assertSame('not_found', $m->fresh()->transcript_status);
        $this->assertSame('Transcript not found', DB::table('notifications')->where('user_id', $lead->id)->value('title'));

        $this->artisan('meetings:collect-transcripts')->assertSuccessful();
        $this->assertCount(4, $this->google->lookups);   // never looked up again
    }

    public function test_google_down_stops_the_run_without_failing_it(): void
    {
        [, , $m] = $this->meetingThatEnded(minutesAgo: 30);
        $this->google->fail('revoked');

        $this->artisan('meetings:collect-transcripts')->expectsOutputToContain('Google: revoked')->assertSuccessful();
        $this->assertSame(['collecting', 1], [$m->fresh()->transcript_status, $m->fresh()->transcript_tries]);
    }

    public function test_a_meeting_hq_cant_look_up_waits_to_be_added_by_hand(): void
    {
        [, , $m] = $this->meetingThatEnded(minutesAgo: 30);
        $m->forceFill(['google_event_id' => null, 'meet_code' => null])->save();

        $this->artisan('meetings:collect-transcripts')->expectsOutputToContain('to add by hand 1')->assertSuccessful();
        $this->assertSame('manual', $m->fresh()->transcript_status);
    }

    public function test_the_lead_uploads_a_vtt_file_and_edits_and_sends_the_minutes(): void
    {
        [$o, $lead, $m] = $this->meetingThatEnded(minutesAgo: 400);
        $m->forceFill(['transcript_status' => 'not_found'])->save();
        $this->actingAs($lead);
        $vtt = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\n<v Ada Obi>We pay 300 people by hand.</v>\n\nNOTE a comment\n\n00:01:05.500 --> 00:01:08.000\n<v Tolu Adeyemi>Let’s start with Lagos.</v>";

        $tab = Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->call('openTranscript', $m->id)
            ->call('saveTranscript')->assertHasErrors(['transcriptText'])
            ->set('transcriptText', $vtt)->set('transcriptSource', 'upload')
            ->call('saveTranscript')->assertHasNoErrors()
            ->assertSee('Transcript added')->assertSee('Payroll runs on spreadsheets.');

        $t = $m->transcripts()->sole();
        $this->assertSame('upload', $t->source);
        $this->assertSame("Ada Obi (00:00:01): We pay 300 people by hand.\nTolu Adeyemi (00:01:05): Let’s start with Lagos.", $t->text);
        $this->assertSame('ready', $m->fresh()->transcript_status);
        $this->assertSame(0, DB::table('notifications')->count());   // added by the lead: no need to tell them
        $this->assertStringContainsString('Start with the Lagos office', $tab->get("minutes.{$t->id}"));

        // Edit, save, send: to the client and guests only, in the opportunity's language
        $tab->set("minutes.{$t->id}", "Thank you for today.\n\nDecisions\n- Start with Lagos")
            ->call('saveMinutes', $t->id)->assertSee('Minutes saved.')
            ->call('sendMinutes', $t->id)->assertSee('Minutes sent to 2 people.')
            ->assertSee('Sent to the client on');
        $t->refresh();
        $this->assertNotNull($t->minutes_sent_at);
        $this->assertSame("Thank you for today.\n\nDecisions\n- Start with Lagos", $t->minutes);
        Mail::assertSent(MeetingMinutesMail::class, 2);
        Mail::assertSent(MeetingMinutesMail::class, fn ($mail) => $mail->hasTo('ada@client.test') && $mail->locale === 'fr');
        Mail::assertNotSent(MeetingMinutesMail::class, fn ($mail) => $mail->hasTo($lead->email));
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'MINUTES_SENT', 'target_id' => $o->id]);

        // Sent minutes don't change
        $tab->call('sendMinutes', $t->id)->assertSee('already sent');
        Mail::assertSent(MeetingMinutesMail::class, 2);
    }

    public function test_minutes_go_once_and_an_address_that_failed_can_be_sent_again(): void
    {
        [$o, $lead, $m] = $this->meetingThatEnded(minutesAgo: 60);
        $this->actingAs($lead);
        $t = Transcripts::fromText($m, 'Ada: hello', 'paste');
        // A real (array) mailer where Ada's mailbox refuses mail until it is fixed
        Mail::swap(new MailManager($this->app));
        $failing = 'ada@client.test';
        $delivered = [];
        Event::listen(MessageSending::class, function (MessageSending $e) use (&$failing, &$delivered) {
            $to = $e->message->getTo()[0]->getAddress();
            if ($to === $failing) {
                throw new RuntimeException('Mailbox unavailable');
            }
            $delivered[] = $to;
        });

        $this->assertSame(['ada@client.test'], Transcripts::sendMinutes($t, 'Thank you for today.'));
        $t->refresh();
        $this->assertNotNull($t->minutes_sent_at);
        $this->assertSame(['ada@client.test'], $t->minutes_unsent);
        $this->assertSame(['guest@partner.test'], $delivered);

        // A second send (a double click, or a colleague at the same moment) sends nothing
        try {
            Transcripts::sendMinutes($t, 'Thank you for today.');
            $this->fail('Minutes were sent twice');
        } catch (LogicException) {
        }
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->set("minutes.{$t->id}", 'Again')
            ->call('sendMinutes', $t->id)->assertSee('already sent');
        $this->assertSame(['guest@partner.test'], $delivered);

        // Send again: only the address that failed
        $failing = null;
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->set('open', $m->id)
            ->assertSee('Not delivered to ada@client.test.')
            ->call('resendMinutes', $t->id)->assertSee('Minutes sent to 1 person.')->assertDontSee('Not delivered to');
        $this->assertSame(['guest@partner.test', 'ada@client.test'], $delivered);
        $this->assertNull($t->fresh()->minutes_unsent);
    }

    public function test_drafting_notes_or_minutes_needs_the_right_to_read_the_transcript(): void
    {
        [$o, $lead, $m] = $this->meetingThatEnded(minutesAgo: 60);
        $t = Transcripts::fromText($m, 'Ada: hello', 'paste');
        // A colleague who manages opportunities but may not read transcripts, and isn't this one's lead
        DB::table('role_permissions')->where('role_id', DB::table('roles')->where('audience', 'staff')->where('slug', 'sales')->value('id'))
            ->whereIn('permission_id', DB::table('permissions')->where('module', 'opportunities')->where('action', 'transcripts')->pluck('id'))->delete();
        $this->actingAs($this->staff('colleague', ['sales']));
        $runs = AiRun::count();

        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->call('draftNotes', $t->id)->assertForbidden();
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->call('draftMinutes', $t->id)->assertForbidden();
        $this->assertSame($runs, AiRun::count());
    }

    public function test_without_a_key_nothing_is_drafted_and_the_lead_can_try_again(): void
    {
        $this->ai->unconfigured();
        [$o, $lead, $m] = $this->meetingThatEnded(minutesAgo: 60);
        $this->actingAs($lead);
        $t = Transcripts::fromText($m, 'Ada: hello', 'paste');
        $this->assertNull($t->fresh()->notes);
        $this->assertSame(0, AiRun::count());

        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])
            ->call('toggle', $m->id)->assertSee('No notes yet.')->assertSee('Write the notes')
            ->call('draftNotes', $t->id)->assertSee(__('ai.errors.not_configured'));

        // A refusal shows on the meeting with "Try again"
        $ai = FakeAi::install()->refuse('meeting_notes')->respond('client_minutes', self::MINUTES);
        Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id])->set('open', $m->id)
            ->call('draftNotes', $t->id)->assertSee(__('ai.errors.refused'))->assertSee('Try again');
        $ai->assertRan('meeting_notes');
    }

    public function test_internal_meetings_get_notes_but_no_minutes_and_every_transcript_is_listed_together(): void
    {
        [$o, $lead, $client] = $this->meetingThatEnded(minutesAgo: 60);
        $internal = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $o->organization_id, 'kind' => 'internal', 'title' => 'Team debrief',
            'starts_at' => now()->subMinutes(30), 'ends_at' => now()->subMinutes(10), 'status' => 'held', 'transcript_status' => 'waiting']);
        Transcripts::fromText($client, 'Ada: the client meeting', 'paste');
        Transcripts::fromText($internal, 'Tolu: our debrief', 'paste');
        $this->assertCount(1, $this->ai->requestsFor('client_minutes'));
        $this->assertCount(2, $this->ai->requestsFor('meeting_notes'));

        $this->actingAs($lead);
        Livewire::test(TranscriptList::class, ['opportunityId' => $o->id])
            ->assertSeeInOrder(['Discovery', 'Team debrief'])->assertSee('Ada: the client meeting')->assertSee('Tolu: our debrief')
            ->assertSee('Payroll runs on spreadsheets.');
        $this->actingAs($this->staff('finance', ['finance']));
        Livewire::test(TranscriptList::class, ['opportunityId' => $o->id])
            ->assertSee('Payroll runs on spreadsheets.')->assertDontSee('Ada: the client meeting');
    }

    public function test_the_minutes_email_in_both_languages(): void
    {
        $this->assertSame([
            ['text' => "We met.\nTwo lines."],
            ['heading' => 'Decisions', 'items' => ['One', 'Two']],
            ['heading' => null, 'items' => ['Only items']],
        ], MeetingMinutesMail::blocks("We met.\nTwo lines.\n\nDecisions\n- One\n- Two\n\n- Only items"));

        foreach (['en' => 'Minutes: Discovery: finance system on Thursday 5 November 2026', 'fr' => "Compte rendu\u{00A0}: Discovery: finance system du jeudi 5 novembre 2026"] as $locale => $subject) {
            $parts = MeetingMinutesMail::samples()['minutes']->locale($locale)->renderParts();
            $this->assertSame($subject, $parts['subject']);
            $this->assertStringContainsString('<li style="margin: 0 0 4px;">Start with the three Lagos entities</li>', $parts['html']);
            $this->assertStringNotContainsString('emails.', $parts['html']);
        }
    }

    /** A client meeting created through Google that ended $minutesAgo, in a French opportunity led by a sales person */
    private function meetingThatEnded(int $minutesAgo): array
    {
        $lead = $this->staff('lead', ['sales']);
        $org = $this->organisation(['name' => 'Société Kribi', 'preferred_language' => 'FR']);
        $o = $this->opportunityFor($org, ['lead_user_id' => $lead->id, 'language' => 'fr']);
        $contact = $this->clientContact($org, ['email' => 'ada@client.test']);
        $ends = now()->subMinutes($minutesAgo);
        $m = Meetings::schedule($o, ['kind' => 'client', 'title' => 'Discovery', 'starts_at' => $ends->copy()->subHour(), 'ends_at' => $ends,
            'staff' => [$lead->id], 'contacts' => [$contact], 'guests' => [['email' => 'guest@partner.test', 'name' => 'Guest']]], brandedEmail: false)->meeting;
        $this->google->created = [];

        return [$o, $lead, $m];
    }
}
