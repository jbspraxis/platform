// Starting the conversational forms on a page (components/agent-dialog).
//
// Each dialog names its form in data-form; the opener that goes with it carries data-agent-open with the
// same name. Openers that are no use without JavaScript are hidden in the markup and revealed here, and a
// dialog whose form we do not know, or whose browser has no modal dialogs, is left alone so its opener
// stays what it is: a plain link to the page form.

import { buildAgent } from './engine';
import { FORMS } from './forms';

export function startAgentForms() {
    document.querySelectorAll('[data-agent-dialog]').forEach((dialog) => {
        const flow = FORMS[dialog.dataset.form];
        if (!flow || typeof dialog.showModal !== 'function') return;

        const openers = document.querySelectorAll(`[data-agent-open="${dialog.dataset.form}"]`);
        if (!openers.length) return;
        openers.forEach((opener) => { if (opener.hasAttribute('data-agent-reveal')) opener.hidden = false; });

        let agent = null;
        openers.forEach((opener) => opener.addEventListener('click', (event) => {
            event.preventDefault();
            if (!agent) agent = buildAgent(dialog, flow);
            if (!dialog.open) dialog.showModal();
            agent.opened();
        }));
    });
}
