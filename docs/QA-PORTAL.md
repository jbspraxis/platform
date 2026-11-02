# Client portal and HQ: end-to-end QA on the live site (2 Nov 2026)

A full engagement was played on the live site (hq.jbspraxis.com and portal.jbspraxis.com) with a **test client**, before MyCity's people sign in to the portal for the first time. This file holds what was tested, every finding and its fix, and how the test data is removed. It names no real client and holds no client data.

## 1. The test client

- **Organisation**: "QA Test Client (delete)", Lagos, Nigeria, English. Opened from *Open an opportunity* (new organisation inline), so it is a normal organisation; **Test client** is ticked on the opportunity's *Edit details* (section 3), on live since 2 Nov once #23 was deployed.
- **Opportunity**: "Fleet tracking and dispatch portal (QA test)", led by the owner, billed by Nigeria's company.
- **People**: two portal accounts on the owner's own test addresses, invited from System › Accounts: "Chidi Nwosu (QA)" is the Client Admin (primary contact) and "Adaeze Okafor (QA)" a Team member. (The member was meant to be invited second; the other order made Chidi primary, which HQ couldn't change: finding 10.) The owner set the passwords and does every sign-in. Nobody outside JBS Praxis is emailed.
- **A French spot check** uses a second opportunity of the same organisation in French.

## 2. What was played

