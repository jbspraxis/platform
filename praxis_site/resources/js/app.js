import { createIcons } from 'lucide';
import icons from './icons';

// Icons are written as <i data-lucide="name"> in the views: one pinned copy of lucide (package.json), only
// the icons the views use (scripts/icons.mjs), drawn with a 1.5px stroke (docs/BRAND.md section 9).
const drawIcons = () => createIcons({ icons, attrs: { 'stroke-width': 1.5, 'aria-hidden': 'true' } });

// The assistant's light markdown, as App\Support\ChatText renders it on the server: "- " or "* " bullets,
// **bold** and [text](https://…) links. Built as DOM nodes with textContent, never as HTML.
const CHAT_MARKUP = /\*\*(.+?)\*\*|\[([^[\]]+)\]\((https?:\/\/[^\s()<>"]+)\)/gu;
function chatNodes(text) {
    const source = text.replace(/^[-*] /gmu, '• ');
    const nodes = [];
    let last = 0;
    for (const match of source.matchAll(CHAT_MARKUP)) {
        if (match.index > last) nodes.push(document.createTextNode(source.slice(last, match.index)));
        if (match[3] !== undefined) {
            const link = document.createElement('a');
            link.href = match[3];
            link.rel = 'noopener';
            link.textContent = match[2];
            nodes.push(link);
        } else {
            const strong = document.createElement('strong');
            strong.textContent = match[1];
            nodes.push(strong);
        }
        last = match.index + match[0].length;
    }
    if (last < source.length) nodes.push(document.createTextNode(source.slice(last)));
    return nodes;
}

// A reply's links (docs/SITE_API.md 6.4) shown as buttons: same-site paths only, checked exactly as
// App\Support\ChatLinks::isSitePath checks them on the server, at most 3.
const SITE_PATH = /^\/(?:[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*)?$/;
function chatLinks(links, label) {
    const safe = (Array.isArray(links) ? links : [])
        .filter((link) => link && typeof link.label === 'string' && typeof link.path === 'string'
            && link.label.trim() !== '' && link.label.length <= 80 && link.path.length <= 200 && SITE_PATH.test(link.path))
        .slice(0, 3);
    if (safe.length === 0) return [];
    const group = document.createElement('span');
    group.className = 'chat-links';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-label', label);
    for (const link of safe) {
        const button = document.createElement('a');
        button.className = 'btn btn-secondary btn-sm';
        button.href = link.path;
        button.textContent = link.label.trim();
        group.append(button);
    }
    return [group];
}

// Praxis AI works as a plain form (POST /chat, then back to the page). With JavaScript the reply is
// fetched and added in place. Text is always set with textContent, never as HTML.
function enhanceChat() {
    const form = document.querySelector('[data-chat-form]');
    const log = document.querySelector('[data-chat-log]');
    const status = document.querySelector('[data-chat-status]');
    const details = document.getElementById('chat');
    if (!form || !log || !status) return;

    const textarea = form.querySelector('textarea');
    const button = form.querySelector('button[type="submit"]');
    const scrollDown = () => { log.scrollTop = log.scrollHeight; };

    const addTurn = (role, text, links = []) => {
        const item = document.createElement('li');
        item.className = 'chat-turn ' + (role === 'user' ? 'is-user' : 'is-assistant');
        const who = document.createElement('span');
        who.className = 'who';
        who.textContent = role === 'user' ? form.dataset.you : form.dataset.assistant;
        item.append(who, ...(role === 'user' ? [document.createTextNode(text)] : [...chatNodes(text), ...chatLinks(links, form.dataset.links)]));
        log.append(item);
        scrollDown();
    };

    // Cloudflare Turnstile (only when its keys are set): a token works once, so ask for a fresh one after each question
    const resetCheck = () => {
        const box = form.querySelector('.cf-turnstile');
        if (box && window.turnstile) {
            try { window.turnstile.reset(box); } catch { /* the widget was not drawn */ }
        }
    };

    details?.addEventListener('toggle', () => {
        if (details.open) { scrollDown(); textarea?.focus(); }
    });
    if (details?.open) scrollDown();

    form.addEventListener('submit', async (event) => {
        const message = textarea.value.trim();
        if (message === '') return; // the server answers with the message to type a question first
        event.preventDefault();

        addTurn('user', message);
        textarea.value = '';
        button.disabled = true;
        status.textContent = form.dataset.sending;

        try {
            const body = new FormData(form);
            body.set('chat_message', message);
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body,
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && typeof data.reply === 'string' && data.reply !== '') {
                // HQ's calm limit text (limited: true) is shown like any other reply
                addTurn('assistant', data.reply, data.links);
                status.textContent = '';
            } else {
                // 419: the page's form token expired (the framework's words, not ours); otherwise the site's or HQ's message
                status.textContent = response.status === 419 ? form.dataset.expired : (data.message || form.dataset.failed);
                textarea.value = message;
            }
        } catch {
            status.textContent = form.dataset.failed;
            textarea.value = message;
        } finally {
            button.disabled = false;
            resetCheck();
            textarea.focus();
        }
    });
}

// Show a "something else" field only when its choice is picked (it stays visible without JavaScript)
function toggleOtherFields() {
    document.querySelectorAll('[data-show-when]').forEach((wrapper) => {
        const [name, value] = wrapper.dataset.showWhen.split('=');
        const select = document.querySelector(`[name="${name}"]`);
        if (!select) return;
        const update = () => { wrapper.hidden = select.value !== value; };
        select.addEventListener('change', update);
        update();
    });
}

// The home page's interface loop: plays only when the visitor hasn't asked for reduced motion, and the button
// pauses it (moving content longer than 5 seconds needs a pause control). Without JavaScript the poster shows.
function enhanceLoop() {
    const video = document.querySelector('[data-loop]');
    const toggle = document.querySelector('[data-loop-toggle]');
    if (!video || !toggle) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const show = (playing) => {
        const label = playing ? toggle.dataset.pause : toggle.dataset.play;
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        const icon = document.createElement('i');
        icon.setAttribute('data-lucide', playing ? 'pause' : 'play'); // both listed in pages/home.blade.php for icons.mjs
        toggle.replaceChildren(icon);
        drawIcons();
    };
    toggle.addEventListener('click', () => {
        // Remember a pause the visitor chose, so closing the demo dialog doesn't restart the loop
        if (video.paused) { delete video.dataset.userPaused; video.play().catch(() => {}); } else { video.dataset.userPaused = '1'; video.pause(); }
    });
    video.addEventListener('play', () => show(true));
    video.addEventListener('pause', () => show(false));
    toggle.hidden = false;
    video.play().catch(() => show(false));
}

// The narrated demo (partials.demo-dialog): the link opens a dialog and only then adds YouTube's player, with captions
// on in the page's language; closing removes it, which stops the video. The loop pauses while the dialog is open.
function enhanceDemo() {
    const dialog = document.querySelector('[data-demo-dialog]');
    const frame = dialog?.querySelector('[data-demo-frame]');
    if (!dialog || !frame || typeof dialog.showModal !== 'function') return;
    const loop = document.querySelector('[data-loop]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Every way out runs this (some browsers don't fire the dialog's close event reliably): remove the player,
    // which stops the video, and restart the loop unless the visitor had paused it
    const shut = () => {
        if (dialog.open) dialog.close();
        if (!frame.firstChild) return;
        frame.replaceChildren();
        if (loop && !reduced && !loop.dataset.userPaused) loop.play().catch(() => {});
    };

    document.querySelectorAll('[data-demo-open]').forEach((link) => link.addEventListener('click', (event) => {
        const id = link.dataset.demoOpen;
        if (!/^[A-Za-z0-9_-]{6,20}$/.test(id)) return; // the plain link to YouTube still works
        event.preventDefault();
        const lang = dialog.dataset.lang === 'fr' ? 'fr' : 'en';
        const player = document.createElement('iframe');
        player.src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0&modestbranding=1&cc_load_policy=1&cc_lang_pref=${lang}&hl=${lang}`;
        player.title = dialog.querySelector('h2')?.textContent || 'Demo';
        player.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        player.allowFullscreen = true;
        player.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.replaceChildren(player);
        loop?.pause();
        dialog.showModal();
    }));

    dialog.querySelector('[data-demo-close]')?.addEventListener('click', shut);
    // A click on the backdrop (outside the dialog's box) closes it too
    dialog.addEventListener('click', (event) => { if (event.target === dialog) shut(); });
    dialog.addEventListener('keydown', (event) => { if (event.key === 'Escape') { event.preventDefault(); shut(); } });
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); shut(); });
    dialog.addEventListener('close', shut);
}

// Cookie choice (resources/views/partials/consent.blade.php): Google Analytics loads only after "Accept". The choice is
// kept for 6 months in the jbs_consent cookie; "Cookie settings" in the footer shows the banner again.
const CONSENT_COOKIE = 'jbs_consent';
function readConsent() {
    const match = document.cookie.match(/(?:^|;\s*)jbs_consent=(granted|denied)/);
    return match ? match[1] : null;
}
function saveConsent(value) {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${CONSENT_COOKIE}=${value}; Max-Age=${60 * 60 * 24 * 182}; Path=/; SameSite=Lax${secure}`;
}
function loadAnalytics(id) {
    if (window.__jbsAnalytics || !/^G-[A-Z0-9]{4,20}$/.test(id)) return;
    window.__jbsAnalytics = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', id);
    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.append(script);
}
function forgetAnalytics() {
    // Remove Google Analytics' own cookies (_ga, _ga_…) on this domain and its parent
    const host = location.hostname.replace(/^www\./, '');
    document.cookie.split(';').map((c) => c.trim().split('=')[0]).filter((name) => /^_ga(_|$)/.test(name)).forEach((name) => {
        for (const domain of ['', `; Domain=${host}`, `; Domain=.${host}`]) {
            document.cookie = `${name}=; Max-Age=0; Path=/${domain}`;
        }
    });
}
function enhanceConsent() {
    const banner = document.querySelector('[data-consent]');
    if (!banner) return;
    const id = banner.dataset.ga || '';
    const choice = readConsent();
    if (choice === 'granted') loadAnalytics(id);
    if (choice === null) banner.hidden = false;

    banner.querySelector('[data-consent-accept]')?.addEventListener('click', () => {
        saveConsent('granted');
        banner.hidden = true;
        loadAnalytics(id);
    });
    banner.querySelector('[data-consent-reject]')?.addEventListener('click', () => {
        const wasOn = readConsent() === 'granted';
        saveConsent('denied');
        banner.hidden = true;
        forgetAnalytics();
        if (wasOn) location.reload(); // stop the script that is already running
    });
    document.querySelectorAll('[data-consent-open]').forEach((button) => button.addEventListener('click', () => {
        banner.hidden = false;
        banner.querySelector('[data-consent-accept]')?.focus();
    }));
}

/* ---- Header disclosures (language, menu) ----
 * Plain <details>, so they open without JavaScript; this only closes them on Escape (focus back on the
 * button), on a click outside, and when the other one opens. */
function enhanceHeaderMenus() {
    const menus = [...document.querySelectorAll('details.lang-menu, details.nav-menu')];
    menus.forEach((menu) => menu.addEventListener('toggle', () => {
        if (menu.open) menus.forEach((other) => { if (other !== menu) other.open = false; });
    }));
    document.addEventListener('click', (event) => {
        menus.forEach((menu) => { if (menu.open && !menu.contains(event.target)) menu.open = false; });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const open = menus.find((menu) => menu.open);
        if (!open) return;
        open.open = false;
        open.querySelector('summary')?.focus();
    });
}


/* ---- /verify/{token}: does the reader's own copy match the one that was issued? ----
 * The file is read and hashed here, in the reader's browser (SHA-256, crypto.subtle). It is never
 * uploaded, so their copy of the document never reaches JBS Praxis or anybody else. */
function enhanceVerify() {
    const panel = document.querySelector('[data-verify-check]');
    if (!panel) return;
    const drop = panel.querySelector('[data-verify-drop]');
    const input = panel.querySelector('[data-verify-input]');
    const busy = panel.querySelector('[data-verify-busy]');
    const yours = panel.querySelector('[data-verify-yours]');
    const hashOut = panel.querySelector('[data-verify-hash]');
    const results = [...panel.querySelectorAll('[data-verify-result]')];
    const expected = (panel.dataset.expected || '').toLowerCase();

    const show = (which) => results.forEach((box) => { box.hidden = box.dataset.verifyResult !== which; });

    const check = async (file) => {
        if (!file) return;
        show(null);
        yours.hidden = true;
        busy.hidden = false;
        try {
            const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
            const hash = [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
            hashOut.textContent = hash;
            yours.hidden = false;
            show(hash === expected ? 'same' : 'different');
        } catch {
            show('failed'); // an older browser, or a page served over plain http (crypto.subtle needs https)
        }
        busy.hidden = true;
    };

    input?.addEventListener('change', () => check(input.files[0]));
    ['dragenter', 'dragover'].forEach((name) => drop?.addEventListener(name, (event) => {
        event.preventDefault();
        drop.classList.add('is-over');
    }));
    ['dragleave', 'dragend'].forEach((name) => drop?.addEventListener(name, () => drop.classList.remove('is-over')));
    drop?.addEventListener('drop', (event) => {
        event.preventDefault();
        drop.classList.remove('is-over');
        check(event.dataTransfer?.files[0]);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    drawIcons();
    enhanceHeaderMenus();
    enhanceChat();
    toggleOtherFields();
    enhanceLoop();
    enhanceDemo();
    enhanceConsent();
    enhanceVerify();
});
