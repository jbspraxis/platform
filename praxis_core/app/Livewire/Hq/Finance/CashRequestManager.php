<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class CashRequestManager extends Component
{
    use WithFileUploads;

    public $activeTab = 'pending_auth'; // pending_auth, pending_clearance, history
    
    public $requests = [];
    public $selectedRequest = null;

    // Authorization State (Seal B)
    public $showAuthModal = false;
    public $authorizer_signature = null;

    // Audit Clearance State
    public $showClearanceModal = false;
    public $receipt_file;

    public function mount()
    {
        $this->loadRequests();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadRequests();
    }

    public function loadRequests()
    {
        $query = DB::table('cash_requests')
            ->join('users', 'cash_requests.requester_id', '=', 'users.id')
            ->join('mdm_financial_dictionary', 'cash_requests.financial_dictionary_id', '=', 'mdm_financial_dictionary.id')
            ->leftJoin('projects', 'cash_requests.project_id', '=', 'projects.id')
            ->select(
                'cash_requests.*', 
                'users.first_name', 'users.last_name', 
                'mdm_financial_dictionary.item_code', 'mdm_financial_dictionary.category',
                'projects.title as project_title'
            )
            ->orderBy('cash_requests.created_at', 'desc');

        if ($this->activeTab === 'pending_auth') {
            $query->where('cash_requests.status', 'Pending Auth');
        } elseif ($this->activeTab === 'pending_clearance') {
            $query->where('cash_requests.status', 'Authorized');
        } else {
            $query->whereIn('cash_requests.status', ['Cleared', 'Rejected']);
        }

        $this->requests = $query->get();
    }

    public function openAuthModal($id)
    {
        $this->selectedRequest = collect($this->requests)->firstWhere('id', $id);
        $this->authorizer_signature = null;
        $this->showAuthModal = true;
        
        // Tells the frontend to reset the canvas
        $this->dispatch('clear-canvas'); 
    }

    public function authorizeRequest()
    {
        // UX micro-delay
        sleep(1);

        $this->validate([
            'authorizer_signature' => 'required|string'
        ], [
            'authorizer_signature.required' => 'Cryptographic signature is required to authorize the deployment of funds.'
        ]);

        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c';
        $ipAddress = request()->ip();
        $timestamp = now()->toDateTimeString();

        // Generate Dual-Seal Hash (Seal B)
        $rawData = $this->selectedRequest->reference . $userId . $ipAddress . $timestamp . substr($this->authorizer_signature, -50);
        $hash = hash('sha256', $rawData);

        // Update Ledger
        DB::table('cash_requests')->where('id', $this->selectedRequest->id)->update([
            'status' => 'Authorized',
            'authorizer_id' => $userId,
            'authorizer_signature' => $this->authorizer_signature,
            'authorizer_ip' => $ipAddress,
            'authorizer_hash' => $hash,
            'authorized_at' => now(),
            'updated_at' => now(),
        ]);

        // Log System Event
        DB::table('system_audit_logs')->insert([
            'user_id' => $userId,
            'action' => 'AUTHORIZED_CASH_REQUEST',
            'target_table' => 'cash_requests',
            'ip_address' => $ipAddress,
            'created_at' => now()
        ]);

        // --- NOTIFICATION ENGINE ---
        // Notify the Requester that their funds are authorized
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->selectedRequest->requester_id,
            'type' => 'System',
            'title' => 'FUNDS AUTHORIZED',
            'message' => "Request {$this->selectedRequest->reference} has been sealed and authorized for disbursement.",
            'link_url' => '#',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        $this->showAuthModal = false;
        $this->loadRequests();
    }

    public function rejectRequest()
    {
        DB::table('cash_requests')->where('id', $this->selectedRequest->id)->update([
            'status' => 'Rejected',
            'updated_at' => now(),
        ]);

        // Notify Requester of Rejection
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->selectedRequest->requester_id,
            'type' => 'System',
            'title' => 'REQUEST REJECTED',
            'message' => "Request {$this->selectedRequest->reference} has been rejected by Super Admin.",
            'link_url' => '#',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        $this->showAuthModal = false;
        $this->loadRequests();
    }

    // --- AUDIT CLEARANCE (Part 3) ---

    public function openClearanceModal($id)
    {
        $this->selectedRequest = collect($this->requests)->firstWhere('id', $id);
        $this->receipt_file = null;
        $this->showClearanceModal = true;
    }

    public function uploadReceipt()
    {
        $this->validate([
            'receipt_file' => 'required|image|max:5120', // 5MB max
        ]);

        $path = $this->receipt_file->store('receipts', 'local');

        DB::table('cash_requests')->where('id', $this->selectedRequest->id)->update([
            'receipt_url' => $path,
            'updated_at' => now()
        ]);

        $this->selectedRequest->receipt_url = $path; // Update local state to immediately show button
        session()->flash('message', 'Receipt uploaded securely to the Vault.');
    }

    public function clearLedger()
    {
        $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c';

        DB::transaction(function () use ($userId) {
            
            // 1. Clear the Cash Request
            DB::table('cash_requests')->where('id', $this->selectedRequest->id)->update([
                'status' => 'Cleared',
                'cleared_by' => $userId,
                'cleared_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. FINALIZATION: Deduct from the Project Budget spent column
            if ($this->selectedRequest->project_id && $this->selectedRequest->financial_dictionary_id) {
                DB::table('project_budgets')
                    ->where('project_id', $this->selectedRequest->project_id)
                    ->where('dict_id', $this->selectedRequest->financial_dictionary_id)
                    ->increment('total_spent', $this->selectedRequest->amount_requested);
            }
            
            // Phase 10 will add the strict IFRS Double-Entry Journal execution here later.
        });

        $this->showClearanceModal = false;
        $this->loadRequests();
    }

    public function render()
    {
        return view('livewire.hq.finance.cash-request-manager');
    }
}