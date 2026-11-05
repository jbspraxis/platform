import './bootstrap';
import './toasts';
import { createIcons } from 'lucide';
import icons from './icons';

// Icons are written as <i data-lucide="name"> in the views. One pinned copy of the library (package.json),
// only the icons the views use (scripts/icons.mjs), drawn with a 1.5px stroke (docs/BRAND.md section 9).
// Views that call lucide.createIcons() keep working.
const drawIcons = () => createIcons({ icons, attrs: { 'stroke-width': 1.5 } });
window.lucide = { createIcons: drawIcons };

document.addEventListener('DOMContentLoaded', drawIcons);
document.addEventListener('livewire:navigated', drawIcons);
document.addEventListener('livewire:init', () => {
    // Redraw icons that arrive with a Livewire update
    window.Livewire.hook('morph.updated', () => requestAnimationFrame(drawIcons));
});
