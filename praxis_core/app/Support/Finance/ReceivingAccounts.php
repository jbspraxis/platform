<?php

namespace App\Support\Finance;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The accounts a client pays an invoice into (owner, 2 Nov 2026; docs/QA-PORTAL.md D8): active, in the invoice's
 * currency and held by the company that bills it (no billing company: Nigeria's, as Vat::issuer). An account without
 * a currency or a company is shown to no client until HQ sets both in Master data › Remittance accounts.
 */
class ReceivingAccounts
{
    public static function for(?string $currency, ?string $entityId): Collection
    {
        $company = Vat::issuer($entityId)?->id;
        if (! $currency || ! $company) {
            return collect();
        }

        return DB::table('mdm_remittance_accounts')->where('is_active', 1)->where('currency', strtoupper($currency))
            ->where('legal_entity_id', $company)->orderBy('type')->orderBy('provider_name')->get();
    }

    /** The accounts for a project's unpaid invoices; with none, for the project's own currency and billing company */
    public static function forProject(object $project): Collection
    {
        $invoices = DB::table('invoices')->where('project_id', $project->id)->whereNull('deleted_at')->whereIn('status', ['Pending', 'Overdue'])
            ->select('currency', 'billing_entity_id')->distinct()->get();
        if ($invoices->isEmpty()) {
            $invoices = collect([(object) ['currency' => $project->currency, 'billing_entity_id' => Vat::projectEntity($project->id)]]);
        }

        return $invoices->flatMap(fn ($i) => self::for($i->currency, $i->billing_entity_id))->unique('id')->values();
    }
}
