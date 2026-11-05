<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * /profile/{token}: a tracked share link for the company profile (docs/COMPANY_PROFILE.md section 14).
 *
 * The page's job is narrow: pass the PDF straight through while the link is open, and otherwise say the link
 * has ended in the same words whatever the reason, so a stranger trying a token learns nothing. It is never
 * indexed and never cached.
 */
class ProfileShareTest extends TestCase
{
    private function token(string $fill = 'a'): string
    {
        return str_repeat($fill, 48);
    }

    public function test_an_open_link_streams_the_pdf_and_tells_robots_to_stay_away(): void
    {
        $this->fakeHq(['profile/*' => Http::response('%PDF-1.4 the profile', 200, ['Content-Type' => 'application/pdf'])]);

        $response = $this->get('/profile/' . $this->token())->assertOk();

        $this->assertSame('%PDF-1.4 the profile', $response->getContent());
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_link_that_has_ended_says_so_in_the_same_words_whatever_the_reason(): void
    {
        $pages = [];

        foreach (['revoked', 'expired', 'not_found'] as $state) {
            $this->refakeHttp([
                self::HQ . '/profile/*' => Http::response(['error' => ['code' => 'link_ended', 'state' => $state]], 410),
                self::HQ . '/settings' => Http::response(['data' => $this->settingsData()]),
            ]);

            $response = $this->get('/profile/' . $this->token())->assertStatus(410);

            $response->assertSee('This link has ended');
            $response->assertSee('noindex, nofollow', false);
            $this->assertStringNotContainsString('%PDF', $response->getContent());

            // Per-request tokens differ by design; everything else must not
            $pages[$state] = preg_replace(
                ['/name="_token" value="[^"]+"/', '/name="form_ts" value="[^"]+"/'],
                ['_token', 'form_ts'],
                (string) $response->getContent(),
            );
        }

        // The strongest form of "learns nothing": the three answers are the same page, byte for byte
        $this->assertSame($pages['revoked'], $pages['expired'], 'revoked and expired read alike');
        $this->assertSame($pages['revoked'], $pages['not_found'], 'a token nobody was given reads alike too');
    }

    public function test_the_french_page_says_it_in_french(): void
    {
        $this->fakeHq(['profile/*' => Http::response(['error' => ['code' => 'link_ended', 'state' => 'expired']], 410)]);

        $this->get('/fr/profile/' . $this->token())->assertStatus(410)->assertSee('Ce lien a expiré');
    }

    public function test_a_token_of_the_wrong_shape_is_not_a_page_at_all(): void
    {
        $this->fakeHq();

        foreach ([str_repeat('a', 47), str_repeat('a', 49), 'not-a-token', 'a!' . str_repeat('a', 46)] as $bad) {
            $this->get('/profile/' . $bad)->assertNotFound();
        }

        // Nothing of that reached HQ
        $this->assertCount(0, Http::recorded(fn ($r) => str_contains($r->url(), '/profile/')));
    }

    public function test_too_many_opens_are_answered_calmly_rather_than_with_the_pdf(): void
    {
        $this->fakeHq(['profile/*' => Http::response(['error' => ['code' => 'too_many', 'retry_after' => 300]], 429)]);

        $response = $this->get('/profile/' . $this->token())->assertStatus(429);

        $response->assertSee('resting');
        $this->assertStringNotContainsString('%PDF', $response->getContent());
    }

    public function test_the_route_is_limited_so_a_found_link_cannot_be_pulled_in_bulk(): void
    {
        $this->fakeHq(['profile/*' => Http::response('%PDF-1.4 the profile', 200, ['Content-Type' => 'application/pdf'])]);

        $limit = (int) config('site.limits.profile_share_per_minute', 10);
        for ($i = 0; $i < $limit; $i++) {
            $this->get('/profile/' . $this->token())->assertOk();
        }

        $this->get('/profile/' . $this->token())->assertStatus(429);
    }
}
