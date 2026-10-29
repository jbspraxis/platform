<?php

// IntelligenceBroadcast: an editorial email from HQ → Broadcasts. Its subject and body are the campaign's own.
return [
    // Recipients are client contacts (leads and clients) who have not unsubscribed; they did not sign up to a list.
    'reason' => 'You are receiving this because you are on the JBS Praxis contact list.',
    // Filled in when a campaign uses {{ first_name }}, {{ company_name }} or {{ industry }} and the contact has no value
    'fallback_first_name' => 'colleague',
    'fallback_company' => 'your organisation',
    'fallback_industry' => 'your sector',
];
