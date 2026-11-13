<?php

namespace App\Http\Controllers;

use App\Support\Access;
use App\Support\Finance\PaymentReceipts;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * A payment's receipt as a PDF (docs/RECEIPTS.md): in HQ › Finance › Invoices › Receipts (the route needs
 * finance.view), in the client's language; in the portal for an account that sees invoices (portal.billing.view) on
 * one of its organisation's projects it may open, in the portal's language.
 */
class ReceiptPdfController extends Controller
{
    public function hq(string $transaction): Response
    {
        return $this->pdf(PaymentReceipts::find($transaction) ?? abort(404), 'inline', null);
    }

    public function portal(string $transaction): Response
    {
        $client = Auth::guard('client')->user();
        abort_unless(Access::allows($client, Access::CLIENT_PREFIX . 'billing.view'), 403);
        $receipt = PaymentReceipts::find($transaction);
        abort_unless($receipt && $receipt->organization_id === $client->organization_id
            && (! $client->restricted_project_id || $client->restricted_project_id === $receipt->project_id), 404);

        return $this->pdf($receipt, 'attachment', app()->getLocale() === 'fr' ? 'fr' : 'en');
    }

    private function pdf(object $receipt, string $disposition, ?string $language): Response
    {
        return response(PaymentReceipts::render($receipt, $language), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . PaymentReceipts::filename($receipt) . '"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
