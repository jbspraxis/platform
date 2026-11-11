<?php

namespace App\Support\CompanyProfile;

use App\Support\Opportunities\ProposalPdf;
use Composer\InstalledVersions;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Throwable;

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
 *
 * A finished book is kept (kept()): a published version is downloaded from HQ and opened from share links
 * again and again, and each time it would otherwise be laid out anew, which takes one to three seconds.
 */
final class PdfRenderer implements CompanyProfileRenderer
{
    /** Small type at the foot of every page, in points from the top. */
    private const FOOTER_BASELINE = 560.0;

    private const FOOTER_SIZE = 6.6;

    /** Bump when the drawing changes in a way kept() cannot see, so books kept by the previous code are not served */
    private const CACHE_FORMAT = '1';

    /** Days a kept book stays after it was last served */
    private const CACHE_DAYS = 30;

    public function pdf(array $data): string
    {
        $prepared = $this->prepare($data);
        $pages = Layout::pages($prepared);
        $html = $this->render($prepared, $pages);
        $roots = Assets::chroot($prepared);

        return $this->kept($html, $this->footer($prepared), $this->footerPlacement($pages), $roots,
            fn () => $this->draw($prepared, $pages, $html, $roots));
    }

    /** The book itself: planned, checked against dompdf, and evened out (fit(), reflow(), balance()) */
    private function draw(array $prepared, array $pages, string $html, array $roots): string
    {
        $cache = $this->cache();

        // The book is rendered to find out whether the plan was right, and when it was - which is the
        // usual case, because Layout counts the pages from the same font metrics dompdf sets them with
        // - those are the bytes to send. Rendering it again to say the same thing is the difference
        // between a download that takes a second and one that takes three.
        $book = $this->book($prepared, $pages, $cache, $roots, $html);

        if ($book['pages'] === count($pages)) {
            $balanced = $this->balance($prepared, $pages, $cache, $roots);
            if ($balanced === $pages) {
                return $book['pdf'];
            }
            $pages = $balanced;
        } else {
            $pages = $this->balance($prepared, $this->reflow($prepared, $pages, $cache, $roots), $cache, $roots);
        }

        return $this->book($prepared, $pages, $cache, $roots)['pdf'];
    }

