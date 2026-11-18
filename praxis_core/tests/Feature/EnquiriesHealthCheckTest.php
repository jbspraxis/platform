<?php

namespace Tests\Feature;

use App\Support\Hosts;
use App\Support\Monitor\FormsSelfTest;
use App\Support\Monitor\HealthChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * System health → Website forms (HealthChecks::enquiriesFrom with FormsSelfTest). Owner, 18 Nov 2026: no enquiry
 * for a while is information; the check is amber or red only when the forms' daily self-test fails or stops.
 */
class EnquiriesHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function passed(string $at): array
    {
        return ['at' => $at, 'ok' => true, 'failed' => []];
    }

    public function test_no_enquiry_for_weeks_is_information_while_the_forms_pass_their_test(): void
    {
        Carbon::setTestNow('2026-11-18 12:00:00');
        $check = HealthChecks::enquiriesFrom(null, '2026-10-30', $this->passed('2026-11-18 07:00:00'));
        $this->assertSame('ok', $check['state']);
        $this->assertSame('Website forms', $check['label']);
        $this->assertNull($check['fix']);
        $this->assertStringContainsString('passed their daily test 5 hours ago', $check['detail']);
        $this->assertStringContainsString('No enquiry yet (booking, contact form or cost report) since the site went live 2 weeks ago', $check['detail']);

        // Two months without one: still only information
        Carbon::setTestNow('2027-01-10 12:00:00');
        $later = HealthChecks::enquiriesFrom('2026-11-02 09:00:00', '2026-10-30', $this->passed('2027-01-10 07:00:00'));
        $this->assertSame('ok', $later['state']);
        $this->assertStringContainsString('Last enquiry', $later['detail']);
    }

    public function test_a_failed_forms_test_is_red_and_names_the_form(): void
    {
        Carbon::setTestNow('2026-11-18 12:00:00');
        $check = HealthChecks::enquiriesFrom(null, '2026-10-30', ['at' => '2026-11-18 11:55:00', 'ok' => false, 'failed' => ['booking form' => 'answered HTTP 500 instead of 422']]);
        $this->assertSame('bad', $check['state']);
        $this->assertStringContainsString('booking form answered HTTP 500 instead of 422', $check['detail']);
        $this->assertStringContainsString('Send the contact form on jbspraxis.com yourself', (string) $check['fix']);
    }

    public function test_a_test_that_stopped_running_is_amber_and_one_never_run_is_waiting(): void
    {
        Carbon::setTestNow('2026-11-18 12:00:00');
        $this->assertSame('warn', HealthChecks::enquiriesFrom(null, '2026-10-30', $this->passed('2026-11-15 07:00:00'))['state']);
        $this->assertSame('unknown', HealthChecks::enquiriesFrom(null, '2026-10-30', null)['state']);
        $this->assertStringContainsString('No enquiry has been recorded yet', HealthChecks::enquiriesFrom(null, null, null)['detail']);
    }

    /** The self-test's own requests, replayed against HQ's site API: signature accepted, form refused as empty, nothing stored. */
    public function test_the_self_test_sends_signed_empty_forms_that_the_api_accepts_and_refuses_as_invalid(): void
    {
        Mail::fake();
        config(['site_api.keys' => ['monitor-test' => str_repeat('k', 48)], 'services.monitor.forms_base' => 'https://hq.example.test']);
        $sent = [];
        Http::fake(function (ClientRequest $request) use (&$sent) {
            $sent[] = $request;

            return Http::response(['error' => ['code' => 'invalid']], 422);
        });

        $result = FormsSelfTest::run();
        $this->assertTrue($result['ok']);
        $this->assertSame(['/api/site/v1/enquiries', '/api/site/v1/bookings', '/api/site/v1/calculator/assessments'],
            array_map(fn ($r) => parse_url($r->url(), PHP_URL_PATH), $sent));

        foreach ($sent as $request) {
            $server = ['REMOTE_ADDR' => '198.51.100.10', 'CONTENT_TYPE' => 'application/json'];
            foreach ($request->headers() as $name => $values) {
                if (str_starts_with(strtolower($name), 'x-site-') || strtolower($name) === 'accept') {
                    $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $values[0];
                }
            }
            $response = $this->call('POST', 'http://' . Hosts::hq() . parse_url($request->url(), PHP_URL_PATH), [], [], [], $server, $request->body());
            $response->assertStatus(422);
            $this->assertSame('invalid', $response->json('error.code'));
        }

        $this->assertSame(0, DB::table('crm_notes')->count());
        $this->assertSame(0, DB::table('appointments')->count());
        $this->assertSame(0, DB::table('copq_assessments')->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_a_form_that_answers_otherwise_fails_and_the_test_reruns_hourly_until_it_passes(): void
    {
        config(['site_api.keys' => ['monitor-test' => str_repeat('k', 48)], 'services.monitor.forms_base' => 'https://hq.example.test']);
        $broken = true; // the booking endpoint answers 500 until it is "fixed" below
        Http::fake(function (ClientRequest $r) use (&$broken) {
            return $broken && str_ends_with($r->url(), '/bookings') ? Http::response('', 500) : Http::response([], 422);
        });

        Carbon::setTestNow('2026-11-18 10:00:00');
        $first = FormsSelfTest::runIfDue();
        $this->assertFalse($first['ok']);
        $this->assertSame(['booking form' => 'answered HTTP 500 instead of 422'], $first['failed']);
        $this->assertFalse(FormsSelfTest::last()['ok']);

        Carbon::setTestNow('2026-11-18 10:30:00');
        $this->assertNull(FormsSelfTest::runIfDue(), 'not again within the hour');

        $broken = false;
        Carbon::setTestNow('2026-11-18 11:01:00');
        $this->assertTrue(FormsSelfTest::runIfDue()['ok']);

        Carbon::setTestNow('2026-11-19 10:00:00');
        $this->assertNull(FormsSelfTest::runIfDue(), 'once passed, once a day');
        Carbon::setTestNow('2026-11-19 11:02:00');
        $this->assertNotNull(FormsSelfTest::runIfDue());
    }

    public function test_without_a_site_key_every_form_fails(): void
    {
        config(['site_api.keys' => []]);
        Http::fake();
        $result = FormsSelfTest::run();
        $this->assertFalse($result['ok']);
        $this->assertSame('no site key in .env', $result['failed']['contact form']);
        Http::assertNothingSent();
    }
}
