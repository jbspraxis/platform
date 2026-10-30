# Launch copy (EN and FR), 30 October 2026

Final text for the launch pages of the new jbspraxis.com. Builders lay it out; they don't rewrite it.
Facts come only from docs/BRAND.md (method, domains, approved names). No prices, no "predictive", no certificates,
no figures presented as client results, no named people. Contact details, offices and registrations always come from
Website settings, never from this file. FR follows BRAND.md's French rules (« », narrow no-break spaces before ; : ! ?).

Scope at launch (owner, 30 Oct): Home, Services, **How we work**, Company, **Security and data**, Privacy, plus the
existing flows (book, contact, calculator and report, Insights, careers). "How we work" and "Security and data" are
full pages, built by Claude in the foreground (views `pages/how-we-work` and `pages/security`). The navigation is
Services · How we work · Company · Insights · [Book a free audit and demo]; "Client login" sits in the header's right
and the footer. Google Analytics 4 ships at launch behind a consent banner (nothing loads before "Accept").

Owner decisions on the method (30 Oct): public name **the Praxis Method** (never "Predictive", never ™), with the line
"Formerly the Praxis Framework" on the How we work page for continuity; `/framework` → 301 to `/how-we-work#method`; the
old PDF stays available until it is rewritten as "The Praxis Method" (EN and FR) after launch. **Forecasting and
financial modelling are a real capability** (built for a client whose work stays confidential; a demo is available on
request): say it plainly, never name the client, never call it "predictive".

---

## Shared

| Key | EN | FR |
|---|---|---|
| CTA | Book a free audit and demo | Réserver un audit gratuit et une démo |
| CTA short (mobile) | Book a free audit | Réserver un audit |
| Client login | Client login | Espace client |
| Nav: Services | Services | Services |
| Nav: Company | Company | Entreprise |
| Nav: Insights | Insights | Analyses |
| Language switch | English · Français | English · Français |
| Footer tagline | Consulting and engineering for Africa’s institutions | Conseil et ingénierie pour les institutions africaines |
| Footer: offices | Offices | Bureaux |
| Footer: registrations | Registration | Immatriculation |
| Registration pending | registration in progress | immatriculation en cours |
| Footer: follow | Follow us | Suivez-nous |
| Footer links | Security and data · Privacy · Careers · Client login | Sécurité et données · Confidentialité · Carrières · Espace client |
| Copyright | © 2026 JBS Praxis | © 2026 JBS Praxis |
| Praxis AI button | Ask Praxis AI | Demander à Praxis AI |

---

## Home (`/`, `/fr/`)

**Title / description**
- EN: "JBS Praxis | Consulting and engineering for Africa’s institutions" / "We design how your organisation runs, then build the system that answers for it: finance, people, operations and risk in one place. Book a free audit and demo."
- FR: "JBS Praxis | Conseil et ingénierie pour les institutions africaines" / "Nous concevons le fonctionnement de votre organisation, puis le système qui en rend compte : finance, équipes, opérations et risques au même endroit. Réservez un audit gratuit et une démo."

**Hero**
- EN: **Ask your company anything.** / We design how your organisation runs, then build the system that answers for it. / [Book a free audit and demo] / secondary link: See what Praxis AI answers ↓ (scrolls to the loop)
- FR: **Posez toutes vos questions à votre entreprise.** / Nous concevons le fonctionnement de votre organisation, puis le système qui en rend compte. / [Réserver un audit gratuit et une démo] / Voir ce que répond Praxis AI ↓
- Hero visual: the 20-second interface loop (`brand/kit/social/posts/ask-your-company-anything-16x9.mp4`, muted, looping, `playsinline`, poster = its first answer frame; reduced motion shows the poster only). EN video for both languages for now.

**Section: the problem**
- EN heading: A dozen tools. Not one answer.
- EN text: Email in one place, chat in another, accounts in a third, approvals somewhere in between. Each tool answers for itself; none answers for the company. So teams re-key figures, reconcile spreadsheets at month end, and leaders decide on numbers nobody fully trusts.
- FR heading: Une dizaine d’outils. Aucune réponse.
- FR text: Les e-mails d’un côté, la messagerie de l’autre, la comptabilité ailleurs, les validations quelque part entre les trois. Chaque outil répond pour lui-même ; aucun ne répond pour l’entreprise. Les équipes ressaisissent les chiffres, réconcilient des tableurs en fin de mois, et la direction décide sur des données dont personne n’est tout à fait sûr.

