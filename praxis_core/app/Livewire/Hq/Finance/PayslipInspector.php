<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

#[Layout('components.layouts.hq')]
class PayslipInspector extends Component
{
    public $payrollRuns = [];
    public $selectedRunId = '';
    
    public $employeesInRun = [];
    public $selectedEmployeeId = '';

    // The core payload for the legal document
    public $payslipData = null;

    public function mount()
    {
        // Only fetch runs that have been Approved, Disbursed, or Locked
        $this->payrollRuns = DB::table('payroll_runs')
            ->whereIn('status', ['Approved', 'Disbursed', 'Locked'])
            ->orderBy('pay_period_end', 'desc')
            ->get()
            ->toArray();
    }

    public function updatedSelectedRunId()
    {
        $this->selectedEmployeeId = '';
        $this->payslipData = null;
        $this->employeesInRun = [];

        if (!empty($this->selectedRunId)) {
            // Fetch employees who have a locked ledger line in this specific run
            $this->employeesInRun = DB::table('payroll_lines')
                ->join('users', 'payroll_lines.user_id', '=', 'users.id')
                ->where('payroll_lines.payroll_run_id', $this->selectedRunId)
                ->select('users.id', 'users.first_name', 'users.last_name')
                ->orderBy('users.first_name')
                ->get()
                ->toArray();
        }
    }

    public function inspectPayslip()
    {
        if (empty($this->selectedRunId) || empty($this->selectedEmployeeId)) {
            $this->payslipData = null;
            return;
        }

        // 1. Fetch the payroll line
        $line = DB::table('payroll_lines')
            ->where('payroll_run_id', $this->selectedRunId)
            ->where('user_id', $this->selectedEmployeeId)
            ->first();

        if (!$line) return;

        // 2. Fetch the Master Run Data
        $run = DB::table('payroll_runs')->where('id', $this->selectedRunId)->first();

        // 3. Fetch Employee Historical/Live Demographics
        $emp = DB::table('mdm_employees')
            ->join('users', 'mdm_employees.user_id', '=', 'users.id')
            ->where('users.id', $this->selectedEmployeeId)
            ->select('mdm_employees.*', 'users.first_name', 'users.last_name')
            ->first();

        // The staff record may have been removed since the run
        if (!$emp) {
            session()->flash('error', "We couldn’t show this payslip because the staff member’s record is no longer in the staff directory. Ask HR to restore the record, then try again.");
            $this->payslipData = null;
            return;
        }

        // 4. Fetch Employer Legal Entity Data
        $employer = DB::table('mdm_legal_entities')
            ->where('region_code', $run->region_code)
            ->first();

        // 5. Fetch Leave Balances (OHADA Requirement)
        $leaveYear = Carbon::parse($run->pay_period_end)->year;
        $leave = DB::table('leave_balances')
            ->where('mdm_employee_id', $emp->id)
            ->where('leave_year', $leaveYear)
            ->first();

        // 6. Document fingerprint (SHA-256)
        $hashString = $run->id . $line->id . $line->net_pay . $emp->employee_code;
        $securityHash = hash('sha256', $hashString);

        // Resolve accurate Social ID based on Jurisdiction
        $socialId = match($run->region_code) {
            'CM' => $emp->social_security_number ?? 'N/A',
            'NG' => $emp->rsa_number ?? 'N/A',
            'KE' => $emp->nssf_number ?? 'N/A',
            default => 'N/A'
        };

        // 7. Build the payslip data
        $this->payslipData = [
            // Header & Identification
            'employer_name' => $employer->legal_name ?? 'JBS Praxis ' . ($run->region_code === 'CM' ? 'SARL' : 'LLC'),
            'employer_address' => $employer->registered_address ?? 'Address not recorded',
            'employer_tax_id' => $employer->tax_identification_number ?? 'N/A',
            'employer_social_id' => $employer->employer_social_security_code ?? 'N/A',
            
            'employee_name' => $emp->first_name . ' ' . $emp->last_name,
            'employee_code' => $emp->employee_code,
            'employee_address' => $emp->residential_address ?? 'Address not recorded',
            'marital_status' => $emp->marital_status,
            'dependents' => $emp->dependents_count,
            'job_classification' => $emp->job_classification,
            
            // Statutory IDs
            'tax_region' => $run->region_code,
            'tin_number' => $emp->tin_number ?? 'N/A',
            'national_id' => $emp->national_id ?? 'N/A',
            'social_id' => $socialId,
            
            // Period & Timing
            'pay_period_start' => $run->pay_period_start,
            'pay_period_end' => $run->pay_period_end,
            'payment_date' => $run->disbursed_at ?? $run->approved_at ?? $run->updated_at,
            'pay_frequency' => $emp->pay_frequency ?? 'Monthly',
            'currency' => $emp->currency ?? 'USD',

            // Financials (From Immutable Ledger Line)
            'base_salary' => $line->base_salary,
            'allowance_transport' => $line->allowance_transport,
            'allowance_housing' => $line->allowance_housing,
            'overtime_pay' => $line->overtime_pay,
            'performance_bonus' => $line->performance_bonus,
            'arrears' => $line->arrears,
            'benefits_in_kind' => $line->benefits_in_kind,
            'gross_pay' => $line->gross_pay,
            
            // Deductions
            'tax_paye' => $line->tax_paye,
            'deduction_pension' => $line->deduction_pension,
            'deduction_nhf' => $line->deduction_nhf,
            'deduction_nhis' => $line->deduction_nhis,
            'deduction_other' => $line->deduction_other,
            'total_deductions' => $line->total_deductions,
            
            // Take Home
            'net_pay' => $line->net_pay,
            'payment_method' => $line->payment_method ?? $emp->payment_method,
            'account_routing' => $line->account_or_wallet ?? $emp->bank_name . ' | ' . $emp->account_number,

            // Cumulative & Leave (The Records)
            'ytd_gross' => $line->ytd_gross,
            'ytd_tax' => $line->ytd_tax_paid ?? $line->ytd_tax, 
            'annual_leave_balance' => $leave ? ($leave->annual_accrued_days - $leave->annual_taken_days) : 0.00,
            'sick_leave_taken' => $leave ? $leave->sick_taken_days : 0.00,
            
            // Authentication
            'security_hash' => $securityHash,
            'hash_short' => substr($securityHash, 0, 12)
        ];
    }

    public function render()
    {
        return view('livewire.hq.finance.payslip-inspector');
    }
}