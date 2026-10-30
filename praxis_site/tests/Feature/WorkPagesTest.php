<?php

namespace Tests\Feature;

use App\Support\Work;
use Tests\TestCase;

/** The proof pages (PR4): Our work, every case study and Industries, in English and French, and where they are linked. */
class WorkPagesTest extends TestCase
{
    /** Every proof page path, English form (French: /fr + path). */
    private function paths(): array
    {
        return ['/work', '/industries', ...array_map(fn (string $slug) => '/work/' . $slug, Work::slugs())];
    }

    public function test_every_proof_page_answers_in_english_and_french(): void
    {
        $this->fakeHq();

        foreach ($this->paths() as $path) {
            $this->get($path)->assertOk()->assertSee('<html lang="en">', false)
                ->assertSee('<link rel="alternate" hreflang="fr" href="' . url('/fr' . $path) . '">', false);
            $this->get('/fr' . $path)->assertOk()->assertSee('<html lang="fr">', false)
                ->assertSee('<link rel="alternate" hreflang="en" href="' . url($path) . '">', false);
        }

        $this->get('/work')->assertSee('What we built, and how it runs.')->assertSee('Book a free audit and demo');
        $this->get('/fr/work')->assertSee('Ce que nous avons construit, et comment cela fonctionne.')
            ->assertSee('Réserver un audit gratuit et une démo');
    }

    public function test_the_index_lists_every_case_study_and_the_sector_only_work(): void
    {
        $this->fakeHq();

        $index = $this->get('/work')->assertOk();
        foreach (Work::slugs() as $slug) {
            $index->assertSee('href="' . url('/work/' . $slug) . '"', false)->assertSee(__('work.cases.' . $slug . '.name', [], 'en'));
        }
        foreach (array_keys(Work::OTHER) as $key) {
            $index->assertSee('id="' . $key . '"', false);
        }

        // French pages link inside /fr
        $this->get('/fr/work')->assertSee('href="' . url('/fr/work/smart-logistics') . '"', false)
            ->assertSee('href="' . url('/fr/industries') . '"', false);
    }

    public function test_a_case_study_has_its_facts_map_sequence_role_and_related_work(): void
    {
        $this->fakeHq();

        $this->get('/work/smart-logistics')->assertOk()
            ->assertSee('<title>Smart Logistics and Services: Praxis LS | JBS Praxis</title>', false)
            ->assertSee('Praxis LS')
            ->assertSee('class="system-groups"', false)
            ->assertSee('One general ledger under SYSCOHADA.')
            ->assertSee('class="steps"', false)
            ->assertSee('Our role')
            ->assertSee('href="' . url('/industries') . '#logistics"', false)
            ->assertSee('href="' . url('/work/fmcg-distributor-cameroon') . '"', false)
            ->assertSee('href="' . url('/schedule') . '"', false);

        // Work in build says so; the partner is named only where approved
        $this->get('/work/pharmaceutical-manufacturer')->assertOk()
            ->assertSee('In build')->assertSee('What changes')->assertSee('Tales Consulting')->assertSee('demo on request');
        $this->get('/fr/work/nfelia')->assertOk()->assertSee('Notre produit');
    }

    public function test_an_unknown_case_study_is_a_404(): void
    {
        $this->fakeHq();

        $this->get('/work/no-such-client')->assertNotFound();
        $this->get('/fr/work/no-such-client')->assertNotFound();
    }

    public function test_industries_marks_only_what_the_work_covers_and_links_to_it(): void
    {
        $this->fakeHq();

        $response = $this->get('/industries')->assertOk();
        foreach (Work::sectors() as $sector) {
            $response->assertSee('id="' . $sector . '"', false);
            $this->assertNotSame([], Work::coverage($sector), "$sector has no covered domain");
        }
        // Small businesses: nfelia covers money and operations only
        $this->assertSame(['money', 'operations'], Work::coverage('small-business'));
        $response->assertSee('href="' . url('/work/nfelia') . '"', false)
            ->assertSee('href="' . url('/work') . '#oil-and-gas"', false);

        // Every sector shown has work behind it, and every piece of work has a sector that is shown
        foreach ([...array_values(Work::CASES), ...array_values(Work::OTHER)] as $entry) {
            $this->assertContains($entry['sector'], Work::sectors());
        }
        foreach (Work::CASES as $slug => $case) {
            foreach ($case['related'] as $related) {
                $this->assertArrayHasKey($related, Work::CASES, "$slug relates to $related");
                $this->assertNotSame($slug, $related);
            }
        }
    }

