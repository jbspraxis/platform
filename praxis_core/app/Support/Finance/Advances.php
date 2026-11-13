<?php

namespace App\Support\Finance;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Advances on a milestone (owner, 13 Nov 2026: Tales pays ₦300,000 of M1's ₦900,000 before M1 is accepted).
 *
 * An advance invoice bills part of one milestone ahead of time: one line, kind 'advance', naming the milestone. It
 * does not count as billing the milestone (MilestoneInvoices leaves it out), so the milestone is still invoiced in
 * full later, and then every live advance on it is taken off that invoice automatically: a 'less_advance' line per
 * advance, naming the advance invoice, never typed by hand. Once deducted by a live invoice an advance is used up; if
 * that invoice is cancelled the advance is free again. An advance that a live invoice has deducted can't be
 * cancelled (the client would be billed twice).
 */
final class Advances
{
    public const INVOICE_TYPE = 'Advance Invoice';

    /**
     * Live advance invoices on these milestones not yet deducted by a live invoice, oldest first:
     * [{invoice_id, invoice_number, phase_id, amount (before VAT), status}].
     */
    public static function open(iterable $phaseIds): Collection
    {
        $ids = collect($phaseIds)->map(fn ($id) => (string) $id)->filter()->unique()->values()->all();
        if ($ids === []) {
            return collect();
        }

        return DB::table('invoice_lines')->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoice_lines.kind', 'advance')->whereIn('invoice_lines.phase_id', $ids)
            ->whereNull('invoices.deleted_at')->where('invoices.status', '!=', 'Cancelled')
            ->whereNotExists(fn ($q) => $q->from('invoice_lines as used')->join('invoices as by', 'by.id', '=', 'used.invoice_id')
                ->whereColumn('used.advance_invoice_id', 'invoices.id')->where('used.kind', 'less_advance')
                ->whereNull('by.deleted_at')->where('by.status', '!=', 'Cancelled'))
            ->orderBy('invoices.issued_at')->orderBy('invoices.created_at')
            ->get(['invoices.id as invoice_id', 'invoices.invoice_number', 'invoices.status', 'invoice_lines.phase_id', 'invoice_lines.total_price as amount'])
            ->map(function ($row) {
                $row->amount = round((float) $row->amount, 2);

                return $row;
            });
    }

    /** The deduction lines for an invoice that bills these milestones: one per open advance (amounts negative) */
    public static function deductionLines(iterable $phaseIds, ?callable $label = null): array
    {
        $label ??= fn ($advance) => "Less advance {$advance->invoice_number}";

        return self::open($phaseIds)->map(fn ($advance) => [
            'kind' => 'less_advance',
            'phase_id' => (string) $advance->phase_id,
            'advance_invoice_id' => (string) $advance->invoice_id,
            'description' => $label($advance),
            'amount' => -$advance->amount,
        ])->values()->all();
    }

    /** Everything already advanced on a milestone (live advances, deducted or not) */
    public static function advancedOn(string $phaseId): float
    {
        return round((float) DB::table('invoice_lines')->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoice_lines.kind', 'advance')->where('invoice_lines.phase_id', $phaseId)
            ->whereNull('invoices.deleted_at')->where('invoices.status', '!=', 'Cancelled')
            ->sum('invoice_lines.total_price'), 2);
    }

    /** The live invoice that deducted this advance, if any (then the advance can't be cancelled) */
    public static function deductedBy(string $advanceInvoiceId): ?object
    {
        return DB::table('invoice_lines')->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoice_lines.kind', 'less_advance')->where('invoice_lines.advance_invoice_id', $advanceInvoiceId)
            ->whereNull('invoices.deleted_at')->where('invoices.status', '!=', 'Cancelled')
            ->first(['invoices.id', 'invoices.invoice_number']);
    }
}
