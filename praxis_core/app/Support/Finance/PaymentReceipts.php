<?php

namespace App\Support\Finance;

use App\Mail\ReceiptMail;
use App\Support\MailLocale;
use App\Support\Opportunities\ProposalPdf;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use function Illuminate\Support\defer;

/**
 * A receipt for every verified payment (owner, 13 Nov 2026; docs/RECEIPTS.md): numbered RCT-202611-0001 (in order
 * within the month it was verified, Lagos time), a PDF on the letterhead of the company that billed the invoice, in
 * the client's language, and emailed to the client's billing contacts as soon as the payment is recorded in HQ or
 * the client's proof is verified. HQ can email it again (Finance › Invoices › Receipts); HQ and the portal download it.
 */
final class PaymentReceipts
{
    /**
     * Give a verified payment its receipt number (once; the same number after). Call inside the transaction that
     * verified it. Null when the payment isn't verified.
     */
    public static function number(string $transactionId): ?string
    {
        $tx = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first(['id', 'status', 'receipt_number', 'verified_at']);
        if (! $tx || $tx->status !== 'Verified') {
            return null;
        }
        if ($tx->receipt_number) {
            return $tx->receipt_number;
        }
        $prefix = 'RCT-' . Carbon::parse($tx->verified_at ?? now())->setTimezone('Africa/Lagos')->format('Ym') . '-';
        $last = (string) DB::table('transactions')->where('receipt_number', 'like', $prefix . '%')->max('receipt_number');
        $sequence = $last !== '' ? (int) substr($last, strlen($prefix)) : 0;
        for ($try = 1; $try <= 20; $try++) {
            $number = $prefix . str_pad((string) ($sequence + $try), 4, '0', STR_PAD_LEFT);
            try {
                DB::table('transactions')->where('id', $tx->id)->update(['receipt_number' => $number]);

                return $number;
            } catch (QueryException $e) {
                // Taken a moment ago by another payment verified at the same time: the next one
            }
        }

        throw new \RuntimeException('No free receipt number for ' . $prefix);
    }

    /** A verified payment with its invoice, project and organisation, or null */
    public static function find(string $transactionId): ?object
    {
        return DB::table('transactions')->join('invoices', 'invoices.id', '=', 'transactions.invoice_id')
            ->join('projects', 'projects.id', '=', 'invoices.project_id')
            ->join('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->where('transactions.id', $transactionId)->where('transactions.status', 'Verified')->whereNull('transactions.deleted_at')
            ->whereNotNull('transactions.receipt_number')
            ->select('transactions.*', 'invoices.invoice_number', 'invoices.currency', 'invoices.total_amount', 'invoices.project_id',
                'invoices.billing_entity_id', 'invoices.client_billing_address', 'invoices.client_tax_id', 'invoices.phase_id', 'invoices.invoice_type',
                'projects.title as project_title', 'projects.organization_id', 'organizations.name as organisation_name', 'organizations.preferred_language')
            ->first();
    }

    /** The verified payments of an invoice with their receipts, oldest first (HQ › Receipts, the portal) */
    public static function forInvoice(string $invoiceId)
    {
        return DB::table('transactions')->where('invoice_id', $invoiceId)->where('status', 'Verified')->whereNull('deleted_at')
            ->whereNotNull('receipt_number')
            ->orderByRaw('COALESCE(verified_at, created_at)')->orderBy('receipt_number')
            ->get(['id', 'receipt_number', 'amount_claimed', 'wht_deducted', 'tolerance_writeoff', 'method', 'tx_hash_or_ref', 'received_on', 'verified_at', 'created_at', 'receipt_emailed_at']);
    }

    /** "RCT-202611-0001.pdf" */
    public static function filename(object $receipt): string
    {
        return (preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $receipt->receipt_number) ?: 'receipt') . '.pdf';
    }

    /** The receipt as a PDF, in $language (default: the client's) */
    public static function render(object $receipt, ?string $language = null): string
    {
        [$html, $legal, $contact] = self::html($receipt, $language);
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        return \App\Support\Letters\LetterPdf::fromHtml($html, $legal, $contact, $cache, null, $language ?? MailLocale::forRecipient($receipt->preferred_language));
    }

    /** The page's HTML and the letterhead's footer lines (legal, contact) */
    public static function html(object $receipt, ?string $language = null): array
    {
        $language ??= MailLocale::forRecipient($receipt->preferred_language);
        $previous = App::getLocale();
        App::setLocale($language);
        try {
            $data = self::data($receipt, $language);

            return [view('pdf.receipt', $data)->render(), $data['letterhead']['footer_legal'], $data['letterhead']['footer_contact']];
        } finally {
            App::setLocale($previous);
        }
    }

