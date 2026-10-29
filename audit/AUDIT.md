# jbspraxis.com: audit and revamp plan

29 October 2026. Sources:
- every public page, read live
- desktop and phone screenshots
- the full Laravel code and database copy taken this morning
- the 35 MB error log
- your own projects: mycity-app (latest, from GitHub), praxis-ls, nfelia, Fidson FieldForce, Bureau LPC, OK Veggies, Hub/Pixie Girl Hub

Five areas were audited by agents: security, bugs, positioning, SEO and UX. I re-checked their most serious findings in the code. Speed, lead handling, the portfolio and the visual pass I did myself.

---

## 1. The honest answer

Suppose a pharma, FMCG or oil & gas buyer sent me this site today and asked whether to consider JBS Praxis. **I would say no, or "not from this website".**

| Visitor | Verdict | Why |
|---|---|---|
| Procurement manager | **No** | They can't open a vendor file. There is no legal entity, no registration number and no team. The ISO badges have no certificate numbers. Budget bands start at "Under $5,000" and offer "Pre-revenue". |
| IT manager / CTO | Maybe | Only if they find the Praxis AI article, the one credible page. Everything else works against you: fake terminals, three different uptime figures, and stack claims (Golang/Kafka/EKS) that contradict the case studies (PHP/Laravel). |
| COO / operations | Maybe | Only if they reach the CEMAC beverage case on page 3 (returnable containers, fuel fraud, proof of delivery). It is buried under "CLASSIFIED INTEL", with no screenshots or numbers. |
| CFO | **No** | The money story (stop leakage, stop paying USD licences) is never told. The calculator defaults to USD and shows "$460,000" before any input. |
| CEO / MD | **No** | No names, no faces, no recognisable clients. The tone (strike teams, classified dossiers) reads as theatre, not seniority. |

**The gap.** Your work is far better than your website.
- **What you have built:**
  - a live distribution ERP in Cameroon
  - a pharma field-force app for a major Nigerian manufacturer
  - a multi-tenant logistics ERP
  - an ERP with Praxis AI backed by about 4,400 automated tests and 50 release checks
  - your own SaaS, live in 4 countries
- **What the site shows:** none of that. No product screenshots, no demo, and no sector pages. Pharma, FMCG, waybills, NAFDAC, batch/expiry and SAP are mentioned **zero** times across 20 pages, and FMCG and Ghana don't appear once.
- **No enquiries since May:** the site has recorded **zero enquiries since 22 May 2026**. That covers bookings, contact messages, calculator reports and job applications. There are no analytics, so we can't tell whether that is no traffic or broken forms. Section 4.6 shows forms that really are broken.

---

## 2. Fix now: outside the 4 PRs

These are live risks. All are small.

| # | What | Who | Effort |
|---|---|---|---|
| U1 | **`praxisjbs/mycity-app` on GitHub is public** (since 24 Oct). It contains MyCity's real staff list (`seeds/staff/staff-list.xlsx`), 8 client meeting transcripts, and 2 Google API keys inside two transcripts. **Switch it to Private now**, then rotate those 2 keys. If the client must be told under the NDPA, that is your call. | You (GitHub and Google Cloud) | 10 min |
| U2 | **Anyone with a client login can open the internal HQ ERP.** The HQ admin-access check exists in the code but is never switched on, and 33 of 52 HQ screens do no role check of their own. Those include payroll, the general ledger, payslips, remittance (bank) accounts, the leads register, candidate CVs, role management and others (SEC-1, re-checked). | Me (one-line route fix plus a check on each screen) | 1–2 h |
| U3 | **5 Google/Gemini keys are written into the website's source in 9 places** (SEC-6). The source isn't public, but the public AI chat has no rate limit (SEC-5), so anyone can run up your bill. Rotate the keys, move them to `.env`, and throttle the chat. | Me in code; you rotate in Google Cloud | 1 h |
| U4 | **2 candidate CVs and payment receipts can be downloaded** from `/storage` without logging in. The file names are random and long, so the risk is low, but they are personal data (SEC-8). | Me | 30 min |

---

## 3. What you can actually prove (portfolio)

