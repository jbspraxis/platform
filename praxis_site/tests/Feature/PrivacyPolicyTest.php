<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The privacy policy (lang/{en,fr}/legal.php): both languages, every section, the facts it promises, no inline code. */
class PrivacyPolicyTest extends TestCase
{
    private const SECTIONS = ['who', 'scope', 'collect', 'bases', 'sharing', 'transfers', 'retention', 'security', 'cookies', 'rights', 'complaints', 'children', 'changes'];

    public function test_the_policy_answers_in_english_and_french_with_every_section_and_its_contents(): void
    {
        $this->fakeHq();

        foreach (['/privacy' => 'en', '/fr/privacy' => 'fr'] as $path => $locale) {
            $response = $this->get($path)->assertOk()->assertSee('<html lang="' . $locale . '">', false);

            $response->assertSee('<h1 class="page-h1">' . ($locale === 'fr' ? 'Politique de confidentialité' : 'Privacy policy') . '</h1>', false)
                ->assertSee($locale === 'fr' ? 'Version du 31 octobre 2026' : 'Version of 31 October 2026')
                ->assertSee('<h2 id="summary">', false)
                ->assertSee('<nav class="policy-toc" aria-labelledby="contents-title">', false);

            foreach (self::SECTIONS as $number => $id) {
                $title = __('legal.privacy.sections.' . $id . '.title', [], $locale);
                $response->assertSee('<li><a href="#' . $id . '">' . e($title) . '</a></li>', false)
                    ->assertSee('<h2 id="' . $id . '">' . ($number + 1) . '. ' . e($title) . '</h2>', false);
            }

            // Facts from Website settings, filled in; no placeholder is left
            $response->assertSee('JBS Praxis LLC')
                ->assertSee('Lagos, Nigeria')
                ->assertSee('mailto:info@jbspraxis.com', false)
                ->assertSee(config('session.cookie'))
                ->assertDontSee(':email')
                ->assertDontSee(':company')
                ->assertDontSee(':offices')
                ->assertDontSee(':session_');

            // What the systems really do, and the law (docs: the CV purge in praxis_core, Praxis AI's providers)
            $response->assertSee('Gemini')->assertSee('DeepSeek')->assertSee('ndpc.gov.ng')->assertSee('2024/017')
                ->assertSee('General Application and Implementation Directive')
                ->assertSee('youtube-nocookie.com')->assertSee('jbs_consent')->assertSee('_ga, _ga_…');
            $response->assertSee($locale === 'fr' ? '12 mois à compter de leur réception' : '12 months from the day we receive them');

            // The cookie banner's "How we use cookies" link lands on the cookies section
            $this->assertStringContainsString('id="cookies"', $response->getContent());
        }
    }

    public function test_the_policy_carries_no_inline_script_or_style(): void
    {
        $this->fakeHq();

        foreach (['/privacy', '/fr/privacy'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\b(?:src=|type="application\/ld\+json"))[^>]*>/i', $html);
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $html);
            $this->assertDoesNotMatchRegularExpression('/<style\b/i', $html);
        }
    }

    public function test_the_cookie_settings_button_shows_only_when_analytics_is_configured(): void
    {
        $this->fakeHq();

        config(['site.ga4_id' => '']);
        $this->get('/privacy')->assertOk()->assertDontSee('data-consent-open', false);

        config(['site.ga4_id' => 'G-91FM19NGCK']);
        $this->get('/privacy')->assertOk()
            ->assertSee('<button class="btn btn-secondary btn-sm" type="button" data-consent-open>Cookie settings</button>', false);
    }

    public function test_the_offices_line_is_left_out_when_website_settings_list_none(): void
    {
        $settings = $this->settingsData();
        $settings['company']['offices'] = [];
        Http::fake([self::HQ . '/settings' => Http::response(['data' => $settings])]);

        $this->get('/privacy')->assertOk()->assertDontSee('Our offices')->assertSee('mailto:info@jbspraxis.com', false);
    }

    public function test_english_and_french_have_the_same_keys_and_french_typography(): void
    {
        $en = require lang_path('en/legal.php');
        $fr = require lang_path('fr/legal.php');
        $this->assertSame($this->shape($en), $this->shape($fr));

        $french = implode("\n", $this->strings($fr));
        // A narrow no-break space (U+202F) before ; : ! ? and inside « », never a plain space; ":email" is a placeholder
        $this->assertDoesNotMatchRegularExpression('/ [;!?»]| :(?![a-z_])|« /u', $french);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $french);
        $this->assertStringContainsString("Nos bureaux\u{202F}: :offices.", $french);
        $this->assertStringContainsString("«\u{202F}JBS Praxis\u{202F}»", $french);
    }

    /** The keys and the kind of every value (paragraph or block), all the way down. */
    private function shape(array $values): array
    {
        return array_map(fn ($value) => is_array($value) ? $this->shape($value) : 'text', $values);
    }

    /** @return list<string> */
    private function strings(array $values): array
    {
        $out = [];
        array_walk_recursive($values, function ($value) use (&$out) {
            $out[] = (string) $value;
        });

        return $out;
    }
}
