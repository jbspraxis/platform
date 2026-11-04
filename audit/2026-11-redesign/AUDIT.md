# jbspraxis.com: redesign audit (4 November 2026)

Brief: make jbspraxis.com hold its own next to global multinationals. Keep the PHP stack (Laravel 12 + Blade + Vite/Tailwind v4 in `praxis_site/`).

This audits **the site that went live on 31 October** (praxis_site), not the old site audited on 29 October (`audit/AUDIT.md`). Most of that audit's findings are already fixed. The benchmark is a tier-one consulting or software brand (McKinsey, Accenture, Stripe, Palantir).

**Sources:**
- Live pages: full-page screenshots at 1440 px and 390 px in `before-live/`.
- Local run: screenshots in `before/`. Local `public/build` dates from 30 October, so case-study styles are missing locally. Treat the live shots as the truth.
- Lighthouse 12.8: `lighthouse-before/`.
- Code: `praxis_site/`, brand book: `docs/BRAND.md` v1.3.

## Score: 63 / 100

**In one line:** the engineering is already at multinational level (Lighthouse 91–99 / 100 / 100 / 100). The gap is entirely in what a visitor feels and believes: no imagery, no motion, no numbers and a hero that can't be read. The redesign must close that gap without losing the Lighthouse scores.

### Lighthouse 12.8, live site, 4 Nov 2026

| Page | Device | Perf | A11y | Best pr. | SEO | LCP | CLS | Weight |
|---|---|---|---|---|---|---|---|---|
| Home | Mobile | 97 | 100 | 100 | 100 | 2.0 s | 0.006 | 4,112 KiB |
| Home | Desktop | 91 | 100 | 100 | 100 | 0.5 s | **0.174** | 4,274 KiB |
| Case study (FMCG) | Mobile | 98 | 100 | 100 | 100 | 1.7 s | 0.006 | 350 KiB |
| Case study (FMCG) | Desktop | 98 | 100 | 100 | 100 | 0.5 s | 0.015 | 350 KiB |
| Industries | Mobile | 91 | 100 | 100 | 100 | 2.6 s | 0 | 361 KiB |
| Industries | Desktop | 99 | 100 | 100 | 100 | 0.5 s | 0.003 | 427 KiB |

Failing audits on home (mobile): text compression, HTTP/2 (9 requests on HTTP/1.1), cache lifetimes (7 resources), server response 780 ms, total byte weight.

| Area | Score | Why |
|---|---|---|
| Strategy and messaging | 11 / 15 | Clear line and descriptor, and honest copy with no inflated claims. But the first screen never says who it is for or what changes for them; the support line ("We design how your organisation runs…") describes a process, not an outcome. |
| Visual design | 9 / 15 | Disciplined and on brand (Newsreader/Jakarta, logo teals, Praxis rule), but plain. Every page has the same pattern: a serif heading over rule-topped text columns. There are **no images at all**: 2 `<img>` per page, both of them the logo. The brand book's product and place layers (§10) are not built. |
| Motion and interaction | 3 / 10 | Hover colour only. The hero moment the brand book specifies (§11: the question, then the rule draws, then the answer settles) was never built. A 6.4 MB screen-recording loop stands in for it. |
| Proof and credibility | 7 / 15 | Named clients, honest case studies, and a CAC number in the footer are good. But there is no number, logo, quote or product screen anywhere. Homepage proof is a plain list of seven names. |
| UX and conversion | 10 / 15 | One consistent CTA and a working booking flow. But two floating layers cover the mobile first screen, the header scrolls away on 4,000–7,700 px pages, and there's no WhatsApp. |
| Performance | 7 / 10 | Lighthouse 91–99, but home weighs 4–6.7 MB because of the video loop (6.7 MB in a full Playwright load, ~30 s to network idle). HTML, CSS and JS are uncompressed, it's HTTP/1.1, there's no CDN, TTFB is 0.78–1.26 s, and desktop home CLS is 0.174. |
| Accessibility | 9 / 10 | Lighthouse 100. Strong base: skip link, labelled fields, focus rings, a pause button on the video, reduced motion respected. The losses are overlapping floating controls on mobile. |
| SEO | 7 / 10 | Lighthouse 100. | Unique titles and descriptions, hreflang, a 46-URL sitemap, llms.txt and IndexNow are in place. Missing: compression, breadcrumb, Article or case-study schema, per-page OG images, and any image content. |

## Top 10 issues, ranked by impact

