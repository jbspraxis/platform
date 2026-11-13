# Clients

HQ › **Clients** lists the organisations JBS Praxis works for, and each client's page holds the details its invoices print. It also lets you give a client's people access to the Client Portal. Built on 13 Nov 2026, after the owner said "I don't even know where we have the client details": until then they could only be changed under Leads › a lead › Edit details.

## 1. The owner's decisions (13 Nov 2026)

1. **Its own menu section**, right after "Leads and proposals". On phones it is under More.
2. **Active and past clients.**
   - The list opens on active clients. *Past* and *All* are filters.
   - Before this page, past clients could not be opened anywhere in HQ, because the lead page opens only leads and active clients (`LeadAccess::STATUSES`).
3. **Sales and Finance change the invoice details.** That means anyone with `crm.edit` or `finance.edit`. Finance makes the invoices, so it can fix what prints on them.
4. **Columns:**
   - main contact, and how many contacts;
   - projects (running, and in all);
   - unpaid balance per currency, shown only to people with `finance.view`;
   - which invoice details are missing.
5. **"We should create accounts for their portal directly there."** Each contact on a client's page can be invited to the Client Portal (section 4).

## 2. Who may do what (`App\Support\Clients\ClientAccess`)

| Action | Needs |
|---|---|
| Open the list and a client's page | `crm.view` or `finance.view` |
| Change the invoice details, add a person | `crm.edit` or `finance.edit` |
| See what is owed (Unpaid column, Unpaid invoices card) | `finance.view` |
| Invite a contact to the Client Portal | `access.create` (as on Settings and access › Accounts) |
| Send an invitation again | `access.create` or `access.edit` |

Hiding a button is not the check: every Livewire action asks `ClientAccess` again. The routes (`hq.clients`, `hq.client`) sit in the staff group without a `can:` middleware, because opening them needs either of two permissions. Each screen's `mount()` checks instead.

## 3. Invoice details

A client is an `organizations` row with status `Active Client` or `Past Client` (`Clients::find`). The page edits the same columns as the lead page:

- name, address (`billing_address`), company number (`registration_number`), tax number (`tax_identification_number`), email, phone;
- country, city, and the language for invoices and emails (`preferred_language`).

Saving goes through `Leads::updateDetails`. So the rules, the lead timeline line ("Details changed: …") and the audit line (`LEAD_UPDATED`) are the lead page's.

Two cases need care:

- **No industry.** If an older client has none, the form asks for it, because saving needs one. It is not printed on invoices.
- **No source.** If an older client has none, the page sends `manual`, as the lead page does.

**What the invoice PDF prints** (`InvoicePdf::html`, "Billed to" in `resources/views/pdf/invoice.blade.php`):

- **Company number, email and phone:** always today's values from the organisation.
- **Address and tax number:** the values copied onto the invoice when it was issued (`client_billing_address`, `client_tax_id`). If the invoice was issued without them, it shows today's.

The page says this under the card's title. `Clients::missing()` names the empty fields that print. The list and the page both show them as a warning.

## 4. Client Portal invitations (`App\Support\Clients\PortalInvites`)

- **No second account.** The contact's own `client_users` row becomes the portal account, so an email address still belongs to one person in HQ.
- **What an invitation sets:**
  - `invitation_token`: 60 characters;
  - `lifecycle_stage`: Onboarding;
  - `status`: Active;
  - `password`: none until the person opens the link.
- **The email** is the same `UserInvitation` email Accounts sends. It goes in the organisation's language, and the setup link is `/setup/{token}` on the portal.
- **Roles:**
  - The organisation's first portal account becomes its primary contact (Client Admin, `is_primary_client`), as on Accounts.
  - The others get the client role chosen. The default is Client Member.
- **States shown on each contact:**
  - *No portal account*
  - *Invited*: no password yet. *Send invitation again* makes a new link, and the old one stops working.
  - *Has access*
  - *Suspended*
- **Adding a person.** *Add a person* adds a contact through `Contacts::add`. It can invite them in the same step; the email is then required.
- **Records.** Every invitation writes:
  - a lead timeline line `portal_invited`;
  - an audit line, `CLIENT_ACCOUNT_INVITED` or `CLIENT_INVITATION_RESENT`.
- **If the email fails:** the account is ready, the page says so, and *Send invitation again* retries. The link is never written to the log.
- **What stays on Accounts:** suspending and removing a portal account. The page links there with *Manage portal accounts*.
- **Contact details.** A contact's name, email and phone are changed on the lead page, which only active clients have.

## 5. Unpaid balances (`Clients::unpaid`, `Clients::unpaidInvoices`)

- **Which invoices count:** the client's projects' invoices with status Pending or Overdue, not deleted.
- **What is owed on each:** the total less what `InvoicePayments::settled` counts (verified payments, withholding tax, write-off). Fully paid ones are left out.
- **How it shows:** amounts per currency, never added across currencies. The page's card lists each invoice, with a link to its PDF.

## 6. Files and tests

- **Screens:** `app/Livewire/Hq/Clients/ClientList.php`, `ClientPage.php`, and their views in `resources/views/livewire/hq/clients/`.
- **Logic:** `app/Support/Clients/` (`ClientAccess`, `Clients`, `PortalInvites`).
- **Words:** `lang/en/clients.php` and `lang/fr/clients.php`, with the same keys.
- **Menu:** `HqNav` section `clients`, icon `building-2`.
- **Lead page:** an active client's lead page links to its client page (*Open client page*).
- **Tests:** `tests/Feature/Clients/ClientsTest.php`, on MySQL (`UsesThrowawayDatabase`). They cover:
  - the menu and who opens the pages;
  - the list's columns and filters;
  - Sales and Finance saving, with the invoice PDF reading the result;
  - a past client without an industry;
  - invitations: primary, role, twice, no email, resend;
  - adding a person and inviting them at once;
  - EN/FR key parity.
