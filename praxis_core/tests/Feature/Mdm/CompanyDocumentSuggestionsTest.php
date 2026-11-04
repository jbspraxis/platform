<?php

namespace Tests\Feature\Mdm;

use App\Livewire\Hq\Mdm\CompanyDocumentPage;
use App\Models\AiRun;
use App\Models\CompanyDeadline;
use App\Models\CompanyDocument;
use App\Support\Ai\Ai;
use App\Support\Ai\AiRequest;
use App\Support\Ai\Budget;
use App\Support\Ai\ClaudeProvider;
use App\Support\CompanyDocuments\Documents;
use App\Support\CompanyDocuments\Reader;
use App\Support\Import\Xlsx\Zip;
use App\Support\Integrations as Settings;
use App\Support\WebsiteSettings;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Claude reads uploads and suggests details (owner's decision 4, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md): the file
 * goes to Claude as a document or image block; the panel shows HQ's value next to the document's; nothing changes
 * until Apply; where they disagree HQ keeps its value unless the row is ticked (the misprinted address); Apply goes
 * through Legal entities, Website settings and the calendar with their audit lines; when Claude can't read the file
 * the person types the details.
 */
class CompanyDocumentSuggestionsTest extends TestCase
{
    use CompanyDocumentFixtures, UsesThrowawayDatabase;

    private FakeAi $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00:00', 'UTC'));
        Storage::fake('local');
        $this->ai = FakeAi::install();
    }

    private function page(CompanyDocument $doc)
    {
        return Livewire::test(CompanyDocumentPage::class, ['document' => $doc->fresh()]);
    }

    private function entity(string $id): object
    {
        return DB::table('mdm_legal_entities')->where('id', $id)->first();
    }

    public function test_claude_reads_the_certificate_and_hq_keeps_its_address_unless_ticked(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $this->ai->respond(Reader::PURPOSE, $this->certificateReading());

        $doc = $this->upload($owner, $ng, 'certificate_of_incorporation', ['title' => 'Certificate of incorporation']);

        // The PDF itself went to Claude, as an attachment; the run is about this version, and only its path is stored
        $this->ai->assertRan(Reader::PURPOSE, fn (AiRequest $r) => count($r->attachments) === 1 && $r->attachments[0]['media_type'] === 'application/pdf'
            && str_contains($r->prompt, 'KIND: Certificate of incorporation') && str_contains($r->prompt, 'COUNTRY: Nigeria') && $r->model === 'claude-haiku-5-5');
        $run = AiRun::firstOrFail();
        $this->assertSame(['company_document_version', $doc->current_version_id, 'done'], [$run->subject_type, $run->subject_id, $run->status]);
        $this->assertSame([['path' => $doc->currentVersion->path, 'media_type' => 'application/pdf']], $run->input['files']);

        // Nothing has changed yet
        $this->assertNull($this->entity($ng)->registration_number);

        $this->actingAs($owner);
        $page = $this->page($doc)
            ->assertSee('Suggested details')->assertSee('Read by Claude from version 1')
            ->assertSee('The document disagrees with HQ.')->assertSee('HQ keeps “' . self::HQ_ADDRESS . '” unless you tick this row.')
            ->assertSet('values.entity__registered_address', self::MISPRINTED_ADDRESS)
            // Empty in HQ: ticked. Different from HQ: not ticked. The website's registration is still in progress: ticked
            ->assertSet('ticks.entity__registration_number', true)->assertSet('ticks.entity__tax_identification_number', true)
            ->assertSet('ticks.entity__incorporated_on', true)->assertSet('ticks.entity__company_type', true)->assertSet('ticks.entity__share_capital', true)
            ->assertSet('ticks.entity__registered_address', false)->assertSet('ticks.entity__legal_name', false)
            ->assertSet('ticks.website__legal_name', false)->assertSet('ticks.website__registration', true)
            ->assertSet('ticks.document__issued_on', true);
        $this->assertNull($this->entity($ng)->registration_number);

        $page->call('applySuggestions')->assertHasNoErrors()
            ->assertSee('Applied.')->assertSee('Company registration number (RC): 1234567')
            ->assertSee('Kept HQ’s registered office address: the document says “' . self::MISPRINTED_ADDRESS . '”.');

        $entity = $this->entity($ng);
        $this->assertSame(['1234567', '9876543210', '2026-11-04', 'Private company limited by shares', 'NGN 1,000,000', self::HQ_ADDRESS, 'JBS Praxis LLC'],
            [$entity->registration_number, $entity->tax_identification_number, substr($entity->incorporated_on, 0, 10), $entity->company_type, $entity->share_capital,
                $entity->registered_address, $entity->legal_name]);

        // Website settings: the registration row, through WebsiteSettings::set; the legal name is kept
        $ngRow = collect(WebsiteSettings::get('company.registrations'))->firstWhere('country', 'NG');
        $this->assertSame(['country' => 'NG', 'label' => 'RC', 'number' => '1234567', 'status' => 'registered'], $ngRow);
        $this->assertSame('JBS Praxis LLC', WebsiteSettings::get('company.legal_name'));
        $this->assertSame('2026-11-04', $doc->fresh()->issued_on->format('Y-m-d'));

        // Each contract's own audit line, and the document's
        $this->assertSame(1, DB::table('system_audit_logs')->where('action', 'LEGAL_ENTITY_UPDATED')->count());
        $this->assertSame(1, DB::table('system_audit_logs')->where('action', 'WEBSITE_SETTINGS_UPDATED')->count());
        $applied = DB::table('system_audit_logs')->where('action', 'COMPANY_DOCUMENT_DETAILS_APPLIED')->first();
        $this->assertSame($owner->id, $applied->user_id);
        $this->assertStringContainsString('Kept HQ’s registered office address', $applied->description);
        $this->assertSame(null, json_decode($applied->old_values, true)['entity.registration_number']);

        $version = $doc->fresh()->currentVersion;
        $this->assertSame($owner->id, $version->reviewed_by);
        $this->assertArrayHasKey('entity.registration_number', $version->applied);
        $this->assertArrayNotHasKey('entity.registered_address', $version->applied);

        // Afterwards the panel says what was applied, and can be opened again
        $this->page($doc)->assertSee('Details from version 1 were applied')->assertSee('Review the suggestions again')
            ->call('startReview')->assertSet('ticks.entity__registration_number', false); // now the same as HQ
    }

    public function test_ticking_a_disagreement_applies_the_corrected_value(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $this->ai->respond(Reader::PURPOSE, $this->certificateReading());
        $doc = $this->upload($owner, $ng, 'certificate_of_incorporation');

        $this->actingAs($owner);
        $this->page($doc)
            ->set('ticks.entity__legal_name', true)->set('values.entity__legal_name', 'JBS Praxis Limited')
            ->set('ticks.website__legal_name', true)->set('values.website__legal_name', 'JBS Praxis Limited')
            ->set('ticks.entity__registered_address', true)->set('values.entity__registered_address', '21B Sample Close, Lekki Phase 1, Lagos')
            ->call('applySuggestions')->assertHasNoErrors()->assertDontSee('Kept HQ’s');

        $entity = $this->entity($ng);
        $this->assertSame(['JBS Praxis Limited', '21B Sample Close, Lekki Phase 1, Lagos'], [$entity->legal_name, $entity->registered_address]);
        $this->assertSame('JBS Praxis Limited', WebsiteSettings::get('company.legal_name'));
    }

    public function test_a_ticked_row_needs_a_value_and_nothing_ticked_applies_nothing(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $this->ai->respond(Reader::PURPOSE, $this->certificateReading(['tin' => '']));
        $doc = $this->upload($owner, $ng, 'certificate_of_incorporation');

        $this->actingAs($owner);
        $page = $this->page($doc)->assertSet('ticks.entity__tax_identification_number', false)->assertSee('Not in the document')
            ->set('ticks.entity__tax_identification_number', true)
            ->call('applySuggestions')->assertHasErrors(['values.entity__tax_identification_number'])->assertSee('Nothing was applied.');
        $this->assertNull($this->entity($ng)->registration_number); // nothing at all

        foreach (array_keys($page->get('ticks')) as $key) {
            $page->set("ticks.{$key}", false);
        }
        $page->call('applySuggestions')->assertHasErrors(['apply']);
        $this->assertSame(0, DB::table('system_audit_logs')->where('action', 'COMPANY_DOCUMENT_DETAILS_APPLIED')->count());
    }

    public function test_the_status_report_adds_its_deadlines_to_the_calendar(): void
    {
        $ng = $this->company('NG', ['registration_number' => '1234567']);
        $owner = $this->owner();
        $this->ai->respond(Reader::PURPOSE, $this->certificateReading([
            'issued_on' => '2026-11-04',
            'deadlines' => [
                ['title' => 'First accounts due (made up to 3 May 2027)', 'due_on' => '2028-05-03', 'repeats' => 'once', 'source' => 'first accounts to be made up to 03/05/2027'],
                ['title' => 'Annual return', 'due_on' => '2026-01-31', 'repeats' => 'yearly', 'source' => 'annual returns'],
            ],
        ]));
        $doc = $this->upload($owner, $ng, 'status_report', ['title' => 'CAC status report']);

        $this->actingAs($owner);
        $this->page($doc)
            ->assertSee('Compliance calendar')->assertSee('The document says: “first accounts to be made up to 03/05/2027”')->assertSee('This date has passed.')
            ->assertSet('suggestedDeadlines.0.tick', true)->assertSet('suggestedDeadlines.1.tick', false)
            ->call('applySuggestions')->assertHasNoErrors()->assertSee('Calendar: First accounts due (made up to 3 May 2027), due 3 May 2028');

        $deadline = CompanyDeadline::sole();
        $this->assertSame([$ng, '2028-05-03', 'once', 30, $doc->id], [$deadline->legal_entity_id, $deadline->due_on->format('Y-m-d'), $deadline->repeats, $deadline->remind_days, $deadline->document_id]);
        $this->assertSame(1, DB::table('system_audit_logs')->where('action', 'COMPANY_DEADLINE_ADDED')->count());

        // Read again later: the deadline is already in the calendar
        $this->page($doc)->call('startReview')->assertSet('suggestedDeadlines.0.tick', false)
            ->assertSee('Already in the calendar as ‘First accounts due (made up to 3 May 2027)’.');
    }

    public function test_the_website_rows_need_website_settings_access(): void
    {
        $ng = $this->company('NG');
        $editor = $this->editor(); // mdm.edit, no website.edit
        $this->ai->respond(Reader::PURPOSE, $this->certificateReading());
        $doc = $this->upload($this->owner(), $ng, 'certificate_of_incorporation');

        $this->actingAs($editor);
        $this->page($doc)->assertSee('Changing the website needs Website settings access.')
            ->assertSet('ticks.website__registration', false)
            ->set('ticks.website__registration', true) // forced from the browser: still refused
            ->call('applySuggestions')->assertHasNoErrors();

        $this->assertSame('1234567', $this->entity($ng)->registration_number);
        $this->assertSame('pending', collect(WebsiteSettings::get('company.registrations'))->firstWhere('country', 'NG')['status']);
        $this->assertSame(0, DB::table('system_audit_logs')->where('action', 'WEBSITE_SETTINGS_UPDATED')->count());
    }

    public function test_when_claude_cannot_read_the_file_the_person_types_the_details(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $this->ai->unconfigured();
        $doc = $this->upload($owner, $ng, 'certificate_of_incorporation');
        $this->assertSame('ai.errors.not_configured', $doc->currentVersion->read_error);
        $this->assertSame(0, AiRun::count());

        $this->actingAs($owner);
        $this->page($doc)
            ->assertSee('Claude couldn’t read this document.')->assertSee('The AI isn’t set up yet')
            ->call('typeDetails')->assertSet('values.entity__registration_number', '')->assertSet('ticks.entity__registration_number', false)
            ->set('values.entity__registration_number', '7654321')->set('ticks.entity__registration_number', true)
            ->set('values.entity__registered_address', self::MISPRINTED_ADDRESS) // typed, not ticked: HQ keeps its own
            ->call('applySuggestions')->assertHasNoErrors()->assertSee('Applied.');
        $this->assertSame(['7654321', self::HQ_ADDRESS], [$this->entity($ng)->registration_number, $this->entity($ng)->registered_address]);

        // A failed run says why, and can be read again
        $this->ai = FakeAi::install()->fail(Reader::PURPOSE, 'busy');
        $other = $this->upload($owner, $ng, 'tax_certificate', ['title' => 'TIN certificate']);
        $this->actingAs($owner);
        $this->ai = FakeAi::install()->respond(Reader::PURPOSE, $this->certificateReading(['company_name' => '', 'tin' => '1112223334']));
        $this->page($other)->assertSee('Claude is busy right now.')
            ->call('readAgain')->assertSet('values.entity__tax_identification_number', '1112223334')->assertSet('ticks.entity__tax_identification_number', true);
    }

    public function test_keep_hq_as_it_is_changes_nothing(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $this->ai->respond(Reader::PURPOSE, $this->certificateReading());
        $doc = $this->upload($owner, $ng, 'certificate_of_incorporation');

        $this->actingAs($owner);
        $this->page($doc)->call('setAside')->assertSee('HQ kept its details as they are.')->assertSee('HQ kept its details as they were for version 1');
        $this->assertNull($this->entity($ng)->registration_number);
        $this->assertSame([], $doc->fresh()->currentVersion->applied);
        $line = DB::table('system_audit_logs')->where('action', 'COMPANY_DOCUMENT_DETAILS_SET_ASIDE')->sole();
        $this->assertStringContainsString('Kept HQ’s registered office address', $line->description);
    }

    public function test_word_files_go_as_text_and_large_photos_are_not_sent(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $this->ai->respond(Reader::PURPOSE, $this->certificateReading());

        $docx = Zip::build(['word/document.xml' => '<w:document><w:body><w:p><w:r><w:t>MEMORANDUM OF ASSOCIATION OF JBS PRAXIS LIMITED</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>The share capital is NGN 1,000,000 &amp; divided into shares</w:t></w:r></w:p></w:body></w:document>']);
        $this->upload($owner, $ng, 'memorandum_and_articles', ['title' => 'Memorandum and articles'], UploadedFile::fake()->createWithContent('memart.docx', $docx));
        $this->ai->assertRan(Reader::PURPOSE, fn (AiRequest $r) => $r->attachments === []
            && str_contains($r->prompt, "MEMORANDUM OF ASSOCIATION OF JBS PRAXIS LIMITED\nThe share capital is NGN 1,000,000 & divided into shares"));

        $this->ai->requests = [];
        $photo = $this->upload($owner, $ng, 'tax_certificate', ['title' => 'TIN certificate photo'], UploadedFile::fake()->create('tin.png', 6000, 'image/png'));
        $this->assertSame('company_documents.read.image_too_big', $photo->currentVersion->read_error);
        $this->ai->assertNothingRan();
        $this->actingAs($owner);
        $this->page($photo)->assertSee('This image is over 5 MB, more than Claude can read.');
    }

    public function test_claude_receives_the_pdf_as_a_document_block_and_a_photo_as_an_image_block(): void
    {
        Storage::disk('local')->put('company-documents/x/cert.pdf', "%PDF-1.4\n1 0 obj << /Type /Pages /Count 2 >>\n2 0 obj << /Type /Page >>\n3 0 obj << /Type /Page >>\n%%EOF");
        Storage::disk('local')->put('company-documents/x/tin.png', 'PNG-BYTES');
        $request = Ai::request(Reader::PURPOSE, ['material' => 'KIND: Certificate of incorporation', 'files' => [
            ['path' => 'company-documents/x/cert.pdf', 'media_type' => 'application/pdf'],
            ['path' => 'company-documents/x/tin.png', 'media_type' => 'image/png'],
        ]]);
        $this->assertSame(2, $request->attachments[0]['pages']);
        $this->assertSame(2 * AiRequest::TOKENS_PER_PDF_PAGE + AiRequest::TOKENS_PER_IMAGE, $request->attachmentTokens());
        $this->assertGreaterThan(Budget::worstCase(Ai::request(Reader::PURPOSE, ['material' => 'KIND: Certificate of incorporation'])), Budget::worstCase($request));

        Settings::set('anthropic.api_key', 'sk-ant-api03-FAKEKEYFORTESTSONLY_0123456789abcdef');
        config(['ai.providers.claude.max_retries' => 0]);
        $sent = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($this->certificateReading())]],
            'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => ['input_tokens' => 2400, 'output_tokens' => 300],
        ]))]));
        $stack->push(Middleware::history($sent));

        (new ClaudeProvider(new Guzzle(['handler' => $stack])))->generate($request);

        $sent[0]['request']->getBody()->rewind();
        $content = json_decode((string) $sent[0]['request']->getBody(), true)['messages'][0]['content'];
        $this->assertEquals(['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf',
            'data' => base64_encode(Storage::disk('local')->get('company-documents/x/cert.pdf'))]], $content[0]);
        $this->assertEquals(['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => base64_encode('PNG-BYTES')]], $content[1]);
        $this->assertSame('text', $content[2]['type']);
        $this->assertStringContainsString('KIND: Certificate of incorporation', $content[2]['text']);

        // Without files the message stays plain text, as every other purpose sends it
        $plain = (new ClaudeProvider())->params(Ai::request(Reader::PURPOSE, ['material' => 'x']));
        $this->assertIsString($plain['messages'][0]['content']);
    }
}