**Section: what we build** (5 blocks, one per domain; icon + heading + one line)
| EN | FR |
|---|---|
| **Money.** Invoicing, payments, ledger, reporting, forecasts and financial models, in your currencies, under IFRS and OHADA. | **Finance.** Facturation, paiements, comptabilité, reporting, prévisions et modèles financiers, dans vos devises, selon les normes IFRS et OHADA. |
| **People.** Payroll, time, leave and approvals, with the right person seeing the right figures. | **Équipes.** Paie, temps, congés et validations, chacun voyant les chiffres qui le concernent. |
| **Operations.** Projects, stock, deliveries and field teams, tracked as they happen. | **Opérations.** Projets, stocks, livraisons et équipes terrain, suivis en temps réel. |
| **Risk and governance.** Every action permissioned and logged, with controls built into the work. | **Risques et gouvernance.** Chaque action autorisée et tracée, avec les contrôles intégrés au travail. |
| **Data and AI.** Praxis AI answers questions from your own data, cites its sources and opens the next step. | **Données et IA.** Praxis AI répond à partir de vos propres données, cite ses sources et ouvre l’étape suivante. |

**Section: how we work** (the Praxis Method, numbered: it is a real sequence)
| EN | FR |
|---|---|
| 1. **See.** We map how one area of your organisation really runs today. It starts with the free audit. | 1. **Diagnostiquer.** Nous cartographions le fonctionnement réel d’un domaine de votre organisation. Tout commence par l’audit gratuit. |
| 2. **Design.** We design how it should run: processes, controls, roles and the numbers that matter. | 2. **Concevoir.** Nous concevons son fonctionnement cible : processus, contrôles, rôles et indicateurs qui comptent. |
| 3. **Build.** We build and configure the system that runs it and shows it. | 3. **Construire.** Nous construisons et paramétrons le système qui le fait fonctionner et le rend visible. |
| 4. **Hold.** We stay until it holds, train your team, then hand over the keys. You own the code and the data. | 4. **Pérenniser.** Nous restons jusqu’à ce que tout tienne, formons vos équipes, puis vous remettons les clés. Le code et les données vous appartiennent. |

**Section: who we work with**
- EN heading: Built with institutions across Nigeria and Cameroon
- Items: Smart Logistics and Services (Praxis LS) · MyCity App · Pixie Girl Global · Orika Living · a leading pharmaceutical manufacturer, with Tales Consulting · an FMCG distributor in Cameroon · and nfelia, our own product.
- FR heading: Conçu avec des institutions au Nigeria et au Cameroun
- FR items: Smart Logistics and Services (Praxis LS) · MyCity App · Pixie Girl Global · Orika Living · un grand laboratoire pharmaceutique, avec Tales Consulting · un distributeur de biens de consommation au Cameroun · et nfelia, notre propre produit.
- Names as plain text (no logos until approved).

**Section: the calculator**
- EN: **What is poor quality costing you?** Rework, delays, errors and double entry have a price. Estimate yours in a few minutes, in your currency. [Use the calculator]
- FR: **Combien vous coûte la non-qualité ?** Reprises, retards, erreurs et doubles saisies ont un prix. Estimez le vôtre en quelques minutes, dans votre devise. [Utiliser le calculateur]

**Final call to action** (Ink band)
- EN: **See it on your own operations.** The free audit and demo takes about an hour. We look at one area of your organisation and show you what a straight answer looks like. [Book a free audit and demo] · or call / WhatsApp (numbers from Website settings, hidden when empty)
- FR: **Voyez-le sur vos propres opérations.** L’audit gratuit et la démo durent environ une heure. Nous étudions un domaine de votre organisation et vous montrons à quoi ressemble une réponse claire. [Réserver un audit gratuit et une démo] · ou appelez / WhatsApp

---

## Services (`/services`, `/fr/services`)

