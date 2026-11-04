<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Redesign PR 3 (Nov 2026): proof and conversion. The logo strip, quotes and results that appear only once approved,
 * the place images, the calculator in the journey and the primary button.
 */
class ProofTest extends TestCase
{
    public function test_the_home_page_shows_every_logo_the_owner_listed_once_to_screen_readers(): void
    {
        $this->fakeHq();
        $clients = config('site.clients');
        $this->assertGreaterThanOrEqual(15, count($clients));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Organisations we have worked with', $html);
        foreach ($clients as $client) {
            // Once with its name (the first copy), once silent (the copy that closes the loop); a logo whose file
            // isn't there yet is left out entirely
            $expected = is_file(public_path('img/clients/' . $client['file'] . '.webp')) ? 1 : 0;
            $this->assertSame($expected, substr_count($html, 'alt="' . e($client['name']) . '"'), $client['name']);
        }
        $this->assertStringContainsString('alt="OK Veggies"', $html);
        $this->assertSame(1, preg_match_all('#<ul class="clients-list"\s+aria-hidden="true"\s*>#', $html));
        // La Petite Cour is named (owner, 4 Nov 2026); Horizon Segment stays described
        $this->assertStringContainsString('alt="La Petite Cour"', $html);
        $this->assertStringContainsString('alt="An oil and gas services company"', $html);
    }

    public function test_quotes_appear_only_once_approved(): void
    {
        $this->fakeHq();

        $this->get('/')->assertOk()->assertDontSee('quotes-title', false)->assertDontSee('Timothee Massomba');

        config(['site.quotes_approved' => ['smart-logistics', 'la-petite-cour']]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('In their words', $html);
        $this->assertStringContainsString('<span class="quote-name">Timothee Massomba</span>', $html);
        $this->assertStringContainsString('Managing Director, Smart Logistics and Services', $html);
        $this->assertStringContainsString('<span class="quote-name">Elombo T.</span>', $html);
        $this->assertStringContainsString('Chief Executive, La Petite Cour', $html);

        $this->get('/fr')->assertOk()->assertSee('Ils en parlent')->assertSee('Directeur général, Smart Logistics and Services');
    }

    public function test_results_appear_only_for_confirmed_figures_in_the_page_language(): void
    {
        $this->fakeHq();

        $this->get('/work/smart-logistics')->assertOk()->assertDontSee('results-title', false);

        config(['site.results.smart-logistics' => [['value' => '24', 'label' => ['en' => 'months live', 'fr' => 'mois en service']]]]);
        $this->get('/work/smart-logistics')->assertOk()
            ->assertSee('In figures')->assertSee('<dd class="result-value">24</dd>', false)->assertSee('months live')
            ->assertSee('Figures confirmed by the client.');
        $this->get('/fr/work/smart-logistics')->assertOk()->assertSee('mois en service')->assertDontSee('months live');
        $this->get('/work/mycity-app')->assertOk()->assertDontSee('results-title', false);
    }

    public function test_the_primary_button_is_book_a_free_audit_with_the_line_under_the_final_one(): void
    {
        $this->fakeHq();

        $this->get('/')->assertOk()
            ->assertSee('Book a free audit')->assertDontSee('Book a free audit and demo')
            ->assertSee('See it on your operations: every free audit ends with a demo on one area of your organisation.');
        $this->get('/fr')->assertOk()
            ->assertSee('Réserver un audit gratuit')->assertDontSee('Réserver un audit gratuit et une démo')
            ->assertSee("Voyez-le sur vos propres opérations\u{202F}:", false);
    }

    public function test_the_calculator_is_a_step_on_the_home_page_and_every_case_study(): void
    {
        $this->fakeHq();

        foreach (['/', '/work/smart-logistics', '/fr/work/pixie-girl-global'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('class="calculator-steps"', $html, $path);
            $this->assertSame(3, substr_count($html, '<li>' . "\n" . '                    <h3>'), $path);
        }
        $this->get('/')->assertSee('It counts three costs');
    }

    public function test_place_images_on_industries_and_company_with_their_descriptions(): void
    {
        $this->fakeHq();

        $industries = $this->get('/industries')->assertOk()->getContent();
        foreach (['logistics', 'consumer-goods', 'pharmaceuticals', 'oil-and-gas'] as $image) {
            $this->assertStringContainsString(asset("img/place/{$image}-1280.webp"), $industries);
        }
        $this->assertStringContainsString('alt="A container port in the early morning', $industries);

        $company = $this->get('/company')->assertOk()->getContent();
        $this->assertStringContainsString('Two regional desks', $company);
        $this->assertStringContainsString(asset('img/place/lagos-1280.webp'), $company);
        $this->assertStringContainsString(asset('img/place/douala-1280.webp'), $company);

        $this->get('/fr/company')->assertOk()->assertSee('Deux bureaux régionaux');
    }

    public function test_our_products_present_praxis_ls_and_nfelia_each_on_its_own(): void
    {
        $this->fakeHq();

        $company = $this->get('/company')->assertOk()->getContent();
        $this->assertStringContainsString('id="products"', $company);
        $this->assertStringContainsString('<h3 class="product-name">Praxis LS</h3>', $company);
        $this->assertStringContainsString('<h3 class="product-name">NFELIA</h3>', $company);
        $this->assertStringContainsString('SYSCOHADA accounting at its core', $company);
        // What is built is said plainly; what is being built is said to be in build
        $this->assertStringContainsString('The version for many operators is now in build.', $company);
        $this->assertStringContainsString('href="' . route('work.show', ['slug' => 'smart-logistics']) . '"', $company);

        $this->get('/fr/company')->assertOk()->assertSee('Notre ERP logistique');
        $this->get('/work')->assertOk()->assertSee('NFELIA and Praxis LS are ours')
            ->assertSee('href="' . route('company') . '#products"', false);
    }

    public function test_every_client_logo_has_a_transparent_background(): void
    {
        // WebP with an alpha channel: an extended header (VP8X) with its alpha flag set
        foreach (glob(public_path('img/clients/*.webp')) as $file) {
            $head = (string) file_get_contents($file, false, null, 0, 21);
            $this->assertSame('VP8X', substr($head, 12, 4), basename($file) . ' has no extended header');
            $this->assertNotSame(0, ord($head[20]) & 0x10, basename($file) . ' has no alpha');
        }
    }

    public function test_ask_praxis_has_a_close_button_in_its_corner(): void
    {
        $this->fakeHq();

        $this->get('/company')->assertOk()
            ->assertSee('<button class="chat-close" type="button" data-chat-close hidden aria-label="Close Ask Praxis" title="Close Ask Praxis">', false);
        $this->get('/fr/company')->assertOk()->assertSee('aria-label="Fermer Demander à Praxis"', false);
    }

    public function test_the_header_is_sticky_and_the_pages_keep_the_strict_csp(): void
    {
        $this->fakeHq();

        foreach (['/', '/industries', '/company', '/work/smart-logistics'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=|<style\b/i', $html, $path);
        }
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.site-header { position: sticky; top: 0;', $css);
    }
}
