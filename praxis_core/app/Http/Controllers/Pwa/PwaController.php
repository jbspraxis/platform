<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Pwa\Pwa;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The app shell on the HQ host (docs/PWA.md): manifest, service worker, offline page, the app's files,
 * the connection check and the manifest shortcuts. All but the shortcuts answer without a session and
 * set no cookies (routes/pwa.php), so nothing in them is ever personal.
 */
class PwaController extends Controller
{
    // The monochrome notification badge (Android shows it in the status bar)
    private const BADGE = 'img/badge-96.png';

    // Manifest shortcuts (long press or right click on the app icon): section => [name, short name for
    // narrow menus, icon]. The names are the rail's.
    private const SHORTCUTS = [
        'workspace' => ['My workspace', 'Workspace', 'img/shortcut-workspace.png'],
        'leads' => ['Leads and proposals', 'Leads', 'img/shortcut-leads.png'],
        'projects' => ['Projects', null, 'img/shortcut-projects.png'],
        'finance' => ['Finance', null, 'img/shortcut-finance.png'],
    ];

    // Where a shortcut lands: the first screen of that rail section the person can open, in the rail's
    // order (components/layouts/hq.blade.php). A section they can't open lands on My workspace.
    private const SECTIONS = [
        'leads' => ['crm.view' => 'hq.crm.leads', 'broadcasts.view' => 'hq.crm.broadcast'],
        'projects' => ['projects.view' => 'hq.ops.deployments', 'qa.view' => 'hq.ops.qa', 'timesheets.view' => 'hq.ops.timers', 'signoffs.view' => 'hq.ops.signoffs'],
        'finance' => ['finance.view' => 'hq.finance.bi', 'payroll.view' => 'hq.finance.payroll', 'expenses.view' => 'hq.finance.manager', 'expenses.create' => 'hq.finance.request'],
    ];

    // Pictures of HQ for the richer install dialog in Chrome, Edge and Android: My workspace with fictional demo
    // records, signed in as a test account (never production data). The labels are their alt text.
    private const SCREENSHOTS = [
        ['img/screenshots/hq-wide.png', 'wide', 'My workspace in JBS Praxis HQ on a computer: open tasks, hours this week, proposals awaiting signature, meetings and notifications'],
        ['img/screenshots/hq-narrow.png', 'narrow', 'My workspace in JBS Praxis HQ on a phone: open tasks, hours this week and proposals awaiting signature'],
    ];

    public function manifest()
    {
        $manifest = [
            // The id stays "/" (what the first manifest's start_url gave), so devices that installed HQ before keep the same app
            'id' => '/',
            'name' => config('pwa.name'),
            'short_name' => config('pwa.short_name'),
            'description' => config('pwa.description'),
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => config('pwa.background_color'),
            'theme_color' => config('pwa.theme_color'),
            'lang' => 'en',
            'dir' => 'ltr',
            'categories' => ['business', 'productivity'],
            // The app icons are static files in the web root (brand/kit/icons)
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => $this->shortcuts(),
            // One HQ window: opening the app again (or a shortcut) brings the open one forward
            'launch_handler' => ['client_mode' => ['focus-existing', 'auto']],
            'prefer_related_applications' => false,
        ];
        if ($screenshots = $this->screenshots()) {
            $manifest['screenshots'] = $screenshots;
        }

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function serviceWorker()
    {
        $source = Pwa::file('sw.js');
        abort_unless($source, 404);

        // hq-pwa.js and hq-pwa.css only once they exist: a precache that fails would stop the worker installing
        $precache = ['/offline'];
        foreach (['hq-pwa.js', 'hq-pwa.css', self::BADGE] as $path) {
            if (Pwa::file($path)) {
                $precache[] = Pwa::asset($path);
            }
        }

        $js = strtr(file_get_contents($source), [
            '__PWA_VERSION__' => Pwa::version(),
            '__PWA_PRECACHE__' => json_encode($precache, JSON_UNESCAPED_SLASHES),
            '__PWA_BADGE__' => Pwa::file(self::BADGE) ? Pwa::asset(self::BADGE) : '',
        ]);

        // Never cached: browsers compare this file byte for byte to find a new version of the app
        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline()
    {
        return response()->view('pwa.offline', [], 200, ['Cache-Control' => 'no-cache']);
    }

    public function ping()
    {
        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    public function asset(string $version, string $path)
    {
        // The service worker is only ever served at /sw.js (its scope is the whole host)
        $file = strtolower($path) === 'sw.js' ? null : Pwa::file($path);
        abort_unless($file, 404);

        $response = new BinaryFileResponse($file, 200, ['Content-Type' => Pwa::type($path)], true, null, false, true);
        // A current address never changes. An old one (a page open since before an update) still answers, but is checked every time.
        $response->headers->set('Cache-Control', hash_equals(Pwa::version(), $version) ? 'public, max-age=31536000, immutable' : 'no-cache');

        return $response;
    }

    public function go(Request $request, string $section)
    {
        $user = $request->user();
        $route = collect(self::SECTIONS[$section] ?? [])->first(fn ($route, $permission) => $user->can($permission));

        return redirect()->route($route ?? 'hq.dash.workspace');
    }

    private function shortcuts(): array
    {
        $shortcuts = [];
        foreach (self::SHORTCUTS as $section => [$name, $shortName, $icon]) {
            $shortcut = array_filter(['name' => $name, 'short_name' => $shortName, 'url' => '/pwa/go/' . $section]);
            if (Pwa::file($icon)) {
                $shortcut['icons'] = [['src' => Pwa::asset($icon), 'sizes' => '96x96', 'type' => 'image/png', 'purpose' => 'any']];
            }
            $shortcuts[] = $shortcut;
        }

        return $shortcuts;
    }

    private function screenshots(): array
    {
        $screenshots = [];
        foreach (self::SCREENSHOTS as [$path, $formFactor, $label]) {
            $file = Pwa::file($path);
            $size = $file ? @getimagesize($file) : false;
            if ($size) {
                $screenshots[] = ['src' => Pwa::asset($path), 'sizes' => $size[0] . 'x' . $size[1], 'type' => 'image/png', 'form_factor' => $formFactor, 'label' => $label];
            }
        }

        return $screenshots;
    }
}
