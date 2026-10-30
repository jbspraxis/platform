<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class ExecutiveBiDashboard extends Component
{
    public $startDate;
    public $endDate;
    public $regionFilter = 'ALL';

    // Figures shown on the overview
    public $kpis = [];
    public $pnl = [];
    public $cashFlow = [];
    public $trends = [];

    public function mount()
    {
        // Default to Year-to-Date for the macroscopic view
        $this->startDate = now()->startOfYear()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        
        $this->compileTelemetry();
    }

    public function compileTelemetry()
    {
        // 1. Compile the Profit & Loss (P&L) Statement
        $this->calculatePnL();

        // 2. Compile the Indirect Cash Flow Statement
        $this->calculateCashFlow();

        // 3. Compile High-Level Executive KPIs (Cash on Hand, Runway, etc.)
        $this->calculateKPIs();

        // 4. Compile 6-Month Rolling Data for the Visual UI Charts
        $this->calculateTrends();
    }

    public function filterMatrix()
    {
        $this->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);
        
        $this->compileTelemetry();
    }

    /**
     * PROFIT & LOSS (INCOME STATEMENT)
     * Revenue (Credit normal) - Expenses (Debit normal)
     */
    private function calculatePnL()
    {
        $query = DB::table('journal_lines')
            ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereBetween('journal_entries.entry_date', [$this->startDate, $this->endDate]);

        if ($this->regionFilter !== 'ALL') {
            $query->where('journal_entries.region_code', $this->regionFilter);
        }

        // Fetch raw aggregates grouped by the first digit of the account code
        $aggregates = $query->selectRaw('
            LEFT(chart_of_accounts.account_code, 1) as category_code,
            SUM(journal_lines.credit_amount) as total_credit,
            SUM(journal_lines.debit_amount) as total_debit
        ')->groupBy('category_code')->get()->keyBy('category_code');

        // Revenue (4xxx) = Credits - Debits
        $revenueRaw = $aggregates->get('4');
        $revenue = $revenueRaw ? ($revenueRaw->total_credit - $revenueRaw->total_debit) : 0;

        // COGS (5xxx) = Debits - Credits
        $cogsRaw = $aggregates->get('5');
        $cogs = $cogsRaw ? ($cogsRaw->total_debit - $cogsRaw->total_credit) : 0;

        // OPEX (6xxx) = Debits - Credits
        $opexRaw = $aggregates->get('6');
        $opex = $opexRaw ? ($opexRaw->total_debit - $opexRaw->total_credit) : 0;

        $grossProfit = $revenue - $cogs;
        $netIncome = $grossProfit - $opex;
        $margin = $revenue > 0 ? ($netIncome / $revenue) * 100 : 0;

        $this->pnl = [
            'total_revenue' => (float) $revenue,
            'cogs' => (float) $cogs,
            'gross_profit' => (float) $grossProfit,
            'opex' => (float) $opex,
            'net_income' => (float) $netIncome,
            'net_margin_percent' => (float) $margin
        ];
    }

    /**
     * INDIRECT CASH FLOW STATEMENT
     * Starts with Net Income, adjusts for Working Capital changes to find actual cash generated.
     */
    private function calculateCashFlow()
    {
        $query = DB::table('journal_lines')
            ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereBetween('journal_entries.entry_date', [$this->startDate, $this->endDate]);

        if ($this->regionFilter !== 'ALL') {
            $query->where('journal_entries.region_code', $this->regionFilter);
        }

        $changes = $query->selectRaw('
            LEFT(chart_of_accounts.account_code, 2) as sub_category,
            SUM(journal_lines.debit_amount) as total_debit,
            SUM(journal_lines.credit_amount) as total_credit
        ')->groupBy('sub_category')->get()->keyBy('sub_category');

        // Accounts Receivable (11) Change: Increase in AR is a NEGATIVE cash adjustment (Money we haven’t received yet)
        $ar = $changes->get('11');
        $changeInAR = $ar ? ($ar->total_credit - $ar->total_debit) : 0; 

        // Prepayments (12) Change: Increase is a NEGATIVE cash adjustment
        $prepaid = $changes->get('12');
        $changeInPrepaid = $prepaid ? ($prepaid->total_credit - $prepaid->total_debit) : 0;

        // Deferred Revenue (21) Change: Increase is a POSITIVE cash adjustment (Received cash but haven’t earned it)
        $defRev = $changes->get('21');
        $changeInDefRev = $defRev ? ($defRev->total_credit - $defRev->total_debit) : 0;

        // Payables & Liabilities (20, 22, 23, 24) Change: Increase is a POSITIVE cash adjustment
        $liabilitiesCredit = 0; $liabilitiesDebit = 0;
        foreach (['20', '22', '23', '24', '25'] as $code) {
            if ($row = $changes->get($code)) {
                $liabilitiesCredit += $row->total_credit;
                $liabilitiesDebit += $row->total_debit;
            }
        }
        $changeInLiabilities = $liabilitiesCredit - $liabilitiesDebit;

        $operatingCashFlow = $this->pnl['net_income'] + $changeInAR + $changeInPrepaid + $changeInDefRev + $changeInLiabilities;

        $this->cashFlow = [
            'net_income_start' => $this->pnl['net_income'],
            'adjustment_ar' => (float) $changeInAR,
            'adjustment_prepaid' => (float) $changeInPrepaid,
            'adjustment_def_rev' => (float) $changeInDefRev,
            'adjustment_liabilities' => (float) $changeInLiabilities,
            'net_operating_cash' => (float) $operatingCashFlow
        ];
    }

    /**
     * MACRO KPIs (All-Time Balances & Burn Rate)
     */
    private function calculateKPIs()
    {
        // 1. Total Cash on Hand (10xx series balances from inception to now)
        $cashRaw = DB::table('journal_lines')
            ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('chart_of_accounts.account_code', 'LIKE', '10%')
            ->selectRaw('SUM(debit_amount - credit_amount) as balance')
            ->first();
        $cashOnHand = $cashRaw->balance ?? 0;

        // 2. Outstanding A/R (11xx series)
        $arRaw = DB::table('journal_lines')
            ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('chart_of_accounts.account_code', 'LIKE', '11%')
            ->selectRaw('SUM(debit_amount - credit_amount) as balance')
            ->first();
        $outstandingAR = $arRaw->balance ?? 0;

        // 3. 30-Day Burn Rate (All 5xxx and 6xxx expenses in the last 30 days)
        $burnRaw = DB::table('journal_lines')
            ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.entry_date', '>=', now()->subDays(30)->format('Y-m-d'))
            ->where(function($q) {
                $q->where('chart_of_accounts.account_code', 'LIKE', '5%')
                  ->orWhere('chart_of_accounts.account_code', 'LIKE', '6%');
            })
            ->selectRaw('SUM(debit_amount - credit_amount) as balance')
            ->first();
        $monthlyBurn = $burnRaw->balance ?? 0;

        // 4. Runway Calculation
        $runwayMonths = $monthlyBurn > 0 ? ($cashOnHand / $monthlyBurn) : 999;

        $this->kpis = [
            'cash_on_hand' => (float) $cashOnHand,
            'outstanding_ar' => (float) $outstandingAR,
            'monthly_burn_rate' => (float) $monthlyBurn,
            'runway_months' => (float) $runwayMonths
        ];
    }

    /**
     * VISUAL TRENDS (6-Month Rolling Data)
     */
    private function calculateTrends()
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->format('Y-m'));
        }

        $trendData = [];

        foreach ($months as $month) {
            $start = Carbon::parse($month)->startOfMonth()->format('Y-m-d');
            $end = Carbon::parse($month)->endOfMonth()->format('Y-m-d');

            $revRaw = DB::table('journal_lines')
                ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereBetween('journal_entries.entry_date', [$start, $end])
                ->where('chart_of_accounts.account_code', 'LIKE', '4%')
                ->selectRaw('SUM(credit_amount - debit_amount) as total')
                ->first();

            $expRaw = DB::table('journal_lines')
                ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereBetween('journal_entries.entry_date', [$start, $end])
                ->where(function($q) {
                    $q->where('chart_of_accounts.account_code', 'LIKE', '5%')
                      ->orWhere('chart_of_accounts.account_code', 'LIKE', '6%');
                })
                ->selectRaw('SUM(debit_amount - credit_amount) as total')
                ->first();

            $trendData[] = [
                'month' => Carbon::parse($month)->format('M y'),
                'revenue' => (float) ($revRaw->total ?? 0),
                'expenses' => (float) ($expRaw->total ?? 0),
            ];
        }

        $this->trends = $trendData;
    }

    public function exportStatements()
    {
        abort_unless(Auth::user()->can('finance.export'), 403);

        // Statement downloads are not built yet (planned PDF export)
        session()->flash('info', "Statement downloads aren’t available yet. The figures on this page are up to date for the dates you chose.");
    }

    /** Field names in validation messages: the labels people see, not the property names (docs/BRAND.md sections 5 and 12) */
    protected function validationAttributes(): array
    {
        return [
            'startDate' => 'from date',
            'endDate' => 'to date',
        ];
    }

    public function render()
    {
        return view('livewire.hq.finance.executive-bi-dashboard');
    }
}