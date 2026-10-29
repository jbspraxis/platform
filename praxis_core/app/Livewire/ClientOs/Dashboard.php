<?php

namespace App\Livewire\ClientOs;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use App\Support\People;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

#[Layout('components.layouts.portal')]
class Dashboard extends Component
{
    use WithFileUploads;

    // Everything that identifies this client (organisation, projects, tickets,
    // invoices, lockout) is #[Locked]: set on the server only. Actions that take
    // an id from the browser check it belongs to this organisation.

    // View State
    #[Locked]
    public $activeTab = 'overview';
    #[Locked]
    public $financialLockout = false;
    #[Locked]
    public $lockoutReason = '';
    public $showRedlineModal = false;
    public $redlineAuthDescription = '';

    // Core Data
    #[Locked]
    public $user;
    #[Locked]
    public $organization;
    #[Locked]
    public $clientSettings;

    // Project Management
    #[Locked]
    public $allProjects = [];
    public $activeProjectId;
    #[Locked]
    public $activeProject;

    // Overview & Sprints State
    #[Locked]
    public $leadArchitect;
    #[Locked]
    public $overallProgress = 0;
    #[Locked]
    public $phases = [];
    #[Locked]
    public $liveLogs = [];
    #[Locked]
    public $activeTasks = [];

    // QA Hub State
    #[Locked]
    public $qaTickets = [];
    public $qaTaskId = '';
    public $qaTitle = '';
    public $qaSeverity = 'Medium';
    public $qaDescription = '';

    // QA Offcanvas State
    #[Locked]
    public $selectedTicket = null;
    #[Locked]
    public $ticketComments = [];
    public $initialAttachment;
    public $newCommentText = '';
    #[Locked]
    public $ticketAttachments = [];
    public $newQaAttachment;

    // Financial State
    #[Locked]
    public $invoices = [];
    #[Locked]
    public $remittanceAccounts = [];
    #[Locked]
    public $uploadInvoiceId = null;
    public $popMethod = 'Bank Transfer';
    public $popRef = '';
    public $popAmount = '';
    public $popFile;
    public $popWhtAmount = 0.00;
    public $popWhtFile;

    // Vault & Team State
    #[Locked]
    public $vaultSows = [];
    #[Locked]
    public $vaultSignoffs = [];
    #[Locked]
    public $teamMembers = [];
    public $inviteFirstName = '';
    public $inviteLastName = '';
    public $inviteEmail = '';
    public $inviteProjectId = ''; 

    // Brand Config State
    public $brandPrimary = '#00E5FF';
    public $brandSecondary = '#FF3366';
    public $brandAccent = '#FFBD2E';
    public $brandLogo; 

    public function mount()
    {
        // Client accounts only (guard 'client', client_users); staff use HQ
        $this->user = Auth::guard('client')->user();

        if (!$this->user || $this->user->status !== 'Active') {
            Auth::guard('client')->logout();
            abort(403, 'UNAUTHORIZED NODE: Access restricted to Client Level Clearance.');
        }

        $this->organization = DB::table('organizations')->where('id', $this->user->organization_id)->first();
        if (!$this->organization) {
            Auth::guard('client')->logout();
            abort(404, 'CRITICAL ERROR: No organization linked to this node.');
        }

        $this->loadBrandSettings();

        // Project Routing
        $projectQuery = DB::table('projects')->where('organization_id', $this->organization->id)->orderBy('created_at', 'desc');
        if ($this->user->restricted_project_id) {
            $projectQuery->where('id', $this->user->restricted_project_id);
        }
        
        $this->allProjects = $projectQuery->get()->toArray();

        if (empty($this->allProjects)) {
            abort(403, 'NO ACTIVE BATTLESPACE DETECTED. Awaiting HQ provisioning.');
        }

        $defaultProject = collect($this->allProjects)->firstWhere('status', 'Active') 
                       ?? collect($this->allProjects)->firstWhere('status', 'Scoping')
                       ?? $this->allProjects[0];
                       
        $this->switchProject($defaultProject->id);
    }

    public function loadBrandSettings()
    {
        $this->clientSettings = DB::table('client_portal_settings')->where('organization_id', $this->organization->id)->first();
        if ($this->clientSettings) {
            $this->brandPrimary = $this->clientSettings->primary_color ?? '#00E5FF';
            $this->brandSecondary = $this->clientSettings->secondary_color ?? '#FF3366';
            $this->brandAccent = $this->clientSettings->accent_color ?? '#FFBD2E';
        }
    }