**Title / description**
- EN: "Services | JBS Praxis" / "Consulting and engineering for finance, people, operations, risk, and data and AI, using the Praxis Method: See, Design, Build, Hold."
- FR: "Services | JBS Praxis" / "Conseil et ingénierie pour la finance, les équipes, les opérations, les risques, les données et l’IA, selon la méthode Praxis : diagnostiquer, concevoir, construire, pérenniser."

**Intro**
- EN: **One system, designed around how you run.** We don’t sell software licences. We design how an area of your organisation should work, then build the system that runs it, connected to the tools you keep.
- FR: **Un seul système, conçu autour de votre fonctionnement.** Nous ne vendons pas de licences. Nous concevons le fonctionnement d’un domaine de votre organisation, puis construisons le système qui le fait tourner, relié aux outils que vous conservez.

**The five domains**: the same five blocks as Home, each with 2–3 bullet examples:
- Money / Finance: invoicing and collections; payment verification; general ledger and reporting in several currencies; cash and revenue forecasts, budgets and scenario models (demo on request) (EN) · facturation et recouvrement ; vérification des paiements ; comptabilité générale et reporting multidevise ; prévisions de trésorerie et de chiffre d’affaires, budgets et modèles de scénarios (démo sur demande) (FR).
- People / Équipes: payroll for Nigeria and Cameroon; time and leave; appraisals and approvals · paie Nigeria et Cameroun ; temps et congés ; évaluations et validations.
- Operations / Opérations: projects and budgets; stock and deliveries; field teams and sign-offs · projets et budgets ; stocks et livraisons ; équipes terrain et réceptions.
- Risk and governance / Risques et gouvernance: roles and permissions; audit logs; controls in every workflow · rôles et permissions ; journaux d’audit ; contrôles dans chaque processus.
- Data and AI / Données et IA: Praxis AI on your own data; dashboards for leaders; reports that build themselves · Praxis AI sur vos propres données ; tableaux de bord pour la direction ; rapports qui se construisent seuls.

**How we work**: the Praxis Method (same 4 steps as Home) plus:
- EN: **What you can expect.** You own the code and the data. No per-user licences. English and French from day one. We stay until the system holds.
- FR: **Ce que vous pouvez attendre.** Le code et les données vous appartiennent. Aucune licence par utilisateur. Anglais et français dès le premier jour. Nous restons jusqu’à ce que le système tienne.

**The Praxis Method PDF** (existing form, retitled)
- EN: **Read the Praxis Method.** Get the short guide by email. / [Send me the guide]
- FR: **Découvrir la méthode Praxis.** Recevez le guide par e-mail. / [Recevoir le guide]

---

## How we work (`/how-we-work`, `/fr/how-we-work`): built by Claude

**Title / description**
- EN: "How we work | JBS Praxis" / "The Praxis Method: we see how your organisation runs, design how it should run, build the system, and stay until it holds. You own the code and the data."
- FR: "Notre méthode | JBS Praxis" / "La méthode Praxis : nous diagnostiquons votre fonctionnement, concevons le fonctionnement cible, construisons le système et restons jusqu’à ce qu’il tienne. Le code et les données vous appartiennent."

**Hero**
- EN: **How we work.** / The Praxis Method takes one area of your organisation from how it runs today to a system that answers for it. / small line: Formerly the Praxis Framework.
- FR: **Notre méthode.** / La méthode Praxis fait passer un domaine de votre organisation de son fonctionnement actuel à un système qui en rend compte. / petite ligne : Anciennement le Praxis Framework.

