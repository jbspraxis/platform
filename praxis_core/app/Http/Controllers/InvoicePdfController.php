<?php

namespace App\Http\Controllers;

use App\Support\Access;
use App\Support\Finance\InvoicePdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * An invoice's PDF (docs/QA-PORTAL.md D11): in HQ › Finance › Invoices (the route needs finance.view), in the client's
 * language, and in the portal for an account that sees invoices (portal.billing.view) on one of its organisation's
 * projects it may open, in the portal's language.
 */
class InvoicePdfController extends Controller
{
    public function hq(string $invoice): Response
    {
        return $this->pdf(DB::table('invoices')->where('id', $invoice)->whereNull('deleted_at')->first() ?? abort(404), 'inline', null);
    }

    public function portal(string $invoice): Response
    {
        $client = Auth::guard('client')->user();
        abort_unless(Access::allows($client, Access::CLIENT_PREFIX . 'billing.view'), 403);
        $row = DB::table('invoices')->join('projects', 'projects.id', '=', 'invoices.project_id')
            ->where('invoices.id', $invoice)->whereNull('invoices.deleted_at')
            ->where('projects.organization_id', $client->organization_id)
            ->when($client->restricted_project_id, fn ($q, $project) => $q->where('projects.id', $project))
            ->select('invoices.*')->first();
        abort_unless($row, 404);
        if (! $row->client_viewed_at) {
            DB::table('invoices')->where('id', $row->id)->update(['client_viewed_at' => now()]);
        }

        return $this->pdf($row, 'attachment', app()->getLocale() === 'fr' ? 'fr' : 'en');
    }

    private function pdf(object $invoice, string $disposition, ?string $language): Response
    {
        return response(InvoicePdf::render($invoice, $language), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . InvoicePdf::filename($invoice) . '"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
