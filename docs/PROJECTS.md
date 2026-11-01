# Projects: one page per project, with milestones, sprints and backlog (runbook for PR 7)

Agreed with the owner on 1 November 2026, after MyCity App ERP was imported (`docs/OPPORTUNITIES.md` 12) and the owner asked where a sprint can be seen and edited, how weight, notes and dependencies are set, and how work is added mid-project. Today HQ has an "All projects" list (`ActiveDeployments`), one sprint board per project (`SprintBoard`: task columns by status, "Assign to me", drag to move, timers), the QA hub, timers and sign-offs, and *Scrum master* (AI backlog draft). There is no project page, nothing can be edited after it is created, tasks have no due date or dependencies, and milestones (`project_phases`) have no dates.

The model to follow is MyCity's My Workspace (`praxisjbs/mycity-app`, `docs/guides/workspace.md`): a task panel with a lead and the people sharing it, watchers, steps, comments, blockers, "waits on" links, due dates and history; a board per project.

> **Every chat working on this reads this whole file first.** Record changes to its contracts in section 9 in the same commit.

## 1. Owner decisions (1 Nov 2026, binding)

| # | Question | Decision |
|---|---|---|
| 1 | Where milestones, sprints and backlog live | **One project page with tabs**: Overview, Milestones (kanban), Sprints, Backlog, QA, Meetings and money. Everything opens in a **drawer** to see and edit. Today's sprint board becomes the Sprints tab. |
| 2 | Milestone kanban columns | **Not started → In progress → Client review → Accepted → Invoiced → Paid.** |
| 3 | Weight | **One weight, meaning the same everywhere**: a milestone's weight is its share of the contract (the SOW's %, e.g. M1 12%). A sprint shows the weight of the milestone(s) it delivers; tasks carry no weight of their own. Progress: a milestone's is its done tasks over its tasks; the project's is the sum of each milestone's weight × its progress. Story points stay in the database (timers read them) but are no longer shown or asked for. |
| 4 | People on a task | **A lead (picked from the team: a select, not only "Assign to me") + people sharing it + watchers**, as in MyCity. Watchers are told when it moves to Done or Client review, or gets a comment. |
| 5 | Dependencies | **Warn only**: a task can wait on tasks, a milestone on milestones; the drawer and the card show "Waits on …" and a warning while the other isn't done, but nothing is ever stopped. Loops and self-links are refused. |
| 6 | Deadlines | **Due dates and overdue flags only**: a due date on tasks and milestones (sprints keep their start and end), and a red *Overdue* badge when one passes unfinished. No reminders or late reasons for now. |
| 7 | Adding work mid-project | **Free to add; a fee needs a change report.** Anyone allowed adds a task under a milestone, or an internal milestone with 0% weight (as M0 Foundation was), at once. A milestone or task that changes the price goes into a **change report** (SOW clause 7.1): price, effect on the timeline, and the client's decision. Approved, it joins the plan and the contract total; declined, it is kept as declined (MyCity's Change Report 01, Mailbox, was declined on 1 Nov). |
| 8 | Who edits | **The project lead and managers edit everything** (milestones, sprints, any task, change reports). **Team members move and update their own tasks** (status, steps, comments, due date proposals visible to the lead). |

## 2. The project page (`/operations/projects/{id}`)

Opened from All projects, the sprint boards list, the opportunity's Project tab and the QA hub. Header: client, title, lead, dates (start, target end), contract total and currency, overall progress (decision 3), the next milestone due, and counts of overdue tasks and open QA tickets.

- **Overview**: milestones as a strip with weight, status, due date and progress; this week's sprint; overdue items; recent activity (from the audit log).
- **Milestones**: the kanban of decision 2. A card shows code and title, weight %, due date (and Overdue), progress, "Waits on …" warnings, and the invoice badge. Dragging between Not started, In progress, Client review and Accepted sets `project_phases.status` (Accepted asks for the acceptance date and who accepted, e.g. "Oyebode, CTO, 22 Oct"). **Invoiced** and **Paid** follow the milestone's invoice: dragging to Invoiced opens Finance's *Create invoice* prefilled (needs the finance permission; otherwise it says who can); Paid follows the invoice being marked paid in Finance. *New milestone* adds one (decision 7).
- **Sprints**: today's sprint board, kept as its component (`SprintBoard`, with its Sprint menu that now switches at once), with the task drawer of section 4. A strip above lists the sprints (dates, status, the weight of what they deliver); *New sprint* and a sprint's drawer edit them.
- **Backlog**: every task of the project, grouped by milestone, filterable by sprint, lead, status and Overdue; *New task* under any milestone; a task opens the same drawer. Tasks with no sprint are the backlog proper.
- **QA**: the project's QA tickets (link to the QA hub filtered to it).
- **Meetings and money**: links to the opportunity's Meetings tab (transcripts, handovers), the SOW, the project's invoices and change reports.

