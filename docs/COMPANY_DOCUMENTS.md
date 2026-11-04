# Company documents

HQ › Master data › Company documents keeps each JBS Praxis company's administrative papers (certificate of incorporation, memorandum and articles, CAC status report, tax and bank papers…) with their renewal dates, and each company's compliance calendar. Claude reads uploaded certificates and suggests company details; a person checks them and applies what is right. Built 4 Nov 2026 from the owner's decisions below.

## 1. The owner's decisions (4 Nov 2026)

1. **Where.** Master data, per company: Company documents sits next to Legal entities, filtered by company (each legal entity, Nigeria's first, and the head office). Each company's row on Legal entities links to its documents ("Documents (n)").
2. **Who.** Anyone with Master data access. `mdm.view` sees and downloads; `mdm.create` uploads and adds deadlines; `mdm.edit` replaces files, edits details, marks deadlines done and applies suggested details; `mdm.delete` archives (and restores). Archive is soft: nothing is ever deleted. Every view and download is a line in `system_audit_logs`.
3. **Dates and the compliance calendar.** A document has an issue date and an optional expiry or renewal date, with a bell 30 days before (changeable per document, or none). Each company has a calendar of deadlines (title, due date, once or every year, bell days, done with who and when). `mdm:compliance-bell` rings on working days at 07:30 Lagos time, once per item and date, to the owner and everyone with `mdm.edit`. Expired and due-soon items come first in the lists and are counted at the top.
4. **Claude suggests, a person decides.** For a certificate of incorporation, memorandum and articles, status report or tax certificate, Claude copies the company name, RC number, TIN, date of registration, company type, registered address, share capital, the document's own dates and any deadlines. The "Suggested details" panel shows HQ's value next to the document's, each editable with a tick box. **Nothing changes until someone clicks Apply ticked details. Where the document disagrees with HQ, HQ keeps its value unless the row is ticked**, and the panel says so. (The CAC documents first issued carry the lawyer's typo in the registered address; HQ keeps the correct one.)

## 2. Screens

| Screen | What it does |
|---|---|
| Company documents (`/mdm/company-documents`) | Filter by company (a select on phones). Documents tab: title, kind, company, version, issue and expiry dates, status (Expired, Expires in n days, Valid, No expiry date), View and Download. Show archived. Compliance calendar tab: open deadlines (Overdue, Due in n days, Upcoming) with Mark done, Edit, Archive; recently done below. Upload a document and Add a deadline open dialogs. |
| A document (`/mdm/company-documents/{id}`) | Suggested details (while Claude reads, the page checks every 3 seconds), Details (Edit details), Versions (each with who and when, View and Download, and whether its details were applied), Replace file, Archive document / Restore document. |
| Legal entities | New optional fields: date of registration, company type, share capital. Each company links to its documents. |

Files: PDF, JPG, PNG or Word (.docx), up to 20 MB. Livewire's temporary upload limit is raised from 12 to 20 MB for every screen (`AppServiceProvider`); each screen keeps its own limit. PDFs and images open in the browser; Word files and every Download come as attachments.

## 3. Data

- `company_documents`: one paper of one company (`legal_entity_id` null = head office, group-wide), kind, title, dates, `remind_days`, `reminded_for` (the expiry date the bell last rang for), `current_version_id`, `archived_at/by`.
- `company_document_versions`: every file: number, private path, original name, type, size, sha256, who uploaded it, `ai_run_id` (Claude's reading), `read_error`, `applied` (what was applied, field => [before, after]) and `reviewed_at/by`.
- `company_deadlines`: the calendar: title, `due_on`, `repeats` (once, yearly), `remind_days`, `reminded_for`, `document_id` (the document it came from), `done_at/by`, `archived_at/by`. Marking a yearly one done adds next year's.
- `mdm_legal_entities`: `incorporated_on`, `company_type`, `share_capital` added.
- Files: private disk only, `storage/app/private/company-documents/<legal entity id or group>/<document id>/v<n>-<random>.<ext>`, served by `CompanyDocumentController` (route needs `mdm.view`, version must belong to the document).

Audit actions: `COMPANY_DOCUMENT_UPLOADED`, `_REPLACED`, `_EDITED`, `_ARCHIVED`, `_RESTORED`, `_VIEWED`, `_DOWNLOADED`, `_DETAILS_APPLIED`, `_DETAILS_SET_ASIDE`; `COMPANY_DEADLINE_ADDED`, `_EDITED`, `_DONE`, `_ARCHIVED`. Apply also writes `LEGAL_ENTITY_UPDATED` and `WEBSITE_SETTINGS_UPDATED` through those contracts.

## 4. How Claude reads a document

- Purpose `company_document_read` (`config/ai.php`): Claude Haiku, low effort, on the `ai` queue (run every minute by the scheduler). Roughly $0.001 a page; the monthly AI budget counts 3,000 tokens a PDF page and 5,000 an image before a run starts.
- The server has no PDF tools and needs none: `AiRequest` now carries attachments, and `ClaudeProvider` sends a PDF as a base64 `document` block and a JPG or PNG as an `image` block (the official SDK supports both). Only the file's path is stored in `ai_runs.input`; the file is read when the request is made. A Word file goes as its text (read with the plain-PHP zip reader). Images over 5 MB are not sent (Claude's limit).
- When Claude can't read the file (no key, budget spent, busy, a large photo, a Word file without text), the panel says why and offers **Type the details** (the same panel, empty) and **Ask Claude to read it again**.
- Apply goes through the existing contracts: `App\Support\Mdm\LegalEntities::update` (the same rules as the Legal entities form), `WebsiteSettings::set` (the legal name on jbspraxis.com for Nigeria's company, and the registration row `{country, label 'RC' (NG) or 'RCCM' (CM), number, status 'registered'}`), and `Calendar::add` for ticked deadlines. Website rows need `website.edit` as well; without it they show locked.
- Default ticks: a value HQ doesn't have yet is ticked; the same value is left alone; a different value is not ticked; a value the document doesn't give can't be applied empty.

## 5. Files

`app/Support/CompanyDocuments/` (Companies, Documents, Reader, Suggestions, Calendar, Reminders, CannotRead), `app/Support/Mdm/LegalEntities.php`, `app/Models/CompanyDocument*.php`, `app/Models/CompanyDeadline.php`, `app/Livewire/Hq/Mdm/CompanyDocuments.php` and `CompanyDocumentPage.php` with their views, `app/Http/Controllers/CompanyDocumentController.php`, `app/Console/Commands/ComplianceBell.php`, migration `2026_11_04_950000_create_company_documents.php`. Shared files touched: `routes/web.php`, `routes/console.php`, `config/ai.php`, `lang/*/ai.php`, `lang/en/validation.php` (the 20 MB message), `AppServiceProvider`, the Ai layer (`Ai`, `AiRequest`, `Budget`, `ClaudeProvider`, `GeminiProvider`), Legal entities, the HQ rail.

Tests: `tests/Feature/Mdm/` (CompanyDocumentsTest, CompanyDocumentSuggestionsTest, ComplianceCalendarTest), all with FakeAi and fictional `.test` data.

## 6. After it is live

1. Upload the CAC certificate, the status report and the memorandum and articles yourself (nobody else uploads the real files). Check each panel, tick only what is right, and apply. The registered address on those documents is the typo: leave it unticked.
2. Add the deadlines the documents don't state (CAC annual return, tax filings) in the Compliance calendar.
3. If uploads over a few MB fail on the live server, raise PHP's `upload_max_filesize` and `post_max_size` to at least 25M in cPanel › MultiPHP INI Editor.
