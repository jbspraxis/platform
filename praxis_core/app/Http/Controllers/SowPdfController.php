<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\StatementOfWork;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\SowPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * The PDF of a version of the SOW (docs/OPPORTUNITIES.md section 4, row 5): in HQ for anyone who can open that
 * opportunity (a preview while it is a draft), and in the portal from the signed link of a version that was sent.
 */
class SowPdfController extends Controller
{
    public function hq(string $opportunity, string $sow): Response
    {
        abort_unless(OpportunityAccess::canView(Auth::user(), Opportunity::findOrFail($opportunity)), 403);

        return $this->pdf(StatementOfWork::where('opportunity_id', $opportunity)->findOrFail($sow), 'inline');
    }

    public function portal(string $sow): Response
    {
        // Only what the client was sent: never a draft
        return $this->pdf(StatementOfWork::whereNotNull('sent_at')->findOrFail($sow), 'attachment');
    }

    private function pdf(StatementOfWork $sow, string $disposition): Response
    {
        // An imported SOW was signed on paper: HQ has no fees for it, so no contract-looking PDF is made
        abort_if(\App\Support\Opportunities\Sows::isImported($sow), 404);

        return response(SowPdf::render($sow), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . SowPdf::filename($sow) . '"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
