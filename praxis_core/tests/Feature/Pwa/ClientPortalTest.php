<?php

namespace Tests\Feature\Pwa;

use App\Livewire\ClientOs\NotificationBell;
use App\Models\ClientUser;
use App\Support\Hosts;
use App\Support\Pwa\ClientNotify;
use App\Support\Pwa\ClientPush;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** The Client Portal as an app (docs/PWA.md, "Client Portal"): its shell, its devices, its inbox and its pushes. */
class ClientPortalTest extends TestCase
{
    use PushTestKit;
    use RefreshDatabase;

    private string $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CoreSeeder::class, AccessSeeder::class]);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 201)]);
        ClientNotify::flush();
        $this->org = $this->organisation('Northwind Ltd');
    }

    private function portal(string $path): string
    {
        return 'https://' . Hosts::portal() . $path;
    }

    private function organisation(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('organizations')->insert(['id' => $id, 'name' => $name]);

        return $id;
    }

    private function client(string $role = 'client-finance', array $attributes = []): ClientUser
    {
        return ClientUser::forceCreate($attributes + [
            'organization_id' => $this->org,
            'role_id' => DB::table('roles')->where('audience', 'client')->where('slug', $role)->value('id'),
            'first_name' => 'Chidi',
            'last_name' => 'Eze',
            'email' => Str::lower(Str::random(10)) . '@northwind.test',
            'password' => Str::random(24),
            'status' => 'Active',
            'lifecycle_stage' => 'Active',
        ]);
    }

    /** A client device with real keys, in a language. */
    private function clientDevice(ClientUser $client, string $locale, string $endpoint): array
    {
        $keys = self::deviceKeys();
        $id = DB::table('push_subscriptions')->insertGetId([
            'user_id' => null, 'client_user_id' => $client->id, 'locale' => $locale,
            'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => self::b64u($keys['public']), 'auth_token' => self::b64u($keys['auth']),
            'content_encoding' => 'aes128gcm', 'device' => 'Test phone', 'standalone' => 1,
            'last_seen_at' => now(), 'failures' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $keys + ['id' => $id, 'endpoint' => $endpoint];
    }

    private function subscription(string $endpoint): array
    {
        $keys = self::deviceKeys();

        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => self::b64u($keys['public']), 'auth' => self::b64u($keys['auth'])], 'contentEncoding' => 'aes128gcm'];
    }

    public function test_the_portal_manifest_is_in_the_clients_language(): void
    {
        $fr = $this->get($this->portal('/manifest.webmanifest?lang=fr'))->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->json();
        $this->assertSame(['Portail client JBS Praxis', 'Portail client', 'fr', '/', '/?source=pwa', 'standalone'],
            [$fr['name'], $fr['short_name'], $fr['lang'], $fr['id'], $fr['start_url'], $fr['display']]);
        $this->assertArrayNotHasKey('shortcuts', $fr, 'HQ\'s shortcuts are staff screens');

        $en = $this->get($this->portal('/manifest.webmanifest'))->assertOk()->json();
        $this->assertSame(['JBS Praxis Client Portal', 'Client Portal', 'en'], [$en['name'], $en['short_name'], $en['lang']]);
    }

    public function test_the_offline_page_and_the_worker_speak_for_the_portal(): void
    {
        $offline = (string) $this->get($this->portal('/offline'))->assertOk()->getContent();
        $this->assertStringContainsString('This device can’t reach the Client Portal right now.', $offline);
        $this->assertStringContainsString('Vous êtes hors ligne', $offline, 'the French words travel with the page');

        $this->assertStringContainsString('"name":"JBS Praxis Client Portal"', (string) $this->get($this->portal('/sw.js'))->assertOk()->getContent());
        $this->assertStringContainsString('"title":"New notification in HQ"', (string) $this->get('https://' . Hosts::hq() . '/sw.js')->assertOk()->getContent());
    }

    public function test_a_client_registers_a_device_in_their_language_and_staff_never_see_it(): void
    {
        $client = $this->client();
        $this->actingAs($client, 'client')->postJson($this->portal('/pwa/push/subscription'), $this->subscription('https://web.push.apple.com/client-1') + ['locale' => 'fr'])
            ->assertOk()->assertJson(['ok' => true]);

        $row = DB::table('push_subscriptions')->first();
        $this->assertSame([null, $client->id, 'fr'], [$row->user_id, $row->client_user_id, $row->locale]);

        // A client can only remove their own device; an unknown language falls back to English
        $other = $this->client('client-member');
        $this->actingAs($other, 'client')->post($this->portal('/pwa/push/subscription/delete'), ['endpoint' => 'https://web.push.apple.com/client-1'])->assertNoContent();
        $this->assertSame(1, DB::table('push_subscriptions')->count());
        $this->actingAs($other, 'client')->postJson($this->portal('/pwa/push/subscription'), $this->subscription('https://fcm.googleapis.com/fcm/send/other') + ['locale' => 'de'])->assertOk();
        $this->assertSame('en', DB::table('push_subscriptions')->where('client_user_id', $other->id)->value('locale'));
    }

    public function test_news_reaches_only_the_people_who_may_see_it(): void
    {
        $project = (string) Str::uuid();
        $otherProject = (string) Str::uuid();
        $admin = $this->client('client-member', ['is_primary_client' => 1]);
        $finance = $this->client('client-finance');
        $member = $this->client('client-member');
        $financeElsewhere = $this->client('client-finance', ['restricted_project_id' => null]);
        DB::table('client_users')->where('id', $financeElsewhere->id)->update(['restricted_project_id' => null]);
        $lead = $this->client('client-finance', ['password' => null]);
        $suspended = $this->client('client-finance', ['status' => 'Suspended']);
        $otherOrg = $this->client('client-finance', ['organization_id' => $this->organisation('Contoso')]);

        $billing = ClientNotify::recipients($this->org, 'portal.billing.view');
        sort($billing);
        $expected = [$admin->id, $finance->id, $financeElsewhere->id];
        sort($expected);
        $this->assertSame($expected, $billing, 'the primary account and finance, never members, leads, suspended accounts or other organisations');
        $this->assertNotContains($member->id, $billing);
        $this->assertNotContains($lead->id, $billing);
        $this->assertNotContains($suspended->id, $billing);
        $this->assertNotContains($otherOrg->id, $billing);

        // ClientNotify::organization writes one inbox row per recipient
        $this->assertSame(3, ClientNotify::organization($this->org, 'invoice_issued', ['ref' => 'INV-1', 'amount' => 'NGN 240,000.00', 'due' => '2026-11-06']));
        $this->assertSame(3, DB::table('client_notifications')->where('kind', 'invoice_issued')->count());
        $this->assertSame('/?tab=financials', DB::table('client_notifications')->value('link_url'));
        $this->assertSame(0, ClientNotify::organization($this->org, 'no_such_kind'));
        $this->assertNotSame($project, $otherProject);
    }

    public function test_each_device_gets_the_news_once_in_its_own_language(): void
    {
        $client = $this->client();
        $english = $this->clientDevice($client, 'en', 'https://fcm.googleapis.com/fcm/send/client-en');
        $french = $this->clientDevice($client, 'fr', 'https://web.push.apple.com/client-fr');

        ClientNotify::clients([$client->id], 'payment_verified', ['ref' => 'INV-202610-1042'], null, $this->org);
        $stats = ClientPush::dispatchPending();
        $this->assertSame(1, $stats['pushed']);
        $this->assertSame(2, $stats['devices']);

        $en = $this->pushedTo($english);
        $fr = $this->pushedTo($french);
        $this->assertCount(1, $en);
        $this->assertSame(['Payment confirmed', 'We received your payment for invoice INV-202610-1042. Thank you.'], [$en[0]['title'], $en[0]['body']]);
        $this->assertSame(['Paiement confirmé', 'Nous avons bien reçu votre paiement pour la facture INV-202610-1042. Merci.'], [$fr[0]['title'], $fr[0]['body']]);
        $this->assertStringStartsWith('/inbox/open/', $fr[0]['url']);
        $this->assertSame(1, $fr[0]['badge']);

        // Claimed once: a second run sends nothing
        $this->assertSame(0, ClientPush::dispatchPending()['pushed']);
        $this->assertCount(1, $this->pushedTo($english));
    }

    public function test_opening_a_notification_marks_it_read_and_lands_on_its_tab(): void
    {
        $client = $this->client();
        $someoneElse = $this->client();
        ClientNotify::clients([$client->id, $someoneElse->id], 'invoice_overdue', ['ref' => 'INV-1', 'amount' => 'NGN 1.00', 'due' => '2026-10-01'], null, $this->org);
        $mine = DB::table('client_notifications')->where('client_user_id', $client->id)->value('id');
        $theirs = DB::table('client_notifications')->where('client_user_id', $someoneElse->id)->value('id');

        $this->actingAs($client, 'client')->get($this->portal('/inbox/open/' . $mine))->assertRedirect('/?tab=financials');
        $this->assertSame(1, (int) DB::table('client_notifications')->where('id', $mine)->value('is_read'));

        $this->actingAs($client, 'client')->get($this->portal('/inbox/open/' . $theirs))->assertRedirect(route('client.dashboard'));
        $this->assertSame(0, (int) DB::table('client_notifications')->where('id', $theirs)->value('is_read'));
    }

    public function test_the_bell_lists_the_clients_news_in_their_language(): void
    {
        $client = $this->client();
        ClientNotify::clients([$client->id], 'ticket_resolved', ['ticket' => 'Login page slow'], null, $this->org);
        session(['locale' => 'fr']);

        $bell = Livewire::actingAs($client, 'client')->test(NotificationBell::class)
            ->assertSet('unreadTotal', 1)
            ->assertSee('Anomalie résolue')
            ->assertDispatched('hq-unread', count: 1);
        $bell->call('markAllRead')->assertSet('unreadTotal', 0);
        $this->assertSame(0, ClientNotify::unread($client->id));
    }
}
