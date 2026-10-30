<?php

namespace Tests\Feature;

use App\Support\Hosts;
use Tests\TestCase;

/**
 * The public site's host in this app. jbspraxis.com is served by praxis_site, so HQ keeps only the page names
 * (emails and HQ build the same addresses with route()), broadcast tracking, the www redirect and the old
 * single-host redirects. The old marketing pages are gone: every one of their paths is 404 here.
 */
class SiteHostTest extends TestCase
{
    /** Route name => path, as emails and HQ have always built them */
    private const PAGES = [
        'home' => '/', 'schedule' => '/schedule', 'contact' => '/contact', 'about' => '/about',
        'portfolio' => '/portfolio', 'services' => '/services', 'careers' => '/careers', 'kaizen' => '/kaizen',
        'privacy' => '/privacy', 'trust-center' => '/trust-center', 'feedback' => '/feedback', 'framework' => '/framework',
    ];

    private function site(string $path): string
    {
        return 'https://' . Hosts::site() . $path;
    }

    public function test_the_page_names_still_build_the_same_addresses(): void
    {
        foreach (self::PAGES as $name => $path) {
            $url = route($name);
            $this->assertSame(Hosts::site(), parse_url($url, PHP_URL_HOST), $name);
            $this->assertSame($path, parse_url($url, PHP_URL_PATH) ?? '/', $name);
        }

        $url = route('kaizen.show', ['slug' => 'closing-the-month']);
        $this->assertSame(Hosts::site(), parse_url($url, PHP_URL_HOST));
        $this->assertSame('/kaizen/closing-the-month', parse_url($url, PHP_URL_PATH));
    }

    public function test_the_old_pages_are_not_served_here(): void
    {
        foreach ([...array_values(self::PAGES), '/kaizen/closing-the-month', '/sitemap.xml'] as $path) {
            $this->get($this->site($path))->assertNotFound();
        }
    }

    public function test_www_and_the_old_single_host_addresses_still_redirect(): void
    {
        $this->get('https://www.' . Hosts::site() . '/schedule?ref=email')
            ->assertStatus(301)->assertRedirect($this->site('/schedule?ref=email'));

        $this->get($this->site('/hq/dashboard/workspace'))
            ->assertStatus(301)->assertRedirect('https://' . Hosts::hq() . '/dashboard/workspace');
        $this->get($this->site('/client/login'))
            ->assertStatus(301)->assertRedirect('https://' . Hosts::portal() . '/login');
    }

    public function test_the_unsubscribe_page_still_answers(): void
    {
        $this->get($this->site('/tracking/unsubscribe/00000000-0000-4000-8000-000000000001?campaign=c1&t=abc'))
            ->assertOk()
            ->assertSee(route('tracking.unsubscribe.confirm', ['user_id' => '00000000-0000-4000-8000-000000000001']), false);
    }
}
