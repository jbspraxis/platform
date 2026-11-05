<?php

namespace Tests\Feature;

use App\Support\Clients;
use Illuminate\Http\Client\Request as HttpRequest;
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
        $this->fakeLibrary([['key' => 'brand-new-client', 'name' => 'Brand New Client', 'sha256' => str_repeat('a', 64)]]);

        $version = str_repeat('a', 12);
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
