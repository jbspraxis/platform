import { createIcons } from 'lucide';
import icons from './icons';
import { startMotion } from './motion/index';

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

// Ask Praxis (Praxis AI) works as a plain form (POST /chat, then back to the page). With JavaScript it feels like a
// conversation (redesign PR 2): Enter sends at once and the question appears straight away, a typing indicator holds
// the place, then the reply writes itself in, word by word (all at once with reduced motion). Text is always set
// with textContent, never as HTML. The hero's question box hands its question over with window.praxisAsk().
const REDUCED = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Wrap each word of the reply's text nodes in a span that fades in after the one before (at most 160 animated)
function writeIn(item) {
    if (REDUCED()) return;
    let index = 0;
    const walker = document.createTreeWalker(item, NodeFilter.SHOW_TEXT, {
        acceptNode: (node) => (node.parentElement?.closest('.who, .chat-links') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
    });
    const texts = [];
    while (walker.nextNode()) texts.push(walker.currentNode);
    for (const node of texts) {
        const fragment = document.createDocumentFragment();
        for (const part of node.textContent.split(/(\s+)/)) {
            if (part === '' || /^\s+$/.test(part) || index > 160) { fragment.append(part); continue; }
            const word = document.createElement('span');
            word.className = 'chat-word';
            word.style.setProperty('--i', String(index++));
            word.textContent = part;
            fragment.append(word);
        }
        node.replaceWith(fragment);
    }
    item.querySelector('.chat-links')?.style.setProperty('--i', String(Math.min(index, 160) + 4));
}

function enhanceChat() {
    const form = document.querySelector('[data-chat-form]');
    const log = document.querySelector('[data-chat-log]');
    const status = document.querySelector('[data-chat-status]');
    const details = document.getElementById('chat');
    if (!form || !log || !status) return;

    const textarea = form.querySelector('textarea');
    const button = form.querySelector('button[type="submit"]');
    const behavior = (smooth) => (smooth && !REDUCED() ? 'smooth' : 'instant');
    const scrollDown = (smooth = true) => log.scrollTo({ top: log.scrollHeight, behavior: behavior(smooth) });
    // A reply is read from its first line: bring its top to the top of the log, not the log's end
    const scrollToTurn = (item, smooth = true) => {
        const top = log.scrollTop + item.getBoundingClientRect().top - log.getBoundingClientRect().top - (parseFloat(getComputedStyle(log).paddingTop) || 0);
        log.scrollTo({ top: Math.max(0, top), behavior: behavior(smooth) });
    };
    // On opening: the latest reply from its start (or the end, when the last turn is a question)
    const showLatest = (smooth = false) => {
        const last = log.lastElementChild;
        if (last?.classList.contains('is-assistant')) scrollToTurn(last, smooth);
        else scrollDown(smooth);
    };
    let busy = false;

    const addTurn = (role, text, links = [], animate = false) => {
        const item = document.createElement('li');
        item.className = 'chat-turn ' + (role === 'user' ? 'is-user' : 'is-assistant');
        const who = document.createElement('span');
        who.className = 'who';
        who.textContent = role === 'user' ? form.dataset.you : form.dataset.assistant;
        item.append(who, ...(role === 'user' ? [document.createTextNode(text)] : [...chatNodes(text), ...chatLinks(links, form.dataset.links)]));
        if (animate) writeIn(item);
        log.append(item);
        if (role === 'user') scrollDown();
        else scrollToTurn(item);
        return item;
    };

    // The typing indicator: three dots in the assistant's place (screen readers hear "Praxis AI is writing")
    const typing = () => {
        const item = document.createElement('li');
        item.className = 'chat-turn is-assistant is-typing';
        const who = document.createElement('span');
        who.className = 'who';
        who.textContent = form.dataset.assistant;
        const dots = document.createElement('span');
        dots.className = 'chat-dots';
        dots.setAttribute('role', 'img');
        dots.setAttribute('aria-label', form.dataset.typing);
        dots.append(document.createElement('span'), document.createElement('span'), document.createElement('span'));
        item.append(who, dots);
        log.append(item);
        scrollDown();
        return item;
    };

    // The box grows with the question, up to about six lines
    const grow = () => {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 168) + 'px';
    };
    textarea.addEventListener('input', grow);
    // Enter sends; Shift and Enter (or Enter while an input method is composing) makes a new line
    textarea.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    // Cloudflare Turnstile (only when its keys are set): a token works once, so ask for a fresh one after each question
    const resetCheck = () => {
        const box = form.querySelector('.cf-turnstile');
        if (box && window.turnstile) {
            try { window.turnstile.reset(box); } catch { /* the widget was not drawn */ }
        }
    };

    // The × in the panel's corner, and Escape, close it and hand focus back to the launcher
    const close = () => {
        if (!details?.open) return;
        details.open = false;
        details.querySelector('summary')?.focus();
    };
    // The (i) beside the title: a tap or a keypress toggles the notice; a tap elsewhere or Escape hides it
    const info = details?.querySelector('[data-chat-info]');
    const infoButton = info?.querySelector('[data-chat-info-button]');
    const setInfo = (open) => {
        if (!info) return false;
        const was = info.classList.contains('is-open');
        info.classList.toggle('is-open', open);
        infoButton.setAttribute('aria-expanded', String(open));
        return was;
    };
    if (info && infoButton) {
        info.classList.add('is-ready');
        infoButton.addEventListener('click', () => setInfo(!info.classList.contains('is-open')));
        document.addEventListener('pointerdown', (event) => {
            if (!info.contains(event.target)) setInfo(false);
        });
    }

    const closer = details?.querySelector('[data-chat-close]');
    if (closer) {
        closer.hidden = false;
        closer.addEventListener('click', close);
    }
    details?.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        if (setInfo(false)) infoButton.focus(); // the notice first, then the panel
        else close();
    });

    details?.addEventListener('toggle', () => {
        if (details.open) { showLatest(); textarea?.focus(); }
        else setInfo(false);
    });
    if (details?.open) showLatest();

    form.addEventListener('submit', async (event) => {
        const message = textarea.value.trim();
        if (message === '') return; // the server answers with the message to type a question first
        event.preventDefault();
        if (busy) return;
        busy = true;

        addTurn('user', message);
        textarea.value = '';
        grow();
        button.disabled = true;
        status.textContent = '';
        const waiting = typing();

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
            waiting.remove();
            if (response.ok && typeof data.reply === 'string' && data.reply !== '') {
                // HQ's calm limit text (limited: true) is shown like any other reply
                addTurn('assistant', data.reply, data.links, true);
            } else {
                // 419: the page's form token expired (the framework's words, not ours); otherwise the site's or HQ's message
                status.textContent = response.status === 419 ? form.dataset.expired : (data.message || form.dataset.failed);
                textarea.value = message;
                grow();
            }
        } catch {
            waiting.remove();
            status.textContent = form.dataset.failed;
            textarea.value = message;
            grow();
        } finally {
            busy = false;
            button.disabled = false;
            resetCheck();
            textarea.focus();
        }
    });

    // The hero's question box (pages/home.blade.php) asks here: open the panel, then send
    window.praxisAsk = (question) => {
        if (details && !details.open) details.open = true;
        textarea.value = question;
        grow();
        form.requestSubmit();
    };
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
        if (loop && !reduced && !loop.dataset.userPaused && loop.dataset.wasPlaying) loop.play().catch(() => {});
        if (loop) delete loop.dataset.wasPlaying;
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
        if (loop && !loop.paused) { loop.dataset.wasPlaying = '1'; loop.pause(); }
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

