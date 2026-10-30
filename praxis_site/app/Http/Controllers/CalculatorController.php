<?php

namespace App\Http\Controllers;

use App\Support\CalculatorOptions;
use App\Support\HqApi;
use Illuminate\Http\Request;

/**
 * /cost-calculator: the cost of poor quality calculator. The lists come from GET /calculator/reference;
 * HQ runs the figures, stores them and emails the report (POST /calculator/assessments), then the
 * visitor lands on /dossier/{token}.
 */
class CalculatorController extends Controller
{
    private const FIELDS = [
        'email', 'name', 'company', 'industry', 'tool', 'bottleneck', 'acv', 'headcount', 'mgmt_nodes',
        'turnover', 'manual_hrs', 'hourly_cost', 'error_rate', 'currency',
    ];

    /** Figures typed with thousands separators ("1,250,000") are sent as plain numbers. */
    private const NUMBERS = ['acv', 'headcount', 'mgmt_nodes', 'turnover', 'manual_hrs', 'hourly_cost', 'error_rate'];

    public function show(HqApi $api)
    {
        $options = CalculatorOptions::fromApi($api->calculatorReference());

        return view('pages.calculator', [
            'options' => $options,
            'stale' => $api->lastReadWasStale(),
        ]);
    }

    public function store(Request $request, HqApi $api)
    {
        $fields = $this->fields($request, self::FIELDS);
        foreach (self::NUMBERS as $name) {
            if (isset($fields[$name])) {
                $plain = preg_replace('/[\s,\x{00A0}\x{202F}]/u', '', $fields[$name]);
                $fields[$name] = $plain !== '' ? $plain : $fields[$name];
            }
        }

        $data = $api->createAssessment($fields);

        $token = is_string($data['token'] ?? null) ? $data['token'] : '';
        $path = is_string($data['report_path'] ?? null) && preg_match('~^/dossier/[A-Za-z0-9-]+$~', $data['report_path'])
            ? $data['report_path']
            : '/dossier/' . rawurlencode($token);

        // "emailed": false when HQ could not email the copy (the report link still works)
        return redirect()->to($path)
            ->with('report_new', true)
            ->with('report_emailed', ($data['emailed'] ?? true) !== false);
    }
}
