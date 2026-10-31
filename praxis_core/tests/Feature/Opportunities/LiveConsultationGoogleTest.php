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
 * booking creates the meeting through Google instead of a pasted link, under the organisation's opportunity.
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

    public function test_approving_creates_the_google_meeting_under_a_new_opportunity_and_emails_the_meet_link(): void
    {
        $google = FakeGoogle::install();
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        [$org, $booker, $appt] = $this->booking('FR');

        Livewire::test(LiveConsultation::class)
            ->assertSee('Approve and invite')->assertDontSee('Paste the meeting link')
            ->call('approveMeeting', $appt);

        $o = Opportunity::where('organization_id', $org)->sole();
        $this->assertSame(['Finance and accounting', 'website_booking', 'discovery', $sales->id, 'fr'], [$o->title, $o->source, $o->stage, $o->lead_user_id, $o->language]);
        $m = Meeting::with('attendees')->sole();
        $this->assertSame([$o->id, $appt, 'scheduled', 'Free audit: Finance and accounting'], [$m->opportunity_id, $m->appointment_id, $m->status, $m->title]);
        $this->assertSame('2026-11-06 09:30:00', $m->starts_at->format('Y-m-d H:i:s'));   // 10:30 WAT
        $this->assertSame('2026-11-06 10:30:00', $m->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame($sales->id, $m->attendees->firstWhere('role', 'staff')->user_id);
        $this->assertSame($booker, $m->attendees->firstWhere('role', 'client')->client_user_id);
        $google->assertMeetingCreated(fn (MeetingRequest $r) => str_contains((string) $r->description, 'JBS Praxis enregistre une transcription'));

        $appointment = DB::table('appointments')->where('id', $appt)->first();
        $this->assertSame(['Scheduled', $m->meet_link], [$appointment->status, $appointment->meeting_link]);
        Mail::assertSent(MeetingApprovedMail::class, fn ($mail) => $mail->hasTo('ada@acme.test') && $mail->meetingLink === $m->meet_link && $mail->locale === 'fr');
        Mail::assertNotSent(MeetingInvitationMail::class);   // the free audit email is the branded one here
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'OPPORTUNITY_OPENED', 'target_id' => $o->id]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'MEETING_SCHEDULED', 'target_id' => $o->id]);

        $html = (new MeetingApprovedMail($appointment, 'Acme', $m->meet_link, 'Ada'))->locale('en')->renderParts()['html'];
        $this->assertStringContainsString('We record a transcript of this meeting with Google Meet', $html);
    }

    public function test_an_open_opportunity_is_reused_and_a_google_failure_leaves_the_request_waiting(): void
    {
        $google = FakeGoogle::install();
        $this->actingAs($this->staff('sales', ['sales']));
        [$org, , $appt] = $this->booking();
        $open = $this->opportunityFor($org, ['title' => 'Existing work']);

        $google->fail('quota', 'createMeeting');
        Livewire::test(LiveConsultation::class)->call('approveMeeting', $appt)->assertSee('The free audit wasn’t approved');
        $this->assertSame('Requested', DB::table('appointments')->where('id', $appt)->value('status'));
        $this->assertSame(0, Meeting::count());
        Mail::assertNothingSent();

        $google = FakeGoogle::install();
        Livewire::test(LiveConsultation::class)->call('approveMeeting', $appt)->assertSee('Free audit approved');
        $this->assertSame($open->id, Meeting::sole()->opportunity_id);
        $this->assertSame(1, Opportunity::count());
    }

    public function test_without_google_the_pasted_link_still_works(): void
    {
        FakeGoogle::install()->disconnected();
        $this->actingAs($this->staff('sales', ['sales']));
        [, , $appt] = $this->booking();

        Livewire::test(LiveConsultation::class)
            ->assertSee('Paste the meeting link')
            ->call('approveMeeting', $appt, '')->assertSee('Add the meeting link')
            ->call('approveMeeting', $appt, 'meet.google.com/abc-defg-hij')->assertSee('Free audit approved');
        $this->assertSame('https://meet.google.com/abc-defg-hij', DB::table('appointments')->where('id', $appt)->value('meeting_link'));
        $this->assertSame(0, Meeting::count());
    }

    /** A free audit booked on the website: organisation, the contact who booked, the Requested appointment (Lagos wall-clock) */
    private function booking(string $language = 'EN'): array
    {
        $org = $this->organisation(['name' => 'Acme Ltd', 'preferred_language' => $language]);
        $booker = $this->clientContact($org, ['first_name' => 'Ada', 'email' => 'ada@acme.test']);
        $appt = (string) Str::uuid();
        $this->insertRow('appointments', ['id' => $appt, 'organization_id' => $org, 'title' => 'Free audit: Finance and accounting', 'status' => 'Requested',
            'start_time' => '2026-11-06 10:30:00', 'end_time' => '2026-11-06 11:30:00', 'created_by' => $booker, 'meeting_link' => null, 'created_at' => now()]);

        return [$org, $booker, $appt];
    }
}
