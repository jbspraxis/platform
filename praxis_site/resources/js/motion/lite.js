// The light layer, on every device: the Praxis rules above sections, domains, steps and rows draw left to right as
// they come into view (docs/BRAND.md section 9, "The moment"; section 11, "Drawing line"). Only a class is added
// here; app.css does the drawing with transform, so it costs nothing on the main thread. Siblings draw 80 ms apart.
const RULED = [
    '.praxis-rule', '.system-spine', '.domain', '.method-step', '.system-group', '.register-row', '.sector',
    '.names li', '.steps li', '.shifts li', '.feature',
].join(', ');

export function startLite() {
    const root = document.documentElement;
    if (!('IntersectionObserver' in window)) { root.classList.remove('motion-ready'); return; }

    const watch = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        watch.unobserve(entry.target);
    }), { rootMargin: '0px 0px -10% 0px' });

    document.querySelectorAll(RULED).forEach((element) => {
        const index = element.parentElement ? [...element.parentElement.children].indexOf(element) : 0;
        element.style.setProperty('--rule-delay', `${Math.min(Math.max(index, 0), 6) * 80}ms`);
        watch.observe(element);
    });
}
