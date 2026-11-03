<?php

// Broadcasts (HQ › Campaigns › Broadcasts, docs/CAMPAIGNS.md). The subject, headline and blocks are the broadcast's own;
// these are the words around them. IntelligenceBroadcast (broadcasts from before 3 Nov 2026) uses 'reason' and the fallbacks.
return [
    // Why the person got it, by who they are to us (counsel to confirm the wording, docs/CAMPAIGNS.md section 9)
    'reason' => 'You are receiving this because you are on the JBS Praxis contact list.',
    'reasons' => [
        'client' => 'You are receiving this because your organisation works with JBS Praxis.',
        'lead' => 'You are receiving this because you are in touch with JBS Praxis.',
        'subscriber' => 'You are receiving this because you asked to hear from JBS Praxis.',
        'kaizen' => 'You are receiving this because you wrote for JBS Praxis Insights.',
    ],
    // The small label above the headline, per layout
    'eyebrow' => [
        'announcement' => 'News from JBS Praxis',
        'insight' => 'JBS Praxis Insights',
        'event' => 'Invitation',
        'case_study' => 'Case study',
    ],
    // A test send from the editor
    'test_subject' => 'Test: :subject',
    // Filled in when a broadcast uses {{ first_name }}, {{ company_name }} or {{ industry }} and the person has no value
    'fallback_first_name' => 'colleague',
    'fallback_company' => 'your organisation',
    'fallback_industry' => 'your sector',
];
