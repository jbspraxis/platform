<?php

namespace App\Livewire\Hq\Mdm;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Exception;

#[Layout('components.layouts.hq')]
class RemittanceAccounts extends Component
{
    public $accounts = [];
    
    // Form State
    public $editMode = false;
    public $recordId = null;
    
    public $type = 'Bank';
    public $provider_name = '';
    public $account_name = '';
    public $account_reference = '';
    public $routing_code = '';

    // UX State
    public $showSuccessModal = false;
    public $successMessage = '';

    public function mount()
    {
        $this->loadAccounts();
    }

    public function loadAccounts()
    {
        // Join with COA to pull the dynamically assigned ledger code for the UI
        $this->accounts = DB::table('mdm_remittance_accounts as ra')
            ->leftJoin('chart_of_accounts as coa', 'ra.ledger_account_id', '=', 'coa.id')
            ->select('ra.*', 'coa.account_code as ledger_code')
            ->orderBy('ra.type')
            ->orderBy('ra.provider_name')
            ->get();
    }

    public function saveAccount()
    {
        $this->validate([
            'type' => 'required|in:Bank,Mobile Money,Crypto,Cash',
            'provider_name' => 'required|string|max:150',
            'account_name' => 'required|string|max:150',
            'account_reference' => 'required|string|max:255',
            'routing_code' => 'nullable|string|max:50',
        ]);

        $data = [
            'type' => $this->type,
            'provider_name' => $this->provider_name,
            'account_name' => $this->account_name,
            'account_reference' => $this->account_reference,
            'routing_code' => $this->routing_code,
            'updated_at' => now()
        ];

        try {
            DB::transaction(function () use ($data) {
                if ($this->editMode && $this->recordId) {
                    // 1. Get the existing remittance record to find its ledger_account_id
                    $existing = DB::table('mdm_remittance_accounts')->where('id', $this->recordId)->first();
                    
                    // 2. Update the Remittance Table
                    DB::table('mdm_remittance_accounts')->where('id', $this->recordId)->update($data);
                    
                    // 3. Keep the Chart of Accounts synchronized (Update Name)
                    if ($existing && $existing->ledger_account_id) {
                        DB::table('chart_of_accounts')->where('id', $existing->ledger_account_id)->update([
                            'account_name' => $this->provider_name . ' (' . $this->account_name . ')',
                        ]);
                    }
                    
                    $this->successMessage = "Treasury account and linked ledger updated securely.";
                } else {
                    // Check for exact duplicate reference
                    $exists = DB::table('mdm_remittance_accounts')->where('account_reference', $this->account_reference)->exists();
                    if ($exists) {
                        throw new Exception('This account reference/wallet already exists in the ledger.');
                    }

                    // --- ACCOUNTING AUTOMATION: Create the Sub-Account ---
                    
                    // A. Map the UI type to the Parent Account Code
                    $parentMap = [
                        'Cash' => '1000',
                        'Bank' => '1010',
                        'Crypto' => '1020',
                        'Mobile Money' => '1030',
                    ];
                    $parentCode = $parentMap[$this->type];
                    
                    // B. Fetch Parent ID
                    $parentAccount = DB::table('chart_of_accounts')->where('account_code', $parentCode)->first();
                    if (!$parentAccount) throw new Exception("Critical Error: Master Parent Account {$parentCode} missing.");

                    // C. Auto-Generate the next logical Account Code (e.g., if 1011 exists, make 1012)
                    $latestChild = DB::table('chart_of_accounts')
                        ->where('parent_account_id', $parentAccount->id)
                        ->orderByRaw('CAST(account_code AS UNSIGNED) DESC')
                        ->first();

                    $newCode = $latestChild ? (string)((int)$latestChild->account_code + 1) : (string)((int)$parentCode + 1);

                    // D. Insert into Chart of Accounts
                    $coaId = DB::table('chart_of_accounts')->insertGetId([
                        'parent_account_id' => $parentAccount->id,
                        'account_code' => $newCode,
                        'account_name' => $this->provider_name . ' (' . $this->account_name . ')',
                        'account_type' => 'Asset', // Treasury accounts are always Assets
                        'is_active' => 1,
                        'is_system_locked' => 0, // Unlocked because it's a dynamic sub-account
                        'created_at' => now(),
                    ]);

                    // E. Insert into Remittance with the new foreign key
                    $data['ledger_account_id'] = $coaId;
                    $data['is_active'] = 1;
                    $data['created_at'] = now();
                    DB::table('mdm_remittance_accounts')->insert($data);
                    
                    $this->successMessage = "New treasury node registered and sub-account [{$newCode}] created.";
                }
            });

            $this->resetForm();
            $this->loadAccounts();
            $this->showSuccessModal = true;

        } catch (Exception $e) {
            $this->addError('account_reference', $e->getMessage());
        }
    }

    public function editAccount($id)
    {
        $record = DB::table('mdm_remittance_accounts')->where('id', $id)->first();
        if ($record) {
            $this->editMode = true;
            $this->recordId = $record->id;
            $this->type = $record->type;
            $this->provider_name = $record->provider_name;
            $this->account_name = $record->account_name;
            $this->account_reference = $record->account_reference;
            $this->routing_code = $record->routing_code;
        }
    }

    public function toggleStatus($id, $currentStatus)
    {
        DB::transaction(function () use ($id, $currentStatus) {
            $newStatus = !$currentStatus;
            
            // 1. Update Remittance record
            DB::table('mdm_remittance_accounts')->where('id', $id)->update([
                'is_active' => $newStatus,
                'updated_at' => now()
            ]);

            // 2. Mirror status to Chart of Accounts so accountants can't post to frozen accounts
            $record = DB::table('mdm_remittance_accounts')->where('id', $id)->first();
            if ($record && $record->ledger_account_id) {
                DB::table('chart_of_accounts')->where('id', $record->ledger_account_id)->update([
                    'is_active' => $newStatus
                ]);
            }
        });
        
        $this->loadAccounts();
    }

    public function resetForm()
    {
        $this->reset(['editMode', 'recordId', 'provider_name', 'account_name', 'account_reference', 'routing_code']);
        $this->type = 'Bank';
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    public function render()
    {
        return view('livewire.hq.mdm.remittance-accounts');
    }
}