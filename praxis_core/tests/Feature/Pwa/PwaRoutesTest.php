<?php

namespace Tests\Feature\Pwa;

use App\Models\User;
use App\Support\Hosts;
use App\Support\Pwa\Pwa;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The app shell on the HQ host (docs/PWA.md): manifest, service worker, offline page, app files, shortcuts. */
class PwaRoutesTest extends TestCase
{
    use RefreshDatabase;

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

    /** The routes that answer without a session (path => expected status). */
    private function statelessRoutes(): array
    {
        return [
            '/manifest.webmanifest' => 200,
            '/sw.js' => 200,
            '/offline' => 200,
            '/pwa/ping' => 204,
            '/pwa/v/' . Pwa::version() . '/img/badge-96.png' => 200,
        ];
    }

    private function cacheControl(TestResponse $response): array
    {
        return array_map('trim', explode(',', strtolower((string) $response->headers->get('Cache-Control'))));
    }

    private function staff(?string $role = null, array $permissions = []): User
    {
        $user = User::forceCreate([
            'organization_id' => CoreSeeder::INTERNAL_ORG_ID,
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'email' => Str::lower(Str::random(10)) . '@jbspraxis.test',
            'password' => Str::random(24),
            'status' => 'Active',
        ]);
        if ($permissions) {
            // A role made for the test, with exactly these permissions
            $roleId = DB::table('roles')->insertGetId(['name' => 'Test ' . Str::random(6), 'slug' => 'test-' . Str::lower(Str::random(8)), 'audience' => 'staff', 'is_protected' => false, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('permissions')->whereIn('slug', $permissions)->pluck('id') as $permissionId) {
                DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        } else {
            $roleId = DB::table('roles')->where('audience', 'staff')->where('slug', $role)->value('id');
        }
        DB::table('staff_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId]);

        return $user;
    }

    private function assertLandsOn(TestResponse $response, string $route): void
    {
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertSame(Hosts::hq(), parse_url($location, PHP_URL_HOST), $location);
        $this->assertSame(parse_url(route($route), PHP_URL_PATH), parse_url($location, PHP_URL_PATH), $location);
    }

    public function test_the_app_shell_answers_on_hq_and_the_portal_never_on_the_site(): void
    {
        foreach ($this->statelessRoutes() as $path => $status) {
            $this->get($this->hq($path))->assertStatus($status);
            $this->get($this->on(Hosts::portal(), $path))->assertStatus($status);
            $this->get($this->on(Hosts::site(), $path))->assertNotFound();
        }

        $owner = $this->staff('owner');
        $this->actingAs($owner)->get($this->hq('/pwa/go/leads'))->assertRedirect();
        $this->actingAs($owner)->get($this->on(Hosts::portal(), '/pwa/go/leads'))->assertNotFound();
        $this->actingAs($owner)->get($this->on(Hosts::site(), '/pwa/go/leads'))->assertNotFound();
    }

    public function test_the_stateless_routes_start_no_session_and_set_no_cookie(): void
    {
        foreach (array_keys($this->statelessRoutes()) as $path) {
            $response = $this->get($this->hq($path));
            $this->assertFalse($response->headers->has('Set-Cookie'), $path . ' sets a cookie');
            $this->assertSame([], $response->headers->getCookies(), $path . ' sets a cookie');
        }
    }

    public function test_the_manifest(): void
    {
        $response = $this->get($this->hq('/manifest.webmanifest'))->assertOk();

        $this->assertSame('application/manifest+json', $response->headers->get('Content-Type'));
        $this->assertContains('no-cache', $this->cacheControl($response));
        $manifest = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            'id' => '/',
            'name' => 'JBS Praxis HQ',
            'short_name' => 'HQ',
            'description' => config('pwa.description'),
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#0C142B',
            'theme_color' => '#0C142B',
            'lang' => 'en',
            'dir' => 'ltr',
            'categories' => ['business', 'productivity'],
            'launch_handler' => ['client_mode' => ['focus-existing', 'auto']],
            'prefer_related_applications' => false,
        ], array_intersect_key($manifest, array_flip(['id', 'name', 'short_name', 'description', 'start_url', 'scope', 'display', 'orientation', 'background_color', 'theme_color', 'lang', 'dir', 'categories', 'launch_handler', 'prefer_related_applications'])));

        $this->assertSame([
            ['/icons/icon-192.png', '192x192', 'any'],
            ['/icons/icon-512.png', '512x512', 'any'],
            ['/icons/icon-maskable-192.png', '192x192', 'maskable'],
            ['/icons/icon-maskable-512.png', '512x512', 'maskable'],
        ], array_map(fn ($icon) => [$icon['src'], $icon['sizes'], $icon['purpose']], $manifest['icons']));
        $this->assertSame(['image/png'], array_values(array_unique(array_column($manifest['icons'], 'type'))));
    }

    public function test_the_manifest_shortcuts_and_their_icons(): void
    {
        $manifest = $this->get($this->hq('/manifest.webmanifest'))->json();

        $this->assertSame(
            [['My workspace', '/pwa/go/workspace'], ['Leads and proposals', '/pwa/go/leads'], ['Projects', '/pwa/go/projects'], ['Finance', '/pwa/go/finance']],
            array_map(fn ($shortcut) => [$shortcut['name'], $shortcut['url']], $manifest['shortcuts']),
        );
        foreach ($manifest['shortcuts'] as $shortcut) {
            $this->assertCount(1, $shortcut['icons'], $shortcut['name']);
            $icon = $shortcut['icons'][0];
            $this->assertMatchesRegularExpression('#^/pwa/v/' . Pwa::version() . '/img/shortcut-[a-z]+\.png$#', $icon['src']);
            $this->assertSame(['96x96', 'image/png'], [$icon['sizes'], $icon['type']]);

            $file = $this->get($this->hq($icon['src']))->assertOk();
            $this->assertSame('image/png', $file->headers->get('Content-Type'));
            $this->assertSame([96, 96], array_slice(getimagesize(Pwa::file(substr($icon['src'], strlen('/pwa/v/' . Pwa::version() . '/')))), 0, 2));
        }
    }

    public function test_the_manifest_screenshots_match_their_files(): void
    {
        $manifest = $this->get($this->hq('/manifest.webmanifest'))->json();
        if (! isset($manifest['screenshots'])) {
            $this->assertNull(Pwa::file('img/screenshots/hq-wide.png'));
            $this->assertNull(Pwa::file('img/screenshots/hq-narrow.png'));

            return;
        }

        $this->assertSame(['wide', 'narrow'], array_column($manifest['screenshots'], 'form_factor'));
        foreach ($manifest['screenshots'] as $screenshot) {
            $this->get($this->hq($screenshot['src']))->assertOk()->assertHeader('Content-Type', 'image/png');
            [$width, $height] = getimagesize(Pwa::file(substr($screenshot['src'], strlen('/pwa/v/' . Pwa::version() . '/'))));
            $this->assertSame("{$width}x{$height}", $screenshot['sizes']);
            $this->assertNotEmpty($screenshot['label']);
            // What Chrome accepts for its richer install dialog: 320 to 3840px, the long side at most 2.3 times the short one
            $this->assertTrue(min($width, $height) >= 320 && max($width, $height) <= 3840 && max($width, $height) / min($width, $height) <= 2.3);
            $this->assertSame($screenshot['form_factor'] === 'wide', $width > $height);
        }
    }

    public function test_the_service_worker(): void
    {
        $response = $this->get($this->hq('/sw.js'))->assertOk();

        $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));
        $this->assertSame('/', $response->headers->get('Service-Worker-Allowed'));
        foreach (['no-cache', 'no-store', 'must-revalidate'] as $directive) {
            $this->assertContains($directive, $this->cacheControl($response));
        }

