# Campaigns: Broadcasts and Outreach (HQ › Campaigns)

The runbook for JBS Praxis's email marketing in HQ: what the owner decided, how it is built, the contracts the next
PRs build on, and what is still open. Built from 3 Nov 2026, when JBS Praxis hired its Head of Sales.

## Contents

1. [What Campaigns is](#1-what-campaigns-is)
2. [The PRs](#2-the-prs)
3. [Screens and permissions](#3-screens-and-permissions)
4. [How it works, and the contracts](#4-how-it-works-and-the-contracts)
5. [Data](#5-data)
6. [Where Outreach (C2) and replies (C3) plug in](#6-where-outreach-c2-and-replies-c3-plug-in)
7. [Testing and running it locally](#7-testing-and-running-it-locally)
8. [Owner decisions still open](#8-owner-decisions-still-open)
9. [Deployed](#9-deployed)
10. [Outreach (C2)](#10-outreach-c2)
11. [For C3: replies](#11-for-c3-replies)

---

## 1. What Campaigns is

One area with two tools (owner, 3 Nov 2026):

- **Broadcasts** (C1, this runbook's first PR): designed newsletters and announcements to people who already know us.
- **Outreach** (C2 and C3): AI-personalised one-to-one emails to researched prospects, sent from the salesperson's own
  jbspraxis.com mailbox with one automatic follow-up; replies sorted by AI and turned into leads.

**The owner's decisions for Broadcasts**

1. **Design:** four branded layouts (Announcement, Insight, Event invite, Case study) and a **Letter** layout that is
   just rich text; a block editor (heading, text, image, button, quote, key figures, details, divider); every text
   block is a rich-text editor (bold, italics, underline, links, lists, two heading levels); live computer and phone
   preview; a test send to yourself; **Claude drafts the text from a short brief** (it replaces the old Gemini
   "enhance").
2. **Language:** each person gets their own language. A broadcast has an English and a French version; Claude can
   translate one into the other for editing. The person's language comes from their stored preference, else their
   organisation's, else their country (French-speaking country → French).
3. **Audience:** only clients, leads and website subscribers who haven't unsubscribed, chosen through **saved
   segments**, with live counts. **Outreach prospects never receive broadcasts. One unsubscribe stops all marketing to
   that address everywhere.**
4. **Website subscribers** (owner, 3 Nov 2026, asked during C1): a subscriber list kept in HQ (sales add or paste
   people, with how they agreed); **people who wrote for JBS Praxis Insights (Kaizen) through the website count as
   subscribers too**. A sign-up form on the website comes later, once counsel has confirmed the consent wording.

## 2. The PRs

| PR | Branch | What | Status |
|---|---|---|---|
| **C1 Broadcasts** | `campaigns/c1-broadcasts` | The Campaigns area; Broadcasts (editor, layouts, blocks, rich text, AI draft and translation, EN/FR, preview, test send, send now or schedule, report); segments, subscribers and the **do-not-email list** with its contract; one-click unsubscribe; counted opens and clicks; this runbook | In review |
| C2 Outreach | `campaigns/c2-outreach` | Import researched prospects (the shared Excel engine), Claude's drafts, the review table and its dialog, sending from the salesperson's own mailbox within owner-editable limits, one automatic follow-up about 3 working days later; section 10 | In review |
| C3 Outreach replies | — | Replies sorted by AI (Interested, Not now, Not interested, Unsubscribe), one-click Make a lead, Not-now reminders, campaign numbers | Not started |

## 3. Screens and permissions

The rail has a **Campaigns** entry (icon `send`) after Leads and proposals, for anyone with `broadcasts.view` or
`outreach.view`. Its sub-links are **Broadcasts** and **Audiences** (`broadcasts.view`) and **Outreach** (`outreach.view`,
section 10). Broadcasts used to sit under
Leads and proposals at `/intelligence/broadcast-ledger`: that address (and the bells that point at it) redirects to
`/campaigns/broadcasts`. Marketing's own sub-link "Campaigns" (paid ads, `hq.marketing.campaigns`) is now called **Ad
campaigns** so the two don't read the same.

| Address | Route | Screen |
|---|---|---|
| `/campaigns` | `hq.campaigns` | redirects to Broadcasts |
| `/campaigns/broadcasts` | `hq.campaigns.broadcasts` | every broadcast with its numbers; **New broadcast** (choose a layout) |
| `/campaigns/broadcasts/{id}` | `hq.campaigns.broadcast` | a draft in three steps: **Write**, **Audience**, **Test and send**; read-only while scheduled |
| `/campaigns/broadcasts/{id}/report` | `hq.campaigns.report` | sent, opens, clicks per link, unsubscribes, every recipient |
| `/campaigns/audiences` | `hq.campaigns.audiences` | **Segments**, **Subscribers**, **Do not email** |

Routes live in `praxis_core/routes/campaigns.php` (HQ) and `routes/email-links.php` (the portal host, section 4.5).

| Permission | Allows |
|---|---|
| `broadcasts.view` | open Campaigns, read drafts, reports, segments, subscribers and the do-not-email list |
| `broadcasts.create` | write drafts, use the AI, send a test to yourself, make and edit segments, add subscribers, add an address to the do-not-email list |
| `broadcasts.approve` | **Send now**, **Schedule**, **Unschedule**; take an address added by hand or that bounced off the do-not-email list |

The Sales & Marketing role has all three (so the Head of Sales can send); Auditor has view only.

## 4. How it works, and the contracts

### 4.1 The do-not-email list (`App\Support\Campaigns\Marketing`)

**This is the contract every marketing email obeys, Broadcasts and Outreach alike.** One row per address in
`marketing_suppressions`, whichever tool put it there.

```php
Marketing::canEmail($email): bool          // false for an invalid address or one on the list: never send
Marketing::suppress($email, $reason, ['source' => 'outreach', 'broadcast_id' => …, 'note' => …, 'by' => $staffId]): bool
Marketing::lift($email): bool              // HQ only; false for unsubscribes and spam complaints
Marketing::entry($email): ?object          // the row: reason, source, note, created_at
Marketing::normalize($email): string       // lower case, trimmed: the form stored and compared
```

- **Reasons:** `unsubscribed`, `bounced`, `complained` (marked as spam), `manual` (added in HQ, for example someone who
  asked by phone). An unsubscribe or a complaint outranks a bounce or a manual entry, and **only the person can undo
  them**: `lift()` refuses, and HQ shows no button.
- **Sources:** `broadcast`, `outreach`, `legacy_link` (an unsubscribe link in a broadcast sent before 3 Nov 2026),
  `backfill`, `hq`.
- `suppress()` also sets `client_users.is_subscribed = 0` and `marketing_subscribers.unsubscribed_at` for that address,
  so older screens agree; `lift()` undoes both.
- **Backfill:** migration `2026_11_03_700001` put every contact with `is_subscribed = 0` on the list.
- Old broadcast links on jbspraxis.com (`/tracking/unsubscribe/…`, served by the site through the site API) still
  work, and now also call `suppress()` (`App\Services\Site\TrackingService::unsubscribe`).
- Transactional email (invoices, proposals, sign-in codes, meeting invites) never asks: the list only stops marketing.

### 4.2 Audiences and segments (`Audience`, `SegmentFilters`)

A **segment** (`broadcast_segments`) is a name and its rules:

```json
{"who": ["clients", "leads", "subscribers"], "filters": {"industry": ["Banking"], "language": ["fr"]}}
```

- `Audience::recipients($rules)` gives one row per address: `email`, `first_name`, `last_name`, `company`, `kind`
  (`client`, `lead`, `kaizen`, `subscriber`), `contact_id`, `subscriber_id`, `locale`. `Audience::summary($rules)`
  gives the live numbers (total, per kind, per language, and `left_out`); `Audience::describe($rules)` the words HQ shows.
- **Who:** `clients` = contacts of organisations with status Active Client or Past Client; `leads` = status Lead;
  `subscribers` = the subscriber list **and** Kaizen writers (contacts with lifecycle Public Contributor, filed under the
  internal "Kaizen Public Contributors" organisation).
- **Always left out:** test organisations (`is_test`), JBS Praxis itself and Suspended clients, deleted or suspended
  contacts, contacts without an email. **An address that unsubscribed anywhere** (as a contact, as a subscriber, or on
  the do-not-email list) **is left out of every group**; `left_out` counts those.
- **One email per address:** when an address is in two groups, client beats lead beats Kaizen writer beats subscriber
  (it decides the footer's "why you got this").
- **Prospects are never in an audience:** Outreach keeps them in its own table (section 6), which Audience never reads.
  A prospect becomes a lead only through C3's Make a lead, and only then can receive broadcasts.
- **The audience is worked out when sending starts**, not when the segment is saved or the broadcast scheduled, so it
  reflects that moment's leads and unsubscribes. The rules used are kept on the broadcast (`audience`).

**Filters** (`SegmentFilters::definitions()`): Client status (narrows clients only), Lead stage or Lead status (narrows
leads only), Industry, Country, Language, Owner (`organizations.assigned_to`). A filter on a field a group doesn't have
leaves that group out while it is set (subscribers have no industry or owner), so a segment never reaches people it
can't vouch for. Country applies to subscribers (their own column).

- **Leads PR 1:** once it adds `organizations.lead_status` (New, Contacted, Qualified, Client, Lost), the **Lead status**
  filter replaces Lead stage by itself (the column is detected). Nothing to change here; add its `source` as a filter
  the same way if sales want it (one entry in `definitions()`: label, groups, column, options).
- **Tags** were asked "if cheap": HQ has no tags on organisations or contacts yet, so there is no tag filter. Add one
  in `definitions()` when tags exist.

**Subscribers** (`marketing_subscribers`): added in Audiences › Subscribers, one per line (`ada@example.com`,
`Ada Okafor <ada@example.com>` or `ada@example.com, Ada, Okafor, Acme Ltd`), with country, language and a required
**"How they agreed to hear from us"** note kept with each row. Addresses on the do-not-email list are skipped.
Removing a subscriber deletes the row; to make sure nobody emails them again, use the do-not-email list.

### 4.3 Language (`App\Support\MailLocale::forContact`)

`forContact($own, $organisation, $country)`: the person's preference (subscribers), else their organisation's
(`organizations.preferred_language`), else French for a French-speaking country (`MailLocale::fromCountry`: Cameroon,
Côte d’Ivoire, Senegal, Benin, Togo, Burkina Faso, Mali, Niger, Chad, Central African Republic, Gabon, both Congos,
Guinea, Equatorial Guinea, Madagascar, Burundi, Djibouti, Comoros, France, Belgium, Luxembourg, Monaco, Haiti, Morocco,
Algeria, Tunisia, Mauritania; names in English or French, or ISO codes), else English. Kaizen writers get English (their
shared organisation says nothing about them).

A broadcast is **English and French** (each person their own), **English only** or **French only** (everyone that
version; the Audience step warns how many read the other language). **Caveat:** `organizations.preferred_language`
still defaults to `'EN'`, which counts as a choice, so the country rule only applies to organisations whose language is
empty (section 8).

### 4.4 Writing a broadcast (`Layouts`, `Blocks`, `RichText`, `Renderer`, `BroadcastEditor`)

- **Layouts** (`Layouts::ALL`): Announcement, Insight, Event invite, Case study, Letter. A layout sets the small label
  above the headline (`emails.broadcast.eyebrow.*`, none for Letter), the blocks a new broadcast starts with, and what
  the AI is asked to write. Changing the layout replaces the blocks only of a version nobody has written in yet.
- **Blocks** (`Blocks::TYPES`, stored by `Blocks::clean()`): heading, text, image, button, quote, key figures (1 to 3),
  details (label and value rows, for date, time and place), divider. At most 30 per version. `Blocks::problems()` lists
  what stops a version going out, in plain words ("Button 2 needs a label and a link that starts with https://").
- **Rich text** (`RichText::clean()`): the editor's HTML is never trusted. Kept: `p`, `br`, `strong`, `em`, `u`, `a`
  (http, https or mailto only, no other attribute), `ul`, `ol`, `li`, `h2`, `h3` (b, i, div, h1 and h4 to h6 are renamed);
  scripts, styles, images, frames and forms are dropped with their content; everything else is unwrapped.
  `RichText::forEmail()` adds the inline styles. The editor script is `resources/campaigns/editor.js` (no build step).
- **Placeholders:** `{{ first_name }}`, `{{ company_name }}` and `{{ industry }}` in any text, filled per person (escaped)
  or with the language's fallback. The optional greeting is `<x-mail.greeting>`: "Hello Ada," / « Bonjour, » (French
  never greets by first name alone).
- **Pictures** (`Images`): JPG or PNG, 2 MB at most, stored in `storage/app/campaign-images/{uuid}.{ext}` and served to
  inboxes from the portal host (`/email/img/{file}`, public, cached a year). Wider than 1200 px, a picture is scaled
  down when the server has GD. An image block only accepts our own uploads, and needs a description (alt text).
- **The email** (`App\Mail\BroadcastEmail`, view `emails/campaign`, blocks in `emails/partials/broadcast-blocks`) is the
  branded layout with tables and inline styles only, through the `<x-mail.*>` components: `h2`, `image`, `figures`,
  `divider` are new, `facts`, `quote` and `button` are reused, and `layout` gained the `eyebrow` prop and a stacking
  column for phones. Its plain-text part is generated as for every email.
- **Preview:** the editor draws the email as Ada Okafor at Acme Ltd (demo) would get it, at computer (680 px) or phone
  (390 px) width, and redraws it after every change.
- **AI** (`config/ai.php`, both Claude Sonnet, through `App\Support\Ai\Ai::queue`, polled every 2 s):
  - `broadcast_draft`: one language's subject, preheader, headline and blocks from the brief (INSTRUCTIONS) and the
    layout (MATERIAL). It never sets an address: the button keeps the version's link (the free audit page by default).
    Pictures are not written by the AI.
  - `broadcast_translate`: the other version, block by block (by index), keeping pictures and links; text blocks keep
    their tags. Both replace the target version after a confirmation, and say to read it through.
- **Test send:** **Send a test to me** sends each version to the signed-in person, subject "Test: …", with plain links
  (nothing counted) and an unsubscribe link that opens a harmless preview of the page. At most 10 an hour per person.

### 4.5 Links inside broadcasts (portal host, `EmailLinkController`, `Tracking`)

Every recipient's row has a random **40-character token**; every link in their email carries it, so nobody can open,
click or unsubscribe for someone else. The links are on the portal host, with every other link sent to clients:

| Link | What it does |
|---|---|
| `GET /email/unsubscribe/{token}` | the page, in the language the email was sent in (a link switches language): "Unsubscribe from JBS Praxis news?" and **one button**. Opening it never unsubscribes: mail scanners open links on their own |
| `POST /email/unsubscribe/{token}` | the button, and the inbox's own **one-click unsubscribe** (body `List-Unsubscribe=One-Click`, RFC 8058); exempt from CSRF (`bootstrap/app.php`), the token is the proof. Calls `Marketing::suppress(…, 'unsubscribed', source broadcast)` |
| `GET /email/open/{token}.png` | the open-counting pixel (always returns the image) |
| `GET /email/click/{token}/{link}` | counts the click, then goes to the link's address. Only addresses stored for that broadcast (`broadcast_links`): an unknown token or another broadcast's link goes to the website's home page, never elsewhere |
| `GET /email/img/{file}` | pictures |

Each email carries `List-Unsubscribe: <…/email/unsubscribe/{token}>` and `List-Unsubscribe-Post:
List-Unsubscribe=One-Click`, so Gmail, Apple Mail and Outlook show their own unsubscribe button. The token `preview` is
the editor's and test emails' stand-in: its page says so and its button does nothing.

**Opens are approximate:** Apple Mail loads every image when an email arrives (Mail Privacy Protection) and some inboxes
hide images, so opens can be over- or under-counted. A click also counts as an open. Clicks and unsubscribes are exact.

### 4.6 Sending (`Broadcasts`, `broadcast:process`)

- **Draft → Send now** (or **Schedule**, then at its time) → **Sending** → **Completed**. A scheduled broadcast is
  read-only; **Unschedule** makes it a draft again. Only `Draft` can be edited or deleted.
- **Starting** (`Broadcasts::start`, claimed once so a double click or two runs can't start it twice): the segment is
  worked out, one `broadcast_logs` row per person is written (address, names, company, kind, language, token), each
  version's counted links go into `broadcast_links`, and the content is kept as it went out. Nobody left: Completed at
  once, with a bell to the sender.
- **`broadcast:process`** runs every 5 minutes (`routes/console.php`, `withoutOverlapping`):
  0. an email a run claimed but never finished (the run died mid-send: a PHP timeout, an SMTP hang cPanel killed)
     is still Sending after 15 minutes: it becomes **Failed** ("Interrupted while sending…") and is **not** sent
     again, because the first try may have reached the person; so the broadcast can still complete;
  1. scheduled broadcasts whose time has come start;
  2. the next **30** queued emails go out, oldest first, each in its person's language. Each row is claimed before
     sending (Queued → Sending), so an email can never go twice. At the last moment `Marketing::canEmail()` is asked
     again: an address listed since is **Skipped**. A permanent refusal from the mail server (550, 5.1.x, user unknown,
     an invalid address) is **Bounced** and goes on the do-not-email list; only the mailer's own errors
     (`RfcComplianceException`, Symfony's `TransportExceptionInterface`) can be a bounce, so a bug in HQ never
     unsubscribes anyone. Anything else is **Failed** and reported;
  3. broadcasts with nothing left to send are **Completed**, with a bell to whoever sent them ("Broadcast sent").
- **Pace:** 30 every 5 minutes (360 an hour) is the pace the cPanel server has always kept, as before.
- **The one-broadcast-at-a-time lock is gone.** It only protected the SMTP limit, and the sender's pace now does that
  for every broadcast together: a second broadcast queues behind the first (oldest rows first) instead of running beside
  it, so the total never goes over 30 a run. The list shows how many emails are waiting and when the last should go.
- Broadcasts written before 3 Nov 2026 (no layout, one HTML body) that are still queued go out the old way
  (`IntelligenceBroadcast`), and their reports open.
- **Bells:** "Broadcast sending" and "Broadcast scheduled" to staff with `broadcasts.approve`; "Broadcast sent" and
  "Broadcast not sent" to the sender.

### 4.7 The report (`Broadcasts::stats`, `BroadcastReport`)

Sent (of the total, per language), Opened (approximately, % of sent), Clicked (people and clicks), Unsubscribed, Not
delivered (bounced, failed, skipped), each link's people and clicks, and every recipient with their status, first
open, clicks and unsubscribe, searchable and filterable. **View the email** shows each version as sent. The report
refreshes itself every 15 seconds while sending.

## 5. Data

| Table | What | Key columns |
|---|---|---|
| `marketing_suppressions` | the do-not-email list | `email` (unique, lower case), `reason`, `source`, `broadcast_id`, `note`, `created_by` |
| `marketing_subscribers` | the subscriber list | `email` (unique), names, `organisation`, `country`, `language` (empty: from the country), `source` (hq; website later), `consent`, `unsubscribed_at` |
| `broadcast_segments` | saved audiences | `id` (uuid), `name`, `rules` (json) |
| `broadcast_campaigns` | a broadcast (+ C1) | `layout` (null: before 3 Nov 2026), `content` (`{"en": {subject, preheader, headline, blocks}, "fr": {…}}`), `language` (`both`, `en`, `fr`), `greeting`, `segment_id`, `audience`, `status` (Draft, Scheduled, Sending, Completed, Cancelled), `scheduled_at`, `started_at`, `completed_at`, `sent_by` |
| `broadcast_logs` | one recipient (+ C1) | `user_id` (now nullable), `subscriber_id`, `kind`, `email`, `first_name`, `last_name`, `company`, `locale`, `token` (unique), `status` (+ Sending, Skipped, Bounced), `sent_at`, `first_opened_at`, `click_count`, `first_clicked_at`, `unsubscribed_at`; unique (`campaign_id`, `email`) |
| `broadcast_links` | a counted address | `campaign_id`, `locale`, `url`, `clicks` |
| `broadcast_clicks` | one click | `campaign_id`, `log_id`, `link_id`, `created_at` |

Migrations: `2026_11_03_700000_create_campaigns_broadcasts.php` (tables and columns) and `2026_11_03_700001` (backfill).

## 6. Where Outreach (C2) and replies (C3) plug in

**C2, Outreach**

- **Navigation:** add the **Outreach** link in the Campaigns rail block of `components/layouts/hq.blade.php` (after
  Audiences), and show the Campaigns entry for `outreach.view` too (`@canany(['broadcasts.view', 'outreach.view'])`).
  Its routes go in their own file, `routes/campaigns/outreach.php`, required from `routes/campaigns.php` (route names
  `hq.campaigns.outreach.*`, so the rail's `hq.campaigns.*` highlight works). New permissions `outreach.view`,
  `outreach.create`, `outreach.approve` in `config/access.php` with a grant migration.
- **Prospects live in their own table** (for example `outreach_prospects`), never in `client_users` or
  `marketing_subscribers`, so `Audience` can never reach them.
- **Before every outreach email and follow-up:** `if (! Marketing::canEmail($email)) { skip }`.
- **Every outreach email** needs an unsubscribe line and a `List-Unsubscribe` header too. Either give outreach rows a
  token and add `GET|POST /email/unsubscribe/o/{token}` beside the broadcast ones (same page, same one-click POST, CSRF
  already exempt for `email/unsubscribe/*`), or reuse `EmailLinkController` with a lookup that also searches outreach
  rows. Either way it ends in `Marketing::suppress($email, 'unsubscribed', ['source' => 'outreach'])`.
- A bounce while sending: `Marketing::suppress($email, 'bounced', ['source' => 'outreach'])` (the broadcast sender's
  `ProcessBroadcastQueue::isPermanent()` tells a permanent refusal from a temporary one).
- The import engine is Leads PR 3's; the language column fills from `MailLocale::fromCountry()`.

**C3, replies**

- A reply sorted **Unsubscribe**: `Marketing::suppress($from, 'unsubscribed', ['source' => 'outreach'])`. A spam
  complaint: `'complained'`. Bounce notices (DSN emails) that reach the mailbox: `'bounced'`.
- **Make a lead** creates the organisation and contact: from then on they are a lead and can receive broadcasts, unless
  their address is on the list.
- Campaign numbers for Outreach live with Outreach; Broadcasts' report is `Broadcasts::stats()`.

## 7. Testing and running it locally

- **Tests** (`praxis_core/tests/Feature/Campaigns/`, MySQL, `UsesThrowawayDatabase`):
  `MarketingSuppressionTest` (the contract, backfill, old links), `AudienceTest` (groups, exclusions, one per address,
  filters, language, the Lead status switch), `BroadcastContentTest` (rich text, blocks, layouts, the email in EN and
  FR, every sample), `BroadcastEditorTest` (the area, permissions, editor, AI draft and translation with `FakeAi`, test
  send, send now, schedule, segments), `BroadcastSendingTest` (each person's language, headers, counted links, 30 a run,
  last-moment check, bounces, old broadcasts, the report), `EmailLinksTest` (the unsubscribe page in both languages,
  one-click POST, unknown and preview links, clicks, opens, pictures).

  ```
  DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_PASSWORD= DB_DATABASE=praxis_test_camp1 MAIL_MAILER=array \
    php vendor/bin/phpunit tests/Feature/Campaigns
  ```
- **Samples:** `php artisan app:email-samples --only=broadcastemail` renders every layout in both languages.
- **Look at it:** a local server (`APP_URL=http://hq.localhost:8119`, `MAIL_MAILER=log`), signed in as a fictional
  staff member. Never send a real broadcast from a local copy; test sends go to a fictional address.

## 8. Owner decisions still open

1. **Counsel** to confirm the consent wording: the footer's "why you got this" lines (`emails.broadcast.reasons.*`),
   the unsubscribe page (`lang/*/marketing.php`), and the "How they agreed to hear from us" note for subscribers. The
   website sign-up form waits for it.
2. **`organizations.preferred_language` defaults to `'EN'`**, which reads as a choice, so Cameroonian leads created in
   HQ get English unless someone changes it. Changing the default to empty (so the country decides) is the owner's call.
3. **Bounces after delivery:** SMTP2GO accepts every email and reports later bounces and spam complaints by email or
   webhook, which HQ doesn't read yet; only refusals while sending are caught. A webhook or a bounce mailbox read by C3
   would call `Marketing::suppress(…, 'bounced' | 'complained')`.
4. **How often one person may receive broadcasts:** there is no limit today (sales decide by segment).
5. **Suspended clients** (access on hold) are never in an audience; say if they should be.
6. **Outreach (C2), counsel and the owner:** the cold-email wording (section 10.9): the unsubscribe line in every
   outreach email (`lang/*/outreach.php`) and its page (`marketing.outreach_unsubscribe`), and whether cold email to
   business addresses in Nigeria and Cameroon needs anything more (for example a line on where the address came from).

## 9. Deployed

Not yet: C1 is in review.

## 10. Outreach (C2)

One-to-one cold email to researched prospects, written by Claude, read by a person, and sent from the salesperson's
own jbspraxis.com mailbox, a few a day, with exactly one follow-up. Built 3–4 Nov 2026 on the contract in section 6.

### 10.1 The owner's decisions (3 Nov 2026, binding)

1. **Sender:** each salesperson's own mailbox as connected in HQ Mail (a personal `mail_accounts` row), never a second
   domain. Without one, the screens say how to connect it (Mail › Mailboxes › Your mailbox).
2. **Limits the owner edits:** 15 a day per sender for their first 2 weeks, then 30; weekdays 08:30–17:30 WAT only; at
   least 4 minutes apart; follow-ups count toward the limit. **Auto-pause** a sender when more than 5% of that day's
   emails bounced, or on any spam complaint, with a bell to them and the owner.
3. **Review:** a clean table of drafts; a row opens a small dialog with the full email, where you **Approve**, **Edit**
   (rich text), **Rewrite** (Claude again, optionally with a note) or **Skip**. **"Approve the rest" unlocks only after
   10 approved by hand** in that campaign.
4. **Follow-up:** exactly one, written by Claude, sent about 3 working days later within the window; cancelled if they
   reply, bounce or unsubscribe. A reply is found in HQ Mail (mail from their address, or in the same thread).
5. **Template:** required company, contact first name, email, country, industry, **plus at least one of "What we
   found" or company website**; optional last name, job title, language. Sheet 1 is a guide with good and bad examples.
6. **Language per person:** from the country (`MailLocale::fromCountry()`, French-speaking → French), editable in the
   Language column; both emails are written in it.
7. **Writing:** core + one insight, from "What we found", the company website when given, and JBS Praxis's services.
   Website reading must be safe (10.5).
8. Prospects never get broadcasts; one unsubscribe stops all marketing.

### 10.2 Screens, routes and permissions

Routes in `praxis_core/routes/campaigns/outreach.php` (required from `routes/campaigns.php`, outside the Broadcasts
group, so Outreach opens without `broadcasts.view`):

| Address | Route | Screen |
|---|---|---|
| `/campaigns/outreach` | `hq.campaigns.outreach` | every campaign with its numbers; **Your sending** (mailbox, today's count and limit, a pause); **New campaign** |
| `/campaigns/outreach/{campaign}` | `hq.campaigns.outreach.campaign` | the numbers, the prospects table (To review, Not ready, Approved, Sent, Not sent, All; search), **Import prospects**, the review dialog, **Approve the rest**, Edit details, Pause / Resume, Archive |
| `/campaigns/outreach/limits` | `hq.campaigns.outreach.limits` | **Sending limits** (outreach.approve) and every sender's day, with **Resume** for one that paused |
| `GET/POST /email/unsubscribe/o/{token}` (portal host) | `email.unsubscribe.outreach(.confirm)` | the unsubscribe page and one-click POST for an outreach email (section 4.5's page, outreach wording) |

| Permission | Allows | Who has it |
|---|---|---|
| `outreach.view` | open Outreach, read campaigns, prospects, drafts and numbers | Sales & Marketing, Partner, Auditor, Owner |
| `outreach.create` | make campaigns (sent from your own mailbox), import prospects, review the drafts of campaigns you own or send | Sales & Marketing, Partner, Owner |
| `outreach.approve` | the Sending limits, Resume a paused sender, review any campaign, make a campaign for another sender | Owner only (tick it on Roles and permissions for others) |

Grants for existing databases: migration `2026_11_03_800001_grant_outreach_permissions`. Reviewing a campaign needs
`outreach.create` **and** being its owner or sender (or `outreach.approve`): another salesperson can read it but not
approve emails that go out under someone else's name.

### 10.3 Data (migration `2026_11_03_800000_create_campaigns_outreach`)

| Table | What | Key columns |
|---|---|---|
| `outreach_campaigns` | a list of prospects | `id` uuid, `name`, `brief` ("What to offer", read by Claude), `owner_id`, `sender_id` (whose mailbox sends), `status` (active, paused, archived) |
| `outreach_prospects` | the people (never `client_users` or `marketing_subscribers`) | `campaign_id`, `company`, `first_name`, `last_name`, `job_title`, `email` (**unique across all campaigns**), `country`, `industry`, `language`, `findings`, `website`, `website_text` / `website_note` / `website_read_at`, `status` (new, emailed, replied, bounced, unsubscribed, skipped), `replied_at`, `bounced_at`, `unsubscribed_at`, `complained_at`, `dedupe_key` |
| `outreach_emails` | one email: `kind` first or follow_up (one of each per prospect) | `status` (below), `subject`, `body_html` (rich text, cleaned), `insight` (Claude's note for the reviewer), `ai_run_id`, `ai_error`, `rewrite_note`, `edited`, `approved_by` / `approved_at` / `approval` (hand, bulk), `send_after`, `attempts`, `sender_id`, `mail_account_id`, `tried_at` (counts toward the day), `sent_at`, `message_id`, `token` (unsubscribe, 40 characters), `note` |
| `outreach_senders` | each salesperson's sending state | `user_id`, `first_sent_at` (the first weeks run from here), `paused_at`, `paused_reason` (bounces, complaint, mailbox), `paused_note`, `resumed_by` / `resumed_at` |
| `outreach_settings` | the owner's limits, one row per changed key | `key`, `value`, `updated_by` |

**Email statuses:** `writing` (waiting for Claude) → `draft` (to review) → `approved` → `sending` → `sent`; a
follow-up goes `approved` → `scheduled` (`send_after` set when the first email went) → `sending` → `sent`. Also
`not_written` (Claude couldn't: `ai_error` says why; **Try again**, or **Write it myself**), `skipped` (a person, or the
address was on the list at the last moment), `bounced`, `failed` (not sent again) and `cancelled` (a follow-up stopped:
they replied, bounced or unsubscribed, or the first didn't go).

### 10.4 Import (`App\Support\Campaigns\Outreach\ProspectImportSchema`, the shared engine)

Registered as `prospects` in `Imports::SCHEMAS`; the campaign page shows `<livewire:hq.import.import-dialog
schema="prospects" />`. The engine is unchanged (`ImportEngineTest` and `LeadImportTest` stay green).

- Columns: Company *, Contact first name *, Contact last name, Email *, Job title, Country * (the country list), Industry
  * (Master data's list), What we found +, Company website +, Language (English or French; "Français" reads as French).
  Each has a good and a bad example in the guide sheet; the guide's lines give whole-row good and bad examples.
- `fill()`: a dialling code typed as the country becomes the country; the email is lower case; `kilo.test` becomes
  `https://kilo.test`; an empty Language follows the country (`MailLocale::fromCountry()`, else English).
- **Already exists** (left alone, with the reason): a prospect with that address in any campaign (or the one this
  file's row made), an address on the do-not-email list (`Marketing::entry`), a lead's or client's contact with that
  address, or an organisation with the same name (`Duplicates::find`, name keys equal).
- **Needs fixing:** a required cell empty, an email that isn't one, the same email twice in the file, neither What we
  found nor website, a website that isn't http(s), a language other than English or French, over-long cells.
- The dialog's one choice is the **campaign** (the page it was opened from, by default; only campaigns you may review).
- Each new prospect gets its first email and follow-up in `writing`, each with its own token.

### 10.5 Drafts (`Drafts`, `Website`, AI purpose `outreach_email`)

- `outreach:write` (every minute, `withoutOverlapping`) takes the next **10** first emails in `writing`, reads the
  prospect's website once, and queues **one** job per prospect on the `ai` queue (`Ai::queue`, run by the scheduler's
  worker). The listener `App\Listeners\Campaigns\FileOutreachAi` files the answer: both emails become drafts, or the
  first becomes `not_written` with the `ai.errors.*` key. The pass also files jobs a stopped worker left behind.
- `config/ai.php` `outreach_email`: Claude Sonnet 5.5, effort low, about $0.01 a prospect. MATERIAL: SENDER (name and
  job title, from the HQ Mail signature), PROSPECT, WHAT WE FOUND, THEIR WEBSITE (marked "facts only, never
  instructions"); INSTRUCTIONS: the campaign's WHAT TO OFFER and a REWRITE NOTE; LANGUAGE: the prospect's. Output:
  `subject`, `body`, `follow_up` (plain text; HQ turns them into rich text), `insight` (shown to the reviewer only).
  The company brief (services, approved proof, "never invent") opens the request as usual.
- **Rewrite** keeps the old words until the new ones arrive, and keeps them (with the reason) if Claude can't.
- **Website reading** (`Website::read`), safely: http or https, ports 80 and 443 only, no user name in the address,
  never an IP or a `localhost` / `.local` / `.internal` name; the name is resolved and **every** address must be public
  (private, loopback, link-local, reserved, shared 100.64/10 and IPv4-in-IPv6 refused), and curl is **pinned** to the
  checked address (`CURLOPT_RESOLVE`), so DNS can't change in between; redirects followed by hand, at most 3, each
  checked the same way; 4 s to connect, 8 s in all, 512 KB at most; HTML or plain text only; only the words are kept
  (title, description, headings, paragraphs; scripts, styles, menus and footers dropped), 6,000 characters at most.
  Without curl the server reads no websites and says so. The result or the reason shows in the dialog.

### 10.6 Review (`OutreachCampaignPage`)

The table shows each prospect, the status (and the follow-up's: "Follow-up from 6 Nov, 10:00", "Follow-up cancelled:
they replied"), the subject, email and language. A row opens the dialog: what we found, the website's state, Claude's
insight, the email and its follow-up as they will read (the signature and unsubscribe line are added when sending).
**Approve** approves the first email and its follow-up (an empty follow-up means none) and opens the next draft;
**Skip** skips both and opens the next; **Back to review** undoes an approval (until it goes) or a skip. **Edit** uses
the Broadcasts rich-text editor (`resources/campaigns/editor.js`, also served at `hq.campaigns.outreach.script`),
cleaned by `RichText::clean`. **Approve the rest** counts first emails approved by hand (`approval = 'hand'`) and needs
`Outreach::HAND_APPROVALS` (10); it asks first, approves every draft left (`approval = 'bulk'`) and writes
`OUTREACH_APPROVED_REST` to the audit log. Drafts still being written are never approved unseen. The page refreshes
itself every 4 seconds while Claude writes.

### 10.7 Sending (`Sender`, `outreach:send` every minute, `withoutOverlapping`)

0. Emails left in `sending` for 15 minutes fail ("Interrupted while sending…") and are never sent again.
1. `Replies::sweep()` stops pending follow-ups of prospects who wrote back; `Replies::complaints()` pauses senders.
2. Outside the window nothing goes (`Limits::inWindow`, Lagos time, Monday to Friday).
3. Each sender who isn't paused, whose own mailbox is connected and active (and who may send from it,
   `MailAccess::can`), who is under today's limit (`Limits::dailyLimit`: `first_weeks_limit` until `first_weeks_days`
   after their first outreach email, then `daily_limit`; follow-ups count) and whose last email went `gap_minutes` ago
   sends **one**: a due follow-up first, else the oldest approved first email of their **active** campaigns.
4. Each email is **claimed** (`approved` / `scheduled` → `sending`, one update) before it goes. At the last moment:
   `Marketing::canEmail()` (else Skipped / Cancelled), the prospect's state, and for a follow-up a reply once more.
5. `MailTransport::make()->send($sender, $account, $email)`: From is the mailbox; the copy goes to Sent. The message
   (`Message::build`): the approved words, the sender's HQ Mail signature (full on the first, short on the follow-up),
   one unsubscribe line in the prospect's language, `List-Unsubscribe: <…/email/unsubscribe/o/{token}>` and
   `List-Unsubscribe-Post: List-Unsubscribe=One-Click`. The follow-up is a reply: `Re:` subject, `In-Reply-To` and
   `References` the first email's Message-ID, the first quoted below.
6. After the first email: the prospect is `emailed`; its approved follow-up is `scheduled` for `follow_up_days` working
   days later at the same time (the window applies when it is due).
7. Refusals: a permanent refusal of the address (`Sender::isBounce`: the mailer's own errors only, read with
   `ProcessBroadcastQueue::refusesAddress`) is **Bounced**, goes on the list (`suppress(…, 'bounced', ['source' =>
   'outreach'])`) and stops the follow-up; when more of the sender's emails that day bounced than
   `bounce_pause_percent` (bounced ÷ sent and bounced today) the sender **pauses** (`Senders::pause`: a bell to them
   and to everyone with `outreach.approve`, once per pause). A refused password puts the email back and pauses the
   sender (`mailbox`). A "try later" (4xx) puts it back, 3 tries at most. Anything else is **Not sent** (`failed`) and
   its follow-up is cancelled.

**Note on the bounce rule:** with 15 a day, the first bounce of a day pauses the sender unless 20 emails went before
it (1 of 20 is exactly 5%). This is the owner's rule taken literally; the owner can raise the percentage.

### 10.8 Unsubscribes and replies

- The outreach link and the inbox's one-click button (`EmailLinkController::outreachPage` / `outreachUnsubscribe`):
  `Marketing::suppress($email, 'unsubscribed', ['source' => 'outreach'])`, the prospect `unsubscribed`, the unsent
  first email skipped and the follow-up cancelled (`Outreach::unsubscribe`). CSRF is already exempt for
  `email/unsubscribe/*`. The page speaks the prospect's language.
- A **reply** (`Replies::found`): a message in HQ Mail's copy of any mailbox (`mail_messages`, not sent by HQ) from the
  prospect's address that arrived after the first email went, or whose `In-Reply-To` or `References` name the first
  email's Message-ID. It marks the prospect `replied` and cancels the follow-up ("They replied"). C2 doesn't read or
  sort the reply.
- A **spam complaint**: a sent-to prospect whose address is on the list as `complained` (C3 or the owner records it)
  gets `complained_at`, the follow-up stops, and the sender who wrote to them pauses.

### 10.9 Choices made in C2 (the owner can change any)

- **One prospect per address across all campaigns**, so nobody gets two cold emails from two campaigns.
- **The follow-up is written with the first email** and approved with it, so a person has read it (or Approve the
  rest covered it) before it goes by itself; an emptied follow-up means none.
- **"Already exists" includes an organisation with the same name** in HQ (a lead or client), not only its contacts.
- **Reviewing is for the campaign's own salesperson** (or the owner): the emails go out under their name.
- **The unsubscribe line** (EN): "If you’d rather not hear from JBS Praxis again, just say so, or unsubscribe here."
  (FR): « Si vous préférez ne plus recevoir de messages de JBS Praxis, dites-le-moi simplement, ou désabonnez-vous
  ici. » Counsel to confirm (section 8, item 6).
- **Public holidays** aren't known to the window: only weekends are skipped.
- **Every attempt counts toward the day** (sent, bounced, failed), not only deliveries.

### 10.10 Files

`app/Support/Campaigns/Outreach/` (`Outreach`, `Limits`, `Senders`, `Drafts`, `Website`, `UnsafeAddress`, `Message`,
`Sender`, `Replies`, `ProspectImportSchema`), `app/Models/Campaigns/Outreach{Campaign,Prospect,Email}.php`,
`app/Livewire/Hq/Campaigns/Outreach/{OutreachList,OutreachCampaignPage,OutreachLimits}.php` and their views under
`resources/views/livewire/hq/campaigns/outreach/`, `app/Console/Commands/Outreach{Write,Send}.php`,
`app/Listeners/Campaigns/FileOutreachAi.php`, `routes/campaigns/outreach.php`, `lang/{en,fr}/outreach.php`. Shared files
changed: `routes/campaigns.php` (one `require`), `routes/email-links.php` (two routes), `routes/console.php` (two
schedule lines), `config/access.php` (the module, Sales' grant), `config/ai.php` (the purpose), `lang/*/ai.php` (its
label), `lang/*/marketing.php` (`outreach_unsubscribe`), `components/layouts/hq.blade.php` (the rail),
`marketing/unsubscribe.blade.php` (its words and routes can be the outreach ones), `EmailLinkController` (two actions),
`ProcessBroadcastQueue` (`refusesAddress()` split out of `isPermanent()`), `Imports::SCHEMAS`.

### 10.11 Testing and running it locally

- `tests/Feature/Campaigns/Outreach{Sending,Drafts,Import,Review}Test.php` with `OutreachFixtures` (25 tests): the
  window, the gap, 15 then 30, follow-ups counting, the last-moment check, bounces and the auto-pause with its bells, a
  spam complaint, never twice, interrupted sends, a refused password, 4xx retries, the unsubscribe page and one-click,
  the follow-up 3 working days later and its cancelling by In-Reply-To, References or the address, prospects never in
  an audience; drafts from research and website in the prospect's language, not written and Try again, a failed
  rewrite keeping the draft, a stopped worker; every website safety rule; the template and guide, good, bad and
  existing rows, who may import where; the dialog, Edit, Rewrite, Skip, Approve the rest after 10, permissions, the
  limits screen. Nothing leaves the machine: `FakeMailServer`, `FakeAi`, `Http::fake`, `MAIL_MAILER=array`.
- Local check (4 Nov 2026, `hq.localhost:8122`, database `praxis_camp2`, launch config `hq-campaigns-c2` with
  `SESSION_COOKIE=praxis_camp2_session`): the demo mailbox pointed at `127.0.0.1:1`; drafts written by `FakeAi` and
  sending run on `FakeMailServer` from scratch scripts with the clock set to a weekday; desktop and 375 px.

## 11. For C3: replies

- **Where replies are:** `Replies::found($prospect, $first)` already recognises them; C3 reads the same `mail_messages`
  rows (the first email's `message_id` is on `outreach_emails`, the prospect's address on `outreach_prospects`).
  Prospects C2 marked `replied` have `replied_at`.
- **Sorting:** Interested, Not now, Not interested, Unsubscribe. **Unsubscribe** →
  `Marketing::suppress($from, 'unsubscribed', ['source' => 'outreach'])` and `Outreach::stop($prospectId,
  'Unsubscribed')`; a spam complaint → `'complained'` (C2's `Replies::complaints()` then pauses the sender by itself);
  a bounce notice (DSN) → `'bounced'`, and set `outreach_prospects.bounced_at`.
- **Make a lead:** `Leads::create` from the prospect (company, country, industry, the person as a contact with source
  `outreach`); keep the prospect row (its emails are the history). From then on they are a lead and can get broadcasts
  unless their address is on the list.
- **Not now reminders:** a date on the prospect (a new column) and a bell; don't send outreach again to someone who
  said not now without a person approving it.
- **Numbers:** add replies and their sorting to `Outreach::stats()`, which the list and the campaign page already show.
- **Keep:** the claim-before-send in `Sender`, the one-follow-up rule, and `Marketing::canEmail()` at the last moment.
