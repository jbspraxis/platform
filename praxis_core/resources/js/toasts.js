// The toast in the corner: one place where every confirmation and error in HQ and the Client Portal shows up.
//
// The host and any message waiting on a page load are rendered by resources/views/components/flash.blade.php,
// which the layouts include. Messages from a Livewire action arrive as the praxis-toast browser event, dispatched
// by App\Support\Flash\ToastBridge. Views can also call window.praxisToast('Saved.', 'success').

const FALLBACK = { autoCloseMs: 4000, max: 4, close: 'Close message', tones: {} };
const FALLBACK_TONE = ['alert-info', 'info', 'status', true];

const host = () => document.querySelector('[data-toast-host]');

function settings() {
    const el = host();

    try {
        return { ...FALLBACK, ...JSON.parse(el?.dataset.toastConfig || '{}') };
    } catch {
        return FALLBACK;
    }
}

function icon(name) {
    const el = document.createElement('i');
    el.setAttribute('data-lucide', name);

    return el;
}

function build(message, tone, config) {
    const [klass, glyph, role, closesItself] = config.tones[tone] || config.tones.info || FALLBACK_TONE;

    const el = document.createElement('div');
    el.className = `alert ${klass} alert-toast`;
    el.setAttribute('role', role);
    el.dataset.toast = '';
    if (closesItself) el.dataset.toastAuto = '';

    const text = document.createElement('span');
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'alert-close';
    close.dataset.toastClose = '';
    close.setAttribute('aria-label', config.close);
    close.append(icon('x'));

    el.append(icon(glyph), text, close);

    return el;
}

function dismiss(el) {
    if (el.dataset.toastGone !== undefined) return;

    el.dataset.toastGone = '';
    el.classList.remove('is-in');
    el.classList.add('is-out');

    const remove = () => el.remove();
    el.addEventListener('transitionend', remove, { once: true });
    // Belt and braces: the element goes even if the transition never fires (reduced motion, a hidden tab)
    setTimeout(remove, 400);
}

// Success and information close themselves. Reading one keeps it: the clock stops while the pointer or the
// keyboard is on it, so a message is never pulled away mid-sentence.
function arm(el, config) {
    if (el.dataset.toastArmed !== undefined) return;
    el.dataset.toastArmed = '';

    if (el.dataset.toastAuto === undefined) return;

    let timer = null;
    const start = () => { timer = setTimeout(() => dismiss(el), config.autoCloseMs); };
    const stop = () => { clearTimeout(timer); timer = null; };

    el.addEventListener('mouseenter', stop);
    el.addEventListener('focusin', stop);
    el.addEventListener('mouseleave', start);
    el.addEventListener('focusout', start);

    start();
}

function trim(config) {
    const shown = host()?.querySelectorAll('[data-toast]:not([data-toast-gone])') || [];

    for (let i = 0; i < shown.length - config.max; i++) dismiss(shown[i]);
}

export function toast(message, tone = 'success') {
    const parent = host();
    const text = typeof message === 'string' ? message.trim() : '';

    if (!parent || text === '') return;

    const config = settings();
    const el = build(text, tone, config);

    parent.append(el);
    trim(config);

    window.lucide?.createIcons();
    requestAnimationFrame(() => el.classList.add('is-in'));
    arm(el, config);
}

// Messages already on the page: rendered by Blade, armed here once the script runs
function armExisting() {
    const config = settings();

    host()?.querySelectorAll('[data-toast]').forEach((el) => arm(el, config));
}

document.addEventListener('DOMContentLoaded', armExisting);
document.addEventListener('livewire:navigated', armExisting);

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-toast-close]');

    if (button) dismiss(button.closest('[data-toast]'));
});

window.addEventListener('praxis-toast', (event) => toast(event.detail?.message, event.detail?.tone));

window.praxisToast = toast;
