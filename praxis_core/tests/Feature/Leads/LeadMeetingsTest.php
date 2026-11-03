<?php

namespace Tests\Feature\Leads;

use App\Mail\MeetingInvitationMail;
use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\Site\BookingService;
use App\Support\Ai\AiRequest;
use App\Support\Ai\AiUnavailable;
use App\Support\Google\MeetingRequest;
use App\Support\Hosts;
use App\Support\Leads\LeadMeetings;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\Leads;
use App\Support\Opportunities\MeetingNotesFile;
use App\Support\Opportunities\OpportunityContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * Leads PR 2's meetings (docs/LEADS.md section 9, owner's decisions 11 and 13–16): a lead's meetings without an
 * opportunity, scheduled through Google (Meet, or in person at an address) or logged; their transcripts and the AI's
 * notes through the opportunity meeting code; website bookings as requested lead meetings; Open an opportunity from a
 * meeting (AI title, the meeting and its people carried over); the notes download.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class LeadMeetingsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const NOTES = ['summary' => 'They count stock on paper.', 'decisions' => ['Pilot in two stores'], 'needs' => ['Stock that matches the tills'],
        'risks' => [], 'open_questions' => ['Which stores?'], 'next_steps' => [['action' => 'Send a pilot outline', 'owner' => 'JBS Praxis', 'due' => 'Friday']]];

    private FakeGoogle $google;

    private FakeAi $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
        $this->google = FakeGoogle::install();
        $this->ai = FakeAi::install()->respond('meeting_notes', self::NOTES)->respond('opportunity_title', ['title' => 'Stock and till reconciliation']);
    }

    /** A lead with one contact who has an email (and the person adding it as owner) */
    private function lead(User $by, array $values = []): Organization
    {
        return Leads::create($values + ['name' => 'Kilo Retail (test)', 'country' => 'Nigeria', 'industry' => 'Retail', 'phone' => '+234 800 000 0001'],
            [['name' => 'Ada Test', 'email' => 'ada@kilo.test', 'deal_role' => 'decision_maker']], $by);
    }

    private function contactId(Organization $lead): string
    {
        return (string) DB::table('client_users')->where('organization_id', $lead->id)->where('email', 'ada@kilo.test')->value('id');
    }

    private function data(Organization $lead, User $by, array $extra = []): array
    {
        $starts = CarbonImmutable::parse('2026-11-09 10:00', 'Africa/Lagos');

        return $extra + ['title' => 'Introduction: Kilo Retail', 'agenda' => 'How stock is counted today', 'starts_at' => $starts, 'ends_at' => $starts->addHour(),
            'staff' => [$by->id], 'contacts' => [$this->contactId($lead)], 'guests' => []];
    }

    public function test_scheduling_a_lead_meeting_on_meet_and_in_person(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales, ['country' => 'Cameroon']);   // French by its country

        $m = LeadMeetings::schedule($lead, $this->data($lead, $sales), $sales)->meeting;
        $this->assertSame([null, $lead->id, 'client', 'scheduled', 'waiting'], [$m->opportunity_id, $m->organization_id, $m->kind, $m->status, $m->transcript_status]);
        $this->assertNotNull($m->meet_link);
        $this->assertSame(['staff', 'client'], $m->attendees->pluck('role')->sort()->values()->reverse()->values()->all());
        $this->google->assertMeetingCreated(fn (MeetingRequest $r) => $r->withMeet && $r->location === null && str_contains((string) $r->description, 'JBS Praxis enregistre une transcription'));
        Mail::assertSent(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo('ada@kilo.test') && $mail->locale === 'fr');
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'MEETING_SCHEDULED', 'target_table' => 'organizations', 'target_id' => $lead->id]);
        $line = DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'meeting')->sole();
        $this->assertSame($m->id, $line->meeting_id);
        $this->assertStringContainsString('Meeting scheduled for Mon 9 Nov, 10:00 (WAT) on Google Meet: Introduction: Kilo Retail', $line->summary);

        // In person: the invite has the address and no Meet link, nothing is recorded, the email gives the address
        $visit = LeadMeetings::schedule($lead, $this->data($lead, $sales, ['title' => 'Site visit', 'location' => '12 Example Road, Douala']), $sales)->meeting;
        $this->assertSame([null, '12 Example Road, Douala', 'manual'], [$visit->meet_link, $visit->location, $visit->transcript_status]);
        $this->google->assertMeetingCreated(fn (MeetingRequest $r) => ! $r->withMeet && $r->location === '12 Example Road, Douala'
            && ! str_contains((string) $r->description, 'transcription') && ($r->toEvent()['location'] ?? null) === '12 Example Road, Douala');
        $html = (new MeetingInvitationMail($visit->fresh(['attendees', 'organization']), 'invite', 'Ada'))->locale('en')->renderParts()['html'];
        $this->assertStringContainsString('In person, at 12 Example Road, Douala', $html);
        $this->assertStringContainsString('Kilo Retail (test)', $html);
        $this->assertStringNotContainsString('Join the meeting', $html);

        // Cancelled: Google tells everyone; a lead meeting's email offers a reply, not a booking page
        LeadMeetings::cancel($visit->fresh('attendees'), $sales);
        $this->assertSame('cancelled', $visit->fresh()->status);
        $this->assertSame(3, DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'meeting')->count());
        $cancelled = (new MeetingInvitationMail($visit->fresh(['attendees', 'organization']), 'cancelled'))->locale('en')->renderParts()['html'];
        $this->assertStringContainsString('Reply to this email and we’ll find another time', $cancelled);
        $this->assertSame([null, null], [$lead->fresh()->opportunities()->value('id'), Opportunity::value('id')]);
    }

    public function test_logging_a_past_meeting_with_its_transcript_writes_the_notes_but_no_minutes(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $starts = CarbonImmutable::parse('2026-11-04 15:00', 'Africa/Lagos');

        $m = LeadMeetings::logPast($lead, $this->data($lead, $sales, [
            'starts_at' => $starts, 'ends_at' => $starts->addMinutes(45), 'location' => 'Their office',
            'typed_notes' => "They count stock on paper.\nWant a pilot.", 'transcript' => "Ada Test: We count stock on paper every Friday.\nSales Test: We can pilot it.",
        ]), $sales);

        $this->assertSame(['held', 'ready', null, 'Their office'], [$m->status, $m->transcript_status, $m->google_event_id, $m->location]);
        $this->google->assertNothingCreated();
        Mail::assertNothingSent();
        $t = $m->transcripts->sole();
        $this->assertEquals(self::NOTES, $t->fresh()->notes);
        $this->ai->assertRan('meeting_notes', fn (AiRequest $r) => str_contains($r->prompt, 'We count stock on paper every Friday')
            && str_contains($r->prompt, 'Notes typed by the team: They count stock on paper.') && str_contains($r->system[1]['text'] ?? json_encode($r->system), 'Kilo Retail (test)'));
        $this->assertSame([], $this->ai->requestsFor('client_minutes'), 'A lead meeting sends no minutes');
        $line = DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'meeting')->sole();
        $this->assertSame(["They count stock on paper.\nWant a pilot.", '2026-11-04 14:00:00'], [$line->body, substr($line->occurred_at, 0, 19)]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'MEETING_LOGGED', 'target_id' => $lead->id]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'TRANSCRIPT_ADDED', 'target_id' => $lead->id]);

        // A meeting that hasn't happened can't be logged
        $this->expectException(LogicException::class);
        LeadMeetings::logPast($lead, $this->data($lead, $sales), $sales);
    }

    public function test_the_collector_tells_the_leads_owner_about_a_lead_meeting(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $starts = CarbonImmutable::parse('2026-11-05 10:00', 'Africa/Lagos');
        $m = LeadMeetings::schedule($lead, $this->data($lead, $sales, ['starts_at' => $starts, 'ends_at' => $starts->addHour()]), $sales)->meeting;

        // Six hours after the end with nothing in Google: Not found, and the lead's owner gets a bell to its Meetings tab
        $this->travelTo(CarbonImmutable::parse('2026-11-05 16:30:00', 'UTC'));
        $this->artisan('meetings:collect-transcripts')->assertSuccessful();
        $this->assertSame(['held', 'not_found'], [$m->fresh()->status, $m->fresh()->transcript_status]);
        $bell = DB::table('notifications')->where('user_id', $sales->id)->where('title', __('meetings.notify.not_found_title'))->sole();
        $this->assertStringContainsString("/intelligence/leads/{$lead->id}?tab=meetings&meeting={$m->id}", $bell->link_url);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'TRANSCRIPT_NOT_FOUND', 'target_id' => $lead->id]);
    }

    public function test_a_website_booking_becomes_a_requested_lead_meeting_not_an_opportunity(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $gerald = $this->staff('gerald', ['sales']);
        $this->actingAs($owner);
        LeadOwner::setDefault($gerald->id, $owner);
        auth()->logout();

        $booked = app(BookingService::class)->book(['full_name' => 'Femi Test', 'title' => 'COO', 'email' => 'femi@foxtrot.test', 'phone' => '+234 800 000 0002',
            'enterprise_name' => 'Foxtrot Foods (test)', 'focus' => 'Finance and accounting', 'date' => '2026-11-09', 'time' => '10:30']);
        $this->assertNotNull($booked);

        $org = Organization::where('name', 'Foxtrot Foods (test)')->sole();
        $appt = DB::table('appointments')->where('organization_id', $org->id)->sole();
        $m = Meeting::with('attendees')->where('appointment_id', $appt->id)->sole();
        $this->assertSame([null, $org->id, 'requested', 'Free audit: Finance and accounting'], [$m->opportunity_id, $m->organization_id, $m->status, $m->title]);
        $this->assertSame('2026-11-09 09:30:00', $m->starts_at->format('Y-m-d H:i:s'));   // 10:30 WAT
        $this->assertSame([$gerald->id], $m->attendees->where('role', 'staff')->pluck('user_id')->values()->all());
        $this->assertSame(['femi@foxtrot.test'], $m->attendees->where('role', 'client')->pluck('email')->values()->all());
        $this->assertSame(0, Opportunity::count());
        $this->google->assertNothingCreated();

        // Nobody approved it in time: the request closes, and so does its meeting
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00:00', 'UTC'));
        $this->artisan('crm:expire-audit-requests')->assertSuccessful();
        $this->assertSame('cancelled', $m->fresh()->status);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $org->id, 'type' => 'meeting', 'meeting_id' => $m->id,
            'summary' => 'Free audit request closed: no one approved it before its time.']);
    }

    public function test_opening_an_opportunity_from_a_meeting_carries_it_over_with_an_ai_title(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $colleague = $this->staff('colleague', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $starts = CarbonImmutable::parse('2026-11-04 15:00', 'Africa/Lagos');
        $m = LeadMeetings::logPast($lead, $this->data($lead, $sales, ['starts_at' => $starts, 'ends_at' => $starts->addHour(), 'staff' => [$sales->id, $colleague->id],
            'typed_notes' => 'Wants a pilot.', 'transcript' => 'Ada Test: Our stock never matches the tills.']), $sales);

        // The AI reads the transcript and the typed notes for the title
        $this->assertSame('Stock and till reconciliation', LeadMeetings::suggestTitle($m));
        $this->ai->assertRan('opportunity_title', fn (AiRequest $r) => str_contains($r->prompt, 'Our stock never matches the tills') && str_contains($r->prompt, 'Wants a pilot.'));
        $this->assertSame(1, AiRun::where('purpose', 'opportunity_title')->where('subject_type', 'meeting')->where('subject_id', $m->id)->count());
        $this->assertSame('Kilo Retail', LeadMeetings::defaultTitle($m->replicate()->forceFill(['title' => 'Introduction: Kilo Retail'])));

        $o = LeadMeetings::openOpportunity($m, ['title' => 'Stock and till pilot', 'lead_user_id' => $sales->id, 'language' => 'en'], $sales);
        $this->assertSame([$lead->id, 'discovery', 'manual', $sales->id], [$o->organization_id, $o->stage, $o->source, $o->lead_user_id]);
        $this->assertSame($o->id, $m->fresh()->opportunity_id);
        $this->assertSame([$colleague->id], DB::table('opportunity_members')->where('opportunity_id', $o->id)->pluck('user_id')->all(), 'The staff on the meeting join its team');
        $line = DB::table('lead_timeline')->where('organization_id', $lead->id)->where('type', 'opportunity_opened')->sole();
        $this->assertSame([$o->id, $m->id, $this->contactId($lead)], [$line->opportunity_id, $line->meeting_id, $line->client_user_id]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'OPPORTUNITY_OPENED', 'target_id' => $o->id]);
        // Its notes come with it: the opportunity's context reads the typed notes and the AI's notes
        $context = OpportunityContext::for($o->fresh());
        $this->assertStringContainsString('Notes typed by the team: Wants a pilot.', $context);
        $this->assertStringContainsString('They count stock on paper.', $context);

        // Once only, and only from a lead's own meeting
        $this->expectException(LogicException::class);
        LeadMeetings::openOpportunity($m->fresh(), ['title' => 'Again', 'lead_user_id' => $sales->id], $sales);
    }

    public function test_no_ai_title_without_a_transcript_or_a_key(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $starts = CarbonImmutable::parse('2026-11-04 15:00', 'Africa/Lagos');
        $m = LeadMeetings::logPast($lead, $this->data($lead, $sales, ['starts_at' => $starts, 'ends_at' => $starts->addHour()]), $sales);

        $this->assertNull(LeadMeetings::suggestTitle($m), 'Nothing to read');
        $this->ai->unconfigured();
        LeadMeetings::saveTypedNotes($m, 'Wants a pilot.', $sales);
        $this->expectException(AiUnavailable::class);
        LeadMeetings::suggestTitle($m->fresh());
    }

    public function test_the_notes_download_of_a_lead_meeting(): void
    {
        $hq = 'http://' . Hosts::hq();
        $sales = $this->staff('sales', ['sales']);
        $finance = $this->staff('finance', ['finance']);   // Leads access, no transcripts
        $engineer = $this->staff('engineer', ['engineer']);   // no Leads access
        $this->actingAs($sales);
        $lead = $this->lead($sales);
        $starts = CarbonImmutable::parse('2026-11-04 15:00', 'Africa/Lagos');
        $typedOnly = LeadMeetings::logPast($lead, $this->data($lead, $sales, ['title' => 'Call', 'starts_at' => $starts, 'ends_at' => $starts->addMinutes(30), 'typed_notes' => 'Called Ada.']), $sales);
        $m = LeadMeetings::logPast($lead, $this->data($lead, $sales, ['starts_at' => $starts, 'ends_at' => $starts->addHour(), 'location' => 'Their office',
            'typed_notes' => 'Wants a pilot.', 'transcript' => 'Ada Test: Our stock never matches the tills.']), $sales);
        $url = fn ($meeting, $what) => $hq . "/intelligence/leads/{$lead->id}/meetings/{$meeting->id}/{$what}.md";

        $file = $this->actingAs($sales)->get($url($m, 'transcript'))->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')->getContent();
        $this->assertStringContainsString('- **Lead:** Kilo Retail (test)', $file);
        $this->assertStringContainsString('- **Where:** Their office', $file);
        $this->assertStringContainsString("## Notes typed by the team\n\nWants a pilot.", $file);
        $this->assertStringContainsString('They count stock on paper.', $file);
        $this->assertStringContainsString('Our stock never matches the tills.', $file);
        $this->assertStringContainsString('Called Ada.', $this->actingAs($finance)->get($url($typedOnly, 'notes'))->assertOk()->getContent());
        $this->actingAs($finance)->get($url($m, 'notes'))->assertOk();
        $this->actingAs($finance)->get($url($m, 'transcript'))->assertForbidden();
        $this->actingAs($engineer)->get($url($m, 'notes'))->assertForbidden();
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'TRANSCRIPT_DOWNLOADED', 'target_id' => $lead->id]);
        $this->assertStringContainsString('-introduction-kilo-retail-notes.md', MeetingNotesFile::filename($m->transcripts->sole(), false));
    }
}
