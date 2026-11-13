<?php

namespace App\Support\Clients;

use App\Models\Organization;
use App\Support\Finance\InvoicePayments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HQ › Clients (docs/CLIENTS.md): the organisations we work for, active and past, and what prints on their invoices.
 *
 * A client is an organisation with status Active Client or Past Client. Its details are the lead's (organizations
 * columns, saved through Leads::updateDetails); this class only reads: the list's rows, what is still owed, and
 * which of the details the invoice PDF prints ("Billed to", resources/views/pdf/invoice.blade.php) are empty.
 */
final class Clients
{
    /** The list's filter => the organisation statuses it shows */
    public const SHOW = [
        'active' => ['Active Client'],
        'past' => ['Past Client'],
        'all' => ['Active Client', 'Past Client'],
    ];

    /** What the invoice PDF prints in "Billed to", besides the name: column => lang key under clients.fields */
    public const PRINTED = [
        'billing_address' => 'address',
        'registration_number' => 'registration_number',
        'tax_identification_number' => 'tax_number',
        'email' => 'email',
        'phone' => 'phone',
    ];

    /** Invoices that still wait for money */
    public const UNPAID = ['Pending', 'Overdue'];

    /** A project that is still running (projects.status) */
    private const FINISHED = ['Completed', 'Cancelled'];

    /** An active or past client, not deleted, else null */
    public static function find(?string $id): ?Organization
    {
        return $id ? Organization::query()->whereKey($id)->whereIn('status', self::SHOW['all'])->whereNull('deleted_at')->first() : null;
    }

    /**
     * The list's rows: active clients first, then by name. Each row has the organisation's columns plus main_contact,
     * contacts, projects_running and projects_total.
     */
    public static function rows(string $show, string $search = ''): Collection
    {
        $statuses = self::SHOW[$show] ?? self::SHOW['active'];
        $search = trim($search);

        return DB::table('organizations as o')
            ->whereIn('o.status', $statuses)->whereNull('o.deleted_at')
            ->when($search !== '', fn ($q) => $q->where('o.name', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->select('o.id', 'o.name', 'o.status', 'o.country', 'o.city', 'o.client_since', 'o.billing_address', 'o.registration_number',
                'o.tax_identification_number', 'o.email', 'o.phone')
            ->selectSub(fn ($q) => $q->from('client_users')->whereColumn('organization_id', 'o.id')->whereNull('deleted_at')
                ->orderByDesc('is_main_contact')->orderBy('created_at')->limit(1)
                ->selectRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))"), 'main_contact')
            ->selectSub(fn ($q) => $q->from('client_users')->whereColumn('organization_id', 'o.id')->whereNull('deleted_at')->selectRaw('COUNT(*)'), 'contacts')
            ->selectSub(fn ($q) => $q->from('projects')->whereColumn('organization_id', 'o.id')->whereNull('deleted_at')
                ->whereNotIn('status', self::FINISHED)->selectRaw('COUNT(*)'), 'projects_running')
            ->selectSub(fn ($q) => $q->from('projects')->whereColumn('organization_id', 'o.id')->whereNull('deleted_at')->selectRaw('COUNT(*)'), 'projects_total')
            ->orderByRaw("CASE WHEN o.status = 'Active Client' THEN 0 ELSE 1 END")
            ->orderBy('o.name')
            ->get();
    }

    /** How many clients each filter shows */
    public static function counts(): array
    {
        $by = DB::table('organizations')->whereIn('status', self::SHOW['all'])->whereNull('deleted_at')
            ->groupBy('status')->selectRaw('status, COUNT(*) as n')->pluck('n', 'status');
        $active = (int) ($by['Active Client'] ?? 0);
        $past = (int) ($by['Past Client'] ?? 0);

        return ['active' => $active, 'past' => $past, 'all' => $active + $past];
    }

    /**
     * What is still owed on sent and overdue invoices (total less verified payments, InvoicePayments::settled), by
     * organisation and currency: [organisation id => [currency => amount]]. Fully paid ones are left out.
     *
     * @param  iterable<string>  $organizationIds
     * @return array<string, array<string, float>>
     */
    public static function unpaid(iterable $organizationIds): array
    {
        $invoices = self::unpaidInvoices($organizationIds);
        $owed = [];
        foreach ($invoices as $invoice) {
            $owed[$invoice->organization_id][$invoice->currency] = round(($owed[$invoice->organization_id][$invoice->currency] ?? 0) + $invoice->left, 2);
        }
        // Currencies in a fixed order (alphabetical), however the invoices came back
        foreach ($owed as &$byCurrency) {
            ksort($byCurrency);
        }
        unset($byCurrency);

        return $owed;
    }

    /**
     * The sent and overdue invoices of these organisations with what is left on each (left > 0 only), oldest due first.
     * Each row: id, invoice_number, invoice_type, status, due_date, currency, total_amount, left, organization_id,
     * project_id, project_title.
     *
     * @param  iterable<string>  $organizationIds
     */
    public static function unpaidInvoices(iterable $organizationIds): Collection
    {
        $ids = collect($organizationIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return collect();
        }
        $invoices = DB::table('invoices as i')->join('projects as p', 'p.id', '=', 'i.project_id')
            ->whereIn('p.organization_id', $ids)->whereIn('i.status', self::UNPAID)->whereNull('i.deleted_at')
            ->orderBy('i.due_date')->orderBy('i.invoice_number')
            ->get(['i.id', 'i.invoice_number', 'i.invoice_type', 'i.status', 'i.due_date', 'i.currency', 'i.total_amount', 'p.organization_id', 'p.id as project_id', 'p.title as project_title']);
        $settled = InvoicePayments::settled($invoices->pluck('id'));

        return $invoices->map(function ($invoice) use ($settled) {
            $invoice->left = max(0.0, round((float) $invoice->total_amount - (float) ($settled->get($invoice->id) ?? 0), 2));

            return $invoice;
        })->filter(fn ($invoice) => $invoice->left > 0)->values();
    }

    /**
     * The details the invoice prints that are empty, as lang keys under clients.fields (address, tax_number…), in the
     * order PRINTED lists them
     *
     * @return list<string>
     */
    public static function missing(object $organization): array
    {
        $missing = [];
        foreach (self::PRINTED as $column => $key) {
            if (trim((string) ($organization->{$column} ?? '')) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
