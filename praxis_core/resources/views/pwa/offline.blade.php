{{-- The HQ offline page (docs/PWA.md). The service worker keeps a copy at install and shows it when a page can't
     be reached. Fully self-contained: inline CSS, the logo and the tab icon inlined, no font or script from the
     network. It checks the connection by itself (when the browser says it's back, and every 5 to 30 seconds)
     and reloads the page that was asked for as soon as HQ answers. Same look as the sign-in page: one card on Mist. --}}
@php
    $logo = file_get_contents(resource_path('pwa/img/logo-horizontal-colour.svg'));
    $icon = 'data:image/svg+xml,' . strtr(str_replace('"', "'", trim(file_get_contents(resource_path('pwa/img/favicon.svg')))), ['%' => '%25', '#' => '%23', '<' => '%3C', '>' => '%3E']);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#0C142B">
<title>You’re offline | JBS Praxis HQ</title>
<link rel="icon" href="{{ $icon }}" type="image/svg+xml">
<style>
    :root {
        --ink: #0C142B; --petrol: #044B6A; --deep-teal: #08707C; --mist: #F2F5F7; --paper: #FFFFFF;
        --line: #D8DEE6; --muted: #5E6679; --slate: #4A5468;
        --motion-instant: 100ms;
    }
    *, *::before, *::after { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; }
    body {
        margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center;
        padding: max(32px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(32px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
        background: var(--mist); color: var(--slate);
        /* The brand face if this device has it; otherwise the app's own fallbacks (nothing is downloaded) */
        font-family: "Plus Jakarta Sans", "Segoe UI", Arial, sans-serif; font-size: 1rem; line-height: 1.625rem;
        -webkit-font-smoothing: antialiased;
    }
    /* Installed on iPhone and iPad the status bar sits over the page in white: give it HQ's Ink behind it */
    body::before { content: ""; position: fixed; top: 0; left: 0; right: 0; height: env(safe-area-inset-top, 0px); background: var(--ink); }
    main { width: 100%; max-width: 420px; background: var(--paper); border: 1px solid var(--line); border-radius: 8px; padding: 32px; }
    .logo { margin: 0 0 24px; }
    .logo svg { display: block; width: 201px; height: 40px; }
    h1 { margin: 0 0 8px; color: var(--ink); font-size: 1.5rem; line-height: 2rem; font-weight: 600; text-wrap: balance; }
    p { margin: 0; }
    .note { margin-top: 12px; color: var(--muted); font-size: 0.875rem; line-height: 1.375rem; }
    .retry {
        display: flex; align-items: center; justify-content: center; width: 100%; min-height: 44px; margin-top: 24px; padding: 9px 16px;
        border: 1px solid transparent; border-radius: 4px; background: var(--deep-teal); color: #fff;
        font: inherit; font-size: 0.875rem; line-height: 1.25rem; font-weight: 500; cursor: pointer;
        transition: background var(--motion-instant);
    }
    .retry:hover { background: var(--petrol); }
    .retry:focus-visible { outline: 2px solid var(--deep-teal); outline-offset: 2px; }
    .status { min-height: 1.125rem; margin-top: 12px; color: var(--muted); font-size: 0.8125rem; line-height: 1.125rem; font-variant-numeric: tabular-nums; }
    .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
    @media (max-width: 400px) {
        main { padding: 24px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .retry { transition: none; }
    }
    @media (forced-colors: active) {
        .retry { border-color: ButtonText; }
    }
</style>
</head>
<body>
<main>
    <div class="logo">{!! $logo !!}</div>
    <h1>You’re offline</h1>
    <p>This device can’t reach HQ right now. Check your Wi-Fi or mobile data. This page reloads by itself as soon as HQ answers.</p>
    <p class="note">Anything you hadn’t saved before the connection dropped may not have reached HQ. Check your last changes when the page reloads.</p>
    <button type="button" class="retry" id="offline-retry">Try again</button>
    <p class="status" id="offline-status" aria-hidden="true"></p>
    <p class="sr-only" id="offline-announce" role="status"></p>
    <noscript><p class="status">Reload this page to try again.</p></noscript>
</main>
<script>
(function () {
    'use strict';

    // Seconds before each automatic check: 5, then 10 and 20, then every 30
    var WAITS = [5, 10, 20, 30];
    var attempt = 0;
    var remaining = 0;
    var ticker = null;
    var checking = false;
    var status = document.getElementById('offline-status');
    var announce = document.getElementById('offline-announce');

    function show(text) {
        if (status.textContent !== text) status.textContent = text;
    }

    // Screen readers hear only what changed after an action, never the countdown
    function tell(text) {
        if (announce.textContent !== text) announce.textContent = text;
    }

    function seconds(n) {
        return n === 1 ? '1 second' : n + ' seconds';
    }

    function schedule(prefix) {
        clearInterval(ticker);
        remaining = WAITS[Math.min(attempt, WAITS.length - 1)];
        attempt += 1;
        show(prefix + 'Next check in ' + seconds(remaining) + '.');
        ticker = setInterval(function () {
            remaining -= 1;
            if (remaining > 0) {
                show(prefix + 'Next check in ' + seconds(remaining) + '.');
            } else {
                check(false, false);
            }
        }, 1000);
    }

    function reconnected() {
        clearInterval(ticker);
        show('Connected. Opening HQ…');
        tell('Connected. Opening HQ.');
        // The page that was asked for; this address on its own goes to HQ's home
        if (location.pathname === '/offline') {
            location.replace('/');
        } else {
            location.reload();
        }
    }

    // byHand: the "Try again" button (says what happened); restart: begin the waits again at 5 seconds
    function check(byHand, restart) {
        if (checking) return;
        checking = true;
        clearInterval(ticker);
        show('Checking the connection…');
        if (byHand) tell('Checking the connection.');

        function done(online) {
            checking = false;
            if (online) {
                reconnected();
                return;
            }
            if (restart) attempt = 0;
            if (byHand) tell('Still offline.');
            schedule('Still offline. ');
        }

        // The browser knows there is no network at all: no request to make
        if (navigator.onLine === false) {
            setTimeout(function () { done(false); }, 400);
            return;
        }
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timeout = setTimeout(function () { if (controller) controller.abort(); }, 8000);
        // Only HQ's own answer counts (a Wi-Fi sign-in page would answer 200)
        fetch('/pwa/ping', { cache: 'no-store', credentials: 'same-origin', signal: controller ? controller.signal : undefined })
            .then(function (response) { return response.status === 204; }, function () { return false; })
            .then(function (online) { clearTimeout(timeout); done(online); });
    }

    document.getElementById('offline-retry').addEventListener('click', function () {
        check(true, true);
    });
    window.addEventListener('online', function () {
        check(false, true);
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') check(false, false);
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) check(false, true);
    });

    // A notification tapped while this page is open: go to its screen
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', function (event) {
            var data = event.data || {};
            if (data.type === 'hq:navigate' && typeof data.url === 'string' && /^\/(?!\/)/.test(data.url)) {
                location.assign(data.url);
            }
        });
    }

    schedule('');
})();
</script>
</body>
</html>
