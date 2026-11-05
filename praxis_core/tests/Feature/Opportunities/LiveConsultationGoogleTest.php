<?php

namespace Tests\Feature\Opportunities;

use App\Livewire\Hq\Intelligence\LiveConsultation;
use App\Mail\MeetingApprovedMail;
use App\Mail\MeetingInvitationMail;
use App\Models\Meeting;
use App\Models\Opportunity;
use App\Support\Google\MeetingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * Live consultation's approval (docs/OPPORTUNITIES.md section 4, row 2): with Google connected, approving a website
 * booking creates the meeting through Google instead of a pasted link. Since Leads PR 2 (docs/LEADS.md section 9) the
 * booking is the lead's meeting, not an opportunity's: no opportunity is opened or reused; one is opened from the
 * meeting on the lead's page when there is work to discuss.
 */
class LiveConsultationGoogleTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
    }

    public function test_approving_schedules_the_bookings_lead_meeting_through_google_and_emails_the_meet_link(): void
    {
        $google = FakeGoogle::install();
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        [$org, $booker, $appt] = $this->booking('FR');

        Livewire::test(LiveConsultation::class)
            ->assertSee('Approve and invite')->assertDontSee('Paste the meeting link')
            ->call('approveMeeting', $appt);

        $this->assertSame(0, Opportunity::count(), 'A booking is a lead meeting, not an opportunity');
        $m = Meeting::with('attendees')->sole();
        $this->assertSame([null, $org, $appt, 'scheduled', 'Free audit: Finance and accounting'], [$m->opportunity_id, $m->organization_id, $m->appointment_id, $m->status, $m->title]);
        $this->assertSame('2026-11-06 09:30:00', $m->starts_at->format('Y-m-d H:i:s'));   // 10:30 WAT
        $this->assertSame('2026-11-06 10:30:00', $m->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame($sales->id, $m->attendees->firstWhere('role', 'staff')->user_id);
        $this->assertSame($booker, $m->attendees->firstWhere('role', 'client')->client_user_id);
        $google->assertMeetingCreated(fn (MeetingRequest $r) => str_contains((string) $r->description, 'JBS Praxis enregistre une transcription'));

        $appointment = DB::table('appointments')->where('id', $appt)->first();
        $this->assertSame(['Scheduled', $m->meet_link], [$appointment->status, $appointment->meeting_link]);
        Mail::assertSent(MeetingApprovedMail::class, fn ($mail) => $mail->hasTo('ada@acme.test') && $mail->meetingLink === $m->meet_link && $mail->locale === 'fr');
        Mail::assertNotSent(MeetingInvitationMail::class);   // the free audit email is the branded one here
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'MEETING_CONFIRMED', 'target_table' => 'organizations', 'target_id' => $org]);
        $this->assertDatabaseHas('lead_timeline', ['organization_id' => $org, 'type' => 'meeting', 'meeting_id' => $m->id, 'summary' => 'Free audit scheduled for Fri 6 Nov, 10:30 (WAT) on Google Meet.']);

        $html = (new MeetingApprovedMail($appointment, 'Acme', $m->meet_link, 'Ada'))->locale('en')->renderParts()['html'];
        $this->assertStringContainsString('We record a transcript of this meeting with Google Meet', $html);
    }

    public function test_an_open_opportunity_is_left_alone_and_a_google_failure_leaves_the_request_waiting(): void
    {
        $google = FakeGoogle::install();
        $this->actingAs($this->staff('sales', ['sales']));
        [$org, , $appt] = $this->booking();
        $open = $this->opportunityFor($org, ['title' => 'Existing work']);

        $google->fail('quota', 'createMeeting');
        Livewire::test(LiveConsultation::class)->call('approveMeeting', $appt)->assertDispatched('praxis-toast', $this->toast('The free audit wasn’t approved'));
        $this->assertSame('Requested', DB::table('appointments')->where('id', $appt)->value('status'));
        $this->assertSame(['requested', null], [Meeting::sole()->status, Meeting::sole()->google_event_id]);   // the booking's lead meeting waits too
        Mail::assertNothingSent();

        FakeGoogle::install();
        Livewire::test(LiveConsultation::class)->call('approveMeeting', $appt)->assertDispatched('praxis-toast', $this->toast('Free audit approved'));
        $this->assertSame([null, 'scheduled'], [Meeting::sole()->opportunity_id, Meeting::sole()->status]);
        $this->assertSame(0, Meeting::where('opportunity_id', $open->id)->count());
        $this->assertSame(1, Opportunity::count());
    }

    public function test_without_google_the_pasted_link_still_works_and_declining_closes_the_meeting(): void
    {
        FakeGoogle::install()->disconnected();
        $this->actingAs($this->staff('sales', ['sales']));
        [$org, , $appt] = $this->booking();

        Livewire::test(LiveConsultation::class)
            ->assertSee('Paste the meeting link')
            ->call('approveMeeting', $appt, '')->assertDispatched('praxis-toast', $this->toast('Add the meeting link'))
            ->call('approveMeeting', $appt, 'meet.google.com/abc-defg-hij')->assertDispatched('praxis-toast', $this->toast('Free audit approved'));
        $this->assertSame('https://meet.google.com/abc-defg-hij', DB::table('appointments')->where('id', $appt)->value('meeting_link'));
        $m = Meeting::sole();
        $this->assertSame([null, 'scheduled', 'https://meet.google.com/abc-defg-hij', 'abc-defg-hij'], [$m->opportunity_id, $m->status, $m->meet_link, $m->meet_code]);

        // Another booking, declined: its meeting is cancelled and the timeline keeps the team's reason
        [, , $other] = $this->booking('EN', 'Beta Ltd', 'bola@beta.test');
        \App\Support\Leads\LeadMeetings::forAppointment(DB::table('appointments')->where('id', $other)->first());   // as BookingService makes it
        Livewire::test(LiveConsultation::class)->call('declineRequest', $other, 'A test booking', false)->assertSee('Request declined');
        $declined = Meeting::where('appointment_id', $other)->sole();
        $this->assertSame('cancelled', $declined->status);
        $this->assertDatabaseHas('lead_timeline', ['type' => 'meeting', 'meeting_id' => $declined->id, 'summary' => 'Free audit request declined in Free audits.', 'body' => 'A test booking']);
    }

    /** A free audit booked on the website: organisation, the contact who booked, the Requested appointment (Lagos wall-clock) */
    private function booking(string $language = 'EN', string $name = 'Acme Ltd', string $email = 'ada@acme.test'): array
    {
        $org = $this->organisation(['name' => $name, 'preferred_language' => $language]);
        $booker = $this->clientContact($org, ['first_name' => 'Ada', 'email' => $email]);
        $appt = (string) Str::uuid();
        $this->insertRow('appointments', ['id' => $appt, 'organization_id' => $org, 'title' => 'Free audit: Finance and accounting', 'status' => 'Requested',
            'start_time' => '2026-11-06 10:30:00', 'end_time' => '2026-11-06 11:30:00', 'created_by' => $booker, 'meeting_link' => null, 'created_at' => now()]);

        return [$org, $booker, $appt];
    }
}
