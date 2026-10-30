/* HQ as an installable app (docs/PWA.md), the "client" part. It registers the service worker; invites people to
   install HQ and to turn on notifications (one card at a time, never on the sign-in page, never during the launch
   animation); draws the app actions in the bell menu and "This device" in the notification settings panel; keeps
   this device's push subscription in step with the server; sets the app icon badge; and says when HQ is offline.
   Plain JavaScript without a build step: Safari 16.4+ (iPhone, iPad, Mac), Chrome and Edge 110+, Firefox 115+,
   Samsung Internet. It starts once per page load; after each wire:navigate page change it reads its settings
   again and redraws what the new page needs. */
(function () {
    'use strict';

    // Already running (wire:navigate can add a newer copy after a release: the first stays in charge), or not HQ
    if (window.hqPwa || !document.getElementById('hq-pwa-config')) {
        return;
    }

    var PREFIX = 'hq-pwa:';
    var DAY = 86400000;
    var SVG_NS = 'http://www.w3.org/2000/svg';
    var noop = function () {};

    var config = readConfig();

    // The words on screen. HQ's are the English in this file; the Client Portal sends its own in config.strings
    // (its English, which says "Client Portal", or its French). :name placeholders are filled from vars.
    function t(text, vars) {
        var words = config.strings || {};
        var out = Object.prototype.hasOwnProperty.call(words, text) ? words[text] : text;
        if (vars) {
            Object.keys(vars).forEach(function (key) { out = out.split(':' + key).join(vars[key]); });
        }
        return out;
    }

    // "Tap *Share*." becomes ['Tap ', { strong: 'Share' }, '.'] for the install steps
    function rich(text) {
        return text.split(/\*([^*]+)\*/).map(function (part, i) { return i % 2 ? { strong: part } : part; })
            .filter(function (part) { return part !== ''; });
    }

    // The offline page (kept by the service worker) shows itself in the language this device last used
    function rememberLanguage() {
        try {
            if (config.lang) localStorage.setItem('hq-pwa:lang', config.lang);
        } catch (e) { /* private window: the offline page uses the browser's language */ }
    }
    var env = detect();
    var state = {
        standalone: isStandalone(),
        installed: read('installed') === true,
        installPrompt: window.hqPwaInstallPrompt || null, // the browser's install offer (beforeinstallprompt)
        permission: currentPermission(),
        subscribed: false,  // this browser has a subscription that HQ accepted
        endpoint: null,
        endpointHash: null,
        busy: false,        // turning notifications on
        error: null,        // why that failed: 'failed', 'service', 'worker'
        test: null,         // test notification: 'sending', 'sent', 'failed', 'busy'
        offline: false,
        notice: null,       // 'offline' or 'online'
        card: null,         // 'install' or 'notify': the card on screen
        cardResult: null    // what happened in the notifications card: 'on', 'blocked', 'failed'
    };

    // ---- Settings and the device --------------------------------------------------------------------------

    function readConfig() {
        var nodes = document.querySelectorAll('script#hq-pwa-config');
        var latest = nodes[nodes.length - 1];
        // wire:navigate appends each new page's settings and never removes the old ones
        for (var i = 0; i < nodes.length - 1; i++) {
            nodes[i].parentNode.removeChild(nodes[i]);
        }
        var parsed = null;
        try {
            parsed = JSON.parse(latest.textContent);
        } catch (e) { /* unreadable settings: behave as a signed-out page */ }
        parsed = parsed && typeof parsed === 'object' ? parsed : { page: 'auth' };
        parsed.routes = parsed.routes || {};
        parsed.snoozeDays = parsed.snoozeDays || {};
        return parsed;
    }

    function detect() {
        var ua = navigator.userAgent || '';
        // iPadOS reports itself as a Mac; touch gives it away
        var ipad = /iPad/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
        var device = /iPhone|iPod/.test(ua) ? 'iPhone' : ipad ? 'iPad' : /Android/.test(ua) ? 'Android'
            : /CrOS/.test(ua) ? 'ChromeOS' : /Macintosh|Mac OS X/.test(ua) ? 'Mac' : /Windows/.test(ua) ? 'Windows'
            : /Linux/.test(ua) ? 'Linux' : '';
        var browser = /SamsungBrowser/.test(ua) ? 'Samsung Internet' : /EdgA?\/|EdgiOS/.test(ua) ? 'Edge'
            : /OPR\/|OPiOS/.test(ua) ? 'Opera' : /Firefox\/|FxiOS/.test(ua) ? 'Firefox'
            : /CriOS|Chrome\/|Chromium\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : '';
        var ios = device === 'iPhone' || device === 'iPad';
        var safariVersion = browser === 'Safari' ? parseInt((ua.match(/Version\/(\d+)/) || [])[1] || '0', 10) : 0;
        return {
            device: device,
            browser: browser,
            ios: ios,
            mobile: ios || device === 'Android',
            tablet: device === 'iPad' || (device === 'Android' && !/Mobile/.test(ua)),
            platform: ios ? 'ios' : device ? device.toLowerCase() : 'other',
            safariVersion: safariVersion,
            push: !!(window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window
                && 'Notification' in window && window.ServiceWorkerRegistration
                && 'showNotification' in window.ServiceWorkerRegistration.prototype)
        };
    }

    function isStandalone() {
        if (navigator.standalone === true) {
            return true;
        }
        return !!window.matchMedia && ['standalone', 'window-controls-overlay', 'minimal-ui'].some(function (mode) {
            return window.matchMedia('(display-mode: ' + mode + ')').matches;
        });
    }

    function currentPermission() {
        return env.push ? Notification.permission : 'unsupported';
    }

    function isApp() {
        return config.page === 'app' && !!document.querySelector('.app-shell');
    }

    // "this iPhone", "this computer"…
    function thisDevice() {
        if (env.ios) {
            return t('this :device', { device: env.device });
        }
        return env.device === 'Android' ? (env.tablet ? t('this tablet') : t('this phone')) : t('this computer');
    }

    // How the device appears in "Your devices", e.g. "iPhone · HQ app", "Windows · Edge"
    function deviceLabel() {
        var name = env.device || (env.mobile ? t('Phone') : t('Computer'));
        return name + ' · ' + (state.standalone ? t('HQ app') : env.browser || t('Browser'));
    }

    // ---- Storage (per browser; private windows may refuse it, then invitations just show again) -------------

    function read(key, session) {
        try {
            var raw = (session ? window.sessionStorage : window.localStorage).getItem(PREFIX + key);
            return raw === null ? null : JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function write(key, value, session) {
        try {
            var store = session ? window.sessionStorage : window.localStorage;
            if (value === null || value === undefined) {
                store.removeItem(PREFIX + key);
            } else {
                store.setItem(PREFIX + key, JSON.stringify(value));
            }
        } catch (e) { /* nothing to do */ }
    }

    function snoozed(kind) {
        var until = read('snooze-' + kind);
        return typeof until === 'number' && until > Date.now();
    }

    // "Not now": the card stays away for the configured days (config/pwa.php snooze_days)
    function snooze(kind) {
        var days = Number(config.snoozeDays[kind]) || 14;
        write('snooze-' + kind, Date.now() + days * DAY);
    }

    // ---- Small DOM helpers ----------------------------------------------------------------------------------

    function h(tag, attrs, children) {
        var el = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (name) {
            var value = attrs[name];
            if (value === null || value === undefined || value === false) {
                return;
            }
            if (name === 'text') {
                el.textContent = value;
            } else if (name === 'onclick') {
                el.addEventListener('click', value);
            } else {
                el.setAttribute(name, value === true ? '' : String(value));
            }
        });
        (children || []).forEach(function (child) {
            if (child !== null && child !== undefined && child !== false) {
                el.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
            }
        });
        return el;
    }

    // Line icons from the same set as the rest of HQ (lucide, 24px grid, 1.5px stroke)
    var ICONS = {
        bell: ['M10.268 21a2 2 0 0 0 3.464 0', 'M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326'],
        check: ['M20 6 9 17l-5-5'],
        download: ['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'm7 10 5 5 5-5', 'M12 15V3'],
        lock: ['M5 11h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2z', 'M7 11V7a5 5 0 0 1 10 0v4'],
        alert: ['M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0z', 'M12 8v4', 'M12 16h.01'],
        x: ['M18 6 6 18', 'm6 6 12 12'],
        wifiOff: ['M12 20h.01', 'M8.5 16.429a5 5 0 0 1 7 0', 'M5 12.859a10 10 0 0 1 5.17-2.69', 'M19 12.859a10 10 0 0 0-2.007-1.523',
            'M2 8.82a15 15 0 0 1 4.177-2.643', 'M22 8.82a15 15 0 0 0-11.288-3.764', 'm2 2 20 20'],
        share: ['M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8', 'm16 6-4-4-4 4', 'M12 2v13'],
        addSquare: ['M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z', 'M12 8v8', 'M8 12h8'],
        dock: ['M4 3h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z', 'M2 13h20', 'M6 21h12', 'M9 17v4', 'M15 17v4'],
        home: ['M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8', 'M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z']
    };

    function icon(name) {
        var svg = document.createElementNS(SVG_NS, 'svg');
        var attrs = { viewBox: '0 0 24 24', width: '18', height: '18', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.5',
            'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false' };
        Object.keys(attrs).forEach(function (k) { svg.setAttribute(k, attrs[k]); });
        ICONS[name].forEach(function (d) {
            var path = document.createElementNS(SVG_NS, 'path');
            path.setAttribute('d', d);
            svg.appendChild(path);
        });
        return svg;
    }

    function glyph(name, tone) {
        return h('span', { class: 'hqp-glyph' + (tone ? ' ' + tone : ''), 'aria-hidden': 'true' }, [icon(name)]);
    }

    // A brand button. Busy buttons stay focusable (aria-disabled) so keyboard focus survives the redraw.
    function btn(label, style, onClick, action, busy) {
        return h('button', {
            type: 'button',
            class: 'btn btn-' + style + ' btn-sm',
            'data-action': action || null,
            'aria-disabled': busy ? 'true' : null,
            onclick: function (e) {
                if (busy || !onClick) {
                    e.preventDefault();
                    return;
                }
                onClick(e);
            }
        }, [label]);
    }

    // Replace a box's content and keep keyboard focus on the same action (data-action) when it was inside
    function fill(box, nodes) {
        var active = document.activeElement;
        var hadFocus = !!active && active !== box && box.contains(active);
        var action = hadFocus ? active.getAttribute('data-action') : null;
        box.textContent = '';
        nodes.forEach(function (node) {
            if (node) {
                box.appendChild(node);
            }
        });
        if (hadFocus) {
            var target = (action && box.querySelector('[data-action="' + action + '"]')) || box.querySelector('button, [tabindex="-1"]');
            if (target) {
                target.focus({ preventScroll: true });
            }
        }
    }

    function leave(el, done) {
        if (!el || !el.parentNode) {
            if (done) done();
            return;
        }
        if (el.contains(document.activeElement)) {
            document.activeElement.blur();
        }
        var finished = false;
        var finish = function () {
            if (finished) return;
            finished = true;
            if (el.parentNode) el.parentNode.removeChild(el);
            if (done) done();
        };
        el.classList.remove('hqp-enter');
        el.classList.add('hqp-leave');
        el.addEventListener('animationend', finish);
        setTimeout(finish, 400);
    }

    function within(promise, ms) {
        return Promise.race([promise, new Promise(function (resolve) { setTimeout(resolve, ms); })]);
    }

    // The promise, or a rejection with this reason once ms have passed (a push service that never answers
    // must not leave "Turning on…" on screen for good)
    function timeLimit(promise, ms, reason) {
        return Promise.race([promise, new Promise(function (resolve, reject) {
            setTimeout(function () { reject(failure(reason)); }, ms);
        })]);
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrf || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        });
    }

    function failure(reason) {
        var error = new Error(reason);
        error.hqReason = reason;
        return error;
    }

    // ---- Service worker ------------------------------------------------------------------------------------

    function registerWorker() {
        if (!('serviceWorker' in navigator) || !config.sw) {
            return;
        }
        var register = function () {
            navigator.serviceWorker.register(config.sw, { scope: '/', updateViaCache: 'none' }).catch(noop);
        };
        if (document.readyState === 'complete') {
            register();
        } else {
            window.addEventListener('load', register, { once: true });
        }
        navigator.serviceWorker.addEventListener('message', onWorkerMessage);
        if (navigator.serviceWorker.startMessages) {
            navigator.serviceWorker.startMessages();
        }
    }

    // The registration as it is now (no waiting), or null
    function currentRegistration() {
        if (!('serviceWorker' in navigator)) {
            return Promise.resolve(null);
        }
        return navigator.serviceWorker.getRegistration('/').then(function (reg) { return reg || null; }, function () { return null; });
    }

    // An active registration (needed to subscribe), or a rejection when the worker doesn't start
    function readyRegistration() {
        if (!('serviceWorker' in navigator)) {
            return Promise.reject(failure('worker'));
        }
        return timeLimit(navigator.serviceWorker.ready, 10000, 'worker');
    }

    function onWorkerMessage(e) {
        var data = e.data;
        if (!data || typeof data !== 'object' || (e.origin && e.origin !== location.origin)) {
            return;
        }
        if (data.type === 'hq:push') {
            refreshBell();
            if (typeof data.badge === 'number') {
                setBadge(data.badge);
            }
        } else if (data.type === 'hq:navigate' && typeof data.url === 'string') {
            go(data.url);
        }
    }

    // A notification was clicked while HQ was open: change page in place (HQ addresses only)
    function go(url) {
        var target;
        try {
            target = new URL(url, location.href);
        } catch (e) {
            return;
        }
        if (target.origin !== location.origin) {
            return;
        }
        var path = target.pathname + target.search + target.hash;
        if (config.page === 'app' && window.Livewire && typeof window.Livewire.navigate === 'function') {
            window.Livewire.navigate(path);
        } else {
            location.assign(path);
        }
    }

    function refreshBell() {
        if (config.page === 'app' && window.Livewire && typeof window.Livewire.dispatch === 'function') {
            window.Livewire.dispatch('hq-push-received');
        }
    }

    // ---- App icon badge ------------------------------------------------------------------------------------

    function setBadge(count) {
        if (!navigator.setAppBadge) {
            return;
        }
        count = Math.max(0, Math.floor(Number(count) || 0));
        try {
            (count > 0 ? navigator.setAppBadge(count) : navigator.clearAppBadge()).catch(noop);
        } catch (e) { /* not allowed here (Safari needs notification permission) */ }
    }

    // ---- Install ---------------------------------------------------------------------------------------------

    // How this browser installs HQ: 'prompt' (its own dialog), 'ios' (Share, then Add to Home Screen),
    // 'mac' (Safari 17+: File, then Add to Dock), or null (already installed, or no install: Firefox and others)
    function installKind() {
        if (state.standalone || state.installed) {
            return null;
        }
        if (state.installPrompt) {
            return 'prompt';
        }
        if (env.ios && /^(Safari|Chrome|Edge)$/.test(env.browser)) {
            return 'ios';
        }
        if (env.device === 'Mac' && env.browser === 'Safari' && env.safariVersion >= 17) {
            return 'mac';
        }
        return null;
    }

    function onInstallPrompt(e) {
        e.preventDefault(); // our card replaces Chrome's own banner on Android
        state.installPrompt = e;
        state.installed = false;
        write('installed', null);
        update();
    }

    function install() {
        var kind = installKind();
        closeMenu();
        if (kind === 'prompt') {
            var offer = state.installPrompt;
            state.installPrompt = null; // an offer works once
            try {
                var shown = offer.prompt();
                if (shown && typeof shown.catch === 'function') shown.catch(noop);
            } catch (e) {
                update();
                return;
            }
            offer.userChoice.then(function (choice) {
                if (choice && choice.outcome === 'accepted') {
                    state.installed = true;
                    write('installed', true);
                } else {
                    snooze('install');
                }
            }, noop).then(function () {
                if (state.card === 'install') closeCard();
                update();
            });
        } else if (kind === 'ios' || kind === 'mac') {
            snooze('install'); // the bell menu keeps "Install the app" for later
            if (state.card === 'install') closeCard();
            openSheet(kind);
        }
    }

    // ---- Notifications ---------------------------------------------------------------------------------------

    // What this device can do: 'on', 'off' (can be turned on), 'blocked', 'install-first' (iPhone or iPad in a
    // browser tab: push works only in the installed app), 'unsupported'
    function notifyKind() {
        if (!env.push) {
            return env.ios && !state.standalone ? 'install-first' : 'unsupported';
        }
        if (!config.vapidKey) {
            return 'unsupported';
        }
        if (state.permission === 'denied') {
            return 'blocked';
        }
        return state.permission === 'granted' && state.subscribed ? 'on' : 'off';
    }

    // Decision 6: phones and tablets ask in the installed app (on iPhone push only works there), computers after sign-in
    function wantsNotifyCard() {
        if (state.permission !== 'default' || notifyKind() !== 'off' || snoozed('notify')) {
            return false;
        }
        return env.mobile ? state.standalone : true;
    }

    // "Turn on": the browser's own question appears only from this click, asked before anything else so the
    // click still counts as the person's gesture (Safari is strict about that)
    function turnOn() {
        var kind = notifyKind();
        if (state.busy || kind === 'on') {
            return;
        }
        if (kind === 'install-first') {
            openSheet('ios');
            return;
        }
        if (kind !== 'off') {
            return;
        }
        var asking = askPermission();
        write('off', null);
        state.busy = true;
        state.error = null;
        state.test = null;
        update();
        asking.then(function (answer) {
            state.permission = answer;
            if (answer !== 'granted') {
                state.busy = false;
                if (answer === 'default') {
                    // The question was closed without an answer: ask again another day
                    snooze('notify');
                    if (state.card === 'notify') closeCard();
                } else if (state.card === 'notify') {
                    state.cardResult = 'blocked';
                }
                update();
                return;
            }
            subscribe(true).then(function () {
                state.busy = false;
                if (state.card === 'notify') state.cardResult = 'on';
                update();
            }, function (err) {
                state.busy = false;
                state.error = (err && err.hqReason) || 'failed';
                if (state.card === 'notify') state.cardResult = 'failed';
                update();
            });
        });
    }

    // Safari used to take a callback instead of returning a promise: accept both
    function askPermission() {
        return new Promise(function (resolve) {
            var done = function (value) { resolve(value || Notification.permission); };
            try {
                var asked = Notification.requestPermission(done);
                if (asked && typeof asked.then === 'function') {
                    asked.then(done, function () { done(Notification.permission); });
                }
            } catch (e) {
                done(Notification.permission);
            }
        });
    }

    function keyBytes() {
        if (!config.vapidKey) {
            return null;
        }
        try {
            var base64 = config.vapidKey.replace(/-/g, '+').replace(/_/g, '/');
            base64 += '===='.slice((base64.length % 4) || 4);
            var raw = window.atob(base64);
            var bytes = new Uint8Array(raw.length);
            for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
            return bytes.length === 65 ? bytes : null;
        } catch (e) {
            return null;
        }
    }

    // Was this subscription made with HQ's current key? Browsers that don't say: compare with the key used here.
    function sameKey(sub, key) {
        var used = sub.options && sub.options.applicationServerKey;
        if (used && used.byteLength) {
            var a = new Uint8Array(used);
            if (a.length !== key.length) return false;
            for (var i = 0; i < a.length; i++) {
                if (a[i] !== key[i]) return false;
            }
            return true;
        }
        var stored = read('key');
        return !stored || stored === config.vapidKey;
    }

    // Subscribe this browser (or reuse its subscription) and register it with HQ for the signed-in person
    function subscribe(force) {
        var key = keyBytes();
        if (!key) {
            return Promise.reject(failure('failed'));
        }
        return readyRegistration().then(function (reg) {
            return reg.pushManager.getSubscription().then(function (sub) {
                if (sub && !sameKey(sub, key)) {
                    // HQ has a new key pair: the old subscription can't receive from it any more
                    return sub.unsubscribe().catch(noop).then(function () { return null; });
                }
                return sub;
            }).then(function (sub) {
                return sub || timeLimit(reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }), 20000, 'failed');
            });
        }).then(function (sub) {
            state.endpoint = sub.endpoint;
            write('key', config.vapidKey);
            return register(sub, force);
        }, function (err) {
            throw err && err.hqReason ? err : failure('failed');
        });
    }

    // Tell HQ about this subscription: once a session, again when the signed-in person changes, and daily
    function register(sub, force) {
        return hashText(sub.endpoint).then(function (hash) {
            state.endpointHash = hash;
            var mark = [config.user, hash, config.vapidKey, config.lang || ''].join('|');
            var last = read('synced', true);
            if (!force && last && last.mark === mark && Date.now() - last.at < DAY) {
                state.subscribed = true;
                return;
            }
            var json = sub.toJSON ? sub.toJSON() : {};
            var keys = json.keys || {};
            return timeLimit(post(config.routes.subscribe, {
                endpoint: sub.endpoint,
                keys: { p256dh: keys.p256dh, auth: keys.auth },
                contentEncoding: 'aes128gcm',
                standalone: state.standalone,
                device: deviceLabel(),
                locale: config.lang || undefined
            }), 15000, 'failed').then(function (res) {
                if (res.status === 422) {
                    throw failure('service');
                }
                if (!res.ok) {
                    throw failure('failed');
                }
                write('synced', { mark: mark, at: Date.now() }, true);
                state.subscribed = true;
            });
        });
    }

    // On every start with permission already given: quietly make sure HQ has this device for this person
    function resync() {
        if (config.page !== 'app' || notifyKind() === 'unsupported' || state.permission !== 'granted') {
            readEndpoint();
            return;
        }
        if (read('off') === config.user) {
            // This person turned notifications off on this device
            state.subscribed = false;
            readEndpoint().then(update);
            return;
        }
        subscribe(false).then(update, function (err) {
            state.subscribed = false;
            update();
            // The worker was still starting (a slow connection or the first visit): try again once it's ready
            if (err && err.hqReason === 'worker' && 'serviceWorker' in navigator && !resyncWaiting) {
                resyncWaiting = true;
                navigator.serviceWorker.ready.then(function () {
                    resyncWaiting = false;
                    resync();
                });
            }
        });
    }

    var resyncWaiting = false;

    function readEndpoint() {
        return currentRegistration().then(function (reg) {
            return reg && reg.pushManager ? reg.pushManager.getSubscription() : null;
        }).then(function (sub) {
            state.endpoint = sub ? sub.endpoint : null;
            return sub;
        }, function () {
            return null;
        });
    }

    // Notifications off on this device (the settings panel removes "This device"): HQ forgets it, the browser
    // drops the subscription, and the quiet start-up check leaves it off for this person
    function turnOff(options) {
        var forgetOnServer = !(options && options.server === false);
        write('off', config.user);
        write('synced', null, true);
        state.subscribed = false;
        state.test = null;
        update();
        return readEndpoint().then(function (sub) {
            var tasks = [];
            if (sub && forgetOnServer) {
                tasks.push(post(config.routes.unsubscribe, { endpoint: sub.endpoint }).catch(noop));
            }
            if (sub) {
                tasks.push(sub.unsubscribe().catch(noop));
            }
            return Promise.all(tasks);
        }).then(function () {
            state.endpoint = null;
            state.endpointHash = null;
            update();
        });
    }

    function sendTest() {
        if (!state.endpoint || state.test === 'sending') {
            return;
        }
        state.test = 'sending';
        update();
        post(config.routes.test, { endpoint: state.endpoint }).then(function (res) {
            if (res.status === 429) {
                return 'busy';
            }
            return res.json().then(function (data) {
                return res.ok && data && data.ok && data.sent > 0 ? 'sent' : 'failed';
            }, function () {
                return 'failed';
            });
        }, function () {
            return 'failed';
        }).then(function (result) {
            state.test = result;
            update();
        });
    }

    function hashText(text) {
        if (!window.crypto || !window.crypto.subtle || !window.TextEncoder) {
            return Promise.resolve(null);
        }
        return window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buffer) {
            return Array.prototype.map.call(new Uint8Array(buffer), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        }, function () {
            return null;
        });
    }

    // sha256 of this browser's push address (what the server stores as endpoint_hash), or null
    function deviceHash() {
        if (state.endpointHash) {
            return Promise.resolve(state.endpointHash);
        }
        return readEndpoint().then(function (sub) {
            return sub ? hashText(sub.endpoint) : null;
        }).then(function (hash) {
            state.endpointHash = hash || null;
            return state.endpointHash;
        }, function () {
            return null;
        });
    }

    // ---- Signing out: HQ forgets this device for this person before the sign-out happens ---------------------

    function onClick(e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var logoutButton = e.target && e.target.closest ? e.target.closest('[wire\\:click="executeLogout"]') : null;
        if (logoutButton) {
            signOutButton(e, logoutButton);
            return;
        }
        var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!link || (link.target && link.target !== '_self')) {
            return;
        }
        var url;
        try {
            url = new URL(link.href, location.href);
        } catch (err) {
            return;
        }
        if (url.origin === location.origin && /\/logout$/.test(url.pathname)) {
            signOut(e, url.href);
        }
    }

    // The portal's sign-out button: forget this device (at most 2.5 s), then let Livewire sign out
    function signOutButton(e, button) {
        write('synced', null, true);
        setBadge(0);
        if (button.hqpPass || config.page !== 'app' || !env.push || !config.routes.unsubscribe) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        var known = state.endpoint ? Promise.resolve(state.endpoint) : readEndpoint().then(function (sub) { return sub && sub.endpoint; });
        within(known, 1000).then(function (endpoint) {
            if (!endpoint || !window.fetch) return null;
            var form = new FormData();
            form.append('_token', config.csrf || '');
            form.append('endpoint', endpoint);
            return within(fetch(config.routes.unsubscribe, {
                method: 'POST', body: form, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function () { return true; }, function () { return true; }), 2500);
        }).then(null, noop).then(function () {
            button.hqpPass = true;
            button.click();
            button.hqpPass = false;
        });
    }

    function signOut(e, href) {
        write('synced', null, true);
        setBadge(0);
        if (config.page !== 'app' || !env.push || !config.routes.unsubscribe) {
            return; // nothing to forget: the link works as usual
        }
        e.preventDefault();
        var left = false;
        var leaveNow = function () {
            if (left) return;
            left = true;
            location.assign(href);
        };
        var known = state.endpoint ? Promise.resolve(state.endpoint) : readEndpoint().then(function (sub) { return sub && sub.endpoint; });
        within(known, 1000).then(function (endpoint) {
            if (!endpoint) {
                return null;
            }
            // The form the route takes from sendBeacon: the token in the body, so no header is needed
            var form = new FormData();
            form.append('_token', config.csrf || '');
            form.append('endpoint', endpoint);
            if (!window.fetch) {
                if (navigator.sendBeacon) navigator.sendBeacon(config.routes.unsubscribe, form);
                return null;
            }
            // Sent with fetch and answered before the sign-out starts: a beacon still running when the
            // sign-out ends the session could save that session again. At most 2.5 s, then the sign-out goes ahead.
            var controller = window.AbortController ? new AbortController() : null;
            var sent = fetch(config.routes.unsubscribe, {
                method: 'POST', body: form, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller ? controller.signal : undefined
            }).then(function () { return true; }, function () { return true; });
            return within(sent, 2500).then(function (answered) {
                if (!answered && controller) controller.abort();
            });
        }).then(leaveNow, leaveNow);
    }

    // ---- Offline -------------------------------------------------------------------------------------------

    var pingTimer = null;
    var noticeTimer = null;

    function checkConnection() {
        var controller = window.AbortController ? new AbortController() : null;
        var timer = setTimeout(function () { if (controller) controller.abort(); }, 5000);
        return fetch('/pwa/ping', { cache: 'no-store', credentials: 'same-origin', signal: controller ? controller.signal : undefined })
            .then(function (res) { return res.status === 204 || res.ok; }, function () { return false; })
            .then(function (ok) {
                clearTimeout(timer);
                if (ok) goOnline(); else goOffline();
                return ok;
            });
    }

    function goOffline() {
        if (state.offline) {
            return;
        }
        state.offline = true;
        state.notice = 'offline';
        clearTimeout(noticeTimer);
        update();
        clearInterval(pingTimer);
        pingTimer = setInterval(function () {
            if (!document.hidden && navigator.onLine !== false) checkConnection();
        }, 5000);
    }

    function goOnline() {
        if (!state.offline) {
            return;
        }
        state.offline = false;
        clearInterval(pingTimer);
        pingTimer = null;
        state.notice = 'online';
        update();
        refreshBell(); // catch up on what arrived meanwhile
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(function () {
            if (state.notice === 'online') {
                state.notice = null;
                update();
            }
        }, 3000);
    }

    // Livewire requests that can't reach HQ show the notice instead of failing silently or with an error box
    var livewireHooked = false;
    function hookLivewire() {
        if (livewireHooked || !window.Livewire) {
            return;
        }
        livewireHooked = true;
        if (typeof window.Livewire.interceptRequest === 'function') {
            window.Livewire.interceptRequest(function (request) {
                request.onFailure(function () {
                    if (navigator.onLine === false) goOffline(); else checkConnection();
                });
                request.onError(function (error) {
                    if (state.offline || navigator.onLine === false) {
                        error.preventDefault();
                        goOffline();
                    }
                });
            });
        } else if (typeof window.Livewire.hook === 'function') {
            // Livewire 3
            window.Livewire.hook('request', function (request) {
                request.fail(function (failed) {
                    if (state.offline || navigator.onLine === false) {
                        failed.preventDefault();
                        goOffline();
                    }
                });
            });
        }
    }

    // ---- Cards: one at a time, after sign-in, never during the launch animation --------------------------------

    var cardTimer = null;

    function pickCard() {
        if (!isApp()) {
            return null;
        }
        if (installKind() && !snoozed('install')) {
            return 'install';
        }
        return wantsNotifyCard() ? 'notify' : null;
    }

    function cardWanted() {
        if (!isApp()) {
            return false;
        }
        if (state.card === 'install') {
            return !!installKind();
        }
        if (state.card === 'notify') {
            return !!state.cardResult || state.busy || wantsNotifyCard();
        }
        return false;
    }

    // Cards appear when a page starts (never in the middle of someone's work), once the launch animation is gone
    function scheduleCard(delay) {
        clearTimeout(cardTimer);
        whenLaunched(function () {
            clearTimeout(cardTimer);
            cardTimer = setTimeout(function () {
                if (!state.card) {
                    state.card = pickCard();
                    state.cardResult = null;
                }
                update();
            }, delay);
        });
    }

    function whenLaunched(callback) {
        var root = document.documentElement;
        if (!root.classList.contains('hq-launching')) {
            callback();
            return;
        }
        var done = false;
        var observer = null;
        var finish = function () {
            if (done) return;
            done = true;
            document.removeEventListener('hq:launch-done', finish);
            if (observer) observer.disconnect();
            callback();
        };
        document.addEventListener('hq:launch-done', finish);
        if (window.MutationObserver) {
            observer = new MutationObserver(function () {
                if (!root.classList.contains('hq-launching')) finish();
            });
            observer.observe(root, { attributes: true, attributeFilter: ['class'] });
        }
        setTimeout(finish, 10000); // the animation never takes this long
    }

    function closeCard() {
        state.card = null;
        state.cardResult = null;
        update();
    }

    function laterNotify() {
        snooze('notify');
        closeCard();
    }

    function installPitch() {
        if (env.device === 'Android') return t('Open HQ from your Home screen, full screen, like any other app.');
        if (env.device === 'Windows') return t('Open HQ in its own window, from the Start menu or the taskbar.');
        if (env.device === 'Mac') return t('Open HQ in its own window, from the Dock or Launchpad.');
        if (env.device === 'ChromeOS') return t('Open HQ in its own window, from the launcher or the shelf.');
        return t('Open HQ in its own window, like any other app.');
    }

    function errorText(reason) {
        if (reason === 'service') return t('HQ can’t send notifications to this browser. Try Chrome, Edge, Firefox or Safari.');
        if (reason === 'worker') return t('We couldn’t turn on notifications because part of HQ didn’t load. Reload the page and try again.');
        return t('We couldn’t turn on notifications. Check your connection and try again.');
    }

    function cardContent() {
        if (state.card === 'install') {
            var how = installKind();
            return {
                art: h('span', { class: 'hqp-glyph', 'aria-hidden': 'true' }, [h('img', { src: '/icons/icon-192.png', alt: '', width: '40', height: '40' })]),
                title: how === 'ios' ? t('Install HQ on your :device', { device: env.device }) : how === 'mac' ? t('Add HQ to your Dock') : t('Install HQ'),
                text: how === 'ios' ? t('Add HQ to your Home Screen to open it like an app and get notifications.')
                    : how === 'mac' ? t('Open HQ in its own window, straight from your Dock.') : installPitch(),
                actions: [
                    btn(how === 'prompt' ? t('Install the app') : t('Show me how'), 'primary', install, 'install'),
                    btn(t('Not now'), 'ghost', function () { snooze('install'); closeCard(); }, 'later')
                ]
            };
        }
        if (state.cardResult === 'on') {
            return {
                art: glyph('check', 'is-on'),
                focus: 'turn-on',
                title: t('Notifications are on'),
                text: t('HQ notifications now come to :device. Choose what they’re about in Notification settings.', { device: thisDevice() }),
                actions: [
                    btn(t('Notification settings'), 'secondary', function () { closeCard(); openSettings(); }, 'settings'),
                    btn(t('Close'), 'ghost', closeCard, 'close')
                ]
            };
        }
        if (state.cardResult === 'blocked') {
            return {
                art: glyph('lock', 'is-blocked'),
                focus: 'turn-on',
                title: t('Notifications are blocked'),
                text: t('This browser is set to block notifications from HQ. You can allow them in its settings.'),
                actions: [
                    btn(t('Show me how'), 'secondary', function () { closeCard(); openSettings(); }, 'settings'),
                    btn(t('Close'), 'ghost', closeCard, 'close')
                ]
            };
        }
        if (state.cardResult === 'failed' && state.error === 'service') {
            return {
                art: glyph('alert', 'is-blocked'),
                focus: 'turn-on',
                title: t('Notifications aren’t available here'),
                text: errorText('service'),
                actions: [btn(t('Close'), 'ghost', laterNotify, 'close')]
            };
        }
        if (state.cardResult === 'failed') {
            return {
                art: glyph('alert', 'is-blocked'),
                title: t('Turn on notifications'),
                text: errorText(state.error),
                actions: [btn(t('Try again'), 'primary', turnOn, 'turn-on'), btn(t('Not now'), 'ghost', laterNotify, 'later')]
            };
        }
        return {
            art: glyph('bell'),
            title: t('Turn on notifications'),
            text: t('See HQ notifications on :device as they arrive, even when HQ isn’t on screen.', { device: thisDevice() }),
            actions: [
                btn(state.busy ? t('Turning on…') : t('Turn on'), 'primary', turnOn, 'turn-on', state.busy),
                btn(t('Not now'), 'ghost', laterNotify, 'later', state.busy)
            ]
        };
    }

    function renderCard() {
        if (state.card && !cardWanted()) {
            state.card = null;
            state.cardResult = null;
        }
        var box = document.body.querySelector(':scope > .hqp-cards');
        if (!box) {
            if (!isApp()) return;
            // Made before any card, so screen readers announce a card when it arrives. First in the page, so the
            // keyboard reaches it first; it floats in the corner, so nothing on the page moves.
            box = h('div', { class: 'hqp-cards', 'aria-live': 'polite' });
            document.body.insertBefore(box, document.body.firstChild);
        }
        var sig = state.card ? [state.card, installKind(), state.cardResult, state.busy, state.error].join('|') : '';
        if (box.getAttribute('data-sig') === sig) {
            return;
        }
        box.setAttribute('data-sig', sig);
        var old = box.firstElementChild;
        if (!state.card) {
            leave(old, liftNotice);
            return;
        }
        var c = cardContent();
        var card = h('section', { class: 'hqp-card', 'data-kind': state.card, 'aria-labelledby': 'hqp-card-title' }, [
            c.art,
            h('div', { class: 'hqp-card-main' }, [
                h('h2', { class: 'hqp-card-title', id: 'hqp-card-title', tabindex: '-1', 'data-action': c.focus || null, text: c.title }),
                h('p', { class: 'hqp-card-text', text: c.text }),
                h('div', { class: 'hqp-card-actions' }, c.actions)
            ])
        ]);
        if (!old || old.getAttribute('data-kind') !== state.card || old.classList.contains('hqp-leave')) {
            card.classList.add('hqp-enter');
        }
        fill(box, [card]);
        liftNotice();
    }

    // ---- Notices -------------------------------------------------------------------------------------------

    function renderNotice() {
        var box = document.body.querySelector(':scope > .hqp-toasts');
        if (!box) {
            // Made before any notice, so screen readers announce it when it arrives
            box = h('div', { class: 'hqp-toasts', role: 'status' });
            document.body.appendChild(box);
        }
        var sig = state.notice || '';
        if (box.getAttribute('data-sig') === sig) {
            return;
        }
        box.setAttribute('data-sig', sig);
        var old = box.firstElementChild;
        if (!state.notice) {
            leave(old);
            return;
        }
        var online = state.notice === 'online';
        var toast = h('div', { class: 'hqp-toast hqp-enter' + (online ? ' is-online' : '') }, [
            icon(online ? 'check' : 'wifiOff'),
            h('span', { text: online ? t('Back online.') : t('You’re offline. HQ can’t save changes until your connection is back.') })
        ]);
        box.textContent = '';
        box.appendChild(toast);
        liftNotice();
    }

    // Keep the notice clear of a card in the same corner
    function liftNotice() {
        var box = document.body.querySelector(':scope > .hqp-toasts');
        if (!box) return;
        var card = document.querySelector('body > .hqp-cards > .hqp-card:not(.hqp-leave)');
        var lift = 0;
        if (card && box.firstElementChild) {
            box.style.removeProperty('--hqp-lift');
            var a = card.getBoundingClientRect();
            var b = box.getBoundingClientRect();
            if (b.left < a.right && b.right > a.left) {
                lift = Math.ceil(a.height) + 12;
            }
        }
        if (lift) box.style.setProperty('--hqp-lift', lift + 'px'); else box.style.removeProperty('--hqp-lift');
    }

    // ---- The bell menu and the settings panel ----------------------------------------------------------------

    function menuItem(iconName, label, onClick, action, busy) {
        return h('button', {
            type: 'button',
            class: 'hqp-menu-item',
            'data-action': action,
            'aria-disabled': busy ? 'true' : null,
            onclick: function (e) {
                if (busy) {
                    e.preventDefault();
                    return;
                }
                onClick(e);
            }
        }, [icon(iconName), label]);
    }

    function closeMenu() {
        document.querySelectorAll('[data-hq-pwa="bell-actions"]').forEach(function (box) {
            box.dispatchEvent(new CustomEvent('hq-pwa-close-menu', { bubbles: true }));
        });
    }

    function openSettings() {
        closeMenu();
        window.dispatchEvent(new CustomEvent('open-notification-settings'));
    }

    function renderBell() {
        var boxes = document.querySelectorAll('[data-hq-pwa="bell-actions"]');
        if (!boxes.length) return;
        var kind = notifyKind();
        var install = installKind();
        var sig = [kind, state.busy, state.error, install].join('|');
        boxes.forEach(function (box) {
            if (box.getAttribute('data-sig') === sig) return;
            box.setAttribute('data-sig', sig);
            var items = [];
            if (kind === 'off') {
                items.push(menuItem('bell', state.busy ? t('Turning on…') : t('Turn on notifications'), turnOn, 'turn-on', state.busy));
                if (state.error) items.push(h('p', { class: 'hqp-menu-note', text: errorText(state.error) }));
            } else if (kind === 'on') {
                items.push(h('p', { class: 'hqp-menu-item is-status', tabindex: '-1', 'data-action': 'turn-on' }, [icon('check'), t('Notifications are on')]));
            } else if (kind === 'blocked') {
                items.push(menuItem('lock', t('Allow notifications'), openSettings, 'allow'));
            } else if (kind === 'install-first') {
                items.push(menuItem('bell', t('Install the app to get notifications'), function () { openSheet('ios'); }, 'install-first'));
            }
            if (install && kind !== 'install-first') {
                items.push(menuItem('download', t('Install the app'), install, 'install'));
            }
            fill(box, items);
        });
    }

    // Where to allow notifications again, for this browser
    function blockedHelp() {
        var host = location.host;
        if (env.ios) {
            return [t('Open Settings on your :device, then Notifications.', { device: env.device }), t('Tap HQ and turn on Allow Notifications.')];
        }
        if (env.device === 'Android' && state.standalone) {
            return [t('Touch and hold the HQ icon, then tap App info.'), t('Tap Notifications and turn them on.')];
        }
        if (env.device === 'Mac' && env.browser === 'Safari' && state.standalone) {
            return [t('Open System Settings, then Notifications.'), t('Choose HQ and turn on Allow notifications.')];
        }
        if (env.browser === 'Safari') {
            return [t('In Safari, choose Safari > Settings, then Websites.'), t('Select Notifications, find :host and choose Allow.', { host: host })];
        }
        if (env.browser === 'Firefox') {
            return [t('Select the permissions icon to the left of the address bar.'), t('Clear the Blocked setting next to Send notifications.'), t('Reload HQ and turn notifications on.')];
        }
        if (env.browser === 'Samsung Internet') {
            return [t('Open the menu, then Settings > Sites and downloads > Notifications.'), t('Allow :host, then reload HQ.', { host: host })];
        }
        if (env.device === 'Android') {
            return [t('Tap the icon to the left of the address bar, then Permissions.'), t('Turn on Notifications, then reload HQ.')];
        }
        if (state.standalone) {
            return [t('Open the menu at the top of the HQ window, then App info.'), t('Set Notifications to Allow, then reload HQ.')];
        }
        return [t('Select the icon to the left of the address bar.'), t('Set Notifications to Allow, then reload HQ.')];
    }

    function settingsContent() {
        var kind = notifyKind();
        var nodes = [h('p', { class: 'hqp-this-name', text: deviceLabel() })];
        var actions = [];
        if (kind === 'on') {
            nodes.push(h('p', { class: 'hqp-state is-on', tabindex: '-1', 'data-action': 'turn-on' }, [icon('check'), t('Notifications are on for this device.')]));
            actions.push(btn(state.test === 'sending' ? t('Sending…') : t('Send a test notification'), 'secondary', sendTest, 'test', state.test === 'sending'));
            if (config.app === 'portal') {
                actions.push(btn(t('Turn off'), 'ghost', function () { turnOff(); }, 'turn-off'));
            }
        } else if (kind === 'off') {
            nodes.push(h('p', { class: 'hqp-state' }, [icon('bell'), state.busy ? t('Turning on notifications…') : t('Notifications are off for this device.')]));
            actions.push(btn(state.busy ? t('Turning on…') : t('Turn on notifications'), 'primary', turnOn, 'turn-on', state.busy));
        } else if (kind === 'blocked') {
            nodes.push(h('p', { class: 'hqp-state is-blocked', tabindex: '-1', 'data-action': 'turn-on' }, [icon('lock'), t('Notifications are blocked for HQ in this browser. To allow them:')]));
            nodes.push(h('ol', { class: 'hqp-steps-help' }, blockedHelp().map(function (step) { return h('li', { text: step }); })));
        } else if (kind === 'install-first') {
            nodes.push(h('p', { class: 'hqp-state' }, [icon('bell'), t('On :device, HQ can send notifications only from the app on your Home Screen.', { device: env.device })]));
            actions.push(btn(t('Install the app'), 'primary', function () { openSheet('ios'); }, 'install-first'));
        } else if (env.ios && state.standalone) {
            nodes.push(h('p', { class: 'hqp-state' }, [icon('alert'), t('Notifications need iOS 16.4 or later. Update your :device in Settings > General > Software Update.', { device: env.device })]));
        } else {
            nodes.push(h('p', { class: 'hqp-state' }, [icon('alert'), t('This browser can’t show notifications from HQ.')]));
        }
        if (actions.length) {
            nodes.push(h('div', { class: 'hqp-this-actions' }, actions));
        }
        if (kind === 'off' && state.error) {
            nodes.push(h('p', { class: 'hqp-result is-error' }, [icon('alert'), errorText(state.error)]));
        }
        if (kind === 'on' && state.test && state.test !== 'sending') {
            var sent = state.test === 'sent';
            nodes.push(h('p', { class: 'hqp-result ' + (sent ? 'is-ok' : 'is-error') }, [icon(sent ? 'check' : 'alert'),
                sent ? t('Test notification sent. It should appear in a few seconds.')
                    : state.test === 'busy' ? t('You’ve sent several tests in a row. Wait a minute and try again.')
                    : t('We couldn’t send the test notification. Check your connection and try again.')]));
        }
        return [h('div', { class: 'hqp-this' }, nodes)];
    }

    function renderSettings() {
        var boxes = document.querySelectorAll('[data-hq-pwa="settings-device"]');
        if (!boxes.length) return;
        var sig = [notifyKind(), state.busy, state.error, state.test, state.standalone].join('|');
        boxes.forEach(function (box) {
            if (box.getAttribute('data-sig') === sig) return;
            box.setAttribute('data-sig', sig);
            fill(box, settingsContent());
        });
    }

    // ---- Install steps: iPhone and iPad (Share, then Add to Home Screen), Mac Safari (File, then Add to Dock) --

    var sheet = null;

    function openSheet(kind) {
        closeMenu();
        var panel = document.querySelector('.hqp-panel');
        if (panel && panel.getClientRects().length) {
            // The settings panel keeps focus inside it: close it first, then show the steps
            window.dispatchEvent(new CustomEvent('close-notification-settings'));
            setTimeout(function () { showSheet(kind); }, 60);
            return;
        }
        showSheet(kind);
    }

    function step(glyphName, number, parts, hint) {
        var text = h('p', { class: 'hqp-step-text' }, [h('span', { class: 'hqp-step-num', text: number + '. ' })].concat(parts.map(function (part) {
            return typeof part === 'string' ? part : h('strong', { text: part.strong });
        })).concat(hint ? [h('span', { class: 'hqp-step-hint', text: hint })] : []));
        return h('li', { class: 'hqp-step' }, [glyph(glyphName), text]);
    }

    function showSheet(kind) {
        if (sheet) closeSheet();
        var mac = kind === 'mac';
        var shareHint = env.browser === 'Safari' ? t('It’s in Safari’s toolbar. If you don’t see it, tap More (•••) first.')
            : t('It’s next to the address bar or in the browser’s menu.');
        var steps = mac ? [
            step('dock', 1, rich(t('In the menu bar, choose *File*, then *Add to Dock*.'))),
            step('check', 2, rich(t('Select *Add*. HQ then opens in its own window from your Dock.')))
        ] : [
            step('share', 1, rich(t('Tap *Share*.')), shareHint),
            step('addSquare', 2, rich(t('Scroll down and tap *Add to Home Screen*.'))),
            step('home', 3, rich(t('Tap *Add*, then open HQ from your Home Screen and sign in.')))
        ];
        var returnTo = document.activeElement;
        var dialog = h('div', { class: 'hqp-sheet hqp-enter', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'hqp-sheet-title', tabindex: '-1' }, [
            h('div', { class: 'hqp-sheet-head' }, [
                h('h2', { class: 'hqp-sheet-title', id: 'hqp-sheet-title', text: mac ? t('Add HQ to your Dock') : t('Install HQ on your :device', { device: env.ios ? env.device : 'iPhone' }) }),
                h('button', { type: 'button', class: 'hqp-icon-btn', 'aria-label': t('Close the steps'), onclick: function () { closeSheet(); } }, [icon('x')])
            ]),
            h('ol', { class: 'hqp-steps' }, steps),
            mac ? null : h('p', { class: 'hqp-sheet-note', text: t('On iPhone and iPad, HQ can send notifications only from the app on your Home Screen.') }),
            h('div', { class: 'hqp-sheet-actions' }, [btn(t('Close'), 'secondary', function () { closeSheet(); }, 'close')])
        ]);
        var backdrop = h('div', { class: 'hqp-sheet-backdrop hqp-enter', onclick: function () { closeSheet(); } });
        // Everything behind the steps is out of reach until they close
        var hidden = [];
        Array.prototype.forEach.call(document.body.children, function (el) {
            if (!el.inert && el.tagName !== 'SCRIPT') {
                el.inert = true;
                hidden.push(el);
            }
        });
        document.body.appendChild(backdrop);
        document.body.appendChild(dialog);
        var onKey = function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                closeSheet();
            } else if (e.key === 'Tab') {
                var items = Array.prototype.filter.call(dialog.querySelectorAll('button, a[href]'), function (el) { return !el.disabled; });
                if (!items.length) return;
                var first = items[0];
                var last = items[items.length - 1];
                if (e.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };
        document.addEventListener('keydown', onKey, true);
        sheet = { dialog: dialog, backdrop: backdrop, hidden: hidden, onKey: onKey, returnTo: returnTo };
        dialog.focus({ preventScroll: true });
        update();
    }

    function closeSheet() {
        if (!sheet) return;
        var s = sheet;
        sheet = null;
        document.removeEventListener('keydown', s.onKey, true);
        s.hidden.forEach(function (el) { el.inert = false; });
        leave(s.backdrop);
        leave(s.dialog);
        var target = s.returnTo && s.returnTo.isConnected && s.returnTo.offsetParent !== null ? s.returnTo : document.querySelector('[data-hq-bell-button]');
        if (target && target.focus) target.focus({ preventScroll: true });
        update();
    }

    // ---- State -------------------------------------------------------------------------------------------------

    function publicState() {
        return {
            installable: !!installKind(),
            standalone: state.standalone,
            platform: env.platform,
            permission: state.permission,
            subscribed: state.subscribed,
            pushSupported: env.push
        };
    }

    var lastState = '';

    // Redraw everything that shows app state, and tell the page when that state changed (hq-pwa-state)
    function update() {
        if (!state.busy) {
            state.permission = currentPermission();
        }
        renderBell();
        renderSettings();
        renderCard();
        renderNotice();
        var detail = publicState();
        var json = JSON.stringify(detail);
        if (json !== lastState) {
            lastState = json;
            window.dispatchEvent(new CustomEvent('hq-pwa-state', { detail: detail }));
        }
    }

    // ---- Start -------------------------------------------------------------------------------------------------

    window.hqPwa = {
        version: config.version,
        state: publicState,
        turnOn: turnOn,
        turnOff: turnOff,
        install: install,
        sendTest: sendTest,
        deviceHash: deviceHash,
        openSettings: openSettings
    };

    window.addEventListener('beforeinstallprompt', onInstallPrompt);
    window.addEventListener('appinstalled', function () {
        state.installPrompt = null;
        state.installed = true;
        write('installed', true);
        update();
    });
    if (navigator.getInstalledRelatedApps && !state.standalone) {
        navigator.getInstalledRelatedApps().then(function (apps) {
            if (apps && apps.length) {
                state.installed = true;
                update();
            }
        }, noop);
    }

    // The app icon badge follows the bell's exact unread total
    window.addEventListener('hq-unread', function (e) {
        if (e.detail && e.detail.count !== undefined) setBadge(e.detail.count);
    });

    document.addEventListener('click', onClick, true);

    window.addEventListener('offline', goOffline);
    window.addEventListener('online', function () { checkConnection(); });
    // A page change that couldn't reach HQ (Livewire drops the error): check the connection
    window.addEventListener('unhandledrejection', function (e) {
        var reason = e.reason;
        if (reason && reason.name === 'TypeError' && /fetch|network|load failed/i.test(String(reason.message))) {
            checkConnection();
        }
    });
    document.addEventListener('livewire:navigate', function (e) {
        // Offline, a link can't load its page: say so instead of doing nothing (history moves are left alone)
        if ((state.offline || navigator.onLine === false) && !(e.detail && e.detail.history)) {
            e.preventDefault();
            goOffline();
        }
    });
    document.addEventListener('livewire:init', hookLivewire);

    window.addEventListener('resize', liftNotice);

    // Chrome's "Open in app" moves the page into the app window without reloading it
    if (window.matchMedia) {
        var standaloneQuery = window.matchMedia('(display-mode: standalone)');
        if (standaloneQuery.addEventListener) {
            standaloneQuery.addEventListener('change', function () {
                state.standalone = isStandalone();
                update();
            });
        }
    }

    // Permission changed in the browser's settings while HQ is open
    if (env.push && navigator.permissions && navigator.permissions.query) {
        try {
            navigator.permissions.query({ name: 'notifications' }).then(function (status) {
                status.addEventListener('change', function () {
                    if (state.busy) {
                        return; // the answer to "Turn on": that click finishes the job
                    }
                    state.permission = currentPermission();
                    if (state.permission === 'granted') resync(); else state.subscribed = false;
                    update();
                });
            }, noop);
        } catch (e) { /* this browser can't watch the permission: the next page start reads it */ }
    }

    // Opening HQ again (its icon, a shortcut) brings this window forward instead of a second one (manifest
    // launch_handler, focus-existing): go to the screen it was opened for. The app's own start address only
    // brings HQ forward, and the launch that opened this page (a shortcut's redirect included) is already here.
    if (window.launchQueue && typeof window.launchQueue.setConsumer === 'function') {
        var startedAt = Date.now();
        window.launchQueue.setConsumer(function (params) {
            var target;
            try {
                target = new URL(params && params.targetURL, location.href);
            } catch (e) {
                return;
            }
            if (!params || !params.targetURL || target.origin !== location.origin || target.searchParams.get('source') === 'pwa'
                || target.pathname === '/' || target.href === location.href || Date.now() - startedAt < 3000) {
                return;
            }
            go(target.href);
        });
    }

    // Each wire:navigate page change: new body, maybe new settings (the person, the token)
    document.addEventListener('livewire:navigated', function () {
        var before = config.user;
        config = readConfig();
        rememberLanguage();
        state.standalone = isStandalone();
        if (state.cardResult && !state.busy) {
            // "Notifications are on" and the like don't follow people to the next page
            state.card = null;
            state.cardResult = null;
        }
        state.test = null;
        if (sheet && !sheet.dialog.isConnected) {
            document.removeEventListener('keydown', sheet.onKey, true);
            sheet = null;
        }
        if (config.user !== before) {
            state.subscribed = false;
            write('synced', null, true);
            resync();
        }
        update();
        if (!state.card) scheduleCard(800);
    });

    rememberLanguage();
    hookLivewire();
    registerWorker();
    if (navigator.onLine === false) {
        goOffline();
    }
    update();
    resync();
    scheduleCard(1200);
})();
