# MyCity gaps behind the Ndali Group demo

For the MyCity team (GE, TB, LA, AJ). Written 30 October 2026.

What the JBS Praxis demo videos show that MyCity does not have yet, and what it would take to build each
piece for real, in MyCity's own conventions.

Sources: the demo runbook (`jbspraxis_project/docs/DEMO_RUNBOOK.md` §5 to §7), the demo build spec
(`ndali-demo/docs/DEMO_SPEC.md`, mainly §4.4, §4.5, §5 and §9), the demo contract
(`ndali-demo/packages/contract/demo.d.ts`), the story figures (`ndali-demo/data/story-figures.json`), and
MyCity's local copy as last updated on 13 October 2026 (`mycity-app`: `CLAUDE.md`, `README.MD`,
`docs/*.md`, `docs/guides/*.md`, `MILESTONES.md`, `REUSE_MAP.md`). Donor files were read in the local
`praxis-ls` and `pixie-girl-hub` checkouts.

This is a proposal. It changes nothing in `MILESTONES.md`, the SOW or the schedule. **Proposed** marks
something MyCity's documents do not describe. **Unsure** marks something they do not settle.

---

## 1. Summary

The videos are recorded from a demo copy of the MyCity build, set up for Ndali Group, a fictional FMCG
distributor (head office Ikeja, depots at Apapa and Ibadan, a branch in Douala; naira and FCFA). MyCity's
repository is a scaffold. Of 765 files under `src/`, two hold code (`shared/http/paged.js`,
`shared/errors.js`); of the contract, three (`common.js`, `organisation.js`, `index.js`). `app/` and
`migrations/` are empty and all 85 screens in `docs/PROGRESS.md` are Not started. So every capability below
is mocked in the demo, and "what MyCity has" means what its documents plan.

| § | Capability | Seen in | Closest MyCity home | In scope | Priority | Days |
|---|---|---|---|---|---|---|
| 3 | Answer pane (Overview, Table, Record, Sources) with source chips | every answer | Praxis AI · Ask #67, M10 | Partly | P1 | 19 |
| 4 | Refusal by role with an audit entry | 60 s shot 4 | Ask #67, M10; Audit Log #79 | Partly | P1 | 4 |
| 5 | Excel export of an answer's table with provenance | 60 s shot 3 | `shared/spreadsheet`, M0 | Partly | P1 | 4 |
| 6 | Attendance lateness by site with clock-in histogram | 60 s shot 2 | People · Attendance #25, M1 | Partly | P1 | 4 |
| 7 | Approvals across leave, expenses and purchase orders, one-click approve | 60 s shot 2 | `workflow` my approvals, Home #1, Expenses · Approvals #57 | Partly; no POs | P1 | 19 |
| 8 | Sales by depot and week | 3 min; brief | none (no sales, MILESTONES §6.1) | No | P1 | 12 |
| 9 | Stock cover in days by depot and category, stock-outs | 3 min; brief | none | No | P1 | 8 |
| 10 | Project budgets and actuals in NGN and XAF, % of budget | 60 s shot 3 | Expenses · Budgets #60, M8 | Partly | P2 | 7 |
| 11 | Morning brief with role-sensitive KPI tiles | 60 s shot 1 | Home · Overview #1, M1 | Partly | P2 | 4 |
| 12 | Calendar booking from a question via a pre-filled form | 60 s shot 5 | My Workspace · Calendar #6, M2 | Partly | P2 | 4 |
| 13 | Mailbox thread summary and a drafted reply with stock impact | 60 s shot 6 | Mailbox · Inbox #18, M1 | No | P2 | 7 |
| 14 | Receivables ageing and debtors over 60 days in NGN and XAF | 3 min | none | No | P2 | 9 |
| 15 | Bank cash position by currency | brief; 3 min | none | No | P2 | 6 |
| 16 | Audit log screen | 3 min | Settings · Audit Log #79, M9 | Planned | P2 | 3 |
| 17 | AI spend against a monthly naira cap | 3 min | `ai_governance`, M10 (no numbered screen) | Planned | P3 | 4 |
| 18 | Cached, reproducible answers | not visible | Praxis AI · History #68, M10 | No | P3 | 4 |
| | **Total** (P1 70, P2 40, P3 8; 106 without purchase orders) | | | | | **118** |

**Days** are person-days for one engineer who knows the codebase, on top of the MyCity milestone work each
item stands on. They include contract, migration, service, routes, manifest, tests, screens and the
PROGRESS, guide and README updates. They exclude review wait, staging walkthroughs, cleaning a client's data
and live connectors to a client's accounting system or bank. The prerequisites are MyCity's own scope and
are not costed here; the largest is the M10 assistant engine (`services/ai/assistant/`), scheduled for 14
to 18 December.

**The three to build first (27 days):** the answer pane (§3), the refusal with its audit entry (§4) and the
Excel export (§5). Every other capability reuses them. With them in place, and MyCity's own planned
attendance, leave, workflow and budgets behind them, the shape of shots 2 to 4 of the 60-second cut runs
live on MyCity's own data before any new domain exists: late staff with leave and expense approvals, budget
against actual by department with its Excel file, and the salary refusal. Sales and stock (§8, §9) are the
first new domain after that, because a distributor's COO asks about them before anything else.

**What is reusable across all sixteen:**

| Piece | First built in | Used by |
|---|---|---|
| The answer pane and its contract | §3 | §4 to §10, §12 to §15, §18 |
| The Excel builder (`shared/spreadsheet/build.js`, keep per-column currency when porting) | §5 | §6, §10, §14, any list export |
| The audit entry on refusal (one `emit()` path, one label set) | §4 | §5, §7 (approve from an answer), §12, §16 |
| The import engine (M0) as the feed for commercial data | §8 | §9, §10 (actuals), §14, §15 |
| KPI tile and chart primitives (`kpi-tile.tsx`, `chart.tsx`, M0) | §3 | §11 |
| A route with its prefill held by reference | §12 | §7, §13, every "Open in" action |
| Money with a currency | §8 | §9, §10, §11, §14, §15 |

**The contradictions that matter** (full list in §20): MyCity has no sales (MILESTONES §6.1, M1 definition of
done), is NGN only (MILESTONES §1.1, §13; `shared/money` "NGN only"), and keeps mail out of the AI
(`docs/guides/mailbox.md` §9). The commercial capabilities (§7's purchase orders, §8, §9, §14, §15, and the
second currency in §10 and §11) cannot go into MyCity's SOW as it stands. Where they are built is D1.

---

## 2. Conventions used in this document

- **Endpoints** are under `/api/v1/<basePath>`. Contract names follow `BACKEND_GUIDE.md` §3.1
  (`ThingListQuery`, `ThingPage`, `ThingCreate`). Every list takes `listQuery()` and returns `Paged()` from
  `packages/contract/schemas/common.js`: 25 rows, `page` only. Money is a 2-decimal string. Dates are ISO.
- **Permission keys** are README §5.2's 15 keys; actions are `view create edit delete approve export`. New
  keys are marked proposed; adding one changes `access.catalog.js`, README §5.2 and the approved screen
  inventory (MILESTONES §18) together (D10).
- **Migrations** go in the milestone's number block (README §6.3). Modules outside MyCity's scope have no
  block; this document uses a proposed 12000 to 12999 block (D10). `NNNN` means the next free number.
- **AI reads and routes** are entries in a module's `.ai.js` (`defineManifest`, BACKEND_STRUCTURE §14.3).
  Under A23 the assistant never writes: a write is a route to a screen with a prefill, and the person saves.
  Two manifest fields are proposed throughout, `label` (the status line) and `explain` (the Sources line):

```js
// src/modules/attendance/attendance.ai.js: shape from BACKEND_STRUCTURE §14.3; label and explain proposed
module.exports = defineManifest({
  module: "people",
  reads: [{
    key: "attendance.lateness",
    description: "Staff late or not yet in at a worksite on a date",
    label: "Reading attendance",                                          // proposed: status line
    explain: (params, result) => `Clock-ins on ${params.work_date} for ${result.page.total} staff`, // proposed
    params: c.attendance.LatenessQuery,                                   // listQuery(): pages at 25
    permission: ["people", "view"],
    run: (client, params, actor) => repo.lateness(client, params, actor.scope),
    cite: (row) => ({ type: "employee", id: row.employee_id }),
  }],
  routes: [{ intent: "raise a query for lateness", screen: "queries.new", prefill: c.queries.QueryCreate }],
});
```

- **Build plans** follow `CLAUDE.md`: contract first, then backend (migration, repo, service with `emit()`,
  routes with permissions, events, `.ai.js`, `.search.js`, tests), then frontend (screen, shared
  components), then the PROGRESS row, the feature guide and the README map.
- **Seen in** refers to the runbook's shots (§6.1: 1 brief, 2 late and approvals, 3 projects and Excel,
  4 refusal, 5 booking, 6 mailbox) and the 3-minute chapters (§6.2).

---

## 3. Answer pane with source chips · P1 · 19 days

**What the video shows.** Every assistant shot: the answer sits in the thread as 2 to 4 sentences with
source chips, while the pane on the right fills with KPI numbers and one chart titled as a sentence
(Overview), the rows behind the answer with Download Excel (Table), the item behind a chip (Record) and each
source with what was read and when (Sources). Empty tabs are disabled, not hidden, and the latest answer
replaces the pane.

