# Documents: the letterhead, and the papers JBS Praxis issues

HQ › Documents writes the firm's own formal documents — board resolutions, letters, introductions, employment letters and authorities — on a letterhead built from Master data › Legal entities, signs them with each person's own stored signature, and issues them with a reference, a stored PDF and its fingerprint. Anyone holding one can check it on jbspraxis.com. Built 4 November 2026 from the owner's decisions below.

Company documents (docs/COMPANY_DOCUMENTS.md) keeps the papers the firm **receives**; this keeps the ones it **issues**, and an issued PDF can be filed there in one click.

## 1. The owner's decisions (4 November 2026)

1. **Its own place.** Documents is its own entry in HQ's left rail, with its own permission group, the `letters` module: `view`, `create`, `sign`, `manage`.
2. **Only the Owner role, for now.** No other role gets anything until the owner ticks it on Roles and permissions. The grant migration grants no role anything, and the module is marked `'wildcard' => false` in `config/access.php`, so a starter role's `*` never sweeps it up on a database built from nothing either.
3. **What it writes**, all on the letterhead, as a PDF: ready-made templates (a board resolution — opening a corporate bank account, or appointing a signatory — a formal letter, a letter of introduction, an employment letter, an authority to act), a free letter with a rich-text body, a draft Claude writes from one sentence, and a blank letterhead to download as a PDF or a Word file.
4. **Today's date** (Africa/Lagos) fills in by itself and can be edited.
5. **A reference per kind and year** — `JBS/RES/2026/001`, `JBS/LET/2026/001` — with a register of every document: who, when, status.
6. **Signatures.** Each person draws or uploads their signature once; it is stored encrypted and never on a public path. Only its owner can apply it, and only with a fresh sign-in. Every use is in the audit log.
7. **An optional QR code**, off by default, that opens a page on jbspraxis.com saying whether the document is genuine and whether it still stands. The reader can drop their own copy on that page to check nothing in it has changed.
8. **After issue**: revoke, or replace with a new version that points back. The issued PDF itself never changes.

## 2. Who may do what

| Permission | What it opens |
|---|---|
| `letters.view` | The register, a document, its PDF, the blank letterhead |
| `letters.create` | Start a draft, fill it in, ask Claude for a draft |
| `letters.sign` | Apply **your own** signature (needs a fresh sign-in) |
| `letters.manage` | Issue, revoke, replace; whether the check page may show a copy |

Filing an issued PDF in Company documents needs `mdm.create` as well, which is the permission that screen uses for an upload. Saving your own signature needs nothing but being signed in — it is yours, and it signs nothing on its own.

## 3. The letterhead

One per company in Master data › Legal entities (`mdm_legal_entities`), so it follows the company's own details and is never hard-coded: JBS Praxis Limited (NG) and JBS Praxis SARL (CM) today.

- **Header**: the logo, then the office's own details on the right — the legal name, the registered address line by line, the phone and the email address.
- **A fine rule in brand teal** under the header, and another above the footer.
- **Footer, on every page**: the legal name · `RC`/`RCCM` number · `TIN`/`NIU` number · `Registered office: …`, then the phone · the email address · the website, with **Page 2 of 3** on the right of anything longer than a page. It is drawn by `LetterPdf::fromHtml`, not by the page, so it repeats whatever the document does.
- A4, 48 pt top, 56 pt sides, 86 pt bottom.
- **The registered address is 21B Bode Ajakaiye Street, Lekki, Lagos.** The CAC papers carry "12": that is the lawyer's typo, being corrected. Everything here reads the legal entity, so putting it right there puts it right everywhere.

### Why the letterhead is drawn the way it is

The live web PHP has **no gd, no imagick, no zip extension and no QR library** (docs/OPPORTUNITIES.md section 9). dompdf is what renders the PDF, as it does for proposals and SOWs, and on that PHP it can draw **SVG** (through php-svg-lib) and carry a **JPEG** as it is, but **cannot read a PNG at all**. So:

- **The logo is SVG**, `resources/brand/letterhead-logo.svg`. The brand kit's horizontal logo nests one `<svg>` inside another and crops each one through its viewport; php-svg-lib ignores a nested `<svg>`'s viewBox and does not clip (its `ClipPath` tag is a stub), so the kit's file came out as the wrong shapes in the wrong places. The copy here is the same artwork resolved into flat `<path>` elements in the root viewBox's own coordinates, cropped where the viewports cropped it. Rebuild it from the kit with the script in the PR; `LetterPdfWithoutExtensionsTest` checks it stays flat.
- **A signature is a JPEG.** The browser turns a drawing or a photograph into one on a white background before it is sent, so nothing server-side has to convert an image.
- **The QR code is SVG**, drawn by `App\Support\Letters\Qr`, written here because there is no library and no gd: byte mode, error correction level M, versions 1 to 10, the smallest version that fits, then the mask with the lowest penalty, as ISO/IEC 18004 says. It was checked against the python `qrcode` library while it was written (identical codewords for every version, and the identical matrix for versions 5 and up), and `tests/Unit/Letters/QrTest.php` reads a code back to the text that went into it.
- **Fonts**: dompdf carries DejaVu Serif and DejaVu Sans, and those are what the documents use. The brand's own faces (Newsreader and Plus Jakarta Sans) exist in the repository only as woff2, which dompdf cannot read. Shipping them as TTFs is a small follow-up; the colour, the hierarchy and the spacing already follow docs/BRAND.md.

## 4. What it writes

Templates live in `App\Support\Letters\LetterTypes` (what each one is called, the code its references run with, its few fields) and `App\Support\Letters\LetterBody` (the words). Nothing in them is a legal opinion: it is the firm's house wording, and every sentence can be edited before the document is issued.

| Kind | Code | Fields |
|---|---|---|
| Board resolution: open a corporate bank account | `RES` | bank, account, currency, signatories, how the account is operated, who signs the bank's forms, place, legal basis |
| Board resolution: appoint a signatory | `RES` | bank, account, account number, the person and their role, the mandate, who signs the forms, place, legal basis |
| Formal letter | `LET` | a rich-text body you write (or Claude drafts) |
| Free letter | `LET` | the same, with nothing filled in |
| Letter of introduction | `INT` | who is introduced, their role, what they are there to do, good until |
| Employment letter | `EMP` | employee, job title, basis, started on, pay (left out when empty), what the letter is for |
| Authority to act | `AUT` | who is authorised, their role, what they may do (a line each), what they may not, from, until |

Every kind also has: the organisation it is addressed to and their address, a salutation and a closing.

A board resolution prints a centred title block — the company, its RC number, a short teal rule, "Written resolution of the sole director" and what it is for — then the date and place, the legal basis, the numbered `RESOLVED` clauses and the signature block. A bank asks for exactly this.

**Claude's draft** (`letter_draft` in `config/ai.php`): Claude Haiku, low effort, 3,000 tokens, run while the page waits. One sentence of purpose goes in; a subject line and a plain body come back, turned into the rich-text body by `App\Support\Campaigns\RichText::fromPlain`. It writes the body only — never the letterhead, the date, the address block, the salutation or the closing — and is told to write around anything the brief leaves out rather than guess it. Ten drafts per person per ten minutes. The author reads it, changes it and saves it; nothing is issued by Claude.

**The rich-text body** is cleaned on the server by `App\Support\Campaigns\RichText::clean` before it is kept: paragraphs, line breaks, bold, italics, underline, links, lists and two heading levels survive, and nothing else.

## 5. Signatures

