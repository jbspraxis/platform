# Separation plan: hq. + portal. + marketing

Agreed 29 October 2026. It runs before PR2.

## Target

| Address | What runs there | Who signs in |
|---|---|---|
| `jbspraxis.com` | Marketing site. From PR2 it is its own lean app, with no database password; it reaches HQ through a private API. | Nobody |
| `hq.jbspraxis.com` | HQ ERP: CRM, finance, payroll, operations, System Health, Roles & Permissions, Website settings; the task force (consultants). | Staff (`users`) |
| `portal.jbspraxis.com` | Client OS, proposals, sign-offs, contract checks, project trackers, cost reports. | Clients (`client_users`) |

HQ and the portal are **one codebase** (`praxis_core`) served on two subdomains. Each subdomain has only its own routes, its own login (guard) and its own session cookie. Everything shares **one database**.

## Owner decisions

- **Subdomains:** `hq.` and `portal.`.
- **Code:** the marketing site is separate (from PR2). HQ and the portal are one app.
- **Database:** one shared database. The marketing site goes through a private, key-protected API on `hq.`.
- **Accounts:** staff and clients are split, and the schema becomes proper Laravel migrations, so the database can be rebuilt from code.
- **Data:** test records are cleared. Reference data is kept: currencies, tax tables, chart of accounts, payroll settings, calculator settings, catalogues.
- **RBAC:** each module has view / create / edit / delete / approve / export, with no record scope. Roles can be created and edited. A staff member can hold several roles, and they add up.
- **Staff roles from day one:** Owner (protected), Partner/Director, Finance & Payroll, Delivery Lead, Engineer, Sales & Marketing, HR, Auditor.
- **Portal roles:** Client Admin, Client Finance and Client Member. Each client's admin invites colleagues, picks their role and limits them to projects. Custom portal roles can be added from HQ.

## Design choices (made by Claude, for the owner's review)

- **`users` stays the staff table.** About 150 HQ queries use it, so it is not renamed. **`client_users`** is new and holds everyone outside JBS: website leads (no portal access) and client portal accounts (invited, with a password). A lead becomes a portal user when invited; there is no copying between tables.
- **Tables written by both sides** get an `author_type` (`staff` / `client`) next to the author id: QA comments, QA attachments, task comments, QA tickets and payment proofs.
- **The `clearance_level` checks** in about 20 HQ components are replaced by permission checks. "Notify the admins" becomes "notify staff who can approve in this module".
- **Old addresses** redirect with a 301:
  - `jbspraxis.com/hq/*` → `hq.`
  - `/client/*`, `/portal/*`, `/signoff/*`, `/verify/*` → `portal.`
  - `/taskforce/*` → `hq.`
  - The cost-report page (`/dossier/*`) stays on `jbspraxis.com` until PR2 moves it behind the API.
- **The marketing pages keep running** from `praxis_core` on `jbspraxis.com` until PR2/PR3 replace them with the new app. This avoids porting pages that are about to be redesigned.

## Phases

| # | Phase | Main work | Estimate |
|---|---|---|---|
| S0 | Local copy | Local MySQL (Laragon) with the live schema imported; every phase is tested there first | 0.5 h |
| S1 | Migrations baseline | Generate Laravel migrations for all 91 tables from the live schema. Prove `migrate:fresh` rebuilds an identical schema. Reference-data seeders. | 3–4 h |
| S2 | Accounts split | `client_users` + `client` guard; `author_type` on shared tables; the portal, CRM, lead capture and HQ client screens move over | 4–6 h |
| S3 | RBAC | roles (staff/client), role_permissions (module × 6 actions), staff role pivot; a module registry; route and action checks; the Roles & Permissions screen; client team management; seed 8 + 3 roles | 5–7 h |
| S4 | Subdomains | cPanel subdomains + SSL; one web root for both; `Route::domain` groups; redirects from the old paths; links in emails and pages use the new addresses | 2–3 h |
| S5 | Data reset | Clear test records, keep reference data. The owner's account is created by a one-time setup link (the owner chooses the password). | 1 h |
| S6 | Deploy (owner approval) | Back up files and database; maintenance mode; upload; rebuild the database from migrations and seeders; verify everything | 2 h |

Total: about 18–24 hours of work, over 2–3 sessions. Each phase is committed locally and tested on the local copy. Production changes only once, at S6, after the owner's approval.

### S2 notes (done)
- Staff are `users` (guard `web`); everyone outside JBS Praxis is `client_users` (guard `client`): website leads (no password, cannot sign in), Kaizen contributors, and portal accounts (invited, set their own password).
- Shared tables keep the person's id plus a type: `qa_tickets.submitted_by_type`, `qa_comments.author_type`, `task_comments.author_type`, `crm_notes.author_type`, `qa_attachments.uploader_type`. `App\Support\People::joinAuthor()` adds the name either way.
- Kaizen articles: `created_by` is the "JBS Praxis Editorial" staff account (`User::EDITORIAL_ID`), the outside writer is `contributor_id`; the byline shows the contributor.
- `ClientActive` middleware signs a suspended client out on their next request. Staff setup link now has its own page (`/hq/setup/{token}`); the invite email picks the staff or portal link.
- Notifications stay staff-only. The client portal has no inbox, so the old "notify the client" rows (invoice issued, payment verified or rejected, ticket resolved) were never seen and are no longer written; emailing clients those events is a possible later addition.
- The HQ gate is now "any active staff account", with clearance 4 still required for senior screens until S3 replaces clearance with roles.

### S3 notes (roles & permissions)
- `config/access.php` is the single catalogue: 17 HQ modules and 7 portal modules, each with the actions that make sense (view, create, edit, delete, approve, export), plus the starter roles. `php artisan access:sync` (and the seeders) write it to `permissions` and add missing starter roles without touching roles the owner has edited.
- Staff hold one or more roles (`staff_roles`); permissions add up. Clients hold one portal role (`client_users.role_id`); an organisation's primary account is always Client Admin. Clearance levels and `users.role_id` are gone.
- Checks: routes use `can:<module>.view`; components check `Auth::user()->can('<module>.<action>')` on the server for every action (the UI only hides buttons); Blade uses `@can`. Staff notifications go to `Access::staffWith('<permission>')`.
- Starter staff roles: Owner (protected, always everything), Partner / Director, Finance & Payroll, Delivery Lead, Engineer, Sales & Marketing, HR, Auditor (view + export only). Client roles: Client Admin, Client Finance, Client Member.
- Guards against climbing: only an owner gives or removes the Owner role or changes an owner's account; nobody else can give a role, or add a permission to a role, that they don't hold themselves; nobody can change an account with more access than their own; the last active owner can't be suspended or lose the role. The same rule applies in the portal (a client role can only be given by someone holding all of its permissions; the primary account can't be changed from the Team tab).
- Screens: System & Access → Accounts (invite staff with roles, clients with a portal role) and Roles & Permissions (module × action matrix per role). Portal: Team tab assigns portal roles.

## Not in this project
- The new marketing app (PR2), the homepage and demo (PR3), proof pages (PR4).
- Record scope (all / team / own) and field masking: not chosen. Two-step sign-in comes later.
