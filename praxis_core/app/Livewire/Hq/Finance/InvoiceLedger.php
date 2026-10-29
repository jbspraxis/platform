<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\Access;

#[Layout('components.layouts.hq')]
class InvoiceLedger extends Component
{
    public $activeTab = 'Pending'; // Pending, Overdue, Paid, Cancelled
    public $invoices = [];
    
    // Create Invoice State
    public $showCreateModal = false;
    public $activeProjects = [];
    public $projectPhases = [];
    public $remittanceAccounts = [];
    public $exchangeRates = [];
    
    // Form Inputs
    public $new_project_id = '';
    public $new_phase_id = '';
    public $new_invoice_type = 'Tax Invoice - Phase Milestone';
    public $new_amount_due = 0.00;
    public $new_currency = 'USD';
    public $new_payment_terms = 'Net 10';
    public $new_remittance_id = '';
    public $new_description = '';

    public function mount()
    {
        $this->loadMasterData();
        $this->loadInvoices();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadInvoices();
    }

    public function loadMasterData()
    {
        // Load active projects that have cleared the scoping phase
        $this->activeProjects = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->whereNotIn('projects.status', ['Completed', 'Cancelled'])
            ->select('projects.id', 'projects.title', 'projects.currency', 'organizations.name as client_name')
            ->orderBy('projects.created_at', 'desc')
            ->get();

        $this->remittanceAccounts = DB::table('mdm_remittance_accounts')->where('is_active', 1)->get();
        $this->exchangeRates = DB::table('mdm_exchange_rates')->get();
    }

    public function updatedNewProjectId($projectId)
    {
        // When a project is selected, fetch its pending phases and currency
        if (!empty($projectId)) {
            $this->projectPhases = DB::table('project_phases')
                ->where('project_id', $projectId)
                ->where('status', 'Pending')
                ->get();

            $project = collect($this->activeProjects)->firstWhere('id', $projectId);
            if ($project) {
                $this->new_currency = $project->currency;
            }
        } else {
            $this->projectPhases = [];
        }
    }

