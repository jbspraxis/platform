<?php

/*
| HQ Mail, writing (PR 3, docs/MAIL.md): the composer, the outbox and mail:send-due, signatures, Write with AI.
*/

return [
    // Undo send: how long a sent message waits before it goes (seconds)
    'undo_seconds' => 10,

    // Drafts: HQ saves while you type (the browser waits this long after the last key, in ms); the copy in the
    // server's Drafts folder is written when the composer closes, and at most this often while typing (seconds)
    'autosave_ms' => 1500,
    'server_draft_seconds' => 60,

    // Sending: temporary failures are tried again after these waits (minutes); then the sender is told
    'retry_minutes' => [1, 2, 5, 15, 30, 60],
    // A message stuck in "sending" this long (minutes) is marked not sent, never sent twice
    'stuck_minutes' => 15,
    // How many due messages one mail:send-due run sends
    'batch' => 25,

    // Attachments: all files of one message together (MB); uploads wait in this folder of the local disk
    'max_attachments_mb' => 20,
    'upload_dir' => 'mail-outbox',

    // Recipients: most addresses on one message (To, Cc and Bcc together), and suggestions shown
    'max_recipients' => 100,
    'suggestions' => 8,

    // Scheduled sending and the times people see
    'timezone' => env('MAIL_COMPOSE_TIMEZONE', 'Africa/Lagos'),

    // Write with AI (docs/MAIL.md 7.6): Gemini only, never DeepSeek; a flash model, not flash-lite
    'ai' => [
        'model' => env('MAIL_AI_MODEL', 'gemini-2.5-flash'),
        'timeout' => 25,
        'context_messages' => 30,
        'per_hour' => 30,
    ],
];
