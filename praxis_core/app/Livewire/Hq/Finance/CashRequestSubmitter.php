<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class CashRequestSubmitter extends Component
{
    use WithFileUploads;

    public $activeTab = 'request'; // 'request' or 'ledger'

    public $projects = [];
    public $dictionaryItems = [];
    public $myRequests = [];
    
    // Core Form State
    public $project_id = '';
    public $financial_dictionary_id = '';
    public $amount_requested = '';
    public $currency = 'USD';
    public $purpose = '';
    
    // Dynamic Disbursement State
    public $disbursement_method = 'Bank Transfer';
    public $payee_bank = '';
    public $payee_account_name = '';
    public $payee_account_number = '';
    public $momo_provider = 'MTN';
    public $momo_phone = '';
    public $momo_name = '';
    public $crypto_network = 'TRC20';
    public $crypto_address = '';
    
    // Cryptographic & File State
    public $requester_signature = null; 
    public $showSuccessModal = false;
    public $generatedReference = '';
    public $receipt_file; 

    public function mount()
    {
        $this->loadMasterData();
        $this->loadMyRequests();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        if ($tab === 'ledger') $this->loadMyRequests();
    }

    public function loadMasterData()
    {
        try {
            $this->projects = DB::table('projects')->where('status', 'Active')->select('id', 'title', 'currency')->get();
            $this->dictionaryItems = DB::table('mdm_financial_dictionary')->where('is_active', 1)->orderBy('category')->get();
        } catch (\Exception $e) {
            $this->projects = collect([]);
            $this->dictionaryItems = collect([]);
        }
    }

    public function loadMyRequests()
    {
        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c';
        $this->myRequests = DB::table('cash_requests')
            ->join('mdm_financial_dictionary', 'cash_requests.financial_dictionary_id', '=', 'mdm_financial_dictionary.id')
            ->where('cash_requests.requester_id', $userId)
            ->select('cash_requests.*', 'mdm_financial_dictionary.item_code', 'mdm_financial_dictionary.category')
            ->orderBy('cash_requests.created_at', 'desc')
            ->get();
    }

    public function updatedDisbursementMethod()
    {
        $this->reset(['payee_bank', 'payee_account_name', 'payee_account_number', 'momo_phone', 'momo_name', 'crypto_address']);
    }

    public function submitRequest()
    {
        sleep(1);

        $final_details = '';
        if ($this->disbursement_method === 'Bank Transfer') {
            $this->validate(['payee_bank' => 'required', 'payee_account_name' => 'required', 'payee_account_number' => 'required']);
            $final_details = "Bank: {$this->payee_bank} | Name: {$this->payee_account_name} | Acct: {$this->payee_account_number}";
        } elseif ($this->disbursement_method === 'Mobile Money') {
            $this->validate(['momo_provider' => 'required', 'momo_name' => 'required', 'momo_phone' => 'required']);
            $final_details = "Provider: {$this->momo_provider} | Name: {$this->momo_name} | Phone: {$this->momo_phone}";
        } elseif ($this->disbursement_method === 'Crypto Wallet') {
            $this->validate(['crypto_network' => 'required', 'crypto_address' => 'required']);
            $final_details = "Network: {$this->crypto_network} | Address: {$this->crypto_address}";
        } else {
            $final_details = "Physical Cash - HQ Delivery";
        }

        $this->validate([
            'financial_dictionary_id' => 'required',
            'amount_requested' => 'required|numeric|min:0.01',
            'purpose' => 'required|string|max:1000',
            'requester_signature' => 'required|string'
        ]);

        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c';
        $ipAddress = request()->ip();
        $timestamp = now()->toDateTimeString();

        $datePrefix = now()->format('Ymd');
        $count = DB::table('cash_requests')->whereDate('created_at', now()->toDateString())->count() + 1;
        $this->generatedReference = 'EXP-' . $datePrefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $rawData = $this->generatedReference . $userId . $ipAddress . $timestamp . substr($this->requester_signature, -50);
        $hash = hash('sha256', $rawData);

        DB::table('cash_requests')->insert([
            'id' => Str::uuid()->toString(),
            'reference' => $this->generatedReference,
            'requester_id' => $userId,
            'project_id' => empty($this->project_id) ? null : $this->project_id,
            'financial_dictionary_id' => $this->financial_dictionary_id,
            'amount_requested' => $this->amount_requested,
            'currency' => $this->currency,
            'purpose' => $this->purpose,
            'disbursement_method' => $this->disbursement_method,
            'disbursement_details' => $final_details,
            'requester_signature' => $this->requester_signature,
            'requester_ip' => $ipAddress,
            'requester_hash' => $hash,
            'status' => 'Pending Auth',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('system_audit_logs')->insert([
            'user_id' => $userId, 'action' => 'SUBMITTED_CASH_REQUEST', 'target_table' => 'cash_requests', 'ip_address' => $ipAddress, 'created_at' => now()
        ]);

        $adminIds = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', 5)->pluck('users.id');
        foreach ($adminIds as $adminId) {
            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(), 'user_id' => $adminId, 'type' => 'Financial', 'title' => 'CAPITAL REQUISITION',
                'message' => "New request {$this->generatedReference} requires Super Admin seal.", 'link_url' => route('hq.finance.manager'), 'is_read' => 0, 'created_at' => now(),
            ]);
        }

        $this->showSuccessModal = true;
    }

    public function uploadReceipt($requestId)
    {
        $this->validate([
            'receipt_file' => 'required|image|max:5120',
        ]);

        $path = $this->receipt_file->store('receipts', 'public');

        DB::table('cash_requests')->where('id', $requestId)->update([
            'receipt_url' => $path,
            'updated_at' => now()
        ]);

        // Notify Admins that Receipt is ready for Audit Clearance
        $req = collect($this->myRequests)->firstWhere('id', $requestId);
        $adminIds = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', 5)->pluck('users.id');
        foreach ($adminIds as $adminId) {
            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(), 'user_id' => $adminId, 'type' => 'Financial', 'title' => 'RECEIPT UPLOADED',
                'message' => "Receipt uploaded for {$req->reference}. Ready for Audit Clearance.", 'link_url' => route('hq.finance.manager'), 'is_read' => 0, 'created_at' => now(),
            ]);
        }

        $this->receipt_file = null;
        $this->loadMyRequests();
        session()->flash('message-'.$requestId, 'Receipt uploaded successfully. Awaiting Super Admin Clearance.');
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
        $this->setTab('ledger'); // Auto-redirect to their ledger to see it
    }

    public function render()
    {
        return view('livewire.hq.finance.cash-request-submitter');
    }
}