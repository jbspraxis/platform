// What each conversational form asks, and in what order.
//
// Structure only: every word the visitor reads comes from lang/{en,fr}/site.php, handed to the engine as
// JSON on the dialog (the CSP allows no inline script, so strings travel as a data attribute). Field names
// are exactly the page form's input names, so the hidden form posts to the existing controller unchanged.
//
// kind decides how an answer is read:
//   text      taken as typed
//   longtext  the same, but longer, and shortened on its chip
//   email     checked
//   tel       a phone number, kept in the shape it was written
//   url       a web address, with or without the scheme (host narrows it, e.g. linkedin.com)
//   select    one of the page's lists; pills to tap, or type and we match it
//
// required fields may not be skipped. minChars mirrors the page form's own minimum.
// showWhen mirrors the page form's data-show-when.

/** /feedback (App\Http\Controllers\FeedbackController). */
export const feedback = {
    name: 'feedback',
    steps: ['what', 'you'],
    fields: [
        { name: 'description', kind: 'longtext', step: 'what', required: true, gate: true, max: 5000, minChars: 10 },
        { name: 'category', kind: 'select', step: 'what', list: 'categories', required: true },
        { name: 'severity', kind: 'select', step: 'what', list: 'severities', required: true, default: '1' },
        { name: 'identity', kind: 'text', step: 'you', max: 255 },
        { name: 'email', kind: 'email', step: 'you', max: 255 },
    ],
};

export const FORMS = { feedback };
