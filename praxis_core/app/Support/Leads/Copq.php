<?php

namespace App\Support\Leads;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The cost of poor quality estimate (copq_assessments): the website calculator's maths (CostReportService uses this
 * too), so an estimate made in HQ, in Add a lead's "More details" or with "Calculate" on the lead page, gives the same
 * figure the website would. Optional for a lead (owner's decision 2).
 */
final class Copq
{
    /** The figures the estimate needs, with their labels */
    public const FIGURES = [
        'turnover' => 'Annual turnover',
        'acv' => 'Average contract value',
        'headcount' => 'Total headcount',
        'mgmt_nodes' => 'Managers who approve work',
        'manual_hrs' => 'Manual hours a week, per manager',
        'hourly_cost' => 'Hourly cost of a manager',
        'error_rate' => 'Error rate (%)',
    ];

    /** Rules for the estimate's fields under $prefix (tool, bottleneck, currency and FIGURES), all required together */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}tool" => ['required', 'string', 'max:50'],
            "{$prefix}bottleneck" => ['required', 'string', 'max:100'],
            "{$prefix}currency" => ['required', 'string', 'max:3'],
            "{$prefix}turnover" => ['required', 'numeric', 'min:0', 'max:1000000000000000'],
            "{$prefix}acv" => ['required', 'numeric', 'min:0', 'max:1000000000000'],
            "{$prefix}headcount" => ['required', 'integer', 'min:1', 'max:1000000'],
            "{$prefix}mgmt_nodes" => ['required', 'integer', 'min:1', 'max:1000000'],
            "{$prefix}manual_hrs" => ['required', 'numeric', 'min:0', 'max:168'],
            "{$prefix}hourly_cost" => ['required', 'numeric', 'min:0', 'max:100000000'],
            "{$prefix}error_rate" => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public static function messages(string $prefix = ''): array
    {
        $out = [
            "{$prefix}tool.required" => 'Choose the main system they use.',
            "{$prefix}bottleneck.required" => 'Choose their main bottleneck.',
            "{$prefix}currency.required" => 'Choose the currency of the figures.',
        ];
        foreach (self::FIGURES as $key => $label) {
            $out["{$prefix}{$key}.required"] = "Enter the {$label}, or leave every figure empty.";
            $out["{$prefix}{$key}.numeric"] = "Enter the {$label} as a number.";
            $out["{$prefix}{$key}.integer"] = "Enter the {$label} as a whole number.";
            $out["{$prefix}{$key}.min"] = "The {$label} can’t be below :min.";
            $out["{$prefix}{$key}.max"] = "The {$label} is too large.";
        }

        return $out;
    }

    /** Whether any figure is filled in (then every field of the estimate is required) */
    public static function started(array $in): bool
    {
        return collect(array_keys(self::FIGURES))->contains(fn ($k) => trim((string) ($in[$k] ?? '')) !== '');
    }

    /**
     * The estimate, as the website calculator computes it: appraisal cost, internal failure and external failure by
     * sector archetype (mdm_copq_industries), multipliers from the system (mdm_copq_architectures) and bottleneck
     * (mdm_copq_bottlenecks); the tier from USD thresholds (mdm_exchange_rates).
     *
     * @return array{total: float, tier: int, error_rate: float, mgmt_nodes: int}
     */
    public static function calculate(string $industry, string $tool, string $bottleneck, array $f, string $currency): array
    {
        $acv = (float) ($f['acv'] ?? 0);
        $headcount = (int) ($f['headcount'] ?? 1);
        $mgmtNodes = (int) ($f['mgmt_nodes'] ?? max(1, floor($headcount * 0.20)));
        $turnover = (float) ($f['turnover'] ?? 0);
        $manualHrs = (float) ($f['manual_hrs'] ?? 5);
        $hourlyCost = (float) ($f['hourly_cost'] ?? 50);
        $baseError = (float) ($f['error_rate'] ?? 3);

        $archData = DB::table('mdm_copq_architectures')->where('tech_key', $tool)->first();
        $bnData = DB::table('mdm_copq_bottlenecks')->where('bottleneck_name', $bottleneck)->first();
        $indData = DB::table('mdm_copq_industries')->where('industry_name', $industry)->first();

        $actualErrorRate = $baseError * ($archData ? $archData->error_multiplier : 1.00);
        $appMult = $bnData ? $bnData->appraisal_multiplier : 1.00;
        $failMult = $bnData ? $bnData->failure_multiplier : 1.00;
        $archetype = $indData->sector_archetype ?? 'COMMERCIAL';

        // Internal costs (time and process friction)
        $appraisalCost = ($mgmtNodes * $manualHrs * $hourlyCost * 52) * $appMult;
        $internalFailure = ($turnover * ($actualErrorRate / 100) * 0.60) * $failMult;

        // External costs by sector
        $externalFailure = match ($archetype) {
            'SOVEREIGN' => $turnover * ($actualErrorRate / 100) * 0.85, // public funds misallocated, no clients to lose
            'IMPACT' => $turnover * ($actualErrorRate / 100) * 0.80,    // beneficiary reach and donor clawbacks
            'ASSET_HEAVY' => $turnover * ($actualErrorRate / 100) * 0.90, // downtime and supply chain penalties
            default => (($acv > 0 ? max(1, $turnover / $acv) : 0) * (($actualErrorRate / 100) * 0.40)) * $acv * 1.5, // client churn
        };
        $total = $appraisalCost + $internalFailure + $externalFailure * $failMult;

        // Tier, on USD thresholds (rate_to_usd = units of this currency per 1 USD)
        $rateToUsd = (float) (DB::table('mdm_exchange_rates')->where('currency_code', $currency)->value('rate_to_usd') ?: 0);
        $turnoverUsd = $rateToUsd > 0 ? $turnover / $rateToUsd : $turnover;
        $copqUsd = $rateToUsd > 0 ? $total / $rateToUsd : $total;
        $tier = match (true) {
            $turnoverUsd >= 5000000 || $copqUsd >= 250000 => 1, // enterprise
            $turnoverUsd >= 1000000 || $copqUsd >= 50000 => 2,  // mid-market
            default => 3,
        };

        return ['total' => $total, 'tier' => $tier, 'error_rate' => $actualErrorRate, 'mgmt_nodes' => $mgmtNodes];
    }

    /**
     * Calculate and store an estimate for the lead from HQ ($in: tool, bottleneck, currency, FIGURES; the lead's
     * industry), with the industry's and bottleneck's own summary text (no AI call). Timeline "Cost of poor quality".
     */
    public static function store(Organization $lead, array $in, ?User $by, ?string $contactName = null, ?string $contactEmail = null): object
    {
        $industry = (string) $lead->industry;
        $result = self::calculate($industry, (string) $in['tool'], (string) $in['bottleneck'], $in, (string) $in['currency']);
        $indData = DB::table('mdm_copq_industries')->where('industry_name', $industry)->first();
        $bnData = DB::table('mdm_copq_bottlenecks')->where('bottleneck_name', $in['bottleneck'])->first();
        $summary = trim(str_replace('{COMPANY_NAME}', $lead->name, ($indData->fallback_diagnostic ?? "We found signs of avoidable cost in how {COMPANY_NAME} runs.")
            . ' ' . ($bnData->bottleneck_narrative ?? 'We recommend a review of the main bottleneck.')));

        $row = [
            'id' => (string) Str::uuid(), 'report_token' => 'PRX-' . strtoupper(Str::random(24)) . '-' . date('Y'), 'organization_id' => $lead->id,
            'executive_name' => $contactName, 'company_name' => $lead->name, 'corporate_email' => $contactEmail,
            'copq_industry' => $industry ?: null, 'primary_tool' => (string) $in['tool'], 'bottleneck' => (string) $in['bottleneck'],
            'copq_currency' => (string) $in['currency'], 'copq_turnover' => (float) $in['turnover'], 'headcount' => (int) $in['headcount'],
            'mgmt_nodes' => $result['mgmt_nodes'], 'copq_latency' => (float) $in['manual_hrs'], 'copq_hourly_cost' => (float) $in['hourly_cost'],
            'copq_error_rate' => $result['error_rate'], 'avg_contract_value' => (float) $in['acv'], 'calculated_leakage' => $result['total'],
            'ai_friction_analysis' => $summary, 'lead_tier' => $result['tier'], 'created_by' => $by?->id, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('copq_assessments')->insert($row);
        DB::table('organizations')->where('id', $lead->id)->update(['tech_reality' => $row['primary_tool'], 'turnover_bracket' => $row['copq_turnover'], 'updated_at' => now()]);
        LeadTimeline::record($lead->id, 'copq_calculated', 'Cost of poor quality estimated: ' . $row['copq_currency'] . ' ' . number_format($row['calculated_leakage']) . ' a year.', [
            'actor' => $by, 'related_type' => 'copq_assessment', 'related_id' => $row['id'], 'payload' => ['tier' => $result['tier']],
        ]);

        return (object) $row;
    }

    /** The lead's latest estimate, else null */
    public static function latest(string $organizationId): ?object
    {
        return DB::table('copq_assessments')->where('organization_id', $organizationId)->whereNull('deleted_at')->orderByDesc('created_at')->first();
    }

    /** Systems, bottlenecks and currencies from Master data, for the selects */
    public static function choices(): array
    {
        return [
            'systems' => DB::table('mdm_copq_architectures')->orderBy('display_name')->pluck('display_name', 'tech_key')->all(),
            'bottlenecks' => DB::table('mdm_copq_bottlenecks')->orderBy('bottleneck_name')->pluck('bottleneck_name')->all(),
            'currencies' => DB::table('mdm_exchange_rates')->orderBy('currency_code')->pluck('currency_name', 'currency_code')->all(),
        ];
    }
}
