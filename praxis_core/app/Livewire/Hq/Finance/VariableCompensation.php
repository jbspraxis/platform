<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

#[Layout('components.layouts.hq')]
class VariableCompensation extends Component
{
    public $targetMonth;
    public $targetYear;
    public $targetRegion = 'ALL';
    
    // The working dataset for the grid
    public $employeeVariables = [];
    
    // UI State
    public $showSuccessModal = false;
    public $successMessage = '';

    public function mount()
    {
        $this->targetMonth = date('m');
        $this->targetYear = date('Y');
        $this->loadVariables();
    }

    public function updated($propertyName)
    {
        // Reload the grid if the filter changes
        if (in_array($propertyName, ['targetMonth', 'targetYear', 'targetRegion'])) {
            $this->loadVariables();
        }
    }

    public function loadVariables()
    {
        $periodString = Carbon::create($this->targetYear, $this->targetMonth, 1)->toDateString();

        $query = DB::table('mdm_employees as emp')
            ->leftJoin('monthly_payroll_variables as mpv', function($join) use ($periodString) {
                $join->on('emp.id', '=', 'mpv.mdm_employee_id')
                     ->where('mpv.payroll_period', '=', $periodString);
            })
            ->join('users as u', 'emp.user_id', '=', 'u.id')
            ->select(
                'emp.id as employee_id', 'emp.employee_code', 'emp.tax_region', 'emp.hire_date',
                'u.first_name', 'u.last_name',
                'mpv.id as variable_id',
                'mpv.prime_anciennete',
                'mpv.prime_performance',
                'mpv.thirteenth_month_bonus',
                'mpv.other_deductions',
                'mpv.is_processed'
            )
            ->where('emp.is_active', 1)
            ->orderBy('u.first_name');

        if ($this->targetRegion !== 'ALL') {
            $query->where('emp.tax_region', $this->targetRegion);
        }

        $results = $query->get();

        $this->employeeVariables = [];
        foreach ($results as $row) {
            $this->employeeVariables[$row->employee_id] = [
                'variable_id' => $row->variable_id,
                'name' => $row->first_name . ' ' . $row->last_name,
                'code' => $row->employee_code,
                'region' => $row->tax_region,
                'hire_date' => $row->hire_date,
                'prime_anciennete' => $row->prime_anciennete ?? 0.00,
                'prime_performance' => $row->prime_performance ?? 0.00,
                'thirteenth_month_bonus' => $row->thirteenth_month_bonus ?? 0.00,
                'other_deductions' => $row->other_deductions ?? 0.00,
                'is_processed' => $row->is_processed ?? 0
            ];
        }
    }

    /**
     * Auto-calculates the statutory Seniority Bonus (Prime d'Ancienneté) for OHADA regions.
     * Standard Law: 2% of base salary after 2 years, +1% for each subsequent year.
     */
    public function autoCalculateAnciennete()
    {
        $periodStart = Carbon::create($this->targetYear, $this->targetMonth, 1);
        $empIds = array_keys($this->employeeVariables);
        
        // Anti-N+1: Pre-fetch all active compensations for the current grid in a single query
        $activeComps = DB::table('mdm_employee_compensations')
            ->whereIn('mdm_employee_id', $empIds)
            ->whereNull('valid_to')
            ->pluck('base_salary', 'mdm_employee_id');
        
        foreach ($this->employeeVariables as $empId => $data) {
            if ($data['is_processed'] == 1 || empty($data['hire_date'])) continue;

            $hireDate = Carbon::parse($data['hire_date']);
            $yearsOfService = $hireDate->diffInYears($periodStart);

            if ($yearsOfService >= 2) {
                $baseSalary = $activeComps[$empId] ?? 0;
                
                if ($baseSalary > 0) {
                    $percentage = 0.02 + (($yearsOfService - 2) * 0.01);
                    $calculatedBonus = $baseSalary * $percentage;
                    $this->employeeVariables[$empId]['prime_anciennete'] = round($calculatedBonus, 2);
                }
            }
        }
    }

    public function saveVariables()
    {
        abort_unless(Auth::user()->can('payroll.edit'), 403);

        $periodString = Carbon::create($this->targetYear, $this->targetMonth, 1)->toDateString();

        try {
            DB::transaction(function () use ($periodString) {
                // Rows locked by an approved payroll run, read from the database (not from the page)
                $lockedIds = DB::table('monthly_payroll_variables')
                    ->where('payroll_period', $periodString)
                    ->where('is_processed', 1)
                    ->pluck('mdm_employee_id')
                    ->map(fn ($id) => (string) $id)
                    ->flip();

                foreach ($this->employeeVariables as $empId => $data) {
                    // Skip rows already locked by an approved payroll run
                    if ($data['is_processed'] == 1 || isset($lockedIds[(string) $empId])) continue;

                    // Upsert prevents duplicate rows if 'variable_id' is out of sync
                    DB::table('monthly_payroll_variables')->updateOrInsert(
                        [
                            'mdm_employee_id' => $empId,
                            'payroll_period' => $periodString,
                        ],
                        [
                            'id' => $data['variable_id'] ?: Str::uuid()->toString(),
                            'prime_anciennete' => (float) $data['prime_anciennete'],
                            'prime_performance' => (float) $data['prime_performance'],
                            'thirteenth_month_bonus' => (float) $data['thirteenth_month_bonus'],
                            'other_deductions' => (float) $data['other_deductions'],
                            'updated_at' => now(),
                            'created_at' => $data['variable_id'] ? DB::raw('created_at') : now(),
                        ]
                    );
                }
            });

            $this->successMessage = "Variable pay for " . Carbon::create($this->targetYear, $this->targetMonth, 1)->format('F Y') . " saved. The payroll run for that month uses it.";
            $this->showSuccessModal = true;
            $this->loadVariables();

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn't save variable pay, so nothing was changed. Check that every amount is a number, then save again.");
        }
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    public function render()
    {
        return view('livewire.hq.finance.variable-compensation');
    }
}