**The four stages** (id="method"; each: number, name, what happens, what you get)
| EN | FR |
|---|---|
| 1. **See.** We sit with the people who do the work and map how one area really runs: who does what, where the numbers live, where time and money leak. *You get:* a clear picture and the free audit report. | 1. **Diagnostiquer.** Nous travaillons avec les équipes et cartographions le fonctionnement réel d’un domaine : qui fait quoi, où vivent les chiffres, où se perdent le temps et l’argent. *Vous recevez :* une vision claire et le rapport de l’audit gratuit. |
| 2. **Design.** We design how it should run: the process, the controls, who approves what, and the few numbers that matter. *You get:* a design you can read and sign off. | 2. **Concevoir.** Nous concevons le fonctionnement cible : le processus, les contrôles, qui valide quoi, et les quelques indicateurs qui comptent. *Vous recevez :* une conception lisible, à valider. |
| 3. **Build.** We build and configure the system around that design, connect the tools you keep, and move your data across. *You get:* a working system, tested with your team. | 3. **Construire.** Nous construisons et paramétrons le système autour de cette conception, le relions aux outils que vous conservez et reprenons vos données. *Vous recevez :* un système opérationnel, testé avec vos équipes. |
| 4. **Hold.** We stay until it holds: we train your people, watch the first cycles, fix what the real world reveals, then hand over the keys. *You get:* a system your team runs, and the code and data that are yours. | 4. **Pérenniser.** Nous restons jusqu’à ce que tout tienne : formation des équipes, suivi des premiers cycles, corrections nées du terrain, puis remise des clés. *Vous recevez :* un système que vos équipes font tourner, avec un code et des données qui vous appartiennent. |

**What you can expect** (4 short points)
| EN | FR |
|---|---|
| You own the code and the data. | Le code et les données vous appartiennent. |
| No per-user licences. | Aucune licence par utilisateur. |
| English and French from day one. | Anglais et français dès le premier jour. |
| One team from first meeting to handover. | Une seule équipe, du premier rendez-vous à la remise des clés. |

**The free audit** (CTA band)
- EN: **It starts with a free audit.** About an hour, on one area of your organisation. You leave with a clear picture of how it runs today and what to fix first. [Book a free audit and demo]
- FR: **Tout commence par un audit gratuit.** Environ une heure, sur un domaine de votre organisation. Vous repartez avec une vision claire de son fonctionnement actuel et des priorités. [Réserver un audit gratuit et une démo]

**The Praxis Method guide**: the existing method-PDF form (EN "Get the Praxis Method guide by email" / FR "Recevoir le guide de la méthode Praxis par e-mail").

---

## Security and data (`/security`, `/fr/security`): built by Claude

**Title / description**
- EN: "Security and data | JBS Praxis" / "How JBS Praxis protects your data: roles and permissions, audit logs, private storage, processes aligned with ISO 27001, NDPA and Cameroonian data protection law."
- FR: "Sécurité et données | JBS Praxis" / "Comment JBS Praxis protège vos données : rôles et permissions, journaux d’audit, stockage privé, processus alignés sur ISO 27001, NDPA et loi camerounaise sur la protection des données."

**Hero**
- EN: **Your data stays yours.** / The systems we build protect what matters by design: who can see it, who can change it, and a record of everything.
- FR: **Vos données restent les vôtres.** / Les systèmes que nous construisons protègent l’essentiel dès la conception : qui peut voir, qui peut modifier, et une trace de tout.

**Six blocks**
| EN | FR |
|---|---|
| **Roles and permissions.** Every screen and action is allowed by role, view, create, edit, approve or export, so people see what their job needs and nothing more. | **Rôles et permissions.** Chaque écran et chaque action dépendent du rôle (consulter, créer, modifier, valider, exporter) : chacun voit ce dont son travail a besoin, rien de plus. |
| **A record of everything.** Sensitive actions are written to an audit log: who did what, when and from where. | **Une trace de tout.** Les actions sensibles sont inscrites dans un journal d’audit : qui a fait quoi, quand et d’où. |
| **Separation of duties.** The person who prepares a payment or payroll can’t approve it. Controls are built into the work, not added after. | **Séparation des tâches.** Celui qui prépare un paiement ou une paie ne peut pas le valider. Les contrôles font partie du travail, ils ne s’ajoutent pas après. |
| **Private by default.** Documents, CVs, receipts and payment proofs are stored privately and only opened through signed, permissioned links. | **Privé par défaut.** Documents, CV, reçus et preuves de paiement sont stockés en privé et ne s’ouvrent que par des liens signés et autorisés. |
| **Aligned with ISO 27001.** Our processes follow ISO 27001 practices. We don’t claim a certification we don’t hold. | **Aligné sur ISO 27001.** Nos processus suivent les pratiques ISO 27001. Nous ne revendiquons pas une certification que nous ne détenons pas. |
| **Your rights.** We work under Nigeria’s Data Protection Act and Cameroon’s data protection law. Ask what we hold about you, correct it or have it deleted: write to the email below. | **Vos droits.** Nous respectons la loi nigériane sur la protection des données (NDPA) et la loi camerounaise. Demandez ce que nous détenons à votre sujet, faites-le corriger ou supprimer : écrivez-nous à l’adresse ci-dessous. |

