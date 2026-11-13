<?php

namespace App\Support\Finance;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which invoices bill which milestones (owner, 10 Nov 2026: one invoice can bill several milestones, a line each).
 * A milestone is billed by an invoice that names it (invoices.phase_id: an invoice for exactly one milestone, as
 * before) or by one of an invoice's lines (invoice_lines.phase_id). "Live" leaves cancelled invoices out; deleted
 * invoices never count. Every screen that asks "is this milestone invoiced?" asks here.
 *
 * An advance on a milestone (Advances, 13 Nov 2026) is not billing it: advance lines and the "less advance" lines that
 * deduct them are left out, so the milestone is still invoiced in full later. An advance does keep the milestone from
 * deletion (any()).
 */
final class MilestoneInvoices
{
    /** [phase_id, invoice_id] pairs for these milestones */
    public static function pairs(iterable $phaseIds, bool $liveOnly = true): Collection
    {
        $ids = self::ids($phaseIds);
        if ($ids === []) {
            return collect();
        }
        $direct = DB::table('invoices')->whereIn('phase_id', $ids)->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('invoice_type')->orWhere('invoice_type', '!=', Advances::INVOICE_TYPE))
            ->when($liveOnly, fn ($q) => $q->where('status', '!=', 'Cancelled'))
            ->get(['phase_id', 'id as invoice_id']);
        $lines = DB::table('invoice_lines')->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->whereIn('invoice_lines.phase_id', $ids)->whereNull('invoices.deleted_at')
            ->whereNotIn('invoice_lines.kind', ['advance', 'less_advance'])
            ->when($liveOnly, fn ($q) => $q->where('invoices.status', '!=', 'Cancelled'))
            ->get(['invoice_lines.phase_id', 'invoices.id as invoice_id']);

        return $direct->concat($lines)->unique(fn ($p) => $p->phase_id . '|' . $p->invoice_id)->values();
    }

    /** The milestones among these that a live invoice already bills */
    public static function billed(iterable $phaseIds): array
    {
        return self::pairs($phaseIds)->pluck('phase_id')->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    /** Has any invoice, even a cancelled one, billed this milestone? (A billed milestone can't be deleted.) */
    public static function any(string $phaseId): bool
    {
        return self::pairs([$phaseId], false)->isNotEmpty()
            || DB::table('invoice_lines')->where('phase_id', $phaseId)->whereIn('kind', ['advance', 'less_advance'])->exists();
    }

    /** Each milestone's latest live invoice, keyed by milestone id (its status sets Invoiced or Paid on the board) */
    public static function latest(iterable $phaseIds): Collection
    {
        $pairs = self::pairs($phaseIds);
        if ($pairs->isEmpty()) {
            return collect();
        }
        $invoices = DB::table('invoices')->whereIn('id', $pairs->pluck('invoice_id')->unique()->values()->all())
            ->orderByDesc('issued_at')->orderByDesc('created_at')
            ->get(['id', 'phase_id', 'invoice_number', 'status', 'total_amount', 'currency', 'due_date', 'paid_at']);
        $rank = $invoices->pluck('id')->flip();
        $byId = $invoices->keyBy('id');

        return $pairs->sortBy(fn ($p) => $rank[$p->invoice_id] ?? PHP_INT_MAX)->groupBy(fn ($p) => (string) $p->phase_id)
            ->map(fn (Collection $group) => $byId->get($group->first()->invoice_id));
    }

    /** For each invoice, the numbers of the milestones it bills, in order, keyed by invoice id */
    public static function milestoneNumbers(iterable $invoiceIds): Collection
    {
        $ids = self::ids($invoiceIds);
        if ($ids === []) {
            return collect();
        }
        $direct = DB::table('invoices')->join('project_phases', 'project_phases.id', '=', 'invoices.phase_id')
            ->whereIn('invoices.id', $ids)->get(['invoices.id as invoice_id', 'project_phases.phase_number']);
        $lines = DB::table('invoice_lines')->join('project_phases', 'project_phases.id', '=', 'invoice_lines.phase_id')
            ->whereIn('invoice_lines.invoice_id', $ids)->get(['invoice_lines.invoice_id', 'project_phases.phase_number']);

        return $direct->concat($lines)->groupBy(fn ($r) => (string) $r->invoice_id)
            ->map(fn (Collection $rows) => $rows->pluck('phase_number')->map(fn ($n) => (int) $n)->unique()->sort()->values());
    }

    private static function ids(iterable $ids): array
    {
        return collect($ids)->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
    }
}
