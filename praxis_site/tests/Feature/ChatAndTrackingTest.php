<?php

namespace Tests\Feature;

use App\Http\Controllers\ChatController;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Praxis AI (POST /chat: last 20 turns and a question counter in the session, links as buttons) and the broadcast email links. */
class ChatAndTrackingTest extends TestCase
{
    /** A Cloudflare edge address (104.16.0.0/13) and one that is not Cloudflare's. */
    private const CLOUDFLARE = ['REMOTE_ADDR' => '104.16.1.1'];

    private const ELSEWHERE = ['REMOTE_ADDR' => '198.51.100.20'];

    private function chatBodies(): array
    {
        return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/chat'))
            ->map(fn ($pair) => json_decode($pair[0]->body(), true))->values()->all();
    }

    // ---- Praxis AI

    public function test_praxis_ai_is_on_every_page(): void
    {
        $this->fakeHq();

        foreach (['/', '/contact', '/feedback'] as $path) {
            $this->get($path)->assertSee('id="chat"', false)
                ->assertSee('Ask Praxis AI')
                ->assertSee('<h2>Praxis AI</h2>', false)
                ->assertSee('action="' . url('/chat') . '"', false)
                ->assertDontSee('JBS Praxis assistant')
                ->assertDontSee('Nexus');
        }
    }

    public function test_a_chat_turn_returns_the_reply_and_keeps_the_conversation(): void
    {
        $this->fakeHq(['chat' => Http::sequence()
            ->push(['data' => ['reply' => 'We start with a free audit and demo.', 'links' => [], 'limited' => false]])
            ->push(['data' => ['reply' => 'It takes about an hour.', 'links' => [], 'limited' => false]])]);

        $this->postJson('/chat', ['chat_message' => 'How do you start?'])->assertOk()
            ->assertJson(['reply' => 'We start with a free audit and demo.', 'links' => [], 'limited' => false]);
        $this->postJson('/chat', ['chat_message' => 'How long is it?'])->assertOk();

        $second = Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/chat'))->last()[0];
        $body = json_decode($second->body(), true);
        $this->assertSame('How long is it?', $body['message']);
        $this->assertSame([
            ['role' => 'user', 'text' => 'How do you start?'],
            ['role' => 'assistant', 'text' => 'We start with a free audit and demo.'],
        ], $body['history'], 'HQ gets role and text only, never the links');
        $this->assertTrue($this->signatureIsValid($second));
        $this->assertCount(4, session(ChatController::SESSION_KEY));
    }

    public function test_every_question_carries_the_session_question_number(): void
    {
        $this->fakeHq(['chat' => Http::response(['data' => ['reply' => 'Noted.', 'links' => [], 'limited' => false]])]);

        foreach (['One?', 'Two?', 'Three?'] as $question) {
            $this->postJson('/chat', ['chat_message' => $question])->assertOk();
        }

        $this->assertSame([1, 2, 3], array_column($this->chatBodies(), 'question_number'));
        $this->assertSame(3, session(ChatController::COUNTER_KEY));
    }

    public function test_the_counter_keeps_counting_after_old_turns_are_dropped(): void
    {
        config(['site.limits.chat_per_minute' => 100]);
        $this->fakeHq(['chat' => Http::response(['data' => ['reply' => 'Noted.', 'links' => [], 'limited' => false]])]);

        for ($i = 1; $i <= 12; $i++) {
            $this->postJson('/chat', ['chat_message' => "Question $i"])->assertOk();
        }

        $turns = session(ChatController::SESSION_KEY);
        $this->assertCount(20, $turns);
        $this->assertSame('Question 3', $turns[0]['text']);
        $last = $this->chatBodies()[11];
        $this->assertCount(20, $last['history']);
        $this->assertSame(12, $last['question_number'], 'HQ counts the whole conversation, not the turns kept');
    }