    public function loadInvoices()
    {
        $this->invoices = DB::table('invoices')
            ->join('projects', 'invoices.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('invoices.status', $this->activeTab)
            ->select(
                'invoices.*', 
                'projects.title as project_title', 
                'organizations.name as client_name'
            )
            ->orderBy('invoices.due_date', 'asc')
            ->get();
    }

    public function draftSubsequentInvoice()
    {
        abort_unless(Auth::user()->can('finance.create'), 403);

        $this->validate([
            'new_project_id' => 'required|exists:projects,id',
            'new_phase_id' => 'nullable|exists:project_phases,id',
            'new_amount_due' => 'required|numeric|min:1',
            'new_payment_terms' => 'required|string',
            'new_remittance_id' => 'required|exists:mdm_remittance_accounts,id',
            'new_description' => 'required|string|max:255'
        ]);

        $project = DB::table('projects')->where('id', $this->new_project_id)->first();
        $org = DB::table('organizations')->where('id', $project->organization_id)->first();
        
        // 1. HARDENED TAX LOGIC: Attempt to match tax jurisdiction to client country
        $taxData = DB::table('mdm_tax_compliance')
            ->where('is_active', 1)
            ->where(function($query) use ($org) {
                if ($org && $org->country) {
                    $query->where('country_code', 'LIKE', '%' . substr($org->country, 0, 2) . '%')
                          ->orWhere('region_name', 'LIKE', '%' . $org->country . '%');
                }
            })
            ->first();
            
        // Fallback to default if no match, but prioritize the match
        if (!$taxData) {
            $taxData = DB::table('mdm_tax_compliance')->where('is_active', 1)->first();
        }

        $vatRate = $taxData ? ((float)$taxData->vat_rate / 100) : 0.075;
        $vatAmount = $this->new_amount_due * $vatRate;
        $totalAmount = $this->new_amount_due + $vatAmount;

        // 2. HARDENED DUE DATE LOGIC: Properly handle "Receipt" vs "Net X"
        if (stripos(strtolower($this->new_payment_terms), 'receipt') !== false) {
            $days = 0;
        } else {
            $parsedDays = (int) filter_var($this->new_payment_terms, FILTER_SANITIZE_NUMBER_INT);
            $days = $parsedDays > 0 ? $parsedDays : 10; // Only fallback if explicitly 0 but not "Receipt"
        }
        $dueDate = now()->addDays($days);

        // Lock Current Exchange Rate
        $fxRate = 1.000000;
        if ($this->new_currency !== 'USD') {
            $rateData = collect($this->exchangeRates)->firstWhere('currency_code', $this->new_currency);
            if ($rateData) $fxRate = $rateData->rate_to_usd;
        }

        $invoiceId = (string) Str::uuid();
        $invNumber = 'INV-' . date('Ym') . '-' . rand(1000, 9999);
        $timestamp = now();

        DB::transaction(function () use ($invoiceId, $invNumber, $project, $org, $vatAmount, $totalAmount, $dueDate, $fxRate, $timestamp) {
            
            DB::table('invoices')->insert([
                'id' => $invoiceId,
                'project_id' => $project->id,
                'phase_id' => empty($this->new_phase_id) ? null : $this->new_phase_id,
                'invoice_type' => $this->new_invoice_type,
                'invoice_number' => $invNumber,
                'amount_due' => $this->new_amount_due,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'wht_amount' => 0.00,
                'currency' => $this->new_currency,
                'exchange_rate_locked' => $fxRate,
                'status' => 'Pending',
                'due_date' => $dueDate,
                'client_billing_address' => $org->billing_address ?? 'TBD',
                'client_tax_id' => $org->tax_identification_number ?? null,
                'remittance_account_id' => $this->new_remittance_id,
                'payment_terms' => $this->new_payment_terms,
                'jbs_signature_hash' => 'SHA256-JBS-' . strtoupper(hash('sha256', $invoiceId . $totalAmount . $timestamp)),
                'created_by' => Auth::id(),
                'issued_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ]);

            // 3. HARDENED INVOICE LINES: Included UUID generation
            DB::table('invoice_lines')->insert([
                'id' => (string) Str::uuid(), // Critical Fix
                'invoice_id' => $invoiceId,
                'description' => $this->new_description,
                'metric_of_billing' => 'Phase Completion',
                'quantity' => 1,
                'unit_price' => $this->new_amount_due,
                'total_price' => $this->new_amount_due,
                'created_at' => $timestamp
            ]);

        });

        $this->showCreateModal = false;
        $this->reset(['new_project_id', 'new_phase_id', 'new_amount_due', 'new_description']);
        $this->loadInvoices();
        session()->flash('success', '> INVOICE GENERATED AND DISPATCHED TO CLIENT OS.');
    }

    public function cancelInvoice($invoiceId)
    {
        abort_unless(Auth::user()->can('finance.delete'), 403);

        DB::table('invoices')->where('id', $invoiceId)->update([
            'status' => 'Cancelled',
            'updated_by' => Auth::id(),
            'updated_at' => now()
        ]);

        DB::table('system_audit_logs')->insert([
            // 4. HARDENED AUDIT LOG: Safe null UUID handling
            'user_id' => Auth::check() ? Auth::id() : null, 
            'action' => 'INVOICE_CANCELLED',
            'target_table' => 'invoices',
            'target_id' => $invoiceId,
            'ip_address' => request()->ip(),
            'created_at' => now()
        ]);

        $this->loadInvoices();
        session()->flash('success', '> INVOICE CANCELLED. LEDGER NEUTRALIZED.');
    }

    /**
     * THE 10-DAY GUILLOTINE EXECUTOR
     * In production, this runs via Laravel Scheduler daily.
     * Here, it can be manually triggered by staff with finance.approve to audit the ledger.
     */
    public function enforceGuillotineProtocol()
    {
        abort_unless(Auth::user()->can('finance.approve'), 403);

        $expiredInvoices = DB::table('invoices')
            ->where('status', 'Pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->get();

        if ($expiredInvoices->isEmpty()) {
            session()->flash('success', '> GUILLOTINE AUDIT COMPLETE. NO DELINQUENT ACCOUNTS DETECTED.');
            return;
        }

        DB::transaction(function () use ($expiredInvoices) {
            foreach ($expiredInvoices as $invoice) {
                // 1. Drop the Guillotine
                DB::table('invoices')->where('id', $invoice->id)->update([
                    'status' => 'Overdue',
                    'updated_at' => now()
                ]);

                // 2. Alert finance approvers
                $admins = Access::staffWith('finance.approve');

                $notifications = [];
                foreach($admins as $adminId) {
                    $notifications[] = [
                        'id' => (string) Str::uuid(), 'user_id' => $adminId,
                        'title' => 'GUILLOTINE DROPPED: DELINQUENT ACCOUNT', 
                        'message' => "Invoice {$invoice->invoice_number} is overdue. Client OS has been automatically locked. Awaiting manual order to halt engineering deployment.", 
                        'type' => 'System', 'created_at' => now()
                    ];
                }
                if(!empty($notifications)) DB::table('notifications')->insert($notifications);

                // 3. Immutable Audit Trail (Hardened UUID)
                DB::table('system_audit_logs')->insert([
                    'user_id' => Auth::check() ? Auth::id() : null, // Prevent 'SYSTEM_CRON' crash
                    'action' => 'GUILLOTINE_PROTOCOL_ENGAGED',
                    'description' => "Invoice {$invoice->invoice_number} expired. Financial Lockout engaged on Client OS.",
                    'target_table' => 'invoices',
                    'target_id' => $invoice->id,
                    'created_at' => now()
                ]);
            }
        });

        $this->loadInvoices();
        session()->flash('error', '> GUILLOTINE PROTOCOL ENGAGED. ' . $expiredInvoices->count() . ' ACCOUNT(S) LOCKED.');
    }
    public function render()
    {
        return view('livewire.hq.finance.invoice-ledger');
    }
}