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
use App\Support\Chat\ProjectChat;
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
    // Public properties travel to the browser and back on every click, so they hold
    // only the fields the page shows or the actions use (never signatures, codes,
    // IP addresses or internal notes), and only the open section's rows.

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
    // What the delivery team needs from the client, milestone by milestone (13 Nov 2026): the open items, and how
    // many are received; the form that marks one sent (a note and maybe a file)
    #[Locked]
    public $clientNeeds = [];
    #[Locked]
    public $clientNeedsReceived = 0;
    #[Locked]
    public ?int $needItemId = null;
    public $needNote = '';
    public $needFile;
    #[Locked]
    public $activeTasks = [];

    // Quality and issues: the issues of the project (their cards in the conversation), and the conversation's
    // unread count and last message. The conversation itself is App\Livewire\ClientOs\DeliveryChat (docs/CHAT.md)
    #[Locked]
    public $qaTickets = [];
    #[Locked]
    public $qaChat = [];
    // Opened from a notification (/?chat=<project>): the conversation opens with the page
    #[Locked]
    public bool $openChat = false;

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
    /** Each piece of work that has client meetings: its meetings page (upcoming meetings, minutes, booking a follow-up) */
    #[Locked]
    public $vaultMeetings = [];
    #[Locked]
    public $teamMembers = [];
    public $inviteFirstName = '';
    public $inviteLastName = '';
    public $inviteEmail = '';
    public $inviteProjectId = ''; 
    public $inviteRoleId = '';

    // Portal accounts an organisation's admins may have in all (owner, 2 Nov 2026: 10; HQ › Accounts has no limit)
    public const MAX_PORTAL_ACCOUNTS = 10;

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

    // The rows each section shows. Leaving a section empties its rows (loadTab), so the page's state never carries
    // every section at once; the section reloads them when it is opened again
    private const TAB_DATA = [
        'overview' => ['phases', 'liveLogs', 'clientNeeds'],
        'sprints' => ['activeTasks'],
        'qa_hub' => ['qaTickets', 'qaChat'],
        'financials' => ['invoices', 'remittanceAccounts'],
        'vault' => ['vaultSows', 'vaultSignoffs', 'vaultContracts', 'vaultProposals', 'vaultMeetings'],
        'team' => ['teamMembers', 'clientRoles'],
    ];

    // Sections about a project; before the first one exists (until the SOW is signed) they say so
    public const PROJECT_TABS = ['overview', 'sprints', 'qa_hub', 'financials'];

    // What a payment hold closes: the project's work. Invoices stay open to pay; Documents and Team aren't the project's
    public const LOCKED_TABS = ['overview', 'sprints', 'qa_hub'];

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

        // The three fields the portal uses; the rest of the row (internal CRM notes) stays on the server
        $this->organization = DB::table('organizations')->where('id', $this->user->organization_id)->first(['id', 'name', 'preferred_language']);
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

        // Project Routing (the columns the page and the actions read: the project picker, the overview, the lockout)
        $projectQuery = DB::table('projects')->where('organization_id', $this->organization->id)->orderBy('created_at', 'desc')
            ->select('id', 'title', 'status', 'total_budget', 'currency', 'lead_user_id', 'created_by');
        if ($this->user->restricted_project_id) {
            $projectQuery->where('id', $this->user->restricted_project_id);
        }
        
        $this->allProjects = $projectQuery->get()->toArray();

        if (empty($this->allProjects)) {
            // No project yet: Documents (their proposals and SOWs) and Team still work; the other sections say why they're empty
            if (in_array($this->activeTab, self::PROJECT_TABS, true)) {
                $this->activeTab = collect(['vault', 'team'])->first(fn ($tab) => $this->can[self::TAB_PERMISSIONS[$tab]] ?? false) ?? $this->activeTab;
            }
            $this->loadTab();

            return;
        }

        $defaultProject = collect($this->allProjects)->firstWhere('status', 'Active') 
                       ?? collect($this->allProjects)->firstWhere('status', 'Scoping')
                       ?? $this->allProjects[0];
                       
        // A notification about the conversation (/?chat=<project id>) opens it on its project
        $askedChat = request()->query('chat');
        if (is_string($askedChat) && $askedChat !== '' && ($this->can['qa.view'] ?? false)) {
            $this->openChat = true;
            $defaultProject = collect($this->allProjects)->firstWhere('id', $askedChat) ?? $defaultProject;
        }

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

        // On hold: to the invoices if this account may see them, else to a section that stays open (a member without
        // billing used to land on an invoices page its role can't read, with every other section closed)
        if ($this->financialLockout && in_array($this->activeTab, self::LOCKED_TABS, true)) {
            $this->activeTab = collect(['financials', 'vault', 'team'])->first(fn ($tab) => $this->allowed(self::TAB_PERMISSIONS[$tab])) ?? $this->activeTab;
        }

        // Initialize default tab data (nothing kept from the project shown before)
        $this->loadTab(true);
    }

    /**
     * Load the open section's rows. The other sections' rows are emptied first (every section's, after a change of
     * project), so the page's state carries one section at a time and doesn't grow as the client moves around.
     */
    private function loadTab(bool $everything = false): void
    {
        $keep = $everything ? [] : (self::TAB_DATA[$this->activeTab] ?? []);
        $stale = array_values(array_unique(array_diff(array_merge(...array_values(self::TAB_DATA)), $keep)));
        if ($stale) $this->reset($stale); // never an empty list: reset() with none resets every property

        match ($this->activeTab) {
            'overview' => $this->loadOverview(),
            'sprints' => $this->loadSprints(),
            'qa_hub' => $this->loadQaHub(),
            'financials' => $this->loadFinancials(),
            'vault' => $this->loadVault(),
            'team' => $this->loadTeamCommand(),
            default => null,
        };
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
            // Someone who can't see invoices is told who can
            $member = $this->allowed('billing.view') ? '' : '_member';
            $this->lockoutReason = $hasPendingTx
                ? __('portal.lockout.verifying')
                : ($blockingInvoice->status === 'Overdue' ? __('portal.lockout.overdue' . $member) : __('portal.lockout.deposit' . $member));
        } else {
            $this->financialLockout = false;
        }
    }

    // --- TAB LOADERS ---

    // Overview: the lead, the milestones and the client's own activity (the task list is the Progress section's)
    public function loadOverview()
    {
        if (!$this->activeProject || !$this->allowed('projects.view')) return;

        // The project's lead (projects created before leads existed: whoever created it)
        $architect = DB::table('users')->where('id', $this->activeProject->lead_user_id ?? $this->activeProject->created_by)->first(['first_name', 'last_name']);
        $this->leadArchitect = $architect ? trim($architect->first_name . ' ' . mb_substr((string) $architect->last_name, 0, 1) . '.') : __('portal.overview.lead_fallback');

        // The milestones, read once for both: their steps (the four fields shown) and the same figure as HQ's project
        // page, each milestone's weight × its share of done tasks (docs/PROJECTS.md, decision 3)
        $milestones = ProjectPlan::milestones($this->activeProject->id);
        $this->phases = $milestones->map(fn ($m) => ['id' => $m->id, 'phase_number' => $m->phase_number, 'title' => $m->title, 'status' => $m->status])->all();
        $this->overallProgress = (int) round(ProjectPlan::progress($milestones));

        // What the team needs from the client: the open items, in milestone order (ClientDependencies)
        $needs = \App\Support\Projects\ClientDependencies::forProject($this->activeProject->id);
        $this->clientNeedsReceived = $needs->whereIn('status', ['received', 'waived'])->count();
        $this->clientNeeds = $needs->whereIn('status', \App\Support\Projects\ClientDependencies::OPEN)->values()->map(fn ($i) => [
            'id' => (int) $i->id, 'milestone' => 'M' . $i->phase_number, 'group' => trim(($i->group_code ? $i->group_code . ' ' : '') . ($i->group_title ?? '')),
            'title' => $i->title, 'due' => $i->due_date ? PortalFormat::date($i->due_date) : null, 'late_days' => (int) $i->late_days, 'status' => $i->status,
            'sent' => $i->sent_at ? PortalFormat::date($i->sent_at) : null,
        ])->all();

        // The client's own approvals only: staff comments on a task stay inside HQ (docs/PROJECTS.md)
        $logs = $this->clientTasks()->join('task_comments', 'task_comments.task_id', '=', 'tasks.id')->where('task_comments.author_type', 'client')->whereNull('task_comments.deleted_at')
            ->orderBy('task_comments.created_at', 'desc')->limit(15)->select('task_comments.comment_text', 'task_comments.created_at');
        $this->liveLogs = People::joinAuthor($logs, 'task_comments.user_id', 'task_comments.author_type')->get()->map(function($log) {
            return ['time' => PortalFormat::date($log->created_at, 'datetime'), 'author' => trim(mb_substr((string) $log->first_name, 0, 1) . '. ' . $log->last_name), 'text' => $log->comment_text];
        })->toArray();
    }

    // Progress: the client's tasks, milestone by milestone (what the list shows, not the tasks' descriptions)
    public function loadSprints()
    {
        if (!$this->activeProject || !$this->allowed('projects.view')) return;

        $this->activeTasks = $this->clientTasks()->select('tasks.id', 'tasks.title', 'tasks.status', 'project_phases.title as phase_title')->orderBy('project_phases.phase_number')->orderByRaw("FIELD(tasks.status, 'QA_Review', 'In-Progress', 'To-Do', 'Done')")->orderBy('tasks.updated_at', 'desc')->get()->toArray();
    }

    public function loadQaHub()
    {
        if (!$this->activeProject || !$this->allowed('qa.view')) return;
        // A ticket on an internal task keeps its own title; the task's is not shown. The list shows the start of the
        // description (excerpt), its card in the conversation (opened from the list) and how many replies it has
        $tickets = DB::table('qa_tickets')->leftJoin('tasks', fn ($j) => $j->on('qa_tickets.task_id', '=', 'tasks.id')->where('tasks.is_client_visible', 1))
            ->where('qa_tickets.project_id', $this->activeProject->id)->whereNull('qa_tickets.deleted_at')
            ->select('qa_tickets.id', 'qa_tickets.task_id', 'qa_tickets.title', 'qa_tickets.status', 'qa_tickets.severity', 'qa_tickets.created_at', 'qa_tickets.description', 'tasks.title as task_title')
            ->orderBy('qa_tickets.created_at', 'desc')->get();
        // Tickets made outside the conversation (handover points, imports) get their card now
        $cards = $tickets->isEmpty() ? collect() : DB::table('chat_messages')->whereIn('qa_ticket_id', $tickets->pluck('id'))->where('kind', 'issue')->pluck('id', 'qa_ticket_id');
        foreach ($tickets as $ticket) {
            if (! isset($cards[$ticket->id])) {
                $cards[$ticket->id] = ProjectChat::cardFor($ticket->id);
            }
        }
        $replies = $cards->isEmpty() ? collect() : ProjectChat::visible($this->activeProject->id, 'client')->whereIn('m.reply_to_id', $cards->values())
            ->where('m.kind', 'text')->groupBy('m.reply_to_id')->selectRaw('m.reply_to_id, COUNT(*) as n')->pluck('n', 'reply_to_id');
        $this->qaTickets = $tickets->map(function ($ticket) use ($cards, $replies) {
            $ticket->excerpt = Str::limit((string) $ticket->description, 100);
            unset($ticket->description);
            $ticket->card_id = (int) ($cards[$ticket->id] ?? 0);
            $ticket->replies = (int) ($replies[$ticket->card_id] ?? 0);
            return $ticket;
        })->toArray();

        // The conversation: unread for this person, and its last message in a line
        [$messages] = ProjectChat::messages($this->activeProject->id, 'client', 1);
        $last = $messages[0] ?? null;
        $this->qaChat = [
            'unread' => ProjectChat::unread($this->activeProject->id, 'client', $this->user->id),
            'last' => $last ? [
                'name' => $last['party'] === 'client' && $last['author_id'] === $this->user->id ? __('chat.you') : $last['name'],
                'time' => PortalFormat::date($last['at'], 'datetime'),
                'text' => match ($last['kind']) {
                    'issue' => __('chat.summary.issue', ['title' => $last['ticket']['title'] ?? '']),
                    'event' => __('chat.events.' . $last['event'] . '.short'),
                    default => $last['body'] !== '' ? Str::limit(preg_replace('/\s+/u', ' ', $last['body']), 160) : __('chat.summary.' . ($last['files'][0]['kind'] ?? 'file')),
                },
            ] : null,
        ];
    }

    public function loadFinancials()
    {
        if (!$this->activeProject || !$this->allowed('billing.view')) return;
        $invoices = DB::table('invoices')->where('project_id', $this->activeProject->id)->orderBy('created_at', 'desc')
            ->get(['id', 'invoice_number', 'issued_at', 'due_date', 'status', 'total_amount', 'currency']);
        // In one query (not one per invoice): the invoices with a proof of payment waiting for HQ's check, and what is
        // already paid on each (verified payments, HQ-recorded ones too), so the client sees what is left
        $payments = $invoices->isEmpty() ? collect() : DB::table('transactions')->whereIn('invoice_id', $invoices->pluck('id'))->whereNull('deleted_at')
            ->groupBy('invoice_id')
            ->selectRaw("invoice_id, MAX(CASE WHEN status = 'Pending Verification' THEN 1 ELSE 0 END) as pending, COALESCE(SUM(CASE WHEN status = 'Verified' THEN amount_claimed + COALESCE(wht_deducted, 0) + COALESCE(tolerance_writeoff, 0) ELSE 0 END), 0) as settled")
            ->get()->keyBy('invoice_id');
        $this->invoices = $invoices->map(function($invoice) use ($payments) {
            $invoice->has_pending_pop = (int) ($payments->get($invoice->id)->pending ?? 0) === 1;
            $invoice->paid_so_far = round((float) ($payments->get($invoice->id)->settled ?? 0), 2);
            $invoice->balance = max(0, round((float) $invoice->total_amount - $invoice->paid_so_far, 2));
            return $invoice;
        })->toArray();
        // Only the accounts in the currency of the invoices held by the company that bills them (D8), with the fields shown
        $this->remittanceAccounts = \App\Support\Finance\ReceivingAccounts::forProject($this->activeProject)
            ->map(fn ($account) => (object) \Illuminate\Support\Arr::only((array) $account, ['type', 'currency', 'provider_name', 'account_name', 'account_reference', 'routing_code']))
            ->toArray();
    }

    public function loadVault()
    {
        if (!$this->allowed('documents.view')) return;

        // Older proposals and handovers: the fields the list and its links use (never the signatures, codes or IP addresses)
        $this->vaultSows = DB::table('proposals')
            ->where('organization_id', $this->organization->id)
            ->whereIn('status', ['Authorized', 'Converted'])
            ->orderBy('created_at', 'desc')
            ->get(['title', 'proposal_ref', 'updated_at', 'token_hash'])->toArray();

        $this->vaultSignoffs = DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->where('projects.organization_id', $this->organization->id)
            ->whereIn('project_signoffs.status', ['Signed', 'Frozen'])
            ->select('project_signoffs.token', 'project_signoffs.client_signature_hash', 'projects.title as project_title')
            ->orderBy('project_signoffs.created_at', 'desc')
            ->get()->toArray();

        // Sent through the opportunity pipeline: the version they have now (older ones are superseded). A member limited
        // to one project sees that project's SOW only. Links are made now, so they outlive the email's (docs/QA-PORTAL.md)
        // Each query reads only the columns the list uses: a version's sections and signature images stay in the database
        $opportunityIds = DB::table('opportunities')->where('organization_id', $this->organization->id)->whereNull('deleted_at')->pluck('id');
        $restricted = $this->user->restricted_project_id;
        $this->vaultContracts = StatementOfWork::whereIn('opportunity_id', $opportunityIds)
            ->whereIn('status', [StatementOfWork::SENT, StatementOfWork::CHANGES_REQUESTED, StatementOfWork::SIGNED])
            ->when($restricted, fn ($q) => $q->where('project_id', $restricted))
            ->orderByDesc('created_at')->get(['id', 'title', 'reference', 'version', 'status', 'signed_at', 'changes_requested_at', 'sent_at', 'proposal_id'])
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
            ->orderByDesc('created_at')->get(['id', 'title', 'proposal_ref', 'version', 'status', 'client_approved_at', 'sent_at'])
            ->map(fn (Proposal $proposal) => ['id' => $proposal->id, 'title' => $proposal->title, 'reference' => $proposal->proposal_ref, 'version' => $proposal->version,
                'state' => match ($proposal->status) { Proposal::CLIENT_APPROVED => 'approved', Proposal::CHANGES_REQUESTED => 'changes_requested', default => 'sent' },
                'date' => PortalFormat::date($proposal->client_approved_at ?? $proposal->sent_at),
                'open' => PortalLinks::fromPortal('portal.proposals.review', $proposal->id, $this->user->id),
                'pdf' => PortalLinks::fromPortal('portal.proposals.pdf', $proposal->id)])->all();

        // Meetings: each piece of work with a client meeting (a member limited to one project: that project's work only)
        $this->vaultMeetings = \App\Models\Opportunity::whereIn('id', $opportunityIds)->where('stage', '!=', 'lost')
            ->whereHas('meetings', fn ($q) => $q->where('kind', 'client'))
            ->when($restricted, fn ($q) => $q->where('project_id', $restricted))
            ->orderByDesc('created_at')->get(['id', 'title'])
            ->map(fn ($o) => ['id' => $o->id, 'title' => $o->title, 'link' => PortalLinks::fromPortal('portal.meetings', $o->id, $this->user->id)])->all();
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
        $this->clientRoles = DB::table('roles')->where('audience', 'client')->whereNull('deleted_at')->orderBy('id')->get(['id', 'name', 'slug'])->toArray();
    }

    public function switchTab($tab)
    {
        if ($this->financialLockout && in_array($tab, self::LOCKED_TABS, true)) {
            session()->flash('lockout_error', __('portal.lockout.tab_closed') . ' ' . $this->lockoutReason);
            return;
        }
        $needs = self::TAB_PERMISSIONS[$tab] ?? null;
        if (!$needs || !$this->allowed($needs)) {
            session()->flash('lockout_error', __('portal.lockout.no_permission'));
            return;
        }
        $this->activeTab = $tab;
        
        // Dynamically load the required data for the tab (and drop the rows of the one left)
        $this->loadTab();
    }

    // --- FORM SUBMISSIONS ---

    // --- DIRECT SPRINT APPROVALS ---
    public function approveTask($taskId)
    {
        abort_unless($this->allowed('projects.approve'), 403);
        if (!$this->activeProject) return;
        // Only a task waiting for the client's review (the button shows on those alone)
        if (!$this->ownsTask($taskId) || !$this->clientTasks()->where('tasks.id', $taskId)->where('tasks.status', 'QA_Review')->exists()) return;

        DB::table('tasks')->where('id', $taskId)->update(['status' => 'Done', 'updated_at' => now()]);
        $this->tellProjectLead('Task approved by the client', "{$this->user->first_name} {$this->user->last_name} ({$this->organization->name}) approved “"
            . DB::table('tasks')->where('id', $taskId)->value('title') . "” on {$this->activeProject->title}.");
        
        DB::table('task_comments')->insert([
            'task_id' => $taskId, 'user_id' => $this->user->id, 'author_type' => 'client',
            'comment_text' => __('portal.tasks.approved_comment', [], 'en'), 'created_at' => now()
        ]);

        $this->loadSprints(); // the Approve button is on Progress
        session()->flash('global_success', __('portal.tasks.approved'));
    }

    /** A bell for the project's lead about what the client just did, opening the project's milestone board */
    private function tellProjectLead(string $title, string $message): void
    {
        if (!$this->activeProject || !($this->activeProject->lead_user_id ?? null)) return;
        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->activeProject->lead_user_id, 'title' => $title,
            'message' => $message, 'link_url' => route('hq.ops.project', ['project' => $this->activeProject->id, 'tab' => 'board']), 'type' => 'Project', 'created_at' => now()]);
    }

    /** "Mark as sent" on something the team needs: opens the small form */
    public function openNeed(int $id): void
    {
        abort_unless($this->allowed('projects.view'), 403);
        if (! collect($this->clientNeeds)->contains('id', $id)) {
            return;
        }
        $this->resetErrorBag();
        $this->needItemId = $id;
        $this->needNote = '';
        $this->needFile = null;
    }

    public function cancelNeed(): void
    {
        $this->needItemId = null;
        $this->reset(['needNote', 'needFile']);
    }

    public function submitNeedSent(): void
    {
        abort_unless($this->allowed('projects.view'), 403);
        if (! $this->activeProject || ! $this->needItemId) {
            return;
        }
        $this->validate([
            'needNote' => 'nullable|string|max:2000',
            'needFile' => 'nullable|file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt,zip,ppt,pptx',
        ]);
        $item = DB::table('milestone_dependencies')->where('id', $this->needItemId)->where('project_id', $this->activeProject->id)->whereNull('deleted_at')
            ->whereIn('status', \App\Support\Projects\ClientDependencies::OPEN)->first();
        if (! $item || ! $this->ownsProject($item->project_id)) {
            $this->cancelNeed();
            return;
        }
        if (trim((string) $this->needNote) === '' && ! $this->needFile) {
            $this->addError('needNote', __('portal.needs.note_or_file'));
            return;
        }
        $path = $this->needFile ? $this->needFile->store('dependency_files/' . $item->project_id, 'local') : null;
        $name = $this->needFile ? $this->needFile->getClientOriginalName() : null;
        \App\Support\Projects\ClientDependencies::markSent($item, $this->user, $this->needNote, $path, $name);

        $this->cancelNeed();
        $this->loadOverview();
        session()->flash('global_success', __('portal.needs.sent'));
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
        if (!$this->activeProject) return;
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
            if ($portalAccounts >= self::MAX_PORTAL_ACCOUNTS) {
                session()->flash('team_error', __('portal.team.limit_reached', ['max' => self::MAX_PORTAL_ACCOUNTS])); return;
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