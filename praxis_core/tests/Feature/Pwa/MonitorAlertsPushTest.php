<?php

namespace Tests\Feature\Pwa;

use App\Mail\SystemAlert;
use App\Support\Monitor\ErrorMonitor;
use App\Support\Monitor\HealthChecks;
use App\Support\Monitor\MonitorAlerts;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** System Health alerts also land in the bell (and so on phones) of staff who can open System health. */
class MonitorAlertsPushTest extends TestCase
{
    use PushTestKit;
    use RefreshDatabase;

    private $owner;
    private $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CoreSeeder::class, AccessSeeder::class]);
        Mail::fake();
        Http::preventStrayRequests();
        $this->owner = $this->staff(['first_name' => 'Owner'], 'owner');
        $this->engineer = $this->staff(['first_name' => 'Engineer'], 'engineer');
        $this->staff(['first_name' => 'Former', 'status' => 'Offboarded'], 'owner');
    }

    private function errorGroup(string $message): int
    {
        return DB::table('monitor_error_groups')->insertGetId([
            'fingerprint' => sha1($message), 'source' => 'exception', 'level' => 'error', 'exception_class' => 'RuntimeException',
            'message' => $message, 'occurrences' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(), 'status' => 'new',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function bell(): \Illuminate\Support\Collection
    {
        return DB::table('notifications')->orderBy('created_at')->get();
    }

    public function test_new_errors_reach_the_bell_even_without_an_alert_email_address(): void
    {
        config(['services.monitor.alert_email' => null]);
        $groups = [$this->errorGroup('Payment provider timed out'), $this->errorGroup('Undefined index: total')];

        $this->assertSame(2, MonitorAlerts::newErrors());

        $bell = $this->bell();
        $this->assertCount(1, $bell, 'only active staff who can open System health');
        $this->assertSame($this->owner->id, $bell[0]->user_id);
        $this->assertSame('New errors in HQ', $bell[0]->title);
        $this->assertSame('HQ recorded 2 new or returning error types. Review them in System health.', $bell[0]->message);
        $this->assertSame('/system/health', $bell[0]->link_url);
        $this->assertSame('System', $bell[0]->type);
        $this->assertSame(0, (int) $bell[0]->is_read);
        Mail::assertNothingSent();
        $this->assertSame(2, DB::table('monitor_error_groups')->whereIn('id', $groups)->whereNotNull('alerted_at')->count());

        // Alerted once: the next run is quiet
        $this->assertSame(0, MonitorAlerts::newErrors());
        $this->assertCount(1, $this->bell());
    }

    public function test_with_an_alert_email_address_both_go_out(): void
    {
        config(['services.monitor.alert_email' => 'alerts@jbspraxis.test']);
        $this->errorGroup('Payment provider timed out');

        $this->assertSame(1, MonitorAlerts::newErrors());

        Mail::assertSent(SystemAlert::class, 1);
        $this->assertSame(['HQ recorded 1 new or returning error type. Review it in System health.'], $this->bell()->pluck('message')->all());
    }

    public function test_the_hourly_limit_holds_back_the_bell_too(): void
    {
        config(['services.monitor.alert_email' => null]);
        ErrorMonitor::setState('alerts_hour:' . now()->format('YmdH'), 6);
        $this->errorGroup('Payment provider timed out');

        $this->assertSame(0, MonitorAlerts::newErrors());

        $this->assertCount(0, $this->bell());
        $this->assertSame(1, DB::table('monitor_error_groups')->whereNull('alerted_at')->count(), 'left for a later run');
    }

    public function test_an_urgent_red_check_reaches_the_bell_at_most_every_6_hours(): void
    {
        config(['services.monitor.alert_email' => null]);
        // In tests APP_ENV is not production, so "Live settings" is red; keep the scheduler green
        ErrorMonitor::setState('heartbeat', now()->toDateTimeString());
        $urgent = collect(HealthChecks::all())->where('state', 'bad')->whereIn('key', ['scheduler', 'email', 'ai', 'config'])->values();
        $config = $urgent->firstWhere('key', 'config');
        $this->assertNotNull($config);

        $this->assertSame($urgent->count(), MonitorAlerts::health());

        $bell = $this->bell()->where('user_id', $this->owner->id)->keyBy('title');
        $this->assertCount($urgent->count(), $bell);
        $this->assertSame($config['detail'] . ' See how to fix it in System health.', $bell['Live settings need attention']->message);
        $this->assertSame('/system/health', $bell['Live settings need attention']->link_url);
        $this->assertSame(0, $this->bell()->where('user_id', $this->engineer->id)->count());

        // Still red 5 minutes later: no second notification
        Carbon::setTestNow(now()->addMinutes(5));
        $this->assertSame(0, MonitorAlerts::health());
        $this->assertCount($urgent->count(), $this->bell());

        // 6 hours later it is said again
        Carbon::setTestNow(now()->addHours(6));
        ErrorMonitor::setState('heartbeat', now()->toDateTimeString());
        $this->assertSame($urgent->count(), MonitorAlerts::health());
        $this->assertCount($urgent->count() * 2, $this->bell());
        Carbon::setTestNow();
    }

    public function test_a_check_label_in_the_singular_needs_attention(): void
    {
        config(['services.monitor.alert_email' => null]);
        // No heartbeat at all: the scheduler check is red
        DB::table('monitor_state')->where('key', 'heartbeat')->delete();

        MonitorAlerts::health();

        $this->assertContains('Scheduler (cron) needs attention', $this->bell()->pluck('title')->all());
    }

    public function test_the_daily_digest_never_writes_to_the_bell(): void
    {
        config(['services.monitor.alert_email' => 'alerts@jbspraxis.test']);
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:05', 'Africa/Lagos'));

        $this->assertTrue(MonitorAlerts::digest());

        Mail::assertSent(SystemAlert::class, 1);
        $this->assertCount(0, $this->bell());
        Carbon::setTestNow();
    }

    public function test_monitoring_never_throws_when_the_bell_cannot_be_written(): void
    {
        config(['services.monitor.alert_email' => 'alerts@jbspraxis.test']);
        Log::spy();
        $this->errorGroup('Payment provider timed out');
        DB::listen(function ($query) {
            if (preg_match('/^insert into [`"]?notifications/i', $query->sql)) {
                throw new \RuntimeException('The bell is down');
            }
        });

        $this->assertSame(1, MonitorAlerts::newErrors(), 'the email still counts');
        $this->assertSame(1, DB::table('monitor_error_groups')->whereNotNull('alerted_at')->count());
        Mail::assertSent(SystemAlert::class, 1);
        Log::shouldHaveReceived('warning')->with('Monitor: bell alert failed', ['message' => 'The bell is down'])->once();
    }
}
