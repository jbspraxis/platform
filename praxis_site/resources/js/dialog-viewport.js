// Making a full-screen conversation fit the screen it is actually on.
//
// Two things browsers do not solve for a <dialog> that fills a phone:
//
//  - 100dvh follows the browser's own chrome but knows nothing about the on-screen keyboard, so a dialog
//    stays full height while the keyboard covers its bottom half, hiding the composer and any button near
//    it. The visual viewport is the only thing that knows, so we read it and size the dialog to it.
//  - A dialog is centred by margin:auto against inset:0, so one a pixel too tall loses equal slices off
//    the top and the bottom, which is why a clipped title appears. On a phone it is pinned instead (CSS).
//
// This attaches to any dialog carrying the "agent" class, so the conversational forms and the cost of
// poor quality calculator are both covered without either engine knowing about it. It only ever sets two
// custom properties and blurs a field; it never touches what a dialog says or sends.

const PHONE = '(max-width: 640px)';
const TYPEABLE = 'input, textarea, select';

export function startDialogViewport() {
    const dialogs = document.querySelectorAll('dialog.agent');
    if (!dialogs.length) return;

    const viewport = window.visualViewport;

    dialogs.forEach((dialog) => {
        let watching = false;

        /**
         * The dialog takes exactly the part of the screen the visitor can actually see. offsetTop matters
         * because on iOS the page itself scrolls under the keyboard, and a fixed element goes with it.
         */
        const fit = () => {
            if (!viewport || !window.matchMedia(PHONE).matches) return;
            dialog.style.setProperty('--agent-screen-h', `${Math.round(viewport.height)}px`);
            dialog.style.setProperty('--agent-screen-top', `${Math.round(viewport.offsetTop)}px`);
            // With a keyboard up there is very little screen left. Nothing is hidden: the head and the
            // answers stay where they are, and only the space around them tightens.
            dialog.classList.toggle('is-tight', viewport.height < 430);
            reveal();
        };

        const unfit = () => {
            dialog.style.removeProperty('--agent-screen-h');
            dialog.style.removeProperty('--agent-screen-top');
            dialog.classList.remove('is-tight');
        };

        const focused = () => {
            const field = document.activeElement;
            return field && field.matches?.(TYPEABLE) && dialog.contains(field) ? field : null;
        };

        /**
         * Bring what is being answered back into view once the keyboard has finished arriving.
         *
         * This has to run on resize, not only on focus: when a field is tapped it is usually still in
         * view, so there is nothing to scroll, and the keyboard only takes the room away a moment later
         * without firing another focus event. On the first screen the button is what comes next, so that
         * is what is brought into view and the field rides just above it.
         */
        let revealing = 0;
        const reveal = () => {
            window.clearTimeout(revealing);
            revealing = window.setTimeout(() => {
                const field = focused();
                if (!field) return;

                // On the first screen the button is what comes next, so that is what is brought into
                // view and the field rides just above it.
                const gate = field.closest('[data-agent-gate], [data-calc-gate]');
                const start = gate?.querySelector('[data-agent-start], [data-calc-start]');
                if (start) { start.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); return; }

                // In the conversation the composer is already pinned above the keyboard, so scrolling to
                // it would do nothing. What they need to see is the question they are answering.
                const log = dialog.querySelector('[data-agent-body], [data-calc-body]');
                if (log) log.scrollTo({ top: log.scrollHeight, behavior: 'smooth' });
            }, 260);
        };

        const watch = () => {
            if (watching || !viewport) return;
            watching = true;
            viewport.addEventListener('resize', fit);
            viewport.addEventListener('scroll', fit);
            fit();
        };

        const stop = () => {
            if (!watching || !viewport) return;
            watching = false;
            viewport.removeEventListener('resize', fit);
            viewport.removeEventListener('scroll', fit);
            unfit();
        };

        // A dialog has no open/close event of its own, so the attribute is what to watch
        new MutationObserver(() => (dialog.open ? watch() : stop()))
            .observe(dialog, { attributes: true, attributeFilter: ['open'] });
        if (dialog.open) watch();

        dialog.addEventListener('focusin', (event) => {
            if (event.target.matches?.(TYPEABLE)) { watch(); reveal(); }
        });

        // Reading what was said should not happen behind a keyboard: touching or dragging the conversation
        // puts it away, and the composer brings it back because it is still just a field to tap.
        const body = dialog.querySelector('[data-agent-body], [data-calc-body]');
        if (body) {
            const putAway = (event) => {
                const field = document.activeElement;
                if (!field || !field.matches?.(TYPEABLE) || !dialog.contains(field)) return;
                // A tap on something meant to be tapped keeps its own behaviour; this is only for the page
                if (event.type === 'pointerdown' && event.target.closest('button, a, label, input, textarea, select')) return;
                field.blur();
            };
            body.addEventListener('pointerdown', putAway);
            // touchmove, not scroll: a scroll we caused ourselves must never put the keyboard away
            body.addEventListener('touchmove', putAway, { passive: true });
        }
    });
}
