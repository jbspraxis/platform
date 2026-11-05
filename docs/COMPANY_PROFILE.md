# The company profile

How the firm's own profile is written, approved and sent. Version 2, 5 November 2026.

The profile is one document in two languages, English and French, 10–12 pages, A4 landscape. There is one
profile — there is no separate client version and no separate tender version. It is written in HQ, approved in
HQ and downloaded from HQ. `docs/BRAND.md` wins on every question of language and design.

Three pull requests build it:

| | What it is | Where it lives |
|---|---|---|
| **PR 1** | The engine: the content, the versions, the HQ screens, the download hook | this document |
| PR 2 | The design and the PDF renderer | `App\Support\CompanyProfile\CompanyProfileRenderer` |
| PR 3 | The logo library and the one-page letter of introduction | `App\Support\CompanyProfile\ClientLogos` |

The data contract the three share is `company-profile/CONTRACT.md` in the repository root. It is fixed. Change
it with the lead chat, never on your own, and change `App\Support\CompanyProfile\Sections` in the same commit.

---

## 1. Where it is

HQ → **Company profile**, its own entry in the rail.

It is not under Documents, which is a register of individual documents issued on the letterhead, each with its
own reference and its own life. The profile is one standing asset of the firm with one lifecycle, its own
permission module and its own approval; sales open it weekly to download it, and the rail entry is how they
find it. Three screens:

- **Sections** (`/company-profile`) — the editor, section by section, English and French side by side.
- **Versions** (`/company-profile/versions`) — every version there has ever been, and what changed.
- **Preview** (`/company-profile/versions/{version}/preview`) — a version exactly as the renderers read it.

## 2. Who may do what

The permission module is `company_profile`, with four actions:

| Action | What it opens |
|---|---|
| `view` | Read every screen and download the published profile |
| `edit` | Start a version, write it, send it for review |
| `approve` | Approve and publish a version, or send it back |
| `manage` | Throw a draft away, and look after the logo library (PR 3) |

The profile carries the teams' credentials, so the module is marked `'wildcard' => false` in
`config/access.php`: a starter role granted `*` or `*.view` — including **Auditor** and **Partner** — does not
get it. The migration `2026_11_05_900001_grant_company_profile_permissions` grants exactly one thing: the
**Sales & Marketing** role gets `company_profile.view`, because sales cannot do their job without the profile.
Everything else is the Owner's until the owner ticks it on Roles and permissions.

**Team data never leaves HQ.** Nothing here is exposed on the website, the client portal or the Site API.

## 3. Versions

A version is `draft` → `in_review` → `published`. Exactly one version is `published`; the one it replaced
becomes `superseded` and is never changed again, so any profile that has ever gone out can still be read
exactly as it was sent.

- **Start a new version** copies the published one word for word. Only one draft is open at a time: two people
  editing two drafts would quietly lose one of them.
- **Send for review** moves it to `in_review` and records who sent it.
- **Approve and publish** (`company_profile.approve`) publishes it and supersedes the old. Publishing never
  edits a published version in place.
- **Send back** returns it to `draft` with a reason.
- **Throw the draft away** (`company_profile.manage`) deletes a draft and nothing else. A published or
  superseded version cannot be deleted.

Each version has a number (`v3`) and a reference, `JBS-CP-2026-11-v3`: the year and month it was started, and
the version. Every step reaches the audit log under `COMPANY_PROFILE_*`.

## 4. How the content is stored

Two tables (`2026_11_05_900000_create_company_profile`):

- `company_profile_versions` — one row per version.
- `company_profile_sections` — one row per (version, section), with three JSON columns.

The three columns are the heart of it:

| Column | What it holds |
|---|---|
| `structure` | Everything that is **not words**: the id and order of every list item, the `held` switch on a credential, the `enabled` switch on a fact, a case story's image, the contact URLs |
| `text_en` | The English words, keyed by those ids |
| `text_fr` | The French words, keyed by those ids |

A credential the firm holds is held whatever language you are reading. So is the number of case stories and
the order they come in. Keeping that in one place makes it **impossible** for English and French to disagree
about it: add an item and both languages get it, move one and both move, switch one off and it is off in both.

