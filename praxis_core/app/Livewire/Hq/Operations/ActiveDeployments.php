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

    // The projects, the KPIs and the menus are worked out in render(), not kept here, so every poll and click
    // sends only the filters and the form back and forth

    // Filters
    public $searchQuery = '';
    public $filterStatus = '';
    public $filterHealth = '';
    public $filterAssignee = '';

    // The Create project panel is open: its list of clients is loaded only then
    public bool $creating = false;

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
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    /** The 30-second poll: render() reads the projects again */
    public function loadData(): void
    {
    }

    /** The Assigned to filter (managers, Active projects tab): active people with a task */
    private function assignees(): array
    {
        if (! $this->canSeeAll || $this->activeTab !== 'matrix') {
            return [];
        }

        return DB::table('users')
            ->where('status', 'Active')
            ->whereIn('id', function($query) {
                $query->select('assigned_to')->from('tasks')->whereNotNull('assigned_to');
            })
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->toArray();
    }

    /** The Create project panel's client menu, only while the panel is open */
    private function organizations(): array
    {
        if (! $this->canCreate || ! $this->creating) {
            return [];
        }

        return DB::table('organizations')
            ->orderBy('name', 'asc')
            ->select('id', 'name')
            ->get()
            ->toArray();
    }

    /** The Active projects tab: each project's progress and money, and the KPIs above them */
    private function matrixData(): array
    {
        // 1. Fetch Active & Locked Projects for Live Telemetry (only the columns the cards and the export use)
        $query = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->whereIn('projects.status', ['Active', 'Locked'])
            ->select('projects.id', 'projects.title', 'projects.status', 'projects.is_financially_locked', 'projects.total_budget', 'projects.currency',
                'organizations.name as client_name')
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

        // Milestones, hours and unpaid invoices for every listed project at once: a few queries, not a few per project
        $projectIds = $rawProjects->pluck('id')->all();
        $plans = \App\Support\Projects\ProjectPlan::milestonesFor($projectIds);
        // Hours on every task of the project, deleted ones included, as before
        $hoursByProject = $projectIds ? DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->whereIn('project_phases.project_id', $projectIds)
            ->groupBy('project_phases.project_id')
            ->selectRaw('project_phases.project_id, SUM(tasks.actual_hours_logged) AS hours')
            ->pluck('hours', 'project_id') : collect();
        // Pending and overdue invoices, every currency added together, as before
        $unpaidByProject = $projectIds ? DB::table('invoices')
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', ['Pending', 'Overdue'])
            ->groupBy('project_id')
            ->selectRaw('project_id, SUM(total_amount) AS unpaid')
            ->pluck('unpaid', 'project_id') : collect();

        foreach ($rawProjects as $project) {
            // Progress is the milestones' weights × their done tasks (docs/PROJECTS.md, decision 3), as on the project page
            $milestones = $plans->get($project->id) ?? collect();
            $totalPoints = $milestones->sum('tasks_total');
            $completedPoints = $milestones->sum('tasks_done');
            $progressPercent = (int) round(\App\Support\Projects\ProjectPlan::progress($milestones));
            $totalHours = $hoursByProject[$project->id] ?? 0;

            $spentAmount = $totalHours * $this->firmHourlyRate;
            $burnPercent = $project->total_budget > 0 ? round(($spentAmount / $project->total_budget) * 100) : 0;
            $isBleeding = false;

            $unpaidInvoices = $unpaidByProject[$project->id] ?? 0;

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

        return [
            'matrixProjects' => $processedProjects,
            // Global KPIs
            'kpiActiveDeployments' => DB::table('projects')->whereIn('status', ['Active', 'Locked'])->count(),
            'kpiBleedingProjects' => $bleedingCount,
            // Tasks done in the last 7 days (story points are no longer shown, docs/PROJECTS.md decision 3)
            'kpiSprintVelocity' => DB::table('tasks')
                ->where('status', 'Done')
                ->where('updated_at', '>=', now()->subDays(7))
                ->count(),
            // Per currency: ['NGN' => 537.5, 'USD' => 322.5]
            'kpiCapitalAtRisk' => $this->canSeeMoney ? HqMoney::sumByCurrency(DB::table('invoices')
                ->join('projects', 'invoices.project_id', '=', 'projects.id')
                ->whereIn('projects.status', ['Active', 'Locked'])
                ->whereIn('invoices.status', ['Pending', 'Overdue']), 'invoices.total_amount', 'invoices.currency') : [],
        ];
    }

    /** The Full list tab */
    private function ledgerData(): array
    {
        // Master List of ALL projects (only the columns the table and the export use)
        $query = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->select('projects.id', 'projects.title', 'projects.status', 'projects.is_financially_locked', 'projects.total_budget', 'projects.currency',
                'projects.start_date', 'projects.target_end_date', 'organizations.name as client_name')
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

        $projects = json_decode(json_encode($query->get()), true);

        // Budgets stay on the server without finance.view
        if (! $this->canSeeMoney) {
            $projects = array_map(fn ($p) => array_merge($p, ['total_budget' => null]), $projects);
        }

        return $projects;
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

        session()->flash('success', $newLockStatus ? 'Project put on hold. It stays on hold until someone releases the hold.' : 'Hold released. The project is active again.');
    }

    // --- CSV EXPORT ---
    public function exportCsv()
    {
        if (! Auth::user()->can('projects.export')) {
            session()->flash('error', 'You don’t have permission to export projects. Ask an administrator to change your role.');
            return;
        }

        // What the tab shows now, with the same search and filters
        $data = $this->activeTab === 'matrix' ? $this->matrixData()['matrixProjects'] : $this->ledgerData();

        if (empty($data)) {
            session()->flash('error', 'There are no projects to export. Clear the search or filters and try again.');
            return;
        }

        $filename = 'projects_' . ($this->activeTab === 'matrix' ? 'active' : 'all') . '_' . now()->format('Y_m_d_His') . '.csv';
        // The page is unchanged: only the file goes back, not the projects worked out again
        $this->skipRender();

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
        $data = $this->activeTab === 'matrix'
            ? $this->matrixData() + ['ledgerProjects' => []]
            : ['ledgerProjects' => $this->ledgerData(), 'matrixProjects' => [], 'kpiActiveDeployments' => 0, 'kpiSprintVelocity' => 0, 'kpiCapitalAtRisk' => [], 'kpiBleedingProjects' => 0];

        return view('livewire.hq.operations.active-deployments', $data + [
            'availableAssignees' => $this->assignees(),
            'availableOrganizations' => $this->organizations(),
        ]);
    }
}