<?php

namespace Tests\Feature;

use App\Livewire\Hq\Intelligence\LeadsRegister;
use App\Livewire\Hq\Intelligence\LiveConsultation;
use App\Mail\MeetingApprovedMail;
use App\Services\Site\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HQ → Leads → Book free audit: the date and time are entered in Lagos time (WAT) and stored as Lagos wall-clock
 * time, the same way as website bookings (BookingService), so the Consultation hub, the website's free times and
 * the client's confirmation email all show the time the staff member chose. Runs on a throwaway MySQL database
 * (see UsesThrowawayDatabase).
 */
class AdHocConsultationTimeTest extends TestCase
{
    use UsesThrowawayDatabase;

    private string $org;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 4 November 2026, 08:52 UTC = 09:52 in Lagos
        Carbon::setTestNow(Carbon::parse('2026-11-04 08:52:00', 'UTC'));
        $this->org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $this->org, 'name' => 'Acme Ltd (test)', 'status' => 'Lead', 'preferred_language' => 'EN']);
        $this->insertRow('client_users', ['id' => (string) Str::uuid(), 'organization_id' => $this->org, 'first_name' => 'Ada', 'last_name' => 'Test', 'email' => 'ada.' . Str::random(6) . '@example.test', 'deleted_at' => null, 'is_primary_client' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_dialog_suggests_15_minutes_from_now_in_lagos_time(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));

        Livewire::test(LeadsRegister::class)->call('requestConsultation', $this->org)
            ->assertSet('showBookingModal', true)
            ->assertSet('bookingDate', '2026-11-04')
            ->assertSet('bookingTime', '10:10') // 09:52 + 15 minutes, on the next 5 minutes
            ->assertSee('Book a free audit')->assertSee('Time (WAT)');

        $this->assertSame(0, DB::table('appointments')->count(), 'nothing is booked until the time is confirmed');
    }

    public function test_the_time_entered_is_stored_as_lagos_time_like_website_bookings(): void
    {
        Mail::fake();
        $this->actingAs($this->staff('sales', ['sales']));

        Livewire::test(LeadsRegister::class)->call('requestConsultation', $this->org)
            ->set('bookingTime', '10:30')
            ->call('bookConsultation')
            ->assertHasNoErrors()
            ->assertRedirect(route('hq.crm.consult'));

        $appt = DB::table('appointments')->where('organization_id', $this->org)->first();
        $this->assertSame('2026-11-04 10:30:00', (string) $appt->start_time);
        $this->assertSame('2026-11-04 11:30:00', (string) $appt->end_time);
        $this->assertSame('Requested', $appt->status);
        $this->assertSame('Free audit booked for Acme Ltd (test) on Wed 4 Nov, 10:30 WAT. Approve it with the meeting link to email the client.', session('success'));

        // The website no longer offers 10:30 that day: both kinds of booking hold the same clock
        $this->assertNotContains('10:30', (new BookingService)->freeSlots('2026-11-04'));

        // The hub and the client's confirmation show 10:30 WAT
        Livewire::test(LiveConsultation::class)->assertSee('Wed 4 Nov 2026, 10:30 WAT')
            ->call('approveMeeting', $appt->id, 'https://meet.google.com/abc-defg-hij');
        Mail::assertSent(MeetingApprovedMail::class, function (MeetingApprovedMail $mail) {
            $this->assertStringContainsString('10:30 to 11:30 (WAT)', $mail->render());

            return true;
        });
    }

    public function test_a_time_that_has_passed_or_is_taken_is_refused(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));

        // 09:30 in Lagos has passed (it is 09:52 there), though 09:30 is still ahead on the server’s UTC clock
        Livewire::test(LeadsRegister::class)->call('requestConsultation', $this->org)
            ->set('bookingTime', '09:30')->call('bookConsultation')
            ->assertHasErrors('bookingTime')->assertSee('That time has passed. Choose a time that is still ahead (Lagos time).');

        $this->insertRow('appointments', [
            'id' => (string) Str::uuid(), 'organization_id' => $this->org, 'title' => 'Free audit and demo', 'status' => 'Scheduled',
            'start_time' => '2026-11-04 13:00:00', 'end_time' => '2026-11-04 14:00:00',
        ]);
        Livewire::test(LeadsRegister::class)->call('requestConsultation', $this->org)
            ->set('bookingTime', '13:00')->call('bookConsultation')
            ->assertHasErrors('bookingTime')->assertSee('Another free audit is booked at that time. Choose another time.');

        Livewire::test(LeadsRegister::class)->call('requestConsultation', $this->org)
            ->set('bookingTime', '9am')->call('bookConsultation')->assertHasErrors('bookingTime');

        $this->assertSame(1, DB::table('appointments')->count());
    }

    public function test_only_staff_who_can_add_leads_can_book(): void
    {
        $this->actingAs($this->staff('engineer', ['engineer']));

        Livewire::test(LeadsRegister::class)->call('requestConsultation', $this->org)->assertForbidden();
        $this->assertSame(0, DB::table('appointments')->count());
    }
}
