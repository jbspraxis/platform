# HQ Mail: own and shared mailboxes (runbook for PRs 1–4)

Agreed with the owner on 30 October 2026. Staff read and send their **own mailbox** and **shared mailboxes** (intelligence@jbspraxis.com and the like) inside HQ (`hq.jbspraxis.com`), over IMAP and SMTP, the way MyCity's Mailbox does (praxisjbs/mycity-app, `docs/guides/mailbox.md`). Four PRs: PR 1 builds the foundation; **PRs 2, 3 and 4 are built in parallel, in separate chats, each from this document.**

> **Every chat working on Mail reads this whole file first and follows it.** Change a contract only in the PR that owns it, and record the change here (section 12) in the same commit.

## 1. Owner decisions (30 Oct 2026, binding)

| # | Question | Decision |
|---|---|---|
| 1 | When a PR is done | **Deploy each PR live as it is finished** (the owner signs in to cPanel in the built-in browser; Claude uploads). Mail stays **visible to Owner accounts only** until reading (PR 2) and sending (PR 3) are both live; then the owner presses *Open Mail to everyone* on Mailboxes. |
| 2 | Who adds a shared mailbox | Holders of the **Shared mailboxes: Create** permission (`mailboxes.create`; Owner and Partner have it). The person who adds one becomes its first manager. |
| 3 | Access levels | **Three fixed levels**: *Read* (sees all mail), *Read and send* (also replies, sends, files, deletes), *Manager* (also invites people, changes levels, updates the password, renames, removes). |
| 4 | From line on shared mail | **The mailbox**: `"JBS Praxis Intelligence" <intelligence@jbspraxis.com>`. Who wrote it shows in **their own signature** below the message; HQ records the sender (`mail_messages.sent_by`). |
| 5 | Signatures | **One company design built from HR records** (as MyCity): name, job title, department, phone from the employee record; logo and company details from settings; a person edits only their numbers. On a shared mailbox the signature carries the person and the shared address. |
| 6 | Read state in shared mailboxes | **Shared, with who opened it**: opening marks it read for everyone (the server's `\Seen`), and HQ shows "Opened by Ade, 10:42" (`seen_by`, `seen_at`). |
| 7 | Teamwork in shared mailboxes | **All four**: assign a conversation (with "Assigned to me"), internal notes, "someone is replying" warning, Open / Done (back to Open when the client replies). |
| 8 | What HQ keeps | **12 months copied** (headers and text, for fast lists and search); older mail and **all attachments** are read from the server when opened. |
| 9 | New-mail alerts | **Everything to everyone**: every new message in a mailbox goes to the bell (and so phone push) of everyone who can read that mailbox. |
| 10 | Praxis AI | **Write with AI and Summarise, on request** (as MyCity), **plus Ask-your-company can search mail** the person can read. HQ has no Ask-your-company chat yet, so PR 4 builds *Ask about your mail* in Mail on a reusable search contract (section 7.6). |

Server settings (cPanel's "Mail Client Manual Settings" for jbspraxis.com): `mail.jbspraxis.com`, **IMAP 993 SSL/TLS**, **SMTP 465 SSL/TLS**, username = full address, password = the mailbox's password. The server is Dovecot (IMAP: CONDSTORE, MOVE, UIDPLUS, SPECIAL-USE, LITERAL+) and Exim (SMTP) on the same cPanel machine as HQ. Folders are `INBOX`, `INBOX.Sent`, `INBOX.Drafts`, `INBOX.Junk` or `INBOX.spam`, `INBOX.Trash`, `INBOX.Archive`.

## 2. The four PRs

| PR | Branch | Scope | Status |
|---|---|---|---|
| **1 Foundation** | `mail/pr1-foundation` | All tables; models; access and levels; encrypted passwords; IMAP client (hand-written), MIME and BODYSTRUCTURE reading; SMTP transport; sync (`mail:sync` every minute); actions written back to the server; live reads (bodies, attachments, older mail, server search); `MailReceived` event; **Mailboxes** screen (connect, shared mailboxes, people, passwords, rename, remove, *Open Mail to everyone*); Mail in the rail with unread count; routes and stub components for PRs 2–4; fake mail server for tests; this runbook. | **Live** 30 Oct 2026; merged to `master` |
| **2 Reading** | `mail/pr2-reading` | The **Mail page** (`/mail`): mailbox switcher, folders with counts, conversation list (unread, flagged, attachment, assigned, Done badges; filters Unread / Assigned to me / Open / Done; pagination), the **reader** (whole conversation, safe HTML, remote images held back with Load images / Always for this sender, `cid:` pictures, quoted text folded, attachments to download, "Opened by"), toolbar actions (archive, spam, trash, move, mark unread, flag, delete for good in Trash / Spam, empty Trash / Spam), **search** (HQ's copy, then "Search older mail on the server"), "Load older mail" past 12 months, Refresh and auto-refresh, phone layout, keyboard shortcuts. | Not started |
| **3 Writing** | `mail/pr3-writing` | The **composer** (new, reply, reply all, forward, edit draft; To/Cc/Bcc with suggestions; rich text; attachments, forwarding the original's; drafts saved as you type and kept in the server's Drafts; send now, **undo send** (10 s), schedule), the **outbox** and `mail:send-due`, copy in Sent, `sent_by`; **signatures** (company design from HR records, the Signature page, company settings); **Write with AI** in the composer; marks the reply's original `\Answered`. | Not started |
| **4 Teamwork and AI** | `mail/pr4-team` | On shared mailboxes: **assign** (+ notification), **Open / Done** (reopens on a client reply), **internal notes**, **viewing / replying** presence, activity timeline; **new-mail notifications** to everyone who can read the mailbox (bell, so push) with a *Mail* push group; **Summarise** (card above the conversation, kept until new mail); **Ask about your mail** (Praxis AI answers from mail the person can read, with links). | Not started |

PRs 2, 3 and 4 depend only on PR 1, never on each other: each works against PR 1's contracts and the stubs in section 6. Where one PR's feature shows inside another's screen, the screen keeps a **mount point** (section 6.2) and the other PR fills the component behind it.

## 3. Starting a PR chat (PRs 2–4)

Paste this to a new chat: *"You are building HQ Mail PR N. Read docs/MAIL.md in the worktree (all of it) and follow it; your scope is section 2, your files section 5."*

1. **Worktree** (never work in `C:\Users\Tom-Blake\jbspraxis_project` itself: other sessions use that checkout), branched from `master` once PR 1 is merged there:
   ```bash
   cd /c/Users/Tom-Blake/jbspraxis_project && git worktree add -b mail/prN-<name> ../jbspraxis_mail/prN master
   ```
2. **Make it runnable** (all ignored by git). Copy, never link, `vendor`: a junction makes Composer's autoloader load the *main* checkout's `app/`.
   ```powershell
   robocopy C:\Users\Tom-Blake\jbspraxis_project\praxis_core\vendor C:\Users\Tom-Blake\jbspraxis_mail\prN\praxis_core\vendor /E /NFL /NDL /NJH /NP /MT:16
   robocopy C:\Users\Tom-Blake\jbspraxis_project\praxis_core\public\build C:\Users\Tom-Blake\jbspraxis_mail\prN\praxis_core\public\build /E
   copy C:\Users\Tom-Blake\jbspraxis_project\praxis_core\.env.localmysql C:\Users\Tom-Blake\jbspraxis_mail\prN\praxis_core\
   ```
   Then `php artisan package:discover` in the worktree's `praxis_core`.
3. **PHP**: always Laragon PHP 8.3, `/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe` (same as the server). The `php` on PATH is 8.5 without MySQL drivers.
4. **MySQL** on `127.0.0.1:3307` (root, no password; data in `_localtest/mysql-data`). If it isn't running, start it in the background: `"/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqld.exe" --datadir="C:/Users/Tom-Blake/jbspraxis_project/_localtest/mysql-data" --port=3307 --bind-address=127.0.0.1 --mysqlx=OFF --console`. Never stop it if another session started it.
5. **Your databases** (never someone else's): dev `praxis_mail_prN`, tests `praxis_test_mail_prN` (the test runner creates it). Dev setup: create it, then `APP_ENV=localmysql DB_DATABASE=praxis_mail_prN php artisan migrate:fresh --seed --force`, then demo users and mailboxes: `APP_ENV=localmysql DB_DATABASE=praxis_mail_prN php artisan tinker /c/Users/Tom-Blake/jbspraxis_project/_localtest/mail_make_demo.php` (writes `_localtest/mail_test_users.json`: an Owner and a Sales account; the demo mailboxes have no real server, so Refresh on them fails by design).
6. **Your local server port**: PR 2 `8096`, PR 3 `8097`, PR 4 `8098` (PR 1 used `8095`). `APP_ENV=localmysql DB_DATABASE=praxis_mail_prN APP_URL=http://hq.localhost:809N php artisan serve --host=127.0.0.1 --port=809N`, then `http://hq.localhost:809N`. Stop only your own server (by its PID or background task), never `taskkill /IM php.exe`.
7. **At most 5 agents at once on this PC, all chats together** (the owner's standing rule; it crashed twice). Work in the foreground; use an agent only when it clearly helps.
8. **No Composer, npm or Vite changes**: the server has no terminal. CSS goes in a `<style>` block in your views (brand tokens from `resources/css/app.css` only); JS inline or as a file served like `resources/pwa/` is. Icons are Lucide (`<i data-lucide="…">`).
9. **No real mailbox passwords** in code, tests, logs or chat. Tests use the fake server (section 9). The owner tests on live after deploy.

## 4. Architecture

```
HQ (Livewire)                         App\Support\Mail                                Mail server (cPanel)
-------------                         ----------------                                --------------------
Mailboxes (PR1) ──► MailConnector ──► MailServer::connect()/checkSmtp() ──IMAP 993──► Dovecot
Inbox (PR2)     ──► MailActions   ──► MailServer::open() → ImapSession ──────────────► (flags, MOVE, EXPUNGE)
                ──► MailFetch     ──► bodies, attachments, older mail, server search
Composer (PR3)  ──► MailTransport ──► MailServer::send() ─────────────SMTP 465──────► Exim; then APPEND to Sent
schedule: mail:sync (every minute) ──► MailSync ──► MessageWriter, Threader, ThreadCounters ──► mail_* tables
                                                 └─► event MailReceived ──► PR4 listener: notifications (bell → push)
                    mail:send-due (every minute, PR3) · mail:prune (daily 03:40)
```

- **No IMAP extension and no Composer on the server**: the IMAP client is hand-written PHP over TLS sockets (`App\Support\Mail\Imap\ImapClient`), tested against a scripted server over a real socket. SMTP uses Symfony Mailer's `EsmtpTransport`, already in `vendor`.
- **Passwords** are encrypted with APP_KEY in `mail_accounts.secret` (`MailCredentials`) and read back only by `MailServer`. They never go into a Livewire property after use, a log, an exception message, or the audit log.
- **Sync** (`MailSync`): per folder, Inbox first: `UID SEARCH SINCE <12 months ago>` gives the UIDs that belong in HQ; new ones are fetched newest first in batches of 40 (headers, flags, BODYSTRUCTURE), then their text and HTML parts (batched by section); UIDs gone from the server (deleted, moved elsewhere, or now older than 12 months) are removed; flags are updated with CONDSTORE `CHANGEDSINCE`. A changed UIDVALIDITY drops that folder's copy and reads it again. 50 seconds per run shared by all mailboxes, least recently synced first; a first copy carries on over several runs. Other folders are checked every 5 minutes, the folder list every 30. One run per mailbox at a time (cache lock `mail-sync:<id>`).
- **A refused password** marks the mailbox `needs_reconnect`, tells its managers once (bell), and stops trying until someone enters the password on Mailboxes.
- **Bodies too large** (over 1 MB each of text and HTML) are not copied (`body_state = remote`) and are read when opened. HTML is stored as sent (unsanitised).

## 5. Files and owners

**Never edit a file another PR owns.** If you need a change in one, write it down under section 12 "Requests" and tell the owner; PR 1's files change only in a follow-up agreed with the owner.

| Owner | Files |
|---|---|
| **PR 1** (frozen after merge, except agreed follow-ups) | `config/mail_client.php`; `database/migrations/2026_10_31_100000_create_mail_tables.php`, `…100001_grant_mail_permissions.php`; `app/Models/Mail/*`; `app/Support/Mail/*` (all of it: `Imap/`, `Mime/`, `Sync/`, `MailAccess`, `MailActions`, `MailFetch`, `MailServer`, `MailTransport`, `MailConnector`, `MailMembers`, `MailCredentials`, `MailSettings`, `MailNotices`, `MailNav`, `Autodiscover`, `MailSendException`); `app/Events/Mail/*`; `app/Providers/MailServiceProvider.php`; `app/Console/Commands/{MailSyncCommand,MailPrune,MailCheck}.php`; `app/Livewire/Hq/Mail/Mailboxes.php` and `resources/views/livewire/hq/mail/mailboxes.blade.php`, `partials/mailbox-row.blade.php`, `partials/server-fields.blade.php`; `routes/mail.php`; `tests/Support/Mail/*`; `tests/Unit/Mail/*`; `tests/Feature/Mail/{MailTestKit,MailSyncTest,MailAccessTest,MailActionsTest,MailboxesScreenTest}.php`; this file's sections 1–11. Shared files PR 1 changed once: `config/access.php` (modules `mail`, `mailboxes`), `routes/web.php` (one `require`), `routes/console.php` (three schedule lines), `bootstrap/providers.php` (one line), `resources/views/components/layouts/hq.blade.php` (the Mail rail entry). |
| **PR 2** Reading | `app/Livewire/Hq/Mail/Inbox.php` and `resources/views/livewire/hq/mail/inbox.blade.php` (replace PR 1's placeholder), any new `app/Livewire/Hq/Mail/Read*.php` / `Reader*.php` / `ThreadList*.php` / `Folder*.php` and their views, `resources/views/livewire/hq/mail/read/*`, `routes/mail/read.php`, `app/Http/Controllers/Mail/*` (attachments, inline pictures), `app/Support/Mail/Read/*` (the HTML sanitiser, list queries, search), `resources/mail/read*.js` if needed, `database/migrations/2026_10_31_2*` (e.g. image trust per sender), `tests/Feature/Mail/Read*Test.php`, `tests/Unit/Mail/Read*Test.php`. |
| **PR 3** Writing | `app/Livewire/Hq/Mail/Composer.php` and `composer.blade.php` (replace the stub), `app/Livewire/Hq/Mail/Signature.php` and `signature.blade.php` (replace the placeholder), `app/Livewire/Hq/Mail/Compose*.php` and views under `resources/views/livewire/hq/mail/compose/*`, `app/Console/Commands/MailSendDue.php` (replace the stub), `app/Support/Mail/Compose/*` (outbox, queue, MIME building, signatures, Write with AI), `routes/mail/compose.php`, `resources/mail/compose*.js` / `.css` if needed, `database/migrations/2026_10_31_3*` (signature tables), `tests/Feature/Mail/Compose*Test.php`, `tests/Unit/Mail/Compose*Test.php`. |
| **PR 4** Teamwork and AI | `app/Livewire/Hq/Mail/{ThreadTeam,ThreadSummary,AskMail}.php` and their views (replace the stubs), `app/Livewire/Hq/Mail/Team*.php` and views under `resources/views/livewire/hq/mail/team/*`, `app/Listeners/Mail/*`, `app/Support/Mail/Team/*` (assignment, status, notes, presence, notifications), `app/Support/Mail/Ai/*` (summary, search, Ask), `routes/mail/team.php`, `database/migrations/2026_10_31_4*`, `tests/Feature/Mail/Team*Test.php`, `tests/Unit/Mail/Team*Test.php`; and the one-line `mail` group in `app/Support/Pwa/PushCategories.php` (section 7.5). |

Other rules:
- **Migrations**: only in your range (`2026_10_31_2*` PR 2, `_3*` PR 3, `_4*` PR 4). Never change PR 1's tables' existing columns; add your own tables or columns.
- **Config**: your own file if you need one (`config/mail_read.php`, `config/mail_compose.php`, `config/mail_team.php`); never edit `config/mail_client.php`.
- **Settings** (`mail_settings` via `MailSettings::get/set`): PR 3 uses keys starting `signature.`, PR 4 `team.` and `ai.`. `open_to` is PR 1's.
- **No other shared HQ file** (layout, `routes/web.php`, `routes/console.php`, `config/access.php`, `bootstrap/*`, `app.css`, the bell) may change in PRs 2–4, apart from PR 4's one line in `PushCategories`. Everything they need is already wired by PR 1.
- Writing style for every word a person sees: `docs/BRAND.md` section 5 (plain, sentence case, verb-first buttons, no blame in errors). HQ is in English.

## 6. Screens, routes and mount points

### 6.1 Routes (HQ host, signed-in staff, `can:use-mail`; `routes/mail.php`)

| Route | Name | Component | Owner |
|---|---|---|---|
| `GET /mail?box=<account id>&folder=<role or folder id>&thread=<thread id>` | `hq.mail.inbox` | `App\Livewire\Hq\Mail\Inbox` | PR 2 |
| `GET /mail/mailboxes?box=<account id>` | `hq.mail.mailboxes` | `App\Livewire\Hq\Mail\Mailboxes` | PR 1 |
| `GET /mail/signature` | `hq.mail.signature` | `App\Livewire\Hq\Mail\Signature` | PR 3 |
| anything else under `/mail/…` | `hq.mail.read.*` / `hq.mail.compose.*` / `hq.mail.team.*` | in `routes/mail/read.php`, `compose.php`, `team.php` | PR 2 / 3 / 4 |

**Links into Mail** (notifications, other screens) always use `route('hq.mail.inbox', ['box' => $accountId, 'thread' => $threadId], false)` (relative, as other bell links are). PR 2 must open that mailbox and conversation from those parameters, and `folder` defaults to `inbox` (a conversation link shows the conversation whatever folder it is in).

The `use-mail` gate (`MailServiceProvider`) = the `mail.view` permission **and** Mail open to the person (`MailAccess::canUse()`). Each component checks again in `mount()` and every action checks the mailbox's level (section 7.1).

### 6.2 Mount points (PR 2's Inbox view must contain these)

| Mount point | Where | Filled by | Notes |
|---|---|---|---|
| `<livewire:hq.mail.composer />` | once on the Mail page, outside the list and reader | PR 3 | Opens on the browser event `mail-compose` (6.3). Modal on desktop, full screen on phones. |
| `<livewire:hq.mail.thread-summary :thread-id="$threadId" :key="'sum-'.$threadId" />` | top of the reader, above the messages | PR 4 | Summarise button and the summary card. Not in Spam. |
| `<livewire:hq.mail.thread-team :thread-id="$threadId" :key="'team-'.$threadId" />` | the reader's header or side, for **shared** mailboxes only | PR 4 | Assignee, Open / Done, notes, who is viewing / replying, activity. |
| `<livewire:hq.mail.ask-mail :account-id="$accountId" />` | the Mail page header | PR 4 | *Ask about your mail* button and panel. |

Until PR 3 / PR 4 land, the stubs render an empty `<div>`, so PR 2's page works with them in place. PR 3 and PR 4 test their components on their own (Livewire tests) and, locally, by adding the mount point to their copy of PR 1's placeholder Inbox view **without committing it** (the Inbox view is PR 2's).

### 6.3 Browser and Livewire events between the parts

| Event | From → to | Payload | Meaning |
|---|---|---|---|
| `mail-compose` | PR 2 (Reply, Reply all, Forward, New, a draft) → PR 3 Composer | `{mode: 'new'\|'reply'\|'reply_all'\|'forward'\|'draft', accountId, messageId?: int, outboxId?: string, to?: string}` | Open the composer. `messageId` is `mail_messages.id` answered or forwarded. |
| `mail-sent` | PR 3 → PR 2 | `{accountId, threadId?: int, outboxId}` | A message was queued or sent: refresh the list and the open conversation. |
| `mail-composing` | PR 3 → PR 4 | `{threadId: int, state: 'replying'\|'stopped'}` | The person started or stopped replying in a conversation (presence). |
| `mail-thread-opened` | PR 2 → PR 4 | `{accountId, threadId}` | A conversation is on screen (presence "viewing", notes load). |
| `mail-thread-changed` | PR 4 (assign, status) and PR 2 (actions) → PR 2 | `{threadId}` | Refresh that conversation's row and header. |
| `mail-refresh` | anyone → PR 2 | `{accountId?}` | Reload counts and list. |

Dispatch with `$this->dispatch('mail-compose', mode: 'reply', accountId: …, messageId: …)` (Livewire 4 named arguments); listen with `#[On('mail-compose')]`.

## 7. Contracts (PR 1 provides; PRs 2–4 call)

All in `App\Support\Mail` unless named otherwise. **Every call takes the acting `User` and checks access itself**; never query mail tables for a person without `MailAccess`.

### 7.1 Access (`MailAccess`)

```php
MailAccess::canUse(?User $u): bool                 // mail.view and Mail open to them (owner-only until opened)
MailAccess::canAddShared(?User $u): bool           // + mailboxes.create
MailAccess::level(?User $u, MailAccount $a): ?string   // 'owner' (own personal mailbox) | 'manage' | 'send' | 'read' | null
MailAccess::can(?User $u, MailAccount $a, string $ability): bool  // read | send | organise (flag, move, delete; = send) | manage
MailAccess::authorize(?User $u, MailAccount $a, string $ability): void   // abort 403
MailAccess::canThread(?User, MailThread, $ability) / canMessage(?User, MailMessage, $ability): bool
MailAccess::accounts(?User $u, string $ability = 'read'): Collection<MailAccount>   // personal first; each has ->access_level
MailAccess::accountIds(?User $u, string $ability = 'read'): array
MailAccess::readers(MailAccount $a): array<string>   // active user ids who can read it now (PR 4 notifications)
MailAccess::managers(MailAccount $a): array<string>
MailAccess::isOwner(User $u): bool; MailAccess::isOpenToEveryone(): bool; MailAccess::flush(): void
```

Personal mailboxes are private: nobody else sees them, not even Owner accounts. Owner accounts are managers of every shared mailbox without being listed. Teamwork (assign, notes, status) is for **shared** mailboxes; PR 4 may also allow Open / Done on personal ones, the owner's call.

### 7.2 Reading and changing mail

```php
MailActions::make()->opened(User, MailMessage)                         // marks read (server + HQ) and records seen_by/seen_at once
MailActions::make()->markRead(User, iterable<MailMessage|int>|MailThread, bool $read = true)
MailActions::make()->flag(User, iterable|MailThread, bool $flagged = true)          // organise
MailActions::make()->moveToRole(User, iterable|MailThread, 'archive'|'spam'|'trash'|'inbox')  // creates INBOX.Archive if missing
MailActions::make()->move(User, iterable|MailThread, MailFolder $to)
MailActions::make()->deleteForever(User, iterable|MailThread)          // only messages in Trash or Spam (else 422)
MailActions::make()->emptyFolder(User, MailFolder)                     // Trash or Spam: everything on the server
MailFetch::make()->body(User, MailMessage): array{text: string, html: ?string}   // cached or read from the server
MailFetch::make()->attachment(User, MailAttachment): array{filename, mime, content}   // decoded bytes; 413 over 30 MB
MailFetch::make()->inline(User, MailMessage, string $contentId): ?array            // cid: pictures
MailFetch::make()->older(User, MailFolder, ?int $beforeUid = null, int $limit = 50): array{items: list<array>, next: ?int}
MailFetch::make()->message(User, MailFolder, int $uid): ?array        // a live (older) message with text, html, attachments
MailFetch::make()->liveAttachment(User, MailFolder, int $uid, string $section): array
MailFetch::make()->searchServer(User, MailFolder, string $query, int $limit = 50): list<array>   // all dates
Sync\MailSync::make()->account(MailAccount, ?float $budget = null, bool $allFolders = false): array   // Refresh
MailNav::forget(string $userId)                                        // after changing what someone has unread
```

Actions write to the server first and throw `Imap\ImapException` when it refuses; show `$e->getMessage()` in a calm sentence ("The mail server didn't accept that. Try again in a minute.") and log nothing secret. A message with `body_state` `remote` / `pending` is read through `MailFetch::body()`; never show `body_html` without PR 2's sanitiser.

### 7.3 Sending (`MailTransport`, PR 3 builds on it)

```php
$result = MailTransport::make()->send(User $sender, MailAccount $account, Symfony\Component\Mime\Email $email, bool $copyToSent = true);
// ['message_id' => 'abc…@jbspraxis.com' (no brackets), 'raw' => message as sent, 'sent_uid' => ?int UID in Sent]
MailTransport::newMessageId(MailAccount): string        // set it before sending if the outbox needs it first
MailTransport::make()->appendToSent(MailAccount, string $raw): ?int
```

`send()` checks `send` level, sets **From to the mailbox** (decision 4), adds Message-ID and Date if missing, sends over the mailbox's own SMTP, then appends the copy to Sent (`\Seen`). Bcc goes in the envelope only (Symfony strips the header). Throws `MailSendException` with `$reason` (`auth`, `unreachable`, `rejected`, `temporary`) and `$permanent`. To have sync credit the sender, PR 3 writes the outbox row with `message_id_hash = MailMessage::hashId($messageId)` and status `sending`/`sent` **before** the Sent copy is synced; sync then sets `mail_messages.sent_by` from `mail_outbox.user_id`.

### 7.4 Events

| Event | When | Payload |
|---|---|---|
| `App\Events\Mail\MailReceived` | sync stored new Inbox mail that arrived since the last check (not the first copy, not mail older than 48 h, not mail the mailbox sent itself) | `accountId`, `messageIds` (oldest first), `threadIds` |
| `App\Events\Mail\MailThreadsMerged` | a reply linked two conversations; messages, notes, activity, outbox rows already moved to `intoId`; presence of the others deleted | `fromIds`, `intoId` |

Listeners go in `app/Listeners/Mail/` (Laravel's event discovery finds them; register nothing). Sync runs in the scheduler, so a listener must be quick and must not throw (catch, log, move on).

### 7.5 Notifications

Insert into `notifications` (as `MailNotices::send()` does: `type` `Message`, `link_url` relative). PR 1 sends: *Mail needs a password* (managers) and *You have a shared mailbox* (the invited person). **PR 4** sends new-mail notifications: title `New mail: <sender name>` (or the address), message the subject (else the first words), link to the conversation (6.1), to `MailAccess::readers($account)` (decision 9), one per message, nothing to the person who sent it from HQ. PR 4 adds `'mail' => 'Mail'` to `PushCategories::LABELS` with the path rule `/mail` → `mail`, so people can mute mail push separately.

### 7.6 Praxis AI (PR 3 Write with AI, PR 4 Summarise and Ask)

- Provider: **Gemini only** (`config('services.gemini.*')`, as `App\Services\Site\ChatService` calls it, `Http::` with timeouts). **Never DeepSeek for mail** (personal data), as in MyCity. Use `gemini-*-flash` for drafts and summaries, not flash-lite.
- Only on the person's press; the AI never sends anything. It reads **only** what the person can read (`MailAccess`), never attachments' contents, at most the conversation's last 30 messages (quoted copies stripped), and never Spam.
- PR 4 builds `App\Support\Mail\Ai\MailSearch::for(User $user, string $question, int $limit = 20): list<array{message_id: int, thread_id: int, account_id: string, subject, from, date, excerpt}>` over HQ's copy (MySQL FULLTEXT `mail_messages_search` on subject, from_name, from_email, to_text, body_text), so a future HQ-wide *Ask your company* can call it. Answers cite the messages they used, as links (6.1).
- Summaries are stored in `mail_threads.ai_summary` (JSON: gist, key points, asked of you) and `ai_summary_at`; sync clears both when a newer message arrives (decision: kept until new mail).

## 8. Tables (all created by PR 1)

| Table | What | Notes |
|---|---|---|
| `mail_accounts` | a connected mailbox | `id` uuid; `kind` personal/shared; `email` unique; `display_name` (the From name); `owner_user_id` (personal: its person; shared: who added it); `username`; `secret` (encrypted password, hidden); `imap_*`, `smtp_*`; `status` active/needs_reconnect/paused; `last_error`; `capabilities`; `folders_synced_at`; `last_synced_at` |
| `mail_account_members` | people on a shared mailbox | `account_id`, `user_id` unique together; `level` read/send/manage; `added_by` |
| `mail_folders` | server folders | `path` (server name, use in IMAP), `name` (readable), `role` inbox/sent/drafts/archive/spam/trash/other, `selectable`, `uidvalidity`, `uidnext`, `highestmodseq`, `unread_count` and `total_count` (HQ's copy), `server_unseen`, `server_total`, `initial_synced_at` (first copy done), `synced_at` |
| `mail_threads` | conversations | `subject`, `subject_key`, `snippet`, `participants` [{name, email}], `message_count`, `unread_count` (copies counted once; Spam and Trash left out unless only there), `has_attachments`, `first_message_at`, `last_message_at`, `last_inbound_at`; **PR 4**: `status` open/done, `assigned_to`, `assigned_by`, `assigned_at`, `done_by`, `done_at`, `ai_summary`, `ai_summary_at` |
| `mail_messages` | one message in one folder | `folder_id` + `uid` unique; `thread_id`; `message_id` (+ `message_id_hash`, use `MailMessage::hashId()`), `in_reply_to`, `references`; `subject`, `from_name`, `from_email`, `to`/`cc`/`bcc`/`reply_to` [{name, email}], `to_text`; `sent_at` (Date), `received_at` (arrival; sort by this); flags `is_seen`, `is_flagged`, `is_answered`, `is_draft`; `has_attachments` (not counting inline pictures); `is_outbound`; `snippet`; `body_text`, `body_html` (hidden from serialisation), `body_state` pending/cached/remote/error, `parts`; `list_unsubscribe`; `seen_by`, `seen_at` (first opener in HQ, decision 6); `sent_by` (decision 4); FULLTEXT `mail_messages_search` on MySQL |
| `mail_attachments` | attachment details | `section`, `filename`, `mime`, `encoding`, `size` (decoded, estimated), `content_id`, `is_inline`. The file stays on the server. |
| `mail_outbox` | PR 3: drafts, queued, scheduled, sent | `id` uuid; `account_id`; `user_id` (who wrote it); `kind` new/reply/reply_all/forward; `status` draft/queued/sending/sent/failed/cancelled; `thread_id`; `reply_to_message_id`; `payload` JSON (PR 3 decides its keys and documents them in section 12); `message_id`, `message_id_hash`; `send_at`; `sent_at`; `attempts`; `last_error`; `idempotency_key` unique |
| `mail_thread_notes` | PR 4: team notes | `thread_id`, `user_id`, `body`, soft deletes |
| `mail_thread_activity` | PR 4: what happened to a conversation | `thread_id`, `user_id`, `type` (assigned, unassigned, done, reopened, note, sent, …), `data`, `created_at` |
| `mail_presence` | PR 4: viewing / replying | primary key (`thread_id`, `user_id`); `state` viewing/replying; `expires_at` (keep it short, e.g. 60 s, refreshed while on screen); `mail:prune` clears expired rows |
| `mail_settings` | key → JSON | `open_to` (PR 1), `signature.*` (PR 3), `team.*`, `ai.*` (PR 4) |

Removing a mailbox deletes everything below it (foreign keys cascade); the server's mail is never touched.

## 9. Testing

- **Fake server**: `Tests\Support\Mail\FakeMailServer::install()` replaces `MailServer` for the test; `addMailbox($email, $password)`, `deliver($email, 'INBOX', FakeMailServer::message(from:, to:, subject:, text:, html:, messageId:, inReplyTo:, references:, date:, cc:, attachments: [['name', 'mime', 'content', 'inline'?, 'cid'?]]), $flags)`, `setFlags()`, `remove()`, `renumber()`, `setPassword()`, `folder($email, $path)`, `$server->sent` (SMTP), `$server->smtpFailure = 'rejected'|'temporary'|'auth'`. It behaves like Dovecot (UIDs, UIDVALIDITY, MODSEQ, MOVE with COPYUID, APPEND with APPENDUID).
- **Fixtures**: `use Tests\Feature\Mail\MailTestKit;` gives `setUpMail()`, `openMailToEveryone()`, `mailbox($email, 'shared'|'personal', ?$owner)`, `member($account, $user, $level)` and `staff($slug, [$roles])` (from `UsesThrowawayDatabase`). Feature tests need MySQL.
- **Run**: from your worktree's `praxis_core`: `bash /c/Users/Tom-Blake/jbspraxis_project/_localtest/mail-test.sh prN [phpunit args]` (default: `tests/Unit/Mail tests/Feature/Mail`; database `praxis_test_mail_prN`, rebuilt each run; a throwaway APP_KEY). Before handing over, run the **whole** suite once (`… mail-test.sh prN tests`): the only known failure is Laravel's stock `Tests\Feature\ExampleTest` (it requests `/` without a host; HQ answers per host).
- **Look at it**: your local server (section 3) in the built-in browser, desktop and the mobile preset (375 px), signed in with `_localtest/mail_test_users.json`.
- PR 1's suite: 43 tests (21 unit, including the IMAP client over a real socket against `tests/Support/Mail/scripted_imap_server.php`; 22 feature on MySQL).

## 10. Deploy (owner present, cPanel)

Each PR, when its tests pass and the owner has seen it locally:

1. **Commit** on the PR branch (end the message with the attribution lines the session gives). **Merge into `master`** in the main checkout only when the owner says so: `git -C /c/Users/Tom-Blake/jbspraxis_project merge --no-ff mail/prN-…` (the main checkout has other sessions' uncommitted work: merge only if it applies cleanly to the files you touch; never stash or reset their work).
2. **Owner signs in** to cPanel (`https://srv-web-ns9.newtoncorp.fr:2083`, account `smartqaq`) in the built-in browser. No terminal: uploads go through the cPanel file API from that tab (relay tooling in `_localtest/`, as for the PWA: `pwa_deploy_build.py`, `pwa_bridge.js`; Mail's builder is `_localtest/mail_deploy_build.py`).
3. **Backups first**: every live file you will replace is copied to `~/mail_backup_<pr>_<date>/` on the server, and the database is backed up (cPanel, Backup).
4. **Upload** `praxis_core` files only, built from `git diff --name-status master^1..master` of the merge (new files, then changed shared files, each replaced only after checking the live copy matches the previous `master` version). Text files ship with LF endings.
5. **Migrations**: a one-off cron job runs `php artisan migrate --force --path=database/migrations/<your files>` (each file by name, never a bare `migrate`), with output to `~/mail_migrate_<pr>.log`; remove the cron right after. No config or route cache on the server, so nothing to clear.
6. **Check live**: `/mail/mailboxes` loads for the owner, the scheduler runs `mail:sync` (the cron `schedule:run` already exists), errors in System Health stay clear, and what the PR added works with a real mailbox (**the owner** enters mailbox passwords, never Claude).
7. **Record** it in section 11: date and time, files, backups, migration log, what was checked, rollback.

**Rollback**: copy the backup files back (the new files are then unused); new tables can stay, or `migrate:rollback --path=<file>` from a one-off cron.

## 11. Deployed

### PR 1: 30 October 2026, about 13:40 CET (owner signed in to cPanel; Claude uploaded)

- **Files**: 66 new files (app, config, migrations, views, routes; no tests or docs) uploaded through the cPanel file API (`Fileman/upload_files`) from the signed-in tab, each read back and checked by SHA-256. Builder: `_localtest/mail_deploy_build.py ../jbspraxis_mail/pr1 master mail/pr1-foundation _localtest/mail_deploy_pr1`; relay: `node _localtest/pwa_bridge.js <pull dir> _localtest/mail_deploy_pr1 8765`, the cPanel tab visiting `http://127.0.0.1:8765/relay?pull=1&b=bN&to=<cPanel URL>` (direct `fetch` to 127.0.0.1 from cPanel is blocked).
- **Shared files** (5): `config/access.php`, `bootstrap/providers.php`, `routes/console.php`, `routes/web.php`, `resources/views/components/layouts/hq.blade.php`, replaced **after** the migrations ran (the rail reads `mail_settings`). Four matched `master` on the server; the HQ layout had a live hotfix not in git (`<head><meta charset="utf-8">`, line 3), kept and committed (`d0db5a2`).
- **Backups**: the 5 live shared files in `~/mail_backup_pr1_20261030/` (names with `__` for `/`; saved with CRLF endings by `save_file_content`, content otherwise identical). No database backup: the migrations only add tables and permission rows (rollback below).
- **Migrations**: one-off cron (removed straight after; the 7 other cron jobs untouched), log `~/mail_migrate_pr1.log`: `create_mail_tables` DONE, `grant_mail_permissions` DONE.
- **Checked live**: jbspraxis.com, portal sign-in, HQ sign-in and `/manifest.webmanifest` answer 200; `/mail` and `/mail/mailboxes` redirect to sign-in when signed out; no errors in `storage/logs` after the scheduler's `mail:sync` runs. Mail is **Owner-only** (`open_to` = owner).
- **Not yet checked by a person**: connecting a real mailbox. The owner connects their own mailbox and intelligence@ on HQ → Mail → Mailboxes (Claude never enters mailbox passwords).
- **Rollback**: copy the 5 backups back over the shared files (convert `__` to `/`); the new files are then unused. Tables can stay, or a one-off cron runs `migrate:rollback --path=database/migrations/2026_10_31_100001_grant_mail_permissions.php --path=database/migrations/2026_10_31_100000_create_mail_tables.php`.

## 12. Changes to contracts, PR notes and requests

*(each PR records here: payload keys it chose, new events, anything another PR must know; and requests for changes to files it doesn't own)*

- **PR 1, 30 Oct 2026**: foundation as above. Local check: Mailboxes on desktop and at 375 px, People dialog, the rail entry with unread count, the placeholder Inbox.
- **PR 4, 30 Oct 2026** (branch `claude/mail-pr4-team-5ec64c`, not merged or deployed): teamwork, new-mail alerts, Summarise, Ask about your mail.
  - **Files**: `app/Livewire/Hq/Mail/{ThreadTeam,ThreadSummary,AskMail}.php` and views; `app/Support/Mail/Team/{MailTeam,MailPresence,NewMailNotices}.php`; `app/Support/Mail/Ai/{Gemini,MailSearch,MailAsk,ThreadSummariser,MailText}.php`; `app/Listeners/Mail/{OnMailReceived,OnMailThreadsMerged}.php`; `config/mail_team.php`; the `mail` lines in `PushCategories` (label `'mail' => 'Mail'`, path rule `'mail' => 'mail'`, which covers `/mail` and `/mail/…`). No migration, no route (`routes/mail/team.php` stays empty), no `mail_settings` keys yet.
  - **Levels (my choice)**: *read* sees the bar, notes, activity and presence, adds notes and deletes **their own** notes; *send* also assigns and marks Open / Done; *manage* also deletes anyone's note. Only people with **send** level can be assigned (an assignee must be able to reply). Teamwork is for **shared mailboxes only**: `ThreadTeam` renders nothing on a personal mailbox, and `MailTeam` answers 404 there. Open / Done on personal mailboxes is left for the owner to decide.
  - **Assign**: bell notification *Conversation assigned to you* (link 6.1) to the assignee, unless they assigned themselves. Raises `mail-thread-changed {threadId}` after assign and status changes (not after notes).
  - **Activity types** written to `mail_thread_activity`: `assigned` / `unassigned` (`data.to`, `data.from`), `done`, `reopened` (`data.reason = 'reply'`, `data.message_id` when a client reply opened it; `user_id` null), `note`, `note_deleted` (`data.note_id`), `merged` (`data.from`). PR 3 may add `sent` (`MailTeam::log($threadId, $userId, 'sent')`); the timeline already words it as "X replied".
  - **Presence**: `mail_presence` rows last 60 s (`config('mail_team.presence_seconds')`); `ThreadTeam` writes `viewing` on mount, on `mail-thread-opened` and on a 20 s poll; `mail-composing` `replying` / `stopped` switches state (a poll never downgrades an unexpired `replying`). Expired rows are hidden at once and cleared by `mail:prune`.
  - **New-mail alerts** (`OnMailReceived`): one bell row per message to `MailAccess::readers()`, title `New mail: <from name or address>`, message the subject (else the first 12 words) plus ` · <mailbox>` on shared mailboxes, link `hq.mail.inbox?box&thread`. Skips outbound mail, `sent_by`, and the writer of an HQ outbox row with the same `message_id_hash`. Also reopens a Done conversation (`reopened`, reason `reply`). Errors are logged (`Mail: new-mail alerts failed`), never thrown. `OnMailThreadsMerged` clears the joined conversation's summary and logs `merged`.
  - **AI** (`App\Support\Mail\Ai`): Gemini only, model `config('mail_team.ai_model')` = `MAIL_AI_MODEL`, default `gemini-2.5-flash` (the flash model HQ already uses); key in the `x-goog-api-key` header; nothing of the mail is logged. Buttons are hidden when `GEMINI_API_KEY` is empty. Limits per person per hour: 30 summaries, 20 questions.
  - **`ai_summary` JSON**: `{gist: string, key_points: string[] (≤5), asked_of_you: string[] (≤5), by: user id}`. Built from the last 30 unique messages outside Spam and Drafts, ≤4,000 characters each, quoted text cut (`MailText::withoutQuotes`), never attachments; `ThreadSummariser::isSpam()` (every copy in Spam) hides the button. PR 2 can show the card's data elsewhere from `mail_threads.ai_summary`.
  - **`MailSearch::for(User, string $question, int $limit = 20, ?array $accountIds = null)`**: the 7.6 contract plus an optional 4th argument that narrows to some mailboxes (never widens past `MailAccess::accountIds($user, 'read')`). Skips Spam, Trash and Drafts; one row per message (copies in two folders count once). FULLTEXT `mail_messages_search` first, then `LIKE` over the same columns for words FULLTEXT can't see (under three letters, e.g. "Q4") or rows not indexed yet. A question with no useful words returns the newest mail.
  - **Ask** (`MailAsk::answer`): no AI call when nothing matches; up to 12 messages (excerpts of ~400 characters) go to Gemini, which cites them as `[n]`; the page shows only the cited ones as links (subject, sender, date) and keeps no excerpts in the Livewire state. Answers are escaped; `[n]` becomes a link and `- ` lines a list.
  - **For PR 2**: keep the three mount points of 6.2 as written (`ThreadTeam` only for shared mailboxes; it renders nothing otherwise, so mounting it always is also fine). Please dispatch `mail-thread-opened` when a conversation opens. `mail-thread-changed` is raised by PR 4 as above.
  - **For PR 3**: dispatch `mail-composing` with `state: 'replying'` when a reply to a thread opens and `'stopped'` when it is sent, discarded or closed.
  - **Request (owner)**: `PushCategories::for()` lists *Mail* in everyone's notification settings, also for people without Mail; hiding it for them would need one more line in that file (`$key !== 'mail' || MailAccess::canUse($user)`), outside PR 4's agreed change.
  - **One file outside PR 4's list (owner to confirm)**: `tests/Feature/Pwa/NotificationSettingsTest.php` line 227 lists every push group exactly, so the new *Mail* group made it fail; PR 4 added `'mail' => true` to that list and nothing else.
  - **Tests**: `tests/Feature/Mail/{TeamWorkTest,TeamNotificationsTest,TeamAiTest}.php`, `tests/Unit/Mail/TeamTextTest.php`: 21 tests (access per level, assignment bell, notes, Open / Done, presence and expiry, alerts to readers only and never to outsiders, leavers or the HQ writer, reopen on reply, listener never throws, merge, summary without quotes / attachments / Spam, cleared by new mail, search and Ask never reading mail the person can't, escaping). Gemini faked with `Http::fake`. FULLTEXT itself only runs on committed rows (tests run in a transaction, so they exercise the `LIKE` path); it was checked by hand on `praxis_mail_pr4`.
  - **Local check**: mount points added to an uncommitted copy of the placeholder Inbox; bar, notes, activity, replying warning, summary card and the Ask panel at desktop width and at 375 px (no sideways scrolling; Ask is full screen on phones). Not checked: a real Gemini answer (no key locally).