`App\Support\CompanyProfile\Sections` is the single definition of what a section is made of, and the one place
that decides, field by field, whether it is words or a shared fact. Nothing else in the module knows the
sections by name, apart from the `clients` logo wall and the preview screen's layout.

The twelve sections: `cover`, `pov`, `system`, `problems`, `practices`, `method`, `teams`, `work`, `clients`,
`security`, `facts`, `contact`.

## 5. The editor

`/company-profile`. Sections down the left, the section's fields on the right, English and French side by side
(or one at a time, from the Language picker). Every word and every switch saves itself as it is changed —
nobody loses a draft to a closed tab.

Lists — teams, case stories, problems and the lists inside them — have **Add**, **Move up**, **Move down** and
**Remove**, and each of those acts on both languages at once. A credential has a **Held** switch and a company
fact has a **Show** switch; the editor shows all of them, and only the ones that are on are ever rendered.

A list of switches also has **All on** / **All off** and says how many are on (`3 of 9 on`), because the
written profile arrives with twenty-one credentials and only one held: the owner ticks a team in one go and
unticks the odd one. Each credential row is named after the credential, not numbered.

A published version opens read-only, with **Start a new version** next to it.

## 6. Reading the profile

```php
use App\Support\CompanyProfile\Profile;

Profile::data('en');          // the published version, exactly as company-profile/CONTRACT.md fixes it
Profile::data('fr', 3);       // version 3 (the NUMBER, not the id), for a preview
Profile::editorData('en');    // everything, including switched-off items and every item's id
Profile::changes($version);   // what is different from the published version, as readable lines
```

`data()` is what every renderer reads. It has already dropped the credentials that are not held and the facts
that are switched off, so a renderer cannot leak one by mistake. `editorData()` shows everything and is for
the editor alone.

## 7. The seeder

`CompanyProfileSeeder` imports `database/seeders/company-profile/content.en.json` and `content.fr.json` as
version 1, published. Those two files are where the written copy lives, in the contract's own shape; replacing
them and running `migrate:fresh --seed` is how new copy arrives.

The two files are read side by side: the nth case story in English is the nth in French, and they become one
item with one id. Switches and values that are not words are taken from the **English** file, so the French
file need not repeat a credential at all. Anything that does not line up is printed as a warning, never
guessed at.

The seeder leaves a database that already has a version alone — that version may be the one the firm is
sending out.

The written profile is in those two files as of 5 November 2026 (the lead chat's copy, imported unchanged).
It arrives with **one credential held of twenty-one** — the owner ticks the rest in the editor — the Cameroon
entity fact **off**, and `contact.phone` **empty** for the owner to fill in.

## 8. The download

`GET /company-profile/download?locale=en&version=3` — route name **`company-profile.download`** (the plain
name, agreed in the contract, so PR 2 and PR 3 link to it without knowing where it is mounted). `version` is
optional and is the version number; without it you get the published one. A version that is not published can
only be downloaded by someone who may edit or approve.

The route hands `Profile::data()` to `CompanyProfileRenderer::pdf()`. `StubProfileRenderer` is still here and
still works — one plain A4 landscape page written by hand, no PDF library and no font files — but
`AppServiceProvider` now binds **`App\Support\CompanyProfile\PdfRenderer`**, the designed profile: A4
landscape, twelve pages, one template for English and French, rendered by dompdf.
**`docs/company-profile/DESIGN.md`** has the grid, the type, the device, the page plan, and what the live
server's missing `gd` forces on the design. Nothing else about the download changed.

### The deck

`GET /company-profile/deck?locale=en&version=3` - route name **`company-profile.deck`** - is the same profile
as **twenty-four animated slides**, in English and French, from the same version and behind the same
permission. The two downloads share the code that resolves the version, so a draft cannot leak through one of
them and not the other, and the filename is the PDF's with its own extension. The button sits beside Download
on the editor and on a version's preview; the versions list carries PDF EN / PDF FR / Deck EN / Deck FR.

**`docs/company-profile/DECK.md`** has the slide list, the template, the motion, the two faces it is set in
and why it embeds none, and what the live server's missing `ext-zip` forces on it.

## 9. The logo wall: the client logo library

`clients.logos` comes from the `ClientLogos` provider, never from the editor. **PR 3 binds the HQ logo
library** (`App\Support\CompanyProfile\LogoLibrary`) over PR 1's `DirectoryClientLogos` in
`AppServiceProvider`, so the wall is managed in **HQ > Company profile > Client logos** rather than by dropping
files in `resources/company-profile/logos/`. That folder is no longer read.

One library; the company profile, the deck and the strip on jbspraxis.com all read it, so a client is named
once and corrected once. Each logo has the organisation's name **as it may be shown** (section 10's naming
rules hold here too: a client who has not agreed to be named is described, and then no logo is used), a sector,
a place in the order, and three ticks - **profile**, **deck**, **website** - saying where it appears. A logo is
**retired**, never deleted, so the profile versions that carried it stay true; a retired logo can be put back,
and returns at the end of the order.