    public function test_a_question_that_fails_still_counts(): void
    {
        $this->fakeHq(['chat' => Http::sequence()
            ->push(['error' => ['code' => 'server']], 500)
            ->push(['data' => ['reply' => 'Back again.', 'links' => [], 'limited' => false]])]);

        $this->postJson('/chat', ['chat_message' => 'First?'])->assertStatus(503);
        $this->postJson('/chat', ['chat_message' => 'Second?'])->assertOk();

        $this->assertSame([1, 2], array_column($this->chatBodies(), 'question_number'));
    }

    public function test_the_replys_links_are_buttons_and_only_same_site_paths_are_kept(): void
    {
        $this->fakeHq(['chat' => Http::response(['data' => ['reply' => 'Start with a free audit and demo.', 'limited' => false, 'links' => [
            ['label' => 'Book a free audit and demo', 'path' => '/schedule'],
            ['label' => 'Somewhere else', 'path' => 'https://example.com/schedule'],
            ['label' => 'Protocol-relative', 'path' => '//example.com'],
            ['label' => 'Script', 'path' => 'javascript:alert(1)'],
            ['label' => 'Up a level', 'path' => '/../etc'],
            ['label' => 'Write for us', 'path' => '/kaizen/write'],
            ['label' => 'The cost calculator', 'path' => '/cost-calculator'],
            ['label' => 'A fourth good one', 'path' => '/careers'],
        ]]])]);

        $this->postJson('/chat', ['chat_message' => 'How do I start?'])->assertOk()->assertExactJson([
            'reply' => 'Start with a free audit and demo.',
            'links' => [
                ['label' => 'Book a free audit and demo', 'path' => '/schedule'],
                ['label' => 'Write for us', 'path' => '/kaizen/write'],
                ['label' => 'The cost calculator', 'path' => '/cost-calculator'],
            ],
            'limited' => false,
        ]);

        // Without JavaScript the same buttons are drawn from the session
        $this->get('/contact')->assertOk()
            ->assertSee('<span class="chat-links" role="group" aria-label="Pages that may help">', false)
            ->assertSee('<a class="btn btn-secondary btn-sm" href="' . url('/schedule') . '">Book a free audit and demo</a>', false)
            ->assertSee('href="' . url('/kaizen/write') . '"', false)
            ->assertDontSee('example.com', false)
            ->assertDontSee('javascript:', false)
            ->assertDontSee('<a class="btn btn-secondary btn-sm" href="' . url('/careers') . '"', false);
    }

    public function test_hqs_calm_limit_message_is_shown_as_the_reply(): void
    {
        $limit = 'You’ve reached the number of questions Praxis AI can answer for now. Book a free audit and demo to talk to our team.';
        $this->fakeHq(['chat' => Http::response(['data' => ['reply' => $limit, 'links' => [['label' => 'Book a free audit and demo', 'path' => '/schedule']], 'limited' => true]])]);

        $this->postJson('/chat', ['chat_message' => 'One more?'])->assertOk()->assertJson(['reply' => $limit, 'limited' => true]);

        $this->from('/contact')->post('/chat', ['chat_message' => 'And another?'])->assertRedirect(url('/contact') . '#chat');
        $this->get('/contact')->assertSee('You’ve reached the number of questions Praxis AI can answer for now.');
    }

    public function test_the_chat_works_without_javascript(): void
    {
        $this->fakeHq(['chat' => Http::response(['data' => ['reply' => 'Book a free audit and demo to start.', 'links' => [], 'limited' => false]])]);

        $this->from('/contact')->post('/chat', ['chat_message' => 'Where do I start?'])->assertRedirect(url('/contact') . '#chat');

        $this->get('/contact')->assertSee('<details class="chat" id="chat"  open', false)
            ->assertSee('Where do I start?')
            ->assertSee('Book a free audit and demo to start.')
            ->assertSee('<span class="who">Praxis AI</span>', false);
    }

    public function test_an_empty_question_is_refused_locally(): void
    {
        $this->fakeHq();

        $this->postJson('/chat', ['chat_message' => ''])->assertStatus(422)->assertJson(['message' => 'Type a question first.']);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/chat'));
    }

