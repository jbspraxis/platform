import './toasts';
import { createIcons } from 'lucide';
import icons from './icons';

// Icons are written as <i data-lucide="name"> in the views. One pinned copy of the library (package.json),
// only the icons the views use (scripts/icons.mjs), drawn with a 1.5px stroke (docs/BRAND.md section 9).
// Views that call lucide.createIcons() keep working.
//
// Only the placeholders still waiting are drawn: lucide's createIcons() on its own rebuilds every icon on the page,
// drawn or not, and a Livewire update used to ask for that once per element it morphed (hundreds of full redraws
// after opening a mail, freezing the page for up to seconds). The placeholders are marked, lucide draws exactly those
// (its own attributes and classes), and the mark is taken off again.
const ATTRS = { 'stroke-width': 1.5 };
const drawIcons = () => {
    const waiting = document.querySelectorAll('i[data-lucide]');
    if (!waiting.length) {
        return;
    }
    waiting.forEach((el) => el.setAttribute('data-lucide-pending', el.getAttribute('data-lucide')));
    try {
        createIcons({ icons, nameAttr: 'data-lucide-pending', attrs: ATTRS });
    } finally {
        document.querySelectorAll('[data-lucide-pending]').forEach((el) => el.removeAttribute('data-lucide-pending'));
    }
};
window.lucide = { createIcons: drawIcons };

// At most one pass per frame, however many elements an update touched
let queued = false;
const drawIconsSoon = () => {
    if (queued) {
        return;
    }
    queued = true;
    requestAnimationFrame(() => {
        queued = false;
        drawIcons();
    });
};

document.addEventListener('DOMContentLoaded', drawIcons);
document.addEventListener('livewire:navigated', drawIcons);
document.addEventListener('livewire:init', () => {
    // Icons that arrive with a Livewire update
    window.Livewire.hook('morph.updated', drawIconsSoon);
    window.Livewire.hook('morph.added', drawIconsSoon);
});
