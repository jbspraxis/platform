<?php

namespace App\Support\CompanyProfile;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * The company profile as a PDF: A4 landscape, ten to twelve pages, one template for English and French
 * (docs/company-profile/DESIGN.md).
 *
 * dompdf renders it, as the letterhead and the proposal do, because it is pure PHP and the live server
 * has no Chrome and no gd (docs/OPPORTUNITIES.md section 12). Two things follow from the missing gd and
 * shape the whole design: no page may carry a CSS background image, and no page may place a PNG, so the
 * imagery and the logos are JPEGs placed as <img>, cut to their exact shape when they were built
 * (App\Support\CompanyProfile\Assets). The two brand families are embedded as static TTF cuts; dompdf
 * writes a registered @font-face into its font directory, so `fontDir` has to be writable, and a font or
 * an image is only found at an absolute path, never at one relative to the document.
 *
 * Page breaks are not left to dompdf. App\Support\CompanyProfile\Layout cuts the content into pages
 * first, and each page is one fixed-height <div>, so no section ever splits across a break and French,
 * which runs 15 to 20 per cent longer than English, changes how many pages the open sets take and
 * nothing else.
 *
 * The reference and the version are drawn on every page by a page script rather than by the page, which
 * is the only way to be certain they are on all of them.
 */
final class PdfRenderer implements CompanyProfileRenderer
{
    /** Small type at the foot of every page, in points from the top. */
    private const FOOTER_BASELINE = 560.0;

    private const FOOTER_SIZE = 6.6;

    public function pdf(array $data): string
    {
        $prepared = $this->prepare($data);

        $pages = $this->fit($prepared, Layout::pages($prepared));

        return $this->fromHtml(
            $this->render($prepared, $pages),
            $this->footer($prepared),
            $this->footerPlacement($pages),
            $this->cache()
        );
    }

    /**
     * What the pages are actually set from: every string prepared for the page, and the logo wall filled
     * in from disk when the content carries none. The page list is counted from this and not from the raw
     * content, or the page script would mark the wrong page as Ink.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data): array
    {
        $data = Typography::prepare($data, (string) ($data['meta']['locale'] ?? 'en'));

        // Profile::data() fills the wall from the engine's logo library. Content that has not been
        // through it — a preview straight off the seed, or a test — gets the same library here, so
        // there is one answer to what is on the wall and PR 3 changes it in one place.
        if (($data['clients']['logos'] ?? []) === []) {
            $data['clients']['logos'] = app(ClientLogos::class)->all();
        }

        return $data;
    }

    /**
     * The whole book as HTML, from the content as it comes out of the engine, so a test or the preview
     * command can read the markup without rendering a PDF.
     *
     * @param  array<string, mixed>  $data
     */
    public function html(array $data): string
    {
        $prepared = $this->prepare($data);

        return $this->render($prepared, $this->fit($prepared, Layout::pages($prepared)));
    }

    /**
     * @param  array<string, mixed>  $prepared  content prepare() has been over
     * @param  list<array{view: string, surface: string, data: array<string, mixed>}>  $pages
     */
    private function render(array $prepared, array $pages): string
    {
        $locale = (string) ($prepared['meta']['locale'] ?? 'en');

        return view('pdf.company-profile', [
            'profile' => $prepared,
            'locale' => $locale,
            'pages' => $pages,
            'words' => self::words($locale),
        ])->render();
    }

    /**
     * The page plan, made exact. Layout counts the open sets onto pages from the font metrics, which is
     * close but not the same thing as laying them out: a page can still come out a line too long, and
     * dompdf would then push its last block onto a page of its own, leaving a short page and a nearly
     * empty one. So each page is test-rendered on its own, and a page that does not fit gives its last
     * item to the page after it until every one of them does.
     *
     * Each check costs a render of a single page, and the fonts are already in the cache by the second
     * one, so the whole pass is a fraction of a second.
     *
     * @param  list<array{view: string, surface: string, data: array<string, mixed>}>  $pages
     * @return list<array{view: string, surface: string, data: array<string, mixed>}>
     */
    public function fit(array $prepared, array $pages): array
    {
        $cache = $this->cache();
        $checked = [];

        for ($pass = 0; $pass < 24; $pass++) {
            $moved = false;

            foreach ($pages as $index => $page) {
                $key = $index.':'.md5(serialize($page));
                if (($checked[$key] ?? false) === true) {
                    continue;
                }

                if ($this->fits($prepared, $page, $cache)) {
                    $checked[$key] = true;

                    continue;
                }

                $shrunk = Layout::shrink($pages, $index);
                if ($shrunk === null) {
                    // Nothing left to move: a page the design fixes is too full for its own copy, which
                    // is a thing to change in the content or the template, not something to hide here.
                    $checked[$key] = true;

                    continue;
                }

                $pages = $shrunk;
                $checked = [];
                $moved = true;
                break;
            }

            if (! $moved) {
                $pages = $this->balance($prepared, $pages, $cache);

                break;
            }
        }

        return array_values($pages);
    }

