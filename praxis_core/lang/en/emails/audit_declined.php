<?php

// FreeAuditDeclinedMail: a staff member declined a free audit request in HQ (Free audits); the visitor is invited to
// choose another time. The team's reason is never shown to the visitor.
return [
    'subject' => 'Your free audit request for :date',
    'preheader' => 'We can’t confirm the time you asked for. Choose another time, or reply to this email.',
    'headline' => 'About your free audit request',
    'intro' => 'Thank you for asking for a free audit and demo for :company. We’re not able to confirm the time you asked for.',
    'label_date' => 'Date',
    'label_time' => 'Time',
    'time' => ':time (WAT)',
    'another' => 'If you’d still like to meet, choose another time and we’ll confirm it by email.',
    'button' => 'Choose another time',
    'reply' => 'You can also reply to this email and tell us which days suit you.',
    'reason' => 'You are receiving this because you asked JBS Praxis for a free audit and demo for :company.',
];