## 3. Drawers (side panels; edit in place; Escape closes; focus returns)

- **Milestone**: code and title, description, weight % (the project's milestones should add up to 100%: show the total and warn when they don't), status, start date, due date, accepted on/by, notes, waits on (other milestones), its tasks (with progress), its invoice. Delete only when it has no tasks or invoice.
- **Sprint**: title, start and end, status (Planned, Active, Completed), goal, notes, the milestone(s) it delivers (derived from its tasks) and their weight.
- **Task**: title, description, milestone, sprint (or backlog), **lead** (select of staff on the project or with project rights), people sharing it, watchers, status, due date (Overdue badge), estimated hours, steps (a checklist), waits on (other tasks of the project), comments (`task_comments`, with the existing attachment path), history (from the audit log), client-visible switch (`tasks.is_client_visible`). "Assign to me" stays as a shortcut beside the lead select.
- **Change report**: number (CR-01…), title, description, price and currency, effect on the timeline (days), status (Draft, Sent, Approved, Declined), the client's decision (date, name, title), the milestone and tasks it adds (drafted inside it, joining the plan only when Approved), and on approval the contract total grows by its price.

## 4. Data (all in PR 7's migrations)

- `project_phases`: `start_date`, `due_date`, `accepted_at`, `accepted_by_name`, `notes`. Backfill `due_date` from the latest `end_date` of the sprints holding its tasks (MyCity: one sprint per milestone), `start_date` from the earliest `start_date`.
- `sprints`: `goal`, `notes`.
- `tasks`: `due_date`; the lead stays `assigned_to`.
- `task_people` (`task_id`, `user_id`, `role` `sharing`|`watcher`, unique pair), `task_steps` (`task_id`, `position`, `text`, `done_at`, `done_by`), `task_dependencies` (`task_id`, `depends_on_task_id`, unique, no self, no loop), `phase_dependencies` (same for milestones).
- `change_reports` (`project_id`, `number`, `title`, `description`, `price`, `currency`, `timeline_days`, `status`, `decided_on`, `decided_by_name`, `decided_by_title`, `phase_id` once approved, audit columns).
- `projects`: `lead_user_id` (backfilled from the opportunity's lead, else the creator).
- MyCity's Change Report 01 (Mailbox, ₦250,000, declined 1 Nov 2026) is added by a small data migration only if the MyCity project exists, as Declined.

## 5. Rules

- Permissions: page and drawers need `projects.view`; editing everything needs being the project's lead or `projects.create`; a team member edits only tasks where they are the lead or share it (status, steps, comments). Check every action on the server; `#[Locked]` on ids.
- Every change writes `system_audit_logs` (it feeds the task and milestone history).
- Nothing here emails the client. Watchers and leads get the bell (and so a phone push) only.
- Dates show in Africa/Lagos. Overdue: due date before today and not Done/Accepted.
- Keep the existing sprint board behaviour (timers, the 45-minute check, QA review SLA `ops:qa-auto-approve`) unchanged.
- The **sprint-handover PR** (PR 6, built in parallel) adds *Schedule handover* to the sprint and turns transcript points into QA tickets. Keep `SprintBoard` as one component so both land; whichever merges second rebases and keeps both.
- EN and FR strings; sentence case; `docs/BRAND.md`; desktop and 375 px; no Composer or npm packages needed.

## 6. Done means

The page, its tabs and the four drawers work end to end on a local copy of MyCity's shape (11 milestones + M0, one sprint per milestone, 148 tasks), in English and French, desktop and 375 px; tests cover permissions (lead, manager, team member, outsider), weights and progress, dependencies (warn, loops refused), overdue, change reports (approved adds to the plan and the total; declined doesn't), and that nothing emails the client. CI green. The PR description lists what the owner should click to check it live on MyCity.

## 7. Starting the chat

Worktree `C:\Users\Tom-Blake\jbspraxis_opp\pr7`, branch `proj/pr7-project-page` (this file is its first commit). Copy `vendor` from `C:\Users\Tom-Blake\jbspraxis_import\praxis_core\vendor` and `public/build` from `C:\Users\Tom-Blake\jbspraxis_opp\pr1\praxis_core\public\build` (copy, not a link); port 8107; tests: `bash /c/Users/Tom-Blake/jbspraxis_project/_localtest/mail-test.sh pr7 <paths>` (database `praxis_test_mail_pr7`). Rebase on `main` before reporting.

## 8. Deployed

Nothing yet.

## 9. Changes and notes

- 1 Nov 2026: spec written from the owner's 8 answers.
