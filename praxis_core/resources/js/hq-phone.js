// HQ on phones (docs/HQ_PHONE.md): the top bar's title, the strip of a module's pages, sheets closed by dragging them
// down, and pull to refresh in the installed app. Only HQ's layout has .hq-shell: on the Client Portal this does
// nothing. Every page change (wire:navigate) brings a new page, so the page parts start again on livewire:navigated.

const PHONE = '(max-width: 900px)';
const isPhone = () => window.matchMedia(PHONE).matches;
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const installed = () => navigator.standalone === true
    || ['standalone', 'fullscreen', 'minimal-ui'].some((mode) => window.matchMedia(`(display-mode: ${mode})`).matches);

// ---- The title: the bar shows the page's title once the page's own heading has scrolled under it ----------------
let titleWatch = null;

function watchTitle() {
    titleWatch?.disconnect();
    titleWatch = null;
    const bar = document.querySelector('.hq-shell [data-hq-topbar]');
    const label = bar?.querySelector('[data-hq-bar-title]');
    if (!bar || !label) return;

    const heading = document.querySelector('.hq-shell .workspace .page-title');
    if (!heading || !('IntersectionObserver' in window)) {
        // No heading of its own (or an old browser): the bar keeps the module's name in view
        bar.classList.add('is-titled');
        return;
    }
    const text = heading.textContent.replace(/\s+/g, ' ').trim();
    if (text) label.textContent = text;
    bar.classList.remove('is-titled');

    titleWatch = new IntersectionObserver(([entry]) => {
        // Gone above the bar, not below the screen
        const above = !entry.isIntersecting && entry.boundingClientRect.top < bar.getBoundingClientRect().bottom;
        bar.classList.toggle('is-titled', above);
    }, { rootMargin: `-${Math.round(bar.getBoundingClientRect().height)}px 0px 0px 0px` });
    titleWatch.observe(heading);
}

// ---- The strip of a module's pages: the current page in view --------------------------------------------------
function centreCurrentPage() {
    const strip = document.querySelector('[data-hq-subnav]');
    const here = strip?.querySelector('[aria-current="page"]');
    if (!strip || !here) return;
    strip.scrollLeft = Math.max(0, here.offsetLeft - (strip.clientWidth - here.offsetWidth) / 2);
}

// ---- Sheets: drag the handle down to close (More, the bell) -----------------------------------------------------
const CLOSE_AT = 80;
let drag = null;

function onDragStart(e) {
    const grab = e.target.closest?.('[data-hq-sheet-grab]');
    const sheet = grab?.closest('[data-hq-sheet]');
    if (!sheet || e.touches.length !== 1) return;
    drag = { sheet, y: e.touches[0].clientY, dy: 0 };
    sheet.classList.add('is-dragging');
}

function onDragMove(e) {
    if (!drag) return;
    drag.dy = Math.max(0, e.touches[0].clientY - drag.y);
    drag.sheet.style.transform = `translateY(${drag.dy}px)`;
}

function onDragEnd() {
    if (!drag) return;
    const { sheet, dy } = drag;
    drag = null;
    sheet.classList.remove('is-dragging');
    sheet.style.transform = '';
    if (dy >= CLOSE_AT) sheet.dispatchEvent(new CustomEvent('hq-sheet-close', { bubbles: true }));
}

// ---- Pull to refresh: in the installed app on a phone, where the browser has no refresh button -------------------
// It only listens and never holds up scrolling: it starts at the very top of the page, on a clear move down, and not
// over a sheet, a dialog, a list scrolled away from its own top, or anything marked data-pull="off".
const READY_AT = 64;
const MAX_PULL = 96;
let pull = null;
let refreshing = false;

const pullDisc = () => document.querySelector('[data-hq-pull]');

function scrolledInside(el) {
    for (let node = el; node && node !== document.body; node = node.parentElement) {
        if (node.scrollTop > 0) return true;
    }
    return false;
}

function onPullStart(e) {
    if (refreshing || e.touches.length !== 1 || !document.querySelector('.hq-shell') || !isPhone() || !installed()) return;
    // A sheet or a dialog that holds the page still (x-trap.noscroll) is open
    if (window.scrollY > 0 || document.documentElement.style.overflow === 'hidden' || document.body.style.overflow === 'hidden') return;
    const target = e.target;
    if (target.closest?.('[data-pull="off"], [data-hq-sheet], .hq-sheet-dim, .hq-menu-dim, [role="dialog"], dialog, .modal-backdrop, input, textarea, select, [contenteditable]')) return;
    if (scrolledInside(target)) return;
    pull = { y: e.touches[0].clientY, x: e.touches[0].clientX, d: 0, on: false };
}

function onPullMove(e) {
    if (!pull) return;
    const dy = e.touches[0].clientY - pull.y;
    const dx = Math.abs(e.touches[0].clientX - pull.x);
    if (!pull.on) {
        // A sideways swipe or a move up is not a pull
        if (dy < 10 || dx > dy || window.scrollY > 0) {
            if (dy < 0 || dx > 10) pull = null;
            return;
        }
        pull.on = true;
    }
    pull.d = Math.min(MAX_PULL, Math.max(0, dy * 0.5));
    const disc = pullDisc();
    if (!disc) return;
    disc.classList.remove('is-back');
    disc.style.opacity = String(Math.min(1, pull.d / READY_AT));
    disc.style.transform = `translateY(${pull.d - 48}px) rotate(${reduced() ? 0 : pull.d * 3}deg)`;
    disc.classList.toggle('is-ready', pull.d >= READY_AT);
}

function settleDisc() {
    const disc = pullDisc();
    if (!disc) return;
    disc.classList.add('is-back');
    disc.classList.remove('is-ready', 'is-busy');
    disc.style.opacity = '';
    disc.style.transform = '';
}

function onPullEnd() {
    if (!pull) return;
    const ready = pull.on && pull.d >= READY_AT;
    pull = null;
    if (!ready) {
        settleDisc();
        return;
    }
    refreshing = true;
    const disc = pullDisc();
    if (disc) {
        disc.classList.remove('is-ready');
        disc.classList.add('is-busy');
        disc.style.opacity = '1';
        disc.style.transform = `translateY(${READY_AT - 48}px)`;
    }
    const done = () => {
        refreshing = false;
        settleDisc();
    };
    // The same page again, in place: the shell stays and nothing reloads but the page. Without Livewire, a reload.
    if (window.Livewire?.navigate) {
        const timeout = setTimeout(done, 15000);
        document.addEventListener('livewire:navigated', () => {
            clearTimeout(timeout);
            done();
        }, { once: true });
        window.Livewire.navigate(window.location.pathname + window.location.search + window.location.hash);
    } else {
        window.location.reload();
    }
}

// ---- Start ------------------------------------------------------------------------------------------------------
function startPage() {
    if (!document.querySelector('.hq-shell')) return;
    watchTitle();
    centreCurrentPage();
}

document.addEventListener('DOMContentLoaded', startPage);
document.addEventListener('livewire:navigated', startPage);

document.addEventListener('touchstart', (e) => {
    onDragStart(e);
    if (!drag) onPullStart(e);
}, { passive: true });
document.addEventListener('touchmove', (e) => {
    onDragMove(e);
    onPullMove(e);
}, { passive: true });
document.addEventListener('touchend', () => {
    onDragEnd();
    onPullEnd();
}, { passive: true });
document.addEventListener('touchcancel', () => {
    onDragEnd();
    pull = null;
    if (!refreshing) settleDisc();
}, { passive: true });
