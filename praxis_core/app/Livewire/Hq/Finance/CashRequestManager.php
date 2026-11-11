<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Layout('components.layouts.hq')]
class CashRequestManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $activeTab = 'pending_auth'; // pending_auth, pending_clearance, history

    /** Closed and rejected requests shown per page (the history only grows) */
    private const HISTORY_PER_PAGE = 25;

    // The request open in a dialog; its row is read again from the database on each round trip (never kept in the page)
    #[Locked]
    public ?string $selectedId = null;

    // Authorization State (Seal B)
    public $showAuthModal = false;
    public $authorizer_signature = null;

    // Audit Clearance State
    public $showClearanceModal = false;
    public $receipt_file;

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    /**
     * Cash requests with who asked and what for. Never the two signature images (base64 PNGs of 5-30 KB each): no
     * list or dialog shows them, and they would travel with every click.
     */
    private function rows()
    {
        return DB::table('cash_requests')
            ->join('users', 'cash_requests.requester_id', '=', 'users.id')
            ->join('mdm_financial_dictionary', 'cash_requests.financial_dictionary_id', '=', 'mdm_financial_dictionary.id')
            ->leftJoin('projects', 'cash_requests.project_id', '=', 'projects.id')
            ->select(
                'cash_requests.id', 'cash_requests.reference', 'cash_requests.requester_id', 'cash_requests.project_id',
                'cash_requests.financial_dictionary_id', 'cash_requests.amount_requested', 'cash_requests.currency', 'cash_requests.purpose',
                'cash_requests.disbursement_method', 'cash_requests.disbursement_details', 'cash_requests.status', 'cash_requests.receipt_url',
                'cash_requests.created_at',
                'users.first_name', 'users.last_name',
                'mdm_financial_dictionary.item_code', 'mdm_financial_dictionary.category',
                'projects.title as project_title'
            );
    }

    /** The current tab's requests, newest first */
    private function tabRows()
    {
        $query = $this->rows()->orderBy('cash_requests.created_at', 'desc');

        if ($this->activeTab === 'pending_auth') {
            $query->where('cash_requests.status', 'Pending Auth');
        } elseif ($this->activeTab === 'pending_clearance') {
            $query->where('cash_requests.status', 'Authorized');
        } else {
            $query->whereIn('cash_requests.status', ['Cleared', 'Rejected']);
        }

        return $query;
    }

    /** The request open in a dialog, as it is now in the database (null when none, or gone) */
    private function selected(): ?object
    {
        return $this->selectedId ? $this->rows()->where('cash_requests.id', $this->selectedId)->first() : null;
    }

    /** A dialog opens on a request of the tab it was clicked in */
    private function select($id): void
    {
        $this->selectedId = $this->tabRows()->where('cash_requests.id', (string) $id)->value('cash_requests.id');
    }

    public function openAuthModal($id)
    {
        $this->select($id);
        $this->authorizer_signature = null;
        $this->showAuthModal = true;
        
        // Tells the frontend to reset the canvas
        $this->dispatch('clear-canvas'); 
    }

    public function authorizeRequest()
    {
        abort_unless(Auth::user()->can('expenses.approve'), 403);

        $this->validate([
            'authorizer_signature' => 'required|string'
        ], [
            'authorizer_signature.required' => 'Sign in the signature box, then approve the request again.'
        ]);

        $request = $this->selected();
        if (! $this->ensureDecidable($request)) {
            return;
        }

        $userId = Auth::id(); // always signed in here (auth middleware); approvals are never attributed to anyone else
        $ipAddress = request()->ip();
        $timestamp = now()->toDateTimeString();

        // Generate Dual-Seal Hash (Seal B)
        $rawData = $request->reference . $userId . $ipAddress . $timestamp . substr($this->authorizer_signature, -50);
        $hash = hash('sha256', $rawData);

        // Update the request (only while it is still awaiting approval)
        $updated = DB::table('cash_requests')->where('id', $request->id)->where('status', 'Pending Auth')->update([
            'status' => 'Authorized',
            'authorizer_id' => $userId,
            'authorizer_signature' => $this->authorizer_signature,
            'authorizer_ip' => $ipAddress,
            'authorizer_hash' => $hash,
            'authorized_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $updated) {
            $this->addError('decision', 'This request has already been approved or rejected, so it was not changed. The list has been refreshed.');
            $this->showAuthModal = false;
            return;
        }

        $this->logDecision($request, 'AUTHORIZED_CASH_REQUEST', 'Pending Auth', 'Authorized', "Approved cash request {$request->reference}.");

        // --- NOTIFICATION ENGINE ---
        // Notify the Requester that their funds are authorized
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $request->requester_id,
            'type' => 'System',
            'title' => 'Cash request approved',
            'message' => "Your cash request {$request->reference} was approved. Upload the receipt in Cash requests once you have spent the money.",
            'link_url' => '#',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        $this->showAuthModal = false;
        $this->authorizer_signature = null; // stored with the request; no need to carry it in the page any more
    }

    public function rejectRequest()
    {
        abort_unless(Auth::user()->can('expenses.approve'), 403);

        $request = $this->selected();
        if (! $this->ensureDecidable($request)) {
            return;
        }

        $updated = DB::table('cash_requests')->where('id', $request->id)->where('status', 'Pending Auth')->update([
            'status' => 'Rejected',
            'updated_at' => now(),
        ]);

        if (! $updated) {
            $this->addError('decision', 'This request has already been approved or rejected, so it was not changed. The list has been refreshed.');
            $this->showAuthModal = false;
            return;
        }

        $this->logDecision($request, 'REJECTED_CASH_REQUEST', 'Pending Auth', 'Rejected', "Rejected cash request {$request->reference}.");

        // Notify Requester of Rejection
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $request->requester_id,
            'type' => 'System',
            'title' => 'Cash request rejected',
            'message' => "Your cash request {$request->reference} was rejected. Ask Finance for the reason before you send a new one.",
            'link_url' => '#',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        $this->showAuthModal = false;
        $this->authorizer_signature = null;
    }

    // --- AUDIT CLEARANCE (Part 3) ---

    public function openClearanceModal($id)
    {
        $this->select($id);
        $this->receipt_file = null;
        if (! $this->ensureClearable($this->selected())) {
            return;
        }
        $this->showClearanceModal = true;
    }

    public function uploadReceipt()
    {
        abort_unless(Auth::user()->can('expenses.approve'), 403);

        $request = $this->selected();
        if (! $this->ensureClearable($request)) {
            return;
        }

        $this->validate([
            'receipt_file' => 'required|image|max:5120', // 5MB max
        ]);

        $path = $this->receipt_file->store('receipts', 'local');

        DB::table('cash_requests')->where('id', $request->id)->update([
            'receipt_url' => $path,
            'updated_at' => now()
        ]);

        // The dialog reads the request again when it renders, so the Close request button shows at once
        session()->flash('message', 'Receipt uploaded. Check it matches the amount paid, then close the request.');
    }

    public function clearLedger()
    {
        abort_unless(Auth::user()->can('expenses.approve'), 403);

        $userId = Auth::id(); // always signed in here (auth middleware); approvals are never attributed to anyone else

        // Only an approved request can be closed, only once, and not by the person who raised it
        $request = $this->selected();
        if (! $this->ensureClearable($request)) {
            return;
        }

        DB::transaction(function () use ($userId, $request) {
            
            // 1. Clear the Cash Request
            $closed = DB::table('cash_requests')->where('id', $request->id)->where('status', 'Authorized')->update([
                'status' => 'Cleared',
                'cleared_by' => $userId,
                'cleared_at' => now(),
                'updated_at' => now(),
            ]);

            if ($closed) {
                $this->logDecision($request, 'CLEARED_CASH_REQUEST', 'Authorized', 'Cleared', "Checked the receipt and closed cash request {$request->reference}.");
            }

            // 2. Add the amount to the project budget’s spending (once, only if this call closed it)
            if ($closed && $request->project_id && $request->financial_dictionary_id) {
                DB::table('project_budgets')
                    ->where('project_id', $request->project_id)
                    ->where('dict_id', $request->financial_dictionary_id)
                    ->increment('total_spent', $request->amount_requested);
            }
            
            // Phase 10 will add the strict IFRS Double-Entry Journal execution here later.
        });

        $this->showClearanceModal = false;
    }

    /** Each decision goes to the audit log: who made it, on which request, from which status to which */
    private function logDecision(object $request, string $action, string $from, string $to, string $description): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'target_table' => 'cash_requests',
            'target_id' => $request->id,
            'old_values' => json_encode(['status' => $from]),
            'new_values' => json_encode(['status' => $to]),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Approving or rejecting needs a request that is still awaiting approval,
     * and nobody may decide on their own request (separation of duties).
     * $current: the request as it is now in the database (selected()).
     */
    private function ensureDecidable(?object $current): bool
    {
        if (! $current || $current->status !== 'Pending Auth') {
            $this->addError('decision', 'This request has already been approved or rejected, so it was not changed. The list has been refreshed.');
            $this->showAuthModal = false;
            return false;
        }

        if ((string) $current->requester_id === (string) Auth::id()) {
            $this->addError('decision', "You can’t approve or reject your own cash request. Ask another approver to review it.");
            return false;
        }

        return true;
    }

    /**
     * Checking the receipt and closing a request needs an approved request, and the person who
     * spent the money may not sign off their own receipt (separation of duties).
     * $current: the request as it is now in the database (selected()).
     */
    private function ensureClearable(?object $current): bool
    {
        if (! $current || $current->status !== 'Authorized') {
            $this->addError('decision', 'Only an approved request can be closed. This one has changed since you opened it; the list has been refreshed.');
            $this->showClearanceModal = false;
            return false;
        }

        if ((string) $current->requester_id === (string) Auth::id()) {
            $this->addError('decision', "You can’t check the receipt for your own cash request. Ask another approver to close it.");
            $this->showClearanceModal = false;
            return false;
        }

        return true;
    }

    public function render()
    {
        return view('livewire.hq.finance.cash-request-manager', [
            // Approvals and receipts to check stay short (they leave the tab once decided); the history is paged
            'requests' => $this->activeTab === 'history' ? $this->tabRows()->paginate(self::HISTORY_PER_PAGE) : $this->tabRows()->get(),
            'selectedRequest' => $this->showAuthModal || $this->showClearanceModal ? $this->selected() : null,
        ]);
    }
}