    /**
     * Even out a section that runs over several pages. Fitting fills each page to the brim, which can
     * leave the last one holding a single problem beside a page of eight: correct, and it reads as
     * running out rather than as a designed ending. So wherever a page carries more than the page after
     * it can account for, it hands one item along, and the two pages it touched are re-rendered to be
     * sure they both still fit. The moment one does not, the section keeps the last arrangement that
     * worked.
     *
     * @param  list<array{view: string, surface: string, data: array<string, mixed>}>  $pages
     * @return list<array{view: string, surface: string, data: array<string, mixed>}>
     */
    private function balance(array $prepared, array $pages, string $cache): array
    {
        foreach ($this->runs($pages) as [$from, $to]) {
            for ($pass = 0; $pass < 12; $pass++) {
                $move = null;
                for ($index = $from; $index < $to; $index++) {
                    if ($this->itemCount($pages[$index]) > $this->itemCount($pages[$index + 1]) + 1) {
                        $move = $index;

                        break;
                    }
                }

                if ($move === null) {
                    break;
                }

                $shifted = Layout::shrink($pages, $move);
                if ($shifted === null || count($shifted) !== count($pages)) {
                    break;
                }

                if (! $this->fits($prepared, $shifted[$move], $cache)
                    || ! $this->fits($prepared, $shifted[$move + 1], $cache)) {
                    break;
                }

                $pages = $shifted;
            }
        }

        return $pages;
    }

    /**
     * The runs of consecutive pages that carry one section between them and can pass items along.
     *
     * @return list<array{int, int}>
     */
    private function runs(array $pages): array
    {
        $runs = [];
        $start = null;

        foreach ($pages as $index => $page) {
            $same = $start !== null && $pages[$start]['view'] === $page['view'];
            if ($same) {
                continue;
            }
            if ($start !== null && $index - 1 > $start) {
                $runs[] = [$start, $index - 1];
            }
            $start = in_array($page['view'], ['problems', 'teams', 'clients', 'work-grid'], true) ? $index : null;
        }

        if ($start !== null && count($pages) - 1 > $start) {
            $runs[] = [$start, count($pages) - 1];
        }

        return $runs;
    }

    /** How many things a page carries, whatever kind of page it is. */
    private function itemCount(array $page): int
    {
        $data = $page['data'] ?? [];

        if (isset($data['groups'])) {
            return array_sum(array_map(fn ($group) => count($group['items'] ?? []), $data['groups']));
        }

        return count($data['items'] ?? $data['rows'] ?? []);
    }

    /** True when this one page sets on one page. */
    private function fits(array $prepared, array $page, string $cache): bool
    {
        $dompdf = new Dompdf($this->options($cache));
        $dompdf->loadHtml($this->render($prepared, [$page]), 'UTF-8');
        $dompdf->setPaper([0, 0, Layout::PAGE_WIDTH, Layout::PAGE_HEIGHT]);
        $dompdf->render();

        return $dompdf->getCanvas()->get_page_count() <= 1;
    }

