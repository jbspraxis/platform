<?php

namespace App\Livewire\Hq\Dashboards;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;
use App\Livewire\Hq\HqMoney;

#[Layout('components.layouts.hq')]
class AuditTerminal extends Component
{
    // Nothing is kept in the page's state: the figures and lists are read when the page draws (render)

    /** Everything the page shows, with only the columns it shows */
    private function auditData(): array
    {
        // THE SAFETY NET: Pre-fill every possible key the frontend might ask for.
        // This guarantees you will NEVER get an "Undefined array key" error here again.
        // Money figures are kept per currency (['NGN' => 3332.5, 'USD' => 322.5]), never added across currencies.
        $data = [
            'kpis' => [
                'ytd_cleared_revenue' => [],
                'ytd_vat_liability' => [],
                'verified_transactions' => 0,
                'escrow_verifications' => 0,
                'pending_escrow' => 0,
                'total_system_events' => 0,
            ],
            'invoiceLedger' => collect([]),
            'escrowLogs' => collect([]),
            'systemLogs' => collect([]),
        ];

        try {
            // This year as a date range rather than YEAR(column), so each count can use an index on its date
            $yearStart = Carbon::now()->startOfYear();
            $nextYear = $yearStart->copy()->addYear();

            // 1. Financial KPIs
            $data['kpis']['ytd_cleared_revenue'] = HqMoney::sumByCurrency(DB::table('invoices')
                ->where('status', 'Paid')
                ->where('paid_at', '>=', $yearStart)->where('paid_at', '<', $nextYear), 'total_amount');

            $data['kpis']['ytd_vat_liability'] = HqMoney::sumByCurrency(DB::table('invoices')
                ->where('status', 'Paid')
                ->where('paid_at', '>=', $yearStart)->where('paid_at', '<', $nextYear), 'vat_amount');

            // Map the same DB count to both possible keys just to be perfectly safe
            $verifiedCount = DB::table('transactions')
                ->where('status', 'Verified')
                ->where('verified_at', '>=', $yearStart)->where('verified_at', '<', $nextYear)
                ->count();
            $data['kpis']['verified_transactions'] = $verifiedCount;
            $data['kpis']['escrow_verifications'] = $verifiedCount;

            $data['kpis']['pending_escrow'] = DB::table('transactions')
                ->where('status', 'Pending Verification')
                ->where('created_at', '>=', $yearStart)->where('created_at', '<', $nextYear)
                ->count();

            $data['kpis']['total_system_events'] = DB::table('system_audit_logs')
                ->where('created_at', '>=', $yearStart)
                ->where('created_at', '<', $nextYear)
                ->count();

            // 2. Latest invoices
            $data['invoiceLedger'] = DB::table('invoices')
                ->join('projects', 'invoices.project_id', '=', 'projects.id')
                ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
                ->select('invoices.id', 'invoices.invoice_number', 'invoices.status', 'invoices.currency', 'invoices.total_amount', 'invoices.created_at', 'organizations.name as client_name')
                ->orderBy('invoices.created_at', 'desc')
                ->limit(6)
                ->get();

            // 3. Payment verifications
            $data['escrowLogs'] = DB::table('transactions')
                ->leftJoin('invoices', 'transactions.invoice_id', '=', 'invoices.id')
                ->select('transactions.status', 'transactions.method', 'transactions.amount_claimed', 'transactions.created_at', 'invoices.currency')
                ->orderBy('transactions.created_at', 'desc')
                ->limit(6)
                ->get();

            // 4. Process Safety & Security Logs (not the old and new values each line recorded)
            $data['systemLogs'] = DB::table('system_audit_logs')
                ->leftJoin('users', 'system_audit_logs.user_id', '=', 'users.id')
                ->select('system_audit_logs.created_at', 'system_audit_logs.action', 'system_audit_logs.ip_address', 'users.email as user_email')
                ->orderBy('system_audit_logs.created_at', 'desc')
                ->limit(8)
                ->get();

        } catch (\Exception $e) {
            // If the DB connection fails, the collections are safely emptied
            $data['invoiceLedger'] = collect([]);
            $data['escrowLogs'] = collect([]);
            $data['systemLogs'] = collect([]);
        }

        return $data;
    }

    public function exportBooks()
    {
        // The books are the invoice ledger, so finance exporters may take them too
        abort_unless(auth()->user()->can('audit.export') || auth()->user()->can('finance.export'), 403);

        $fileName = 'JBS_Praxis_invoices_' . Carbon::now()->format('Y_m_d') . '.csv';

        DB::table('system_audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => 'EXPORT_REPORT',
            'ip_address' => request()->ip(),
            'created_at' => now()
        ]);

        // The page itself doesn't change, so it isn't drawn again
        $this->skipRender();

        return Response::streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Invoice number', 'Client', 'Status', 'Currency', 'Amount due', 'VAT amount', 'Total amount', 'Paid on']);

            $data = DB::table('invoices')
                ->join('projects', 'invoices.project_id', '=', 'projects.id')
                ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
                ->select('invoices.*', 'organizations.name as client_name')
                ->orderBy('invoices.created_at', 'desc')
                ->get();

            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->created_at,
                    $row->invoice_number,
                    $row->client_name,
                    $row->status,
                    $row->currency,
                    $row->amount_due,
                    $row->vat_amount,
                    $row->total_amount,
                    $row->paid_at ?? 'Not paid'
                ]);
            }
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function render()
    {
        return view('livewire.hq.dashboards.audit-terminal', $this->auditData());
    }
}
