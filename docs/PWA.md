# HQ as an installable app (PWA)

Agreed with the owner on 30 October 2026. **Only HQ** (`hq.jbspraxis.com`) becomes an app. The Client Portal and the public site are not touched: no manifest change, no service worker, no prompts.

## Owner decisions

| # | Question | Decision |
|---|---|---|
| 1 | Launch animation | **B. Converge**: the letters are on screen first; the ten bars arrive from both sides and meet at the split, then the Praxis rule draws and HQ opens (about 1.9 s). A brand book exception, recorded in BRAND.md v1.2 (sections 6, 11 and 14), limited to the HQ app launch. |
| 2 | What sends a push | **Everything that lands in the HQ bell, plus system alerts** (new errors and urgent failing checks from System Health, to staff who can open System health). Each person can mute groups. |
| 3 | Lock screen | **Title and message** (the phone's own settings can still hide previews). |
| 4 | Offline | **A branded offline screen** that reconnects by itself. No client or finance data is stored on devices: pages are never cached. |
| 5 | Install invitation | **After sign-in, a small card.** "Not now" hides it for 14 days; "Install the app" stays in the bell menu. |
| 6 | Notification permission | **Phones: on the first launch of the installed app** (on iPhone push only works there). **Computers: a card after sign-in.** The browser's own prompt only appears after "Turn on". The bell dropdown also has a "Turn on notifications" button. |

## What the owner sees

- **Install**: Chrome, Edge, Samsung Internet and Android: an "Install HQ" card after sign-in that opens the browser's install dialog. iPhone and iPad (Safari): the card explains Share → Add to Home Screen. Mac Safari: File → Add to Dock. Firefox (no install support): no card.
- **Launch**: the installed app opens on Ink with the letters, the bars converge, the rule draws, then HQ fades in. Only on a cold start of the installed app, never in a browser tab, never on page changes. Reduced motion: the full logo shows still, then fades.
- **Notifications**: every bell notification also arrives as a system notification on each device the person turned on, with the title and message. Tapping it opens HQ on the right screen and marks it read. The bell refreshes by itself when a push arrives; the app icon shows the unread count where the platform supports it.
- **Offline**: a branded screen ("You're offline") that reconnects and reloads when the connection is back. Open pages show a short "You're offline" notice instead of failing silently.

## Architecture

```
Browser (HQ host only)                         Laravel (praxis_core)
-----------------------                        ----------------------------------------------
<head> pwa.head  ── manifest link ───────────► GET /manifest.webmanifest   PwaController@manifest
                 ── startup images ──────────► GET /pwa/v/{ver}/startup/… PwaController@asset
                 ── hq-pwa.css / hq-pwa.js ──► GET /pwa/v/{ver}/…         PwaController@asset
hq-pwa.js ── register('/sw.js') ─────────────► GET /sw.js                  PwaController@serviceWorker
          ── subscribe / unsubscribe / test ─► POST /pwa/push/…            PushSubscriptionController
sw.js     ── offline fallback ───────────────► GET /offline (precached)    PwaController@offline
notification click ──────────────────────────► GET /pwa/open/{id}          NotificationOpenController
<x-pwa.launch /> (body) ◄── session flash ◄─── GET /?source=pwa            PwaLaunch middleware (web group)

DB insert into notifications (anywhere) ─► DB::listen flag ─► app()->terminating ─► PushDispatcher::dispatchPending()
schedule: pwa:push-pending every minute (safety net), pwa:push-prune daily
MonitorAlerts::newErrors()/health() ─► bell notifications for staff with system.view ─► pushed like any other
```

- **No Vite, no Composer, no npm changes.** The app's own files live in `praxis_core/resources/pwa/` and are served by Laravel on the HQ host only at `/pwa/v/{version}/{path}` (`App\Support\Pwa\Pwa::asset()`), so they never depend on the shared Vite build and never exist on the portal or the public site. `{version}` is a 10-character hash of the files in `resources/pwa` (paths, sizes, times); a new version means new addresses, so each one is cached for a year.
- **Web push is implemented in PHP** (`App\Support\Pwa\WebPush`: RFC 8291 aes128gcm payload encryption, RFC 8292 VAPID ES256 JWT) with OpenSSL, sent with Laravel's HTTP client. No new packages: the server has no Composer and no terminal.
- **VAPID keys** are made on first use and kept in `pwa_settings` (private key encrypted with APP_KEY). `.env` can override them (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`).
- **Push follows the bell.** About 35 places insert rows into `notifications` with the query builder. None of them changes: `PwaServiceProvider` listens for `insert into notifications` and runs the dispatcher after the response has been sent (or the command has finished). The scheduler runs `pwa:push-pending` every minute for anything missed. `push_deliveries` (one row per notification, primary key = notification id) makes sure each notification is pushed at most once, whichever path gets there first. The migration writes a `baseline` row for every existing notification, so the first run never pushes old ones.

## Files and owners

Skeleton (written first, shared): `config/pwa.php`, `app/Providers/PwaServiceProvider.php` (+1 line in `bootstrap/providers.php`), `routes/pwa.php`, `database/migrations/2026_10_30_300000_create_pwa_tables.php`, `app/Models/PushSubscription.php`, `app/Support/Pwa/{Pwa,PushCategories,PushPreferences,VapidKeys}.php`, `resources/views/pwa/head.blade.php`, the HQ branch of `resources/views/partials/app-head.blade.php`, `<x-pwa.launch />` after `<body>` in `components/layouts/hq.blade.php` and `components/layouts/auth.blade.php`, two schedule lines in `routes/console.php`.

| Part | Owns |
|---|---|
| **push** (server push) | `app/Support/Pwa/WebPush.php`, `app/Support/Pwa/PushDispatcher.php`, `app/Http/Controllers/Pwa/PushSubscriptionController.php`, `app/Http/Controllers/Pwa/NotificationOpenController.php`, `app/Console/Commands/PwaPushPending.php`, `app/Console/Commands/PwaPushPrune.php`, `app/Console/Commands/PwaPushTest.php`, the alert hook in `app/Support/Monitor/MonitorAlerts.php`, a push check in `app/Support/Monitor/HealthChecks.php`, `tests/Unit/Pwa/WebPushTest.php`, `tests/Feature/Pwa/Push*Test.php`, `tests/Feature/Pwa/NotificationOpenTest.php` |
| **shell** (service worker, manifest, offline) | `app/Http/Controllers/Pwa/PwaController.php`, `resources/pwa/sw.js`, `resources/views/pwa/offline.blade.php`, `resources/views/pwa/head/manifest.blade.php`, `resources/pwa/img/*` (notification badge, shortcut icons, manifest screenshots), `tests/Feature/Pwa/PwaRoutesTest.php` |
| **splash** (launch animation) | `resources/views/components/pwa/launch.blade.php`, `app/Http/Middleware/PwaLaunch.php`, `resources/views/pwa/head/startup-images.blade.php`, `resources/pwa/startup/*.png`, `scripts/pwa/*` (generators), the v1.2 entries in `docs/BRAND.md`, `tests/Feature/Pwa/LaunchSplashTest.php` |
| **client** (install and notification UX) | `resources/pwa/hq-pwa.js`, `resources/pwa/hq-pwa.css`, `resources/views/pwa/head/client.blade.php`, `app/Livewire/Hq/Components/NotificationBell.php` and its view, `app/Livewire/Hq/Pwa/NotificationSettings.php` and `resources/views/livewire/hq/pwa/notification-settings.blade.php`, `tests/Feature/Pwa/NotificationSettingsTest.php` |

Never edit files outside these lists. A parallel piece of work (the marketing site API, website settings) is editing other files in the same checkout: `routes/web.php`, `bootstrap/app.php`, `.env.example`, `config/access.php`, the rail in `components/layouts/hq.blade.php`, `app/Livewire/{AiAgent,Careers,ContactHub,ConversionHub,Kaizen,ScheduleConsultation,Services,TelemetryNode}.php`, `app/Services/Site/*`, `app/Http/Controllers/SiteApi/*`, `praxis_site/`.

## Contracts

### Routes (HQ host only, `routes/pwa.php`)

| Method, path | Name | Middleware | Answer |
|---|---|---|---|
| GET `/manifest.webmanifest` | `pwa.manifest` | none (no session, no cookies) | `application/manifest+json`, `Cache-Control: no-cache` |
| GET `/sw.js` | `pwa.sw` | none | `application/javascript`, `Cache-Control: no-cache, no-store, must-revalidate`, `Service-Worker-Allowed: /`; `resources/pwa/sw.js` with `__PWA_VERSION__` and `__PWA_PRECACHE__` replaced |
| GET `/offline` | `pwa.offline` | none | the branded offline page, fully self-contained (inline CSS and SVG, no fonts or scripts from the network) |
| GET `/pwa/ping` | `pwa.ping` | none | 204, `Cache-Control: no-store` (the offline page polls it) |
| GET `/pwa/v/{version}/{path}` | `pwa.asset` | none | a file from `resources/pwa` (js, css, svg, png, json, webp, ico); `public, max-age=31536000, immutable` when `{version}` is current, `no-cache` otherwise; 404 for anything else |
| GET `/pwa/go/{section}` | `pwa.go` | web, auth, hq.staff | manifest shortcuts: `workspace`, `leads`, `projects`, `finance` → redirect to the first screen in that section the person can open (as the rail does), else My workspace |
| GET `/pwa/open/{notification}` | `pwa.open` | web, auth, hq.staff | if the notification is the person's: mark it read; redirect to its link (HQ paths only) or My workspace |
| POST `/pwa/push/subscription` | `pwa.push.store` | web, auth, hq.staff, throttle:30,1 | register or refresh this device for the signed-in person |
| POST `/pwa/push/subscription/delete` | `pwa.push.destroy` | web, auth, hq.staff, throttle:30,1 | forget this device (also used with `navigator.sendBeacon` at sign-out, `_token` in the form body) |
| POST `/pwa/push/test` | `pwa.push.test` | web, auth, hq.staff, throttle:6,1 | send a test notification to this device (or all the person's devices) |

`routes/web.php` ends the HQ host group with a catch-all 404 (`Route::any('{any}', …)`). The PWA routes are registered by `PwaServiceProvider::boot()`, before `routes/web.php` is loaded, so they match first. Keep it that way (check with a request, not with `route:list`, which sorts).

### Subscription API

`POST /pwa/push/subscription` with `X-CSRF-TOKEN` and JSON:

```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/…",
  "keys": { "p256dh": "<base64url, 65 bytes, starts 0x04>", "auth": "<base64url, 16 bytes>" },
  "contentEncoding": "aes128gcm",
  "standalone": true,
  "device": "iPhone · HQ app"
}
```

- `endpoint` must be `https://` on a push service in `config('pwa.push.services')` (no other hosts: the server makes requests to it). At most 2,000 characters.
- Upsert by `endpoint_hash = sha256(endpoint)`. The device moves to the signed-in person (a shared computer follows whoever is signed in). `failures` resets, `last_seen_at = now`.
- At most `config('pwa.push.max_devices')` per person; beyond that the least recently seen is removed.
- Answer: `200 {"ok": true, "id": 12}`; validation errors are `422` JSON.

`POST /pwa/push/subscription/delete`: `endpoint` (JSON or form). Deletes it if it belongs to the signed-in person. `204`.

`POST /pwa/push/test`: optional `endpoint`. Answer `200 {"ok": bool, "sent": n, "failed": n, "devices": [{"device": "…", "status": 201}]}`.

### Push payload (server → service worker)

JSON, encrypted with aes128gcm, under 3,000 bytes:

```json
{
  "v": 1,
  "id": "9b0c…-uuid or test-…",
  "title": "Cash request to approve",
  "body": "Cash request CR-1042 needs approval. Review it in Cash request approvals.",
  "url": "/pwa/open/9b0c…",
  "category": "finance",
  "tag": "9b0c…",
  "badge": 3,
  "ts": 1793000000000
}
```

`body` is the notification message, trimmed to `config('pwa.push.body_limit')` characters with an ellipsis. `badge` is the person's unread count when sent (null if unknown). Headers: `TTL: config('pwa.push.ttl')`, `Urgency: high` for `system` and for titles that ask for an approval, else `normal`, `Content-Encoding: aes128gcm`, `Authorization: vapid t=<jwt>, k=<public key>`. JWT: `aud` = endpoint origin, `exp` = now + 12 h, `sub` = `config('pwa.vapid.subject')`.

Push service answers: 201/202 sent; 404 or 410 → delete the subscription; 400/403 (bad or changed keys) → delete; 413 → log; 429 or 5xx → `failures + 1` (removed after 5 in a row).

### Groups a person can mute (`App\Support\Pwa\PushCategories`)

`leads` Leads and proposals, `projects` Projects, `finance` Finance, `team` Team, `marketing` Marketing, `system` System health (only listed for people who can open System health), `other` Other updates. A notification's group comes from its link's path (`/intelligence/` leads, `/operations/` projects, `/finance/` finance, `/hr/` and `/mdm/employees` team, `/marketing/` marketing, `/system/` system), else from its type (`Financial` finance, `Project` and `QA_Update` projects), else `other`. Preferences: `push_preferences.muted` (JSON list), via `App\Support\Pwa\PushPreferences`.

### Server API the other parts call

```php
App\Support\Pwa\Pwa::version(): string                 // 10 hex chars
App\Support\Pwa\Pwa::asset(string $path): string       // '/pwa/v/{version}/{path}'
App\Support\Pwa\Pwa::file(string $path): ?string       // absolute path in resources/pwa, or null
App\Support\Pwa\Pwa::type(string $path): string        // Content-Type for that file
App\Support\Pwa\VapidKeys::publicKey(): string         // base64url, 65-byte uncompressed P-256 point
App\Support\Pwa\VapidKeys::privateKeyPem(): string
App\Support\Pwa\VapidKeys::subject(): string
App\Support\Pwa\PushCategories::LABELS / forNotification(?string $link, ?string $type): string / for(User $user): array
App\Support\Pwa\PushPreferences::muted(string $userId): array / isMuted(...) / set(string $userId, string $category, bool $on): array
App\Support\Pwa\PushDispatcher::dispatchPending(?int $limit = null): array   // ['checked' => n, 'sent' => n, …]
App\Support\Pwa\PushDispatcher::dispatchPendingQuietly(): void               // never throws (used after responses)
App\Support\Pwa\PushDispatcher::sendTest(App\Models\User $user, ?string $endpoint = null): array  // same shape as POST /pwa/push/test
App\Models\PushSubscription  (user_id, endpoint, endpoint_hash, public_key, auth_token, content_encoding, device, standalone, user_agent, last_seen_at, last_sent_at, failures)
```

### Page configuration (`pwa/head/client.blade.php`)

```html
<script id="hq-pwa-config" type="application/json">{
  "version": "3f2a9c01de", "sw": "/sw.js",
  "page": "app",            // "app" in the signed-in HQ layout, "auth" on sign-in and setup pages
  "user": "a1b2c3d4e5f60718", // first 16 hex of sha256(user id), or null when signed out
  "csrf": "…", "vapidKey": "B…",
  "routes": { "subscribe": "/pwa/push/subscription", "unsubscribe": "/pwa/push/subscription/delete", "test": "/pwa/push/test" },
  "snoozeDays": { "install": 14, "notify": 14 }
}</script>
```

### Events

| Event | From → to | Meaning |
|---|---|---|
| `html.hq-launching` class, then `hq:launch-done` on `document` | launch splash → hq-pwa.js | the splash is on screen; cards wait until it is gone |
| `{type: 'hq:push', id, category, badge}` | service worker → page (`postMessage`) | a push arrived |
| Livewire `hq-push-received` | hq-pwa.js → NotificationBell | reload the bell |
| browser `hq-unread` with `detail.count` | NotificationBell (`$this->dispatch('hq-unread', count: n)`) → hq-pwa.js | set the app icon badge |
| `{type: 'hq:navigate', url}` | service worker → page | a notification was clicked: `Livewire.navigate(url)` (or `location.assign`) |
| browser `open-notification-settings` | bell menu → NotificationSettings | open the settings panel |
| browser `hq-pwa-state` with `detail` = `{installable, standalone, platform, permission, subscribed, pushSupported}` | hq-pwa.js → anything that shows app state | state changed |

Elements that hq-pwa.js renders into Livewire views carry `wire:ignore` so morphing never undoes them.

### Service worker (`resources/pwa/sw.js`)

- Caches: `hq-shell-<version>` (precache: `/offline`, `hq-pwa.js`, `hq-pwa.css`, the notification badge; old versions deleted on activate) and `hq-static-v1` (runtime, at most 80 entries: `/build/assets/*`, `/assets/*`, `/icons/*`, `/livewire-*/livewire*.js`).
- Install: precache, `skipWaiting()`. Activate: delete old caches, `clients.claim()`, enable navigation preload.
- Navigations: network only (navigation preload when available); on network failure, the cached `/offline`. Pages are never stored.
- Never handled (left to the browser): non-GET, other origins, `/livewire*` (except the Livewire script), `/pwa/push/*`, `/pwa/open/*`, `/pwa/ping`, `/sw.js`, `/files/*`, `/taskforce*`, `/logout`, `/up`, `/setup/*`, `/login`.
- `push`: show the notification (`icon` `/icons/icon-192.png`, `badge` the monochrome badge, `tag`, `data: {url, id}`, `timestamp`), set the app badge, post `hq:push` to open windows. A push without data still shows "New notification in HQ".
- `notificationclick`: close it; focus an open HQ window and post `hq:navigate`, else `clients.openWindow(url)`.
- `pushsubscriptionchange`: subscribe again with the same key; the page re-registers it on its next start.

## Local testing

- Database `praxis_pwa` on the local MySQL (port 3307), never `praxis_local` (the parallel work uses it). Every command: `APP_ENV=localmysql DB_DATABASE=praxis_pwa` (process environment overrides `.env.localmysql`).
- `php artisan test` uses SQLite in memory (phpunit.xml): safe to run at any time.
- A server: `APP_ENV=localmysql DB_DATABASE=praxis_pwa APP_URL=http://hq.localhost:<port> php artisan serve --host=127.0.0.1 --port=<port>`, then HQ is `http://hq.localhost:<port>` (Chrome treats `*.localhost` as secure, so service workers and push work).
- Test accounts: `_localtest/pwa_test_users.json` (an owner and an engineer, local only).

## Client Portal (added 30 October 2026)

The owner then asked for the leanest extension to the Client Portal (`portal.jbspraxis.com`): the same app, not a second one. Decisions (four questions): **push for every client event plus an inbox (a bell in the portal)**; **the Converge launch with the logo alone**; **each client's language** (English or French); **the install card after sign-in, "Not now" for 3 days**. Built in the foreground, no background agents.

- **One app shell, two hosts.** `routes/pwa.php` registers the same stateless routes on the portal host (`portal.pwa.*` names). `PwaController` answers in the portal's words: its manifest follows `?lang=` (the head links it in the page's language), its offline page carries English and French and picks the language this device last used (`localStorage hq-pwa:lang`, written by hq-pwa.js), and `sw.js` gets the app's name through `__PWA_APP__`. HQ's answers are unchanged.
- **One script.** hq-pwa.js reads `app`, `lang` and `strings` from the page settings; every phrase goes through `t()`. HQ sends no strings (the script's own English); the portal sends its English (which says "Client Portal") or its French, from `lang/*/portal.php` (`pwa.js`). The portal's sign-out button (`wire:click="executeLogout"`) forgets the device first, like HQ's sign-out link. "This device" offers "Turn off" on the portal (no settings panel there).
- **One device table.** `push_subscriptions` rows belong to a staff member (`user_id`) or a client (`client_user_id`, with `locale`): migration `2026_10_30_400000_extend_pwa_to_the_client_portal.php`. Sending and device bookkeeping are HQ's (`PushDispatcher` helpers, now public).
- **The inbox.** `client_notifications` (kind + params, rendered in the reader's language by `ClientNotify::text()`), shown by `App\Livewire\ClientOs\NotificationBell` in the dashboard's top bar; `/inbox/open/{id}` marks it read and opens its tab (`/?tab=financials`, the dashboard now honours `?tab=`). Each row is pushed once (`pushed_at` is the claim) by `ClientPush`, after the HQ request that wrote it and by `pwa:push-pending` every minute.
- **Who hears what** (`ClientNotify::KINDS`): invoices issued, overdue or cancelled and payments confirmed or rejected to the client's billing people (`portal.billing.view`); proposals and handovers ready to sign to approvers (`portal.projects.approve`); tasks waiting for review to the project's accounts (`portal.projects.view`); issues being looked at, replied to or resolved to the client who reported them. Project-limited accounts only hear about their project; leads (no password) and suspended accounts never.
- **Where HQ calls it** (one line each): `InvoiceLedger` (issue, cancel, overdue), `EscrowVerification` (verify, reject), `QaHub` (investigating, reply that isn't internal, resolved), `SprintBoard` (moved to QA_Review), `SignoffManager` (handover drafted), `ProposalBuilder` (signed by JBS Praxis).
- **Launch.** `PwaLaunch` also works on the portal; it runs before the sign-in check (kernel priority) so a guest sent to the sign-in page still sees the launch. The component hides "HQ" on the portal (`<style id="hq-launch-portal">`); startup images `portal-*.png` (`python scripts/pwa/startup_images.py portal`). Brand book version 1.3.
- **Checked:** 107 PWA tests (`ClientPortalTest` among them); in real Chrome on the portal in French: installable with no errors, the page registers the device in French, a test push and an HQ event arrive through FCM in French ("Notification de test", "Paiement confirmé"), the bell reloads by itself.

## HQ deployed: 30 October 2026, about 10:58 CET (owner approved, owner confirmed it works on their devices)

- 91 files uploaded through the cPanel file API from the browser (the local bridge and relay used for PR2a; tooling in `_localtest/pwa_deploy_build.py` and `_localtest/pwa_bridge.js`), each checked by size. The 9 shared files matched git HEAD on the server before being replaced; the HQ layout shipped as HEAD plus the `<x-pwa.launch />` line only.
- Backups of the 9 replaced files: `~/pwa_backup_20261030/`. The migration ran alone (`migrate --path=…create_pwa_tables.php`) from a one-off cron job, removed right after; the log is `~/pwa_migrate.log`. No config or route cache on the server, so nothing needed clearing.
- Checked live: the app files answer on `hq.` only (404 on `portal.` and `jbspraxis.com`), the VAPID key pair was made on first use, and the portal head is unchanged.
- Rollback: copy the backups back over the 9 files (the new files are then unused); the tables can stay, or `migrate:rollback` that one migration.

## Client Portal deployed: 30 October 2026, about 12:45 CET (owner approved, owner confirmed it works on their devices)

- 80 files uploaded the same way (`_localtest/pwa_deploy_build_portal.py`), each checked by size, in five batches so the live site kept working: the migration alone; the new classes, wording, startup images, routes and provider; the HQ screens with their one-line hooks; the pages and the portal layout last. The 30 replaced files matched git HEAD on the server first.
- Backups of the 30 replaced files: `~/pwa_backup_20261030_portal/praxis_core/…` (same paths as the app; the loose copies at the top of that folder are an earlier attempt and can be ignored). The migration ran alone from a one-off cron job, removed right after.
- Checked live (17 of 17): the portal's manifest in English and French, `sw.js` with the portal's name, the offline page in both languages, the 44 `portal-` startup images and the logo-only launch for a guest sent to sign-in; HQ unchanged (its own images, the launch with "HQ", the English offline page); `jbspraxis.com` still has no app (404 for `/sw.js` and `/manifest.webmanifest`); no errors in the server log.
- Rollback: copy the backups back over the 30 files (the new files are then unused); the migration can stay, or `migrate:rollback` it, which also deletes the client devices and the inbox.

## Deploy (owner present, cPanel)

Backups first (files and database), then upload `praxis_core` files only (nothing in `public_html/PRAXIS` changes), run the migration (one-off cron, as in S6), check `/manifest.webmanifest`, `/sw.js` and `/offline` on `hq.` and `portal.` and that they are 404 on `jbspraxis.com`, then install on a phone and a computer and send a test notification. The deploy list is built from this checkout's PWA files only; shared files are rebuilt from `HEAD` plus the PWA change, so unfinished parallel work never ships.
