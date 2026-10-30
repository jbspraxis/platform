# Hosting check for demo.jbspraxis.com (runbook §4.3)

Checked 30 October 2026 in the jbspraxis.com cPanel account, read-only:
nothing was created or changed.

| Need | Found | Verdict |
|---|---|---|
| Setup Node.js App | Present (CloudLinux Node.js selector). Versions offered: 6.17.1 to 19.9.0, **20.20.2**, 22.23.4, 24.22.1. No apps exist yet | Meets MyCity's Node ≥ 20.19 < 21 |
| PostgreSQL Databases | **Not present.** The Databases section has MySQL only (phpMyAdmin, Manage My Databases, Database Wizard, Remote Database Access) | **Missing** |
| Room for one more database | MySQL databases 5 of 5 (100%) | Full |
| Memory and processes | 1 GB physical memory, 20 entry processes, 100 processes, 1 MB/s I/O | Tight for an API, a worker and headless Chromium |

**Decision (owner, 30 October 2026): keep the demo local.** No VPS for now; `demo.jbspraxis.com`
is not created. Live demos run from this PC (`node mock/server.js` in `C:\Users\Tom-Blake\ndali-demo`,
then http://localhost:4100).

**Result:** the server cannot host the demo copy as it stands, so the demo runs and is recorded
locally (a private PostgreSQL 18 cluster in `C:\Users\Tom-Blake\ndali-demo\.pgdata`, API and app
on `localhost:4100`). The hosting question for the owner (runbook §3.2: a small VPS the owner
buys, or keep the demo local) is asked once, at handover, and does not hold up the video.
