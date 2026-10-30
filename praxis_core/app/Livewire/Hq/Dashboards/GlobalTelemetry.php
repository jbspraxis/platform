<?php

namespace App\Livewire\Hq\Dashboards;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;
use App\Livewire\Hq\HqMoney;

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
        // SAFETY NET: Pre-initialize to prevent undefined array keys.
        // Money totals are kept per currency (['NGN' => 3332.5, 'USD' => 322.5]), never added across currencies.
        $this->kpis = [
            'capital_cleared' => [],
            'ar_pipeline' => [],
            'active_deployments' => 0,
            'total_leads' => 0,
            'strike_capacity' => 0,
            'proposal_win_rate' => 0
        ];
        $this->recentLeads = collect([]);
        $this->criticalAlerts = collect([]);
        $this->chartLabels = ['Jan'];
        $this->chartRevenue = [];
        $this->chartBurn = [0];

        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        // Each block has its own try/catch, so one failure does not erase figures that loaded correctly
        try {
            // 1. Core Financial & Operational Aggregation
            $this->kpis['capital_cleared'] = HqMoney::sumByCurrency(DB::table('invoices')->where('status', 'Paid'), 'total_amount');
            // Unpaid = sent and not yet paid, whether or not someone has marked it overdue on Invoices
            $this->kpis['ar_pipeline'] = HqMoney::sumByCurrency(DB::table('invoices')->whereIn('status', ['Pending', 'Overdue']), 'total_amount');
            $this->kpis['active_deployments'] = DB::table('projects')->where('status', 'Active')->count();
            $this->kpis['total_leads'] = DB::table('organizations')->where('status', 'Lead')->count();

            // Staff with a running time log (who is working right now)
            $this->kpis['strike_capacity'] = DB::table('task_time_logs')->whereNull('end_time')->distinct('user_id')->count();
        } catch (\Exception $e) {
            report($e);
        }

        try {
            // 2. Newest leads
            $this->recentLeads = DB::table('organizations')
                ->where('status', 'Lead')
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get();
        } catch (\Exception $e) {
            report($e);
        }

        try {
            // 3. Unpaid invoices more than 30 days past their due date (invoices link to clients through their project)
            $this->criticalAlerts = DB::table('invoices')
                ->join('projects', 'invoices.project_id', '=', 'projects.id')
                ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
                ->whereIn('invoices.status', ['Pending', 'Overdue'])
                ->where('invoices.due_date', '<', Carbon::now()->subDays(30))
                ->select('invoices.invoice_number', 'invoices.total_amount', 'invoices.currency', 'organizations.name as client_name', 'invoices.due_date')
                ->orderBy('invoices.due_date', 'asc')
                ->limit(4)
                ->get();
        } catch (\Exception $e) {
            report($e);
        }

        try {
            // 4. Dynamic Chart Data Generation (YTD up to Current Month); revenue is one series per currency
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $revenueData = [];
            $burnData = array_fill(0, 12, 0);

            $revenues = DB::table('invoices')
                ->selectRaw("MONTH(paid_at) as month, UPPER(COALESCE(currency, '')) as cur, SUM(total_amount) as total")
                ->where('status', 'Paid')
                ->whereYear('paid_at', $currentYear)
                ->groupBy('month', 'cur')
                ->get();

            foreach ($revenues as $rev) {
                if (! $rev->month) continue;
                $revenueData[$rev->cur] ??= array_fill(0, 12, 0);
                $revenueData[$rev->cur][$rev->month - 1] = (float) $rev->total;
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
            $this->chartRevenue = array_map(fn ($series) => array_slice($series, 0, $currentMonth), $revenueData);
            $this->chartBurn = array_slice($burnData, 0, $currentMonth);
        } catch (\Exception $e) {
            report($e);
        }
    }

    public function exportTelemetry()
    {
        abort_unless(auth()->user()->can('dashboard.export'), 403);

        $fileName = 'JBS_Praxis_company_overview_' . Carbon::now()->format('Y_m_d_H_i') . '.csv';

        DB::table('system_audit_logs')->insert([
            'user_id' => auth()->id() ?? 'SYSTEM',
            'action' => 'EXPORT_GLOBAL_TELEMETRY',
            'ip_address' => request()->ip(),
            'created_at' => now()
        ]);

        return Response::streamDownload(function () {
            $handle = fopen('php://output', 'w');
            
            fputcsv($handle, ['JBS Praxis company overview']);
            fputcsv($handle, ['Created (UTC)', now()->toDateTimeString()]);
            fputcsv($handle, []);
            
            fputcsv($handle, ['Measure', 'Currency', 'Value']);
            // One row per currency, and a 0 row when there are none, so the file always has the same measures
            foreach ((array) $this->kpis['capital_cleared'] ?: ['' => 0] as $currency => $total) {
                fputcsv($handle, ['Paid invoices, total', $currency, $total]);
            }
            foreach ((array) $this->kpis['ar_pipeline'] ?: ['' => 0] as $currency => $total) {
                fputcsv($handle, ['Unpaid invoices', $currency, $total]);
            }
            fputcsv($handle, ['Active projects', '', $this->kpis['active_deployments']]);
            fputcsv($handle, ['Open leads', '', $this->kpis['total_leads']]);
            
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