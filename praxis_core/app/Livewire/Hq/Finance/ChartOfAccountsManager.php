<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Exception;

#[Layout('components.layouts.hq')]
class ChartOfAccountsManager extends Component
{
    public $accounts = [];
    public $parentAccounts = [];

    // Form State
    public $showModal = false;
    public $parent_account_id = '';
    public $account_name = '';
    public $account_type = 'Asset';

    public function mount()
    {
        $this->loadAccounts();
    }

    public function loadAccounts()
    {
        // Fetch and group all accounts by Type for the Frontend Matrix
        // Simple string sorting works perfectly for "1100" and "1100-0001"
        $rawAccounts = DB::table('chart_of_accounts')->orderBy('account_code', 'asc')->get();
        $this->accounts = collect($rawAccounts)->groupBy('account_type')->toArray();

        // Fetch only Master (Parent) accounts for the dropdown
        $this->parentAccounts = DB::table('chart_of_accounts')
            ->whereNull('parent_account_id')
            ->where('is_active', 1)
            ->orderBy('account_code', 'asc')
            ->get()
            ->toArray();
    }

    public function updatedParentAccountId($value)
    {
        // Auto-match the account type to the parent's type to prevent IFRS mismatch
        if (!empty($value)) {
            $parent = collect($this->parentAccounts)->firstWhere('id', $value);
            if ($parent) {
                $this->account_type = $parent->account_type;
            }
        }
    }

    public function saveAccount()
    {
        $this->validate([
            'account_name' => 'required|string|max:150',
            'account_type' => 'required|string',
            'parent_account_id' => 'nullable|exists:chart_of_accounts,id'
        ]);

        try {
            DB::transaction(function () {
                // Auto-generate the correct hyphenated code
                $newCode = $this->generateAccountSequence($this->parent_account_id, $this->account_type);

                DB::table('chart_of_accounts')->insert([
                    'account_code' => $newCode,
                    'account_name' => strtoupper($this->account_name),
                    'account_type' => $this->account_type,
                    'parent_account_id' => empty($this->parent_account_id) ? null : $this->parent_account_id,
                    'is_active' => 1,
                    'is_system_locked' => 0, // Manual accounts can be toggled
                    'created_at' => now()
                ]);

                session()->flash('success', '> SUB-LEDGER PROVISIONED SUCCESSFULLY: [' . $newCode . ']');
            });
        } catch (Exception $e) {
            session()->flash('error', '> SECURE LEDGER ERROR: ' . $e->getMessage());
        }

        $this->reset(['account_name', 'parent_account_id', 'showModal']);
        $this->account_type = 'Asset';
        $this->loadAccounts();
    }

    private function generateAccountSequence($parentId, $type)
    {
        if ($parentId) {
            $parent = DB::table('chart_of_accounts')->where('id', $parentId)->first();
            
            // Get the latest child code by sorting descending
            $lastChild = DB::table('chart_of_accounts')
                ->where('parent_account_id', $parentId)
                ->orderBy('account_code', 'desc')
                ->first();
            
            if ($lastChild && strpos($lastChild->account_code, '-') !== false) {
                // Extract the numeric part after the hyphen (e.g., '1100-0042' -> '0042')
                $parts = explode('-', $lastChild->account_code);
                $sequence = (int) end($parts);
                return $parent->account_code . '-' . str_pad($sequence + 1, 4, '0', STR_PAD_LEFT);
            } else {
                // First sub-account ever created for this parent
                return $parent->account_code . '-0001';
            }
        }

        // If creating a brand new Master Account (Extremely rare, but supported)
        $prefixes = ['Asset' => '1', 'Liability' => '2', 'Equity' => '3', 'Revenue' => '4', 'Expense' => '5'];
        $prefix = $prefixes[$type] ?? '9';

        $maxMasterCode = DB::table('chart_of_accounts')
            ->where('account_type', $type)
            ->whereNull('parent_account_id')
            ->max(DB::raw('CAST(account_code AS UNSIGNED)'));

        if ($maxMasterCode) {
            return (string)(floor($maxMasterCode / 100) * 100 + 100); // Jumps by 100
        }

        return $prefix . '000';
    }

    public function toggleStatus($id)
    {
        $acc = DB::table('chart_of_accounts')->where('id', $id)->first();
        
        if ($acc) {
            if ($acc->is_system_locked) {
                session()->flash('error', '> SYSTEM OVERRIDE DENIED. MASTER IFRS ACCOUNTS ARE IMMUTABLE.');
                return;
            }

            DB::table('chart_of_accounts')->where('id', $id)->update([
                'is_active' => !$acc->is_active
            ]);
            $this->loadAccounts();
        }
    }

    /**
     * THE AUTOMATED LISTENER (Static Method)
     * This will be called globally from ProposalGateway when an SOW is signed.
     */
    public static function provisionClientAccounts($clientId, $clientName)
    {
        $parentAR = DB::table('chart_of_accounts')->where('account_code', '1100')->first();

        if ($parentAR) {
            // Check if client already has an A/R sub-ledger
            $exists = DB::table('chart_of_accounts')
                ->where('client_id', $clientId)
                ->where('parent_account_id', $parentAR->id)
                ->exists();

            if (!$exists) {
                $lastChild = DB::table('chart_of_accounts')
                    ->where('parent_account_id', $parentAR->id)
                    ->orderBy('account_code', 'desc')
                    ->first();

                if ($lastChild && strpos($lastChild->account_code, '-') !== false) {
                    $parts = explode('-', $lastChild->account_code);
                    $sequence = (int) end($parts);
                    $newCode = $parentAR->account_code . '-' . str_pad($sequence + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $newCode = $parentAR->account_code . '-0001';
                }

                // Provision the immutable client ledger
                DB::table('chart_of_accounts')->insert([
                    'account_code' => $newCode,
                    'account_name' => 'A/R - ' . strtoupper($clientName),
                    'account_type' => 'Asset',
                    'parent_account_id' => $parentAR->id,
                    'client_id' => $clientId,
                    'is_active' => 1,
                    'is_system_locked' => 1, // Hard-locked to prevent accidental deletion of client records
                    'created_at' => now()
                ]);
            }
        }
    }

    public function render()
    {
        return view('livewire.hq.finance.chart-of-accounts-manager');
    }
}