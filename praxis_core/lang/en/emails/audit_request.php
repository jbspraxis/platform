<?php

// AuditRequested: the visitor's copy after booking a free audit and demo. AuditRequestAlert ('alert'): the team's copy, English only.
return [
    'subject' => 'We’ve received your request for a free audit',
    'preheader' => 'We’ll confirm the meeting by email and send you the link to join.',
    'intro' => 'Thank you for requesting a free audit and demo with JBS Praxis. Our team will confirm the meeting by email and send you the link to join.',
    'requested_time' => 'Requested time',
    'topic' => 'Topic',
    'change' => 'To change the time or the topic, reply to this email.',

    // Requested date and time (Lagos time). Formats are Carbon isoFormat patterns.
    'when' => ':date, :time (WAT)',
    'time_format' => 'HH:mm',

    // The topics offered on the booking form (stored values on the left); anything else is shown as typed (team alert).
    'topics' => [
        'Performance Audit' => 'Performance audit and compliance',
        'Data Architecture' => 'Data engineering review',
        'Custom Software' => 'Custom software or ERP build',
        'Retainer' => 'Ongoing support and maintenance',
        // A topic the visitor typed: the visitor's copy never repeats what they typed, the team's copy has it
        'Other' => 'The topic you described',
    ],

    'alert' => [
        'subject' => 'New free audit request: :company, :date',
        'preheader' => ':name asked for :when. Approve it in HQ to confirm the meeting.',
        'headline' => 'New free audit request from :company',
        'intro' => ':name from :company asked for a free audit and demo through jbspraxis.com.',
        'approve' => 'Approve the request in HQ to confirm the meeting and send the link to join. Reply to this email to reach :name directly.',
        'button' => 'Approve the request in HQ',
        'facts' => [
            'name' => 'Name',
            'job_title' => 'Job title',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Organisation',
            'topic' => 'Topic',
            'when' => 'Requested time',
        ],
    ],
];
