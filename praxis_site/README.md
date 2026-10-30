# praxis_site: the JBS Praxis marketing site

Laravel 12, Blade and Vite + Tailwind v4, with **no database**. Everything the site reads or writes goes
through the private, signed Site API on HQ (`docs/SITE_API.md`), through one client:
`App\Support\HqApi`. Preview at `next.jbspraxis.com` (noindex) until PR3 moves it to `jbspraxis.com`.

## Pages (PR2b: working flows on plain brand pages)

| Path | What it does | Site API |
|---|---|---|
| `/` | Preview landing page linking to every flow | `GET /settings` (footer, every page) |
| `/contact` | Enquiry, with optional questions about the organisation and “When would you like to start?” (no budget) | `POST /enquiries` |
| `/schedule` | Month view (up to 92 days ahead), day, time (WAT), then the booking form; HQ’s “already booked” message above the form | `GET /booking/slots`, `POST /bookings` |
| `/cost-calculator` | Cost of poor quality calculator, then `/dossier/{token}` | `GET /calculator/reference`, `POST /calculator/assessments` |
| `/dossier/{token}` | Cost of poor quality report | `GET /reports/{token}` |
| `/kaizen`, `/kaizen/{slug}`, `/kaizen/write` | JBS Praxis Insights and the submission form | `GET /kaizen/articles…`, `POST /kaizen/submissions` |
| `/services` | Praxis Method PDF by email | `POST /whitepaper` |
| `/careers` | Application with a CV (multipart) | `POST /careers/applications` |
| `/feedback` | Feedback on the site | `POST /feedback` |
| `POST /chat` | Praxis AI, the website assistant, on every page (last 20 turns and a question counter in the session; the reply's links as buttons) | `POST /chat` |
| `/tracking/open/{c}/{u}?t=…` | Email open pixel (2 s, failures ignored; the link's token `t` is passed on). Always returns the image: past 60 a minute per visitor only the call to HQ is skipped, because mail image proxies fetch for many readers from a few addresses | `GET /tracking/open/…` |
| `/tracking/unsubscribe/{u}?campaign=…&t=…` | Confirm page, then unsubscribe (campaign and token passed on) | `POST /tracking/unsubscribe/…` |
| `/robots.txt` | `Disallow: /` while `SITE_NOINDEX=true` | none |
| `/sitemap.xml`, `/llms.txt` | Every page in English and French, then the published Insights; a summary for AI crawlers | `GET /kaizen/articles` (sitemap) |
| `/{key}.txt` | IndexNow's key file: only the key in `config/site.php` (`indexnow.key`, or `INDEXNOW_KEY`) is found | none |

Every form: CSRF, a light limit per visitor, a honeypot (`website`), a signed page-load time (`form_ts`, at
least 3 seconds before submit), Cloudflare Turnstile when its keys are set, Post/Redirect/Get, a label on
every input, HQ's 422 messages next to the fields, entered values kept, a success state, and a calm message
(with the contact email from the last good settings) when HQ is down. A filled honeypot or a submit under
3 seconds gets the normal success answer and nothing is sent (the unsubscribe button asks for one more
click instead, because a person can click it that fast). Reads are cached (settings 5 min, Kaizen,
portfolio and calculator lists 10 min) and the last good copy is served for 24 h while HQ is down. Every
response carries a strict CSP (self only, no inline script or style; Cloudflare's Turnstile origin only when
Turnstile is on), `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors 'none'` and, in preview,
`X-Robots-Tag: noindex, nofollow`.

All text is in `lang/en/site.php` (French arrives in PR2c as `lang/fr/site.php`).

## Talking to HQ (`App\Support\HqApi`, docs/SITE_API.md section 6)

- **Signing (6.1):** 9 lines (method, path with query, timestamp, nonce, visitor IP, locale, key id, media
  type, body hash). Multipart (the CV) hashes the form: `t:<name>=<base64>` and `f:<name>=<sha256>` lines
  sorted by field name. Both contract vectors are in `tests/Unit/SigningTest.php`.
- **Timeouts (6.6):** reads 8 s with one retry, writes 20 s, Praxis AI 35 s (the chat route raises PHP's
  time limit to 60 s), the email pixel 2 s. Writes and chat are never retried, and a POST that times out
  never marks HQ as down. Redirects are never followed: a 3xx is treated as "HQ unavailable" and logged.
- **https:** with `APP_ENV=production`, a `SITE_API_BASE` that is not `https://` is refused: nothing is sent
  and an error is logged.
- On the host, PHP-FPM or LiteSpeed must not stop a request before 60 s (`request_terminate_timeout` or the
  LiteSpeed external app timeout), or a slow Praxis AI answer is cut off.

## Visitor IP (`TRUSTED_PROXIES`)

HQ's limits count each visitor by the IP the site sends (`X-Site-Visitor-Ip`), and the site's own limits
count an IPv4 address or an IPv6 /64 network. Getting the visitor IP right matters:

| Set-up | `TRUSTED_PROXIES` | What the site uses |
|---|---|---|
| No proxy in front of the site (default) | empty | the connecting address |
| Behind Cloudflare | `cloudflare` | `CF-Connecting-IP`, but only when the connecting address is in Cloudflare's published ranges (`config/site.php`); also firewall the origin to Cloudflare's ranges |
| Behind another proxy or load balancer | its addresses or ranges, comma-separated | `X-Forwarded-For` from those addresses only |

Never use `*`: anyone reaching the origin could then pick any visitor IP and get around every limit. The
site ignores `*` and logs a warning. `CF-IPCountry` (the open pixel's country) is believed only from
Cloudflare's ranges too. Compare `cloudflare_ranges` in `config/site.php` with
https://www.cloudflare.com/ips/ before each deploy.

## IndexNow

`php artisan site:indexnow` tells the IndexNow engines (Bing, Yandex, Seznam, Naver…) which addresses are new
or changed: every address in the sitemap, built on `https://jbspraxis.com` whatever `APP_URL` says, or only the
addresses or paths given (`php artisan site:indexnow /careers /fr/careers`). `--dry-run` lists them without
sending. Run it on the live server after a deploy that adds or changes pages. While `SITE_NOINDEX=true` it sends
nothing. Addresses on any other host are refused before anything is sent.

The key (32 hex characters, generated once) is public by design: it is in `config/site.php` and served at
`https://jbspraxis.com/{key}.txt`, where the engines check it. To change it, set `INDEXNOW_KEY` (8 to 128
letters, digits or dashes) and clear the config cache; the key file follows.

## PHP without fileinfo

The live web PHP has no `fileinfo` extension, and the careers CV's `mimes:pdf,doc,docx` rule needs it to read
a file's type. When it is missing, `AppServiceProvider` loads `app/Support/Polyfill/fileinfo.php`: a global
`finfo`, `finfo_*()` and `mime_content_type()` backed by `App\Support\Polyfill\Finfo` (the same stand-in as
HQ's). `tests/Feature/WithoutFileinfoTest.php` runs the CV check in a second PHP started without fileinfo.

## Cloudflare Turnstile

Off while `TURNSTILE_SITE_KEY` or `TURNSTILE_SECRET_KEY` is empty. With both set, every form shows the
widget, the CSP allows `https://challenges.cloudflare.com` for its script and frame, and the token is
checked with Cloudflare before anything reaches HQ (no answer from Cloudflare within 5 s counts as a
failed check). Praxis AI checks once, then trusts the session for 30 minutes. With Turnstile on, forms need
JavaScript.

## Local setup

```bash
composer install
cp .env.example .env            # then set the local values below; never commit .env
php artisan key:generate
npm install && npm run build    # npm run build also regenerates resources/js/icons.js
php artisan serve --port=8002
```

Local `.env`: `SITE_API_BASE=http://127.0.0.1:8001/api/site/v1`, `SITE_API_HOST=hq.localhost`, and
`SITE_API_KEY_ID` / `SITE_API_SECRET` matching HQ's `config/site_api.php` keys.

## Tests

```bash
php vendor/bin/phpunit          # Http::fake() for HQ and Cloudflare: no network, no database
```

`tests/Unit/SigningTest.php` reproduces both signing test vectors of `docs/SITE_API.md` section 6.1 (JSON
and multipart). Feature tests post forms with a valid page-load stamp (`Tests\TestCase::post()` adds one
unless the test sets `form_ts` itself).
