<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\ProposalPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * The PDF of a version of the proposal (docs/OPPORTUNITIES.md section 4, row 4): in HQ for anyone who can open
 * opportunities (a preview while it is a draft), and in the portal from the signed link of a version that was sent.
 */
class ProposalPdfController extends Controller
{
    public function hq(string $opportunity, string $proposal): Response
    {
        abort_unless(OpportunityAccess::canView(Auth::user()), 403);
        $p = Proposal::where('opportunity_id', $opportunity)->where('signing_mode', 'approve')->findOrFail($proposal);

        return $this->pdf($p, 'inline');
    }

    public function portal(string $proposal): Response
    {
        // Only what the client was sent: never a draft
        $p = Proposal::where('signing_mode', 'approve')->whereNotNull('sent_at')->findOrFail($proposal);

        return $this->pdf($p, 'attachment');
    }

    private function pdf(Proposal $proposal, string $disposition): Response
    {
        return response(ProposalPdf::render($proposal), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . ProposalPdf::filename($proposal) . '"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