    /**
     * dompdf's part, which needs nothing from Laravel: also what the test on a PHP without the live
     * server's missing extensions renders. $cache is the one writable folder dompdf gets, for the fonts
     * it registers and its temporary files; the pages read only resources/company-profile.
     *
     * @param  array<int, array{colour: list<float>, y: float}>  $placement  where and in what ink the
     *                                                                       footer goes, on the pages
     *                                                                       where Muted on Paper at the
     *                                                                       foot would not do
     */
    public function fromHtml(string $html, string $footer, array $placement, string $cache): string
    {
        $dompdf = new Dompdf($this->options($cache));

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, Layout::PAGE_WIDTH, Layout::PAGE_HEIGHT]);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (int $page, int $pages, CPDF $pdf, FontMetrics $metrics) use ($footer, $placement) {
            $font = $metrics->getFont('Jakarta', 'normal') ?: $metrics->getFont('DejaVu Sans');
            $colour = $placement[$page]['colour'] ?? [0.369, 0.400, 0.475];   // Muted #5E6679 on Paper
            $y = $placement[$page]['y'] ?? self::FOOTER_BASELINE;
            $label = $page.' / '.$pages;

            $pdf->text(Layout::MARGIN_X, $y, $footer, $font, self::FOOTER_SIZE, $colour);
            $pdf->text(
                Layout::PAGE_WIDTH - Layout::MARGIN_X - $metrics->getTextWidth($label, $font, self::FOOTER_SIZE),
                $y,
                $label,
                $font,
                self::FOOTER_SIZE,
                $colour
            );
        });

        return $dompdf->output();
    }

    private function options(string $cache): Options
    {
        return new Options([
            'defaultFont' => 'Jakarta',
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'landscape',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'fontDir' => $cache,
            'fontCache' => $cache,
            'tempDir' => $cache,
            'chroot' => [$cache, Assets::root()],
        ]);
    }

    /** The line every page carries: who it is from, which version it is, and the reference. */
    public function footer(array $data): string
    {
        $meta = $data['meta'] ?? [];
        $parts = array_filter([
            'JBS Praxis',
            ($locale = (string) ($meta['locale'] ?? 'en')) === 'fr' ? 'Profil d’entreprise' : 'Company profile',
            isset($meta['version']) ? (string) $meta['version'] : null,
            isset($meta['reference']) ? (string) $meta['reference'] : null,
        ]);

        return implode('   ·   ', $parts);
    }

    /**
     * Where the reference goes on the pages where Muted on Paper at the foot would not do: quiet grey on
     * the Ink page, and white on a page a photograph bleeds across. Keyed the way dompdf numbers pages.
     *
     * @param  list<array{view: string, surface: string, data: array<string, mixed>}>  $pages
     * @return array<int, array{colour: list<float>, y: float}>
     */
    public function footerPlacement(array $pages): array
    {
        $placement = [];
        foreach ($pages as $index => $page) {
            $number = $index + 1;
            $footer = $page['footer'] ?? '';

            if ($footer === 'light' || ($page['surface'] ?? '') === 'bleed') {
                $placement[$number] = ['colour' => [1.0, 1.0, 1.0], 'y' => self::FOOTER_BASELINE];
            } elseif (($page['surface'] ?? '') === 'ink') {
                $placement[$number] = ['colour' => [0.604, 0.631, 0.698], 'y' => self::FOOTER_BASELINE];
            }
        }

        return $placement;
    }

    private function cache(): string
    {
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        return str_replace(DIRECTORY_SEPARATOR, '/', realpath($cache) ?: $cache);
    }

    /**
     * The handful of words the template says itself, rather than the content. They are kept here and not
     * in a language file so the renderer has no key of its own to keep in step with the engine's.
     *
     * @return array<string, string>
     */
    public static function words(string $locale): array
    {
        $words = [
            'en' => [
                'continued' => 'continued',
                'problem' => 'The problem',
                'built' => 'What we built',
                'outcome' => 'The outcome',
                'capabilities' => 'What this covers',
                'disciplines' => 'Disciplines',
                'experience' => 'Experience',
                'credentials' => 'Credentials held',
                'published' => 'Published',
                'speak' => 'Let us look at one part of your organisation.',
                // The strip of platforms has no copy of its own in the contract, so this line lives
                // here until the content carries it. It is the lead chat's to rewrite.
                'platforms' => 'The platforms, as their teams see them',
            ],
            'fr' => [
                'continued' => 'suite',
                'problem' => 'Le problème',
                'built' => 'Ce que nous avons construit',
                'outcome' => 'Le résultat',
                'capabilities' => 'Ce que cela couvre',
                'disciplines' => 'Disciplines',
                'experience' => 'Expérience',
                'credentials' => 'Certifications détenues',
                'published' => 'Publié le',
                'speak' => 'Examinons ensemble une partie de votre organisation.',
                'platforms' => 'Les plateformes, telles que leurs équipes les voient',
            ],
        ];

        return $words[$locale] ?? $words['en'];
    }
}
