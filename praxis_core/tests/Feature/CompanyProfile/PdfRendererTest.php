<?php

namespace Tests\Feature\CompanyProfile;

use App\Support\CompanyProfile\Assets;
use App\Support\CompanyProfile\ClientLogos;
use App\Support\CompanyProfile\CompanyProfileRenderer;
use App\Support\CompanyProfile\Layout;
use App\Support\CompanyProfile\Measure;
use App\Support\CompanyProfile\PdfRenderer;
use App\Support\CompanyProfile\Typography;
use Tests\TestCase;

/**
 * The company profile's PDF: that it renders in both languages, that it is the length the design asks
 * for, and above all that the two rules in the contract hold in the file itself and not only in the
 * editor. A credential the owner has not switched on, and a fact the owner has switched off, must not be
 * in the bytes a client can read, search or copy out.
 *
 * The renderer needs no database, so these tests do not rebuild one.
 */
class PdfRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Measure::forget();
    }

    public function test_it_renders_a_pdf_for_both_languages(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $pdf = (new PdfRenderer)->pdf($this->content($locale));

            $this->assertStringStartsWith('%PDF-', $pdf, "{$locale} is not a PDF");
            $this->assertStringContainsString('%%EOF', substr($pdf, -1024), "{$locale} has no end-of-file marker");
            $this->assertGreaterThan(100_000, strlen($pdf), "{$locale} is too small to carry its imagery");
        }
    }

    public function test_the_renderer_satisfies_the_interface_the_engine_asks_for(): void
    {
        $this->assertInstanceOf(CompanyProfileRenderer::class, new PdfRenderer);
    }

    public function test_the_book_is_a4_landscape_and_the_length_the_owner_asked_for(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $pdf = (new PdfRenderer)->pdf($this->content($locale));

            // A4 landscape in points, as the page box is written into the file.
            preg_match('/\/MediaBox \[([\d. ]+)\]/', $pdf, $box);
            $media = array_map('floatval', preg_split('/ +/', trim($box[1] ?? '')) ?: []);
            $this->assertSame([0.0, 0.0], array_slice($media, 0, 2), "{$locale} has an odd page origin");
            $this->assertEqualsWithDelta(Layout::PAGE_WIDTH, $media[2] ?? 0, 0.01, "{$locale} is not A4 wide");
            $this->assertEqualsWithDelta(Layout::PAGE_HEIGHT, $media[3] ?? 0, 0.01, "{$locale} is not A4 landscape");

            // The page tree's own count, not the outline's, which is also written as /Count.
            preg_match('/\/Type \/Pages\b.*?\/Count (\d+)/s', $pdf, $match);
            $pages = (int) ($match[1] ?? 0);
            // The owner's brief: ten to twelve pages, in both languages.
            $this->assertGreaterThanOrEqual(10, $pages, "{$locale} is shorter than the design allows");
            $this->assertLessThanOrEqual(12, $pages, "{$locale} is longer than the owner asked for");
        }
    }

    public function test_a_credential_that_is_not_held_never_reaches_the_page(): void
    {
        $content = $this->content('en');
        $content['teams']['items'][0]['credentials'] = [
            ['name' => 'Held Credential Alpha', 'issuer' => 'Issuer One', 'held' => true],
            ['name' => 'Hidden Credential Beta', 'issuer' => 'Issuer Two', 'held' => false],
            ['name' => 'Hidden Credential Gamma', 'issuer' => 'Issuer Three'],
        ];

        $html = (new PdfRenderer)->html($content);

        $this->assertStringContainsString('Held Credential Alpha', $html);
        $this->assertStringNotContainsString('Hidden Credential Beta', $html);
        $this->assertStringNotContainsString('Hidden Credential Gamma', $html);
    }

    public function test_a_fact_that_is_switched_off_never_reaches_the_page(): void
    {
        $content = $this->content('en');
        $content['facts']['items'] = [
            ['label' => 'Shown Fact', 'value' => 'Visible Value', 'enabled' => true],
            ['label' => 'Hidden Fact', 'value' => 'Secret Value', 'enabled' => false],
            ['label' => 'Unset Fact', 'value' => 'Unset Value'],
        ];

        $html = (new PdfRenderer)->html($content);

        $this->assertStringContainsString('Visible Value', $html);
        $this->assertStringNotContainsString('Secret Value', $html);
        $this->assertStringNotContainsString('Unset Value', $html);
    }

    public function test_the_cameroon_entity_is_off_in_the_approved_content_and_is_not_in_the_file(): void
    {
        $content = $this->content('en');
        $cameroon = null;
        foreach ($content['facts']['items'] as $fact) {
            if (str_contains(mb_strtolower((string) $fact['label']), 'cameroon')) {
                $cameroon = $fact;
            }
        }

        $this->assertNotNull($cameroon, 'the approved content no longer carries the Cameroon entity fact');
        $this->assertFalse($cameroon['enabled'], 'the Cameroon entity must default to off');
        $this->assertStringNotContainsString($cameroon['value'], (new PdfRenderer)->html($content));
    }

    public function test_every_page_carries_the_reference_and_the_version(): void
    {
        $content = $this->content('en');
        $renderer = new PdfRenderer;
        $pages = $renderer->fit($renderer->prepare($content), Layout::pages($renderer->prepare($content)));

        $footer = $renderer->footer($content);
        $this->assertStringContainsString($content['meta']['reference'], $footer);
        $this->assertStringContainsString($content['meta']['version'], $footer);

        // The footer is drawn by a page script, which runs once per page, so every page carries it.
        $this->assertGreaterThanOrEqual(10, count($pages));
    }

    public function test_the_page_plan_puts_the_cover_first_and_the_back_cover_last(): void
    {
        $renderer = new PdfRenderer;
        $pages = Layout::pages($renderer->prepare($this->content('en')));
        $views = array_column($pages, 'view');

        $this->assertSame('cover', $views[0]);
        $this->assertSame('back', $views[count($views) - 1]);
        $this->assertContains('problems', $views);
        $this->assertContains('work-grid', $views);
        $this->assertContains('clients', $views);
        $this->assertContains('facts', $views);
    }

    public function test_the_platforms_page_shows_only_the_clients_whose_system_we_can_show(): void
    {
        $renderer = new PdfRenderer;
        $content = $this->content('en');
        $pages = Layout::pages($renderer->prepare($content));

        $platforms = [];
        foreach ($pages as $page) {
            if ($page['view'] === 'clients') {
                $platforms = array_merge($platforms, $page['data']['platforms'] ?? []);
            }
        }

        $this->assertNotEmpty($platforms, 'no platform has a screen of its own');
        foreach ($platforms as $item) {
            $this->assertFileExists($item['screen']);
            $this->assertStringEndsWith('.jpg', $item['screen'], 'dompdf cannot read a PNG without gd');
        }

        // A client the copy describes rather than names has no screen to show, and must not be there.
        $clients = array_column($platforms, 'client');
        $this->assertNotContains('A leading pharmaceutical manufacturer', $clients);
        $this->assertNotContains('An oil and gas company', $clients);
    }

    public function test_french_spacing_never_leaves_a_glyph_the_brand_fonts_do_not_have(): void
    {
        // The brand writes a narrow no-break space before a colon; neither family has that glyph, so it
        // must have become a no-break space before it reaches a page.
        $prepared = (new PdfRenderer)->prepare($this->content('fr'));
        $flat = json_encode($prepared, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString("\u{202F}", (string) $flat);
        $this->assertStringNotContainsString("\u{2009}", (string) $flat);
        $this->assertStringContainsString(Typography::NBSP.':', (string) $flat);
    }

    public function test_the_logo_wall_comes_from_the_engines_logo_library(): void
    {
        $content = $this->content('en');
        $content['clients']['logos'] = [];

        $prepared = (new PdfRenderer)->prepare($content);

        $this->assertNotEmpty($prepared['clients']['logos']);
        $this->assertSame(app(ClientLogos::class)->all(), $prepared['clients']['logos']);
    }

    public function test_the_wall_places_only_what_dompdf_can_draw(): void
    {
        // Without gd dompdf cannot read a PNG at all, so one on the wall must be left off rather than
        // drawn as a broken image. The library lists it; the page does not place it.
        $content = $this->content('en');
        $content['clients']['logos'] = [
            ['name' => 'Drawable', 'path' => Assets::path('logos', 'tales-consulting.jpg')],
            ['name' => 'Not drawable', 'path' => Assets::path('brand', 'horizontal-colour.jpg')],
            ['name' => 'A png', 'path' => resource_path('brand/jbs-praxis-stacked-colour-1200.png')],
        ];

        $pages = Layout::pages((new PdfRenderer)->prepare($content));
        $names = [];
        foreach ($pages as $page) {
            if ($page['view'] === 'clients') {
                foreach ($page['data']['rows'] as $row) {
                    foreach ($row as $logo) {
                        $names[] = $logo['name'];
                    }
                }
            }
        }

        $this->assertContains('Drawable', $names);
        $this->assertNotContains('A png', $names);
    }

    public function test_the_designed_renderer_is_what_the_engine_resolves(): void
    {
        $this->assertInstanceOf(PdfRenderer::class, app(CompanyProfileRenderer::class));
    }

    public function test_the_wall_draws_an_svg_and_a_jpeg_from_a_library_outside_the_resources_folder(): void
    {
        // PR 3's logo library keeps its files in its own storage, not in resources/company-profile/logos.
        // dompdf refuses anything outside its chroot, so a logo from there would print blank unless the
        // renderer puts the library's folder on the list. This is that, with both formats it may hold.
        $library = sys_get_temp_dir().'/jbs-logo-library-'.bin2hex(random_bytes(4));
        mkdir($library, 0777, true);

        $svg = $library.'/one-mark.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 60">'
            .'<rect x="0" y="0" width="240" height="60" fill="#0C142B"/>'
            .'<rect x="12" y="12" width="96" height="36" fill="#0BB8C4"/></svg>');

        $jpeg = $library.'/other-mark.jpg';
        copy(Assets::path('logos', 'tales-consulting.jpg'), $jpeg);

        $content = $this->content('en');
        $content['clients']['logos'] = [
            ['name' => 'One Mark', 'path' => str_replace(DIRECTORY_SEPARATOR, '/', $svg)],
            ['name' => 'Other Mark', 'path' => str_replace(DIRECTORY_SEPARATOR, '/', $jpeg)],
        ];

        $renderer = new PdfRenderer;
        $prepared = $renderer->prepare($content);

        // Both are on the wall, and the SVG is sized from its viewBox rather than skipped.
        $wall = [];
        foreach (Layout::pages($prepared) as $page) {
            if ($page['view'] === 'clients') {
                foreach ($page['data']['rows'] as $row) {
                    foreach ($row as $logo) {
                        $wall[] = $logo['name'];
                    }
                }
            }
        }
        $this->assertSame(['One Mark', 'Other Mark'], $wall);

        $box = Assets::logoBox($svg);
        $this->assertNotNull($box, 'the SVG has no size the wall could use');
        $this->assertEqualsWithDelta(4.0, $box['width'] / $box['height'], 0.01, 'the viewBox ratio is 4:1');

        $this->assertContains(
            str_replace(DIRECTORY_SEPARATOR, '/', (string) realpath($library)),
            Assets::chroot($prepared),
            'the library folder is not readable by dompdf'
        );

        // And the page really draws them: the teal of the SVG and the JPEG both reach the file.
        $pdf = $renderer->pdf($content);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf, 'the JPEG was not placed');
        $this->assertGreaterThan(1, substr_count($pdf, '/Subtype /Image'), 'only one image reached the file');

        unlink($svg);
        unlink($jpeg);
        rmdir($library);
    }

    /** @return array<string, mixed> */
    private function content(string $locale): array
    {
        $path = database_path("seeders/company-profile/content.{$locale}.json");
        $this->assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $data['meta']['locale'] = $locale;

        return $data;
    }
}
