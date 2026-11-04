<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The launch set (docs/LAUNCH_SET.md): content pages in EN and FR, old addresses, crawler files, consent. */
class LaunchPagesTest extends TestCase
{
    public function test_every_content_page_answers_in_english_and_french(): void
    {
        $this->fakeHq();

        $pages = [
            '/' => ['Ask your company anything.', 'Posez toutes vos questions à votre entreprise.'],
            '/services' => ['One system, designed around how you run.', 'Un seul système, conçu autour de votre fonctionnement.'],
            '/how-we-work' => ['Formerly the Praxis Framework.', 'Anciennement le Praxis Framework.'],
            '/company' => ['Order, made visible.', 'L’ordre, rendu visible.'],
            '/security' => ['Your data stays yours.', 'Vos données restent les vôtres.'],
            '/privacy' => ['Privacy policy', 'Politique de confidentialité'],
        ];

        foreach ($pages as $path => [$en, $fr]) {
            $this->get($path)->assertOk()->assertSee('<html lang="en">', false)->assertSee($en);
            $this->get('/fr' . ($path === '/' ? '' : $path))->assertOk()->assertSee('<html lang="fr">', false)->assertSee($fr);
        }
    }

    public function test_french_pages_link_and_post_inside_fr(): void
    {
        $this->fakeHq(['whitepaper' => Http::response(['data' => ['sent' => true]], 201)]);

        $this->get('/fr/contact')->assertOk()
            ->assertSee('action="' . url('/fr/contact') . '"', false)
            ->assertSee('href="' . url('/fr/services') . '"', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="' . url('/contact') . '">', false)
            ->assertSee('Réserver un audit gratuit et une démo');

        // The guide form sends visitors back to the page they were on, in their language
        $this->from('/fr/how-we-work')->post('/fr/services', ['email' => 'ada@example.com', 'back' => 'how-we-work'])
            ->assertRedirect(url('/fr/how-we-work') . '#guide');
    }

    public function test_old_addresses_move_permanently(): void
    {
        $this->get('/about')->assertStatus(301)->assertRedirect('/company');
        $this->get('/trust-center')->assertStatus(301)->assertRedirect('/security');
        $this->get('/framework')->assertStatus(301)->assertRedirect('/how-we-work#method');
        $this->get('/hq/finance/invoices?x=1')->assertStatus(301)->assertRedirect('https://hq.jbspraxis.com/finance/invoices?x=1');
        $this->get('/client/login')->assertStatus(301)->assertRedirect('https://portal.jbspraxis.com/login');
        $this->get('http://www.jbspraxis.test/services?a=b')->assertStatus(301)->assertRedirect('http://jbspraxis.test/services?a=b');
    }

    public function test_the_sitemap_lists_both_languages_with_hreflang_pairs(): void
    {
        $this->fakeHq();

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>' . url('/fr/how-we-work') . '</loc>', false)
            ->assertSee('<xhtml:link rel="alternate" hreflang="fr" href="' . url('/fr/security') . '"/>', false);
        $this->get('/llms.txt')->assertOk()->assertSee('the Praxis Method (formerly the Praxis Framework)');
    }

    public function test_analytics_waits_for_consent_and_only_when_configured(): void
    {
        $this->fakeHq();

        config(['site.ga4_id' => '']);
        $off = $this->get('/');
        $off->assertDontSee('data-consent', false);
        $this->assertStringNotContainsString('googletagmanager', $off->headers->get('Content-Security-Policy'));

        config(['site.ga4_id' => 'G-91FM19NGCK']);
        $on = $this->get('/');
        $on->assertSee('data-ga="G-91FM19NGCK"', false)->assertSee('hidden>', false)->assertDontSee('gtag/js', false);
        $this->assertStringContainsString('https://www.googletagmanager.com', $on->headers->get('Content-Security-Policy'));
    }

    public function test_the_demo_button_opens_youtube_only_when_configured(): void
    {
        $this->fakeHq();

        config(['site.demo_video' => ['en' => '', 'fr' => '']]);
        $off = $this->get('/');
        $off->assertOk()->assertDontSee('data-demo-open', false)->assertSee('data-answer', false)->assertDontSee('<video', false);
        $this->assertStringNotContainsString('youtube', $off->headers->get('Content-Security-Policy'));

        config(['site.demo_video' => ['en' => 'EnDemo123', 'fr' => 'FrDemo456']]);
        $en = $this->get('/');
        $en->assertSee('href="https://www.youtube.com/watch?v=EnDemo123" rel="noopener" data-demo-open="EnDemo123"', false)
            ->assertSee('Watch the 3-minute demo')->assertSee('data-demo-dialog', false);
        $this->assertStringContainsString("frame-src 'self' https://www.youtube-nocookie.com", $en->headers->get('Content-Security-Policy'));
        // No player in the page until the visitor clicks (app.js adds it)
        $en->assertDontSee('<iframe', false);

        $this->get('/fr')->assertSee('data-demo-open="FrDemo456"', false)->assertSee('Voir la démo de 3 minutes');
    }
}
