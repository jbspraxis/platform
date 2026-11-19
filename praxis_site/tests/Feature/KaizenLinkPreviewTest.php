<?php

namespace Tests\Feature;

use App\Support\Articles;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A shared Kaizen link (LinkedIn, X, WhatsApp) shows what the article says, as an article (owner, 19 Nov 2026):
 * og:description and the meta description come from its summary, not the site's generic line.
 */
class KaizenLinkPreviewTest extends TestCase
{
    public function test_an_article_page_describes_itself_and_is_an_article(): void
    {
        Http::fake([self::HQ . '/kaizen/articles/*' => Http::response(['data' => [
            'slug' => 'your-ai-assistant-is-a-data-export-channel',
            'title' => 'Your AI Assistant Is a Data Export Channel. Govern It Like One.',
            'category' => 'boardroom',
            'summary' => "An AI assistant connected to live data can turn every prompt into a **cross-border transfer**.\nFor a Nigerian pharmaceutical or Cameroonian bank, that is a board liability.",
            'published_at' => '2026-11-07 09:00:00',
        ]])]);

        $page = $this->get('/kaizen/your-ai-assistant-is-a-data-export-channel')->assertOk();
        $page->assertSee('<meta property="og:type" content="article">', false);
        $page->assertSee('<meta property="og:description" content="An AI assistant connected to live data can turn every prompt into a cross-border transfer. For a Nigerian pharmaceutical or Cameroonian bank, that is a board liability.">', false);
        $page->assertDontSee('<meta property="og:description" content="' . e(__('pages.home.description')) . '">', false);
    }

    public function test_other_pages_keep_the_site_line_as_a_website(): void
    {
        Http::fake([self::HQ . '/*' => Http::response(['data' => []])]);

        $this->get('/how-we-work')->assertOk()->assertSee('<meta property="og:type" content="website">', false);
    }

    public function test_a_long_summary_is_cut_at_a_word_and_markup_never_leaks(): void
    {
        $long = str_repeat('Controls are architecture and contracts are promises. ', 10);
        $plain = Articles::plain($long);
        $this->assertLessThanOrEqual(201, mb_strlen($plain));
        $this->assertStringEndsWith('…', $plain);
        $this->assertStringNotContainsString('  ', $plain);
        $this->assertSame('Bold and plain', Articles::plain("**Bold**\n\nand   plain"));
        $this->assertSame('', Articles::plain(null));
    }
}
