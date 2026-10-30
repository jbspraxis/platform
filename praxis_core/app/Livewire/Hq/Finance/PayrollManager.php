<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Exception;

#[Layout('components.layouts.hq')]
class PayrollManager extends Component
{
    public $activeTab = 'ledger'; // 'ledger', 'inspector'

    // Loaded on the server only; the browser must not change them
    #[Locked]
    public $payrollRuns = [];
    #[Locked]
    public $selectedRunId = null;
    #[Locked]
    public $runDetails = [];
    #[Locked]
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
     * Stage 1: the preparer creates the draft run
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

        // One run per month and country, paid ones included, so nobody is paid twice:
        // a run for all countries overlaps every country's run, and the other way round
        $existingRun = DB::table('payroll_runs')
            ->where('pay_period_start', $periodStart)
            ->when($this->targetRegion !== 'ALL', fn ($q) => $q->whereIn('region_code', [$this->targetRegion, 'ALL']))
            ->whereIn('status', ['Draft', 'Pending_Review', 'Approved', 'Disbursed', 'Locked'])
            ->exists();

        if ($existingRun) {
            $periodLabel = date('F Y', strtotime($periodStart));
            $regionLabel = $this->targetRegion === 'ALL' ? 'all countries' : $this->targetRegion;
            session()->flash('error', "A payroll run for {$periodLabel} that covers {$regionLabel} already exists. Open it from the list below instead of creating another.");
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

                $lineCount = count($lines);
                $this->successMessage = "Draft payroll run {$refNumber} created with {$lineCount} " . ($lineCount === 1 ? 'staff member' : 'staff members') . ". Someone other than you checks it next.";
            });

            $this->showSuccessModal = true;
            $this->loadPayrollRuns();

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t create the draft payroll run. Try again, and if it still fails, ask an administrator to check the error log.");
        }
    }

    /**
     * Stage 2: a second person checks it and sends it for approval
     */
    public function submitForReview($runId)
    {
        abort_unless(Auth::user()->can('payroll.edit'), 403);

        // Read the run from the database, not from component state
        $run = DB::table('payroll_runs')->where('id', $runId)->first();
        $currentUserId = Auth::id() ?? 'sys-admin';

        if (! $run) {
            session()->flash('error', "We couldn’t find that payroll run. Refresh the page and try again.");
            return;
        }

        // Segregation of duties: the person who prepared the run cannot check it
        if ((string) $run->maker_id === (string) $currentUserId) {
            session()->flash('error', "You prepared this payroll run, so someone else must check it.");
            return;
        }

        $updated = DB::table('payroll_runs')
            ->where('id', $runId)
            ->where('status', 'Draft')
            ->update([
                'status' => 'Pending_Review',
                'checker_id' => $currentUserId,
                'reviewed_at' => now(),
                'updated_at' => now()
            ]);

        if (! $updated) {
            session()->flash('error', "This payroll run is no longer a draft, so it wasn’t sent for approval. The list has been refreshed.");
            $this->loadPayrollRuns();
            $this->viewRun($runId);
            return;
        }

        $this->logDecision('PAYROLL_RUN_CHECKED', $run, 'Draft', 'Pending_Review', "Checked payroll run {$run->reference_number} and sent it for approval.");

        $this->successMessage = "Payroll run checked and sent for approval.";
        $this->showSuccessModal = true;
        $this->loadPayrollRuns();
        $this->viewRun($runId);
    }

    /**
     * Stage 3: an approver (not the preparer or checker) approves and locks it
     */
    public function approveRun($runId)
    {
        abort_unless(Auth::user()->can('payroll.approve'), 403);

        // Read the run from the database, not from component state
        $run = DB::table('payroll_runs')->where('id', $runId)->first();
        $currentUserId = Auth::id() ?? 'sys-admin';

        if (! $run) {
            session()->flash('error', "We couldn’t find that payroll run. Refresh the page and try again.");
            return;
        }

        // Segregation of duties: whoever prepared or checked the run cannot approve it
        if ((string) $run->maker_id === (string) $currentUserId) {
            session()->flash('error', "You prepared this payroll run, so you can’t approve it. Ask another approver to approve it.");
            return;
        }
        if ($run->checker_id && (string) $run->checker_id === (string) $currentUserId) {
            session()->flash('error', "You checked this payroll run, so you can’t approve it. Ask another approver to approve it.");
            return;
        }
        if (in_array($run->status, ['Approved', 'Disbursed'], true)) {
            session()->flash('error', "This payroll run has already been approved or paid, so it wasn’t changed. The list has been refreshed.");
            $this->loadPayrollRuns();
            $this->viewRun($runId);
            return;
        }
        // Three people: a run must be checked by someone other than the preparer before it can be approved
        if ($run->status !== 'Pending_Review' || ! $run->checker_id) {
            session()->flash('error', "This payroll run hasn’t been checked yet, so it can’t be approved. Someone other than the preparer must check it first.");
            $this->loadPayrollRuns();
            $this->viewRun($runId);
            return;
        }

        try {
            $approved = DB::transaction(function () use ($runId, $run, $currentUserId) {
                $updated = DB::table('payroll_runs')
                    ->where('id', $runId)
                    ->where('status', 'Pending_Review')
                    ->update([
                        'status' => 'Approved',
                        'approver_id' => $currentUserId,
                        'approved_at' => now(),
                        'updated_at' => now()
                    ]);

                if (! $updated) {
                    return false;
                }

                // Once approved, lock the variable inputs for this specific period
                DB::table('monthly_payroll_variables')
                    ->where('payroll_period', $run->pay_period_start)
                    ->update(['is_processed' => 1]);

                return true;
            });

            if (! $approved) {
                session()->flash('error', "This payroll run has already been approved or paid, so it wasn’t changed. The list has been refreshed.");
                $this->loadPayrollRuns();
                $this->viewRun($runId);
                return;
            }

            $this->logDecision('PAYROLL_RUN_APPROVED', $run, 'Pending_Review', 'Approved', "Approved payroll run {$run->reference_number}.");

            $this->successMessage = "Payroll run approved. Variable pay for this month is now locked.";
            $this->showSuccessModal = true;
            $this->loadPayrollRuns();
            $this->viewRun($runId);

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t approve the payroll run. Try again, and if it still fails, ask an administrator to check the error log.");
        }
    }

    /**
     * Stage 4: mark the run as paid
     */
    public function disburseRun($runId)
    {
        abort_unless(Auth::user()->can('payroll.approve'), 403);

        // Read the run from the database, not from component state
        $run = DB::table('payroll_runs')->where('id', $runId)->first();

        if (! $run) {
            session()->flash('error', "We couldn’t find that payroll run. Refresh the page and try again.");
            return;
        }

        // Segregation of duties: whoever prepared the run cannot also record it as paid
        if ((string) $run->maker_id === (string) Auth::id()) {
            session()->flash('error', "You prepared this payroll run, so someone else must mark it as paid.");
            return;
        }

        $updated = DB::table('payroll_runs')
            ->where('id', $runId)
            ->where('status', 'Approved')
            ->update([
                'status' => 'Disbursed',
                'disbursed_at' => now(),
                'updated_at' => now()
            ]);

        if (! $updated) {
            session()->flash('error', "Only an approved payroll run can be marked as paid. This one has changed; the list has been refreshed.");
            $this->loadPayrollRuns();
            $this->viewRun($runId);
            return;
        }

        $this->logDecision('PAYROLL_RUN_PAID', $run, 'Approved', 'Disbursed', "Marked payroll run {$run->reference_number} as paid.");

        $this->successMessage = "Payroll run marked as paid. It can no longer be changed.";
        $this->showSuccessModal = true;
        $this->loadPayrollRuns();
        $this->viewRun($runId);
    }

    /**
     * Stage 5: return the run to draft and unlock variable pay
     */
    public function rejectRun($runId)
    {
        abort_unless(Auth::user()->can('payroll.approve'), 403);

        // Read the run from the database, not from component state
        $run = DB::table('payroll_runs')->where('id', $runId)->first();

        if (! $run) {
            session()->flash('error', "We couldn’t find that payroll run. Refresh the page and try again.");
            return;
        }

        try {
            $returned = DB::transaction(function () use ($runId, $run) {
                $updated = DB::table('payroll_runs')
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

                // A paid run (or one already in draft) stays as it is, and its month's variable pay stays locked
                if (! $updated) {
                    return false;
                }

                // Unlock the variables so the Maker can fix the errors
                DB::table('monthly_payroll_variables')
                    ->where('payroll_period', $run->pay_period_start)
                    ->update(['is_processed' => 0]);

                return true;
            });

            if (! $returned) {
                session()->flash('error', "Only a run awaiting approval or approved (not yet paid) can be returned to draft. The list has been refreshed.");
                $this->loadPayrollRuns();
                $this->viewRun($runId);
                return;
            }

            $this->logDecision('PAYROLL_RUN_RETURNED', $run, $run->status, 'Draft', "Returned payroll run {$run->reference_number} to draft.");

            $this->successMessage = "Payroll run returned to draft. Variable pay for this month is unlocked so it can be corrected.";
            $this->showSuccessModal = true;
            $this->loadPayrollRuns();
            $this->viewRun($runId);

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t return the payroll run to draft. Try again, and if it still fails, ask an administrator to check the error log.");
        }
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    /** Each step of a run goes to the audit log: who moved it, from which status to which */
    private function logDecision(string $action, object $run, string $from, string $to, string $description): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'target_table' => 'payroll_runs',
            'target_id' => $run->id,
            'old_values' => json_encode(['status' => $from]),
            'new_values' => json_encode(['status' => $to]),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /** Field names in validation messages: the labels people see, not the property names (docs/BRAND.md sections 5 and 12) */
    protected function validationAttributes(): array
    {
        return [
            'draftPeriodMonth' => 'month',
            'draftPeriodYear' => 'year',
            'targetRegion' => 'country',
        ];
    }

    public function render()
    {
        return view('livewire.hq.finance.payroll-manager');
    }
}