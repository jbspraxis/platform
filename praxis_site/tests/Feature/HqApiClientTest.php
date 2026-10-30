<?php

namespace Tests\Feature;

use App\Exceptions\Hq\HqConflict;
use App\Exceptions\Hq\HqNotFound;
use App\Exceptions\Hq\HqRateLimited;
use App\Exceptions\Hq\HqUnavailable;
use App\Exceptions\Hq\HqValidationException;
use App\Support\HqApi;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** App\Support\HqApi against a faked HQ: signing, retries, caching with stale copies, typed errors. */
class HqApiClientTest extends TestCase
{
    private function api(): HqApi
    {
        return app(HqApi::class);
    }

    public function test_every_request_is_signed_with_all_contract_headers(): void
    {
        $this->fakeHq(['booking/slots*' => Http::response(['data' => ['month' => '2026-11', 'days' => []]])]);

        $this->api()->bookingSlots('2026-11');

        Http::assertSent(function (HttpRequest $request) {
            if (! str_contains($request->url(), 'booking/slots')) {
                return false;
            }
            $this->assertSame('http://hq.test/api/site/v1/booking/slots?month=2026-11', $request->url());
            $this->assertSame(['site-test'], $request->header('X-Site-Key'));
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $request->header('X-Site-Nonce')[0]);
            $this->assertEqualsWithDelta(time(), (int) $request->header('X-Site-Timestamp')[0], 5);
            $this->assertSame(['en'], $request->header('X-Site-Locale'));
            $this->assertNotEmpty($request->header('X-Site-Visitor-Ip')[0]);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $request->header('X-Site-Signature')[0]);
            $this->assertTrue($this->signatureIsValid($request), 'the signature covers the path with its query');
            $this->assertFalse($this->signatureIsValid($request, 'another-secret'));

            return true;
        });
    }

    public function test_the_host_header_is_sent_when_configured(): void
    {
        config(['hq_api.host' => 'hq.localhost']);
        app()->forgetScopedInstances();
        $this->fakeHq(['reports/*' => Http::response(['data' => ['token' => 'PRX-1']])]);

        $this->api()->report('PRX-1');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'reports/PRX-1') && $r->header('Host') === ['hq.localhost']);
    }

    public function test_json_bodies_are_hashed_exactly_as_sent(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);

        $this->api()->createEnquiry(['full_name' => 'Ada Test', 'email' => 'ada@example.com', 'message' => 'Café ’quotes’']);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/enquiries')
            && $r->method() === 'POST'
            && $r->header('Content-Type') === ['application/json']
            && json_decode($r->body(), true)['message'] === 'Café ’quotes’'
            && $this->signatureIsValid($r));
    }

    public function test_a_get_is_retried_once_with_a_fresh_nonce(): void
    {
        Http::fake([
            self::HQ . '/reports/*' => Http::sequence()
                ->push(['error' => ['code' => 'server']], 500)
                ->push(['data' => ['token' => 'PRX-1']], 200),
        ]);

        $this->assertSame('PRX-1', $this->api()->report('PRX-1')['token']);

        $nonces = Http::recorded()->map(fn ($pair) => $pair[0]->header('X-Site-Nonce')[0]);
        $this->assertCount(2, $nonces);
        $this->assertNotSame($nonces[0], $nonces[1]);
    }

    public function test_a_get_that_fails_twice_is_unavailable(): void
    {
        $attempts = 0;
        Http::fake([self::HQ . '/reports/*' => function () use (&$attempts) {
            $attempts++;
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        }]);

        try {
            $this->api()->report('PRX-1');
            $this->fail('HqUnavailable expected');
        } catch (HqUnavailable) {
            $this->assertSame(2, $attempts);
        }
    }

    public function test_a_post_is_never_retried(): void
    {
        Http::fake([self::HQ . '/feedback' => Http::response(['error' => ['code' => 'server']], 500)]);

        try {
            $this->api()->sendFeedback(['description' => 'Something is wrong here']);
            $this->fail('HqUnavailable expected');
        } catch (HqUnavailable) {
            Http::assertSentCount(1);
        }
    }

    public function test_settings_are_cached_for_five_minutes_then_refreshed(): void
    {
        $this->fakeHq();

        $this->api()->settings();
        $this->api()->settings();
        Http::assertSentCount(1);

        $this->travel(6)->minutes();
        $this->api()->settings();
        Http::assertSentCount(2);
    }

    public function test_the_last_good_copy_is_served_while_hq_is_down(): void
    {
        $this->fakeHq(['kaizen/articles*' => Http::response(['data' => [['slug' => 'a', 'title' => 'A']]])]);
        $this->api()->kaizenArticles(1);
        $this->assertFalse($this->api()->lastReadWasStale());

        $this->travel(11)->minutes();
        Http::fake([self::HQ . '/*' => $this->down()]);

        $copy = $this->api()->kaizenArticles(1);
        $this->assertSame('a', $copy['data'][0]['slug']);
        $this->assertTrue($this->api()->lastReadWasStale());
    }

    public function test_the_stale_copy_expires_after_24_hours(): void
    {
        $this->fakeHq();
        $this->api()->settings();

        $this->travel(25)->hours();
        Http::fake([self::HQ . '/*' => $this->down()]);

        $this->expectException(HqUnavailable::class);
        $this->api()->settings();
    }

    public function test_no_copy_and_hq_down_throws_unavailable(): void
    {
        Http::fake([self::HQ . '/*' => $this->down()]);

        $this->expectException(HqUnavailable::class);
        $this->api()->calculatorReference();
    }

    public function test_the_contact_email_comes_from_the_last_good_settings(): void
    {
        $this->assertSame('fallback@jbspraxis.com', $this->api()->contactEmail());

        Http::fake([self::HQ . '/settings' => Http::response(['data' => ['contact' => ['email' => 'hello@jbspraxis.com']]])]);
        $this->api()->settings();

        $this->assertSame('hello@jbspraxis.com', $this->api()->contactEmail());
    }

    public function test_a_422_becomes_field_errors_with_the_summary(): void
    {
        Http::fake([self::HQ . '/enquiries' => Http::response(['error' => [
            'code' => 'invalid', 'message' => 'Check the email address.', 'fields' => ['email' => ['Enter a valid email address.']],
        ]], 422)]);

        try {
            $this->api()->createEnquiry(['email' => 'nope']);
            $this->fail('HqValidationException expected');
        } catch (HqValidationException $e) {
            $this->assertSame(['email' => ['Enter a valid email address.']], $e->errors());
            $this->assertSame('Check the email address.', $e->summary);
        }
    }

    public function test_other_answers_become_typed_exceptions(): void
    {
        Http::fake([
            self::HQ . '/reports/missing' => Http::response(['error' => ['code' => 'not_found']], 404),
            self::HQ . '/bookings' => Http::response(['error' => ['code' => 'slot_taken']], 409),
            self::HQ . '/whitepaper' => Http::response(['error' => ['code' => 'too_many', 'retry_after' => 42]], 429),
            self::HQ . '/feedback' => Http::response(['error' => ['code' => 'unauthorised']], 401),
        ]);

        try {
            $this->api()->report('missing');
            $this->fail();
        } catch (HqNotFound) {
        }
        try {
            $this->api()->createBooking([]);
            $this->fail();
        } catch (HqConflict $e) {
            $this->assertSame('slot_taken', $e->errorCode);
        }
        try {
            $this->api()->requestWhitepaper('a@b.co');
            $this->fail();
        } catch (HqRateLimited $e) {
            $this->assertSame(42, $e->retryAfter);
        }
        $this->expectException(HqUnavailable::class);
        $this->api()->sendFeedback([]);
    }

    public function test_the_cv_goes_as_multipart_and_the_signature_covers_the_exact_body(): void
    {
        $this->fakeHq(['careers/applications' => Http::response(['data' => ['received' => true]], 201)]);

        $this->api()->applyForJob(['full_name' => 'Ada Test', 'email' => 'ada@example.com'], UploadedFile::fake()->createWithContent('my cv.pdf', '%PDF-1.4 test'));

        Http::assertSent(function (HttpRequest $r) {
            if (! str_contains($r->url(), 'careers/applications')) {
                return false;
            }
            $this->assertStringStartsWith('multipart/form-data; boundary=', $r->header('Content-Type')[0]);
            $this->assertStringContainsString('name="full_name"', $r->body());
            $this->assertStringContainsString('name="cv"; filename="my cv.pdf"', $r->body());
            $this->assertStringContainsString('%PDF-1.4 test', $r->body());
            $this->assertTrue($this->signatureIsValid($r));

            return true;
        });
    }

    public function test_the_open_pixel_call_never_throws(): void
    {
        $attempts = 0;
        Http::fake([self::HQ . '/tracking/open/12/34' => function () use (&$attempts) {
            $attempts++;
            throw new \Illuminate\Http\Client\ConnectionException('Timed out');
        }]);

        $this->api()->trackOpen('12', '34');

        $this->assertSame(1, $attempts, 'one attempt, no retry');
        $this->assertNull(Cache::get('hq_api:down'), 'a slow pixel never pauses the other reads');
    }

    // ---- section 6.6: timeouts, redirects, https

    /** Records the Guzzle options of each request to HQ and answers with $response. */
    private function recordOptions(array &$seen, $response): \Closure
    {
        return function ($request, array $options) use (&$seen, $response) {
            $seen[] = ['url' => $request->url(), 'timeout' => $options['timeout'] ?? null, 'connect' => $options['connect_timeout'] ?? null, 'redirects' => $options['allow_redirects'] ?? null];

            return $response instanceof \Closure ? $response() : $response;
        };
    }

    public function test_each_kind_of_request_has_its_own_timeout_and_never_follows_redirects(): void
    {
        $seen = [];
        Http::fake([
            self::HQ . '/reports/*' => $this->recordOptions($seen, Http::response(['data' => ['token' => 'PRX-1']])),
            self::HQ . '/enquiries' => $this->recordOptions($seen, Http::response(['data' => ['received' => true]], 201)),
            self::HQ . '/chat' => $this->recordOptions($seen, Http::response(['data' => ['reply' => 'Hi.', 'links' => [], 'limited' => false]])),
            self::HQ . '/tracking/open/*' => $this->recordOptions($seen, Http::response(['data' => ['recorded' => true]])),
            self::HQ . '/careers/applications' => $this->recordOptions($seen, Http::response(['data' => ['received' => true]], 201)),
        ]);

        $this->api()->report('PRX-1');
        $this->api()->createEnquiry(['email' => 'a@b.co']);
        $this->api()->chat([], 'Hello?', 1);
        $this->api()->trackOpen('1', '2');
        $this->api()->applyForJob(['full_name' => 'Ada'], UploadedFile::fake()->createWithContent('cv.pdf', '%PDF'));

        $this->assertSame([8, 20, 35, 2, 20], array_column($seen, 'timeout'), 'reads 8 s, writes 20 s, chat 35 s, pixel 2 s');
        $this->assertSame([5, 5, 5, 2, 5], array_column($seen, 'connect'));
        $this->assertSame([false, false, false, false, false], array_column($seen, 'redirects'));
    }

    public function test_a_redirect_is_unavailable_logged_and_never_followed(): void
    {
        Log::spy();
        $attempts = 0;
        Http::fake([
            self::HQ . '/reports/*' => function () use (&$attempts) {
                $attempts++;

                return Http::response('', 302, ['Location' => 'https://elsewhere.example/steal']);
            },
            'elsewhere.example/*' => Http::response('should never be called'),
        ]);

        try {
            $this->api()->report('PRX-1');
            $this->fail('HqUnavailable expected');
        } catch (HqUnavailable $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(1, $attempts, 'a redirect is not retried either');
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'elsewhere.example'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => str_contains($message, 'redirect') && ($context['status'] ?? null) === 302)->once();
    }

    public function test_a_post_that_times_out_never_marks_hq_down_but_a_read_does(): void
    {
        Http::fake([
            self::HQ . '/enquiries' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out'),
            self::HQ . '/bookings' => Http::response('', 503),
            self::HQ . '/reports/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out'),
        ]);

        foreach ([fn () => $this->api()->createEnquiry(['email' => 'a@b.co']), fn () => $this->api()->createBooking([])] as $call) {
            try {
                $call();
                $this->fail('HqUnavailable expected');
            } catch (HqUnavailable) {
            }
        }
        $this->assertNull(Cache::get('hq_api:down'), 'HQ may still be storing the request');

        try {
            $this->api()->report('PRX-1');
        } catch (HqUnavailable) {
        }
        $this->assertTrue(Cache::get('hq_api:down'));
    }

    public function test_production_refuses_a_base_url_that_is_not_https(): void
    {
        Log::spy();
        $this->app['env'] = 'production';
        config(['hq_api.base' => 'http://hq.test/api/site/v1']);
        app()->forgetScopedInstances();
        Http::fake([self::HQ . '/*' => Http::response(['data' => ['received' => true]], 201)]);

        try {
            $this->api()->createEnquiry(['email' => 'a@b.co']);
            $this->fail('HqUnavailable expected');
        } catch (HqUnavailable) {
        }
        $this->assertNull($this->api()->settingsIfAvailable());
        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'must start with https://'))->atLeast()->once();

        // https is fine
        config(['hq_api.base' => 'https://hq.test/api/site/v1']);
        app()->forgetScopedInstances();
        $this->assertSame(['received' => true], $this->api()->createEnquiry(['email' => 'a@b.co']));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://hq.test/api/site/v1/enquiries' && $this->signatureIsValid($r));
    }

    public function test_http_is_allowed_outside_production(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);

        $this->assertSame(['received' => true], $this->api()->createEnquiry(['email' => 'a@b.co']));
    }

    public function test_the_key_id_and_media_type_are_signed(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);

        $this->api()->createEnquiry(['email' => 'a@b.co']);

        Http::assertSent(function (HttpRequest $r) {
            if (! str_contains($r->url(), '/enquiries')) {
                return false;
            }
            $this->assertTrue($this->signatureIsValid($r));
            // The same request claimed for another key id no longer verifies
            $forged = $r->toPsrRequest()->withHeader('X-Site-Key', 'other-key');
            $this->assertFalse($this->signatureIsValid(new HttpRequest($forged)));
            // …nor sent as another media type
            $forged = $r->toPsrRequest()->withHeader('Content-Type', 'text/plain');
            $this->assertFalse($this->signatureIsValid(new HttpRequest($forged)));

            return true;
        });
    }

    public function test_a_409_and_a_429_carry_hqs_calm_message(): void
    {
        Http::fake([
            self::HQ . '/bookings' => Http::response(['error' => ['code' => 'already_booked', 'message' => 'You already have a request with us.']], 409),
            self::HQ . '/whitepaper' => Http::response(['error' => ['code' => 'too_many', 'retry_after' => 30, 'message' => 'Try again tomorrow.']], 429),
        ]);

        try {
            $this->api()->createBooking([]);
            $this->fail();
        } catch (HqConflict $e) {
            $this->assertSame('already_booked', $e->errorCode);
            $this->assertSame('You already have a request with us.', $e->hqMessage);
        }
        try {
            $this->api()->requestWhitepaper('a@b.co');
            $this->fail();
        } catch (HqRateLimited $e) {
            $this->assertSame(30, $e->retryAfter);
            $this->assertSame('Try again tomorrow.', $e->hqMessage);
        }
    }

    public function test_an_ipv6_visitor_is_sent_as_is_and_signed(): void
    {
        $this->fakeHq(['reports/*' => Http::response(['data' => ['token' => 'PRX-1']])]);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::7'])->get('/dossier/PRX-ABCDEF');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/reports/') && $r->header('X-Site-Visitor-Ip') === ['2001:db8::7'] && $this->signatureIsValid($r));
    }

    public function test_the_chat_reply_keeps_only_safe_links(): void
    {
        Http::fake([self::HQ . '/chat' => Http::response(['data' => ['reply' => ' Hi. ', 'limited' => true, 'links' => [
            ['label' => 'Contact us', 'path' => '/contact'], ['label' => '', 'path' => '/schedule'], ['label' => 'Bad', 'path' => '/\\evil'], 'not a link',
        ]]])]);

        $answer = $this->api()->chat([['role' => 'user', 'text' => 'Earlier']], 'Hello?', 4);

        $this->assertSame(['reply' => 'Hi.', 'links' => [['label' => 'Contact us', 'path' => '/contact']], 'limited' => true], $answer);
        $this->assertSame(['history' => [['role' => 'user', 'text' => 'Earlier']], 'message' => 'Hello?', 'question_number' => 4], $this->sentJson('/chat'));
    }
}
