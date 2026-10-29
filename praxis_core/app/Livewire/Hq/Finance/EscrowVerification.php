<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class EscrowVerification extends Component
{
    public $activeTab = 'Pending Verification'; // Pending Verification, Verified, Rejected
    public $transactions = [];
    
    // Verification Modal State (set on the server; approve/reject re-read the payment from the database)
    public $showVerifyModal = false;
    #[Locked]
    public $selectedTx = null;
    #[Locked]
    public $linkedInvoice = null;
    
    // Admin Inputs
    public $tolerance_writeoff = 0.00;
    public $extend_guillotine_days = 0;

    public function mount()
    {
        $this->loadTransactions();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadTransactions();
    }

    public function loadTransactions()
    {
        $this->transactions = DB::table('transactions')
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->join('projects', 'invoices.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->select(
                'transactions.*',
                'invoices.invoice_number',
                'invoices.total_amount',
                'invoices.currency',
                'invoices.status as invoice_status',
                'invoices.due_date',
                'projects.title as project_title',
                'organizations.name as client_name',
                'organizations.id as org_id'
            )
            ->where('transactions.status', $this->activeTab)
            ->orderBy('transactions.created_at', 'desc')
            ->get();
    }

    public function openVerifyModal($txId)
    {
        $this->selectedTx = collect($this->transactions)->firstWhere('id', $txId);
        if (!$this->selectedTx) return; // no longer in this tab (handled meanwhile)
        $this->linkedInvoice = DB::table('invoices')->where('id', $this->selectedTx->invoice_id)->first();
        
        // Auto-calculate suggested tolerance write-off (Shortfall)
        $expected = (float) $this->linkedInvoice->total_amount;
        $claimed = (float) $this->selectedTx->amount_claimed;
        $wht = (float) $this->selectedTx->wht_deducted;
        
        $shortfall = $expected - ($claimed + $wht);
        
        // Suggest up to 50 as a valid network/bank fee write-off. Anything larger requires manual input.
        $this->tolerance_writeoff = ($shortfall > 0 && $shortfall <= 50) ? $shortfall : 0.00; 
        
        $this->extend_guillotine_days = 0;
        $this->showVerifyModal = true;
    }

    public function approveTransaction()
    {
        abort_unless(Auth::user()->can('finance.approve'), 403);

        $this->validate([
            'tolerance_writeoff' => 'required|numeric|min:0',
            'extend_guillotine_days' => 'required|integer|min:0'
        ]);

        // Amounts come from the database, not from the page: only the write-off and extension are inputs
        $error = DB::transaction(function () {
            $tx = DB::table('transactions')->where('id', $this->selectedTx->id ?? null)->lockForUpdate()->first();
            if (!$tx || $tx->status !== 'Pending Verification') {
                return 'This payment has already been verified or rejected, so nothing was changed. The list has been refreshed.';
            }
            $invoice = DB::table('invoices')->where('id', $tx->invoice_id)->lockForUpdate()->first();
            if (!$invoice || $invoice->status === 'Cancelled') {
                return "The invoice for this payment is cancelled, so the payment can't be verified. Mark the payment as not verified, or create a new invoice.";
            }

            // 1. Mark Transaction as Verified and lock the write-off amount
            DB::table('transactions')->where('id', $tx->id)->update([
                'status' => 'Verified',
                'tolerance_writeoff' => $this->tolerance_writeoff,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'updated_at' => now()
            ]);

            // 2. Is the invoice now fully cleared? Count every verified payment, not just this one
            $verified = DB::table('transactions')->where('invoice_id', $invoice->id)->where('status', 'Verified')
                ->selectRaw('COALESCE(SUM(amount_claimed + COALESCE(wht_deducted, 0) + COALESCE(tolerance_writeoff, 0)), 0) as settled, COALESCE(SUM(wht_deducted), 0) as wht')
                ->first();

            if ((float) $verified->settled >= (float) $invoice->total_amount) {
                DB::table('invoices')->where('id', $invoice->id)->update([
                    'status' => 'Paid',
                    'wht_amount' => $verified->wht, // Sync WHT to invoice
                    'paid_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                // Partial payment: the invoice stays unpaid, but its due date can be extended
                $updateData = ['wht_amount' => $verified->wht, 'updated_at' => now()];

                if ($this->extend_guillotine_days > 0) {
                    $updateData['status'] = 'Pending'; // Restores portal access if it was overdue
                    $updateData['due_date'] = Carbon::parse($invoice->due_date)->addDays($this->extend_guillotine_days);
                }

                DB::table('invoices')->where('id', $invoice->id)->update($updateData);
            }


            // 4. Audit trail
            DB::table('system_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'ESCROW_PAYMENT_VERIFIED',
                'description' => "Verified payment {$tx->id}. Write-off: {$this->tolerance_writeoff}. " . ($this->extend_guillotine_days > 0 ? "Due date extended by {$this->extend_guillotine_days} days." : ""),
                'target_table' => 'transactions',
                'target_id' => $tx->id,
                'created_at' => now()
            ]);

            // 5. Double-entry receipt in the general ledger
            \App\Livewire\Hq\Finance\GeneralLedgerManager::recordInvoicePaid($tx->id);

            return null;
        });

        if ($error) {
            $this->showVerifyModal = false;
            $this->loadTransactions();
            session()->flash('error', $error);
            return;
        }

        $this->showVerifyModal = false;
        $this->loadTransactions();
        session()->flash('success', 'Payment verified and recorded in the general ledger.');
    }

    public function rejectTransaction()
    {
        abort_unless(Auth::user()->can('finance.approve'), 403);

        $handled = DB::transaction(function () {
            // Only a payment still awaiting verification can be rejected (a verified one is already in the ledger)
            $updated = DB::table('transactions')->where('id', $this->selectedTx->id ?? null)->where('status', 'Pending Verification')->update([
                'status' => 'Rejected',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'updated_at' => now()
            ]);
            if (!$updated) {
                return false;
            }

            DB::table('system_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'ESCROW_PAYMENT_REJECTED',
                'target_table' => 'transactions',
                'target_id' => $this->selectedTx->id,
                'created_at' => now()
            ]);

            return true;
        });

        if (!$handled) {
            $this->showVerifyModal = false;
            $this->loadTransactions();
            session()->flash('error', 'This payment has already been verified or rejected, so nothing was changed. The list has been refreshed.');
            return;
        }

        $this->showVerifyModal = false;
        $this->loadTransactions();
        session()->flash('error', 'Payment marked as not verified. The invoice stays unpaid; let the client know what to send instead.');
    }

    public function render()
    {
        return view('livewire.hq.finance.escrow-verification');
    }
}