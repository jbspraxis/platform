# Leads: a simple CRM for the sales team (runbook for PRs 1–3)

Agreed with the owner on 3 November 2026, the day JBS Praxis hired its Head of Sales. Until now a lead was an organisation row with a hand-moved `pipeline_stage`, shown in one 605-line screen (`LeadsRegister`) with a drawer; adding a lead by hand required the full cost of poor quality questionnaire; contacts, owners and the next step were thin or missing.

This runbook replaces that with a **lead**: the same `organizations` row, with a status that doesn't depend on its opportunities, contacts with roles, an owner, a source, a next step, a **timeline** of everything that happened, and a page of its own (**Lead 360**) with tabs. Three PRs. **PR 1 is built first and alone; PRs 2 and 3 then run in parallel on PR 1's contracts.**

> **Every chat working on leads reads this whole file first and follows it.** Change a contract only in the PR that owns it, and record the change in section 12 in the same commit. Opportunities have their own runbook, `docs/OPPORTUNITIES.md`.

## 1. Owner decisions (3 Nov 2026, binding)

| # | Question | Decision |
|---|---|---|
| 1 | Required to save a lead | **Organisation name, country, industry, and at least a phone or an email** (the organisation's or a contact's). Address, the organisation's email and phone, and contacts are optional. |
| 2 | Cost of poor quality | **Optional**, in a collapsed **More details** section that starts closed. Website leads keep their COPQ; it can be calculated later from the lead page. |
| 3 | Contacts | Any number per lead: name, job title, email, phone, **deal role** (decision maker / finance / technical / user); **one is the main contact**; the form shows one contact block with **Add another contact**. |
| 4 | Duplicates | While typing, matching leads and clients (by **name, email or phone**) appear with **Open it**. Saving a new one anyway is allowed. |
| 5 | Status | **Independent of opportunities**: New → Contacted → Qualified → Client, or **Lost** with a reason from a list (no budget, no need, chose someone else, no reply, not a fit, other + note). A lead can have many opportunities, some won and some lost. |
| 6 | Lead → client | **Automatic on the first signed proposal or SOW**, and sales can **mark it by hand**. Show the date and how it happened. |
| 7 | Owner | Website leads go to a **default owner chosen in Settings** (Gerald, Head of Sales, for now), who gets a bell; leads added by hand belong to whoever adds them; owner and source are editable on the lead page. |
| 8 | Visibility | Everyone with Leads access (`crm.view` / `crm.edit`) sees every lead. The list's **Mine** filter is on by default (PR 3). |
| 9 | Lead 360 | Opening a lead goes to a **page**, not the drawer, with tabs and a link back to all leads. |
| 10 | Next step | **Every open lead has one** (what, by when). PR 3 builds the follow-up list and reminders; PR 1 stores, shows and edits it. |
| 11 | Meetings | **Meetings for leads exist** (PR 2). A free-audit booking from the website becomes a lead meeting, not an opportunity (PR 2). |

The rest of the owner's 18 answers (Excel import, its preview and its owner/source, emails on the timeline, meeting details, "Open an opportunity" from a meeting with an AI title, the follow-up list and its bell, the summary strip) are PR 2's and PR 3's scope in section 9.

Defaults chosen in PR 1 without asking (change them here if the owner disagrees): see section 12, *Choices made in PR 1*.

## 2. What changes

| Before | After | PR |
|---|---|---|
| `pipeline_stage`, six stages moved by hand on a board | `lead_status` (new, contacted, qualified, client, lost), with a lost reason and the date and way it became a client | 1 |
| A drawer on the Leads list | **Lead 360**: `/intelligence/leads/{id}` with Overview, Meetings, Emails, Opportunities, Activity | 1 (2 fills Meetings, Emails, Opportunities) |
| "Add lead" needed every COPQ figure | **Add a lead**: name, country, industry and a phone or email; contacts; source; next step; COPQ optional | 1 |
| One contact, from the website | Contacts with deal roles and a main contact | 1 |
| Website leads unowned until someone clicks "Assign to me" | The **default owner** gets them, with a bell | 1 |
| Notes in `crm_notes` only | The **lead timeline** (`lead_timeline`): every change, note, call, WhatsApp, visit and website message; old notes copied in | 1 |
| Meetings only on opportunities | Lead meetings (`meetings.opportunity_id` may be empty), Google invite, transcript, summary; bookings become lead meetings | 1 (column), 2 |
| Email only in Mail | The lead's Emails tab, matched by contact address | 2 |
| No import | **Excel import** with a template and a preview | 3 |
| One unfiltered table | Filters, **Mine**, **My follow-ups**, the summary strip | 3 |

## 3. The three PRs

| PR | Branch | Scope | Depends on | Status |
|---|---|---|---|---|
| **1 Foundation** | `leads/pr1-foundation` | Data and backfill (section 4); the timeline (section 5); the `App\Support\Leads` contracts (section 6); Add a lead, Lead 360 with Overview and Activity built and the other tabs as stubs, the slimmed list, Lead settings (section 7); website forms and signing through the contracts; this runbook. | — | Built, in review |
| **2 Lead 360 tabs** | `leads/pr2-lead360` | Meetings (schedule with a Google invite and Meet, or log one; transcript plus Claude summary, reusing the opportunity meeting code), **Open an opportunity** from a meeting (meeting, notes and contacts carried over, AI-suggested title), website bookings as lead meetings, the Emails tab (HQ Mail both ways by contact address, "Email them"), the Opportunities tab in full, timeline entries for all of these. | 1 | Not started |
| **3 List and sales** | `leads/pr3-list-sales` | Excel import on a **shared import engine** that Campaigns Outreach (C2) reuses (section 6, *The shared import engine*): template, preview Ready / Needs fixing / Already exists, owner and source in the modal, bad rows back as an Excel; Leads is its first schema. List filters with **Mine** on by default, **My follow-ups** (overdue in red, a morning bell), the summary strip (new this week/month, by status, conversion, open opportunity value, overdue, per owner, period filter). | 1 | Not started |

