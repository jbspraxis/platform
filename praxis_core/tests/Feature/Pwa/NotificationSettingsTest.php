<?php

namespace Tests\Feature\Pwa;

use App\Livewire\Hq\Components\NotificationBell;
use App\Livewire\Hq\Pwa\NotificationSettings;
use App\Models\User;
use App\Support\Hosts;
use App\Support\Pwa\PushPreferences;
use App\Support\Pwa\Pwa;
use App\Support\Pwa\VapidKeys;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The app inside HQ's pages, the "client" part (docs/PWA.md): the notification settings panel, the bell's
 * unread total and the page settings that hq-pwa.js reads.
 */
class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CoreSeeder::class, AccessSeeder::class]);
        Http::preventStrayRequests();
        VapidKeys::flush();
        Pwa::flush();
    }

    protected function tearDown(): void
    {
        VapidKeys::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function staff(string $role = 'engineer', array $attributes = []): User
    {
        $user = User::forceCreate($attributes + [
            'organization_id' => CoreSeeder::INTERNAL_ORG_ID,
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'email' => Str::lower(Str::random(10)) . '@jbspraxis.test',
            'password' => Str::random(24),
            'status' => 'Active',
        ]);
        DB::table('staff_roles')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('audience', 'staff')->where('slug', $role)->value('id'),
        ]);

        return $user;
    }

    private function device(User $user, string $endpoint, string $label, array $attributes = []): int
    {
        return DB::table('push_subscriptions')->insertGetId($attributes + [
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => 'BPublicKey-' . Str::random(40),
            'auth_token' => 'Auth-' . Str::random(16),
            'content_encoding' => 'aes128gcm',
            'device' => $label,
            'standalone' => 0,
            'last_seen_at' => now(),
            'failures' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notification(User $user, array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert($attributes + [
            'id' => $id,
            'user_id' => $user->id,
            'title' => 'Cash request to approve',
            'message' => 'Cash request CR-1042 needs approval. Review it in Cash request approvals.',
            'link_url' => '/finance/manager',
            'type' => 'Financial',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        return $id;
    }

    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    /** The JSON in <script id="hq-pwa-config"> */
    private function pageConfig(string $html): array
    {
        $this->assertSame(1, preg_match('~<script id="hq-pwa-config" type="application/json">(.*?)</script>~s', $html, $m), 'The page carries the app settings once');

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    // ---------------------------------------------------------------- the settings panel: devices

    public function test_the_panel_lists_only_the_persons_own_devices_and_marks_this_one(): void
    {
        $me = $this->staff();
        $other = $this->staff();
        $computer = $this->device($me, 'https://fcm.googleapis.com/fcm/send/mine-computer', 'Windows · Edge');
        $phone = $this->device($me, 'https://web.push.apple.com/mine-phone', 'iPhone · HQ app', ['standalone' => 1]);
        $this->device($other, 'https://fcm.googleapis.com/fcm/send/theirs', 'Android · HQ app');

        $panel = Livewire::actingAs($me)->test(NotificationSettings::class)
            ->assertSee('Loading your devices')
            ->assertDontSee('Windows · Edge');

        $panel->call('open', hash('sha256', 'https://web.push.apple.com/mine-phone'))
            ->assertSee('Windows · Edge')
            ->assertSee('iPhone · HQ app')
            ->assertDontSee('Android · HQ app')
            ->assertSee('Remove iPhone · HQ app');

        $html = $panel->html();
        $this->assertSame(1, substr_count($html, '<span class="tag">This device</span>'));
        $this->assertMatchesRegularExpression('~wire:key="device-' . $phone . '".*?<span class="tag">This device</span>~s', $html);
        $this->assertDoesNotMatchRegularExpression('~wire:key="device-' . $computer . '"(?:(?!wire:key=).)*<span class="tag">This device</span>~s', $html);

        // Push addresses and keys never reach the page, neither in the markup nor in the component's state
        // (only the marker this page sent itself comes back)
        $sent = $html . json_encode($panel->snapshot);
        foreach (DB::table('push_subscriptions')->get() as $row) {
            $this->assertStringNotContainsString($row->endpoint, $sent);
            $this->assertStringNotContainsString($row->public_key, $sent);
            $this->assertStringNotContainsString($row->auth_token, $sent);
            if ($row->id !== $phone) {
                $this->assertStringNotContainsString($row->endpoint_hash, $sent);
            }
        }
        $this->assertStringNotContainsString('push.apple.com', $sent);
        $this->assertStringNotContainsString('googleapis', $sent);
    }

    public function test_a_marker_that_is_not_a_hash_is_ignored(): void
    {
        $me = $this->staff();
        $this->device($me, 'https://fcm.googleapis.com/fcm/send/mine', 'Windows · Chrome');

        $html = Livewire::actingAs($me)->test(NotificationSettings::class)
            ->call('open', 'https://fcm.googleapis.com/fcm/send/mine')
            ->assertSee('Windows · Chrome')
            ->html();

        $this->assertStringNotContainsString('<span class="tag">This device</span>', $html);
    }

    public function test_people_remove_only_their_own_devices(): void
    {
        $me = $this->staff();
        $other = $this->staff();
        $mine = $this->device($me, 'https://fcm.googleapis.com/fcm/send/mine', 'Windows · Edge');
        $kept = $this->device($me, 'https://web.push.apple.com/mine-too', 'Mac · Safari');
        $theirs = $this->device($other, 'https://fcm.googleapis.com/fcm/send/theirs', 'Android · HQ app');

        $panel = Livewire::actingAs($me)->test(NotificationSettings::class)->call('open');

        $panel->call('removeDevice', $theirs)->assertOk();
        $this->assertDatabaseHas('push_subscriptions', ['id' => $theirs, 'user_id' => $other->id]);

        $panel->call('removeDevice', 999999)->assertOk();
        $this->assertSame(3, DB::table('push_subscriptions')->count());

        $panel->call('removeDevice', $mine)
            ->assertDontSee('Windows · Edge')
            ->assertSee('Mac · Safari');
        $this->assertDatabaseMissing('push_subscriptions', ['id' => $mine]);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $kept]);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $theirs]);
    }

    public function test_the_removal_question_names_the_device(): void
    {
        $me = $this->staff();
        $this->device($me, 'https://fcm.googleapis.com/fcm/send/mine', 'Windows · Edge');

        Livewire::actingAs($me)->test(NotificationSettings::class)->call('open')
            ->assertSee('Remove Windows · Edge? It stops getting HQ notifications.')
            ->assertSee('Remove device')
            ->assertSee('Keep device');
    }

    public function test_with_no_devices_the_panel_says_how_to_add_one(): void
    {
        Livewire::actingAs($this->staff())->test(NotificationSettings::class)->call('open')
            ->assertSee('No devices yet')
            ->assertSee('Turn on notifications on this device');
    }

    // ---------------------------------------------------------------- the settings panel: groups

    public function test_switches_are_saved_for_the_person_and_shown_again(): void
    {
        $me = $this->staff('owner');
        $other = $this->staff('owner');

        Livewire::actingAs($me)->test(NotificationSettings::class)->call('open')
            ->assertSet('groups.finance', true)
            ->set('groups.finance', false)
            ->assertOk()
            ->assertSet('groups.finance', false)
            ->set('groups.marketing', false)
            ->assertSet('groups.marketing', false);

        $this->assertSame(['finance', 'marketing'], PushPreferences::muted($me->id));
        $this->assertSame([], PushPreferences::muted($other->id));

        // A new visit shows what was saved, and turning a group back on saves too
        Livewire::actingAs($me)->test(NotificationSettings::class)->call('open')
            ->assertSet('groups', ['leads' => true, 'projects' => true, 'finance' => false, 'team' => true, 'marketing' => false, 'mail' => true, 'system' => true, 'other' => true])
            ->set('groups.finance', true)
            ->assertSet('groups.finance', true);

        $this->assertSame(['marketing'], PushPreferences::muted($me->id));
    }

    public function test_unknown_groups_are_refused(): void
    {
        $me = $this->staff('owner');
        $panel = Livewire::actingAs($me)->test(NotificationSettings::class)->call('open');

        $panel->set('groups.payroll', false)->assertForbidden();
        $this->assertSame(0, DB::table('push_preferences')->count());

        // The whole list at once is not a switch either
        Livewire::actingAs($me)->test(NotificationSettings::class)->call('open')
            ->set('groups', ['finance' => false])
            ->assertForbidden();
        $this->assertSame([], PushPreferences::muted($me->id));
    }

    public function test_system_health_is_offered_only_to_people_who_can_open_it(): void
    {
        $engineer = $this->staff('engineer');
        $this->assertFalse($engineer->can('system.view'));

        $panel = Livewire::actingAs($engineer)->test(NotificationSettings::class)->call('open')
            ->assertSee('Leads and proposals')
            ->assertSee('Other updates')
            ->assertDontSee('System health');
        $this->assertArrayNotHasKey('system', $panel->get('groups'));

        $panel->set('groups.system', false)->assertForbidden();
        $this->assertSame([], PushPreferences::muted($engineer->id));

        $owner = $this->staff('owner');
        $this->assertTrue($owner->can('system.view'));
        Livewire::actingAs($owner)->test(NotificationSettings::class)->call('open')
            ->assertSee('System health')
            ->set('groups.system', false)
            ->assertSet('groups.system', false);
        $this->assertSame(['system'], PushPreferences::muted($owner->id));
    }

    public function test_only_signed_in_active_staff_can_use_the_panel(): void
    {
        $device = $this->device($this->staff(), 'https://fcm.googleapis.com/fcm/send/someone', 'Windows · Edge');

        Livewire::test(NotificationSettings::class)->call('open')->assertForbidden();
        Livewire::test(NotificationSettings::class)->call('removeDevice', $device)->assertForbidden();

        $suspended = $this->staff('owner', ['status' => 'Suspended']);
        Livewire::actingAs($suspended)->test(NotificationSettings::class)->call('open')->assertForbidden();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $device]);
        $this->assertSame(0, DB::table('push_preferences')->count());
    }

    public function test_the_panel_shows_the_app_version(): void
    {
        Livewire::actingAs($this->staff())->test(NotificationSettings::class)
            ->assertSee('HQ app version ' . Pwa::version());
    }

    // ---------------------------------------------------------------- the bell

    public function test_the_bell_counts_every_unread_notification_and_tells_the_page(): void
    {
        $me = $this->staff();
        $other = $this->staff();
        foreach (range(1, 8) as $i) {
            $this->notification($me, ['created_at' => now()->subMinutes($i)]);
        }
        foreach (range(1, 3) as $i) {
            $this->notification($me, ['is_read' => 1, 'created_at' => now()->subHours($i)]);
        }
        $this->notification($other);
        $this->notification($other);

        $bell = Livewire::actingAs($me)->test(NotificationBell::class)
            ->assertSet('unreadCount', 8)
            ->assertCount('notifications', 6)
            ->assertDispatched('hq-unread', count: 8)
            ->assertSee('8 unread')
            ->assertSee('Notifications, 8 unread');

        // A push arrived: the bell reloads and the badge follows
        $this->notification($me);
        $bell->dispatch('hq-push-received')
            ->assertSet('unreadCount', 9)
            ->assertDispatched('hq-unread', count: 9)
            ->assertSee('9 unread');
    }

    public function test_the_bell_menu_has_the_app_actions_and_the_settings(): void
    {
        $html = Livewire::actingAs($this->staff())->test(NotificationBell::class)
            ->assertSee('Notification settings')
            ->assertSeeHtml('data-hq-pwa="bell-actions"')
            ->assertSeeHtml("\$dispatch('open-notification-settings')")
            ->html();

        // hq-pwa.js draws into this box: Livewire must never redraw it
        $this->assertMatchesRegularExpression('~<div wire:ignore data-hq-pwa="bell-actions"~', $html);
        // The settings panel comes with the bell, so it exists on every HQ page
        $this->assertStringContainsString('id="hqp-settings-title"', $html);
    }

    public function test_opening_a_notification_marks_only_the_persons_own(): void
    {
        $me = $this->staff();
        $other = $this->staff();
        $mine = $this->notification($me, ['link_url' => '/finance/manager']);
        $theirs = $this->notification($other, ['link_url' => '/finance/manager']);

        Livewire::actingAs($me)->test(NotificationBell::class)
            ->call('markAndRedirect', $theirs)
            ->assertNoRedirect();
        $this->assertSame(0, (int) DB::table('notifications')->where('id', $theirs)->value('is_read'));

        Livewire::actingAs($me)->test(NotificationBell::class)
            ->call('markAndRedirect', $mine)
            ->assertRedirect('/finance/manager');
        $this->assertSame(1, (int) DB::table('notifications')->where('id', $mine)->value('is_read'));
    }

    public function test_a_link_that_is_not_a_web_address_is_never_opened(): void
    {
        $me = $this->staff();
        $note = $this->notification($me, ['link_url' => 'javascript:alert(1)']);

        Livewire::actingAs($me)->test(NotificationBell::class)
            ->call('markAndRedirect', $note)
            ->assertNoRedirect()
            ->assertDispatched('hq-unread', count: 0);
        $this->assertSame(1, (int) DB::table('notifications')->where('id', $note)->value('is_read'));
    }

    // ---------------------------------------------------------------- page settings (pwa/head/client.blade.php)

    public function test_hq_pages_carry_the_app_settings_for_the_signed_in_person(): void
    {
        $this->withoutVite();
        $owner = $this->staff('owner');

        $response = $this->actingAs($owner)->get($this->hq('/dashboard/workspace'));

        $response->assertOk();
        $config = $this->pageConfig($response->getContent());
        $this->assertSame(Pwa::version(), $config['version']);
        $this->assertSame('/sw.js', $config['sw']);
        $this->assertSame('app', $config['page']);
        $this->assertSame(substr(hash('sha256', $owner->id), 0, 16), $config['user']);
        $this->assertSame(session()->token(), $config['csrf']);
        $this->assertSame(VapidKeys::publicKey(), $config['vapidKey']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{87}$/', $config['vapidKey']);
        $this->assertSame([
            'subscribe' => '/pwa/push/subscription',
            'unsubscribe' => '/pwa/push/subscription/delete',
            'test' => '/pwa/push/test',
        ], $config['routes']);
        $this->assertSame(['install' => 14, 'notify' => 14], $config['snoozeDays']);

        // The app's styles come with app.css: no second stylesheet holds up the page
        $response->assertDontSee('hq-pwa.css', false);
        $response->assertSee('<script src="' . Pwa::asset('hq-pwa.js') . '" defer></script>', false);
        // The bell's app actions and the settings panel are on every HQ page
        $response->assertSee('data-hq-pwa="bell-actions"', false);
        $response->assertSee('data-hq-pwa="settings-device"', false);
    }

    public function test_the_snooze_days_come_from_the_configuration(): void
    {
        $this->withoutVite();
        config(['pwa.snooze_days' => ['install' => 30, 'notify' => 7]]);

        $config = $this->pageConfig($this->actingAs($this->staff())->get($this->hq('/dashboard/workspace'))->assertOk()->getContent());

        $this->assertSame(['install' => 30, 'notify' => 7], $config['snoozeDays']);
    }

    public function test_the_sign_in_page_is_an_auth_page(): void
    {
        $this->withoutVite();

        $response = $this->get($this->hq('/login'))->assertOk();

        $config = $this->pageConfig($response->getContent());
        $this->assertSame('auth', $config['page']);
        $this->assertNull($config['user']);
        $response->assertSee(Pwa::asset('hq-pwa.js'), false);
        $response->assertDontSee('data-hq-pwa="bell-actions"', false);
    }

    public function test_a_vapid_key_that_cannot_be_read_never_breaks_a_page(): void
    {
        $this->withoutVite();
        // A damaged key row: reading it fails, the page still opens without the key
        DB::table('pwa_settings')->insert(['key' => 'vapid', 'value' => 'not json', 'created_at' => now(), 'updated_at' => now()]);
        VapidKeys::flush();
        config(['pwa.vapid.public_key' => null, 'pwa.vapid.private_key' => null]);

        $response = $this->actingAs($this->staff())->get($this->hq('/dashboard/workspace'));

        $response->assertOk();
        $config = $this->pageConfig($response->getContent());
        $this->assertSame('app', $config['page']);
        $this->assertNull($config['vapidKey']);
    }

    public function test_the_client_portal_gets_its_own_app_but_never_hq_settings(): void
    {
        $this->withoutVite();

        $html = (string) $this->get('https://' . Hosts::portal() . '/login')->assertOk()->assertSee('hq-pwa.js', false)->getContent();
        $this->assertSame(1, preg_match('~<script id="hq-pwa-config" type="application/json">(.*?)</script>~s', $html, $m));
        $config = json_decode($m[1], true);
        $this->assertSame('portal', $config['app']);
        $this->assertSame('auth', $config['page'], 'never an invitation on the sign-in page');
        $this->assertSame('/pwa/push/subscription', $config['routes']['subscribe']);
        $this->assertSame(3, $config['snoozeDays']['install']);
        $this->assertNotEmpty($config['strings'], 'the portal sends its own words');
        $this->assertStringNotContainsString('notification-settings', $html, 'HQ\'s staff settings panel never shows on the portal');
    }
}