| Project | What it is | Target-sector fit | Status | Can it be named? |
|---|---|---|---|---|
| **Fidson FieldForce** (fidrep, Fidson-CRM) | Field-force SFA/CRM for medical reps: an offline phone app, territories and GPS check-ins (PostGIS), orders, an ERP adapter, Microsoft sign-in | **Pharma**, and the strongest one | In delivery (Sep 2026) through Tales Consulting, with JBS Praxis as the engineering team | Needs consent from both Tales and Fidson. Otherwise: "a top Nigerian pharmaceutical manufacturer". |
| **Bureau LPC** (La Petite Cour) | Live ERP for an agri-food distributor: sales, inventory, fleet, CRM, accounting, HR, analytics | **FMCG distribution** | Live at bureau.lpc.cm | Ask the client |
| **Praxis LS / Smart Logistics** | Multi-tenant logistics ERP: freight, customs, WMS, fleet, procurement, OHADA finance, workflow, mail, AI, client portal | **Oil & gas supply chain, FMCG logistics** | First tenant in Cameroon. Local code is behind the lost main branch. | Ask the client |
| **MyCity ERP + Praxis AI** | Operations ERP: workspace, tasks, projects, calendar, analytics, geofenced attendance, OKR→KPI→scorecard, mail synced to the DB with AI drafting, audit log | Cross-sector. **Best CTO proof**: about 4,400 tests, about 50 release checks, staging sealed before production, nightly backups with restore drills, passkeys, ClamAV | In delivery until 18 Dec; 67 of 102 screens built | Not without written consent |
| **Nfelia** | Your own SaaS: shops and records for small businesses | Shows you build **and run** products | Live in NG, GH, KE and CM | Yes, it's yours |
| **Hub / Pixie Girl Hub** | Multi-entity retail ERP: POS, stock, purchasing, payroll, loyalty, logistics | Retail / FMCG-adjacent | Various stages | Ask |
| **OK Veggies** | Lagos fresh-provisions storefront, B2B Pro Portal and back office | FMCG B2B ordering | Various stages | Ask |

**What the evidence does and doesn't back:**
- **Waybill reconciliation and inventory leakage:** backed by Bureau LPC, Smart Logistics and the beverage case. **Not** by MyCity, which has no inventory or waybill module.
- **No USD licences:** true of everything you build (self-hosted, no per-seat fees).
- **"Prediction":** **not backed anywhere yet.** Praxis AI in MyCity answers questions and opens pre-filled forms. It deliberately never acts, and there is no forecasting. Don't claim prediction until something ships.
- **Praxis AI's real strength is how it is governed:**
  - permissions are enforced in code before the model sees any data, and ID and bank numbers are redacted
  - the model never writes SQL, and every answer comes with citations to the records it used
  - AI spend is metered in naira, with caps
  - That is a strong CTO story.

---

## 4. Findings

Severity: **C** = critical, **H** = high, **M** = medium, **L** = low. Rows marked ✔ were re-checked in code by me.

### 4.1 Security (17 findings, from the code and live checks)
**The outside of the site holds up.** `.env`, `.git` and the logs are blocked, debug is off, cookies are secure, and Laravel 12.53 and Livewire 4.2.1 are current. **The inside is where the problems are.**

