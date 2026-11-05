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

## 9. The logo wall

`clients.logos` comes from the `ClientLogos` provider, never from the editor. PR 1 binds
`DirectoryClientLogos`, which lists the JPEGs, PNGs and SVGs in `praxis_core/resources/company-profile/logos/`
in filename order; a leading number sets the order and is not shown. An empty or missing folder gives an empty
wall, never an error. **PR 3 rebinds the interface** with the logo library managed from HQ.

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

They need MySQL (`tests/Feature/UsesThrowawayDatabase`):

```
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_DATABASE=praxis_test_profile \
  php vendor/bin/phpunit tests/Feature/CompanyProfile
```

## 12. Open decisions for the owner

1. **Who else may read the profile?** Today: the Owner, and Sales & Marketing (view only). Partner, Delivery
   Lead and Auditor do not see it, because it carries the teams' credentials.
2. **Who approves?** Today, only a role with `company_profile.approve` — which is the Owner alone.
3. **Where the profile lives in the rail** — it has its own entry, next to Documents. Say if it belongs inside
   Documents instead.
4. **The Cameroon entity fact** is off. Switch it on when the entity is registered.
5. **Tracked share links** (a link per prospect, with a record of who opened it) are not in PR 1. They need a
   public route and a decision about what a link exposes. Worth their own PR.
6. **The animated 24-slide deck** is after the profile, as agreed.
