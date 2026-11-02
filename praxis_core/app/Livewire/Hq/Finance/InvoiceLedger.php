<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\Access;
use App\Support\Finance\ReceivingAccounts;
use App\Support\Finance\Vat;
use Illuminate\Validation\Rule;

#[Layout('components.layouts.hq')]
class InvoiceLedger extends Component
{
    /** Statuses an invoice can be cancelled from: unpaid ones only */
    private const CANCELLABLE = ['Pending', 'Overdue'];

    public $activeTab = 'Pending'; // Pending, Overdue, Paid, Cancelled
    public $invoices = [];
    
    // Create Invoice State
    public $showCreateModal = false;
    public $activeProjects = [];
    public $projectPhases = [];
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
        $this->prefillFromProject();
    }

    /**
     * ?create=1&project=<id>&phase=<id>: a milestone dragged to Invoiced on its project page (docs/PROJECTS.md)
     * opens Create invoice with the project, the milestone and its share of the contract filled in.
     */
    private function prefillFromProject(): void
    {
        if (! request()->boolean('create') || ! Auth::user()->can('finance.create')) {
            return;
        }
        $project = collect($this->activeProjects)->firstWhere('id', (string) request()->query('project'));
        if ($project) {
            $this->new_project_id = $project->id;
            $this->updatedNewProjectId($project->id);
            $phase = collect($this->projectPhases)->firstWhere('id', (string) request()->query('phase'));
            if ($phase) {
                // The milestone's agreed amount, to the unit (project_phases.amount; weight × total for an older row),
                // less the SOW deposit's share when the project has one ("less deposit, every milestone": Deposit)
                $due = \App\Support\Projects\Deposit::forMilestone($phase);
                $this->new_phase_id = $phase->id;
                $this->new_amount_due = $due['due'];
                $this->new_description = Str::limit($due['deposit']
                    ? __('projects.invoice_line_less', ['milestone' => $phase->title, 'percent' => \App\Support\Projects\Deposit::percentLabel($due['deposit']['percent'], app()->getLocale())])
                    : __('projects.invoice_line', ['milestone' => $phase->title]), 250);
            }
        }
        $this->showCreateModal = true;
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

        $this->exchangeRates = DB::table('mdm_exchange_rates')->get();
    }

    public function updatedNewProjectId($projectId)
    {
        // When a project is selected, fetch the phases (milestones) not invoiced yet and its currency. A milestone is
        // invoiced on the client's acceptance, so accepted ones are offered too (docs/PROJECTS.md, decision 2)
        if (!empty($projectId)) {
            $this->projectPhases = DB::table('project_phases')
                ->where('project_id', $projectId)
                ->whereNull('deleted_at')
                ->whereNotIn('id', DB::table('invoices')->whereNotNull('phase_id')->whereNull('deleted_at')->where('status', '!=', 'Cancelled')->select('phase_id'))
                ->orderBy('phase_number')
                ->get();

            $project = collect($this->activeProjects)->firstWhere('id', $projectId);
            if ($project) {
                $this->new_currency = $project->currency;
            }
            // The only account that fits is chosen for you
            $matching = $this->matchingAccounts();
            $this->new_remittance_id = $matching->count() === 1 ? (string) $matching->first()->id : '';
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
            'new_remittance_id' => ['required', Rule::in($this->matchingAccounts()->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'new_description' => 'required|string|max:255'
        ]);

        $project = DB::table('projects')->where('id', $this->new_project_id)->first();
        $org = DB::table('organizations')->where('id', $project->organization_id)->first();
        
        // 1. VAT: only when the company issuing it (the project's opportunity's billing company, else Nigeria's) charges
        // VAT today, at 0% for a client abroad (App\Support\Finance\Vat); the invoice records what it applied, and who
        $vat = Vat::for($org, null, Vat::projectEntity($project->id));
        $vatAmount = $vat['applies'] ? round((float) $this->new_amount_due * $vat['rate'] / 100, 2) : 0.0;
        $totalAmount = (float) $this->new_amount_due + $vatAmount;

        // 2. Due date: handle "Receipt" and "Net X" terms
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

        DB::transaction(function () use ($invoiceId, $invNumber, $project, $org, $vat, $vatAmount, $totalAmount, $dueDate, $fxRate, $timestamp) {
            
            DB::table('invoices')->insert([
                'id' => $invoiceId,
                'project_id' => $project->id,
                'phase_id' => empty($this->new_phase_id) ? null : $this->new_phase_id,
                'invoice_type' => $this->new_invoice_type,
                'invoice_number' => $invNumber,
                'amount_due' => $this->new_amount_due,
                'vat_amount' => $vatAmount,
                'vat_rate' => $vat['rate'],
                'vat_applied' => $vat['applies'],
                'billing_entity_id' => $vat['entity_id'],
                'region_code' => $vat['region'] ?? Vat::HOME,
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

            // 3. INVOICE LINES (id is auto-increment and the table has no timestamps)
            DB::table('invoice_lines')->insert([
                'invoice_id' => $invoiceId,
                'description' => $this->new_description,
                'metric_of_billing' => 'Phase Completion',
                'quantity' => 1,
                'unit_price' => $this->new_amount_due,
                'total_price' => $this->new_amount_due,
            ]);

            // Accrue the receivable in the general ledger, as the proposal gateway does for deposits
            \App\Livewire\Hq\Finance\GeneralLedgerManager::recordInvoiceIssued($invoiceId);
        });
        // The client's billing people hear about it in their portal and on their devices (docs/PWA.md)
        \App\Support\Pwa\ClientNotify::invoice($invoiceId, 'invoice_issued');

        $this->showCreateModal = false;
        $this->reset(['new_project_id', 'new_phase_id', 'new_amount_due', 'new_description']);
        $this->loadInvoices();
        session()->flash('success', "Invoice {$invNumber} created for " . ($org->name ?? 'the client') . '.');
    }

    public function cancelInvoice($invoiceId)
    {
        abort_unless(Auth::user()->can('finance.delete'), 403);

        // Only an unpaid invoice (pending or overdue) can be cancelled: never a paid one, never twice
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
        if (! $invoice) {
            session()->flash('error', "We couldn’t find that invoice. Refresh the page and try again.");
            $this->loadInvoices();
            return;
        }
        if (! in_array($invoice->status, self::CANCELLABLE, true)) {
            session()->flash('error', $invoice->status === 'Paid'
                ? "Invoice {$invoice->invoice_number} is paid, so it can’t be cancelled."
                : "Invoice {$invoice->invoice_number} is already cancelled.");
            $this->loadInvoices();
            return;
        }

        $updated = DB::table('invoices')
            ->where('id', $invoiceId)
            ->whereIn('status', self::CANCELLABLE)
            ->update([
                'status' => 'Cancelled',
                'updated_by' => Auth::id(),
                'updated_at' => now()
            ]);

        if (! $updated) {
            session()->flash('error', "Invoice {$invoice->invoice_number} changed while you were working, so it wasn’t cancelled. Check its status and try again.");
            $this->loadInvoices();
            return;
        }

        DB::table('system_audit_logs')->insert([
            // Safe null UUID handling
            'user_id' => Auth::check() ? Auth::id() : null, 
            'action' => 'INVOICE_CANCELLED',
            'description' => "Cancelled invoice {$invoice->invoice_number}.",
            'target_table' => 'invoices',
            'target_id' => $invoice->id,
            'old_values' => json_encode(['status' => $invoice->status]),
            'new_values' => json_encode(['status' => 'Cancelled']),
            'ip_address' => request()?->ip(),
            'created_at' => now()
        ]);
        \App\Support\Pwa\ClientNotify::invoice($invoiceId, 'invoice_cancelled');

        $this->loadInvoices();
        session()->flash('success', "Invoice {$invoice->invoice_number} cancelled.");
    }

    /**
     * Overdue check: marks unpaid invoices past their due date as overdue,
     * which puts the client’s portal access for that project on hold.
     * In production this runs daily via the Laravel scheduler; staff with
     * finance.approve can also run it from the Invoices screen.
     */
    public function enforceGuillotineProtocol()
    {
        abort_unless(Auth::user()->can('finance.approve'), 403);

        $expiredInvoices = DB::table('invoices')
            ->where('status', 'Pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->get();

        if ($expiredInvoices->isEmpty()) {
            session()->flash('success', 'No unpaid invoices are past their due date. Nothing was changed.');
            return;
        }

        $count = 0;
        DB::transaction(function () use ($expiredInvoices, &$count) {
            foreach ($expiredInvoices as $invoice) {
                // 1. Mark the invoice overdue (only if it is still unpaid: a payment may have landed meanwhile)
                $marked = DB::table('invoices')->where('id', $invoice->id)->where('status', 'Pending')->update([
                    'status' => 'Overdue',
                    'updated_at' => now()
                ]);
                if (! $marked) {
                    continue;
                }
                $count++;

                // 2. Alert finance approvers
                $admins = Access::staffWith('finance.approve');

                $notifications = [];
                foreach($admins as $adminId) {
                    $notifications[] = [
                        'id' => (string) Str::uuid(), 'user_id' => $adminId,
                        'title' => 'Invoice overdue', 
                        'message' => "Invoice {$invoice->invoice_number} is past its due date. The client’s portal access for this project is on hold until it is paid. Decide whether work on the project should pause.", 
                        'type' => 'System', 'created_at' => now()
                    ];
                }
                if(!empty($notifications)) DB::table('notifications')->insert($notifications);
                \App\Support\Pwa\ClientNotify::invoice($invoice->id, 'invoice_overdue');

                // 3. Audit trail (safe null UUID)
                DB::table('system_audit_logs')->insert([
                    'user_id' => Auth::check() ? Auth::id() : null, // Prevent 'SYSTEM_CRON' crash
                    'action' => 'GUILLOTINE_PROTOCOL_ENGAGED',
                    'description' => "Invoice {$invoice->invoice_number} is past its due date and was marked overdue. Client Portal access for the project is on hold.",
                    'target_table' => 'invoices',
                    'target_id' => $invoice->id,
                    'old_values' => json_encode(['status' => 'Pending']),
                    'new_values' => json_encode(['status' => 'Overdue']),
                    'ip_address' => request()?->ip(),
                    'created_at' => now()
                ]);
            }
        });

        $this->loadInvoices();
        session()->flash('error', $count . ($count === 1 ? ' invoice was' : ' invoices were') . " marked overdue. Client Portal access for those projects is on hold until they’re paid.");
    }
    /** Accounts the client may pay this project's invoice into: its currency, held by its billing company (D8) */
    private function matchingAccounts(): \Illuminate\Support\Collection
    {
        return $this->new_project_id ? ReceivingAccounts::for($this->new_currency, Vat::projectEntity((string) $this->new_project_id)) : collect();
    }

    /** Field names in validation messages: the labels people see, not the property names (docs/BRAND.md sections 5 and 12) */
    protected function validationAttributes(): array
    {
        return [
            'new_project_id' => 'project',
            'new_phase_id' => 'phase',
            'new_amount_due' => 'amount before VAT',
            'new_payment_terms' => 'payment terms',
            'new_remittance_id' => 'account to pay into',
            'new_description' => 'description',
        ];
    }

    /** Validation messages that say what to enter instead */
    public function messages(): array
    {
        return [
            'new_amount_due.min' => 'Enter an amount of 1 or more.',
            'new_remittance_id.in' => 'Choose an account in the project’s currency held by the company that bills it.',
        ];
    }

    public function render()
    {
        // What Create invoice adds for the project chosen: VAT, or a line saying why none (App\Support\Finance\Vat)
        $project = $this->showCreateModal && $this->new_project_id ? DB::table('projects')->where('id', $this->new_project_id)->first(['id', 'organization_id']) : null;

        return view('livewire.hq.finance.invoice-ledger', [
            'vat' => $vat = $project ? Vat::for((string) $project->organization_id, null, Vat::projectEntity($project->id)) : null,
            'matchingAccounts' => $project ? $this->matchingAccounts() : collect(),
            'billingCompany' => $vat['company'] ?? null,
        ]);
    }
}