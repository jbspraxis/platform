/*
 * The JBS Praxis HQ service worker (docs/PWA.md, "Service worker"). PwaController@serviceWorker serves this
 * file at /sw.js with the app version, the files to keep and the badge address filled in.
 *
 * - Pages are never stored. Navigations always go to the network; only when the network fails does the
 *   offline page (kept at install) answer. No client, finance or personal data is ever kept on a device.
 * - A few static files (the compiled CSS and JS, Livewire's script, brand images, app icons) are kept in
 *   a small cache, so HQ opens faster.
 * - Push: shows each bell notification, sets the app icon badge and tells open HQ windows. A click on a
 *   notification brings HQ forward on the right screen.
 *
 * Plain JavaScript, no imports: Safari 16.4+ (iPhone and iPad), Chrome and Edge 110+, Firefox 115+, Samsung Internet.
 */
'use strict';

const VERSION = '__PWA_VERSION__';
const PRECACHE = __PWA_PRECACHE__;
const BADGE = '__PWA_BADGE__';

const SHELL_CACHE = 'hq-shell-' + VERSION;
const STATIC_CACHE = 'hq-static-v1';
const STATIC_LIMIT = 80;
const OFFLINE_URL = '/offline';
const ICON = '/icons/icon-192.png';
// The app's own words: HQ or the Client Portal (PwaController@serviceWorker)
const APP = __PWA_APP__;
const DEFAULT_TITLE = APP.title;

// Livewire's own script (/livewire-<key>/livewire.min.js?id=<build>); everything else under /livewire* is left alone
const LIVEWIRE_SCRIPT = /^\/livewire-[^/]+\/livewire(\.csp)?(\.min)?\.js$/;

// Shown only if the kept offline page is missing (storage full or cleared). Like the real one, it reloads by
// itself when the browser is back online or HQ answers again.
const FALLBACK_PAGE = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
    + '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#0C142B">'
    + '<title>You’re offline | ' + APP.name + '</title></head>'
    + '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#F2F5F7;color:#4A5468;font:16px/26px &quot;Plus Jakarta Sans&quot;,&quot;Segoe UI&quot;,Arial,sans-serif">'
    + '<main style="max-width:420px;padding:32px 16px"><h1 style="margin:0 0 8px;color:#0C142B;font-size:24px;line-height:32px;font-weight:600">You’re offline</h1>'
    + '<p style="margin:0 0 24px">This device can’t reach ' + APP.short + ' right now. Check your Wi-Fi or mobile data. This page reloads by itself as soon as it answers.</p>'
    + '<button type="button" onclick="location.reload()" style="min-height:44px;padding:9px 16px;border:0;border-radius:4px;background:#08707C;color:#fff;font:inherit;font-weight:500;cursor:pointer">Try again</button>'
    + '</main><script>(function () { var reload = function () { location.reload(); };'
    + ' addEventListener("online", reload);'
    + ' setInterval(function () { fetch("/pwa/ping", { cache: "no-store" }).then(function (r) { if (r.status === 204) reload(); }, function () {}); }, 15000); })();</script>'
    + '</body></html>';

// ---------------------------------------------------------------------------------------------------------
// Install and activate
// ---------------------------------------------------------------------------------------------------------