### The three renditions, and why there are three

No PHP here can convert an image: the live web PHP has **no gd and no imagick**, and dompdf **cannot read a
PNG at all** (`docs/DOCUMENTS.md` section 3). So the browser makes all three when the file is chosen
(`resources/company-profile/logos.js`), the way the signature pad makes a JPEG, and the server only checks the
bytes really are what they claim before it keeps them:

| Slot | What it is | Who reads it |
|---|---|---|
| `print` | the SVG as it was chosen, or a **JPEG on white** | the company profile PDF, and anything else dompdf renders |
| `png` | the same artwork on transparency | the deck, and any renderer that can read a PNG |
| `web` | a WebP | jbspraxis.com |

A raster logo is trimmed to its ink and scaled to 160 px high (at most 720 px wide) before any of this, so
every logo on the wall sits on the same baseline. An SVG is kept exactly as it was for print, because it stays
sharp at any size, and is rasterised for the other two.

**An uploaded SVG is cleaned** (`App\Support\CompanyProfile\LogoFiles::cleanSvg`): only shape elements and
presentation attributes survive, every event handler and script goes, and a reference may only point inside
the file. What is left is what php-svg-lib draws and what a browser can show without running anything.

**An SVG that draws its lettering with a font is refused for print**, rather than quietly losing the words:
the PDF carries only DejaVu and cannot fetch the real face. The screen's own script notices `<text>` first,
draws the logo to a JPEG with the font it has right there, and sends that as the print copy with a line saying
why; `LogoFiles::decode` refuses such an SVG outright, so the words can never be lost without somebody being
told. Outlined lettering is only shapes, and goes in as it is.

The files live on the **private disk** (`storage/app/private/company-profile/logos/`). Nothing is on a public
path: HQ is served a logo through a route behind `company_profile.view`, and the website through the signed
Site API.

`ClientLogos::all()` is what the engine reads - `[['name' => ..., 'path' => ...], ...]` for the logos ticked
for the profile. `path` is always a file the PDF renderer can **actually draw**: an SVG or a JPEG, **never a
PNG**, which is a deliberate narrowing of `clients.logos` in the contract ("a PNG/SVG"), because a PNG would
throw on the live server. `LogoLibrary::forProfile('deck'|'website')` gives the richer rows - the transparent
PNG, the kind, the key and the sector - to anything that wants them. A logo whose print file has gone missing
is left out rather than breaking the render.

### Where it starts

`Database\Seeders\ClientLogoSeeder` puts the **fifteen logos the website already shows** in the library, in
the order it shows them, so nothing changes on jbspraxis.com the day HQ takes the strip over. Their three
renditions live in `database/seeders/company-profile/logos/`; `build_logos.py` beside them rebuilds those files
from the website's own WebP, and is the only thing that ever converts an image here. The last two, Praxis LS
and NFELIA, are our own products: the strip shows them, the profile does not count them as clients. Sectors
nobody has on record are left empty for the owner to fill, rather than guessed.

Re-seeding never overwrites a logo somebody replaced in HQ: a key that is already there is left alone.

JPEG is on that list because of the renderer. The live web PHP has no `gd`, and without it dompdf cannot read
a PNG at all, while a JPEG needs nothing but `getimagesize()`. The library lists a PNG as it always did, and
the PDF leaves it off the wall rather than drawing a broken image, so a logo that will not appear is a logo in
the wrong format rather than a bug. Put a JPEG or an SVG in that folder.

## 10. Rules that do not bend