| ID | Sev | Finding | Fix |
|---|---|---|---|
| SEC-1 ✔ | C | HQ ERP is open to any logged-in user, including clients. The admin-access check is never switched on, and 33 of 52 screens check no role. | Switch the check on for every /hq route and Livewire request; add per-screen role checks. |
| SEC-2 ✔ | H | Client onboarding link: the browser can change which user's password gets set (unlocked `$user`; nothing in the app uses `#[Locked]`). | Keep only the token, lock it, re-query the user server-side. |
| SEC-3 | H | Project-tracker PIN can be skipped by setting `isAuthorized=true` from the browser. | Keep authorisation server-side, in the session; add a rate limit. |
| SEC-4 | H | The handover e-signature can be bound without the OTP, even on already-signed contracts; the OTP leaks to the browser. | Record OTP verification server-side and block re-signing. |
| SEC-5 | H | Public AI chat on every page: no login, no rate limit, browser-editable history, model output rendered as raw HTML. | Server-side history, rate limit, escaped output, timeouts. |
| SEC-6 ✔ | H | Gemini keys written into the source (5 distinct keys, 9 places); `config/services.php` is broken. | Rotate; `.env` only; add a secret scan. |
| SEC-7 | M | Cost calculator: no validation or throttle; it creates "Active" users, attaches them to existing client organisations by name, and emails any address. | Validate, rate-limit, add Turnstile; create leads, not users. |
| SEC-8 | M | CVs and payment receipts publicly downloadable under /storage. | Private disk plus an authorised download. |
| SEC-9 | M | 4 login forms with no rate limit and no 2FA; password recovery unfinished. | Rate limit; 2FA for staff; finish or remove the reset flows. |
| SEC-10 | M | Client dashboard: one client can act on another client's tasks, tickets and invoices. | Scope every write by organisation; lock state. |
| SEC-11 | M | No security headers (HSTS, CSP, X-Frame-Options, nosniff, Referrer-Policy). | Add a middleware or `.htaccess` block. |
| SEC-12 | L | Weak, never-expiring tokens on the dossier, proposal and tracker pages. | 40-character random or ULID, plus expiry and a throttle. |
| SEC-13 | L | Stored-XSS paths on Kaizen article fields and client-portal logs. | Escape. |
| SEC-14 | L | Unsigned GET tracking/unsubscribe links; IP geolocation over plain http. | Signed URLs; POST to unsubscribe; HTTPS geolocation. |
| SEC-15 | L | Production logs at debug level in one 35 MB file; OTPs could be logged. | Warning level, daily files, 14-day retention. |
| SEC-16/17 | info | Dependencies current; perimeter checks passed. | Run `composer audit` monthly. |

### 4.2 Bugs and reliability (21 findings)
All 23 public pages return 200 and none of about 50 live requests failed. **But leads are being lost silently.**

| ID | Sev | Finding | Fix |
|---|---|---|---|
| BUG-1 ✔ | H | The contact form says "check your inbox for your calendar link", but **sends no email and notifies nobody at HQ**. | Confirmation email, internal alert and HQ notification. |
| BUG-2 | H | Queued emails are never sent: a database queue with no worker. 4 jobs have been stuck since March–May, including whitepaper and invitation emails. | Send synchronously or run a worker from the scheduler; clear the stuck jobs. |
| BUG-3 | H | **The cron scheduler stopped on 19 Oct** (it also stopped for 16 days in June–July). Exchange rates are no longer syncing. | Restore the cPanel cron and add a heartbeat monitor. |
| BUG-4 | M | Lead tiering ignores currency, so 13 of 14 calculator leads are "Tier 1". | Convert to USD before tiering. |
| BUG-5 | M | The Kaizen "submit insight" form always fails for new contributors, and the visitor sees no error. | Fix the organisation link; show the error. |
| BUG-6 | M | "Email my report" always says "PAYLOAD DELIVERED", even when sending fails; the error isn't logged. | Return and show the real status; set an SMTP timeout. |
| BUG-7 | M | Bots tamper with unlocked homepage properties, causing all 112 web errors in the last 30 days. | Load lists in render; lock state. |
| BUG-8 | M | Scheduled `finance:run-automations` doesn't exist and has failed on 145 nights. | Implement it or remove it. |
| BUG-9 | M | Two full-site outages went unnoticed for 15–36 h (a Blade syntax error in May, DB access denied in July). | Uptime monitor; `view:cache` before deploy. |
| BUG-10 | M | Slow: 0.9–3.8 s before the first byte; full pages took 11–25 s on the first pass. No Laravel caches. | Cache config, routes and views; Cloudflare (PERF-2). |
| BUG-11 | M | French mode mixes languages: 10 of 15 public views aren't translatable, and the homepage is missing 140 French strings. | See PR4. |
| BUG-12 | M | Same as SEC-6 (hard-coded keys). | — |
| BUG-13 | L | Booking offers past times today, shows UTC labelled as WAT, and has no double-booking check. | Africa/Lagos time zone; validate the slot server-side. |
| BUG-14 | L | Validation gaps (careers phone length can cause a 500; feedback has no limits). | Max lengths matching the database, plus lists of allowed values. |
| BUG-15 | L | The old /client-portal is still live, and "Recover key" will crash. | Redirect it to /client/login. |
| BUG-16 | L | Footer links to /hq/login ("COMMAND ACCESS"); the sitemap lists it but leaves out contact, privacy, trust-center and framework. | Remove the link; fix the sitemap. |
| BUG-17 | L | The storage link points at the wrong public folder. | Set the public path. |
| BUG-18 | L | `index.php` looks for the maintenance file in the wrong place. | Fix the path. |
| BUG-19 | L | Test-like leads, undated articles and inconsistent content in the database. | Clean up. |
| BUG-20 | L | AI chat shows raw Google errors to visitors. | Friendly message; log the detail. |
| BUG-21 | info | Debug logging into one ever-growing file. | See SEC-15. |