- **Saving one**: HQ › Documents › My signature. Draw it with a finger or a mouse, or choose a photograph of a signature on white paper. Either way the browser hands over a **JPEG on white** (`resources/letters/documents.js`), a drawing trimmed to its ink so it sits on the line rather than floating above it. The server checks the bytes really are a JPEG, up to 2 MB.
- **Where it is kept**: `letter_signatures.image`, with Laravel's `encrypted` cast, so it is unreadable in the database and never written to a public path. One per person.
- **Applying it**: on a draft, "Sign as `<name>`, `<role>`". `Letters::sign` always reads the signed-in person's own signature, so nobody can apply anybody else's. The moment it is applied, the image is copied onto `letter_document_signatures`, so a document keeps the signature as it was that day even if the person later changes or removes theirs.
- **The fresh sign-in**: signing needs a sign-in less than ten minutes old (`App\Support\SignIn\SignIn::isFresh`, docs/SIGN_IN.md section 2). Otherwise the page asks for the password again — five tries, then ten minutes' wait — and signs once it is given. Saving a signature asks the same.
- **Audit**: `SIGNATURE_SAVED`, `SIGNATURE_REMOVED`, `LETTER_SIGNED`, `LETTER_SIGNATURE_REMOVED`.
- An unsigned document prints a signature line with the legal representative's name and role under it, for a pen.

## 6. References, issuing, and what comes after

- **The reference** is `JBS/<code>/<year>/<number>`, the number running per code and year, taken under a lock so two people cannot take the same one. A free letter shares the letter's run of numbers.
- **Issuing** renders the PDF once, stores it on the private disk (`storage/app/private/letters/<year>/<id>.pdf`), records its SHA-256 and size, and gives the document the token the QR code carries. **After that the file never changes.** The screen refuses to save an issued document, and the download is the stored file, byte for byte.
- **Revoking** keeps the file and marks the document revoked, with the date and the reason. Anyone who checks it is told.
- **Replacing** starts a new draft that points back at the old one. The old one becomes "replaced" only when the new one is issued, so a replacement nobody finishes leaves the original standing.
- **Filing**: once issued, "File in Company documents" puts the PDF in Master data › Company documents under its own reference, unchanged. Filing it twice is refused.
- **The blank letterhead**: `/documents/blank/{entity}.pdf` and `.docx`, built from the legal entity each time, so neither is ever out of date. The Word file is written by `App\Support\Letters\DocxLetterhead` — a .docx is a zip of XML parts, and the zip is written by hand with `gzdeflate` and `crc32` because the live PHP may not have the zip extension. The header and footer are real Word header and footer parts, so they repeat on every page and the typing stays in the body.
- **Audit**: `LETTER_CREATED`, `LETTER_ISSUED`, `LETTER_REVOKED`, `LETTER_REPLACEMENT_STARTED`, `LETTER_REPLACED`, `LETTER_FILED`.

## 7. Checking a document: jbspraxis.com/verify/{token}

The QR code is small, sits at the foot of the document beside a line of plain words, and carries `https://jbspraxis.com/verify/<token>`. **The token is 48 random characters, not the reference**: knowing that `JBS/RES/2026/001` exists tells a visitor nothing, and nobody can walk the register by guessing.

The page (`praxis_site`, English at `/verify/{token}`, French at `/fr/verify/{token}`, `noindex`) says:

- **"Genuine document issued by JBS Praxis Limited"**, or, for a token nobody was given, "We have no record of this document" — the same page, so a scan always answers in the same words.
- The reference, what the document is, the date, who issued it, the organisation it is addressed to, and who signed it, with their roles.
- Its state: **valid**, **revoked on `<date>`** with the reason, or **replaced on `<date>` by `<reference>`**.
- **A copy to open**, when the document allows it. It is off for anything confidential, and then the page says so.
- **Check your own copy**: the reader drops the PDF they were given on the page. **The file is hashed in their own browser** (SHA-256, `crypto.subtle`) and compared with the fingerprint of the issued file. It never leaves their device and never reaches us. A changed byte says so.

The site reads HQ through the signed Site API (docs/SITE_API.md), never the database:

| Method and path | What it answers |
|---|---|
| `GET /api/site/v1/documents/{token}` | Everything above, as the document says it in print; `404 not_found` for anything else |
| `GET /api/site/v1/documents/{token}/file` | The issued PDF, only for a document whose copy may be shown |

