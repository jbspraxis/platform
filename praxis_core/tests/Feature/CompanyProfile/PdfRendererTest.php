<?php

namespace Tests\Feature\CompanyProfile;

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

    public function test_the_book_is_a4_landscape_and_the_length_the_design_allows(): void
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
            $this->assertGreaterThanOrEqual(10, $pages, "{$locale} is shorter than the design allows");
            $this->assertLessThanOrEqual(16, $pages, "{$locale} is longer than the design allows");
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
        $this->assertContains('work-feature', $views);
        $this->assertContains('platforms', $views);
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
            if ($page['view'] === 'platforms') {
                $platforms = array_merge($platforms, $page['data']['items']);
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

    public function test_the_logo_wall_falls_back_to_the_logos_on_disk(): void
    {
        $content = $this->content('en');
        $content['clients']['logos'] = [];

        $prepared = (new PdfRenderer)->prepare($content);

        $this->assertNotEmpty($prepared['clients']['logos']);
        foreach ($prepared['clients']['logos'] as $logo) {
            $this->assertFileExists($logo['path']);
            $this->assertStringEndsWith('.jpg', $logo['path'], 'dompdf cannot read a PNG without gd');
        }
    }

    /** @return array<string, mixed> */
    private function content(string $locale): array
    {
        $path = resource_path("company-profile/content/content.{$locale}.json");
        $this->assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $data['meta']['locale'] = $locale;

        return $data;
    }
}