### 4.3 Positioning and trust (13 findings)

| ID | Sev | Finding |
|---|---|---|
| POS-1 | **C** | **Unbacked ISO 9001/14001/27001 and FCPA badges marked "[ SYSTEM: VERIFIED ]"**, two of them mislabelled. The footer "SLA" on every page promises 99.9% uptime, zero data loss and <30 min response. A procurement check will catch this. Remove it unless certificates exist. |
| POS-2 | **C** | Not outcome-based. The H1 is "JBS PRAXIS"; the meta says "global Tier-1 enterprises". No target sector, country or pain is named. |
| POS-3 | H | Proof is anonymous and unquantified. 7 anonymous testimonials across unrelated sectors, all 3 cases in CEMAC, no Nigeria or Ghana case, results like "100%". |
| POS-4 | H | The real product is invisible: no demo and no screenshots; fake terminals take their place. |
| POS-5 | H | Military and hacker jargon: "strike" 27×, "protocol" 24×, "vault" 20×, "dossier" 10×; "ENGAGE JBS", "AUTHORIZE TRANSMISSION". |
| POS-6 | H | Three company names (JBS Praxis, Jireh Business Solutions, Praxis Intelligence). No legal form, registration, founding year or team. |
| POS-7 | M | Contradictions a CTO will spot: uptime 99.9 / 99.97 / 99.99%; Golang/Kafka/EKS vs PHP/Laravel. |
| POS-8 | M | Pricing signals in USD from "Under $5,000"; "Pre-Revenue" option; CTAs dead-end. |
| POS-9 | M | Privacy and Trust pages are marketing copy, not documents a vendor review can use (no controller, retention or sub-processors; the AI chat is undisclosed). |
| POS-10 | M | French is partial; Spanish is advertised but doesn't exist. |
| POS-11 | M | Kaizen: 5 of 6 articles are about 150 words under "4–7 minute read" labels; the strong Praxis AI article shows raw `**` Markdown. |
| POS-12 | M | Homepage: 11 sections and gimmicks; no Industries or Case studies in the nav. |
| POS-13 | L | Same jargon title and description on every page. |

**Keep:**
- the beverage case story
- the Praxis AI article
- the cost-of-manual-work calculator idea, simplified and in local currency
- "approval rules built into the system"
- the short Jireh origin story
- the French translation set-up

### 4.4 SEO and AI discoverability (15 findings)

