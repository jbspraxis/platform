<?php

namespace Tests\Feature\CompanyProfile;

use App\Support\CompanyProfile\ClientLogos;
use App\Support\CompanyProfile\Deck\Blueprint;
use App\Support\CompanyProfile\Deck\DeckRenderer;
use App\Support\CompanyProfile\Deck\Fit;
use App\Support\CompanyProfile\Deck\Metrics;
use App\Support\CompanyProfile\Deck\Style;
use App\Support\CompanyProfile\Deck\Zip;
use App\Support\CompanyProfile\Typography;
use Tests\TestCase;

/**
 * The company profile's deck: that it is a .pptx a machine with no ext-zip can write and a reader can
 * open, that it is twenty-four slides in both languages, that it really animates, and above all that the
 * rules in company-profile/CONTRACT.md hold in the file itself and not only in the editor.
 *
 * A credential the owner has not switched on, and a fact the owner has switched off, must not be in the
 * bytes a client can open, search or copy out — the same bar PdfRendererTest holds the book to.
 *
 * The renderer needs no database, so these tests do not rebuild one. They also never call ZipArchive: the
 * live web PHP has no ext-zip, and neither does the PHP this was built on, so a test that leant on it
 * would be testing a machine the deck will never run on.
 */
class DeckRendererTest extends TestCase
{
    /** @var list<string> scratch files a test wrote, taken away afterwards */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Metrics::forget();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        $this->files = [];

