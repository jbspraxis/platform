<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Livewire\Hq\HqMoney;

#[Layout('components.layouts.hq')]
class ActiveDeployments extends Component
{
    // What this user may do here (set in mount, see config/access.php)
    #[Locked]
    public bool $canSeeAll = false;    // projects.approve, projects.export or finance.view: every deployment, not only assigned ones
    #[Locked]
    public bool $canCreate = false;    // projects.create
    #[Locked]
    public bool $canSeeMoney = false;  // finance.view: budgets, burn, capital at risk
    #[Locked]
    public bool $canLock = false;      // finance.approve: financial lock override
    #[Locked]
    public bool $canExport = false;    // projects.export

    public $activeTab = 'matrix'; // 'matrix' or 'ledger'

    // View State
    public $matrixProjects = [];
    public $ledgerProjects = [];
    public $availableAssignees = [];
    public $availableOrganizations = [];
    
    // Filters
    public $searchQuery = '';
    public $filterStatus = '';
    public $filterHealth = '';
    public $filterAssignee = '';

    // KPIs (For Matrix Tab)
    public $kpiActiveDeployments = 0;
    public $kpiSprintVelocity = 0;
    public $kpiCapitalAtRisk = []; // per currency: ['NGN' => 537.5, 'USD' => 322.5]
    public $kpiBleedingProjects = 0;

    // Configuration
    private $firmHourlyRate = 150.00;

    // --- NEW PROJECT CREATION STATE ---
    public $newOrgId = '';
    public $newTitle = '';
    public $newDescription = '';
    public $newBudget = 0;
    public $newCurrency = 'USD';
    public $newStatus = 'Scoping';
    public $newStartDate = '';
    public $newEndDate = '';

    public function mount()
    {
        $user = Auth::user();
        // Managers, readers who export (Auditor) and finance (money columns, lock) see every deployment
        $this->canSeeAll = $user->can('projects.approve') || $user->can('projects.export') || $user->can('finance.view');
        $this->canCreate = $user->can('projects.create');
        $this->canSeeMoney = $user->can('finance.view');
        $this->canLock = $user->can('finance.approve');
        $this->canExport = $user->can('projects.export');

        $this->loadAssignees();
        $this->loadOrganizations();
        $this->loadData();
    }

    public function updated($property)
    {
        if (in_array($property, ['searchQuery', 'filterStatus', 'filterHealth', 'filterAssignee'])) {
            $this->loadData();
        }
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadData();
    }

    private function loadAssignees()
    {
        if ($this->canSeeAll) {
            $this->availableAssignees = DB::table('users')
                ->where('status', 'Active')
                ->whereIn('id', function($query) {
                    $query->select('assigned_to')->from('tasks')->whereNotNull('assigned_to');
                })
                ->select('id', 'first_name', 'last_name')
                ->get()
                ->toArray();
        }
    }

    private function loadOrganizations()
    {
        // For the Project Creation dropdown
        $this->availableOrganizations = DB::table('organizations')
            ->orderBy('name', 'asc')
            ->select('id', 'name')
            ->get()
            ->toArray();
    }

    public function loadData()
    {
        if ($this->activeTab === 'matrix') {
            $this->loadMatrixData();
        } else {
            $this->loadLedgerData();
        }
    }

