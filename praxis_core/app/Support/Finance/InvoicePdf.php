<?php

namespace App\Support\Finance;

use App\Support\Opportunities\ProposalPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * An invoice as a PDF (owner, 2 Nov 2026; docs/QA-PORTAL.md D11): emailed to the client's billing contacts when it is
 * issued (InvoiceMailer), downloaded in HQ › Finance › Invoices and in the portal's Invoices and payments.
 * resources/views/pdf/invoice.blade.php on the company letterhead (owner, 13 Nov 2026), laid out to fit one page:
 * the Documents letterhead's header, and its footer drawn on every page by LetterPdf::fromHtml() (dompdf, nothing
 * remote). "Tax invoice" only when it carries VAT, else "Invoice"; the company that bills it with its tax number, the
 * client with theirs, the lines, and the accounts to pay into (ReceivingAccounts: its currency, that company's).
 */
final class InvoicePdf
{
    public static function render(object $invoice, ?string $language = null): string
    {
        $language ??= self::language($invoice);
        [$html, $footerLegal, $footerContact] = self::html($invoice, $language);
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        return \App\Support\Letters\LetterPdf::fromHtml($html, $footerLegal, $footerContact, $cache, null, $language);
    }

    /** The page's HTML and the letterhead's two footer lines (legal, contact), in $language (default: the client's) */
    public static function html(object $invoice, ?string $language = null): array
    {
        $language ??= self::language($invoice);
        $previous = App::getLocale();
        App::setLocale($language);
        try {
            $data = self::data($invoice, $language);

            return [
                view('pdf.invoice', $data)->render(),
                $data['letterhead']['footer_legal'],
                $data['letterhead']['footer_contact'],
            ];
        } finally {
            App::setLocale($previous);
        }
    }

    /** The client organisation's language: French when it prefers French, else English */
    public static function language(object $invoice): string
    {
        $preference = DB::table('projects')->join('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->where('projects.id', $invoice->project_id)->value('organizations.preferred_language');

        return \App\Support\MailLocale::forRecipient($preference);
    }

    /** "INV-202611-4032.pdf" */
    public static function filename(object $invoice): string
    {
        return (preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $invoice->invoice_number) ?: 'invoice') . '.pdf';
    }

    /** Tax invoice only when it carries VAT (owner, D11) */
    public static function title(object $invoice): string
    {
        return Vat::applied($invoice->vat_applied ?? null, $invoice->vat_rate ?? null) ? __('invoices.pdf.tax_invoice') : __('invoices.pdf.invoice');
    }

    private static function data(object $invoice, string $language): array
    {
        $project = DB::table('projects')->where('id', $invoice->project_id)->first();
        $organisation = $project ? DB::table('organizations')->where('id', $project->organization_id)->first() : null;
        // The company recorded on the invoice, even if it has been deactivated since; none recorded: Nigeria's
        $issuer = $invoice->billing_entity_id ? DB::table('mdm_legal_entities')->where('id', $invoice->billing_entity_id)->first() : Vat::issuer(null);
        $vat = Vat::recorded($invoice->vat_applied ?? null, $invoice->vat_rate ?? null, $issuer?->id, $organisation, $issuer);
        $lines = DB::table('invoice_lines')->where('invoice_id', $invoice->id)->orderBy('id')->get();
        if ($lines->isEmpty()) {
            $lines = collect([(object) ['description' => (string) $invoice->invoice_type, 'quantity' => 1, 'unit_price' => $invoice->amount_due, 'total_price' => $invoice->amount_due]]);
        }
        // Only the account the invoice names; none named (or no longer usable): every one that matches it (ReceivingAccounts)
        $accounts = ReceivingAccounts::forInvoice($invoice, $issuer?->id);
        $address = trim((string) $invoice->client_billing_address);
        $date = fn ($day) => $day ? Carbon::parse($day)->locale($language)->translatedFormat(__('proposals.formats.date')) : null;

        return [
            'invoice' => $invoice,
            'language' => $language,
            // The company letterhead (App\Support\Letters\Letterhead): header details, footer lines, the logo
            'letterhead' => \App\Support\Letters\Letterhead::details($issuer ? (array) $issuer : []),
            'logo' => \App\Support\Letters\Letterhead::logoDataUri(),
            'title' => self::title($invoice),
            'issuer' => $issuer,
            'client' => $organisation,
            // The address as it was when the invoice was issued; an older invoice issued without one shows today's
            'clientAddress' => $address !== '' && $address !== 'TBD' ? $address : (trim((string) ($organisation->billing_address ?? '')) ?: null),
            'clientRegistration' => trim((string) ($organisation->registration_number ?? '')) ?: null,
            'clientEmail' => trim((string) ($organisation->email ?? '')) ?: null,
            'clientPhone' => trim((string) ($organisation->phone ?? '')) ?: null,
            'clientTaxId' => trim((string) ($invoice->client_tax_id ?: ($organisation->tax_identification_number ?? ''))) ?: null,
            'project' => $project,
            'phase' => $invoice->phase_id ? DB::table('project_phases')->where('id', $invoice->phase_id)->value('title') : null,
            'lines' => $lines,
            'vat' => $vat,
            'vatNote' => Vat::note($vat),
            'accounts' => $accounts,
            // A discount, an advance taken off or a payment reads "−NGN 100,000.00"
            'money' => fn ($amount) => ((float) $amount < 0 ? "\u{2212}" : '') . ProposalPdf::money(abs((float) $amount), (string) $invoice->currency, $language),
            'paidSoFar' => (float) (InvoicePayments::settled([$invoice->id])->get($invoice->id) ?? 0),
            'issued' => $date($invoice->issued_at ? Carbon::parse($invoice->issued_at)->setTimezone('Africa/Lagos') : $invoice->created_at),
            'due' => $date($invoice->due_date),
            'paid' => $invoice->status === 'Paid' ? $date($invoice->paid_at ? Carbon::parse($invoice->paid_at)->setTimezone('Africa/Lagos') : null) ?? '' : null,
        ];
    }
}
