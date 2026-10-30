<?php

// The privacy policy (/privacy), version of 30 October 2026: Nigeria Data Protection Act 2023 and the NDPC's GAID 2025,
// Cameroon's Law No. 2024/017 of 23 December 2024. It describes what the site and HQ really do (praxis_site routes,
// praxis_core app/Services/Site, the portal); change it together with the code. The CV period is config('praxis.
// cv_retention_months') in praxis_core, enforced by careers:purge-cvs. Placeholders, filled by pages/privacy.blade.php:
// :company and :email (Website settings), :offices, :session_cookie and :session_minutes (config/session.php).
// Blocks in a section body: a string is a paragraph, ['heading' => …] a sub-heading, ['items' => […]] a list,
// ['rows' => [[term, detail], …]] a two-column list.
return [

    'privacy' => [
        'title' => 'Privacy policy | JBS Praxis',
        'description' => 'How JBS Praxis collects, uses, shares and protects personal data in Nigeria and Cameroon, and your rights under the NDPA and Cameroonian law.',
        'eyebrow' => 'Privacy',
        'h1' => 'Privacy policy',
        'lead' => 'How we collect, use, share and protect personal data on this website, in our client portal and in our work for clients in Nigeria and Cameroon.',
        'effective' => 'Version of 30 October 2026, in effect from that date.',
        'summary_title' => 'In short',
        'summary' => [
            'We collect what you send us through our forms, the questions you ask Praxis AI, and the technical data needed to run and protect our systems.',
            'We use it to answer you, run the free audit and demo, send what you ask for, consider job applications, work with our clients and keep our systems secure.',
            'We don’t sell personal data, and Google Analytics loads only if you accept it.',
            'Questions you ask Praxis AI are sent to Google’s Gemini and, if it can’t answer, to DeepSeek. Please don’t share personal details in the chat.',
            'We delete CVs 12 months after we receive them, unless you agree that we keep them longer or you join us.',
            'You can ask to see, correct or delete your data, or object to how we use it, by writing to :email. You can also complain to Nigeria’s or Cameroon’s data protection authority.',
        ],
        'toc_title' => 'Contents',
        'offices_separator' => '; ',
        'cookie_button' => 'Cookie settings',

        'sections' => [
            'who' => ['title' => 'Who we are', 'body' => [
                ':company (“JBS Praxis”, “we”, “us”) is a consulting and engineering firm that works with institutions in Nigeria and Cameroon. We run this website, jbspraxis.com, our own system (JBS Praxis HQ) and the JBS Praxis Client Portal.',
                'For the personal data described in this policy, we decide why and how it is used: in the words of data protection law, we are its controller.',
                'Our offices: :offices.',
                'For any question about this policy or your data, write to :email.',
            ]],

            'scope' => ['title' => 'What this policy covers', 'body' => [
                'This policy covers personal data about these people:',
                ['items' => [
                    'Visitors to jbspraxis.com, in English and in French.',
                    'People who contact us, book a free audit and demo, use the cost of poor quality calculator, ask for the Praxis Method guide, write for JBS Praxis Insights, send feedback or apply for a job.',
                    'People who receive our emails.',
                    'The people at our clients and prospective clients we work with, including users of the client portal.',
                ]],
                'When we build or run a system for a client, the personal data inside it (about that client’s staff, customers or suppliers, for example) belongs to the client. We handle it only on the client’s instructions and under our agreement with them, and the client’s own privacy notice applies. If your question is about such data, write to that organisation first; we will help it answer.',
                'This policy doesn’t cover other websites we link to, such as our pages on LinkedIn, X, YouTube or Instagram, which follow their own policies.',
            ]],

            'collect' => ['title' => 'What we collect, and why', 'body' => [
                'We get personal data from you directly, through our forms, emails and meetings, and sometimes from your organisation (for example, when it names you as the contact for a project). We collect only what each activity needs; optional fields are marked and can stay empty.',
                'No form asks for sensitive data, such as health, religion or ethnic origin. Please don’t include it in your messages or CV beyond what is needed.',

                ['heading' => 'Contact form'],
                'Your name, email address, organisation and message; if you choose to add them, your job title, phone number, city, the language you would like our reply in, and your answers to our questions about your organisation (its stage, its main bottleneck, the systems it uses, how it is governed, and when you would like to start).',
                'We record your organisation, and you as its contact, in our client records (JBS Praxis HQ), save your message, tell the team, email you a confirmation and email the team the details. We use them to answer you and to follow up on your enquiry.',

                ['heading' => 'Free audit and demo'],
                'Your name, email address, phone number and organisation, the area you would like to look at, and the date and time you choose; your job title if you add it.',
                'We record the request, your organisation and you as its contact, email you and the team, and use them to prepare and hold the session. If we can’t keep the time, we may email you to offer another.',

                ['heading' => 'Cost of poor quality calculator'],
                'Your email address and, if you add them, your name and organisation, with the figures and choices you enter: sector, main system, main bottleneck, currency, turnover, headcount, number of managers, hours of manual work, hourly cost, error rate and average contract value.',
                'Our system works out the estimate, saves it with a private link to your report, records your organisation and you as its contact, emails you a copy and tells the team. Anyone who has the report link can open the report, so share it only with people you trust.',
                'To write the short summary at the top of the report, we send Google’s Gemini your organisation’s name, its type of sector, its main system and its main bottleneck. We don’t send your name, your email address or your figures.',
                'The system also sorts reports into three groups by the size of the figures, so our team knows which to follow up first. This has no legal or similarly significant effect on you, and a person decides what we do next.',

                ['heading' => 'The Praxis Method guide'],
                'Your email address. We email you the guide and record the request with its date.',

                ['heading' => 'JBS Praxis Insights'],
                'When you send an article for review: your name, job title, email address and LinkedIn profile, and the article itself. We record you as a contributor, tell our editors, and use your details to review the article and contact you about it.',

                ['heading' => 'Job applications'],
                'Your name, email address, phone number and city, your discipline and skills, your LinkedIn profile, your portfolio link if you add one, your answer to our question, and your CV (PDF or Word, up to 5 MB).',
                'Your CV is stored privately and opened only by the team members who handle recruitment. If you apply again with the same email address, we update your application and add the new CV. We may look at the public profiles you link to. We use your application only to consider you for roles at JBS Praxis.',

                ['heading' => 'Feedback on the website'],
                'What you report, its category and how much it affects you; your name and email address only if you choose to give them. We use it to fix and improve the website.',

                ['heading' => 'Praxis AI, the website assistant'],
                'The questions you ask and the answers you receive. The conversation is kept in your session on our website’s server (its last 20 messages) so the assistant can follow it, and it ends with your visit. We don’t save it in our client records.',
                'To answer, our system sends the latest messages of the conversation (12 at most), with our instructions, to Google’s Gemini. If Gemini is busy or doesn’t answer, it sends them to DeepSeek instead. We don’t send your name, email address or IP address with them. Please don’t share personal, financial or confidential details in the chat.',

                ['heading' => 'Emails from us'],
                'If you are in our client records (because you contacted us, booked an audit, used the calculator, wrote for Insights or work with us), we may send you occasional emails about our work and our articles. Every one has an unsubscribe link: follow it, confirm, and we stop.',
                'These emails contain a small image that tells us when the email is opened, with the IP address that loaded it and, where our network provider gives it, the country. Some email services load images through their own servers, so the address may be theirs. If you don’t want this, set your email app not to load images.',
                'We also send the emails each form promises, such as confirmations and your cost report, and only a limited number each day to any one address.',

                ['heading' => 'Clients and the client portal'],
                'For the organisations we work with and their people: names, job titles, work email addresses and phone numbers; portal accounts (passwords are stored only as a one-way hash); proposals, projects, tasks, documents and messages; approvals and sign-offs, with the name, job title and drawn signature of the person who signs, the date and time, their IP address and the one-time code we email to confirm it; invoices and payment records.',
                'We use them to deliver the work we agreed, keep a reliable record of decisions, bill and account for our work, and support you. If you turn on notifications in the portal, your browser’s push service delivers them.',

                ['heading' => 'Technical data and security'],
                'When you use our website, HQ or the portal, our servers receive your IP address and technical details of your browser, as every website does. Our hosting provider keeps server logs for a limited time to run and protect the service.',
                'To stop abuse, our systems count the requests from each connection: your IP address (for IPv6, the network part of it) is kept for this purpose for 24 hours at most. Email addresses used for these limits are kept only in scrambled (hashed) form, for 7 days at most.',
                'People signed in to HQ or the portal have a session that records their IP address and browser, and actions on sensitive records are written to an audit log.',
                'Each form also carries a hidden field and a timer that tell people from automated programs; they collect nothing about you. If we turn on Cloudflare Turnstile, a check that does the same job, the form pages load it from Cloudflare, which receives your IP address and technical details of your browser to run the check.',
            ]],

            'bases' => ['title' => 'Our legal bases', 'body' => [
                'Data protection law lets us use personal data only on a legal basis. For each purpose, ours is:',
                ['rows' => [
                    ['Answering enquiries, the free audit and demo, the cost report', 'Steps you ask us to take before a possible contract, and our legitimate interest in answering requests about our services.'],
                    ['Sending the Praxis Method guide', 'Your consent, given when you ask for it.'],
                    ['Reviewing articles for Insights', 'Your consent, given when you send the article.'],
                    ['Job applications', 'Steps you ask us to take before a possible employment contract; your consent if you agree that we keep your CV longer.'],
                    ['Feedback on the website', 'Our legitimate interest in fixing and improving the website.'],
                    ['Praxis AI', 'Our legitimate interest in answering questions about our services.'],
                    ['Emails about our work', 'Our legitimate interest in keeping our professional contacts informed. You can object at any time.'],
                    ['Google Analytics', 'Your consent, given with Accept in the cookie banner. You can withdraw it at any time.'],
                    ['Work with clients and the client portal', 'Our contract with your organisation, and our legitimate interest in working with its people and keeping evidence of approvals.'],
                    ['Accounting, tax and legal records', 'Our legal obligations.'],
                    ['Security, limits and preventing abuse', 'Our legitimate interest in keeping our systems, our clients and our visitors safe.'],
                ]],
                'Where we rely on legitimate interests, we use only what the purpose needs, and you can object at any time (see Your rights).',
                'Cameroon’s law gives consent a central place. When you send us a form, you choose to give us the details it asks for, for the purpose it states. You can withdraw that consent at any time by writing to :email; this doesn’t affect what we did before, or the records the law requires us to keep.',
            ]],

            'sharing' => ['title' => 'Who receives it', 'body' => [
                'We don’t sell personal data, and we never use one client’s data for another’s purposes.',
                'Inside JBS Praxis, only the team members whose role needs it can see personal data, through permissions that are set by role and logged.',
                'We use these providers, each only for the service it gives us:',
                ['items' => [
                    'Our hosting provider, whose servers run this website, HQ and the client portal, and store their data and backups.',
                    'Our email providers, which deliver our emails and host our mailboxes.',
                    'Google: Analytics, only if you accept it; Gemini, for Praxis AI and the summary in your cost report, and when our team uses it in HQ to help draft proposals, project notes and emails (it then receives the text that task needs); YouTube, only when you open the demo video.',
                    'DeepSeek, for Praxis AI when Gemini doesn’t answer.',
                    'Your browser’s push service, run by the company that makes your browser, if you turn on notifications in HQ or the portal.',
                    'Cloudflare, only if we turn on its Turnstile check on our forms.',
                ]],
                'We may also share personal data when the law requires it (for example, with a court or a regulator), to protect our rights or someone’s safety, or with professional advisers bound by confidentiality. If JBS Praxis is reorganised or its business passes to another company, your data would pass with it, under the commitments in this policy.',
            ]],

            'transfers' => ['title' => 'International transfers', 'body' => [
                'Our team in Nigeria and our team in Cameroon work in the same systems, so data about a person in one country may be seen from the other.',
                'Some of our providers also handle data outside Nigeria and Cameroon:',
                ['items' => [
                    'Google, in the United States and the other countries where it operates.',
                    'DeepSeek, in China.',
                    'Our hosting and email providers, in the data centres they use, which may be outside Nigeria and Cameroon.',
                ]],
                'When data leaves the country, we send only what the task needs, always over encrypted connections, to providers whose business terms set out how they handle and protect it.',
                'Nigeria’s Data Protection Act allows a transfer abroad when the destination offers adequate protection, when appropriate safeguards are in place, or on the other grounds the Act lists, such as a transfer needed to do what you asked. We rely on our providers’ safeguards and, where they are not enough, on those other grounds.',
                'Cameroon’s law makes transfers abroad subject to the prior authorisation of the Personal Data Protection Authority, which is to publish the countries it considers adequate. We will follow that procedure as soon as the Authority publishes it.',
                'You can ask us for more information about a transfer and its safeguards at :email.',
            ]],

            'retention' => ['title' => 'How long we keep it', 'body' => [
                'We keep personal data only as long as we need it for the purpose we collected it for, then we delete it or make it anonymous. In particular:',
                ['rows' => [
                    ['Job applications and CVs', '12 months from the day we receive them. Our system then deletes the CV and removes everything in the application that identifies you. A new application starts the 12 months again. We keep a CV longer only if you agree, and if you join us, your application becomes part of your staff record.'],
                    ['Enquiries, audit and demo requests, cost reports and contact records', 'While we are in touch, then 3 years after our last contact with you, unless you become a client. The report link works for as long as we keep the report.'],
                    ['Praxis Method guide requests', '12 months.'],
                    ['Insights submissions', 'While we review your article and, if we publish it, while it is published. Your contact details then follow the line above.'],
                    ['Feedback on the website', '2 years.'],
                    ['Praxis AI conversations', 'For your visit only: your session ends after :session_minutes minutes without activity. We don’t keep them in our records. Google and DeepSeek keep what they receive under their own terms.'],
                    ['Email open records and unsubscribes', 'With your contact record. If you unsubscribe, we keep that fact so that we don’t email you again.'],
                    ['Client, project and portal records, approvals and invoices', 'For the engagement, then as long as contract, accounting and tax law require (up to ten years).'],
                    ['Limits against abuse', 'IP addresses: 24 hours at most. Scrambled email addresses: 7 days at most.'],
                    ['Cookies', 'As listed under Cookies.'],
                ]],
                'You can ask us to delete your data sooner (see Your rights). We may keep what the law requires us to keep, or what we need to establish or defend a legal claim, for as long as that need lasts.',
            ]],

            'security' => ['title' => 'How we protect it', 'body' => [
                'We protect personal data with the same controls we build for our clients (see Security and data):',
                ['items' => [
                    'Encrypted connections (HTTPS) between your browser and our systems. The website keeps no database of its own: what you send goes straight to HQ over a signed, encrypted connection.',
                    'Access by role (view, create, edit, approve or export), so people see what their job needs and nothing more.',
                    'An audit log of sensitive actions: who did what, when and from where.',
                    'Private storage for documents and CVs, opened only through signed, permissioned links.',
                    'Limits and checks on every form against automated abuse.',
                    'Processes aligned with ISO 27001. We don’t hold a certification and don’t claim one.',
                ]],
                'No system is perfectly secure. If a breach puts your data at risk, we act at once to contain it, tell the authorities as the law requires (in Nigeria, the NDPC within 72 hours of becoming aware of it), and tell you without undue delay when it is likely to put your rights at high risk.',
            ]],

            'cookies' => ['title' => 'Cookies', 'body' => [
                'Cookies are small files a website stores in your browser. This website uses these:',
                ['rows' => [
                    [':session_cookie', 'Keeps your visit together (your forms and the Praxis AI conversation). Needed for the site to work. Expires after :session_minutes minutes without activity.'],
                    ['XSRF-TOKEN', 'Protects the forms against forged requests. Needed for the site to work. Expires with the session.'],
                    ['jbs_consent', 'Remembers whether you accepted or rejected Google Analytics. Kept for 6 months.'],
                    ['_ga, _ga_…', 'Google Analytics: tells us which pages people use. Set only if you select Accept. Kept for up to 2 years.'],
                ]],
                'Only Google Analytics needs your consent. If you select Reject, or make no choice, it never loads; if you reject it later, we remove its cookies from your browser.',
                'The demo video on the home page plays from YouTube (youtube-nocookie.com), which may store its own data in your browser. Nothing from YouTube loads until you select Watch the 3-minute demo.',
                'HQ and the client portal use their own session and security cookies, which are needed to sign in.',
                'You can change your choice at any time with Cookie settings at the bottom of every page, and delete cookies in your browser’s settings.',
            ]],

            'rights' => ['title' => 'Your rights', 'body' => [
                'Under Nigeria’s Data Protection Act 2023 and Cameroon’s Law No. 2024/017 of 23 December 2024 on personal data protection, you can:',
                ['items' => [
                    'Ask whether we hold personal data about you, and get a copy of it with information on how we use it.',
                    'Have inaccurate or incomplete data corrected.',
                    'Have your data deleted when we no longer need it, when you withdraw your consent, or when it was used unlawfully.',
                    'Ask us to restrict how we use it while a question about it is settled.',
                    'Object to our use of it based on legitimate interests, and to emails about our work at any time.',
                    'Receive the data you gave us in a structured, commonly used format, or have it sent to another organisation where this is technically possible.',
                    'Withdraw your consent at any time, without affecting what we did before.',
                    'Not be subject to a decision based solely on automated processing that has legal or similarly significant effects on you. We make no such decisions.',
                ]],
                'To use any of these rights, write to :email and tell us what you are asking for; we may ask you to confirm your identity before we act. It’s free. We reply within a few working days and complete your request within 30 days. If a request is complex and needs longer, we tell you why within those 30 days.',
                'If we can’t do what you ask (for example, because the law requires us to keep a record), we tell you why.',
                'If your data is in a system we run for a client, we pass your request to that client and help it answer.',
            ]],

            'complaints' => ['title' => 'Complaints', 'body' => [
                'If you are unhappy with how we handle your data, please write to us first at :email, and we will try to put it right.',
                'You can also complain to the data protection authority:',
                ['items' => [
                    'In Nigeria: the Nigeria Data Protection Commission (NDPC), through the channels on its website, ndpc.gov.ng.',
                    'In Cameroon: the Personal Data Protection Authority (Autorité de protection des données à caractère personnel) created by Law No. 2024/017, through the channels it publishes.',
                ]],
                'If you live or work elsewhere, you can also contact the authority of your own country.',
            ]],

            'children' => ['title' => 'Children', 'body' => [
                'Our website and services are for adults acting for their organisations or applying for work. We don’t knowingly collect personal data from anyone under 18. If you think a child has sent us personal data, write to :email and we will delete it.',
            ]],

            'changes' => ['title' => 'Changes to this policy', 'body' => [
                'We review this policy when our systems or the law change. We publish each new version here with its date; when a change is significant, we say so at the top of this page and, where the law requires it, we tell you directly.',
                'This policy is published in English and in French. Both versions say the same thing; if you notice a difference, write to us, and the reading more favourable to you applies.',
            ]],
        ],
    ],
];
