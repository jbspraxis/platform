<?php

return [

    // Preview mode (next.jbspraxis.com): X-Robots-Tag noindex on every response and robots.txt disallows all.
    'noindex' => (bool) env('SITE_NOINDEX', true),

    /*
    | IndexNow (Bing, Yandex, Seznam, Naver…): `php artisan site:indexnow` tells the engines which addresses are new
    | or changed (every address in the sitemap, or the ones given). Nothing is sent while noindex is on.
    | The key is public by design: the engines read it at https://jbspraxis.com/{key}.txt (PageController::indexNowKey)
    | to check that a submission comes from this site. A new key needs only INDEXNOW_KEY (8 to 128 letters, digits, -).
    */
    'indexnow' => [
        'key' => (string) (env('INDEXNOW_KEY') ?: '107c5408c63d049ad4d07d14e5f2e8e6'),
        'host' => 'jbspraxis.com',
        'endpoint' => 'https://api.indexnow.org/indexnow',
    ],

    // Google Analytics 4 (G-…). Loaded only after the visitor accepts the cookie banner; empty = no banner, no analytics.
    'ga4_id' => (string) env('GA4_ID', ''),

    // The other hosts: Client login, and where the old /hq, /client… addresses now live (PageController::legacy)
    'hq_url' => env('HQ_URL', 'https://hq.jbspraxis.com'),
    'portal_url' => env('PORTAL_URL', 'https://portal.jbspraxis.com'),

    // Shown with the social links from Website settings, which has no Instagram field yet
    'instagram_url' => 'https://www.instagram.com/jbspraxis',

    // The narrated 3-minute demo on YouTube (video ids), played on the home page only after a click
    // (partials.demo-dialog); empty = the button is not shown and no YouTube address is allowed by the CSP
    'demo_video' => [
        'en' => env('DEMO_VIDEO_EN', 'XPqfJZ32jvc'),   // "Ask your company anything: a COO’s morning in 3 minutes"
        'fr' => env('DEMO_VIDEO_FR', 'nhx9HYZkIq4'),   // the same, narrated in French
    ],

    // Our product (Company page)
    'nfelia_url' => 'https://nfelia.com',

    // Contact address shown in the calm "HQ is unavailable" message before settings have ever been read.
    'fallback_email' => env('SITE_FALLBACK_EMAIL', 'info@jbspraxis.com'),

    /*
    | Proxies whose X-Forwarded-* headers are trusted (docs/SITE_API.md section 6.6; README "Visitor IP").
    | Empty (the default): no proxy is trusted and the visitor IP is the connecting address.
    | "cloudflare": Cloudflare's published ranges below. Otherwise a comma-separated list of IPs or CIDR
    | ranges, which may include "cloudflare". "*" is never accepted: it is ignored and logged.
    */
    'trusted_proxies' => (string) env('TRUSTED_PROXIES', ''),

    /*
    | Cloudflare's published ranges (https://www.cloudflare.com/ips-v4 and /ips-v6).
    | CF-Connecting-IP and CF-IPCountry are believed only when the connecting address (REMOTE_ADDR) is in
    | one of them. Compare the two lists with Cloudflare's pages again before each deploy.
    */
    'cloudflare_ranges' => [
        // IPv4
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ],

    // The site's own limits per visitor (an IPv4 address or an IPv6 /64), before HQ's stricter ones.
    'limits' => [
        'forms_per_minute' => 6,
        'forms_per_hour' => 30,
        'chat_per_minute' => 10,
        'chat_per_hour' => 60,
        'pixel_per_minute' => 60,
        'unsubscribe_per_minute' => 30,
    ],

    // Every form: at least this many seconds between page load and submit (a signed timestamp field).
    'form_min_seconds' => 3,

    // Chat: turns kept in the session (one turn = one message).
    'chat_turns' => 20,

    // Chat: the reply's links shown as buttons (HQ sends at most 3, same-site paths only).
    'chat_links' => 3,

    // Booking times are Lagos wall-clock time (WAT); a booking can be at most this many days ahead.
    'booking_timezone' => 'Africa/Lagos',
    'booking_days_ahead' => 92,

    /*
    | Proof (redesign PR 3, Nov 2026)
    |
    | clients: the scrolling strip "Organisations we have worked with" on the homepage, in this order. The owner
    | listed them (4 Nov 2026); each file is public/img/clients/{file}.webp, 96px high. The name is only the
    | logo's accessible name: no names are shown.
    */
    'clients' => [
        ['file' => 'smart-logistics', 'name' => 'Smart Logistics and Services'],
        ['file' => 'mtn', 'name' => 'MTN'],
        ['file' => 'pixie-girl-global', 'name' => 'Pixie Girl Global'],
        ['file' => 'la-petite-cour', 'name' => 'La Petite Cour'],
        ['file' => 'mycity-app', 'name' => 'MyCity App'],
        ['file' => 'wellsmart-drilling', 'name' => 'Wellsmart Drilling Nigeria'],
        ['file' => 'orika-living', 'name' => 'Orika Living'],
        ['file' => 'oil-gas-services', 'name' => 'An oil and gas services company'],
        ['file' => 'tales-consulting', 'name' => 'Tales Consulting'],
        ['file' => 'interworks-drill-tech', 'name' => 'Interworks Drill Tech'],
        ['file' => 'rn-afriluxe', 'name' => 'RN Afriluxe'],
        ['file' => 'hod', 'name' => 'HOD'],
        ['file' => 'ok-veggies', 'name' => 'OK Veggies'],
        // Our own products, side by side
        ['file' => 'praxis-ls', 'name' => 'Praxis LS'],
        ['file' => 'nfelia', 'name' => 'nfelia'],
    ],

    /*
    | quotes_approved: the client quotes (lang site.proof.quotes) that may be shown. A quote goes here only once the
    | person has approved its wording, name and role in writing. Until then the section is not rendered at all.
    */
    'quotes_approved' => [
        // 'smart-logistics',
        // 'la-petite-cour',
        // 'mycity-app',
        // 'pixie-girl-global',
    ],

    /*
    | results: confirmed figures for a case study, shown above "What changed" (pages/work/show). Only figures the
    | client has confirmed in writing (audit/2026-11-redesign/FIGURES_FORM.md). Empty: the block is not rendered.
    | Shape: 'smart-logistics' => [['value' => '24', 'label' => ['en' => 'months live', 'fr' => 'mois en service']], ...]
    */
    'results' => [],

];