    /**
     * The whole book, and how many pages it came out at. One sheet sets as one page only if it fitted,
     * so the count is also the answer to whether the plan held.
     *
     * @param  list<array{view: string, surface: string, data: array<string, mixed>}>  $pages
     * @return array{pdf: string, pages: int}
     */
    private function book(array $prepared, array $pages, string $cache, array $roots, ?string $html = null): array
    {
        $pdf = $this->fromHtml(
            $html ?? $this->render($prepared, $pages),
            $this->footer($prepared),
            $this->footerPlacement($pages),
            $cache,
            $roots
        );

        // The page tree's own count, not the outline's, which is also written as /Count.
        preg_match('/\/Type \/Pages\b.*?\/Count (\d+)/s', $pdf, $match);

        return ['pdf' => $pdf, 'pages' => (int) ($match[1] ?? 0)];
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

        // Profile::data() fills the wall from the engine's logo library, which is where the wall comes
        // from. Content that has not been through it — a preview rendered straight off the seed files, a
        // test — asks the same library here, so there is one answer to what is on the wall.
        //
        // The library is a database table, and a preview run without one would otherwise fail on a page
        // of logos. That, and only that, falls back to the folder PR 1 read: a wall of the right shape
        // to look at, never what HQ would actually publish.
        if (($data['clients']['logos'] ?? []) === []) {
            try {
                $data['clients']['logos'] = app(ClientLogos::class)->all();
            } catch (Throwable $e) {
                $data['clients']['logos'] = (new DirectoryClientLogos)->all();
            }
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
        $roots = Assets::chroot($prepared);

        // One render of the whole book answers whether the plan held: it comes out at exactly as many
        // pages as were planned only if every sheet fitted. Checking sheet by sheet costs a render
        // each, so it is kept for the times the book disagrees.
        if ($this->book($prepared, $pages, $cache, $roots)['pages'] === count($pages)) {
            return $this->balance($prepared, $pages, $cache, $roots);
        }

        return $this->balance($prepared, $this->reflow($prepared, $pages, $cache, $roots), $cache, $roots);
    }

    /**
     * Sheet by sheet: a page that does not fit gives its last item to the page after it, until all of
     * them do.
     *
     * @param  list<array{view: string, surface: string, data: array<string, mixed>}>  $pages
     * @return list<array{view: string, surface: string, data: array<string, mixed>}>
     */
    private function reflow(array $prepared, array $pages, string $cache, array $roots): array
    {
        $checked = [];

        for ($pass = 0; $pass < 24; $pass++) {
            $moved = false;

            foreach ($pages as $index => $page) {
                // Keyed on what the page carries, not where it sits: moving an item shifts every later
                // page along, and a page that has already been proved to fit still fits at its new
                // number. Keying on the position would re-render the whole book after every move.
                $key = md5(serialize($page));
                if (($checked[$key] ?? false) === true) {
                    continue;
                }

                if ($this->fits($prepared, $page, $cache, $roots)) {
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
                $moved = true;
                break;
            }

            if (! $moved) {
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
    private function balance(array $prepared, array $pages, string $cache, array $roots = []): array
    {
        $before = $pages;
        $moved = false;

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

                if (! $this->fits($prepared, $shifted[$move], $cache, $roots)
                    || ! $this->fits($prepared, $shifted[$move + 1], $cache, $roots)) {
                    break;
                }

                $pages = $shifted;
                $moved = true;
            }
        }

        if (! $moved) {
            return $before;
        }

        return $this->book($prepared, $pages, $cache, $roots)['pages'] === count($pages) ? $pages : $before;
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
    private function fits(array $prepared, array $page, string $cache, array $roots = []): bool
    {
        $dompdf = new Dompdf($this->options($cache, $roots));
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
    public function fromHtml(string $html, string $footer, array $placement, string $cache, array $roots = []): string
    {
        $dompdf = new Dompdf($this->options($cache, $roots));

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

    /** @param  list<string>  $roots  the folders the pages read from, beyond dompdf's own cache */
    private function options(string $cache, array $roots = []): Options
    {
        return new Options([
            'defaultFont' => 'Jakarta',
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'landscape',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'fontDir' => $cache.'/fonts',
            'fontCache' => $cache.'/fonts',
            'tempDir' => $cache.'/tmp',
            'chroot' => array_values(array_unique(array_merge([$cache], $roots ?: [Assets::root()]))),
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

    /**
     * The book kept for exactly this content, or $draw's, kept for next time. The key is a hash of what the book is
     * drawn from: the planned pages' HTML (every word, every setting of the template, every file path), the footer
     * and where it goes, the folders dompdf may read, the size and date of every file the pages place (a logo
     * replaced under the same name), the renderer's own code (which moves items between pages after the plan) and
     * the dompdf version. A new version, a logo, an edit or a deploy draws it anew. The files sit beside the other
     * kept PDFs (ProposalPdf::cacheFolder), written to a temporary file then renamed so a reader never gets half a
     * book; failing to keep one only means it is drawn again next time.
     *
     * @param  array<int, array{colour: list<float>, y: float}>  $placement
     * @param  list<string>  $roots
     */
    private function kept(string $html, string $footer, array $placement, array $roots, \Closure $draw): string
    {
        $folder = ProposalPdf::cacheFolder();
        if ($folder === null) {
            return $draw();
        }

        $files = [];
        preg_match_all('/(?:src="|url\(\')([^"\']+)/', $html, $found);
        $code = array_map(fn ($class) => (new \ReflectionClass($class))->getFileName(), [self::class, Layout::class, Measure::class, Assets::class]);
        foreach (array_unique([...array_map('html_entity_decode', $found[1]), ...$code]) as $path) {
            if (is_file($path)) {
                $files[$path] = filesize($path) . ':' . filemtime($path);
            }
        }
        $key = hash('sha256', serialize([self::CACHE_FORMAT, self::dompdfVersion(), $html, $footer, $placement, $roots, $files]));
        $file = "{$folder}/profile-{$key}.pdf";

        if (is_file($file) && ($pdf = @file_get_contents($file)) !== false && str_starts_with($pdf, '%PDF-')) {
            @touch($file); // Still in use: pruning goes by the last time a file was served

            return $pdf;
        }

        $pdf = $draw();
        if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
            return $pdf;
        }
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($temporary, $pdf) !== strlen($pdf) || ! @rename($temporary, $file)) {
            @unlink($temporary);

            return $pdf;
        }

        // Pruned now and then, when a new one is kept: books not served for 30 days go
        $before = time() - self::CACHE_DAYS * 86400;
        foreach (glob("{$folder}/profile-*.pdf") ?: [] as $old) {
            if (($at = @filemtime($old)) !== false && $at < $before) {
                @unlink($old);
            }
        }

        return $pdf;
    }

    /** The installed dompdf (an update may set type differently); its file's date if Composer can't say */
    private static function dompdfVersion(): string
    {
        try {
            return (string) InstalledVersions::getReference('dompdf/dompdf') ?: (string) InstalledVersions::getVersion('dompdf/dompdf');
        } catch (\Throwable) {
            return (string) @filemtime((new \ReflectionClass(Dompdf::class))->getFileName());
        }
    }

    private function cache(): string
    {
        $cache = storage_path('framework/cache/dompdf');
        // The fonts dompdf registers and the files it works in are kept apart: sharing one folder made
        // it fail to open its own font file part way through a run of renders.
        File::ensureDirectoryExists($cache.'/fonts');
        File::ensureDirectoryExists($cache.'/tmp');

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