**Done means** for every PR: the scope works end to end on a local server, desktop and 375 px; tests cover it (section 10); CI is green; the PR description lists what the owner should click to check it live; section 11 gets a line once it is live. HQ is English (no language switch); anything a client sees is in English and French.

## 4. Data (PR 1)

Migrations `2026_11_03_200000_add_leads_foundation` and `2026_11_03_200001_backfill_leads`.

- **`organizations`** (a lead is an organisation with status Lead, and stays one as an Active Client):
  - `lead_status` enum `new`, `contacted`, `qualified`, `client`, `lost` (null for JBS Praxis itself). **Client goes with `status = 'Active Client'`**; Lost keeps `status = 'Lead'`.
  - `lost_reason` (a `LeadStatus::LOST_REASONS` key), `lost_note`, `lost_at`.
  - `client_since`, `client_via` (`signed`: the first signed proposal or SOW; `manual`: marked by hand; `existing`: a client before 3 Nov 2026), `client_reference` (the proposal or SOW reference).
  - `source` (a `Sources::LABELS` key: `website_enquiry`, `website_booking`, `cost_report`, `manual`, `import`, `referral`, `event`, `outreach`, `social`, `partner`, `other`) and `source_detail` (free text).
  - `next_step` (255) and `next_step_due` (a date, Lagos).
  - `phone` and `email`: the organisation's own. The address is `billing_address` (shown as "Address").
  - `last_activity_at`: the latest timeline row's time (only ever moves forward; it never touches `updated_at`).
  - Indexes `(lead_status, assigned_to)` and `next_step_due`, for PR 3's lists.
  - Unchanged and still written for older screens: `pipeline_stage` (Lost sets it to Lost; Free audits and Broadcasts read it), `disqualification_reason`, `is_duplicate_suspect`, `assigned_to` (the owner).
- **`client_users`** (contacts): `deal_role` enum `decision_maker`, `finance`, `technical`, `user`; `is_main_contact` (the lead's main contact; **not** `is_primary_client`, which is the portal's Client Admin). **`email` may now be empty** (a contact known by phone only); it stays unique.
- **`meetings.opportunity_id` is nullable**: a meeting can belong to a lead alone (`organization_id` is the lead). Index `(organization_id, starts_at)`.
- **`lead_timeline`**: section 5.
- **Default owner**: not a column. `integration_settings` key `leads.default_owner_id` (users.id), read and written through `App\Support\Integrations` by `LeadOwner`.
- **Backfill** (idempotent; per organisation, JBS Praxis left out):
  - Active Client or Past Client → `client`; the old board's **Won** → `client`, marked by hand, and the organisation becomes an Active Client; **Lost** → `lost` (reason `other`, the disqualification reason as the note); Proposal sent and Free audit (In Consultation) → `qualified`; Qualifying → `contacted`; else `new`.
  - Clients: since the first signed proposal (Authorized or Converted) or SOW, `client_via = signed` with its reference; otherwise `existing` (since the first project, if any).
  - Source: from the first contact's `client_users.source` (`contact_form`, `booking`, `cost_report`, `hq`); else `manual` when staff created the row or it has a COPQ but no contact (the old intake form); else not recorded. `utm_source` / `utm_campaign` go into `source_detail`.
  - Country: the contact form stored the dialling code (`+234`); it becomes the country's name.
  - Main contact: the portal's Client Admin, else the first active contact.
  - Timeline: "Lead added", every `crm_notes` row (website messages as `website_enquiry`, Call / Email / Meeting / Internal Note as `call` / `email` / `meeting` / `note`), each opportunity opened, and how it became a client or was lost; `last_activity_at` set from them.
  - Idempotency: an organisation whose `backfill:created:<id>` row exists is skipped; every row it writes has a `dedupe_key` (`crm_note:<id>`, `opportunity:<id>`, `backfill:client:<id>`, `backfill:lost:<id>`). `updated_at` doesn't move.

## 5. The timeline (`lead_timeline`, `App\Support\Leads\LeadTimeline`)

One row per thing that happened to a lead; the Activity tab lists them newest first, the Overview shows the latest five.

