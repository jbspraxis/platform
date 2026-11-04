<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The preview home page, the layout built from GET /settings, security headers and noindex. */
class LayoutAndSecurityTest extends TestCase
{
    public function test_the_home_page_carries_the_launch_copy_and_links_to_the_main_pages(): void
    {
        $this->fakeHq();

        $response = $this->get('/')->assertOk();

        $response->assertSee('Ask your company anything.')
            ->assertSee('Book a free audit')
            ->assertSee('<title>JBS Praxis | Consulting and engineering for Africa’s institutions</title>', false)
            ->assertSee('<link rel="alternate" hreflang="fr" href="' . url('/fr') . '">', false);
        foreach (['/schedule', '/cost-calculator', '/kaizen', '/services', '/how-we-work', '/company', '/security', '/privacy', '/careers'] as $path) {
            $response->assertSee('href="' . url($path), false);
        }
    }

    public function test_the_footer_is_built_from_the_website_settings(): void
    {
        $this->fakeHq();

        $this->get('/contact')->assertOk()
            ->assertSee('Lagos, Nigeria')
            ->assertSee('Douala, Cameroon')
            // Registrations still in progress are not shown (owner, 30 Oct 2026)
            ->assertDontSee('CAC (Nigeria):')
            ->assertDontSee('registration in progress')
            ->assertSee('mailto:info@jbspraxis.com', false)
            // Social links are logos with the name in the link's label
            ->assertSee('href="https://www.linkedin.com/company/jbspraxis" rel="noopener" title="LinkedIn"', false)
            ->assertSee('aria-label="JBS Praxis on LinkedIn"', false)
            ->assertSee('https://x.com/jbspraxis', false)
            ->assertSee('https://www.youtube.com/@jbspraxis', false)
            ->assertSee('<svg class="social-icon"', false);
    }

    public function test_the_header_language_menu_shows_the_current_code_and_links_to_each_language(): void
    {
        $this->fakeHq();

        $en = $this->get('/how-we-work')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<details class="lang-menu">\s*<summary aria-label="Language: English. Choose another">.*?<span>EN</span>~s', $en);
        $this->assertStringContainsString('href="' . url('/fr/how-we-work') . '" hreflang="fr" lang="fr" ><i data-lucide="check"></i>Français</a>', $en);
        $this->assertStringContainsString('href="' . url('/how-we-work') . '" hreflang="en" lang="en"  aria-current="true" ><i data-lucide="check"></i>English</a>', $en);

        $fr = $this->get('/fr/how-we-work')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<summary aria-label="Langue\x{202F}: français\. Choisir une autre langue">.*?<span>FR</span>~su', $fr);

        // The closing band is one call to action: the phones are in the footer, under their desks
        $this->assertStringNotContainsString('or call', $en);
        $this->assertStringNotContainsString('ou appelez', $fr);
    }

    public function test_the_footer_lists_the_regional_desks_with_their_addresses_and_phones(): void
    {
        // Phones labelled with an office's city belong to that desk; any other phone stays under Contact
        $settings = $this->settingsData();
        $settings['company']['offices'] = [
            ['city' => 'Lagos', 'country' => 'NG', 'address' => '21B Bode Ajakaiye Street, Lekki'],
            ['city' => 'Douala', 'country' => 'CM', 'address' => '1030 Avenue Douala Manga Bell, Bali'],
        ];
        $settings['contact']['phones'] = [
            ['label' => 'Lagos', 'number' => '+234 816 978 9106'],
            ['label' => 'Douala', 'number' => '+237 696 12 25 11'],
            ['label' => 'Support', 'number' => '+234 800 000 0000'],
        ];
        Http::fake([self::HQ . '/settings' => Http::response(['data' => $settings])]);

        $html = $this->get('/contact')->assertOk()->getContent();
        $footer = substr($html, strpos($html, '<footer'));

        $this->assertStringContainsString('Regional desks', $footer);
        $this->assertMatchesRegularExpression('~West Africa \(ECOWAS\).*Lagos, Nigeria.*21B Bode Ajakaiye Street, Lekki.*href="tel:\+2348169789106">\+234 816 978 9106<~s', $footer);
        $this->assertMatchesRegularExpression('~Central Africa \(CEMAC\).*Douala, Cameroon.*1030 Avenue Douala Manga Bell, Bali.*href="tel:\+237696122511">\+237 696 12 25 11<~s', $footer);
        $this->assertMatchesRegularExpression('~Contact.*Support: <a href="tel:\+2348000000000">~s', $footer);
        // The longer line under the logo; the legal name (JBS Praxis LLC) is no longer printed in the footer
        $this->assertStringContainsString('We design how your organisation runs, then build the system that answers for it.', $footer);
        $this->assertStringNotContainsString('LLC', $footer);
        // A quiet way back up, and Google gets the same addresses and phones
        $this->assertStringContainsString('<a class="back-to-top" href="#top">Back to top', $footer);
        $this->assertStringContainsString('<body id="top">', $html);
        $this->assertStringContainsString('"streetAddress":"21B Bode Ajakaiye Street, Lekki","addressLocality":"Lagos","addressCountry":"NG"', $html);
        $this->assertStringContainsString('"telephone":"+237 696 12 25 11","contactType":"sales","areaServed":"CM"', $html);

        $fr = $this->get('/fr/contact')->assertOk()->getContent();
        $this->assertStringContainsString('Bureaux régionaux', $fr);
        $this->assertStringContainsString('Afrique de l’Ouest (CEDEAO)', $fr);
        $this->assertStringContainsString('Haut de page', $fr);
        $this->assertMatchesRegularExpression('~West Africa \(ECOWAS\)<br>\s*Lagos, Nigeria\s*<br>21B Bode Ajakaiye Street, Lekki~', $this->get('/company')->getContent());
    }

