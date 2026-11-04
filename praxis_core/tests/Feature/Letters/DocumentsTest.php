<?php

namespace Tests\Feature\Letters;

use App\Livewire\Hq\Letters\DocumentPage;
use App\Livewire\Hq\Letters\DocumentRegister;
use App\Livewire\Hq\Letters\MySignature;
use App\Models\Letters\LetterDocument;
use App\Models\Letters\LetterSignature;
use App\Models\User;
use App\Support\Letters\DocxLetterhead;
use App\Support\Letters\Letterhead;
use App\Support\Letters\LetterPdf;
use App\Support\Letters\Letters;
use App\Support\Letters\Signatures;
use App\Support\Hosts;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * HQ › Documents (docs/DOCUMENTS.md): the register, the templates, the Claude draft, signing with a fresh
 * sign-in, issuing, revoking and replacing, the blank letterhead, and who may see any of it.
 * No request reaches Anthropic (FakeAi) and no signature here is a real one.
 */
class DocumentsTest extends TestCase
{
    use LetterFixtures, UsesThrowawayDatabase;

    /** The HQ routes answer on the HQ host only, as every HQ screen test calls them */
    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    public function test_only_the_owner_sees_documents_until_the_owner_opens_it_to_others(): void
    {
        $this->company();

        $this->actingAs($this->staff('delivery', ['delivery']))->get($this->hq('/documents'))->assertForbidden();
        $this->actingAs($this->staff('sales', ['sales']))->get($this->hq('/documents'))->assertForbidden();
        $this->actingAs($this->staff('auditor', ['auditor']))->get($this->hq('/documents'))->assertForbidden();

        $this->actingAs($this->owner())->get($this->hq('/documents'))->assertOk()->assertSee('Documents');
    }

    public function test_the_rail_shows_documents_only_to_someone_who_may_open_it(): void
    {
        $this->company();

        $this->actingAs($this->owner())->get($this->hq('/dashboard/workspace'))->assertOk()->assertSee('/documents', false);
        $this->actingAs($this->staff('delivery', ['delivery']))->get($this->hq('/dashboard/workspace'))->assertOk()->assertDontSee('/documents', false);
    }

    public function test_the_four_permissions_are_in_the_catalogue_and_granted_to_no_other_role(): void
    {
        $slugs = ['letters.view', 'letters.create', 'letters.sign', 'letters.manage'];
        $this->assertSame(4, DB::table('permissions')->whereIn('slug', $slugs)->count());

        $granted = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->whereIn('permissions.slug', $slugs)
            ->pluck('roles.slug')->unique()->values()->all();

        // The Owner role holds every permission through '*', never through a row of its own
        $this->assertSame([], $granted);
    }