    private function loadMatrixData()
    {
        // 1. Fetch Active & Locked Projects for Live Telemetry
        $query = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->whereIn('projects.status', ['Active', 'Locked'])
            ->select('projects.*', 'organizations.name as client_name')
            ->orderBy('projects.created_at', 'desc');

        // Apply Text Search
        if (!empty($this->searchQuery)) {
            $query->where(function($q) {
                $q->where('projects.title', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('organizations.name', 'like', '%' . $this->searchQuery . '%');
            });
        }

        // Isolation: without $canSeeAll, only the projects this user leads or has tasks on (App\Support\ProjectAccess)
        if (! $this->canSeeAll) {
            $query->whereIn('projects.id', \App\Support\ProjectAccess::assignedProjectIds());
        } else {
            if (!empty($this->filterAssignee)) {
                $filteredProjectIds = DB::table('tasks')
                    ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                    ->where('tasks.assigned_to', $this->filterAssignee)
                    ->pluck('project_phases.project_id')
                    ->unique();
                    
                $query->whereIn('projects.id', $filteredProjectIds);
            }
        }

        $rawProjects = $query->get();
        $processedProjects = [];
        $bleedingCount = 0;

        foreach ($rawProjects as $project) {
            // Progress is the milestones' weights × their done tasks (docs/PROJECTS.md, decision 3), as on the project page
            $milestones = \App\Support\Projects\ProjectPlan::milestones($project->id);
            $totalPoints = $milestones->sum('tasks_total');
            $completedPoints = $milestones->sum('tasks_done');
            $progressPercent = (int) round(\App\Support\Projects\ProjectPlan::progress($milestones));
            $totalHours = DB::table('tasks')
                ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                ->where('project_phases.project_id', $project->id)
                ->sum('tasks.actual_hours_logged');

            $spentAmount = $totalHours * $this->firmHourlyRate;
            $burnPercent = $project->total_budget > 0 ? round(($spentAmount / $project->total_budget) * 100) : 0;
            $isBleeding = false;

            $unpaidInvoices = DB::table('invoices')
                ->where('project_id', $project->id)
                ->whereIn('status', ['Pending', 'Overdue'])
                ->sum('total_amount');

            if ($this->canSeeMoney) {
                if ($burnPercent > ($progressPercent + 15)) {
                    $bleedingCount++;
                    $isBleeding = true;
                }
            }

            if ($this->filterHealth === 'Bleeding' && !$isBleeding) continue;
            if ($this->filterHealth === 'Healthy' && $isBleeding) continue;

            $processedProjects[] = [
                'id' => $project->id,
                'title' => $project->title,
                'client_name' => $project->client_name,
                'status' => $project->status,
                'is_financially_locked' => $project->is_financially_locked,
                'total_points' => $totalPoints,
                'completed_points' => $completedPoints,
                'progress_percent' => $progressPercent,
                // Money stays on the server without finance.view
                'total_budget' => $this->canSeeMoney ? $project->total_budget : null,
                'currency' => $project->currency,
                'spent_amount' => $this->canSeeMoney ? $spentAmount : null,
                'burn_percent' => $this->canSeeMoney ? $burnPercent : null,
                'unpaid_invoices' => $this->canSeeMoney ? $unpaidInvoices : null,
                'needs_midpoint_funds' => ($unpaidInvoices > 0 && $progressPercent >= 40),
                'is_bleeding' => $isBleeding
            ];
        }

        $this->matrixProjects = $processedProjects;

        // Global KPIs
        $this->kpiActiveDeployments = DB::table('projects')->whereIn('status', ['Active', 'Locked'])->count();
        $this->kpiBleedingProjects = $bleedingCount;

        // Tasks done in the last 7 days (story points are no longer shown, docs/PROJECTS.md decision 3)
        $this->kpiSprintVelocity = DB::table('tasks')
            ->where('status', 'Done')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        if ($this->canSeeMoney) {
            $this->kpiCapitalAtRisk = HqMoney::sumByCurrency(DB::table('invoices')
                ->join('projects', 'invoices.project_id', '=', 'projects.id')
                ->whereIn('projects.status', ['Active', 'Locked'])
                ->whereIn('invoices.status', ['Pending', 'Overdue']), 'invoices.total_amount', 'invoices.currency');
        }
    }

    private function loadLedgerData()
    {
        // Master List of ALL projects
        $query = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->select('projects.*', 'organizations.name as client_name')
            ->orderByRaw("FIELD(projects.status, 'Active', 'Scoping', 'Locked', 'Completed', 'Cancelled')")
            ->orderBy('projects.created_at', 'desc');

        if (!empty($this->searchQuery)) {
            $query->where(function($q) {
                $q->where('projects.title', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('organizations.name', 'like', '%' . $this->searchQuery . '%');
            });
        }

        if (!empty($this->filterStatus)) {
            $query->where('projects.status', $this->filterStatus);
        }

        // Still isolate without $canSeeAll
        if (! $this->canSeeAll) {
            $query->whereIn('projects.id', \App\Support\ProjectAccess::assignedProjectIds());
        }

        $this->ledgerProjects = json_decode(json_encode($query->get()), true);

        // Budgets stay on the server without finance.view
        if (! $this->canSeeMoney) {
            $this->ledgerProjects = array_map(fn ($p) => array_merge($p, ['total_budget' => null]), $this->ledgerProjects);
        }
    }

    // --- NEW PROJECT CREATION ---
    public function createProject()
    {
        if (! Auth::user()->can('projects.create')) {
            session()->flash('error', 'You don’t have permission to create projects. Ask an administrator to change your role.');
            return;
        }

        $this->validate([
            'newOrgId' => 'required|string|max:36',
            'newTitle' => 'required|string|max:255',
            'newBudget' => 'required|numeric|min:0',
            'newStatus' => 'required|string|in:Scoping,Active,Locked,Completed,Cancelled',
            'newStartDate' => 'nullable|date',
            'newEndDate' => 'nullable|date|after_or_equal:newStartDate',
        ], [], [
            'newOrgId' => 'client', 'newTitle' => 'project name', 'newBudget' => 'budget', 'newStatus' => 'status',
            'newStartDate' => 'start date', 'newEndDate' => 'planned end date',
        ]);

        $projectId = (string) Str::uuid();

        DB::table('projects')->insert([
            'id' => $projectId,
            'organization_id' => $this->newOrgId,
            'title' => $this->newTitle,
            'description' => $this->newDescription,
            'total_budget' => $this->newBudget,
            'currency' => $this->newCurrency,
            'status' => $this->newStatus,
            'start_date' => $this->newStartDate ?: null,
            'target_end_date' => $this->newEndDate ?: null,
            'lead_user_id' => Auth::id(), // whoever creates it leads it until the project page says otherwise
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Reset form and reload
        $this->reset(['newOrgId', 'newTitle', 'newDescription', 'newBudget', 'newStatus', 'newStartDate', 'newEndDate']);
        
        // If they created an active project, switch to the matrix to see it immediately
        if ($this->newStatus === 'Active') {
            $this->activeTab = 'matrix';
        }

        $this->loadData();
        session()->flash('success', 'Project created.');
    }

    // --- FINANCIAL LOCK OVERRIDE (finance.approve) ---
    public function toggleFinancialLock($projectId)
    {
        if (! Auth::user()->can('finance.approve')) {
            session()->flash('error', 'You don’t have permission to put projects on hold. Ask an administrator to change your role.');
            return;
        }

        $project = DB::table('projects')->where('id', $projectId)->first();
        if (!$project) return;

        $newLockStatus = $project->is_financially_locked ? 0 : 1;
        $overrideBy = $newLockStatus ? Auth::id() : null;
        $newProjectStatus = $newLockStatus ? 'Locked' : 'Active';

        DB::transaction(function () use ($projectId, $newLockStatus, $overrideBy, $newProjectStatus, $project) {
            DB::table('projects')->where('id', $projectId)->update([
                'is_financially_locked' => $newLockStatus,
                'financial_override_by' => $overrideBy,
                'status' => $newProjectStatus,
                'updated_at' => now()
            ]);

            DB::table('system_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => $newLockStatus ? 'PROJECT_LOCKED' : 'PROJECT_UNLOCKED',
                'description' => ($newLockStatus ? "Project put on hold: " : "Hold released on project: ") . $project->title,
                'ip_address' => request()->ip(),
                'created_at' => now()
            ]);
        });

        $this->loadData();
        session()->flash('success', $newLockStatus ? 'Project put on hold. It stays on hold until someone releases the hold.' : 'Hold released. The project is active again.');
    }

    // --- CSV EXPORT ---
    public function exportCsv()
    {
        if (! Auth::user()->can('projects.export')) {
            session()->flash('error', 'You don’t have permission to export projects. Ask an administrator to change your role.');
            return;
        }

        $data = $this->activeTab === 'matrix' ? $this->matrixProjects : $this->ledgerProjects;

        if (empty($data)) {
            session()->flash('error', 'There are no projects to export. Clear the search or filters and try again.');
            return;
        }

        $filename = 'projects_' . ($this->activeTab === 'matrix' ? 'active' : 'all') . '_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $file = fopen('php://output', 'w');
            
            if ($this->activeTab === 'matrix') {
                fputcsv($file, ['Client', 'Project', 'Status', 'On hold', 'Story points done', 'Total story points', 'Progress %', 'Budget used %', 'Spent (USD)', 'Spending ahead of progress']);
                foreach ($data as $p) {
                    fputcsv($file, [
                        $p['client_name'], $p['title'], $p['status'], 
                        $p['is_financially_locked'] ? 'Yes' : 'No',
                        $p['completed_points'], $p['total_points'], $p['progress_percent'], 
                        $this->canSeeMoney ? $p['burn_percent'] : '',
                        $this->canSeeMoney ? round($p['spent_amount'], 2) : '',
                        $p['is_bleeding'] ? 'Yes' : 'No'
                    ]);
                }
            } else {
                fputcsv($file, ['Client', 'Project', 'Status', 'Budget', 'Start date', 'Planned end date']);
                foreach ($data as $p) {
                    fputcsv($file, [
                        $p['client_name'], $p['title'], $p['status'], 
                        $this->canSeeMoney ? $p['total_budget'] . ' ' . $p['currency'] : '',
                        $p['start_date'], $p['target_end_date']
                    ]);
                }
            }
            fclose($file);
        }, $filename);
    }

    public function render()
    {
        return view('livewire.hq.operations.active-deployments');
    }
}