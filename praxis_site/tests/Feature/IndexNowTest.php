<?php

namespace Tests\Feature;

use App\Support\Sitemap;
use App\Support\Work;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** IndexNow: the key file at /{key}.txt and `php artisan site:indexnow` (never sent while the site is not indexed). */
class IndexNowTest extends TestCase
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** The pages and case studies, each in English and French (what is sent when HQ lists no article) */
    private function pageCount(): int
    {
        return 2 * (count(Sitemap::PAGES) + count(Work::slugs()));
    }

    private function article(string $slug): array
    {
        return ['slug' => $slug, 'title' => 'One ledger, not three', 'category' => 'The Boardroom', 'published_at' => '2026-10-01T09:00:00+01:00'];
    }

    /** HQ with one published article, and IndexNow answering $status */
    private function fakeHqAndIndexNow(int $status = 200): void
    {
        $this->fakeHq(['kaizen/articles*' => Http::response(['data' => [$this->article('one-ledger')], 'meta' => ['current_page' => 1, 'last_page' => 1]])]);
        Http::fake([self::ENDPOINT => Http::response('', $status)]);
    }

    /** The JSON body of the one request sent to IndexNow */
    private function submitted(): array
    {
        $sent = Http::recorded(fn (HttpRequest $r) => $r->url() === self::ENDPOINT);
        $this->assertCount(1, $sent, 'one request to IndexNow');

        return json_decode($sent->first()[0]->body(), true);
    }

    public function test_the_key_is_32_hex_characters_and_its_file_is_served(): void
    {
        $this->fakeHq();
        $key = config('site.indexnow.key');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);

        $response = $this->get("/{$key}.txt")->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame($key, $response->getContent());
        $this->assertEmpty($response->headers->getCookies(), 'no session cookie for the key file');

        // Only our key is found; the crawler files keep their own routes
        $this->get('/0123456789abcdef0123456789abcdef.txt')->assertNotFound();
        $this->get('/robots.txt')->assertOk()->assertSee('User-agent: *');
        $this->get('/llms.txt')->assertOk();
    }

    public function test_nothing_is_sent_while_the_site_is_not_indexed(): void
    {
        Http::fake();

        $this->artisan('site:indexnow')->expectsOutputToContain('nothing was sent to IndexNow')->assertSuccessful();
        $this->artisan('site:indexnow', ['urls' => ['/careers']])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_sitemaps_addresses_are_sent_on_jbspraxis_com(): void
    {
        config(['site.noindex' => false]);
        $this->fakeHqAndIndexNow();
        $key = config('site.indexnow.key');

        $this->artisan('site:indexnow')->expectsOutputToContain('Sent ' . ($this->pageCount() + 1) . ' addresses to IndexNow (200 OK).')->assertSuccessful();

        $body = $this->submitted();
        $this->assertSame('jbspraxis.com', $body['host']);
        $this->assertSame($key, $body['key']);
        $this->assertSame("https://jbspraxis.com/{$key}.txt", $body['keyLocation']);
        foreach (['/', '/fr', '/careers', '/fr/careers', '/how-we-work', '/fr/how-we-work', '/work', '/fr/industries', '/work/nfelia', '/fr/work/nfelia', '/kaizen/one-ledger'] as $path) {
            $this->assertContains('https://jbspraxis.com' . $path, $body['urlList']);
        }

        // Exactly what the sitemap lists when it is read on jbspraxis.com
        preg_match_all('~<loc>([^<]+)</loc>~', $this->get('https://jbspraxis.com/sitemap.xml')->assertOk()->getContent(), $loc);
        $this->assertSame($loc[1], $body['urlList']);

        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::ENDPOINT && $r->method() === 'POST'
            && $r->header('Content-Type')[0] === 'application/json; charset=utf-8');
    }

    public function test_the_pages_are_still_sent_when_hq_is_down_and_the_command_says_so(): void
    {
        config(['site.noindex' => false]);
        $this->fakeHq(['kaizen/articles*' => $this->down()]);
        Http::fake([self::ENDPOINT => Http::response('', 200)]);

        $this->artisan('site:indexnow')
            ->expectsOutputToContain('The Insights could not be read from HQ: only the pages are listed.')
            ->expectsOutputToContain('Sent ' . $this->pageCount() . ' addresses to IndexNow (200 OK).')
            ->assertSuccessful();

        $urls = $this->submitted()['urlList'];
        $this->assertContains('https://jbspraxis.com/fr/privacy', $urls);
        // No articles: /kaizen/write is a page of its own (Sitemap::PAGES), so it is listed either way
        $this->assertSame([], preg_grep('~/kaizen/(?!write$).~', $urls));
        $this->assertContains('https://jbspraxis.com/kaizen/write', $urls);
    }

    public function test_given_paths_and_addresses_are_sent_as_they_are(): void
    {
        config(['site.noindex' => false]);
        $this->fakeHqAndIndexNow();

        $this->artisan('site:indexnow', ['urls' => ['/careers', 'fr/careers', 'https://jbspraxis.com/kaizen/one-ledger', '/careers']])
            ->expectsOutputToContain('Sent 3 addresses')->assertSuccessful();

        $this->assertSame(
            ['https://jbspraxis.com/careers', 'https://jbspraxis.com/fr/careers', 'https://jbspraxis.com/kaizen/one-ledger'],
            $this->submitted()['urlList']
        );
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'kaizen/articles'));
    }

    public function test_addresses_elsewhere_are_refused_before_anything_is_sent(): void
    {
        config(['site.noindex' => false]);
        Http::fake();

        $this->artisan('site:indexnow', ['urls' => ['/careers', 'https://example.com/careers']])
            ->expectsOutputToContain('Only addresses on jbspraxis.com can be sent')->assertFailed();
        $this->artisan('site:indexnow', ['urls' => ['https://next.jbspraxis.com/']])->assertFailed();
        $this->artisan('site:indexnow', ['urls' => ['ftp://jbspraxis.com/file']])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_a_key_from_the_environment_is_used_everywhere(): void
    {
        config(['site.noindex' => false, 'site.indexnow.key' => 'praxis-key-2026']);
        $this->fakeHqAndIndexNow();
        $default = require base_path('config/site.php');

        $this->assertSame('praxis-key-2026', $this->get('/praxis-key-2026.txt')->assertOk()->getContent());
        $this->get('/' . $default['indexnow']['key'] . '.txt')->assertNotFound();

        $this->artisan('site:indexnow', ['urls' => ['/']])->assertSuccessful();
        $this->assertSame('https://jbspraxis.com/praxis-key-2026.txt', $this->submitted()['keyLocation']);
    }

    public function test_a_dry_run_lists_the_addresses_and_sends_nothing(): void
    {
        config(['site.noindex' => false]);
        $this->fakeHqAndIndexNow();

        $this->artisan('site:indexnow', ['--dry-run' => true])
            ->expectsOutput('https://jbspraxis.com/fr/security')
            ->expectsOutputToContain(($this->pageCount() + 1) . ' addresses; nothing was sent')
            ->assertSuccessful();

        Http::assertNotSent(fn (HttpRequest $r) => $r->url() === self::ENDPOINT);
    }

    public function test_indexnows_answers_are_reported(): void
    {
        config(['site.noindex' => false]);
        $key = config('site.indexnow.key');

        $this->fakeHqAndIndexNow(202);
        $this->artisan('site:indexnow', ['urls' => ['/']])->expectsOutputToContain('still checking the key')->assertSuccessful();

        foreach ([
            403 => "https://jbspraxis.com/{$key}.txt must show it",
            422 => 'each must be on jbspraxis.com',
            429 => 'try again later',
            500 => 'IndexNow answered HTTP 500.',
        ] as $status => $message) {
            $this->refakeHttp([self::ENDPOINT => Http::response('', $status)]);
            $this->artisan('site:indexnow', ['urls' => ['/']])->expectsOutputToContain($message)->assertFailed();
        }

        $this->refakeHttp([self::ENDPOINT => fn () => throw new ConnectionException('Connection timed out')]);
        $this->artisan('site:indexnow', ['urls' => ['/']])->expectsOutputToContain('IndexNow could not be reached')->assertFailed();
    }
}
