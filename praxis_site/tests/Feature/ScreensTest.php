<?php

namespace Tests\Feature;

use App\Support\Work;
use Tests\TestCase;

/** The platforms' own sign-in screens (4 Nov 2026): on each case study that has one, and as previews on the Work page. */
class ScreensTest extends TestCase
{
    private const WITH_SCREENS = ['smart-logistics', 'mycity-app', 'pixie-girl-global', 'orika-living', 'fmcg-distributor-cameroon', 'nfelia'];

    /** Our own platforms, captured signed out (no tenant, no names): HQ and Praxis LS have no case study */
    private const OWN = ['jbs-praxis-hq', 'praxis-ls'];

    public function test_every_screen_exists_in_both_formats_and_all_widths(): void
    {
        foreach ([...self::WITH_SCREENS, ...self::OWN] as $slug) {
            foreach (['avif', 'webp'] as $ext) {
                foreach ([800, 1280, 1920] as $w) {
                    $this->assertFileExists(public_path("img/screens/{$slug}-{$w}.{$ext}"));
                }
            }
        }
    }

    public function test_a_case_study_with_a_screen_shows_it_framed_under_its_title_in_both_languages(): void
    {
        $this->fakeHq();

        foreach (self::WITH_SCREENS as $slug) {
            $name = __('work.cases.' . $slug . '.name');
            $html = $this->get('/work/' . $slug)->assertOk()->getContent();
            $this->assertStringContainsString(asset("img/screens/{$slug}-1280.webp"), $html, $slug);
            $this->assertStringContainsString('width="1920" height="960"', $html, $slug);
            $this->assertStringContainsString('alt="' . e(__('work.screen.alt', ['name' => $name])) . '"', $html, $slug);
        }
        $this->get('/fr/work/mycity-app')->assertOk()->assertSee('L’écran de connexion du système de MyCity App');
        // No screen, no band
        $this->get('/work/pharmaceutical-manufacturer')->assertOk()->assertDontSee('case-screen', false);
    }

    public function test_the_work_page_previews_every_platform_and_links_to_its_case_study(): void
    {
        $this->fakeHq();

        $html = $this->get('/work')->assertOk()->getContent();
        $this->assertStringContainsString('The platforms we run', $html);
        foreach (self::WITH_SCREENS as $slug) {
            $this->assertStringContainsString('<a href="' . route('work.show', ['slug' => $slug]) . '">' . "\n" . '                                <span class="platform-frame">', $html, $slug);
        }
        $this->assertSame(count(self::WITH_SCREENS), substr_count($html, '<li class="platform">'));
        $this->get('/fr/work')->assertOk()->assertSee('Les plateformes que nous faisons tourner');
    }

    public function test_company_shows_hq_and_each_product_by_its_sign_in_screen(): void
    {
        $this->fakeHq();

        $html = $this->get('/company')->assertOk()->getContent();
        $this->assertStringContainsString(asset('img/screens/jbs-praxis-hq-1280.webp'), $html);
        $this->assertStringContainsString('alt="The sign-in screen of JBS Praxis HQ."', $html);
        $this->assertStringContainsString(asset('img/screens/praxis-ls-1280.webp'), $html);
        $this->assertStringContainsString(asset('img/screens/nfelia-1280.webp'), $html);
        $this->assertStringContainsString(asset('img/clients/praxis-ls.webp'), $html); // the product card's logo
        $this->get('/fr/company')->assertOk()->assertSee('L’écran de connexion de JBS Praxis HQ.');
        $this->get('/')->assertOk()->assertSee('alt="Praxis LS"', false); // the logo strip
    }

    public function test_la_petite_cour_is_named_on_its_case_study_and_the_work_pages(): void
    {
        $this->fakeHq();

        $this->get('/work/fmcg-distributor-cameroon')->assertOk()
            ->assertSee('<h1 class="page-h1">', false)->assertSee('La Petite Cour, an FMCG distributor in Cameroon, runs its orders');
        $this->get('/fr/work/fmcg-distributor-cameroon')->assertOk()->assertSee('La Petite Cour, distributeur de biens de consommation au Cameroun');
        $this->get('/industries')->assertOk()->assertSee('La Petite Cour');
        $this->assertContains('fmcg-distributor-cameroon', array_keys(Work::CASES));
    }
}