**Footer of the page**: contact email from Website settings, link to Privacy.

---

## Company (`/company`, `/fr/company`)

**Title / description**
- EN: "Company | JBS Praxis" / "JBS Praxis is a consulting and engineering firm for Africa’s institutions, with offices in Lagos and Douala."
- FR: "Entreprise | JBS Praxis" / "JBS Praxis est un cabinet de conseil et d’ingénierie au service des institutions africaines, présent à Lagos et à Douala."

**Intro**
- EN: **Order, made visible.** JBS Praxis is a consulting and engineering firm for Africa’s institutions. We design how organisations run, then build the systems that answer for them, in English and French.
- FR: **L’ordre, rendu visible.** JBS Praxis est un cabinet de conseil et d’ingénierie au service des institutions africaines. Nous concevons le fonctionnement des organisations, puis les systèmes qui en rendent compte, en anglais et en français.

**Facts** (from Website settings): legal name · offices · registrations ("registration in progress" while pending) · contact email · social links. Leadership block appears only when switched on in HQ.

**We run on it ourselves**
- EN: We run our own invoicing, payroll, projects and approvals on the same system we build for clients.
- FR: Nous gérons notre propre facturation, notre paie, nos projets et nos validations sur le système que nous construisons pour nos clients.

**Our products**
- EN: **nfelia**, a JBS Praxis product. [nfelia.com]
- FR: **nfelia**, un produit JBS Praxis. [nfelia.com]

**Security and data** (a short teaser here linking to the full `/security` page; `/trust-center` → 301 to `/security`)
- EN: Your data stays yours. Every action in the systems we build is permissioned by role and recorded in an audit log. Files are stored privately, never on public links. Our processes are aligned with ISO 27001. We work under Nigeria’s Data Protection Act (NDPA) and Cameroon’s data protection law, and you can ask what we hold about you at any time.
- FR: Vos données restent les vôtres. Chaque action dans les systèmes que nous construisons est autorisée selon le rôle et enregistrée dans un journal d’audit. Les fichiers sont stockés de façon privée, jamais sur des liens publics. Nos processus sont alignés sur la norme ISO 27001. Nous respectons la loi nigériane sur la protection des données (NDPA) et la loi camerounaise sur la protection des données personnelles ; vous pouvez demander à tout moment ce que nous détenons à votre sujet.

**Careers** link: EN "Work with us" → /careers · FR "Travailler avec nous" → /fr/careers

---

## Privacy (`/privacy`, `/fr/privacy`)

Port the current policy (`praxis_core/resources/views/livewire/privacy.blade.php`) into the brand voice, keeping every
legal point it makes, and update it to what the new site actually does. Sections (EN / FR):
1. Who we are / Qui nous sommes: legal name, offices and contact email from Website settings.
2. What we collect / Ce que nous collectons: the forms (contact, booking, calculator, careers with CV, Insights submissions, feedback, method guide), Praxis AI questions, and technical data needed to protect the forms (IP address for rate limits, kept for at most 24 hours).
3. Why / Pourquoi: to answer you, run the free audit, send what you asked for, recruit, and keep the site secure.
4. How long / Durée de conservation: as in the current policy; CVs as in the current policy.
5. Who sees it / Qui y a accès: JBS Praxis staff by role; our email and hosting providers; never sold.
6. Your rights / Vos droits: access, correction, deletion, objection, under the NDPA and Cameroonian law; how to ask (email from settings).
7. Cookies / Cookies: what the site needs to work (session, security, your cookie choice), and Google Analytics only if you accept it in the banner; you can change your choice at any time from the "Cookie settings" link in the footer.
8. Changes / Modifications: date of this version (30 October 2026).
