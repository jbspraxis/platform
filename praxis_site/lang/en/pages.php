<?php

// Text of the Home, Services, Company and Privacy pages (docs/LAUNCH_COPY.md, final, 30 October 2026).
// Facts (contacts, offices, registrations) always come from Website settings, never from here.
return [

    // The five domains: Home shows the line, Services adds the examples
    'domains' => [
        ['icon' => 'landmark', 'name' => 'Money', 'line' => 'Invoicing, payments, ledger, reporting, forecasts and financial models, in your currencies, under IFRS and OHADA.',
            'examples' => ['Invoicing and collections', 'Payment verification', 'General ledger and reporting in several currencies', 'Cash and revenue forecasts, budgets and scenario models (demo on request)']],
        ['icon' => 'users', 'name' => 'People', 'line' => 'Payroll, time, leave and approvals, with the right person seeing the right figures.',
            'examples' => ['Payroll for Nigeria and Cameroon', 'Time and leave', 'Appraisals and approvals']],
        ['icon' => 'truck', 'name' => 'Operations', 'line' => 'Projects, stock, deliveries and field teams, tracked as they happen.',
            'examples' => ['Projects and budgets', 'Stock and deliveries', 'Field teams and sign-offs']],
        ['icon' => 'shield-check', 'name' => 'Risk and governance', 'line' => 'Every action permissioned and logged, with controls built into the work.',
            'examples' => ['Roles and permissions', 'Audit logs', 'Controls in every workflow']],
        ['icon' => 'message-square-text', 'name' => 'Data and AI', 'line' => 'Praxis AI answers questions from your own data, cites its sources and opens the next step.',
            'examples' => ['Praxis AI on your own data', 'Dashboards for leaders', 'Reports that build themselves']],
    ],

    // The Praxis Method in short (Home and Services); the full stages are on How we work
    'method' => [
        ['name' => 'See', 'text' => 'We map how one area of your organisation really runs today. It starts with the free audit.'],
        ['name' => 'Design', 'text' => 'We design how it should run: processes, controls, roles and the numbers that matter.'],
        ['name' => 'Build', 'text' => 'We build and configure the system that runs it and shows it.'],
        ['name' => 'Hold', 'text' => 'We stay until it holds, train your team, then hand over the keys. You own the code and the data.'],
    ],

    'home' => [
        'title' => 'JBS Praxis | Consulting and engineering for Africa’s institutions',
        'description' => 'We design how your organisation runs, then build the system that answers for it: finance, people, operations and risk in one place. Book a free audit.',
        'h1' => 'Ask your company anything.',
        'lead' => 'We design how your organisation runs, then build the system that answers for it.',
        'see_answers' => 'See what Praxis AI answers',
        'video_label' => 'The 60-second demo: a chief operating officer asks her company questions and gets answers from its own data. Ndali Group is a fictional company.',
        'watch_demo' => 'Watch the 3-minute demo',
        'demo_title' => 'A chief operating officer’s morning, in 3 minutes',
        'demo_close' => 'Close the video',
        'demo_note' => 'Ndali Group is a fictional company: all names and figures are demo data. The video plays from YouTube.',
        'pause' => 'Pause the animation',
        'play' => 'Play the animation',
        // The hero's live answer (redesign PR 2): demo data from the fictional Ndali Group, as in the 60-second demo
        'answer_label' => 'A question and its answer, from a demo company',
        'answer_company' => 'Ndali Group',
        'answer_place' => 'Workspace',
        'answer_demo' => 'Demo data',
        'answer_question' => 'Which projects are over budget?',
        'answer_figure' => '₦38,204,600',
        'answer_figure_label' => 'over budget, across 4 of 8 projects',
        'answer_rows' => [
            ['name' => 'Ibadan cold room', 'share' => '109.0%', 'value' => 109.0],
            ['name' => 'Route optimisation, Lagos', 'share' => '107.0%', 'value' => 107.0],
            ['name' => 'ERP rollout', 'share' => '106.5%', 'value' => 106.5],
            ['name' => 'Apapa depot expansion', 'share' => '104.8%', 'value' => 104.8],
        ],
        'answer_share_label' => 'Spent against budget',
        'answer_sources' => 'Sources: 8 project budgets and their cost lines to 31 October 2026',
        'ask_label' => 'Ask Praxis a question about JBS Praxis',
        'ask_placeholder' => 'Ask about our work, the free audit, or how we build…',
        'ask_button' => 'Ask',
        'replay' => 'Play the answer again',
        'problem_title' => 'A dozen tools. Not one answer.',
        'problem_text' => 'Email in one place, chat in another, accounts in a third, approvals somewhere in between. Each tool answers for itself; none answers for the company. So teams re-key figures, reconcile spreadsheets at month end, and leaders decide on numbers nobody fully trusts.',
        'build_title' => 'What we build',
        'method_title' => 'How we work',
        'method_link' => 'More about the Praxis Method',
        'work_title' => 'Built with institutions across Nigeria and Cameroon',
        'work' => [
            'Smart Logistics and Services (Praxis LS)',
            'MyCity App',
            'Pixie Girl Global',
            'Orika Living',
            'A leading pharmaceutical manufacturer, with Tales Consulting',
            'La Petite Cour',
            'NFELIA, our own product',
        ],
        'calculator_title' => 'What is poor quality costing you?',
        'calculator_text' => 'Rework, delays, errors and double entry have a price. Estimate yours in a few minutes, in your currency.',
        'calculator_cta' => 'Use the calculator',
        // The calculator as a step in the journey (redesign PR 3): its three real steps, from pages/calculator
        'calculator_counts' => 'It counts three costs: managers checking work by hand, errors fixed inside, and errors that reach your customers.',
        'calculator_steps' => [
            ['title' => 'Your organisation', 'text' => 'Sector, the system you use today, where work slows down, and your currency.'],
            ['title' => 'Your figures', 'text' => 'Staff, managers, turnover, hours of manual checking, rework. Approximate is fine.'],
            ['title' => 'Your report', 'text' => 'On screen at once, and a copy by email to share with your team.'],
        ],
        'chapter_alt' => 'Lagos from above in the late afternoon: roofs, roads and bridges across the lagoon.',
        'final_title' => 'See it on your own operations.',
        'final_text' => 'The free audit and demo takes about an hour. We look at one area of your organisation and show you what a straight answer looks like.',
    ],

    'services' => [
        'title' => 'Services | JBS Praxis',
        'description' => 'Consulting and engineering for finance, people, operations, risk, and data and AI, using the Praxis Method: See, Design, Build, Hold.',
        'eyebrow' => 'Services',
        'h1' => 'One system, designed around how you run.',
        'lead' => 'We don’t sell software licences. We design how an area of your organisation should work, then build the system that runs it, connected to the tools you keep.',
        'domains_title' => 'What we build',
        'method_title' => 'How we work',
        'expect_title' => 'What you can expect',
        'expect' => ['You own the code and the data.', 'No per-user licences.', 'English and French from day one.', 'We stay until the system holds.'],
        'guide_title' => 'Read the Praxis Method.',
        'guide_text' => 'Get the short guide by email.',
        'guide_send' => 'Send me the guide',
    ],

    'company' => [
        'title' => 'Company | JBS Praxis',
        'description' => 'JBS Praxis is a consulting and engineering firm for Africa’s institutions, with offices in Lagos and Douala.',
        'eyebrow' => 'Company',
        'h1' => 'Order, made visible.',
        'lead' => 'JBS Praxis is a consulting and engineering firm for Africa’s institutions. We design how organisations run, then build the systems that answer for them, in English and French.',
        'facts_title' => 'The company',
        'desks_title' => 'Two regional desks',
        'desks' => [
            ['image' => 'lagos', 'city' => 'Lagos', 'line' => 'West Africa (ECOWAS)', 'alt' => 'Lagos from above: roofs, roads and bridges across the lagoon.'],
            ['image' => 'douala', 'city' => 'Douala', 'line' => 'Central Africa (CEMAC)', 'alt' => 'Douala at dusk, where the city meets the Wouri river: the bridge, the port and the first streetlights.'],
        ],
        'legal_name' => 'Legal name',
        'offices' => 'Offices',
        'registration' => 'Registration',
        'contact' => 'Contact',
        'follow' => 'Follow us',
        'ourselves_title' => 'We run on it ourselves',
        'ourselves_text' => 'We run our own invoicing, payroll, projects and approvals on the same system we build for clients.',
        'products_title' => 'Our products',
        'nfelia' => 'NFELIA, a JBS Praxis product.',
        // Our products (redesign PR 3): what each one is as a product, apart from any client's case study
        'hq_caption' => 'JBS Praxis HQ, where we run our own invoicing, payroll, projects and approvals.',
        'hq_alt' => 'The sign-in screen of JBS Praxis HQ.',
        'product_screen_alt' => 'The sign-in screen of :name.',
        'products_lead' => 'Two products we design, build and run ourselves, on the same principles as the systems we build for clients.',
        'products' => [
            'praxis-ls' => [
                'name' => 'Praxis LS',
                'line' => 'Our logistics ERP, offered as a service to freight forwarders, customs agents, warehouses and fleets in the OHADA region.',
                'points' => [
                    'One file per shipment, from the quote through customs, the warehouse and the truck to the invoice',
                    'SYSCOHADA accounting at its core, in English and French',
                    'Each operator’s own brand on its documents, and its own address',
                    'Each operator’s data in its own database: we hold the code, they hold the data',
                ],
                'status' => 'Runs Smart Logistics and Services in Cameroon today. The version for many operators is now in build.',
                'link' => 'See it at work: Smart Logistics and Services',
            ],
            'nfelia' => [
                'name' => 'NFELIA',
                'line' => 'Our product for small businesses that sell through WhatsApp, Instagram and word of mouth.',
                'points' => [
                    'A website of its own for each business',
                    'Every sale becomes an order, with its invoice, receipt and any debt',
                    'Stock and deliveries on the phone',
                    'In plain words, for owners who are not accountants',
                ],
                'status' => 'Live in Nigeria, Cameroon, Ghana and Kenya.',
                'link' => 'Visit the NFELIA website',
            ],
        ],
        'security_title' => 'Security and data',
        'security_text' => 'Your data stays yours. Every action in the systems we build is permissioned by role and recorded in an audit log. Files are stored privately, never on public links. Our processes are aligned with ISO 27001. We work under Nigeria’s Data Protection Act (NDPA) and Cameroon’s data protection law, and you can ask what we hold about you at any time.',
        'security_link' => 'How we protect your data',
        'careers' => 'Work with us',
    ],

    'privacy' => [
        'title' => 'Privacy | JBS Praxis',
        'description' => 'What jbspraxis.com collects, why, how long we keep it, who sees it, your rights under the NDPA and Cameroonian law, and how cookies and Google Analytics are used.',
        'eyebrow' => 'Privacy',
        'h1' => 'Privacy policy',
        'updated' => 'Version of 30 October 2026.',
        'sections' => [
            'who' => ['title' => 'Who we are', 'body' => [
                ':company runs this website and decides how the personal data it collects is used. You can reach us at :email.',
            ]],
            'what' => ['title' => 'What we collect', 'body' => [
                'What you send us through the forms: contact messages, requests for a free audit and demo, the cost of poor quality calculator, job applications with your CV, articles sent for Insights, feedback on the site, and requests for the Praxis Method guide. Each form asks only for what it needs, and optional fields are marked.',
                'The questions you ask Praxis AI, our website assistant, and its answers. Please don’t share passwords or payment details with it.',
                'The technical data needed to protect the forms: your IP address, used to limit how many requests one connection can send, kept for at most 24 hours.',
            ]],
            'why' => ['title' => 'Why we use it', 'body' => [
                'To answer you, run the free audit and demo, send you what you asked for (the cost report, the Praxis Method guide), consider your application, and keep the site and its forms secure.',
                'We don’t sell your data, and we never use client data for anyone else’s commercial purposes. What you tell us about your organisation stays with us.',
            ]],
            'how_long' => ['title' => 'How long we keep it', 'body' => [
                'We keep messages, requests and reports only as long as we need them for the reason you sent them and for the follow-up you agreed to, then we delete them.',
                'Applications and CVs are kept while we consider you for a role. Ask us at any time to delete yours.',
                'IP addresses used to protect the forms are kept for at most 24 hours.',
            ]],
            'who_sees' => ['title' => 'Who sees it', 'body' => [
                'JBS Praxis staff whose role needs it, in systems where every access is permissioned and logged.',
                'The providers that host our systems and send our emails, only to do that work for us.',
                'Data travels encrypted between your browser and our servers.',
            ]],
            'rights' => ['title' => 'Your rights', 'body' => [
                'You can ask what we hold about you, have it corrected or deleted, and object to how we use it. We work under Nigeria’s Data Protection Act (NDPA) and Cameroon’s data protection law, and we apply the same standard wherever you are, in line with the GDPR (Europe), South Africa’s POPIA, Kenya’s Data Protection Act and the CEMAC rules on data protection.',
                'To use any of these rights, write to :email. We answer within a few working days.',
            ]],
            'cookies' => ['title' => 'Cookies', 'body' => [
                'The site uses the cookies it needs to work: a session cookie and a security token for the forms, and a cookie that remembers your choice in the cookie banner.',
                'Google Analytics, which tells us which pages people use, loads only if you select Accept in the banner. Its cookies (_ga and _ga_…) are then kept for up to two years. If you select Reject, or make no choice, it never loads.',
                'The demo video on the home page plays from YouTube (youtube-nocookie.com), which may store its own cookies. Nothing from YouTube loads until you select Watch the 3-minute demo.',
                'You can change your choice at any time with the Cookie settings link at the bottom of every page.',
            ]],
            'changes' => ['title' => 'Changes to this policy', 'body' => [
                'When we change this policy, we publish the new version here with its date.',
            ]],
        ],
    ],
];
