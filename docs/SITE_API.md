# Site API: how jbspraxis.com talks to HQ

Agreed 30 October 2026 (PR2b). The marketing site (`praxis_site`, its own Laravel app) has **no database
password**. Everything it reads or writes goes through this private API on `hq.jbspraxis.com`, served by
`praxis_core`. The live marketing pages in `praxis_core` keep running until PR3 swaps the domain, so every
write endpoint calls the **same service class** the current Livewire page calls: one behaviour, two callers.

Preview of the new app: `next.jbspraxis.com` (noindex). Live switch: PR3.

## 1. Transport and authentication

- Base URL: `https://hq.jbspraxis.com/api/site/v1` (locally `http://hq.localhost:8001/api/site/v1`).
- Only the HQ host answers; the routes live in `routes/site-api.php`, registered in `bootstrap/app.php` on
  `Hosts::hq()`, with no session, cookies or CSRF (stateless `api` middleware group).
- Every request is signed with HMAC-SHA256. Headers:
  - `X-Site-Key`: key id (`SITE_API_KEY_ID`).
  - `X-Site-Timestamp`: Unix seconds. Rejected if more than 300 s from server time.
  - `X-Site-Nonce`: 16+ random hex chars. Each nonce is accepted once (cached 10 min); a replay is rejected.
  - `X-Site-Visitor-Ip`: the visitor's IP as the site saw it (used for rate limits and records, never trusted for auth).
  - `X-Site-Locale`: `en` or `fr`. Messages and emails follow it (`MailLocale::forVisitor` semantics).
  - `X-Site-Signature`: lowercase hex of `hmac_sha256(secret, canonical)` where `canonical` is these lines joined by `\n`:
    `METHOD`, `path with query exactly as sent` (e.g. `/api/site/v1/booking/slots?month=2026-11`), timestamp, nonce,
    visitor IP, locale, `sha256_hex(raw body)` (empty body: hash of the empty string).
- Test vector (both sides must reproduce it in a unit test): secret `test-secret`, `POST`,
  path `/api/site/v1/enquiries`, timestamp `1700000000`, nonce `abcdef0123456789`, visitor IP `203.0.113.5`,
  locale `en`, body `{"email":"a@b.co"}` (body hash `9815d6fc0ef9ca98be55b9208af405483e24ba8739309bb7cf77d4bd74747b84`)
  → signature `b86a3e6839e1de593ea300468dbf8aec50b9d088b900003c1e14f2bd20e08028`.
- Multipart requests (`multipart/form-data`, only `POST /careers/applications`): PHP never sees the raw body, so the
  body line is `sha256_hex` of the form instead: one line per field, sorted by field name (byte order), `name=value`
  for a text field (value exactly as sent) and `name=sha256:<sha256_hex of the file bytes>` for a file, joined by `\n`.
  Text fields must be scalar. Test vector: fields `full_name` = `Ada Test`, `email` = `a@b.co`, file `cv` whose bytes
  are `%PDF-1.4\n` (9 bytes; file hash `e5c62df5dab5c87b6a015ef3d43597074d1eec433b15f51aec63b8582d0e4ab4`), so the
  lines are `cv=sha256:e5c6…4ab4`, `email=a@b.co`, `full_name=Ada Test` → body hash
  `465f76a4d49b8f49569fca15df9d1169d138a6f97b2f938f27dba080adee20e8`; with the other values of the vector above and
  path `/api/site/v1/careers/applications` → signature `4d4e1d5cb648a795b896af9e04a3f665a6291134d5fae29e6bcaf205cdbf523a`.
- The nonce is 16 to 128 hex characters; the visitor IP must be a valid IPv4 or IPv6 address. Inputs reach HQ exactly
  as sent (no trimming, empty strings stay empty), as the old Livewire forms' inputs do.
- Comparison with `hash_equals`. Secrets: `SITE_API_SECRET` in both `.env` files (never in git, never logged).
  Config in `config/site_api.php`; keys can be rotated by allowing a list `id => secret`.
