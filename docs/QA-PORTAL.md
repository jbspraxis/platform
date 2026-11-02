# Client portal and HQ: end-to-end QA on the live site (2 Nov 2026)

A full engagement was played on the live site (hq.jbspraxis.com and portal.jbspraxis.com) with a **test client**, before MyCity's people sign in to the portal for the first time. This file holds what was tested, every finding and its fix, and how the test data is removed. It names no real client and holds no client data.

## 1. The test client

- **Organisation**: "QA Test Client (delete)", Lagos, Nigeria, English. Opened from *Open an opportunity* (new organisation inline), so it is a normal organisation; the owner then ticks **Test client** on the opportunity's *Edit details* (section 3).
- **Opportunity**: "Fleet tracking and dispatch portal (QA test)", led by the owner, billed by Nigeria's company.
- **People**: two portal accounts on the owner's own test Gmail addresses, invited from System › Accounts (the first one is the Client Admin, the second a Team member). The admin adds and limits a third member from the portal's Team page, on a `+` alias of one of those addresses. Nobody outside JBS Praxis is ever emailed.
- **A French spot check** uses a second opportunity of the same organisation in French.

## 2. What was played

HQ: meeting through Google (Meet link, Google's invite, our branded email), transcript pasted, AI notes, Download menu (both files), client minutes sent; questionnaire; proposal (draft, versions, approval, send); SOW (draft, send, signing with the one-time code, countersignature); deposit invoice and Won; project draft and *Create project*; milestones and tasks, the planner, an internal meeting on a milestone, a milestone handover whose points become QA tickets; milestone invoices less the deposit, VAT off.

Portal: meetings and a follow-up request; proposal review (comments by section, ask for changes, approve); SOW review and signing; projects and progress, QA and messages, invoices and payments, documents; team (the admin adds and limits a member), branding, support; every email and PDF the client receives.

Across both: a team member against the admin; signed links expired, reused and tampered with; empty, error and loading states; 375 px and the keyboard; English throughout and French on the second opportunity; the server logs after each step.

## 3. Removing the test data: `php artisan qa:wipe`

Run by the owner from cPanel's Terminal, in `~/praxis_core`:

```
php artisan qa:wipe "QA Test Client (delete)"            # shows what it would delete, changes nothing
php artisan qa:wipe "QA Test Client (delete)" --force    # backs up, cancels its Google events, deletes
```

- **It refuses** any organisation not flagged as a test (`organizations.is_test`). Only the owner sets the flag, on an opportunity of that organisation: *Edit details › Test client* (history line `ORGANISATION_TEST_FLAG_CHANGED`); the opportunity header then shows a *Test client* tag. It also refuses an organisation with staff accounts, a name that several organisations share (give the id instead), AI runs still queued or running, and, when Google isn't connected, events it can't cancel (add `--skip-google` to delete anyway).
- **What goes**: the organisation; its portal accounts with their portal sessions, passkeys, PINs, password-reset tokens, push subscriptions and bells; its opportunities with their team, meetings (attendees, transcripts, handover points), questionnaires, proposals (comments, lines), statements of work and project drafts; its projects with their milestones, sprints, tasks (people, steps, comments, timers, dependencies), QA tickets (comments, attachments), plans and suggestions, change reports, budgets and sign-offs; its invoices (lines), payments and their **ledger postings** (accrual and receipt journal entries, and any reversal of them, plus a client receivables sub-account if one was made); the AI runs about any of these; staff bells that link to any of it; CRM notes, free audits and whitepaper downloads in its name; and every file uploaded for it (QA evidence, payment proofs, task files, the portal logo).
- **Backup first**: every row (as JSON) and every file is copied to `~/praxis_core/storage/app/private/qa-wipes/<date-time>-<name>/`, readable by the owner's account only, before anything is deleted. Delete that folder by hand once the wipe is checked.
- **Google**: each of its meetings still in the calendar is cancelled through the connected account; guests of a meeting still to come are told, a past one goes quietly.
- **Audit log (decision)**: the test's own history lines go with it (they describe rows that no longer exist and hold only test data), and **one line stays**: `QA_WIPE` on the organisation's id, with the counts per table, the files, the Google events and the backup folder. So the history shows that a wipe ran, and when, without keeping the test itself.
- **Not touched**: invoice numbers the test used stay as gaps (accepted by the owner); staff accounts and staff mailboxes (HQ Mail threads with the test addresses stay in the owner's mailbox); cash requests that named a test project keep their row and lose the project link.
- Tested locally (`tests/Feature/QaWipeTest.php`): a whole engagement for a test client next to a real one; after the wipe no uuid column of any table holds one of the test's ids, the real client keeps every row and file, the backup holds the rows and files, and the Google event is cancelled.

## 4. Findings

Severity: **High** blocks a client or shows wrong money or data; **Medium** misleads or breaks a step that has a work-around; **Low** a rough edge.

| # | Severity | Where | What happened (steps) | Fix | PR |
|---|---|---|---|---|---|
| 1 | Low | HQ › System › Accounts | Fill *Add an account*, open *Client Portal accounts* to check a name, come back: the form is empty again. | | |
| 2 | Low | HQ › System › Accounts | The staff role checkboxes are announced as "1", "2", "3" by screen readers (no name). | | |
| 3 | Low | HQ › System › Accounts | The client role list shows both "Team member (default)" and "Team member". | | |
| 4 | Low | HQ › Opportunity › history | The billing-company and tax-number history lines had no label, so a French HQ showed them in English. | Labels added in EN and FR. | C |

## 5. AI spend

| Step | Purpose | Model | Cost (USD) |
|---|---|---|---|

## 6. Server logs

Read after each step from `~/deploy/logs/` and `~/praxis_core/storage/logs/`.
