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
    public $kpis = [];
    public $invoiceLedger = [];
    public $escrowLogs = [];
    public $systemLogs = [];

    public function mount()
    {
        $this->loadAuditData();
    }

    public function loadAuditData()
    {
        // THE SAFETY NET: Pre-fill every possible key the frontend might ask for.
        // This guarantees you will NEVER get an "Undefined array key" error here again.
        // Money figures are kept per currency (['NGN' => 3332.5, 'USD' => 322.5]), never added across currencies.
        $this->kpis = [
            'ytd_cleared_revenue' => [],
            'ytd_vat_liability' => [],
            'verified_transactions' => 0, 
            'escrow_verifications' => 0, 
            'pending_escrow' => 0,
            'total_system_events' => 0,
        ];

        try {
            $currentYear = Carbon::now()->year;

            // 1. Financial KPIs
            $this->kpis['ytd_cleared_revenue'] = HqMoney::sumByCurrency(DB::table('invoices')
                ->where('status', 'Paid')
                ->whereYear('paid_at', $currentYear), 'total_amount');

            $this->kpis['ytd_vat_liability'] = HqMoney::sumByCurrency(DB::table('invoices')
                ->where('status', 'Paid')
                ->whereYear('paid_at', $currentYear), 'vat_amount');

            // Map the same DB count to both possible keys just to be perfectly safe
            $verifiedCount = DB::table('transactions')
                ->where('status', 'Verified')
                ->whereYear('verified_at', $currentYear)
                ->count();
            $this->kpis['verified_transactions'] = $verifiedCount;
            $this->kpis['escrow_verifications'] = $verifiedCount;

            $this->kpis['pending_escrow'] = DB::table('transactions')
                ->where('status', 'Pending Verification')
                ->whereYear('created_at', $currentYear)
                ->count();

            $this->kpis['total_system_events'] = DB::table('system_audit_logs')
                ->whereYear('created_at', $currentYear)
                ->count();

            // 2. Latest invoices
            $this->invoiceLedger = DB::table('invoices')
                ->join('projects', 'invoices.project_id', '=', 'projects.id')
                ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
                ->select('invoices.*', 'organizations.name as client_name', 'projects.title as project_title')
                ->orderBy('invoices.created_at', 'desc')
                ->limit(6)
                ->get();

            // 3. Payment verifications
            $this->escrowLogs = DB::table('transactions')
                ->leftJoin('invoices', 'transactions.invoice_id', '=', 'invoices.id')
                ->select('transactions.*', 'invoices.invoice_number', 'invoices.currency')
                ->orderBy('transactions.created_at', 'desc')
                ->limit(6)
                ->get();

            // 4. Process Safety & Security Logs
            $this->systemLogs = DB::table('system_audit_logs')
                ->leftJoin('users', 'system_audit_logs.user_id', '=', 'users.id')
                ->select('system_audit_logs.*', 'users.email as user_email')
                ->orderBy('system_audit_logs.created_at', 'desc')
                ->limit(8)
                ->get();

        } catch (\Exception $e) {
            // If the DB connection fails, the collections are safely emptied
            $this->invoiceLedger = collect([]);
            $this->escrowLogs = collect([]);
            $this->systemLogs = collect([]);
        }
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
        return view('livewire.hq.dashboards.audit-terminal');
    }
}