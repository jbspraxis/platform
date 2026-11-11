<?php

/*
| The private API the marketing site (praxis_site) uses on hq.jbspraxis.com: docs/SITE_API.md.
| Every request is signed with HMAC-SHA256 by a key listed here. Secrets live only in .env
| (never in git, never logged).
*/

return [

    // Key id => secret. The previous pair stays valid while a new one is rolled out (rotation):
    // set SITE_API_KEY_ID/SITE_API_SECRET to the new pair on both sides, keep the old one in
    // SITE_API_KEY_ID_PREVIOUS/SITE_API_SECRET_PREVIOUS until the site sends the new id, then remove it.
    'keys' => (function () {
        $keys = [];
        foreach ([['SITE_API_KEY_ID', 'SITE_API_SECRET'], ['SITE_API_KEY_ID_PREVIOUS', 'SITE_API_SECRET_PREVIOUS']] as [$id, $secret]) {
            $keyId = (string) env($id, '');
            $value = (string) env($secret, '');
            if ($keyId !== '' && $value !== '') {
                $keys[$keyId] = $value;
            }
        }

        return $keys;
    })(),

    // Seconds a request timestamp may differ from server time
    'window' => 300,

    // Seconds a nonce is remembered (a replay inside this time is refused)
    'nonce_ttl' => 600,

    // Requests that fail authentication, per connecting address and minute; past it: 429, nothing logged
    'auth_failures_per_minute' => 30,

    /*
    | Limits (docs/SITE_API.md section 6.2). The old Livewire pages and the API share these numbers and
    | the same limiter keys (App\Services\Site\SiteLimits), so a visitor never gets two budgets.
    | "visitor" = the IPv4 address, or the IPv6 /64 network. Windows: minute, hour, day, week, month (30 days).
    | "global" = all visitors together: a circuit breaker that also raises an alert in HQ → System health.
    | Tighten a number here, then run php artisan config:cache on the server.
    */
    'limits' => [
        'enquiry' => ['visitor' => ['hour' => 3, 'day' => 6], 'email' => ['day' => 2], 'global' => ['hour' => 60]],
        'booking' => ['visitor' => ['day' => 2], 'global' => ['hour' => 20]],
        'assessment' => ['visitor' => ['hour' => 3, 'day' => 8], 'email' => ['day' => 3], 'global' => ['hour' => 60]],
        'kaizen' => ['visitor' => ['day' => 2], 'email' => ['week' => 2], 'global' => ['hour' => 20]],
        'feedback' => ['visitor' => ['hour' => 3, 'day' => 10], 'global' => ['hour' => 60]],
        'careers' => ['visitor' => ['day' => 2], 'global' => ['hour' => 30]],
        'method_pdf' => ['visitor' => ['day' => 3], 'email' => ['day' => 1], 'global' => ['hour' => 60]],
        'chat' => ['visitor' => ['10min' => 10, 'day' => 30], 'global' => ['hour' => 300]],
        'read' => ['visitor' => ['minute' => 120], 'global' => ['minute' => (int) env('SITE_API_GLOBAL_READS_PER_MINUTE', 1200)]],
        // Every email to an address a visitor typed (confirmations, the cost report, the method PDF)
        'visitor_email' => ['recipient' => ['day' => 3], 'global' => ['day' => 300]],
    ],

    // Questions per chat conversation (the site sends question_number)
    'chat_questions_per_conversation' => 20,

    // AI model calls a day, all callers together: Praxis AI, the old chat widget and the calculator
    'ai_calls_per_day' => (int) env('SITE_API_AI_PER_DAY', 500),

    // Seconds of AI time one Praxis AI answer may use on the API path (the site waits 35 s)
    'chat_ai_seconds' => 25,

    // A free audit can be booked at most this many days ahead (Lagos time)
    'booking_days_ahead' => 92,

    // Careers: one application per email in this many days; a repeat updates it and adds the CV
    'careers_repeat_days' => 30,

    // Visitor-typed text in visitor emails: the first name only, at most this many characters
    'email_first_name_max' => 40,

    // The day the new jbspraxis.com went live. System health → Website enquiries counts from here while no
    // enquiry has been recorded yet, so an empty history (a new site, or test enquiries cleared) is not a fault.
    'live_since' => env('SITE_LIVE_SINCE', '2026-10-30'),
];