// Small images that must always appear (the footer logo) stay lazy, so they never compete with the first screen,
// then load once the page has: some browsers never fire a footer image's lazy load when the page is captured whole
function loadAfterPage() {
    const go = () => document.querySelectorAll('img[data-load-after-page][loading="lazy"]').forEach((img) => { img.loading = 'eager'; });
    if (document.readyState === 'complete') go(); else window.addEventListener('load', go, { once: true });
}

// The sticky header (redesign PR 3) slims once the page has scrolled: a sentinel at the top of the page tells when
function enhanceHeaderStick() {
    const header = document.querySelector('.site-header');
    if (!header || !('IntersectionObserver' in window)) return;
    const sentinel = document.createElement('div');
    sentinel.setAttribute('aria-hidden', 'true');
    sentinel.className = 'header-sentinel';
    document.body.prepend(sentinel);
    new IntersectionObserver(([entry]) => header.classList.toggle('is-stuck', !entry.isIntersecting)).observe(sentinel);
}

// The logo strip (partials.clients): its button pauses and plays the drift (WCAG 2.2.2). Reduced motion: no drift,
// so no button.
function enhanceClients() {
    const strip = document.querySelector('[data-clients]');
    const toggle = strip?.querySelector('[data-clients-toggle]');
    if (!strip || !toggle || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const show = (paused) => {
        const label = paused ? toggle.dataset.play : toggle.dataset.pause;
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        const icon = document.createElement('i');
        icon.setAttribute('data-lucide', paused ? 'play' : 'pause');
        toggle.replaceChildren(icon);
        drawIcons();
    };
    toggle.hidden = false;
    toggle.addEventListener('click', () => show(strip.classList.toggle('is-paused')));
}

document.addEventListener('DOMContentLoaded', () => {
    enhanceHeaderStick();
    enhanceClients();
    loadAfterPage();
    drawIcons();
    enhanceHeaderMenus();
    enhanceChat();
    toggleOtherFields();
    enhanceDemo();
    enhanceConsent();
    enhanceVerify();
    startMotion();
});
