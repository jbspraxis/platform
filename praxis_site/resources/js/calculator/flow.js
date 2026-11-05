// The shape of the conversation: six steps, thirteen fields, and the email asked for before any of it.
//
// Only structure lives here. Every word the visitor reads comes from lang/{en,fr}/site.php, handed to the
// engine as JSON on the dialog (the CSP allows no inline script, so strings travel as a data attribute).
// The field names are exactly the form's input names, so the hidden form posts to the existing controller
// with nothing changed on the server.

/** The six segments of the progress rail, in order. */
export const STEPS = ['you', 'organisation', 'scale', 'flow', 'quality', 'money'];

/**
 * kind decides how an answer is read and shown:
 *   text     a name, taken as typed
 *   email    checked, and the one field the report cannot be made without
 *   select   one of HQ's lists; pills to tap, or type and we match it
 *   count    whole people
 *   hours    hours in a week
 *   percent  a share of work
 *   money    a sum, with its currency
 *   pay      a sum said any way at all, turned into an hourly cost (the agentic one)
 *   currency one of HQ's currency codes, usually already known by the time we ask
 *
 * words are what people call the field when they ask to change it later, in both languages.
 */
export const FIELDS = [
    {
        name: 'email', kind: 'email', step: 'you', required: true, gate: true,
        words: ['email', 'e-mail', 'mail', 'address', 'adresse', 'courriel'],
    },
    {
        name: 'name', kind: 'text', step: 'you',
        words: ['my name', 'name', 'called', 'nom', 'prenom', "m'appelle"],
    },
    {
        name: 'company', kind: 'text', step: 'you',
        words: ['company', 'organisation', 'organization', 'employer', 'firm', 'entreprise', 'societe', 'boite'],
    },
    {
        name: 'industry', kind: 'select', step: 'organisation', list: 'industries',
        words: ['industry', 'sector', 'secteur', 'industrie', 'domaine'],
    },
    {
        name: 'tool', kind: 'select', step: 'organisation', list: 'systems',
        words: ['system', 'tool', 'software', 'platform', 'systeme', 'outil', 'logiciel', 'plateforme'],
    },
    {
        name: 'headcount', kind: 'count', step: 'scale',
        words: ['headcount', 'staff', 'employees', 'people', 'team size', 'salaries', 'effectif', 'employes', 'personnes'],
    },
    {
        name: 'mgmt_nodes', kind: 'count', step: 'scale', fraction: { of: 'headcount', share: 0.2 },
        words: ['managers', 'manager', 'supervisors', 'approvers', 'responsables', 'encadrants', 'superviseurs'],
    },
    {
        name: 'bottleneck', kind: 'select', step: 'flow', list: 'bottlenecks',
        words: ['bottleneck', 'slows', 'slow down', 'delay', 'blocage', 'goulot', 'ralentit'],
    },
    {
        name: 'manual_hrs', kind: 'hours', step: 'flow', max: 80,
        words: ['manual checks', 'checking', 'checks', 'hours', 'manual hours', 'controles', 'verification', 'heures'],
    },
    {
        name: 'error_rate', kind: 'percent', step: 'quality', max: 100,
        words: ['error rate', 'errors', 'rework', 'redone', 'redo', 'taux d erreur', 'erreurs', 'refaire'],
    },
    {
        name: 'acv', kind: 'money', step: 'quality', annual: true, skippable: 'noncommercial',
        words: ['contract', 'contract value', 'customer value', 'acv', 'client', 'contrat', 'valeur client'],
    },
    // Pay comes before currency on purpose: "600,000 FCFA a month" tells us both, so the currency question
    // is usually answered before it is asked, and only put to anyone whose figures named no currency at all.
    {
        name: 'hourly_cost', kind: 'pay', step: 'money',
        words: ['hourly cost', 'hourly', 'pay', 'salary', 'wage', 'cost of a manager', 'cout horaire', 'salaire', 'paie', 'remuneration'],
    },
    {
        name: 'turnover', kind: 'money', step: 'money', annual: true,
        words: ['turnover', 'revenue', 'budget', 'income', 'chiffre d affaires', 'ca', 'recettes'],
    },
    {
        name: 'currency', kind: 'currency', step: 'money', list: 'currencies',
        words: ['currency', 'devise', 'monnaie'],
    },
];

export const byName = (name) => FIELDS.find((field) => field.name === name) || null;

/** The fields of one step, in order. */
export const fieldsOfStep = (step) => FIELDS.filter((field) => field.step === step && !field.gate);

/** The words every field answers to, for readTarget(). */
export const targets = () => FIELDS.map((field) => ({ name: field.name, words: field.words }));

/**
 * What to ask next: the first field of the current step that is neither answered nor deliberately skipped,
 * then on into the following steps. Returns null when there is nothing left to ask.
 */
export function nextField(answers, from = 0) {
    for (let index = from; index < STEPS.length; index += 1) {
        const field = fieldsOfStep(STEPS[index]).find((entry) => !(entry.name in answers));
        if (field) return { field, stepIndex: index };
    }
    return null;
}

/** Which fields are numbers the controller sends to HQ as plain digits (App\Http\Controllers\CalculatorController). */
export const NUMERIC = ['acv', 'headcount', 'mgmt_nodes', 'turnover', 'manual_hrs', 'hourly_cost', 'error_rate'];