- Only **held** credentials and **enabled** facts are ever rendered.
- Team data is HQ only. People are never named; teams are described by discipline and credentials.
- No prices anywhere.
- Banned words: "squad", "deployed", "buy"/"sell" for our systems ("we build"), "leverage", "cutting-edge",
  "solutions provider", "one-stop shop".
- Fidson is never named ("a leading pharmaceutical manufacturer"); the oil and gas client is "an oil and gas
  company". **La Petite Cour may be named**, because the website names it.
- `contact.closing` is the brand's sign-off ("Order, made visible." / « L'ordre, rendu visible. »), on the
  last page and nowhere else.
- The Cameroon entity is a company fact that is **off by default**.

## 11. Tests

`praxis_core/tests/Feature/CompanyProfile/` — 50 tests:

- `ProfileContractTest` — the contract shape for both locales, key by key; the gating; the editor query; the
  logo provider; a missing section still returning its whole shape.
- `ProfileWorkflowTest` — draft from published, one draft at a time, publish and supersede, send back, discard,
  the editor refusing to write to a published version, add/move/remove and switches holding in both languages,
  the diff, the audit log.
- `ProfileAccessTest` — the four permissions, the wildcard being kept off, the owner, sales, every screen,
  every action, and what the grant migration grants.
- `ProfileSeederAndDownloadTest` — the seeder, its idempotence, the importer lining the two files up and
  warning when they do not, the download route, the stub PDF and the default logo provider.

PR 3 adds, in the same folder:

- `LogoLibraryTest` - the four permissions and the wildcard being kept off, adding a logo with its name and
  sector, the SVG cleaner, an SVG whose lettering is live text, a file that lies about what it is, the order
  and its two ends, retire and restore, what the provider hands the engine, a logo whose file has gone
  missing, and the signed `/clients` feed.
- `IntroductionLetterTest` - the template and its reference, the language and contact fields, the recipient's
  own details, the words coming from the published profile, the banned words, the sign-off following the
  language, one page in both languages, the copy budget, issuing and downloading it, and what the letter says
  when nothing is published.

PR 4 adds:

- `DeckRendererTest` - the .pptx opening without `ext-zip`, twenty-four well-formed slide parts, the 16:9
  slide and every relationship a slide names, that every slide builds itself and the sections morph with a
  fade under them, the held and enabled rules in the slide text, nobody named and no banned word, the
  pharmaceutical client unnamed and no price, every slide fitting in both languages with no autofit anywhere,
  no reading text below the floor, the two faces, French spacing, and the same profile rendering to the same
  bytes twice. It needs no database.
- `ProfileDownloadDeckTest` - the route, the permission, both languages, the filename, the published copy, and
  a draft needing edit or approve.

They need MySQL (`tests/Feature/UsesThrowawayDatabase`):

```
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_DATABASE=praxis_test_profile \
  php vendor/bin/phpunit tests/Feature/CompanyProfile
```

## 12. The introduction letter

**HQ > Documents > New document > Introduction letter** (PR 3). One page introducing JBS Praxis to a company we
would like to work with. It is a `letter_documents` row like every other formal document
(`docs/DOCUMENTS.md`), so it carries a reference of its own (`JBS/INT/2026/001`), sits in the register, is
signed with a stored signature, is issued once with its SHA-256, and can carry the QR code that opens
`jbspraxis.com/verify/{token}`.

The author fills in four things beyond the common ones: the **company** and its **address** (the document's
"Addressed to"), a **contact person**, their **email** (optional), and the **language**, English or French.

**Everything else is read from the published profile** through `App\Support\CompanyProfile\ProfileCopy`,
which calls `Profile::data($locale)`. Nothing is kept twice, so the letter and the profile cannot drift apart:

1. the salutation - the contact person by name, or "Dear Sir or Madam" / "Madame, Monsieur,";
2. one short paragraph naming the recipient company and why we write, with `cover.descriptor` inside it;
3. one paragraph on what we build: `pov`'s closing paragraph, without the sentence that only makes sense after
   the two before it ("We close it differently.");
4. **three proof lines** from `work.items` - the client and what the work did for them. Our own products are
   left out: they are not proof of what we do for a client. A client is named exactly as the profile names
   them, which is where section 10's naming rules are kept;
