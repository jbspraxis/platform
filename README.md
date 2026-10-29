# JBS Praxis (jbspraxis.com): local copy

A byte-exact copy of the live site, taken from cPanel on 29 October 2026. The site never went through GitHub; this folder's own git history starts from this copy.

## What is where

| Here | On the server | What it is |
|---|---|---|
| `praxis_core/` | `/home/smartqaq/praxis_core` | The Laravel 12 app (PHP 8.2): `app/`, `config/`, `routes/`, `resources/`, `database/`, `vendor/`, `.env` |
| `public_html_PRAXIS/` | `/home/smartqaq/public_html/PRAXIS` | The web root jbspraxis.com serves. Its `index.php` loads `../../praxis_core` (on the server that is `~/praxis_core`) |
| `database/smartqaq_praxis_core_2026-10-29.sql` | MySQL database `smartqaq_praxis_core` | Full export (91 tables), from cPanel's database backup |
| `_downloads/` | `~/praxis_export_20261029/` | The original zips and `.sql.gz`, kept as downloaded |

Left out on purpose: `praxis_core/error_log` (76 MB of PHP error log).

## Security changes made on 29 October 2026

Both were reachable by anyone on the web; they were moved (not deleted) to `/home/smartqaq/praxis_private/`:
- `public_html/PRAXIS/smartqaq_praxis_core.sql`: an old full database dump.
- `public_html/PRAXIS/backup/`: the old plain-PHP site (Feb 2026), with its admin pages and `api/`.

The local copies of both are still in `public_html_PRAXIS/`.

## Run it locally

1. Create a local MySQL database and import `database/smartqaq_praxis_core_2026-10-29.sql`.
2. In `praxis_core/.env` (a copy of the live one, with live secrets), set `APP_ENV=local`, `APP_URL=http://localhost:8000` and the local `DB_*` values. Never commit `.env`.
3. From `praxis_core/`: `php artisan serve` (it serves `praxis_core/public/`). The live web root's `index.php` uses server paths and isn't needed locally.

## Git

`.gitignore` keeps out secrets (`.env`), installed packages (`vendor/`, `node_modules/`), logs and caches, and every database dump (they contain personal data). Push to GitHub only after checking nothing private is tracked.
