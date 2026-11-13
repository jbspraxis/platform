<?php

namespace App\Support\Finance;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What has been paid on an invoice, and recording a payment from HQ (owner, 13 Nov 2026).
 *
 * A payment is a `transactions` row. The client's proof from the portal waits as "Pending Verification" until Finance
 * verifies it (Payment verification); a payment Finance records itself (money seen in the bank, cash, a cheque) is
 * verified as it is recorded. Only verified payments count. An invoice is Paid once its verified payments, with any
 * withholding tax and write-off, reach its total; until then it stays Sent (or Overdue) and shows what is left.
 */
final class InvoicePayments
{
    /** Ways money arrives (transactions.method) */
    public const METHODS = ['Bank Transfer', 'Cash', 'Cheque', 'Paystack', 'USDT', 'Campay', 'Other'];

    /** Settled so far on each invoice (verified payments + WHT + write-off), keyed by invoice id */
    public static function settled(iterable $invoiceIds): Collection
    {
        $ids = collect($invoiceIds)->map(fn ($id) => (string) $id)->filter()->unique()->values()->all();
        if ($ids === []) {
            return collect();
        }

        return DB::table('transactions')->whereIn('invoice_id', $ids)->where('status', 'Verified')->whereNull('deleted_at')
            ->groupBy('invoice_id')
            ->selectRaw('invoice_id, COALESCE(SUM(amount_claimed + COALESCE(wht_deducted, 0) + COALESCE(tolerance_writeoff, 0)), 0) as settled')
            ->pluck('settled', 'invoice_id')->map(fn ($v) => round((float) $v, 2));
    }

    /** Left to pay on this invoice */
    public static function balance(object $invoice): float
    {
        return max(0.0, round((float) $invoice->total_amount - (float) (self::settled([$invoice->id])->get($invoice->id) ?? 0), 2));
    }

    /**
     * After a payment is verified (HQ or portal): the invoice is Paid once settled, else it stays unpaid, its due date
     * optionally pushed back. Call inside the transaction that holds the invoice row locked.
     */
    public static function settle(object $invoice, int $extendDays = 0): bool
    {
        $verified = DB::table('transactions')->where('invoice_id', $invoice->id)->where('status', 'Verified')->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(amount_claimed + COALESCE(wht_deducted, 0) + COALESCE(tolerance_writeoff, 0)), 0) as settled, COALESCE(SUM(wht_deducted), 0) as wht')
            ->first();

        if (round((float) $verified->settled, 2) >= round((float) $invoice->total_amount, 2)) {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'status' => 'Paid',
                'wht_amount' => $verified->wht,
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        }

        $update = ['wht_amount' => $verified->wht, 'updated_at' => now()];
        if ($extendDays > 0) {
            $update['status'] = 'Pending'; // restores portal access if it was overdue
            $update['due_date'] = Carbon::parse($invoice->due_date)->addDays($extendDays);
        }
        DB::table('invoices')->where('id', $invoice->id)->update($update);

        return false;
    }

    /**
     * Finance records money that arrived: verified at once, settles the invoice, posts the receipt to the general
     * ledger and leaves an audit line. $data: amount, received_on (Y-m-d), method, reference, wht, note.
     *
     * @return array{paid: bool, transaction_id: string, balance: float, receipt: ?string}
     */
    public static function recordFromHq(string $invoiceId, array $data, string $userId): array
    {
        $result = DB::transaction(function () use ($invoiceId, $data, $userId) {
            $invoice = DB::table('invoices')->where('id', $invoiceId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $invoice || ! in_array($invoice->status, ['Pending', 'Overdue'], true)) {
                throw new \DomainException($invoice && $invoice->status === 'Paid'
                    ? "Invoice {$invoice->invoice_number} is already paid."
                    : 'Only a sent or overdue invoice can take a payment.');
            }
            $amount = round((float) $data['amount'], 2);
            $wht = round((float) ($data['wht'] ?? 0), 2);
            $left = self::balance($invoice);
            if ($amount + $wht > $left + 0.004) {
                throw new \DomainException('That is more than the ' . \App\Livewire\Hq\HqMoney::format($invoice->currency, $left) . " left on {$invoice->invoice_number}.");
            }

            $txId = (string) Str::uuid();
            DB::table('transactions')->insert([
                'id' => $txId,
                'invoice_id' => $invoice->id,
                'source' => 'hq',
                'received_on' => $data['received_on'],
                'method' => $data['method'],
                'tx_hash_or_ref' => Str::limit(trim((string) $data['reference']), 250, ''),
                'note' => filled($data['note'] ?? null) ? Str::limit(trim((string) $data['note']), 250, '') : null,
                'amount_claimed' => $amount,
                'wht_deducted' => $wht,
                'tolerance_writeoff' => 0,
                'status' => 'Verified',
                'verified_by' => $userId,
                'verified_at' => now(),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paid = self::settle($invoice);
            $receipt = PaymentReceipts::number($txId); // docs/RECEIPTS.md

            DB::table('system_audit_logs')->insert([
                'user_id' => $userId,
                'action' => 'PAYMENT_RECORDED',
                'description' => "Recorded a payment of {$invoice->currency} " . number_format($amount, 2) . " ({$data['method']}, ref {$data['reference']}, received {$data['received_on']}) on invoice {$invoice->invoice_number}.",
                'target_table' => 'transactions',
                'target_id' => $txId,
                'new_values' => json_encode(['amount' => $amount, 'wht' => $wht, 'invoice_paid' => $paid]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            \App\Livewire\Hq\Finance\GeneralLedgerManager::recordInvoicePaid($txId);

            $fresh = DB::table('invoices')->where('id', $invoice->id)->first();

            return ['paid' => $paid, 'transaction_id' => $txId, 'balance' => self::balance($fresh), 'receipt' => $receipt];
        });

        \App\Support\Pwa\ClientNotify::invoice($invoiceId, 'payment_verified');
        PaymentReceipts::sendAfterResponse($result['transaction_id']);

        return $result;
    }

    /** Has any verified payment landed on this invoice? (Then it can't be cancelled.) */
    public static function hasPayments(string $invoiceId): bool
    {
        return DB::table('transactions')->where('invoice_id', $invoiceId)->where('status', 'Verified')->whereNull('deleted_at')->exists();
    }
}
