<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Support\Access;

#[Layout('components.layouts.hq')]
class CashRequestSubmitter extends Component
{
    use WithFileUploads;

    public $activeTab = 'request'; // 'request' or 'ledger'

    // The form's choices and the person's requests are read when the page renders (never kept in the page)

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
    
    // Signature & file state
    public $requester_signature = null; 
    public $showSuccessModal = false;
    public $generatedReference = '';
    public $receipt_file; 

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    /** New request: the active projects and expense items to choose from */
    private function masterData(): array
    {
        try {
            return [
                DB::table('projects')->where('status', 'Active')->select('id', 'title')->get(),
                DB::table('mdm_financial_dictionary')->where('is_active', 1)->orderBy('category')->select('id', 'item_code', 'description')->get(),
            ];
        } catch (\Exception $e) {
            return [collect([]), collect([])];
        }
    }

    /** My requests: what the list shows, without the signature image (a base64 PNG of 5-30 KB) */
    private function myRequests()
    {
        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c';

        return DB::table('cash_requests')
            ->join('mdm_financial_dictionary', 'cash_requests.financial_dictionary_id', '=', 'mdm_financial_dictionary.id')
            ->where('cash_requests.requester_id', $userId)
            ->select('cash_requests.id', 'cash_requests.reference', 'cash_requests.created_at', 'cash_requests.purpose', 'cash_requests.currency',
                'cash_requests.amount_requested', 'cash_requests.status', 'cash_requests.receipt_url', 'mdm_financial_dictionary.category')
            ->orderBy('cash_requests.created_at', 'desc')
            ->get();
    }

    public function updatedDisbursementMethod()
    {
        $this->reset(['payee_bank', 'payee_account_name', 'payee_account_number', 'momo_phone', 'momo_name', 'crypto_address']);
    }

    public function submitRequest()
    {
        abort_unless(Auth::user()->can('expenses.create'), 403);

        $final_details = '';
        if ($this->disbursement_method === 'Bank Transfer') {
            $this->validate(['payee_bank' => 'required', 'payee_account_name' => 'required', 'payee_account_number' => 'required'], [], [
                'payee_bank' => 'bank name', 'payee_account_name' => 'account name', 'payee_account_number' => 'account number',
            ]);
            $final_details = "Bank: {$this->payee_bank} | Name: {$this->payee_account_name} | Acct: {$this->payee_account_number}";
        } elseif ($this->disbursement_method === 'Mobile Money') {
            $this->validate(['momo_provider' => 'required', 'momo_name' => 'required', 'momo_phone' => 'required'], [], [
                'momo_provider' => 'provider', 'momo_name' => 'name on the account', 'momo_phone' => 'phone number',
            ]);
            $final_details = "Provider: {$this->momo_provider} | Name: {$this->momo_name} | Phone: {$this->momo_phone}";
        } elseif ($this->disbursement_method === 'Crypto Wallet') {
            $this->validate(['crypto_network' => 'required', 'crypto_address' => 'required'], [], [
                'crypto_network' => 'network', 'crypto_address' => 'wallet address',
            ]);
            $final_details = "Network: {$this->crypto_network} | Address: {$this->crypto_address}";
        } else {
            $final_details = "Physical Cash - HQ Delivery";
        }

        $this->validate([
            'financial_dictionary_id' => 'required',
            'amount_requested' => 'required|numeric|min:0.01',
            'purpose' => 'required|string|max:1000',
            'requester_signature' => 'required|string'
        ], [
            'requester_signature.required' => 'Sign in the signature box, then send the request again.',
        ], [
            'financial_dictionary_id' => 'expense item',
            'amount_requested' => 'amount',
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

        // Every approver hears about it (one insert for all of them)
        $this->notifyApprovers('Cash request to approve', "Cash request {$this->generatedReference} needs approval. Review it in Cash request approvals.");

        $this->requester_signature = null; // stored with the request; no need to carry it in the page any more
        $this->showSuccessModal = true;
    }

    public function uploadReceipt($requestId)
    {
        abort_unless(Auth::user()->can('expenses.create'), 403);

        // Staff upload receipts for their own requests only
        $req = DB::table('cash_requests')->where('id', $requestId)->where('requester_id', Auth::id())->first();
        abort_unless($req, 403);

        $this->validate([
            'receipt_file' => 'required|image|max:5120',
        ]);

        $path = $this->receipt_file->store('receipts', 'local');

        DB::table('cash_requests')->where('id', $requestId)->update([
            'receipt_url' => $path,
            'updated_at' => now()
        ]);

        // Notify Admins that Receipt is ready for Audit Clearance
        $this->notifyApprovers('Receipt uploaded', "A receipt was uploaded for cash request {$req->reference}. Check it and close the request in Cash request approvals.");

        $this->receipt_file = null;
        session()->flash('message-'.$requestId, 'Receipt uploaded. Finance will check it and close the request.');
    }

    /** One notification per approver, written in a single insert */
    private function notifyApprovers(string $title, string $message): void
    {
        $rows = array_map(fn ($adminId) => [
            'id' => Str::uuid()->toString(), 'user_id' => $adminId, 'type' => 'Financial', 'title' => $title,
            'message' => $message, 'link_url' => route('hq.finance.manager'), 'is_read' => 0, 'created_at' => now(),
        ], array_values(Access::staffWith('expenses.approve')));
        if ($rows) {
            DB::table('notifications')->insert($rows);
        }
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
        $this->setTab('ledger'); // Auto-redirect to their ledger to see it
    }

    public function render()
    {
        // Only what the open tab shows
        [$projects, $dictionaryItems] = $this->activeTab === 'request' ? $this->masterData() : [collect(), collect()];

        return view('livewire.hq.finance.cash-request-submitter', [
            'projects' => $projects,
            'dictionaryItems' => $dictionaryItems,
            'myRequests' => $this->activeTab === 'ledger' ? $this->myRequests() : collect(),
        ]);
    }
}