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
            ->assertSee('Book a free audit and demo')
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
            ->assertSee('JBS Praxis LLC')
            ->assertSee('Lagos, Nigeria')
            ->assertSee('Douala, Cameroon')
            ->assertSee('CAC (Nigeria):')
            ->assertSee('registration in progress')
            ->assertSee('mailto:info@jbspraxis.com', false)
            ->assertSee('https://www.linkedin.com/company/jbspraxis', false)
            ->assertSee('https://x.com/jbspraxis', false)
            ->assertSee('https://www.youtube.com/@jbspraxis', false);
    }

    public function test_a_registration_number_is_shown_once_it_is_registered(): void
    {
        $settings = $this->settingsData();
        $settings['company']['registrations'][0] = ['country' => 'NG', 'label' => 'CAC', 'number' => 'RC 1234567', 'status' => 'registered'];
        Http::fake([self::HQ . '/settings' => Http::response(['data' => $settings])]);

        $this->get('/feedback')->assertOk()->assertSee('RC 1234567');
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
