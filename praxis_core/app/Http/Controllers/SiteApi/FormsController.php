<?php

namespace App\Http\Controllers\SiteApi;

use App\Services\Site\CareersService;
use App\Services\Site\FeedbackService;
use App\Services\Site\PortfolioService;
use App\Services\Site\WhitepaperService;
use Illuminate\Http\Request;

/** The smaller site endpoints: feedback, careers, the method PDF and the portfolio */
class FormsController extends SiteApiController
{
    /** POST /feedback (TelemetryNode on the old site) */
    public function feedback(Request $request, FeedbackService $feedback)
    {
        // Severity starts at 1 on the old form
        if (!$request->filled('severity')) {
            $request->merge(['severity' => 1]);
        }
        $in = $this->input($request, FeedbackService::rules(), array_values(FeedbackService::FIELDS));

        $feedback->submit($in, $this->visitorIp($request));

        return $this->data(['received' => true], 201);
    }

    /** POST /careers/applications, multipart with the CV in "cv" (Careers on the old site) */
    public function careers(Request $request, CareersService $careers)
    {
        // The country code starts at +234 on the old form
        if (!$request->filled('country_code')) {
            $request->merge(['country_code' => '+234']);
        }
        $in = $this->input($request, CareersService::rules(), array_values(CareersService::FIELDS), CareersService::apiMessages());

        // A repeat from the same email updates the application and adds the CV (still 201)
        $careers->submit($in, $request->file('cv'), $this->visitorIp($request));

        return $this->data(['received' => true], 201);
    }

    /** POST /whitepaper: record the request and email the method PDF (Services on the old site) */
    public function whitepaper(Request $request, WhitepaperService $whitepaper)
    {
        $in = $this->input($request, WhitepaperService::rules(), ['email']);

        // Limits in the service (429 too_many); the email goes after the response ("sent": accepted for sending)
        $whitepaper->record($in['email'], $this->visitorIp($request));
        $sent = $whitepaper->send($in['email'], afterResponse: true);

        return $this->data(['sent' => $sent], 201);
    }

    /** GET /portfolio */
    public function portfolio(PortfolioService $portfolio)
    {
        return $this->data($portfolio->payload(), 200, 600);
    }
}
