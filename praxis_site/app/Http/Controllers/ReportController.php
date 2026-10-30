<?php

namespace App\Http\Controllers;

use App\Support\CostReport;
use App\Support\HqApi;

/** /dossier/{token}: the cost of poor quality report (GET /reports/{token}). The path stays for old emails. */
class ReportController extends Controller
{
    public function show(string $token, HqApi $api)
    {
        return view('pages.report', ['report' => CostReport::fromApi($api->report($token))]);
    }
}
