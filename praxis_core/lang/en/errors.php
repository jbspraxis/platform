<?php

// Error pages for HQ and the portal (resources/views/errors): what happened, and the way back
return [
    'code' => 'Error :code',
    'portal_sign_in' => 'Sign in to the Client Portal',
    'hq_home' => 'Go to HQ',
    'home' => 'Go to jbspraxis.com',
    'contact' => 'Email info@jbspraxis.com',

    'link' => [
        'title' => 'This link has expired',
        'body' => 'Links in our emails work for a limited time, and this one has ended or isn’t complete. Sign in to your Client Portal to find your proposals and statements of work, or ask your JBS Praxis contact to send it again.',
    ],
    'forbidden' => [
        'title' => 'You can’t open this page',
        'body' => 'Your account doesn’t give access to it. Sign in with the right account, or ask your JBS Praxis contact.',
    ],
    'not_found' => [
        'title' => 'We couldn’t find this page',
        'body' => 'The address may be mistyped, or the page has moved.',
    ],
    'expired' => [
        'title' => 'This page timed out',
        'body' => 'It was open too long without being used. Go back, reload the page and try again.',
    ],
    'too_many' => [
        'title' => 'Too many tries',
        'body' => 'For your security, wait a minute, then try again.',
    ],
    'server' => [
        'title' => 'Something went wrong on our side',
        'body' => 'We’ve been told about it. Try again in a few minutes.',
    ],
    'unavailable' => [
        'title' => 'Back in a moment',
        'body' => 'We’re updating this service. Try again in a few minutes.',
    ],
];