    public function test_a_registration_number_is_shown_once_it_is_registered(): void
    {
        $settings = $this->settingsData();
        $settings['company']['registrations'][0] = ['country' => 'NG', 'label' => 'CAC', 'number' => 'RC 1234567', 'status' => 'registered'];
        Http::fake([self::HQ . '/settings' => Http::response(['data' => $settings])]);

        $this->get('/feedback')->assertOk()->assertSee('CAC (Nigeria):')->assertSee('RC 1234567')
            ->assertDontSee('RCCM (Cameroon):');
        $this->get('/company')->assertOk()->assertSee('CAC: RC 1234567')->assertDontSee('RCCM');
    }

    public function test_pages_still_render_with_the_fallback_email_when_hq_was_never_reached(): void
    {
        Http::fake([self::HQ . '/*' => $this->down()]);

        $this->get('/contact')->assertOk()
            ->assertSee('mailto:fallback@jbspraxis.com', false)
            ->assertSee('Send message');
    }

    public function test_every_response_has_the_security_headers_and_noindex(): void
    {
        $this->fakeHq();

        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_robots_txt_disallows_everything_in_preview(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertEmpty($response->headers->getCookies(), 'no session cookie for robots');
    }

    public function test_robots_txt_allows_crawling_once_noindex_is_off(): void
    {
        config(['site.noindex' => false]);

        $response = $this->get('/robots.txt');

        $this->assertSame("User-agent: *\nDisallow:\n\nSitemap: " . url('/sitemap.xml') . "\n", $response->getContent());
        $response->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_the_pages_carry_no_inline_script_or_style(): void
    {
        $this->fakeHq();

        $html = $this->get('/contact')->getContent();

        // JSON-LD is data, never run (the CSP's script-src does not apply to it)
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\b(?:src=|type="application\/ld\+json"))[^>]*>/i', $html);
        $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<style\b/i', $html);
    }

    public function test_no_lang_file_holds_a_control_character(): void
    {
        // A stray byte (once \x01 where a French : or ? belonged) shows as nothing or a box on the page
        foreach (glob(lang_path('*/*.php')) as $file) {
            $this->assertDoesNotMatchRegularExpression('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', file_get_contents($file), $file);
        }
    }

    public function test_an_unknown_page_is_a_calm_404(): void
    {
        $this->fakeHq();

        $this->get('/no-such-page')->assertNotFound()->assertSee('We couldn’t find that page');
    }

    public function test_every_form_has_csrf_a_honeypot_and_labels(): void
    {
        $this->fakeHq([
            'calculator/reference' => Http::response(['data' => ['industries' => ['Banking'], 'systems' => [], 'bottlenecks' => [], 'currencies' => []]]),
            'booking/slots*' => Http::response(['data' => ['month' => now('Africa/Lagos')->format('Y-m'), 'days' => []]]),
        ]);

        foreach (['/contact', '/schedule', '/cost-calculator', '/kaizen/write', '/services', '/careers', '/feedback', '/tracking/unsubscribe/42'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('name="_token"', $html, $path);
            $this->assertStringContainsString('name="website"', $html, $path . ' honeypot');
            // Every visible input, select and textarea has a label pointing at it
            preg_match_all('/<(?:input|select|textarea)\b(?![^>]*type="(?:hidden|radio|submit)")[^>]*\bid="([^"]+)"/', $html, $m);
            foreach ($m[1] as $id) {
                $this->assertStringContainsString('for="' . $id . '"', $html, "$path: label for #$id");
            }
        }
    }
}
