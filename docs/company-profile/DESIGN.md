# Company profile: the design and the PDF renderer

PR 2 of the company profile (the data, the editor and the versions are PR 1; the logo library and the
intro letter are PR 3). This is the part a client actually sees: a ten to fourteen page A4 landscape
monograph, one template for English and French, rendered by dompdf.

`docs/COMPANY_PROFILE.md` is the engine: the content, the versions, the editor, the download. This is the
design that engine renders, and section 8 of that document points here.

## The format

A4 landscape, 841.89 × 595.28pt. Twelve columns, a 16pt gutter, 54pt side margins, 46pt at the head and
52pt at the foot. Reading text sits in five columns, which is about 62 characters.

The type is `docs/BRAND.md` section 8, set for print:

| Role | Face | Size / leading |
|---|---|---|
| Cover line | Newsreader Display 400 | 46 / 52, −0.01em |
| Section statement | Newsreader Display 400 | 34 / 41 |
| Section title | Newsreader Headline 500 | 26 / 31 |
| Sub-head, team, pillar | Newsreader Text 400 | 12.5 / 16.5 |
| Problem | Newsreader Text 400 | 10.5 / 15 |
| Lead | Plus Jakarta Sans 400 | 11 / 17 |
| Body | Plus Jakarta Sans 400 | 9.3 / 14.5 |
| Small body | Plus Jakarta Sans 400 | 8.6 / 13 |
| Caption, meta | Plus Jakarta Sans 400 | 7.4 / 10.5 |
| Reference on every page | Plus Jakarta Sans 400 | 6.6 |

Colour is the brand's: Ink `#0C142B` for headings, Slate `#4A5468` for body, Muted `#5E6679` for
captions, hairlines `#C5CCD6`, and the teal family as a signal rather than a wash. One page is Ink.

## The device

One device, said once or twice a page, never as a pattern: **the Praxis rule** (`docs/BRAND.md` section
9). A 2pt horizontal rule, Ink to the left of its split and Teal to the right of it, with the split on
the column where the answer starts.

It is not decoration. On the problem pages the rule is Ink across the width of the problems and turns
Teal exactly where the answers begin, so the page's one graphic element states the page's one idea.
Everything else that separates is a 0.5pt hairline. On the Praxis Method the rule's split falls between
*Design* and *Build* — the point where the thinking becomes the thing, which is what praxis means.

The cover and the back cover carry the bar stack: three bars in the logo's proportions, ends cut on the
diagonal like the logo's, Ink left of the split and Teal right of it.

## The pages

1. **Cover** — the lockup, the line, the bar stack, the descriptor, and a great deal of air.
2. **Point of view** — the statement large on the left, the argument at reading size on the right, the
   three principles across the foot.
3. **What we build** — the pillars, two to a row under their own hairlines. Not cards.
4–6. **The hard problems we solve** — problem left, answer right, under the split rule. The domain sits
   in the problem column above the first problem it governs, not on a line of its own: on its own line
   it cost a third of the section in white space and made the list read as six short lists. An open
   set: the domains are listed, never counted.
7. **Practices and the Praxis Method** — one page, in both languages.
8. **Teams and credentials** — by discipline. Never a name.
9. **Selected work** — every case as an entry, three to a row: its own line, the client, the sector and
   what the system changed.
10. **Clients** — the logo wall, and the platforms across the foot: each client's own system as that
    client's team signs in to it, every one in the client's own brand. A logo wall says who hired us;
    the strip says what they got. The screens are the website's own, already public, and they are
    sign-in screens, so none carries a client's records. A case whose client the copy describes rather
    than names has no screen, which is right: there is nothing to show without naming them.
11. **Security and company facts** — the one Ink page.
12. **Back cover** — the call to action, where to find us, and the closing line.

**Twelve pages, in both languages**, which is the owner's brief. Getting there cost two things worth
knowing, because they are the first two to give back if the copy grows:

