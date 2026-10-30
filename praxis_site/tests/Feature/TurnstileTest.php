<?php

namespace Tests\Feature;

use App\Http\Middleware\FormGuard;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cloudflare Turnstile behind TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY (docs/SITE_API.md 6.6), checked
 * with Cloudflare (faked here) before anything is sent to HQ. Off while either key is empty.
 */
class TurnstileTest extends TestCase
{
    private const VERIFY = 'challenges.cloudflare.com/turnstile/v0/siteverify';

    private const CONTACT = ['full_name' => 'Ada Test', 'email' => 'ada@example.com', 'company_name' => 'Example Ltd', 'message' => 'Hello there.'];

    private function turnOn(): void
    {
        config(['services.turnstile.site_key' => '1x00000000000000000000AA', 'services.turnstile.secret_key' => 'test-turnstile-secret']);
    }

    private function sentTo(string $needle): int
    {
        return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), $needle))->count();
    }

    // ---- off (the default)

    public function test_with_empty_keys_there_is_no_widget_no_script_and_no_check(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);

        $response = $this->get('/contact')->assertOk()
            ->assertDontSee('cf-turnstile', false)
            ->assertDontSee('challenges.cloudflare.com', false);
        $this->assertStringNotContainsString('challenges.cloudflare.com', $response->headers->get('Content-Security-Policy'));

        $this->from('/contact')->post('/contact', self::CONTACT)->assertSessionHas('sent', 'contact');

        $this->assertSame(1, $this->sentTo('/enquiries'));
        $this->assertSame(0, $this->sentTo(self::VERIFY));
    }

    public function test_one_key_alone_keeps_it_off(): void
    {
        config(['services.turnstile.site_key' => '1x00000000000000000000AA', 'services.turnstile.secret_key' => '']);
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);

        $this->get('/contact')->assertDontSee('cf-turnstile', false);
        $this->from('/contact')->post('/contact', self::CONTACT);

        $this->assertSame(0, $this->sentTo(self::VERIFY));
        $this->assertSame(1, $this->sentTo('/enquiries'));
    }

    // ---- on

    public function test_with_both_keys_every_form_shows_the_widget_and_the_csp_allows_cloudflare(): void
    {
        $this->turnOn();
        $this->fakeHq([
            'calculator/reference' => Http::response(['data' => ['industries' => ['Banking'], 'systems' => [], 'bottlenecks' => [], 'currencies' => []]]),
            'booking/slots*' => Http::response(['data' => ['month' => now('Africa/Lagos')->format('Y-m'), 'days' => []]]),
        ]);

        foreach (['/contact', '/schedule', '/cost-calculator', '/kaizen/write', '/services', '/careers', '/feedback', '/tracking/unsubscribe/42'] as $path) {
            $response = $this->get($path)->assertOk()
                ->assertSee('<div class="cf-turnstile" data-sitekey="1x00000000000000000000AA"', false)
                ->assertSee('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>', false)
                ->assertDontSee('test-turnstile-secret');
            $csp = $response->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("script-src 'self' https://challenges.cloudflare.com", $csp, $path);
            $this->assertStringContainsString("frame-src 'self' https://challenges.cloudflare.com", $csp, $path);
            $this->assertStringNotContainsString('unsafe-inline', $csp);
        }
    }

    public function test_a_confirmed_token_is_checked_with_cloudflare_before_hq_is_called(): void
    {
        $this->turnOn();
        Http::fake([
            self::VERIFY => Http::response(['success' => true, 'error-codes' => []]),
            self::HQ . '/enquiries' => Http::response(['data' => ['received' => true]], 201),
            self::HQ . '/settings' => Http::response(['data' => $this->settingsData()]),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->from('/contact')
            ->post('/contact', self::CONTACT + ['cf-turnstile-response' => 'good-token'])
            ->assertRedirect('/contact')->assertSessionHas('sent', 'contact');

        $order = Http::recorded()->map(fn ($pair) => $pair[0]->url())->values()->all();
        $verify = array_search('https://' . self::VERIFY, $order, true);
        $hq = array_search('http://' . self::HQ . '/enquiries', $order, true);
        $this->assertIsInt($verify);
        $this->assertIsInt($hq);
        $this->assertLessThan($hq, $verify, 'Cloudflare first, then HQ');

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://' . self::VERIFY
            && $r['secret'] === 'test-turnstile-secret' && $r['response'] === 'good-token' && $r['remoteip'] === '198.51.100.20');
        $this->assertArrayNotHasKey('cf-turnstile-response', $this->sentJson('/enquiries'));
    }

    public function test_a_refused_or_missing_token_never_reaches_hq(): void
    {
        $this->turnOn();
        Http::fake([
            self::VERIFY => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
            self::HQ . '/settings' => Http::response(['data' => $this->settingsData()]),
        ]);

        $this->from('/contact')->post('/contact', self::CONTACT + ['cf-turnstile-response' => 'bad-token'])
            ->assertRedirect('/contact')
            ->assertSessionHas('hq_error', 'We couldn’t complete the security check. Wait for it to finish, then send the form again.')
            ->assertSessionHasInput('full_name', 'Ada Test')
            ->assertSessionMissing('sent');
        $this->from('/contact')->post('/contact', self::CONTACT)->assertSessionHas('hq_error');

        $this->assertSame(0, $this->sentTo('/enquiries'));
        $this->assertSame(1, $this->sentTo(self::VERIFY), 'no token: Cloudflare is not even asked');
        $this->get('/contact')->assertSee('We couldn’t complete the security check.');
    }

    public function test_cloudflare_not_answering_counts_as_a_failed_check(): void
    {
        $this->turnOn();
        Http::fake([
            self::VERIFY => $this->down(),
            self::HQ . '/settings' => Http::response(['data' => $this->settingsData()]),
        ]);

        $this->from('/contact')->post('/contact', self::CONTACT + ['cf-turnstile-response' => 'token'])->assertSessionHas('hq_error');

        $this->assertSame(0, $this->sentTo('/enquiries'));
    }

    public function test_chat_checks_once_then_trusts_the_session_for_a_while(): void
    {
        $this->turnOn();
        Http::fake([
            self::VERIFY => Http::response(['success' => true]),
            self::HQ . '/chat' => Http::response(['data' => ['reply' => 'Hello.', 'links' => [], 'limited' => false]]),
            self::HQ . '/settings' => Http::response(['data' => $this->settingsData()]),
        ]);

        $this->get('/')->assertSee('cf-turnstile', false);
        $this->postJson('/chat', ['chat_message' => 'First?', 'cf-turnstile-response' => 'token-1'])->assertOk();
        $this->postJson('/chat', ['chat_message' => 'Second?'])->assertOk();
        $this->assertSame(1, $this->sentTo(self::VERIFY));
        $this->assertSame(2, $this->sentTo('/chat'));
        $this->assertNotNull(session(FormGuard::CHAT_CLEARANCE));

        // After 30 minutes the chat asks again
        $this->travel(31)->minutes();
        $this->postJson('/chat', ['chat_message' => 'Third?'])->assertStatus(422)
            ->assertJson(['message' => 'We couldn’t complete the security check. Wait for it to finish, then send the form again.']);
        $this->assertSame(2, $this->sentTo('/chat'));
    }

    public function test_a_failed_chat_check_answers_json_and_sends_nothing(): void
    {
        $this->turnOn();
        Http::fake([
            self::VERIFY => Http::response(['success' => false]),
            self::HQ . '/settings' => Http::response(['data' => $this->settingsData()]),
        ]);

        $this->postJson('/chat', ['chat_message' => 'Hello?', 'cf-turnstile-response' => 'bad'])->assertStatus(422);

        $this->assertSame(0, $this->sentTo('/chat'));
        $this->assertNull(session('chat.questions'));
    }
}