    /** Settled on the invoice up to and including this payment (receipts sent again later show the balance as it was) */
    public static function settledUpTo(object $receipt): float
    {
        $at = $receipt->verified_at ?? $receipt->created_at;

        return round((float) DB::table('transactions')->where('invoice_id', $receipt->invoice_id)->where('status', 'Verified')->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereRaw('COALESCE(verified_at, created_at) < ?', [$at])
                ->orWhere(fn ($q) => $q->whereRaw('COALESCE(verified_at, created_at) = ?', [$at])->where('receipt_number', '<=', $receipt->receipt_number)))
            ->selectRaw('COALESCE(SUM(amount_claimed + COALESCE(wht_deducted, 0) + COALESCE(tolerance_writeoff, 0)), 0) as settled')->value('settled'), 2);
    }

    private static function data(object $receipt, string $language): array
    {
        $organisation = DB::table('organizations')->where('id', $receipt->organization_id)->first();
        $issuer = $receipt->billing_entity_id ? DB::table('mdm_legal_entities')->where('id', $receipt->billing_entity_id)->first() : Vat::issuer(null);
        $address = trim((string) $receipt->client_billing_address);
        $date = fn ($day) => $day ? Carbon::parse($day)->locale($language)->translatedFormat(__('proposals.formats.date')) : null;
        $settled = self::settledUpTo($receipt);

        return [
            'receipt' => $receipt,
            'language' => $language,
            'letterhead' => \App\Support\Letters\Letterhead::details($issuer ? (array) $issuer : []),
            'logo' => \App\Support\Letters\Letterhead::logoDataUri(),
            'issuer' => $issuer,
            'client' => $organisation,
            'clientAddress' => $address !== '' && $address !== 'TBD' ? $address : (trim((string) ($organisation->billing_address ?? '')) ?: null),
            'clientRegistration' => trim((string) ($organisation->registration_number ?? '')) ?: null,
            'clientTaxId' => trim((string) ($receipt->client_tax_id ?: ($organisation->tax_identification_number ?? ''))) ?: null,
            'phase' => $receipt->phase_id ? DB::table('project_phases')->where('id', $receipt->phase_id)->value('title') : null,
            'money' => fn ($amount) => ((float) $amount < 0 ? "\u{2212}" : '') . ProposalPdf::money(abs((float) $amount), (string) $receipt->currency, $language),
            'received' => $date($receipt->received_on ?: Carbon::parse($receipt->verified_at ?? $receipt->created_at)->setTimezone('Africa/Lagos')),
            'issued' => $date(Carbon::parse($receipt->verified_at ?? $receipt->created_at)->setTimezone('Africa/Lagos')),
            'method' => __('invoices.receipt.methods.' . $receipt->method) !== 'invoices.receipt.methods.' . $receipt->method ? __('invoices.receipt.methods.' . $receipt->method) : $receipt->method,
            'amount' => (float) $receipt->amount_claimed,
            'wht' => (float) ($receipt->wht_deducted ?? 0),
            'writeoff' => (float) ($receipt->tolerance_writeoff ?? 0),
            'settledTotal' => round((float) $receipt->amount_claimed + (float) ($receipt->wht_deducted ?? 0) + (float) ($receipt->tolerance_writeoff ?? 0), 2),
            'paidToDate' => $settled,
            'left' => max(0.0, round((float) $receipt->total_amount - $settled, 2)),
        ];
    }

    /**
     * Email the receipt with its PDF to the client's billing contacts (the invoice's: InvoiceMailer::recipients), in
     * the organisation's language. Returns the addresses reached; transactions.receipt_emailed_at records the last
     * time it reached at least one.
     *
     * @return list<string>
     */
    public static function send(string $transactionId): array
    {
        $receipt = self::find($transactionId);
        if (! $receipt) {
            return [];
        }
        $language = MailLocale::forRecipient($receipt->preferred_language);
        $pdf = self::render($receipt, $language);
        $settled = self::settledUpTo($receipt);
        $invoice = (object) ['id' => $receipt->invoice_id, 'project_id' => $receipt->project_id];

        $reached = [];
        foreach (InvoiceMailer::recipients($invoice) as $contact) {
            try {
                Mail::to($contact->email)->locale($language)->send(new ReceiptMail(
                    $receipt, $contact->first_name ?: null, max(0.0, round((float) $receipt->total_amount - $settled, 2)), $pdf, self::filename($receipt),
                ));
                $reached[] = $contact->email;
            } catch (\Throwable $e) {
                Log::error('Receipt email failed', ['transaction_id' => $transactionId, 'client_user_id' => $contact->id, 'message' => $e->getMessage()]);
            }
        }
        if ($reached) {
            DB::table('transactions')->where('id', $transactionId)->update(['receipt_emailed_at' => now()]);
        }

        return $reached;
    }

    /** send(), once the response is out (recording or verifying a payment shouldn't wait for the PDF and the mail server) */
    public static function sendAfterResponse(string $transactionId): void
    {
        defer(function () use ($transactionId) {
            try {
                self::send($transactionId);
            } catch (\Throwable $e) {
                Log::error('Receipt email not prepared', ['transaction_id' => $transactionId, 'message' => $e->getMessage()]);
            }
        }, always: true);
    }
}
