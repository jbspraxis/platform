<?php

namespace Tests\Feature\CompanyProfile;

use App\Models\CompanyProfile\ProfileSection;
use App\Support\CompanyProfile\Profile;
use App\Support\CompanyProfile\Sections;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The data contract the three company-profile PRs share (company-profile/CONTRACT.md). If one of these fails,
 * PR 2's renderer and PR 3's letter are reading something other than what they were written against, so the
 * fix is the engine, never the test — unless the contract itself has been changed with the lead chat.
 */
class ProfileContractTest extends TestCase
{
    use ProfileFixtures, UsesThrowawayDatabase;

    public function test_every_locale_returns_the_contract_shape(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $data = Profile::data($locale);

            $this->assertSame(
                array_merge(['meta'], Sections::KEYS),
                array_keys($data),
                "the top-level keys of the {$locale} profile",
            );

            $this->assertSame(['version', 'published_at', 'locale', 'reference'], array_keys($data['meta']));
            $this->assertSame($locale, $data['meta']['locale']);
            $this->assertSame('v1', $data['meta']['version']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['meta']['published_at']);
            $this->assertMatchesRegularExpression('/^JBS-CP-\d{4}-\d{2}-v1$/', $data['meta']['reference']);

            $this->assertSame(['headline', 'descriptor'], array_keys($data['cover']));
            $this->assertSame(['title', 'paragraphs'], array_keys($data['pov']));
            $this->assertSame(['title', 'lead', 'pillars'], array_keys($data['system']));
            $this->assertSame(['title', 'lead', 'groups', 'closing'], array_keys($data['problems']));
            $this->assertSame(['title', 'lead', 'items'], array_keys($data['practices']));
            $this->assertSame(['title', 'lead', 'stages', 'principles'], array_keys($data['method']));
            $this->assertSame(['title', 'lead', 'items'], array_keys($data['teams']));
            $this->assertSame(['title', 'lead', 'items'], array_keys($data['work']));
            $this->assertSame(['title', 'logos'], array_keys($data['clients']));
            $this->assertSame(['title', 'items'], array_keys($data['security']));
            $this->assertSame(['title', 'items'], array_keys($data['facts']));
            $this->assertSame(
                ['cta', 'cta_url', 'email', 'sales_email', 'phone', 'website', 'offices', 'closing'],
                array_keys($data['contact']),
            );

            $this->assertSame(
                ['name', 'summary', 'disciplines', 'experience', 'credentials'],
                array_keys($data['teams']['items'][0]),
            );
            // Most credentials are not held yet, so take the first one that is actually rendered
            $credential = collect($data['teams']['items'])->flatMap(fn ($team) => $team['credentials'])->first();
            $this->assertNotNull($credential, 'at least one credential should be held');
            $this->assertSame(['name', 'issuer', 'held'], array_keys($credential));
            $this->assertSame(['domain', 'items'], array_keys($data['problems']['groups'][0]));
            $this->assertSame(['problem', 'answer'], array_keys($data['problems']['groups'][0]['items'][0]));
            $this->assertSame(['name', 'summary', 'capabilities'], array_keys($data['practices']['items'][0]));
            $this->assertSame(
                ['client', 'sector', 'title', 'problem', 'built', 'outcome', 'image'],
                array_keys($data['work']['items'][0]),
            );
            $this->assertSame(['label', 'value', 'enabled'], array_keys($data['facts']['items'][0]));

            // Plain string lists are strings, not objects, for the renderers
            $this->assertIsString($data['pov']['paragraphs'][0]);
            $this->assertIsString($data['teams']['items'][0]['disciplines'][0]);
            $this->assertIsString($data['practices']['items'][0]['capabilities'][0]);
            $this->assertIsString($data['contact']['offices'][0]);
        }
    }

    public function test_the_two_locales_say_different_words_about_the_same_things(): void
    {
        $en = Profile::data('en');
        $fr = Profile::data('fr');

        $this->assertNotSame($en['cover']['headline'], $fr['cover']['headline']);
        $this->assertNotSame($en['teams']['items'][0]['name'], $fr['teams']['items'][0]['name']);

        // The same number of everything, because the structure is shared
        $this->assertCount(count($en['teams']['items']), $fr['teams']['items']);
        $this->assertCount(count($en['work']['items']), $fr['work']['items']);
        $this->assertCount(count($en['facts']['items']), $fr['facts']['items']);

        // Credentials are facts about the firm, not words: identical in both
        $this->assertSame($en['teams']['items'][0]['credentials'], $fr['teams']['items'][0]['credentials']);
    }

    public function test_only_held_credentials_and_enabled_facts_are_rendered(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $render = Profile::data($locale);
            $editor = Profile::editorData($locale);

            foreach ($render['teams']['items'] as $team) {
                foreach ($team['credentials'] as $credential) {
                    $this->assertTrue($credential['held'], 'a credential that is not held was rendered');
                }
            }
            foreach ($render['facts']['items'] as $fact) {
                $this->assertTrue($fact['enabled'], 'a fact that is switched off was rendered');
            }

            // The editor sees everything, and there IS something switched off to see
            $heldOff = collect($editor['teams']['items'])->flatMap(fn ($t) => $t['credentials'])->where('held', false);
            $this->assertGreaterThan(0, $heldOff->count(), 'the seed should carry a credential that is not held');
            $this->assertGreaterThan(
                count($render['facts']['items']),
                count($editor['facts']['items']),
                'the editor should see more facts than the profile renders',
            );
        }
    }

    public function test_the_brand_sign_off_is_the_last_thing_in_the_contact_section(): void
    {
        $this->assertSame('Order, made visible.', Profile::data('en')['contact']['closing']);
        $this->assertSame('closing', array_key_last(Profile::data('fr')['contact']));
        $this->assertNotSame('', Profile::data('fr')['contact']['closing']);
    }

    public function test_the_written_copy_keeps_the_client_naming_rules(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $clients = collect(Profile::data($locale)['work']['items'])->pluck('client')->implode(' | ');

            $this->assertStringNotContainsStringIgnoringCase('fidson', $clients, 'Fidson is never named');
            $this->assertStringContainsString('La Petite Cour', $clients, 'La Petite Cour may be named');
        }
    }

    public function test_the_cameroon_entity_is_off_by_default(): void
    {
        foreach (['en' => 'cameroon', 'fr' => 'cameroun'] as $locale => $word) {
            $facts = collect(Profile::editorData($locale)['facts']['items']);
            $cameroon = $facts->first(fn ($f) => str_contains(strtolower($f['label'] . ' ' . $f['value']), $word));

            $this->assertNotNull($cameroon, "the seed should carry the Cameroon entity as a {$locale} fact");
            $this->assertFalse($cameroon['enabled']);

            // It is the switch that keeps it out, not the wording: its value is nowhere in what is rendered
            $rendered = collect(Profile::data($locale)['facts']['items'])->pluck('value')->all();
            $this->assertNotContains($cameroon['value'], $rendered);
        }
    }

    public function test_the_editor_query_carries_the_ids_the_renderers_never_see(): void
    {
        $editor = Profile::editorData('en');
        $render = Profile::data('en');

        $this->assertArrayHasKey('id', $editor['teams']['items'][0]);
        $this->assertArrayNotHasKey('id', $render['teams']['items'][0]);
        $this->assertArrayHasKey('status', $editor['meta']);
        $this->assertArrayNotHasKey('status', $render['meta']);

        // A plain string list comes back as id + value for the editor, and as a string for the renderers
        $this->assertSame(['id', 'value'], array_keys($editor['pov']['paragraphs'][0]));
    }

    public function test_the_logo_wall_comes_from_the_provider(): void
    {
        $this->fakeLogos([
            ['name' => 'Fidson Healthcare', 'path' => '/logos/fidson.png'],
            ['name' => 'LPC', 'path' => '/logos/lpc.svg'],
        ]);

        $this->assertSame(
            [['name' => 'Fidson Healthcare', 'path' => '/logos/fidson.png'], ['name' => 'LPC', 'path' => '/logos/lpc.svg']],
            Profile::data('en')['clients']['logos'],
        );
    }

    public function test_an_empty_logo_folder_gives_an_empty_wall(): void
    {
        $this->fakeLogos([]);

        $this->assertSame([], Profile::data('fr')['clients']['logos']);
    }

    public function test_a_section_that_is_not_there_still_returns_its_whole_shape(): void
    {
        ProfileSection::where('version_id', $this->published()->id)->where('key', 'security')->delete();

        $data = Profile::data('en');

        $this->assertSame(['title', 'items'], array_keys($data['security']));
        $this->assertSame('', $data['security']['title']);
        $this->assertSame([], $data['security']['items']);
    }

    public function test_an_unknown_locale_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Profile::data('de');
    }
}
