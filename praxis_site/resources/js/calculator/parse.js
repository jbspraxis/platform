// The conversational calculator's reader: plain sentences in, structured figures out.
//
// Nothing here touches the DOM and nothing here guesses silently. Every function returns what it understood
// together with the working it used, so the agent can say the sum out loud ("600,000 XOF a month ÷ 173.3 h")
// and the visitor can correct any part of it. The engine is told an hourly figure; the visitor is never asked
// to do the division themselves.
//
// The reader is deterministic: it runs offline, costs nothing and answers in the same millisecond. interpret()
// is the one seam where a server-side reader could be added later without changing anything that calls it.

// ---------------------------------------------------------------- the working week

/** The baseline ladder (docs/BRAND.md has no opinion on this; these are the common defaults). */
export const DEFAULT_WEEK = { hoursPerDay: 8, daysPerWeek: 5 };

const clamp = (value, low, high) => Math.min(high, Math.max(low, value));

/**
 * Every period expressed in hours, from one week shape. A month is 52/12 weeks, so the ladder stays
 * consistent: changing the week re-derives the month and the year with it.
 */
export function ladder(week = DEFAULT_WEEK) {
    const hoursPerDay = clamp(Number(week.hoursPerDay) || DEFAULT_WEEK.hoursPerDay, 1, 24);
    const daysPerWeek = clamp(Number(week.daysPerWeek) || DEFAULT_WEEK.daysPerWeek, 1, 7);
    const hoursPerWeek = hoursPerDay * daysPerWeek;

    return {
        hoursPerDay,
        daysPerWeek,
        hour: 1,
        shift: hoursPerDay,
        day: hoursPerDay,
        week: hoursPerWeek,
        month: (hoursPerWeek * 52) / 12,
        year: hoursPerWeek * 52,
    };
}

// ---------------------------------------------------------------- text