        parent::tearDown();
    }

    public function test_it_writes_a_pptx_that_opens_in_both_languages(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $pptx = (new DeckRenderer)->pptx($this->content($locale));

            $this->assertStringStartsWith("PK\x03\x04", $pptx, "{$locale} is not a zip");

            $entries = Zip::entries($pptx);

            $this->assertArrayHasKey('[Content_Types].xml', $entries, "{$locale} has no content types");
            $this->assertArrayHasKey('_rels/.rels', $entries, "{$locale} has no package relationships");
            $this->assertArrayHasKey('ppt/presentation.xml', $entries, "{$locale} has no presentation");
            $this->assertArrayHasKey('ppt/theme/theme1.xml', $entries, "{$locale} has no theme");
            $this->assertArrayHasKey('ppt/slideMasters/slideMaster1.xml', $entries, "{$locale} has no master");
        }
    }

    public function test_the_deck_is_twenty_four_slides_and_every_part_is_well_formed_xml(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $entries = Zip::entries((new DeckRenderer)->pptx($this->content($locale)));

            $slides = array_values(array_filter(
                array_keys($entries),
                fn ($name) => (bool) preg_match('#^ppt/slides/slide\d+\.xml$#', $name),
            ));
            sort($slides);

            $this->assertCount(Blueprint::SLIDES, $slides, "{$locale} is not twenty-four slides");
            for ($i = 1; $i <= Blueprint::SLIDES; $i++) {
                $this->assertArrayHasKey("ppt/slides/slide{$i}.xml", $entries, "{$locale} is missing slide {$i}");
                $this->assertArrayHasKey("ppt/slides/_rels/slide{$i}.xml.rels", $entries, "{$locale} slide {$i} has no relationships");
            }

            foreach ($entries as $name => $body) {
                if (! str_ends_with($name, '.xml') && ! str_ends_with($name, '.rels')) {
                    continue;
                }
                $this->assertTrue($this->isWellFormed($body), "{$locale} {$name} is not well-formed XML");
            }
        }
    }

    public function test_the_slides_are_sixteen_by_nine_and_every_one_is_declared(): void
    {
        $entries = Zip::entries((new DeckRenderer)->pptx($this->content('en')));
        $presentation = $entries['ppt/presentation.xml'];

        $this->assertStringContainsString(
            '<p:sldSz cx="12192000" cy="6858000"/>',
            $presentation,
            'the slide is not 16:9 at 960 x 540pt',
        );
        $this->assertSame(Blueprint::SLIDES, substr_count($presentation, '<p:sldId '), 'the presentation lists a different number of slides');
        $this->assertSame(
            Blueprint::SLIDES,
            substr_count($entries['[Content_Types].xml'], 'presentationml.slide+xml'),
            'the content types list a different number of slides',
        );

        // Every relationship a slide names is a relationship its own .rels file declares
        for ($i = 1; $i <= Blueprint::SLIDES; $i++) {
            preg_match_all('/r:embed="(rId\d+)"/', $entries["ppt/slides/slide{$i}.xml"], $used);
            foreach (array_unique($used[1]) as $rel) {
                $this->assertStringContainsString(
                    'Id="'.$rel.'"',
                    $entries["ppt/slides/_rels/slide{$i}.xml.rels"],
                    "slide {$i} uses {$rel} and does not declare it",
                );
            }
        }
    }

    public function test_every_slide_moves_and_the_section_slides_morph(): void
    {
        $entries = Zip::entries((new DeckRenderer)->pptx($this->content('en')));

        $animated = 0;
        $morphs = 0;
        for ($i = 1; $i <= Blueprint::SLIDES; $i++) {
            $xml = $entries["ppt/slides/slide{$i}.xml"];

            if (str_contains($xml, '<p:timing>')) {
                $animated++;
                $this->assertStringContainsString('presetClass="entr"', $xml, "slide {$i} has timing but no entrance");
                // "After previous" rather than "on click": the slide builds itself when it arrives
                $this->assertStringContainsString('nodeType="afterEffect"', $xml, "slide {$i} waits for a click");
                $this->assertStringContainsString('<p:cond evt="onBegin" delay="0"><p:tn val="2"/></p:cond>', $xml, "slide {$i} never starts");
            }

            if (str_contains($xml, 'p159:morph')) {
                $morphs++;
                // Morph is a 2016 feature: a reader that has never heard of it must still get a transition
                $this->assertStringContainsString('<mc:Fallback>', $xml, "slide {$i} morphs with no fallback");
                $this->assertStringContainsString('<p:fade/>', $xml, "slide {$i} has no fade under its morph");
            }

            if ($i > 1) {
                $this->assertStringContainsString('<p:transition ', $xml, "slide {$i} arrives with no transition");
            }
        }

        $this->assertSame(Blueprint::SLIDES, $animated, 'a slide carries no animation at all');
        $this->assertGreaterThanOrEqual(5, $morphs, 'the sections do not morph into their own content');
    }

    public function test_a_credential_that_is_not_held_never_reaches_a_slide(): void
    {
        $content = $this->content('en');
        $content['teams']['items'][0]['credentials'] = [
            ['name' => 'Held Credential Alpha', 'issuer' => 'Issuer One', 'held' => true],
            ['name' => 'Hidden Credential Beta', 'issuer' => 'Issuer Two', 'held' => false],
            ['name' => 'Hidden Credential Gamma', 'issuer' => 'Issuer Three'],
        ];

        $text = $this->slideText($content);

        $this->assertStringContainsString('Held Credential Alpha', $text);
        $this->assertStringNotContainsString('Hidden Credential Beta', $text);
        $this->assertStringNotContainsString('Hidden Credential Gamma', $text);
    }

    public function test_a_fact_that_is_switched_off_never_reaches_a_slide(): void
    {
        $content = $this->content('en');
        $content['facts']['items'] = [
            ['label' => 'Shown Fact', 'value' => 'Visible Value', 'enabled' => true],
            ['label' => 'Hidden Fact', 'value' => 'Secret Value', 'enabled' => false],
            ['label' => 'Unset Fact', 'value' => 'Unset Value'],
        ];

        $text = $this->slideText($content);

        $this->assertStringContainsString('Visible Value', $text);
        $this->assertStringNotContainsString('Secret Value', $text);
        $this->assertStringNotContainsString('Unset Value', $text);
    }

    public function test_the_cameroon_entity_is_off_in_the_approved_content_and_is_not_in_the_deck(): void
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
        $this->assertStringNotContainsString($cameroon['value'], $this->slideText($content));
    }

    public function test_the_deck_names_nobody_and_says_neither_squad_nor_deployed(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $text = mb_strtolower($this->slideText($this->content($locale)));

            foreach (['squad', 'deployed', 'escouade', 'déployé'] as $banned) {
                $this->assertStringNotContainsString($banned, $text, "{$locale} says \"{$banned}\"");
            }

            // The teams are presented by discipline. The owner and the Head of Sales are the people HQ knows
            // by name, and neither of them is in a client's hands.
            foreach (['Gerald', 'Nwoke', 'Asaah'] as $person) {
                $this->assertStringNotContainsString(mb_strtolower($person), $text, "{$locale} names a person");
            }
        }
    }

    public function test_the_pharmaceutical_client_is_never_named_and_no_price_is_quoted(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $text = $this->slideText($this->content($locale));

            $this->assertStringNotContainsString('Fidson', $text, "{$locale} names the pharmaceutical client");

            // No price anywhere: not a naira figure, not a currency word, not a per-month line
            $this->assertDoesNotMatchRegularExpression('/[₦$€£]\s?\d/u', $text, "{$locale} quotes a price");
            $this->assertDoesNotMatchRegularExpression('/\bNGN\b|\bUSD\b|\bFCFA\b/u', $text, "{$locale} quotes a currency");
        }
    }

    public function test_every_slide_fits_in_both_languages_without_shrinking_the_type(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $content = Typography::prepare($this->content($locale), $locale);
            $slides = (new Blueprint($content, $locale))->slides();

            $this->assertCount(Blueprint::SLIDES, $slides, "{$locale} is not twenty-four slides");
            $this->assertSame([], Fit::problems($slides), "{$locale} overflows: ".Fit::describe(Fit::problems($slides)));

            // Nothing is ever rescued by autofit, so a slide that fits is a slide that was designed to
            $entries = Zip::entries((new DeckRenderer)->pptx($this->content($locale)));
            for ($i = 1; $i <= Blueprint::SLIDES; $i++) {
                $this->assertStringNotContainsString('normAutofit', $entries["ppt/slides/slide{$i}.xml"], "{$locale} slide {$i} shrinks its own type");
                $this->assertStringContainsString('<a:noAutofit/>', $entries["ppt/slides/slide{$i}.xml"], "{$locale} slide {$i} lets the reader resize it");
            }
        }
    }

    public function test_no_reading_text_is_set_below_the_floor(): void
    {
        foreach (Style::READING_ROLES as $role) {
            $this->assertGreaterThanOrEqual(
                Style::FLOOR,
                Style::type($role)['size'],
                "the {$role} role is set below the floor",
            );
        }

        foreach (['caption', 'meta'] as $role) {
            $this->assertGreaterThanOrEqual(Style::CHROME_FLOOR, Style::type($role)['size'], "the {$role} role is below every floor");
        }
    }

    public function test_the_deck_is_set_in_faces_every_machine_already_has(): void
    {
        $entries = Zip::entries((new DeckRenderer)->pptx($this->content('en')));

        // No font is embedded, on purpose: the deck is designed to Georgia and Arial metrics, which
        // Windows, macOS and Google Slides all have, so it looks the same everywhere with nothing to load.
        $this->assertStringNotContainsString('embeddedFontLst', $entries['ppt/presentation.xml']);
        $this->assertStringContainsString('<a:latin typeface="Georgia"/>', $entries['ppt/theme/theme1.xml']);
        $this->assertStringContainsString('<a:latin typeface="Arial"/>', $entries['ppt/theme/theme1.xml']);

        for ($i = 1; $i <= Blueprint::SLIDES; $i++) {
            preg_match_all('/<a:latin typeface="([^"]+)"\/>/', $entries["ppt/slides/slide{$i}.xml"], $fonts);
            foreach (array_unique($fonts[1]) as $font) {
                $this->assertContains($font, [Style::SERIF, Style::SANS], "slide {$i} asks for {$font}");
            }
        }
    }

    public function test_the_same_profile_renders_to_the_same_bytes_twice(): void
    {
        $renderer = new DeckRenderer;

        $this->assertSame(
            md5($renderer->pptx($this->content('en'))),
            md5($renderer->pptx($this->content('en'))),
            'the deck is not deterministic, so two downloads of one version differ',
        );
    }

    public function test_french_spacing_never_asks_for_a_glyph_the_deck_has_no_width_for(): void
    {
        $text = $this->slideText($this->content('fr'));

        // Georgia has no narrow no-break space; Typography turns it into one the face does have
        $this->assertStringNotContainsString("\u{202F}", $text);
        $this->assertStringNotContainsString("\u{2009}", $text);
        $this->assertStringContainsString(Typography::NBSP.':', $text);
    }

    public function test_the_wall_draws_the_librarys_logos_when_there_are_some(): void
    {
        $this->wallFromTheContract();

        // A wall of real marks at the proportions logos really arrive at: a wide wordmark, a square mark,
        // a tall one, and the rest in between.
        $shapes = [
            'Smart Logistics and Services' => [620, 120],
            'Pixie Girl Global' => [420, 130],
            'La Petite Cour' => [360, 180],
            'NFELIA' => [300, 300],
            'Orika Living' => [400, 110],
            'OK Veggies' => [240, 400],
        ];

        $logos = [];
        foreach ($shapes as $name => [$width, $height]) {
            $logos[] = [
                'name' => $name,
                'path' => $this->pngFile($width, $height),
                'png' => $this->pngFile($width, $height),
                'kind' => 'png',
                'key' => \Illuminate\Support\Str::slug($name),
                'sector' => null,
            ];
        }

        $content = $this->content('en');
        $content['clients']['logos'] = $logos;

        $entries = Zip::entries((new DeckRenderer)->pptx($content));
        $slide = $entries['ppt/slides/slide22.xml'];
        $rels = $entries['ppt/slides/_rels/slide22.xml.rels'];

        // One picture a logo, each with a part of its own and a relationship that resolves
        $this->assertSame(count($logos), substr_count($slide, '<p:pic>'), 'the wall is not drawing the logos');
        $this->assertSame(count($logos), substr_count($rels, 'relationships/image'), 'a logo has no relationship');
        foreach (array_keys($logos) as $i) {
            $this->assertArrayHasKey('ppt/media/slide22-'.($i + 2).'.png', $entries, 'a logo has no part in the package');
        }

        // The names are not drawn as well: a mark replaces its name, it does not sit beside it
        $this->assertStringNotContainsString('Pixie Girl Global', $slide, 'the wall draws a name over a mark');

        // Equal area, not equal height: that is what stops a wide wordmark shouting and a square mark
        // whispering. The marks here run from 5.2:1 to 0.6:1 and are given in that order.
        preg_match_all('#<p:pic>.*?<a:ext cx="(\d+)" cy="(\d+)"/>.*?</p:pic>#s', $slide, $boxes, PREG_SET_ORDER);
        $this->assertCount(count($logos), $boxes, 'a logo was not drawn');

        $matched = 0;
        foreach ($boxes as $box) {
            $width = (int) $box[1] / 12700;
            $height = (int) $box[2] / 12700;

            // Nothing breaks its cell or the band the row gives it
            $this->assertLessThanOrEqual(Style::even(5) + 0.01, $width, 'a logo is wider than its cell');
            $this->assertLessThanOrEqual(52.01, $height, 'a logo is taller than its band');

            if (abs(($width * $height) - 2400) <= 48) {
                $matched++;
            }
        }

        // Every mark but the tall one lands on the same area; the tall one is held to the band's height,
        // which is the clamp doing its job rather than the rule failing
        $this->assertSame(count($logos) - 1, $matched, 'the wall is not matched on area');
        $this->assertEqualsWithDelta(52.0, (int) $boxes[count($logos) - 1][2] / 12700, 0.01, 'the tall mark was not held to the band');
    }

    public function test_the_wall_falls_back_to_the_names_the_profile_itself_uses(): void
    {
        $this->wallFromTheContract();

        $content = $this->content('en');
        $content['clients']['logos'] = [];

        $entries = Zip::entries((new DeckRenderer)->pptx($content));
        $slide = $entries['ppt/slides/slide22.xml'];

        $this->assertStringNotContainsString('<p:pic>', $slide, 'the wall placed a picture it does not have');

        // The clients the profile names in its own work, named exactly as it names them
        $named = array_values(array_unique(array_filter(array_map(
            fn ($item) => trim((string) ($item['client'] ?? '')),
            (array) $content['work']['items'],
        ))));
        $this->assertNotEmpty($named);
        foreach ($named as $client) {
            $this->assertStringContainsString(
                htmlspecialchars(Typography::text($client, 'en'), ENT_QUOTES | ENT_XML1, 'UTF-8'),
                $slide,
                "the wall does not name {$client}",
            );
        }
    }

    public function test_a_logo_the_library_has_no_png_for_is_left_off_rather_than_broken(): void
    {
        $this->wallFromTheContract();

        $content = $this->content('en');
        $content['clients']['logos'] = [
            ['name' => 'Has A Mark', 'path' => $this->pngFile(400, 120), 'png' => $this->pngFile(400, 120)],
            // PowerPoint places an SVG only beside a raster copy, and nothing on the server can make one
            ['name' => 'Svg Only', 'path' => $this->svgFile(), 'png' => null],
        ];

        $slide = Zip::entries((new DeckRenderer)->pptx($content))['ppt/slides/slide22.xml'];

        $this->assertSame(1, substr_count($slide, '<p:pic>'), 'the SVG was placed as something it is not');
        $this->assertStringContainsString('Svg Only', $slide, 'the logo with no mark lost its name as well');
    }

    /**
     * Put a provider that is not the HQ library in front of the renderer.
     *
     * DeckRenderer asks the library for its own deck set and falls back to the contract when the provider
     * is something else. These tests are about what the wall does with a given set of logos, not about
     * which set it is given, so they hand it one and take the library out of the way — otherwise the
     * answer would depend on whether the run has a database behind it.
     */
    private function wallFromTheContract(): void
    {
        $this->app->bind(ClientLogos::class, fn () => new class implements ClientLogos
        {
            public function all(): array
            {
                return [];
            }
        });
    }

    /** A real PNG of a given size, written to the scratch disk: enough bytes for Deck\Images to read it. */
    private function pngFile(int $width, int $height): string
    {
        $path = sys_get_temp_dir().'/deck-logo-'.$width.'x'.$height.'.png';
        if (is_file($path)) {
            return $path;
        }

        $chunk = function (string $type, string $body): string {
            return pack('N', strlen($body)).$type.$body.pack('N', crc32($type.$body));
        };

        // 8-bit greyscale, one filter byte a row, which is the smallest PNG that still states a size
        $raw = str_repeat("\x00".str_repeat("\xFF", $width), $height);
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0))
            .$chunk('IDAT', (string) gzcompress($raw, 9))
            .$chunk('IEND', '');

        file_put_contents($path, $png);
        $this->files[] = $path;

        return $path;
    }

    private function svgFile(): string
    {
        $path = sys_get_temp_dir().'/deck-logo.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 40"><rect width="100" height="40"/></svg>');
        $this->files[] = $path;

        return $path;
    }

    /** Every word the deck draws, in reading order. */
    private function slideText(array $content): string
    {
        $entries = Zip::entries((new DeckRenderer)->pptx($content));

        $text = '';
        for ($i = 1; $i <= Blueprint::SLIDES; $i++) {
            preg_match_all('#<a:t>(.*?)</a:t>#s', $entries["ppt/slides/slide{$i}.xml"], $runs);
            foreach ($runs[1] as $run) {
                $text .= html_entity_decode($run, ENT_QUOTES | ENT_XML1, 'UTF-8')."\n";
            }
        }

        return $text;
    }

    private function isWellFormed(string $xml): bool
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new \DOMDocument;
        $ok = $document->loadXML($xml);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok;
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
