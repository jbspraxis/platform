<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\Payroll\CameroonTaxCalculator;
use App\Services\Payroll\NigeriaTaxCalculator;
use App\Services\Payroll\ProrationService;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class ComplianceMatrix extends Component
{
    public $matrix_run_filter = 'live';   
    public $matrix_region_filter = 'ALL'; 
    
    public $payrollRuns = [];
    public $matrix_kpis = [];
    public $remittance_list = [];
    public $statutory_remittance_list = [];

    public function mount()
    {
        $this->payrollRuns = DB::table('payroll_runs')
            ->whereIn('status', ['Approved', 'Disbursed', 'Locked'])
            ->orderBy('pay_period_end', 'desc')
            ->get()
            ->toArray();
            
        $this->loadMatrix();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['matrix_run_filter', 'matrix_region_filter'])) {
            $this->loadMatrix();
        }
    }

    public function loadMatrix()
    {
        if ($this->matrix_run_filter === 'live') {
            $this->loadLiveForecast();
        } else {
            $this->loadHistoricalMatrix($this->matrix_run_filter);
        }
    }

    private function loadLiveForecast()
    {
        $targetMonth = date('m');
        $targetYear = date('Y');

        $query = DB::table('mdm_employees')
            ->join('users', 'mdm_employees.user_id', '=', 'users.id')
            ->where('mdm_employees.is_active', 1)
            ->select('mdm_employees.*', 'users.first_name', 'users.last_name');

        if ($this->matrix_region_filter !== 'ALL') {
            $query->where('mdm_employees.tax_region', $this->matrix_region_filter);
        }

        $employees = $query->get();

        $kpis = [
            'headcount' => 0, 'total_gross' => 0.00, 'total_net' => 0.00,
            'total_paye_irpp' => 0.00, 'total_pension_cnps_employee' => 0.00,
            'total_pension_cnps_employer' => 0.00, 'total_other_taxes' => 0.00,
        ];
        
        $bank_remittance = [];
        $statutory_remittance = [];

        foreach ($employees as $emp) {
            $config = DB::table('tax_configurations')->where('region_code', $emp->tax_region)->first();
            $bands = DB::table('tax_bands')->where('tax_configuration_id', $config->id ?? null)->orderBy('lower_limit', 'asc')->get();

            $proratedComp = ProrationService::calculateProratedGross($emp->id, $targetMonth, $targetYear);
            $variables = DB::table('monthly_payroll_variables')
                ->where('mdm_employee_id', $emp->id)
                ->where('payroll_period', Carbon::create($targetYear, $targetMonth, 1)->toDateString())
                ->first();

            $payload = array_merge((array)$emp, $proratedComp);
            if ($variables) {
                $payload['prime_anciennete'] = $variables->prime_anciennete;
                $payload['prime_performance'] = $variables->prime_performance;
                $payload['thirteenth_month_bonus'] = $variables->thirteenth_month_bonus;
                $payload['unexcused_absence_deduction'] = $variables->unexcused_absence_days * ($proratedComp['base_salary'] / 22);
            }

            if ($emp->tax_region === 'CM') {
                $calc = CameroonTaxCalculator::calculate($payload, $config, $bands);
                $kpis['total_paye_irpp'] += $calc['deductions']['irpp'];
                $kpis['total_pension_cnps_employee'] += $calc['deductions']['cnps'];
                $kpis['total_pension_cnps_employer'] += $calc['employer_liabilities']['cnps'];
                $kpis['total_other_taxes'] += ($calc['deductions']['cac'] + $calc['deductions']['cfc'] + $calc['deductions']['rav']);
                
                $statutory_remittance[] = [
                    'name' => $emp->first_name . ' ' . $emp->last_name, 
                    'tin' => $emp->tin_number, 
                    'social_id' => $emp->social_security_number,
                    'tax_amount' => $calc['deductions']['irpp'] + $calc['deductions']['cac'], 
                    'pension_employee' => $calc['deductions']['cnps'], 
                    'pension_employer' => $calc['employer_liabilities']['cnps'],
                    'other_liabilities' => $calc['employer_liabilities']['cfc'] + $calc['employer_liabilities']['fne'],
                    'currency' => $emp->currency, 
                    'region' => $emp->tax_region
                ];
            } elseif ($emp->tax_region === 'NG') {
                $calc = NigeriaTaxCalculator::calculate($payload, $config, $bands);
                $kpis['total_paye_irpp'] += $calc['deductions']['paye'];
                $kpis['total_pension_cnps_employee'] += $calc['deductions']['pension'];
                $kpis['total_pension_cnps_employer'] += $calc['employer_liabilities']['pension'];
                $kpis['total_other_taxes'] += ($calc['deductions']['nhf'] + $calc['deductions']['nhis']);

                $statutory_remittance[] = [
                    'name' => $emp->first_name . ' ' . $emp->last_name, 
                    'tin' => $emp->tin_number, 
                    'social_id' => $emp->rsa_number,
                    'tax_amount' => $calc['deductions']['paye'], 
                    'pension_employee' => $calc['deductions']['pension'], 
                    'pension_employer' => $calc['employer_liabilities']['pension'],
                    'other_liabilities' => $calc['employer_liabilities']['itf'] + $calc['employer_liabilities']['nsitf'],
                    'currency' => $emp->currency, 
                    'region' => $emp->tax_region
                ];
            } else {
                continue; 
            }

            $kpis['headcount']++;
            $kpis['total_gross'] += $calc['gross_cash'];
            $kpis['total_net'] += $calc['net_pay'];

            if ($emp->payment_method === 'Bank Transfer') {
                $bank_remittance[] = [
                    'name' => $emp->first_name . ' ' . $emp->last_name, 
                    'bank' => $emp->bank_name,
                    'account' => $emp->account_number, 
                    'amount' => $calc['net_pay'], 
                    'currency' => $emp->currency
                ];
            }
        }

        $this->matrix_kpis = $kpis;
        $this->remittance_list = $bank_remittance;
        $this->statutory_remittance_list = $statutory_remittance;
    }

    private function loadHistoricalMatrix($runId)
    {
        $run = collect($this->payrollRuns)->firstWhere('id', $runId);
        if (!$run) return;

        $lines = DB::table('payroll_lines')
            ->leftJoin('users', 'payroll_lines.user_id', '=', 'users.id')
            ->leftJoin('mdm_employees', 'users.id', '=', 'mdm_employees.user_id')
            ->where('payroll_lines.payroll_run_id', $runId)
            ->select('payroll_lines.*', 'users.first_name', 'users.last_name', 'mdm_employees.tin_number', 'mdm_employees.social_security_number', 'mdm_employees.rsa_number')
            ->get();

        $kpis = [
            'headcount' => $lines->count(), 
            'total_gross' => $lines->sum('gross_pay'), 
            'total_net' => $lines->sum('net_pay'),
            'total_paye_irpp' => $lines->sum('tax_paye'), 
            'total_pension_cnps_employee' => $lines->sum('deduction_pension'),
            'total_pension_cnps_employer' => $lines->sum('employer_liability_pension'),
            'total_other_taxes' => $lines->sum('deduction_nhf') + $lines->sum('deduction_nhis') + $lines->sum('deduction_other'),
        ];

        $bank_remittance = [];
        $statutory_remittance = [];

        foreach ($lines as $line) {
            // Restore Phase 9 Data Integrity Fallback
            $fullName = $line->first_name ? ($line->first_name . ' ' . $line->last_name) : 'Archived User (' . substr($line->user_id, 0, 8) . ')';
            $social_id = $run->region_code === 'CM' ? $line->social_security_number : $line->rsa_number;

            $statutory_remittance[] = [
                'name' => $fullName,
                'tin' => $line->tin_number ?? 'N/A',
                'social_id' => $social_id ?? 'N/A',
                'tax_amount' => $line->tax_paye, 
                'pension_employee' => $line->deduction_pension, 
                'pension_employer' => $line->employer_liability_pension,
                'other_liabilities' => $line->employer_liability_health + $line->employer_liability_training,
                'currency' => $run->region_code === 'CM' ? 'XAF' : 'NGN',
                'region' => $run->region_code
            ];

            if ($line->payment_method === 'Bank Transfer') {
                $bankInfo = explode('|', $line->account_or_wallet);
                $bank_remittance[] = [
                    'name' => $fullName, 
                    'bank' => $bankInfo[0] ?? 'Unknown',
                    'account' => $bankInfo[1] ?? 'Unknown', 
                    'amount' => $line->net_pay, 
                    'currency' => $run->region_code === 'CM' ? 'XAF' : 'NGN'
                ];
            }
        }

        $this->matrix_kpis = $kpis;
        $this->remittance_list = $bank_remittance;
        $this->statutory_remittance_list = $statutory_remittance;
    }

    /**
     * PHASE 10 EXPORT: BANK REMITTANCE
     * Bulletproof RFC 4180 CSV Generation
     */
    public function exportBankRemittance()
    {
        abort_unless(Auth::user()->can('payroll.export'), 403);

        $status = $this->matrix_run_filter === 'live' ? 'FORECAST' : 'ACTUALS';
        $filename = "JBS_Net_Remittance_{$status}_" . date('Y_m_d') . ".csv";

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ["Employee Name", "Bank Name", "Account Number", "Currency", "Net Pay Amount"]);

            foreach ($this->remittance_list as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['bank'],
                    $row['account'],
                    $row['currency'],
                    $row['amount']
                ]);
            }
            fclose($handle);
        }, $filename);
    }

    /**
     * PHASE 10 EXPORT: STATUTORY / TAX REMITTANCE
     * Bulletproof RFC 4180 CSV Generation
     */
    public function exportStatutoryRemittance()
    {
        abort_unless(Auth::user()->can('payroll.export'), 403);

        $status = $this->matrix_run_filter === 'live' ? 'FORECAST' : 'ACTUALS';
        $filename = "JBS_Statutory_Remittance_{$status}_" . date('Y_m_d') . ".csv";

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                "Employee Name", "Jurisdiction", "Tax ID (TIN/NIU)", "Social ID (RSA/CNPS)", "Currency", 
                "Income Tax (PAYE/IRPP)", "Employee Pension Share", "Employer Pension Share", "Total Pension Remittance", "Other Employer Liabilities"
            ]);

            foreach ($this->statutory_remittance_list as $row) {
                $total_pension = $row['pension_employee'] + $row['pension_employer'];
                fputcsv($handle, [
                    $row['name'],
                    $row['region'],
                    $row['tin'] ?? 'N/A',
                    $row['social_id'] ?? 'N/A',
                    $row['currency'],
                    $row['tax_amount'],
                    $row['pension_employee'],
                    $row['pension_employer'],
                    $total_pension,
                    $row['other_liabilities']
                ]);
            }
            fclose($handle);
        }, $filename);
    }

    public function render()
    {
        return view('livewire.hq.finance.compliance-matrix');
    }
}