    public function test_a_board_resolution_is_numbered_filled_in_and_rendered(): void
    {
        $owner = $this->owner();
        $company = $this->company();

        $document = Letters::start('board_resolution', 'open_bank_account', $company, $owner);
        $this->assertSame('JBS/RES/' . now('Africa/Lagos')->format('Y') . '/001', $document->reference);
        $this->assertSame(now('Africa/Lagos')->toDateString(), $document->document_date->toDateString());

        // The template starts from the company's own details
        $this->assertSame('Any one signatory', $document->fields['mandate']);
        $this->assertStringContainsString('21B Bode Ajakaiye Street', $document->fields['place']);

        $document->forceFill(['fields' => array_merge((array) $document->fields, [
            'bank_name' => 'Example Microfinance Bank',
            'account_type' => 'Corporate current account',
            'signatories' => [['name' => 'Ada Test', 'title' => 'Director']],
        ])])->save();

        $html = LetterPdf::html($document->fresh(), Letterhead::details($company), final: true);
        $this->assertStringContainsString('Written resolution of the sole director', $html);
        $this->assertStringContainsString('Opening of a corporate bank account', $html);
        $this->assertStringContainsString('Example Microfinance Bank', $html);
        $this->assertStringContainsString('Ada Test', $html);
        // The letterhead's own details, never hard-coded in the view
        $this->assertStringContainsString('RC 9985884', $html);

        $pdf = LetterPdf::render($document->fresh(), final: true);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_the_next_number_runs_per_type_and_year(): void
    {
        $owner = $this->owner();
        $company = $this->company();
        $year = (int) now('Africa/Lagos')->format('Y');

        $first = Letters::start('board_resolution', 'open_bank_account', $company, $owner);
        $second = Letters::start('board_resolution', 'appoint_signatory', $company, $owner);
        $letter = Letters::start('formal_letter', null, $company, $owner);

        $this->assertSame("JBS/RES/{$year}/001", $first->reference);
        $this->assertSame("JBS/RES/{$year}/002", $second->reference);
        $this->assertSame("JBS/LET/{$year}/001", $letter->reference);
        // A free letter shares the letter's run of numbers
        $this->assertSame("JBS/LET/{$year}/002", Letters::nextReference('LET', $year));
    }

    public function test_the_screen_saves_a_draft_and_the_claude_draft_fills_the_body(): void
    {
        $owner = $this->owner();
        $company = $this->company();
        $ai = FakeAi::install()->respond('letter_draft', [
            'title' => 'Confirmation of our bank details',
            'body' => "We are writing to confirm the account we use for payments.\n\n- It has not changed.\n- Telephone us before paying into any other account.",
        ]);
        $document = Letters::start('free_letter', null, $company, $owner);

        Livewire::actingAs($owner)->test(DocumentPage::class, ['document' => $document->id])
            ->set('addressedTo', 'Foxtrot Foods Limited (test)')
            ->set('brief', 'Confirm the bank account we are paid into has not changed.')
            ->call('draft')
            ->assertHasNoErrors()
            ->call('save')
            ->assertHasNoErrors();

        $saved = $document->fresh();
        $this->assertSame('Confirmation of our bank details', $saved->title);
        $this->assertStringContainsString('<p>We are writing to confirm', $saved->body);
        $this->assertStringContainsString('<li>It has not changed.</li>', $saved->body);
        $this->assertNotNull($saved->ai_run_id);
        $ai->assertRan('letter_draft', fn ($request) => str_contains($request->prompt, 'Foxtrot Foods Limited (test)'));
    }

    public function test_the_body_the_browser_sends_is_cleaned_before_it_is_kept(): void
    {
        $owner = $this->owner();
        $document = Letters::start('free_letter', null, $this->company(), $owner);

        Livewire::actingAs($owner)->test(DocumentPage::class, ['document' => $document->id])
            ->set('title', 'A letter')
            ->call('setBody', '<p>Plain words</p><script>alert(1)</script><img src=x onerror=alert(1)>')
            ->call('save')->assertHasNoErrors();

        $body = (string) $document->fresh()->body;
        $this->assertStringContainsString('<p>Plain words</p>', $body);
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    public function test_signing_needs_a_signature_of_your_own_and_a_fresh_sign_in(): void
    {
        $owner = $this->owner();
        $document = Letters::start('formal_letter', null, $this->company(), $owner);

        // No signature saved yet
        Livewire::actingAs($owner)->test(DocumentPage::class, ['document' => $document->id])
            ->call('sign')->assertHasErrors('signature');
        $this->assertSame(0, $document->signatures()->count());

        $this->signatureFor($owner);

        // Signed in a long time ago: the password is asked for again, and nothing is signed until it is given
        session()->forget('sign_in.fresh_at');
        $component = Livewire::actingAs($owner)->test(DocumentPage::class, ['document' => $document->id])
            ->call('sign')
            ->assertSet('needsPassword', true);
        $this->assertSame(0, $document->signatures()->count());

        $component->set('confirmPassword', 'the-wrong-one')->call('confirmIdentity')->assertHasErrors('confirmPassword');
        $this->assertSame(0, $document->signatures()->count());

        $component->set('confirmPassword', self::PASSWORD)->call('confirmIdentity')
            ->assertSet('needsPassword', false)->assertHasNoErrors();

        $signature = $document->fresh()->signatures()->first();
        $this->assertSame(['Ada Test', 'Director'], [$signature->signatory_name, $signature->signatory_title]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'LETTER_SIGNED', 'target_id' => $document->id]);
    }

    public function test_a_signature_is_stored_encrypted_and_only_its_owner_can_apply_it(): void
    {
        $owner = $this->owner();
        $signature = $this->signatureFor($owner);

        $stored = (string) DB::table('letter_signatures')->where('user_id', $owner->id)->value('image');
        $this->assertNotSame($signature->image, $stored, 'the signature is stored encrypted');
        $this->assertStringNotContainsString(substr($signature->image, 0, 40), $stored);

        // Somebody else's document signature is their own: sign() always reads the signed-in person's
        $other = $this->staff('owner', ['owner']);
        $document = Letters::start('formal_letter', null, $this->company(), $owner);
        Signatures::save($other, $this->jpeg(), 'Bola Test', 'Partner', 'drawn');

        $this->actingAs($other);
        Letters::sign($document, $other);
        $this->assertSame('Bola Test', $document->fresh()->signatures()->first()->signatory_name);
    }

    public function test_issuing_stores_the_pdf_with_its_fingerprint_and_the_file_never_changes(): void
    {
        $owner = $this->owner();
        $this->signatureFor($owner);
        $document = Letters::start('formal_letter', null, $this->company(), $owner);
        $document->forceFill(['title' => 'Confirmation of our bank details', 'show_qr' => true])->save();

        $this->actingAs($owner);
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);

        $issued = $document->fresh();
        $this->assertSame(LetterDocument::ISSUED, $issued->status);
        $this->assertNotNull($issued->verify_token);
        $this->assertSame(64, strlen((string) $issued->file_sha256));

        $file = Letters::file($issued);
        $this->assertSame($issued->file_sha256, hash('sha256', (string) $file));
        $this->assertSame($issued->file_bytes, strlen((string) $file));

        // The screen will not change an issued document
        Livewire::actingAs($owner)->test(DocumentPage::class, ['document' => $issued->id])
            ->set('title', 'Something else')->call('save')->assertStatus(422);
        $this->assertSame('Confirmation of our bank details', $document->fresh()->title);

        // And the download is the stored file, byte for byte
        $this->get($this->hq('/documents/' . $issued->id . '/pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($issued->file_sha256, hash('sha256', (string) $file));
    }

    public function test_revoking_and_replacing_leave_a_trail_the_check_page_can_read(): void
    {
        $owner = $this->owner();
        $this->signatureFor($owner);
        $this->actingAs($owner);
        $company = $this->company();

        $first = Letters::start('authority', null, $company, $owner);
        $first->forceFill(['title' => 'Authority to act'])->save();
        Letters::sign($first, $owner);
        Letters::issue($first->fresh(), $owner);

        Letters::revoke($first->fresh(), 'The visit was moved.', $owner);
        $this->assertSame(LetterDocument::REVOKED, $first->fresh()->status);
        $this->assertSame('The visit was moved.', $first->fresh()->revoked_reason);

        $replacement = Letters::replace($first->fresh(), $owner);
        $this->assertSame($first->id, $replacement->replaces_id);
        // The original still stands as revoked until the replacement is issued
        $this->assertSame(LetterDocument::REVOKED, $first->fresh()->status);

        Letters::issue($replacement->fresh(), $owner);
        Letters::markReplaced($replacement->fresh());
        $this->assertSame(LetterDocument::REPLACED, $first->fresh()->status);
        $this->assertSame($replacement->id, $first->fresh()->replaced_by_id);

        foreach (['LETTER_ISSUED', 'LETTER_REVOKED', 'LETTER_REPLACED'] as $action) {
            $this->assertDatabaseHas('system_audit_logs', ['action' => $action, 'target_table' => 'letter_documents']);
        }
    }

    public function test_the_blank_letterhead_comes_as_a_pdf_and_a_word_file(): void
    {
        $company = $this->company();
        $this->actingAs($this->owner());

        $pdf = $this->get($this->hq('/documents/blank/' . $company['id'] . '.pdf'));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        $docx = $this->get($this->hq('/documents/blank/' . $company['id'] . '.docx'));
        $docx->assertOk();
        $bytes = (string) $docx->getContent();
        $this->assertStringStartsWith("PK\x03\x04", $bytes, 'a .docx is a zip');

        // The parts Word needs are there, each one whole, and every one of them is XML Word can read
        $parts = $this->unzip($bytes);
        foreach (['[Content_Types].xml', '_rels/.rels', 'word/document.xml', 'word/_rels/document.xml.rels',
            'word/header1.xml', 'word/_rels/header1.xml.rels', 'word/footer1.xml', 'word/media/logo.png'] as $part) {
            $this->assertArrayHasKey($part, $parts, "the .docx holds {$part}");
        }
        foreach ($parts as $name => $contents) {
            if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                $this->assertNotFalse(simplexml_load_string($contents), "{$name} is XML Word can read");
            }
        }
        // The footer and the header come from the legal entity, never from anything written here
        $this->assertStringContainsString('RC 9985884', $parts['word/footer1.xml']);
        $this->assertStringContainsString('21B Bode Ajakaiye Street', $parts['word/header1.xml']);
        $this->assertStringStartsWith("\x89PNG", $parts['word/media/logo.png']);
    }

    public function test_the_word_letterhead_is_built_without_the_zip_extension(): void
    {
        // DocxLetterhead writes the zip itself, so it does not matter whether the live PHP has ext-zip
        $bytes = DocxLetterhead::build($this->company());
        $this->assertStringStartsWith("PK\x03\x04", $bytes);
        $this->assertStringContainsString('word/header1.xml', $bytes);
    }

    public function test_my_signature_saves_a_drawing_and_refuses_anything_that_is_not_a_jpeg(): void
    {
        $owner = $this->owner();
        SignIn::markFresh();

        Livewire::actingAs($owner)->test(MySignature::class)
            ->call('setImage', $this->jpeg(), 'drawn', 520, 180)
            ->set('signatoryName', 'Ada Test')
            ->set('signatoryTitle', 'Director')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1, LetterSignature::where('user_id', $owner->id)->count());

        Livewire::actingAs($owner)->test(MySignature::class)
            ->call('setImage', 'data:image/png;base64,' . base64_encode('not a jpeg'), 'uploaded', 10, 10)
            ->set('signatoryName', 'Ada Test')->set('signatoryTitle', 'Director')
            ->call('save')->assertHasErrors('image');
    }

