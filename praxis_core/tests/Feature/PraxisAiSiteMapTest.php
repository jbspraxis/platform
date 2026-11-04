<?php

namespace Tests\Feature;

use App\Services\Site\PraxisAi;
use Tests\TestCase;

/**
 * Praxis AI's site map (config/praxis_ai.php): the proof pages of PR4 (/work, each case study, /industries) are pages
 * an answer may link to, with a label in each language. Paths follow the site's own rule for chat links
 * (praxis_site App\Support\ChatLinks: lowercase words joined by hyphens). No database needed.
 */
class PraxisAiSiteMapTest extends TestCase
{
    public function test_the_proof_pages_are_links_an_answer_can_carry(): void
    {
        $links = PraxisAi::links(['/work/nfelia', '/industries', '/work/', '/unknown'], 'fr');

        $this->assertSame(['/work/nfelia', '/industries', '/work'], array_column($links, 'path'));
        $this->assertSame(['Découvrir NFELIA', 'Voir les secteurs que nous servons', 'Voir nos réalisations'], array_column($links, 'label'));
        $this->assertSame('Read the Smart Logistics case study', PraxisAi::links(['/work/smart-logistics'], 'en')[0]['label']);
    }

    public function test_every_page_has_both_languages_and_a_path_the_site_accepts(): void
    {
        foreach (config('praxis_ai.site_map') as $path => $page) {
            $this->assertMatchesRegularExpression('~^/(?:[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*)?$~', $path);
            foreach (['en', 'fr'] as $locale) {
                $this->assertNotSame('', trim($page[$locale]['label'] ?? ''), "$path $locale label");
                $this->assertLessThanOrEqual(80, mb_strlen($page[$locale]['label']), "$path $locale label");
                $this->assertNotSame('', trim($page[$locale]['purpose'] ?? ''), "$path $locale purpose");
            }
        }
    }
}