    // Ids this client may touch, checked by every action that takes an id from the browser
    private function ownsProject($projectId): bool
    {
        return $projectId && collect($this->allProjects)->contains('id', $projectId);
    }

    private function ownsTask($taskId): bool
    {
        return $taskId && $this->activeProject && DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('tasks.id', $taskId)
            ->where('project_phases.project_id', $this->activeProject->id)
            ->exists();
    }

    public function switchProject($projectId)
    {
        $project = collect($this->allProjects)->firstWhere('id', $projectId);
        if (!$project) {
            $this->activeProjectId = $this->activeProject->id ?? null;
            return;
        }
        $this->activeProjectId = $projectId;
        $this->activeProject = $project;
        
        $this->enforceFinancialLockout();
        
        if ($this->financialLockout && $this->activeTab !== 'financials') {
            $this->activeTab = 'financials';
        }

        // Initialize default tab data
        if ($this->activeTab === 'overview' || $this->activeTab === 'sprints') $this->loadProjectTelemetry();
        if ($this->activeTab === 'qa_hub') $this->loadQaHub();
        if ($this->activeTab === 'financials') $this->loadFinancials();
        if ($this->activeTab === 'vault') $this->loadVault();
        if ($this->activeTab === 'team') $this->loadTeamCommand();
    }

    public function enforceFinancialLockout()
    {
        if (!$this->activeProject) return;

        $blockingInvoice = DB::table('invoices')
            ->where('project_id', $this->activeProject->id)
            ->where(function($query) {
                $query->where('status', 'Overdue')
                      ->orWhere(function($sub) {
                          $sub->where('status', 'Pending')->whereExists(function($p) {
                                  $p->select(DB::raw(1))->from('projects')->whereColumn('projects.id', 'invoices.project_id')->where('projects.status', 'Scoping');
                              });
                      });
            })->first();

        if ($blockingInvoice) {
            $this->financialLockout = true;
            $hasPendingTx = DB::table('transactions')->where('invoice_id', $blockingInvoice->id)->where('status', 'Pending Verification')->exists();
            $this->lockoutReason = $hasPendingTx 
                ? 'PAYMENT VERIFICATION IN PROGRESS: HQ is cryptographically verifying your Proof of Payment.' 
                : ($blockingInvoice->status === 'Overdue' ? 'ACCOUNT DELINQUENT: Overdue invoice detected.' : 'AWAITING INITIAL DEPOSIT: Please upload Proof of Payment to commence execution.');
        } else {
            $this->financialLockout = false;
        }
    }

    // --- TAB LOADERS ---

    public function loadProjectTelemetry()
    {
        if (!$this->activeProject) return;

        $architect = DB::table('users')->where('id', $this->activeProject->created_by)->first();
        $this->leadArchitect = $architect ? strtoupper($architect->first_name . ' ' . substr($architect->last_name, 0, 1) . '.') : 'PRAXIS COMMAND';
        
        $rawPhases = DB::table('project_phases')->where('project_id', $this->activeProject->id)->orderBy('phase_number', 'asc')->get();
        $completedWeight = 0;
        $this->phases = $rawPhases->map(function ($phase) use (&$completedWeight) {
            if ($phase->status === 'Completed') $completedWeight += $phase->weight_percentage;
            return (array) $phase;
        })->toArray();
        $this->overallProgress = $completedWeight;

        $logs = DB::table('task_comments')->join('tasks', 'task_comments.task_id', '=', 'tasks.id')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')->where('project_phases.project_id', $this->activeProject->id)->orderBy('task_comments.created_at', 'desc')->limit(15)->select('task_comments.comment_text', 'task_comments.created_at');
        $this->liveLogs = People::joinAuthor($logs, 'task_comments.user_id', 'task_comments.author_type')->get()->map(function($log) {
            return ['time' => Carbon::parse($log->created_at)->format('M d - H:i'), 'author' => strtoupper(substr($log->first_name, 0, 1) . '. ' . $log->last_name), 'text' => $log->comment_text];
        })->toArray();

        $this->activeTasks = DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')->where('project_phases.project_id', $this->activeProject->id)->select('tasks.*', 'project_phases.title as phase_title')->orderByRaw("FIELD(tasks.status, 'QA_Review', 'In-Progress', 'To-Do', 'Done')")->orderBy('tasks.updated_at', 'desc')->get()->toArray();
    }