self.addEventListener('install', (event) => {
    // Chrome and Edge 123+: every POST (each Livewire update among them) goes straight to the network, so the
    // browser no longer has to wake this worker only for it to step aside. Other browsers skip this, and a
    // browser that refuses the rule loses nothing: the fetch handler below lets POSTs through anyway.
    if (event.addRoutes) {
        try {
            event.waitUntil(event.addRoutes([{ condition: { requestMethod: 'POST' }, source: 'network' }]).catch(() => {}));
        } catch (error) {
            // Refused: as above
        }
    }
    event.waitUntil((async () => {
        try {
            const cache = await caches.open(SHELL_CACHE);
            // 'reload' skips the browser's HTTP cache, so every kept copy belongs to this version
            await cache.addAll(PRECACHE.map((url) => new Request(url, { cache: 'reload' })));
        } catch (error) {
            // A file that didn't download (a TypeError) stops the install: the browser tries again later, so the
            // worker never runs half-ready. Storage that is full or unavailable (a DOMException) doesn't:
            // notifications still work, and a page that can't load shows the built-in offline page above.
            if (error instanceof TypeError) {
                throw error;
            }
        }
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        try {
            // Earlier versions of the shell (and any other cache of ours this version doesn't use)
            const keys = await caches.keys();
            await Promise.all(keys
                .filter((key) => key.startsWith('hq-') && key !== SHELL_CACHE && key !== STATIC_CACHE)
                .map((key) => caches.delete(key)));
        } catch (error) {
            // Storage unavailable: nothing to tidy, and the worker still takes over below
        }
        if (self.registration.navigationPreload) {
            // The page request starts while the worker wakes up
            await self.registration.navigationPreload.enable().catch(() => {});
        }
        await self.clients.claim();
    })());
});

// ---------------------------------------------------------------------------------------------------------
// Requests
// ---------------------------------------------------------------------------------------------------------

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    // A request the browser itself can only answer from its cache (developer tools)
    if (request.cache === 'only-if-cached' && request.mode !== 'same-origin') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        if (!leftAlone(url.pathname)) {
            event.respondWith(navigate(event));
        } else if (event.preloadResponse) {
            // Left to the browser, but the navigation preload has already asked the server: hand that answer
            // over unchanged instead of making the same request twice (a failure stays the browser's own error)
            event.respondWith(preloaded(event).then((response) => response || fetch(request)));
        }
        return;
    }

    // Partial requests (audio and video) stay with the browser
    if (leftAlone(url.pathname) || request.headers.has('range')) {
        return;
    }
    if (url.pathname.startsWith('/pwa/v/' + VERSION + '/')) {
        event.respondWith(fromShell(request));
        return;
    }
    const rule = staticRule(url);
    if (rule === 'keep') {
        event.respondWith(cacheFirst(event, request));
    } else if (rule === 'refresh') {
        event.respondWith(staleWhileRevalidate(event, request));
    }
});

/** Addresses the worker never answers: sign-in and account setup, private files, push, the tracker (/taskforce). */
function leftAlone(path) {
    if (path.startsWith('/livewire')) {
        return !LIVEWIRE_SCRIPT.test(path);
    }

    return path === '/sw.js' || path === '/up' || path === '/login' || path === '/logout' || path === '/pwa/ping'
        || path.startsWith('/pwa/push/') || path.startsWith('/pwa/open/') || path.startsWith('/files/')
        || path.startsWith('/setup/') || path.startsWith('/taskforce');
}

/**
 * Static files kept in hq-static-v1. 'keep': the address changes with the content (Vite's hashed files,
 * Livewire's script with its build id), so a kept copy is always right. 'refresh': brand images and app
 * icons keep their address, so the kept copy answers at once and the network refreshes it for next time.
 */
function staticRule(url) {
    const path = url.pathname;
    if (path.startsWith('/build/assets/')) {
        return 'keep';
    }
    if (LIVEWIRE_SCRIPT.test(path)) {
        return url.searchParams.has('id') ? 'keep' : 'refresh';
    }
    if (path.startsWith('/assets/') || path.startsWith('/icons/')) {
        return 'refresh';
    }

    return null;
}

async function navigate(event) {
    try {
        return (await preloaded(event)) || (await fetch(event.request));
    } catch (error) {
        return offlinePage();
    }
}

/** The navigation preload answer, or undefined when there is none or it failed (fetch() then tries once more). */
async function preloaded(event) {
    try {
        return await event.preloadResponse;
    } catch (error) {
        return undefined;
    }
}

