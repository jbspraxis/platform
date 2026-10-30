<?php

namespace Tests\Feature\Pwa;

use App\Support\Pwa\PushDispatcher;
use App\Support\Pwa\VapidKeys;
use App\Support\Pwa\WebPush;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** POST /pwa/push/subscription, /pwa/push/subscription/delete and /pwa/push/test (docs/PWA.md, "Subscription API"). */
class PushSubscriptionTest extends TestCase
{
    use PushTestKit;
    use RefreshDatabase;

    private const FCM = 'https://fcm.googleapis.com/fcm/send/eXk2:APA91bH-device';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CoreSeeder::class, AccessSeeder::class]);
        PushDispatcher::flush();
        VapidKeys::flush();
        WebPush::flush();
        Http::preventStrayRequests();
    }

    private function url(string $path): string
    {
        return 'https://' . config('app.domains.hq') . $path;
    }

    private function payload(array $overrides = []): array
    {
        $keys = self::deviceKeys();

        return array_replace_recursive([
            'endpoint' => self::FCM,
            'keys' => ['p256dh' => self::b64u($keys['public']), 'auth' => self::b64u($keys['auth'])],
            'contentEncoding' => 'aes128gcm',
            'standalone' => true,
            'device' => 'iPhone · HQ app',
        ], $overrides);
    }

    public function test_only_signed_in_active_staff_can_register_a_device(): void
    {
        $this->postJson($this->url('/pwa/push/subscription'), $this->payload())->assertUnauthorized();

        $suspended = $this->staff(['status' => 'Suspended']);
        $this->actingAs($suspended)->postJson($this->url('/pwa/push/subscription'), $this->payload())->assertForbidden();

        $this->assertSame(0, DB::table('push_subscriptions')->count());
    }

    public function test_the_routes_only_answer_on_the_hq_host(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->postJson('https://' . config('app.domains.portal') . '/pwa/push/subscription', $this->payload())->assertNotFound();
        $this->actingAs($user)->postJson('https://' . config('app.domains.site') . '/pwa/push/subscription', $this->payload())->assertNotFound();
        $this->assertSame(0, DB::table('push_subscriptions')->count());
    }

    public function test_a_device_is_registered_for_the_signed_in_person(): void
    {
        $user = $this->staff();
        $payload = $this->payload(['device' => '  <b>iPhone</b>   ·  HQ app ']);

        $response = $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Safari/604.1')
            ->postJson($this->url('/pwa/push/subscription'), $payload);

        $response->assertOk()->assertJson(['ok' => true]);
        $row = DB::table('push_subscriptions')->first();
        $this->assertSame($row->id, $response->json('id'));
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame(self::FCM, $row->endpoint);
        $this->assertSame(hash('sha256', self::FCM), $row->endpoint_hash);
        $this->assertSame($payload['keys']['p256dh'], $row->public_key);
        $this->assertSame($payload['keys']['auth'], $row->auth_token);
        $this->assertSame('aes128gcm', $row->content_encoding);
        $this->assertSame('iPhone · HQ app', $row->device);
        $this->assertSame(1, (int) $row->standalone);
        $this->assertStringContainsString('iPhone OS 17_5', $row->user_agent);
        $this->assertSame(0, (int) $row->failures);
        $this->assertNotNull($row->last_seen_at);
    }

    public function test_keys_sent_as_padded_base64_are_stored_as_base64url(): void
    {
        $user = $this->staff();
        $keys = self::deviceKeys();

        $this->actingAs($user)->postJson($this->url('/pwa/push/subscription'), $this->payload([
            'keys' => ['p256dh' => base64_encode($keys['public']), 'auth' => base64_encode($keys['auth'])],
        ]))->assertOk();

        $row = DB::table('push_subscriptions')->first();
        $this->assertSame(self::b64u($keys['public']), $row->public_key);
        $this->assertSame(self::b64u($keys['auth']), $row->auth_token);
    }

    public function test_every_known_push_service_is_accepted(): void
    {
        $user = $this->staff();
        foreach ([
            'https://fcm.googleapis.com/fcm/send/abc',
            'https://jmt17.google.com.googleapis.com/x',
            'https://updates.push.services.mozilla.com/wpush/v2/gAAAAAB',
            'https://web.push.apple.com/QGxL2HDvxzQ',
            'https://wns2-par02p.notify.windows.com/w/?token=BQYAAAB%2bR',
            'https://FCM.GoogleAPIs.com:443/fcm/send/upper',
        ] as $endpoint) {
            $this->actingAs($user)->postJson($this->url('/pwa/push/subscription'), $this->payload(['endpoint' => $endpoint]))
                ->assertOk()->assertJson(['ok' => true]);
        }
        $this->assertSame(6, DB::table('push_subscriptions')->where('user_id', $user->id)->count());
    }

    public function test_addresses_off_the_push_services_and_bad_keys_are_refused(): void
    {
        $user = $this->staff();
        $keys = self::deviceKeys();
        $cases = [
            'http' => [['endpoint' => 'http://fcm.googleapis.com/fcm/send/abc'], 'endpoint'],
            'unknown host' => [['endpoint' => 'https://push.example.com/abc'], 'endpoint'],
            'look-alike host' => [['endpoint' => 'https://fcm.googleapis.com.evil.example/abc'], 'endpoint'],
            'user info' => [['endpoint' => 'https://fcm.googleapis.com@evil.example/abc'], 'endpoint'],
            'other port' => [['endpoint' => 'https://fcm.googleapis.com:8443/abc'], 'endpoint'],
            'internal address' => [['endpoint' => 'https://127.0.0.1/abc'], 'endpoint'],
            'backslash' => [['endpoint' => 'https://fcm.googleapis.com\\@evil.example/abc'], 'endpoint'],
            'wildcard alone' => [['endpoint' => 'https://notify.windows.com/abc'], 'endpoint'],
            'too long' => [['endpoint' => 'https://fcm.googleapis.com/fcm/send/' . str_repeat('a', 2000)], 'endpoint'],
            // parse_url() reads these as port 443 or a host on the list; the HTTP client that sends the push doesn't
            'plus sign in the port' => [['endpoint' => 'https://fcm.googleapis.com:+443/fcm/send/abc'], 'endpoint'],
            'empty port' => [['endpoint' => 'https://fcm.googleapis.com:/fcm/send/abc'], 'endpoint'],
            'padded port' => [['endpoint' => 'https://fcm.googleapis.com:0443/fcm/send/abc'], 'endpoint'],
            'star in the host' => [['endpoint' => 'https://*.googleapis.com/fcm/send/abc'], 'endpoint'],
            'percent in the host' => [['endpoint' => 'https://ex%61mple.googleapis.com/abc'], 'endpoint'],
            'empty label' => [['endpoint' => 'https://a..googleapis.com/abc'], 'endpoint'],
            'braces' => [['endpoint' => 'https://fcm.googleapis.com/fcm/send/{abc}'], 'endpoint'],
            'control character' => [['endpoint' => "https://fcm.googleapis.com/fcm/send/abc\x7f"], 'endpoint'],
            'not ascii' => [['endpoint' => 'https://fcm.googleapis.com/fcm/send/é'], 'endpoint'],
            'short key' => [['keys' => ['p256dh' => self::b64u(substr($keys['public'], 0, 64))]], 'keys.p256dh'],
            'compressed key' => [['keys' => ['p256dh' => self::b64u("\x03" . substr($keys['public'], 1))]], 'keys.p256dh'],
            'key off the curve' => [['keys' => ['p256dh' => self::b64u("\x04" . str_repeat("\x01", 64))]], 'keys.p256dh'],
            'not base64' => [['keys' => ['p256dh' => '***not-base64***']], 'keys.p256dh'],
            'short auth' => [['keys' => ['auth' => self::b64u(random_bytes(15))]], 'keys.auth'],
            'long auth' => [['keys' => ['auth' => self::b64u(random_bytes(17))]], 'keys.auth'],
            'old encoding' => [['contentEncoding' => 'aesgcm'], 'contentEncoding'],
            'label too long' => [['device' => str_repeat('x', 501)], 'device'],
        ];
        foreach ($cases as $name => [$override, $field]) {
            $response = $this->actingAs($user)->postJson($this->url('/pwa/push/subscription'), $this->payload($override));
            $this->assertSame(422, $response->status(), $name);
            $response->assertJson(['ok' => false])->assertJsonValidationErrors([$field], 'errors');
        }

        $this->actingAs($user)->postJson($this->url('/pwa/push/subscription'), ['endpoint' => self::FCM])
            ->assertStatus(422)->assertJsonValidationErrors(['keys'], 'errors');
        // A plain form post still gets JSON, never a redirect
        $this->actingAs($user)->post($this->url('/pwa/push/subscription'), ['endpoint' => 'http://nope'])
            ->assertStatus(422)->assertJson(['ok' => false]);

        $this->assertSame(0, DB::table('push_subscriptions')->count());
    }

    public function test_a_device_moves_to_whoever_signs_in_on_it_and_starts_afresh(): void
    {
        $first = $this->staff();
        $second = $this->staff();
        $id = $this->actingAs($first)->postJson($this->url('/pwa/push/subscription'), $this->payload())->json('id');
        DB::table('push_subscriptions')->where('id', $id)->update(['failures' => 3, 'last_seen_at' => now()->subDays(3)]);

        $again = $this->actingAs($second)->postJson($this->url('/pwa/push/subscription'), $this->payload(['device' => 'Windows · Edge']));

        $again->assertOk()->assertJson(['ok' => true, 'id' => $id]);
        $this->assertSame(1, DB::table('push_subscriptions')->count());
        $row = DB::table('push_subscriptions')->first();
        $this->assertSame($second->id, $row->user_id);
        $this->assertSame('Windows · Edge', $row->device);
        $this->assertSame(0, (int) $row->failures);
        $this->assertTrue(now()->subMinute()->lt($row->last_seen_at));
    }

    public function test_a_refresh_without_a_label_keeps_the_label(): void
    {
        $user = $this->staff();
        $payload = $this->payload();
        $this->actingAs($user)->postJson($this->url('/pwa/push/subscription'), $payload)->assertOk();

        unset($payload['device']);
        $this->actingAs($user)->postJson($this->url('/pwa/push/subscription'), $payload)->assertOk();

        $this->assertSame('iPhone · HQ app', DB::table('push_subscriptions')->value('device'));
    }

    public function test_beyond_the_device_limit_the_least_recently_seen_go(): void
    {
        config(['pwa.push.max_devices' => 3]);
        $user = $this->staff();
        $other = $this->staff();
        $oldest = $this->device($user, 'https://fcm.googleapis.com/fcm/send/oldest', ['last_seen_at' => now()->subDays(30)]);
        $older = $this->device($user, 'https://fcm.googleapis.com/fcm/send/older', ['last_seen_at' => now()->subDays(20)]);
        $recent = $this->device($user, 'https://fcm.googleapis.com/fcm/send/recent', ['last_seen_at' => now()->subDay()]);
        $theirs = $this->device($other, 'https://fcm.googleapis.com/fcm/send/theirs', ['last_seen_at' => now()->subDays(90)]);

        $id = $this->actingAs($user)->postJson($this->url('/pwa/push/subscription'), $this->payload())->json('id');

        $left = DB::table('push_subscriptions')->where('user_id', $user->id)->pluck('id')->sort()->values()->all();
        $this->assertSame([$older['id'], $recent['id'], $id], $left);
        $this->assertFalse(DB::table('push_subscriptions')->where('id', $oldest['id'])->exists());
        $this->assertTrue(DB::table('push_subscriptions')->where('id', $theirs['id'])->exists(), 'other people are never touched');
    }

    public function test_a_person_can_only_remove_their_own_device(): void
    {
        $user = $this->staff();
        $other = $this->staff();
        $mine = $this->device($user, 'https://fcm.googleapis.com/fcm/send/mine');
        $theirs = $this->device($other, 'https://fcm.googleapis.com/fcm/send/theirs');

        $this->postJson($this->url('/pwa/push/subscription/delete'), ['endpoint' => $theirs['endpoint']])->assertUnauthorized();
        $this->actingAs($user)->postJson($this->url('/pwa/push/subscription/delete'), ['endpoint' => $theirs['endpoint']])->assertNoContent();
        $this->assertTrue(DB::table('push_subscriptions')->where('id', $theirs['id'])->exists());

        // navigator.sendBeacon at sign-out: a form body, not JSON
        $this->actingAs($user)->post($this->url('/pwa/push/subscription/delete'), ['endpoint' => $mine['endpoint'], '_token' => 'beacon'])->assertNoContent();
        $this->assertFalse(DB::table('push_subscriptions')->where('id', $mine['id'])->exists());

        $this->actingAs($user)->postJson($this->url('/pwa/push/subscription/delete'), [])->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertTrue(DB::table('push_subscriptions')->where('id', $theirs['id'])->exists());
    }

    public function test_the_test_endpoint_reports_each_of_the_persons_devices(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response('', 201),
            '*.notify.windows.com/*' => Http::response('', 410),
        ]);
        $user = $this->staff();
        $phone = $this->device($user, 'https://fcm.googleapis.com/fcm/send/phone', ['device' => 'Android · HQ app']);
        $laptop = $this->device($user, 'https://wns2-par02p.notify.windows.com/w/?token=laptop', ['device' => 'Windows · Edge']);
        // Unread, and too old to be pushed itself: it only counts towards the badge
        $this->notification($user, ['is_read' => 0, 'created_at' => now()->subDays(2)]);

        $response = $this->actingAs($user)->postJson($this->url('/pwa/push/test'));

        $response->assertOk()->assertExactJson([
            'ok' => true, 'sent' => 1, 'failed' => 1,
            'devices' => [['device' => 'Android · HQ app', 'status' => 201], ['device' => 'Windows · Edge', 'status' => 410]],
        ]);
        $pushed = $this->pushedTo($phone)[0];
        $this->assertSame('Test notification', $pushed['title']);
        $this->assertSame('HQ notifications are working on this device.', $pushed['body']);
        $this->assertSame('/dashboard/workspace', $pushed['url']);
        $this->assertStringStartsWith('test-', $pushed['id']);
        $this->assertSame($pushed['id'], $pushed['tag']);
        $this->assertSame(1, $pushed['badge']);
        $this->assertNotNull(DB::table('push_subscriptions')->where('id', $phone['id'])->value('last_sent_at'));
        $this->assertFalse(DB::table('push_subscriptions')->where('id', $laptop['id'])->exists(), '410: the device is gone');
    }

    public function test_the_test_endpoint_can_target_one_device_but_never_someone_elses(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $user = $this->staff();
        $other = $this->staff();
        $this->device($user, 'https://fcm.googleapis.com/fcm/send/phone');
        $this->device($user, 'https://fcm.googleapis.com/fcm/send/laptop');
        $theirs = $this->device($other, 'https://fcm.googleapis.com/fcm/send/theirs');

        $this->actingAs($user)->postJson($this->url('/pwa/push/test'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/laptop'])
            ->assertOk()->assertJson(['ok' => true, 'sent' => 1, 'failed' => 0]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/fcm/send/laptop');

        $this->actingAs($user)->postJson($this->url('/pwa/push/test'), ['endpoint' => $theirs['endpoint']])
            ->assertOk()->assertExactJson(['ok' => false, 'sent' => 0, 'failed' => 0, 'devices' => []]);
        Http::assertSentCount(1);
    }
}