HQ: meeting through Google (Meet link, Google's invite, our branded email), transcript pasted, AI notes, Download menu (both files), client minutes sent; questionnaire; proposal (draft, versions, approval, send); SOW (draft, send, signing with the one-time code, countersignature); deposit invoice and Won; project draft and *Create project*; milestones and tasks, the planner, an internal meeting on a milestone, a milestone handover whose points become QA tickets; milestone invoices less the deposit, VAT off.

Portal: meetings and a follow-up request; proposal review (comments by section, ask for changes, approve); SOW review and signing; projects and progress, QA and messages, invoices and payments, documents; team (the admin adds and limits a member), branding, support; every email and PDF the client receives.

Across both: a team member against the admin; signed links expired, reused and tampered with; empty, error and loading states; 375 px and the keyboard; English throughout and French on the second opportunity; the server logs after each step.

## 3. Removing the test data: `php artisan qa:wipe`

Run by the owner from cPanel's Terminal, in `~/praxis_core`:

```
php artisan qa:wipe "QA Test Client (delete)"            # shows what it would delete, changes nothing
php artisan qa:wipe "QA Test Client (delete)" --force    # asks for the exact name, then backs up, cancels its Google events, deletes
```

- **The preview** lists the rows per table, the files and the Google events, the day the organisation was added and the date range of its rows, and who flagged it as a test and when.
- **With `--force`** the person types the organisation's exact name; without questions (`--no-interaction`) it must be given as `--confirm="QA Test Client (delete)"`.
- **It refuses**:
  - any organisation not flagged as a test (`organizations.is_test`). Only the owner sets the flag, on an opportunity of that organisation: *Edit details › Test client* (history line `ORGANISATION_TEST_FLAG_CHANGED`); the opportunity header then shows a *Test client* tag;
  - an organisation added more than 30 days ago (a test client is added on the day of its QA), unless `--allow-old-organisation` is given;
  - an organisation with staff accounts, and a name that several organisations share (give the id instead);
  - while any of its AI runs is still queued or running, and, when Google isn't connected, while it has events to cancel (`--skip-google` deletes anyway; the events stay in the calendar).
- **What goes**: the organisation; its portal accounts with their portal sessions, passkeys, PINs, password-reset tokens, push subscriptions and bells; its opportunities with their team, meetings (attendees, transcripts, handover points), questionnaires, proposals (comments, lines), statements of work and project drafts; its projects with their milestones, sprints, tasks (people, steps, comments, timers, dependencies), QA tickets (comments, attachments), plans and suggestions, change reports, budgets and sign-offs; its invoices (lines), payments and their **ledger postings** (accrual and receipt journal entries, and any reversal of them, plus a client receivables sub-account if one was made); staff bells that link to any of it; CRM notes, free audits and whitepaper downloads in its name; and every file uploaded for it (QA evidence, payment proofs, task files, the portal logo).
- **AI runs stay, emptied**: the test's AI cost was really spent and `Budget::spent()` sums `ai_runs.cost_usd`, so its runs keep their purpose, model, tokens, cost, status and date; what they were about (`subject_type`, `subject_id`) and their content (`input`, `output`, `error`) are cleared. The month's AI budget is the same before and after the wipe.
- **Backup first**: every row (as JSON) and every file is copied to `~/praxis_core/storage/app/private/qa-wipes/<date-time>-<name>/`, readable by the owner's account only, before anything is deleted. Delete that folder by hand once the wipe is checked.
- **Google**: each of its meetings still in the calendar is cancelled through the connected account; guests of a meeting still to come are told, a past one goes quietly.
- **Audit log (decision)**: the test's own history lines go with it (they describe rows that no longer exist and hold only test data), and **one line stays**: `QA_WIPE` on the organisation's id, with the counts per table, the AI runs kept, the files, the Google events, the backup folder, who flagged it as a test and when (that history line goes with the test), the server account that ran the command (`get_current_user()`: the CLI has no HQ user) and whether `--allow-old-organisation` was used.
- **Not touched**: invoice numbers the test used stay as gaps (accepted by the owner); staff accounts and staff mailboxes (HQ Mail threads with the test addresses stay in the owner's mailbox); cash requests and case studies that named a test project keep their row and lose the project link.
- Tested locally (`tests/Feature/QaWipeTest.php`, 8 tests): a whole engagement for a test client next to a real one; after the wipe no uuid column of any table holds one of the test's ids (bar the kept AI runs' own ids), the real client keeps every row and file, the backup holds the rows and files, the Google event is cancelled and the AI budget is unchanged; the name confirmation, the 30-day guard and the flag details.

## 4. Findings

Severity: **High** blocks a client or shows wrong money or data; **Medium** misleads or breaks a step that has a work-around; **Low** a rough edge.

PRs: **A** = #24, **B** = #25, **C** = #23, all merged and live on 2 Nov (release 508e5ec); the owner's decisions are **D** = #26 and **E** = #27 (section 5), merged and live the same day; **F** is this report's PR (findings 23 and 24).

| # | Severity | Where | What happened (steps) | Fix | PR |
|---|---|---|---|---|---|
| 1 | High | HQ › every "saved when you leave the field" editor | Livewire 4's `wire:model.blur` only syncs in the browser and sends nothing. Proposal, SOW, project draft, handover points, website settings and mailbox fields (66) were saved only if another click followed. On live: a proposal line's price, typed and tabbed out of, was back at the dictionary price after a reload, and the total never moved. | `wire:model.live.blur` everywhere; a unit test refuses a bare `.blur`, `.change` or `.enter`. | A |
| 2 | High | Portal, first sign-in | A client invited before their project exists (proposal or SOW stage) sets a password and lands on Laravel's bare "403 THERE ARE NO PROJECTS…" page, with no way to sign out (seen live). | The portal opens on Documents and Team; project sections say the space opens once the SOW is signed. | B |
| 3 | High | Portal › Documents | Lists only proposals from the old builder. SOWs and proposals sent through the opportunity pipeline never appear, so a client can't find a signed SOW once its email link has expired (30 days). | Documents lists the SOWs and proposals the client was sent, with links made when the page is drawn; an imported SOW shows as signed on paper. | B |
| 4 | High | Portal, any expired or altered email link | Laravel's bare "403 INVALID SIGNATURE." (seen live). | Branded error pages (403, 404, 419, 429, 500, 503) in English and French; an expired link explains itself and leads to the portal sign-in. | B |
| 5 | Medium | Portal, signed in | No way to reach the meetings page or book a follow-up: only the links in emails. | Documents › Meetings links each piece of work's meetings page. | B |
| 6 | Medium | Emails › meeting invitation, change, cancellation | Told the client "We've arranged a meeting with QA Test Client (delete)", i.e. with their own organisation (MyCity would read "with MyCity App Ltd"). | "a meeting between <client> and JBS Praxis", EN and FR. | B |
| 7 | Medium | Portal › Overview | Overall progress counted only accepted milestones, so it disagreed with HQ's project page; "Lead" was whoever created the project. | Same figure and lead as HQ. | B |
| 8 | Medium | Portal › Quality and issues | Deleted QA tickets still showed. | Hidden. | B |
| 9 | Medium | Portal › Project progress | *Approve* could mark any visible task Done, and HQ wasn't told. | Only a task in client review; the project lead gets a bell. | B |
| 10 | Medium | HQ › System › Accounts | The first account invited always becomes the primary contact (Client Admin), and HQ couldn't change it. On live the member was invited first by mistake. | *Make primary contact* on a client account (history line). | A |
| 11 | Low | Emails › minutes | "Thank you for your time on Monday 2 November 2026" twice: the email's intro, then the AI's first line. | The minutes prompt no longer opens with thanks or the date. | A |
| 12 | Low | Portal → HQ bells | A client's issue, reply or payment proof gave bells with no link; the meetings page wrote the lead's bells in the client's page language (a French client gave HQ a French bell). | Bells link to the QA hub or Payment verification; HQ's language. | B |
| 13 | Low | HQ › Proposals | A first proposal for a Lagos client defaulted to USD. | The client's currency where JBS Praxis bills it (NGN, XAF), else USD. | A |
| 14 | Low | HQ › Proposal and SOW send lists | A meeting guest was shown as "blakeasaah@gmail.com blakeasaah@gmail.com". | Shown once. | A |
| 15 | Low | Emails › SOW | The AI titled the SOW "Statement of work: …", so the subject read "Statement of work to sign: Statement of work: …". | The SOW prompt keeps the label out of the title. | A |
| 16 | Low | HQ › System › Accounts | Opening the account lists cleared a half-filled form; "Team member" listed twice; an organisation added there had no "added by"; a stray scrollbar beside the tabs. | Fixed. | A |
| 17 | Low | Portal invitation link, portal colours | A suspended or removed account's unused invitation could still set a password; colours were checked for length only. | Refused; #RRGGBB only. | B |
| 18 | Low | HQ › Opportunity › history | The billing-company and tax-number lines had no label (English on a French HQ). | Labels in EN and FR. | C |
| 19 | High | Portal, payment hold | After signing, the deposit hold put a Team member (no billing access) on *Invoices and payments*, which her role can't read ("No invoices yet", "payment details aren't available"), told her to upload a proof of payment, and locked every section she may open (seen live). | Only the project's sections close; a member without billing lands on Documents and is told her administrator or finance contact pays. | B |
| 20 | Medium | HQ › QA hub, payment verification, cash requests, leads, audit, broadcasts, mail sync | 19 times of day were shown in UTC as if Lagos time, an hour early (a reply sent at 12:57 read 11:57). | Shown in Africa/Lagos; a unit test refuses a `*_at` time without it. Appointment and timer times, saved as Lagos wall-clock, are unchanged. | B |
| 21 | Medium | Portal › 404 page (review of B) | A missing record's 404 would have shown Laravel's "No query results for model [App\Models\…] <id>". | Framework messages are never shown. | B |
| 22 | Low | Portal › Overview, Project progress | "Phases" / "Phase 1" where HQ, the SOW and the emails say milestones (M1); tasks listed M2 before M1. | "Milestones" / "M1" (FR "Jalons"); tasks by milestone. | B |
| 23 | Medium | Portal › Team (re-test after D) | With the limit raised to 10, the page still said "Your organisation can have up to 3 Client Portal accounts" and "Team members (2 of 3)", and the guide "Invite up to 2 colleagues" (seen live). | The three lines use the limit the portal applies (EN, FR). | F |
| 24 | Low | Portal › Team › Invite a team member | "Team member" was listed twice in the role list: the default and the role itself. | Listed once. | F |

## 5. Decisions for the owner

| # | Where | Question | Today |
|---|---|---|---|
| D1 | Portal › Team | A portal admin can't invite anyone once the organisation has 3 portal accounts. MyCity already has 4 (made from HQ), so its admins can't add a colleague from the portal. Keep 3, raise it, or remove the limit? | **Decided 2 Nov: 10 accounts** (#26). |
| D2 | Portal › Team | A portal admin may only invite addresses on their own email domain (e.g. @mycityapp.io). Keep? | Open: kept as is. |
| D3 | Portal › Documents › appearance | "Second colour" and "Accent colour" are saved but nothing uses them; only the main colour shows. Remove the two fields, or use them? | Open: saved, unused. |
| D4 | Portal invitations | An invitation link never expires until it's used (or HQ resends it, or the person signs a SOW, which issues a new one). Expire it after, say, 14 days (HQ can resend)? | Open: never expires. |
| D5 | Proposal and SOW pages | Anyone the proposal or SOW was sent to who is an active contact of the organisation could approve it or sign it, whatever their portal role (a Team member too). Should approving and signing need the portal's Approve permission? | **Decided 2 Nov: approvers only** (#26). Approving a proposal, and asking for or using a SOW signing code, need the portal's Approve permission: Client Admin (the primary contact always is) or the new **Client Approver** role (projects, QA and messages, documents; no team or branding). Others read, comment and ask for changes; a plain line stands where the button was. A link sent to a contact approves as that contact; a link without one first emails a code to the approver. At the first client, three contacts approve (one moves from Team member to Client Approver) and one reads and comments. |
| D5b | SOW signing | How a SOW is signed: a drawn signature, the signer's 6-digit code by email, and a SHA-256 fingerprint of the text, milestones, fees and signature, with time and IP; no certificate on the PDF. Keep, seal the PDF with a certificate, or move to an e-signature service? | **Decided 2 Nov: keep drawn + email code.** |
| D6 | Master data › Legal entities | Only the Cameroon company (JBS Praxis SARL) existed. "Nigeria's company (default)" had no record, so proposals said "No VAT: no active JBS Praxis company… bills this client" and SOWs named the provider from Website settings ("JBS Praxis LLC"). | **Decided 2 Nov: add it now as "JBS Praxis LLC", VAT off**, renamed JBS Praxis Limited once CAC registers it; #26 lets its numbers wait. |
| D7 | HQ › Proposals | The AI priced from the financial dictionary and didn't fit a budget given in its instructions (asked "around NGN 12M", it drafted about NGN 120M with a year of retained support). | **Decided 2 Nov: a budget for our fees in the instructions caps the draft** (#26); figures about the client's business don't count as one. |
| D8 | Portal › Invoices and payments | Every client saw every active receiving account: Kuda (JBS Praxis LLC), UBA (JBS PRAXIS LTD) and MTN Mobile Money Cameroon in the owner's personal name, whatever their country or currency. | **Decided 2 Nov: only the accounts in the invoice's currency held by its billing company** (#26). Each account now has a currency and a company (an account without them is shown to no client); the three test accounts were deactivated; the owner adds the UBA account with NGN and JBS Praxis LLC. |
| D9 | Portal › Project progress | A task moved to Client review was approved automatically after 5 hours, nights and weekends included. | **Decided 2 Nov: 2 working days** (Monday to Friday, Lagos time) **with one reminder the day before** (#26). |
| D10 | Payment hold | The portal's deposit hold lasts only while the project is *Scoping*: *Create project* makes it Active and lifts the hold even if the deposit isn't paid. Keep (HQ decides when work starts), or hold until the deposit is paid? | **Decided 2 Nov: no change** (lifted at Create project). |
| D11 | Invoices | Clients got no email and no PDF for an invoice: it appeared in the portal with a bell, and HQ's *Download PDF* said "Invoice PDFs are not available yet". | **Decided 2 Nov: build it** (#27). A PDF titled "Invoice" ("Tax invoice" only with VAT) with the billing company and the client and their tax numbers, the lines and the accounts to pay into (D8); emailed to the client's billing contacts (Client Admin, Client Finance) when HQ creates an invoice or a signed SOW raises the deposit; downloadable in HQ (with *Email to client*) and in the portal. Older invoices are emailed only if HQ clicks *Email to client*. |

## 6. AI spend

From HQ › System › Integrations, 2 Nov, after the run (Claude through HQ's own key):

| Purpose | Runs | Model | Cost (USD) |
|---|---|---|---|
| Proposal, version 1 | 1 | Sonnet 5.5 | 0.1170 |
| Project draft (at signing) | 1 | Sonnet 5.5 | 0.0936 |
| Statement of work | 1 | Sonnet 5.5 | 0.0694 |
| Milestone plan (M2) | 1 | Sonnet 5.5 | 0.0494 |
| Questionnaire (5 questions) | 1 | Sonnet 5.5 | 0.0300 |
| Meeting notes | 4 | Haiku 5.5 | 0.0115 |
| Client minutes | 3 | Haiku 5.5 | 0.0033 |
| Milestone handover points | 1 | Haiku 5.5 | 0.0009 |

About **$0.37** for the whole run; the month stands at **$0.38 of $5** (it was $0.0045 before). `qa:wipe` keeps these runs (emptied), so the month's figure doesn't drop after the wipe.

## 7. Server logs

Read after each step from `~/deploy/logs/` and `~/praxis_core/storage/logs/` (cPanel's file API). Live release during the run: da80c92 (#22). No Laravel log was written on 2 November (warning level and above) through the meeting, transcript, notes, minutes, questionnaire, proposal versions and SOW; System › Health showed no errors and no email failures.
After the merges: release 508e5ec (#23, #24, #25) went live at 14:02 server time with its migration (`add_is_test_to_organizations`); still no Laravel log since 31 October. #26 and #27 went live together as release 1adee2a at 15:49 Lagos time (51 files, migrations `2026_11_02_300000` to `600000` done); no Laravel log on 2 November.

## 8. Re-tests on live after the merges (2 Nov)

| Fix or decision | What was checked on live | Result |
|---|---|---|
| Findings 2–5 (B) | Portal › Documents as the test client | Lists the SOW, both proposals (one waiting, one approved) and the meetings page, each with a working link. |
| Finding 4 (B) | An expired or altered SOW link; a used setup link | Branded "This link has expired" page; branded 404 that says the setup link expired or was used. |
| Section 3 (C) | Test client flag | Ticked on the opportunity's *Edit details*; the header shows the *Test client* tag. |
| D5 (D) | The French proposal, opened from Documents by the test admin | *Approuver la proposition* is offered; the address is read-only with "Ce lien vous a été envoyé à cette adresse…". A member's view is covered by the tests. |
| D6 (D) | Master data › Legal entities | JBS Praxis LLC added (Nigeria, 21B Bode Ajakaiye Street, Lekki, Lagos; representative the owner, Managing Partner); shown as "Being registered", tax ID "Not yet", no VAT. |
| D8 (D) | Portal payment details; Remittance accounts | No account has a currency and company yet, so the portal says payment details aren't available; the form asks for both. The owner's UBA account is pre-filled with NGN and JBS Praxis LLC for him to save. |
| D9 (D) | Portal › Project progress and the guide | "Approve it or report an issue within 2 working days" and "…approved automatically 2 working days (Monday to Friday) after it's ready; we remind you the day before." |
| D11 (E) | HQ › Finance › Invoices; portal › Invoices and payments; the test inbox | HQ's *Download PDF* returns both test invoices as PDFs; *Email to client* reached only the test admin ("Emailed with its PDF to …"), not the member; the email says "From JBS Praxis LLC", with `INV-202611-6971.pdf` attached; the portal downloads the same PDF. |
| Roles (D) | HQ › Accounts, portal › Team | The *Approver* role is offered in both; the client contact who moves to it is pre-filled for the owner to save. |
| 375 px | Portal › Team, Invoices and payments | No sideways scrolling. |

Not re-checked on live: a payment proof upload, a branding change and an urgent support request as the admin (unchanged since the first run).