async function offlinePage() {
    try {
        const cache = await caches.open(SHELL_CACHE);
        // ignoreVary: a host that adds Vary (Accept-Encoding, User-Agent) never hides the kept copy
        const page = await cache.match(OFFLINE_URL, { ignoreVary: true });
        if (page) {
            // Safari refuses a redirected response as the answer to a navigation
            return page.redirected ? new Response(await page.blob(), { status: 200, headers: page.headers }) : page;
        }
    } catch (error) {
        // Storage unavailable: the page below still explains what happened
    }

    return new Response(FALLBACK_PAGE, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } });
}

async function fromShell(request) {
    try {
        const cache = await caches.open(SHELL_CACHE);
        const kept = await cache.match(request, { ignoreVary: true });
        if (kept) {
            return kept;
        }
    } catch (error) {
        // Storage unavailable: ask the network
    }

    return fetch(request);
}

async function cacheFirst(event, request) {
    const cache = await openStatic();
    const kept = await lookUp(cache, request);
    if (kept) {
        return kept;
    }
    const response = await fetch(request);
    if (cache && storable(response)) {
        extend(event, store(cache, request, response.clone()));
    }

    return response;
}

async function staleWhileRevalidate(event, request) {
    const cache = await openStatic();
    const kept = await lookUp(cache, request);
    const network = fetch(request).then((response) => {
        if (cache && storable(response)) {
            extend(event, store(cache, request, response.clone()));
        }
        return response;
    });
    if (!kept) {
        return network;
    }
    extend(event, network.catch(() => {}));

    return kept;
}

/** Keep the worker awake for a cache write that ends after the answer; never fails the answer itself. */
function extend(event, promise) {
    try {
        event.waitUntil(promise);
    } catch (error) {
        // The event is already over: the write still runs, nothing waits for it
    }
}

async function openStatic() {
    try {
        return await caches.open(STATIC_CACHE);
    } catch (error) {
        return null;
    }
}

async function lookUp(cache, request) {
    try {
        return cache ? await cache.match(request) : undefined;
    } catch (error) {
        return undefined;
    }
}

/** Only complete, same-origin, cacheable answers, and never a page. */
function storable(response) {
    if (!response || response.status !== 200 || response.type !== 'basic') {
        return false;
    }
    const control = response.headers.get('Cache-Control') || '';
    const type = response.headers.get('Content-Type') || '';
    const vary = response.headers.get('Vary') || '';

    return !/no-store|private/i.test(control) && !/text\/html/i.test(type) && !vary.includes('*');
}

let trimming = Promise.resolve();

async function store(cache, request, response) {
    try {
        await cache.put(request, response);
    } catch (error) {
        // Storage full or refused: the file still reached the page, it just isn't kept
        return;
    }
    // At most STATIC_LIMIT files, the oldest go first (one trim at a time)
    trimming = trimming.then(async () => {
        const keys = await cache.keys();
        for (let i = 0; i < keys.length - STATIC_LIMIT; i++) {
            await cache.delete(keys[i]);
        }
    }).catch(() => {});

    await trimming;
}

// ---------------------------------------------------------------------------------------------------------
// Push notifications
// ---------------------------------------------------------------------------------------------------------

self.addEventListener('push', (event) => {
    event.waitUntil(showPush(readPush(event)));
});

function readPush(event) {
    if (!event.data) {
        return {};
    }
    try {
        const data = event.data.json();
        return data && typeof data === 'object' ? data : {};
    } catch (error) {
        // Not JSON (a test message from the browser's developer tools): the text is the message
        try {
            return { body: event.data.text() };
        } catch (ignored) {
            return {};
        }
    }
}

