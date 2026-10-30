<?php

namespace Tests\Feature\Pwa;

use App\Support\Pwa\PushDispatcher;
use App\Support\Pwa\PushPreferences;
use App\Support\Pwa\VapidKeys;
use App\Support\Pwa\WebPush;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CoreSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/** PushDispatcher, the provider's after-the-response hook and the pwa:push-* commands (docs/PWA.md). */
class PushDispatcherTest extends TestCase
{
    use PushTestKit;
    use RefreshDatabase;

    private const FCM = 'https://fcm.googleapis.com/fcm/send/phone';
    private const WNS = 'https://wns2-par02p.notify.windows.com/w/?token=laptop';
    private const MOZ = 'https://updates.push.services.mozilla.com/wpush/v2/firefox';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CoreSeeder::class, AccessSeeder::class]);
        PushDispatcher::flush();
        VapidKeys::flush();
        WebPush::flush();
        Http::preventStrayRequests();
    }

    /** Every push service answers 201 unless told otherwise (the first matching fake wins, so these come first) */
    private function fakePush(array $answers = []): void
    {
        Http::fake($answers + [
            'fcm.googleapis.com/*' => Http::response('', 201),
            '*.notify.windows.com/*' => Http::response('', 201),
            '*.push.services.mozilla.com/*' => Http::response('', 201),
        ]);
    }

    /** [claims, header, token] of a request's VAPID JWT, after checking its signature and public key */
    private function vapid($request): array
    {
        $this->assertTrue((bool) preg_match('/^vapid t=([\w-]+\.[\w-]+\.[\w-]+), k=([\w-]+)$/', $request->header('Authorization')[0], $m));
        $this->assertSame(VapidKeys::publicKey(), $m[2]);
        [$header, $claims, $signature] = explode('.', $m[1]);
        $this->assertSame(1, openssl_verify("{$header}.{$claims}", self::rawToDer(self::b64uDecode($signature)), self::publicPem(self::b64uDecode($m[2])), OPENSSL_ALGO_SHA256));

        return [json_decode(self::b64uDecode($claims), true), json_decode(self::b64uDecode($header), true), $m[1]];
    }

    public function test_a_new_notification_reaches_every_device_of_its_person(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $phone = $this->device($user, self::FCM);
        $laptop = $this->device($user, self::WNS);
        $this->notification($user, ['created_at' => now()->subDays(3), 'is_read' => 0]); // too old to push, still unread
        $created = Carbon::parse(now()->subMinute()->format('Y-m-d H:i:s'));
        $id = $this->notification($user, ['created_at' => $created]);

        $stats = PushDispatcher::dispatchPending();

        $this->assertSame(['checked' => 1, 'sent' => 1, 'partial' => 0, 'failed' => 0, 'muted' => 0, 'no-device' => 0, 'skipped' => 0, 'devices' => 2, 'removed' => 0], $stats);
        $expected = [
            'v' => 1,
            'id' => $id,
            'title' => 'Cash request to approve',
            'body' => 'Cash request CR-1042 needs approval. Review it in Cash request approvals.',
            'url' => "/pwa/open/{$id}",
            'category' => 'finance',
            'tag' => $id,
            'badge' => 2,
            'ts' => $created->getTimestampMs(),
        ];
        $this->assertSame([$expected], $this->pushedTo($phone));
        $this->assertSame([$expected], $this->pushedTo($laptop));

        $delivery = DB::table('push_deliveries')->where('notification_id', $id)->first();
        $this->assertSame(['sent', 2, $user->id], [$delivery->status, (int) $delivery->devices, $delivery->user_id]);
        $this->assertNotNull($delivery->sent_at);
        $this->assertSame(2, DB::table('push_subscriptions')->whereNotNull('last_sent_at')->where('failures', 0)->count());
    }

    public function test_requests_follow_rfc_8030_and_carry_a_valid_vapid_jwt_per_push_service(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $this->device($user, self::FCM);
        $this->device($user, self::WNS);
        $this->notification($user, ['title' => 'Lead added', 'link_url' => '/intelligence/leads', 'type' => 'System']);
        $this->notification($user, ['title' => 'Lead added', 'link_url' => '/intelligence/leads', 'type' => 'System']);

        PushDispatcher::dispatchPending();

        $requests = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertCount(4, $requests);
        $tokens = [];
        foreach ($requests as $request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame(['86400'], $request->header('TTL'));
            $this->assertSame(['normal'], $request->header('Urgency'));
            $this->assertSame(['aes128gcm'], $request->header('Content-Encoding'));
            $this->assertSame(['application/octet-stream'], $request->header('Content-Type'));
            [$claims, $header, $jwt] = $this->vapid($request);
            $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], $header);
            $this->assertSame(WebPush::audience($request->url()), $claims['aud']);
            $this->assertSame('mailto:system@jbspraxis.com', $claims['sub']);
            $this->assertEqualsWithDelta(time() + 12 * 3600, $claims['exp'], 60);
            $tokens[$claims['aud']][] = $jwt;
        }
        $this->assertSame(['https://fcm.googleapis.com', 'https://wns2-par02p.notify.windows.com'], array_keys($tokens));
        $this->assertCount(1, array_unique($tokens['https://fcm.googleapis.com']), 'one JWT reused per push service');
        $this->assertNotSame($tokens['https://fcm.googleapis.com'][0], $tokens['https://wns2-par02p.notify.windows.com'][0]);
    }

    public function test_approvals_and_system_alerts_are_urgent(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $phone = $this->device($user, self::FCM);
        $this->notification($user, ['title' => 'Campaign budget to approve', 'link_url' => '/marketing/campaigns', 'created_at' => now()->subSeconds(3)]);
        $this->notification($user, ['title' => 'Scheduler (cron) needs attention', 'link_url' => '/system/health', 'type' => 'System', 'created_at' => now()->subSeconds(2)]);
        $this->notification($user, ['title' => 'Campaign approved', 'link_url' => '/marketing/campaigns', 'created_at' => now()->subSecond()]);

        PushDispatcher::dispatchPending();

        $urgency = Http::recorded()->map(fn ($pair) => $pair[0]->header('Urgency')[0])->all();
        $this->assertSame(['high', 'high', 'normal'], $urgency);
        $this->assertSame(['marketing', 'system', 'marketing'], array_column($this->pushedTo($phone), 'category'));
    }

    public function test_the_message_is_one_line_trimmed_to_the_limit_with_an_ellipsis(): void
    {
        $this->fakePush();
        config(['pwa.push.body_limit' => 40]);
        $user = $this->staff();
        $phone = $this->device($user, self::FCM);
        $this->notification($user, [
            'title' => "  Proof of payment\n uploaded ",
            'message' => "Acme Ltd uploaded proof of a ₦1.2m payment\n\nfor INV-0042. Verify it in Payments.",
            'link_url' => 'https://' . config('app.domains.hq') . '/finance/payments',
            'created_at' => now()->subSeconds(2),
        ]);
        $this->notification($user, ['title' => '', 'message' => 'Short.', 'link_url' => null, 'type' => 'Message', 'created_at' => now()->subSecond()]);

        PushDispatcher::dispatchPending();

        [$long, $short] = $this->pushedTo($phone);
        $this->assertSame('Proof of payment uploaded', $long['title']);
        $this->assertSame('Acme Ltd uploaded proof of a ₦1.2m paym…', $long['body']);
        $this->assertSame(40, mb_strlen($long['body']));
        $this->assertSame('finance', $long['category']);
        $this->assertSame('New notification in HQ', $short['title']);
        $this->assertSame('Short.', $short['body']);
        $this->assertSame('other', $short['category']);
    }

    public function test_each_notification_is_pushed_once_however_often_the_dispatcher_runs(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $this->device($user, self::FCM);
        $this->notification($user);

        $first = PushDispatcher::dispatchPending();
        $second = PushDispatcher::dispatchPending();

        $this->assertSame(1, $first['sent']);
        $this->assertSame(0, $second['checked']);
        Http::assertSentCount(1);
    }

    public function test_a_notification_another_run_claims_first_is_left_to_it(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $this->device($user, self::FCM);
        $id = $this->notification($user);
        // Another process claims it between this run's search and its claim
        $claimed = false;
        DB::listen(function ($query) use (&$claimed, $id, $user) {
            if (! $claimed && str_starts_with(strtolower(ltrim($query->sql)), 'select') && str_contains($query->sql, 'push_deliveries')) {
                $claimed = true;
                DB::table('push_deliveries')->insert(['notification_id' => $id, 'user_id' => $user->id, 'status' => 'sending', 'devices' => 0, 'created_at' => now()]);
            }
        });

        $stats = PushDispatcher::dispatchPending();

        $this->assertTrue($claimed);
        $this->assertSame(1, $stats['checked']);
        $this->assertSame(0, $stats['sent'] + $stats['failed']);
        Http::assertNothingSent();
        $this->assertSame('sending', DB::table('push_deliveries')->where('notification_id', $id)->value('status'));
    }

    public function test_old_and_baseline_notifications_are_never_pushed(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $this->device($user, self::FCM);
        $old = $this->notification($user, ['created_at' => now()->subHours(25)]);
        $baseline = $this->notification($user);
        DB::table('push_deliveries')->insert(['notification_id' => $baseline, 'user_id' => $user->id, 'status' => 'baseline', 'devices' => 0, 'created_at' => now()]);

        $stats = PushDispatcher::dispatchPending();

        $this->assertSame(0, $stats['checked']);
        Http::assertNothingSent();
        $this->assertFalse(DB::table('push_deliveries')->where('notification_id', $old)->exists());
        $this->assertSame('baseline', DB::table('push_deliveries')->where('notification_id', $baseline)->value('status'));
    }

    public function test_the_migration_marks_existing_notifications_as_baseline(): void
    {
        $this->fakePush();
        // The migration's own statement, on notifications that existed before push
        $user = $this->staff();
        $before = $this->notification($user);
        DB::table('push_deliveries')->insertOrIgnoreUsing(
            ['notification_id', 'user_id', 'status', 'devices', 'created_at'],
            DB::table('notifications')->select('id', 'user_id', DB::raw("'baseline'"), DB::raw('0'), DB::raw('CURRENT_TIMESTAMP')),
        );
        $oneLine = fn (string $code) => preg_replace('/\s+/', '', $code);
        $this->assertStringContainsString(
            $oneLine("DB::table('push_deliveries')->insertOrIgnoreUsing(['notification_id', 'user_id', 'status', 'devices', 'created_at'], DB::table('notifications')->select('id', 'user_id', DB::raw(\"'baseline'\"), DB::raw('0'), DB::raw('CURRENT_TIMESTAMP')),"),
            $oneLine(file_get_contents(database_path('migrations/2026_10_30_300000_create_pwa_tables.php'))),
            'the migration uses this same statement',
        );
        $this->device($user, self::FCM);

        $stats = PushDispatcher::dispatchPending();

        $this->assertSame(0, $stats['checked']);
        Http::assertNothingSent();
        $this->assertSame('baseline', DB::table('push_deliveries')->where('notification_id', $before)->value('status'));
    }

    public function test_muted_groups_missing_devices_and_inactive_staff_are_recorded_not_pushed(): void
    {
        $this->fakePush();
        $muted = $this->staff();
        $this->device($muted, 'https://fcm.googleapis.com/fcm/send/muted');
        PushPreferences::set($muted->id, 'finance', false);
        $noDevice = $this->staff();
        $suspended = $this->staff(['status' => 'Suspended']);
        $this->device($suspended, 'https://fcm.googleapis.com/fcm/send/suspended');
        $offboarded = $this->staff(['status' => 'Offboarded']);
        $this->device($offboarded, 'https://fcm.googleapis.com/fcm/send/offboarded');

        $ids = [
            'muted' => $this->notification($muted, ['link_url' => '/finance/manager']),
            'no-device' => $this->notification($noDevice),
            'suspended' => $this->notification($suspended),
            'offboarded' => $this->notification($offboarded),
        ];
        $notMuted = $this->notification($muted, ['link_url' => '/operations/qa-hub', 'title' => 'Issue resolved', 'type' => 'QA_Update']);

        $stats = PushDispatcher::dispatchPending();

        $this->assertSame(['checked' => 5, 'sent' => 1, 'muted' => 1, 'no-device' => 1, 'skipped' => 2], array_intersect_key($stats, array_flip(['checked', 'sent', 'muted', 'no-device', 'skipped'])));
        $status = fn ($id) => DB::table('push_deliveries')->where('notification_id', $id)->value('status');
        $this->assertSame(['muted', 'no-device', 'skipped', 'skipped', 'sent'], [$status($ids['muted']), $status($ids['no-device']), $status($ids['suspended']), $status($ids['offboarded']), $status($notMuted)]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/fcm/send/muted');
    }

    public function test_push_service_answers_keep_remove_or_count_against_each_device(): void
    {
        $this->fakePush([
            'fcm.googleapis.com/fcm/send/ok' => Http::response('', 201),
            'fcm.googleapis.com/fcm/send/gone' => Http::response('', 410),
            'fcm.googleapis.com/fcm/send/missing' => Http::response('', 404),
            'fcm.googleapis.com/fcm/send/rekeyed' => Http::response('', 403),
            'fcm.googleapis.com/fcm/send/busy' => Http::response('', 429),
            'fcm.googleapis.com/fcm/send/down' => Http::response('', 503),
            'fcm.googleapis.com/fcm/send/last-chance' => Http::response('', 500),
        ]);
        $user = $this->staff();
        $devices = [];
        foreach (['ok', 'gone', 'missing', 'rekeyed', 'busy', 'down', 'last-chance'] as $name) {
            $devices[$name] = $this->device($user, "https://fcm.googleapis.com/fcm/send/{$name}", ['failures' => $name === 'last-chance' ? 4 : ($name === 'ok' ? 2 : 0)]);
        }
        $id = $this->notification($user);

        $stats = PushDispatcher::dispatchPending();

        $this->assertSame(['partial' => 1, 'devices' => 1, 'removed' => 4], array_intersect_key($stats, array_flip(['partial', 'devices', 'removed'])));
        $this->assertSame(['partial', 1], [DB::table('push_deliveries')->where('notification_id', $id)->value('status'), (int) DB::table('push_deliveries')->where('notification_id', $id)->value('devices')]);
        $left = DB::table('push_subscriptions')->orderBy('id')->get()->keyBy('id');
        $this->assertSame(0, (int) $left[$devices['ok']['id']]->failures);
        $this->assertNotNull($left[$devices['ok']['id']]->last_sent_at);
        foreach (['gone', 'missing', 'rekeyed', 'last-chance'] as $name) {
            $this->assertArrayNotHasKey($devices[$name]['id'], $left->all(), $name);
        }
        $this->assertSame(1, (int) $left[$devices['busy']['id']]->failures);
        $this->assertSame(1, (int) $left[$devices['down']['id']]->failures);
        $this->assertNull($left[$devices['busy']['id']]->last_sent_at);
    }

    public function test_a_device_that_never_answers_is_dropped_after_five_runs_in_a_row(): void
    {
        $this->fakePush(['fcm.googleapis.com/*' => Http::failedConnection()]);
        $user = $this->staff();
        $device = $this->device($user, self::FCM);

        for ($run = 1; $run <= 5; $run++) {
            $this->notification($user);
            $stats = PushDispatcher::dispatchPending();
            $this->assertSame(1, $stats['failed'], "run {$run}");
            $failures = DB::table('push_subscriptions')->where('id', $device['id'])->value('failures');
            $this->assertSame($run < 5 ? $run : null, $failures === null ? null : (int) $failures, "run {$run}");
        }
        $this->assertSame(5, DB::table('push_deliveries')->where('status', 'failed')->whereNull('sent_at')->count());
    }

    public function test_one_bad_device_never_stops_the_others(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $good = $this->device($user, self::FCM);
        $broken = $this->device($user, 'https://fcm.googleapis.com/fcm/send/broken', ['public_key' => self::b64u("\x04" . str_repeat("\x01", 64))]);
        $plainHttp = $this->device($user, 'http://fcm.googleapis.com/fcm/send/plain');
        $id = $this->notification($user);

        $stats = PushDispatcher::dispatchPending();

        $this->assertSame(1, $stats['partial']);
        $this->assertCount(1, $this->pushedTo($good));
        Http::assertSentCount(1);
        $this->assertFalse(DB::table('push_subscriptions')->whereIn('id', [$broken['id'], $plainHttp['id']])->exists(), 'keys and addresses that can never work are dropped');
        $this->assertSame('partial', DB::table('push_deliveries')->where('notification_id', $id)->value('status'));
    }

    public function test_an_address_the_http_client_cannot_read_stops_no_one_else(): void
    {
        $this->fakePush();
        Exceptions::fake();
        $owner = $this->staff();
        $colleague = $this->staff();
        // Rows written before the stricter check: parse_url() reads port 443 here, Guzzle refuses the address,
        // and braces would be expanded as a URI template by Laravel's HTTP client
        $plus = $this->device($owner, 'https://fcm.googleapis.com:+443/fcm/send/plus');
        $braces = $this->device($owner, 'https://fcm.googleapis.com/fcm/send/{token}');
        $phone = $this->device($owner, self::FCM);
        $laptop = $this->device($colleague, self::WNS);
        $mine = $this->notification($owner);
        $theirs = $this->notification($colleague, ['title' => 'Lead added', 'link_url' => '/intelligence/leads']);

        $stats = PushDispatcher::dispatchPending();

        $this->assertSame(['sent' => 1, 'partial' => 1, 'failed' => 0, 'devices' => 2, 'removed' => 2], array_intersect_key($stats, array_flip(['sent', 'partial', 'failed', 'devices', 'removed'])));
        $this->assertCount(1, $this->pushedTo($phone));
        $this->assertCount(1, $this->pushedTo($laptop));
        Http::assertSentCount(2);
        $this->assertSame(['partial', 'sent'], [DB::table('push_deliveries')->where('notification_id', $mine)->value('status'), DB::table('push_deliveries')->where('notification_id', $theirs)->value('status')]);
        $this->assertFalse(DB::table('push_subscriptions')->whereIn('id', [$plus['id'], $braces['id']])->exists(), 'addresses that can never work are dropped');
        Exceptions::assertNothingReported();
    }

    public function test_a_redirect_from_a_push_service_is_not_followed(): void
    {
        $this->fakePush([
            'fcm.googleapis.com/*' => Http::response('', 307, ['Location' => 'https://169.254.169.254/latest/meta-data/']),
            '169.254.169.254/*' => Http::response('', 200),
        ]);
        $user = $this->staff();
        $device = $this->device($user, self::FCM);
        $id = $this->notification($user);

        $stats = PushDispatcher::dispatchPending();

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
        $this->assertSame(1, $stats['failed']);
        $this->assertSame('failed', DB::table('push_deliveries')->where('notification_id', $id)->value('status'));
        $this->assertSame(1, (int) DB::table('push_subscriptions')->where('id', $device['id'])->value('failures'));
    }

    public function test_a_message_too_long_for_a_push_is_never_sent_and_blames_no_device(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $device = $this->device($user, self::FCM);

        $results = WebPush::send([
            'long' => ['endpoint' => self::FCM, 'p256dh' => self::b64u($device['public']), 'auth' => self::b64u($device['auth']), 'payload' => str_repeat('a', WebPush::MAX_PAYLOAD + 1)],
            'fits' => ['endpoint' => self::FCM, 'p256dh' => self::b64u($device['public']), 'auth' => self::b64u($device['auth']), 'payload' => str_repeat('a', WebPush::MAX_PAYLOAD)],
        ]);

        $this->assertSame(['long', 'fits'], array_keys($results));
        $this->assertSame([413, false, false], [$results['long']['status'], $results['long']['ok'], $results['long']['gone']]);
        $this->assertSame([201, true], [$results['fits']['status'], $results['fits']['ok']]);
        Http::assertSentCount(1);
        $this->assertSame([str_repeat('a', WebPush::MAX_PAYLOAD)], array_map(fn ($pair) => self::decryptPush($pair[0]->body(), $device['private'], $device['public'], $device['auth']), Http::recorded()->all()));
    }

    public function test_a_problem_on_this_side_is_reported_and_blames_no_device(): void
    {
        $this->fakePush();
        Exceptions::fake();
        config(['pwa.vapid.public_key' => 'BBroken', 'pwa.vapid.private_key' => 'not-a-key']);
        VapidKeys::flush();
        $user = $this->staff();
        $device = $this->device($user, self::FCM, ['failures' => 1]);
        $id = $this->notification($user);

        $stats = PushDispatcher::dispatchPending();

        $this->assertSame(1, $stats['failed']);
        Http::assertNothingSent();
        Exceptions::assertReported(\RuntimeException::class);
        $this->assertSame('failed', DB::table('push_deliveries')->where('notification_id', $id)->value('status'));
        $this->assertSame(1, (int) DB::table('push_subscriptions')->where('id', $device['id'])->value('failures'), 'the device is not to blame');
    }

    public function test_quietly_does_nothing_without_the_tables_and_never_throws(): void
    {
        Exceptions::fake();
        $connection = DB::connection();
        $user = $this->staff();
        $this->notification($user);

        // Code uploaded, migration not run yet: every PWA table is missing
        $connection->setTablePrefix('missing_');
        try {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            PushDispatcher::dispatchPendingQuietly();
            PushDispatcher::dispatchPendingQuietly();
            $this->assertFalse(PushDispatcher::ready());
            $this->assertSame(1, $queries, 'one table check, once per process');
            $this->artisan('pwa:push-pending')->expectsOutputToContain('not there yet')->assertSuccessful();
            $this->artisan('pwa:push-prune')->expectsOutputToContain('not there yet')->assertSuccessful();
        } finally {
            $connection->setTablePrefix('');
        }
        Exceptions::assertNothingReported();
        Http::assertNothingSent();

        // Tables there, but the database fails half way: reported, not thrown
        PushDispatcher::flush();
        $this->assertTrue(PushDispatcher::ready());
        $connection->setTablePrefix('missing_');
        try {
            PushDispatcher::dispatchPendingQuietly();
        } finally {
            $connection->setTablePrefix('');
        }
        Exceptions::assertReported(QueryException::class);
    }

    public function test_a_notification_written_during_a_request_is_pushed_after_the_response(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $device = $this->device($user, self::FCM);
        $id = (string) Str::uuid();
        $sentDuringRequest = null;
        Route::middleware('web')->get('/_push_test/write', function () use ($user, $id, &$sentDuringRequest) {
            DB::table('notifications')->insert([
                'id' => $id, 'user_id' => $user->id, 'title' => 'Lead added', 'message' => 'Acme Ltd was added to Leads.',
                'link_url' => '/intelligence/leads', 'type' => 'System', 'is_read' => 0, 'created_at' => now(),
            ]);
            $sentDuringRequest = Http::recorded()->count();

            return response('written');
        });
        Route::middleware('web')->get('/_push_test/read', fn () => response('read ' . DB::table('notifications')->count()));

        $this->get('http://localhost/_push_test/read')->assertOk();
        Http::assertNothingSent();

        $this->get('http://localhost/_push_test/write')->assertOk()->assertSee('written');

        $this->assertSame(0, $sentDuringRequest, 'nothing is pushed while the response is being made');
        Http::assertSentCount(1);
        $this->assertSame($id, $this->pushedTo($device)[0]['id']);
        $this->assertSame('sent', DB::table('push_deliveries')->where('notification_id', $id)->value('status'));
    }

    public function test_the_commands_report_in_one_line_and_tidy_up(): void
    {
        $this->fakePush();
        $user = $this->staff();
        $this->device($user, self::FCM);
        $this->notification($user);
        $this->notification($this->staff());

        $this->artisan('pwa:push-pending')
            ->expectsOutput('push: 2 new, 1 sent, 0 partly sent, 0 failed, 0 muted, 1 without a device, 0 skipped; 1 device(s) reached, 0 removed')
            ->assertSuccessful();

        // Prune: deliveries after 60 days, devices not seen for 180
        DB::table('push_deliveries')->insert([
            ['notification_id' => (string) Str::uuid(), 'user_id' => null, 'status' => 'sent', 'devices' => 1, 'sent_at' => null, 'created_at' => now()->subDays(61)],
            ['notification_id' => (string) Str::uuid(), 'user_id' => null, 'status' => 'sent', 'devices' => 1, 'sent_at' => null, 'created_at' => now()->subDays(59)],
        ]);
        $stale = $this->device($user, 'https://fcm.googleapis.com/fcm/send/stale', ['last_seen_at' => now()->subDays(181)]);
        $never = $this->device($user, 'https://fcm.googleapis.com/fcm/send/never', ['last_seen_at' => null, 'created_at' => now()->subDays(200)]);
        $quiet = $this->device($user, 'https://fcm.googleapis.com/fcm/send/quiet', ['last_seen_at' => now()->subDays(179)]);

        $this->artisan('pwa:push-prune')->expectsOutput('push-prune: 1 delivery record(s) and 2 device(s) removed')->assertSuccessful();

        $this->assertSame(3, DB::table('push_deliveries')->count());
        $this->assertSame([$quiet['id']], DB::table('push_subscriptions')->whereIn('id', [$stale['id'], $never['id'], $quiet['id']])->pluck('id')->all());
    }

    public function test_the_push_test_command_lists_each_device(): void
    {
        $this->fakePush(['*.notify.windows.com/*' => Http::response('', 410)]);
        $user = $this->staff(['email' => 'pwa.someone@jbspraxis.test']);
        $this->device($user, self::FCM, ['device' => 'Android · HQ app']);
        $this->device($user, self::WNS, ['device' => 'Windows · Edge']);
        $nobody = $this->staff(['email' => 'pwa.nobody@jbspraxis.test']);

        $this->artisan('pwa:push-test', ['email' => 'pwa.someone@jbspraxis.test'])
            // One line per device, the answers in a column
            ->expectsOutput('  Android · HQ app' . str_repeat(' ', 34) . '201: accepted by the push service')
            ->expectsOutput('  Windows · Edge' . str_repeat(' ', 36) . '410: the device unsubscribed, so it was removed')
            ->expectsOutput('Sent to 1 of 2 device(s).')
            ->assertSuccessful();
        $this->artisan('pwa:push-test', ['email' => $nobody->email])->expectsOutputToContain('has no device')->assertFailed();
        $this->artisan('pwa:push-test', ['email' => 'nobody@example.com'])->expectsOutputToContain('No active staff member')->assertFailed();
    }
}
