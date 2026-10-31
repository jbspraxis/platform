<?php

// Passkeys and the sign-in PIN (docs/SIGN_IN.md), on HQ and the Client Portal (docs/BRAND.md sections 5 and 12).
// Read as __('sign_in.<group>.<key>'). HQ is English; the portal shows the French file to French readers.

return [
    // ---- The sign-in pages ----
    'login' => [
        'or' => 'or',
        'passkey' => 'Sign in with a passkey',
        'passkey_busy' => 'Waiting for your device…',
        'passkey_help' => 'Your fingerprint, face or screen lock.',
        'pin' => 'Sign in with a PIN',
        'password' => 'Use your password',
        'pin_label' => 'PIN',
        'pin_help' => 'The 4 digits you chose.',
        'pin_submit' => 'Sign in',
        'pin_submitting' => 'Signing in…',
        'email_required' => 'Enter your email address.',
        'email_invalid' => 'Enter an email address in the format name@example.com.',
    ],

    // ---- Why a sign-in didn't work ----
    'errors' => [
        'pin_digits' => 'Enter the 4 digits of your PIN.',
        'pin_failed' => 'That email address and PIN don’t match, or this account has no PIN. Check them, or use your password.',
        'pin_wrong_left' => '{1} That PIN isn’t right. 1 more wrong try switches it off.|[2,*] That PIN isn’t right. :left more wrong tries switch it off.',
        'pin_wait' => 'Wait :wait before you try the PIN again, or use your password.',
        'pin_switched_off' => 'Too many wrong tries, so your PIN is switched off. Sign in with your password, then set a new PIN.',
        'too_many' => '{1} Too many tries from this connection. Try again in 1 minute, or use your password.|[2,*] Too many tries from this connection. Try again in :minutes minutes, or use your password.',
        'seconds' => '{1} 1 second|[2,*] :count seconds',
        'minutes' => '{1} 1 minute|[2,*] :count minutes',
    ],

    'passkey' => [
        // Shown when a passkey answer was not accepted (App\Support\SignIn\PasskeyRefused)
        'failed' => 'Your passkey couldn’t be checked. Try again, or use your password.',
        'expired' => 'That took too long. Try again.',
        'unknown' => 'That passkey isn’t linked to an account here. Use your password, then add a passkey.',
        'inactive' => 'This account can’t sign in. Contact JBS Praxis if you think that’s wrong.',
        'already_added' => 'That passkey is already added.',
        'too_many' => 'You have 10 passkeys, the most an account can have. Remove one to add another.',
        // From the browser, before anything reached the server
        'cancelled' => 'The passkey request was cancelled or timed out. Try again when you’re ready.',
        'unsupported' => 'This browser can’t use passkeys. Use your password, or try another browser.',
        'exists' => 'This device already has a passkey for your account.',
    ],

    // ---- Setting them up (HQ: My profile; Client Portal: Sign-in settings) ----
    'settings' => [
        'title' => 'Sign-in settings',
        'open' => 'Sign-in settings',
        'intro' => 'Sign in with your fingerprint, face or a PIN instead of your password.',

        'passkeys_title' => 'Passkeys',
        'passkeys_help' => 'A passkey signs you in with this device’s fingerprint, face or screen lock. Add one on each device you use.',
        'passkeys_none' => 'No passkeys yet. Add one to sign in with your fingerprint or face.',
        'passkeys_unsupported' => 'This browser can’t create passkeys. Open this page in an up-to-date browser on your phone or computer to add one.',
        'add_passkey' => 'Add a passkey',
        'adding_passkey' => 'Waiting for your device…',
        'added' => 'Added :date',
        'used' => 'last used :date',
        'synced' => 'Synced',
        'remove_passkey' => 'Remove',
        'remove_passkey_confirm' => 'Remove the passkey “:name”? That device will need your password or PIN to sign in.',
        'passkey_added' => 'Passkey added. Next time, sign in with your fingerprint or face.',
        'passkey_removed' => 'Passkey removed.',

        'pin_title' => 'PIN',
        'pin_help' => 'A 4-digit code you can sign in with instead of your password.',
        'pin_on' => 'Your PIN is set.',
        'pin_none' => 'No PIN yet.',
        'pin_off' => 'Your PIN was switched off after too many wrong tries. Set a new one to use it again.',
        'set_pin' => 'Set a PIN',
        'change_pin' => 'Change PIN',
        'remove_pin' => 'Remove PIN',
        'remove_pin_confirm' => 'Remove your PIN? You’ll sign in with your password or a passkey.',
        'new_pin' => 'New PIN',
        'new_pin_help' => '4 digits. Not a run like 1234 or one digit repeated.',
        'new_pin_again' => 'New PIN again',
        'save_pin' => 'Save PIN',
        'cancel' => 'Cancel',
        'pin_saved' => 'PIN saved. Use it next time you sign in.',
        'pin_removed' => 'PIN removed.',

        'confirm_title' => 'Confirm it’s you',
        'confirm_help' => 'Enter your password to change how you sign in.',
        'password' => 'Password',
        'confirm' => 'Confirm',
        'confirming' => 'Checking…',
    ],

    'pin' => [
        'required' => 'Enter 4 digits.',
        'mismatch' => 'The two PINs don’t match. Type the same 4 digits in both fields.',
        'password_required' => 'Enter your password.',
        'password_wrong' => 'That isn’t your password. Check it and try again.',
        'password_too_many' => 'Too many tries. Wait :minutes minutes and try again.',
        'weak' => [
            'digits' => 'Enter 4 digits.',
            'repeated' => 'Use different digits, not one digit repeated.',
            'run' => 'Use a PIN that isn’t a run like 1234 or 9876.',
            'pair' => 'Use a PIN that isn’t a repeated pair like 1212.',
            'doubles' => 'Use a PIN that isn’t two doubles like 1155.',
            'common' => 'That PIN is one of the most common. Choose another.',
        ],
    ],

    // ---- The card after a password sign-in ----
    'offer' => [
        'title' => 'Sign in faster next time',
        'body' => 'Add a passkey and sign in with your fingerprint or face.',
        'add' => 'Add a passkey',
        'later' => 'Not now',
        'done' => 'Passkey added. Next time, sign in with your fingerprint or face.',
    ],
];
