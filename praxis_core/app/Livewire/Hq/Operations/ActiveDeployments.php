<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
    public $kpiCapitalAtRisk = 0;
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

        // Isolation: without $canSeeAll, only projects with tasks assigned to this user
        if (! $this->canSeeAll) {
            $assignedProjectIds = DB::table('tasks')
                ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                ->where('tasks.assigned_to', Auth::id())
                ->pluck('project_phases.project_id')
                ->unique();
                
            $query->whereIn('projects.id', $assignedProjectIds);
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
            $taskStats = DB::table('tasks')
                ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                ->where('project_phases.project_id', $project->id)
                ->selectRaw('
                    SUM(tasks.story_points) as total_points,
                    SUM(CASE WHEN tasks.status = "Done" THEN tasks.story_points ELSE 0 END) as completed_points,
                    SUM(tasks.actual_hours_logged) as total_hours
                ')->first();

            $totalPoints = $taskStats->total_points ?? 0;
            $completedPoints = $taskStats->completed_points ?? 0;
            $progressPercent = $totalPoints > 0 ? round(($completedPoints / $totalPoints) * 100) : 0;
            $totalHours = $taskStats->total_hours ?? 0;

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

        $this->kpiSprintVelocity = DB::table('tasks')
            ->where('status', 'Done')
            ->where('updated_at', '>=', now()->subDays(7))
            ->sum('story_points');

        if ($this->canSeeMoney) {
            $this->kpiCapitalAtRisk = DB::table('invoices')
                ->join('projects', 'invoices.project_id', '=', 'projects.id')
                ->whereIn('projects.status', ['Active', 'Locked'])
                ->whereIn('invoices.status', ['Pending', 'Overdue'])
                ->sum('invoices.total_amount');
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
            $assignedProjectIds = DB::table('tasks')
                ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                ->where('tasks.assigned_to', Auth::id())
                ->pluck('project_phases.project_id')
                ->unique();

            $query->whereIn('projects.id', $assignedProjectIds);
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
            session()->flash('error', 'ACCESS DENIED: Insufficient clearance to initialize deployments.');
            return;
        }

        $this->validate([
            'newOrgId' => 'required|string|max:36',
            'newTitle' => 'required|string|max:255',
            'newBudget' => 'required|numeric|min:0',
            'newStatus' => 'required|string|in:Scoping,Active,Locked,Completed,Cancelled',
            'newStartDate' => 'nullable|date',
            'newEndDate' => 'nullable|date|after_or_equal:newStartDate',
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
        session()->flash('success', 'New deployment successfully initialized.');
    }

    // --- FINANCIAL LOCK OVERRIDE (finance.approve) ---
    public function toggleFinancialLock($projectId)
    {
        if (! Auth::user()->can('finance.approve')) {
            session()->flash('error', 'ACCESS DENIED: Only Level 5 Super Admins can override the financial matrix.');
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
                'description' => "Financial lock state changed for Project: {$project->title}",
                'ip_address' => request()->ip(),
                'created_at' => now()
            ]);
        });

        $this->loadData();
        session()->flash('success', $newLockStatus ? 'Project Executions Locked.' : 'Lock Overridden. Executions Restored.');
    }

    // --- CSV EXPORT ---
    public function exportCsv()
    {
        if (! Auth::user()->can('projects.export')) {
            session()->flash('error', 'ACCESS DENIED: Insufficient clearance to export deployments.');
            return;
        }

        $data = $this->activeTab === 'matrix' ? $this->matrixProjects : $this->ledgerProjects;

        if (empty($data)) {
            session()->flash('error', 'No data available to export.');
            return;
        }

        $filename = 'deployments_' . $this->activeTab . '_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $file = fopen('php://output', 'w');
            
            if ($this->activeTab === 'matrix') {
                fputcsv($file, ['Client', 'Project', 'Status', 'Locked', 'Story Points Done', 'Total Points', 'Progress %', 'Burn %', 'Spent $', 'Bleeding']);
                foreach ($data as $p) {
                    fputcsv($file, [
                        $p['client_name'], $p['title'], $p['status'], 
                        $p['is_financially_locked'] ? 'YES' : 'NO',
                        $p['completed_points'], $p['total_points'], $p['progress_percent'], 
                        $this->canSeeMoney ? $p['burn_percent'] : '',
                        $this->canSeeMoney ? round($p['spent_amount'], 2) : '',
                        $p['is_bleeding'] ? 'YES' : 'NO'
                    ]);
                }
            } else {
                fputcsv($file, ['Client', 'Project', 'Status', 'Budget', 'Start Date', 'Target End Date']);
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