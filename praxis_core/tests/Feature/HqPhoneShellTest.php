<?php

namespace Tests\Feature;

use App\Support\Hosts;
use App\Support\HqNav;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailSettings;
use Tests\TestCase;

/**
 * HQ on phones (docs/HQ_PHONE.md, owner 12 Nov 2026): the bottom bar Home, Mail, Leads, Projects and More, a place
 * the person cannot open filled from the reserve, More holding every other module they can open, and the strip of
 * the current module's pages. One registry (App\Support\HqNav) draws the rail too.
 */
class HqPhoneShellTest extends TestCase
{
    use UsesThrowawayDatabase;

    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    /** A link to the route, whatever scheme the page was drawn with; $after follows the closing quote */
    private function href(string $route, string $after = ''): string
    {
        return '#href="https?://' . preg_quote(Hosts::hq() . parse_url(route($route), PHP_URL_PATH), '#') . '"' . $after . '#';
    }

    public function test_the_owner_has_home_mail_leads_and_projects_then_more(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $bar = HqNav::bar(HqNav::sections($owner));

        $this->assertSame(['workspace', 'mail', 'leads', 'projects'], array_column($bar, 'key'));
        $this->assertSame(['Home', 'Mail', 'Leads', 'Projects'], array_column($bar, 'short'));

        $html = $this->actingAs($owner)->get($this->hq('/dashboard/workspace'))->assertOk()->getContent();
        $nav = substr($html, strpos($html, 'class="hq-bottombar"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));
        $this->assertSame(5, preg_match_all('/class="hq-tab[ "]/', $nav));
        foreach (['Home', 'Mail', 'Leads', 'Projects', 'More'] as $label) {
            $this->assertStringContainsString('<span class="hq-tab-label">' . $label . '</span>', $nav);
        }
        $this->assertMatchesRegularExpression($this->href('hq.mail.inbox'), $nav);
        $this->assertMatchesRegularExpression($this->href('hq.dash.workspace', '[^>]*aria-current="page"'), $nav);
    }

    public function test_a_place_the_person_cannot_open_goes_to_the_next_module_they_can(): void
    {
        foreach (['engineer', 'finance', 'sales', 'hr', 'delivery', 'auditor', 'partner'] as $role) {
            $person = $this->staff($role, [$role]);
            $sections = HqNav::sections($person);
            $keys = array_column($sections, 'key');
            $bar = array_column(HqNav::bar($sections), 'key');

            $this->assertSame('workspace', $bar[0], "{$role}: Home comes first");
            $this->assertSame(array_values(array_unique($bar)), $bar, "{$role}: no module twice");
            $this->assertSame([], array_diff($bar, $keys), "{$role}: only modules they can open");
            $this->assertCount(min(4, count($keys)), $bar, "{$role}: the bar is full when they can open enough");

            // The four they can open keep their order; the rest follow the reserve's order
            $expected = array_values(array_intersect(HqNav::BAR, $keys));
            foreach (HqNav::RESERVE as $key) {
                if (count($expected) < 4 && in_array($key, $keys, true)) {
                    $expected[] = $key;
                }
            }
            $this->assertSame($expected, $bar, "{$role}: the reserve fills the bar in order");
        }
    }

    public function test_an_engineer_without_leads_gets_the_next_reserve_module(): void
    {
        $engineer = $this->staff('eng', ['engineer']);
        $sections = HqNav::sections($engineer);
        $this->assertNotContains('leads', array_column($sections, 'key'));

        $bar = array_column(HqNav::bar($sections), 'key');
        $this->assertSame(['workspace', 'mail', 'projects'], array_slice($bar, 0, 3));
        $reserve = array_values(array_intersect(HqNav::RESERVE, array_column($sections, 'key')));
        $this->assertSame($reserve[0], $bar[3]);
    }

    public function test_mail_leaves_the_bar_when_it_is_limited_to_owner_accounts(): void
    {
        $engineer = $this->staff('eng', ['engineer']);
        $this->assertContains('mail', array_column(HqNav::bar(HqNav::sections($engineer)), 'key'), 'open to everyone (12 Nov 2026)');

        MailSettings::set('open_to', 'owner');
        MailAccess::flush();

        $bar = array_column(HqNav::bar(HqNav::sections($engineer)), 'key');
        $this->assertNotContains('mail', $bar);
        $html = $this->actingAs($engineer)->get($this->hq('/dashboard/workspace'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression($this->href('hq.mail.inbox'), $html);
    }

    public function test_more_holds_every_other_module_the_person_can_open_and_nothing_else(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $sections = HqNav::sections($owner);
        $bar = array_column(HqNav::bar($sections), 'key');
        $more = array_merge(...array_values(array_map(fn ($g) => array_column($g, 'key'), HqNav::more($sections))));

        $this->assertEqualsCanonicalizing(array_values(array_diff(array_column($sections, 'key'), $bar)), $more);
        $this->assertSame([], array_intersect($bar, $more), 'nothing twice');

        $html = $this->actingAs($owner)->get($this->hq('/dashboard/workspace'))->assertOk()->getContent();
        $sheet = substr($html, strpos($html, 'id="hq-more"'));
        $this->assertStringContainsString('role="dialog"', substr($html, strpos($html, 'id="hq-more"') - 80, 200));
        foreach (HqNav::more($sections) as $group => $tiles) {
            $this->assertStringContainsString('<h3 class="hq-group-title">' . $group . '</h3>', $sheet);
            foreach ($tiles as $tile) {
                $this->assertMatchesRegularExpression('#href="[^"]*' . preg_quote(parse_url($tile['href'], PHP_URL_PATH), '#') . '"#', $sheet);
            }
        }

        // An engineer's More never offers a module they cannot open
        $engineer = $this->staff('eng', ['engineer']);
        $html = $this->actingAs($engineer)->get($this->hq('/dashboard/workspace'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression($this->href('hq.system.roles'), $html);
        $this->assertDoesNotMatchRegularExpression($this->href('hq.mdm.dictionary'), $html);
    }

    public function test_the_strip_shows_the_pages_of_the_module_in_view(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $html = $this->actingAs($owner)->get($this->hq('/company-profile'))->assertOk()->getContent();

        $strip = substr($html, strpos($html, 'data-hq-subnav'));
        $strip = substr($strip, 0, strpos($strip, '</nav>'));
        foreach (['hq.company-profile.editor', 'hq.company-profile.versions', 'hq.company-profile.logos', 'hq.company-profile.shares'] as $route) {
            $this->assertMatchesRegularExpression($this->href($route), $strip);
        }
        $this->assertMatchesRegularExpression($this->href('hq.company-profile.editor', '[^>]*aria-current="page"'), $strip);

        // My workspace has no pages of its own: no strip
        $html = $this->actingAs($owner)->get($this->hq('/dashboard/workspace'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-hq-subnav', $html);
    }

    public function test_the_phone_top_bar_has_the_mark_the_title_and_the_avatar_and_no_bell_of_its_own(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $html = $this->actingAs($owner)->get($this->hq('/company-profile'))->assertOk()->getContent();

        $bar = substr($html, strpos($html, 'data-hq-topbar'));
        $bar = substr($bar, 0, strpos($bar, '</header>'));
        $this->assertStringContainsString('jbs-praxis-monogram-colour.svg', $bar);
        $this->assertMatchesRegularExpression('/data-hq-bar-title[^>]*>Company profile</', $bar);
        $this->assertStringContainsString('hq-avatar-wrap', $bar);
        // The bell is hidden on phones (hq-phone.css); More's bell opens its panel
        $this->assertStringContainsString('data-hq-bell-button', $bar);
        $this->assertStringContainsString('@hq-open-bell.window="open = true"', $bar);
        $this->assertStringContainsString("new CustomEvent('hq-open-bell')", $html);
    }

    public function test_every_navigation_icon_is_in_the_bundle(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $bundle = file_get_contents(resource_path('js/icons.js'));
        $pascal = fn (string $name) => str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));

        foreach (HqNav::sections($owner) as $section) {
            foreach (array_unique([$section['icon'], $section['bar_icon']]) as $icon) {
                $this->assertMatchesRegularExpression('/\b' . $pascal($icon) . '\b/', $bundle, "{$icon} ({$section['key']}) is in resources/js/icons.js: run npm run build");
            }
        }
        foreach (['layout-grid', 'bell', 'refresh-cw', 'info'] as $icon) {
            $this->assertMatchesRegularExpression('/\b' . $pascal($icon) . '\b/', $bundle);
        }
    }
}
