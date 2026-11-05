<?php

// The Site API on HQ (docs/SITE_API.md, section 6.6 for the timeouts). App\Support\HqApi is the only client.
return [

    // Base URL including the /api/site/v1 prefix. Locally http://127.0.0.1:8001/api/site/v1.
    // Must be https when APP_ENV=production: HqApi refuses to send anything otherwise.
    'base' => rtrim((string) env('SITE_API_BASE', 'http://127.0.0.1:8001/api/site/v1'), '/'),

    // Optional Host header, for a local HQ reached by IP (e.g. hq.localhost). Empty on live.
    'host' => env('SITE_API_HOST') ?: null,

    // Key id and shared secret (git-ignored .env only; never logged).
    'key_id' => (string) env('SITE_API_KEY_ID', ''),
    'secret' => (string) env('SITE_API_SECRET', ''),

    // Seconds per kind of request (section 6.6). Reads get one retry; writes and chat are never retried.
    // HQ caps its own AI time for a chat turn at 25 s, so the site waits a little longer than that.
    'timeouts' => [
        'read' => 8,
        'write' => 20,
        'chat' => 35,
        'pixel' => 2,
    ],
    'connect_timeout' => 5,

    // GET requests are retried once after this pause (milliseconds), each attempt freshly signed.
    'retry_pause_ms' => 200,

    // Read caches (seconds). A stale copy is kept for 'stale_ttl' to serve while HQ is down.
    'cache' => [
        'settings' => 300,
        'portfolio' => 600,
        'kaizen' => 600,
        'calculator_reference' => 600,
        // The client logo strip, fed by the HQ logo library (docs/COMPANY_PROFILE.md section 4)
        'clients' => 600,
    ],
    'stale_ttl' => 86400,

    // After a failed read, cached reads answer from the saved copy for this many seconds without asking HQ.
    // A failed POST never sets this pause (HQ may still be storing the request).
    'down_pause' => 30,

];
