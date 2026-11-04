// The motion system (redesign PR 2, docs/BRAND.md section 11, v1.4).
//
// Three layers, chosen per device so phones stay fast (mobile Lighthouse holds at 90+):
// - hero.js       every device: the hero's live answer is staged (question, rule, figure, bars, sources).
// - lite.js       every device: Praxis rules draw as they come into view (IntersectionObserver and CSS only).
// - cinematic.js  wide screens with a fine pointer only, loaded after the page: smooth scrolling (Lenis), the
//                 scroll scenes (GSAP ScrollTrigger) and the hero's flow field (WebGL). It is its own file, so
//                 phones never download it.
// With prefers-reduced-motion nothing moves: every final state is already in the page.
import { startHero } from './hero';
import { startLite } from './lite';

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function cinematicAllowed() {
    const connection = navigator.connection || {};
    const slow = connection.saveData === true || /(^|-)(2g|3g)$/.test(connection.effectiveType || '');
    return !slow && window.matchMedia('(min-width: 1080px) and (hover: hover) and (pointer: fine)').matches;
}

export function startMotion() {
    const still = reduced();
    startHero(still);
    if (still) return;

    document.documentElement.classList.add('motion-ready');
    startLite();

    if (cinematicAllowed()) {
        const load = () => import('./cinematic')
            .then((module) => module.startCinematic())
            .catch(() => { /* the page is complete without it */ });
        if (document.readyState === 'complete') load(); else window.addEventListener('load', load, { once: true });
    }
}
