<?php

namespace Tests\Feature\Pwa;

use App\Http\Middleware\PwaLaunch;
use App\Models\User;
use App\Support\Hosts;
use App\Support\Pwa\Pwa;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The launch animation of the installed HQ app (docs/PWA.md; docs/BRAND.md sections 11 and 14): it plays once,
 * on the first page of a cold start (the manifest's start_url, /?source=pwa), on the HQ host only, and its first
 * frame is the iPhone and iPad startup image.
 */
class LaunchSplashTest extends TestCase
{
    use RefreshDatabase;

    private const OVERLAY = 'id="hq-launch"';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CoreSeeder::class, AccessSeeder::class]);
        Http::preventStrayRequests();
        Pwa::flush();
    }

    private function on(string $host, string $path): string
    {
        return 'https://' . $host . $path;
    }

    private function hq(string $path): string
    {
        return $this->on(Hosts::hq(), $path);
    }

    private function staff(): User
    {
        $user = User::forceCreate([
            'organization_id' => CoreSeeder::INTERNAL_ORG_ID,
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'email' => Str::lower(Str::random(10)) . '@jbspraxis.test',
            'password' => Str::random(24),
            'status' => 'Active',
        ]);
        DB::table('staff_roles')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('audience', 'staff')->where('slug', 'owner')->value('id'),
        ]);

        return $user;
    }

    /** How many launch overlays the page has */
    private function overlays(TestResponse $response): int
    {
        return substr_count((string) $response->getContent(), self::OVERLAY);
    }

    private function assertRedirectsTo(TestResponse $response, string $host, string $path): string
    {
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertSame($host, parse_url($location, PHP_URL_HOST), $location);
        $this->assertSame($path, parse_url($location, PHP_URL_PATH), $location);

        return $location;
    }

    /** A request as the browser sends it, with the session the web group would give it. */
    private function request(string $url, string $method = 'GET', array $headers = []): Request
    {
        $request = Request::create($url, $method);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }
        $session = $this->app['session']->driver();
        $session->flush();
        $request->setLaravelSession($session);

        return $request;
    }

    /** Runs the middleware around a page that answers with $response; returns whether the request was a launch. */
    private function launch(Request $request, Response $response): bool
    {
        (new PwaLaunch)->handle($request, fn () => $response);

        return PwaLaunch::active($request);
    }

    public function test_a_cold_start_plays_once_on_the_sign_in_page_it_redirects_to(): void
    {
        $start = $this->get($this->hq('/?source=pwa'));
        $this->assertSame(0, $this->overlays($start));
        $start->assertSessionHas(PwaLaunch::FLASH, true);
        $location = $this->assertRedirectsTo($start, Hosts::hq(), '/login');

        $first = $this->get($location)->assertOk();
        $this->assertSame(1, $this->overlays($first));
        $first->assertSessionMissing(PwaLaunch::FLASH);

        $this->assertSame(0, $this->overlays($this->get($this->hq('/login'))->assertOk()));
    }

    public function test_a_signed_in_cold_start_plays_once_on_my_workspace(): void
    {
        $this->actingAs($this->staff());

        $start = $this->get($this->hq('/?source=pwa'));
        $start->assertSessionHas(PwaLaunch::FLASH, true);
        $location = $this->assertRedirectsTo($start, Hosts::hq(), '/dashboard/workspace');

        $first = $this->get($location)->assertOk();
        $this->assertSame(1, $this->overlays($first));
        $first->assertSessionMissing(PwaLaunch::FLASH);

        $this->assertSame(0, $this->overlays($this->get($location)->assertOk()));
    }

    public function test_a_launch_page_that_answers_itself_plays_once_and_leaves_nothing_behind(): void
    {
        $first = $this->get($this->hq('/login?source=pwa'))->assertOk();
        $this->assertSame(1, $this->overlays($first));
        $first->assertSessionMissing(PwaLaunch::FLASH);

        $this->assertSame(0, $this->overlays($this->get($this->hq('/login'))->assertOk()));
    }

    public function test_the_portal_and_the_public_site_never_launch(): void
    {
        foreach ([Hosts::portal(), Hosts::site()] as $host) {
            $response = $this->get($this->on($host, '/?source=pwa'));
            $this->assertLessThan(500, $response->getStatusCode(), $host);
            $this->assertSame(0, $this->overlays($response), $host);
            $response->assertSessionMissing(PwaLaunch::FLASH);
            if ($response->isRedirect()) {
                $this->assertSame(0, $this->overlays($this->get((string) $response->headers->get('Location'))), $host);
            }

            // The middleware itself, around a page or a redirect on that host
            foreach ([response('<html></html>'), redirect($this->on($host, '/login'))] as $answer) {
                $request = $this->request($this->on($host, '/?source=pwa'));
                $this->assertFalse($this->launch($request, $answer), $host);
                $this->assertFalse($request->session()->has(PwaLaunch::FLASH), $host);
            }
        }
    }

    public function test_livewire_json_and_non_get_requests_never_launch(): void
    {
        // Through the app: a Livewire request, wire:navigate fetching the next page, JSON and XHR
        foreach ([['X-Livewire' => 'true'], ['X-Livewire-Navigate' => '1'], ['Accept' => 'application/json'], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
            foreach (['/?source=pwa', '/login?source=pwa'] as $path) {
                $response = $this->withHeaders($headers)->get($this->hq($path));
                $this->assertSame(0, $this->overlays($response), key($headers) . ' ' . $path);
                $response->assertSessionMissing(PwaLaunch::FLASH);
            }
            $this->flushHeaders();
        }
        $this->post($this->hq('/?source=pwa'))->assertSessionMissing(PwaLaunch::FLASH);

        // The middleware itself: other methods, Livewire updates, prefetches and frames are never a launch
        $cases = [
            ['POST', []], ['PUT', []], ['DELETE', []], ['HEAD', []],
            ['POST', ['X-Livewire' => 'true', 'Content-Type' => 'application/json']],
            ['GET', ['X-Livewire' => 'true']], ['GET', ['X-Livewire-Navigate' => '1']],
            ['GET', ['Accept' => 'application/json']], ['GET', ['X-Requested-With' => 'XMLHttpRequest']],
            ['GET', ['Sec-Purpose' => 'prefetch']], ['GET', ['Purpose' => 'prefetch']], ['GET', ['Sec-Fetch-Dest' => 'iframe']],
        ];
        foreach ($cases as [$method, $headers]) {
            foreach ([response('<html></html>'), redirect($this->hq('/login'))] as $answer) {
                $request = $this->request($this->hq('/?source=pwa'), $method, $headers);
                $this->assertFalse($this->launch($request, $answer), $method . ' ' . json_encode($headers));
                $this->assertFalse($request->session()->has(PwaLaunch::FLASH), $method . ' ' . json_encode($headers));
            }
        }

        // A browser navigation is one (Sec-Fetch-Dest: document); a redirect to another host does not carry it
        $request = $this->request($this->hq('/?source=pwa'), 'GET', ['Sec-Fetch-Dest' => 'document']);
        $this->assertTrue($this->launch($request, redirect($this->hq('/login'))));
        $this->assertTrue($request->session()->get(PwaLaunch::FLASH));
        $request = $this->request($this->hq('/?source=pwa'));
        $this->assertTrue($this->launch($request, redirect()->away($this->on(Hosts::portal(), '/login'))));
        $this->assertFalse($request->session()->has(PwaLaunch::FLASH));
    }

    public function test_the_launch_component_renders_nothing_on_normal_pages(): void
    {
        $this->assertSame('', trim((string) $this->blade('<x-pwa.launch />')));

        $this->assertSame(0, $this->overlays($this->get($this->hq('/login'))->assertOk()));
        $this->assertSame(0, $this->overlays($this->get($this->hq('/login?source=other'))->assertOk()));

        $this->actingAs($this->staff());
        $this->assertSame(0, $this->overlays($this->get($this->hq('/dashboard/workspace'))->assertOk()));
        $this->get($this->hq('/'))->assertSessionMissing(PwaLaunch::FLASH);
    }

    public function test_the_overlay_needs_nothing_from_the_network_and_hides_from_screen_readers(): void
    {
        $html = (string) $this->get($this->hq('/login?source=pwa'))->assertOk()->getContent();

        // Right after <body>: its style, the overlay, and the script that plays it
        $this->assertMatchesRegularExpression('~<body[^>]*>\s*<style id="hq-launch-style">~', $html);
        $this->assertSame(1, preg_match('~<style id="hq-launch-style">.*?</script>~s', $html, $match));
        $launch = $match[0];

        $this->assertStringContainsString('<div class="hq-launch" id="hq-launch" aria-hidden="true">', $launch);
        $this->assertSame(20, substr_count($launch, 'class="hql-bar '), 'ten bars, each in two halves');
        $this->assertStringContainsString('class="hql-rule"', $launch);
        // "HQ" is drawn as outlines: no text, no web font, no file from anywhere
        $this->assertDoesNotMatchRegularExpression('~<text|font-family|@font-face|@import|<img|<image|\bsrc=|url\(\s*[\'"]?(?!#)~i', $launch);
        $this->assertDoesNotMatchRegularExpression('~>\s*HQ\s*<~', $launch);
        // Nothing to focus
        $this->assertDoesNotMatchRegularExpression('~tabindex|<a\s|<button~i', $launch);
        // Hands over to the app script (docs/PWA.md, Events)
        foreach (["'hq-launching'", "'hq:launch-done'", 'window.__hqLaunchDone = true', "'(prefers-reduced-motion: reduce)'", "'(display-mode: standalone)'"] as $needle) {
            $this->assertStringContainsString($needle, $launch);
        }
    }

    public function test_every_iphone_and_ipad_has_a_startup_image_in_both_orientations(): void
    {
        $html = (string) $this->get($this->hq('/login'))->assertOk()->getContent();
        preg_match_all('~<link rel="apple-touch-startup-image" media="screen and \(device-width: (\d+)px\) and \(device-height: (\d+)px\) '
            . 'and \(-webkit-device-pixel-ratio: (\d)\) and \(orientation: (portrait|landscape)\)" href="([^"]+)">~', $html, $links, PREG_SET_ORDER);

        $this->assertCount(44, $links, '22 iPhones and iPads, portrait and landscape');
        $media = [];
        foreach ($links as [, $w, $h, $ratio, $orientation, $href]) {
            $this->assertLessThan((int) $h, (int) $w, 'device-width and device-height are the portrait values');
            [$pw, $ph] = $orientation === 'portrait' ? [$w * $ratio, $h * $ratio] : [$h * $ratio, $w * $ratio];
            $path = "startup/hq-{$pw}x{$ph}.png";
            $this->assertSame(Pwa::asset($path), $href);
            $file = Pwa::file($path);
            $this->assertNotNull($file, $path);
            $this->assertSame([$pw, $ph], array_slice(getimagesize($file), 0, 2), $path);
            $media["{$w}x{$h}@{$ratio} {$orientation}"] = true;
        }
        $this->assertCount(44, $media, 'each device and orientation once');
        $this->assertCount(44, glob(resource_path('pwa/startup/hq-*.png')), 'no image that is not listed');

        // Served on HQ only
        $this->get($this->hq($links[0][5]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($this->on(Hosts::portal(), $links[0][5]))->assertNotFound();
        $portal = $this->get($this->on(Hosts::portal(), '/login'))->assertOk();
        $this->assertStringNotContainsString('/pwa/', (string) $portal->getContent());
    }
}
