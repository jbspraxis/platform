<?php

namespace Tests\Feature;

use App\Support\Clients;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The logo strip, fed by the client logo library in HQ (docs/COMPANY_PROFILE.md section 4).
 *
 * Two things matter: a logo added in HQ appears here without a code change, and the strip never breaks when
 * HQ cannot be reached — it falls back to the logos shipped with the site, which is what it showed before.
 */
class ClientStripTest extends TestCase
{
    /** A 1x1 WebP, as HQ would serve one */
    private function webp(): string
    {
        return (string) base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==');
    }

    private function fakeLibrary(array $clients, bool $withFile = true): void
    {
        $routes = ['clients' => Http::response(['data' => ['clients' => $clients]])];
        if ($withFile) {
            $routes['clients/*'] = Http::response($this->webp(), 200, ['Content-Type' => 'image/webp']);
        }
        $this->fakeHq($routes);
    }

    // ------------------------------------------------------------------ the list comes from HQ

    public function test_a_logo_added_in_hq_appears_on_the_site_without_a_code_change(): void
    {
        Storage::fake('local');
        $this->fakeLibrary([
            ['key' => 'brand-new-client', 'name' => 'Brand New Client', 'sector' => 'Logistics', 'sha256' => str_repeat('a', 64)],
            ['key' => 'smart-logistics', 'name' => 'Smart Logistics and Services', 'sector' => 'Logistics', 'sha256' => str_repeat('b', 64)],
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('alt="Brand New Client"', $html);
        $this->assertStringContainsString('/img/client-logos/brand-new-client.webp?v=' . str_repeat('a', 12), $html);
        // The name is only the accessible name; the second copy that closes the loop stays silent
        $this->assertSame(1, substr_count($html, 'alt="Brand New Client"'));
        $this->assertSame(2, substr_count($html, '/img/client-logos/brand-new-client.webp'));
    }

    public function test_the_order_hq_gives_is_the_order_the_strip_shows(): void
    {
        $this->fakeLibrary([
            ['key' => 'second-first', 'name' => 'Second First', 'sha256' => str_repeat('c', 64)],
            ['key' => 'first-second', 'name' => 'First Second', 'sha256' => str_repeat('d', 64)],
        ]);

        $strip = Clients::strip();

        $this->assertSame(['second-first', 'first-second'], array_column($strip, 'key'));
    }

    public function test_a_row_with_no_usable_key_is_left_out(): void
    {
        $this->fakeLibrary([
            ['key' => 'Good Key?', 'name' => 'Nonsense', 'sha256' => ''],
            ['key' => 'good-key', 'name' => 'Good', 'sha256' => str_repeat('e', 64)],
        ]);

        $this->assertSame(['good-key'], array_column(Clients::strip(), 'key'));
    }

    // ------------------------------------------------------------------ the files

    public function test_a_logo_is_fetched_from_hq_once_and_then_served_from_here(): void
    {
        Storage::fake('local');
        // The list vouches for the bytes HQ will serve; anything else is refused (see the abuse tests below)
        $sha = hash('sha256', $this->webp());
        $this->fakeLibrary([['key' => 'brand-new-client', 'name' => 'Brand New Client', 'sha256' => $sha]]);

        $version = substr($sha, 0, 12);
        $first = $this->get('/img/client-logos/brand-new-client.webp?v=' . $version);
        $first->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertSame($this->webp(), $first->getContent());
        $this->assertStringContainsString('immutable', (string) $first->headers->get('Cache-Control'));

        $this->assertTrue(Storage::disk('local')->exists('clients/brand-new-client-' . $version . '.webp'));

        // The second request never asks HQ again
        $this->get('/img/client-logos/brand-new-client.webp?v=' . $version)->assertOk();
        $this->assertCount(1, Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/clients/brand-new-client.webp')));
    }

    public function test_a_logo_hq_does_not_know_falls_back_to_what_the_site_ships_and_then_404s(): void
    {
        Storage::fake('local');
        $this->fakeHq(['clients' => Http::response(['data' => ['clients' => []]]), 'clients/*' => Http::response(['error' => ['code' => 'not_found']], 404)]);

        // Shipped with the site: public/img/clients/mtn.webp
        $this->get('/img/client-logos/mtn.webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get('/img/client-logos/nobody-here.webp')->assertNotFound();
        $this->get('/img/client-logos/Not A Key.webp')->assertNotFound();
    }

    // ------------------------------------------------------------------ the route is public, so it is abused

    /**
     * The route has no session and no cookie, so a visitor chooses both the key and the version. Neither may
     * make this server call HQ or write a file: a bot looping over made-up versions would otherwise fill the
     * disk a logo at a time and drain the Site API allowance the contact form, the chat and the verify page
     * all share.
     */
    public function test_a_made_up_version_never_calls_hq_and_never_writes_a_file(): void
    {
        Storage::fake('local');
        $sha = hash('sha256', $this->webp());
        $this->fakeLibrary([['key' => 'real-client', 'name' => 'Real Client', 'sha256' => $sha]]);

        // One honest request stores the one version the list names
        $this->get('/img/client-logos/real-client.webp?v=' . substr($sha, 0, 12))->assertOk();
        $this->assertCount(1, Storage::disk('local')->files('clients'));

        $fetches = fn () => count(Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/clients/real-client.webp')));
        $this->assertSame(1, $fetches());

        // Now the loop a bot would run: the same real key, a different made-up version each time
        foreach (['abcdef012345', '0123456789ab', 'ffffffffffff', 'deadbeefcafe'] as $madeUp) {
            $response = $this->get('/img/client-logos/real-client.webp?v=' . $madeUp)->assertOk();
            // It is answered from what is already here, and never as immutable under that address
            $this->assertSame($this->webp(), $response->getContent());
            $this->assertStringNotContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        }

        $this->assertSame(1, $fetches(), 'no further call to HQ');
        $this->assertCount(1, Storage::disk('local')->files('clients'), 'no further file written');
    }

    public function test_a_key_hq_does_not_know_never_calls_hq_and_never_writes_a_file(): void
    {
        Storage::fake('local');
        $this->fakeLibrary([['key' => 'real-client', 'name' => 'Real Client', 'sha256' => hash('sha256', $this->webp())]]);

        foreach (['nobody-here', 'also-not-here', 'mtn-x'] as $unknown) {
            $this->get('/img/client-logos/' . $unknown . '.webp?v=abcdef012345')->assertNotFound();
        }

        $this->assertCount(0, Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/clients/')
            && ! str_ends_with($r->url(), '/clients')));
        $this->assertSame([], Storage::disk('local')->files('clients'));
    }

    public function test_bytes_that_are_not_the_ones_the_list_vouched_for_are_neither_kept_nor_served(): void
    {
        Storage::fake('local');
        // The list promises one file; HQ answers with different bytes
        $this->fakeHq([
            'clients' => Http::response(['data' => ['clients' => [
                ['key' => 'real-client', 'name' => 'Real Client', 'sha256' => hash('sha256', $this->webp())],
            ]]]),
            'clients/*' => Http::response('RIFF....WEBPsomething else', 200, ['Content-Type' => 'image/webp']),
        ]);

        $version = substr(hash('sha256', $this->webp()), 0, 12);
        // Nothing stored and nothing shipped under that name: there is nothing honest to show
        $this->get('/img/client-logos/real-client.webp?v=' . $version)->assertNotFound();

        $this->assertSame([], Storage::disk('local')->files('clients'), 'the wrong bytes are not kept');
    }

    public function test_a_new_version_replaces_the_one_it_supersedes(): void
    {
        Storage::fake('local');
        $first = hash('sha256', $this->webp());
        $this->fakeLibrary([['key' => 'real-client', 'name' => 'Real Client', 'sha256' => $first]]);
        $this->get('/img/client-logos/real-client.webp?v=' . substr($first, 0, 12))->assertOk();
        $this->assertSame(['clients/real-client-' . substr($first, 0, 12) . '.webp'], Storage::disk('local')->files('clients'));

        // The logo is replaced in HQ: a new sha, new bytes, a new address
        $replacement = (string) base64_decode('UklGRiIAAABXRUJQVlA4TBUAAAAvAAAAEAcQEREBiIj+ByAAAAD+AAA=');
        $second = hash('sha256', $replacement);
        $this->refakeHttp([
            self::HQ . '/clients' => Http::response(['data' => ['clients' => [
                ['key' => 'real-client', 'name' => 'Real Client', 'sha256' => $second],
            ]]]),
            self::HQ . '/clients/*' => Http::response($replacement, 200, ['Content-Type' => 'image/webp']),
            self::HQ . '/settings' => Http::response(['data' => $this->settingsData()]),
        ]);
        Cache::flush();

        $this->get('/img/client-logos/real-client.webp?v=' . substr($second, 0, 12))->assertOk();

        $this->assertSame(
            ['clients/real-client-' . substr($second, 0, 12) . '.webp'],
            Storage::disk('local')->files('clients'),
            'one file per key: the superseded version goes',
        );
    }

    /**
     * "acme" and "acme-corp" are both real keys, and one is a prefix of the other. Keeping a new version of
     * the shorter one must not take the longer one's file with it: that would lose a logo nobody touched and
     * make the site fetch it from HQ again on the next request.
     */
    public function test_keeping_one_logo_leaves_a_key_that_merely_starts_the_same_alone(): void
    {
        Storage::fake('local');
        $short = $this->webp();
        $long = (string) base64_decode('UklGRiIAAABXRUJQVlA4TBUAAAAvAAAAEAcQEREBiIj+ByAAAAD+AAA=');
        $shortSha = hash('sha256', $short);
        $longSha = hash('sha256', $long);

        $this->fakeHq([
            'clients' => Http::response(['data' => ['clients' => [
                ['key' => 'acme', 'name' => 'Acme', 'sha256' => $shortSha],
                ['key' => 'acme-corp', 'name' => 'Acme Corp', 'sha256' => $longSha],
            ]]]),
            'clients/acme.webp*' => Http::response($short, 200, ['Content-Type' => 'image/webp']),
            'clients/acme-corp.webp*' => Http::response($long, 200, ['Content-Type' => 'image/webp']),
        ]);

        // The longer key is fetched and kept first
        $this->get('/img/client-logos/acme-corp.webp?v=' . substr($longSha, 0, 12))->assertOk();
        // Then the shorter one, whose name starts the longer one
        $this->get('/img/client-logos/acme.webp?v=' . substr($shortSha, 0, 12))->assertOk();

        $files = Storage::disk('local')->files('clients');
        sort($files);
        $this->assertSame([
            'clients/acme-' . substr($shortSha, 0, 12) . '.webp',
            'clients/acme-corp-' . substr($longSha, 0, 12) . '.webp',
        ], $files, 'both logos are still here');

        // And the longer one is still served from disk, without asking HQ a second time
        $before = count(Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/clients/acme-corp.webp')));
        $this->get('/img/client-logos/acme-corp.webp?v=' . substr($longSha, 0, 12))->assertOk();
        $this->assertSame(
            $before,
            count(Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/clients/acme-corp.webp'))),
            'its file survived, so HQ is not asked again',
        );
    }

    // ------------------------------------------------------------------ when HQ cannot be reached

    public function test_the_strip_falls_back_to_the_shipped_logos_when_hq_has_never_answered(): void
    {
        // No /clients stub at all: the call is a stray request, which is what an unreachable HQ looks like here
        $this->fakeHq();

        $html = $this->get('/')->assertOk()->getContent();
        $strip = Clients::strip();

        $this->assertGreaterThanOrEqual(15, count($strip));
        $this->assertSame(array_column(Clients::shipped(), 'key'), array_column($strip, 'key'));
        $this->assertStringContainsString('alt="Smart Logistics and Services"', $html);
        $this->assertStringContainsString('img/clients/smart-logistics.webp', $html);
        $this->assertStringContainsString('Organisations we have worked with', $html);
    }

    public function test_the_last_good_list_stands_in_when_hq_stops_answering(): void
    {
        $this->fakeLibrary([['key' => 'brand-new-client', 'name' => 'Brand New Client', 'sha256' => str_repeat('a', 64)]]);
        $this->assertSame(['brand-new-client'], array_column(Clients::strip(), 'key'));

        // HQ goes away; the saved copy is served rather than the shipped list
        $this->refakeHttp([self::HQ . '/clients' => Http::response('', 503)]);

        $this->assertSame(['brand-new-client'], array_column(Clients::strip(), 'key'));
    }

    public function test_an_empty_list_from_hq_is_treated_as_nothing_to_show_and_the_shipped_logos_stand_in(): void
    {
        $this->fakeHq(['clients' => Http::response(['data' => ['clients' => []]])]);

        $this->assertSame(array_column(Clients::shipped(), 'key'), array_column(Clients::strip(), 'key'));
    }
}
