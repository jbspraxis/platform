<?php

namespace Tests\Feature;

use App\Support\FormStamp;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * App\Http\Middleware\FormGuard on every form (docs/SITE_API.md 6.6): the honeypot and the signed
 * page-load time (3 seconds minimum). A bot gets the normal success answer and nothing reaches HQ.
 */
class FormGuardTest extends TestCase
{
    private const CONTACT = ['full_name' => 'Ada Test', 'email' => 'ada@example.com', 'company_name' => 'Example Ltd', 'message' => 'Hello there.'];

    private function enquiriesSent(): int
    {
        return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/enquiries'))->count();
    }

    public function test_every_form_carries_a_signed_page_load_time(): void
    {
        $this->fakeHq([
            'calculator/reference' => Http::response(['data' => ['industries' => ['Banking'], 'systems' => [], 'bottlenecks' => [], 'currencies' => []]]),
            'booking/slots*' => Http::response(['data' => ['month' => now('Africa/Lagos')->format('Y-m'), 'days' => []]]),
        ]);

        // The page's own form and the Praxis AI form, so two. A page that also offers its form as a
        // conversation carries a third real form: the calculator's own dialog (partials/calculator-dialog),
        // or the shared one (components/agent-dialog). Both are real forms and both must be guarded.
        $forms = [
            '/contact' => 3, '/schedule' => 2, '/cost-calculator' => 3, '/kaizen/write' => 2,
            '/services' => 2, '/careers' => 3, '/feedback' => 3, '/tracking/unsubscribe/42' => 2,
        ];

        foreach ($forms as $path => $expected) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame($expected, preg_match_all('/<input type="hidden" name="form_ts" value="(\d+\.[0-9a-f]{40})">/', $html, $m), $path);
            foreach ($m[1] as $stamp) {
                $this->assertNotNull(FormStamp::age($stamp), $path . ': every stamp is signed with the app key');
            }
        }
    }

    public function test_a_submit_with_a_stamp_older_than_3_seconds_is_sent(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);

        $this->from('/contact')->post('/contact', self::CONTACT + ['form_ts' => FormStamp::make(now()->subSeconds(3))])
            ->assertRedirect('/contact')->assertSessionHas('sent', 'contact');

        $this->assertSame(1, $this->enquiriesSent());
    }

    public function test_a_submit_under_3_seconds_looks_sent_but_reaches_nobody(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);

        $this->from('/contact')->post('/contact', self::CONTACT + ['form_ts' => FormStamp::make(now()->subSeconds(2))])
            ->assertRedirect('/contact')->assertSessionHas('sent', 'contact');

        $this->assertSame(0, $this->enquiriesSent());
        $this->get('/contact')->assertSee('Thank you. We’ve received your message');
    }

    public function test_a_missing_or_forged_stamp_is_treated_like_the_honeypot(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);
        $old = now()->subMinute()->getTimestamp();

        foreach (['', 'nonsense', $old . '.' . str_repeat('0', 40), $old . '.' . substr(hash('sha256', 'x'), 0, 40), ['array']] as $stamp) {
            $this->from('/contact')->post('/contact', self::CONTACT + ['form_ts' => $stamp])->assertSessionHas('sent', 'contact');
        }

        $this->assertSame(0, $this->enquiriesSent());
    }

    public function test_a_stamp_cannot_be_moved_to_another_time(): void
    {
        $stamp = FormStamp::make(now());
        [, $mac] = explode('.', $stamp);

        $this->assertNull(FormStamp::age((now()->getTimestamp() - 60) . '.' . $mac));
        $this->assertSame(0, FormStamp::age($stamp));
    }

    public function test_a_filled_honeypot_looks_sent_but_reaches_nobody(): void
    {
        $this->fakeHq();

        $this->from('/contact')->post('/contact', self::CONTACT + ['website' => 'http://spam.example'])
            ->assertRedirect('/contact')->assertSessionHas('sent', 'contact');

        $this->assertSame(0, $this->enquiriesSent());
    }

    public function test_a_too_fast_chat_question_gets_an_empty_answer_and_reaches_nobody(): void
    {
        $this->fakeHq(['chat' => Http::response(['data' => ['reply' => 'Hello.', 'links' => [], 'limited' => false]])]);

        $this->postJson('/chat', ['chat_message' => 'Hello?', 'form_ts' => FormStamp::make(now())])
            ->assertOk()->assertExactJson(['reply' => '', 'links' => [], 'limited' => false]);

        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/chat'));
        $this->assertNull(session('chat.questions'), 'a bot question is not counted');
    }

    public function test_the_unsubscribe_button_clicked_within_3_seconds_asks_for_one_more_click(): void
    {
        $this->fakeHq(['tracking/unsubscribe/*' => Http::response(['data' => ['unsubscribed' => true]])]);

        $this->from('/tracking/unsubscribe/42')->post('/tracking/unsubscribe/42', ['form_ts' => FormStamp::make(now()->subSecond())])
            ->assertRedirect('/tracking/unsubscribe/42')
            ->assertSessionMissing('sent');
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/tracking/unsubscribe/'));

        $this->get('/tracking/unsubscribe/42')->assertSee('To confirm, select the Unsubscribe button once more.')
            ->assertDontSee('You’re unsubscribed');

        $this->from('/tracking/unsubscribe/42')->post('/tracking/unsubscribe/42', ['form_ts' => FormStamp::make(now()->subSeconds(4))])
            ->assertSessionHas('sent', 'unsubscribe');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/tracking/unsubscribe/42'));
    }

    public function test_the_stamp_and_the_check_token_are_never_flashed_back_into_a_form(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['error' => ['code' => 'invalid', 'message' => 'Check the fields.', 'fields' => ['email' => ['Enter a valid email address.']]]], 422)]);

        $this->from('/contact')->post('/contact', self::CONTACT + ['cf-turnstile-response' => 'token'])
            ->assertSessionHasInput('full_name', 'Ada Test')
            ->assertSessionMissing('_old_input.form_ts')
            ->assertSessionMissing('_old_input.cf-turnstile-response');
    }
}
