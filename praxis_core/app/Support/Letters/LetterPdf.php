<?php

namespace App\Support\Letters;

use App\Models\Letters\LetterDocument;
use Carbon\CarbonImmutable;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * The letterhead as a PDF (docs/DOCUMENTS.md section 3), rendered by dompdf, as the proposal and the SOW are
 * (App\Support\Opportunities\ProposalPdf). Pure PHP: it needs only dom, mbstring and iconv, which the live PHP
 * has. The logo and the QR code are SVG, which php-svg-lib draws; a signature is a JPEG, which the PDF carries
 * as it is. Nothing needs gd, which the live PHP has not got, and no PNG is ever placed on a page.
 *
 * The footer is drawn on every page from the company's own details, with "Page 2 of 3" on anything longer than
 * one page, over a fine rule in brand teal.
 */
final class LetterPdf
{
    /** A4 in points, and the text column between the left and right margins */
    private const LEFT = 56.0;

    private const RIGHT = 539.0;

    public static function render(LetterDocument $document, bool $final = false): string
    {
        $company = Letterhead::company($document->legal_entity_id) ?? Letterhead::defaultCompany() ?? [];
        $details = Letterhead::details($company);
        $html = self::html($document, $details, $final);

        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        return self::fromHtml($html, $details['footer_legal'], $details['footer_contact'], $cache);
    }

    /** The page's HTML on its own, so a test can look at it without rendering a PDF */
    public static function html(LetterDocument $document, array $details, bool $final = false): string
    {
        $document->loadMissing('signatures');
        $verifyUrl = $document->verify_token ? Verification::url($document->verify_token) : null;

        return view('pdf.letter', [
            'document' => $document,
            'details' => $details,
            'blocks' => LetterBody::blocks($document, $details),
            'signatures' => $document->signatures,
            'logo' => Letterhead::logoDataUri(),
            'issuedDate' => CarbonImmutable::parse($document->document_date)->format('j F Y'),
            'final' => $final,
            'qr' => $document->show_qr && $verifyUrl ? Qr::dataUri($verifyUrl, 22) : null,
            'verifyUrl' => $verifyUrl,
            'blankSignatory' => [
                'name' => $details['representative'] ?: 'For and on behalf of ' . $details['name'],
                'title' => $details['representative_title'] ?: 'Director',
            ],
        ])->render();
    }

    /**
     * dompdf's part, which needs nothing from Laravel: also what the test on a PHP without the live server's
     * missing extensions runs. $cache is a writable folder for fonts and temporary files, and the only folder
     * dompdf may read; the page reads none, because the logo, the QR code and the signatures are data URIs.
     */
    public static function fromHtml(string $html, string $footerLegal, string $footerContact, string $cache): string
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

        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (int $page, int $pages, CPDF $pdf, FontMetrics $metrics) use ($footerLegal, $footerContact) {
            $font = $metrics->getFont('DejaVu Sans');
            $teal = [0.047, 0.545, 0.608];   // #0C8B9B
            $grey = [0.369, 0.400, 0.475];   // #5E6679

            $pdf->line(self::LEFT, 776, self::RIGHT, 776, $teal, 0.7);
            $pdf->text(self::LEFT, 787, $footerLegal, $font, 7, $grey);
            $pdf->text(self::LEFT, 798, $footerContact, $font, 7, $grey);

            if ($pages > 1) {
                $label = 'Page ' . $page . ' of ' . $pages;
                $pdf->text(self::RIGHT - $metrics->getTextWidth($label, $font, 7), 798, $label, $font, 7, $grey);
            }
        });

        return $dompdf->output();
    }

    /** A blank letterhead, for someone who would rather type the document themselves */
    public static function blank(array $company): string
    {
        $details = Letterhead::details($company);
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        $html = view('pdf.letterhead-blank', [
            'details' => $details,
            'logo' => Letterhead::logoDataUri(),
        ])->render();

        return self::fromHtml($html, $details['footer_legal'], $details['footer_contact'], $cache);
    }
}
