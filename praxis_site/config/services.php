<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Cloudflare Turnstile on every form (docs/SITE_API.md section 6.6). Off while either key is empty.
    | The token is checked server-side before anything is sent to HQ. Chat checks it once, then trusts
    | the session for 'chat_clearance_minutes'.
    */
    'turnstile' => [
        'site_key' => (string) env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => (string) env('TURNSTILE_SECRET_KEY', ''),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'script_url' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        'origin' => 'https://challenges.cloudflare.com',
        'timeout' => 5,
        'chat_clearance_minutes' => 30,
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
