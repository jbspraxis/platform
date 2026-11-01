<?php

namespace App\Support\Finance;

use App\Livewire\Hq\Finance\GeneralLedgerManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invoice #001, raised when the client signs (moved out of Portal\ProposalGateway for the SOW, PR 5; owner decision
 * 10: "deposit invoice raised automatically, as today"): the first share of the payment schedule (40_30_30 → 40%,
 * 100 → everything; "custom" reads the custom percentage) of the price before VAT, plus VAT at the agreed rate. Due in
 * 10 days, the exchange rate to the US dollar locked, the active remittance account, a SHA-256 fingerprint, one line,
 * and posted to the general ledger. Runs inside the caller's transaction.
 */
final class DepositInvoice
{
    /** The deposit's share of the price, in percent */
    public static function percent(string $schedule, $customPercent = null): float
    {
        return $schedule === 'custom' ? (float) $customPercent : (float) current(explode('_', $schedule));
    }

    /**
     * $terms: subtotal, vat_rate, currency, payment_schedule_type, custom_deposit_percent (optional), title, reference.
     * Returns the invoice's id, number and total.
     *
     * @return array{id: string, number: string, total: float}
     */
    public static function raise(string $projectId, object $organization, array $terms, ?string $createdBy, $timestamp): array
    {
        $depositPercentage = self::percent((string) $terms['payment_schedule_type'], $terms['custom_deposit_percent'] ?? null);
        $depositSubtotal = (float) $terms['subtotal'] * ($depositPercentage / 100);
        $depositVat = $terms['vat_rate'] ? ($depositSubtotal * ((float) $terms['vat_rate'] / 100)) : 0;
        $depositTotal = $depositSubtotal + $depositVat;

        $invoiceId = (string) Str::uuid();
        $invNumber = 'INV-' . date('Ym') . '-' . random_int(1000, 9999);

        // Auto-FX lock
        $fxRate = 1.000000;
        if ($terms['currency'] !== 'USD') {
            $fxRate = DB::table('mdm_exchange_rates')->where('currency_code', $terms['currency'])->value('rate_to_usd') ?? 1.000000;
        }

        $remittanceId = DB::table('mdm_remittance_accounts')->where('is_active', 1)->value('id');

        // SHA-256 fingerprint of the invoice
        $invoiceHash = 'SHA256-INV-' . strtoupper(hash('sha256', $invoiceId . $depositTotal . $timestamp));

        DB::table('invoices')->insert([
            'id' => $invoiceId,
            'project_id' => $projectId,
            'invoice_number' => $invNumber,
            'invoice_type' => 'Tax Invoice - Initial Deposit',
            'po_sow_reference' => $terms['reference'],
            'client_billing_address' => $organization->billing_address ?? 'TBD',
            'client_tax_id' => $organization->tax_identification_number ?? null,
            'amount_due' => $depositSubtotal,
            'vat_amount' => $depositVat,
            'wht_amount' => 0.00,
            'total_amount' => $depositTotal,
            'currency' => $terms['currency'],
            'exchange_rate_locked' => $fxRate,
            'status' => 'Pending',
            'due_date' => now()->addDays(10),
            'payment_terms' => 'Net 10',
            'remittance_account_id' => $remittanceId,
            'jbs_signature_hash' => $invoiceHash,
            'created_by' => $createdBy,
            'issued_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('invoice_lines')->insert([
            'invoice_id' => $invoiceId,
            'description' => "Deposit ({$depositPercentage}%): " . $terms['title'],
            'metric_of_billing' => 'Fixed Milestone',
            'quantity' => 1,
            'unit_price' => $depositSubtotal,
            'total_price' => $depositSubtotal,
        ]);

        // The IFRS general ledger
        GeneralLedgerManager::recordInvoiceIssued($invoiceId);

        return ['id' => $invoiceId, 'number' => $invNumber, 'total' => round($depositTotal, 2)];
    }
}