    public function test_the_chat_says_calmly_when_hq_is_down(): void
    {
        $this->fakeHq(['chat' => Http::response(['error' => ['code' => 'server']], 500)]);

        $this->postJson('/chat', ['chat_message' => 'Hello'])->assertStatus(503)
            ->assertJson(['message' => 'Praxis AI isn’t available right now. Try again in a few minutes, or email us at fallback@jbspraxis.com.']);
        $this->assertEmpty(session(ChatController::SESSION_KEY, []));
    }

    public function test_hqs_429_on_chat_shows_hqs_calm_message(): void
    {
        $this->fakeHq(['chat' => Http::response(['error' => ['code' => 'too_many', 'retry_after' => 60, 'message' => 'Praxis AI has had many questions from your network. Try again in an hour.']], 429)]);

        $this->postJson('/chat', ['chat_message' => 'Hello'])->assertStatus(429)
            ->assertJson(['message' => 'Praxis AI has had many questions from your network. Try again in an hour.']);
    }

    public function test_the_sites_own_chat_limit_answers_calmly_in_json(): void
    {
        config(['site.limits.chat_per_minute' => 2]);
        $this->fakeHq(['chat' => Http::response(['data' => ['reply' => 'Noted.', 'links' => [], 'limited' => false]])]);

        $this->postJson('/chat', ['chat_message' => 'One'])->assertOk();
        $this->postJson('/chat', ['chat_message' => 'Two'])->assertOk();
        $this->postJson('/chat', ['chat_message' => 'Three'])->assertStatus(429)
            ->assertJson(['message' => 'You’ve sent several requests in a short time. Wait a minute and try again.']);
    }

    public function test_the_chat_route_allows_php_to_wait_for_hq(): void
    {
        $this->assertSame(35, config('hq_api.timeouts.chat'));
        $this->fakeHq(['chat' => function ($request, array $options) {
            $this->assertSame(35, $options['timeout'], 'Praxis AI waits 35 s');
            $this->assertFalse($options['allow_redirects']);

            return Http::response(['data' => ['reply' => 'Noted.', 'links' => [], 'limited' => false]]);
        }]);

        $this->postJson('/chat', ['chat_message' => 'Hello'])->assertOk();
        Http::assertSentCount(1);
    }

    // ---- tracking

