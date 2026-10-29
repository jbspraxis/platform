<?php

namespace App\Livewire\Hq\Marketing;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Support\Access;

#[Layout('components.layouts.hq')]
class MarketingSync extends Component
{
    use WithFileUploads;

    public $activeTab = 'campaigns'; // 'campaigns', 'social', or 'assets'

    // What the signed-in user may do here (UI only; each action re-checks)
    #[Locked]
    public bool $canApprove = false;
    #[Locked]
    public bool $canSeeCredentials = false;

    // View Data
    public $campaigns = [];
    public $socialAccounts = [];
    
    // UI Modals
    public $showCreateModal = false;
    public $showReviewModal = false;
    public $selectedCampaign = null;
    public $showActivateModal = false;
    public $externalCampaignId = '';
    public $showAssetModal = false;
    public $showCredentialModal = false;

    // --- CAMPAIGN DRAFT STATE (Structured Inputs) ---
    public $campName;
    public $platform = 'META';
    public $targetUrl = '/'; 
    public $startDate;
    public $endDate;
    public $budgetAmount = 0.00;
    public $targetLeads = 0;
    public $targetOpportunities = 0;
    public $targetWon = 0;
    public $creativeLink; 
    public $creativeFile; 
    public $remarks;
    
    // --- REVIEW STATE ---
    public $rejectionReason = '';

    // --- DIGITAL ASSET & CREDENTIAL STATE ---
    public $assetPlatform = 'META (FACEBOOK)';
    public $assetType = 'Social Profile';
    public $assetName = '';
    public $assetUrl = '';
    public $assetStatus = 'ACTIVE';
    public $loginIdentifier = '';
    public $assetPassword = '';
    public $adminProfileLink = '';
    
    // Credential Management UI
    public $selectedAsset = null;
    public $newPassword = '';
    public $decryptedPassword = '';
    public $credentialHistory = [];

    public function mount()
    {
        $this->canApprove = Auth::user()->can('marketing.approve');
        $this->canSeeCredentials = Auth::user()->can('marketing.delete');
        $this->loadData();
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadData();
    }

    public function loadData()
    {
        // 1. Load Campaigns
        $query = DB::table('marketing_campaigns')->orderBy('created_at', 'desc');
        
        // Isolation: someone who submits campaigns but can't approve them (e.g. an agency) only sees their own
        if (! Auth::user()->can('marketing.approve') && Auth::user()->can('marketing.create')) {
            $query->where('created_by', Auth::id());
        }
        
        $this->campaigns = json_decode(json_encode($query->get()), true);

        // 2. Load Social Accounts
        $this->socialAccounts = json_decode(json_encode(DB::table('social_media_accounts')->orderBy('created_at', 'desc')->get()), true);
    }

    // ========================================================================
    // CAMPAIGN MANAGEMENT LOGIC (From Old Code)
    // ========================================================================

