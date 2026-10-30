# Launch set: switching jbspraxis.com to the new site

Decided 30 October 2026: the owner wants the new site live on **jbspraxis.com** without a preview address
(next.jbspraxis.com was removed). PR2b's HQ part (Website settings, private API, strict limits, reworded alerts)
goes live first, on the current site. Then this launch set is built in `praxis_site` and the domain switches.
It merges the essentials of PR2c and PR3; PR4 (case studies, industries, full proof pages) follows after launch.

Binding references: `docs/BRAND.md` (sections 4, 5, 10, 11, 14), `docs/glossary.csv`, `docs/SITE_API.md`
(including section 6, Revision 2), `audit/AUDIT.md` section 7 (owner decisions). Claims rules: never "predictive",
no prices, no certificates ("processes aligned with ISO 27001"), no named people, client names only as approved.

**Google Analytics (created 30 Oct 2026 under praxisjbs@gmail.com):** account "JBS Praxis", property "jbspraxis.com"
(Nigeria time, NGN), web stream https://jbspraxis.com, Measurement ID **G-91FM19NGCK** → `GA4_ID=G-91FM19NGCK` in
praxis_site's production `.env`. It loads only after the visitor accepts the consent banner.

**Trimmed for speed (owner, 30 Oct 2026, "finish ASAP"):** launch pages are Home, Services (with "How we work"),
Company (with "Security and data" at `#security`) and Privacy, in EN and FR, plus the existing flows. The final text
is in `docs/LAUNCH_COPY.md` (builders lay it out, they don't rewrite it). `/how-we-work` → 301 to
`/services#how-we-work`; `/security` and `/trust-center` → 301 to `/company#security`. GA4 and the consent banner wait
for the owner's measurement ID (added later in minutes). The table below is the original plan; where it differs, this
note wins.

## 1. Pages at launch

| Path | Page | Content |
|---|---|---|
| `/` | Home | Hero "Ask your company anything." + support line + "Book a free audit and demo"; the hero moment (BRAND §11) on a framed screen with demo data (coded, no video yet; a "Watch the demo" slot hidden until the video exists); what we build (4 capability blocks); how we work (discovery → build → support, you own the code and data); proof (approved names, section 3); Praxis AI in one line; final call to action with phone/WhatsApp from Website settings. |
| `/services` | Services | Capabilities in plain words; the Praxis Method in plain words; the method PDF form (existing flow). |
| `/how-we-work` | How we work | Engagement steps, ownership of code and data, support, no per-user dollar licences, no prices. |
| `/company` | Company | Facts from Website settings (legal name, registrations "in progress" until set, offices, founded); leadership block shown only when switched on; "Our products: nfelia". |
| `/security` | Security and data | How data is protected, roles and audit logs, hosting, "processes aligned with ISO 27001". |
| `/privacy` | Privacy policy | Port of the current policy text, cleaned to the brand voice, contacts from settings, NDPA wording kept; a full legal rewrite stays in PR4. |
| `/schedule`, `/contact` | Book a free audit and demo; contact | Existing flows (PR2b). |
| `/cost-calculator`, `/dossier/{token}` | Calculator and report | Existing flows. |
| `/kaizen`, `/kaizen/{slug}`, `/kaizen/write` | Insights | Existing flows, titled "Insights". |
| `/careers`, `/feedback` | Careers, feedback | Existing flows (feedback not in the navigation). |
| 404, 500, 503 | Error pages | On brand, with the booking call to action. |

Header: horizontal logo; navigation Services · How we work · Company · Insights; "Book a free audit and demo"; a
"Client login" link to `portal.jbspraxis.com`. Footer: Ink band with offices, registrations, contact, socials
(from settings), links to Security, Privacy, Careers, Client login. Praxis AI on every page.

**French at launch (owner, 30 Oct 2026):** every page above also exists under `/fr/` (e.g. `/fr/services`,
`/fr/planifier` is not used: French pages keep the English slugs after `/fr/` so links stay predictable), with
the EN / FR switch in the header, `hreflang` pairs, French titles and descriptions, and all text in
`lang/fr/site.php` following BRAND.md's French rules (« … », narrow no-break spaces, "Réserver un audit gratuit et
une démo" as the CTA; check the glossary's French column). Forms on `/fr/` send `X-Site-Locale: fr`, so HQ's
messages and visitor emails come back in French. Praxis AI answers in the page language and links to the `/fr/`
pages. The cost report and Insights articles keep their stored language (articles are English for now).

## 2. Must not break (the domain switch)

- **301s** from old paths: `/about` → `/company`, `/trust-center` → `/security`, `/portfolio` → `/#work`,
  `/framework` → `/services#method`, the old calculator at `/` keeps a link from the home page; the legacy HQ and
  portal paths (`/hq/*`, `/client/*`, `/portal/*`, `/signoff/*`, `/verify/*`, `/taskforce/*`) redirect to
  `hq.` / `portal.` exactly as `praxis_core` does today (`App\Support\Hosts::LEGACY_PATTERN`); `www.` → apex.
- **Files that emails and other sites link to on jbspraxis.com**: `/assets/img/email/jbs-praxis-email-logo@2x.png`
  (every email), favicons and OG images, the method PDF if linked, and any `/storage/...` media used by Kaizen
  articles (serve via HQ URLs from the API, or copy). Inventory every absolute jbspraxis.com URL in praxis_core
  (mail views, config, seeds, Kaizen bodies) and make each one resolve on the new site.
- `/tracking/open/...`, `/tracking/unsubscribe/...`, `/dossier/{token}` keep working (PR2b).
- `/sitemap.xml`, `/robots.txt` (allow everything except forms' success states), `/llms.txt`.

## 3. Proof (approved names only)

Named: Smart Logistics and Services (Praxis LS), MyCity App, Pixie Girl Global, Orika Living, nfelia (our product).
Described, never named: "a leading pharmaceutical manufacturer, with Tales Consulting"; "an FMCG distributor,
Cameroon". Horizon Segment: not shown until the owner decides. No numbers that are not documented.

## 4. SEO, analytics, accessibility

- Per-page title and description (EN), canonical URLs, Open Graph images from `brand/kit`, JSON-LD @graph
  (Organization from settings with sameAs = the four social profiles, WebSite, BreadcrumbList, Article for Insights).
- `sitemap.xml` (pages + published Insights) and an IndexNow ping after deploy; Search Console and Bing already
  verified for the domain.
- GA4 with a consent banner (accept / reject; nothing loads before accept). Measurement ID from `GA4_ID` in `.env`;
  until the owner creates the property, the banner and analytics stay off.
- Accessibility base: skip link, visible focus, labels, one h1 per page, heading order, reduced motion, contrast AA.
- `SITE_NOINDEX=false` at the switch.

## 5. Go-live steps

1. Upload `praxis_site` to `~/praxis_site` (vendor from `composer install --no-dev`, `public/build` from `npm run build`).
2. `.env` per SITE_API.md (production key pair shared with HQ, https base, `TRUSTED_PROXIES` empty unless Cloudflare).
3. Smoke-test on a temporary host header before switching (curl with `Host: jbspraxis.com` to the server IP).
4. Change the addon domain `jbspraxis.com` document root from `public_html/PRAXIS` to `praxis_site/public`
   (hq. and portal. stay on `public_html/PRAXIS`), clear the nginx cache, check SSL.
5. Check every path in section 2, every form, the email logo URL, and Search Console's indexed pages (they must answer
   200 or 301, never 404). Rollback = point the document root back to `public_html/PRAXIS`.