Both are limited again per token — 60 scans an hour, then `429 too_many` — on top of the API's own per-visitor limits. The payload carries no internal id, no email address and not the token itself.

## 8. Data

- `letter_documents`: the register. Reference, type and subtype, year and number, `legal_entity_id`, title, who it is addressed to, date, the template's `fields`, the rich-text `body`, status (`draft`, `issued`, `revoked`, `replaced`), `show_qr`, `copy_public`, `verify_token`, `issued_at/by`, `file_path`, `file_sha256`, `file_bytes`, `revoked_at/by/reason`, `replaced_by_id`, `replaces_id`, `ai_run_id`, `company_document_id`.
- `letter_signatures`: one per person. Name and role as printed, `kind` (drawn or uploaded), the JPEG as base64, **encrypted**.
- `letter_document_signatures`: who signed what, with the signature as it was applied, and when, and from which address.
- `letter_verification_hits`: scans per token, for the rate limit; older than a day, they are cleared as they are counted.

## 9. Files

| Part | Files |
|---|---|
| Rules | `app/Support/Letters/{Letters,Letterhead,LetterTypes,LetterBody,LetterPdf,DocxLetterhead,Signatures,Verification,Qr}.php` |
| Models | `app/Models/Letters/{LetterDocument,LetterSignature,LetterDocumentSignature}.php` |
| Screens | `app/Livewire/Hq/Letters/{DocumentRegister,DocumentPage,MySignature}.php` and `resources/views/livewire/hq/letters/*.blade.php` |
| The documents themselves | `resources/views/pdf/letter.blade.php`, `resources/views/pdf/letterhead-blank.blade.php` |
| Browser | `resources/letters/documents.js` (the signature pad and the body editor), served by `routes/letters.php` |
| Files handed over | `app/Http/Controllers/LetterheadController.php` |
| The API the site reads | `app/Http/Controllers/SiteApi/DocumentsController.php`, `routes/site-api.php` |
| Logo | `resources/brand/letterhead-logo.svg`, `resources/brand/jbs-praxis-horizontal-colour-1600.png` (Word only) |
| Access | `config/access.php` (`letters`), `database/migrations/2026_11_04_960001_grant_letters_permissions.php` |
| Claude | `config/ai.php` (`letter_draft`) |
| The site | `praxis_site`: `app/Http/Controllers/VerifyController.php`, `resources/views/pages/verify.blade.php`, `app/Support/HqApi.php` (`document`, `documentFile`), `lang/{en,fr}/site.php` (`site.verify`), the `.vf-` rules in `resources/css/app.css`, `enhanceVerify()` in `resources/js/app.js` |
| Tests | `tests/Feature/Letters/*`, `tests/Unit/Letters/QrTest.php`; `praxis_site/tests/Feature/VerifyDocumentTest.php` |

## 10. Running it locally

```
cd praxis_core
APP_ENV=localmysql SESSION_COOKIE=praxis_letters_session DB_DATABASE=praxis_letters MAIL_MAILER=array \
  APP_URL=http://hq.localhost:8125 php artisan serve --host=127.0.0.1 --port=8125

cd praxis_site     # SITE_API_BASE points at the HQ above, with the same SITE_API_SECRET
APP_URL=http://site.localhost:8126 php artisan serve --host=127.0.0.1 --port=8126
```

The tests need MySQL and a throwaway database (tests/Feature/UsesThrowawayDatabase.php):

```
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_DATABASE=praxis_test_letters \
  php vendor/bin/phpunit tests/Feature/Letters tests/Unit/Letters
```

## 11. Still to do

- The brand's own faces in the PDF: Newsreader and Plus Jakarta Sans exist only as woff2, which dompdf cannot read. Converting them to TTF and shipping them would replace DejaVu.
- The owner ticks `letters.*` for whichever other roles should have it, on Roles and permissions.
