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
//   picker    a list too long to tap through; a search box with the matches under it
//   file      chosen on a real file input in the form, because a File cannot be put in a hidden one
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

/** /contact (App\Http\Controllers\ContactController). */
export const contact = {
    name: 'contact',
    steps: ['you', 'organisation', 'reach', 'situation', 'systems'],
    fields: [
        // What they came to say, asked the ordinary way before any of the talking
        { name: 'message', kind: 'longtext', step: 'you', required: true, gate: true, max: 2000, minChars: 10 },

        { name: 'full_name', kind: 'text', step: 'you', required: true, max: 100 },
        { name: 'email', kind: 'email', step: 'you', required: true, max: 100 },
        { name: 'title', kind: 'text', step: 'you', max: 100 },

        { name: 'company_name', kind: 'text', step: 'organisation', required: true, max: 150 },
        { name: 'city', kind: 'text', step: 'organisation', max: 100 },

        // Every country, so the one to tap through is a search box rather than a list of 247
        { name: 'country_code', kind: 'picker', step: 'reach', list: 'countries', default: '+234' },
        { name: 'phone', kind: 'tel', step: 'reach', max: 20 },
        { name: 'language', kind: 'select', step: 'reach', list: 'languages' },

        { name: 'start_timing', kind: 'select', step: 'situation', list: 'start_timings' },
        { name: 'stage', kind: 'select', step: 'situation', list: 'stages' },
        { name: 'bottleneck', kind: 'select', step: 'situation', list: 'bottlenecks' },
        // The page form hides this until the bottleneck is "other"; here it is simply the next question
        { name: 'other_bottleneck', kind: 'text', step: 'situation', max: 255, required: true, showWhen: { field: 'bottleneck', is: 'other' } },

        { name: 'tech_reality', kind: 'select', step: 'systems', list: 'tech_realities' },
        { name: 'gov_status', kind: 'select', step: 'systems', list: 'gov_statuses' },
    ],
};

/** /careers (App\Http\Controllers\CareersController). Every field is required but the portfolio. */
export const careers = {
    name: 'careers',
    steps: ['you', 'reach', 'skills', 'work'],
    fields: [
        // Why they want to work here, asked the ordinary way before any of the talking
        { name: 'praxis_answer', kind: 'longtext', step: 'you', required: true, gate: true, max: 1000, minChars: 20 },

        { name: 'full_name', kind: 'text', step: 'you', required: true, max: 100 },
        { name: 'email', kind: 'email', step: 'you', required: true, max: 100 },

        { name: 'country_code', kind: 'picker', step: 'reach', list: 'countries', required: true, default: '+234' },
        { name: 'phone', kind: 'tel', step: 'reach', required: true, max: 20 },
        { name: 'city', kind: 'text', step: 'reach', required: true, max: 255 },

        { name: 'primary_skill', kind: 'select', step: 'skills', list: 'primary_skills', required: true },
        // The page form hides this until the skill is "other"; here it is simply the next question
        { name: 'other_skill', kind: 'text', step: 'skills', required: true, max: 100, showWhen: { field: 'primary_skill', is: 'other' } },
        { name: 'secondary_skillset', kind: 'text', step: 'skills', required: true, max: 255 },

        { name: 'linkedin_url', kind: 'url', step: 'work', required: true, max: 255, host: 'linkedin.' },
        { name: 'portfolio_url', kind: 'url', step: 'work', max: 255 },
        // The controller's own rules (CareersController::CV_RULES), checked here so a 12 MB file is caught
        // in the conversation rather than after it is sent
        { name: 'cv', kind: 'file', step: 'work', required: true, accept: ['pdf', 'doc', 'docx'], maxBytes: 5 * 1024 * 1024 },
    ],
};

export const FORMS = { feedback, contact, careers };
