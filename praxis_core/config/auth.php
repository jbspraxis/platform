<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        // Staff (HQ): the users table
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Clients (portal): the client_users table. A client account can never pass 'web'.
        'client' => [
            'driver' => 'session',
            'provider' => 'client_users',
        ],

        // [ INJECTED FOR THE STANDALONE TRACKER ]
        'tracker' => [
            'driver' => 'session',
            'provider' => 'tracker_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],

        'client_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\ClientUser::class,
        ],

        // [ INJECTED FOR THE STANDALONE TRACKER ]
        'tracker_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\TrackerUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];