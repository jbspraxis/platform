<?php

namespace Tests\Feature;

use App\Http\Controllers\SiteApi\ApiErrors;
use App\Mail\AuditRequested;
use App\Mail\CopqReportMail;
use App\Mail\EnquiryAlert;
use App\Mail\EnquiryReceived;
use App\Mail\PraxisFrameworkDispatch;
use App\Services\Site\Deferred;
use App\Services\Site\EnquiryService;
use App\Services\Site\SiteSignature;
use App\Services\Site\TrackingService;
use App\Support\Hosts;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The private site API (docs/SITE_API.md, section 6 "Revision 2" in particular): signing, refusals, routes and
 * error mapping, the shared limits (IPv6 /64, per email, circuit breakers), bookings, careers repeats, Praxis AI,
 * tracking tokens, emails after the response and database errors without personal data.
 * Requests go through the real HTTP kernel, signed as praxis_site signs them. Mail is faked, AI providers are faked.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class SiteApiTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const KEY = 'test-key';
    private const SECRET = 'test-secret-for-the-hq-tests';

    private string $hq;

    /** What the faked Gemini answers; null = the provider fails */
    private ?string $aiText = 'Praxis AI answer.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'site_api.keys' => [self::KEY => self::SECRET],
            'services.leads.alert_email' => 'team@local.test',
            'services.gemini.api_key' => 'test-gemini', 'services.gemini.chat_model' => 'gemini-test',
            'services.deepseek.api_key' => null,
        ]);
        $this->hq = 'http://' . Hosts::hq();

        Mail::fake();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'generativelanguage.googleapis.com') && $this->aiText !== null) {
                return Http::response(['candidates' => [['content' => ['parts' => [['text' => $this->aiText]]]]]], 200);
            }

            return Http::response(['error' => ['message' => 'unavailable']], 503);
        });
    }

    // ------------------------------------------------------------------ signing and refusals (6.1)

    public function test_a_signed_read_works(): void
    {
        $response = $this->api('GET', '/settings')->assertOk()->assertJsonPath('data.company.trading_name', 'JBS Praxis');
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
    }

    public function test_refusals_are_401_without_detail(): void
    {
        $unsigned = $this->call('GET', $this->hq . '/api/site/v1/settings');
        $unsigned->assertStatus(401)->assertExactJson(['error' => ['code' => 'unauthorised']]);
        $this->assertStringContainsString('no-store', (string) $unsigned->headers->get('Cache-Control'));

        $this->api('GET', '/settings', secret: 'wrong-secret')->assertStatus(401);
        $this->api('GET', '/settings', headers: ['HTTP_X_SITE_NONCE' => 'not-hex!'])->assertStatus(401);
        $this->api('GET', '/settings', headers: ['HTTP_X_SITE_LOCALE' => 'de'])->assertStatus(401);
        $this->api('GET', '/settings', without: ['HTTP_X_SITE_VISITOR_IP'])->assertStatus(401);
        $this->api('GET', '/settings', timestamp: time() - 301)->assertStatus(401);

        // A replayed nonce
        $this->api('GET', '/settings', nonce: 'aaaabbbbccccdddd0001')->assertOk();
        $this->api('GET', '/settings', nonce: 'aaaabbbbccccdddd0001')->assertStatus(401);
    }

    public function test_the_signature_covers_the_key_id_and_the_media_type(): void
    {
        // Signed as JSON but sent as another media type: the canonical line differs
        $this->api('POST', '/whitepaper', ['email' => 'media@example.test'], headers: ['CONTENT_TYPE' => 'text/plain'])->assertStatus(401);

        // A second valid key cannot reuse a signature made for the first one
        config(['site_api.keys' => [self::KEY => self::SECRET, 'other-key' => self::SECRET]]);
        $this->api('GET', '/settings', headers: ['HTTP_X_SITE_KEY' => 'other-key'])->assertStatus(401);
    }

    public function test_failed_authentications_are_limited_per_address_and_logged_sparingly(): void
    {
        Log::spy();

        for ($i = 1; $i <= 30; $i++) {
            $this->call('GET', $this->hq . '/api/site/v1/settings', [], [], [], ['REMOTE_ADDR' => '198.51.100.66'])->assertStatus(401);
        }
        $this->call('GET', $this->hq . '/api/site/v1/settings', [], [], [], ['REMOTE_ADDR' => '198.51.100.66'])
            ->assertStatus(429)->assertJsonPath('error.code', 'too_many');

        // Another connecting address is still answered (401 here, as it isn't signed either)
        $this->call('GET', $this->hq . '/api/site/v1/settings', [], [], [], ['REMOTE_ADDR' => '198.51.100.67'])->assertStatus(401);

        // One log line a minute per reason, not one per refusal
        Log::shouldHaveReceived('info')->with('Site API: request refused', \Mockery::any())->once();
    }

    public function test_ambiguous_multipart_forms_are_refused(): void
    {
        Storage::fake('local');
        $base = $this->careerFields('multi@example.test');

        $this->api('POST', '/careers/applications', fields: $base + ['tags' => ['a', 'b']], files: ['cv' => $this->pdf()])->assertStatus(401);
        $this->api('POST', '/careers/applications', fields: $base + ['cv' => 'f:' . str_repeat('0', 64)], files: ['cv' => $this->pdf()])->assertStatus(401);
        $this->assertSame(0, DB::table('talent_pool')->where('email', 'multi@example.test')->count());
    }

    // ------------------------------------------------------------------ routes and errors (6.3)

    public function test_unknown_paths_and_methods_are_404_and_other_4xx_are_400(): void
    {
        $this->call('POST', $this->hq . '/api/site/v1/nope', [], [], [], ['REMOTE_ADDR' => '198.51.100.70'])
            ->assertStatus(404)->assertExactJson(['error' => ['code' => 'not_found']]);
        $this->call('GET', $this->hq . '/api/site/v1')->assertStatus(404);
        $this->call('PUT', $this->hq . '/api/site/v1/chat')->assertStatus(404);
        $this->api('DELETE', '/settings')->assertStatus(404);
        $this->api('POST', '/chat', ['message' => 'x'], headers: ['HTTP_X_HTTP_METHOD_OVERRIDE' => 'PUT'])->assertStatus(404);

        $request = Request::create($this->hq . '/api/site/v1/settings');
        $this->assertSame(400, ApiErrors::render(new AccessDeniedHttpException(), $request)->getStatusCode());
        $this->assertSame('bad_request', json_decode(ApiErrors::render(new HttpException(419), $request)->getContent(), true)['error']['code']);
        $this->assertSame(500, ApiErrors::render(new \RuntimeException('x'), $request)->getStatusCode());
        $this->assertStringContainsString('no-store', ApiErrors::render(new HttpException(419), $request)->headers->get('Cache-Control'));
    }

    public function test_database_errors_are_reported_without_the_values(): void
    {
        Log::spy();
        $email = 'secret.person.' . Str::random(6) . '@example.test';
        $this->insertRow('talent_pool', ['id' => (string) Str::uuid(), 'email' => $email, 'full_name' => 'Secret Person', 'status' => 'Pending']);

        try {
            $this->insertRow('talent_pool', ['id' => (string) Str::uuid(), 'email' => $email, 'full_name' => 'Secret Person', 'status' => 'Pending']);
            $this->fail('The duplicate should have been refused');
        } catch (QueryException $e) {
            report($e);
        }

        $group = DB::table('monitor_error_groups')->orderByDesc('id')->first();
        $this->assertNotNull($group);
        $this->assertStringContainsString('Duplicate entry', $group->message);
        $this->assertStringContainsString('?', $group->message);
        $this->assertStringNotContainsString($email, $group->message . $group->trace);
        $this->assertStringNotContainsString('Secret Person', $group->message . $group->trace);

        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => !str_contains($message . json_encode($context), $email))->atLeast()->once();
        Log::shouldNotHaveReceived('error', [\Mockery::on(fn ($m) => str_contains((string) $m, $email)), \Mockery::any()]);
    }

    // ------------------------------------------------------------------ limits (6.2)

    public function test_visitor_limits_bucket_ipv6_to_64_and_count_per_email(): void
    {
        // Method PDF: 3 a day per visitor; three addresses in one /64 are one visitor
        foreach (['2001:db8:1::1', '2001:db8:1::2', '2001:db8:1::3'] as $i => $ip) {
            $this->api('POST', '/whitepaper', ['email' => "pdf{$i}@example.test"], ip: $ip)->assertCreated();
        }
        $this->api('POST', '/whitepaper', ['email' => 'pdf9@example.test'], ip: '2001:db8:1::ffff')
            ->assertStatus(429)->assertJsonPath('error.code', 'too_many')
            ->assertJsonPath('error.message', 'You’ve sent several requests in a short time. Try again later.')
            ->assertJson(fn ($json) => $json->where('error.retry_after', fn ($s) => $s > 0)->etc());

        // Another /64 is another visitor
        $this->api('POST', '/whitepaper', ['email' => 'pdf8@example.test'], ip: '2001:db8:2::1')->assertCreated();

        // 1 a day per email, whatever the address
        $this->api('POST', '/whitepaper', ['email' => 'PDF0@example.test'], ip: '198.51.100.20')->assertStatus(429);

        // In French too
        $this->api('POST', '/whitepaper', ['email' => 'pdf9@example.test'], ip: '2001:db8:1::5', locale: 'fr')
            ->assertStatus(429)->assertJsonPath('error.message', 'Vous avez envoyé plusieurs demandes en peu de temps. Réessayez plus tard.');
    }

    public function test_a_global_limit_is_a_circuit_breaker_with_an_alert(): void
    {
        config(['site_api.limits.feedback.global.hour' => 2]);
        $feedback = ['category' => 'Bug', 'description' => 'The page did not load on my phone.'];

        $this->api('POST', '/feedback', $feedback, ip: '198.51.100.31')->assertCreated();
        $this->api('POST', '/feedback', $feedback, ip: '198.51.100.32')->assertCreated();
        $this->api('POST', '/feedback', $feedback, ip: '198.51.100.33')->assertStatus(429);
        $this->api('POST', '/feedback', $feedback, ip: '198.51.100.34')->assertStatus(429);

        $alerts = DB::table('monitor_error_groups')->where('source', 'site-limit')->get();
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('website feedback reports', $alerts[0]->message);
    }

    public function test_visitor_emails_are_capped_per_recipient(): void
    {
        $this->staff('sales', ['sales']);
        $email = 'cap@example.test';

        // 3 cost reports a day per email, each emailed: that uses the address's 3 visitor emails
        foreach ([1, 2, 3] as $i) {
            $this->api('POST', '/calculator/assessments', $this->assessment($email), ip: "198.51.100.4{$i}")
                ->assertCreated()->assertJsonPath('data.emailed', true);
        }
        Mail::assertSent(CopqReportMail::class, 3);

        // A 4th email to that address today is not sent
        $this->api('POST', '/whitepaper', ['email' => $email], ip: '198.51.100.49')->assertCreated()->assertJsonPath('data.sent', false);
        Mail::assertNotSent(PraxisFrameworkDispatch::class);

        // And a 4th report for that email is refused
        $this->api('POST', '/calculator/assessments', $this->assessment($email), ip: '198.51.100.48')->assertStatus(429);
    }

    // ------------------------------------------------------------------ endpoints (6.4)

    public function test_enquiry_stores_start_timing_and_emails_only_the_first_name(): void
    {
        $this->api('POST', '/enquiries', $this->enquiry('timing@example.test', 'Timing Ltd') + ['start_timing' => 'next_quarter'])->assertCreated();

        $org = DB::table('organizations')->where('name', 'Timing Ltd')->first();
        $this->assertSame('next_quarter', $org->start_timing);
        $this->assertStringContainsString('Next quarter', DB::table('crm_notes')->where('organization_id', $org->id)->value('note_text'));

        Mail::assertSent(EnquiryReceived::class, fn ($mail) => $mail->hasTo('timing@example.test') && $mail->firstName === 'Adaeze');
        Mail::assertSent(EnquiryAlert::class, fn ($mail) => $mail->hasTo('team@local.test') && ($mail->facts()['When they’d like to start'] ?? null) === 'Next quarter');

        // Shown in HQ → Leads
        $this->actingAs($this->staff('sales', ['sales']));
        Livewire::test(\App\Livewire\Hq\Intelligence\LeadsRegister::class)->call('openDrawer', $org->id)
            ->assertSee('When they’d like to start')->assertSee('Next quarter');

        $this->api('POST', '/enquiries', $this->enquiry('timing2@example.test', 'Timing Two') + ['start_timing' => 'tomorrow'])
            ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['start_timing']]]);

        // The first name in visitor emails is at most 40 characters; long values that pass the rules never cause a 500
        $long = str_repeat('a', 60);
        $this->api('POST', '/enquiries', ['full_name' => "{$long} Smith", 'budget' => str_repeat('b', 90), 'language' => str_repeat('l', 45)]
            + $this->enquiry('long@example.test', 'Long Ltd'), ip: '198.51.100.51')->assertCreated();
        $this->api('POST', '/enquiries', ['full_name' => 'Ada ' . str_repeat('z', 90)] + $this->enquiry('long2@example.test', 'Long Two'), ip: '198.51.100.52')->assertCreated();
        Mail::assertSent(EnquiryReceived::class, fn ($mail) => $mail->hasTo('long@example.test') && $mail->firstName === str_repeat('a', 40));
    }

    public function test_enquiries_are_limited_per_email(): void
    {
        $this->api('POST', '/enquiries', $this->enquiry('twice@example.test', 'Twice One'), ip: '198.51.100.61')->assertCreated();
        $this->api('POST', '/enquiries', $this->enquiry('twice@example.test', 'Twice Two'), ip: '198.51.100.62')->assertCreated();
        $this->api('POST', '/enquiries', $this->enquiry('TWICE@example.test', 'Twice Three'), ip: '198.51.100.63')->assertStatus(429);
    }

    public function test_emails_wait_until_after_the_response(): void
    {
        app(EnquiryService::class)->submit($this->enquiry('later@example.test', 'Later Ltd') + EnquiryService::defaults(), false, '198.51.100.71', afterResponse: true);
        $this->assertNotNull(DB::table('organizations')->where('name', 'Later Ltd')->first(), 'stored before the response');
        Mail::assertNothingSent();

        app(DeferredCallbackCollection::class)->invoke(); // what Laravel does once the response is sent
        Mail::assertSent(EnquiryReceived::class, fn ($mail) => $mail->hasTo('later@example.test'));

        $ran = false;
        Deferred::run(false, function () use (&$ran) { $ran = true; });
        $this->assertTrue($ran, 'the old pages still send during the request');
    }

    public function test_booking_rules(): void
    {
        $day = $this->nextWeekday(2);
        $far = $this->nextWeekday(100);

        $this->api('POST', '/bookings', $this->booking('far@example.test', 'Far Ltd', $far, '09:00'))
            ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['date']]]);

        $this->api('POST', '/bookings', $this->booking('book@example.test', 'Book Ltd', $day, '09:00', 'Cash flow reporting'))
            ->assertCreated()->assertJsonPath('data.start', Carbon::parse("{$day} 09:00", 'Africa/Lagos')->toIso8601String());

        // The visitor's copy never repeats a topic they typed
        Mail::assertSent(AuditRequested::class, fn ($mail) => $mail->hasTo('book@example.test') && $mail->topic === 'Other' && $mail->firstName === 'Adaeze');

        // One open request per email and per organisation
        $this->api('POST', '/bookings', $this->booking('book@example.test', 'Another Ltd', $day, '10:30'))
            ->assertStatus(409)->assertJsonPath('error.code', 'already_booked');
        $this->api('POST', '/bookings', $this->booking('colleague@example.test', 'BOOK LTD', $day, '10:30'))
            ->assertStatus(409)->assertJsonPath('error.code', 'already_booked');

        // A taken time
        $this->api('POST', '/bookings', $this->booking('other@example.test', 'Other Ltd', $day, '09:00'))
            ->assertStatus(409)->assertJsonPath('error.code', 'slot_taken');

        // 2 a day per visitor (only accepted bookings count)
        $this->api('POST', '/bookings', $this->booking('second@example.test', 'Second Ltd', $day, '13:00'))->assertCreated();
        $this->api('POST', '/bookings', $this->booking('third@example.test', 'Third Ltd', $day, '14:30'))->assertStatus(429);

        // The calendar stops at 92 days
        $slots = $this->api('GET', '/booking/slots?month=' . Carbon::parse($far)->format('Y-m'))->assertOk()->json('data.days');
        $this->assertNotContains($far, array_column($slots, 'date'));
    }

    public function test_a_repeat_application_updates_it_and_adds_the_cv(): void
    {
        Storage::fake('local');

        $this->api('POST', '/careers/applications', fields: $this->careerFields('apply@example.test'), files: ['cv' => $this->pdf()])->assertCreated();
        $this->api('POST', '/careers/applications', fields: ['city' => 'Douala'] + $this->careerFields('apply@example.test'), files: ['cv' => $this->pdf()], ip: '198.51.100.81')
            ->assertCreated()->assertJsonPath('data.received', true);

        $rows = DB::table('talent_pool')->where('email', 'apply@example.test')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Douala', $rows[0]->city);
        $this->assertSame(2, DB::table('media_assets')->where('talent_id', $rows[0]->id)->count());
    }

    public function test_kaizen_articles_carry_no_author_or_job_title(): void
    {
        $slug = 'test-article-' . Str::lower(Str::random(5));
        $this->insertRow('kaizen_articles', [
            'id' => (string) Str::uuid(), 'slug' => $slug, 'category' => 'The Boardroom', 'title' => 'A test article',
            'executive_summary' => 'Summary.', 'problem_statement' => 'Problem.', 'engineered_solution' => 'Solution.', 'final_roi' => 'Result.',
            'status' => 'Published', 'is_public' => 1, 'created_by' => null, 'contributor_id' => null,
        ]);

        $list = $this->api('GET', '/kaizen/articles')->assertOk()->json('data.articles');
        $article = collect($list)->firstWhere('slug', $slug);
        $this->assertNotNull($article);
        $this->assertArrayNotHasKey('author', $article);
        $this->assertArrayNotHasKey('designation', $article);

        $one = $this->api('GET', "/kaizen/articles/{$slug}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('author', $one);
        $this->assertArrayNotHasKey('designation', $one);
    }

    public function test_calculator_notifications_are_reworded(): void
    {
        $staff = $this->staff('sales', ['sales']);

        $this->api('POST', '/calculator/assessments', ['company' => 'Calc Co', 'turnover' => 9000000, 'currency' => 'USD'] + $this->assessment('calc@example.test'))
            ->assertCreated()->assertJsonPath('data.emailed', true);

        $note = DB::table('notifications')->where('user_id', $staff->id)->orderByDesc('created_at')->first();
        $this->assertSame('High-value lead from the cost calculator', $note->title);
        $this->assertMatchesRegularExpression('/^Calc Co ran the cost of poor quality calculator: estimated .+ a year\.$/u', $note->message);

        $this->api('POST', '/calculator/assessments', ['company' => 'Small Co', 'turnover' => 1000, 'error_rate' => 1, 'currency' => 'USD'] + $this->assessment('small@example.test'), ip: '198.51.100.91')
            ->assertCreated();
        $this->assertSame('New cost report from the website', DB::table('notifications')->where('user_id', $staff->id)->where('message', 'like', 'Small Co%')->value('title'));
    }

    // ------------------------------------------------------------------ Praxis AI (6.4, 6.5)

    public function test_chat_answers_with_site_map_links_only(): void
    {
        $this->aiText = "We start with a free audit and demo.\n\n[See the method](https://jbspraxis.com/services) or [elsewhere](https://evil.example/x).\n**PAGES: /schedule /unknown /contact /kaizen**";

        $response = $this->api('POST', '/chat', ['message' => 'How do we start?', 'question_number' => 1])->assertOk();
        $data = $response->json('data');

        $this->assertFalse($data['limited']);
        $this->assertStringContainsString('See the method', $data['reply']);
        $this->assertStringNotContainsString('evil.example', $data['reply']);
        $this->assertStringNotContainsString('PAGES', $data['reply']);
        $this->assertSame(['/schedule', '/contact', '/kaizen'], array_column($data['links'], 'path'));
        $this->assertSame('Book a free audit and demo', $data['links'][0]['label']);

        // The prompt is Praxis AI's, built from Website settings, never the old widget's
        Http::assertSent(function ($request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'You are Praxis AI') && str_contains($body, 'info@jbspraxis.com')
                && !str_contains($body, 'Architectural AI (The Nexus)') && !str_contains($body, '816 978');
        });
    }

    public function test_chat_limits_per_conversation_and_day_budget(): void
    {
        $this->api('POST', '/chat', ['message' => 'One more?', 'question_number' => 21])->assertOk()
            ->assertJsonPath('data.limited', true)->assertJsonPath('data.links.0.path', '/schedule');
        Http::assertNothingSent();

        // A history of assistant turns doesn't reset the count: the site's counter decides
        $history = array_fill(0, 20, ['role' => 'assistant', 'text' => 'Earlier answer.']);
        $this->api('POST', '/chat', ['message' => 'Again?', 'history' => $history, 'question_number' => 25])->assertJsonPath('data.limited', true);

        // The shared AI budget (model calls a day, with the calculator)
        config(['site_api.ai_calls_per_day' => 1]);
        $this->api('POST', '/chat', ['message' => 'First?', 'question_number' => 1], ip: '198.51.100.101')->assertJsonPath('data.limited', false);
        $this->api('POST', '/chat', ['message' => 'Second?', 'question_number' => 2], ip: '198.51.100.102')->assertJsonPath('data.limited', true);
    }

    public function test_chat_per_visitor_limit_is_shared_by_a_64(): void
    {
        foreach (range(1, 10) as $i) {
            $this->api('POST', '/chat', ['message' => "Question {$i}?", 'question_number' => $i], ip: "2001:db8:5::{$i}")->assertJsonPath('data.limited', false);
        }
        $this->api('POST', '/chat', ['message' => 'Eleven?', 'question_number' => 11], ip: '2001:db8:5::abcd')->assertJsonPath('data.limited', true);
        $this->api('POST', '/chat', ['message' => 'Other network?', 'question_number' => 1], ip: '2001:db8:6::1')->assertJsonPath('data.limited', false);
    }

    public function test_chat_fallback_when_no_ai_answers(): void
    {
        $this->aiText = null;

        $this->api('POST', '/chat', ['message' => 'Hello?', 'question_number' => 1], locale: 'fr')->assertOk()
            ->assertJsonPath('data.limited', false)
            ->assertJsonPath('data.reply', 'Praxis AI n’est pas disponible pour le moment. Réservez un audit et une démonstration offerts, ou écrivez-nous à info@jbspraxis.com.')
            ->assertJsonPath('data.links.0.path', '/schedule');
    }

    // ------------------------------------------------------------------ tracking tokens (6.4)

    public function test_new_broadcast_links_need_their_token_and_old_ones_still_work(): void
    {
        $staff = $this->staff('sales', ['sales']);
        $contact = (string) Str::uuid();
        $this->insertRow('client_users', ['id' => $contact, 'organization_id' => \Database\Seeders\CoreSeeder::INTERNAL_ORG_ID,
            'first_name' => 'Read', 'last_name' => 'Er', 'email' => 'reader@example.test', 'status' => 'Active', 'is_subscribed' => 1]);
        $old = (string) Str::uuid();
        $new = (string) Str::uuid();
        foreach ([$old, $new] as $campaign) {
            $this->insertRow('broadcast_campaigns', ['id' => $campaign, 'subject' => 'News', 'body_html' => '<p>News</p>', 'created_by' => $staff->id]);
        }
        DB::table('broadcast_logs')->insert([
            ['campaign_id' => $old, 'user_id' => $contact, 'status' => 'Sent', 'signed_links' => 0],
            ['campaign_id' => $new, 'user_id' => $contact, 'status' => 'Sent', 'signed_links' => 1],
        ]);
        $t = TrackingService::token($new, $contact);
        $opens = fn (string $campaign) => (int) DB::table('broadcast_logs')->where('campaign_id', $campaign)->value('open_count');

        // Site API
        $this->api('GET', "/tracking/open/{$new}/{$contact}")->assertJsonPath('data.recorded', false);
        $this->api('GET', "/tracking/open/{$new}/{$contact}?t=" . str_repeat('0', 32))->assertJsonPath('data.recorded', false);
        $this->api('GET', "/tracking/open/{$new}/{$contact}?t={$t}")->assertJsonPath('data.recorded', true);
        $this->api('GET', "/tracking/open/{$old}/{$contact}")->assertJsonPath('data.recorded', true);
        $this->assertSame(1, $opens($new));

        // The old web route follows the same rules
        $site = 'http://' . Hosts::site();
        $this->get("{$site}/tracking/open/{$new}/{$contact}")->assertOk();
        $this->assertSame(1, $opens($new));
        $this->get("{$site}/tracking/open/{$new}/{$contact}?t={$t}")->assertOk();
        $this->assertSame(2, $opens($new));

        // Unsubscribe: with the token always; without it only while an email from before tokens exists
        $this->api('POST', "/tracking/unsubscribe/{$contact}", ['campaign' => $new, 't' => str_repeat('1', 32)])->assertStatus(404);
        $this->api('POST', "/tracking/unsubscribe/{$contact}", ['campaign' => $new, 't' => $t])->assertOk()->assertJsonPath('data.unsubscribed', true);
        $this->assertSame(0, (int) DB::table('client_users')->where('id', $contact)->value('is_subscribed'));

        DB::table('client_users')->where('id', $contact)->update(['is_subscribed' => 1]);
        $this->post("{$site}/tracking/unsubscribe/{$contact}")->assertOk()->assertSee('You’re unsubscribed');

        DB::table('client_users')->where('id', $contact)->update(['is_subscribed' => 1]);
        DB::table('broadcast_logs')->where('campaign_id', $old)->delete();
        $this->api('POST', "/tracking/unsubscribe/{$contact}")->assertStatus(404);
        $this->post("{$site}/tracking/unsubscribe/{$contact}")->assertOk()->assertSee('We couldn’t confirm this link');
        $this->assertSame(1, (int) DB::table('client_users')->where('id', $contact)->value('is_subscribed'));
    }

    // ------------------------------------------------------------------ helpers

    /**
     * A request signed as praxis_site signs it (docs/SITE_API.md section 6.1). $json for a JSON body, or
     * $fields (+ $files) for multipart.
     */
    private function api(string $method, string $path, ?array $json = null, string $ip = '203.0.113.5', string $locale = 'en', ?string $nonce = null,
        ?int $timestamp = null, string $secret = self::SECRET, array $headers = [], array $without = [], ?array $fields = null, array $files = []): TestResponse
    {
        $fullPath = '/api/site/v1' . $path;
        $server = ['REMOTE_ADDR' => '198.51.100.10', 'HTTP_ACCEPT' => 'application/json'];

        if ($fields !== null) {
            $content = null;
            $media = SiteSignature::MULTIPART;
            $bodyHash = SiteSignature::multipartHash($fields, $files) ?? '';
            $server['CONTENT_TYPE'] = 'multipart/form-data; boundary=----site-test';
        } else {
            $content = $json === null ? '' : json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $media = $content === '' ? '' : 'application/json';
            $bodyHash = hash('sha256', $content);
            if ($content !== '') {
                $server['CONTENT_TYPE'] = 'application/json';
            }
        }

        $timestamp = (string) ($timestamp ?? time());
        $nonce ??= bin2hex(random_bytes(12));
        $canonical = SiteSignature::canonical($method, $fullPath, $timestamp, $nonce, $ip, $locale, self::KEY, $media, $bodyHash);

        $server = array_merge($server, [
            'HTTP_X_SITE_KEY' => self::KEY,
            'HTTP_X_SITE_TIMESTAMP' => $timestamp,
            'HTTP_X_SITE_NONCE' => $nonce,
            'HTTP_X_SITE_VISITOR_IP' => $ip,
            'HTTP_X_SITE_LOCALE' => $locale,
            'HTTP_X_SITE_SIGNATURE' => SiteSignature::sign($secret, $canonical),
        ], $headers);
        foreach ($without as $header) {
            unset($server[$header]);
        }

        return $this->call($method, $this->hq . $fullPath, $fields ?? [], [], $files, $server, $content);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n" . Str::random(20));
    }

    private function careerFields(string $email): array
    {
        return [
            'full_name' => 'Ada Test', 'email' => $email, 'country_code' => '+234', 'phone' => '8000000000', 'city' => 'Lagos',
            'primary_skill' => 'Data Engineering', 'secondary_skillset' => 'SQL', 'linkedin_url' => 'https://www.linkedin.com/in/test',
            'praxis_answer' => 'Theory put into practice.',
        ];
    }

    private function enquiry(string $email, string $company): array
    {
        return ['full_name' => 'Adaeze Okonkwo-Bello', 'email' => $email, 'company_name' => $company, 'message' => 'We would like to talk.'];
    }

    private function booking(string $email, string $company, string $date, string $time, string $focus = 'Performance Audit'): array
    {
        return ['full_name' => 'Adaeze Okafor', 'email' => $email, 'phone' => '+234 800 000 0000', 'enterprise_name' => $company,
            'focus' => $focus, 'date' => $date, 'time' => $time];
    }

    private function assessment(string $email): array
    {
        return ['email' => $email, 'name' => 'Ada Test', 'company' => 'Calc Test Co', 'headcount' => 50, 'turnover' => 500000, 'currency' => 'USD',
            'manual_hrs' => 5, 'hourly_cost' => 20, 'error_rate' => 3];
    }

    /** A weekday at least $days days from today (Lagos time), as Y-m-d */
    private function nextWeekday(int $days): string
    {
        $date = Carbon::today('Africa/Lagos')->addDays($days);
        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date->format('Y-m-d');
    }
}
