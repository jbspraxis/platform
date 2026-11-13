# Client conversations: the Client Portal ↔ HQ chat (13 November 2026)

Each client project has **one conversation** between the client's people and the JBS Praxis delivery team, built like a messaging app but in the brand: bubbles, day separators, Seen ticks, "is typing", replies that quote a message, photos, files, voice notes and emoji. It replaced the ticket form ("Report an issue or send a message") and the issue drawer of the portal's *Quality and issues*.

## Owner decisions (13 Nov 2026)

| # | Question | Decision |
|---|---|---|
| 1 | How conversations are organised | **One chat per project.** Reporting an issue posts an *issue card* in the chat (status Open → In progress → Resolved → Closed, priority, task) that anyone can answer by quoting it; the client approves the fix from the card. Existing tickets became cards with their replies. |
| 2 | Where HQ replies, who hears | **A new *Client messages* page in HQ** (Projects › Client messages), unread first, **plus a floating button at the bottom right of every HQ page** with the unread count that opens the same conversations in a panel. A client's message rings the **project lead and the people on the project's tasks** (bell + push); anyone else with QA rights can still read and reply. |
| 3 | How live | **Every 3 seconds**, with **Seen ticks** and **"is typing"**. |
| 4 | Extras | **Photos and files, an emoji picker, voice notes, and an email when a message is not read within an hour.** |

The owner's answer to 2 was cut off after "a floating button at the bottom right…"; it was read as an HQ-wide button for client messages (built). Say so if something else was meant.

## What each side sees

