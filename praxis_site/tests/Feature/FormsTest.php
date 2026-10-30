<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The forms that only write to HQ: contact, Praxis Method PDF, feedback, Insights submission, careers.
 * Each: renders, sends the right fields, shows HQ's 422 next to the fields with values kept, PRG success,
 * the calm message when HQ is down, and the honeypot.
 */
class FormsTest extends TestCase
{
    private function invalid(array $fields, string $summary = 'Some details need attention.'): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['error' => ['code' => 'invalid', 'message' => $summary, 'fields' => $fields]], 422);
    }

    // ---- /contact

    public function test_contact_renders_with_the_optional_questions(): void
    {
        $this->fakeHq();

        $this->get('/contact')->assertOk()
            ->assertSee('Contact us')
            ->assertSee('About your organisation')
            ->assertSee('Main problem today')
            ->assertSee('(optional)')
            ->assertSee('When would you like to start?')
            ->assertSee('<option value="this_quarter" >This quarter</option>', false)
            ->assertSee('<option value="next_quarter" >Next quarter</option>', false)
            ->assertSee('<option value="later" >Later this year</option>', false)
            ->assertSee('<option value="exploring" >Just exploring</option>', false)
            ->assertDontSee('name="budget"', false)
            ->assertDontSee('$', false);
    }

    public function test_contact_sends_the_enquiry_and_shows_the_success_state(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['data' => ['received' => true]], 201)]);

        $this->from('/contact')->post('/contact', [
            'full_name' => 'Ada Test', 'email' => 'ada@example.com', 'company_name' => 'Example Ltd', 'message' => 'We need one view of our receivables.',
            'country_code' => '+234', 'phone' => '800 000 0000', 'language' => 'English (EN)', 'stage' => 'Established organisation',
            'bottleneck' => 'Outdated software', 'other_bottleneck' => 'ignored unless other', 'website' => '',
            'start_timing' => 'next_quarter', 'budget' => '$10k', 'cf-turnstile-response' => 'ignored while Turnstile is off',
        ])->assertRedirect('/contact');

        $sent = $this->sentJson('/enquiries');
        $this->assertSame('Ada Test', $sent['full_name']);
        $this->assertSame('Example Ltd', $sent['company_name']);
        $this->assertSame('+234', $sent['country_code']);
        $this->assertSame('Outdated software', $sent['bottleneck']);
        $this->assertArrayNotHasKey('other_bottleneck', $sent);
        $this->assertArrayNotHasKey('website', $sent);
        $this->assertArrayNotHasKey('_token', $sent);
        $this->assertSame('next_quarter', $sent['start_timing']);
        $this->assertArrayNotHasKey('budget', $sent, 'the budget question is retired');
        $this->assertArrayNotHasKey('form_ts', $sent);
        $this->assertArrayNotHasKey('cf-turnstile-response', $sent);

        $this->get('/contact')->assertSee('Thank you. We’ve received your message')->assertDontSee('Send message</button>', false);
    }

    public function test_contact_shows_hq_validation_errors_next_to_the_fields_and_keeps_the_values(): void
    {
        $this->fakeHq(['enquiries' => $this->invalid(['email' => ['Enter a valid email address, like name@example.com.']], 'Check the highlighted fields.')]);

        $this->from('/contact')->post('/contact', ['full_name' => 'Ada Test', 'email' => 'not-an-email', 'company_name' => 'Example Ltd', 'message' => 'Hello'])
            ->assertRedirect('/contact')
            ->assertSessionHasErrors(['email' => 'Enter a valid email address, like name@example.com.'])
            ->assertSessionHasInput('full_name', 'Ada Test');

        $this->get('/contact')->assertOk()
            ->assertSee('Check the highlighted fields.')
            ->assertSee('id="f-email-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="Ada Test"', false)
            ->assertSee('value="not-an-email"', false);
    }

    public function test_contact_keeps_the_values_and_shows_a_calm_message_when_hq_is_down(): void
    {
        Http::fake([self::HQ . '/enquiries' => $this->down(), self::HQ . '/settings' => Http::response(['data' => $this->settingsData()])]);
        $this->get('/contact'); // settings read once: the message uses their contact email

        $this->from('/contact')->post('/contact', ['full_name' => 'Ada Test', 'email' => 'ada@example.com', 'company_name' => 'X', 'message' => 'Hello'])
            ->assertRedirect('/contact')
            ->assertSessionHas('hq_error');

        $this->get('/contact')
            ->assertSee('We couldn’t send this just now because our systems didn’t respond.')
            ->assertSee('info@jbspraxis.com')
            ->assertSee('value="Ada Test"', false);
    }

    public function test_a_filled_honeypot_looks_sent_but_reaches_nobody(): void
    {
        $this->fakeHq();

        $this->from('/contact')->post('/contact', ['full_name' => 'Bot', 'email' => 'bot@example.com', 'website' => 'http://spam.example'])
            ->assertRedirect('/contact')
            ->assertSessionHas('sent', 'contact');

        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/enquiries'));
    }

    public function test_hq_rate_limiting_reads_as_a_calm_message(): void
    {
        $this->fakeHq(['enquiries' => Http::response(['error' => ['code' => 'too_many', 'retry_after' => 60]], 429)]);

        $this->from('/contact')->post('/contact', ['full_name' => 'Ada Test'])->assertRedirect('/contact');

        $this->get('/contact')->assertSee('We’ve received several requests from your connection in a short time.');
    }

    // ---- /services (Praxis Method PDF)

    public function test_services_requests_the_method_pdf(): void
    {
        $this->fakeHq(['whitepaper' => Http::response(['data' => ['sent' => true]], 201)]);

        $this->get('/services')->assertOk()->assertSee('Read the Praxis Method.')->assertSee('Send me the guide');
        $this->from('/services')->post('/services', ['email' => 'ada@example.com'])->assertRedirect(url('/services') . '#guide');

        $this->assertSame(['email' => 'ada@example.com'], $this->sentJson('/whitepaper'));
        $this->get('/services')->assertSee('Check your inbox');
    }

    public function test_services_maps_the_email_error(): void
    {
        $this->fakeHq(['whitepaper' => $this->invalid(['email' => ['Enter your email address.']])]);

        $this->from('/services')->post('/services', ['email' => ''])->assertSessionHasErrors(['email' => 'Enter your email address.']);
        $this->get('/services')->assertSee('Enter your email address.');
    }

    // ---- /feedback

    public function test_feedback_is_sent_with_its_severity(): void
    {
        $this->fakeHq(['feedback' => Http::response(['data' => ['received' => true]], 201)]);

        $this->get('/feedback')->assertOk()->assertSee('How much does it affect you?');
        $this->from('/feedback')->post('/feedback', ['category' => 'Unclear wording', 'severity' => '3', 'description' => 'The calculator labels are hard to follow.'])
            ->assertRedirect('/feedback');

        $sent = $this->sentJson('/feedback');
        $this->assertSame('3', $sent['severity']);
        $this->assertSame('Unclear wording', $sent['category']);
        $this->get('/feedback')->assertSee('Thank you for your feedback');
    }

    public function test_feedback_maps_the_description_error(): void
    {
        $this->fakeHq(['feedback' => $this->invalid(['description' => ['Write at least 10 characters.']])]);

        $this->from('/feedback')->post('/feedback', ['description' => 'short'])->assertSessionHasErrors('description');
        $this->get('/feedback')->assertSee('Write at least 10 characters.')->assertSee('>short</textarea>', false);
    }

    // ---- /kaizen/write

    public function test_an_insights_article_is_submitted_for_review(): void
    {
        $this->fakeHq(['kaizen/submissions' => Http::response(['data' => ['received' => true]], 201)]);

        $this->get('/kaizen/write')->assertOk()->assertSee('Write for JBS Praxis Insights');
        $this->from('/kaizen/write')->post('/kaizen/write', [
            'full_name' => 'Ada Test', 'email' => 'ada@example.com', 'category' => 'The Boardroom', 'article_title' => 'One ledger',
            'exec_summary' => 'Short summary.', 'vulnerability' => 'Three ledgers.', 'solution' => 'One ledger.', 'roi' => '11 days saved.',
        ])->assertRedirect('/kaizen/write');

        $sent = $this->sentJson('/kaizen/submissions');
        $this->assertSame('The Boardroom', $sent['category']);
        $this->assertSame('Three ledgers.', $sent['vulnerability']);
        $this->get('/kaizen/write')->assertSee('Your article is with us for review');
    }

    public function test_an_insights_submission_shows_field_errors(): void
    {
        $this->fakeHq(['kaizen/submissions' => $this->invalid(['exec_summary' => ['Keep the summary to 50 words or fewer.']])]);

        $this->from('/kaizen/write')->post('/kaizen/write', ['exec_summary' => str_repeat('word ', 60)])->assertSessionHasErrors('exec_summary');
        $this->get('/kaizen/write')->assertSee('Keep the summary to 50 words or fewer.');
    }

    // ---- /careers (multipart)

    public function test_careers_sends_the_application_with_the_cv_as_multipart(): void
    {
        $this->fakeHq(['careers/applications' => Http::response(['data' => ['received' => true]], 201)]);

        $this->get('/careers')->assertOk()->assertSee('enctype="multipart/form-data"', false);
        $this->from('/careers')->post('/careers', [
            'full_name' => 'Ada Test', 'email' => 'ada@example.com', 'country_code' => '+237', 'phone' => '699000000',
            'primary_skill' => 'Data engineering and reporting', 'secondary_skillset' => 'SQL', 'praxis_answer' => 'I rebuilt a month-end close.',
            'cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
        ])->assertRedirect('/careers');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/careers/applications')
            && str_contains($r->body(), 'name="primary_skill"')
            && str_contains($r->body(), 'filename="cv.pdf"')
            && $this->signatureIsValid($r));
        $this->get('/careers')->assertSee('We’ve received your application');
    }

    public function test_careers_checks_the_cv_before_sending_anything(): void
    {
        $this->fakeHq();

        $this->from('/careers')->post('/careers', ['full_name' => 'Ada Test'])->assertSessionHasErrors(['cv' => 'Attach your CV.']);
        $this->from('/careers')->post('/careers', ['cv' => UploadedFile::fake()->create('cv.exe', 10, 'application/octet-stream')])
            ->assertSessionHasErrors(['cv' => 'Your CV must be a PDF, DOC or DOCX file.']);
        $this->from('/careers')->post('/careers', ['cv' => UploadedFile::fake()->create('cv.pdf', 6000, 'application/pdf')])
            ->assertSessionHasErrors(['cv' => 'Your CV must be 5 MB or smaller.']);

        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/careers/applications'));
        $this->get('/careers')->assertSee('Attach your CV again');
    }

    public function test_careers_maps_hq_errors_onto_the_form(): void
    {
        $this->fakeHq(['careers/applications' => $this->invalid(['phone' => ['Enter a phone number.'], 'praxis_answer' => ['Tell us about a process you improved.']])]);

        $this->from('/careers')->post('/careers', ['full_name' => 'Ada Test', 'cv' => UploadedFile::fake()->create('cv.docx', 10)])
            ->assertSessionHasErrors(['phone', 'praxis_answer'])
            ->assertSessionHasInput('full_name', 'Ada Test');
        $this->get('/careers')->assertSee('Enter a phone number.')->assertSee('Tell us about a process you improved.');
    }
}
