<?php

namespace App\Http\Controllers\SiteApi;

use App\Services\Site\CostReportService;
use Illuminate\Http\Request;

/**
 * GET /calculator/reference, POST /calculator/assessments and GET /reports/{token}: the cost of
 * poor quality calculator and its report (ConversionHub and the dossier page on the old site).
 */
class CalculatorController extends SiteApiController
{
    public function reference(CostReportService $reports)
    {
        return $this->data([
            'industries' => $reports->industries()->map(fn ($i) => ['name' => $i->industry_name, 'archetype' => $i->sector_archetype])->values(),
            'tools' => $reports->architectures()->map(fn ($a) => ['key' => $a->tech_key, 'name' => $a->display_name])->values(),
            'bottlenecks' => $reports->bottlenecks()->map(fn ($b) => ['name' => $b->bottleneck_name])->values(),
            'currencies' => $reports->currencies()->map(fn ($c) => ['code' => $c->currency_code, 'name' => $c->currency_name, 'rate_to_usd' => (float) $c->rate_to_usd])->values(),
        ], 200, 600);
    }

    public function store(Request $request, CostReportService $reports)
    {
        $in = $this->input($request, CostReportService::rules(), array_values(CostReportService::FIELDS));

        // Limits in the service (429 too_many); the email goes after the response ("emailed": accepted for sending)
        $token = $reports->generate($in, $this->visitorIp($request));
        $emailed = $reports->emailReport($token, afterResponse: true);

        return $this->data([
            'token' => $token,
            'report_path' => '/dossier/' . $token,
            'emailed' => $emailed,
            'figures' => $reports->figuresFor($token),
        ], 201);
    }

    public function report(string $token, CostReportService $reports)
    {
        $report = $reports->report($token);
        if (!$report) {
            return $this->error(404, ['code' => 'not_found']);
        }

        return $this->data($report, 200, 300);
    }
}