**Client Portal** (`portal.jbspraxis.com`)
- *Message your delivery team*: the floating button on every section, with the unread count. On a computer the chat opens as a panel at the bottom right (the page stays usable); on a phone (900 px and below) it covers the whole screen inside the safe areas and follows the keyboard (`visualViewport`), with a Back arrow.
- *Quality and issues*: the conversation's card (last message, unread, *Open the conversation*, *Report an issue*) and the list of issues, each opening its card in the chat.
- *Project progress › Report an issue* opens the chat with the issue form on that task.
- A notification about the chat opens `/?chat=<project id>`: that project, with the chat open.
- Who writes: portal `qa.create` (Client Admin, Approver, Member). Client Finance reads only. An account limited to one project sees that project's chat only. *Approve the fix* and *Close issue and approve task* need `projects.approve`.
- The chat stays open during a payment hold (the hold closes the project's work, not the conversation).

**HQ** (`hq.jbspraxis.com`)
- *Projects › Client messages* (`/operations/client-messages`, `qa.view`): every conversation the person may open, unread first, then the latest; projects still running without messages come last, to start one. On a phone, the list, then the conversation over it.
- The floating *Client messages* button on every page (kept across pages with `@persist`, hidden on the page itself, above the bottom bar on phones).
- In a conversation: *Internal note* (the lock) writes a note only JBS Praxis sees (amber, dashed); a line says who at the client reads the chat, or warns that nobody has a portal account yet; each issue card links to *Quality and issues*.
- *Quality and issues*: the issue drawer's conversation and evidence are the chat's messages and files about that issue; replying or adding a file there posts in the chat. Opening an issue posts "… started work on …", resolving it "… marked … as resolved".
- Who reads: `qa.view` on a project the person can see (QA approvers and managers: every project; others: the projects they lead or have a task on, as the QA hub). Who writes: `qa.edit`.

## How it works

- **Tables** (`2026_11_13_400000_create_project_chat_tables`):
  - `chat_messages`: `id` auto-increment (polling asks "after id N", read receipts keep "read up to N"), `project_id`, `author_type` (client | staff) + `author_id`, `kind` (text | issue | event), `body`, `event`, `qa_ticket_id`, `reply_to_id`, `is_internal`, `imported`, `legacy_id`, `created_at`. Shown in (created_at, id) order.
  - `chat_attachments`: `kind` (image | file | voice), name, path on the private disk (`chat/<project id>/…`), type, size, `duration_ms` for voice notes.
  - `chat_readers`: per person and chat: `last_read_id`, `seen_at` (presence), `typing_at`, `emailed_at`, `emailed_up_to`.
- **Existing tickets** became cards (`imported` = 1) with their `qa_comments` as replies and their `qa_attachments` on the card. Imported rows never count as unread and are never emailed. `qa_comments` and `qa_attachments` are kept but no longer read or written; `qa_tickets` stays the record of an issue's status. A ticket made outside the chat (handover points) gets its card when the portal lists it (`ProjectChat::cardFor`).
- **Code**: `App\Support\Chat\ProjectChat` (reading, posting, notifying, who may see), `App\Livewire\Concerns\ProjectChatRoom` (what both sides do), `App\Livewire\ClientOs\DeliveryChat`, `App\Livewire\Hq\ClientMessages\{Inbox, Thread, Launcher}`, the shared views `partials/chat/{conversation,composer}.blade.php`, `resources/js/chat.js`, `resources/css/chat.css`.
- **Live without websockets**: the browser calls `heartbeat(open, reading)` every 3 s while the chat is on screen and in front (30 s otherwise, for the badge). The server answers with a browser event `chat-state` (Seen up to, who is typing, unread) and draws the conversation again **only when it changed** (`ProjectChat::signature`: the latest message and the project's tickets); otherwise `skipRender()` sends no HTML. "Reading" means scrolled to the newest message with the page in front.
- **Seen** is per side: a message is Seen when anyone on the other side has read up to it. Typing lasts 6 s after the last keystroke (sent at most every 3 s). Someone looking at the chat (checked in within 15 s) gets no bell for what arrives.
- **Bells and pushes**: a client's message → HQ bell (pushed like every bell, docs/PWA.md) for the project lead and the people on its tasks with `qa.view` (nobody there: everyone with `qa.edit`), linking to the conversation; one unread bell per person per chat, updated for 10 minutes instead of ringing again (a new issue always rings). A JBS Praxis reply → portal notification (`chat_message`, or `chat_file` for a file alone, in the reader's language) for the project's accounts with portal `qa.view`, same 10-minute rule. Reading the chat marks its bells read.
- **Email after an hour** (`php artisan chat:email-unread`, every 5 minutes): messages from the last day that nobody on the other side has read for an hour go by email (`App\Mail\ChatUnreadMail`, EN/FR for clients, English for staff) to that side's people (clients: the chat's accounts; staff: the bell's people), at most one email an hour per person and chat, never for internal notes, issue status lines or imported messages. `--dry-run` lists who would be emailed.
- **Files**: photos (jpg, png, gif, webp), PDF, Word, Excel, PowerPoint, CSV, text, ZIP; 10 MB each, 5 at a time; picked, pasted or dropped. Voice notes are recorded in the browser (MediaRecorder: webm/opus in Chrome and Firefox, mp4/aac in Safari), up to 5 minutes. Served by `ChatFileController` (`/chat/files/{id}` on both hosts): staff who may open the chat; client accounts of the organisation that read the chat (not limited to another project); never a file of an internal note. Pictures, PDFs and sound open in the browser, the rest downloads; `nosniff` always.
- **Words** are escaped; links open in a new tab (`rel="noopener noreferrer nofollow"`); line breaks kept.
- **Accessibility**: the conversation is a `role="log"` live region; every icon button has a name; Seen/Sent is spoken; the composer has a label (visually hidden) and Enter / Shift + Enter (phones: Enter makes a new line, the button sends); the panel is a non-modal dialog on computers and a modal one with a focus trap on phones; Escape closes; focus returns to the button; reduced motion is honoured.
- **Wipe**: `qa:wipe` deletes a test client's chat rows and its `chat/<project>` files.

## Tests and local preview

- `tests/Feature/DeliveryChatTest.php` (7 tests: both sides, bells, collapse, internal notes, Seen/typing/unread, issues to the approved fix, files and voice notes and who may open them, the hourly email, HQ visibility), `tests/Unit/ChatTextTest.php`; QA hub, portal, handover and wipe tests updated.
- MySQL tests: Laragon 3307, a `praxis_test…` database (`tests/Feature/UsesThrowawayDatabase.php`).
- Preview: launch config `delivery-chat` (port 8137, database `praxis_chat` with fictional demo data), `http://portal.localhost:8137` and `http://hq.localhost:8137`.

## Rollback

Revert the merge. The migration's `down()` drops the three tables; tickets, their old comments and files were never changed, so the old *Quality and issues* works again as it was. Messages written in the chat meanwhile are lost with the tables (back them up first if needed).
