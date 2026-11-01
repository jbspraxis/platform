<?php

namespace App\Support\Opportunities;

use App\Mail\MeetingMinutesMail;
use App\Models\Proposal;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * A version of the proposal as the PDF attached to the client's email and offered in the portal (owner decision 8),
 * in the opportunity's language. dompdf (pure PHP: needs only dom, mbstring and iconv, which the live PHP has; no
 * gd, imagick, intl or fileinfo) renders resources/views/pdf/proposal.blade.php with its bundled DejaVu Sans, which
 * covers French typography. Nothing remote is fetched and no PHP in the page runs.
 */
final class ProposalPdf
{
    public static function render(Proposal $proposal): string
    {
        [$html, $footer, $pageLabel] = self::page($proposal);
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        return self::fromHtml($html, $footer, $pageLabel, $cache);
    }

    /**
     * The page's HTML, its footer and the "Page :n of :of" label, in the opportunity's language
     *
     * @return array{string, string, string}
     */
    public static function page(Proposal $proposal): array
    {
        $proposal->loadMissing('opportunity.organization');
        $language = $proposal->opportunity?->language === 'fr' ? 'fr' : 'en';
        $previous = App::getLocale();
        App::setLocale($language);
        try {
            return [
                view('pdf.proposal', self::data($proposal, $language))->render(),
                __('proposals.pdf.footer', ['ref' => $proposal->proposal_ref, 'version' => $proposal->version]),
                __('proposals.pdf.page'),
            ];
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * dompdf's part, which needs nothing from Laravel: also what the test on a PHP without the live server's missing
     * extensions runs (ProposalPdfWithoutExtensionsTest). $cache is a writable folder for fonts and temporary files;
     * it is also the only folder dompdf may read (chroot), and the page reads none.
     */
    public static function fromHtml(string $html, string $footer, string $pageLabel, string $cache): string
    {
        $dompdf = new Dompdf(new Options([
            'defaultFont' => 'DejaVu Sans',
            'defaultPaperSize' => 'a4',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'fontCache' => $cache,
            'tempDir' => $cache,
            'chroot' => [$cache],
        ]));
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        // Footer on every page: the reference on the left, "Page 2 of 5" on the right
        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (int $page, int $pages, CPDF $pdf, FontMetrics $metrics) use ($footer, $pageLabel) {
            $font = $metrics->getFont('DejaVu Sans');
            $grey = [0.369, 0.4, 0.475];
            $pdf->text(56, 806, $footer, $font, 7.5, $grey);
            $text = str_replace([':n', ':of'], [$page, $pages], $pageLabel);
            $pdf->text(539 - $metrics->getTextWidth($text, $font, 7.5), 806, $text, $font, 7.5, $grey);
        });

        return $dompdf->output();
    }

    /** "PRP-20261101-482 v2 - Finance system.pdf" */
    public static function filename(Proposal $proposal): string
    {
        $title = trim(preg_replace('/[^A-Za-z0-9 ._-]+/', '', Str::ascii((string) $proposal->title))) ?: 'Proposal';

        return Str::limit("{$proposal->proposal_ref} v{$proposal->version} - {$title}", 120, '') . '.pdf';
    }

    /** Amounts as the opportunity's language writes them: "USD 12,500.00" or "12 500,00 USD" */
    public static function money(float $amount, string $currency, string $language): string
    {
        return $language === 'fr'
            ? number_format($amount, 2, ',', "\u{202F}") . "\u{00A0}" . $currency
            : $currency . "\u{00A0}" . number_format($amount, 2);
    }

    private static function data(Proposal $proposal, string $language): array
    {
        $date = fn ($at) => $at ? Carbon::parse($at)->setTimezone('Africa/Lagos')->locale($language)->translatedFormat(__('proposals.formats.date')) : null;
        $lines = Proposals::lines($proposal);

        return [
            'proposal' => $proposal,
            'language' => $language,
            'organisation' => $proposal->opportunity?->organization?->name ?? '',
            'sections' => array_map(fn ($s) => $s + ['blocks' => MeetingMinutesMail::blocks((string) ($s['body'] ?? ''))], (array) $proposal->sections),
            'changes' => $proposal->version > 1 && filled($proposal->changes_summary) ? MeetingMinutesMail::blocks((string) $proposal->changes_summary) : [],
            'lines' => $lines,
            'totals' => Proposals::totals($lines, (float) $proposal->vat_rate),
            'money' => fn ($amount) => self::money((float) $amount, (string) $proposal->currency, $language),
            'issued' => $date($proposal->sent_at ?? $proposal->approved_at ?? $proposal->updated_at),
            'validUntil' => $date($proposal->validUntil() ?? now()->addDays(max(1, (int) $proposal->validity_days))),
            'schedule' => Lang::has('proposals.schedules.' . $proposal->payment_schedule_type) ? __('proposals.schedules.' . $proposal->payment_schedule_type) : null,
        ];
    }
}
