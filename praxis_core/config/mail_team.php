<?php

/*
| HQ Mail PR 4: teamwork on shared mailboxes and Praxis AI (docs/MAIL.md). The Gemini key is config('services.gemini.api_key').
*/

return [
    // A viewing / replying mark lasts this long unless the screen refreshes it (ThreadTeam polls every 20 s)
    'presence_seconds' => 60,

    // Gemini only for mail (never DeepSeek: personal data). A flash model, not flash-lite.
    'ai_model' => env('MAIL_AI_MODEL', 'gemini-2.5-flash'),
    'ai_timeout' => 30,

    // Presses per person per hour
    'summaries_per_hour' => 30,
    'questions_per_hour' => 20,

    // What the AI may read
    'summary_messages' => 30,
    'summary_chars_per_message' => 4000,
    'ask_sources' => 12,
];
