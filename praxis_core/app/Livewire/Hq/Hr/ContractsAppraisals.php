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
            // Join only the latest contract per staff member, so renewed contracts don't duplicate rows
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
        // The route needs hr.view; issuing a contract needs hr.create, changing one needs hr.edit
        $hasContract = DB::table('employment_contracts')->where('mdm_employee_id', $this->selectedEmployeeId)->exists();
        if (!Auth::user()->can($hasContract ? 'hr.edit' : 'hr.create')) {
            session()->flash('error', "You don't have permission to issue or change employment contracts. Ask an administrator to change your role.");
            return;
        }

        $this->validate([
            'contract_type' => 'required|in:CDI,CDD,Probation,Freelance',
            'standard_weekly_hours' => 'required|numeric|min:1|max:60',
            'notice_period_days' => 'required|numeric|min:0',
        ]);

        // OHADA Compliance Check
        if ($this->contract_type === 'CDD' && empty($this->contract_end_date)) {
            session()->flash('error', 'A fixed-term contract (CDD) needs an end date under OHADA labour law. Add the contract end date, then save again.');
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
                    'status' => 'Sent', // Changed terms go back to Sent
                    'signature_b64' => null, // Earlier signature no longer applies
                    'signed_at' => null, // Clear the signature time
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

            $this->successMessage = "Contract terms saved and marked as sent for signature. Any earlier signature on this contract no longer applies.";
            $this->showSuccessModal = true;
            $this->loadGridData();

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn't save the contract, so nothing was changed. Try again, and if it still fails, ask an administrator to check the error log.");
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

        // Only the assigned line manager may submit the appraisal
        $employee = DB::table('mdm_employees')->where('id', $this->selectedEmployeeId)->first();
        $currentUserId = Auth::id() ?? null;
        
        if ($employee->line_manager_id !== $currentUserId && $currentUserId !== null) {
            session()->flash('error', "Only this staff member's line manager can submit their appraisal.");
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

            $this->successMessage = "Appraisal submitted. Someone who can approve HR records finalises it next.";
            $this->showSuccessModal = true;
            $this->loadGridData();
            $this->selectedEmployeeId = null;

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn't submit the appraisal, so nothing was saved. Try again, and if it still fails, ask an administrator to check the error log.");
        }
    }

    public function finalizeAppraisal($appraisalId)
    {
        if (!Auth::user()->can('hr.approve')) {
            session()->flash('error', "You don't have permission to finalise appraisals. Ask an administrator to change your role.");
            return;
        }

        // Only an appraisal that isn't final yet can be finalised
        DB::table('performance_appraisals')->where('id', $appraisalId)->where('status', '!=', 'Finalized')->update([
            'status' => 'Finalized',
            'updated_at' => now()
        ]);

        $this->successMessage = "Appraisal finalised. The score can no longer be changed.";
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