        $js = $response->getContent();
        $this->assertStringNotContainsString('__PWA_', $js);
        $this->assertStringContainsString("const VERSION = '" . Pwa::version() . "';", $js);
        $this->assertStringContainsString("const BADGE = '" . Pwa::asset('img/badge-96.png') . "';", $js);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{10}$/', Pwa::version());

        $this->assertSame(1, preg_match('/^const PRECACHE = (\[.*\]);$/m', $js, $m));
        $expected = ['/offline'];
        // The client part's script is kept only once it exists (a missing one would stop the worker installing).
        // Its stylesheet is part of app.css, which the worker keeps with the other built files.
        if (Pwa::file('hq-pwa.js')) {
            $expected[] = Pwa::asset('hq-pwa.js');
        }
        $expected[] = Pwa::asset('img/badge-96.png');
        $this->assertSame($expected, json_decode($m[1], true));

        // Every file it keeps answers on HQ
        foreach ($expected as $path) {
            $this->get($this->hq($path))->assertOk();
        }

        // Where the browser can (Chrome, Edge), POSTs such as Livewire's updates never wake the worker; the
        // fetch handler still steps aside for them everywhere else
        $this->assertStringContainsString("event.addRoutes([{ condition: { requestMethod: 'POST' }, source: 'network' }])", $js);
        $this->assertStringContainsString("if (request.method !== 'GET') {", $js);
    }

    public function test_the_offline_page_is_self_contained(): void
    {
        // Livewire injects its assets after any request that rendered a component; in one test process that
        // flag outlives the earlier tests' requests (on the server every request starts clean)
        \Livewire\Livewire::flushState();

        $response = $this->get($this->hq('/offline'))->assertOk();

        $this->assertStringStartsWith('text/html', $response->headers->get('Content-Type'));
        $this->assertContains('no-cache', $this->cacheControl($response));
        $html = $response->getContent();
        $response->assertSee('You’re offline', false)->assertSee('Try again', false)->assertSee('viewport-fit=cover', false);

        // Nothing loaded from anywhere: no src, no stylesheet or script files, no fonts; the only address is
        // the SVG namespace, and the tab icon is inline
        $this->assertDoesNotMatchRegularExpression('/\ssrc\s*=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/@import|url\(|@font-face/i', $html);
        $this->assertStringNotContainsString('/build/', $html);
        $this->assertStringNotContainsString('livewire', strtolower($html));
        preg_match_all('#https?://[^\s"\'<>)&]+#i', $html, $urls);
        $this->assertSame(['http://www.w3.org/2000/svg'], array_values(array_unique($urls[0])));
        preg_match_all('/\shref\s*=\s*"([^"]*)"/i', $html, $hrefs);
        foreach ($hrefs[1] as $href) {
            $this->assertStringStartsWith('data:image/svg+xml,', $href);
        }
    }

    public function test_the_connection_check(): void
    {
        $response = $this->get($this->hq('/pwa/ping'))->assertNoContent();

        $this->assertContains('no-store', $this->cacheControl($response));
    }

    public function test_app_files_are_cached_for_a_year_only_at_the_current_version(): void
    {
        $current = $this->get($this->hq(Pwa::asset('img/badge-96.png')))->assertOk();
        $this->assertSame('image/png', $current->headers->get('Content-Type'));
        $this->assertEqualsCanonicalizing(['public', 'max-age=31536000', 'immutable'], $this->cacheControl($current));

        $svg = $this->get($this->hq(Pwa::asset('img/logo-horizontal-colour.svg')))->assertOk();
        $this->assertSame('image/svg+xml', $svg->headers->get('Content-Type'));

        // A page open since before an update asks for its old address: same file, checked every time
        $old = Pwa::version() === '0000000000' ? '1111111111' : '0000000000';
        $stale = $this->get($this->hq('/pwa/v/' . $old . '/img/badge-96.png'))->assertOk();
        $this->assertContains('no-cache', $this->cacheControl($stale));
        $this->assertNotContains('immutable', $this->cacheControl($stale));
    }

    public function test_app_files_never_leave_resources_pwa_and_only_known_types_are_served(): void
    {
        $version = Pwa::version();
        $refused = [
            'sw.js',                       // only ever at /sw.js
            'SW.JS',
            'img/../sw.js',
            'img/..%2Fsw.js',
            'img/%2e%2e/%2e%2e/.env',
            '..%2F..%2F.env',
            'img/../../../.env',
            'img/../../../config/app.php',
            'img/..%5C..%5C..%5C.env',
            'img/nope.png',
            'img/badge-96.png.php',
            'img/badge-96.php',
            'img/logo.html',
            'pwa.blade.php',
            'notes.txt',
            'img/badge-96.png.',
        ];
        foreach ($refused as $path) {
            $response = $this->get($this->hq('/pwa/v/' . $version . '/' . $path));
            $this->assertSame(404, $response->getStatusCode(), $path);
            $this->assertStringNotContainsString('APP_KEY', $response->getContent(), $path);
        }
        $this->get($this->hq('/pwa/v/notaversion/img/badge-96.png'))->assertNotFound();
    }

    public function test_the_notification_badge_is_a_96px_png_with_transparency(): void
    {
        $png = file_get_contents(Pwa::file('img/badge-96.png'));

        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
        $this->assertSame([96, 96], array_values(unpack('N2', substr($png, 16, 8))));
        $this->assertSame(6, ord($png[25]), 'colour type 6: RGB with an alpha channel');
    }

    public function test_a_shortcut_lands_on_the_first_screen_of_its_section_the_person_can_open(): void
    {
        $owner = $this->staff('owner');
        $this->assertLandsOn($this->actingAs($owner)->get($this->hq('/pwa/go/workspace')), 'hq.dash.workspace');
        $this->assertLandsOn($this->actingAs($owner)->get($this->hq('/pwa/go/leads')), 'hq.crm.leads');
        $this->assertLandsOn($this->actingAs($owner)->get($this->hq('/pwa/go/projects')), 'hq.ops.deployments');
        $this->assertLandsOn($this->actingAs($owner)->get($this->hq('/pwa/go/finance')), 'hq.finance.bi');

        // Engineer: no leads screens (My workspace instead), All projects, and only cash requests in Finance
        $engineer = $this->staff('engineer');
        $this->assertLandsOn($this->actingAs($engineer)->get($this->hq('/pwa/go/workspace')), 'hq.dash.workspace');
        $this->assertLandsOn($this->actingAs($engineer)->get($this->hq('/pwa/go/leads')), 'hq.dash.workspace');
        $this->assertLandsOn($this->actingAs($engineer)->get($this->hq('/pwa/go/projects')), 'hq.ops.deployments');
        $this->assertLandsOn($this->actingAs($engineer)->get($this->hq('/pwa/go/finance')), 'hq.finance.request');
    }

    public function test_a_shortcut_follows_the_rail_order_when_the_first_screen_is_not_open_to_the_person(): void
    {
        $person = $this->staff(null, ['broadcasts.view', 'signoffs.view', 'timesheets.view', 'expenses.view', 'expenses.create']);
        $this->assertLandsOn($this->actingAs($person)->get($this->hq('/pwa/go/leads')), 'hq.campaigns.broadcasts');
        $this->assertLandsOn($this->actingAs($person)->get($this->hq('/pwa/go/projects')), 'hq.ops.timers');
        $this->assertLandsOn($this->actingAs($person)->get($this->hq('/pwa/go/finance')), 'hq.finance.manager');

        $payroll = $this->staff(null, ['payroll.view', 'qa.view']);
        $this->assertLandsOn($this->actingAs($payroll)->get($this->hq('/pwa/go/finance')), 'hq.finance.payroll');
        $this->assertLandsOn($this->actingAs($payroll)->get($this->hq('/pwa/go/projects')), 'hq.ops.qa');
        $this->assertLandsOn($this->actingAs($payroll)->get($this->hq('/pwa/go/leads')), 'hq.dash.workspace');
    }

    public function test_a_shortcut_needs_a_signed_in_staff_member_and_a_known_section(): void
    {
        $this->assertLandsOn($this->get($this->hq('/pwa/go/leads')), 'login');

        $this->actingAs($this->staff('owner'))->get($this->hq('/pwa/go/settings'))->assertNotFound();
    }
}
