<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Redesign PR 2 (Nov 2026): the hero moment, Ask Praxis and the motion system. Motion only stages what the page
 * already holds, so every final value must be in the HTML; the cinematic layer must never load from the HTML.
 */
class MotionTest extends TestCase
{
    public function test_the_hero_holds_the_whole_answer_from_the_demo_company_in_both_languages(): void
    {
        $this->fakeHq();

        $en = $this->get('/')->assertOk();
        $en->assertSee('data-answer', false)
            ->assertSee('Demo data')->assertSee('Ndali Group')
            ->assertSee('Which projects are over budget?')
            // The sign and the digits apart: the sign is set in the sans (no 85 KB font file for one glyph)
            ->assertSee('<span class="answer-currency">₦</span><span data-settle>38,204,600</span>', false)
            ->assertSee('over budget, across 4 of 8 projects')
            ->assertSee('Ibadan cold room')->assertSee('109.0%')->assertSee('Apapa depot expansion')->assertSee('104.8%')
            ->assertSee('Sources: 8 project budgets and their cost lines to 31 October 2026')
            ->assertDontSee('<video', false);

        $fr = $this->get('/fr')->assertOk();
        $fr->assertSee('Données de démo')
            ->assertSee("Quels projets dépassent leur budget\u{202F}?")
            ->assertSee("<span data-settle>38\u{202F}204\u{202F}600</span><span class=\"answer-currency\">₦</span>", false)
            ->assertSee("109,0\u{202F}%");
    }

    public function test_the_hero_question_box_is_a_real_form_to_ask_praxis(): void
    {
        $this->fakeHq();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<form class="answer-ask" action="' . preg_quote(route('chat'), '#') . '" method="POST" data-hero-ask>#', $html);
        $this->assertMatchesRegularExpression('#<form class="answer-ask".*?name="_token".*?id="hp-hero-chat".*?name="chat_message".*?</form>#s', $html);
        $this->assertStringContainsString('<label class="sr-only" for="hero-ask">Ask Praxis a question about JBS Praxis</label>', $html);
        // One honeypot id per form on the page
        $this->assertSame(1, substr_count($html, 'id="hp-hero-chat"'));
        $this->assertSame(1, substr_count($html, 'id="hp-chat"'));
    }

    public function test_ask_praxis_is_a_conversation_with_a_composer(): void
    {
        $this->fakeHq();

        $this->get('/company')->assertOk()
            ->assertSee('<span>Ask Praxis</span>', false)
            ->assertSee('data-typing="Praxis AI is writing"', false)
            ->assertSee('class="chat-panel" data-lenis-prevent', false)
            ->assertSee('class="chat-composer"', false)
            ->assertSee('Enter to send, Shift and Enter for a new line');
        $this->get('/fr/company')->assertOk()->assertSee('<span>Demander à Praxis</span>', false);
    }

    public function test_the_home_page_keeps_the_strict_csp_with_its_chapters(): void
    {
        $this->fakeHq();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-chapter="statement"', $html);
        $this->assertStringContainsString('data-chapter="method"', $html);
        // Motion sets styles from scripts only (CSSOM): never a style attribute or an inline script in the HTML
        $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=|<style\b/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\b(?:src=|type="application\/ld\+json"))[^>]*>/i', $html);
    }

    public function test_the_cinematic_layer_is_its_own_file_that_the_html_never_loads(): void
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) {
            $this->markTestSkipped('No build (npm run build)');
        }
        $entries = json_decode((string) file_get_contents($manifest), true);
        $app = $entries['resources/js/app.js'];
        $this->assertContains('resources/js/motion/cinematic.js', $app['dynamicImports'] ?? []);
        $this->assertArrayHasKey('resources/js/motion/cinematic.js', $entries);
        $this->assertNotContains('resources/js/motion/cinematic.js', $app['imports'] ?? []);

        $this->fakeHq();
        $this->withVite();
        $this->get('/')->assertOk()->assertDontSee($entries['resources/js/motion/cinematic.js']['file'], false);
    }
}
