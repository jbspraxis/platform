// The shared conversational form engine (components/agent-dialog, resources/js/agent/forms).
//
// Built on the pattern the cost of poor quality calculator proved, but driven by a flow definition rather
// than one form's fields, so every form gets the same behaviour: one thing asked the ordinary way, then
// questions answered by tapping or typing, every answer pinned at the top and changeable at any point.
//
// It shares no code with resources/js/calculator. That calculator is live and must keep working exactly as
// it does, so nothing here is imported by it and nothing here can alter it.
//
// Nothing about the server changes. The dialog *is* the page's form: every answer is written into a hidden
// input with the input name the controller already expects, and the last step submits it normally, with
// CSRF, the honeypot, the signed page-load time and Turnstile exactly as the page form has them. Without
// JavaScript the opener is a plain link to the page and the page form is untouched.

import {
    fold, matchOption, rankOptions, readTarget, readEmail, readUrl, readPhone,
    isSkip, isYes, isNo, isEdit,
} from './parse';

const REDUCED = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const wait = (ms) => new Promise((resolve) => { window.setTimeout(resolve, REDUCED() ? 0 : ms); });

/**
 * @param {HTMLDialogElement} dialog
 * @param {object} flow  from resources/js/agent/forms
 */
export function buildAgent(dialog, flow) {
    const strings = read(dialog, 'strings');
    const lists = read(dialog, 'lists');

    const el = {
        form: dialog.querySelector('[data-agent-form]'),
        hidden: dialog.querySelector('[data-agent-hidden]'),
        rail: dialog.querySelector('[data-agent-rail]'),
        chips: dialog.querySelector('[data-agent-chips]'),
        body: dialog.querySelector('[data-agent-body]'),
        gate: dialog.querySelector('[data-agent-gate]'),
        gateInput: dialog.querySelector('[data-agent-gate-input]'),
        gateError: dialog.querySelector('[data-agent-gate-error]'),
        start: dialog.querySelector('[data-agent-start]'),
        log: dialog.querySelector('[data-agent-log]'),
        foot: dialog.querySelector('[data-agent-foot]'),
        choices: dialog.querySelector('[data-agent-choices]'),
        input: dialog.querySelector('[data-agent-input]'),
        send: dialog.querySelector('[data-agent-send]'),
        status: dialog.querySelector('[data-agent-status]'),
        guard: dialog.querySelector('[data-agent-guard]'),
    };

    const state = {
        answers: {},      // input name -> the value the form posts
        shown: {},        // input name -> what the chip shows
        awaiting: null,   // a question of our own that must be answered first
        pending: null,    // the field being asked
        phase: 'gate',
        busy: false,
    };

    const t = (path, replace) => fill(dig(strings, path), replace);
    const options = (name) => (Array.isArray(lists[name]) ? lists[name] : []);
    const byName = (name) => flow.fields.find((field) => field.name === name) || null;
    const gateField = () => flow.fields.find((field) => field.gate) || null;

    /** The words every field answers to when someone asks to change it. */
    const targets = () => flow.fields.map((field) => ({ name: field.name, words: words(field) }));
    const words = (field) => {
        const listed = dig(strings, `words.${field.name}`);
        const extra = typeof listed === 'string' ? listed.split('|').map((word) => word.trim()).filter(Boolean) : [];
        return [t(`short.${field.name}`), ...extra].filter(Boolean);
    };

    /** A conditional field is only asked while its condition holds (the form's data-show-when, in words). */
    const shown = (field) => !field.showWhen || state.answers[field.showWhen.field] === field.showWhen.is;

    const fieldsOfStep = (step) => flow.fields.filter((field) => field.step === step && !field.gate && shown(field));

    // ------------------------------------------------------------ saying things

    function echo(text) {
        el.log.append(turn('is-you', t('you'), text));
        scroll();
    }

    async function say(text, extras) {
        const item = turn('is-agent', t('agent'), '');
        const dots = document.createElement('span');
        dots.className = 'agent-dots';
        dots.setAttribute('aria-hidden', 'true');
        for (let i = 0; i < 3; i += 1) dots.append(document.createElement('i'));
        item.append(dots);
        el.log.append(item);
        scroll();

        await wait(Math.min(900, 260 + text.length * 6));
        dots.remove();

        const body = document.createElement('span');
        body.className = 'agent-said';
        body.textContent = text;
        writeIn(body);          // split into words while detached, so the live region announces once
        item.append(body);
        if (extras) for (const extra of extras) item.append(extra);
        scroll();
        return item;
    }

    function turn(role, who, text) {
        const item = document.createElement('li');
        item.className = `agent-turn ${role}`;
        const label = document.createElement('span');
        label.className = 'who';
        label.textContent = who;
        item.append(label);
        if (text) {
            const body = document.createElement('span');
            body.className = 'agent-said';
            body.textContent = text;
            item.append(body);
        }
        return item;
    }

    const scroll = () => { el.body.scrollTo({ top: el.body.scrollHeight, behavior: REDUCED() ? 'auto' : 'smooth' }); };

    // ------------------------------------------------------------ pills

    function choices(items) {
        el.choices.replaceChildren();
        if (!items || !items.length) { el.choices.hidden = true; return; }
        el.choices.hidden = false;
        items.forEach((item, index) => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = `agent-pill${item.tone ? ` is-${item.tone}` : ''}`;
            pill.textContent = item.label;
            pill.style.setProperty('--i', String(index));
            pill.addEventListener('click', () => {
                if (state.busy) return;   // still speaking: leave the row alone rather than swallow the tap
                choices([]);
                hear(item.say ?? item.label, item);
            });
            el.choices.append(pill);
        });
    }

    // ------------------------------------------------------------ the rail and the chips

    function paint() {
        const current = state.pending ? state.pending.step : null;
        flow.steps.forEach((step) => {
            const node = el.rail.querySelector(`[data-step="${step}"]`);
            if (!node) return;
            const fields = fieldsOfStep(step);
            const answered = fields.filter((field) => field.name in state.answers).length;
            const fillShare = fields.length ? answered / fields.length : 1;
            node.style.setProperty('--fill', String(fillShare));
            node.classList.toggle('is-done', fillShare >= 1);
            node.classList.toggle('is-here', step === current);
            if (step === current) node.setAttribute('aria-current', 'step'); else node.removeAttribute('aria-current');
        });

        el.chips.replaceChildren();
        const answered = flow.fields.filter((field) => field.name in state.answers);
        el.chips.hidden = answered.length === 0;
        answered.forEach((field) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'agent-chip';
            const name = document.createElement('span');
            name.className = 'agent-chip-name';
            name.textContent = t(`short.${field.name}`);
            const value = document.createElement('span');
            value.className = 'agent-chip-value';
            value.textContent = state.shown[field.name];
            chip.append(name, value);
            chip.setAttribute('aria-label', t('edit.chip', { field: t(`short.${field.name}`), value: state.shown[field.name] }));
            chip.addEventListener('click', () => editField(field));
            el.chips.append(chip);
        });
        el.chips.classList.toggle('is-scrollable', el.chips.scrollHeight > el.chips.clientHeight + 1);
    }

    /** Writing the answer into the form itself, so submitting posts exactly what the page form posts. */
    function store(field, value, label) {
        state.answers[field.name] = String(value);
        state.shown[field.name] = label;

        // A file lives on the real file input inside this form: a File cannot be put into a hidden input,
        // so there is nothing to copy and the browser posts it from where the visitor chose it.
        if (field.kind === 'file') { /* nothing to write */ } else {
        const existing = el.hidden.querySelector(`input[name="${field.name}"]`);
        if (String(value) === '') { existing?.remove(); } else {
            const input = existing || el.hidden.appendChild(document.createElement('input'));
            input.type = 'hidden';
            input.name = field.name;
            input.value = String(value);
        }
        }

        // A conditional answer whose condition no longer holds is not ours to send
        for (const other of flow.fields) {
            if (other.showWhen?.field === field.name && other.name in state.answers && !shown(other)) {
                delete state.answers[other.name];
                delete state.shown[other.name];
                el.hidden.querySelector(`input[name="${other.name}"]`)?.remove();
            }
        }
    }

    // ------------------------------------------------------------ asking

    /** What to ask next, in step order, skipping what is answered and what no condition allows. */
    function nextField() {
        for (const step of flow.steps) {
            const field = fieldsOfStep(step).find((entry) => !(entry.name in state.answers));
            if (field) return field;
        }
        return null;
    }

    async function ask(field) {
        // A list the page did not send cannot be chosen from, and free text would only fail validation
        if ((field.kind === 'select' || field.kind === 'picker') && !options(field.list).length) {
            store(field, field.default ?? '', t('skipped'));
            await onward();
            return;
        }

        state.pending = field;
        paint();
        await say(t(`ask.${field.name}`));
        const hint = dig(strings, `hint.${field.name}`);
        if (hint) {
            const note = document.createElement('span');
            note.className = 'agent-note';
            note.textContent = hint;
            el.log.lastElementChild.append(note);
        }
        if (field.kind === 'picker') picker(field);
        else if (field.kind === 'file') fileChoice(field);
        else choices(pillsFor(field));
        el.input.focus({ preventScroll: true });
        scroll();
    }

    /**
     * A list too long to tap through — every country, say — as a search box with the matches under it.
     * Typing in the prompt field works just as well; this is the way that does not need them to know the
     * spelling. Matching is on the name and on the dialling code, so "nig" and "234" both find Nigeria.
     */
    function picker(field) {
        const all = options(field.list);
        el.choices.replaceChildren();
        el.choices.hidden = false;

        const box = document.createElement('span');
        box.className = 'agent-picker';

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'agent-picker-search';
        search.autocomplete = 'off';
        search.placeholder = t('picker.search');
        search.setAttribute('aria-label', t('picker.search'));
        search.setAttribute('role', 'combobox');
        search.setAttribute('aria-expanded', 'true');
        search.setAttribute('aria-autocomplete', 'list');

        const list = document.createElement('span');
        list.className = 'agent-picker-list';
        list.setAttribute('role', 'listbox');
        list.id = `agent-picker-${field.name}`;
        search.setAttribute('aria-controls', list.id);

        const note = document.createElement('span');
        note.className = 'agent-picker-note';

        let marked = 0;
        let showing = [];
        let matches = 0;

        const draw = () => {
            const query = fold(search.value);
            const digits = query.replace(/\D/gu, '');
            const found = !query ? all : all.filter((option) => {
                const name = fold(option.label);
                return name.includes(query)
                    || (digits !== '' && option.value.replace(/\D/gu, '').startsWith(digits));
            });
            // Names that start with what they typed first: "ni" should open on Nicaragua, not Benin
            found.sort((a, b) => Number(fold(b.label).startsWith(query)) - Number(fold(a.label).startsWith(query)));
            matches = found.length;
            showing = found.slice(0, 60);
            marked = 0;

            list.replaceChildren();
            showing.forEach((option, index) => {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'agent-picker-option';
                row.setAttribute('role', 'option');
                row.setAttribute('aria-selected', String(index === 0));
                const name = document.createElement('span');
                name.textContent = option.label;
                const code = document.createElement('span');
                code.className = 'agent-picker-dial';
                code.textContent = option.value;
                row.append(name, code);
                row.addEventListener('click', () => {
                    if (state.busy) return;
                    el.choices.replaceChildren();
                    el.choices.hidden = true;
                    hear(option.label, { label: `${option.label} (${option.value})`, value: option.value });
                });
                list.append(row);
            });
            mark(0);
            // Only two things are worth saying: nothing matched, or there is more than we are showing
            if (!showing.length) note.textContent = t('picker.none');
            else if (matches > showing.length) note.textContent = t('picker.more', { n: String(matches - showing.length) });
            else note.textContent = '';
        };

        const mark = (next) => {
            const rows = [...list.children];
            if (!rows.length) return;
            marked = Math.max(0, Math.min(rows.length - 1, next));
            rows.forEach((row, index) => {
                row.classList.toggle('is-marked', index === marked);
                row.setAttribute('aria-selected', String(index === marked));
            });
            rows[marked].scrollIntoView({ block: 'nearest' });
        };

        search.addEventListener('input', draw);
        search.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') { event.preventDefault(); mark(marked + 1); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); mark(marked - 1); }
            else if (event.key === 'Enter') {
                event.preventDefault();
                list.children[marked]?.click();
            }
        });

        box.append(search, note, list);
        el.choices.append(box);
        if (!field.required) {
            const skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'agent-pill is-quiet';
            skip.textContent = t('skip');
            skip.addEventListener('click', () => { if (!state.busy) { el.choices.hidden = true; hear('skip', { label: t('skip') }); } });
            el.choices.append(skip);
        }
        draw();
        search.focus({ preventScroll: true });
    }

    function pillsFor(field) {
        const skip = { label: t('skip'), tone: 'quiet', say: 'skip' };
        const optional = !field.required;

        if (field.kind === 'select') {
            const all = options(field.list);
            const shortlist = all.slice(0, 6).map((option) => ({ label: option.label, say: option.label, value: option.value }));
            if (all.length > shortlist.length) shortlist.push({ label: t('more'), tone: 'quiet', more: field.list });
            return optional ? [...shortlist, skip] : shortlist;
        }
        return optional ? [skip] : [];
    }

    /**
     * Going back to a field. It runs both from a click (nothing else is happening) and from inside hear()
     * (the busy flag is already held), so it never takes the flag itself.
     */
    async function revisit(field) {
        state.awaiting = null;
        state.phase = 'asking';
        await say(t('edit.ok', { field: t(`short.${field.name}`).toLowerCase(), value: state.shown[field.name] }));
        await ask(field);
    }

    /**
     * Choosing a file. The input is a real one inside this form, because a File cannot be written into a
     * hidden input from script: the visitor picks it on that input and the browser posts it from there.
     * Type and size are checked here so a CV that is too big is caught in the conversation, not after it.
     */
    function fileChoice(field) {
        const input = el.form.querySelector(`input[type="file"][name="${field.name}"]`);
        if (!input) { skipped(field); return; }

        el.choices.replaceChildren();
        el.choices.hidden = false;

        const pick = document.createElement('button');
        pick.type = 'button';
        pick.className = 'agent-pill is-go';
        pick.textContent = t('file.choose');
        pick.addEventListener('click', () => { if (!state.busy) input.click(); });
        el.choices.append(pick);

        if (!field.required) {
            const skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'agent-pill is-quiet';
            skip.textContent = t('skip');
            skip.addEventListener('click', () => { if (!state.busy) { el.choices.hidden = true; hear('skip', { label: t('skip') }); } });
            el.choices.append(skip);
        }

        input.onchange = async () => {
            const file = input.files?.[0];
            if (!file || state.busy) return;
            state.busy = true;
            el.choices.hidden = true;
            el.choices.replaceChildren();

            const problem = fileProblem(file, field);
            if (problem) {
                input.value = '';   // nothing half-chosen is left on the form
                echo(file.name);
                await say(problem);
                state.busy = false;
                fileChoice(field);
                return;
            }

            echo(`${file.name} (${size(file.size)})`);
            store(field, file.name, `${file.name} (${size(file.size)})`);
            await onward();
            state.busy = false;
        };
    }

    /** What is wrong with a chosen file, or null. The same two rules the controller applies. */
    function fileProblem(file, field) {
        const types = (field.accept || []).map((type) => type.toLowerCase());
        const name = file.name.toLowerCase();
        if (types.length && !types.some((type) => name.endsWith(`.${type}`))) {
            return t('file.type', { types: types.join(', ') });
        }
        if (field.maxBytes && file.size > field.maxBytes) {
            return t('file.size', { max: size(field.maxBytes), got: size(file.size) });
        }
        if (file.size === 0) return t('file.empty');
        return null;
    }

    const size = (bytes) => (bytes >= 1048576 ? `${Math.round(bytes / 104857.6) / 10} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

    /** revisit() from a click: the one place that has to check nothing else is mid-sentence. */
    function editField(field) {
        if (state.busy) return;
        state.busy = true;
        revisit(field).finally(() => { state.busy = false; });
    }

    // ------------------------------------------------------------ hearing

    async function hear(text, pill) {
        const said = String(text ?? '').trim();
        if (!said || state.busy) return;
        state.busy = true;
        el.input.value = '';
        resize();
        choices([]);
        echo(pill?.label ?? said);

        try {
            if (pill?.more) { await showAll(pill.more); return; }
            if (state.awaiting) { await state.awaiting(said, pill); return; }
            if (state.phase === 'confirm') { await atConfirm(said); return; }

            // A field named out loud wins, but only when the sentence announces itself as a change:
            // ordinary answers name other fields all the time.
            const mayRedirect = !pill && (!state.pending || isEdit(said));
            const aimed = mayRedirect ? readTarget(said, targets().filter((entry) => entry.name !== state.pending?.name)) : null;
            if (aimed && (aimed.name in state.answers || !state.pending)) {
                const field = byName(aimed.name);
                if (field && await take(field, aimed.rest, null)) { await onward(); return; }
            }

            if (state.pending) {
                if (isSkip(said) && !state.pending.required) { await skipped(state.pending); return; }
                if (await take(state.pending, said, pill)) { await onward(); return; }
                return;   // take() has already said what it did not understand
            }
            await say(t('edit.which'));
        } finally {
            state.busy = false;
            if (!state.awaiting && state.phase !== 'sending') el.input.focus({ preventScroll: true });
        }
    }

    /** Left empty on purpose: answered, so we move on, but nothing is posted for it. */
    async function skipped(field) {
        store(field, field.default ?? '', t('skipped'));
        await say(t('fine'));
        await onward();
    }

    /** The next question, or the summary when there is nothing left to ask. */
    async function onward() {
        paint();
        const next = nextField();
        state.busy = false;
        if (next) { await ask(next); return; }
        state.pending = null;
        await summarise();
    }

    // ------------------------------------------------------------ reading one answer

    /** Reads an answer into a field. Returns false when it could not, having said so. */
    async function take(field, said, pill) {
        const text = String(said).trim();

        if (field.kind === 'email') {
            const email = readEmail(text);
            if (!email) { await say(t('errors.email')); return false; }
            store(field, email, email);
            return true;
        }

        if (field.kind === 'tel') {
            const phone = readPhone(text);
            if (!phone) { await say(t('errors.phone')); return false; }
            store(field, phone, phone);
            return true;
        }

        if (field.kind === 'url') {
            const url = readUrl(text, field.host);
            if (!url) { await say(t(field.host ? 'errors.urlHost' : 'errors.url')); return false; }
            store(field, url, url.replace(/^https?:\/\//u, ''));
            return true;
        }

        if (field.kind === 'select' || field.kind === 'picker') {
            const list = options(field.list);
            const exact = pill?.value ? list.find((option) => option.value === pill.value) : null;
            const hit = exact || matchOption(text, list);
            if (hit) {
                // A picker's chip names what is actually being sent, since the country is not the value
                store(field, hit.value, field.kind === 'picker' ? `${hit.label} (${hit.value})` : hit.label);
                return true;
            }

            const near = rankOptions(text, list);
            if (near.length) {
                await say(t('errors.near'));
                state.awaiting = async (answer, chosen) => {
                    state.awaiting = null;
                    if (chosen?.value) {
                        const option = list.find((entry) => entry.value === chosen.value);
                        if (option) {
                            store(field, option.value, field.kind === 'picker' ? `${option.label} (${option.value})` : option.label);
                            await onward(); return;
                        }
                    }
                    if (isSkip(answer) && !field.required) { await skipped(field); return; }
                    if (await take(field, answer, null)) await onward();
                };
                choices([
                    ...near.map((option) => ({ label: option.label, value: option.value, say: option.label })),
                    ...(field.required ? [] : [{ label: t('skip'), tone: 'quiet', say: 'skip' }]),
                ]);
                return false;
            }
            await say(t('errors.list'));
            if (field.kind === 'picker') picker(field); else choices(pillsFor(field));
            return false;
        }

        if (field.kind === 'file') {
            await say(t('file.again'));
            fileChoice(field);
            return false;
        }

        // text and longtext
        const clean = text.replace(/^(i'?m|i am|my name is|we'?re|we are|it'?s|je m'appelle|c'est)\s+/iu, '').slice(0, field.max ?? 255);
        if (!clean) { await say(t('errors.blank')); return false; }
        if (field.minChars && clean.length < field.minChars) { await say(t('errors.short', { n: String(field.minChars) })); return false; }
        store(field, clean, clean.length > 60 ? `${clean.slice(0, 57)}…` : clean);
        return true;
    }

    // ------------------------------------------------------------ a long list, in full

    async function showAll(list) {
        const field = state.pending;
        if (!field) return;
        const all = options(list);
        await say(t('all', { n: String(all.length) }));
        choices([
            ...all.map((option) => ({ label: option.label, value: option.value, say: option.label })),
            ...(field.required ? [] : [{ label: t('skip'), tone: 'quiet', say: 'skip' }]),
        ]);
        state.awaiting = async (answer, pill) => {
            state.awaiting = null;
            if (isSkip(answer) && !field.required) { await skipped(field); return; }
            if (await take(field, answer, pill)) await onward();
        };
    }

    // ------------------------------------------------------------ the summary

    async function summarise() {
        state.phase = 'confirm';
        paint();
        const table = document.createElement('span');
        table.className = 'agent-summary';
        for (const field of flow.fields) {
            if (!(field.name in state.answers)) continue;
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'agent-summary-row';
            const key = document.createElement('span');
            key.textContent = t(`short.${field.name}`);
            const value = document.createElement('span');
            value.className = 'agent-summary-value';
            value.textContent = state.shown[field.name];
            row.append(key, value);
            row.addEventListener('click', () => editField(field));
            table.append(row);
        }

        const turnNode = await say(t('confirm.heading'), [table]);
        // Scrolling to the end of a long summary hides the sentence that explains it
        turnNode.scrollIntoView({ block: 'start', behavior: REDUCED() ? 'auto' : 'smooth' });
        if (el.guard) el.guard.classList.add('is-ready');
        choices([
            { label: t('confirm.send'), tone: 'go', say: 'ok' },
            { label: t('confirm.change'), tone: 'quiet', say: 'change' },
        ]);
    }

    async function atConfirm(said) {
        if (isYes(said)) { await submit(); return; }
        if (isNo(said)) {
            state.phase = 'asking';
            await say(t('confirm.which'));
            choices(flow.fields.filter((field) => field.name in state.answers)
                .map((field) => ({ label: t(`short.${field.name}`), say: t(`short.${field.name}`), field: field.name })));
            state.awaiting = async (answer, pill) => {
                state.awaiting = null;
                const field = byName(pill?.field || '') || byName(readTarget(answer, targets())?.name || '');
                if (field) { await revisit(field); return; }
                state.phase = 'confirm';
                await summarise();
            };
            return;
        }
        // Anything else at the summary is taken as a correction, not as a yes
        const aimed = readTarget(said, targets());
        if (aimed) {
            const field = byName(aimed.name);
            if (field && await take(field, aimed.rest, null)) {
                paint();
                await say(t('confirm.changed', { field: t(`short.${field.name}`).toLowerCase(), value: state.shown[field.name] }));
                await summarise();
                return;
            }
        }
        await say(t('confirm.ask'));
        choices([{ label: t('confirm.send'), tone: 'go', say: 'ok' }, { label: t('confirm.change'), tone: 'quiet', say: 'change' }]);
    }

    async function submit() {
        state.phase = 'sending';
        choices([]);
        el.input.disabled = true;
        el.send.disabled = true;
        el.status.textContent = t('sending');
        await say(t('sent'));
        // A plain POST to the route the page form already posts to: Post/Redirect/Get
        el.form.submit();
    }

    // ------------------------------------------------------------ the one field asked the ordinary way

    async function begin() {
        const field = gateField();
        const raw = el.gateInput.value;
        const ok = field.kind === 'email' ? readEmail(raw) : (raw.trim() || null);
        if (!ok || (field.minChars && String(ok).trim().length < field.minChars)) {
            el.gateError.textContent = t(`gate.invalid`);
            el.gateInput.setAttribute('aria-invalid', 'true');
            el.gateInput.focus({ preventScroll: true });
            return;
        }
        el.gateError.textContent = '';
        el.gateInput.removeAttribute('aria-invalid');

        const value = String(ok).slice(0, field.max ?? 5000);
        el.gateInput.value = value;   // the gate's own input carries it to the server, tidied to what we read
        state.answers[field.name] = value;
        state.shown[field.name] = value.length > 60 ? `${value.slice(0, 57)}…` : value;

        state.phase = 'asking';
        el.gate.classList.add('is-done');
        await wait(260);
        el.gate.hidden = true;
        dialog.classList.add('is-talking');   // one field needed little room; a conversation needs all of it
        el.rail.hidden = false;
        el.foot.hidden = false;
        paint();

        echo(value);
        await say(t('hello'));
        await onward();
    }

    // ------------------------------------------------------------ wiring

    el.start?.addEventListener('click', begin);
    el.gateInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && el.gateInput.tagName !== 'TEXTAREA') { event.preventDefault(); begin(); }
    });

    el.send?.addEventListener('click', () => hear(el.input.value));
    el.input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); hear(el.input.value); }
    });
    el.input?.addEventListener('input', resize);
    function resize() {
        el.input.style.height = 'auto';
        el.input.style.height = `${Math.min(120, el.input.scrollHeight)}px`;
    }

    // Enter anywhere in the dialog must never post a half-finished form
    el.form?.addEventListener('submit', (event) => {
        if (state.phase !== 'sending') event.preventDefault();
    });

    const shut = () => { if (dialog.open) dialog.close(); };
    dialog.querySelector('[data-agent-close]')?.addEventListener('click', shut);
    dialog.addEventListener('click', (event) => { if (event.target === dialog) shut(); });
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); shut(); });

    return {
        opened() {
            if (state.phase === 'gate') el.gateInput?.focus({ preventScroll: true });
            else el.input?.focus({ preventScroll: true });
        },
    };
}

// ---------------------------------------------------------------- small helpers

function read(node, key) {
    try { return JSON.parse(node.dataset[key] || '{}'); } catch { return {}; }
}

function dig(source, path) {
    return path.split('.').reduce((value, key) => (value && typeof value === 'object' ? value[key] : undefined), source);
}

/** ":name" in a string replaced from an object, the way Laravel writes them. */
function fill(text, replace) {
    let out = String(text ?? '');
    if (replace) for (const [key, value] of Object.entries(replace)) out = out.split(`:${key}`).join(String(value ?? ''));
    return out.replace(/\s{2,}/gu, ' ').trim();
}

/** The agent's words arrive one at a time. Still with reduced motion. */
function writeIn(node) {
    if (REDUCED()) return;
    const text = node.textContent;
    node.replaceChildren();
    let index = 0;
    for (const part of text.split(/(\s+)/u)) {
        if (part === '' || /^\s+$/u.test(part) || index > 120) { node.append(part); continue; }
        const word = document.createElement('span');
        word.className = 'agent-word';
        word.style.setProperty('--i', String(index));
        index += 1;
        word.textContent = part;
        node.append(word);
    }
}
