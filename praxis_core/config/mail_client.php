<?php

/*
| HQ Mail (docs/MAIL.md): staff read and send their own mailbox and shared mailboxes
| (intelligence@…) inside HQ, over IMAP and SMTP. Not to be confused with config/mail.php,
| which is how HQ itself sends system email.
*/

return [

    // Server settings by address domain. Connect fills these in; a person can change them under
    // "Server settings". Domains not listed use mail.<domain> with the default ports.
    'servers' => [
        'jbspraxis.com' => [
            'imap_host' => env('MAIL_CLIENT_IMAP_HOST', 'mail.jbspraxis.com'),
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => env('MAIL_CLIENT_SMTP_HOST', 'mail.jbspraxis.com'),
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
        ],
    ],

    'default_server' => [
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'smtp_port' => 465,
        'smtp_encryption' => 'ssl',
    ],

    // Seconds to wait for the mail server to answer
    'timeout' => (int) env('MAIL_CLIENT_TIMEOUT', 20),

    // Check the server's certificate (only ever turn off for a local test server)
    'verify_tls' => (bool) env('MAIL_CLIENT_VERIFY_TLS', true),

    // Opening mail: write the server's \Seen after the response is sent, so the reader never waits for the mail
    // server (App\Support\Mail\MailActions::openedMany). Unset: whenever PHP can send the response first (PHP-FPM,
    // LiteSpeed); true or false forces it either way.
    'mark_read_after_response' => env('MAIL_CLIENT_MARK_READ_AFTER_RESPONSE'),

    'sync' => [
        // Mail newer than this is copied into HQ (headers and text); older mail is read from the server when opened
        'months' => 12,
        // Seconds one `mail:sync` run may spend in total (it runs every minute)
        'budget' => 50,
        // Messages fetched per IMAP command
        'batch' => 40,
        // Folders other than the inbox are checked at most this often (minutes)
        'other_folders_every' => 5,
        // The folder list is read again this often (minutes)
        'folders_every' => 30,
        // Message bodies larger than this (bytes, each of text and HTML) are read from the server when opened
        'max_body_bytes' => 1_000_000,
        // New mail older than this (hours) never raises MailReceived (catching up after a pause is not "new")
        'notify_within_hours' => 48,
    ],

    // A thread whose subject matches and shares a person joins an earlier thread up to this many days later
    'thread_subject_days' => 30,

    // HQ's own domain: the From domain of new Message-IDs when the address has none
    'message_id_domain' => env('MAIL_CLIENT_MESSAGE_ID_DOMAIN', 'jbspraxis.com'),
];
