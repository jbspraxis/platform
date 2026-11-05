<?php

namespace Tests\Feature\CompanyProfile;

use App\Livewire\Hq\CompanyProfile\ShareLinks as ShareLinksScreen;
use App\Models\CompanyProfile\ProfileShareLink;
use App\Models\CompanyProfile\ProfileShareOpen;
use App\Models\CompanyProfile\ProfileVersion;
use App\Services\Site\SiteSignature;
use App\Support\CompanyProfile\Shares;
use App\Support\Hosts;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Tracked share links for the company profile (docs/COMPANY_PROFILE.md section 14).
 *
 * The things that matter: the token cannot be guessed and names no version, a link that has ended never
 * serves the PDF whatever the reason, the open is counted once per visitor per window and rings the bell,
 * and the record says somebody opened it without saying who.
 */
class ShareLinksTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const KEY_ID = 'test-key';

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['site_api.keys' => [self::KEY_ID => self::SECRET]]);
    }

    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    /** The signed-in person makes the link, so a test can look at their bell afterwards */
    private function link(array $attributes = []): ProfileShareLink
    {
        $user = \Illuminate\Support\Facades\Auth::user() ?? $this->staff('owner', ['owner']);

        return Shares::create($attributes + ['locale' => 'en', 'recipient_name' => 'A. Mbeki'], $user);
    }

    // ------------------------------------------------------------------ the token

    public function test_the_token_is_long_random_and_says_nothing_about_the_version(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $version = ProfileVersion::query()->where('status', ProfileVersion::PUBLISHED)->firstOrFail();

        $first = $this->link(['version_id' => $version->id]);
        $second = $this->link(['version_id' => $version->id]);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $first->token);
        $this->assertNotSame($first->token, $second->token, 'two links never share a token');

        // Nothing in the address is derived from the version. The bare number is one character and turns up
        // in a random token by chance, so the identifiers worth asserting on are the id and the reference.
        foreach ([$version->id, $version->reference] as $secret) {
            $this->assertStringNotContainsString($secret, $first->token);
        }

        // The token never leaves HQ in a payload by accident
        $this->assertArrayNotHasKey('token', $first->fresh()->toArray());
    }

    public function test_a_token_of_the_wrong_shape_never_reaches_the_database(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $this->link();

        foreach (['', 'short', str_repeat('a', 47), str_repeat('a', 49), "' OR 1=1 --", str_repeat('a', 48) . '!'] as $bad) {
            $this->assertNull(Shares::find($bad), $bad . ' is not a token');
        }
    }

    // ------------------------------------------------------------------ what the public route serves

    public function test_an_open_link_serves_the_pdf_and_is_never_indexed(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $link = $this->link();

        $response = $this->signedGet('/profile/' . $link->token)->assertOk();

        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_revoked_an_expired_and_an_unknown_link_all_end_alike_and_never_serve_the_pdf(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);

        $revoked = $this->link();
        Shares::revoke($revoked, $owner);

        $expired = $this->link();
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $unknown = str_repeat('z', 48);

        foreach ([$revoked->token, $expired->token, $unknown] as $token) {
            $response = $this->signedGet('/profile/' . $token);
            $response->assertStatus(410);
            $this->assertSame('link_ended', $response->json('error.code'));
            $this->assertStringNotContainsString('%PDF-', (string) $response->getContent());
        }

        // Nothing was counted for a link that never opened
        $this->assertSame(0, $revoked->fresh()->opens);
        $this->assertSame(0, ProfileShareOpen::query()->count());
    }

    public function test_the_public_route_is_signed_like_every_other_site_read(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $link = $this->link();

        $this->get($this->hq('/api/site/v1/profile/' . $link->token))->assertUnauthorized();
    }

    // ------------------------------------------------------------------ counting the opens

    public function test_an_open_is_counted_once_per_visitor_per_window_and_rings_the_bell(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $link = $this->link();

        $this->signedGet('/profile/' . $link->token, ip: '203.0.113.5')->assertOk();
        $this->assertSame(1, $link->fresh()->opens);
        $this->assertNotNull($link->fresh()->last_opened_at);

        $bells = fn () => DB::table('notifications')->where('user_id', $owner->id)
            ->where('title', 'Company profile opened')->count();
        $this->assertSame(1, $bells(), 'the person who made the link is told');

        // The same visitor again inside the window: one reader, one open
        $this->signedGet('/profile/' . $link->token, ip: '203.0.113.5')->assertOk();
        $this->signedGet('/profile/' . $link->token, ip: '203.0.113.5')->assertOk();
        $this->assertSame(1, $link->fresh()->opens, 'a repeat inside the window is the same open');
        $this->assertSame(1, $bells());

        // Another visitor is another open
        $this->signedGet('/profile/' . $link->token, ip: '198.51.100.9')->assertOk();
        $this->assertSame(2, $link->fresh()->opens);
        $this->assertSame(2, $bells());

        // And the same visitor once the window has passed
        ProfileShareOpen::query()->update(['created_at' => now()->subMinutes(Shares::REPEAT_WINDOW_MINUTES + 1)]);
        $this->signedGet('/profile/' . $link->token, ip: '203.0.113.5')->assertOk();
        $this->assertSame(3, $link->fresh()->opens);
    }

    public function test_the_record_says_somebody_opened_it_and_never_who(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $link = $this->link();

        $this->signedGet('/profile/' . $link->token, ip: '203.0.113.5',
            agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36')->assertOk();

        $open = ProfileShareOpen::query()->firstOrFail();

        // The address is hashed, never kept, and not derivable from the row
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $open->visitor);
        $this->assertSame(Shares::visitorHash('203.0.113.5'), $open->visitor);
        foreach (ProfileShareOpen::query()->get()->toArray() as $row) {
            $this->assertStringNotContainsString('203.0.113.5', json_encode($row));
        }

        // The user agent is reduced to what is worth knowing
        $this->assertSame('Chrome on Windows', $open->agent);
        $this->assertSame('Safari on iPhone', Shares::coarseAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Version/17.0 Safari/604.1'));
        $this->assertNull(Shares::coarseAgent(''));
    }

    public function test_a_token_hammered_for_an_hour_is_told_to_wait(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $link = $this->link();

        // Fill the hour's allowance without going through the route
        $rows = [];
        for ($i = 0; $i < Shares::OPENS_PER_HOUR; $i++) {
            $rows[] = ['id' => (string) \Illuminate\Support\Str::uuid(), 'share_link_id' => $link->id,
                'visitor' => hash('sha256', 'visitor-' . $i), 'agent' => null, 'created_at' => now()];
        }
        DB::table('company_profile_share_opens')->insert($rows);

        $response = $this->signedGet('/profile/' . $link->token, ip: '203.0.113.77');
        $response->assertStatus(429);
        $this->assertSame('too_many', $response->json('error.code'));
        $this->assertStringNotContainsString('%PDF-', (string) $response->getContent());
    }

    // ------------------------------------------------------------------ the screen

    public function test_anyone_who_may_read_the_profile_may_send_it_and_only_the_right_people_revoke(): void
    {
        // Sales & Marketing hold company_profile.view from the engine's grant migration
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales)->get($this->hq('/company-profile/shares'))->assertOk()->assertSee('Share links');

        $made = Livewire::actingAs($sales)->test(ShareLinksScreen::class)
            ->call('make')
            ->set('recipientName', 'A. Mbeki')
            ->set('recipientCompany', 'Foxtrot Foods Limited')
            ->call('create')
            ->assertHasNoErrors();

        $link = ProfileShareLink::query()->firstOrFail();
        $this->assertSame($sales->id, $link->created_by);
        // Shown once, in full, so it can be copied
        $made->assertSee(Shares::url($link->token));

        // Someone else who may only read cannot end another person's link
        $other = $this->staff('sales2', ['sales']);
        Livewire::actingAs($other)->test(ShareLinksScreen::class)->call('revoke', $link->id)->assertForbidden();
        $this->assertNull($link->fresh()->revoked_at);

        // Its maker can, and so can anyone with manage
        Livewire::actingAs($sales)->test(ShareLinksScreen::class)->call('revoke', $link->id);
        $this->assertNotNull($link->fresh()->revoked_at);

        $second = $this->link();
        Livewire::actingAs($this->staff('owner', ['owner']))->test(ShareLinksScreen::class)->call('revoke', $second->id);
        $this->assertNotNull($second->fresh()->revoked_at);
    }

    public function test_a_role_with_nothing_granted_sees_no_share_links_at_all(): void
    {
        $delivery = $this->staff('delivery', ['delivery']);

        $this->actingAs($delivery)->get($this->hq('/company-profile/shares'))->assertForbidden();

        // The screen refuses them at the door: mount() authorises before anything renders
        Livewire::actingAs($delivery)->test(ShareLinksScreen::class)->assertForbidden();
    }

    public function test_a_link_can_be_pinned_to_a_version_or_follow_the_published_one(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $published = ProfileVersion::query()->where('status', ProfileVersion::PUBLISHED)->firstOrFail();

        $pinned = $this->link(['version_id' => $published->id]);
        $this->assertSame($published->id, $pinned->version_id);
        $this->assertSame($published->reference, Shares::data($pinned)['meta']['reference']);

        $following = $this->link();
        $this->assertNull($following->version_id);
        $this->assertSame($published->reference, Shares::data($following)['meta']['reference']);
    }

    private function signedGet(string $path, string $locale = 'en', string $ip = '203.0.113.5', ?string $agent = null)
    {
        $full = '/api/site/v1' . $path;
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(12));
        $canonical = SiteSignature::canonical('GET', $full, $timestamp, $nonce, $ip, $locale, self::KEY_ID, '', hash('sha256', ''));

        return $this->call('GET', 'https://' . Hosts::hq() . $full, [], [], [], array_filter([
            'HTTP_X_SITE_KEY' => self::KEY_ID,
            'HTTP_X_SITE_TIMESTAMP' => $timestamp,
            'HTTP_X_SITE_NONCE' => $nonce,
            'HTTP_X_SITE_VISITOR_IP' => $ip,
            'HTTP_X_SITE_LOCALE' => $locale,
            'HTTP_X_SITE_SIGNATURE' => SiteSignature::sign(self::SECRET, $canonical),
            'HTTP_USER_AGENT' => $agent,
        ]));
    }
}
