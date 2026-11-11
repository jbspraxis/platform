{{-- The app script and its settings: the "client" part (docs/PWA.md). HQ host only (pwa.head). The settings are
     read again after every wire:navigate page change, which appends them to the head when they differ.
     "app" only for a signed-in staff member in the HQ layout: the sign-in and setup pages are "auth" even for
     someone already signed in, so no invitation ever shows there. --}}
@php
    // On the Client Portal the same script runs for the signed-in client, on the dashboard only, in their language
    $pwaPortal = ($pwaApp ?? 'hq') === 'portal';
    if ($pwaPortal) {
        $pwaUser = \Illuminate\Support\Facades\Auth::guard('client')->user();
        $pwaMember = $pwaUser && $pwaUser->status === 'Active';
        $pwaAuthPage = ! request()->routeIs('client.dashboard');
    } else {
        $pwaUser = \Illuminate\Support\Facades\Auth::guard('web')->user();
        $pwaMember = $pwaUser && \App\Http\Middleware\HqStaff::allows($pwaUser);
        $pwaAuthPage = request()->routeIs('login', 'hq.setup', 'taskforce.*');
    }
    $pwaRoute = fn (string $name) => route(($pwaPortal ? 'portal.pwa.' : 'pwa.') . $name, absolute: false);
    $pwaLang = app()->getLocale() === 'fr' ? 'fr' : 'en';
    try {
        // Made on first use and kept in pwa_settings: if that fails (no table yet, no OpenSSL), the page still opens
        $pwaVapidKey = \App\Support\Pwa\VapidKeys::publicKey();
    } catch (\Throwable $e) {
        $pwaVapidKey = null;
    }
    $pwaConfig = [
        'app' => $pwaPortal ? 'portal' : 'hq',
        'lang' => $pwaPortal ? $pwaLang : 'en',
        'version' => \App\Support\Pwa\Pwa::version(),
        'sw' => $pwaRoute('sw'),
        'page' => $pwaMember && ! $pwaAuthPage ? 'app' : 'auth',
        'user' => $pwaMember ? substr(hash('sha256', ($pwaPortal ? 'client:' : '') . $pwaUser->getAuthIdentifier()), 0, 16) : null,
        'csrf' => csrf_token(),
        'vapidKey' => $pwaVapidKey,
        'routes' => [
            'subscribe' => $pwaRoute('push.store'),
            'unsubscribe' => $pwaRoute('push.destroy'),
            'test' => $pwaRoute('push.test'),
        ],
        'snoozeDays' => $pwaPortal
            ? ['install' => (int) config('pwa.snooze_days.portal', 3), 'notify' => (int) config('pwa.snooze_days.portal', 3)]
            : ['install' => (int) config('pwa.snooze_days.install', 14), 'notify' => (int) config('pwa.snooze_days.notify', 14)],
        // The portal's words for the script (its English says "Client Portal"; or its French). HQ uses the script's own.
        'strings' => $pwaPortal ? (array) __('portal.pwa.js', [], $pwaLang) : new \stdClass(),
    ];
@endphp
<script id="hq-pwa-config" type="application/json">{!! json_encode($pwaConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) !!}</script>
{{-- Chrome can offer the install before hq-pwa.js runs: keep the offer for the card and the bell menu --}}
<script>window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); window.hqPwaInstallPrompt = e; });</script>
<script src="{{ \App\Support\Pwa\Pwa::asset('hq-pwa.js') }}" defer></script>