/** Lower case, accents dropped, punctuation to spaces: "Coût horaire" and "cout horaire" read the same. */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/gu, '')
        .toLowerCase()
        .replace(/[‘’ʼ]/gu, "'")
        .replace(/[^a-z0-9%.,/'\s-]/gu, ' ')
        .replace(/\s+/gu, ' ')
        .trim();
}

// ---------------------------------------------------------------- numbers

const MAGNITUDES = [
    { words: ['bn', 'billion', 'billions', 'milliard', 'milliards'], factor: 1e9 },
    { words: ['m', 'mn', 'million', 'millions'], factor: 1e6 },
    { words: ['k', 'thousand', 'thousands', 'mille', 'milliers'], factor: 1e3 },
];

/**
 * The first number in the text, with its magnitude word if there is one.
 *
 * Separators are read by shape, never by locale guessing: "600,000" and "600 000" and "600.000" are all
 * six hundred thousand (three-digit groups), while "1,5" and "1.5" are one and a half (one or two decimals).
 * "1.2m", "250k", "2 millions" and "1,2 million" all work.
 */
export function readNumber(text) {
    const source = fold(text);
    // A number, then optionally a magnitude word (joined or spaced)
    const pattern = /(\d+(?:[.,\s  ]\d+)*)\s*(bn|billions?|milliards?|mn|m|millions?|k|thousands?|mille|milliers)?\b/u;
    const match = source.match(pattern);
    if (!match) return null;

    const digits = match[1].replace(/[\s  ]/gu, '');
    let value;
    if (/^\d{1,3}(?:[.,]\d{3})+$/u.test(digits)) {
        value = Number(digits.replace(/[.,]/gu, '')); // grouped thousands
    } else if (/^\d+[.,]\d{1,2}$/u.test(digits)) {
        value = Number(digits.replace(',', '.')); // a decimal
    } else {
        value = Number(digits.replace(/[.,]/gu, ''));
    }
    if (!Number.isFinite(value)) return null;

    const word = match[2];
    if (word) {
        const magnitude = MAGNITUDES.find((entry) => entry.words.includes(word));
        if (magnitude) value *= magnitude.factor;
    }

    return { value, text: match[0].trim(), index: match.index };
}

/** A percentage: "3%", "about 3 percent", "3 pour cent". Falls back to a bare number. */
export function readPercent(text) {
    const source = fold(text);
    const number = readNumber(source);
    if (!number) return null;
    const after = source.slice(number.index + number.text.length, number.index + number.text.length + 12);
    const marked = /%|percent|per cent|pour ?cent/u.test(after) || /%/u.test(number.text);
    // A share of work redone is a percentage whether or not they typed the sign
    return { value: number.value, marked };
}

// ---------------------------------------------------------------- currency

// Symbols and names we can recognise without being told. FCFA is deliberately ambiguous: see readMoney.
const CURRENCIES = [
    { code: 'NGN', words: ['ngn', 'naira', 'nairas'], symbols: ['₦'] },
    { code: 'GHS', words: ['ghs', 'cedi', 'cedis'], symbols: ['₵'] },
    { code: 'KES', words: ['kes', 'ksh', 'shilling', 'shillings'], symbols: [] },
    { code: 'ZAR', words: ['zar', 'rand'], symbols: [] },
    { code: 'MAD', words: ['mad', 'dirham', 'dirhams'], symbols: [] },
    { code: 'EGP', words: ['egp'], symbols: [] },
    { code: 'USD', words: ['usd', 'dollar', 'dollars'], symbols: ['$'] },
    { code: 'EUR', words: ['eur', 'euro', 'euros'], symbols: ['€'] },
    { code: 'GBP', words: ['gbp', 'pound', 'pounds', 'sterling'], symbols: ['£'] },
    { code: 'CAD', words: ['cad'], symbols: [] },
    { code: 'XOF', words: ['xof'], symbols: [] },
    { code: 'XAF', words: ['xaf'], symbols: [] },
];

/** The CFA franc, written as FCFA or CFA, is two different currencies. We ask rather than assume. */
const CFA = /\b(f ?cfa|cfa|francs? cfa)\b/u;

/**
 * A currency code from free text, or null. Returns { code } when certain, or { ambiguous: ['XOF','XAF'] }
 * when they wrote FCFA without saying which one.
 */
export function readCurrency(text) {
    const raw = String(text ?? '');
    const source = fold(raw);

    for (const currency of CURRENCIES) {
        if (currency.words.some((word) => new RegExp(`\\b${word}\\b`, 'u').test(source))) {
            return { code: currency.code };
        }
    }
    if (CFA.test(source)) return { ambiguous: ['XOF', 'XAF'] };
    for (const currency of CURRENCIES) {
        if (currency.symbols.some((symbol) => raw.includes(symbol))) return { code: currency.code };
    }

    return null;
}

// ---------------------------------------------------------------- period and basis

const PERIODS = [
    { key: 'hour', words: ['hour', 'hourly', 'hr', 'h', 'heure', 'horaire', 'de l heure'] },
    { key: 'shift', words: ['shift', 'shifts', 'poste', 'postes', 'vacation'] },
    { key: 'day', words: ['day', 'daily', 'jour', 'journee', 'journalier', 'par jour'] },
    { key: 'week', words: ['week', 'weekly', 'semaine', 'hebdomadaire'] },
    { key: 'month', words: ['month', 'monthly', 'mois', 'mensuel', 'mensuelle'] },
    { key: 'year', words: ['year', 'yearly', 'annual', 'annually', 'annum', 'an', 'annee', 'annuel', 'annuelle'] },
];

/** The period named in the text ("per month", "a year", "par mois"), or null. */
export function readPeriod(text) {
    const source = fold(text);
    for (const period of PERIODS) {
        for (const word of period.words) {
            if (new RegExp(`(^|\\s|/)(per |a |an |each |par |le |la |l'|/)?${word}\\b`, 'u').test(source)) {
                return period.key;
            }
        }
    }
    return null;
}

/** Whether a figure covers one person or the whole group: "each" / "per manager" vs "altogether". */
export function readBasis(text) {
    const source = fold(text);
    if (/\b(in total|total|altogether|all together|combined|between them|au total|ensemble|a nous tous)\b/u.test(source)) return 'total';
    if (/\b(each|apiece|per (person|head|manager|employee|staff|one)|a head|a piece|chacun|par (personne|responsable|salarie|tete))\b/u.test(source)) return 'each';
    return null;
}

/**
 * A sum of money said in any ordinary way: amount, currency, period and whether it is per person.
 * "we pay managers about 600,000 FCFA per month" -> { amount: 600000, currency: ambiguous CFA, period: 'month' }
 */
export function readMoney(text) {
    const number = readNumber(text);
    if (!number) return null;

    return {
        amount: number.value,
        currency: readCurrency(text),
        period: readPeriod(text),
        basis: readBasis(text),
    };
}

// ---------------------------------------------------------------- deriving an hourly cost

const round = (value, places = 2) => {
    const factor = 10 ** places;
    return Math.round(value * factor) / factor;
};

/**
 * A pay figure turned into the hourly cost the engine wants, with every step it took.
 *
 * `working` is a list of steps the agent reads back ("÷ 173.3 h a month"), so the visitor can see which
 * assumption to argue with. Re-run it with a different week and every figure moves with it.
 *
 * @param {{amount:number, period:?string, basis:?string}} money
 * @param {{week?:object, people?:number}} context  people: how many the figure covers, when it is a total
 * @returns {{hourly:number, period:string, assumed:boolean, working:Array, people:?number}}
 */
export function toHourly(money, context = {}) {
    const hours = ladder(context.week);
    const period = money.period || 'month'; // the commonest way people quote pay; flagged as assumed
    const assumed = !money.period;
    const divisor = hours[period] ?? hours.month;
    const working = [];

    let amount = money.amount;
    let people = null;

    // A total for the whole group becomes one person's figure before it becomes an hourly one
    if (money.basis === 'total' && Number(context.people) > 0) {
        people = Number(context.people);
        amount = amount / people;
        working.push({ step: 'split', people, result: round(amount) });
    }

    const hourly = period === 'hour' ? amount : amount / divisor;
    if (period !== 'hour') {
        working.push({ step: 'divide', period, divisor: round(divisor, 1), result: round(hourly) });
    }

    return { hourly: round(hourly), period, assumed, working, people };
}

// ---------------------------------------------------------------- matching a list

/** How alike two folded strings are, 0 to 1, by shared words and then by shared characters. */
function similarity(a, b) {
    if (a === b) return 1;
    if (!a || !b) return 0;
    if (b.startsWith(a) || a.startsWith(b)) return 0.92;
    if (b.includes(a) || a.includes(b)) return 0.84;

    const wordsA = new Set(a.split(' ').filter((word) => word.length > 2));
    const wordsB = new Set(b.split(' ').filter((word) => word.length > 2));
    if (wordsA.size && wordsB.size) {
        let shared = 0;
        for (const word of wordsA) if (wordsB.has(word)) shared += 1;
        if (shared) return 0.6 + (0.3 * shared) / Math.max(wordsA.size, wordsB.size);
    }

    // Shared character bigrams, for near misses and typos ("helthcare")
    const grams = (text) => {
        const out = new Set();
        for (let i = 0; i < text.length - 1; i += 1) out.add(text.slice(i, i + 2));
        return out;
    };
    const gramsA = grams(a);
    const gramsB = grams(b);
    if (!gramsA.size || !gramsB.size) return 0;
    let shared = 0;
    for (const gram of gramsA) if (gramsB.has(gram)) shared += 1;
    return (2 * shared) / (gramsA.size + gramsB.size);
}

/**
 * The option a typed answer means, or null when nothing is close enough.
 * @param {string} text
 * @param {Array<{value:string,label:string}>} options
 * @returns {{value:string,label:string,score:number}|null}
 */
export function matchOption(text, options) {
    const source = fold(text);
    if (!source) return null;

    let best = null;
    for (const option of options) {
        const score = Math.max(similarity(source, fold(option.label)), similarity(source, fold(option.value)));
        if (!best || score > best.score) best = { ...option, score };
    }

    return best && best.score >= 0.62 ? best : null;
}

/** Every option that matches well enough, best first: for "did you mean one of these?" pills. */
export function rankOptions(text, options, limit = 4) {
    const source = fold(text);
    if (!source) return [];

    return options
        .map((option) => ({
            ...option,
            score: Math.max(similarity(source, fold(option.label)), similarity(source, fold(option.value))),
        }))
        .filter((option) => option.score >= 0.4)
        .sort((a, b) => b.score - a.score)
        .slice(0, limit);
}

// ---------------------------------------------------------------- intent

const EMAIL = /[^\s@]+@[^\s@.]+\.[^\s@]{2,}/u;

/** The email address in a sentence, or null. */
export function readEmail(text) {
    const match = String(text ?? '').match(EMAIL);
    return match ? match[0].replace(/[.,;]$/u, '') : null;
}

const SKIP = /^(skip|pass|next|dunno|don'?t know|no idea|not sure|unsure|n\/?a|none|aucun|je ne sais pas|sais pas|passer|suivant|peu importe)\b/u;
const YES = /^(ok|okay|yes|yep|yeah|sure|go|go ahead|send|run it|do it|looks? good|that'?s right|correct|confirm|oui|d'accord|c'est bon|vas-y|envoie|parfait)\b/u;
const NO = /^(no|nope|wait|hold on|not quite|change|edit|not right|wrong|non|attends|pas tout a fait|modifier|corriger)\b/u;

export const isSkip = (text) => SKIP.test(fold(text));
export const isYes = (text) => YES.test(fold(text));
export const isNo = (text) => NO.test(fold(text));

/**
 * Whether a sentence is asking to change something, rather than answering what was asked.
 *
 * This matters because ordinary answers mention field names all the time: "we pay managers about 600,000
 * FCFA a month" is an answer about pay that happens to contain the word managers. Without a marker it would
 * be read as a correction to the number of managers, and the pay question would go unanswered.
 */
const EDIT = /\b(change|changing|edit|actually|instead|rather|correct|correction|make it|should be|set|update|scratch that|en fait|plutot|corriger|modifier|remplacer|mettre|finalement)\b/u;

export const isEdit = (text) => EDIT.test(fold(text));

/**
 * A correction aimed at a field we have already filled: "change my industry to healthcare",
 * "actually make it 120 staff", "the error rate is 5%".
 *
 * @param {string} text
 * @param {Array<{name:string, words:string[]}>} fields  each field's name and the words people use for it
 * @returns {{name:string, rest:string}|null}
 */
export function readTarget(text, fields) {
    const source = fold(text);
    if (!source) return null;

    let best = null;
    for (const field of fields) {
        for (const word of field.words) {
            const folded = fold(word);
            if (!folded) continue;
            const at = source.indexOf(folded);
            // Longer words win: "hourly cost" beats "cost"
            if (at !== -1 && (!best || folded.length > best.length)) {
                best = { name: field.name, at, length: folded.length };
            }
        }
    }
    if (!best) return null;

    const rest = source.slice(best.at + best.length).replace(/^(\s*(is|to|=|:|should be|est|a|vaut)\s*)/u, ' ').trim();
    return { name: best.name, rest: rest || source };
}

/**
 * The one seam for a server-side reader. Everything above is deterministic and instant; if a sentence ever
 * needs more than that, this is where a call to HQ would go, behind the same shape of answer.
 */
export async function interpret(text, context) {
    return { handled: false, text, context };
}

// ---------------------------------------------------------------- the other two ladders

/**
 * Hours said in any period, expressed per week. "2 hours a day" is ten hours a week on a five-day week,
 * so this moves with the week shape exactly as toHourly() does.
 *
 * @returns {{weekly:number, period:string, assumed:boolean, working:Array}}
 */
export function toWeekly(amount, period, week) {
    const hours = ladder(week);
    const named = period || 'week';
    const assumed = !period;
    const working = [];
    let weekly = amount;

    if (named === 'day' || named === 'shift') {
        weekly = amount * hours.daysPerWeek;
        working.push({ step: 'times', period: named, factor: hours.daysPerWeek, result: round(weekly) });
    } else if (named === 'month') {
        weekly = (amount * 12) / 52;
        working.push({ step: 'divide', period: named, divisor: round(52 / 12, 2), result: round(weekly) });
    } else if (named === 'year') {
        weekly = amount / 52;
        working.push({ step: 'divide', period: named, divisor: 52, result: round(weekly) });
    } else if (named === 'hour') {
        weekly = amount * hours.week;
        working.push({ step: 'times', period: named, factor: hours.week, result: round(weekly) });
    }

    return { weekly: round(weekly), period: named, assumed, working };
}

/**
 * A sum said over any period, expressed per year: turnover and contract value are annual figures.
 *
 * @returns {{annual:number, period:string, assumed:boolean, working:Array}}
 */
export function toAnnual(amount, period, week) {
    const hours = ladder(week);
    const named = period || 'year';
    const assumed = !period;
    const working = [];
    let annual = amount;

    const factors = { month: 12, week: 52, day: hours.daysPerWeek * 52, shift: hours.daysPerWeek * 52, hour: hours.year };
    if (factors[named]) {
        annual = amount * factors[named];
        working.push({ step: 'times', period: named, factor: round(factors[named], 1), result: round(annual) });
    }

    return { annual: round(annual), period: named, assumed, working };
}