    public function test_the_open_pixel_reports_to_hq_and_returns_a_png(): void
    {
        $this->fakeHq(['tracking/open/*' => Http::response(['data' => ['recorded' => true]])]);

        $response = $this->get('/tracking/open/12/34')->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->assertSame(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='), $response->getContent());
        $this->assertEmpty($response->headers->getCookies(), 'no session cookie on the pixel');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/tracking/open/12/34') && $r->method() === 'GET' && $this->signatureIsValid($r));
    }

    public function test_the_open_pixel_passes_the_links_token_to_hq(): void
    {
        $this->fakeHq(['tracking/open/*' => Http::response(['data' => ['recorded' => true]])]);
        $t = str_repeat('ab12', 8);

        $this->get('/tracking/open/c-1/u-2?t=' . $t)->assertOk();
        $this->get('/tracking/open/c-1/u-2?t=not-a-token')->assertOk();

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/tracking/open/c-1/u-2?t=' . $t) && $this->signatureIsValid($r));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/tracking/open/c-1/u-2'), 'a malformed token is not passed on');
    }

    public function test_the_open_pixel_passes_on_cloudflares_country_only_from_cloudflare(): void
    {
        $this->fakeHq(['tracking/open/*' => Http::response(['data' => ['recorded' => true]])]);

        $this->withServerVariables(self::CLOUDFLARE)->get('/tracking/open/12/34', ['CF-IPCountry' => 'NG', 'CF-Connecting-IP' => '203.0.113.9'])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/tracking/open/12/34?country=NG')
            && $r->header('X-Site-Visitor-Ip') === ['203.0.113.9'] && $this->signatureIsValid($r));

        $this->withServerVariables(self::ELSEWHERE)->get('/tracking/open/56/78', ['CF-IPCountry' => 'ZZ', 'CF-Connecting-IP' => '203.0.113.9'])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/tracking/open/56/78')
            && $r->header('X-Site-Visitor-Ip') === ['198.51.100.20']);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'country=ZZ'));
    }

    public function test_past_the_pixel_limit_the_image_is_still_returned_without_calling_hq(): void
    {
        $this->fakeHq(['tracking/open/*' => Http::response(['data' => ['recorded' => true]])]);
        config(['site.limits.pixel_per_minute' => 2]);

        // A mail image proxy fetching for several readers from one IPv6 /64
        foreach (['2001:db8:5::1', '2001:db8:5::2', '2001:db8:5::3'] as $i => $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->get('/tracking/open/c-1/u-' . $i)
                ->assertOk()->assertHeader('Content-Type', 'image/png');
        }

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/tracking/open/c-1/u-1'));
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/tracking/open/c-1/u-2'));
    }

    public function test_the_open_pixel_is_returned_even_when_hq_is_down(): void
    {
        Http::fake([self::HQ . '/*' => $this->down()]);

        $this->get('/tracking/open/12/34')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_unsubscribe_needs_a_click_then_calls_hq(): void
    {
        $this->fakeHq(['tracking/unsubscribe/*' => Http::response(['data' => ['unsubscribed' => true]])]);

        $this->get('/tracking/unsubscribe/42')->assertOk()->assertSee('Unsubscribe from JBS Praxis emails?');
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/tracking/unsubscribe/'));

        $this->from('/tracking/unsubscribe/42')->post('/tracking/unsubscribe/42')->assertRedirect('/tracking/unsubscribe/42');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/tracking/unsubscribe/42') && $r->method() === 'POST'
            && $r->body() === '{}' && $this->signatureIsValid($r));

        $this->get('/tracking/unsubscribe/42')->assertSee('You’re unsubscribed');
    }

    public function test_unsubscribe_passes_the_campaign_and_token_from_the_email_link(): void
    {
        $this->fakeHq(['tracking/unsubscribe/*' => Http::response(['data' => ['unsubscribed' => true]])]);
        $t = str_repeat('0f', 16);

        $this->get("/tracking/unsubscribe/u-42?campaign=c-7&t=$t")->assertOk()
            ->assertSee('name="campaign" value="c-7"', false)
            ->assertSee('name="t" value="' . $t . '"', false);

        $this->from("/tracking/unsubscribe/u-42?campaign=c-7&t=$t")->post('/tracking/unsubscribe/u-42', ['campaign' => 'c-7', 't' => $t])
            ->assertRedirect('/tracking/unsubscribe/u-42');

        $this->assertSame(['campaign' => 'c-7', 't' => $t], $this->sentJson('/tracking/unsubscribe/u-42'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/tracking/unsubscribe/u-42') && $this->signatureIsValid($r));
    }

    public function test_an_unsubscribe_link_hq_refuses_gets_a_calm_way_out(): void
    {
        $this->fakeHq(['tracking/unsubscribe/*' => Http::response(['error' => ['code' => 'not_found']], 404)]);
        $t = str_repeat('0f', 16);
        $this->get("/tracking/unsubscribe/u-42?campaign=c-7&t=$t")->assertOk(); // settings are read with the page

        $this->from("/tracking/unsubscribe/u-42?campaign=c-7&t=$t")->post('/tracking/unsubscribe/u-42', ['campaign' => 'c-7', 't' => $t])
            ->assertRedirect("/tracking/unsubscribe/u-42?campaign=c-7&t=$t");

        $this->get("/tracking/unsubscribe/u-42?campaign=c-7&t=$t")
            ->assertSee('We couldn’t use this unsubscribe link. Email us at info@jbspraxis.com and we’ll remove your address.');
    }

    public function test_unsubscribe_while_hq_is_down_keeps_the_confirm_page_with_a_calm_message(): void
    {
        $this->fakeHq(['tracking/unsubscribe/*' => $this->down()]);

        $this->from('/tracking/unsubscribe/42')->post('/tracking/unsubscribe/42')->assertRedirect('/tracking/unsubscribe/42');

        $this->get('/tracking/unsubscribe/42')->assertSee('We couldn’t send this just now')->assertSee('Unsubscribe from JBS Praxis emails?');
    }
}