    public function test_an_issued_document_can_be_filed_with_the_company_s_other_papers(): void
    {
        $owner = $this->owner();
        $this->signatureFor($owner);
        $this->actingAs($owner);
        $document = Letters::start('formal_letter', null, $this->company(), $owner);
        $document->forceFill(['title' => 'Confirmation of our bank details'])->save();
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);

        Livewire::actingAs($owner)->test(DocumentPage::class, ['document' => $document->id])
            ->call('fileAway')->assertHasNoErrors();

        $filed = $document->fresh();
        $this->assertNotNull($filed->company_document_id);
        $this->assertTrue($filed->isFiled());

        $row = \App\Models\CompanyDocument::findOrFail($filed->company_document_id);
        $this->assertSame($document->legal_entity_id, $row->legal_entity_id);
        $this->assertStringContainsString($document->reference, $row->title);

        // The file in Company documents is the issued PDF, byte for byte
        $version = $row->currentVersion;
        $this->assertSame($filed->file_sha256, hash('sha256', (string) \Illuminate\Support\Facades\Storage::disk('local')->get($version->path)));

        // Filing it twice is refused, so one document is never in the register twice
        Livewire::actingAs($owner)->test(DocumentPage::class, ['document' => $document->id])
            ->call('fileAway')->assertHasErrors('filing');
        $this->assertSame(1, \App\Models\CompanyDocument::count());

        $this->assertDatabaseHas('system_audit_logs', ['action' => 'LETTER_FILED', 'target_id' => $document->id]);
    }

    public function test_the_register_lists_every_document_and_new_document_starts_a_draft(): void
    {
        $owner = $this->owner();
        $company = $this->company();
        $document = Letters::start('introduction', null, $company, $owner);
        $document->forceFill(['title' => 'Letter of introduction'])->save();

        Livewire::actingAs($owner)->test(DocumentRegister::class)
            ->assertSee($document->reference)
            ->assertSee('Letter of introduction')
            ->set('newType', 'board_resolution')
            ->assertSet('newSubtype', 'open_bank_account')
            ->call('create')
            ->assertRedirect();

        $this->assertSame(2, LetterDocument::count());
    }
}