5. the call to action with the schedule link and a reply address (`contact.cta`, `cta_url`, `sales_email`);
6. the sign-off **JBS Praxis**, over the signature block.

`cover.headline` is the subject line.

**With nothing published** - which is where production stands until the owner signs in and the profile is
seeded - `Profile::data()` raises `MissingProfile`, `ProfileCopy` catches it, and the letter writes on short
house copy: the headline, the descriptor, what we build and the call to action, and **no proof lines at all**.
It never names a client of its own accord; a client's work is the profile's to publish, not the letter's to
remember. `test_with_no_published_profile_the_letter_writes_without_claiming_any_work` holds it to that.

### One page, in both languages

The letter is **set tighter than the other templates** (`$compact` in `resources/views/pdf/letter.blade.php`):
9.3 pt on 1.33, smaller margins, a shorter signature line. That buys one page, and no more, so the copy has a
budget, which `IntroductionLetterTest::test_the_letter_copy_stays_inside_the_one_page_budget` holds to:

- the headline and the descriptor: 90 characters each
- what we build: 320 characters
- exactly three proof lines, 220 characters each
- the call to action: 120 characters

`test_it_holds_one_page_in_english_and_in_french` renders both PDFs and counts the pages. If new copy breaks
either, shorten it or decide on a second page on purpose.

French goes all the way through: the date, "Notre reference", the salutation, the closing, the draft notice,
the QR words and "Page 1 sur 2" in the footer. Nothing else in Documents changes language; every other template
is English, as it was.

## 13. The logo strip on jbspraxis.com

The strip "Organisations we have worked with" is fed by the library, so **a logo added in HQ and ticked for the
website appears on the site without a code change**.

| Method and path | What it answers |
|---|---|
| `GET /api/site/v1/clients` | The website logos, in order: `key`, `name`, `sector`, `sha256` of the WebP, `width`, `height` |
| `GET /api/site/v1/clients/{key}.webp` | That logo's WebP |

Both are plain signed reads under the API's own read limit (`docs/SITE_API.md`).

On the site (`App\Support\Clients`):

- The **list** is a cached read: 10 minutes, with the last good copy kept for a day, like settings and
  portfolio. The page makes one call and no more.
- Each **file** is fetched once and then kept on this server. The markup points at the site's own
  `/img/client-logos/{key}.webp?v=<first 12 of the sha>`, so a logo replaced in HQ is a new address and nothing
  anywhere serves yesterday's picture; that address is immutable for a year.
- **The route is public and cookieless, so a visitor chooses both the key and the version.** Neither may make
  this server call HQ or write a file, or a bot could fill the disk one made-up version at a time and drain
  the Site API allowance the contact form, the chat and the verify page all share. So HQ is asked for a logo
  **only** when the cached list names that key **and** the caller asked for exactly the version the list
  names, and the bytes are kept **only** when their SHA-256 is the one the list gave. One file is kept per
  key, and a new version deletes the one it supersedes. Everything else — a made-up version, a key nobody
  knows, bytes that do not match — is answered from what is already here, without a single call to HQ, and a
  made-up version is never answered as immutable.
- **It never breaks.** If HQ cannot be reached, the strip falls back to whatever version of each logo this
  server kept last, then to the fifteen shipped with the site (`config/site.php` `clients` and
  `public/img/clients/`), which is exactly what it showed before, then to 404.

The shipped list and files stay in the repository on purpose: they are the floor the strip stands on.
`praxis_site/tests/Feature/ClientStripTest.php` holds the route to all of this, including the two ways a bot
would try to abuse it.

## 14. Tracked share links

**HQ › Company profile › Share links.** A link per prospect: it opens the profile PDF on jbspraxis.com with no
sign-in, every open is counted and rings the maker's bell, and it can be revoked or left to expire. Built to
the owner's answer of 5 November 2026, "tracked share links (opens and bell)".

Making one needs `company_profile.view` — anyone who may read the profile may send it. Revoking is for the
person who made the link, or anyone with `company_profile.manage`.

A link carries a **language**, a **version** (pinned, or whatever is published when it is opened), an optional
**recipient name and company** — which the visitor never sees; they name the link in the list and on the bell —
and an optional **expiry** in days.