- The feature case lost its page. It had a whole page of place photography with its story on a Paper
  panel, and it was the most striking page in the book; fifteen problems need three pages at a size
  anyone would read, and this was the page that had to go. The entries carry each case's own line and
  its outcome. The problem it started from and what was built for it are in the proposal, where a
  reader who has got this far will ask for them.
- With it went the only photography in the book. The platform screens are the imagery now, and they
  are the better proof: a photograph of a port is atmosphere, and a client's own system is evidence.

The strip of platforms has no copy of its own in the contract, so its label sits in
`PdfRenderer::words()` until the content carries it.

## How the pages are decided

dompdf is given no say in where a page ends. `App\Support\CompanyProfile\Layout` cuts the content into
pages first and each page is one fixed-height `<div>`, so no section can split across a break.

Three things make that reliable:

- **Measuring, not guessing.** `App\Support\CompanyProfile\Measure` asks dompdf's own font metrics how
  wide each word is and breaks lines the way dompdf will. Counting characters is close enough in English
  and wrong in French, which runs 15 to 20 per cent longer.
- **Balancing.** The open sets are filled once to learn how many pages they need, then again with every
  page taking the same fraction of what it could hold, so the last page is not left with two problems
  beside a page of seven.
- **Fitting.** Every page is then test-rendered on its own, and any page that does not fit on one page
  gives its last item to the page after it until all of them do. This is what makes French safe.

## What the live server forces

The live web PHP has no `gd` (`docs/DOCUMENTS.md` section 3), and dompdf without `gd`:

- **cannot read a PNG at all** (`Cpdf::addPngFromFile()` returns before drawing);
- **throws on a CSS `background-image`** (`AbstractRenderer::_background_image()`);
- carries a **JPEG** with nothing but `getimagesize()`, and draws an **SVG** through php-svg-lib.

So all imagery is JPEG placed as `<img>`, cut to its exact shape at build time, and no page carries a
CSS background image. `praxis_core/resources/company-profile/README.md` has the rest.

Two more things dompdf made us do, both worth knowing before touching the templates:

- **Columns are inline blocks, not table cells.** dompdf ignores a cell's declared width — with
  `table-layout: fixed`, with a `<colgroup>`, with either — and gives every column in a row the same
  width. An inline block is placed at exactly the width it is given. Widths are always rounded *down*:
  a row whose columns add up to a hundredth of a point more than the row is wide wraps every column onto
  a line of its own.
- **A registered `@font-face` is written to `fontDir`, not `fontCache`,** and is only found at an
  absolute path. A path relative to the document's base is not resolved for fonts at all.

## The rules the file keeps

- A credential whose `held` is not `true`, and a fact whose `enabled` is not `true`, never reach the
  PDF. The editor shows them with their switch; the renderer drops them again, so a mistake upstream
  cannot put one in a client's hands. Tested in `tests/Feature/CompanyProfile/PdfRendererTest.php`.
- The Cameroon entity is off by default, and the test checks it is both off in the content and absent
  from the file.
- People are never named. Teams are described by discipline, experience and the credentials they hold.
- No prices anywhere.
- French spacing: the brand writes a narrow no-break space (U+202F) before `: ; ! ?` and inside
  guillemets. Neither brand family has that glyph, so dompdf would draw a `.notdef` box; it becomes a
  no-break space, which both families have. `App\Support\CompanyProfile\Typography` also binds the last
  two words of a paragraph, so no line is left holding a single word.

## Proofing it

```
php artisan company-profile:preview          # both languages into storage/app/company-profile
php artisan company-profile:preview fr --html
```

The preview reads `praxis_core/database/seeders/company-profile/content.{en,fr}.json`, the engine's own seed,
so a preview is the same copy a published version carries and there is one place for that copy to live.

`docs/company-profile/previews/` holds every page as an image, and a contact sheet per language. They
are rebuilt by rendering the two PDFs and rasterising them; a PDF rasteriser is not part of the app.
