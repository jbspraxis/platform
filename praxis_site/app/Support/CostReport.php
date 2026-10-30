<?php

namespace App\Support;

/**
 * The cost of poor quality report (GET /reports/{token}) shaped for /dossier/{token}.
 *
 * Reads the figures HQ computes (the site never recalculates them). Accepts the API's snake_case names
 * and the older copq_assessments column names. Price-like figures (a proposed fee, a break-even time)
 * are never shown: the site carries no prices (docs/BRAND.md section 14).
 */
final class CostReport
{
    public static function fromApi(array $data): array
    {
        $figures = is_array($data['figures'] ?? null) ? $data['figures'] : [];
        $inputs = is_array($data['inputs'] ?? null) ? $data['inputs'] : [];
        $labels = is_array($data['labels'] ?? null) ? $data['labels'] : [];
        $pick = function (array $keys, array ...$sources) {
            foreach ($sources as $source) {
                foreach ($keys as $key) {
                    if (isset($source[$key]) && $source[$key] !== '' && ! is_array($source[$key])) {
                        return $source[$key];
                    }
                }
            }

            return null;
        };
        $number = fn (array $keys, array ...$sources) => is_numeric($v = $pick($keys, ...$sources)) ? (float) $v : null;

        $currency = strtoupper((string) ($pick(['currency', 'copq_currency'], $data, $inputs) ?? 'USD'));
        $total = $number(['total', 'total_copq', 'annual', 'annual_cost', 'calculated_leakage'], $figures, $data);
        $appraisal = $number(['appraisal', 'appraisal_cost'], $figures);
        $internal = $number(['internal_failure', 'internal', 'internal_failure_cost'], $figures);
        $external = $number(['external_failure', 'external', 'external_failure_cost'], $figures);
        $monthly = $number(['monthly', 'monthly_cost', 'per_month'], $figures) ?? ($total !== null ? $total / 12 : null);

        $parts = [];
        foreach ([
            'appraisal' => $appraisal,
            'internal' => $internal,
            'external' => $external,
        ] as $key => $amount) {
            if ($amount === null) {
                continue;
            }
            $label = is_array($labels[$key] ?? null) ? $labels[$key] : [];
            $parts[] = [
                'key' => $key,
                'title' => self::text($label['title'] ?? null) ?: __('site.report.parts.' . $key . '.title'),
                'description' => self::text($label['description'] ?? null) ?: __('site.report.parts.' . $key . '.description'),
                'amount' => Format::money($amount, $currency),
                'share' => $total ? (int) round(max(0, $amount) / max(1, $total) * 100) : null,
            ];
        }

        $errorRate = $number(['error_rate', 'copq_error_rate'], $figures, $inputs, $data);
        $baseline = $number(['error_floor', 'sector_baseline', 'baseline_error_rate'], $figures);

        $facts = [];
        foreach ([
            'industry' => $pick(['industry_label', 'industry', 'copq_industry'], $data, $inputs),
            'tool' => $pick(['display_name'], is_array($data['tool_profile'] ?? null) ? $data['tool_profile'] : [])
                ?? $pick(['tool_label', 'tool_name', 'tool', 'primary_tool'], $data, $inputs),
            'bottleneck' => $pick(['bottleneck_label', 'bottleneck'], $data, $inputs),
            'headcount' => is_numeric($v = $pick(['headcount'], $inputs, $data)) ? Format::number($v) : null,
            'mgmt_nodes' => is_numeric($v = $pick(['mgmt_nodes'], $inputs, $data)) ? Format::number($v) : null,
            'turnover' => is_numeric($v = $pick(['turnover', 'copq_turnover'], $inputs, $data)) ? Format::money($v, $currency) : null,
            'manual_hrs' => is_numeric($v = $pick(['manual_hrs', 'copq_latency'], $inputs, $data)) ? Format::number($v, floor((float) $v) == (float) $v ? 0 : 1) : null,
            'hourly_cost' => is_numeric($v = $pick(['hourly_cost', 'copq_hourly_cost'], $inputs, $data)) ? Format::money($v, $currency) : null,
            'currency' => $currency,
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $facts[] = ['label' => __('site.report.facts.' . $key), 'value' => self::text((string) $value)];
            }
        }

        $company = self::text($pick(['company', 'company_name'], $data));

        return [
            'reference' => self::text($pick(['token', 'report_token', 'reference'], $data)),
            'company' => $company,
            'date' => Format::date(is_string($d = $pick(['created_at', 'prepared_at', 'date'], $data)) ? $d : null),
            'total' => $total !== null ? Format::money($total, $currency) : null,
            'monthly' => $monthly !== null ? Format::money($monthly, $currency) : null,
            'parts' => $parts,
            'error_rate' => $errorRate !== null ? Format::percent($errorRate) : null,
            'baseline' => $baseline !== null ? Format::percent($baseline) : null,
            'analysis' => self::text($pick(['analysis', 'ai_friction_analysis', 'summary'], $data)),
            'facts' => $facts,
        ];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_numeric($value)
            ? trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : '';
    }
}
