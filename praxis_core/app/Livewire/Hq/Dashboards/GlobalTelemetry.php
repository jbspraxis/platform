<?php

namespace App\Livewire\Hq\Dashboards;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class GlobalTelemetry extends Component
{
    public $kpis = [];
    public $recentLeads = [];
    public $criticalAlerts = [];
    
    // Dynamic Chart Data Arrays
    public $chartLabels = [];
    public $chartRevenue = [];
    public $chartBurn = [];

    public function mount()
    {
        $this->loadTelemetry();
    }

    public function loadTelemetry()
    {
        // SAFETY NET: Pre-initialize to prevent undefined array keys
        $this->kpis = [
            'capital_cleared' => 0, 
            'ar_pipeline' => 0, 
            'active_deployments' => 0, 
            'total_leads' => 0,
            'strike_capacity' => 0,
            'proposal_win_rate' => 0
        ];

        try {
            $currentYear = Carbon::now()->year;
            $currentMonth = Carbon::now()->month;

            // 1. Core Financial & Operational Aggregation
            $this->kpis['capital_cleared'] = DB::table('invoices')->where('status', 'Paid')->sum('total_amount');
            $this->kpis['ar_pipeline'] = DB::table('invoices')->where('status', 'Pending')->sum('total_amount');
            $this->kpis['active_deployments'] = DB::table('projects')->where('status', 'Active')->count();
            $this->kpis['total_leads'] = DB::table('organizations')->where('status', 'Lead')->count();
            
            // Strike Team Active Logging (Who is working right now)
            $this->kpis['strike_capacity'] = DB::table('task_time_logs')->whereNull('end_time')->distinct('user_id')->count();

            // 2. Recent Intelligence (Leads)
            $this->recentLeads = DB::table('organizations')
                ->where('status', 'Lead')
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get();

            // 3. System Alerts / Financial Bottlenecks (Ageing Invoices > 30 days)
            $this->criticalAlerts = DB::table('invoices')
                ->join('organizations', 'invoices.organization_id', '=', 'organizations.id')
                ->where('invoices.status', 'Pending')
                ->where('invoices.due_date', '<', Carbon::now()->subDays(30))
                ->select('invoices.invoice_number', 'invoices.total_amount', 'invoices.currency', 'organizations.name as client_name', 'invoices.due_date')
                ->orderBy('invoices.due_date', 'asc')
                ->limit(4)
                ->get();

            // 4. Dynamic Chart Data Generation (YTD up to Current Month)
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $revenueData = array_fill(0, 12, 0);
            $burnData = array_fill(0, 12, 0);

            $revenues = DB::table('invoices')
                ->selectRaw('MONTH(paid_at) as month, SUM(total_amount) as total')
                ->where('status', 'Paid')
                ->whereYear('paid_at', $currentYear)
                ->groupBy('month')
                ->get();

            foreach ($revenues as $rev) {
                if ($rev->month) $revenueData[$rev->month - 1] = (float) $rev->total;
            }

            $burns = DB::table('task_time_logs')
                ->selectRaw('MONTH(start_time) as month, SUM(cost_calculated) as total')
                ->whereYear('start_time', $currentYear)
                ->groupBy('month')
                ->get();

            foreach ($burns as $burn) {
                if ($burn->month) $burnData[$burn->month - 1] = (float) $burn->total;
            }

            $this->chartLabels = array_slice($months, 0, $currentMonth);
            $this->chartRevenue = array_slice($revenueData, 0, $currentMonth);
            $this->chartBurn = array_slice($burnData, 0, $currentMonth);

        } catch (\Exception $e) {
            $this->recentLeads = collect([]);
            $this->criticalAlerts = collect([]);
            $this->chartLabels = ['Jan'];
            $this->chartRevenue = [0];
            $this->chartBurn = [0];
        }
    }

    public function exportTelemetry()
    {
        abort_unless(auth()->user()->can('dashboard.export'), 403);

        $fileName = 'JBS_Praxis_Global_Telemetry_' . Carbon::now()->format('Y_m_d_H_i') . '.csv';

        DB::table('system_audit_logs')->insert([
            'user_id' => auth()->id() ?? 'SYSTEM',
            'action' => 'EXPORT_GLOBAL_TELEMETRY',
            'ip_address' => request()->ip(),
            'created_at' => now()
        ]);

        return Response::streamDownload(function () {
            $handle = fopen('php://output', 'w');
            
            fputcsv($handle, ['--- JBS PRAXIS SYSTEM EXPORT ---']);
            fputcsv($handle, ['Generated:', now()->toDateTimeString()]);
            fputcsv($handle, []);
            
            fputcsv($handle, ['KPI', 'Value']);
            fputcsv($handle, ['Capital Cleared', $this->kpis['capital_cleared']]);
            fputcsv($handle, ['A/R Pipeline', $this->kpis['ar_pipeline']]);
            fputcsv($handle, ['Active Deployments', $this->kpis['active_deployments']]);
            fputcsv($handle, ['Total Leads', $this->kpis['total_leads']]);
            
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function render()
    {
        return view('livewire.hq.dashboards.global-telemetry');
    }
}