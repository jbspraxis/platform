// The cost of poor quality calculator, as a conversation (partials/calculator-dialog).
//
// The band's button opens a dialog that asks for one thing in the ordinary way — a work email — and then
// talks. Pills to tap where there is a list, a prompt field to type in where there is not, and the answers
// so far pinned at the top as chips anyone can go back and change, by tapping or by simply saying so.
//
// Nothing about the server changes. The dialog *is* the existing form: every answer is written into a
// hidden input with the input name the controller already expects, and the last step submits it normally,
// with CSRF, the honeypot, the signed page-load time and Turnstile all exactly as the page form has them.
// Without JavaScript the button is a plain link to /cost-calculator and the page form is untouched.

import {
    DEFAULT_WEEK, ladder, readMoney, readNumber, readPercent, readPeriod, readCurrency, readEmail,
    matchOption, rankOptions, readTarget, isSkip, isYes, isNo, isEdit, toHourly, toWeekly, toAnnual,
} from './parse';
import { STEPS, FIELDS, byName, fieldsOfStep, targets, nextField, NUMERIC } from './flow';

const REDUCED = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const wait = (ms) => new Promise((resolve) => { window.setTimeout(resolve, REDUCED() ? 0 : ms); });

export function startCalculator() {
    const dialog = document.querySelector('[data-calc-dialog]');
    const openers = document.querySelectorAll('[data-calc-open]');
    if (!dialog || !openers.length || typeof dialog.showModal !== 'function') return;

    // HQ has never answered and no saved copy exists: there are no lists to offer, so the button stays a
    // plain link to the page form rather than opening a conversation that cannot ask half its questions.
    let lists = {};
    try { lists = JSON.parse(dialog.dataset.lists || '{}'); } catch { lists = {}; }
    if (!Object.values(lists).some((list) => Array.isArray(list) && list.length)) return;

    // Openers that are no use without JavaScript are hidden in the markup and revealed here
    document.querySelectorAll('[data-calc-reveal]').forEach((opener) => { opener.hidden = false; });

    let agent = null;
    const open = (event) => {
        event.preventDefault();
        if (!agent) agent = build(dialog);
        if (!dialog.open) dialog.showModal();
        agent.opened();
    };
    openers.forEach((opener) => opener.addEventListener('click', open));
}

// ---------------------------------------------------------------- building

