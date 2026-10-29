<?php

namespace App\Livewire\Hq\Hr;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

#[Layout('components.layouts.hq')]
class ContractsAppraisals extends Component
{
    public $activeTab = 'contracts'; // 'contracts', 'appraisals'
    
    // Grid Data
    public $employees = [];
    public $appraisals = [];
    
    // UI State
    public $showSuccessModal = false;
    public $successMessage = '';

    // --- CONTRACT BUILDER STATE ---
    public $selectedEmployeeId = null;
    public $contract_type = 'CDI';
    public $professional_category = '';
    public $standard_weekly_hours = 40;
    public $probation_end_date = '';
    public $contract_end_date = '';
    public $notice_period_days = 30;
    public $severance_terms = '';
    public $hourly_rate_offered = 0;

    // --- APPRAISAL STATE ---
    public $review_period_start = '';
    public $review_period_end = '';
    public $kpi_score_overall = 0.00;
    public $manager_comments = '';

    public function mount()
    {
        $this->loadGridData();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->selectedEmployeeId = null; // Reset selection on tab switch
        $this->loadGridData();
    }

    public function loadGridData()
    {
        if ($this->activeTab === 'contracts') {
            // Architect Fix: Use a subquery to guarantee we only join the LATEST contract per employee
            // preventing grid duplication when contracts are renewed.
            $latestContracts = DB::table('employment_contracts')
                ->select('mdm_employee_id', DB::raw('MAX(created_at) as max_created_at'))
                ->groupBy('mdm_employee_id');

            $this->employees = DB::table('mdm_employees as emp')
                ->join('users as u', 'emp.user_id', '=', 'u.id')
                ->leftJoinSub($latestContracts, 'latest_ec', function ($join) {
                    $join->on('emp.id', '=', 'latest_ec.mdm_employee_id');
                })
                ->leftJoin('employment_contracts as ec', function ($join) {
                    $join->on('emp.id', '=', 'ec.mdm_employee_id')
                         ->on('ec.created_at', '=', 'latest_ec.max_created_at');
                })
                ->select(
                    'emp.id', 'emp.employee_code', 'emp.tax_region', 'emp.hire_date',
                    'u.first_name', 'u.last_name',
                    'ec.contract_type', 'ec.status as contract_status', 'ec.contract_end_date'
                )
                ->where('emp.is_active', 1)
                ->orderBy('u.first_name')
                ->get()
                ->toArray();
                
        } else {
            $this->appraisals = DB::table('mdm_employees as emp')
                ->join('users as u', 'emp.user_id', '=', 'u.id')
                ->leftJoin('performance_appraisals as pa', function($join) {
                    $join->on('emp.id', '=', 'pa.mdm_employee_id')
                         ->where('pa.status', '!=', 'Finalized'); 
                })
                ->select(
                    'emp.id', 'emp.employee_code', 'u.first_name', 'u.last_name',
                    'pa.id as appraisal_id', 'pa.kpi_score_overall', 'pa.status', 'pa.review_period_end'
                )
                ->where('emp.is_active', 1)
                ->orderBy('u.first_name')
                ->get()
                ->toArray();
        }
    }