**What MyCity has today.**

| | |
|---|---|
| Module | `assistant` (key `praxis_ai`); engine `services/ai/assistant/{orchestrator, retrieval, embeddings, answer, conversation.repo}.js`; carrier `shared/ai/{manifest, registry, catalogue-sync, authorise, cite}.js` |
| Screens | #67 Praxis AI · Ask (`ask`), #68 History (`history`) |
| Milestone, owner | M10, TB with GE. The AI foundation is an M0 item (GE), not ticked |
| Feature guide | None. `docs/guides/README.md` lists M1 guides only |
| State | Planned and documented (MILESTONES §15, BACKEND_STRUCTURE §14): the assistant answers from manifests and "every answer cites its source records". Every file is 0 bytes; `schemas/assistant.js` is empty. The pane itself (four views, chips, KPIs, charts) is not described anywhere in MyCity's documents. Donor: praxis-ls `client/src/features/ai/right-pane.tsx` (696 lines: Canvas, Table, Record, Sources), `workspace.tsx` (717), `client/src/components/ai/{turn.tsx, grounding.ts, thread.ts, composer.tsx}`, and `src/services/ai/answer-sources.js` (309), which becomes `shared/ai/cite.js`. The donor's Canvas tab (long drafts) becomes Overview; drafts go to the composer instead (§13) |

**What is missing.**

- **Data model** (M10 block). `0095_ai.sql` has the run ledger but no conversation tables, though
  `conversation.repo.js` and `assistant.history.js` exist.
  - `assistant_conversation`: `assistant_conversation_id`, `user_id`, `title`, `created_at`, `updated_at`.
  - `assistant_turn`: `assistant_turn_id`, `assistant_conversation_id`, `question`, `asked_at`, `status`
    (`answered`, `refused`, `no_answer`, `failed`), `answer` jsonb (the validated `AssistantAnswer`),
    `run_id` (to `ai_run`), `model`, `prompt_version`.
  - `assistant_turn_read`: `assistant_turn_read_id`, `assistant_turn_id`, `read_key`, `module`, `params`
    jsonb, `page`, `total`, `read_at`, `outcome` (`read`, `refused`, `masked`). Sources, Record and Excel
    reuse it, and a table can fetch page 2 without calling the model.
- **API.**

| Method and path | Contract | Permission · scope | Paged |
|---|---|---|---|
| `POST /api/v1/assistant/ask` | `AskBody` (`question`, `assistant_conversation_id?`, `context?`) → `AssistantAnswer` | `praxis_ai` view · own | no |
| `GET /api/v1/assistant/conversations` | `ConversationListQuery` → `ConversationPage` | `praxis_ai` view · own | yes |
| `GET /api/v1/assistant/conversations/:id/turns` | `TurnListQuery` → `TurnPage` | own | yes |
| `GET /api/v1/assistant/turns/:id/tables/:table_key` | `AnswerTableQuery` → `AnswerTablePage` | own, plus the read's own permission | yes |
| `GET /api/v1/assistant/turns/:id/records/:record_key` | → `AnswerRecord` | own, plus the owning module's view | no |

  `AssistantAnswer` carries what the demo's `Answer` carries: `text[]`, `chips[]`, `inline` (approvals, a
  route form, a mail draft), `refusal`, `pane` (`default_tab`, `heading`, `overview` with `kpis[]` and one
  `chart`, `tables[]`, `records[]`, `sources[]`) and `actions[]`. Actions are routes and downloads only.
- **Screens.** Ask #67: thread, composer and pane; on desktop three regions with no layout shift when the
  first answer arrives, on mobile the pane is a sheet over the thread (gate 11). History #68 re-opens a turn
  from the stored answer. Shared components (FRONTEND_GUIDE §9.4 is still to be written): `AnswerPane`,
  `SourceChip`, `KpiTile` with a settling number, `AnswerChart` (bar, horizontal bar, grouped, stacked,
  line; a reference line; highlighted rows), `AnswerTable`, `RecordView`, `SourceList`, `StatusLines`.
- **Permissions.** `praxis_ai` view to ask. Every read inside an answer goes through `authorise.js` against
  its own key and scope, with field masks applied before the model sees a row (BACKEND_STRUCTURE §14.3). The
  pane never shows a row the person could not open on the owning screen.
- **AI grounding.**
  - Numbers come from reads, never from the model. `answer.js` builds KPI values, chart data, tables and
    records in code from the read results. The model writes the sentences and picks which reads to show
    and a chart type from an allow-list, through `services/ai/structured.js` with `AssistantAnswer` as the
    schema. A figure check fails the answer if a number in the text is not in a read result (the demo's
    `build-answers.js` does the same against `story-figures.json`).
  - Sources come from `cite.js`, from what was read, not from the prose. The donor's `answer-sources.js`
    header explains why prose-derived citations never rendered there.
  - A table holds one page of a read and shows the true total ("25 of 312"). When the total exceeds the
    page, sorting re-runs the read with a sort parameter; it never sorts 25 rows in the browser and calls
    them the whole result (FRONTEND_GUIDE §4).
  - Status lines come from the proposed `label`, Sources lines from `explain`.

**Build plan.**

1. Contract: `packages/contract/schemas/assistant.js` with `AskBody`, `AssistantAnswer`, `AnswerPane`,
   `Kpi`, `Chart`, `AnswerTable`, `AnswerRecord`, `Source`, `SourceChip`, `AnswerAction`, `Refusal`,
   `Conversation`, `Turn` and their pages. Port the shapes from `demo.d.ts`; use `uuid` ids plus `number`
   where a record has one, and money strings with a `currency` (D3). `npm run gen:contract`.
2. Backend: `migrations/10NNN_assistant_conversations.sql` (M10 block); `conversation.repo.js` through
   `pageQuery`; `answer.js` (assembly and figure check); `orchestrator.js` records each read in
   `assistant_turn_read`; optional `label` and `explain` in `defineManifest()`;
   `modules/assistant/assistant.{routes, controller, service, repo, history}.js`, the service saving the turn
   and emitting `assistant.answered` in one transaction; `assistant.search.js` over the caller's own
   conversation titles or `// search:none` with a reason.
3. Frontend: Ask #67, History #68, the shared components, the mobile sheet. Status lines stream while reads
   run: the donor has an `askStream` endpoint; Socket.io room `user:<id>` is the MyCity alternative. Decide in
   the guide.
4. Records: `docs/guides/assistant.md` from `_TEMPLATE.md`; PROGRESS #67 and #68; README map.

**Estimate.** 19 days: contract 1.5, migration and conversation repo 1.5, answer assembly and figure check 4,
manifest fields and read recording 1.5, pane and components 7, mobile sheet 1.5, tests 2. Assumes the M10
orchestrator already answers from manifests and the M0 design primitives exist.

**Priority.** P1. The pane is in every shot, and the first thing a prospect does after the video is type a
question of their own. Everything below lands in it.

---

## 4. Refusal by role with an audit entry · P1 · 4 days

**What the video shows.** 60-second shot 4: a sales associate asks "Show me everyone's salaries" and gets a
calm sentence with a lock icon saying they have no permission and the request was recorded, with the line
"Audit entry 118204 · 08:31"; the 3-minute cut then shows the entry in the audit log.

**What MyCity has today.**

| | |
|---|---|
| Module | `shared/ai/authorise.js` (M0, from praxis-ls `action-authz.js`: fail closed, a read with no declared permission does not run); `payroll`, `salary_structure` (key `payroll`); `shared/rbac/field-mask.js`; `audit_ledger` through `emit()` |
| Screens | #67 Ask; #79 Settings · Audit Log |
| Milestone, owner | M10 (TB, GE); M0 for `authorise` and the ledger (GE) |
| Feature guide | None |
| State | The refusal is planned: M10's definition of done says a user without expense permission who asks about expenses "is told it is not available to them". An audit entry for it is not described: `emit()` audits writes, and `ai_run` records each AI call with its outcome. Files empty |

**What is missing.**

- **Refusal versus no data.** If the catalogue the model sees is filtered to the caller's grants, the model
  never learns a payroll read exists and answers "I couldn't find that". Proposed: retrieval ranks the full
  catalogue (keys and descriptions, never data); when the best reads are ones `authorise.js` refuses, the
  orchestrator returns a refusal and makes no data call. With partial grants (names visible, salary masked)
  it answers with what is allowed and says what was masked.
- **Data model.** No new table: `assistant_turn.status = 'refused'`, `assistant_turn_read.outcome = 'refused'`.
- **Audit.** The service emits `assistant.refused` (entity `assistant_turn`; after: question, refused read
  keys, reason) in the transaction that saves the turn. The short reference shown in the answer is D8.
- **API.** None new. `AssistantAnswer.refusal` = `message`, `reason`, `audit_entry` (`audit_id`, `reference`, `at`).
- **Screens.** A refusal block in the thread (lock icon, sentence, entry reference linking to #79 for people
  with `settings` view); Sources lists "Payroll: not read, no permission".
- **Permissions.** `payroll` view and the salary field grant are what is checked.
- **AI grounding.** `payroll.ai.js` and the salary structure reads declare `permission: ["payroll", "view"]`
  and mark salary fields restricted. The refusal names the data ("salary data"), not the read key.

**Build plan.**

