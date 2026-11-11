<?php

namespace Tests\Feature;

use App\Support\Monitor\HealthChecks;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** System health → Website enquiries (HealthChecks::enquiriesFrom): the last enquiry, or the day the site went live. */
class EnquiriesHealthCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_recent_enquiry_is_ok_and_old_ones_warn_then_fail(): void
    {
        Carbon::setTestNow('2026-11-11 12:00:00');
        $this->assertSame('ok', HealthChecks::enquiriesFrom('2026-11-05 09:00:00', '2026-10-30')['state']);
        $warn = HealthChecks::enquiriesFrom('2026-10-20 09:00:00', '2026-10-01');
        $this->assertSame('warn', $warn['state']);
        $this->assertStringContainsString('Last enquiry', $warn['detail']);
        $this->assertStringContainsString('Test the contact and booking forms', (string) $warn['fix']);
        $this->assertSame('bad', HealthChecks::enquiriesFrom('2026-10-01 09:00:00', '2026-09-01')['state']);
    }

    /** 11 Nov 2026: every enquiry so far had been a test and was cleared, 12 days after the site went live. */
    public function test_no_enquiry_yet_counts_from_the_day_the_site_went_live(): void
    {
        Carbon::setTestNow('2026-11-11 12:00:00');
        $fresh = HealthChecks::enquiriesFrom(null, '2026-10-30');
        $this->assertSame('ok', $fresh['state']);
        $this->assertNull($fresh['fix']);
        $this->assertStringContainsString('No enquiry yet', $fresh['detail']);
        $this->assertStringContainsString('the site went live 1 week ago', $fresh['detail']);

        Carbon::setTestNow('2026-11-14 12:00:00');
        $quiet = HealthChecks::enquiriesFrom(null, '2026-10-30');
        $this->assertSame('warn', $quiet['state']);
        $this->assertStringContainsString('Test the contact and booking forms', (string) $quiet['fix']);

        Carbon::setTestNow('2026-12-05 12:00:00');
        $this->assertSame('bad', HealthChecks::enquiriesFrom(null, '2026-10-30')['state']);
    }

    public function test_without_a_live_date_an_empty_history_still_warns(): void
    {
        $check = HealthChecks::enquiriesFrom(null, null);
        $this->assertSame('warn', $check['state']);
        $this->assertSame('No enquiry has ever been recorded.', $check['detail']);
    }
}
