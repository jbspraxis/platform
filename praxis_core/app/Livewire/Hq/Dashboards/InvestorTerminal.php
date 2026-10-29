<?php

namespace App\Livewire\Hq\Dashboards;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class InvestorTerminal extends Component
{
    public $metrics = [];
    public $activeDeployments = [];
    public $proposalPipeline = [];
    public $leadershipTeam = [];

    public function mount()
    {
        $this->loadInvestorMetrics();
    }

    public function loadInvestorMetrics()
    {
        // THE SAFETY NET: Pre-fill all keys to guarantee zero crashes
        $this->metrics = [
            'ytd_revenue' => 0,
            'pending_revenue' => 0,
            'active_pipeline' => 0,
            'proposal_pipeline' => 0,
            'active_deployments' => 0,
            'acv' => 0,
            'active_clients' => 0,
            'strike_team_size' => 0,
        ];

        try {
            $currentYear = Carbon::now()->year;

            // --- FINANCIALS ---
            // 1. YTD Cleared Revenue
            $this->metrics['ytd_revenue'] = DB::table('invoices')
                ->where('status', 'Paid')
                ->whereYear('paid_at', $currentYear)
                ->sum('total_amount');

            // 2. Pending Receivables (Money owed to the company)
            $this->metrics['pending_revenue'] = DB::table('invoices')
                ->where('status', 'Pending')
                ->sum('total_amount');

            // 3. Active Contract Value
            $this->metrics['active_pipeline'] = DB::table('projects')
                ->where('status', 'Active')
                ->sum('total_budget');

            // 4. Future Money (Proposals Pending Authorization)
            $this->metrics['proposal_pipeline'] = DB::table('proposals')
                ->whereIn('status', ['Pending Auth', 'Authorized'])
                ->sum('total_value');

            // --- OPERATIONS ---
            $this->metrics['active_deployments'] = DB::table('projects')
                ->where('status', 'Active')
                ->count();

            $this->metrics['active_clients'] = DB::table('organizations')
                ->where('status', 'Active Client')
                ->count();

            $this->metrics['strike_team_size'] = DB::table('users')
                ->where('status', 'Active')
                ->count();

            // ACV Calculation
            $totalProjects = DB::table('projects')->where('status', '!=', 'Cancelled')->count();
            $totalValue = DB::table('projects')->where('status', '!=', 'Cancelled')->sum('total_budget');
            $this->metrics['acv'] = $totalProjects > 0 ? ($totalValue / $totalProjects) : 0;

            // --- DATA GRIDS ---
            
            // Grid 1: Active Deployments (Where the capital is working right now)
            $this->activeDeployments = DB::table('projects')
                ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
                ->where('projects.status', 'Active')
                ->select('projects.title', 'projects.total_budget', 'projects.currency', 'projects.created_at', 'organizations.industry', 'organizations.name as client_name')
                ->orderBy('projects.total_budget', 'desc')
                ->limit(6)
                ->get();

            // Grid 2: Proposal Pipeline (Future capital generation)
            $this->proposalPipeline = DB::table('proposals')
                ->join('organizations', 'proposals.organization_id', '=', 'organizations.id')
                ->where('proposals.status', 'Pending Auth')
                ->select('proposals.title', 'proposals.total_value', 'proposals.currency', 'organizations.name as client_name', 'proposals.created_at')
                ->orderBy('proposals.created_at', 'desc')
                ->limit(4)
                ->get();

            // Grid 3: Leadership / Strike Team (Who is running the money)
            $team = DB::table('users')
                ->where('status', 'Active')
                ->whereNotNull('job_title')
                ->select('id', 'first_name', 'last_name', 'job_title')
                ->orderBy('created_at', 'asc')
                ->limit(5)
                ->get();

            // A person may hold several roles (staff_roles): show them as "Partner / Director, Engineer"
            $roleNames = DB::table('staff_roles')
                ->join('roles', 'staff_roles.role_id', '=', 'roles.id')
                ->whereIn('staff_roles.user_id', $team->pluck('id'))
                ->whereNull('roles.deleted_at')
                ->orderBy('roles.name')
                ->get(['staff_roles.user_id', 'roles.name'])
                ->groupBy('user_id');

            $this->leadershipTeam = $team->map(function ($member) use ($roleNames) {
                $member->role_name = isset($roleNames[$member->id]) ? $roleNames[$member->id]->pluck('name')->implode(', ') : null;
                unset($member->id);
                return $member;
            });

        } catch (\Exception $e) {
            // Live Failsafe
            $this->activeDeployments = collect([]);
            $this->proposalPipeline = collect([]);
            $this->leadershipTeam = collect([]);
        }
    }

    // Triggered when the investor clicks "Generate Report"
    public function logReportGeneration()
    {
        abort_unless(auth()->user()->can('dashboard.export'), 403);

        DB::table('system_audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => 'GENERATED_SHAREHOLDER_PDF',
            'ip_address' => request()->ip(),
            'created_at' => now()
        ]);
        
        // Dispatches a browser event to trigger the native print dialog
        $this->dispatch('trigger-print');
    }

    public function render()
    {
        return view('livewire.hq.dashboards.investor-terminal');
    }
}