- Failure: `401 {"error":{"code":"unauthorised"}}` with no detail about which check failed.
- Rate limits (per visitor IP, plus a global cap per key): reads 120/min; enquiries, bookings, assessments,
  careers, Kaizen submissions, feedback and whitepaper 5/min and 30/hour; chat 20/min.
  Details: each form has its own 5/min and 30/hour per visitor IP; the tracking endpoints count as reads. Global caps
  per key, all visitors together, per minute: reads 1200, forms 60, chat 120 (`SITE_API_GLOBAL_READS_PER_MINUTE`,
  `SITE_API_GLOBAL_WRITES_PER_MINUTE`, `SITE_API_GLOBAL_CHATS_PER_MINUTE`). Requests that fail authentication are
  not counted. Chat also shares the AI budget of the old chat widget (10 questions per 10 min and 40 a day per visitor
  IP, 20 questions per conversation): past it the reply is a short "limit reached" text with `"limited":true` (200), not a 429.

## 2. Envelope

- Success: `200` or `201` with `{"data": ...}`.
- Validation: `422 {"error":{"code":"invalid","message":"<summary in locale>","fields":{"email":["<message in locale>"]}}}`.
  Field names are the JSON names below, so the site maps them straight onto its form.
- Not found: `404 {"error":{"code":"not_found"}}`. Too many: `429 {"error":{"code":"too_many","retry_after":N}}`.
- Server error: `500 {"error":{"code":"server"}}`, reported to System Health; never a stack trace.
- Also: `409 {"error":{"code":"slot_taken","message":"<in locale>"}}` (bookings), `413 {"error":{"code":"too_large"}}`
  (upload over the server's post size), `503 {"error":{"code":"unavailable"}}` (HQ in maintenance). An unknown path
  under `/api/site/v1` on HQ is `404 not_found`. Every error response has `Cache-Control: no-store`.
- Validation messages: English ones are Laravel's, as on the old pages ("The full name field is required.");
  French ones are translated with French field names ("Le champ nom complet est obligatoire."). The old pages' few
  custom messages (careers, Kaizen word limit) are given in plain words in both languages. The summary is
  "Check the highlighted fields and try again." / « Vérifiez les champs signalés et réessayez. »
- JSON uses snake_case. Dates ISO 8601 with offset; booking times are Africa/Lagos wall-clock (`10:00`).
- Responses that may be cached say so with `Cache-Control: max-age=N`; the site caches reads (settings 5 min,
  portfolio and Kaizen 10 min) and serves the last good copy if HQ is down.

## 3. Endpoints

| Method and path | Purpose | Replaces in praxis_core |
|---|---|---|
| `GET /settings` | Public Website settings (section 4) | hard-coded footer, contact, about |
| `POST /enquiries` | Contact form | `ContactHub::transmitIntelligence` |
| `GET /booking/slots?month=YYYY-MM` | Bookable days and free times | `ScheduleConsultation::generateCalendar/loadTimeSlots` |
| `POST /bookings` | Book a free audit | `ScheduleConsultation::processBooking` |
| `GET /calculator/reference` | Industries, current systems, bottlenecks, currencies with rates | `ConversionHub::mount` lists |
| `POST /calculator/assessments` | Run the cost calculator, store it, email the report | `ConversionHub::generateCopqReport` (+ `dispatchReportToEmail`) |
| `GET /reports/{token}` | Data for the cost report page (`/dossier/{token}` on the site) | `dossier.show` route |
| `GET /kaizen/articles?page=N&category=` | Published articles, newest first | `Kaizen` list |
| `GET /kaizen/articles/{slug}` | One published article | `KaizenArticleView` |
| `POST /kaizen/submissions` | Contributor submits an article for review | `Kaizen::submitInsight` |
| `POST /feedback` | Site feedback form | `TelemetryNode::transmitTelemetry` |
| `GET /portfolio` | Published case studies and quotes | `Portfolio` |
| `POST /careers/applications` | Application, multipart with `cv` (PDF/DOC/DOCX, 5 MB), stored on the private disk | `Careers::transmitDossier` |
| `POST /whitepaper` | Method PDF request by email | `Services::processWhitepaper/dispatchEmail` |
| `POST /chat` | One chat turn: `{"history":[{"role":"user|assistant","text":"..."}], "message":"..."}` → `{"reply":"..."}` | `AiAgent` (keys stay in HQ) |
| `GET /tracking/open/{campaign_id}/{user_id}` | Broadcast open pixel bookkeeping (site returns the pixel) | `tracking.open` |
| `POST /tracking/unsubscribe/{user_id}` | Confirm unsubscribe | `tracking.unsubscribe.confirm` |

Request fields (JSON, snake_case of today's Livewire properties; rules exactly as today unless noted):

- `POST /enquiries`: `full_name`*, `email`*, `company_name`*, `message`*, `title`, `country_code`, `phone`, `city`,
  `language`, `budget`, and the diagnostic answers `stage`, `bottleneck`, `other_bottleneck`, `tech_reality`, `gov_status`.
  → `201 {"data":{"received":true}}`.
- `POST /bookings`: `full_name`*, `title`, `email`*, `phone`*, `enterprise_name`*, `focus`*, `custom_focus` (required if focus = Other),
  `date`* (`Y-m-d`), `time`* (one of the slots). → `201 {"data":{"start":"…","focus":"…"}}`; `409 {"error":{"code":"slot_taken"}}` if gone.
- `GET /booking/slots`: `→ {"data":{"month":"2026-11","days":[{"date":"2026-11-03","slots":["10:00","14:00"]}],"timezone":"Africa/Lagos"}}`.
- `POST /calculator/assessments`: `email`*, `name`, `company`, `industry`, `tool`, `bottleneck`, `acv`, `headcount`,
  `mgmt_nodes`, `turnover`, `manual_hrs`, `hourly_cost`, `error_rate`, `currency`.
  → `201 {"data":{"token":"…","report_path":"/dossier/<token>", "figures":{…as the page shows them…}}}`.
- `POST /kaizen/submissions`: `full_name`*, `title`*, `email`*, `linkedin_url`* (URL), `category`* (`The Boardroom` or
  `The Server Room`), `article_title`*, `exec_summary`* (50 words at most), `vulnerability`*, `solution`*, `roi`*.
- `POST /feedback`: `identity`, `email`, `category`*, `severity` (1–3, default 1), `description`* (10–5000).
- `POST /careers/applications` (multipart): `full_name`*, `email`*, `country_code` (default `+234`), `phone`*, `city`*,
  `primary_skill`*, `other_skill` (required if primary_skill = other), `secondary_skillset`*, `linkedin_url`* (URL),
  `portfolio_url`, `praxis_answer`*, `cv`*.
- `POST /whitepaper`: `email`*.
- `POST /chat`: `message`* (max 1500), `history` (max 20 turns, each text max 1500).

`*` = required. Exact lengths and rules come from the current Livewire `validate()` calls.

Details (PR2b API, as built; the rules live once in `app/Services/Site/*` and the old Livewire pages use the same):

- `POST /enquiries`: optional `diagnostic` (boolean). When it is absent, the diagnostic rules apply if any diagnostic
  answer is sent (then `stage`* and `bottleneck`*, and `other_bottleneck`* when `bottleneck` = `other`). A missing or
  empty `country_code` is `+234` and `language` is the page language (`English (EN)` / `Français (FR)`), as on the old form.
- `GET /booking/slots`: `month` defaults to the current Lagos month. `days` lists only bookable days (weekdays from
  today on), each with its free times (an empty list when fully booked); a past month has `"days":[]`.
- `POST /bookings` → `start` is ISO 8601 with the Lagos offset, e.g. `"2026-11-03T10:30:00+01:00"`.
- `POST /calculator/assessments` → `201 {"data":{"token":"PRX-…","report_path":"/dossier/<token>","emailed":true,"figures":{…}}}`.
  `emailed` is false when the email could not be sent (the report link still works). `figures`: `currency`, `total`,
  `appraisal`, `internal_failure`, `external_failure`, `opportunity`, `monthly`, `staff_turnover_per_year`,
  `replacement_cost`, `break_even_months`, `error_rate`, `error_floor`, `severity_ratio`, `risk_probability`,
  `risk_level` (`normal`, `elevated`, `critical`): the arithmetic of `copq-report.blade.php`.
- `GET /reports/{token}` → `{"data":{"token","created_at","name","company","industry","tool","bottleneck","currency",
  "inputs":{"turnover","headcount","mgmt_nodes","manual_hrs","hourly_cost","error_rate","acv"},"analysis",
  "industry_profile":{…the industry's report texts and error_floor…},"tool_profile":{"key","display_name",
  "symptom_title","intervention_strategy"},"rate_to_usd","figures":{…as above…}}}`. No internal ids, no email
  address; a deleted assessment is `404`. Cacheable 5 min.
- `GET /calculator/reference` → `{"industries":[{"name","archetype"}],"tools":[{"key","name"}],"bottlenecks":[{"name"}],
  "currencies":[{"code","name","rate_to_usd"}]}` (cacheable 10 min).
- `GET /kaizen/articles` → `{"articles":[{"slug","category","title","summary","read_time_minutes","author","designation",
  "published_at","created_at"}],"page","per_page":12,"total","last_page"}`; `category` is `The Boardroom` or
  `The Server Room`. `GET /kaizen/articles/{slug}` adds `problem`, `solution`, `roi`, `meta_description` and
  `html` (`summary`, `problem`, `solution`, `roi`: escaped, `**bold**` as `<strong>`, key terms linked as on the old page).
- `POST /enquiries`, `/kaizen/submissions`, `/feedback`, `/careers/applications` → `201 {"data":{"received":true}}`.
- `POST /whitepaper` → `201 {"data":{"sent":true}}` (`false` when the email could not be sent; the request is recorded).
  The email attaches the Praxis Method guide in the `X-Site-Locale` language (`praxis_core/resources/pdf`).
- `POST /chat` → `{"data":{"reply":"…","limited":false}}`. `history` roles are `user` or `assistant`; the AI sees the last
  12 turns. When no AI provider answers, `reply` is a short "unavailable" text in the locale. `reply` is plain text
  with the old chat widget's light markdown (the AI's answers and HQ's own fallback and limit texts use it):
  `**bold**`, `[text](https://…)` links and lines starting `- ` or `* ` as bullets. The site escapes the text and
  renders only that subset (`App\Support\ChatText` on the server, the same rules in `resources/js/app.js`).
- `GET /portfolio` → `{"case_studies":[{"client_pseudonym","title","industry","challenge","audit","architecture_deployed",
  "roi_metrics":{"metric","description","tags":[]}}],"quotes":[{"quote","source"}]}` (cacheable 10 min).
- `GET /tracking/open/{campaign_id}/{user_id}?country=NG`: `country` (optional) is the visitor's country if the site
  knows it (Cloudflare `CF-IPCountry`), else `Unknown Location` is stored; the visitor IP is stored as the open IP.
  → `{"data":{"recorded":true|false}}`. `POST /tracking/unsubscribe/{user_id}` → `{"data":{"unsubscribed":true}}`.

## 4. Website settings (`GET /settings`)

Stored in `website_settings` (one row per key, JSON value), edited in **HQ → Marketing → Website settings**
(module `website`: view, edit). Changes are written to the audit log. Only the public subset is returned:

```json
{"data":{
  "company":{"legal_name":"JBS Praxis LLC","trading_name":"JBS Praxis","founded":null,
    "registrations":[{"country":"NG","label":"CAC","number":null,"status":"pending"},{"country":"CM","label":"RCCM","number":null,"status":"pending"}],
    "offices":[{"city":"Lagos","country":"NG","address":null},{"city":"Douala","country":"CM","address":null}]},
  "contact":{"email":"info@jbspraxis.com","phones":[{"label":"Nigeria","number":null}],"whatsapp":[{"label":"Nigeria","number":null}],"hours":null},
  "social":{"linkedin":"https://www.linkedin.com/company/jbspraxis","x":"https://x.com/jbspraxis","youtube":"https://www.youtube.com/@jbspraxis"},
  "leadership":{"show":false,"people":[]},
  "updated_at":"…"}}
```

When `leadership.show` is false, `people` is returned empty. A registration with `status` `pending` is shown
as "registration in progress", never as a number. Nothing in settings names a person unless leadership is shown.

Details (PR2b settings screen): the `data` object is exactly `App\Support\WebsiteSettings::publicPayload()`.
A person in `people` is `{"name":"…","title":"…","bio_en":"…"|null,"bio_fr":"…"|null,"linkedin":"https://…"|null,"photo_url":"https://…"|null}`.
A pending registration always has `"number":null`. Any text or link may be `null` (a link left empty in HQ); phone and
WhatsApp `number` stay `null` until the owner adds one, so the site skips lines without a number. `country` is an
ISO 3166-1 alpha-2 code. `updated_at` is `null` until the settings are first saved.

## 5. The site app (`praxis_site`)

- Laravel 12, no database (`SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`), Blade, the brand
  stylesheet (same tokens as `praxis_core/resources/css/app.css` `@theme`), self-hosted fonts.
- `App\Support\HqApi`: the only HTTP client to HQ. Signs as above, 8 s timeout, retries GETs once, maps 422 field
  errors onto forms, caches reads with stale fallback, and on failure shows a calm message with the contact email
  from the last good settings.
- Routes keep today's public paths (`/contact`, `/schedule`, `/careers`, `/kaizen`, `/kaizen/{slug}`, `/feedback`,
  `/dossier/{token}`, `/tracking/open/...`, `/tracking/unsubscribe/...`), so links in old emails keep working after PR3.
- PR2b builds the working flows on plain brand pages; PR2c adds the header, footer, navigation, `/fr/`, SEO, GA4 and
  accessibility; PR3 and PR4 design the pages.

## 6. Revision 2 (30 October 2026, after the security and parity reviews)

**This section overrides sections 1 to 5 wherever they differ.** Owner decisions: very strict limits, "Praxis AI"
on brand with full knowledge of the site, calculator alerts reworded, a price-free "when would you like to start"
question instead of the budget.

### 6.1 Signing (replaces the canonical string and the multipart rule)

- The canonical string is these 9 lines joined by `\n`: `METHOD`, path with query exactly as sent, timestamp, nonce,
  visitor IP, locale, **key id**, **media type** (the `Content-Type` without parameters, lower case; empty when there is
  no body), body hash.
- Body hash: for JSON or an empty body, `sha256_hex(raw body)`. For `multipart/form-data`, `sha256_hex` of one line per
  field, sorted by field name (byte order), joined by `\n`: a text field is `t:<name>=<base64 of its value>`, a file is
  `f:<name>=<sha256 hex of the file bytes>`. HQ refuses (401) array or non-scalar text fields, two parts with the same
  name, and a text field named like a file field.
- HQ checks that all six `X-Site-*` headers are present and well formed **before** hashing anything; failed
  authentications are limited to 30 a minute per connecting address, then answered 429 without logging. Refusals are
  logged at most once a minute per reason.
- Test vectors (both sides keep them in a unit test; the secret is `test-secret`, key id `test-key`):
  - JSON: `POST`, `/api/site/v1/enquiries`, `1700000000`, nonce `abcdef0123456789`, IP `203.0.113.5`, locale `en`,
    key `test-key`, media type `application/json`, body `{"email":"a@b.co"}` (hash
    `9815d6fc0ef9ca98be55b9208af405483e24ba8739309bb7cf77d4bd74747b84`)
    → `7ce906b3ff5b4ecc10ee6d0b21a4c588ab73e451ecf18e93ff79118320b55948`.
  - Multipart: `POST`, `/api/site/v1/careers/applications`, `1700000000`, nonce `0123456789abcdef`, IP `2001:db8::7`,
    locale `fr`, key `test-key`, media type `multipart/form-data`, text fields `full_name=Test Person`, `email=a@b.co`,
    file `cv` with bytes `%PDF-1.4 test`. Lines: `f:cv=d663640088750cf16276d623c2588d7233f2b84b45f4b2e20832f47b16aa5618`,
    `t:email=YUBiLmNv`, `t:full_name=VGVzdCBQZXJzb24=`; body hash
    `bfc9b253f09bd08d200f77e3a7d9afaee3c0cbbf2fdd3aeb54ea92443ee5d308`
    → `23a66b9f55b39fa51646cfcd0fd23f93f4ad29ec3f34762dce03533ea84521ac`.

### 6.2 Limits (replaces the rate limits in section 1)

Every limit is enforced in HQ's shared services, on **the same limiter keys for the old Livewire pages and the API**,
so a visitor never gets two budgets. "Visitor" = the IPv4 address, or the IPv6 /64 network.

| Action | Per visitor | Per identity | Global (circuit breaker, alert in System Health) |
|---|---|---|---|
| Enquiry | 3 an hour, 6 a day | 2 a day per email | 60 an hour |
| Booking | 2 a day | 1 open (Requested) booking per email and per organisation; date at most 92 days ahead | 20 an hour |
| Calculator assessment | 3 an hour, 8 a day | 3 reports a day per email | 60 an hour |
| Kaizen submission | 2 a day | 2 a week per email | 20 an hour |
| Feedback | 3 an hour, 10 a day | — | 60 an hour |
| Careers application | 2 a day | 1 per email per 30 days; a repeat updates the existing application and adds the CV (calm 201, never a 500) | 30 an hour |
| Method PDF | 3 a day | 1 a day per email | 60 an hour |
| Praxis AI chat | 10 questions per 10 min, 30 a day | 20 questions per conversation (the site sends `question_number`) | 300 an hour, and an AI budget of 500 model calls a day (`SITE_API_AI_PER_DAY`) shared with the calculator |
| Any visitor-facing email | — | 3 a day per recipient address across all forms | 300 a day |
| Reads | 120 a minute | — | 1200 a minute |

Limits answer `429 too_many` with `retry_after` and a calm message in the locale. The numbers live in
`config/site_api.php` so the owner can tighten them without code. Visitor-typed text in emails is limited to the first
name (40 characters).

### 6.3 Errors and routes
- A catch-all route at the end of `routes/site-api.php` answers unknown paths and methods with 404 `not_found`, so
  nothing reaches `web.php` (no 419 or 500). Any other 4xx becomes 400 `bad_request`. Errors carry `Cache-Control: no-store`.
- Database errors are reported without bound values: System Health and `laravel.log` must never hold a visitor's
  personal data (store the SQL with `?` placeholders only).

### 6.4 Endpoint changes
- `POST /enquiries`: new optional `start_timing`, one of `this_quarter`, `next_quarter`, `later`, `exploring`
  (labels: "This quarter", "Next quarter", "Later this year", "Just exploring"; FR in PR2c). Stored with the lead and
  shown in HQ → Leads. The new site no longer sends `budget` (the old page still may).
- `POST /bookings`: `date` must be a bookable day within 92 days; `409 slot_taken` as before; a second open booking for
  the same email or organisation answers `409 already_booked` with a calm message.
- `POST /chat`: the request adds `question_number` (integer ≥ 1, the site's per-session counter). Response:
  `{"data":{"reply":"…","links":[{"label":"Book a free audit and demo","path":"/schedule"}],"limited":false}}`. `links`
  holds at most 3 entries, only paths from the site map in 6.5. HQ caps its total AI time on this path at 25 s.
- Writes return as soon as the data is stored; HQ sends the emails right after the response (`afterResponse`), so a
  slow mail server never makes the site report a failure.
- Tracking: HQ adds a token to new broadcast links: `t` = first 32 hex characters of
  `hmac_sha256(APP_KEY, campaign_id|user_id)`. `GET /tracking/open/{campaign}/{user}?t=…` and
  `POST /tracking/unsubscribe/{user}` with `campaign` and `t` require it for campaigns sent after this change; links in
  campaigns sent before keep working without it.
- `GET /kaizen/articles*`: no `author` or `designation` fields (the site never shows them).
- `POST /calculator/assessments`: the site says "We couldn’t email a copy" when `emailed` is false.
- Calculator staff notifications: "High-value lead from the cost calculator" and "New cost report from the website",
  with a plain message ("Calc Co ran the cost of poor quality calculator: estimated $412,200 a year."). Same on the old page.

### 6.5 Praxis AI (the website assistant)
- Name: **Praxis AI** (replaces "the Nexus" and "JBS Praxis assistant"). Voice: BRAND.md sections 4 and 5: plain,
  precise, warm; never "predictive", no prices, no certificates, no named people, no client names beyond those approved
  in BRAND.md; the call to action is "Book a free audit and demo".
- It knows the whole site: HQ keeps the site map in `config/praxis_ai.php` (path, page name, what the page answers, in
  EN and FR) and puts it in the system prompt together with the services, the Praxis Method in plain words, how an
  engagement works, and the contact details and offices from `WebsiteSettings::publicPayload()` (never hard-coded).
  Answers point to the right page with `links`.
- Site map for PR2b (PR2c and PR3 extend it): `/` (home), `/schedule` (book a free audit and demo), `/contact`,
  `/cost-calculator` (cost of poor quality calculator), `/services` (the method and its PDF), `/kaizen` (insights),
  `/kaizen/write`, `/careers`, `/feedback`; the `/fr/…` equivalents arrive in PR2c.
- The old live widget keeps its old wording until PR3 replaces the site.
- The calculator's analysis prompt is rewritten in the same voice (plain words: what it costs, what to fix first).

### 6.6 The site app (adds to section 5)
- Timeouts: reads 8 s (one retry), writes 20 s, chat 35 s; never follow redirects; `SITE_API_BASE` must be https in
  production; a POST timeout never marks HQ as down.
- Visitor IP: `TRUSTED_PROXIES` empty unless the site is behind Cloudflare, then only Cloudflare's published ranges
  (never `*`); `CF-IPCountry` and `CF-Connecting-IP` are trusted only when the connecting address is a Cloudflare one.
- Every form: CSRF, a honeypot, and at least 3 seconds between page load and submit (a signed timestamp field);
  Cloudflare Turnstile support behind `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` (off until the owner adds keys).
- Praxis AI on the site: named "Praxis AI", renders `links` as buttons, keeps a per-session question counter.
- Contact form: the optional "When would you like to start?" question replaces the budget.

### 6.7 HQ as built (clarifications of 6.1 to 6.5; nothing here relaxes them)
- Signing: a refusal is logged at `info` level, at most once a minute per reason, with the reason and path only. The
  failed-authentication allowance counts the connecting address the way the limits count visitors (IPv4 address, IPv6
  /64). PHP keeps only the last of two multipart parts with the same name, so HQ hashes and stores that one value; a
  repeat that differs from what the site signed fails the signature (401). The site never sends a repeated name.
- Limits (`App\Services\Site\SiteLimits`, numbers in `config/site_api.php` `limits`): only accepted requests are counted
  (a 422, 409 or refused request uses no allowance). Each per-visitor allowance in the old page's own window keeps that
  page's limiter key (`contact-form:`, `booking:`, `copq-report:`, `kaizen-submit:`, `feedback:`, `whitepaper-mail:`,
  `ai-agent:10m:` / `ai-agent:day:`), so the old pages and the API share one budget. Identity keys hold a hash of the
  address, never the address. A tripped global allowance writes one System health entry (source `site-limit`) per
  action and hour; the monitor's usual alert email and bell notification follow.
- Chat: the conversation count is the larger of `question_number` and the user turns in `history` plus one; the
  question is counted before the AI is asked, so a question whose answer fails still counts. Past any chat allowance
  (visitor, conversation, everyone, the day's AI calls) the answer is `200` with `"limited":true` and a link to
  `/schedule`, never a 429. Gemini gets at most 12 s and DeepSeek the rest of the 25 s.
- Bookings: a date that isn't a weekday from today up to 92 days ahead (Lagos time) is `422 invalid` on `date`
  ("Choose a weekday from today up to 3 months ahead."); the old page greys those days out.
- Emails after the response: `emailed` (assessments) and `sent` (method PDF) are `true` when the email was accepted for
  sending, `false` when it can't be sent, including when the address has had its 3 visitor emails today. A booking
  topic that isn't one of the form's own choices appears in the visitor's copy as "the topic you described".
- Tracking: the unsubscribe body is JSON `{"campaign":"…","t":"…"}`; an invalid link is `404 not_found` (the old page
  says "We couldn’t confirm this link"). `broadcast_logs.signed_links` marks emails sent with tokens; a link without
  `campaign` works only while the contact has a broadcast from before tokens.
- Stored values: a name part over 50 characters, a language over 20, a budget over 50 or a skill over 100 is shortened to
  its column when stored (the rules are unchanged), so a long value never causes a 500.
- Tests: `tests/Unit/SiteSignatureTest.php` (both vectors, no database) runs with the default `phpunit.xml`.
  `tests/Feature/SiteApiTest.php` and `tests/Feature/WebsiteSettingsScreenTest.php` need MySQL (the baseline migration
  is MySQL only) and are skipped otherwise: run them with `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307
  DB_USERNAME=root DB_PASSWORD=… DB_DATABASE=praxis_test php vendor/bin/phpunit tests/Feature/SiteApiTest.php
  tests/Feature/WebsiteSettingsScreenTest.php` on an empty throwaway database; they refuse any name that doesn't
  start with `praxis_test` or `praxis_pr`.