    public function test_the_header_footer_and_home_link_to_the_work(): void
    {
        $this->fakeHq();

        $home = $this->get('/')->assertOk();
        $home->assertSee('<a href="' . url('/work') . '" >Work</a>', false)
            ->assertSee('href="' . url('/industries') . '"', false)
            ->assertSee('href="' . url('/work') . '">See all our work</a>', false);
        foreach (Work::slugs() as $slug) {
            $home->assertSee('href="' . url('/work/' . $slug) . '"', false);
        }

        // The current section is marked on a case study, and the French header says Réalisations
        $this->get('/work/orika-living')->assertSee('<a href="' . url('/work') . '"  aria-current="page" >Work</a>', false);
        $this->get('/fr')->assertSee('<a href="' . url('/fr/work') . '" >Réalisations</a>', false);
    }

    public function test_the_old_portfolio_address_moves_to_the_work(): void
    {
        $this->get('/portfolio')->assertStatus(301)->assertRedirect('/work');
    }

    public function test_the_sitemap_and_llms_txt_list_the_proof_pages(): void
    {
        $this->fakeHq(['kaizen/articles*' => \Illuminate\Support\Facades\Http::response(['data' => [], 'meta' => ['has_more' => false]])]);

        $sitemap = $this->get('/sitemap.xml')->assertOk();
        foreach ($this->paths() as $path) {
            $sitemap->assertSee('<loc>' . url($path) . '</loc>', false)
                ->assertSee('<loc>' . url('/fr' . $path) . '</loc>', false)
                ->assertSee('<xhtml:link rel="alternate" hreflang="fr" href="' . url('/fr' . $path) . '"/>', false);
        }

        $llms = $this->get('/llms.txt')->assertOk();
        foreach ($this->paths() as $path) {
            $llms->assertSee('(' . url($path) . ')', false);
        }
    }

    /** Words that must never reach a page: client names not approved, retired claims, the trademark sign, our code host. */
    public function test_no_page_names_an_unapproved_client_or_a_retired_claim(): void
    {
        $this->fakeHq(['kaizen/articles*' => \Illuminate\Support\Facades\Http::response(['data' => [], 'meta' => ['has_more' => false]])]);

        $paths = ['/', '/services', '/how-we-work', '/company', '/security', '/privacy', '/sitemap.xml', '/llms.txt', ...$this->paths()];
        foreach ($paths as $path) {
            foreach ($path === '/sitemap.xml' || $path === '/llms.txt' ? [$path] : [$path, '/fr' . ($path === '/' ? '' : $path)] as $url) {
                $html = $this->get($url)->assertOk()->getContent();
                foreach (['Fidson', 'La Petite Cour', 'Petite Cour', 'predictive', 'prédictive', 'prédictif', '™', 'github', 'tomblakeasaah'] as $word) {
                    $this->assertStringNotContainsStringIgnoringCase($word, $html, "$url contains $word");
                }
                $this->assertDoesNotMatchRegularExpression('/\bLPC\b/', $html, "$url contains LPC");
            }
        }
    }

    public function test_every_english_key_has_a_french_twin_and_french_spacing(): void
    {
        $keys = function (array $array, string $prefix = '') use (&$keys): array {
            $out = [];
            foreach ($array as $key => $value) {
                $out[] = $prefix . $key;
                if (is_array($value)) {
                    $out = [...$out, ...$keys($value, $prefix . $key . '.')];
                }
            }

            return $out;
        };
        $en = require lang_path('en/work.php');
        $fr = require lang_path('fr/work.php');
        $this->assertSame([], array_values(array_diff($keys($en), $keys($fr))), 'missing in French');
        $this->assertSame([], array_values(array_diff($keys($fr), $keys($en))), 'only in French');

        // Every case study and sector-only entry has its words
        foreach (Work::slugs() as $slug) {
            $this->assertArrayHasKey($slug, $en['cases']);
        }
        $this->assertSame(array_keys(Work::OTHER), array_keys($en['other']));
        $this->assertSame(Work::SECTORS, array_keys($en['sectors']));

        // French on the site: a narrow no-break space (U+202F) before every ; : ! ? and inside « », never another space
        array_walk_recursive($fr, function ($text) {
            if (is_string($text)) {
                $this->assertDoesNotMatchRegularExpression('/(?<!\x{202F})[;:!?»]|«(?!\x{202F})/u', $text, $text);
            }
        });
    }
}
