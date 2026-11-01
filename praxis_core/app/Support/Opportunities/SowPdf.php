<?php

namespace App\Support\Opportunities;

use App\Mail\MeetingMinutesMail;
use App\Models\StatementOfWork;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * A version of the SOW as a PDF (attached to the client's email, in the portal and in HQ), in the opportunity's
 * language: resources/views/pdf/sow.blade.php rendered by PR 4's ProposalPdf::fromHtml() (dompdf, pure PHP, nothing
 * remote). The parties, the sections, the milestones with their weights and amounts, the fees from the approved
 * proposal, and the signatures as text (name, date, fingerprint): no images, since dompdf needs gd for PNGs.
 */
final class SowPdf
{
    public static function render(StatementOfWork $sow): string
    {
        $sow->loadMissing('opportunity.organization', 'proposal');
        $language = $sow->opportunity?->language === 'fr' ? 'fr' : 'en';
        $previous = App::getLocale();
        App::setLocale($language);
        try {
            $html = view('pdf.sow', self::data($sow, $language))->render();
            $footer = __('sow.pdf.footer', ['ref' => $sow->reference, 'version' => $sow->version]);
            $page = __('proposals.pdf.page');
        } finally {
            App::setLocale($previous);
        }
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        return ProposalPdf::fromHtml($html, $footer, $page, $cache);
    }

    /** "SOW-20261105-482 v2 - Finance system.pdf" */
    public static function filename(StatementOfWork $sow): string
    {
        $title = trim(preg_replace('/[^A-Za-z0-9 ._-]+/', '', Str::ascii((string) $sow->title))) ?: 'Statement of work';

        return Str::limit("{$sow->reference} v{$sow->version} - {$title}", 120, '') . '.pdf';
    }

    /**
     * What the PDF, the portal page and HQ show of the price: the approved proposal's lines, schedule and price before
     * VAT, with the SOW's own VAT (Sows::vat: none when the issuing company doesn't charge it), and the deposit
     */
    public static function fees(StatementOfWork $sow): array
    {
        $proposal = $sow->proposal;
        $lines = $proposal ? Proposals::lines($proposal) : collect();
        $vat = Sows::vat($sow);
        $totals = Proposals::totals($lines, (float) $vat['rate']);
        $schedule = (string) ($proposal?->payment_schedule_type ?? '');
        $deposit = $proposal ? \App\Support\Finance\DepositInvoice::percent($schedule, $proposal->custom_deposit_percent ?? null) : 0;
        $milestones = Sows::cleanMilestones((array) $sow->milestones);

        return [
            'currency' => (string) ($proposal?->currency ?? ''),
            'lines' => $lines,
            'totals' => $totals,
            'vat' => $vat,
            'vat_rate' => (float) $vat['rate'],
            'schedule' => Lang::has('proposals.schedules.' . $schedule) ? __('proposals.schedules.' . $schedule) : null,
            'deposit_percent' => $deposit,
            'deposit' => round($totals['subtotal'] * $deposit / 100 * (1 + (float) $vat['rate'] / 100), 2),
            'milestones' => $milestones,
            'amounts' => Sows::amounts($milestones, Sows::contractTotal($sow)),
        ];
    }

    private static function data(StatementOfWork $sow, string $language): array
    {
        $date = fn ($at) => $at ? Carbon::parse($at)->setTimezone('Africa/Lagos')->locale($language)->translatedFormat(__('proposals.formats.date')) : null;

        return [
            'sow' => $sow,
            'language' => $language,
            'party' => Sows::partyName($sow),
            'organisation' => $sow->opportunity?->organization?->name ?? '',
            'sections' => array_map(fn ($s) => $s + ['blocks' => MeetingMinutesMail::blocks((string) ($s['body'] ?? ''))], (array) $sow->sections),
            'changes' => $sow->version > 1 && filled($sow->changes_summary) ? MeetingMinutesMail::blocks((string) $sow->changes_summary) : [],
            'fees' => self::fees($sow),
            'money' => fn ($amount) => ProposalPdf::money((float) $amount, (string) ($sow->proposal?->currency ?? ''), $language),
            'issued' => $date($sow->sent_at ?? $sow->approved_at ?? $sow->updated_at),
            'validUntil' => $date($sow->validUntil() ?? now()->addDays(max(1, (int) $sow->validity_days))),
            'hqSigned' => $date($sow->hq_signed_at),
            'clientSigned' => $date($sow->signed_at),
            'proposalRef' => $sow->proposal ? "{$sow->proposal->proposal_ref} · " . __('proposals.version', ['n' => $sow->proposal->version]) : null,
        ];
    }
}
