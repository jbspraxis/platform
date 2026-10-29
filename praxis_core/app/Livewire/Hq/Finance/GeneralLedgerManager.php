<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class GeneralLedgerManager extends Component
{
    public $entries = [];
    public $startDate;
    public $endDate;
    public $regionFilter = 'ALL';

    // Reversal State
    public $showReversalModal = false;
    public $reversalEntryId = '';
    public $reversalReason = '';

    public function mount()
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        $this->loadLedger();
    }

    public function loadLedger()
    {
        $query = DB::table('journal_entries')
            ->whereBetween('entry_date', [$this->startDate, $this->endDate])
            ->orderBy('created_at', 'desc');

        if ($this->regionFilter !== 'ALL') {
            $query->where('region_code', $this->regionFilter);
        }

        $rawEntries = $query->get();

        // Attach lines to entries with strict IFRS Sorting (Debits strictly above Credits)
        $this->entries = $rawEntries->map(function ($entry) {
            $entry->lines = DB::table('journal_lines')
                ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
                ->where('journal_lines.journal_entry_id', $entry->id)
                ->select(
                    'journal_lines.*', 
                    'chart_of_accounts.account_code', 
                    'chart_of_accounts.account_name'
                )
                // IFRS Rule: Debits (values > 0) sort to the top, Credits sort to the bottom
                ->orderBy('journal_lines.debit_amount', 'desc') 
                ->get();
            return $entry;
        })->toArray();
    }

    public function filterLedger()
    {
        $this->loadLedger();
    }

    public function triggerReversal($entryId)
    {
        $this->reversalEntryId = $entryId;
        $this->reversalReason = '';
        $this->showReversalModal = true;
    }

    public function executeReversingEntry()
    {
        $this->validate([
            'reversalEntryId' => 'required|exists:journal_entries,id',
            'reversalReason' => 'required|string|min:10'
        ]);

        try {
            DB::transaction(function () {
                $originalEntry = DB::table('journal_entries')->where('id', $this->reversalEntryId)->first();
                
                if ($originalEntry->is_reversal) {
                    throw new \Exception("IFRS Violation: Cannot reverse an entry that is already a reversal.");
                }

                $originalLines = DB::table('journal_lines')->where('journal_entry_id', $this->reversalEntryId)->get();

                // 1. Create the new Reversing Entry Header
                $newEntryId = (string) Str::uuid();
                $ref = 'REV-' . date('Ymd') . '-' . rand(1000, 9999);

                DB::table('journal_entries')->insert([
                    'id' => $newEntryId,
                    'reference_number' => $ref,
                    'entry_date' => now()->toDateString(),
                    'description' => "REVERSAL of {$originalEntry->reference_number}: " . $this->reversalReason,
                    'source_module' => 'Manual Reversal',
                    'source_id' => $originalEntry->id,
                    'region_code' => $originalEntry->region_code,
                    'is_reversal' => 1,
                    'reverses_entry_id' => $originalEntry->id,
                    'created_by' => Auth::id(),
                    'created_at' => now()
                ]);

                // 2. Mathematically Swap the Debits and Credits
                foreach ($originalLines as $line) {
                    DB::table('journal_lines')->insert([
                        'journal_entry_id' => $newEntryId,
                        'account_id' => $line->account_id,
                        'memo' => "Reversing: " . ($line->memo ?? 'Original Entry'),
                        'debit_amount' => $line->credit_amount,   // SWAP
                        'credit_amount' => $line->debit_amount,   // SWAP
                        'created_at' => now()
                    ]);
                }

                // 3. Mark original as reversed to lock it from future tampering
                DB::table('journal_entries')->where('id', $this->reversalEntryId)->update(['is_reversal' => 1]);

                // 4. Immutable Audit Log
                DB::table('system_audit_logs')->insert([
                    'user_id' => Auth::check() ? Auth::id() : null, 
                    'action' => 'IFRS_REVERSING_ENTRY_POSTED',
                    'description' => "Reversed {$originalEntry->reference_number}. Reason: {$this->reversalReason}",
                    'target_table' => 'journal_entries', 
                    'target_id' => $newEntryId, 
                    'created_at' => now()
                ]);
            });

            $this->showReversalModal = false;
            $this->loadLedger();
            session()->flash('success', '> IFRS REVERSING ENTRY EXECUTED SUCCESSFULLY.');

        } catch (\Exception $e) {
            session()->flash('error', '> REVERSAL FAILED: ' . $e->getMessage());
        }
    }

    /* ====================================================================================
       THE IFRS AUTOMATION ENGINE (STATIC API)
       Call these methods from anywhere in your application to record a flawless double-entry.
       ==================================================================================== */

    /**
     * Resolves the exact Database ID of a Chart of Account code.
     */
    public static function getAccountId($code, $clientId = null)
    {
        if ($clientId && str_starts_with($code, '1100')) {
            // Find specific Client A/R Sub-Account (e.g., 1100-0001)
            $subAcc = DB::table('chart_of_accounts')
                ->where('client_id', $clientId)
                ->where('is_active', 1)
                ->first();
            if ($subAcc) return $subAcc->id;
        }
        
        // Fallback to strict code match
        $account = DB::table('chart_of_accounts')->where('account_code', $code)->first();
        if (!$account) {
            throw new \Exception("CRITICAL ERROR: Ledger Account [{$code}] does not exist in Chart of Accounts.");
        }
        return $account->id;
    }

    /**
     * The Universal Double-Entry Writer. Enforces the Accounting Equation (Assets = Liab + Equity).
     */
    public static function postDoubleEntry($ref, $desc, $module, $sourceId, $region, $lines)
    {
        $totalDebit = 0; 
        $totalCredit = 0;
        
        foreach($lines as $line) { 
            $totalDebit += round((float)$line['debit'], 2); 
            $totalCredit += round((float)$line['credit'], 2); 
        }
        
        // Strict Validation: Debits must equal Credits to the penny.
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \Exception("IFRS SETTLEMENT REJECTED: Debits ({$totalDebit}) do not equal Credits ({$totalCredit}).");
        }

        $entryId = (string) Str::uuid();
        
        DB::table('journal_entries')->insert([
            'id' => $entryId, 
            'reference_number' => $ref, 
            'entry_date' => now()->toDateString(),
            'description' => $desc, 
            'source_module' => $module, 
            'source_id' => $sourceId,
            'region_code' => $region, 
            'created_by' => Auth::check() ? Auth::id() : null, 
            'created_at' => now()
        ]);

        foreach ($lines as $line) {
            // Only write the line if there is actual financial weight
            if ($line['debit'] > 0 || $line['credit'] > 0) {
                DB::table('journal_lines')->insert([
                    'journal_entry_id' => $entryId, 
                    'account_id' => $line['account_id'],
                    'memo' => $line['memo'] ?? null,
                    'debit_amount' => $line['debit'], 
                    'credit_amount' => $line['credit'], 
                    'created_at' => now()
                ]);
            }
        }
    }

    /* --- THE EVENT TRIGGERS --- */

    // 1. REVENUE ACCRUAL (Invoice Issued)
    public static function recordInvoiceIssued($invoiceId)
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
        $project = DB::table('projects')->where('id', $invoice->project_id)->first();
        $org = DB::table('organizations')->where('id', $project->organization_id)->first();

        // Using our new hyphenated Client AR mapping logic
        $arAccount = self::getAccountId('1100', $project->organization_id);
        $vatAccount = self::getAccountId('2300');
        
        // Assume deferred revenue first until paid
        $revenueAccount = self::getAccountId('2100'); 

        self::postDoubleEntry(
            'JRN-INV-' . $invoice->invoice_number, 
            "Accrual of Invoice {$invoice->invoice_number} for {$org->name}", 
            'Invoicing', 
            $invoiceId, 
            $invoice->region_code ?? 'GLOBAL',
            [
                ['account_id' => $arAccount, 'memo' => 'Accounts Receivable Accrual', 'debit' => $invoice->total_amount, 'credit' => 0.00],
                ['account_id' => $revenueAccount, 'memo' => 'Deferred Service Revenue', 'debit' => 0.00, 'credit' => $invoice->amount_due],
                ['account_id' => $vatAccount, 'memo' => 'VAT Payable Accrual', 'debit' => 0.00, 'credit' => $invoice->vat_amount],
            ]
        );
    }

    // 2. EXPENSE REALIZATION (Cash Request Cleared)
    public static function recordExpenseCleared($requestId)
    {
        $request = DB::table('cash_requests')->where('id', $requestId)->first();
        
        // Consult the MDM Dictionary to find exactly where this hits the P&L
        $dictItem = DB::table('mdm_financial_dictionary')->where('id', $request->financial_dictionary_id)->first();
        $expenseAccountId = $dictItem->expense_account_id ?? self::getAccountId('6300'); // Fallback to Gen Admin
        
        // Find which bank account paid for it
        $remittance = DB::table('mdm_remittance_accounts')->where('id', $request->remittance_account_id)->first();
        $cashAccountId = $remittance->ledger_account_id ?? self::getAccountId('1010');

        self::postDoubleEntry(
            'JRN-EXP-' . $request->reference, 
            "Expense Execution: {$request->purpose}", 
            'Treasury', 
            $requestId, 
            'GLOBAL',
            [
                ['account_id' => $expenseAccountId, 'memo' => $dictItem->description ?? 'Operational Expense', 'debit' => $request->amount_requested, 'credit' => 0.00],
                ['account_id' => $cashAccountId, 'memo' => 'Cash Disbursed', 'debit' => 0.00, 'credit' => $request->amount_requested]
            ]
        );
    }

    // 3. PAYROLL EXECUTION
    public static function recordPayrollDisbursed($payrollRunId)
    {
        $run = DB::table('payroll_runs')->where('id', $payrollRunId)->first();
        
        $salaryExpAccount = self::getAccountId('6000');
        $payeAccount = self::getAccountId('2400');
        $cashAccount = self::getAccountId('1010'); // Or dynamic if multi-bank

        self::postDoubleEntry(
            'JRN-PAY-' . $run->reference_number, 
            "Human Capital Payroll Disbursed - " . date('F Y', strtotime($run->pay_period_end)), 
            'Payroll', 
            $payrollRunId, 
            $run->region_code,
            [
                ['account_id' => $salaryExpAccount, 'memo' => 'Gross Payroll Expense', 'debit' => $run->total_gross, 'credit' => 0.00],
                ['account_id' => $payeAccount, 'memo' => 'Total Tax/Statutory Withheld', 'debit' => 0.00, 'credit' => $run->total_deductions],
                ['account_id' => $cashAccount, 'memo' => 'Net Cash Paid to Talent', 'debit' => 0.00, 'credit' => $run->total_net]
            ]
        );
    }

    public function render()
    {
        return view('livewire.hq.finance.general-ledger-manager');
    }
}