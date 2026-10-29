<?php

// EnquiryReceived: the visitor's copy after the contact form. EnquiryAlert ('alert'): the team's copy, English only.
return [
    'subject' => 'We’ve received your message',
    'preheader' => 'A member of our team will reply to you. You can also book a free audit and demo now.',
    'intro' => 'Thank you for contacting JBS Praxis. A member of our team will read your message and reply to you.',
    'book_intro' => 'If you would like to choose a time now, book a free audit and demo.',
    'button' => 'Book a free audit and demo',
    'not_sent' => 'If you didn’t write to us, you can ignore this email.',

    'alert' => [
        'subject' => 'New enquiry: :company',
        'preheader' => ':name sent a message through the contact form on jbspraxis.com.',
        'headline' => 'New enquiry from :company',
        'intro' => ':name from :company sent a message through the contact form on jbspraxis.com.',
        'message_label' => 'Message',
        'reply' => 'Reply to this email to reach :name directly. The message is also saved in HQ as a note on :company.',
        'button' => 'View leads in HQ',
        'facts' => [
            'name' => 'Name',
            'email' => 'Email',
            'job_title' => 'Job title',
            'phone' => 'Phone',
            'company' => 'Organisation',
            'city' => 'City',
            'language' => 'Preferred language',
            'budget' => 'Budget',
            'stage' => 'Stage',
            'bottleneck' => 'Main bottleneck',
            'systems' => 'Current systems',
            'governance' => 'Governance',
        ],
    ],
];
