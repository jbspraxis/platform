<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Exception;

#[Layout('components.layouts.hq')]
class PayrollManager extends Component
{
    public $activeTab = 'ledger'; // 'ledger', 'inspector'
    public $payrollRuns = [];
    public $selectedRunId = null;
    public $runDetails = [];
    public $runLines = [];
    
    // Draft Generation State
    public $draftPeriodMonth = '';
    public $draftPeriodYear = '';
    public $targetRegion = 'ALL';

    // UI Feedback
    public $showSuccessModal = false;
    public $successMessage = '';

    public function mount()
    {
        $this->draftPeriodMonth = date('m');
        $this->draftPeriodYear = date('Y');
        $this->loadPayrollRuns();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function loadPayrollRuns()
    {
        $this->payrollRuns = DB::table('payroll_runs')
            ->leftJoin('users as maker', 'payroll_runs.maker_id', '=', 'maker.id')
            ->leftJoin('users as checker', 'payroll_runs.checker_id', '=', 'checker.id')
            ->leftJoin('users as approver', 'payroll_runs.approver_id', '=', 'approver.id')
            ->select(
                'payroll_runs.*', 
                'maker.first_name as maker_first', 'maker.last_name as maker_last',
                'checker.first_name as checker_first', 'checker.last_name as checker_last',
                'approver.first_name as approver_first', 'approver.last_name as approver_last'
            )
            ->orderBy('payroll_runs.pay_period_end', 'desc')
            ->get()
            ->toArray();
    }

    public function viewRun($runId)
    {
        $this->selectedRunId = $runId;
        $this->runDetails = (array) collect($this->payrollRuns)->firstWhere('id', $runId);
        
        $this->runLines = DB::table('payroll_lines')
            ->join('users', 'payroll_lines.user_id', '=', 'users.id')
            ->join('mdm_employees', 'users.id', '=', 'mdm_employees.user_id')
            ->where('payroll_lines.payroll_run_id', $runId)
            ->select(
                'payroll_lines.*', 
                'users.first_name', 'users.last_name', 
                'mdm_employees.employee_code', 'mdm_employees.tax_region',
                'mdm_employees.bank_name', 'mdm_employees.account_number'
            )
            ->get()
            ->toArray();
    }

    public function closeRunView()
    {
        $this->selectedRunId = null;
        $this->runDetails = [];
        $this->runLines = [];
    }

    /**
     * STAGE 1: MAKER - Generate the Draft Ledger
     */
    public function generateDraftRun()
    {
        abort_unless(Auth::user()->can('payroll.create'), 403);

        $this->validate([
            'draftPeriodMonth' => 'required|numeric|min:1|max:12',
            'draftPeriodYear' => 'required|numeric|min:2020',
            'targetRegion' => 'required|in:ALL,CM,NG,KE'
        ]);

        $periodStart = "{$this->draftPeriodYear}-" . str_pad($this->draftPeriodMonth, 2, '0', STR_PAD_LEFT) . "-01";
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        // Prevent Duplicate Ledger Generation
        $existingRun = DB::table('payroll_runs')
            ->where('pay_period_start', $periodStart)
            ->where('region_code', $this->targetRegion)
            ->whereIn('status', ['Draft', 'Pending_Review', 'Approved'])
            ->exists();

        if ($existingRun) {
            session()->flash('error', "A payroll ledger for {$periodStart} [{$this->targetRegion}] already exists in the pipeline.");
            return;
        }

        try {
            DB::transaction(function () use ($periodStart, $periodEnd) {
                $runId = Str::uuid()->toString();
                $refNumber = "PRX-PAY-" . date('Ym', strtotime($periodEnd)) . "-" . strtoupper(Str::random(4));

                // 1. Create Master Run
                DB::table('payroll_runs')->insert([
                    'id' => $runId,
                    'reference_number' => $refNumber,
                    'pay_period_start' => $periodStart,
                    'pay_period_end' => $periodEnd,
                    'status' => 'Draft',
                    'maker_id' => Auth::id() ?? null,
                    'region_code' => $this->targetRegion,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // 2. Fetch Active Employees & their current SCD Comp Ledger
                $query = DB::table('mdm_employees as e')
                    ->join('mdm_employee_compensations as c', 'e.id', '=', 'c.mdm_employee_id')
                    ->where('e.is_active', 1)
                    ->where('c.is_active', 1)
                    ->whereNull('c.valid_to'); // Current active comp

                if ($this->targetRegion !== 'ALL') {
                    $query->where('e.tax_region', $this->targetRegion);
                }
                $employees = $query->get();

                // 3. Inject skeletal payroll lines so the Checker has data to review
                // (Exact math loops will overwrite these in Phase 7)
                $lines = [];
                foreach ($employees as $emp) {
                    $lines[] = [
                        'id' => Str::uuid()->toString(),
                        'payroll_run_id' => $runId,
                        'user_id' => $emp->user_id,
                        'job_classification' => $emp->job_classification,
                        'base_salary' => $emp->base_salary,
                        'allowance_housing' => $emp->allowance_housing,
                        'allowance_transport' => $emp->allowance_transport,
                        'allowance_utility' => $emp->allowance_utility,
                        'benefits_in_kind' => 0, // Calculated later
                        'gross_pay' => $emp->base_salary + $emp->allowance_housing + $emp->allowance_transport + $emp->allowance_utility,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }

                if (!empty($lines)) {
                    DB::table('payroll_lines')->insert($lines);
                }

                $this->successMessage = "Draft Payroll Run [{$refNumber}] generated successfully with " . count($lines) . " personnel lines. Pending checker review.";
            });

            $this->showSuccessModal = true;
            $this->loadPayrollRuns();

        } catch (Exception $e) {
            session()->flash('error', "Draft Generation Failed: " . $e->getMessage());
        }
    }

    /**
     * STAGE 2: CHECKER - Submit for Approval
     */
    public function submitForReview($runId)
    {
        abort_unless(Auth::user()->can('payroll.edit'), 403);

        $run = collect($this->payrollRuns)->firstWhere('id', $runId);
        $currentUserId = Auth::id() ?? 'sys-admin';

        // Segregation of Duties: Maker cannot be Checker
        if ($run->maker_id === $currentUserId) {
            session()->flash('error', "Compliance Violation: The Maker cannot review their own ledger.");
            return;
        }

        DB::table('payroll_runs')
            ->where('id', $runId)
            ->where('status', 'Draft')
            ->update([
                'status' => 'Pending_Review',
                'checker_id' => $currentUserId,
                'reviewed_at' => now(),
                'updated_at' => now()
            ]);
        
        $this->successMessage = "Ledger escalated to Pending Review.";
        $this->showSuccessModal = true;
        $this->loadPayrollRuns();
        $this->viewRun($runId);
    }

    /**
     * STAGE 3: APPROVER (CFO) - Lock and Approve
     */
    public function approveRun($runId)
    {
        abort_unless(Auth::user()->can('payroll.approve'), 403);

        $run = collect($this->payrollRuns)->firstWhere('id', $runId);
        $currentUserId = Auth::id() ?? 'sys-admin';

        // Segregation of Duties: Checker cannot be Approver
        if ($run->checker_id === $currentUserId) {
            session()->flash('error', "Compliance Violation: The Checker cannot finalize the treasury approval.");
            return;
        }

        try {
            DB::transaction(function () use ($runId, $run, $currentUserId) {
                DB::table('payroll_runs')
                    ->where('id', $runId)
                    ->whereIn('status', ['Draft', 'Pending_Review'])
                    ->update([
                        'status' => 'Approved',
                        'approver_id' => $currentUserId,
                        'approved_at' => now(),
                        'updated_at' => now()
                    ]);
                
                // Once approved, lock the variable inputs for this specific period
                DB::table('monthly_payroll_variables')
                    ->where('payroll_period', $run->pay_period_start)
                    ->update(['is_processed' => 1]);
            });

            $this->successMessage = "Ledger officially APPROVED and locked by Treasury.";
            $this->showSuccessModal = true;
            $this->loadPayrollRuns();
            $this->viewRun($runId);

        } catch (Exception $e) {
            session()->flash('error', "Approval Failed: " . $e->getMessage());
        }
    }

    /**
     * STAGE 4: TREASURY - Mark as Disbursed
     */
    public function disburseRun($runId)
    {
        abort_unless(Auth::user()->can('payroll.approve'), 403);

        DB::table('payroll_runs')
            ->where('id', $runId)
            ->where('status', 'Approved')
            ->update([
                'status' => 'Disbursed',
                'disbursed_at' => now(),
                'updated_at' => now()
            ]);
        
        $this->successMessage = "Funds marked as DISBURSED. Finalizing ledger closure.";
        $this->showSuccessModal = true;
        $this->loadPayrollRuns();
        $this->viewRun($runId);
    }

    /**
     * STAGE 5: REJECTION - Return to Draft & Unlock
     */
    public function rejectRun($runId)
    {
        abort_unless(Auth::user()->can('payroll.approve'), 403);

        $run = collect($this->payrollRuns)->firstWhere('id', $runId);

        try {
            DB::transaction(function () use ($runId, $run) {
                DB::table('payroll_runs')
                    ->where('id', $runId)
                    ->whereIn('status', ['Pending_Review', 'Approved'])
                    ->update([
                        'status' => 'Draft',
                        'checker_id' => null,
                        'approver_id' => null,
                        'reviewed_at' => null,
                        'approved_at' => null,
                        'updated_at' => now()
                    ]);
                
                // Unlock the variables so the Maker can fix the errors
                DB::table('monthly_payroll_variables')
                    ->where('payroll_period', $run->pay_period_start)
                    ->update(['is_processed' => 0]);
            });

            $this->successMessage = "Ledger rejected and returned to Draft status. Variables unlocked for corrections.";
            $this->showSuccessModal = true;
            $this->loadPayrollRuns();
            $this->viewRun($runId);

        } catch (Exception $e) {
            session()->flash('error', "Rejection Failed: " . $e->getMessage());
        }
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    public function render()
    {
        return view('livewire.hq.finance.payroll-manager');
    }
}