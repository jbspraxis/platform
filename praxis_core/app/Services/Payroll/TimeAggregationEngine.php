<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class TimeAggregationEngine
{
    /**
     * Executes the Time & Attendance aggregation for a specific month and region.
     * Triggered right before a Payroll Draft is generated.
     */
    public static function aggregateMonth($month, $year, $regionCode = 'ALL')
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();

        // 1. Fetch Active Employees, their User IDs, & Contracted Hours
        $query = DB::table('mdm_employees as emp')
            ->join('employment_contracts as ec', 'emp.id', '=', 'ec.mdm_employee_id')
            ->select('emp.id', 'emp.user_id', 'emp.tax_region', 'ec.standard_weekly_hours')
            ->where('emp.is_active', 1);

        if ($regionCode !== 'ALL') {
            $query->where('emp.tax_region', $regionCode);
        }

        $employees = $query->get();

        // Calculate exact standard working days in this specific month (Mon-Fri)
        $workingDaysInMonth = 0;
        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            if (!$date->isWeekend()) {
                $workingDaysInMonth++;
            }
        }

        foreach ($employees as $emp) {
            self::processEmployee($emp, $periodStart, $periodEnd, $workingDaysInMonth);
        }

        return true;
    }

    private static function processEmployee($employee, Carbon $periodStart, Carbon $periodEnd, $workingDaysInMonth)
    {
        $standardWeekly = $employee->standard_weekly_hours ?? 40;
        $standardDaily = $standardWeekly / 5;
        $expectedMonthlyHours = $workingDaysInMonth * $standardDaily; 

        // 1. Tally Clock Logs (Optimized Query)
        $logs = DB::table('user_clock_logs')
            ->where('user_id', $employee->user_id)
            ->whereBetween('clock_in_time', [$periodStart, $periodEnd])
            ->get();

        $totalMinutesLogged = 0;
        $nightPremiumMinutes = 0;

        foreach ($logs as $log) {
            if (!$log->clock_out_time) continue;

            $totalMinutesLogged += $log->duration_minutes;

            // Precise Night Premium Intersection (22:00 to 06:00)
            $shiftStart = Carbon::parse($log->clock_in_time);
            $shiftEnd = Carbon::parse($log->clock_out_time);
            
            $current = $shiftStart->copy();
            while ($current->lt($shiftEnd)) {
                $hour = $current->hour;
                if ($hour >= 22 || $hour < 6) {
                    // Check how many minutes remain in the current hour block vs shift end
                    $minutesToNextHour = 60 - $current->minute;
                    $minutesLeftInShift = $current->diffInMinutes($shiftEnd);
                    $increment = min($minutesToNextHour, $minutesLeftInShift);
                    
                    $nightPremiumMinutes += $increment;
                    $current->addMinutes($increment);
                } else {
                    // Skip to 10 PM tonight
                    $current->setTime(22, 0, 0);
                    if ($current->lt($shiftStart)) {
                        $current->addDay(); // Move to next day's 10 PM if we jumped backward
                    }
                }
            }
        }

        $totalHoursLogged = $totalMinutesLogged / 60;
        $nightPremiumHours = $nightPremiumMinutes / 60;

        // 2. Overtime Distribution (OHADA/Cameroon Tiers based on actual monthly excess)
        $overtime120 = 0; $overtime130 = 0; $overtime140 = 0;
        $excessHours = $totalHoursLogged - $expectedMonthlyHours;

        if ($excessHours > 0) {
            // Generalize weekly tiers to the exact month's proportionality
            $weeksInMonth = $workingDaysInMonth / 5;
            $monthlyTier1Max = 8 * $weeksInMonth; // Next 8 hours/week
            $monthlyTier2Max = 8 * $weeksInMonth; // Subsequent 8 hours/week

            if ($excessHours <= $monthlyTier1Max) {
                $overtime120 = $excessHours;
            } else {
                $overtime120 = $monthlyTier1Max;
                $remaining = $excessHours - $monthlyTier1Max;

                if ($remaining <= $monthlyTier2Max) {
                    $overtime130 = $remaining;
                } else {
                    $overtime130 = $monthlyTier2Max;
                    $overtime140 = $remaining - $monthlyTier2Max;
                }
            }
        }

        // 3. Tally Approved Leaves (Constrained to the current month boundaries)
        $leaves = DB::table('employee_leaves')
            ->where('mdm_employee_id', $employee->id)
            ->where('status', 'Approved')
            ->where(function($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhereBetween('end_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
            })
            ->get();

        $unpaidAbsenceDays = 0;
        $paidLeaveDays = 0;

        foreach ($leaves as $leave) {
            // Calculate exact days overlapping this specific month
            $leaveStart = Carbon::parse($leave->start_date)->max($periodStart);
            $leaveEnd = Carbon::parse($leave->end_date)->min($periodEnd);
            
            $overlappingWeekdays = 0;
            for ($date = $leaveStart->copy(); $date->lte($leaveEnd); $date->addDay()) {
                if (!$date->isWeekend()) { $overlappingWeekdays++; }
            }

            if ($leave->leave_type === 'Unpaid') {
                $unpaidAbsenceDays += $overlappingWeekdays;
            } else {
                $paidLeaveDays += $overlappingWeekdays;
            }
        }

        // 4. Calculate Unexcused Absences
        $shortfallHours = $expectedMonthlyHours - $totalHoursLogged;
        $unexcusedDays = 0;
        
        if ($shortfallHours > 0) {
            $coveredLeaveHours = $paidLeaveDays * $standardDaily;
            $netShortfall = $shortfallHours - $coveredLeaveHours;
            
            if ($netShortfall > 0) {
                $unexcusedDays = $netShortfall / $standardDaily;
            }
        }

        // 5. Upsert into Monthly Payroll Variables
        $periodDateString = $periodStart->toDateString();
        
        $existingVariable = DB::table('monthly_payroll_variables')
            ->where('mdm_employee_id', $employee->id)
            ->where('payroll_period', $periodDateString)
            ->first();

        // Lock enforcement
        if ($existingVariable && $existingVariable->is_processed) {
            return; 
        }

        $data = [
            'mdm_employee_id' => $employee->id,
            'payroll_period' => $periodDateString,
            'overtime_hours_120' => round($overtime120, 2),
            'overtime_hours_130' => round($overtime130, 2),
            'overtime_hours_140' => round($overtime140, 2),
            'night_premium_hours' => round($nightPremiumHours, 2),
            'unexcused_absence_days' => round($unexcusedDays, 2) + $unpaidAbsenceDays,
            'updated_at' => now()
        ];

        if ($existingVariable) {
            DB::table('monthly_payroll_variables')
                ->where('id', $existingVariable->id)
                ->update($data);
        } else {
            $data['id'] = Str::uuid()->toString();
            $data['created_at'] = now();
            DB::table('monthly_payroll_variables')->insert($data);
        }
    }
}