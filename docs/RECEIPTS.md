# Receipts for client payments (13 November 2026)

Every verified payment gets a receipt: a numbered PDF on the letterhead of the company that billed the invoice, in the client's language (English or French), emailed to the client's billing contacts.

## Owner decisions (13 Nov 2026)

| Question | Decision |
|---|---|
| What the client receives | **A numbered receipt per payment** (RCT-202611-0001), not the invoice stamped Paid. |
| When it is emailed | **Automatically** as soon as a payment is recorded in HQ or a client's proof of payment is verified, **plus a button in HQ to send it again**. |

## How it works

- **Number**: `transactions.receipt_number`, given when the payment becomes Verified: `RCT-YYYYMM-NNNN`, in order within the month it was verified (Lagos time). Payments verified before receipts existed were numbered by the migration (`2026_11_13_950000_add_receipts_to_payments`) in the order they were verified; nobody was emailed about them.
- **When**: Finance › Invoices › *Record payment* (`InvoicePayments::recordFromHq`) and Finance › Payment verification › *Verify* (`EscrowVerification::approveTransaction`) both give the number inside their transaction and email the receipt once the response is out (`PaymentReceipts::sendAfterResponse`).
- **To whom**: the same people as the invoice: the organisation's active Client Portal accounts that see invoices (Client Admin, Client Finance) and may open the project (`InvoiceMailer::recipients`). Nobody there: no email; HQ says so when the payment is recorded.
- **The PDF** (`resources/views/pdf/receipt.blade.php`, `PaymentReceipts::render`): received from (the client, its address, company and tax numbers), the day the money arrived and the receipt's date, the invoice and project, the payment (method, reference), any withholding tax or write-off, and where the invoice stands after this payment (total, paid to date, balance; "fully paid" when settled). A receipt opened later still shows the balance as it was after that payment.
- **The email** (`App\Mail\ReceiptMail`, `lang/{en,fr}/emails/receipt.php`): the facts (receipt, invoice, amount, date, method, reference, what is left) with the PDF attached.
- **HQ**: Finance › Invoices › *Receipts* on any invoice with a payment: each receipt with *Download* and *Email receipt* / *Email again* (`finance.create`); "Emailed …" shows the last send (`transactions.receipt_emailed_at`). The flash after *Record payment* names the receipt and who it is going to.
- **Client Portal**: Invoices and payments lists each invoice's receipts with *Download receipt* (portal `billing.view`, own organisation, not another project).
- **Tests**: `tests/Feature/Finance/PaymentReceiptsTest.php`.
