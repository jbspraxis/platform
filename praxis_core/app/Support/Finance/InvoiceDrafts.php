<?php

namespace App\Support\Finance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invoice drafts (owner, 25 Nov 2026: "Do not send any yet. I am going to review and send."; docs/PROJECTS.md §9).
 *
 * Create invoice › Save as draft keeps what the form was filled with in invoice_drafts, never in invoices: so a draft
 * is not in the client's portal, sends no email or alert, posts nothing to the general ledger and counts in no total
 * or reminder, whatever reads invoices. Finance › Invoices › Drafts lists them, shows each one's PDF marked DRAFT
 * (preview()), and Issue and send runs it through the usual Create invoice path, which emails and notifies as ever.
 *
 * A draft keeps its milestones: Create invoice doesn't offer them to another invoice while it is live (held()). It
 * keeps an invoice number too, used when it is issued if still free.
 */
final class InvoiceDrafts
{
    /** A live draft (not issued, not discarded) */
    public static function find(string $id): ?object
    {
        return DB::table('invoice_drafts')->where('id', $id)->whereNull('deleted_at')->first();
    }

    /** The lines as typed: [['kind', 'phase_id', 'description', 'amount'], …] */
    public static function lines(object $draft): array
    {
        $lines = json_decode((string) $draft->lines, true);

        return is_array($lines) ? array_values(array_filter($lines, 'is_array')) : [];
    }

    /** The milestones a draft's lines name (an advance draft's milestone isn't billed by it, as with Advances) */
    public static function milestones(object $draft): array
    {
        if ($draft->kind === 'advance') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(fn ($l) => isset($l['phase_id']) && $l['phase_id'] !== '' ? (string) $l['phase_id'] : null, self::lines($draft)))));
    }

    /** Of these milestones, the ones a live draft holds (other than $except, the draft being edited) */
    public static function held(iterable $phaseIds, ?string $except = null): array
    {
        $ids = collect($phaseIds)->map(fn ($id) => (string) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $projects = DB::table('project_phases')->whereIn('id', $ids->all())->distinct()->pluck('project_id')->all();

        return DB::table('invoice_drafts')->whereIn('project_id', $projects)->whereNull('deleted_at')
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->get()->flatMap(fn ($d) => self::milestones($d))
            ->intersect($ids)->unique()->values()->all();
    }

    /** A free invoice number for this month: not on an invoice, not kept by a live draft */
    public static function freshNumber(): string
    {
        do {
            $number = 'INV-' . date('Ym') . '-' . random_int(1000, 9999);
        } while (DB::table('invoices')->where('invoice_number', $number)->exists()
            || DB::table('invoice_drafts')->where('invoice_number', $number)->whereNull('deleted_at')->exists());

        return $number;
    }

    /** The due date for these terms, from $from: "Due on Receipt" is that day, "Net 10" ten days on (else 10) */
    public static function dueDate(string $terms, ?Carbon $from = null): Carbon
    {
        $from ??= now();
        if (stripos($terms, 'receipt') !== false) {
            return $from->copy();
        }
        $days = (int) filter_var($terms, FILTER_SANITIZE_NUMBER_INT);

        return $from->copy()->addDays($days > 0 ? $days : 10);
    }

    /** A line's signed amount: a discount counts against the total */
    public static function signed(array $line): float
    {
        $amount = round(abs((float) ($line['amount'] ?? 0)), 2);

        return ($line['kind'] ?? 'charge') === 'discount' ? -$amount : $amount;
    }

    /** The invoices table's columns, read once per request */
    private static function invoiceColumns(): array
    {
        static $columns = null;

        return $columns ??= \Illuminate\Support\Facades\Schema::getColumnListing('invoices');
    }

    /**
     * The invoice the draft would be if it were issued now, for its PDF and the Drafts list: [invoice, lines]. The
     * advances open on its milestones today come off, VAT as the billing company charges it today, due from today.
     *
     * @return array{0: object, 1: Collection}
     */
    public static function preview(object $draft): array
    {
        if ($draft->kind === 'advance') {
            $lines = [['kind' => 'advance', 'phase_id' => $draft->advance_phase_id, 'advance_invoice_id' => null,
                'description' => (string) $draft->advance_description, 'amount_signed' => round((float) $draft->advance_amount, 2)]];
        } else {
            $lines = array_map(fn ($l) => [
                'kind' => ($l['kind'] ?? 'charge') === 'discount' ? 'discount' : 'charge',
                'phase_id' => ($l['phase_id'] ?? null) ?: null,
                'advance_invoice_id' => null,
                'description' => trim((string) ($l['description'] ?? '')),
                'amount_signed' => self::signed($l),
            ], self::lines($draft));
            foreach (Advances::deductionLines(self::milestones($draft), fn ($advance) => Str::limit(__('invoices.lines.less_advance', ['number' => $advance->invoice_number]), 250)) as $d) {
                $lines[] = $d + ['amount_signed' => round((float) $d['amount'], 2)];
            }
        }
        $amountDue = round(array_sum(array_map(fn ($l) => round((float) $l['amount_signed'], 2), $lines)), 2);
        $project = DB::table('projects')->where('id', $draft->project_id)->first();
        $org = $project ? DB::table('organizations')->where('id', $project->organization_id)->first() : null;
        $vat = Vat::for($org, null, Vat::projectEntity((string) $draft->project_id));
        $vatAmount = $vat['applies'] ? round($amountDue * $vat['rate'] / 100, 2) : 0.0;
        $milestones = self::milestones($draft);
        $now = now();

        // Every column an invoice has (what the PDF may read), then this draft's values
        $invoice = (object) array_merge(array_fill_keys(self::invoiceColumns(), null), [
            'id' => $draft->id,
            'project_id' => $draft->project_id,
            'phase_id' => count($milestones) === 1 ? $milestones[0] : null,
            'invoice_type' => $draft->invoice_type,
            'invoice_number' => $draft->invoice_number,
            'amount_due' => $amountDue,
            'vat_amount' => $vatAmount,
            'vat_rate' => $vat['rate'],
            'vat_applied' => $vat['applies'],
            'billing_entity_id' => $vat['entity_id'],
            'region_code' => $vat['region'] ?? Vat::HOME,
            'total_amount' => $amountDue + $vatAmount,
            'wht_amount' => 0.0,
            'currency' => $draft->currency,
            'status' => 'Draft',
            'due_date' => self::dueDate((string) $draft->payment_terms, $now)->toDateString(),
            'client_billing_address' => $org->billing_address ?? 'TBD',
            'client_tax_id' => $org->tax_identification_number ?? null,
            'remittance_account_id' => $draft->remittance_account_id,
            'payment_terms' => $draft->payment_terms,
            'issued_at' => $now,
            'paid_at' => null,
            'created_at' => $draft->created_at,
        ]);
        $rows = collect($lines)->values()->map(fn ($l, $i) => (object) [
            'id' => $i + 1,
            'invoice_id' => $draft->id,
            'kind' => $l['kind'],
            'advance_invoice_id' => $l['advance_invoice_id'] ?? null,
            'phase_id' => $l['phase_id'],
            'description' => Str::limit($l['description'], 252),
            'quantity' => 1,
            'unit_price' => round((float) $l['amount_signed'], 2),
            'total_price' => round((float) $l['amount_signed'], 2),
        ]);

        return [$invoice, $rows];
    }
}
