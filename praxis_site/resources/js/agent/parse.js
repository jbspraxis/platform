// The shared agent reader: plain sentences in, structured answers out.
//
// This is deliberately its own copy, not an import from resources/js/calculator. The calculator is live and
// must not change because a later form needed the reader to behave differently; nothing here is shared with
// it, so nothing here can break it.
//
// Everything is deterministic: it runs offline, costs nothing, and answers in the same millisecond.
// interpret() is the one seam where a server-side reader could be added later.

// ---------------------------------------------------------------- text

/** Lower case, accents dropped, punctuation to spaces: "Coût horaire" and "cout horaire" read the same. */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/gu, '')
        .toLowerCase()
        .replace(/[‘’ʼ]/gu, "'")
        .replace(/[^a-z0-9%.,:/'\s-]/gu, ' ')
        .replace(/\s+/gu, ' ')
        .trim();
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
 * @param {Array<{value:string,label:string}>} options
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

// ---------------------------------------------------------------- single values

const EMAIL = /[^\s@]+@[^\s@.]+\.[^\s@]{2,}/u;

/** The email address in a sentence, or null. */
export function readEmail(text) {
    const match = String(text ?? '').match(EMAIL);
    return match ? match[0].replace(/[.,;]$/u, '') : null;
}

/**
 * A web address, however it was typed. "linkedin.com/in/ada" and "www.ada.dev" both become https URLs,
 * because people rarely type the scheme and the field validates as a URL.
 */
export function readUrl(text, host = null) {
    const raw = String(text ?? '').trim().split(/\s+/u).find((part) => /[./]/u.test(part));
    if (!raw) return null;

    let url = raw.replace(/[.,;)]+$/u, '');
    if (!/^https?:\/\//iu.test(url)) url = 'https://' + url.replace(/^\/+/u, '');
    try {
        const parsed = new URL(url);
        if (!parsed.hostname.includes('.')) return null;
        if (host && !parsed.hostname.toLowerCase().includes(host)) return null;
        return parsed.toString().replace(/\/$/u, '');
    } catch {
        return null;
    }
}

/** A phone number as typed, keeping the shape people wrote (digits, spaces, + and dashes). */
export function readPhone(text) {
    const cleaned = String(text ?? '').replace(/[^\d+\s().-]/gu, ' ').replace(/\s+/gu, ' ').trim();
    return (cleaned.match(/\d/gu) || []).length >= 6 ? cleaned : null;
}

/** A first number in the text, for counts. */
export function readNumber(text) {
    const match = fold(text).match(/\d+(?:[.,]\d+)?/u);
    if (!match) return null;
    const value = Number(match[0].replace(',', '.'));
    return Number.isFinite(value) ? value : null;
}

// ---------------------------------------------------------------- intent

const SKIP = /^(skip|pass|next|dunno|don'?t know|no idea|not sure|unsure|n\/?a|none|nothing|aucun|je ne sais pas|sais pas|passer|suivant|rien|peu importe)\b/u;
const YES = /^(ok|okay|yes|yep|yeah|sure|go|go ahead|send|send it|do it|looks? good|that'?s right|correct|confirm|oui|d'accord|c'est bon|vas-y|envoie|parfait)\b/u;
const NO = /^(no|nope|wait|hold on|not quite|change|edit|not right|wrong|non|attends|pas tout a fait|modifier|corriger)\b/u;

/**
 * Whether a sentence asks to change something rather than answering what was asked. Ordinary answers name
 * fields all the time, so a correction has to announce itself before it may redirect an open question.
 */
const EDIT = /\b(change|changing|edit|actually|instead|rather|correct|correction|make it|should be|set|update|scratch that|en fait|plutot|corriger|modifier|remplacer|mettre|finalement)\b/u;

export const isSkip = (text) => SKIP.test(fold(text));
export const isYes = (text) => YES.test(fold(text));
export const isNo = (text) => NO.test(fold(text));
export const isEdit = (text) => EDIT.test(fold(text));

/**
 * A correction aimed at a field already filled: "change my email to ada@example.com".
 * @param {Array<{name:string, words:string[]}>} fields
 */
export function readTarget(text, fields) {
    const source = fold(text);
    if (!source) return null;

    let best = null;
    for (const field of fields) {
        for (const word of field.words || []) {
            const folded = fold(word);
            if (!folded) continue;
            const at = source.indexOf(folded);
            // Longer words win: "company name" beats "name"
            if (at !== -1 && (!best || folded.length > best.length)) {
                best = { name: field.name, at, length: folded.length };
            }
        }
    }
    if (!best) return null;

    const rest = source.slice(best.at + best.length).replace(/^(\s*(is|to|=|:|should be|est|a|vaut)\s*)/u, ' ').trim();
    return { name: best.name, rest: rest || source };
}

/** The one seam for a server-side reader; everything above is deterministic and instant. */
export async function interpret(text, context) {
    return { handled: false, text, context };
}
