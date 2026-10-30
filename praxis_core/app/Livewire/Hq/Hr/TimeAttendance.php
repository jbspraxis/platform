<?php

namespace App\Livewire\Hq\Hr;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\Payroll\TimeAggregationEngine;
use Carbon\Carbon;
use Exception;

#[Layout('components.layouts.hq')]
class TimeAttendance extends Component
{
    // Filter State
    public $targetMonth;
    public $targetYear;
    public $targetRegion = 'ALL';

    // Data State
    public $aggregatedData = [];
    
    // UI State
    public $showSuccessModal = false;
    public $successMessage = '';

    public function mount()
    {
        $this->targetMonth = date('m');
        $this->targetYear = date('Y');
        $this->loadData();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['targetMonth', 'targetYear', 'targetRegion'])) {
            $this->loadData();
        }
    }

    /**
     * Loads the already calculated variables from the ledger for viewing.
     * Uses a LEFT JOIN from mdm_employees so staff without variables still appear.
     */
    public function loadData()
    {
        $periodString = Carbon::create($this->targetYear, $this->targetMonth, 1)->toDateString();

        $query = DB::table('mdm_employees as emp')
            ->join('users as u', 'emp.user_id', '=', 'u.id')
            ->leftJoin('monthly_payroll_variables as mpv', function($join) use ($periodString) {
                $join->on('emp.id', '=', 'mpv.mdm_employee_id')
                     ->where('mpv.payroll_period', '=', $periodString);
            })
            ->where('emp.is_active', 1)
            ->select(
                'emp.employee_code', 'emp.tax_region',
                'u.first_name', 'u.last_name',
                'mpv.id as variable_id',
                'mpv.overtime_hours_120', 'mpv.overtime_hours_130', 'mpv.overtime_hours_140',
                'mpv.night_premium_hours', 'mpv.unexcused_absence_days', 'mpv.is_processed'
            )
            ->orderBy('u.first_name');

        if ($this->targetRegion !== 'ALL') {
            $query->where('emp.tax_region', $this->targetRegion);
        }

        $results = $query->get();
        
        $this->aggregatedData = [];
        foreach ($results as $row) {
            $this->aggregatedData[] = [
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'employee_code' => $row->employee_code,
                'tax_region' => $row->tax_region,
                
                // Safely coalesce nulls to 0 before the engine has run
                'overtime_hours_120' => $row->overtime_hours_120 ?? 0,
                'overtime_hours_130' => $row->overtime_hours_130 ?? 0,
                'overtime_hours_140' => $row->overtime_hours_140 ?? 0,
                'night_premium_hours' => $row->night_premium_hours ?? 0,
                'unexcused_absence_days' => $row->unexcused_absence_days ?? 0,
                
                'is_processed' => $row->is_processed ?? 0,
                'is_calculated' => !is_null($row->variable_id) // Tracks if the engine actually ran for this user
            ];
        }
    }

    /**
     * Runs the attendance calculation service over the month’s clock-in records.
     */
    public function runAggregationEngine()
    {
        // The route needs hr.view; writing attendance variables into the payroll ledger needs hr.edit
        abort_unless(Auth::user()->can('hr.edit'), 403);

        try {
            // Call the static engine
            TimeAggregationEngine::aggregateMonth($this->targetMonth, $this->targetYear, $this->targetRegion);
            
            $this->successMessage = "Attendance for " . Carbon::create($this->targetYear, $this->targetMonth, 1)->format('F Y') . " calculated from the clock-in records. Overtime and absences are saved for that month’s payroll.";
            $this->showSuccessModal = true;
            
            // Reload the grid with the fresh data
            $this->loadData();

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t calculate attendance, so nothing was changed. Try again, and if it still fails, ask an administrator to check the error log.");
        }
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    public function render()
    {
        return view('livewire.hq.hr.time-attendance');
    }
}