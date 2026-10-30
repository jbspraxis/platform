<?php

namespace Tests\Feature\Pwa;

use App\Support\Monitor\HealthChecks;
use App\Support\Pwa\PushDispatcher;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The "Push notifications" check on HQ → System health. */
class PushHealthCheckTest extends TestCase
{
    use PushTestKit;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CoreSeeder::class, AccessSeeder::class]);
        PushDispatcher::flush();
        Http::preventStrayRequests();
    }

    private function check(): array
    {
        return collect(HealthChecks::all())->firstWhere('key', 'push');
    }

    private function deliveries(string $status, int $count): void
    {
        foreach (range(1, $count) as $i) {
            DB::table('push_deliveries')->insert([
                'notification_id' => (string) Str::uuid(), 'user_id' => null, 'status' => $status,
                'devices' => $status === 'failed' ? 0 : 1, 'sent_at' => $status === 'failed' ? null : now()->subMinutes(5), 'created_at' => now()->subHour(),
            ]);
        }
    }

    public function test_no_devices_yet_is_fine(): void
    {
        $this->assertSame([
            'key' => 'push', 'label' => 'Push notifications', 'state' => 'ok',
            'detail' => 'No device has notifications turned on yet. Each person turns them on from the bell menu in HQ.', 'fix' => null,
        ], $this->check());
    }

    public function test_it_counts_devices_and_people_and_says_when_it_last_sent(): void
    {
        $one = $this->staff();
        $two = $this->staff();
        $this->device($one, 'https://fcm.googleapis.com/fcm/send/a', ['last_sent_at' => now()->subMinutes(5)]);
        $this->device($one, 'https://fcm.googleapis.com/fcm/send/b');
        $this->device($two, 'https://web.push.apple.com/c');

        $check = $this->check();

        $this->assertSame('ok', $check['state']);
        $this->assertSame('3 devices for 2 people; last sent 5 minutes ago.', $check['detail']);

        DB::table('push_subscriptions')->update(['last_sent_at' => null]);
        DB::table('push_subscriptions')->where('user_id', $two->id)->delete();
        $this->assertSame('2 devices for 1 person; nothing sent yet.', $this->check()['detail']);
    }

    public function test_many_recent_failures_are_a_warning_never_red(): void
    {
        $this->device($this->staff(), 'https://fcm.googleapis.com/fcm/send/a');
        $this->deliveries('failed', 5);
        $this->deliveries('sent', 4);

        $check = $this->check();

        $this->assertSame('warn', $check['state']);
        $this->assertStringContainsString('In the last 24 hours 5 notifications reached no device and 4 did.', $check['detail']);
        $this->assertStringContainsString('test notification', $check['fix']);

        // A few failures among many deliveries are normal life (phones switched off, apps removed)
        $this->deliveries('sent', 2);
        $this->assertSame('ok', $this->check()['state']);
    }

    public function test_before_the_migration_it_says_what_to_do(): void
    {
        // Every table "missing": the other checks report unknown, this one says what to do
        DB::connection()->setTablePrefix('missing_');
        try {
            $check = $this->check();
        } finally {
            DB::connection()->setTablePrefix('');
        }

        $this->assertSame('warn', $check['state']);
        $this->assertSame('The HQ app tables aren’t in the database yet, so nothing is pushed.', $check['detail']);
        $this->assertStringContainsString('migration', $check['fix']);
    }

    public function test_the_check_shows_on_system_health(): void
    {
        $owner = $this->staff([], 'owner');
        $this->device($owner, 'https://fcm.googleapis.com/fcm/send/a', ['last_sent_at' => now()->subMinutes(5)]);

        $this->actingAs($owner)->get('https://' . config('app.domains.hq') . '/system/health')
            ->assertOk()
            ->assertSee('Push notifications')
            ->assertSee('1 device for 1 person; last sent 5 minutes ago.');
    }
}
