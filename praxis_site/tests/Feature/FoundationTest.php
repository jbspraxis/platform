<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Redesign PR 1 (Nov 2026): breadcrumbs, font preloads, the hero loop's loading and the shared components. */
class FoundationTest extends TestCase
{
    /** The page's JSON-LD graph, decoded. */
    private function graph(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m));

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR)['@graph'];
    }

    private function breadcrumbs(string $html): ?array
    {
        foreach ($this->graph($html) as $node) {
            if ($node['@type'] === 'BreadcrumbList') {
                return array_map(fn ($item) => [$item['position'], $item['name'], $item['item']], $node['itemListElement']);
            }
        }

        return null;
    }

    public function test_a_case_study_has_home_work_and_itself_as_breadcrumbs_in_both_languages(): void
    {
        $this->fakeHq();

        $crumbs = $this->breadcrumbs($this->get('/work/smart-logistics')->assertOk()->getContent());
        $this->assertSame([1, 'Home', url('/')], $crumbs[0]);
        $this->assertSame([2, 'Work', url('/work')], $crumbs[1]);
        $this->assertSame(3, $crumbs[2][0]);
        $this->assertSame(url('/work/smart-logistics'), $crumbs[2][2]);
        $this->assertStringNotContainsString(' | ', $crumbs[2][1]);
        $this->assertStringNotContainsString('&', $crumbs[2][1]); // decoded, not HTML entities

        $fr = $this->breadcrumbs($this->get('/fr/work/smart-logistics')->assertOk()->getContent());
        $this->assertSame([1, 'Accueil', url('/fr')], $fr[0]);
        $this->assertSame([2, 'Réalisations', url('/fr/work')], $fr[1]);
    }

    public function test_a_top_level_page_has_two_breadcrumbs_and_home_has_none(): void
    {
        $this->fakeHq();

        $crumbs = $this->breadcrumbs($this->get('/company')->assertOk()->getContent());
        $this->assertCount(2, $crumbs);
        $this->assertSame(url('/company'), $crumbs[1][2]);
        $this->assertNull($this->breadcrumbs($this->get('/')->assertOk()->getContent()));
    }

    public function test_no_font_is_preloaded(): void
    {
        // Lighthouse (4 Nov 2026): preloading 160 KB of fonts delayed the largest element on a phone by 0.7 s.
        // The metric-matched fallbacks keep the text still without them.
        $this->fakeHq();

        $this->get('/industries')->assertOk()->assertDontSee('as="font"', false);
        $this->get('/')->assertOk()->assertDontSee('as="font"', false);
    }

    public function test_the_picture_component_offers_avif_and_webp_at_three_widths_with_a_fixed_size(): void
    {
        $html = Blade::render('<x-picture name="logistics" alt="A container port" sizes="50vw" />');

        foreach (['avif', 'webp'] as $ext) {
            foreach ([640, 1280, 2400] as $w) {
                $this->assertStringContainsString(asset("img/place/logistics-{$w}.{$ext}") . " {$w}w", $html);
                $this->assertFileExists(public_path("img/place/logistics-{$w}.{$ext}"));
            }
        }
        $this->assertStringContainsString('width="2400" height="1600"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('alt="A container port"', $html);

        $hero = Blade::render('<x-picture name="lagos" alt="Lagos" :priority="true" />');
        $this->assertStringContainsString('fetchpriority="high"', $hero);
        $this->assertStringNotContainsString('loading="lazy"', $hero);
    }

    public function test_every_place_image_exists_in_both_formats_and_all_widths(): void
    {
        foreach (['pharmaceuticals', 'consumer-goods', 'oil-and-gas', 'logistics', 'lagos', 'douala'] as $name) {
            foreach (['avif', 'webp'] as $ext) {
                foreach ([640, 1280, 2400] as $w) {
                    $this->assertFileExists(public_path("img/place/{$name}-{$w}.{$ext}"));
                }
            }
        }
    }
}
