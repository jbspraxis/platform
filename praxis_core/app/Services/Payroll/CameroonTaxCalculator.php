<?php

namespace App\Services\Payroll;

class CameroonTaxCalculator
{
    /**
     * Executes the Cameroon CGI & CNPS Payroll Calculation dynamically
     * 100% Compliant with OHADA and CGI Article 69 (Rounding Laws)
     */
    public static function calculate(array $data, $config, $bands)
    {
        $base = $data['base_salary'] ?? 0;
        $allowances = ($data['allowance_housing'] ?? 0) + ($data['allowance_transport'] ?? 0) + ($data['allowance_utility'] ?? 0);
        $primes = ($data['prime_anciennete'] ?? 0) + ($data['prime_performance'] ?? 0) + ($data['thirteenth_month_bonus'] ?? 0) + ($data['overtime_pay'] ?? 0);
        $deductions = $data['unexcused_absence_deduction'] ?? 0;

        $cashGross = max(0, $base + $allowances + $primes - $deductions);

        // 1. Benefits in Kind (BIK) - Notional Gross
        // Standard CGI practice evaluates BIK against the base salary.
        $bik_housing = !empty($data['bik_housing_provided']) ? ($base * 0.15) : 0;
        $bik_vehicle = !empty($data['bik_vehicle_provided']) ? ($base * 0.10) : 0;
        $bik_domestic = !empty($data['bik_domestic_staff']) ? ($base * 0.05) : 0;
        $total_bik = $bik_housing + $bik_vehicle + $bik_domestic;

        $taxableGross = $cashGross + $total_bik;

        // 2. CNPS Calculation
        $cnps_cap = $config->pension_cap_amount ?? 750000;
        $cnps_base = min($taxableGross, $cnps_cap);
        $cnps_employee = $cnps_base * ($config->pension_employee_rate ?? 0.042);
        $cnps_employer = $cnps_base * ($config->pension_employer_rate ?? 0.084);

        // 3. IRPP (Personal Income Tax) Base
        $net_after_cnps = $taxableGross - $cnps_employee;
        $professional_abatement = $net_after_cnps * ($config->proportional_abatement_rate ?? 0.30);
        
        // Strict rounding to prevent infinite float bleed (500,000 / 12)
        $fixed_abatement = $config->fixed_abatement ?? round(500000 / 12, 2); 

        $net_taxable_income = $net_after_cnps - $professional_abatement - $fixed_abatement;
        if ($net_taxable_income < 0) $net_taxable_income = 0;

        // CRITICAL COMPLIANCE FIX: CGI requires rounding down to the nearest 1,000 XAF
        $net_taxable_income = floor($net_taxable_income / 1000) * 1000;

        // 4. IRPP Progressive Monthly Tax Bands
        $irpp = self::calculateDynamicBands($net_taxable_income, $bands);

        // 5. CAC (Centimes Additionnels Communaux) - 10% of IRPP
        $cac = $irpp * 0.10;

        // 6. CFC (Crédit Foncier du Cameroun)
        $cfc_employee = $taxableGross * ($config->housing_fund_rate ?? 0.01);
        $cfc_employer = $taxableGross * 0.015;

        // 7. RAV (Redevance Audiovisuelle)
        $rav = self::calculateRav($taxableGross);

        // 8. FNE (Fonds National de l'Emploi)
        $fne_employer = $taxableGross * ($config->training_fund_rate ?? 0.01);

        // 9. Final Net Pay Calculation
        $total_deductions = $cnps_employee + $irpp + $cac + $cfc_employee + $rav;
        $net_pay = $cashGross - $total_deductions;

        return [
            'gross_cash' => round($cashGross, 2),
            'taxable_gross_with_bik' => round($taxableGross, 2),
            'deductions' => [
                'cnps' => round($cnps_employee, 2),
                'irpp' => round($irpp, 2),
                'cac'  => round($cac, 2),
                'cfc'  => round($cfc_employee, 2),
                'rav'  => round($rav, 2)
            ],
            'total_deductions' => round($total_deductions, 2),
            'net_pay' => round($net_pay, 2),
            'employer_liabilities' => [
                'cnps' => round($cnps_employer, 2),
                'cfc'  => round($cfc_employer, 2),
                'fne'  => round($fne_employer, 2)
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

    private static function calculateRav($gross)
    {
        if ($gross <= 50000) return 0;
        if ($gross <= 100000) return 750;
        if ($gross <= 200000) return 1500;
        if ($gross <= 300000) return 3250;
        if ($gross <= 400000) return 4500;
        if ($gross <= 500000) return 6000;
        if ($gross <= 600000) return 7500;
        if ($gross <= 700000) return 9000;
        if ($gross <= 800000) return 10500;
        if ($gross <= 900000) return 12000;
        if ($gross <= 1000000) return 13500;
        return 15000;
    }
}