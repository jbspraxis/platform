<?php

/*
| HQ as an installable app (docs/PWA.md): manifest, service worker, launch animation, install and
| notification prompts, and web push for bell notifications. Only HQ is an app: the Client Portal
| and the public site are untouched.
*/

return [

    'name' => 'JBS Praxis HQ',
    'short_name' => 'HQ',
    'description' => 'Leads, projects, finance and the team, in one place.',
    'theme_color' => '#0C142B',
    'background_color' => '#0C142B',

    // Web push (VAPID, RFC 8292). Leave the keys empty: HQ makes a key pair on first use and keeps it in
    // the pwa_settings table (the private key encrypted with APP_KEY). Set both only to bring your own.
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:system@jbspraxis.com'),
    ],

    'push' => [
        // Bell notifications newer than this are pushed; older ones never are
        'window_hours' => 24,
        // How long a push service keeps a message for a device that is switched off (seconds)
        'ttl' => 86400,
        // Longest message shown in a notification (characters)
        'body_limit' => 240,
        // Devices one person can turn on (the least recently seen is dropped beyond this)
        'max_devices' => 20,
        // Notifications handled per run, push requests in flight at once, seconds per request
        'batch' => 200,
        'concurrency' => 20,
        'timeout' => 10,
        // Push services HQ will send to. A device whose address is anywhere else is refused.
        'services' => [
            'fcm.googleapis.com', '*.googleapis.com',
            '*.push.services.mozilla.com',
            'web.push.apple.com', '*.push.apple.com',
            '*.notify.windows.com',
        ],
    ],

    // Days a "Not now" keeps the install card and the notifications card away
    'snooze_days' => [
        'install' => 14,
        'notify' => 14,
    ],
];
