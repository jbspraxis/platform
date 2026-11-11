<?php

namespace App\Support\Opportunities;

use App\Mail\MeetingMinutesMail;
use App\Models\Proposal;
use Composer\InstalledVersions;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Container\Container;
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
    /** Bump when draw() changes how a page is drawn, so PDFs kept by the previous code are not served again */
    private const CACHE_FORMAT = '1';

    /** Days a kept PDF stays after it was last served */
    private const CACHE_DAYS = 30;

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
     *
     * Every proposal, SOW and invoice PDF comes through here, so this is where they are kept: a PDF is stored under a
     * hash of exactly what is drawn (the HTML, the footer, the page label and the dompdf version), and the same
     * document asked for again is the stored file instead of a new dompdf render (90 to 600 ms each). Anything that
     * changes the page (a status, a payment, a signature, the language) changes the hash, so it is drawn anew.
     */
    public static function fromHtml(string $html, string $footer, string $pageLabel, string $cache): string
    {
        $stored = self::cachedFile($html, $footer, $pageLabel);
        if ($stored !== null && is_file($stored) && ($pdf = @file_get_contents($stored)) !== false && str_starts_with($pdf, '%PDF-')) {
            @touch($stored); // Still in use: pruning goes by the last time a file was served

            return $pdf;
        }

        $pdf = self::draw($html, $footer, $pageLabel, $cache);
        if ($stored !== null) {
            self::keep($stored, $pdf);
        }

        return $pdf;
    }

    /** The cached PDFs' folder: in storage/app, outside the private and public disks and the backups */
    public static function cacheFolder(): ?string
    {
        // Without Laravel (the second PHP of ProposalPdfWithoutExtensionsTest) nothing is kept
        return Container::getInstance()->bound('path.storage') ? storage_path('app/pdf-cache') : null;
    }

    private static function cachedFile(string $html, string $footer, string $pageLabel): ?string
    {
        $folder = self::cacheFolder();
        if ($folder === null) {
            return null;
        }
        // CACHE_FORMAT changes when the drawing below changes (options, footer layout), so older files are not reused
        $key = hash('sha256', implode("\0", [self::CACHE_FORMAT, self::dompdfVersion(), $footer, $pageLabel, $html]));

        return "{$folder}/{$key}.pdf";
    }

    /** The installed dompdf (an update may draw differently); its file's date if Composer can't say */
    private static function dompdfVersion(): string
    {
        try {
            return (string) InstalledVersions::getReference('dompdf/dompdf') ?: (string) InstalledVersions::getVersion('dompdf/dompdf');
        } catch (\Throwable) {
            return (string) @filemtime((new \ReflectionClass(Dompdf::class))->getFileName());
        }
    }

    /** Written to a temporary file then renamed, so a reader never gets half a PDF; a failure only skips the cache */
    private static function keep(string $file, string $pdf): void
    {
        $folder = dirname($file);
        if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
            return;
        }
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($temporary, $pdf) !== strlen($pdf) || ! @rename($temporary, $file)) {
            @unlink($temporary);

            return;
        }

        // Pruned now and then, when a new PDF is kept: files not served for 30 days go (no scheduler needed)
        $before = time() - self::CACHE_DAYS * 86400;
        foreach (glob("{$folder}/*") ?: [] as $old) {
            if (is_file($old) && ($at = @filemtime($old)) !== false && $at < $before) {
                @unlink($old);
            }
        }
    }

    private static function draw(string $html, string $footer, string $pageLabel, string $cache): string
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
        $vat = Proposals::vat($proposal);

        return [
            'proposal' => $proposal,
            'language' => $language,
            'organisation' => $proposal->opportunity?->organization?->name ?? '',
            'sections' => array_map(fn ($s) => $s + ['blocks' => MeetingMinutesMail::blocks((string) ($s['body'] ?? ''))], (array) $proposal->sections),
            'changes' => $proposal->version > 1 && filled($proposal->changes_summary) ? MeetingMinutesMail::blocks((string) $proposal->changes_summary) : [],
            'lines' => $lines,
            'vat' => $vat,
            'totals' => Proposals::totals($lines, (float) $vat['rate']),
            'money' => fn ($amount) => self::money((float) $amount, (string) $proposal->currency, $language),
            'issued' => $date($proposal->sent_at ?? $proposal->approved_at ?? $proposal->updated_at),
            'validUntil' => $date($proposal->validUntil() ?? now()->addDays(max(1, (int) $proposal->validity_days))),
            'schedule' => Lang::has('proposals.schedules.' . $proposal->payment_schedule_type) ? __('proposals.schedules.' . $proposal->payment_schedule_type) : null,
        ];
    }
}
