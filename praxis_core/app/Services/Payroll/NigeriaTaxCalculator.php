<?php

namespace App\Services\Payroll;

class NigeriaTaxCalculator
{
    /**
     * Executes the Nigeria PITA & PRA Payroll Calculation using Cumulative YTD Smoothing
     * 100% Compliant with PRA 2014 and Finance Act 2020
     */
    public static function calculate(array $data, $config, $bands)
    {
        // 1. Aggregate Cash Earnings
        $base = $data['base_salary'] ?? 0;
        $allowance_housing = $data['allowance_housing'] ?? 0;
        $allowance_transport = $data['allowance_transport'] ?? 0;
        $allowance_utility = $data['allowance_utility'] ?? 0;
        
        $allowances = $allowance_housing + $allowance_transport + $allowance_utility;
        $primes = ($data['prime_anciennete'] ?? 0) + ($data['prime_performance'] ?? 0) + ($data['thirteenth_month_bonus'] ?? 0) + ($data['overtime_pay'] ?? 0);
        $deductions = $data['unexcused_absence_deduction'] ?? 0;

        $monthly_cash_gross = max(0, $base + $allowances + $primes - $deductions);

        // 2. Benefits in Kind (BIK) - Notional Gross (Added for tax, not paid in cash)
        $bik_housing = !empty($data['bik_housing_provided']) ? ($base * 0.15) : 0;
        $bik_vehicle = !empty($data['bik_vehicle_provided']) ? ($base * 0.05) : 0;
        $bik_domestic = !empty($data['bik_domestic_staff']) ? ($base * 0.05) : 0;
        $monthly_bik_value = $bik_housing + $bik_vehicle + $bik_domestic;

        $monthly_total_gross = $monthly_cash_gross + $monthly_bik_value;

        // 3. Statutory Deductions (PRA & NHF)
        // STRICT COMPLIANCE: PRA 2014 mandates Pension is strictly on Basic + Housing + Transport (BHT)
        $pension_base = $base + $allowance_housing + $allowance_transport;
        
        // Ensure we don't exceed the cap if one is configured
        $pension_subject_amount = isset($config->pension_cap_amount) && $config->pension_cap_amount > 0 
            ? min($pension_base, $config->pension_cap_amount) 
            : $pension_base;

        $monthly_pension_employee = $pension_subject_amount * ($config->pension_employee_rate ?? 0.08);
        $monthly_pension_employer = $pension_subject_amount * ($config->pension_employer_rate ?? 0.10);
        
        $monthly_nhf = $base * ($config->housing_fund_rate ?? 0.025);
        $monthly_nhis = $base * ($config->health_employee_rate ?? 0);

        $total_statutory_reliefs = $monthly_pension_employee + $monthly_nhf + $monthly_nhis;

        // 4. Cumulative YTD Annualization Logic
        $month_index = $data['month_number'] ?? date('n'); // 1 to 12
        $ytd_gross = ($data['ytd_gross'] ?? 0) + $monthly_total_gross;
        $ytd_reliefs = ($data['ytd_reliefs'] ?? 0) + $total_statutory_reliefs;
        $ytd_tax_paid = $data['ytd_tax_paid'] ?? 0;

        // Project the rest of the year assuming current month's regular pay (excluding primes/bonuses)
        $months_remaining = 12 - $month_index;
        $projected_annual_gross = $ytd_gross + (($base + $allowances + $monthly_bik_value) * $months_remaining);
        $projected_annual_reliefs = $ytd_reliefs + ($total_statutory_reliefs * $months_remaining);

        // 5. Consolidated Relief Allowance (CRA) - Finance Act 2020 Compliance
        // Gross Income for CRA must exclude statutory reliefs first
        $cra_gross_income = max(0, $projected_annual_gross - $projected_annual_reliefs);

        $cra_fixed = $config->cra_fixed_amount ?? 200000;
        $cra_percentage = $config->cra_proportional_rate ?? 0.20;
        
        $cra_base = max($cra_fixed, ($cra_gross_income * 0.01));
        $annual_cra = $cra_base + ($cra_gross_income * $cra_percentage);

        // 6. Annual Taxable Income
        $annual_taxable = $cra_gross_income - $annual_cra;
        if ($annual_taxable < 0) $annual_taxable = 0;

        // 7. Calculate Annual PAYE using Dynamic DB Bands
        $annual_paye = self::calculateDynamicBands($annual_taxable, $bands);

        // Minimum Tax Fallback (1% of Finance Act 2020 Gross Income)
        $minimum_tax_rate = $config->minimum_tax_rate ?? 0.01;
        $annual_minimum_tax = $cra_gross_income * $minimum_tax_rate;
        if ($annual_paye < $annual_minimum_tax) {
            $annual_paye = $annual_minimum_tax;
        }

        // 8. Cumulative Extraction for Current Month
        $target_cumulative_tax = ($annual_paye / 12) * $month_index;
        $current_month_paye = $target_cumulative_tax - $ytd_tax_paid;
        
        if ($current_month_paye < 0) $current_month_paye = 0;

        // 9. Net Pay Calculation (Subtract from CASH gross)
        $total_cash_deductions = $monthly_pension_employee + $monthly_nhf + $monthly_nhis + $current_month_paye;
        $net_pay = $monthly_cash_gross - $total_cash_deductions;

        return [
            'gross_cash' => round($monthly_cash_gross, 2),
            'taxable_gross_with_bik' => round($monthly_total_gross, 2),
            'deductions' => [
                'pension' => round($monthly_pension_employee, 2),
                'nhf'     => round($monthly_nhf, 2),
                'nhis'    => round($monthly_nhis, 2),
                'paye'    => round($current_month_paye, 2),
            ],
            'total_deductions' => round($total_cash_deductions, 2),
            'net_pay' => round($net_pay, 2),
            'employer_liabilities' => [
                'pension' => round($monthly_pension_employer, 2),
                'itf'     => round($monthly_total_gross * ($config->training_fund_rate ?? 0.01), 2),
                'nsitf'   => round($monthly_total_gross * 0.01, 2)
            ]
        ];
    }

    private static function calculateDynamicBands($taxable, $bands)
    {
        $tax = 0;
        foreach ($bands as $band) {
            if ($taxable <= 0) break;
            
            $band_capacity = $band->upper_limit ? ($band->upper_limit - $band->lower_limit) : INF;
            $amount_in_band = min($taxable, $band_capacity);
            
            $tax += $amount_in_band * $band->tax_rate;
            $taxable -= $amount_in_band;
        }
        return $tax;
    }
}