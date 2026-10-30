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
        // A reversing entry voids a posted entry
        abort_unless(Auth::user()->can('finance.delete'), 403);

        $this->validate([
            'reversalEntryId' => 'required|exists:journal_entries,id',
            'reversalReason' => 'required|string|min:10'
        ], [], [
            'reversalEntryId' => 'journal entry',
            'reversalReason' => 'reason',
        ]);

        try {
            DB::transaction(function () {
                $originalEntry = DB::table('journal_entries')->where('id', $this->reversalEntryId)->first();
                
                if ($originalEntry->is_reversal) {
                    throw new \DomainException("This entry has already been reversed, or is itself a reversal, so it can’t be reversed again.");
                }

                $originalLines = DB::table('journal_lines')->where('journal_entry_id', $this->reversalEntryId)->get();

                // 1. Create the new Reversing Entry Header
                $newEntryId = (string) Str::uuid();
                $ref = 'REV-' . date('Ymd') . '-' . rand(1000, 9999);

                DB::table('journal_entries')->insert([
                    'id' => $newEntryId,
                    'reference_number' => $ref,
                    'entry_date' => now()->toDateString(),
                    'description' => "Reversal of {$originalEntry->reference_number}: " . $this->reversalReason,
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
                        'memo' => "Reversing: " . ($line->memo ?? 'original entry'),
                        'debit_amount' => $line->credit_amount,   // SWAP
                        'credit_amount' => $line->debit_amount,   // SWAP
                        'created_at' => now()
                    ]);
                }

                // 3. Mark original as reversed to lock it from future tampering
                DB::table('journal_entries')->where('id', $this->reversalEntryId)->update(['is_reversal' => 1]);

                // 4. Audit log
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
            session()->flash('success', 'Reversing entry posted. The original entry is now marked as reversed.');

        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t post the reversing entry, so nothing was changed. Try again, and if it still fails, ask an administrator to check the error log.");
        }
    }

    /* ====================================================================================
       IFRS posting helpers (static API)
       Call these from anywhere in the application to record a balanced double entry.
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
            throw new \Exception("Account {$code} isn’t in the chart of accounts. Add it in Chart of accounts, then try again.");
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
            throw new \Exception("The entry doesn’t balance: debits total {$totalDebit} and credits total {$totalCredit}. Nothing was posted.");
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
            "Accrual of invoice {$invoice->invoice_number} for {$org->name}", 
            'Invoicing', 
            $invoiceId, 
            $invoice->region_code ?? 'GLOBAL',
            [
                ['account_id' => $arAccount, 'memo' => 'Accounts receivable accrual', 'debit' => $invoice->total_amount, 'credit' => 0.00],
                ['account_id' => $revenueAccount, 'memo' => 'Deferred service revenue', 'debit' => 0.00, 'credit' => $invoice->amount_due],
                ['account_id' => $vatAccount, 'memo' => 'VAT payable accrual', 'debit' => 0.00, 'credit' => $invoice->vat_amount],
            ]
        );
    }

    // 1b. CASH RECEIPT (client payment verified in Payment verification)
    // Dr bank (cash received), WHT receivable (tax the client withheld) and bank fees (small
    // shortfall written off); Cr the client’s receivable, and any excess to client overpayments.
    // Revenue was deferred when the invoice was issued: the net-of-VAT share now settled is earned.
    public static function recordInvoicePaid($transactionId)
    {
        $tx = DB::table('transactions')->where('id', $transactionId)->first();
        $invoice = DB::table('invoices')->where('id', $tx->invoice_id)->first();
        $project = DB::table('projects')->where('id', $invoice->project_id)->first();

        // Invoices issued before every issuing screen posted the accrual: post it now
        $accrued = DB::table('journal_entries')->where('source_module', 'Invoicing')->where('source_id', $invoice->id)->exists();
        if (!$accrued) {
            self::recordInvoiceIssued($invoice->id);
        }

        $arAccount = self::getAccountId('1100', $project->organization_id);
        $revenueAccount = self::getAccountId('4000');
        $remittance = $invoice->remittance_account_id ? DB::table('mdm_remittance_accounts')->where('id', $invoice->remittance_account_id)->first() : null;
        $cashAccount = $remittance->ledger_account_id ?? self::getAccountId($tx->method === 'USDT' ? '1020' : '1010');

        $cash = round((float) $tx->amount_claimed, 2);
        $wht = round((float) $tx->wht_deducted, 2);
        $writeOff = round((float) $tx->tolerance_writeoff, 2);
        $settled = round($cash + $wht + $writeOff, 2);

        // What earlier receipts for this invoice already cleared and recognised
        $prefix = 'JRN-RCPT-' . $invoice->invoice_number . '-';
        $earlier = fn ($accountId, $column) => (float) DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.reference_number', 'like', $prefix . '%')
            ->where('journal_lines.account_id', $accountId)
            ->sum("journal_lines.{$column}");
        $arCleared = $earlier($arAccount, 'credit_amount');
        $recognised = $earlier($revenueAccount, 'credit_amount');

        $total = (float) $invoice->total_amount;
        $toAr = round(min($settled, max(0, $total - $arCleared)), 2);
        $overpaid = round($settled - $toAr, 2);

        $net = (float) $invoice->amount_due;
        $stillDeferred = max(0, $net - $recognised);
        $earned = $total > 0 ? round(min($net * $toAr / $total, $stillDeferred), 2) : 0.00;
        if ($toAr > 0 && round($arCleared + $toAr, 2) >= round($total, 2)) {
            $earned = round($stillDeferred, 2); // final receipt: no pennies left deferred by rounding
        }

        self::postDoubleEntry(
            $prefix . substr($transactionId, 0, 8),
            "Receipt for invoice {$invoice->invoice_number} ({$tx->method}, ref {$tx->tx_hash_or_ref})",
            'Receipts',
            $transactionId,
            $invoice->region_code ?? 'GLOBAL',
            [
                ['account_id' => $cashAccount, 'memo' => 'Client payment received', 'debit' => $cash, 'credit' => 0.00],
                ['account_id' => self::getAccountId('1300'), 'memo' => 'WHT withheld by client', 'debit' => $wht, 'credit' => 0.00],
                ['account_id' => self::getAccountId('6950'), 'memo' => 'Shortfall written off (bank/network fees)', 'debit' => $writeOff, 'credit' => 0.00],
                ['account_id' => $arAccount, 'memo' => 'Accounts receivable settled', 'debit' => 0.00, 'credit' => $toAr],
                ['account_id' => self::getAccountId('2500'), 'memo' => 'Client overpayment held', 'debit' => 0.00, 'credit' => $overpaid],
                ['account_id' => self::getAccountId('2100'), 'memo' => 'Deferred revenue released', 'debit' => $earned, 'credit' => 0.00],
                ['account_id' => $revenueAccount, 'memo' => 'Service revenue earned', 'debit' => 0.00, 'credit' => $earned],
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
            "Cash request: {$request->purpose}", 
            'Treasury', 
            $requestId, 
            'GLOBAL',
            [
                ['account_id' => $expenseAccountId, 'memo' => $dictItem->description ?? 'Operating expense', 'debit' => $request->amount_requested, 'credit' => 0.00],
                ['account_id' => $cashAccountId, 'memo' => 'Cash paid out', 'debit' => 0.00, 'credit' => $request->amount_requested]
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
            "Payroll paid for " . date('F Y', strtotime($run->pay_period_end)), 
            'Payroll', 
            $payrollRunId, 
            $run->region_code,
            [
                ['account_id' => $salaryExpAccount, 'memo' => 'Gross payroll expense', 'debit' => $run->total_gross, 'credit' => 0.00],
                ['account_id' => $payeAccount, 'memo' => 'Tax and statutory deductions withheld', 'debit' => 0.00, 'credit' => $run->total_deductions],
                ['account_id' => $cashAccount, 'memo' => 'Net pay to staff', 'debit' => 0.00, 'credit' => $run->total_net]
            ]
        );
    }

    public function render()
    {
        return view('livewire.hq.finance.general-ledger-manager');
    }
}