    public function submitDraft()
    {
        abort_unless(Auth::user()->can('marketing.create'), 403);

        $this->validate([
            'campName' => 'required|string|max:255',
            'platform' => 'required|string',
            'targetUrl' => 'required|string',
            'startDate' => 'required|date',
            'budgetAmount' => 'required|numeric|min:1',
            'creativeFile' => 'nullable|max:10240', // 10MB Max strict limit
            'creativeLink' => 'nullable|url'
        ]);

        DB::transaction(function () {
            $mediaId = null;

            // Handle Local File Upload (<10MB)
            if ($this->creativeFile) {
                $path = $this->creativeFile->store('marketing_assets', 'public');
                $mediaId = (string) Str::uuid();
                
                DB::table('media_assets')->insert([
                    'id' => $mediaId,
                    'file_name' => $this->creativeFile->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $this->creativeFile->getMimeType(),
                    'size_bytes' => $this->creativeFile->getSize(),
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Auto-Generate Standard UTM
            $safeName = Str::slug($this->campName);
            $utmTag = "utm_source=" . strtolower($this->platform) . "&utm_medium=paid_social&utm_campaign={$safeName}";

            $campaignId = (string) Str::uuid();
            
            DB::table('marketing_campaigns')->insert([
                'id' => $campaignId,
                'name' => $this->campName,
                'platform' => $this->platform,
                'target_url' => $this->targetUrl,
                'utm_campaign_tag' => $utmTag,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate ?: null,
                'budget_amount' => $this->budgetAmount,
                'creative_media_id' => $mediaId,
                'creative_external_link' => $this->creativeLink,
                'target_leads' => $this->targetLeads,
                'target_opportunities' => $this->targetOpportunities,
                'target_won' => $this->targetWon,
                'remarks' => $this->remarks,
                'status' => 'PENDING_APPROVAL',
                'owner_name' => Auth::user()->name ?? 'Agency',
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Notify campaign approvers
            foreach (Access::staffWith('marketing.approve') as $adminId) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $adminId,
                    'title' => 'BUDGET APPROVAL REQUIRED',
                    'message' => "Marketing requested $" . number_format($this->budgetAmount, 2) . " for '{$this->campName}'.",
                    'type' => 'Financial',
                    'link_url' => route('hq.marketing.campaigns', [], false),
                    'created_at' => now()
                ]);
            }
        });

        $this->reset(['campName', 'startDate', 'endDate', 'budgetAmount', 'creativeFile', 'creativeLink', 'remarks', 'targetLeads', 'targetOpportunities', 'targetWon']);
        $this->showCreateModal = false;
        $this->loadData();
        session()->flash('success', 'Campaign Draft Submitted for Command Approval.');
    }

    public function openReviewModal($id)
    {
        $this->selectedCampaign = DB::table('marketing_campaigns')->where('id', $id)->first();
        $this->rejectionReason = $this->selectedCampaign->rejection_reason ?? '';
        $this->showReviewModal = true;
    }

    public function approveCampaign()
    {
        if (! Auth::user()->can('marketing.approve') || !$this->selectedCampaign) return;

        DB::transaction(function () {
            // 1. Determine MDM Financial Category based on Platform
            $mdmCode = match($this->selectedCampaign->platform) {
                'META' => 'MKT-META-001',
                'GOOGLE' => 'MKT-GOOG-001',
                'LINKEDIN' => 'MKT-LNKD-001',
                default => 'MKT-MISC-001'
            };
            
            $mdmDict = DB::table('mdm_financial_dictionary')->where('item_code', $mdmCode)->first();
            $mdmId = $mdmDict ? $mdmDict->id : 1;

            // 2. Auto-Spawn the Cash Request
            $cashReqId = (string) Str::uuid();
            $ref = 'EXP-MKT-' . date('Ymd') . '-' . rand(100, 999);
            
            DB::table('cash_requests')->insert([
                'id' => $cashReqId,
                'reference' => $ref,
                'requester_id' => Auth::id(),
                'financial_dictionary_id' => $mdmId,
                'amount_requested' => $this->selectedCampaign->budget_amount,
                'currency' => 'USD',
                'purpose' => "Ad Spend Budget: " . $this->selectedCampaign->name . " [" . $this->selectedCampaign->platform . "]",
                'disbursement_method' => 'Bank Transfer',
                'disbursement_details' => 'Transfer to Praxis Corporate Digital Card',
                'requester_signature' => 'SYSTEM_AUTO_SPAWN',
                'requester_ip' => request()->ip(),
                'requester_hash' => hash('sha256', $ref . now()),
                'status' => 'Pending Auth',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 3. Update Campaign
            DB::table('marketing_campaigns')->where('id', $this->selectedCampaign->id)->update([
                'status' => 'PLANNED',
                'cash_request_id' => $cashReqId,
                'rejection_reason' => null,
                'updated_at' => now()
            ]);
            
            // Notify Agency
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $this->selectedCampaign->created_by,
                'title' => 'CAMPAIGN APPROVED', 'message' => "Budget secured for {$this->selectedCampaign->name}. Proceed to launch.",
                'type' => 'System', 'created_at' => now()
            ]);
        });

        $this->showReviewModal = false;
        $this->loadData();
        session()->flash('success', 'Campaign Approved. Treasury Cash Request Auto-Generated.');
    }

    public function rejectCampaign()
    {
        if (! Auth::user()->can('marketing.approve') || !$this->selectedCampaign) return;
        
        $this->validate(['rejectionReason' => 'required|string|min:5']);

        DB::table('marketing_campaigns')->where('id', $this->selectedCampaign->id)->update([
            'status' => 'REVISION_REQUIRED',
            'rejection_reason' => $this->rejectionReason,
            'updated_at' => now()
        ]);

        $this->showReviewModal = false;
        $this->loadData();
        session()->flash('warning', 'Campaign kicked back for revision.');
    }
    
    public function openActivateModal($id)
    {
        $this->selectedCampaign = DB::table('marketing_campaigns')->where('id', $id)->first();
        $this->externalCampaignId = $this->selectedCampaign->external_campaign_id ?? '';
        $this->showActivateModal = true;
    }

    public function activateCampaign()
    {
        // Editors link any campaign; a submitter may link their own approved one
        $ownCampaign = $this->selectedCampaign && DB::table('marketing_campaigns')
            ->where('id', $this->selectedCampaign->id)->where('created_by', Auth::id())->exists();
        abort_unless(Auth::user()->can('marketing.edit') || ($ownCampaign && Auth::user()->can('marketing.create')), 403);

        $this->validate(['externalCampaignId' => 'required|string|min:5']);

        DB::table('marketing_campaigns')->where('id', $this->selectedCampaign->id)->update([
            'status' => 'ACTIVE',
            'external_campaign_id' => $this->externalCampaignId,
            'updated_at' => now()
        ]);

        $this->showActivateModal = false;
        $this->loadData();
        session()->flash('success', 'Campaign Linked. Telemetry Cron Job will now track spend.');
    }

    // ========================================================================
    // DIGITAL ASSET & CREDENTIAL MANAGEMENT LOGIC (New)
    // ========================================================================

    public function registerDigitalAsset()
    {
        abort_unless(Auth::user()->can('marketing.create'), 403);

        $this->validate([
            'assetPlatform' => 'required|string',
            'assetName' => 'required|string',
            'loginIdentifier' => 'required|string',
            'assetPassword' => 'required|string'
        ]);

        DB::transaction(function () {
            $assetId = (string) Str::uuid();
            $encryptedPass = Crypt::encryptString($this->assetPassword);

            DB::table('social_media_accounts')->insert([
                'id' => $assetId,
                'platform' => strtoupper($this->assetPlatform),
                'asset_type' => $this->assetType,
                'account_name' => $this->assetName,
                'profile_url' => $this->assetUrl,
                'status' => $this->assetStatus,
                'login_identifier' => $this->loginIdentifier,
                'encrypted_password' => $encryptedPass,
                'admin_profile_link' => $this->adminProfileLink,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('asset_credential_history')->insert([
                'asset_id' => $assetId, 
                'encrypted_password' => $encryptedPass,
                'changed_by' => Auth::id(), 
                'created_at' => now()
            ]);
            
            $this->logSystemAudit('ASSET_REGISTERED', "Registered new asset: {$this->assetPlatform} - {$this->assetName}");
        });

        $this->showAssetModal = false;
        $this->reset(['assetPlatform', 'assetType', 'assetName', 'assetUrl', 'assetStatus', 'loginIdentifier', 'assetPassword', 'adminProfileLink']);
        $this->loadData();
        session()->flash('success', 'Asset secured. Credentials encrypted in vault.');
    }

    public function openCredentialModal($id)
    {
        $this->selectedAsset = DB::table('social_media_accounts')->where('id', $id)->first();
        $this->newPassword = '';
        
        if (Auth::user()->can('marketing.delete')) {
            try {
                $this->decryptedPassword = Crypt::decryptString($this->selectedAsset->encrypted_password);
            } catch (\Exception $e) {
                $this->decryptedPassword = 'ERROR_DECRYPTING';
            }
            
            $this->credentialHistory = json_decode(json_encode(
                DB::table('asset_credential_history')
                    ->join('users', 'asset_credential_history.changed_by', '=', 'users.id')
                    ->where('asset_id', $id)
                    ->select('asset_credential_history.*', 'users.name as user_name')
                    ->orderBy('created_at', 'desc')->get()
            ), true);
        } else {
            $this->decryptedPassword = '****************'; 
        }
        
        $this->showCredentialModal = true;
    }

    public function updateAssetPassword()
    {
        abort_unless(Auth::user()->can('marketing.edit'), 403);

        $this->validate(['newPassword' => 'required|string|min:6']);

        DB::transaction(function () {
            $encryptedPass = Crypt::encryptString($this->newPassword);

            DB::table('social_media_accounts')->where('id', $this->selectedAsset->id)->update([
                'encrypted_password' => $encryptedPass,
                'updated_at' => now()
            ]);

            DB::table('asset_credential_history')->insert([
                'asset_id' => $this->selectedAsset->id,
                'encrypted_password' => $encryptedPass,
                'changed_by' => Auth::id(),
                'created_at' => now()
            ]);

            $this->logSystemAudit('CREDENTIAL_UPDATED', "Password changed for asset: {$this->selectedAsset->account_name}");

            // Alert whoever holds the credential vault (marketing.delete)
            foreach (Access::staffWith('marketing.delete') as $adminId) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(), 'user_id' => $adminId,
                    'title' => 'ASSET SECURITY ALERT', 
                    'message' => (Auth::user()->name ?? 'Agency') . " updated the password for {$this->selectedAsset->platform} ({$this->selectedAsset->account_name}).",
                    'type' => 'System', 'created_at' => now()
                ]);
            }
        });

        $this->showCredentialModal = false;
        session()->flash('success', 'Asset password updated and encrypted successfully.');
    }

    private function logSystemAudit($action, $description)
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'created_at' => now()
        ]);
    }

    public function render()
    {
        return view('livewire.hq.marketing.marketing-sync');
    }
}