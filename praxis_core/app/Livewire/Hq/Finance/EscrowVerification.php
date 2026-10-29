<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class EscrowVerification extends Component
{
    public $activeTab = 'Pending Verification'; // Pending Verification, Verified, Rejected
    public $transactions = [];
    
    // Verification Modal State
    public $showVerifyModal = false;
    public $selectedTx = null;
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

        DB::transaction(function () {
            // 1. Mark Transaction as Verified and lock the write-off amount
            DB::table('transactions')->where('id', $this->selectedTx->id)->update([
                'status' => 'Verified',
                'tolerance_writeoff' => $this->tolerance_writeoff,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'updated_at' => now()
            ]);

            $expected = (float) $this->linkedInvoice->total_amount;
            $totalCredited = (float) $this->selectedTx->amount_claimed + (float) $this->selectedTx->wht_deducted + (float) $this->tolerance_writeoff;

            // 2. Check if invoice is fully cleared
            if ($totalCredited >= $expected) {
                DB::table('invoices')->where('id', $this->linkedInvoice->id)->update([
                    'status' => 'Paid',
                    'wht_amount' => $this->selectedTx->wht_deducted, // Sync WHT to invoice
                    'paid_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                // Partial Payment Logic: Invoice remains Pending/Overdue, but Guillotine can be extended
                $updateData = ['wht_amount' => $this->selectedTx->wht_deducted, 'updated_at' => now()];
                
                if ($this->extend_guillotine_days > 0) {
                    $updateData['status'] = 'Pending'; // Defrost if it was overdue
                    $updateData['due_date'] = Carbon::parse($this->linkedInvoice->due_date)->addDays($this->extend_guillotine_days);
                }
                
                DB::table('invoices')->where('id', $this->linkedInvoice->id)->update($updateData);
            }


            // 4. Immutable Audit Trail
            DB::table('system_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'ESCROW_PAYMENT_VERIFIED',
                'description' => "Verified TX {$this->selectedTx->id}. Write-off: {$this->tolerance_writeoff}. " . ($this->extend_guillotine_days > 0 ? "Guillotine extended by {$this->extend_guillotine_days} days." : ""),
                'target_table' => 'transactions',
                'target_id' => $this->selectedTx->id,
                'created_at' => now()
            ]);
            
            // 5. TRIGGER IFRS DOUBLE-ENTRY (Phase 16 Hook Injection)
            \App\Livewire\Hq\Finance\GeneralLedgerManager::recordInvoicePaid($this->selectedTx->id);
        });

        $this->showVerifyModal = false;
        $this->loadTransactions();
        session()->flash('success', '> ESCROW VERIFICATION COMPLETE. FUNDS SECURED & LEDGER UPDATED.');
    }

    public function rejectTransaction()
    {
        abort_unless(Auth::user()->can('finance.approve'), 403);

        DB::transaction(function () {
            DB::table('transactions')->where('id', $this->selectedTx->id)->update([
                'status' => 'Rejected',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'updated_at' => now()
            ]);

            
            DB::table('system_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'ESCROW_PAYMENT_REJECTED',
                'target_table' => 'transactions',
                'target_id' => $this->selectedTx->id,
                'created_at' => now()
            ]);
        });

        $this->showVerifyModal = false;
        $this->loadTransactions();
        session()->flash('error', '> TRANSACTION REJECTED. CLIENT OS ALERTED.');
    }

    public function render()
    {
        return view('livewire.hq.finance.escrow-verification');
    }
}