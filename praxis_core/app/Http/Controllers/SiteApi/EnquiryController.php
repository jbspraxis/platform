<?php

namespace App\Http\Controllers\SiteApi;

use App\Services\Site\EnquiryService;
use Illuminate\Http\Request;

/** POST /enquiries: the contact form (ContactHub on the old site) */
class EnquiryController extends SiteApiController
{
    public function store(Request $request, EnquiryService $enquiries)
    {
        $data = $request->all();

        // Diagnostic mode: as sent, otherwise when any diagnostic answer is given
        $diagnostic = array_key_exists('diagnostic', $data) && $data['diagnostic'] !== null
            ? (bool) filter_var($data['diagnostic'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : collect(['stage', 'bottleneck', 'other_bottleneck', 'tech_reality', 'gov_status'])->contains(fn ($f) => filled($data[$f] ?? null));

        $rules = EnquiryService::apiRules($diagnostic, $data['bottleneck'] ?? null) + ['diagnostic' => 'nullable|boolean'];
        $in = $this->input($request, $rules, [...array_values(EnquiryService::FIELDS), 'start_timing']);

        // Missing or empty: what the old form starts with
        foreach (EnquiryService::defaults() as $field => $value) {
            if (($in[$field] ?? null) === null || $in[$field] === '') {
                $in[$field] = $value;
            }
        }

        // Limits in the service (429 too_many); the emails go after the response
        $enquiries->submit($in, $diagnostic, $this->visitorIp($request), afterResponse: true);

        return $this->data(['received' => true], 201);
    }
}
