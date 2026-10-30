<?php

// Text of the How we work and Security and data pages (docs/LAUNCH_COPY.md, owner decisions of 30 October 2026).
// The method's public name is "the Praxis Method"; never "predictive", never a trademark sign.
return [

    'how' => [
        'title' => 'How we work | JBS Praxis',
        'description' => 'The Praxis Method: we see how your organisation runs, design how it should run, build the system, and stay until it holds. You own the code and the data.',
        'eyebrow' => 'The Praxis Method',
        'h1' => 'How we work.',
        'lead' => 'The Praxis Method takes one area of your organisation from how it runs today to a system that answers for it.',
        'formerly' => 'Formerly the Praxis Framework.',
        'you_get' => 'You get:',
        'stages' => [
            ['name' => 'See', 'text' => 'We sit with the people who do the work and map how one area really runs: who does what, where the numbers live, where time and money leak.', 'get' => 'a clear picture and the free audit report.'],
            ['name' => 'Design', 'text' => 'We design how it should run: the process, the controls, who approves what, and the few numbers that matter.', 'get' => 'a design you can read and sign off.'],
            ['name' => 'Build', 'text' => 'We build and configure the system around that design, connect the tools you keep, and move your data across.', 'get' => 'a working system, tested with your team.'],
            ['name' => 'Hold', 'text' => 'We stay until it holds: we train your people, watch the first cycles, fix what the real world reveals, then hand over the keys.', 'get' => 'a system your team runs, and the code and data that are yours.'],
        ],
        'expect_title' => 'What you can expect',
        'expect' => [
            'You own the code and the data.',
            'No per-user licences.',
            'English and French from day one.',
            'One team from first meeting to handover.',
        ],
        'audit_title' => 'It starts with a free audit.',
        'audit_text' => 'About an hour, on one area of your organisation. You leave with a clear picture of how it runs today and what to fix first.',
        'guide_title' => 'Get the Praxis Method guide by email',
    ],

    'security' => [
        'title' => 'Security and data | JBS Praxis',
        'description' => 'How JBS Praxis protects your data: roles and permissions, audit logs, private storage, processes aligned with ISO 27001, NDPA and Cameroonian data protection law.',
        'eyebrow' => 'Security and data',
        'h1' => 'Your data stays yours.',
        'lead' => 'The systems we build protect what matters by design: who can see it, who can change it, and a record of everything.',
        'blocks' => [
            ['icon' => 'key-round', 'title' => 'Roles and permissions', 'text' => 'Every screen and action is allowed by role (view, create, edit, approve or export), so people see what their job needs and nothing more.'],
            ['icon' => 'scroll-text', 'title' => 'A record of everything', 'text' => 'Sensitive actions are written to an audit log: who did what, when and from where.'],
            ['icon' => 'split', 'title' => 'Separation of duties', 'text' => 'The person who prepares a payment or a payroll run can’t approve it. Controls are built into the work, not added after.'],
            ['icon' => 'lock', 'title' => 'Private by default', 'text' => 'Documents, CVs, receipts and payment proofs are stored privately and only opened through signed, permissioned links.'],
            ['icon' => 'shield-check', 'title' => 'Aligned with ISO 27001', 'text' => 'Our processes follow ISO 27001 practices. We don’t claim a certification we don’t hold.'],
            ['icon' => 'user-round-check', 'title' => 'Your rights', 'text' => 'We work under Nigeria’s Data Protection Act and Cameroon’s data protection law. Ask what we hold about you, correct it or have it deleted: write to the address below.'],
        ],
        'contact_title' => 'Questions about your data',
        'contact_text' => 'Write to us and we’ll answer within a few working days.',
        'privacy_link' => 'Read our privacy policy',
    ],
];