    public function loadQaHub()
    {
        if (!$this->activeProject) return;
        $this->activeTasks = DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')->where('project_phases.project_id', $this->activeProject->id)->select('tasks.id', 'tasks.title', 'tasks.status')->get()->toArray();
        $this->qaTickets = DB::table('qa_tickets')->leftJoin('tasks', 'qa_tickets.task_id', '=', 'tasks.id')->where('qa_tickets.project_id', $this->activeProject->id)->select('qa_tickets.*', 'tasks.title as task_title')->orderBy('qa_tickets.created_at', 'desc')->get()->toArray();
    }

    public function loadFinancials()
    {
        if (!$this->activeProject) return;
        $this->invoices = DB::table('invoices')->where('project_id', $this->activeProject->id)->orderBy('created_at', 'desc')->get()->map(function($invoice) {
            $invoice->has_pending_pop = DB::table('transactions')->where('invoice_id', $invoice->id)->where('status', 'Pending Verification')->exists();
            return $invoice;
        })->toArray();
        $this->remittanceAccounts = DB::table('mdm_remittance_accounts')->where('is_active', 1)->get()->toArray();
    }

    public function loadVault()
    {
        $this->vaultSows = DB::table('proposals')
            ->where('organization_id', $this->organization->id)
            ->whereIn('status', ['Authorized', 'Converted'])
            ->orderBy('created_at', 'desc')
            ->get()->toArray();

        $this->vaultSignoffs = DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->where('projects.organization_id', $this->organization->id)
            ->whereIn('project_signoffs.status', ['Signed', 'Frozen'])
            ->select('project_signoffs.*', 'projects.title as project_title')
            ->orderBy('project_signoffs.created_at', 'desc')
            ->get()->toArray();
    }

    public function loadTeamCommand()
    {
        if (!$this->user->is_primary_client) return;
        // Portal accounts of this organisation (not leads). Only display fields: the list is
        // sent to the browser, so never select passwords or invitation tokens.
        $this->teamMembers = DB::table('client_users')
            ->leftJoin('projects', 'client_users.restricted_project_id', '=', 'projects.id')
            ->where('client_users.organization_id', $this->organization->id)
            ->whereIn('client_users.lifecycle_stage', ['Onboarding', 'Active'])
            ->whereNull('client_users.deleted_at')
            ->select('client_users.id', 'client_users.first_name', 'client_users.last_name', 'client_users.email', 'client_users.job_title',
                     'client_users.status', 'client_users.lifecycle_stage', 'client_users.is_primary_client', 'client_users.restricted_project_id',
                     'client_users.last_login_at', 'client_users.created_at', 'projects.title as restricted_project_title')
            ->get()->toArray();
    }

    public function switchTab($tab)
    {
        if ($this->financialLockout && $tab !== 'financials') {
            session()->flash('lockout_error', '> ACCESS DENIED: ' . $this->lockoutReason);
            return;
        }
        $this->activeTab = $tab;
        
        // Dynamically load the required data for the tab
        if ($tab === 'overview' || $tab === 'sprints') $this->loadProjectTelemetry();
        if ($tab === 'qa_hub') $this->loadQaHub();
        if ($tab === 'financials') $this->loadFinancials();
        if ($tab === 'vault') $this->loadVault();
        if ($tab === 'team') $this->loadTeamCommand();
    }

    // --- FORM SUBMISSIONS ---