| ID | Sev | Finding |
|---|---|---|
| SEO-1 ✔ | **C** | **A template bug drops every page's own title and description**, so all 17 pages carry the same ones. |
| SEO-2 | H | No page targets what buyers search. No sector, solution, country or case-study URLs. |
| SEO-3 | H | The Kaizen hub builds its article links in JavaScript, so crawlers and AI bots can't follow them. |
| SEO-4 | H | Articles are thin and undated, with first-name bylines and no target queries. |
| SEO-5 | M | French is session-only, so crawlers never see it; hreflang en/fr/x-default all point to the same URL. |
| SEO-6 | M | The sitemap lists /hq/login and leaves out key pages, with fake lastmod dates; robots.txt has no Sitemap line. |
| SEO-7 | M | The admin login, token pages and the placeholder portal can all be indexed. |
| SEO-8 | M | No llms.txt, IndexNow or Bing verification. **nfelia already has all three; port them.** |
| SEO-9 | M | Thin structured data: one Organization node, no @id, sameAs, areaServed or breadcrumbs, and no VideoObject. |
| SEO-10 | M | /framework sits outside the layout with no meta tags. |
| SEO-11 | M | The Praxis AI article shows raw `**`. |
| SEO-12..15 | L | Brand-only or missing H1s; hashed slugs (-1b146); /index.php/* duplicates; render-blocking Tailwind CDN. |

### 4.5 UI/UX and accessibility (14 findings, plus my screenshot pass)

| ID | Sev | Finding |
|---|---|---|
| UX-1 | H | The hero sells the brand name, not an outcome; there's no product visual anywhere. |
| UX-2 | H | **Why it feels "choked":** the homepage has 10–11 sections, 35 headings, 55 mono labels, 44 terminal labels ("// SYS.ARCH : ONLINE"), 17 animations, 38 gradients and 17 glass blurs. Across the 15 views: 68 animations, 70 font sizes, 39 colours, and scanlines on 11 pages. |
| UX-3 | H | **On phones the only contact button is hidden** and was never added to the mobile menu. |
| UX-4 | H | Nav: 5 of 6 top items are dropdown triggers with no link, 3 dropdowns hold a single item, Client Portal takes a top slot, and HQ is in the footer. |
| UX-5 | H | 42 form labels across 5 lead forms aren't connected to their inputs (screen readers, autofill). |
| UX-6 | M | The language switcher and the AI chat launcher can't be reached by keyboard; there's no skip link. |
| UX-7 | M | Constant motion; reduced-motion is respected on only 1 of 15 views; the carousel always auto-advances. |
| UX-8 | M | Broken headings: /contact has no H1; /kaizen has a placeholder "Article Title" H1. |
| UX-9 | M | No design system: 37–97 KB of inline CSS per page, and tokens redefined differently on each page. |
| UX-10 | M | Tailwind Play CDN and `lucide@latest` in production; dead social links. |
| UX-11 | M | French leaves every form in English. |
| UX-12..14 | L | Jargon buttons ("INITIATE TRANSFORMATION >_"); small grey footer text; inconsistent breakpoints and stock photos. |
| VIS-1 | M | **A blank screen while scrolling.** Content stays hidden until a reveal animation fires, so fast scrollers, screenshots and crawlers see an empty dark screen. |
| VIS-2 | M | On phones the floating AI button covers the hero's main button. |
| VIS-3 | M | The homepage is 8,726 px tall on desktop (about 11 screens) and 11,530 px on a phone (about 14 screens). |

### 4.6 Speed and lead handling (my checks)

| ID | Sev | Finding | Fix |
|---|---|---|---|
| CONV-1 | **C** | **No enquiry of any kind recorded since 22 May 2026.** Bookings: 6 (Mar–Apr). Contact messages: 5 (Mar–Apr). Calculator reports: 14 (Mar–May, at least 5 of them test-like). Job applications: 3 (Mar). Whitepaper downloads: 15 (Mar–May). | All the fixes below, then measure. |
| CONV-2 | H | **No analytics at all** (no GA4, GTM, Clarity or Plausible) and no cookie notice. Traffic, sources and which buttons work are all unknown. | GA4 + Search Console + a consent banner (NDPA). |
| CONV-3 | H | Lead paths that fail silently: BUG-1, BUG-2, BUG-3 and BUG-6 together. | PR1 |
| CONV-4 | M | No WhatsApp link (the default B2B channel in Nigeria, Ghana and Cameroon). One Nigerian phone number. Social icons link nowhere (`href="#"`). | WhatsApp and phone per country; real social links. |
| CONV-5 | M | The contact form has 13 fields, a USD budget and "Pre-Revenue" options; contact and schedule are separate flows. | One "Book a demo" flow: 5 fields, local currency, slot picker. |
| CONV-6 | L | Your internal CRM pipeline (leads, then proposal, sign-off and escrow) is good tooling, but it is unprotected (SEC-1) and gets no web leads. | PR1 + PR3 |
| PERF-1 | H | **HTML is sent uncompressed**: 245 KB even when gzip is requested. Every page is `no-store`. | Compression and caching for guests. |
| PERF-2 | H | **jbspraxis.com isn't behind Cloudflare**: DNS points straight at the server (37.59.83.88), with no CDN, no WAF and no bot shield. nfelia is behind it. | Orange-cloud the DNS records, and cache HTML for guests. |
| PERF-3 | M | Render-blocking: the Tailwind compiler runs in the browser, plus 3 font families with 10 weights (Inter, Roboto Mono, Montserrat). | Compiled CSS, one font family. |
| PERF-4 | M | Unsplash stock photos hot-linked, up to 504 KB each; the hero image is 242 KB. | Own optimised images or screenshots, AVIF/WebP, width/height set, lazy loading. |
| PERF-5 | M | 37–97 KB of inline CSS/JS per page; no Laravel caches. | One versioned stylesheet; `php artisan optimize` on deploy. |

**Budget for the revamp:**
- HTML under 40 KB compressed
- first view under 1 MB (video excluded)
- LCP under 2.5 s on 4G
- demo video: `preload="none"`, a poster under 100 KB, MP4 under 8 MB, captions in EN and FR

---

## 5. Revamp direction

**Positioning (draft, built on your own line):**
> **ERP and automation for manufacturers and distributors in West and Central Africa.**
> We reconcile every waybill, stop stock and cash leakage, and replace USD-priced software licences with a system you own, with an AI assistant that answers questions from your own data.
> *For pharmaceutical, FMCG and oil & gas companies in Nigeria, Ghana and Cameroon. Custom-built, or layered onto the ERP you already run.*

Rule: publish only numbers you can evidence. Never write "zero", "100%" or "guaranteed".

**Sitemap:**
- Home
- Solutions:
  - waybill and proof-of-delivery reconciliation
  - inventory and returnables
  - field sales and medical reps
  - procurement and approvals
  - finance and multi-currency
  - Praxis AI
  - automating your existing ERP
- Industries: Pharma, FMCG & beverages, Oil & gas
- Case studies (3)
- How we work (with pricing signals)
- Security & data (replaces Trust Center)
- About (company facts and people)
- Insights (Kaizen renamed and cleaned)
- Careers
- Book a demo (merges /contact and /schedule)

Retire /framework, /feedback, the old /client-portal, and the HQ link.

**Homepage (7 sections, about 4 screens, not 11):**
1. Outcome hero, with "Book a demo" and "Watch 60 s"
2. **The 60-second Praxis AI demo**
3. Problems we fix, by sector
4. Featured case with real numbers and screenshots
5. Product tour (4 screenshots)
6. How we work, plus security in brief
7. Final CTA: booking, WhatsApp and phone per country

**Design:** calm industrial software, not a hacker terminal.
- Light-first, one accent colour (teal `#0F766E` on ink `#0B1B33`), IBM Plex Sans, sentence case.
- Motion only for state changes; no glow, glass, scanlines or `//` labels.
- Tokens are drafted in the UX notes. All text pairs pass WCAG AA.

**Demo video:**
- Record with Playwright on a **separate, anonymised demo copy** of the MyCity ERP, never on MyCity's staging, which holds real staff and sends real email.
- Use a fictional company, e.g. "Ndali Foods & Pharma", with Ikeja Plant, Apapa Depot and Port Harcourt Base.
- Storyboard: clock-in at the depot → morning dashboard → *"Who is late at Apapa and which leave requests wait on me?"* (answer with citations) → a junior account is refused the same question → *"Book a stock-count review tomorrow at 10"* opens a pre-filled form → goal cascade → audit log and AI spend in naira → end card.
- A Playwright harness already exists in mycity-app (`scripts/guide/shoot.js`).
- Caveat: it shows operations and AI, not waybills. A logistics clip from Praxis LS can follow.

---

## 6. The 4 PRs

| PR | Scope | Closes | Depends on |
|---|---|---|---|
| **PR1: Safety and lead plumbing** (no redesign; ships first, about 1–2 days) | Switch on the HQ access check and add role checks; lock component state; AI chat throttle and server-side history; keys to `.env`; private storage for CVs and receipts; login rate limits; security headers; tokens and expiry. **Leads:** contact confirmation and internal alert, queue worker, cron restored with a heartbeat, real send status. **Trust:** remove ISO badges, the footer SLA, "VERIFIED" and the Spanish option; remove the HQ link and fix the sitemap and robots; noindex token pages. **Ops:** Cloudflare proxy, compression, Laravel caches, log level, maintenance path, storage link. | SEC-1..15, BUG-1..3, 5..9, 13..18, 20..21, POS-1, CONV-3, PERF-1..2, SEO-6..7 | Only certificate facts (Q7) |
| **PR2: Foundation: design system, layout and SEO plumbing** | Tokens and one compiled stylesheet (drop the Tailwind CDN and lucide@latest); new header, nav and footer (Book a demo, mobile CTA, Client login in the footer); per-page meta fix; JSON-LD @graph; sitemap, robots, llms.txt and IndexNow ported from nfelia; locale URLs (/fr); GA4 and consent; accessibility base (labels, focus, skip link, reduced motion, headings) | SEO-1, 5, 8, 9, 12, 14, 15; UX-3..10; PERF-3..5; CONV-2; VIS-1 | Design direction (Q12), languages (Q11) |
| **PR3: New homepage, demo video and booking flow** | Outcome hero; 60-second demo (anonymised demo environment plus Playwright); problems by sector; featured case; product tour; simplified calculator in local currency; one "Book a demo" flow with WhatsApp and phone per country | POS-2, 4, 5, 8, 12, 13; UX-1, 2, 12; CONV-4, 5; VIS-2, 3; BUG-4, 13 | Positioning (Q2), demo (Q6), pricing (Q10) |
| **PR4: Proof and content pages** | Industries (3); Solutions; 3 case studies rewritten with numbers; Product / Praxis AI; How we work; Security & data; About with company facts and people; privacy rewrite; Insights cleanup (keep and fix the Praxis AI article; retire or rewrite the thin ones with 301s); full French | POS-3, 6, 7, 9, 10, 11; SEO-2, 3, 4, 10, 11, 13; BUG-11, 19; UX-11 | Client naming (Q4), cases (Q5), company facts (Q8), people (Q9) |

PR1 lands first so the revamp sits on a safe base. PR2 → PR3 → PR4 follow in order. The urgent items U1–U4 can go out as a hotfix today.

---

## 7. Owner decisions (29 Oct 2026)

Where these differ from sections 5–6, these win.

### Brand and message
- **Hero:** "**Ask your company anything.**"
  - Subline: "We build and automate the systems your organisation runs on, so every figure, record and approval sits in one place, and answers back in plain language."
  - Brand signature (About, footer): "Complexity, composed."
  - French: "Interrogez votre entreprise."
- **No industries in the hero.** The homepage treats buyers as institutions in motion. Industries live one level down (nav and SEO pages).
- **Don't promise "predictive"** until forecasting ships. Praxis AI today answers questions, cites records and opens pre-filled forms.
- **Countries:** focus on Nigeria and Cameroon; written to be found worldwide (no country in the hero; country and French pages for search).
- **Calls to action:** no prices anywhere. Every CTA is "**Book a free audit & demo**". How we work explains discovery → build → support, that you own the code and data, and that there are no per-user dollar licences.
- **Certificates:** none held. Remove every badge and the footer SLA. Say "processes aligned with ISO 27001" (working towards it).
- **Identity:** "JBS Praxis" only.
  - Registration numbers (CAC/RCCM, due by end of November), founding year, team size, offices and contact numbers are all **edited from an admin "Website settings" page**, with no code changes.
  - **Leadership** is built but hidden, switched on from the same page when ready.
- **Design:** light and calm, using the logo teal.
  - Accent `#08707C`, ink `#0C142B`, IBM Plex Sans.
  - Dark navy only behind the demo and the footer.
- **Languages:** English plus full French on `/fr/` URLs, with a native review.

### Proof
**Homepage section "One system for every moving part"**, organised by business domain:

| Domain | Proof |
|---|---|
| **Human Capital** | MyCity: attendance, performance, HR cases, letters; payroll arrives in December. This is the evidence for HR firms. |
| **Money** | Payment gateways and mobile money, reconciliation (Orika Living, Pixie Girl, nfelia); OHADA accounting (Smart Logistics / Praxis LS); IFRS (Pixie Girl). |
| **Operations** | Stock, fleet, waybills, freight (FMCG distributor, Smart Logistics). |
| **Field & assets** | Rig telemetry and automated daily drilling reports (oil & gas services company); verified field visits (pharma field force, with Tales Consulting). |

Under it: outcome-titled stories and a **Customers** page. Industries sit one level down: Oil & gas, HR & staffing, Pharma, FMCG & retail, Logistics. Smart Logistics is logistics, not oil & gas.

**Naming:**
- **Named:**
  - Smart Logistics and Services (Praxis LS)
  - MyCity App
  - Pixie Girl Global (Pixie Girl Hub)
  - Orika Living (hub-system)
  - nfelia, under "Our products"
- **Described, not named:**
  - Fidson: "a leading pharmaceutical manufacturer, in collaboration with Tales Consulting"
  - LPC: "an FMCG distributor in Cameroon"
- **Horizon Segment: not decided yet.** Their site is suspended.
  - My recommendation: describe them as "an oil & gas services company" until you confirm they can be named and reached.
  - The work: real-time rig operations tracking; automated DDRs; telemetry intake with cryptographic dual-signature ticket validation; a live IOC procurement pipeline with compliance scoring; live surface telemetry (hookload, top-drive RPM, standpipe pressure).
  - **The code isn't on this PC, so screenshots must come from you.**

**Flagship case studies:** FMCG distributor (LPC), pharma field force (with Tales), oil & gas services (Horizon), Smart Logistics.

### Demo video
- **D1, what's on screen:** the MyCity build with a fictional company (e.g. "Ndali Group"), on a separate demo copy. Never MyCity staging, which holds real staff and sends real email.
- **D2, the story:** "A morning with the COO", using only features that exist today:
  1. who hasn't clocked in at the depot
  2. approvals waiting on me → approve
  3. a junior account is refused salary data (enforced in code)
  4. "book a stock-count review tomorrow at 10" opens a pre-filled form → Save
  5. the mailbox summarises a supplier thread and drafts a reply
  6. the audit log, and AI spend capped in naira
  7. end card: "Ask your company anything" · Book a free audit & demo
- **D3, sound:** a silent captioned loop in the hero, and the narrated version on click.
  - English voice: smooth, clear, African (Nigerian) English.
  - French version: French voice, with on-screen text kept in English.
- **D4, versions and hosting:** a 60-second hero cut plus a 3-minute walkthrough. Self-hosted on the site, plus a JBS Praxis YouTube channel (you create the channel).
- **Voice:** VoiceStudio is installed (`C:\Program Files\VoiceStudio`).
  - Its default engine (OmniVoice) is **CC-BY-NC, non-commercial: don't use it for this**.
  - VoxCPM2 and CosyVoice 3 are Apache-2.0 by their model cards, but VoiceStudio marks all engines as not yet cleared, so check the cards first.
  - An authentic African accent needs a 20–30 second recording from a consenting speaker (a team member or a hired voice artist) to clone.
  - The alternative is Azure Neural TTS en-NG voices, licensed for commercial use.
  - Generate one clip at a time with the laptop plugged in.

### Revised plan
| Step | Scope |
|---|---|
| **Hotfix (today)** | U2 HQ access (switch on the role check and add per-screen checks), U3 keys to `.env` plus AI chat throttle and server-side history, U4 CVs and receipts to private storage. **You:** U1 (make the repo private, rotate the 2 Google keys) and rotating the website's Gemini keys. |
| **PR1: Safety and lead plumbing** | The rest of SEC and BUG; remove certificates, SLA and "VERIFIED"; contact confirmation and internal alert; queue worker; cron and heartbeat; Cloudflare proxy, compression and caches; HQ link and sitemap. |
| **PR2: Foundation** | Design tokens and one stylesheet (light, teal, Plex); new header and footer (Platform · Customers · How we work · Company · **Book a free audit & demo**); **admin "Website settings"** (company facts, contact and WhatsApp numbers, leadership toggle); SEO plumbing ported from nfelia; `/fr/` URLs; GA4 and consent; accessibility base. |
| **PR3: Homepage, demo and booking** | Hero "Ask your company anything"; the 60-second demo (loop plus narrated, EN/FR); "One system for every moving part"; outcome stories and logos; one booking flow with WhatsApp. |
| **PR4: Proof and content** | Customers (4 flagship stories plus cards); Industries (5); Platform / Praxis AI with the 3-minute walkthrough; How we work; Security & data (aligned with ISO 27001); Company (facts from settings, hidden leadership, "Our products: nfelia"); Insights cleanup; privacy rewrite; full French. |
