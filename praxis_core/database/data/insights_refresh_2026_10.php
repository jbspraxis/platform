<?php

/*
 * JBS Praxis Insights: the six articles published before the October 2026 relaunch, rewritten in the brand's
 * voice (docs/BRAND.md sections 3 to 5). php artisan insights:import-refresh adds them to HQ as unpublished
 * drafts by JBS Praxis Editorial; the originals are left as they are. Staff review each draft and decide when
 * to publish it and what to do with the article it replaces.
 *
 * One entry per article, in the order they should be listed (first = newest):
 *   slug        the new article's address; the import skips an article whose slug is already in HQ
 *   replaces    slug of the published article it rewrites (for the import's report; never changed)
 *   category    The Boardroom or The Server Room
 *   title, executive_summary (50 words at most), problem_statement and engineered_solution (paragraphs,
 *   **bold** allowed), final_roi (one sentence, 255 characters at most): the site shows them as
 *   Summary, The problem, What was done and The result.
 *
 * Rules kept here: no client named except those approved in BRAND.md, no person named, no figure that is
 * not in the claims register, no prices, no "predictive", typographic apostrophes and quotes.
 */
return [
    [
        'slug' => 'on-a-drilling-campaign-safety-is-a-system-not-paperwork',
        'replaces' => 'safety-is-not-a-slogan-it-is-an-architecture-engineering-zero-downtime-in-high-stakes-drilling-019cc',
        'category' => 'The Boardroom',
        'title' => 'On a drilling campaign, safety is a system, not paperwork',
        'executive_summary' => 'When safety checks, purchasing and progress live in separate spreadsheets and email threads, leaders hear about problems last and checks are signed after the fact. We recommend one system for all three, in which each phase of work waits until its checks are signed.',
        'problem_statement' => [
            'On a drilling campaign, delay is counted in rig days. A rig that waits for a part, a permit or a signature still costs money every hour, and its crew still has to be kept safe while it waits.',
            'Yet many campaigns are still coordinated with office tools. Purchasing is tracked in one spreadsheet, the safety register in another, and progress in daily emails and WhatsApp groups. Each record is right for the person who keeps it. None of them shows the whole campaign.',
            '**Problems reach leaders late.** A delayed component or a hazard on site passes through several people before it reaches someone who can act. By then the rig may have stood idle for days.',
            '**Safety turns into paperwork.** When checks live in documents rather than in the work itself, they tend to be completed afterwards, for the inspection, instead of before the step they are meant to protect.',
            '**Nobody can show what happened.** After an incident, or a dispute with a supplier, the record has to be rebuilt from inboxes and chat threads, and the people who remember it may have rotated off site.',
        ],
        'engineered_solution' => [
            'The approach below comes from our work with a client in oil and gas. It treats safety, purchasing and reporting as one process, and follows the Praxis Method.',
            '**1. See how the campaign really runs.** We map who reports what, to whom and when, from the rig floor to purchasing to head office. Most delays start where information changes hands.',
            '**2. Design the checks into the steps.** Each phase of work gets its own list of safety, commercial and compliance conditions, and a role responsible for signing each one. Where a check matters most, the ticket needs a second signature.',
            '**3. Build one record of the campaign.** Rig status, purchase requests and safety tickets sit in one system. The daily drilling report is produced from what was recorded during the day, not typed up at night from memory.',
            '**4. Make each phase wait for its checks.** The next phase cannot be opened in the system until its conditions are signed, and each signature is recorded with its time and the role of the person who gave it.',
            '**5. Stay until it holds.** We train supervisors on site and at head office, follow the first weeks of use, and adjust the checklists wherever the field shows they are wrong.',
        ],
        'final_roi' => 'Leaders see rig status, open purchase requests and outstanding safety checks on the day they change, and every sign-off shows when it was given and by which role.',
    ],
    [
        'slug' => 'when-revenue-grows-faster-than-the-systems-behind-it',
        'replaces' => 'the-illusion-of-scale-why-revenue-growth-without-systems-is-corporate-suicide-1b145',
        'category' => 'The Boardroom',
        'title' => 'When revenue grows faster than the systems behind it',
        'executive_summary' => 'Rising revenue does not prove that an organisation runs well. When the work depends on effort and memory, each new customer adds rework and delay, and margins fall while sales rise. We recommend fixing how the work flows, and measuring it from the work itself, before growing further.',
        'problem_statement' => [
            'Many growing companies reach a point where revenue keeps rising and margins stop following it. The sales figures look healthy. Yet the month-end takes longer, customers complain more often, and the same few people work later every week.',
            'The cause is rarely the market. It is how the work gets done. Orders, projects and invoices move between people by email and chat. Figures are typed again from one tool into the next. Approvals wait for one or two people who hold the whole process in their heads. Progress is reported by the people doing the work, in their own words, so leaders learn about a problem when it is already late.',
            'At a small size, effort hides all of this. At a larger size, effort stops being enough. Every new customer adds hand-offs, re-keying and waiting, and each of those has a cost: rework, late deliveries, discounts to keep customers, invoices sent late and paid later. When one of the people who hold the process leaves, part of the process leaves with them.',
            'Growing on top of this does not scale the business. It scales the problems.',
        ],
        'engineered_solution' => [
            'We fix how the work flows before adding more volume to it, following the Praxis Method.',
            '**1. Follow one piece of work from first contact to cash.** We take one order or one project and trace it from the first conversation to the payment received. We count the hand-offs, the times a figure is typed again, and the days the work spends waiting. This shows where the cost really sits.',
            '**2. Decide who does what, and who approves what.** We design the process with the people who run it. Each step has an owner, approvals have limits by amount, and nothing waits in one person’s inbox. We agree the few measures that matter, such as the time from order to invoice and from invoice to payment.',
            '**3. Let the system carry the work.** We build or configure one system that moves the work from step to step and records each change. Status comes from the work itself, so nobody has to write a progress report and nobody has to chase one.',
            '**4. Review the measures until the process holds.** During the first cycles we review the measures with your team every week, fix what the real work reveals, then hand the process and the system over to your team.',
        ],
        'final_roi' => 'Leaders see where each order, project and payment stands without asking, and new customers add volume to a process that holds rather than more work for the same few people.',
    ],
    [
        'slug' => 'compliance-belongs-in-the-daily-work-not-on-a-shelf',
        'replaces' => 'compliance-is-not-a-department-it-is-an-architecture-1b146',
        'category' => 'The Boardroom',
        'title' => 'Compliance belongs in the daily work, not on a shelf',
        'executive_summary' => 'Many organisations treat ISO 9001 and business continuity as documents to show an auditor. The controls that protect an organisation are the ones built into the daily work: a supplier that has not passed its checks cannot receive a purchase order, however urgent the request.',
        'problem_statement' => [
            'Organisations invest real time and money in business impact analyses, risk registers and continuity plans. The documents are written, approved and filed. Then the daily work carries on around them.',
            'A purchase is approved in a WhatsApp group to save time. A supplier is paid before its documents have been checked. A policy changes, and the people who apply it hear about it weeks later, if at all. None of this shows in the documents, which still describe the process as it was designed.',
            'The cost appears in three places. Audits become projects, with weeks spent collecting evidence by hand before each visit. Exceptions go unnoticed until they become losses, penalties or audit findings. And the board believes the controls are working because they exist on paper, when nobody can show that they operate.',
            'Compliance treated as a department produces documents. Compliance built into the work produces evidence.',
        ],
        'engineered_solution' => [
            'We start with the processes where a failed control costs most, usually purchasing, payments and supplier onboarding, and follow the Praxis Method.',
            '**1. Compare the policy with the practice.** For each process we set the written procedure next to what really happens, step by step, and list every place where the two differ.',
            '**2. Turn each control into a rule the system can check.** Who may approve, and up to what amount. Which documents and checks a supplier needs before it can be paid, including identity and anti-money-laundering checks. What happens when a site, a system or a key supplier is unavailable, and who decides.',
            '**3. Build the rules into the work.** A purchase order cannot be raised for a supplier whose checks are incomplete. Approvals above a limit go to a second person. An exception is possible only with a recorded reason, and every change is logged. The continuity plan lives in the same system, with its contact lists and fallback steps kept current.',
            '**4. Keep the evidence as you go.** Audit evidence becomes a report the system produces, not a project. We train the teams who apply the controls, review the exceptions with management each month during the first cycles, then hand over.',
        ],
        'final_roi' => 'Audit evidence comes from the system’s own records, exceptions are visible when they happen, and the board can see which controls operate instead of assuming that they do.',
    ],
    [
        'slug' => 'why-leaders-wait-weeks-for-their-own-numbers',
        'replaces' => 'data-silos-and-the-death-of-executive-truth-1b146',
        'category' => 'The Server Room',
        'title' => 'Why leaders wait weeks for their own numbers, and how to end the wait',
        'executive_summary' => 'When finance, stock and purchasing each keep their own system, consolidated figures arrive weeks after the period they describe. We connect the systems you keep to one database, with agreed definitions, so leaders read the same figures the teams work from.',
        'problem_statement' => [
            'In many mid-sized and large organisations in Nigeria and Cameroon, each department runs its own system. Finance uses an accounting package. Logistics uses an application written years ago. Purchasing runs on spreadsheets sent by email. Each system is right on its own terms, and none of them agrees with the others.',
            'So the consolidated picture is assembled by hand. Every month, someone exports, copies, reconciles and corrects. The board pack arrives weeks after the period it describes, and by then the business has moved on.',
            'The delay is more than an inconvenience. The gaps between the systems are where stock losses go unnoticed, where purchasing irregularities hide, and where a cash shortfall appears with little warning. Meetings spend their time on which figure is right instead of what to do about it.',
        ],
        'engineered_solution' => [
            'We do not replace the systems your teams rely on. We connect them to one source of truth.',
            '**1. List every source.** We record each system and spreadsheet that holds a figure leaders use: who owns it, how often it changes, and how it can be read, whether by export, by database connection or through an API.',
            '**2. Agree the definitions.** What counts as a sale, when stock counts as received, which exchange rate converts naira and CFA francs, and when a month is closed. Each definition is written down once and used everywhere.',
            '**3. Build the pipelines.** Scheduled extract, transform and load (ETL) jobs copy data from each source into one relational database, usually PostgreSQL. Each load is checked: totals must reconcile with the source, and missing or duplicate records are flagged. The data is normalised so that each fact is stored once, and its history is kept.',
            '**4. Report from one place.** Dashboards and the month-end pack read from that database. Every figure shows its source and when it was last refreshed, so anyone can trace a number back to where it came from.',
            '**5. Hand over the running of it.** Your team owns the pipelines, with an alert when a load fails and documentation for each source.',
        ],
        'final_roi' => 'Consolidated figures are ready as soon as the sources are refreshed, every figure can be traced to the system it came from, and meetings start from one set of numbers.',
    ],
    [
        'slug' => 'legacy-software-is-a-debt-that-grows-with-your-volumes',
        'replaces' => 'legacy-code-is-corporate-debt-architecting-for-high-velocity-scale-1b147',
        'category' => 'The Server Room',
        'title' => 'Legacy software is a debt that grows with your volumes',
        'executive_summary' => 'Software that worked at launch often struggles as transactions grow: payments time out, reports slow everything else, and each change risks breaking something. Replacing everything at once is rarely the answer. We recommend separating the parts, connecting them through documented interfaces, and replacing the costliest first.',
        'problem_statement' => [
            'Many organisations run on an application written for the volumes of several years ago, or on an off-the-shelf package bent to fit with plug-ins and workarounds. It works, until volumes rise.',
            'Then the symptoms appear together. Month-end reports slow the system down for everyone else. Payment confirmations from a gateway time out and have to be matched by hand. A change in one place breaks something in another, so changes stop being made. The people who understood the code have moved on, and each workaround makes the next one harder.',
            'This is a debt, and it charges interest: in staff time, in failed or duplicated transactions, and in what the organisation cannot do, such as adding a payment channel, a branch or a second currency without a long and risky project.',
        ],
        'engineered_solution' => [
            'A full rewrite is tempting, and it is usually the riskiest option. We replace the system in parts, while it keeps running.',
            '**1. Measure before changing anything.** We find which parts fail under load, which are changed most often, and which hold the data that matters most. That tells us where to start.',
            '**2. Put a documented interface in front of the old system.** Other parts of the business talk to it through one agreed API instead of reaching into its database. From then on, a part behind the interface can be replaced without the others noticing.',
            '**3. Replace the costliest part first.** Often that is payments or reporting. Each payment notification from a gateway is recorded as it arrives and processed again if a step fails, instead of being lost. Reports read from a copy of the database, so they no longer slow down the daily work.',
            '**4. Build with tools that others can maintain.** We use widely supported frameworks, such as Laravel behind the scenes and React or Vue on screen, with automated tests and monitoring, so that your own team, or another firm, can look after what was built.',
            '**5. Hand over the keys.** We document each part, train your team and stay until they can run and change the system without us.',
        ],
        'final_roi' => 'Each part can change without breaking the others, payment notifications are recorded and processed instead of lost, and your own team can maintain what was built.',
    ],
    [
        'slug' => 'praxis-ai-an-assistant-that-works-inside-your-system',
        'replaces' => 'praxis-ai-the-assistant-that-works-inside-your-erp',
        'category' => 'The Server Room',
        'title' => 'Praxis AI: an assistant that works inside your system, not beside it',
        'executive_summary' => 'In the client systems we build, Praxis AI reads data, drafts documents and starts routine tasks, but only within the permissions of the person asking. Account, card, tax and phone numbers are removed before any text leaves, and it keeps no copy of your database.',
        'problem_statement' => [
            'Many AI features added to business software fail one of the tests below. Organisations tend to find out after the fact.',
            '**Where does the data go?** A member of staff asks the assistant to summarise a customer’s history. The tool collects the full record, recent invoices, payment disputes and the account number, and sends them to a model provider in another country. Nobody in the organisation knows it happened, and there is no record of it. The provider’s terms may allow it to keep that text for a period.',
            '**Who is the assistant acting as?** A warehouse clerk who may only scan deliveries asks the assistant to draft a supplier invoice and mark it for payment. The assistant does it. The audit trail records the entry faithfully, but it records an action the clerk had no right to take.',
            '**What leaves the building?** Even with a signed contract, a tool that sends account numbers, card numbers, tax numbers and phone numbers with every question relies on a promise, not a control.',
            'Praxis AI is designed to pass all of these tests.',
        ],
        'engineered_solution' => [
            'Praxis AI follows one rule: **the assistant never has more rights than the person asking, and identifiers are removed before any text leaves.** In practice, it works as follows.',
            '**1. Permissions are checked for the assistant too.** Every action the assistant can take, from reading a customer record to drafting a quotation, is listed with the permission it needs. Before it acts, the assistant checks the person’s permissions in the same way the screens do. If the permission is missing, the action is refused and logged. If an action has no permission listed at all, it is refused as well, so a forgotten setting produces a visible refusal rather than a hidden gap.',
            '**2. Each organisation’s data is kept apart by the database itself.** Every read runs through a database connection limited to the organisation of the person asking. Another organisation’s data cannot be returned, because that connection cannot see it. PostgreSQL enforces this, not a check that someone has to remember.',
            '**3. Confidential documents stay confidential.** Documents in the knowledge base carry a confidentiality level, and a search returns only what the person asking is allowed to see. A confidential HR memo never reaches the assistant for someone without HR permissions, because the query never returns it.',
            '**4. Identifiers are removed before text leaves.** Before any text goes to the language model, a filter replaces IBANs, card numbers, the RIB bank account numbers used in the OHADA zone, tax numbers such as the NIU, phone numbers, email addresses and long runs of digits with neutral labels. It applies to the question, the conversation so far, the documents retrieved and the result of every action. The model sees a label, not the customer’s account number.',
            '**5. The assistant has tools, not a copy of your data.** It works through a list of named actions, each with its own permission. To answer a question, it runs one action as the person asking, returns the answer and keeps no copy of the data. There is no copy of your books in someone else’s cloud.',
            '**6. Everything is logged.** Questions sent to the model, actions taken, refusals and results are all recorded. If an auditor asks what the assistant did for a given person on a given day, there is an answer.',
            '**7. The model can run where you need it.** For banks, public bodies and health organisations that cannot send text abroad, the same design can use a model hosted on their own servers or by a local provider. The code stays the same; only the address of the model changes.',
            'The Praxis AI assistant on jbspraxis.com is different: it answers only from our public pages and sees no client data, and our privacy policy explains where its questions go.',
        ],
        'final_roi' => 'Teams can trust the assistant with real work, such as drafting quotations, opening files and chasing payments, because it can never do more than the person asking and everything it does is logged.',
    ],
];