1. Contract: `Refusal` in `assistant.js`.
2. Backend: refusal branch in `orchestrator.js`; `emit("assistant.refused")` in `assistant.service`; its
   audit label (§16); an integration test that a role without `payroll` view is refused by `authorise.js`
   (not by the prompt), that an audit row exists and that no vendor call was made.
3. Frontend: `RefusalBlock`.

**Estimate.** 4 days. Assumes §3 and `authorise.js`.

**Priority.** P1. "Can a junior see salaries?" is the first security question a COO or CFO asks, and the
answer has to be shown live, not described.

---

## 5. Excel export of an answer's table with provenance · P1 · 4 days

**What the video shows.** 60-second shot 3: Download Excel on the projects answer produces a workbook with a
title row, the question, the sources, "Generated Tuesday 3 November 2026, 08:23 (WAT)", ₦ and FCFA number
formats, a frozen header and a total row; the receivables answer (§14) offers the same.

**What MyCity has today.**

| | |
|---|---|
| Module | `shared/spreadsheet/{build, cells, csv, day-first-cells, parse}.js` (M0), from praxis-ls `services/spreadsheet/` ("MyCity brand header; day-first cells", BACKEND_STRUCTURE §3); `attendance.export.js` (M1) |
| Screens | #67 Ask (the Table tab) |
| Milestone, owner | M0 for the builder (GE); M10 for the assistant (TB) |
| Feature guide | None |
| State | The builder is planned as built in M0 (BACKEND_STRUCTURE §12); files empty. Exporting an answer is not described. Donor: praxis-ls `src/modules/ai/assistant/assistant.export.js` (132 lines, one sheet per answer table) over `services/spreadsheet/build.js` (554 lines: typed money cells with the currency in the header, 0 decimals for XAF, day-first dates, cover sheet). The `export` action exists in RBAC |

**What is missing.**

- **Where the rows come from.** The donor takes the rows from the browser and reads nothing. Proposed: build
  from the stored turn (§3), so the question, sources and generated time are the server's record, not client
  input.
- **More than 25 rows.** An answer table holds one page. The export either says "first 25 of 312" or re-runs
  the read page by page in a worker job up to a ceiling (D9).
- **The file.** Title row, question, sources with read times, generated date and time (day-first, WAT), the
  person and role, the turn reference, a staging mark on staging (A4: marked, never crippled); typed cells
  (money with ₦ or FCFA format, percent, day-first dates), frozen header, widths, a total row where the table
  declares one. Restricted fields stay masked.
- **Audit.** `emit("assistant.exported")` with turn, table, row count and the file's sha256. The file is not
  kept unless D9 says so.
- **API.** `GET /api/v1/assistant/turns/:id/tables/:table_key/export` returns the xlsx. Every MyCity route
  declares a `response`; how a file response is declared is not shown in MyCity's documents (unsure).
- **Permissions.** `praxis_ai` export, plus `export` on the key of every module the rows came from
  (`expenses` for budget rows).
- **Screens.** Download Excel in the Table tab, then a quiet toast.
- **AI grounding.** None at export time; the model is not called.

**Build plan.**

1. Contract: `AnswerTableExportParams`.
2. Backend: `assistant.export.js` adapting `AnswerTable` to `shared/spreadsheet/build` with the provenance
   block; the route with both permission checks; the worker job if D9 chooses it; the emit; a test that
   opens the file and checks title row, question, sources and generated time.
3. Frontend: button, download, toast.