function build(dialog) {
    const strings = read(dialog, 'strings');
    const lists = read(dialog, 'lists');
    const locale = dialog.dataset.locale === 'fr' ? 'fr' : 'en';

    const el = {
        form: dialog.querySelector('[data-calc-form]'),
        hidden: dialog.querySelector('[data-calc-hidden]'),
        rail: dialog.querySelector('[data-calc-rail]'),
        chips: dialog.querySelector('[data-calc-chips]'),
        body: dialog.querySelector('[data-calc-body]'),
        gate: dialog.querySelector('[data-calc-gate]'),
        gateEmail: dialog.querySelector('[data-calc-gate] input[name="email"]'),
        gateError: dialog.querySelector('[data-calc-gate-error]'),
        start: dialog.querySelector('[data-calc-start]'),
        log: dialog.querySelector('[data-calc-log]'),
        foot: dialog.querySelector('[data-calc-foot]'),
        choices: dialog.querySelector('[data-calc-choices]'),
        input: dialog.querySelector('[data-calc-input]'),
        send: dialog.querySelector('[data-calc-send]'),
        status: dialog.querySelector('[data-calc-status]'),
        guard: dialog.querySelector('[data-calc-guard]'),
    };

    const state = {
        answers: {},      // input name -> the value the form posts
        shown: {},        // input name -> what the chip shows
        source: {},       // input name -> how we got it, so a new week can re-derive it
        week: { ...DEFAULT_WEEK },
        awaiting: null,   // a question of our own that must be answered first
        pending: null,    // the field being asked
        phase: 'gate',
        busy: false,
    };

    const number = new Intl.NumberFormat(locale === 'fr' ? 'fr-FR' : 'en-GB', { maximumFractionDigits: 2 });
    const whole = new Intl.NumberFormat(locale === 'fr' ? 'fr-FR' : 'en-GB', { maximumFractionDigits: 0 });
    const t = (path, replace) => fill(dig(strings, path), replace);
    const options = (name) => (Array.isArray(lists[name]) ? lists[name] : []);

    // ------------------------------------------------------------ saying things

    /** A line from the visitor, shown at once: what they tapped or typed. */
    function echo(text) {
        const item = turn('is-you', t('you'), text);
        el.log.append(item);
        scroll();
        return item;
    }

    /** A line from the agent, after a short pause with the typing dots, written in word by word. */
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

    /** The tappable answers under the prompt field. Each one is just another way of typing. */
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
        const done = (step) => {
            const fields = fieldsOfStep(step);
            const answered = fields.filter((field) => field.name in state.answers).length;
            return fields.length ? answered / fields.length : 0;
        };
        const current = state.pending ? state.pending.step : null;

        STEPS.forEach((step) => {
            const node = el.rail.querySelector(`[data-step="${step}"]`);
            if (!node) return;
            const fill = done(step);
            node.style.setProperty('--fill', String(fill));
            node.classList.toggle('is-done', fill >= 1);
            node.classList.toggle('is-here', step === current);
            if (step === current) node.setAttribute('aria-current', 'step'); else node.removeAttribute('aria-current');
        });

        el.chips.replaceChildren();
        const answered = FIELDS.filter((field) => field.name in state.answers);
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
    function store(field, value, shown, source) {
        state.answers[field.name] = String(value);
        state.shown[field.name] = shown;
        if (source) state.source[field.name] = source; else delete state.source[field.name];

        const existing = el.hidden.querySelector(`input[name="${field.name}"]`);
        if (String(value) === '') { existing?.remove(); return; }

        const input = existing || el.hidden.appendChild(document.createElement('input'));
        input.type = 'hidden';
        input.name = field.name;
        input.value = String(value);
    }

    // ------------------------------------------------------------ asking

    async function ask(field) {
        // A list HQ did not send cannot be chosen from, and free text would only fail HQ's own validation
        if ((field.kind === 'select' || field.kind === 'currency') && !options(field.list).length) {
            store(field, '', t('skipped'), null);
            await onward();
            return;
        }

        state.pending = field;
        paint();
        const extras = [];
        const hint = dig(strings, `hint.${field.name}`);
        await say(t(`ask.${field.name}`, { name: state.answers.name || '', count: whole.format(Number(state.answers.headcount) || 0) }), extras);
        if (hint) {
            const note = document.createElement('span');
            note.className = 'agent-note';
            note.textContent = fill(hint, { hours: String(ladder(state.week).week) });
            el.log.lastElementChild.append(note);
        }
        choices(pillsFor(field));
        el.input.focus({ preventScroll: true });
        scroll();
    }

    function pillsFor(field) {
        const skip = { label: t('skip'), tone: 'quiet', say: 'skip' };

        if (field.kind === 'select' || field.kind === 'currency') {
            const all = options(field.list);
            const shortlist = all.slice(0, field.kind === 'currency' ? 5 : 6)
                .map((option) => ({ label: option.label, say: option.label, value: option.value }));
            if (all.length > shortlist.length) shortlist.push({ label: t('more'), tone: 'quiet', more: field.list });
            return [...shortlist, skip];
        }
        if (field.name === 'mgmt_nodes' && Number(state.answers.headcount) > 0) {
            const fifth = Math.max(1, Math.round(Number(state.answers.headcount) * (field.fraction?.share ?? 0.2)));
            return [{ label: t('pills.fifth', { n: whole.format(fifth) }), say: String(fifth) }, skip];
        }
        if (field.name === 'acv') {
            return [{ label: t('pills.noncommercial'), tone: 'quiet', say: 'skip' }, skip];
        }
        if (field.kind === 'percent') {
            return [...['1', '3', '5', '10'].map((n) => ({ label: `${n} %`, say: `${n}%` })), skip];
        }
        if (field.kind === 'hours') {
            return [...['2', '5', '10', '20'].map((n) => ({ label: t('pills.hours', { n }), say: `${n} hours a week` })), skip];
        }
        return [skip];
    }

    /**
     * Going back to a field, from a chip or because they asked. It runs both from a click (nothing else is
     * happening) and from inside hear() (the busy flag is already held), so it never takes the flag itself.
     */
    async function revisit(field) {
        state.awaiting = null;
        state.phase = 'asking';
        await say(t('edit.ok', { field: t(`short.${field.name}`).toLowerCase(), value: state.shown[field.name] }));
        await ask(field);
    }

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

            // "change my industry to healthcare" goes to industry, whatever we happened to be asking. But
            // only when it announces itself as a change: plain answers name other fields all the time
            // ("we pay managers about 600,000 a month" is about pay, not about how many managers there are),
            // so while a question is open the answer to it wins unless the sentence says otherwise.
            const mayRedirect = !pill && (!state.pending || isEdit(said));
            const aimed = mayRedirect ? readTarget(said, targets().filter((entry) => entry.name !== state.pending?.name)) : null;
            if (aimed && (aimed.name in state.answers || !state.pending)) {
                const field = byName(aimed.name);
                if (field && await take(field, aimed.rest, null)) { await onward(); return; }
            }

            if (state.pending) {
                if (isSkip(said)) { await skipped(state.pending); return; }
                if (await take(state.pending, said, pill)) { await onward(); return; }
                return; // take() has already said what it did not understand
            }
            await say(t('edit.which'));
        } finally {
            state.busy = false;
            if (!state.awaiting && state.phase !== 'sending') el.input.focus({ preventScroll: true });
        }
    }

    /** Left empty on purpose: answered, so we move on, but nothing is posted for it. */
    async function skipped(field) {
        store(field, '', t('skipped'), null);
        await say(t('fine'));
        await onward();
    }

    /** The next question, or the summary when there is nothing left to ask. */
    async function onward() {
        paint();
        const next = nextField(state.answers);
        state.busy = false;
        if (next) { await ask(next.field); return; }
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
            store(field, email, email, null);
            return true;
        }

        if (field.kind === 'text') {
            const clean = text.replace(/^(i'?m|i am|my name is|we'?re|we are|it'?s|je m'appelle|c'est)\s+/iu, '').slice(0, 150);
            if (!clean) { await say(t('errors.blank')); return false; }
            store(field, clean, clean, null);
            return true;
        }

        if (field.kind === 'select' || field.kind === 'currency') {
            const list = options(field.list);
            const exact = pill?.value ? list.find((option) => option.value === pill.value) : null;
            const hit = exact || matchOption(text, list);
            if (hit) {
                store(field, hit.value, hit.label, null);
                if (field.kind === 'currency') relabelMoney();
                return true;
            }
            const near = rankOptions(text, list);
            if (near.length) {
                await say(t('errors.near'));
                state.awaiting = async (answer, chosen) => {
                    state.awaiting = null;
                    if (chosen?.value) {
                        const option = list.find((entry) => entry.value === chosen.value);
                        if (option) { store(field, option.value, option.label, null); await onward(); return; }
                    }
                    if (isSkip(answer)) { await skipped(field); return; }
                    if (await take(field, answer, null)) await onward();
                };
                choices([...near.map((option) => ({ label: option.label, value: option.value, say: option.label })),
                    { label: t('skip'), tone: 'quiet', say: 'skip' }]);
                return false;
            }
            await say(t('errors.list'));
            choices(pillsFor(field));
            return false;
        }

        if (field.kind === 'count') {
            const found = readNumber(text);
            if (!found || found.value < 0) { await say(t('errors.number')); return false; }
            const count = Math.round(found.value);
            store(field, count, whole.format(count), null);
            return true;
        }

        if (field.kind === 'percent') {
            const found = readPercent(text);
            if (!found) { await say(t('errors.number')); return false; }
            const value = Math.min(field.max ?? 100, found.value);
            store(field, value, `${number.format(value)} %`, null);
            return true;
        }

        if (field.kind === 'hours') return takeHours(field, text);
        if (field.kind === 'money') return takeMoney(field, text);
        if (field.kind === 'pay') return takePay(field, text);

        return false;
    }

    /** Hours said over any period, held as hours a week, with the sum shown when one was needed. */
    async function takeHours(field, text) {
        const found = readNumber(text);
        if (!found) { await say(t('errors.number')); return false; }

        const period = readPeriod(text);
        const result = toWeekly(found.value, period, state.week);
        const weekly = Math.min(field.max ?? 168, result.weekly);
        store(field, weekly, t('shown.hours', { n: number.format(weekly) }),
            { kind: 'hours', amount: found.value, period });

        if (result.working.length) await say(t('derived.hours', { sum: sum(result.working, null), n: number.format(weekly) }), [weekButton()]);
        return true;
    }

    /** Turnover and contract value: annual figures, whatever period they were said in. */
    async function takeMoney(field, text) {
        const said = readMoney(text);
        if (!said) { await say(t('errors.number')); return false; }
        if (said.currency) noteCurrency(said.currency);

        const result = toAnnual(said.amount, said.period, state.week);
        store(field, result.annual, cash(result.annual), { kind: 'money', amount: said.amount, period: said.period });

        if (result.working.length) {
            await say(t('derived.annual', { sum: sum(result.working, said.period), total: cash(result.annual) }));
        }
        return true;
    }

    /** A sum with the currency we know about, for a chip or a line of text. */
    const cash = (value) => `${number.format(value)} ${state.answers.currency || ''}`.trim();

    /**
     * The one that earns its keep: pay said any way at all, turned into the hourly cost the engine wants.
     * We never let it happen quietly — the sum is read back with the rung it used, and the week that rung
     * rests on is one tap away from being corrected.
     */
    async function takePay(field, text) {
        const money = readMoney(text);
        if (!money) { await say(t('errors.number')); return false; }

        // FCFA is two currencies. We ask rather than pick one and be quietly wrong about every figure.
        if (money.currency?.ambiguous && !state.answers.currency) {
            await say(t('cfa.ask'));
            state.awaiting = async (answer, pill) => {
                state.awaiting = null;
                const code = pill?.value || (/xaf|central|centrale/iu.test(answer) ? 'XAF' : 'XOF');
                setCurrency(code);
                await applyPay(field, money);
                await onward();
            };
            choices(money.currency.ambiguous.map((code) => ({ label: t(`cfa.${code.toLowerCase()}`), value: code, say: code })));
            return false;
        }
        if (money.currency?.code) setCurrency(money.currency.code);

        await applyPay(field, money);
        return true;
    }

    async function applyPay(field, money) {
        const people = money.basis === 'total' ? Number(state.answers.mgmt_nodes) || Number(state.answers.headcount) || 0 : 0;
        const result = toHourly(money, { week: state.week, people });
        store(field, result.hourly, `${number.format(result.hourly)} ${state.answers.currency || ''}/h`.trim(),
            { kind: 'pay', amount: money.amount, period: money.period, basis: money.basis });

        const shown = `${number.format(result.hourly)} ${state.answers.currency || ''}`.trim();
        const line = result.period === 'hour'
            ? t('derived.already', { rate: shown })
            : t('derived.pay', {
                said: `${number.format(money.amount)} ${state.answers.currency || ''}`.trim(),
                sum: sum(result.working, result.period, t('unit.hours')),
                rate: shown,
            });
        await say(line + (result.assumed ? ` ${t('derived.assumed')}` : ''), [weekButton()]);
    }

    function setCurrency(code) {
        const list = options('currencies');
        const option = list.find((entry) => entry.value === code);
        const field = byName('currency');
        if (option) store(field, option.value, option.label, null);
        else store(field, code, code, null);
        relabelMoney();
        paint();
    }

    /**
     * Figures answered before we knew the currency carry no code on their chip. Once we know it — or once
     * it is corrected — those chips are written again so they never disagree with the currency chip.
     */
    function relabelMoney() {
        for (const field of FIELDS) {
            if (!(field.name in state.answers) || state.answers[field.name] === '') continue;
            const value = Number(state.answers[field.name]);
            if (!Number.isFinite(value)) continue;
            if (field.kind === 'pay') {
                state.shown[field.name] = `${number.format(value)} ${state.answers.currency || ''}/h`.trim();
            } else if (field.kind === 'money') {
                state.shown[field.name] = cash(value);
            }
        }
    }

    function noteCurrency(found) {
        if (state.answers.currency || !found?.code) return;
        setCurrency(found.code);
    }

    /**
     * The working, as one readable line: "÷ 173.33 h a month" for pay, "÷ 4.33 a month" for hours.
     * unit is what the divisor counts, so money-over-hours never reads as weeks-over-hours.
     */
    function sum(working, period, unit = '') {
        return working.map((step) => {
            if (step.step === 'split') return t('working.split', { people: whole.format(step.people) });
            if (step.step === 'times') return t('working.times', { factor: number.format(step.factor) });
            return t('working.divide', { divisor: number.format(step.divisor), unit, period: t(`period.${period || step.period}`) });
        }).join(' ');
    }

    // ------------------------------------------------------------ the week behind every sum

    /** "Our week isn't 40 h" — one tap, and every figure we worked out moves with it. */
    function weekButton() {
        const hours = ladder(state.week);
        const holder = document.createElement('span');
        holder.className = 'agent-aside';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'agent-link';
        button.textContent = t('week.button', { hours: number.format(hours.week) });
        holder.append(button);

        const panel = document.createElement('span');
        panel.className = 'agent-week';
        panel.hidden = true;

        const day = spin(t('week.day'), state.week.hoursPerDay, 1, 24);
        const days = spin(t('week.days'), state.week.daysPerWeek, 1, 7);
        const apply = document.createElement('button');
        apply.type = 'button';
        apply.className = 'btn btn-primary btn-sm';
        apply.textContent = t('week.apply');
        panel.append(day.label, days.label, apply);
        holder.append(panel);

        button.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            button.classList.toggle('is-open', !panel.hidden);
            if (!panel.hidden) day.input.focus({ preventScroll: true });
        });
        apply.addEventListener('click', async () => {
            if (state.busy) return;
            state.busy = true;
            panel.hidden = true;
            button.classList.remove('is-open');
            state.week = {
                hoursPerDay: Math.min(24, Math.max(1, Number(day.input.value) || DEFAULT_WEEK.hoursPerDay)),
                daysPerWeek: Math.min(7, Math.max(1, Number(days.input.value) || DEFAULT_WEEK.daysPerWeek)),
            };
            const moved = rederive();
            paint();
            await say(moved.length
                ? t('week.done', { hours: number.format(ladder(state.week).week), fields: moved.join(', ') })
                : t('week.noted', { hours: number.format(ladder(state.week).week) }));
            state.busy = false;
        });

        return holder;
    }

    function spin(labelText, value, low, high) {
        const label = document.createElement('label');
        label.className = 'agent-week-field';
        const span = document.createElement('span');
        span.textContent = labelText;
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'input';
        input.min = String(low);
        input.max = String(high);
        input.inputMode = 'numeric';
        input.value = String(value);
        label.append(span, input);
        return { label, input };
    }

    /** Every figure we worked out ourselves, worked out again on the new week. Typed figures are left alone. */
    function rederive() {
        const moved = [];
        for (const [name, source] of Object.entries(state.source)) {
            const field = byName(name);
            if (!field || !source) continue;

            if (source.kind === 'pay') {
                const people = source.basis === 'total' ? Number(state.answers.mgmt_nodes) || Number(state.answers.headcount) || 0 : 0;
                const result = toHourly({ amount: source.amount, period: source.period, basis: source.basis }, { week: state.week, people });
                store(field, result.hourly, `${number.format(result.hourly)} ${state.answers.currency || ''}/h`.trim(), source);
                moved.push(t(`short.${name}`).toLowerCase());
            } else if (source.kind === 'hours') {
                const result = toWeekly(source.amount, source.period, state.week);
                const weekly = Math.min(field.max ?? 168, result.weekly);
                store(field, weekly, t('shown.hours', { n: number.format(weekly) }), source);
                moved.push(t(`short.${name}`).toLowerCase());
            } else if (source.kind === 'money' && source.period) {
                const result = toAnnual(source.amount, source.period, state.week);
                store(field, result.annual, `${number.format(result.annual)} ${state.answers.currency || ''}`.trim(), source);
                moved.push(t(`short.${name}`).toLowerCase());
            }
        }
        return moved;
    }

    // ------------------------------------------------------------ a long list, in full

    async function showAll(list) {
        const field = state.pending;
        if (!field) return;
        const all = options(list);
        await say(t('all', { n: String(all.length) }));
        choices([...all.map((option) => ({ label: option.label, value: option.value, say: option.label })),
            { label: t('skip'), tone: 'quiet', say: 'skip' }]);
        state.awaiting = async (answer, pill) => {
            state.awaiting = null;
            if (isSkip(answer)) { await skipped(field); return; }
            if (await take(field, answer, pill)) await onward();
        };
    }

    // ------------------------------------------------------------ the summary

    async function summarise() {
        state.phase = 'confirm';
        paint();
        const table = document.createElement('span');
        table.className = 'agent-summary';
        for (const field of FIELDS) {
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

        const turn = await say(t('confirm.heading'), [table]);
        // Scrolling to the end of a fourteen-row summary hides the sentence that explains it
        turn.scrollIntoView({ block: 'start', behavior: REDUCED() ? 'auto' : 'smooth' });
        if (el.guard) el.guard.classList.add('is-ready');
        choices([
            { label: t('confirm.run'), tone: 'go', say: 'ok' },
            { label: t('confirm.change'), tone: 'quiet', say: 'change' },
        ]);
    }

    async function atConfirm(said) {
        if (isYes(said)) { await submit(); return; }
        if (isNo(said)) {
            state.phase = 'asking';
            await say(t('confirm.which'));
            choices(FIELDS.filter((field) => field.name in state.answers)
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
        choices([{ label: t('confirm.run'), tone: 'go', say: 'ok' }, { label: t('confirm.change'), tone: 'quiet', say: 'change' }]);
    }

    async function submit() {
        state.phase = 'sending';
        choices([]);
        el.input.disabled = true;
        el.send.disabled = true;
        el.status.textContent = t('sending');
        await say(t('sent'));
        // A plain POST to the route the page form already posts to: Post/Redirect/Get, then /dossier/{token}
        el.form.submit();
    }

    // ------------------------------------------------------------ the email, asked the ordinary way

    async function begin() {
        const email = readEmail(el.gateEmail.value);
        if (!email) {
            el.gateError.textContent = t('gate.invalid');
            el.gateEmail.setAttribute('aria-invalid', 'true');
            el.gateEmail.focus({ preventScroll: true });
            return;
        }
        el.gateError.textContent = '';
        el.gateEmail.removeAttribute('aria-invalid');
        store(byName('email'), email, email, null);
        // The gate's own input carries the email to the server, tidied to exactly what we read back
        el.gateEmail.value = email;
        el.hidden.querySelector('input[name="email"]')?.remove();

        state.phase = 'asking';
        el.gate.classList.add('is-done');
        await wait(260);
        el.gate.hidden = true;
        dialog.classList.add('is-talking');   // one field needed little room; a conversation needs all of it
        el.rail.hidden = false;
        el.foot.hidden = false;
        paint();

        echo(email);
        await say(t('hello'));
        await onward();
    }

    // ------------------------------------------------------------ wiring

    el.start?.addEventListener('click', begin);
    el.gateEmail?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') { event.preventDefault(); begin(); }
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
    dialog.querySelector('[data-calc-close]')?.addEventListener('click', shut);
    dialog.addEventListener('click', (event) => { if (event.target === dialog) shut(); });
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); shut(); });

    return {
        opened() {
            if (state.phase === 'gate') el.gateEmail?.focus({ preventScroll: true });
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

/** The agent's words arrive one at a time, as they do in Ask Praxis. Still with reduced motion. */
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