### The address

`https://jbspraxis.com/profile/<token>`, where the token is **48 random characters**. It is never the version
number and never derived from one, so a link cannot be guessed and nobody can walk the versions by changing a
digit. The token is shown in full **once**, when the link is made, so it can be copied into an email; after
that the list shows what the link is for, not the address. `token` is hidden on the model, so it does not slip
into a payload by accident.

### What a visitor gets

| State | Answer |
|---|---|
| Open | The PDF itself, rendered through the **same renderer** as the HQ download |
| Revoked, expired, or never a link | `410` and a plain branded page, identical for all three |
| Opened too often in an hour | `429` and a calm page saying the link is resting |

Because the PDF comes through the same renderer, the held/enabled and naming rules of
`company-profile/CONTRACT.md` hold on a share link exactly as they do everywhere else: there is no second path
that could render an unheld credential or name a client the rules say to describe.

The three ended states read **the same page, byte for byte** (a test asserts it), so a stranger who tries a
token cannot tell a revoked link from one that never existed.

Nothing is ever cached: `Cache-Control: no-store` and `X-Robots-Tag: noindex, nofollow, noarchive` on the PDF
and on the page, because the link can be revoked at any moment.

### What is recorded, and what is not

An open is recorded with the time, a **coarse** user agent ("Chrome on Windows" — never the whole string,
which carries versions and build numbers that together name a device), and the visitor as a **SHA-256 of the
address and the app key**. The address itself is never stored.

The same visitor opening it again within **30 minutes** is the same open: it is not counted again and does not
ring the bell again, so a PDF viewer that asks twice is one reader, not three.

The bell goes to the person who made the link. `notifications.type` is an enum (`System`, `Financial`,
`Project`, `Message`, `QA_Update`) — anything else makes the insert fail inside the `rescue` and the bell
never rings, which is why the type here is `System` and a test watches for it.

### The limits

- **Per token**: 120 opens an hour in HQ, then `429`.
- **Per visitor** on the site: 10 a minute and 60 an hour (`site.limits.profile_share_*`), so a bot that finds
  one link cannot pull the PDF in bulk.

### Files

| Part | Files |
|---|---|
| Data | `database/migrations/2026_11_05_980000_create_company_profile_share_links.php`, `app/Models/CompanyProfile/{ProfileShareLink,ProfileShareOpen}.php` |
| Rules | `app/Support/CompanyProfile/Shares.php` |
| Screen | `app/Livewire/Hq/CompanyProfile/ShareLinks.php`, `resources/views/livewire/hq/company-profile/shares.blade.php` |
| The API the site reads | `app/Http/Controllers/SiteApi/ProfileShareController.php`, `routes/site-api.php` |
| The site | `praxis_site`: `app/Http/Controllers/ProfileShareController.php`, `app/Support/HqApi.php` (`profileShare`), `app/Exceptions/Hq/HqGone.php`, `resources/views/pages/profile-link.blade.php`, `lang/{en,fr}/site.php`, `routes/web.php`, `config/site.php` |
| Tests | `praxis_core/tests/Feature/CompanyProfile/ShareLinksTest.php`; `praxis_site/tests/Feature/ProfileShareTest.php` |

## 15. Open decisions for the owner

1. **Who else may read the profile?** Today: the Owner, and Sales & Marketing (view only). Partner, Delivery
   Lead and Auditor do not see it, because it carries the teams' credentials.
2. **Who approves?** Today, only a role with `company_profile.approve` — which is the Owner alone.
3. **Where the profile lives in the rail** — it has its own entry, next to Documents. Say if it belongs inside
   Documents instead.
4. **The Cameroon entity fact** is off. Switch it on when the entity is registered.
5. **Tracked share links** are built (section 14). What is left is the owner's call on how long a link
   should last by default; today the screen offers 30 days and the field can be emptied for one that never
   expires.
6. **The animated 24-slide deck** is after the profile, as agreed.
7. **`company_profile.*` for other roles** - the owner ticks whichever roles should see the logo library, on
   Roles and permissions.
8. **The sectors the logo seed left empty** (La Petite Cour, RN Afriluxe, HOD): nothing on record said what
   they are, so the seeder left them blank rather than guessing. The owner fills them in on the screen.
