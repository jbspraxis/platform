<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProrationService
{
    /**
     * Calculates the exact proportional compensation for an employee in a given month.
     * Handles SCD Type 2 time-travel, mid-month hires, terminations, and mid-month raises.
     */
    public static function calculateProratedGross($employeeId, $month, $year)
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();
        $daysInMonth = $periodStart->daysInMonth;

        // 1. Fetch Master HR Record for Absolute Clamps
        $employee = DB::table('mdm_employees')
            ->where('id', $employeeId)
            ->select('hire_date', 'termination_date')
            ->first();

        if (!$employee) {
            return self::emptyCompensation();
        }

        // Establish Absolute Boundaries (Cannot be paid before hire or after termination)
        $globalStart = $employee->hire_date ? Carbon::parse($employee->hire_date)->startOfDay() : $periodStart;
        $globalEnd = $employee->termination_date ? Carbon::parse($employee->termination_date)->endOfDay() : $periodEnd;

        // If the entire pay period is outside their employment lifecycle, return zero.
        if ($globalStart->gt($periodEnd) || $globalEnd->lt($periodStart)) {
            return self::emptyCompensation();
        }

        // 2. Fetch Compensation Records active during this pay period
        $compensations = DB::table('mdm_employee_compensations')
            ->where('mdm_employee_id', $employeeId)
            ->where('valid_from', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('valid_to')
                      ->orWhere('valid_to', '>=', $periodStart->toDateString());
            })
            ->orderBy('valid_from', 'asc')
            ->get();

        if ($compensations->isEmpty()) {
            return self::emptyCompensation();
        }

        $proratedTotals = self::emptyCompensation();
        $totalActiveDaysProcessed = 0;

        // 3. Execute Fractional Math within Absolute Clamps
        foreach ($compensations as $comp) {
            $compStart = Carbon::parse($comp->valid_from)->startOfDay();
            $compEnd = $comp->valid_to ? Carbon::parse($comp->valid_to)->endOfDay() : $periodEnd;

            // Clamp compensation dates against both the Month Boundaries AND the HR Boundaries
            $effectiveStart = $compStart->max($periodStart)->max($globalStart);
            $effectiveEnd = $compEnd->min($periodEnd)->min($globalEnd);

            // Calculate active days (+1 inclusive logic based on midnight-to-midnight spans)
            // Using diffInDays on startOfDay and endOfDay yields the correct whole number.
            $activeDays = $effectiveStart->copy()->startOfDay()->diffInDays($effectiveEnd->copy()->startOfDay()) + 1;

            if ($activeDays <= 0) continue;
            
            // Failsafe: Ensure we don't accidentally over-prorate if overlaps exist in bad data
            if (($totalActiveDaysProcessed + $activeDays) > $daysInMonth) {
                $activeDays = $daysInMonth - $totalActiveDaysProcessed;
            }
            if ($activeDays <= 0) break;

            $totalActiveDaysProcessed += $activeDays;

            // The fractional multiplier (e.g., 15 days / 31 days)
            $prorationFactor = $activeDays / $daysInMonth;

            // Mathematically prorate all fixed financial columns
            $proratedTotals['base_salary'] += ($comp->base_salary * $prorationFactor);
            $proratedTotals['allowance_housing'] += ($comp->allowance_housing * $prorationFactor);
            $proratedTotals['allowance_transport'] += ($comp->allowance_transport * $prorationFactor);
            $proratedTotals['allowance_utility'] += ($comp->allowance_utility * $prorationFactor);
            
            // BIK Flags: True if active at ANY point in the valid window
            $proratedTotals['bik_housing_provided'] = $proratedTotals['bik_housing_provided'] || $comp->bik_housing_provided;
            $proratedTotals['bik_vehicle_provided'] = $proratedTotals['bik_vehicle_provided'] || $comp->bik_vehicle_provided;
            $proratedTotals['bik_domestic_staff'] = $proratedTotals['bik_domestic_staff'] || $comp->bik_domestic_staff;
            
            // Latest currency wins
            $proratedTotals['currency'] = $comp->currency;
        }

        // 4. Final Ledger Precision Rounding
        $proratedTotals['base_salary'] = round($proratedTotals['base_salary'], 2);
        $proratedTotals['allowance_housing'] = round($proratedTotals['allowance_housing'], 2);
        $proratedTotals['allowance_transport'] = round($proratedTotals['allowance_transport'], 2);
        $proratedTotals['allowance_utility'] = round($proratedTotals['allowance_utility'], 2);

        return $proratedTotals;
    }

    private static function emptyCompensation()
    {
        return [
            'base_salary' => 0.00,
            'allowance_housing' => 0.00,
            'allowance_transport' => 0.00,
            'allowance_utility' => 0.00,
            'bik_housing_provided' => false,
            'bik_vehicle_provided' => false,
            'bik_domestic_staff' => false,
            'currency' => 'USD'
        ];
    }
}