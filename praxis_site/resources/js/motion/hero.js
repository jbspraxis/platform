// The hero moment (docs/BRAND.md section 11, "The hero moment"). The page already holds the final frame; this only
// stages it once, when the frame is on screen:
//   0 ms        the question fades in
//   150 ms      the Praxis rule draws from the question to the answer (1200 ms, Ink then Teal)
//   1350 ms     the figure appears and its last digits settle (600 ms); the bars grow from their baseline
//   1950 ms     the sources, then the question box turns live ("Ask Praxis")
// Then stillness. Reduced motion: none of this runs.

// Spent against budget, drawn on a scale where 125% fills the track (the tick in the track marks 100%)
const SCALE = 125;

function sizeBars(answer) {
    answer.querySelectorAll('.answer-bar-fill').forEach((bar) => {
        const value = Number.parseFloat(bar.dataset.value);
        if (value > 0) bar.style.width = `${Math.min((value / SCALE) * 100, 100)}%`;
    });
}

// The settling number: the final value stays in the page for screen readers; a hidden layer rolls from 97% of it
// to it, keeping the value's own format (separators, currency sign, French spacing)
function settle(element) {
    if (!element) return;
    const final = element.textContent.trim();
    const digits = final.replace(/\D/g, '');
    if (digits.length < 3) return;
    const target = Number(digits);
    const from = Math.round(target * 0.97);
    const format = (value) => {
        const text = String(value).padStart(digits.length, '0');
        let index = 0;
        return final.replace(/\d/g, () => text[index++]);
    };
    const real = document.createElement('span');
    real.className = 'sr-only';
    real.textContent = final;
    const shown = document.createElement('span');
    shown.setAttribute('aria-hidden', 'true');
    element.replaceChildren(real, shown);

    const start = performance.now();
    const duration = 600;
    const ease = (t) => 1 - (1 - t) ** 3; // close to --ease-settle, no overshoot
    const tick = (now) => {
        const t = Math.min((now - start) / duration, 1);
        shown.textContent = format(Math.round(from + (target - from) * ease(t)));
        if (t < 1) requestAnimationFrame(tick); else element.textContent = final;
    };
    requestAnimationFrame(tick);
}

function stage(answer) {
    answer.classList.add('is-staged');
    const on = (name) => answer.querySelector(`[data-answer-step="${name}"]`)?.classList.add('is-on');
    const steps = [
        [0, () => on('question')],
        [150, () => on('rule')],
        [1350, () => { on('figure'); settle(answer.querySelector('[data-settle]')); }],
        [1500, () => on('table')],
        [1950, () => { on('sources'); answer.classList.add('is-live'); }],
    ];
    const run = () => steps.forEach(([at, step]) => window.setTimeout(step, at));

    if (!('IntersectionObserver' in window)) { run(); return; }
    const watch = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        watch.disconnect();
        run();
    }, { threshold: 0.35 });
    watch.observe(answer);
}

export function startHero(still) {
    const answer = document.querySelector('[data-answer]');
    if (!answer) return;
    sizeBars(answer);

    // The question box: with JavaScript it hands the question to Ask Praxis, which opens and answers in place
    const ask = answer.querySelector('[data-hero-ask]');
    ask?.addEventListener('submit', (event) => {
        if (typeof window.praxisAsk !== 'function') return; // the plain form posts to /chat
        event.preventDefault();
        const input = ask.querySelector('input[name="chat_message"]');
        const question = input.value.trim();
        if (question === '') { input.focus(); return; }
        input.value = '';
        window.praxisAsk(question);
    });

    if (!still) stage(answer);
}
