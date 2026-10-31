<?php

namespace Tests\Feature;

use App\Livewire\Hq\Intelligence\LiveConsultation;
use App\Mail\MeetingApprovedMail;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Free audit requests still waiting for approval once their start time (Lagos time) has passed close by
 * themselves every hour: Cancelled, a FREE_AUDIT_EXPIRED audit log line, and no email to the client.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class FreeAuditExpiryTest extends TestCase
{
    use UsesThrowawayDatabase;

    private string $org;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 4 November 2026, 09:20 UTC = 10:20 in Lagos
        Carbon::setTestNow(Carbon::parse('2026-11-04 09:20:00', 'UTC'));
        $this->org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $this->org, 'name' => 'Acme Ltd (test)', 'status' => 'Lead', 'preferred_language' => 'EN']);
        $this->insertRow('client_users', ['id' => (string) Str::uuid(), 'organization_id' => $this->org, 'first_name' => 'Ada', 'last_name' => 'Test', 'email' => 'ada.' . Str::random(6) . '@example.test', 'deleted_at' => null]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function appointment(string $status, string $start): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('appointments', [
            'id' => $id, 'organization_id' => $this->org, 'title' => 'Free audit and demo', 'status' => $status,
            'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function test_requests_past_their_start_time_close_without_emailing_the_client(): void
    {
        Mail::fake();
        $passed = $this->appointment('Requested', '2026-11-04 09:50:00');      // passed in Lagos, though still ahead on a UTC clock
        $yesterday = $this->appointment('Requested', '2026-11-03 16:00:00');
        $ahead = $this->appointment('Requested', '2026-11-04 10:30:00');       // still ahead in Lagos
        $approved = $this->appointment('Scheduled', '2026-11-04 09:00:00');    // approved: never touched

        $this->artisan('crm:expire-audit-requests')->expectsOutput('expire-audit-requests: 2 request(s) closed')->assertSuccessful();

        $status = fn ($id) => DB::table('appointments')->where('id', $id)->value('status');
        $this->assertSame('Cancelled', $status($passed));
        $this->assertSame('Cancelled', $status($yesterday));
        $this->assertSame('Requested', $status($ahead));
        $this->assertSame('Scheduled', $status($approved));

        $log = DB::table('system_audit_logs')->where('action', 'FREE_AUDIT_EXPIRED')->where('target_id', $passed)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertSame('appointments', $log->target_table);
        $this->assertSame('Closed the free audit request from Acme Ltd (test) for 2026-11-04 09:50:00 WAT: no one approved it before its start time. The client wasn’t emailed.', $log->description);
        $this->assertEquals(['status' => 'Cancelled', 'reason' => 'expired', 'client_emailed' => false], json_decode($log->new_values, true));
        $this->assertSame(2, DB::table('system_audit_logs')->where('action', 'FREE_AUDIT_EXPIRED')->count());

        Mail::assertNothingSent();

        // A second run finds nothing more to close and logs nothing twice
        $this->artisan('crm:expire-audit-requests')->expectsOutput('expire-audit-requests: 0 request(s) closed')->assertSuccessful();
        $this->assertSame(2, DB::table('system_audit_logs')->where('action', 'FREE_AUDIT_EXPIRED')->count());
    }

    public function test_a_closed_request_cannot_be_approved_from_a_stale_hub_screen(): void
    {
        Mail::fake();
        $this->actingAs($this->staff('sales', ['sales']));
        $passed = $this->appointment('Requested', '2026-11-04 09:50:00');

        // The hub was opened while the request still waited; the hourly run then closes it
        $hub = Livewire::test(LiveConsultation::class);
        $this->artisan('crm:expire-audit-requests')->assertSuccessful();

        $hub->call('approveMeeting', $passed, 'https://meet.google.com/abc-defg-hij')
            ->assertSee('This request was already approved, declined or closed because its time passed, so nothing changed. The list is up to date now.');

        $this->assertSame('Cancelled', DB::table('appointments')->where('id', $passed)->value('status'));
        $this->assertNull(DB::table('appointments')->where('id', $passed)->value('meeting_link'));
        Mail::assertNotSent(MeetingApprovedMail::class);
    }

    public function test_the_closing_runs_every_hour(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'crm:expire-audit-requests'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