- Columns: `organization_id`, `type` (a `LeadTimeline::TYPES` key), `actor_type` (`staff` | `client` | `system`), `actor_id` (users.id or client_users.id), `summary` (one line, English), `body` (what was said or typed), `payload` (JSON), `client_user_id` (the contact it concerns), `opportunity_id`, `meeting_id`, `related_type` + `related_id` (anything else: `crm_note`, `proposal`, `statement_of_work`, `appointment`, `copq_assessment`; PR 2 adds `mail_message`), `occurred_at` (UTC, **microseconds**, so lines recorded together keep their order), `dedupe_key` (unique: backfills and imports), soft deletes.
- **`LeadTimeline::record(string $orgId, string $type, string $summary, array $with = []): string`** returns the row id. `$with`: `actor` (a `User` = staff, a `ClientUser` = client, none = system), `body`, `payload` (array), `client_user_id`, `opportunity_id`, `meeting_id`, `related_type`, `related_id`, `at` (when it happened; default now), `dedupe_key` (the same key twice records once and returns the first row's id).
- **`LeadTimeline::for($orgId, ?array $types = null, int $limit = 50)`**: rows with `actor_name` (staff name, the contact's name, or "HQ") and `contact_name`; `payload` decoded.
- Types today: `created`, `website_enquiry`, `website_booking`, `cost_report`, `status_changed`, `marked_client`, `marked_lost`, `owner_changed`, `details_changed`, `source_changed`, `contact_added`, `contact_updated`, `contact_removed`, `next_step_set`, `copq_calculated`, `records_merged`, `note`, `call`, `whatsapp`, `visit`, `free_audit_booked`, `signed`, `opportunity_opened` (backfill), and reserved for PR 2 (`email`, `meeting`) and PR 3 (`imported`). A new type is added to `LeadTimeline::TYPES` (label) and to `resources/views/livewire/hq/leads/partials/timeline-icon.blade.php` (icon, **one line**: `scripts/icons.mjs` reads the quoted names there).
- **`LeadTimeline::LOGGABLE`**: what *Log activity* offers (call, WhatsApp, visit, note).

## 6. Contracts (PR 1 provides; PRs 2 and 3 call)

All in `App\Support\Leads`. Rules that are broken throw **`LeadRuleBroken`** (`->getMessage()` fit for the screen, `->field` the field it is about) or a `ValidationException`. Every change writes the timeline; the important ones also write `system_audit_logs` (target `organizations`, actions below).

- **`LeadAccess`**: `canView` (crm.view), `canEdit` (crm.edit), `canCreate` (crm.create); `STATUSES` (`Lead`, `Active Client`: what the list shows and the screens change); `find($id)` (a lead the screens show, else null); `log($action, $description, $orgId, $old, $new)`.
- **`Leads`** (adding and details):
  - `rules($prefix)` / `messages($prefix)` for the lead's own fields (`name`, `country`, `city`, `industry`, `phone`, `email`, `address`, `preferred_language`, `source`, `source_detail`, `next_step`, `next_step_due`); `UNREACHABLE` and `reachable($lead, $contacts)` (decision 1).
  - **`validate(array $lead, array $contacts): array`**: errors keyed `lead.<field>` and `contacts.<n>.<field>` (empty contact blocks dropped first), `lead.phone` for "no phone or email". **PR 3's import shows these as "Needs fixing"**.
  - **`create(array $lead, array $contacts, ?User $by, array $options = []): Organization`**: one transaction; status Lead, `lead_status` new, owner `$options['owner_id']` (default: whoever adds it; pass `null` for none), source `$options['source']` (default the lead's `source`, else `manual`), contacts through `Contacts::add` (the one with `main`, else the first, is the main contact), language from the country when not given, one "Lead added" timeline line (`$options['dedupe_key']` for an import row), audit `LEAD_CREATED`, and the COPQ when `$options['copq']` holds the figures. Options: `owner_id`, `source`, `copq`, `contact_source` (client_users.source, default `hq`; PR 3 passes `import`), `dedupe_key`. Throws `ValidationException` (keys as `validate`, plus `contacts.<n>.email` when an email is already someone's contact).
  - `DETAILS` and `updateDetails($lead, $values, $by)`: the lead page's edit (keys as `rules`; source required there), returns the changed columns; timeline `details_changed` / `source_changed`, audit `LEAD_UPDATED`. `industries()`.
- **`Contacts`**: `DEAL_ROLES`; `rules($prefix)` / `messages($prefix)` (`name`, `job_title`, `email`, `phone`, `deal_role`); `of($orgId)` (main first); `add($lead, $data, $by, $source = 'hq', $quiet = false)` (`$data`: those keys plus `main`; refuses an email that is already a contact anywhere, naming the lead it belongs to); `update($contact, $data, $by)`; `makeMain`; `remove` (soft delete; the next contact becomes main); `emailTaken($email, $exceptId)`; `isPortalAccount` (a password or an invitation: name, email and phone then belong to the portal, and the contact can't be removed here); `splitName`; `isBlank`.
- **`Duplicates`**: **`find(?string $name, array $emails = [], array $phones = [], ?string $exceptId = null, int $limit = 5)`** → `['id', 'name', 'status', 'lead_status', 'country', 'owner', 'matched' => ['name', 'email', 'phone']]`, leads and clients (never JBS Praxis, never deleted). Names compare on `nameKey()` (lower case, punctuation and endings like Ltd, Plc, SARL, Group dropped; one key starting the other at a word counts) or contain what is typed (4 characters or more); emails exactly (the organisation's or a contact's); phones on their last 9 digits (`phoneKey()`), so +234 803… matches 0803…. PR 3's "Already exists" uses it.
- **`LeadStatus`**: `LABELS`, `OPEN` (new, contacted, qualified), `LOST_REASONS` (`no_budget`, `no_need`, `chose_someone_else`, `no_reply`, `not_a_fit`, `other`), `CLIENT_VIA`; `label`, `tone` (pill class), `isOpen`, `clientHow`.
  - `change($lead, $status, $by, $lostReason, $lostNote)`: client goes to `markClient`, lost to `markLost`; leaving Lost clears its reason; leaving a client **marked by hand** makes it a Lead again; a client made by signing (or before the revamp) can't be moved. Audit `LEAD_STATUS_CHANGED`.
  - `markLost($lead, $reason, $note, $by)`: a reason from the list, a note for `other` (2,000 characters at most); never for a client; also sets `pipeline_stage` Lost and `disqualification_reason`. Audit `LEAD_LOST`.
  - `markClient($lead, $by)`: by hand, Active Client from now, `client_via = manual`. Audit `LEAD_MARKED_CLIENT`.
  - **`markClientFromSigning($orgId, 'proposal'|'sow', $reference, $documentId, $at)`**: called **inside** the signing transaction by `Portal\ProposalGateway` (legacy signing) and `Opportunities\Sows::sign` instead of setting Active Client. The first signature sets `client_since`, `signed` and the reference; a later one only adds a `signed` line. No audit line and no bell (the signing writes its own).
- **`LeadOwner`**: `canAssign($user, $lead)` (crm.edit and: nobody owns it, or it's theirs, or they hold `opportunities.approve`); `assign($lead, $userId, $by)` (an active staff member; audit `LEAD_ASSIGNED`, timeline, bell "A lead was assigned to you"); `claim($lead, $by)` ("Assign to me" on an unowned lead only; audit `LEAD_CLAIMED`); **`defaultOwnerId()`** (null when not set or no longer active); `canSetDefault` (`opportunities.approve`); `setDefault($userId|null, $by)` (audit `LEADS_DEFAULT_OWNER_CHANGED`); `options($term, $except)` / `option($user)` for `x-hq.picker`; **`bell($userId, $title, $message, $orgId)`** (a notification opening the lead's page; a bell is also a phone push, filed under Leads and proposals); `activeStaff`, `name`.
- **`NextStep`**: `set($lead, $what, $due, $by)` (both empty clears it; a step needs a date); **`isOverdue($lead)`** (an open lead whose date has passed, Lagos); `suggestion()` (Make first contact, the next working day); `day($date)`; `rules` / `messages`. **PR 3** reads `organizations.next_step` and `next_step_due` for *My follow-ups* and the morning bell.
- **`WebsiteLeads::received($orgId, $isNew, $source, $contactId, $summary, $body, $payload)`**: what the three website forms do once they have found or created the organisation and contact, inside their transaction: a new lead gets its source, status New, a first next step (`FIRST_STEP`, the next working day) and the **default owner, who gets a bell**; the contact becomes the main contact when there is none; the timeline gets the enquiry, the booking (`payload.appointment_id`) or the estimate (`payload.report_token`). The forms keep their other bells (everyone with crm.edit; Free audits for bookings) and emails. **PR 2** turns the booking into a lead meeting here or next to it in `BookingService`.
- **`Copq`**: `calculate($industry, $tool, $bottleneck, $figures, $currency)` (the website calculator's maths, which `CostReportService` now calls too) → `total`, `tier`, `error_rate`, `mgmt_nodes`; `store($lead, $in, $by, $contactName, $contactEmail)` (HQ's estimate, summary from Master data, no AI call; timeline `copq_calculated`); `rules`, `messages`, `started`, `latest`, `choices`, `FIGURES`.
- **`Sources`**: `LABELS`, `WEBSITE`, `BY_HAND` (what the Add a lead form offers), `label`, `isWebsite`. **`Countries`**: `NAMES`, `FRENCH` (emails start in French there), `DIAL_CODES`, `fromDialCode`, `language`.
- **Livewire event `lead-changed`**: `LeadPage` listens and redraws (tab counts, timeline). PR 2's tabs dispatch it after a change.

### The shared import engine (PR 3 builds it; Campaigns Outreach reuses it)

The lead chat's request of 3 Nov 2026: the Excel import is built **once**, generic, so Campaigns Outreach (C2, later) imports prospects with the same engine and screen. Nothing of it is in PR 1.

- **Where**: `App\Support\Import\…` (the engine) and one **schema** class per import type. Leads' schema is the first (e.g. `App\Support\Leads\LeadImportSchema`); Outreach adds a `prospects` schema without changing the engine. The screen is one Livewire import modal that takes a schema key, so Leads and Outreach show the same thing.
- **A schema** says:
  - its columns, each with a label, whether it is required, an example and a help line for the guide;
  - its validators per column, plus rules across columns (for example "at least one of these");
  - the values it fills in itself (for example the language from the country);
  - how rows group into one record (Leads: one row per contact, rows with the same organisation name are one lead);
  - how to tell that a group already exists (Leads: `Duplicates::find`);
  - how to import one good group with the modal's options (Leads: `Leads::create` / `Contacts::add`, as in section 9).
- **The engine** gives every schema:
  - **the template**: an .xlsx with sheet 1 a guide to the columns (required ones marked, examples) and sheet 2 the data with its headers;
  - **reading a file** into a **preview**: each row or group is Ready, Needs fixing (the reason, and the cell in red), or Already exists;
  - **import the good rows**: only the Ready ones, each once (a `dedupe_key` per file and row);
  - **the bad rows as an Excel** to fix and upload again, with their reasons and red cells.
- **Leads' schema**:
  - columns as Add a lead (section 7): organisation name, country, industry, phone, email, city, address, then the contact's name, job title, email, phone and deal role, and an optional owner email that overrides the modal's owner for that row;
  - required as decision 1 (`Leads::validate`);
  - an existing lead is skipped, but its new contacts are added.
- **Outreach's schema** (C2, not built here): `prospects`, with company, contact first name, email, country and industry, plus at least one of "What we found" or website; the language is filled from the country (`Countries::language`).
- **The library**: PR 3 chooses it and checks its `require` against the live PHP first (`docs/OPPORTUNITIES.md` section 5: no `intl`, `fileinfo`, `exif`, `sodium` or `gmp`, and PR 5 found no `gd`). PhpSpreadsheet needs `gd` and `fileinfo`, so it is out unless they are confirmed live. A small reader and writer on `ZipArchive` and `XMLReader` is the fallback, if `zip` is there.

## 7. Screens and routes (PR 1)

- **`hq.crm.leads`** `/intelligence/leads` (`App\Livewire\Hq\Intelligence\LeadsRegister`, crm.view): every lead and client, latest activity first: organisation (industry, country, "Possible duplicate"), main contact, status, next step (red when overdue), owner ("Assign to me" when unowned), COPQ. A row opens the lead. *Book free audit* (as before), *Download CSV* (crm.export: organisation, country, industry, contact, email, phone, status, source, owner, next step and date, COPQ, tier, date added), *Lead settings* (only `opportunities.approve`), *Add lead*. The board by stage is gone (stages are gone); filters, Mine, the summary strip and the import are PR 3's.
- **`hq.crm.leads.new`** `/intelligence/leads/new` (`App\Livewire\Hq\Leads\AddLead`, crm.create): Organisation (name with the duplicates panel and *Open it*, country with *Another country*, city, industry, phone, email, address), Contacts (one block, *Add another contact*, main contact, up to 20), Where it came from and what happens next (source from `Sources::BY_HAND`, details, next step prefilled "Make first contact" by the next working day), More details (closed: language for emails, set from the country; the COPQ figures, all or none). Saved, it opens the new lead. Optional fields are marked "(optional)" (docs/BRAND.md section 12).
- **`hq.crm.lead`** `/intelligence/leads/{organization}` (`App\Livewire\Hq\Leads\LeadPage`, crm.view; 404 for JBS Praxis, Past Client, Suspended or unknown ids), tabs `?tab=overview|meetings|emails|opportunities|activity` (`?type=<timeline type>` filters Activity):
  - Header: back to *All leads*, name, status, owner, place, source, duplicate flag; *Assign to me* when unowned; *Open an opportunity* (`opportunities.create`) → `/opportunities?new=1&organization=<id>`.
  - **Overview**: *Status and next step* (status select; Lost asks the reason and note; *Mark as client* asks first; a client shows since when and how; the next step with *Change*, red when overdue); *Contacts* (main contact, deal role, portal account tag; add, change, make main, remove); *Log activity* (call, WhatsApp, visit, note, with a contact) and the latest five lines; *Details* (edit, source included) and the owner picker (`x-hq.picker`, `LeadOwner::canAssign`); *Cost of poor quality* (the latest estimate and its report, *Calculate* / *Calculate again*, *Review duplicates* → the merge dialog moved from the old drawer).
  - **Activity**: the whole timeline with a filter by type.
  - **Meetings**, **Emails**: stubs saying what is coming (`tabs/meetings.blade.php`, `tabs/emails.blade.php`).
  - **Opportunities**: the lead's opportunities (title linked when `OpportunityAccess::canView`, stage, opened, lead) and *Open an opportunity* (`tabs/opportunities.blade.php`).
- **Lead settings** (`App\Livewire\Hq\Leads\LeadSettings`, a dialog on the list): *Website leads go to* (picker; saved at once), *Give them to no one*.
- **Old links**: notifications and emails that pointed at `hq.crm.leads` still open the list; new bells open the lead's page. The rail highlights Leads on all three pages. My workspace's *Add a lead* opens the form.
- **Free audits** (`LiveConsultation::disqualifyLead`) now marks the lead Lost through `LeadStatus::markLost` (reason other, the typed reason as the note). It used to write `status = 'Lost'`, which isn't one of the column's values.

## 8. Rules for every PR

- **Voice**: sentence case, British English, docs/BRAND.md section 12 (verb-first buttons, mark what is optional, errors that say what to do). HQ strings are English in the views and classes, as the Leads screens were; anything a client sees goes through `BrandedMail` in their language.
- **Access**: crm.view sees every lead; every action checks crm.edit (or crm.create, crm.export, `LeadOwner::canAssign`, `canSetDefault`) on the server; server-set Livewire state is `#[Locked]`.
- **Through the contracts**: never write `lead_status`, `assigned_to`, `is_main_contact`, `next_step*` or `lead_timeline` directly from a screen; use section 6 so the timeline and the audit stay complete. A new kind of event is a new timeline type (section 5).
- **Fixtures** are fictional (`.test` addresses, invented names). Local mail is `log` or `array`.

## 9. PR 2 and PR 3: scope and who owns which files

PRs 2 and 3 run at the same time; each owns its files, and the few shared ones are append-only.

- **PR 2 owns**: `resources/views/livewire/hq/leads/tabs/{meetings,emails,opportunities}.blade.php` (replace each with its component, e.g. `<livewire:hq.leads.meetings-tab :lead-id="$lead->id" />`); new components under `App\Livewire\Hq\Leads\` (`MeetingsTab`, `EmailsTab`, `OpportunitiesTab`) and any `App\Support\Leads\LeadMeetings` / `LeadEmails`; `App\Services\Site\BookingService` (bookings → lead meetings); `LiveConsultation` where bookings become opportunities; the opportunity meeting code it reuses (`App\Support\Opportunities\Meetings`, `MeetingsTab`, `TranscriptCollector`, `FileMeetingAi`), which must now accept meetings **without an opportunity** (`meetings.opportunity_id` null: today they assume one); the tab counts in `LeadPage::render()` and its `lead-changed` listener.
  - Meetings: schedule (Google invite + Meet, or an address) or log; transcript, Claude summary and next steps, typed notes, download; timeline `meeting` lines with `meeting_id`.
  - *Open an opportunity* from a meeting: the meeting (`meetings.opportunity_id` set), its notes and contacts carried over; the AI suggests the title from the transcript; timeline `opportunity_opened` with `opportunity_id`.
  - Emails: HQ Mail to and from the lead's contacts, matched by address (`client_users.email`, lower case), both ways; *Email them* opens the composer with the contact; timeline `email` lines (`related_type = mail_message`).
- **PR 3 owns**: `App\Livewire\Hq\Intelligence\LeadsRegister` and `leads-register.blade.php` (filters, Mine on by default, the summary strip, the import button); the **shared import engine** `App\Support\Import\…` and its import modal (section 6, *The shared import engine*), with Leads' schema (`App\Support\Leads\LeadImportSchema`) as the first; new `App\Livewire\Hq\Leads\FollowUps` (route of its own) and any `LeadNumbers`; the morning bell's command and its schedule line; the Leads template (sheet 1 a guide with required columns marked, sheet 2 the data; one row per contact; rows with the same organisation name are one lead). Campaigns Outreach (C2) later adds only its own schema; a change it needs in the engine is made with PR 3's tests kept green.
  - Leads' schema validates rows with `Leads::validate`, finds "Already exists" with `Duplicates::find`, creates with `Leads::create($lead, $contacts, $by, ['owner_id' => …, 'source' => 'import' or the modal's, 'contact_source' => 'import', 'dedupe_key' => 'import:<file hash>:<row>'])`, adds new contacts to existing leads with `Contacts::add`, and records `imported` lines.
  - Follow-ups read `organizations.next_step` / `next_step_due` for open leads (`LeadStatus::OPEN`), overdue first in red (`NextStep::isOverdue`), leads with no next step flagged; the morning bell through `LeadOwner::bell`.
- **Shared, append-only**: `LeadTimeline::TYPES` and `partials/timeline-icon.blade.php` (add your types); `routes/web.php` (add your routes under the Leads group); this file (your own section in 12).
- **PR 1 keeps**: `App\Support\Leads\*` as they are (a change to a contract is recorded in section 12), `AddLead`, `LeadPage`'s Overview and Activity, `LeadSettings`, the migrations.

## 10. Testing

- **Local**: PHP Laragon 8.3, MySQL on `127.0.0.1:3307` (root, no password). `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_PASSWORD= DB_DATABASE=praxis_test_<yours> MAIL_MAILER=array php vendor/bin/phpunit tests/Feature/Leads`. Feature tests use `tests/Feature/UsesThrowawayDatabase.php` (`staff($slug, $roles)`, `insertRow`). SignInMethodsTest's passkey tests fail on Laragon's OpenSSL and pass in CI.
- **PR 1's tests**: `tests/Feature/Leads/LeadRulesTest.php` (required fields and reachability, creating with contacts, main contact and portal accounts, duplicates, status rules and signing, owners and the default owner with a website enquiry, bookings and the calculator, the next step and `record()`, a lead meeting without an opportunity, the backfill on the old shapes and its second run), `tests/Feature/Leads/LeadScreensTest.php` (routes and who opens them, Add a lead, duplicates while typing, COPQ in More details and *Calculate*, the Overview's status, client, next step and Log activity, details, owner, source and contacts, view-only, the tabs, the list and Lead settings), `tests/Feature/Controls/LeadsControlsTest.php` (merge, assign and claim rules, now on the page), and the lead lines added to `SiteApiTest` and `SowsTest`.
- **Demo data**: `leads_demo.php` in the sessions' notes folder (run with `APP_ENV=localmysql DB_DATABASE=<dev db> LEADS_USERS_FILE=<path> php artisan tinker <file> < /dev/null`): an owner, a sales and a delivery account (passwords to `LEADS_USERS_FILE`), the default owner set, three leads in the old board's shapes through the backfill (a signed client, a booking lead with two assessments and a call, a lost one), a website enquiry and a lead added by hand with two contacts and an overdue next step.
- **Look at it**: the local server in the built-in browser, desktop and 375 px (PR 1: launch config `hq-leads-pr1`, port 8118, database `praxis_leads1`).

## 11. Deployed

- (PR 1: not yet.)

## 12. Changes to contracts, PR notes and open decisions

### PR 1 (3 Nov 2026): the foundation

- **Choices made in PR 1** (the owner can change any of them):
  - **Who sets the default owner**: the owner and partners (`opportunities.approve`), the same people who may reassign a lead someone else owns. Sales doesn't. It lives on the Leads list (*Lead settings*).
  - **Bells for website leads**: the default owner gets "A website lead was assigned to you"; the bells everyone with crm.edit already got (and Free audits' for bookings) stay as they were. Say if only the owner should be rung.
  - **New website leads start with a next step** ("Reply to their enquiry", "Confirm the free audit", "Follow up on their cost report", by the next working day). Backfilled leads keep none, so PR 3 flags them.
  - **The old board's Won** (moved by hand) became a client marked by hand, and those organisations became Active Clients.
  - **A client marked by hand can be moved back** (a mistake); a client by signing can't.
  - **Logging a call doesn't move the status** (New stays New until someone changes it).
  - **An email is one person in HQ**: adding a contact whose email is already someone's contact is refused, naming the lead it belongs to (`client_users.email` is unique).
  - **The board by stage is gone** from the list; PR 3 may add a board by status if sales want one.
- **Found in passing, fixed**: Free audits' *Mark as lost* wrote `organizations.status = 'Lost'` (not a value of that column); it now marks the lead Lost. The contact form stored the dialling code as the country; it stores the country's name.
- **Open owner decisions**: none needed to merge. To confirm after it is live: the choices above.
- **3 Nov 2026, the lead chat**: PR 3's import is a shared engine with a schema per import type, so Campaigns Outreach (C2) reuses it for prospects (section 6, *The shared import engine*; section 9). PR 1 places no part of it. The Broadcasts revamp (C1, branch `campaigns/c1-broadcasts`) runs in parallel and doesn't touch the Leads files.

### PR 3 (3 Nov 2026): list and sales

- **What it adds**: the shared Excel import engine and its dialog, with Leads' schema as the first (*Import leads* on the list); the list's filters with **Mine** on by default; the summary strip with a period and a per-owner view; **My follow-ups** (`hq.crm.leads.follow-ups`, `/intelligence/leads/follow-ups`, crm.view) and its morning bell. No migration, no new package.
- **The shared import engine** (`App\Support\Import`):
  - `ImportSchema` (one class per import type, listed in `Imports::SCHEMAS`), `Column` (key, label, required `*`, a "one of" group `+`, help, good and bad example, choices with `strict`, text format, aliases for other tools' headers), `Option` (what the dialog asks: `staff`, `choice`, `text`, `date`), `Group` (the rows of one record; `fail($row, $column, $message)` makes the row *Needs fixing* with that cell red; `failAll`), `Run` (the file's fingerprint, its name, the dialog's choices, who imports; `dedupeKey($row)` = `import:<sha1 of the file>:<row>`; `$seen` for what a schema remembers between groups), `Analysis` (the preview, then the result: each row `ready`, `fix`, `exists`, `add`, then `imported` / `added`), `Importer` (below), `ImportFailed` (the whole file can't be read; the message is for the screen).
  - **`Importer`**: `template($schema)` (sheet 1 *How to fill it in*: the columns with Required, What to put, a good and a bad example, the lists the drop-downs read, and the schema's example rows; sheet 2 the data headers, frozen, phone-like columns formatted as text, drop-downs for choices); `analyse()` (finds the data sheet and its header row by the headers, never the guide; reads up to **1,000 rows**; a missing required column or none of a "one of" group stops the file; headers it doesn't know are listed as *Not read*); `run()` (reads the file again, so what is in HQ now counts; creates each group with good rows, adds what existing records lack; a cache lock stops the same file running twice at once); `fixFile()` (the rows to fix with their red cells and a *What to fix* column; it reads back as a normal file); `keep()` / `stored()` / `forget()` / `remember()` / `recall()` (the upload and its preview under `storage/app/private/imports/<user id>/`, never in the page; files older than a day go when the next one comes).
  - **The dialog** `App\Livewire\Hq\Import\ImportDialog` (`<livewire:hq.import.import-dialog schema="leads" />`): its own button, step 1 template + upload (.xlsx or .csv, 5 MB), step 2 the preview with filters (All, Ready, Needs fixing, Already exist) and the schema's options, step 3 what was imported and *Download N rows to fix*. It dispatches `import-finished` (with `schema`) when it closes after an import.
  - **The library**: none. PhpSpreadsheet needs `gd` and `fileinfo` (not live), and the live PHP's `zip` isn't confirmed (Laragon's PHP has none). `App\Support\Import\Xlsx\Zip` reads and writes zip files in plain PHP (reading a compressed entry uses zlib's `gzinflate`, else `ZipArchive`; writing compresses with zlib when it is there, else stores), `XlsxReader` reads cells with DOM (live, as Mail and dompdf use it): shared and inline strings, rich text, numbers kept whole (2348031234567, never 2.3E+12), dates by their cell format, TRUE/FALSE, formulas' last values; `XlsxWriter` writes text cells, styles, widths, a frozen header and drop-downs; `CsvReader` takes commas or semicolons (French Excel), with or without a UTF-8 mark, or the Windows encoding. Checked locally on the template, on a filled file Excel saved again (its own shared strings) and on Excel's CSV; Excel opens the template, a filled one and the fix file. If the live PHP had neither zlib nor zip, a compressed .xlsx would get "save it as CSV"; CSV always works.
- **How Campaigns Outreach (C2) adds `prospects`** (nothing in the engine changes):
  1. `App\Support\Campaigns\ProspectImportSchema extends ImportSchema`: `key()` `prospects`, `title()` "Import prospects", `noun()`, `sheetName()`, `canImport()` (its permission), `columns()` (company, contact first name, email, country, industry, and "What we found" and website as a `oneOf: 'reach'`-style group; country with `Countries::NAMES` as choices), `guide()`, `examples()`.
  2. `fill()`: the language from the country (`Countries::language`). `groupKey()`: one prospect per row is the default (or group by company).
  3. `existing()`: the prospect (or a lead or client, through `Duplicates`) it already is, using `$run->dedupeKey($group->first())` to find what an earlier upload of the same file made. `check()`: `$group->fail()` per problem (and `Marketing::canEmail()` for a suppressed address, docs/CAMPAIGNS.md section 6). `create()`: insert the prospect with that dedupe key; return its id. `options()`: e.g. the owner (`Option::staff`) and the list it goes into.
  4. Add it to `Imports::SCHEMAS` and put `<livewire:hq.import.import-dialog schema="prospects" />` on the Outreach screen.
  5. Tests like `tests/Feature/Import/ImportEngineTest.php` (with its made-up `TeamsSchema`) keep the engine's behaviour pinned; a change C2 needs in the engine keeps them and `LeadImportTest` green.
- **Leads' schema** (`App\Support\Leads\LeadImportSchema`): columns as section 6 (organisation name, country, industry, organisation phone and email, city, address, contact name, job title, contact email and phone, deal role, owner email); rows with the same organisation name (case and spaces aside) are one lead; the organisation's columns come from the first row that fills each; each row's person is a contact (the first is the main one); `Leads::validate` per lead, `Contacts::rules` per contact on an existing lead. A dialling code typed as the country (+234) becomes the country. An email given twice in the file, or already someone's contact, makes the row *Needs fixing* naming the lead it belongs to. *Owner email* must be an active HQ account and the same on every row of a lead. New leads: `Leads::create` with the dialog's owner (or the row's owner email), source (*Excel import* or one of the by-hand sources), source details (the file's name to start with), next step (*Make first contact*, the next working day; it can be cleared), `contact_source` `import` and the first row's dedupe key on the "Lead added" line. Existing leads: left as they are; contacts they don't have (by email, or by name or phone without one) come in through `Contacts::add` with one `imported` line. Each owner the import gave leads to (not the person importing) gets one bell.
- **The list** (`LeadsRegister`): filters Owner (Mine, Everyone, No owner, each person who owns a lead), Status (any, open, each status), Next step (overdue, due today, in the next 7 days, no next step), Source, Country, a search (name, industry, city, the organisation's or a contact's name, email or phone). They live in the address (`?owner=all&status=open…`; Mine is the default and not written), *Clear filters* resets all but the owner, 100 rows then *Show more*, the CSV holds what the filters show. *My follow-ups* (with the overdue count) and *Import leads* (crm.create and crm.edit) are in the header.
- **The summary strip** (`App\Support\Leads\LeadNumbers`), for the owner filter (the other filters only narrow the list) and a period (this week, month (default), quarter, year, all time; Lagos calendar): **new leads** in the period (and this week, this month); **by status now** of the leads added in the period (a click filters the list); **conversion**: of the leads added in the period, those that are clients now (clients from before the revamp, `client_via = existing`, are left out); **open opportunity value**: each open opportunity's current proposal (the latest version not superseded, rejected or expired), **before VAT**, per currency, with how many open opportunities have one; **overdue follow-ups** and open leads with **no next step** (each a link). *By owner* adds the same figures per person and for leads nobody owns; a row filters the list to them.
- **My follow-ups** (`App\Livewire\Hq\Leads\FollowUps`): open leads (`LeadStatus::OPEN`) of the owner filter (Mine by default): Overdue (oldest first, red, "N days overdue"), Due today, In the next 7 days, Later, then **No next step** (flagged). Each shows the next step, the main contact with call and email links, and *Change next step* / *Set a next step* (crm.edit, through `NextStep::set`, so the timeline gets its line).
- **The morning bell**: `leads:morning-bell` (`--dry-run` lists who would be rung), scheduled on **working days at 07:30 Lagos time** (`routes/console.php`). Each person owning open leads with an overdue step, a step due today or no step gets **one bell a day** (`LeadsMorningBell::TITLE`, "Your follow-ups for today"): "2 overdue, 1 due today and 3 leads with no next step. First: …", opening My follow-ups. Owners whose account isn't active or who have no Leads access are skipped; unowned leads ring nobody.
- **Change to a contract**: `LeadOwner::bell($userId, $title, $message, $organizationId, ?string $link = null)`: an optional relative link for a bell about several leads (the morning bell opens My follow-ups; an import of several leads opens the list filtered to the owner). Without it, nothing changes.
- **Shared files touched**: `routes/web.php` (the follow-ups route, under the Leads group), `routes/console.php` (the schedule line), `components/layouts/hq.blade.php` (*My follow-ups* under *Leads* in the rail). `LeadTimeline::TYPES` and the timeline icon already had `imported`.
- **Choices made in PR 3** (the owner can change any of them):
  - **"Already exists" means the same name** (Duplicates' name key: "Kilo Logistics" is "KILO LOGISTICS LTD"), not "contains" and not a shared phone or email, so contacts never go to the wrong organisation. A contact email that is another lead's makes the row *Needs fixing* instead.
  - **An existing lead keeps its details and owner**; only its new contacts come in.
  - **Imported leads get a first next step** (*Make first contact*, the next working day), which the dialog can change or clear.
  - **Importing needs crm.create and crm.edit** (it adds contacts to existing leads too).
  - **Conversion is by cohort** (of the leads added in the period, how many are clients now), so a short period shows little conversion; the other usual reading (clients won in the period ÷ leads added in it) is a one-line change in `LeadNumbers::summary`.
  - **Open opportunity value is before VAT**, per currency, and doesn't follow the period (it is today's pipeline).
  - **The bell rings on working days only**, and counts leads with no next step too.
  - **Up to 1,000 rows and 5 MB a file**; uploads are kept a day at most.
- **Found in passing**:
  - Livewire 4: a public property and a method with the same name (`$show` and `show()`) make `wire:click="show"` call the property ("is not a function" in the browser, nothing sent, while the PHP tests pass). The dialog's filter is `$filter`.
  - Local servers on `hq.localhost` share cookies whatever the port, so another worktree's sign-in replaces yours. PR 3's launch config sets `SESSION_COOKIE=praxis_leads3_session`.
- **Tests**: `tests/Feature/Import/ImportEngineTest.php` (8, no database: the zip layer, also against ZipArchive where it exists; the template; what Excel writes; CSV; files refused and why; the preview's statuses and red cells and the fix file read back; importing once; kept files per person) with `TeamsSchema.php`; `tests/Feature/Leads/LeadImportTest.php` (3: the template; a file with good, bad and existing rows through the dialog, the owner email, the bells, the fix file, the same file twice, a fixed row adding its contact; who may import and the dialog's checks), `LeadListTest.php` (2: the filters from Mine, the address, the CSV; the strip, conversion, open value, the per-owner view), `FollowUpsTest.php` (2: the groups, the owner filter, changing a next step, view-only; the morning bell, once a day, its schedule). Full HQ suite locally: 770, only the 18 passkey/OpenSSL errors (pass in CI).
- **Local**: port **8121** (launch config `hq-leads-pr3`), databases `praxis_leads3` / `praxis_test_leads3`; `leads_demo.php` for the demo data.
- **Open owner decisions**: none needed to merge. To confirm after it is live: the choices above.