    // ==========================================
    // CONTRACT BUILDER LOGIC
    // ==========================================
    public function selectContract($employeeId)
    {
        $this->selectedEmployeeId = $employeeId;
        $contract = DB::table('employment_contracts')
            ->where('mdm_employee_id', $employeeId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($contract) {
            $this->contract_type = $contract->contract_type;
            $this->professional_category = $contract->professional_category;
            $this->standard_weekly_hours = $contract->standard_weekly_hours;
            $this->probation_end_date = $contract->probation_end_date;
            $this->contract_end_date = $contract->contract_end_date;
            $this->notice_period_days = $contract->notice_period_days;
            $this->severance_terms = $contract->severance_terms;
            $this->hourly_rate_offered = $contract->hourly_rate_offered;
        } else {
            $this->resetContractForm();
        }
    }

    public function saveContract()
    {
        $this->validate([
            'contract_type' => 'required|in:CDI,CDD,Probation,Freelance',
            'standard_weekly_hours' => 'required|numeric|min:1|max:60',
            'notice_period_days' => 'required|numeric|min:0',
        ]);

        // OHADA Compliance Check
        if ($this->contract_type === 'CDD' && empty($this->contract_end_date)) {
            session()->flash('error', 'OHADA Legal Constraint: Fixed-Term Contracts (CDD) must specify an explicit end date.');
            return;
        }

        try {
            DB::transaction(function () {
                $contract = DB::table('employment_contracts')
                    ->where('mdm_employee_id', $this->selectedEmployeeId)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $data = [
                    'contract_type' => $this->contract_type,
                    'professional_category' => $this->professional_category,
                    'standard_weekly_hours' => $this->standard_weekly_hours,
                    'probation_end_date' => empty($this->probation_end_date) ? null : $this->probation_end_date,
                    'contract_end_date' => empty($this->contract_end_date) ? null : $this->contract_end_date,
                    'notice_period_days' => $this->notice_period_days,
                    'severance_terms' => $this->severance_terms,
                    'hourly_rate_offered' => (float) $this->hourly_rate_offered,
                    'status' => 'Sent', // Legal Fix: Always reset to Sent when terms change
                    'signature_b64' => null, // Legal Fix: Void old signature if terms change
                    'signed_at' => null, // Legal Fix: Clear signature timestamp
                    'updated_at' => now(),
                    'updated_by' => Auth::id() ?? null
                ];

                if ($contract) {
                    DB::table('employment_contracts')->where('id', $contract->id)->update($data);
                } else {
                    $data['id'] = Str::uuid()->toString();
                    $data['mdm_employee_id'] = $this->selectedEmployeeId;
                    $data['contract_pdf_path'] = 'pending_generation';
                    $data['created_at'] = now();
                    $data['created_by'] = Auth::id() ?? null;
                    DB::table('employment_contracts')->insert($data);
                }
            });

            $this->successMessage = "Employment contract terms legally secured. Previous signatures (if any) have been voided.";
            $this->showSuccessModal = true;
            $this->loadGridData();

        } catch (Exception $e) {
            session()->flash('error', "Ledger Error: " . $e->getMessage());
        }
    }

    private function resetContractForm()
    {
        $this->contract_type = 'CDI';
        $this->professional_category = '';
        $this->standard_weekly_hours = 40;
        $this->probation_end_date = '';
        $this->contract_end_date = '';
        $this->notice_period_days = 30;
        $this->severance_terms = '';
        $this->hourly_rate_offered = 0;
    }

    // ==========================================
    // PERFORMANCE APPRAISAL LOGIC
    // ==========================================
    public function selectAppraisal($employeeId)
    {
        $this->selectedEmployeeId = $employeeId;
        
        $this->review_period_start = Carbon::now()->startOfQuarter()->toDateString();
        $this->review_period_end = Carbon::now()->endOfQuarter()->toDateString();
        $this->kpi_score_overall = 0.00;
        $this->manager_comments = '';
    }

    public function submitAppraisal()
    {
        $this->validate([
            'review_period_start' => 'required|date',
            'review_period_end' => 'required|date|after_or_equal:review_period_start',
            'kpi_score_overall' => 'required|numeric|min:0|max:5',
        ]);

        // Compliance Fix: Verify the logged-in user is actually the assigned line manager
        $employee = DB::table('mdm_employees')->where('id', $this->selectedEmployeeId)->first();
        $currentUserId = Auth::id() ?? null;
        
        if ($employee->line_manager_id !== $currentUserId && $currentUserId !== null) {
            session()->flash('error', 'Authorization Error: Only the assigned Line Manager can submit a performance appraisal for this employee.');
            return;
        }

        try {
            DB::table('performance_appraisals')->insert([
                'id' => Str::uuid()->toString(),
                'mdm_employee_id' => $this->selectedEmployeeId,
                'manager_id' => $currentUserId,
                'review_period_start' => $this->review_period_start,
                'review_period_end' => $this->review_period_end,
                'kpi_score_overall' => $this->kpi_score_overall,
                'manager_comments' => $this->manager_comments,
                'status' => 'Submitted_by_Manager',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->successMessage = "Performance appraisal officially logged. Awaiting finalization.";
            $this->showSuccessModal = true;
            $this->loadGridData();
            $this->selectedEmployeeId = null;

        } catch (Exception $e) {
            session()->flash('error', "System Error: " . $e->getMessage());
        }
    }

    public function finalizeAppraisal($appraisalId)
    {
        DB::table('performance_appraisals')->where('id', $appraisalId)->update([
            'status' => 'Finalized',
            'updated_at' => now()
        ]);

        $this->successMessage = "Appraisal finalized. KPI scores are now permanently archived.";
        $this->showSuccessModal = true;
        $this->loadGridData();
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    public function render()
    {
        return view('livewire.hq.hr.contracts-appraisals');
    }
}