    public function submitQaTicket()
    {
        $this->validate([
            'qaTitle' => 'required|string|max:150', 
            'qaDescription' => 'required|string', 
            'qaSeverity' => 'required|in:Low,Medium,Critical',
            'initialAttachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120' // 5MB Limit
        ]);

        if (!$this->activeProject || (!empty($this->qaTaskId) && !$this->ownsTask($this->qaTaskId))) {
            return;
        }

        DB::transaction(function () {
            $ticketId = (string) Str::uuid(); 
            $isGeneralMessage = empty($this->qaTaskId);
            $finalTitle = ($isGeneralMessage ? '[DIRECT COMM] ' : '[BUG REPORT] ') . $this->qaTitle;
            
            DB::table('qa_tickets')->insert([
                'id' => $ticketId, 'project_id' => $this->activeProject->id, 'task_id' => $isGeneralMessage ? null : $this->qaTaskId,
                'title' => $finalTitle, 'description' => $this->qaDescription, 'status' => 'Open', 'severity' => $this->qaSeverity,
                'submitted_by' => $this->user->id, 'submitted_by_type' => 'client', 'created_at' => now(), 'updated_at' => now()
            ]);

            // NEW: Process attachment if uploaded during creation
            if ($this->initialAttachment) {
                $path = $this->initialAttachment->store('qa_evidence', 'local');
                DB::table('qa_attachments')->insert([
                    'id' => (string) Str::uuid(),
                    'ticket_id' => $ticketId,
                    'uploaded_by' => $this->user->id, 'uploader_type' => 'client',
                    'file_name' => $this->initialAttachment->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $this->initialAttachment->getMimeType(),
                    'size_bytes' => $this->initialAttachment->getSize(),
                    'created_at' => now()
                ]);
            }

            $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
            $notifications = [];
            foreach($admins as $admin) {
                $notifications[] = ['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'title' => $isGeneralMessage ? 'CLIENT COMMUNICATION RECEIVED' : 'QA BUG REPORT LOGGED', 'message' => "Project: {$this->activeProject->title} | By: {$this->user->first_name}", 'type' => 'Project', 'created_at' => now()];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });
        
        $this->reset(['qaTitle', 'qaDescription', 'qaTaskId', 'qaSeverity', 'initialAttachment']); 
        $this->loadQaHub(); 
        session()->flash('qa_success', '> INTELLIGENCE TRANSMITTED TO COMMAND MATRIX.');
    }
    
    // --- DIRECT SPRINT APPROVALS ---
    public function approveTask($taskId)
    {
        if (!$this->ownsTask($taskId)) return;

        DB::table('tasks')->where('id', $taskId)->update(['status' => 'Done', 'updated_at' => now()]);
        
        DB::table('task_comments')->insert([
            'task_id' => $taskId, 'user_id' => $this->user->id, 'author_type' => 'client',
            'comment_text' => '[CLIENT VERIFICATION] Deliverable approved and structurally locked.', 'created_at' => now()
        ]);

        $this->loadProjectTelemetry();
        session()->flash('global_success', '> DELIVERABLE APPROVED AND SECURED.');
    }

    public function teleportToQa($taskId, $taskTitle)
    {
        if (!$this->ownsTask($taskId)) return;
        $this->qaTaskId = $taskId;
        $this->qaTitle = "VULNERABILITY: " . strtoupper($taskTitle);
        $this->switchTab('qa_hub');
    }

    // --- QA HUB OFFCANVAS & COMMUNICATION ---
    public function openTicket($ticketId)
    {
        $this->selectedTicket = collect($this->qaTickets)->firstWhere('id', $ticketId);
        if (!$this->selectedTicket) return;

        // Fetch non-internal comments only
        $comments = DB::table('qa_comments')
            ->where('qa_comments.ticket_id', $ticketId)
            ->where('qa_comments.is_internal_only', 0)
            ->select('qa_comments.*')
            ->orderBy('qa_comments.created_at', 'asc');
        $this->ticketComments = People::joinAuthor($comments, 'qa_comments.user_id', 'qa_comments.author_type')->get()->toArray();

        // NEW: Fetch Evidence/Attachments
        $attachments = DB::table('qa_attachments')
            ->where('qa_attachments.ticket_id', $ticketId)
            ->select('qa_attachments.*')
            ->orderBy('qa_attachments.created_at', 'desc');
        $this->ticketAttachments = People::joinAuthor($attachments, 'qa_attachments.uploaded_by', 'qa_attachments.uploader_type', 'uploader')->get()->toArray();

        $this->dispatch('open-qa');
    }

    // NEW: Handle Client Uploads
    public function uploadQaEvidence()
    {
        if (!$this->selectedTicket) return;

        $this->validate([
            'newQaAttachment' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120' // 5MB max
        ]);

        $path = $this->newQaAttachment->store('qa_evidence', 'local');

        DB::table('qa_attachments')->insert([
            'id' => (string) Str::uuid(),
            'ticket_id' => $this->selectedTicket->id,
            'uploaded_by' => $this->user->id, 'uploader_type' => 'client',
            'file_name' => $this->newQaAttachment->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $this->newQaAttachment->getMimeType(),
            'size_bytes' => $this->newQaAttachment->getSize(),
            'created_at' => now()
        ]);

        $this->newQaAttachment = null;
        $this->openTicket($this->selectedTicket->id); // Refresh drawer
    }

    public function postTicketComment()
    {
        if (!$this->selectedTicket) return;

        $this->validate(['newCommentText' => 'required|string|min:2|max:5000']);

        DB::table('qa_comments')->insert([
            'id' => (string) Str::uuid(), 'ticket_id' => $this->selectedTicket->id, 'user_id' => $this->user->id, 'author_type' => 'client',
            'comment_text' => $this->newCommentText, 'is_internal_only' => 0,
            'created_at' => now(), 'updated_at' => now()
        ]);

        // Notify HQ
        $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
        $notifications = [];
        foreach($admins as $admin) {
            $notifications[] = ['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'title' => 'CLIENT RESPONSE LOGGED', 'message' => "Ticket: {$this->selectedTicket->title}", 'type' => 'Project', 'created_at' => now()];
        }
        if(!empty($notifications)) DB::table('notifications')->insert($notifications);

        $this->newCommentText = '';
        $this->openTicket($this->selectedTicket->id); // Refresh thread
    }

    public function approveTicketFix()
    {
        if (!$this->selectedTicket) return;

        DB::table('qa_tickets')->where('id', $this->selectedTicket->id)->update(['status' => 'Closed', 'updated_at' => now()]);
        if ($this->selectedTicket->task_id) {
            DB::table('tasks')->where('id', $this->selectedTicket->task_id)->update(['status' => 'Done', 'updated_at' => now()]);
        }
        
        $this->dispatch('close-qa');
        $this->loadQaHub();
        session()->flash('global_success', '> FIX VERIFIED. TASK CLOSED.');
    }
    
    public function approveAndLockFromQa()
    {
        if (!$this->selectedTicket) return;

        // 1. Force close the QA Ticket
        DB::table('qa_tickets')->where('id', $this->selectedTicket->id)->update([
            'status' => 'Closed', 
            'updated_at' => now()
        ]);

        // 2. Lock the Linked Task to Done
        if ($this->selectedTicket->task_id) {
            DB::table('tasks')->where('id', $this->selectedTicket->task_id)->update([
                'status' => 'Done', 
                'updated_at' => now()
            ]);
            
            DB::table('task_comments')->insert([
                // The 'id' => (string) Str::uuid() has been removed below
                'task_id' => $this->selectedTicket->task_id, 
                'user_id' => $this->user->id,
                'author_type' => 'client',
                'comment_text' => '[CLIENT VERIFICATION] Deliverable verified and locked directly from QA Hub override.', 
                'created_at' => now()
            ]);
        }

        $this->dispatch('close-qa');
        $this->loadQaHub();
        $this->loadProjectTelemetry(); // Refreshes the Sprints tab in the background
        session()->flash('global_success', '> VULNERABILITY OVERRIDDEN. TASK SECURED & LOCKED.');
    }
    
    public function triggerPopUpload($invoiceId, $amountDue) {
        if (!collect($this->invoices)->contains('id', $invoiceId)) return;
        $this->uploadInvoiceId = $invoiceId;
        $this->popAmount = $amountDue; 
        $this->popRef = ''; 
        $this->popWhtAmount = 0.00;
    }
    
    public function cancelPopUpload() { 
        $this->uploadInvoiceId = null; 
        $this->reset(['popMethod', 'popRef', 'popAmount', 'popFile', 'popWhtAmount', 'popWhtFile']); 
    }
    
    public function submitProofOfPayment()
    {
        $this->validate([
            'uploadInvoiceId' => 'required|exists:invoices,id',
            'popMethod' => 'required|string', 
            'popRef' => 'required|string|max:255',
            'popAmount' => 'required|numeric|min:0.01', 
            'popFile' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'popWhtAmount' => 'nullable|numeric|min:0',
            'popWhtFile' => 'nullable|required_with:popWhtAmount|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $invoiceProjectId = DB::table('invoices')->where('id', $this->uploadInvoiceId)->value('project_id');
        if (!$this->ownsProject($invoiceProjectId)) return;

        $filePath = $this->popFile->store('pop_receipts', 'local');
        
        $whtFilePath = null;
        if ($this->popWhtAmount > 0 && $this->popWhtFile) {
            $whtFilePath = $this->popWhtFile->store('pop_receipts_wht', 'local');
        }

        DB::transaction(function () use ($filePath, $whtFilePath) {
            $txId = (string) Str::uuid();
            DB::table('transactions')->insert([
                'id' => $txId, 
                'invoice_id' => $this->uploadInvoiceId, 
                'method' => $this->popMethod,
                'tx_hash_or_ref' => $this->popRef, 
                'proof_file_path' => $filePath, 
                'amount_claimed' => $this->popAmount,
                'wht_deducted' => $this->popWhtAmount ?: 0.00,
                'wht_proof_file_path' => $whtFilePath,
                'status' => 'Pending Verification', 
                'created_by' => null, 'client_user_id' => $this->user->id, 
                'created_at' => now(), 
                'updated_at' => now()
            ]);

            $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
            $notifications = [];
            foreach($admins as $admin) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $admin->id, 'title' => 'FINANCIAL: POP UPLOADED', 
                    'message' => "Client {$this->organization->name} uploaded POP for {$this->activeProject->currency} {$this->popAmount} (+ {$this->popWhtAmount} WHT).", 'type' => 'Financial', 'created_at' => now()
                ];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });

        $this->cancelPopUpload(); 
        $this->loadFinancials(); 
        $this->enforceFinancialLockout();
        session()->flash('finance_success', '> PROOF OF PAYMENT TRANSMITTED. AWAITING HQ CLEARANCE.');
    }

    public function saveBrandSettings()
    {
        $this->validate([
            'brandPrimary' => 'required|string|size:7', 'brandSecondary' => 'required|string|size:7',
            'brandAccent' => 'required|string|size:7', 'brandLogo' => 'nullable|image|mimes:png,svg|max:5120',
        ]);

        $logoPath = $this->clientSettings->logo_path ?? null;
        if ($this->brandLogo) {
            $logoPath = $this->brandLogo->store('client_logos', 'public');
        }

        DB::table('client_portal_settings')->updateOrInsert(
            ['organization_id' => $this->organization->id],
            [
                'id' => $this->clientSettings->id ?? (string) Str::uuid(),
                'primary_color' => $this->brandPrimary, 'secondary_color' => $this->brandSecondary,
                'accent_color' => $this->brandAccent, 'logo_path' => $logoPath, 'updated_at' => now()
            ]
        );

        $this->loadBrandSettings(); 
        session()->flash('brand_success', '> BRAND MATRIX OVERRIDE SUCCESSFUL.');
    }

    public function inviteTeamMember()
    {
        if (!$this->user->is_primary_client) return;

        try {
            $this->validate([
                'inviteFirstName' => 'required|string|max:50', 
                'inviteLastName' => 'required|string|max:50', 
                'inviteEmail' => 'required|email|unique:client_users,email',
                'inviteProjectId' => 'nullable|string' // Added to prevent strict-type silent failures
            ]);

            if (!empty($this->inviteProjectId) && !$this->ownsProject($this->inviteProjectId)) {
                session()->flash('team_error', '> ERR: Unknown project.'); return;
            }

            $portalAccounts = DB::table('client_users')->where('organization_id', $this->organization->id)
                ->whereIn('lifecycle_stage', ['Onboarding', 'Active'])->whereNull('deleted_at')->count();
            if ($portalAccounts >= 3) {
                session()->flash('team_error', '> ERR: MAXIMUM NODE CAPACITY (3) REACHED.'); return;
            }

            $primaryDomain = substr(strrchr($this->user->email, "@"), 1);
            $inviteDomain = substr(strrchr($this->inviteEmail, "@"), 1);
            if (strtolower($primaryDomain) !== strtolower($inviteDomain)) {
                session()->flash('team_error', '> SECURITY BREACH: DOMAIN MUST MATCH @' . strtoupper($primaryDomain)); return;
            }

            $token = Str::random(60);

            // No password until they open the setup link, so the account cannot sign in before then
            DB::table('client_users')->insert([
                'id' => (string) Str::uuid(), 'organization_id' => $this->organization->id,
                'is_primary_client' => 0, 'restricted_project_id' => $this->inviteProjectId ?: null,
                'first_name' => $this->inviteFirstName, 'last_name' => $this->inviteLastName, 'email' => $this->inviteEmail,
                'password' => null, 'invitation_token' => $token, 'lifecycle_stage' => 'Onboarding', 'status' => 'Active',
                'source' => 'invite', 'created_at' => now(), 'updated_at' => now()
            ]);

            $setupUrl = url('/client/setup/' . $token);
            $orgName = e($this->organization->name);
            $inviteName = e($this->inviteFirstName);

            // ACTUALLY DISPATCH THE EMAIL
            Mail::html(
                "<div style='font-family: sans-serif; color: #333;'>
                    <h2 style='color: #00E5FF; background: #0A1128; padding: 15px;'>JBS Praxis: Node Provisioned</h2>
                    <p>Hello {$inviteName},</p>
                    <p>You have been granted executive access to the <strong>{$orgName}</strong> deployment matrix.</p>
                    <p>Click the secure link below to verify your cryptographic signature and set your password:</p>
                    <p><a href='{$setupUrl}' style='display: inline-block; background: #00E5FF; color: #000; padding: 10px 20px; text-decoration: none; font-weight: bold;'>INITIALIZE SECURE LINK</a></p>
                    <p style='font-size: 12px; color: #888;'><em>Link expires in 48 hours.</em></p>
                </div>", 
                function ($message) {
                    $message->to($this->inviteEmail)->subject('[URGENT] JBS Praxis OS: Node Provisioning Instructions');
                }
            );

            $this->reset(['inviteFirstName', 'inviteLastName', 'inviteEmail', 'inviteProjectId']); 
            $this->loadTeamCommand();
            session()->flash('team_success', '> NODE PROVISIONED. ENCRYPTED EMAIL DISPATCHED TO TARGET.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Livewire will catch this and display it via the frontend @errors block
            throw $e;
        } catch (\Exception $e) {
            // Catch DB constraint errors (like UUID mismatches) so it doesn't fail silently
            Log::error('Client team invite failed', ['organization_id' => $this->organization->id, 'message' => $e->getMessage()]);
            session()->flash('team_error', '> ERR: The invitation could not be completed. Please contact JBS Praxis.');
        }
    }

    // NEW: Allow Primary Alpha to edit node access on the fly
    public function updateNodeAccess($userId, $projectId)
    {
        if (!$this->user->is_primary_client || $this->user->id === $userId) return;
        if (!empty($projectId) && !$this->ownsProject($projectId)) return;

        DB::table('client_users')->where('id', $userId)->where('organization_id', $this->organization->id)->update([
            'restricted_project_id' => empty($projectId) ? null : $projectId,
            'updated_at' => now()
        ]);
        
        $this->loadTeamCommand();
        session()->flash('team_success', '> NODE ACCESS PROTOCOLS RECONFIGURED.');
    }

    public function toggleSuspendUser($userId)
    {
        if (!$this->user->is_primary_client || $this->user->id === $userId) return;
        $target = DB::table('client_users')->where('id', $userId)->where('organization_id', $this->organization->id)->first();
        if ($target) {
            $newStatus = $target->status === 'Suspended' ? 'Active' : 'Suspended';
            // A new remember token ends any 'remember me' logins; ClientActive signs out open sessions
            DB::table('client_users')->where('id', $userId)->where('organization_id', $this->organization->id)->update(['status' => $newStatus, 'remember_token' => Str::random(60), 'updated_at' => now()]);
            $this->loadTeamCommand();
        }
    }

    public function triggerAuthenticatedRedline()
    {
        $this->validate(['redlineAuthDescription' => 'required|string|min:10']);
        DB::transaction(function () {
            DB::table('redline_sos_requests')->insert([
                'id' => (string) Str::uuid(), 'organization_id' => $this->organization->id, 'user_id' => $this->user->id,
                'problem_description' => "[AUTH CLIENT OVERRIDE] " . $this->redlineAuthDescription,
                'status' => 'Pending Triage', 'created_at' => now(), 'updated_at' => now()
            ]);
            $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
            $notifications = [];
            foreach($admins as $admin) {
                $notifications[] = ['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'title' => 'CRITICAL: AUTHENTICATED REDLINE TRIGGERED', 'message' => "Active client {$this->organization->name} initiated Redline.", 'type' => 'System', 'created_at' => now()];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });
        $this->showRedlineModal = false; $this->redlineAuthDescription = '';
        session()->flash('global_success', '> REDLINE PROTOCOL ENGAGED. STRIKE TEAM PAGED.');
    }

    public function executeLogout()
    {
        Auth::guard('client')->logout();
        return redirect()->route('client.login');
    }

    public function render()
    {
        return view('livewire.client-os.dashboard');
    }
}