{{-- The app script and its settings: the "client" part (docs/PWA.md). HQ host only (pwa.head). The settings are
     read again after every wire:navigate page change, which appends them to the head when they differ.
     "app" only for a signed-in staff member in the HQ layout: the sign-in and setup pages are "auth" even for
     someone already signed in, so no invitation ever shows there. --}}
@php
    $pwaUser = \Illuminate\Support\Facades\Auth::guard('web')->user();
    $pwaStaff = $pwaUser && \App\Http\Middleware\HqStaff::allows($pwaUser);
    $pwaAuthPage = request()->routeIs('login', 'hq.setup', 'taskforce.*');
    try {
        // Made on first use and kept in pwa_settings: if that fails (no table yet, no OpenSSL), HQ still opens
        $pwaVapidKey = \App\Support\Pwa\VapidKeys::publicKey();
    } catch (\Throwable $e) {
        $pwaVapidKey = null;
    }
    $pwaConfig = [
        'version' => \App\Support\Pwa\Pwa::version(),
        'sw' => route('pwa.sw', absolute: false),
        'page' => $pwaStaff && ! $pwaAuthPage ? 'app' : 'auth',
        'user' => $pwaStaff ? substr(hash('sha256', (string) $pwaUser->getAuthIdentifier()), 0, 16) : null,
        'csrf' => csrf_token(),
        'vapidKey' => $pwaVapidKey,
        'routes' => [
            'subscribe' => route('pwa.push.store', absolute: false),
            'unsubscribe' => route('pwa.push.destroy', absolute: false),
            'test' => route('pwa.push.test', absolute: false),
        ],
        'snoozeDays' => [
            'install' => (int) config('pwa.snooze_days.install', 14),
            'notify' => (int) config('pwa.snooze_days.notify', 14),
        ],
    ];
@endphp
<script id="hq-pwa-config" type="application/json">{!! json_encode($pwaConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) !!}</script>
{{-- Chrome can offer the install before hq-pwa.js runs: keep the offer for the card and the bell menu --}}
<script>window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); window.hqPwaInstallPrompt = e; });</script>
<link rel="stylesheet" href="{{ \App\Support\Pwa\Pwa::asset('hq-pwa.css') }}">
<script src="{{ \App\Support\Pwa\Pwa::asset('hq-pwa.js') }}" defer></script>
