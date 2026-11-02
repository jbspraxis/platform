<?php

namespace App\Livewire\ClientOs;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use App\Models\Proposal;
use App\Models\StatementOfWork;
use App\Support\People;
use App\Support\Opportunities\PortalLinks;
use App\Support\Opportunities\Sows;
use App\Support\Projects\ProjectPlan;
use App\Support\Access;
use App\Support\MailLocale;
use App\Mail\TeamMemberInvitation;
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
    use PortalLanguage;

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
    public $popWhtAmount = null; // empty unless withholding tax is declared (0 counted as "present" and made the certificate required)
    public $popWhtFile;

    // Vault & Team State
    #[Locked]
    public $vaultSows = [];
    #[Locked]
    public $vaultSignoffs = [];
    /** The statements of work and proposals the client was sent (opportunity pipeline), with links made when drawn */
    #[Locked]
    public $vaultContracts = [];
    #[Locked]
    public $vaultProposals = [];
    #[Locked]
    public $teamMembers = [];
    public $inviteFirstName = '';
    public $inviteLastName = '';
    public $inviteEmail = '';
    public $inviteProjectId = ''; 
    public $inviteRoleId = '';

    // Portal permissions of this account, e.g. $can['billing.create'] (role-based, set on the server)
    #[Locked]
    public $can = [];
    #[Locked]
    public $clientRoles = [];

    // Portal appearance (the organisation's logo and colours); defaults are the JBS Praxis colours (docs/BRAND.md section 7)
    public $brandPrimary = '#08707C';
    public $brandSecondary = '#B3261E';
    public $brandAccent = '#8A5700';
    public $brandLogo; 

    // Each tab needs this portal permission (config/access.php, "portal.<module>.<action>")
    private const TAB_PERMISSIONS = [
        'overview' => 'projects.view', 'sprints' => 'projects.view', 'qa_hub' => 'qa.view',
        'financials' => 'billing.view', 'vault' => 'documents.view', 'team' => 'team.view',
    ];

    // Sections about a project; before the first one exists (until the SOW is signed) they say so
    public const PROJECT_TABS = ['overview', 'sprints', 'qa_hub', 'financials'];

    private function allowed(string $permission): bool
    {
        return Access::allows($this->user, Access::CLIENT_PREFIX . $permission);
    }

    // Validation messages in the page's language (lang/<locale>/portal.php)
    protected function messages(): array
    {
        return (array) __('portal.validation.dashboard');
    }

    protected function languageReturnUrl(): string
    {
        return route('client.dashboard');
    }

    public function mount()
    {
        // Client accounts only (guard 'client', client_users); staff use HQ
        $this->user = Auth::guard('client')->user();

        if (!$this->user || $this->user->status !== 'Active') {
            Auth::guard('client')->logout();
            abort(403, __('portal.errors.not_client'));
        }

        $this->organization = DB::table('organizations')->where('id', $this->user->organization_id)->first();
        if (!$this->organization) {
            Auth::guard('client')->logout();
            abort(404, __('portal.errors.no_organisation'));
        }

        // The organisation's preferred language, unless the person already chose one
        $this->applyOrganisationLocale($this->organization->preferred_language ?? null);

        $this->loadBrandSettings();

        // What this account may do (its role; an organisation's primary account is Client Admin)
        $this->can = collect(Access::catalogue())->filter(fn ($p) => $p['audience'] === 'client')
            ->mapWithKeys(fn ($p, $slug) => [substr($slug, strlen(Access::CLIENT_PREFIX)) => Access::allows($this->user, $slug)])->all();
        $this->activeTab = collect(self::TAB_PERMISSIONS)->filter(fn ($perm) => $this->can[$perm] ?? false)->keys()->first() ?? 'overview';
        // A notification opens its tab (/?tab=financials), if this account may see that tab
        $askedTab = request()->query('tab');
        if (is_string($askedTab) && isset(self::TAB_PERMISSIONS[$askedTab]) && ($this->can[self::TAB_PERMISSIONS[$askedTab]] ?? false)) {
            $this->activeTab = $askedTab;
        }

        // Project Routing
        $projectQuery = DB::table('projects')->where('organization_id', $this->organization->id)->orderBy('created_at', 'desc');
        if ($this->user->restricted_project_id) {
            $projectQuery->where('id', $this->user->restricted_project_id);
        }
        
        $this->allProjects = $projectQuery->get()->toArray();

        if (empty($this->allProjects)) {
            // No project yet: Documents (their proposals and SOWs) and Team still work; the other sections say why they're empty
            if (in_array($this->activeTab, self::PROJECT_TABS, true)) {
                $this->activeTab = collect(['vault', 'team'])->first(fn ($tab) => $this->can[self::TAB_PERMISSIONS[$tab]] ?? false) ?? $this->activeTab;
            }
            if ($this->activeTab === 'vault') $this->loadVault();
            if ($this->activeTab === 'team') $this->loadTeamCommand();

            return;
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
            $this->brandPrimary = $this->clientSettings->primary_color ?? '#08707C';
            $this->brandSecondary = $this->clientSettings->secondary_color ?? '#B3261E';
            $this->brandAccent = $this->clientSettings->accent_color ?? '#8A5700';
        }
    }

    // Ids this client may touch, checked by every action that takes an id from the browser
    private function ownsProject($projectId): bool
    {
        return $projectId && collect($this->allProjects)->contains('id', $projectId);
    }

    // A task the client may see and act on: on their project, and shown to the client (tasks.is_client_visible, docs/PROJECTS.md)
    private function ownsTask($taskId): bool
    {
        return $taskId && $this->activeProject && $this->clientTasks()->where('tasks.id', $taskId)->exists();
    }

    /** The active project's tasks the client sees: shown to the client, not deleted, on a milestone that isn't deleted */
    private function clientTasks()
    {
        return DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->activeProject->id)
            ->where('tasks.is_client_visible', 1)
            ->whereNull('tasks.deleted_at')->whereNull('project_phases.deleted_at');
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
                ? __('portal.lockout.verifying')
                : ($blockingInvoice->status === 'Overdue' ? __('portal.lockout.overdue') : __('portal.lockout.deposit'));
        } else {
            $this->financialLockout = false;
        }
    }

    // --- TAB LOADERS ---

    public function loadProjectTelemetry()
    {
        if (!$this->activeProject || !$this->allowed('projects.view')) return;

        // The project's lead (projects created before leads existed: whoever created it)
        $architect = DB::table('users')->where('id', $this->activeProject->lead_user_id ?? $this->activeProject->created_by)->first();
        $this->leadArchitect = $architect ? trim($architect->first_name . ' ' . mb_substr((string) $architect->last_name, 0, 1) . '.') : __('portal.overview.lead_fallback');
        
        $this->phases = DB::table('project_phases')->where('project_id', $this->activeProject->id)->whereNull('deleted_at')->orderBy('phase_number', 'asc')->get()
            ->map(fn ($phase) => (array) $phase)->toArray();
        // The same figure as HQ's project page: each milestone's weight × its share of done tasks (docs/PROJECTS.md, decision 3)
        $this->overallProgress = (int) round(ProjectPlan::progress(ProjectPlan::milestones($this->activeProject->id)));

        // The client's own approvals only: staff comments on a task stay inside HQ (docs/PROJECTS.md)
        $logs = $this->clientTasks()->join('task_comments', 'task_comments.task_id', '=', 'tasks.id')->where('task_comments.author_type', 'client')->whereNull('task_comments.deleted_at')
            ->orderBy('task_comments.created_at', 'desc')->limit(15)->select('task_comments.comment_text', 'task_comments.created_at');
        $this->liveLogs = People::joinAuthor($logs, 'task_comments.user_id', 'task_comments.author_type')->get()->map(function($log) {
            return ['time' => PortalFormat::date($log->created_at, 'datetime'), 'author' => trim(mb_substr((string) $log->first_name, 0, 1) . '. ' . $log->last_name), 'text' => $log->comment_text];
        })->toArray();

        $this->activeTasks = $this->clientTasks()->select('tasks.*', 'project_phases.title as phase_title')->orderByRaw("FIELD(tasks.status, 'QA_Review', 'In-Progress', 'To-Do', 'Done')")->orderBy('tasks.updated_at', 'desc')->get()->toArray();
    }

    public function loadQaHub()
    {
        if (!$this->activeProject || !$this->allowed('qa.view')) return;
        $this->activeTasks = $this->clientTasks()->select('tasks.id', 'tasks.title', 'tasks.status')->get()->toArray();
        // A ticket on an internal task keeps its own title; the task's is not shown
        $this->qaTickets = DB::table('qa_tickets')->leftJoin('tasks', fn ($j) => $j->on('qa_tickets.task_id', '=', 'tasks.id')->where('tasks.is_client_visible', 1))
            ->where('qa_tickets.project_id', $this->activeProject->id)->whereNull('qa_tickets.deleted_at')->select('qa_tickets.*', 'tasks.title as task_title')->orderBy('qa_tickets.created_at', 'desc')->get()->toArray();
    }

    public function loadFinancials()
    {
        if (!$this->activeProject || !$this->allowed('billing.view')) return;
        $this->invoices = DB::table('invoices')->where('project_id', $this->activeProject->id)->orderBy('created_at', 'desc')->get()->map(function($invoice) {
            $invoice->has_pending_pop = DB::table('transactions')->where('invoice_id', $invoice->id)->where('status', 'Pending Verification')->exists();
            return $invoice;
        })->toArray();
        $this->remittanceAccounts = DB::table('mdm_remittance_accounts')->where('is_active', 1)->get()->toArray();
    }

    public function loadVault()
    {
        if (!$this->allowed('documents.view')) return;

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

        // Sent through the opportunity pipeline: the version they have now (older ones are superseded). A member limited
        // to one project sees that project's SOW only. Links are made now, so they outlive the email's (docs/QA-PORTAL.md)
        $opportunityIds = DB::table('opportunities')->where('organization_id', $this->organization->id)->whereNull('deleted_at')->pluck('id');
        $restricted = $this->user->restricted_project_id;
        $this->vaultContracts = StatementOfWork::whereIn('opportunity_id', $opportunityIds)
            ->whereIn('status', [StatementOfWork::SENT, StatementOfWork::CHANGES_REQUESTED, StatementOfWork::SIGNED])
            ->when($restricted, fn ($q) => $q->where('project_id', $restricted))
            ->orderByDesc('created_at')->get()
            ->map(function (StatementOfWork $sow) {
                $paper = Sows::isImported($sow);
                $online = ! $paper && $sow->sent_at;

                return ['id' => $sow->id, 'title' => $sow->title, 'reference' => $sow->reference, 'version' => $sow->version,
                    'state' => $sow->status === StatementOfWork::SIGNED ? ($paper ? 'paper' : 'signed') : $sow->status,
                    'date' => PortalFormat::date($sow->signed_at ?? $sow->changes_requested_at ?? $sow->sent_at),
                    'open' => $online ? PortalLinks::fromPortal('portal.sow', $sow->id, $this->user->id) : null,
                    'pdf' => $online ? PortalLinks::fromPortal('portal.sow.pdf', $sow->id) : null];
            })->all();
        $this->vaultProposals = $restricted ? [] : Proposal::whereIn('opportunity_id', $opportunityIds)
            ->whereIn('status', [Proposal::SENT, Proposal::CHANGES_REQUESTED, Proposal::CLIENT_APPROVED])->whereNotNull('sent_at')
            ->orderByDesc('created_at')->get()
            ->map(fn (Proposal $proposal) => ['id' => $proposal->id, 'title' => $proposal->title, 'reference' => $proposal->proposal_ref, 'version' => $proposal->version,
                'state' => match ($proposal->status) { Proposal::CLIENT_APPROVED => 'approved', Proposal::CHANGES_REQUESTED => 'changes_requested', default => 'sent' },
                'date' => PortalFormat::date($proposal->client_approved_at ?? $proposal->sent_at),
                'open' => PortalLinks::fromPortal('portal.proposals.review', $proposal->id, $this->user->id),
                'pdf' => PortalLinks::fromPortal('portal.proposals.pdf', $proposal->id)])->all();
    }

    public function loadTeamCommand()
    {
        if (!$this->allowed('team.view')) return;
        // Portal accounts of this organisation (not leads). Only display fields: the list is
        // sent to the browser, so never select passwords or invitation tokens.
        $this->teamMembers = DB::table('client_users')
            ->leftJoin('projects', 'client_users.restricted_project_id', '=', 'projects.id')
            ->leftJoin('roles', 'client_users.role_id', '=', 'roles.id')
            ->where('client_users.organization_id', $this->organization->id)
            ->whereIn('client_users.lifecycle_stage', ['Onboarding', 'Active'])
            ->whereNull('client_users.deleted_at')
            ->select('client_users.id', 'client_users.first_name', 'client_users.last_name', 'client_users.email', 'client_users.job_title',
                     'client_users.status', 'client_users.lifecycle_stage', 'client_users.is_primary_client', 'client_users.restricted_project_id',
                     'client_users.last_login_at', 'client_users.created_at', 'projects.title as restricted_project_title',
                     'client_users.role_id', DB::raw("CASE WHEN client_users.is_primary_client = 1 THEN 'Client Admin' ELSE COALESCE(roles.name, 'No role') END as role_name"))
            ->get()->toArray();
        $this->clientRoles = DB::table('roles')->where('audience', 'client')->whereNull('deleted_at')->orderBy('id')->get(['id', 'name'])->toArray();
    }

    public function switchTab($tab)
    {
        if ($this->financialLockout && $tab !== 'financials') {
            session()->flash('lockout_error', __('portal.lockout.tab_closed') . ' ' . $this->lockoutReason);
            return;
        }
        $needs = self::TAB_PERMISSIONS[$tab] ?? null;
        if (!$needs || !$this->allowed($needs)) {
            session()->flash('lockout_error', __('portal.lockout.no_permission'));
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
        abort_unless($this->allowed('qa.create'), 403);
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
            // The title is what the client typed; a general message has no task (older tickets carry a
            // "[DIRECT COMM] " or "[BUG REPORT] " prefix, which the portal hides)
            $finalTitle = $this->qaTitle;
            
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

            $notifications = [];
            foreach (Access::staffWith('qa.edit') as $adminId) {
                $notifications[] = ['id' => (string) Str::uuid(), 'user_id' => $adminId, 'title' => $isGeneralMessage ? 'Message from a client' : 'Issue reported by a client', 'message' => "{$this->user->first_name} {$this->user->last_name} ({$this->organization->name}) " . ($isGeneralMessage ? 'sent a message' : 'reported an issue') . " on {$this->activeProject->title}: “{$this->qaTitle}”. Reply in Quality and issues.", 'link_url' => route('hq.ops.qa', ['project' => $this->activeProject->id]), 'type' => 'Project', 'created_at' => now()];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });
        
        $sentKey = empty($this->qaTaskId) ? 'portal.qa.sent_message' : 'portal.qa.sent_issue';
        $this->reset(['qaTitle', 'qaDescription', 'qaTaskId', 'qaSeverity', 'initialAttachment']); 
        $this->loadQaHub(); 
        session()->flash('qa_success', __($sentKey));
    }
    
    // --- DIRECT SPRINT APPROVALS ---
    public function approveTask($taskId)
    {
        abort_unless($this->allowed('projects.approve'), 403);
        // Only a task waiting for the client's review (the button shows on those alone)
        if (!$this->ownsTask($taskId) || !$this->clientTasks()->where('tasks.id', $taskId)->where('tasks.status', 'QA_Review')->exists()) return;

        DB::table('tasks')->where('id', $taskId)->update(['status' => 'Done', 'updated_at' => now()]);
        $this->tellProjectLead('Task approved by the client', "{$this->user->first_name} {$this->user->last_name} ({$this->organization->name}) approved “"
            . DB::table('tasks')->where('id', $taskId)->value('title') . "” on {$this->activeProject->title}.");
        
        DB::table('task_comments')->insert([
            'task_id' => $taskId, 'user_id' => $this->user->id, 'author_type' => 'client',
            'comment_text' => __('portal.tasks.approved_comment', [], 'en'), 'created_at' => now()
        ]);

        $this->loadProjectTelemetry();
        session()->flash('global_success', __('portal.tasks.approved'));
    }

    /** A bell for the project's lead about what the client just did, opening the project's milestone board */
    private function tellProjectLead(string $title, string $message): void
    {
        if (!$this->activeProject || !($this->activeProject->lead_user_id ?? null)) return;
        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->activeProject->lead_user_id, 'title' => $title,
            'message' => $message, 'link_url' => route('hq.ops.project', ['project' => $this->activeProject->id, 'tab' => 'board']), 'type' => 'Project', 'created_at' => now()]);
    }

    public function teleportToQa($taskId, $taskTitle)
    {
        abort_unless($this->allowed('qa.create'), 403);
        if (!$this->ownsTask($taskId)) return;
        $this->qaTaskId = $taskId;
        $this->qaTitle = __('portal.qa.prefill', ['task' => $taskTitle]);
        $this->switchTab('qa_hub');
    }

    // --- QA HUB OFFCANVAS & COMMUNICATION ---
    public function openTicket($ticketId)
    {
        abort_unless($this->allowed('qa.view'), 403);
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
        abort_unless($this->allowed('qa.create'), 403);
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
        abort_unless($this->allowed('qa.create'), 403);
        if (!$this->selectedTicket) return;

        $this->validate(['newCommentText' => 'required|string|min:2|max:5000']);

        DB::table('qa_comments')->insert([
            'id' => (string) Str::uuid(), 'ticket_id' => $this->selectedTicket->id, 'user_id' => $this->user->id, 'author_type' => 'client',
            'comment_text' => $this->newCommentText, 'is_internal_only' => 0,
            'created_at' => now(), 'updated_at' => now()
        ]);

        // Notify HQ
        $notifications = [];
        foreach (Access::staffWith('qa.edit') as $adminId) {
            $notifications[] = ['id' => (string) Str::uuid(), 'user_id' => $adminId, 'title' => 'Client replied to an issue', 'message' => "{$this->user->first_name} {$this->user->last_name} ({$this->organization->name}) replied on “{$this->selectedTicket->title}”. Read it in Quality and issues.", 'link_url' => route('hq.ops.qa', ['project' => $this->activeProject->id]), 'type' => 'Project', 'created_at' => now()];
        }
        if(!empty($notifications)) DB::table('notifications')->insert($notifications);

        $this->newCommentText = '';
        $this->openTicket($this->selectedTicket->id); // Refresh thread
    }

    public function approveTicketFix()
    {
        abort_unless($this->allowed('projects.approve'), 403);
        if (!$this->selectedTicket) return;

        DB::table('qa_tickets')->where('id', $this->selectedTicket->id)->update(['status' => 'Closed', 'updated_at' => now()]);
        if ($this->selectedTicket->task_id) {
            DB::table('tasks')->where('id', $this->selectedTicket->task_id)->update(['status' => 'Done', 'updated_at' => now()]);
        }
        
        $this->dispatch('close-qa');
        $this->loadQaHub();
        session()->flash('global_success', __('portal.qa.fix_approved'));
    }
    
    public function approveAndLockFromQa()
    {
        abort_unless($this->allowed('projects.approve'), 403);
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
                'comment_text' => __('portal.tasks.approved_from_issue_comment', [], 'en'),
                'created_at' => now()
            ]);
        }

        $this->dispatch('close-qa');
        $this->loadQaHub();
        $this->loadProjectTelemetry(); // Refreshes the Sprints tab in the background
        session()->flash('global_success', __('portal.qa.task_approved_from_issue'));
    }
    
    public function triggerPopUpload($invoiceId, $amountDue) {
        abort_unless($this->allowed('billing.create'), 403);
        if (!collect($this->invoices)->contains('id', $invoiceId)) return;
        $this->uploadInvoiceId = $invoiceId;
        $this->popAmount = $amountDue; 
        $this->popRef = ''; 
        $this->popWhtAmount = null;
    }
    
    public function cancelPopUpload() { 
        $this->uploadInvoiceId = null; 
        $this->reset(['popMethod', 'popRef', 'popAmount', 'popFile', 'popWhtAmount', 'popWhtFile']); 
    }
    
    public function submitProofOfPayment()
    {
        abort_unless($this->allowed('billing.create'), 403);
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

        $invoiceNumber = DB::table('invoices')->where('id', $this->uploadInvoiceId)->value('invoice_number');

        DB::transaction(function () use ($filePath, $whtFilePath, $invoiceNumber) {
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

            $notifications = [];
            foreach (Access::staffWith('finance.approve') as $adminId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $adminId, 'title' => 'Proof of payment uploaded',
                    'message' => "{$this->organization->name} uploaded proof of a {$this->activeProject->currency} " . number_format((float) $this->popAmount, 2) . " payment for invoice {$invoiceNumber}" . ($this->popWhtAmount > 0 ? ' (withholding tax ' . number_format((float) $this->popWhtAmount, 2) . ')' : '') . '. Verify it in Payment verification.', 'link_url' => route('hq.finance.escrow'), 'type' => 'Financial', 'created_at' => now()
                ];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });

        $this->cancelPopUpload(); 
        $this->loadFinancials(); 
        $this->enforceFinancialLockout();
        session()->flash('finance_success', __('portal.finance.pop_sent'));
    }

    public function saveBrandSettings()
    {
        abort_unless($this->allowed('settings.edit'), 403);
        $this->validate([
            'brandPrimary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'brandSecondary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brandAccent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'brandLogo' => 'nullable|image|mimes:png|max:5120',
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
        session()->flash('brand_success', __('portal.documents.saved'));
    }

    public function inviteTeamMember()
    {
        abort_unless($this->allowed('team.create'), 403);

        try {
            $this->validate([
                'inviteFirstName' => 'required|string|max:50', 
                'inviteLastName' => 'required|string|max:50', 
                'inviteEmail' => 'required|email|unique:client_users,email',
                'inviteProjectId' => 'nullable|string', // Added to prevent strict-type silent failures
                'inviteRoleId' => 'nullable|integer',
            ]);

            $roleId = $this->inviteRoleId ?: DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id');
            if (!$roleId || !$this->mayAssignRole($roleId)) {
                session()->flash('team_error', __('portal.team.role_forbidden')); return;
            }

            if (!empty($this->inviteProjectId) && !$this->ownsProject($this->inviteProjectId)) {
                session()->flash('team_error', __('portal.team.project_unknown')); return;
            }

            $portalAccounts = DB::table('client_users')->where('organization_id', $this->organization->id)
                ->whereIn('lifecycle_stage', ['Onboarding', 'Active'])->whereNull('deleted_at')->count();
            if ($portalAccounts >= 3) {
                session()->flash('team_error', __('portal.team.limit_reached')); return;
            }

            $primaryDomain = substr(strrchr($this->user->email, "@"), 1);
            $inviteDomain = substr(strrchr($this->inviteEmail, "@"), 1);
            if (strtolower($primaryDomain) !== strtolower($inviteDomain)) {
                session()->flash('team_error', __('portal.team.wrong_domain', ['domain' => strtolower($primaryDomain)])); return;
            }

            $token = Str::random(60);

            // No password until they open the setup link, so the account cannot sign in before then
            DB::table('client_users')->insert([
                'id' => (string) Str::uuid(), 'organization_id' => $this->organization->id,
                'is_primary_client' => 0, 'role_id' => $roleId, 'restricted_project_id' => $this->inviteProjectId ?: null,
                'first_name' => $this->inviteFirstName, 'last_name' => $this->inviteLastName, 'email' => $this->inviteEmail,
                'password' => null, 'invitation_token' => $token, 'lifecycle_stage' => 'Onboarding', 'status' => 'Active',
                'source' => 'invite', 'created_at' => now(), 'updated_at' => now()
            ]);

            // The invitation email: in the organisation's preferred language (else English)
            $invitedEmail = $this->inviteEmail;
            Mail::to($invitedEmail)
                ->locale(MailLocale::forRecipient($this->organization->preferred_language ?? null))
                ->send(new TeamMemberInvitation(
                    $this->inviteFirstName,
                    $this->inviteLastName,
                    trim($this->user->first_name . ' ' . $this->user->last_name),
                    $this->organization->name,
                    $token
                ));

            $this->reset(['inviteFirstName', 'inviteLastName', 'inviteEmail', 'inviteProjectId']);
            $this->loadTeamCommand();
            session()->flash('team_success', __('portal.team.invited', ['email' => $invitedEmail]));

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Livewire will catch this and display it via the frontend @errors block
            throw $e;
        } catch (\Exception $e) {
            // Catch DB constraint errors (like UUID mismatches) so it doesn't fail silently
            Log::error('Client team invite failed', ['organization_id' => $this->organization->id, 'message' => $e->getMessage()]);
            session()->flash('team_error', __('portal.team.invite_failed'));
        }
    }

    // A member this account may manage: same organisation, not itself, not the primary account
    private function manageableMember($userId)
    {
        if (!$this->allowed('team.edit') || $this->user->id === $userId) return null;

        return DB::table('client_users')->where('id', $userId)->where('organization_id', $this->organization->id)
            ->where('is_primary_client', 0)->whereNull('deleted_at')->first();
    }

    // A client role may only be given by someone who holds every permission in it
    private function mayAssignRole($roleId): bool
    {
        if (!DB::table('roles')->where('id', $roleId)->where('audience', 'client')->whereNull('deleted_at')->exists()) return false;

        return DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)->pluck('permissions.slug')
            ->every(fn ($p) => Access::allows($this->user, $p));
    }

    public function changeMemberRole($userId, $roleId)
    {
        if (!$this->manageableMember($userId) || !$this->mayAssignRole($roleId)) {
            session()->flash('team_error', __('portal.team.role_forbidden')); return;
        }
        DB::table('client_users')->where('id', $userId)->update(['role_id' => (int) $roleId, 'updated_at' => now()]);

        $this->loadTeamCommand();
        session()->flash('team_success', __('portal.team.role_updated'));
    }

    public function updateNodeAccess($userId, $projectId)
    {
        if (!$this->manageableMember($userId)) return;
        if (!empty($projectId) && !$this->ownsProject($projectId)) return;

        DB::table('client_users')->where('id', $userId)->where('organization_id', $this->organization->id)->update([
            'restricted_project_id' => empty($projectId) ? null : $projectId,
            'updated_at' => now()
        ]);
        
        $this->loadTeamCommand();
        session()->flash('team_success', __('portal.team.access_updated'));
    }

    public function toggleSuspendUser($userId)
    {
        $target = $this->manageableMember($userId);
        if ($target) {
            $newStatus = $target->status === 'Suspended' ? 'Active' : 'Suspended';
            // A new remember token ends any 'remember me' logins; ClientActive signs out open sessions
            DB::table('client_users')->where('id', $userId)->where('organization_id', $this->organization->id)->update(['status' => $newStatus, 'remember_token' => Str::random(60), 'updated_at' => now()]);
            $this->loadTeamCommand();
        }
    }

    public function triggerAuthenticatedRedline()
    {
        abort_unless($this->allowed('support.create'), 403);
        $this->validate(['redlineAuthDescription' => 'required|string|min:10']);
        DB::transaction(function () {
            DB::table('redline_sos_requests')->insert([
                'id' => (string) Str::uuid(), 'organization_id' => $this->organization->id, 'user_id' => $this->user->id,
                'problem_description' => "[AUTH CLIENT OVERRIDE] " . $this->redlineAuthDescription,
                'status' => 'Pending Triage', 'created_at' => now(), 'updated_at' => now()
            ]);
            $notifications = [];
            foreach (Access::staffWith('projects.approve') as $adminId) {
                $notifications[] = ['id' => (string) Str::uuid(), 'user_id' => $adminId, 'title' => 'Urgent support requested by a client', 'message' => "{$this->user->first_name} {$this->user->last_name} ({$this->organization->name}) asked for urgent support: “" . Str::limit($this->redlineAuthDescription, 120) . '”. Contact them as soon as possible.', 'type' => 'System', 'created_at' => now()];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });
        $this->showRedlineModal = false; $this->redlineAuthDescription = '';
        session()->flash('global_success', __('portal.urgent.sent'));
    }

    public function executeLogout()
    {
        Auth::guard('client')->logout();
        return redirect()->route('client.login');
    }

    public function render()
    {
        return view('livewire.client-os.dashboard')->title($this->organization->name ?? __('portal.app_name'));
    }
}