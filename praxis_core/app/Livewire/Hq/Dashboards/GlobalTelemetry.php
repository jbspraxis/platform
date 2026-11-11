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
    // Nothing is kept in the page's state: the figures are read when the page draws (render) and for the CSV

    /** The headline figures. Money totals are kept per currency (['NGN' => 3332.5, 'USD' => 322.5]), never added across currencies. */
    private function kpis(): array
    {
        // SAFETY NET: Pre-initialize to prevent undefined array keys.
        $kpis = [
            'capital_cleared' => [],
            'ar_pipeline' => [],
            'active_deployments' => 0,
            'total_leads' => 0,
            'strike_capacity' => 0,
            'proposal_win_rate' => 0
        ];

        try {
            // 1. Core Financial & Operational Aggregation
            $kpis['capital_cleared'] = HqMoney::sumByCurrency(DB::table('invoices')->where('status', 'Paid'), 'total_amount');
            // Unpaid = sent and not yet paid, whether or not someone has marked it overdue on Invoices
            $kpis['ar_pipeline'] = HqMoney::sumByCurrency(DB::table('invoices')->whereIn('status', ['Pending', 'Overdue']), 'total_amount');
            $kpis['active_deployments'] = DB::table('projects')->where('status', 'Active')->count();
            $kpis['total_leads'] = DB::table('organizations')->where('status', 'Lead')->count();

            // Staff with a running time log (who is working right now)
            $kpis['strike_capacity'] = DB::table('task_time_logs')->whereNull('end_time')->distinct('user_id')->count();
        } catch (\Exception $e) {
            report($e);
        }

        return $kpis;
    }

    /** Everything the page shows */
    private function telemetry(): array
    {
        $data = [
            'kpis' => $this->kpis(),
            'recentLeads' => collect([]),
            'criticalAlerts' => collect([]),
            'chartLabels' => ['Jan'],
            'chartRevenue' => [],
            'chartBurn' => [0],
        ];

        $currentMonth = Carbon::now()->month;
        // This year as a date range rather than YEAR(column), so the database can use an index on the date
        $yearStart = Carbon::now()->startOfYear();
        $nextYear = $yearStart->copy()->addYear();

        // Each block has its own try/catch, so one failure does not erase figures that loaded correctly
        try {
            // 2. Newest leads: only the columns the table shows
            $data['recentLeads'] = DB::table('organizations')
                ->where('status', 'Lead')
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get(['name', 'industry', 'source', 'created_at']);
        } catch (\Exception $e) {
            report($e);
        }

        try {
            // 3. Unpaid invoices more than 30 days past their due date (invoices link to clients through their project)
            $data['criticalAlerts'] = DB::table('invoices')
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
                ->where('paid_at', '>=', $yearStart)->where('paid_at', '<', $nextYear)
                ->groupBy('month', 'cur')
                ->get();

            foreach ($revenues as $rev) {
                if (! $rev->month) continue;
                $revenueData[$rev->cur] ??= array_fill(0, 12, 0);
                $revenueData[$rev->cur][$rev->month - 1] = (float) $rev->total;
            }

            $burns = DB::table('task_time_logs')
                ->selectRaw('MONTH(start_time) as month, SUM(cost_calculated) as total')
                ->where('start_time', '>=', $yearStart)->where('start_time', '<', $nextYear)
                ->groupBy('month')
                ->get();

            foreach ($burns as $burn) {
                if ($burn->month) $burnData[$burn->month - 1] = (float) $burn->total;
            }

            $data['chartLabels'] = array_slice($months, 0, $currentMonth);
            $data['chartRevenue'] = array_map(fn ($series) => array_slice($series, 0, $currentMonth), $revenueData);
            $data['chartBurn'] = array_slice($burnData, 0, $currentMonth);
        } catch (\Exception $e) {
            report($e);
        }

        return $data;
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

        // The figures as they are now. The page itself doesn't change, so it isn't drawn again.
        $kpis = $this->kpis();
        $this->skipRender();

        return Response::streamDownload(function () use ($kpis) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['JBS Praxis company overview']);
            fputcsv($handle, ['Created (UTC)', now()->toDateTimeString()]);
            fputcsv($handle, []);

            fputcsv($handle, ['Measure', 'Currency', 'Value']);
            // One row per currency, and a 0 row when there are none, so the file always has the same measures
            foreach ((array) $kpis['capital_cleared'] ?: ['' => 0] as $currency => $total) {
                fputcsv($handle, ['Paid invoices, total', $currency, $total]);
            }
            foreach ((array) $kpis['ar_pipeline'] ?: ['' => 0] as $currency => $total) {
                fputcsv($handle, ['Unpaid invoices', $currency, $total]);
            }
            fputcsv($handle, ['Active projects', '', $kpis['active_deployments']]);
            fputcsv($handle, ['Open leads', '', $kpis['total_leads']]);

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function render()
    {
        return view('livewire.hq.dashboards.global-telemetry', $this->telemetry());
    }
}