**Estimate.** 4 days. Assumes `shared/spreadsheet` is ported in M0 and keeps the donor's per-column currency
(MyCity's port note does not mention it).

**Priority.** P1. Finance people ask for the spreadsheet behind a number within a minute, and an export that
carries its question and sources is what makes an answer auditable.

---

## 6. Attendance lateness by site with a clock-in histogram · P1 · 4 days

**What the video shows.** 60-second shot 2: "Who is late at Apapa today, and what's waiting on me?" returns
7 late (5 clocked in late, 2 not yet in), a chart of clock-ins per 10-minute slot from 06:30 to 08:15 with
late slots in warn, and a table of the 7 with role, clock-in time and minutes late.

**What MyCity has today.**

| | |
|---|---|
| Module | `attendance` (`.rules`, `.lateness`, `.clock-guard`, `.reconcile`, `.auto-query`, `.analytics`, `.export`, `.calendar`, `.ai`), `worksites`, `devices` (key `people`) |
| Screens | #25 People · Attendance, #28 Worksites, #9 ESS · My Attendance, #73 Settings · Attendance Policy (M9), Home tile `attendance_today` (#1) |
| Milestone, owner | M1, LA |
| Feature guide | `docs/guides/attendance.md`: listed, not written |
| State | Planned in detail (MILESTONES §6.2, REUSE_MAP §2.2): punches with device time, geofence per worksite, per-day working week with `grace_minutes` (A22), auto-query on lateness (M3). Files empty. The donor `attendance.rules.js` counts lateness from the start time, not from the end of grace (11 minutes late with 10 minutes' grace is 11), as the demo does. Its analytics bucket by person and day, not by time of day |

**What is missing.**

- **Hours by worksite.** Ndali's depots start at 07:00, head office at 08:00 and Douala at 07:30. MyCity's
  week is per day (A22) with one `work_start`; per-worksite hours are not described (D11). Proposed:
  `worksite_hours` (`worksite_hours_id`, `worksite_id`, `weekday`, `working`, `start_time`, `end_time`,
  `grace_minutes`), owned by the `settings` module beside `working_week`, effective-dated through
  `shared/db/effective-dated`, falling back to the working week.
- **Who is expected today:** active employees assigned to the worksite, on a working day, on-site or hybrid,
  not on approved leave. Without it "not yet in" cannot be counted.
- **API.**

| Method and path | Contract | Permission · scope | Paged |
|---|---|---|---|
| `GET /api/v1/attendance/lateness` | `LatenessQuery` (`worksite_id`, `work_date`) → `LatenessPage` | `people` view · team, all | yes |
| `GET /api/v1/attendance/clock-in-slots` | `ClockInSlotQuery` (`worksite_id`, `work_date`, `slot_minutes`) → `ClockInSlotPage` | `people` view · team, all | yes, at most 25 slots |
| `PUT /api/v1/settings/worksite-hours/:worksite_id` | `WorksiteHoursUpdate` → `WorksiteHours` | `settings` edit | no |

  The histogram is an aggregate; returning it in the page envelope keeps it inside `assertPage()` when the
  AI reads it.
- **Screens.** A "today by worksite" view on #25 with the slot chart; per-worksite hours on #73; the Home
  tile gains a sub-line ("7 late at Apapa").
- **Permissions.** `people` view with team or all scope; a depot manager sees their own team.
- **AI grounding.** Reads `attendance.lateness` (cites the employee) and `attendance.clock_in_slots` (cites
  the worksite and date), label "Reading attendance". Route: "raise a query for lateness" to Queries, new,
  prefilled with the employee and dates (M3).

**Build plan.**

1. Contract: `attendance.js` adds `LatenessQuery`, `LatenessRow`, `LatenessPage`, `ClockInSlotQuery`,
   `ClockInSlot`, `ClockInSlotPage`; `settings.js` adds `WorksiteHours`, `WorksiteHoursUpdate`.
2. Backend: `migrations/1NNN_worksite_hours.sql` (M1 block); repo reads through `pageQuery` honouring scope;
   `attendance.rules` resolves hours by worksite; `settings.service` saves hours in a transaction and emits
   `settings.worksite_hours_changed`; routes; the two manifest reads; tests for the grace boundary, not yet
   in, and leave excluded.
3. Frontend: the #25 view with the shared chart, the hours editor on #73, the tile sub-line.

**Estimate.** 4 days on top of M1 attendance: worksite hours 1.5, expected set and lateness 1, slots 0.5,
AI reads and tile 0.5, tests 0.5.

**Priority.** P1. It is the first question of the 60-second cut, every depot-based COO lives with it, and it
is the cheapest live proof on a module MyCity already plans.

---

## 7. Approvals across leave, expenses and purchase orders · P1 · 19 days (7 without POs)

**What the video shows.** 60-second shot 2, second half: the same answer lists a leave request (LV-0412), an
expense claim (EXP-2291) and a purchase order (PO-1187) waiting on the COO, who approves the leave request
with one click inside the answer; the brief shows "Needs you · 3 approvals".

**What MyCity has today.**

| | |
|---|---|
| Module | `workflow` (M0: definitions, act on an instance, my approvals; Pixie's engine, A11), `leave` (`.balance`, `.clash`), `expenses`, `requisitions` (M8) |
| Screens | #1 Home · Overview ("Needs you", the same for everyone, FRONTEND_GUIDE §8); #26 People · Leave and #12 ESS · My Leave (M1, AJ); #57 Expenses · Approvals and #58 Requisitions (M8, AJ); #72 Settings · Approvals configures chains (M9, GE) |
| Milestone, owner | M0 (GE), M1 (AJ), M8 (AJ) |
| Feature guide | `ess.md` listed, not written; none for workflow, leave or expenses |
| State | Planned. The expense chain is settled (requester, line manager, Operations, requisition, CEO, disbursed; MILESTONES §13), numbered `EXP-0001`. A11 names M3, M5, M6 and M8 as the engine's consumers; leave (M1) is not named, so whether leave approvals run on the engine is unsure. `workflow` sits under the `settings` key (README §5.2); "my approvals" presumably checks ownership like own notifications, but the README does not say so (unsure). No purchase orders: a requisition batches approved staff requests for the CEO and is not an order to a supplier. No cross-module approvals screen is among the 85 |

**What is missing.**

- **One inbox.** A read returning each waiting instance with `workflow_instance_id`, `entity_type`,
  `entity_id`, `number`, `title`, `requester`, `amount`, `currency`, `submitted_at`, `stage_key`,
  `can_decide`. Each owning module registers a resolver for its display fields, as tasks and notifications
  use the route map.
- **Leave on the engine.** A `leave.request` definition so leave lands in the same inbox. Proposed numbering
  scheme `leave_request` (`LV-0001`) if a human reference is wanted.
- **The decision.** Checked with `requirePermission(<owning key>, "approve")`: `people` for leave,
  `expenses` for expenses, the new key for orders. Outbox handlers notify the requester (M6 transports).
- **Approve inside an answer.** The assistant only reads the inbox. The button in the answer is the shared
  approvals component calling the decision endpoint under the person's own grant; no AI is in the write
  path. MILESTONES §15 says the assistant "links the user to the module to make a change", so whether an
  inline decision fits A23 is D6.
- **Purchase orders** (new, out of MyCity's scope). `supplier` (`supplier_id`, `name`, `email_domain`,
  `currency`); `purchase_order` (`purchase_order_id`, `number` `PO-0001`, `supplier_id`, `worksite_id`,
  `currency`, `total` numeric(18,2), `status`, `requested_by`, `needed_by`); `purchase_order_line`
  (`purchase_order_line_id`, `purchase_order_id`, `sku_id` or `description`, `quantity`, `unit_price`,
  `amount`). A `purchase_order.approval` definition with amount conditions. Screens Procurement · Purchase
  Orders and Suppliers (inventory change). Pattern donor outside MyCity's reuse map: praxis-ls
  `src/modules/procurement/purchase_order/` (part of 1,974 lines; XAF, multi-tenant, not audited for this).
- **API.**

| Method and path | Contract | Permission · scope | Paged |
|---|---|---|---|
| `GET /api/v1/workflow/my-approvals` | `MyApprovalQuery` → `MyApprovalPage` | ownership (unsure, see above) | yes |
| `POST /api/v1/workflow/instances/:id/decisions` | `WorkflowDecisionBody` (`decision`, `reason`) → `WorkflowInstance` | owning key · approve | no |
| `GET /api/v1/purchase-orders`, `GET /api/v1/purchase-orders/:id` | `PurchaseOrderListQuery` → `PurchaseOrderPage`; → `PurchaseOrder` | `procurement` view (proposed) | yes |
| `POST /api/v1/purchase-orders` | `PurchaseOrderCreate` → `PurchaseOrder` | `procurement` create | no |

- **Screens.** One `ApprovalList` component for Home "Needs you", the answer and each module's approvals tab;
  the Record tab shows the leave request with balance after approval and cover (`leave.balance`,
  `leave.clash`); PO list and detail.
- **AI grounding.** `workflow.ai.js` read `approvals.mine` (own; cites each instance's record screen);
  `leave.ai.js` read `leave.requests.get`; `purchase_orders.ai.js` reads `purchase_orders.list` and `.get`.
  Routes: "approve a leave request" to People · Leave with the request open; "raise a purchase order" to the
  PO form, prefilled.

**Build plan.**

1. Contract: `workflow.js` (`MyApprovalQuery`, `MyApproval`, `MyApprovalPage`, `WorkflowDecisionBody`,
   `WorkflowInstance`), `leave.js` (`LeaveRequest` with `number`), new `purchase_orders.js` and
   `suppliers.js`.
2. Backend: the leave definition and numbering scheme in the M1 block; resolvers; the decision route; the PO
   module in the README §5.1 shape, `procurement` in `access.catalog.js`, its migration in the new block
   (D10), scheme `purchase_order` in `schemes.js`, its definition, emits, manifests and search providers;
   tests.
3. Frontend: `ApprovalList`, the inline approve with toast, PO screens.

**Estimate.** 19 days: inbox and resolvers 2, leave on the engine 2 (0 if leave already uses it),
inline decision and notification 1.5, AI reads and tests 1.5, purchase orders 12. Assumes the M0 engine and
M8 expenses.

**Priority.** P1. "What is waiting on me", decided in one click, is the moment a COO recognises their own
morning, and it is in the 60-second cut. Purchase orders can follow the inbox.

---

## 8. Sales by depot and week · P1 · 12 days

**What the video shows.** The 3-minute chapter "Sales and stock by depot" and the brief's first tile: last
week's sales per depot (Lagos ₦168.2m, Ibadan ₦94.7m, Douala 112.5m FCFA, each against the previous week)
and yesterday's sales (₦58.6m, with Douala's 21.4m FCFA under it).

**What MyCity has today.**

| | |
|---|---|
| Module | None. Closest: `dashboard` tiles (`kpi-catalog/tiles/business.js`, M1, TB) and the M0 import engine (`shared/import`, `modules/imports`) |
| Screens | None. Worksites #28 is the nearest thing to a depot list |
| Milestone, owner | Outside MyCity's scope |
| Feature guide | None |
| State | Excluded by decision: "There is no sales in this product. Partnerships are not sales and must not be modelled as them" (MILESTONES §6.1); "no sales anywhere" (M1 definition of done); REUSE_MAP §2.1 refuses `sales_procurement.js` "for later". praxis-ls `sales/` is a CRM (leads, quotes), not distributor sales; its `kpi_catalog/money.js` has a `revenue` tile pattern |

**What would be new.**

- **What "sales" is** (D5): (a) a system of record (orders, invoices, deliveries, returns: an order-to-cash
  product, not estimated here) or (b) a read model loaded daily from the client's ERP or distributor system
  through the import engine. This plan is (b).
- **Data model.** `sku` (`sku_id`, `code`, `name`, `brand`, `category`, `pack_size`, `active`); `outlet`
  (`outlet_id`, `code`, `name`, `type`, `region`, `worksite_id`); `sales_daily` (`sales_daily_id`,
  `worksite_id`, `sku_id`, `outlet_id`, `sale_date`, `quantity`, `net_amount` numeric(18,2), `currency`,
  `import_batch_id`), unique on worksite, SKU, outlet and date. `worksite` gains `currency` and `holds_stock`.
- **Importer.** `sales.import.js` on `shared/import` (match keys, coercion, preview, revert), one batch per
  day per source.
- **Rules.** NGN and XAF are never added. Depots are closed on Sundays. "Last week" is Monday to Saturday in
  Africa/Lagos (Douala is also on WAT).
- **API.**

| Method and path | Contract | Permission · scope | Paged |
|---|---|---|---|
| `GET /api/v1/sales/by-worksite` | `SalesByWorksiteQuery` (`from`, `to`) → `SalesByWorksitePage` (amount, currency, change on the previous period) | `sales_stock` view (proposed) | yes |
| `GET /api/v1/sales/by-category` | `SalesByCategoryQuery` (`worksite_id?`, `from`, `to`) → `SalesByCategoryPage` | `sales_stock` view | yes |
| `GET /api/v1/sales/daily` | `SalesDailyQuery` → `SalesDailyPage` | `sales_stock` view | yes |

- **Screens.** New module Sales & Stock, tab Sales (`sales`). This changes the approved inventory
  (MILESTONES §18.2).
- **Permissions.** Proposed key `sales_stock` for `sales`, `stock`, `skus`, `outlets`.
- **AI grounding.** `sales.ai.js` reads `sales.by_worksite`, `sales.by_category`, `sales.daily`, citing the
  worksite and period as a report source, label "Reading sales by depot". No routes: nothing to write.
- **KPI tile** `sales_yesterday` (§11).

**Build plan.**

1. Contract: `sales.js`, `skus.js`, `outlets.js`, re-exported from `index.js`.
2. Backend: module folders per README §5.1; the key in `access.catalog.js`; migration in the new block;
   importer; reads through `pageQuery`; routes; manifest; search providers for SKUs and outlets; tests.
3. Frontend: the Sales tab with the shared chart and table.

**Estimate.** 12 days: module scaffold and key 1, schema 1, importer 2.5, reads 2, AI and tile 1, screen 3,
tests 1.5. Assumes the client sends a daily file; a live connector is extra.

**Priority.** P1. A distributor's COO asks how the depots sold before anything else, and the brief's first
tile promises it.

---

## 9. Stock cover and stock-outs · P1 · 8 days

**What the video shows.** The same chapter and the brief's third tile: days of stock cover by depot and
category, with Apapa noodles at 3.1 days in warn, and 5 lines out of stock (3 at Apapa, 2 at Ibadan); the
booking note (shot 5) and the mail reply (shot 6) quote these figures.

**What MyCity has today.**

| | |
|---|---|
| Module | None. MyCity holds no stock |
| Screens | None. Worksites #28 (depots) is the nearest |
| Milestone, owner | Outside MyCity's scope |
| Feature guide | None |
| State | Not planned. Pattern donors outside the reuse map: praxis-ls `src/modules/wms/inventory/` and `wms/cycle_count/` (1,492 lines across `wms/`; XAF, multi-tenant, not audited for this) |

**What would be new.**

- **Data model.** `stock_snapshot` (`stock_snapshot_id`, `worksite_id`, `sku_id`, `snapshot_date`,
  `on_hand_quantity`, `import_batch_id`), unique on worksite, SKU and date; importer `stock.import.js`.
- **Rules** (to agree with each client): cover in days is on-hand quantity divided by average daily units
  sold over the last 28 selling days at that worksite; a stock-out is an active SKU with nothing on hand at
  a worksite that holds stock. Depends on §8.
- **API.** `GET /api/v1/stock/cover` (`StockCoverQuery`: `worksite_id?`, `category?` → `StockCoverPage`) and
  `GET /api/v1/stock/outs` (`StockOutQuery` → `StockOutPage`), both `sales_stock` view, paged.
- **Screens.** Sales & Stock · Stock (`stock`).
- **AI grounding.** `stock.ai.js` reads `stock.cover` and `stock.outs`, citing worksite and SKU, label
  "Checking stock cover". Route: "book a stock count" to a new calendar event (§12).
- **KPI tile** `stock_outs` (§11).

**Build plan.**

1. Contract: `stock.js`.
2. Backend: migration in the new block, importer, cover and stock-out rules in `stock.cover.js`, reads,
   routes, manifest, tests.
3. Frontend: the Stock tab.

**Estimate.** 8 days: snapshot and importer 2.5, cover and stock-out rules 1.5, reads, AI and tile 1.5,
screen 1.5, tests 1. Assumes §8.

**Priority.** P1. Stock-outs cost a distributor sales every day; the number drives the brief, shot 5 and
shot 6.

---

## 10. Project budgets and actuals in NGN and XAF · P2 · 7 days

**What the video shows.** 60-second shot 3: "Which projects are over budget?" shows 4 over budget, ₦38.2m
over in total, the worst at 109.0%, a bar chart of percentage of budget spent for 8 projects against a 100%
line, the table, one project's record and Open in workspace; one project is in FCFA.

**What MyCity has today.**

| | |
|---|---|
| Module | `budgets` (`.variance`), `expenses` (`.budget-check`), `expense_categories`, `disbursements` (key `expenses`) |
| Screens | #60 Expenses · Budgets |
| Milestone, owner | M8, AJ |
| Feature guide | None |
| State | Planned: "department, project and category budgets with variance" (net-new) and "budget, actual, variance, remaining, committed and disbursed" (M8 definition of done); handed over empty (A25). "What is budget versus actual this month" is one of M10's six target questions. NGN only (MILESTONES §13). No project record is defined anywhere; whether a project is a record or a budget dimension is unsure. Files empty |

**What is missing.**

- **A project record:** `project` (`project_id`, `number` `PRJ-0001` with a proposed scheme `project`,
  `name`, `worksite_id`, `lead_employee_id`, `currency`, `status`, `start_on`, `end_on`).
- **Budget lines that point at a project:** `budget_line` (`budget_line_id`, `dimension`
  (`department`, `project`, `category`), `dimension_id`, `period`, `amount`, `currency`). Requests and
  requisition lines carry `project_id`, so committed and disbursed amounts roll up.
- **Actuals for capital projects** are mostly supplier payments, not staff requests: they need purchase
  orders (§7) and supplier invoices, or an import of actuals from the accounting system (D5).
- **XAF.** A project keeps its own currency. Totals are per currency; "% of budget" is currency-free, so
  projects can be ranked across currencies.
- **API.** `GET /api/v1/budgets/projects` (`ProjectBudgetQuery`: `status?`, `over_only?` →
  `ProjectBudgetPage` with budget, committed, actual, over, `pct_spent`, status, lead) and
  `GET /api/v1/budgets/projects/:id`, `expenses` view, paged.
- **Screens.** A Projects view in #60 (or a new tab, an inventory change); the project record.
- **AI grounding.** `budgets.ai.js` reads `budgets.projects.variance` and `budgets.projects.get`, citing the
  project. Route: "open the project" to #60 with it open.

**Build plan.**

1. Contract: `budgets.js` gains the project shapes.
2. Backend: an M8-block migration for `project` and the `project_id` columns; numbering scheme; variance
   read; manifest; search provider for projects; tests.
3. Frontend: the Projects view and record.

**Estimate.** 7 days: project record and numbering 1.5, budget lines and tagging 1.5, variance read 1, AI
and record 1.5, screen 1.5. Assumes M8 budgets; actuals from supplier invoices are not included.

**Priority.** P2. It is in the 60-second cut, but the pane and Excel it shows off are P1 already, and M8
covers department and category budgets; the gap is the project record and the second currency.

---

## 11. Morning brief with role-sensitive KPI tiles · P2 · 4 days

**What the video shows.** 60-second shot 1 (07:45): the workspace greets the COO by first name, with the
date, and settles four tiles (sales yesterday ₦58.6m, cash today ₦412.8m and 96.4m FCFA, 5 stock-outs, 125
of 140 staff present) above a "Needs you · 3 approvals" line; the sales associate's brief is different.

**What MyCity has today.**

| | |
|---|---|
| Module | `dashboard` with `kpi-catalog/{index, eligibility, guards}.js` and `kpi-catalog/tiles/{people, work, business, finance}.js` (key `home`) |
| Screens | #1 Home · Overview |
| Milestone, owner | M1, TB |
| Feature guide | `docs/guides/home.md`: listed, not written |
| State | Planned (MILESTONES §6.1, REUSE_MAP §2.1, FRONTEND_GUIDE §8): a tile declares its id, grant gate and value source; eligibility is computed at read time, so a tile never grants rights; each role sees its own tiles and main chart; "Needs you" is the same for everyone. Ported tiles include `attendance_today`; MyCity's net-new tiles are partnership pipeline, pending expenses, budget versus actual, pending documentation, upcoming confirmations and reviews. "No sales anywhere" (M1). Files empty |

**What is missing.**

- **Tiles** `sales_yesterday`, `cash_today` and `stock_outs` (they need §8, §9 and §15), in a tiles file that
  ships only with those modules and is gated on `sales_stock` and `finance` (D4).
- **Two currencies on one tile:** a value and a sub-line ("₦58.6m" over "Douala 21.4m FCFA"), never one
  converted figure.
- **Provenance per tile:** an as-of time and a source ("Bank balances at 07:30"), so the brief shows its
  sources like an answer does.
- **Role configuration** for the COO's four tiles: praxis-ls `security/role_kpi/` (398 lines) is already in
  M2's reuse list.
- **API** (proposed; MyCity has not named the dashboard routes): `GET /api/v1/dashboard/overview` →
  `Overview` (`greeting`, `date`, `tiles[]`, `needs_you` as the first page of §7's inbox, `chart`,
  `sources[]`), `home` view.
- **AI grounding.** None: tiles are computed in code. The scaffold has no `dashboard.ai.js`, so MyCity may
  intend `// ai:none` there (unsure).

**Build plan.**

1. Contract: `dashboard.js` (`KpiTile` with `value`, `format`, `currency`, `sub`, `tone`, `as_of`, `source`;
   `Overview`).
2. Backend: the commercial tiles file; provenance in the catalogue's value sources; the overview service;
   tests that a role without `sales_stock` never sees a sales tile.
3. Frontend: greeting, date, four tiles, the Needs you line.

**Estimate.** 4 days on top of M1 Home and the modules behind the tiles: commercial tiles 1.5, two-currency
tile 1, provenance 1, tests 0.5.

**Priority.** P2. It opens the video and prospects expect their own numbers on it, but it only shows what §6
to §9 and §15 compute; build it as they land.

---

## 12. Calendar booking from a question via a pre-filled form · P2 · 4 days

**What the video shows.** 60-second shot 5: "Book a stock-count review at Apapa tomorrow at 10" returns a
pre-filled event form (Wednesday 4 November 2026, 10:00 to 11:00, the depot's meeting room, four
attendees, a note citing the stock-outs); the COO presses Save and the event appears in the week view.

**What MyCity has today.**

| | |
|---|---|
| Module | `calendar` (key `workspace`), `services/calendar/ics` |
| Screens | #6 My Workspace · Calendar |
| Milestone, owner | M2, AJ |
| Feature guide | None |
| State | The closest match to MyCity's plan. A23 routes writes rather than executing them; manifests carry `routes: [{ intent, screen, prefill }]` (BACKEND_STRUCTURE §14.3); "every screen that a write can be routed to accepts its prefill from the URL" (FRONTEND_GUIDE §8); asked to create a task, the assistant opens the task screen (M10 definition of done). Files empty. Donor: praxis-ls `client/src/features/workspace/calendar/event-dialog.tsx` (451 lines) adds participants only after an event exists, and defaults to Africa/Douala |

**What is missing.**

- **The route.** `calendar.ai.js`: `routes: [{ intent: "book a meeting", screen: "calendar.events.new",
  prefill: c.calendar.EventCreate }]`, plus the reads that fill it: busy times, the worksite's address,
  employees by role at a worksite (organisation reads).
- **A prefill resolver:** "tomorrow at 10" in Africa/Lagos; a worksite to a location; roles to people.
- **Attendees on create.** `POST /api/v1/calendar/events` (`EventCreate` with `attendee_ids[]` →
  `CalendarEvent`, `workspace` create) creates the event and its invitations in one transaction; an outbox
  handler sends the ICS invitations. The donor's edit-only participants must change.
- **Prefill by reference** (proposed). The URL carries `?prefill=<assistant_turn_id>:<action_key>` and the
  screen loads the prefill from the stored answer, so names and notes stay out of URLs, browser history and
  access logs.
- **The form in the answer:** the calendar's own form component rendered inline, or the Calendar modal
  opened over the thread (one modal component, FRONTEND_GUIDE §8). Clashes are shown in the form.
- **AI grounding.** Reads `calendar.events.between` (own scope) and organisation `employees.list` by role
  and worksite; the stock note uses §9's reads only if the person can read stock.

**Build plan.**

1. Contract: `calendar.js` (`EventCreate`, `CalendarEvent`), `assistant.js` (`PrefillRef`).
2. Backend: route and resolver; the event service writing attendees in one transaction with `emit()`; the
   ICS handler; a prefill endpoint `GET /api/v1/assistant/turns/:id/prefill/:action_key` (own); tests.
3. Frontend: the prefill loader, the inline form, the new-event highlight on the week view.

**Estimate.** 4 days: route and resolver 1.5, attendees on create 1, prefill by reference 0.5, inline form
0.5, tests 0.5. Assumes M2 calendar and §3.

**Priority.** P2. "From question to action" is in the 60-second cut and is cheap, but prospects ask about
their data before they ask the system to book a meeting.

---

## 13. Mailbox thread summary and a drafted reply with stock impact · P2 · 7 days

**What the video shows.** 60-second shot 6: on a supplier's late-delivery thread, "Summarise and draft a
reply" gives a summary (2,400 cartons now five days late), the stock impact (Apapa runs out of noodles on
Friday, Ibadan is covered) and a reply asking for a part delivery of 800 cartons, left unsent in the editor.

**What MyCity has today.**

| | |
|---|---|
| Module | `mailbox` (key `mailbox`); `mailbox/binding/` (M7) |
| Screens | #18 Mailbox · Inbox, #20 Drafts |
| Milestone, owner | M1, GE; binding M7, GE |
| Feature guide | `docs/guides/mailbox.md` (agreed) |
| State | Contradicted. The guide sets AI reads to `// ai:none` in M1 ("Mail is private correspondence") and says the mailbox is "not linked to operations, partnerships or any other module in M1". REUSE_MAP §2.5 leaves `mail/assist/` (2,261 lines, AI on mail) "until M10"; MILESTONES §6.5 says "M10 at the earliest". The community guide keeps private conversation out of the assistant. Binding a thread to a record arrives in M7, for partnerships only. Files empty, including a `mailbox.ai.js` stub |

**What is missing.**

- **A decision** that a person's own mailbox can be summarised at their request (D7). Never another
  person's mail; nothing runs over mail in the background.
- **Feature AI, not the assistant:** `ai.complete({ purpose: "mail-thread-summary" })` and
  `ai.complete({ purpose: "mail-reply-draft" })`, prompts in `services/ai/prompts/`, structured output,
  `provisional: true`, a ledger row with module `mailbox` (BACKEND_STRUCTURE §14.2).
- **Grounding the stock impact.** Bind the thread to a supplier (sender's domain to
  `supplier.email_domain`, §7) and its open purchase orders, read the SKUs' cover (§9) under the person's
  permissions, and pass those figures to the prompt as data. The model does not compute cover.
- **The draft goes into the composer** and becomes a `mail_draft` only when the composer saves it as the
  person's draft. The AI never sends (A23).
- **Redaction** through `services/ai/redact.js`: addresses and phone numbers out of the prompt unless needed.
- **Data model.** `mail_thread_link` (`mail_thread_link_id`, `thread_id`, `entity_type`, `entity_id`,
  `linked_by`, `source` `auto` or `manual`), widening M7's binding beyond partners.
- **API.** `POST /api/v1/mailbox/threads/:id/assist` (`MailAssistBody`: `want` of `summary`, `draft` →
  `MailAssist`: `summary[]`, `impact[]`, `draft` with `to`, `subject`, `body`, `sources[]`, `provisional`,
  `run_id`), `mailbox` view · own, plus `sales_stock` and `procurement` view for the impact.
- **Screens.** A button in the Inbox reader header, a panel for the summary and impact with sources, the
  reply editor prefilled.

**Build plan.**

1. Contract: `mailbox.js` adds `MailAssistBody`, `MailAssist`.
2. Backend: the two prompts; the assist service reading through manifests and calling `ai.complete()`
   outside any transaction; the binding resolver; the route; tests (own mailbox only; nothing sent).
3. Frontend: button, panel, composer prefill.

**Estimate.** 7 days: assist endpoint and prompts 2, binding and stock grounding 2, UI 2, redaction and
tests 1. Assumes M1 mailbox, §7 suppliers and §9 stock.

**Priority.** P2. It closes the 60-second cut and shows work done, not just answered, but it needs a privacy
decision before anyone demonstrates it on a client's real mail.

---

## 14. Receivables ageing and debtors over 60 days · P2 · 9 days

**What the video shows.** 3-minute chapter "Cash and receivables": "Who owes us more than 60 days?" shows
₦96.3m across 14 customers and 28.4m FCFA across 3 in Douala, an NGN ageing chart (0 to 30, 31 to 60, 61 to
90, over 90 days), the 17 customers, one customer's record and Download Excel.

**What MyCity has today.**

| | |
|---|---|
| Module | None. MyCity records money going out (expenses, disbursements, payroll), not money owed to the company |
| Screens | None |
| Milestone, owner | Outside MyCity's scope |
| Feature guide | None |
| State | Not planned. MILESTONES §11 lists "due invoices" among M6 reminder categories, but no invoice or receivables module exists; unsure what it refers to. Partners (M7) are not customers and must not become them (§6.1). Pattern donor outside the reuse map: praxis-ls `src/modules/finance/smart_receivables/` (477 lines; buckets current, 1 to 30, 31 to 60, 61 to 90, 90+ by days past due; sums with `Number` in centimes, which gate 20 forbids) |

**What would be new.**

- **Data model** (import-fed, D5): `customer` (`customer_id`, `code`, `name`, `worksite_id`, `currency`,
  `credit_days`); `receivable_item` (`receivable_item_id`, `customer_id`, `document_number`,
  `document_date`, `due_on`, `amount`, `open_amount`, `currency`, `import_batch_id`). Importer from the
  client's open-items report.
- **Rules.** The ageing basis (days past due, or days since invoice) is the client's choice. Buckets per
  currency, never a cross-currency total, all sums through `shared/money`.
- **API.** `GET /api/v1/receivables/ageing` (`AgeingQuery`: `currency`, `as_of` → `AgeingBucketPage`),
  `GET /api/v1/receivables/debtors` (`DebtorQuery`: `min_days`, `currency?`, `worksite_id?` → `DebtorPage`),
  `GET /api/v1/receivables/customers/:id`, all `finance` view (proposed key), paged.
- **Screens.** Finance · Receivables (`receivables`), an inventory change.
- **Permissions.** Proposed key `finance` for `receivables`, `customers` and `cash` (§15).
- **AI grounding.** `receivables.ai.js` reads `receivables.ageing`, `receivables.debtors` and
  `receivables.customers.get`, citing the customer, label "Reading receivables". No routes in this scope.

**Build plan.**

1. Contract: `receivables.js`, `customers.js`.
2. Backend: module folders, key, migration in the new block, importer, reads, routes, manifest, customer
   search, tests.
3. Frontend: the Receivables tab; Excel through §5.

**Estimate.** 9 days: tables and importer 3, ageing and debtors 1.5, AI, record and export 1, screen 2.5,
tests 1.

**Priority.** P2. CFOs ask it in the first meeting, but after sales and stock, and the answer is only as
good as the client's open-items export.

---

## 15. Bank cash position by currency · P2 · 6 days

**What the video shows.** The brief's second tile and the 3-minute cash chapter: "Cash today" ₦412.8m with
96.4m FCFA under it, as of 07:30.

**What MyCity has today.**

| | |
|---|---|
| Module | None. Closest: `disbursements` (M8, AJ) records a payout's bank transaction reference, NGN only; `integration_secret` (M0) would hold any bank credentials |
| Screens | None. #59 Expenses · Disbursements and #77 Settings · Integrations are the nearest |
| Milestone, owner | Outside MyCity's scope |
| Feature guide | None |
| State | Not planned |

**What would be new.**

- **Data model.** `bank_account` (`bank_account_id`, `name`, `bank_name`, `account_number` restricted,
  `currency`, `worksite_id`); `bank_balance` (`bank_balance_id`, `bank_account_id`, `as_of` timestamptz,
  `ledger_balance`, `available_balance`, `source`, `import_batch_id`).
- **Source:** a morning balances file or statement closing balances through the import engine; a bank API
  is a later choice per bank (D5).
- **API.** `GET /api/v1/cash/position` (`CashPositionQuery`: `as_of?` → `CashPositionPage`: per currency,
  accounts, as-of time), `finance` view, account numbers field-masked.
- **Screens.** Finance · Cash (`cash`).
- **AI grounding.** `cash.ai.js` read `cash.position`, citing the account, label "Reading bank balances". The
  answer states the as-of time and never converts currencies.
- **KPI tile** `cash_today` (§11).

**Build plan.**

1. Contract: `cash.js`.
2. Backend: migration in the new block, importer, position read, manifest, masking test.
3. Frontend: the Cash tab and the tile.

**Estimate.** 6 days: tables and importer 2.5, position read 1, AI, tile and masking 1, screen 1.5.

**Priority.** P2. A CFO asks for it and it is the brief's second tile, but it depends on the client sending
balances every morning.

---

## 16. Audit log screen · P2 · 3 days

**What the video shows.** 3-minute chapter "Security and the audit log": a table of time, who, event and
outcome for the morning (sign-in, assistant reads, the leave approval, the Excel export), with the refusal in
bad tone and a lock icon.

**What MyCity has today.**

| | |
|---|---|
| Module | `audit` (M0: ledger read; key `settings`); `audit_ledger`, append-only by trigger, written by `emit()` |
| Screens | #79 Settings · Audit Log |
| Milestone, owner | M0 ledger (GE); M9 screen (GE) |
| Feature guide | None |
| State | Planned: searchable by actor, entity, action and date, and cannot be edited (MILESTONES §14). Donors: praxis-ls `security/audit_ledger/` (644 lines, with `entity-label.js`), pixie `IamAuditPage`. Files empty |

**What is missing.**

- **Assistant events and labels:** `assistant.refused`, `assistant.exported`, and decisions taken from an
  answer (already audited by their own modules). Whether every assistant read goes to `audit_ledger` or
  only to `ai_run_step` is D8; the demo shows reads in the log.
- **An outcome** to show and filter on (done, refused): derived from the event key, or a proposed `outcome`
  column on `audit_ledger` in an M9-block migration.
- **Readable lines** ("Asked the assistant for salary data") through the `entity-label` pattern, never raw
  keys.
- **API.** `GET /api/v1/audit` (`AuditListQuery` adds `outcome?` and `event_prefix?` → `AuditPage`),
  `settings` view, paged.
- **Screens.** Refusal rows with the lock icon; a refusal block in an answer links to its row.
- **AI grounding.** None. Proposed `// ai:none`: the log holds the questions people asked.

**Build plan.**

1. Contract: `audit.js` filters.
2. Backend: labels, the outcome, the filter, tests.
3. Frontend: row styling and the link from §4.

**Estimate.** 3 days on top of M9: labels 1, outcome and filter 1, styling and link 0.5, tests 0.5.

**Priority.** P2. Security reviewers ask "where is it logged" right after the refusal; the refusal block
already shows the entry number, so the screen can follow.

---

## 17. AI spend against a monthly naira cap · P3 · 4 days

**What the video shows.** The same 3-minute chapter: AI spend this month as a bar towards the monthly cap in
naira with an 80% alert mark, the number of requests, last month and spend by role.

**What MyCity has today.**

| | |
|---|---|
| Module | `ai_governance` (M10, key `praxis_ai`); `services/ai/governance.js` and `ledger.js` (M0) |
| Screens | No numbered screen. BACKEND_STRUCTURE §14.5 puts a "spend screen" in M10; whether it is a Praxis AI tab or part of Settings · Integrations #77 is unsure |
| Milestone, owner | M10, TB with GE |
| Feature guide | None |
| State | Planned: `ai_setting` (vendor, model, monthly cap, per-module switch), `ai_run` and `ai_run_step` (tokens, cost, latency, purpose, module, actor) in `0095_ai.sql`; governance refuses a call at the cap; cost in NGN; "Spend caps enforce" (M10). An alert before the cap is not described. Donors: pixie `ai_governance` with `soft_cap_ngn`, `hard_cap_ngn` and a built `SpendBudgetPage`; praxis-ls governance with OK, WARN and BLOCK states and vendor cost converted from US dollars at a dated rate |

**What is missing.**

- **A soft cap:** `ai_setting.alert_at_pct` (default 80), raising one `shared/alerts` alert per month to the
  AI owner. No currency in column names (MILESTONES §17), unlike the donor's `soft_cap_ngn`.
- **Pricing in naira.** Vendor prices are in US dollars. Either an administrator keeps prices per 1,000
  tokens in naira (pixie), or the ledger keeps the native cost at 6 decimal places and converts with a dated
  rate (praxis-ls). D12.
- **Spend by role** needs the role at the time of the call: proposed `ai_run.role_id`.
- **API.** `GET /api/v1/ai-governance/spend` (`SpendQuery`: `month` → `AiSpend`: spent, cap, `pct`,
  requests, last month, by role), `praxis_ai` view, proposed to be limited to the AI owner's role;
  `PUT /api/v1/ai-governance/settings` (`AiSettingUpdate`), `praxis_ai` edit.
- **Screens.** The spend view, wherever D12 puts it.
- **AI grounding.** None (`// ai:none`).

**Build plan.**

1. Contract: `ai_governance.js` (`AiSpend`, `SpendQuery`, `AiSettingUpdate`).
2. Backend: an M10-block migration for `alert_at_pct` and `role_id`; the alert; the spend read; tests.
3. Frontend: the spend view.

**Estimate.** 4 days on top of M10 governance: soft cap and alert 1, pricing and rounding 1, spend reads 1,
screen 1.

**Priority.** P3. Prospects ask what the AI costs, but a sentence answers that in a meeting; the screen is
not what they ask to see first.

---

## 18. Cached, reproducible answers · P3 · 4 days

**What the video shows.** Nothing on screen: every take and both voice-overs show identical answers because
each answer was generated once from the seed, checked against `story-figures.json` and replayed when the same
account asks the same question (runbook §6.3, DEMO_SPEC §4.5).

**What MyCity has today.**

| | |
|---|---|
| Module | `assistant` history (`assistant.history.js`, `conversation.repo.js`); `ai_run` ledger; `ai_prompt_version` |
| Screens | #68 Praxis AI · History |
| Milestone, owner | M10, TB |
| Feature guide | None |
| State | No cache or replay in MyCity's documents. M10 answers live through Gemini. The stub vendor is deterministic but returns placeholders. A4 says staging behaves like production with "no feature flag, no stub, no sink", so a replay switch on MyCity staging would break it |

**What is missing.**

- **Stored answers** are covered by §3: every turn is kept and can be re-opened, exported and audited.
- **Saved answers for demonstrations and training:** `assistant_saved_answer` (`assistant_saved_answer_id`,
  `question_normalised`, `role_id`, `answer` jsonb, `data_as_of`, `created_by`), matched by normalised
  question and role, served only in a demonstration environment (not production, not MyCity staging under
  A4), and always labelled "Saved answer, data as of …".
- **Repeatable live answers:** the model, prompt version and temperature 0 pinned and recorded on the turn.
  Still not byte-identical, which is why the demo caches.
- **API.** `POST /api/v1/assistant/saved-answers` (from a turn) and `GET /api/v1/assistant/saved-answers`
  (paged), `praxis_ai` edit, demonstration environment only.

**Build plan.**

1. Contract: `SavedAnswer`, its page.
2. Backend: table, normalisation, environment check, replay in the orchestrator before retrieval.
3. Frontend: the "Saved answer" marker.

**Estimate.** 4 days: table, matching and replay 2, pinning 0.5, marker 0.5, tests 1.

**Priority.** P3. No prospect asks for a cache; they ask for live answers. It matters for repeatable
demonstrations and training.

---

## 19. Demo screens and where they land in MyCity

| Demo route | What it is | MyCity | Section |
|---|---|---|---|
| `/sign-in` | account and password | `identity` login (M0), no screen number | not a gap |
| `/` Workspace | brief, thread and answer pane on one page | two screens: #1 Home · Overview and #67 Praxis AI · Ask. MyCity's My Workspace (#4 to #7) is tasks and calendar, a different thing | §3, §11 |
| `/approvals` | the waiting list with Approve | Home "Needs you" (#1), #26, #57; no cross-module screen among the 85 | §7 |
| `/calendar` | week view, new event with prefill | #6 | §12 |
| `/mail` | threads, open thread, assist | #18 | §13 |
| `/audit` | audit table | #79 | §16 |
| `/settings/ai` | AI spend | M10 spend screen, unnumbered | §17 |
| People | "Not part of this demo" | #24 to #28 | §6 |
| Projects | "Not part of this demo" | #60 | §10 |
| Sales and stock | "Not part of this demo" | none | §8, §9 |
| Finance | "Not part of this demo" | none | §14, §15 |

The demo's navigation keys map to MyCity's as follows: `workspace` to `home` and `praxis_ai`; `approvals` to
`workflow` plus each owning key's `approve`; `calendar` to `workspace`; `mail` to `mailbox`; `people` to
`people`; `projects` to `expenses`; `sales_stock` and `finance` to the proposed keys of the same names;
`audit` to `settings`; `settings` to `settings` and `praxis_ai`.

**Demo-only machinery that MyCity must not build:** the virtual clock (`GET` and `POST /api/demo/clock`),
write timestamps taken from the page (`at` in `AskRequest` and every write), `POST /api/demo/reset`, answer
matching by question text, and `display` strings built from `story-figures.json`. MyCity takes every
timestamp from the server.

---

## 20. Risks and decisions

### Where MyCity's documents contradict the demo

1. **Sales.** "There is no sales in this product" (MILESTONES §6.1); "no sales anywhere" (M1 definition of
   done); `sales_procurement.js` refused "for later" (REUSE_MAP §2.1). The demo's brief, its sales and stock
   chapter and its receivables and cash answers are all commercial.
2. **Currency.** "Currency NGN" (MILESTONES §1.1), "NGN only" for expenses (§13), `shared/money` "NGN only"
   (BACKEND_STRUCTURE §3), and `phone` in `common.js` accepts Nigerian numbers only. The demo runs a Douala
   branch in FCFA.
3. **Mail and the AI.** Mailbox AI reads are `// ai:none` and the mailbox is not linked to other modules in
   M1 (`mailbox.md` §1, §9); mail assist waits until M10 at the earliest (REUSE_MAP §2.5, MILESTONES §6.5);
   private conversation is not material for the assistant (`community.md` §9). The demo summarises a
   supplier thread and drafts a reply with stock figures.
4. **Writes from an answer.** A23 and MILESTONES §15: the assistant links the person to the owning module.
   The demo approves a leave request and saves a booking from inside the answer (D6).
5. **Leave approvals.** A11 names M3, M5, M6 and M8 as the workflow engine's consumers, not leave; the
   demo's inbox mixes leave with expenses and purchase orders.
6. **Records MyCity does not have:** projects (named in M8 budgets, never defined), purchase orders,
   suppliers, customers, SKUs, stock and bank balances.
7. **Audit of the assistant.** MyCity audits writes through `emit()` and records AI calls in `ai_run`;
   nothing says a refusal or a read is written to `audit_ledger`. The demo's log shows both (D8).
8. **Staging.** A4: staging behaves like production, "no feature flag, no stub, no sink". The demo's replay
   of cached answers is exactly such a switch (D2).
9. **Formatting.** FRONTEND_GUIDE §3: format at render time only. The demo's payloads carry pre-formatted
   `display` strings (D14).
10. **Copy and brand.** MyCity's chrome is Title Case (gate 8, FRONTEND_GUIDE §5), compact money is `₦1.3M`
    on KPI tiles only (MILESTONES §17), and the type is Geist (§1.1). The demo is sentence case, `₦38.2m`,
    Inter. A screen copied from the demo fails MyCity's gates.
11. **Names.** "Praxis AI" is the only place JBS Praxis's name appears in MyCity's chrome (MILESTONES §18.1);
    the demo says Assistant and Ask. MyCity's My Workspace is tasks and calendar; the demo's Workspace is the
    brief and the assistant on one page, which MyCity splits into Home · Overview and Praxis AI · Ask.
12. **Contract.** `demo.d.ts` is hand-written TypeScript with string ids; MyCity's contract is Zod,
    generated, with `uuid` ids and `snake_case` keys that equal table columns (BACKEND_STRUCTURE §6).
    Porting the shapes is a rewrite, not a copy.
13. **Versions.** The demo copy runs on a local PostgreSQL 18 and accepts Node 24 (DEMO_SPEC §2, §6); MyCity
    pins PostgreSQL 16 and Node 20 (MILESTONES §1.1, `.nvmrc`). Minor, but nothing from the demo should be
    assumed to run unchanged.

### Decisions the MyCity team must make

| # | Decision | Options | Recommendation | Owner |
|---|---|---|---|---|
| D1 | Where the commercial capabilities are built (§7 POs, §8, §9, §14, §15, the second currency) | (a) a distribution product on MyCity's codebase, its own repository and blocks; (b) modules inside MyCity's repository; (c) a change request to MyCity | (a). MyCity is one client with no sales (A2, §6.1). (b) is awkward: the module loader mounts every module folder and MyCity removed the donor's feature gate (BACKEND_STRUCTURE §3), so "installed but off" does not exist | GE with TB |
| D2 | Cached versus live answers | live only; saved answers on request; both | Live in production, every turn stored (§3). Saved answers only in a separate demonstration environment, labelled with their data date; never on MyCity staging (A4) | TB |
| D3 | Currency handling for NGN and XAF | a currency column on every money row; convert XAF to NGN at import | A `currency` column (ISO code, never in a column name), `shared/money` taking a currency, per-currency totals, no conversion without a stated, dated rate; ₦ before the number, FCFA after with a space; 2 decimals on the wire for both. MyCity itself stays NGN only (M8). Douala as a separate legal entity is D13 | GE |
| D4 | Where the morning brief's KPI catalogue lives | MyCity's `kpi-catalog/tiles/*.js`; a separate tiles file per extra module | The catalogue stays in `src/modules/dashboard/kpi-catalog/`. Commercial tiles go in their own file shipped with their modules, gated on `sales_stock` and `finance`, so eligibility hides them and MyCity's "no sales anywhere" holds. Tiles computed on each Home load, with an as-of time; revisit if a tile is slow | TB |
| D5 | Commercial data: read models or systems of record | daily imports through the M0 engine; live connectors; full order-to-cash, procurement and inventory modules | Imported read models first (one importer each for sales, stock, receivables, balances), connectors per client later, systems of record only when a client buys them | GE with LA |
| D6 | Inline decisions and forms in an answer under A23 | buttons that call the owning module's endpoint; links to the owning screen only | Allow the owning module's own component and endpoint, under the person's grant, with no AI in the write path; write it into MILESTONES as an A23 clarification. Otherwise shot 2's one-click approve becomes a link | TB with GE |
| D7 | AI over a person's own mail | keep `ai:none`; allow on request over the caller's own mailbox | Keep `ai:none` for MyCity (its guide is agreed). For the distribution product, allow on request only, own mailbox, redacted, after a decision on the AI vendor's data terms | GE |
| D8 | What the assistant writes to `audit_ledger` | refusals only; refusals and exports; every read | Refusals and exports in `audit_ledger`; reads stay in `ai_run_step`, and the audit screen shows both. The question text is personal data in an append-only table; decide retention first. For the short reference ("Audit entry 118204"), an identity column on `audit_ledger`, never `numbering.allocate()`, which would serialise every audit write | GE |
| D9 | Exports beyond 25 rows, and keeping the file | first page only with "of N"; a worker job paging through the read to a ceiling; `completeQuery()` | A worker job using `pageQuery` to a ceiling of 5,000 rows (the donor's limit). `completeQuery()` is reserved for sets acted on whole (BACKEND_STRUCTURE §16.3). Do not keep the file; record its sha256 in the audit row | GE |
| D10 | New permission keys, screens and migration block | `sales_stock`, `finance`, `procurement` as new keys; POs under `expenses` | New keys, each with its row in README §5.2, `access.catalog.js` and the inventory (MILESTONES §18.2). A block from 12000 for the new modules, or the distribution repository's own blocks under D1 | GE |
| D11 | Attendance hours per worksite | one organisation-wide week (A22); hours per worksite | Per worksite, falling back to the A22 week. MyCity may want it too (a site that opens earlier) | LA |
| D12 | AI cost in naira, and where the spend screen sits | prices kept in naira (pixie); US dollar prices with a dated rate (praxis-ls) | Naira prices set by an administrator, with the native token counts kept so cost can be recomputed. Screen placement is MyCity's call: a Praxis AI tab or part of Settings · Integrations | TB |
| D13 | Douala as a second country | treat as a worksite; treat as a second legal entity | Unsure for any real client. `phone` in `common.js` accepts Nigerian numbers only; payroll is Nigerian (M6); MyCity has no entity layer (A2, MILESTONES §14). A group with a Cameroonian company needs an entity dimension for reporting | GE |
| D14 | Formatting on the server or at render | `display` strings in the payload (the demo); raw values formatted at render (FRONTEND_GUIDE §3) | Raw values and a format code in the contract; the backend formats numbers in the answer text with the same rules, and a test checks the prose, the pane and the Excel agree | AJ with TB |
| D15 | Refusal wording | name the data ("salary data"); say only "not available to you" | Name the data, as the demo does. It confirms that salary data exists, which is not a secret, and tells the person who to ask | TB |

### Risks

- **R1 Schedule.** The P1 set leans on M10's assistant engine, which MyCity schedules last (14 to 18
  December, parallel with M11). Pulling it forward competes with M1 to M9.
- **R2 Numbers typed by the model.** One wrong figure in a COO's answer costs the product its trust.
  Mitigation: every figure from a read, and the figure check in §3.
- **R3 Latency.** The promise is answers in seconds. Several reads plus a model call can take longer. Stream
  the status lines, cap the reads per answer, index the aggregates. The staging role's 15-second
  `statement_timeout` (SYSTEM_ARCHITECTURE §5) will cut slow aggregates first, which is the right place to
  find them.
- **R4 Pagination against the pane.** Sorting and exporting an answer's table must respect the 25-row rule
  (§3, D9); a pane that sorts one page and presents it as the whole set repeats the praxis-ls bug the rule
  exists for.
- **R5 Imported data that disagrees with the client's own reports.** If "Sales yesterday" differs from the
  finance team's figure, trust goes in a day. Each importer needs a reconciliation total against the source
  report, shown with the batch.
- **R6 Privacy.** HR data and mail sent to the AI vendor; questions kept in an append-only table; data
  protection law in Nigeria and Cameroon. Redaction (`services/ai/redact.js`) and D7, D8 before any client
  pilot.
- **R7 Scope creep.** Order-to-cash, procurement and inventory are ERPs of their own. Keep to read models
  (D5) until a client pays for more.
- **R8 AI spend mid-answer.** When the monthly cap is reached, governance refuses the next call
  (BACKEND_STRUCTURE §14.2). An answer that needs several calls can fail halfway; the orchestrator must
  check the cap before it starts and say so plainly when it cannot answer.
