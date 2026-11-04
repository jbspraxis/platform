// The cinematic layer (wide screens with a fine pointer only; see index.js). Loaded after the page, never on phones.
// - Lenis: smooth scrolling, driven by GSAP's ticker so scroll scenes and scrolling share one clock. Panels that
//   scroll on their own (Ask Praxis, dialogs) carry data-lenis-prevent.
// - The statement (data-chapter="statement"): its words go from quiet to full ink as it scrolls through the view.
// - The method (data-chapter="method"): the section holds in the centre while its four rules draw in turn and each
//   step comes up to full strength (See, Design, Build, Hold).
// - The hero: the answer frame drifts up a little as the page leaves it, and the flow field fades.
// Every scene animates transform, opacity or a CSS variable read by a transform. Nothing changes layout.
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import { startField } from './field';

gsap.registerPlugin(ScrollTrigger);

const SETTLE = 'power3.out'; // the nearest GSAP ease to --ease-settle, cubic-bezier(0.2, 0, 0, 1)

// Split a block's text into word spans for the statement scene (text stays in the page, in reading order)
function words(block) {
    const spans = [];
    const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
    const texts = [];
    while (walker.nextNode()) texts.push(walker.currentNode);
    for (const node of texts) {
        const fragment = document.createDocumentFragment();
        for (const part of node.textContent.split(/(\s+)/)) {
            if (part === '' || /^\s+$/.test(part)) { fragment.append(part); continue; }
            const span = document.createElement('span');
            span.className = 'scrub-word';
            span.textContent = part;
            spans.push(span);
            fragment.append(span);
        }
        node.replaceWith(fragment);
    }
    return spans;
}

function statement() {
    document.querySelectorAll('[data-chapter="statement"]').forEach((section) => {
        const spans = [...section.querySelectorAll('h2, p')].flatMap(words);
        if (spans.length === 0) return;
        // From Muted (5.3:1 on Mist, AA at every size) to each word's own colour: quiet, never unreadable
        const own = spans.map((span) => getComputedStyle(span).color);
        gsap.fromTo(spans, { color: '#5E6679' }, {
            color: (index) => own[index],
            ease: 'none',
            stagger: 0.05,
            scrollTrigger: { trigger: section, start: 'top 78%', end: 'bottom 52%', scrub: 0.6 },
        });
    });
}

function method() {
    document.querySelectorAll('[data-chapter="method"]').forEach((section) => {
        const steps = [...section.querySelectorAll('.method-step')];
        if (steps.length === 0) return;
        section.classList.add('is-scrubbed');
        // Each step's words wait in Muted (5.3:1 on Mist, readable from the start) and come up to their own colour
        // as its rule draws
        const texts = steps.map((step) => [...step.querySelectorAll('.method-number, h3, p')]);
        const own = texts.map((group) => group.map((element) => getComputedStyle(element).color));
        gsap.set(steps, { '--draw': 0 });
        texts.forEach((group) => gsap.set(group, { color: '#5E6679' }));
        const timeline = gsap.timeline({ defaults: { ease: SETTLE } });
        steps.forEach((step, index) => {
            timeline.to(step, { '--draw': 1, duration: 1 }, index * 0.8);
            timeline.to(texts[index], { color: (i) => own[index][i], duration: 0.6 }, index * 0.8 + 0.2);
        });
        ScrollTrigger.create({
            trigger: section,
            start: 'center center',
            end: '+=110%',
            pin: true,
            scrub: 0.6,
            animation: timeline,
        });
    });
}

function hero() {
    const section = document.querySelector('[data-hero]');
    if (!section) return;
    const answer = section.querySelector('[data-answer]');
    const field = section.querySelector('.hero-field');
    const leave = { trigger: section, start: 'top top', end: 'bottom top', scrub: true };
    if (answer) gsap.to(answer, { yPercent: -6, ease: 'none', scrollTrigger: leave });
    if (field) gsap.to(field, { opacity: 0, ease: 'none', scrollTrigger: leave });
}

export function startCinematic() {
    const root = document.documentElement;
    root.classList.add('is-cinematic');

    const lenis = new Lenis({ autoRaf: false, anchors: true, lerp: 0.11, wheelMultiplier: 0.95 });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);

    // A modal dialog (the narrated demo) holds the page still
    document.querySelectorAll('dialog').forEach((dialog) => {
        new MutationObserver(() => (dialog.open ? lenis.stop() : lenis.start())).observe(dialog, { attributes: true, attributeFilter: ['open'] });
    });

    startField(document.querySelector('[data-hero] .hero-field'));
    hero();
    statement();
    method();

    // Fonts and late images change heights: measure the scenes again once everything has settled
    document.fonts?.ready.then(() => ScrollTrigger.refresh());
}