async function showPush(data) {
    const id = text(data.id, 100) || null;
    const url = hqPath(data.url);
    const badge = Number.isInteger(data.badge) && data.badge >= 0 ? data.badge : null;
    const options = {
        body: text(data.body, 500),
        icon: ICON,
        data: { url: url, id: id },
        timestamp: Number.isFinite(data.ts) ? data.ts : Date.now(),
        lang: 'en',
        dir: 'ltr',
    };
    if (BADGE) {
        options.badge = BADGE;
    }
    const tag = text(data.tag, 100);
    if (tag) {
        options.tag = tag;
    }

    try {
        await self.registration.showNotification(text(data.title, 120) || DEFAULT_TITLE, options);
    } catch (error) {
        // Notifications were switched off for HQ since: the bell and the badge still update
    }
    await Promise.all([
        setBadge(badge),
        tellWindows({ type: 'hq:push', id: id, category: text(data.category, 32) || null, badge: badge }),
    ]);
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const data = event.notification.data || {};
    event.waitUntil(openHq(hqPath(data.url)));
});

/** Bring an open HQ window forward and let its page go to the screen; with none open, open one. */
async function openHq(path) {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const hq = windows.filter((client) => isHqWindow(client.url));
    // matchAll lists the most recently focused window first
    const target = hq.find((client) => client.focused) || hq.find((client) => client.visibilityState === 'visible') || hq[0];
    if (target) {
        try {
            await target.focus();
        } catch (error) {
            // Not allowed to take focus: the page still moves to the screen
        }
        target.postMessage({ type: 'hq:navigate', url: path });
        return;
    }
    if (self.clients.openWindow) {
        await self.clients.openWindow(path);
    }
}

/** An HQ screen (a window on a private file, an app file or the tracker at /taskforce isn't one). */
function isHqWindow(address) {
    try {
        const url = new URL(address);
        return url.origin === self.location.origin && !url.pathname.startsWith('/files/') && !url.pathname.startsWith('/taskforce')
            && !url.pathname.startsWith('/pwa/v/') && url.pathname !== '/sw.js' && url.pathname !== '/manifest.webmanifest'
            && url.pathname !== '/pwa/ping';
    } catch (error) {
        return false;
    }
}

self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        // The browser made a new subscription itself (Firefox), or there is no key to ask with: the page
        // sends this device's subscription to HQ again on its next start
        const old = event.oldSubscription;
        const key = old && old.options ? old.options.applicationServerKey : null;
        if (event.newSubscription || !key) {
            return;
        }
        try {
            await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
        } catch (error) {
            // Permission withdrawn or push unavailable: nothing to renew
        }
    })());
});

async function setBadge(count) {
    if (count === null || !self.navigator || !('setAppBadge' in self.navigator)) {
        return;
    }
    try {
        await (count > 0 ? self.navigator.setAppBadge(count) : self.navigator.clearAppBadge());
    } catch (error) {
        // Badges unsupported here (not installed, or the platform has none)
    }
}

async function tellWindows(message) {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    windows.forEach((client) => client.postMessage(message));
}

/** An address on this host only (a push can never open another site); anything else is HQ's home. */
function hqPath(value) {
    try {
        const url = new URL(typeof value === 'string' && value !== '' ? value : '/', self.location.origin);
        return url.origin === self.location.origin ? url.pathname + url.search + url.hash : '/';
    } catch (error) {
        return '/';
    }
}

function text(value, max) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        value = String(value);
    }

    return typeof value === 'string' ? value.trim().slice(0, max) : '';
}

// ---------------------------------------------------------------------------------------------------------
// Messages from HQ pages
// ---------------------------------------------------------------------------------------------------------

// {type: 'hq:version'} → {type: 'hq:version', version}, on the MessageChannel port if one was sent, else to the page
self.addEventListener('message', (event) => {
    const data = event.data;
    if (!data || data.type !== 'hq:version') {
        return;
    }
    const reply = { type: 'hq:version', version: VERSION };
    if (event.ports && event.ports[0]) {
        event.ports[0].postMessage(reply);
    } else if (event.source) {
        event.source.postMessage(reply);
    }
});
