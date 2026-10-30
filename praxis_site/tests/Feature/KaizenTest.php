<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** /kaizen and /kaizen/{slug}: JBS Praxis Insights from GET /kaizen/articles, cached 10 minutes with a stale copy. */
class KaizenTest extends TestCase
{
    private function article(string $slug = 'one-ledger', string $title = 'One ledger, not three'): array
    {
        return [
            'slug' => $slug, 'title' => $title, 'category' => 'The Boardroom', 'read_time_minutes' => 6,
            'summary' => 'Three ledgers became one.', 'problem' => 'Month-end took **19 days**.', 'solution' => 'One chart of accounts.',
            'result' => 'Month-end now takes 8 days.', 'published_at' => '2026-10-01T09:00:00+01:00',
        ];
    }

    public function test_the_list_shows_published_articles_with_pagination(): void
    {
        $this->fakeHq(['kaizen/articles*' => Http::response([
            'data' => [$this->article(), $this->article('second', 'Second article')],
            'meta' => ['current_page' => 1, 'last_page' => 3],
        ])]);

        $this->get('/kaizen')->assertOk()
            ->assertSee('JBS Praxis Insights')
            ->assertSee('One ledger, not three')
            ->assertSee('href="' . url('/kaizen/one-ledger') . '"', false)
            ->assertSee('6 min read')
            ->assertSee('Page 1 of 3')
            ->assertSee('href="' . url('/kaizen?page=2') . '"', false)
            ->assertSee('Write an article');
    }

    public function test_the_category_filter_is_sent_to_hq(): void
    {
        $this->fakeHq(['kaizen/articles*' => Http::response(['data' => []])]);

        $this->get('/kaizen?category=The%20Server%20Room')->assertOk()->assertSee('No articles in this section yet.');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/kaizen/articles?page=1&category=The%20Server%20Room'));
    }

    public function test_an_unknown_category_is_ignored(): void
    {
        $this->fakeHq(['kaizen/articles*' => Http::response(['data' => []])]);

        $this->get('/kaizen?category=Nope')->assertOk()->assertSee('No articles yet');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/kaizen/articles?page=1'));
    }

    public function test_an_article_page_shows_the_article_as_safe_text(): void
    {
        $article = $this->article();
        $article['solution'] = 'One chart of accounts. <script>alert(1)</script>';
        $this->fakeHq(['kaizen/articles/one-ledger' => Http::response(['data' => $article])]);

        $this->get('/kaizen/one-ledger')->assertOk()
            ->assertSee('One ledger, not three')
            ->assertSee('<strong>19 days</strong>', false)
            ->assertSee('By JBS Praxis')
            ->assertSee('Published Thursday 1 October 2026')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_page_title_is_escaped_once(): void
    {
        $this->fakeHq(['kaizen/articles/cash' => Http::response(['data' => $this->article('cash', 'Cash & stock <b>')])]);

        $this->get('/kaizen/cash')->assertOk()->assertSee('<title>Cash &amp; stock | JBS Praxis</title>', false);
    }

    public function test_a_persons_name_from_hq_is_not_shown_as_the_byline(): void
    {
        // HQ no longer sends author or designation (docs/SITE_API.md 6.4); an older HQ that still does is ignored
        $article = $this->article();
        $article['author'] = 'Firstname Lastname';
        $article['designation'] = 'Head of Finance';
        $article['byline'] = 'Someone Else';
        $this->fakeHq(['kaizen/articles/one-ledger' => Http::response(['data' => $article])]);

        $this->get('/kaizen/one-ledger')->assertOk()->assertSee('By JBS Praxis')->assertDontSee('Firstname Lastname')->assertDontSee('Head of Finance')->assertDontSee('Someone Else');
    }

    public function test_an_unknown_article_is_a_404(): void
    {
        $this->fakeHq(['kaizen/articles/*' => Http::response(['error' => ['code' => 'not_found']], 404)]);

        $this->get('/kaizen/no-such-article')->assertNotFound()->assertSee('We couldn’t find that page');
    }

    public function test_the_list_is_cached_and_the_saved_copy_is_served_while_hq_is_down(): void
    {
        $this->fakeHq(['kaizen/articles*' => Http::response(['data' => [$this->article()]])]);
        $this->get('/kaizen')->assertOk();
        $this->get('/kaizen')->assertOk();
        Http::assertSentCount(2); // one list read and one settings read: both cached after that

        $this->travel(11)->minutes();
        Http::fake([self::HQ . '/*' => $this->down()]);

        $this->get('/kaizen')->assertOk()
            ->assertSee('One ledger, not three')
            ->assertSee('We couldn’t refresh this page just now, so you’re seeing the last saved copy.')
            ->assertSee('info@jbspraxis.com');
    }

    public function test_an_article_is_served_from_the_saved_copy_while_hq_is_down(): void
    {
        $this->fakeHq(['kaizen/articles/one-ledger' => Http::response(['data' => $this->article()])]);
        $this->get('/kaizen/one-ledger')->assertOk();

        $this->travel(11)->minutes();
        $this->refakeHttp([self::HQ . '/*' => Http::response(['error' => ['code' => 'server']], 500)]);

        $this->get('/kaizen/one-ledger')->assertOk()->assertSee('One ledger, not three')->assertSee('last saved copy');
    }

    public function test_the_list_without_any_copy_shows_the_calm_message(): void
    {
        Http::fake([self::HQ . '/*' => $this->down()]);

        $this->get('/kaizen')->assertStatus(503)->assertSee('This page isn’t available right now');
    }
}