1. **The homepage downloads a 6.4 MB video on every visit, phones included.**
   - `home.blade.php:36-41` sets `preload="metadata"`, but `app.js:170` calls `video.play()` straight away, so the whole file streams. That's `public/media/hero-60s-loop.mp4`, 6,446,059 bytes.
   - Measured: 6.7 MB transferred and 29–37 s to network idle at both widths (Playwright). Lighthouse counts 4.1–4.3 MB because it stops early. Every other page is 350–430 KB.
   - Home on desktop also shifts layout (CLS 0.174, the only page above 0.1).
   - On a Lagos 4G connection this is the single biggest bounce risk.
2. **No compression, no CDN, slow first byte.**
   - `curl` with `Accept-Encoding: gzip, br` gets no `Content-Encoding` on HTML, CSS (45.7 KB) or JS. JS has no `Cache-Control`. CSS is cached for 7 days but not marked `immutable`. TTFB is 1.26 s, from plain nginx with no Cloudflare.
   - The fix is server and DNS config, not a redesign, and it lifts every page.
3. **The signature hero moment doesn't exist, and the stand-in can't be read.**
   - The brand book storyboard (§11, "The hero moment") is a framed HQ screen where a question appears, the Praxis rule draws to the answer, and the figure settles.
   - What ships instead is a 1920×1080 recording squeezed to ~630 px on desktop and ~358 px on mobile. The spreadsheet text is too small to read (`before-live/home-desktop-fold.png`).
   - On phones the first frame is a blank cream panel (`before-live/home-mobile-fold.png`).
4. **Proof has no numbers, logos, quotes or screens.**
   - Homepage proof is a list of seven names (`home.blade.php:82-94`).
   - Case studies list modules ("What we built") and capabilities ("What changed"), not outcomes. None has a product screenshot (`before-live/work_fmcg-distributor-cameroon-desktop.png`).
   - Multinationals lead with results; this site leads with feature inventories.
5. **No imagery anywhere.**
   - Every page carries exactly two images, the header and footer logos.
   - The brand book's layered system (structure, product, place) is unbuilt. So are the one-image-per-sector rule for Industries and the product crops for case studies (BRAND §10).
   - The pages read as text documents.
6. **Two floating layers cover the first screen on mobile.**
   - The cookie banner (`app.css:489-493`, raised to `bottom: 80px` on phones) and the "Ask Praxis AI" pill stack over the hero video and the next heading (`before-live/home-mobile-fold.png`).
   - On desktop the banner covers the second section's heading (`before-live/home-desktop.png`).
7. **The CTA disappears on long pages.**
   - The header is `position: relative` (`app.css:140`), so "Book a free audit and demo" scrolls away.
   - Pages run 3,900–4,900 px on desktop and up to 7,755 px on a phone (case study), with the next CTA only at the very bottom.
8. **The homepage narrative stalls after the hero.**
   - It runs: problem → five domains → method → names → calculator → CTA. There's no section that shows the product answering a question, and none that shows a result.
   - The five domains sit in a 3 + 2 grid that leaves a hole (`app.css:408-410`; `home-desktop.png`).
   - The calculator is a bordered card with a teal left edge (`app.css:444`), the one card on a site whose brand book says "Website: no cards" (§9).
9. **Same visual rhythm on every page.**
   - Across home, Industries, case studies and How we work, the pattern is a serif H2 with rule-topped columns and grey body text, and the bands alternate Mist and Paper.
   - Nothing changes scale or density to mark the important moment on a page. That flatness is what makes the site feel smaller than it is.
10. **Thin structured data and sharing previews.**
    - Every page carries only `Organization` + `WebSite` JSON-LD: no `BreadcrumbList`, and no `Article` for Insights or case studies.
    - The OG image is one file per language for the whole site (`layouts/site.blade.php:16`), so every shared case study looks the same on LinkedIn and WhatsApp.

## What's already strong (keep)
- The line "Ask your company anything." and the descriptor.
- A brand book with real rules (colour contrast table, type scale, motion tokens).
- Honest, specific case-study copy.
- A strict CSP, form hardening and EN/FR on `/fr/` URLs with hreflang.
- Reduced-motion handling, the WCAG 2.2.2 pause button and labelled forms.
- `llms.txt`, IndexNow and a clean sitemap.

## Constraints the build must respect
- **Strict CSP:** `script-src 'self'`, `style-src 'self'`, no inline style or script. GSAP and Lenis must be bundled through Vite; no CDN. WebGL is fine, but inline `style=` from animation libraries needs care, since CSSOM writes are allowed and inline attributes are not.
- **Brand book §11**, "one orchestrated moment per page" and "sections below do not each animate in", conflicts with an Active Theory scroll system. This goes to the owner in question round 1.
- **Claims:** never invent client metrics or logos. Brand book §1 has a claims register.
