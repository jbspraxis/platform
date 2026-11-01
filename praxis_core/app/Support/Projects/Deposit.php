<?php

namespace App\Support\Projects;

use Illuminate\Support\Facades\DB;

/**
 * "Less deposit, every milestone" (owner, 1 Nov 2026; docs/PROJECTS.md section 9): a project whose signed SOW raised a
 * deposit invoice bills each of the SOW's milestones at its amount less the deposit's share, so the deposit and the
 * milestone invoices add up to exactly the contract. project_phases.amount stays the milestone's full share; only what
 * Finance pre-fills and the drawer shows as due change. A milestone a change report added isn't reduced (the deposit
 * never covered it); a project without a SOW deposit is unchanged. Amounts are before VAT: Finance adds VAT per invoice.
 *
 * The share is the deposit invoice's amount before VAT over the contract before VAT as signed: the SOW's approved
 * proposal's subtotal, or for an imported SOW (MyCity) the project's total less the change reports approved since.
 */
final class Deposit
{
    /** @return array{share: float, percent: float, amount: float, contract: float, invoice: string}|null */
    public static function forProject(string $projectId): ?array
    {
        $row = DB::table('statements_of_work')->join('invoices', 'invoices.id', '=', 'statements_of_work.deposit_invoice_id')
            ->where('statements_of_work.status', 'signed')->whereNull('statements_of_work.deleted_at')
            ->where('invoices.project_id', $projectId)->whereNull('invoices.deleted_at')->where('invoices.status', '!=', 'Cancelled')
            ->orderByDesc('statements_of_work.signed_at')
            ->first(['invoices.amount_due', 'invoices.invoice_number', 'statements_of_work.proposal_id']);
        if (! $row || (float) $row->amount_due <= 0) {
            return null;
        }
        $contract = $row->proposal_id
            ? (float) DB::table('proposals')->where('id', $row->proposal_id)->value('subtotal')
            : (float) DB::table('projects')->where('id', $projectId)->value('total_budget')
                - (float) DB::table('change_reports')->where('project_id', $projectId)->where('status', 'approved')->whereNull('deleted_at')->sum('price');
        if ($contract <= 0) {
            return null;
        }
        $share = min(1.0, (float) $row->amount_due / $contract);

        return ['share' => $share, 'percent' => round($share * 100, 2), 'amount' => (float) $row->amount_due, 'contract' => $contract, 'invoice' => (string) $row->invoice_number];
    }

    /** A milestone a change report added isn't reduced: the deposit never covered it */
    public static function appliesTo(object $phase): bool
    {
        return ! DB::table('change_reports')->where('phase_id', $phase->id)->whereNull('deleted_at')->exists();
    }

    /**
     * What each milestone's invoice is due, before VAT, by milestone id: its amount less the deposit's share. When the
     * reduced milestones' amounts make the whole contract, the last one takes the rounding, so the deposit and the
     * milestone invoices add up to it exactly. Without a deposit, every milestone is due its amount.
     *
     * @return array<string, float>
     */
    public static function dues(string $projectId, ?array $deposit = null): array
    {
        $deposit ??= self::forProject($projectId);
        $phases = DB::table('project_phases')->where('project_id', $projectId)->whereNull('deleted_at')->orderBy('phase_number')->get();
        $dues = [];
        $reduced = [];
        foreach ($phases as $phase) {
            $amount = Milestones::amount($phase);
            if ($deposit && self::appliesTo($phase)) {
                $dues[$phase->id] = round($amount * (1 - $deposit['share']), 2);
                $reduced[$phase->id] = $amount;
            } else {
                $dues[$phase->id] = $amount;
            }
        }
        $last = collect($reduced)->filter(fn ($amount) => $amount > 0)->keys()->last();
        if ($deposit && $last && abs(array_sum($reduced) - $deposit['contract']) < 0.005) {
            $dues[$last] = round($deposit['contract'] - $deposit['amount'] - (array_sum(array_intersect_key($dues, $reduced)) - $dues[$last]), 2);
        }

        return $dues;
    }

    /** One milestone's amount due, and whether the deposit was taken off */
    public static function forMilestone(object $phase): array
    {
        $deposit = self::forProject($phase->project_id);
        $amount = Milestones::amount($phase);
        $due = self::dues($phase->project_id, $deposit)[$phase->id] ?? $amount;

        return ['amount' => $amount, 'due' => $due, 'deposit' => $deposit && self::appliesTo($phase) ? $deposit : null];
    }

    /** "40%" or "13.51%" (French: "13,51 %") */
    public static function percentLabel(float $percent, string $locale): string
    {
        $n = rtrim(rtrim(number_format($percent, 2, $locale === 'fr' ? ',' : '.', ''), '0'), '.,');

        return $locale === 'fr' ? $n . "\u{202F}%" : $n . '%';
    }
}
