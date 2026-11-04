<?php

namespace Tests\Feature\Mdm;

use App\Livewire\Hq\Mdm\CompanyDocumentPage;
use App\Livewire\Hq\Mdm\CompanyDocuments;
use App\Livewire\Hq\Mdm\LegalEntityRegistry;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Support\CompanyDocuments\Companies;
use App\Support\CompanyDocuments\Documents;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Master data › Company documents (owner's decisions 1 and 2, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md): per company,
 * private files served through a permission check, versions kept on replace, soft archive, every view and download
 * in the audit log, and who may do what (mdm.view sees and downloads; create uploads; edit replaces and edits;
 * delete archives).
 */
class CompanyDocumentsTest extends TestCase
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

    private function audits(string $action): \Illuminate\Support\Collection
    {
        return DB::table('system_audit_logs')->where('action', $action)->get();
    }

    public function test_an_editor_uploads_a_document_to_the_private_disk(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $this->actingAs($owner);

        // Choosing the kind names the document after it
        Livewire::test(CompanyDocuments::class)
            ->call('openUpload')->assertSet('newDoc.company', $ng)->assertSet('newDoc.remind_days', '30')
            ->set('newDoc.kind', 'bank_letter')->assertSet('newDoc.title', 'Bank reference or account letter')
            ->set('newDoc.title', 'Account confirmation letter')
            ->set('newDoc.issued_on', '2026-11-01')
            ->set('file', $this->pdf('account-letter.pdf'))
            ->call('saveUpload')
            ->assertHasNoErrors()
            ->assertRedirect(route('hq.mdm.documents.show', CompanyDocument::first()));

        $doc = CompanyDocument::firstOrFail();
        $this->assertSame([$ng, 'bank_letter', 'Account confirmation letter', '2026-11-01', null, 30],
            [$doc->legal_entity_id, $doc->kind, $doc->title, $doc->issued_on->format('Y-m-d'), $doc->expires_on, $doc->remind_days]);
        $version = $doc->currentVersion;
        $this->assertSame([1, 'account-letter.pdf', 'application/pdf', $owner->id], [$version->number, $version->original_name, $version->mime, $version->uploaded_by]);
        $this->assertMatchesRegularExpression("#^company-documents/{$ng}/{$doc->id}/v1-[a-z0-9]{12}\\.pdf$#", $version->path);
        Storage::disk('local')->assertExists($version->path);
        $this->assertSame(64, strlen($version->sha256));

        $this->assertCount(1, $this->audits('COMPANY_DOCUMENT_UPLOADED'));
        $this->ai->assertNothingRan(); // a bank letter isn't one of the kinds Claude reads
    }

    public function test_upload_rules(): void
    {
        $ng = $this->company('NG');
        $this->actingAs($this->owner());
        $form = fn () => Livewire::test(CompanyDocuments::class)->call('openUpload')
            ->set('newDoc', ['company' => $ng, 'kind' => 'policy', 'title' => 'Data protection policy', 'issued_on' => '', 'expires_on' => '', 'remind_days' => '30', 'notes' => '']);

        $form()->call('saveUpload')->assertHasErrors(['file' => 'required']);
        $form()->set('file', UploadedFile::fake()->create('notes.txt', 4, 'text/plain'))->call('saveUpload')->assertHasErrors(['file' => 'mimes']);
        // Over 20 MB: refused as it uploads (Livewire's own check, in HQ's words), so the screen never gets it
        $form()->set('file', UploadedFile::fake()->create('scan.pdf', Documents::MAX_KB + 1, 'application/pdf'))->assertHasErrors(['file'])
            ->assertSee('The file is over 20 MB.')->assertSet('file', null);
        $form()->set('newDoc.title', '')->set('file', $this->pdf())->call('saveUpload')->assertHasErrors(['newDoc.title']);
        $form()->set('newDoc.company', 'not-a-company')->set('file', $this->pdf())->call('saveUpload')->assertHasErrors(['newDoc.company']);
        $form()->set('newDoc.issued_on', '2026-11-04')->set('newDoc.expires_on', '2026-10-01')->set('file', $this->pdf())->call('saveUpload')
            ->assertHasErrors(['newDoc.expires_on']);
        $form()->set('newDoc.remind_days', '0')->set('file', $this->pdf())->call('saveUpload')->assertHasErrors(['newDoc.remind_days']);

        $this->assertSame(0, CompanyDocument::count());
        $this->assertSame([], Storage::disk('local')->allFiles(Documents::FOLDER));
    }

    public function test_files_are_served_with_a_permission_check_and_every_view_and_download_is_logged(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $doc = $this->upload($owner, $ng, 'insurance', ['title' => 'Office insurance']);
        $version = $doc->currentVersion;

        $viewer = $this->viewer(); // mdm.view only: sees and downloads
        $this->actingAs($viewer)->get(route('hq.mdm.documents.file', [$doc, $version, 'view']))
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-disposition', 'inline; filename="Office insurance (version 1).pdf"');
        $this->actingAs($viewer)->get(route('hq.mdm.documents.file', [$doc, $version, 'download']))
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename="Office insurance (version 1).pdf"');

        $viewed = $this->audits('COMPANY_DOCUMENT_VIEWED');
        $downloaded = $this->audits('COMPANY_DOCUMENT_DOWNLOADED');
        $this->assertSame([1, 1], [$viewed->count(), $downloaded->count()]);
        $this->assertSame([$viewer->id, 'company_documents', $doc->id], [$viewed[0]->user_id, $viewed[0]->target_table, $viewed[0]->target_id]);
        $this->assertStringContainsString('Viewed ‘Office insurance’', $viewed[0]->description);

        // No Master data access, signed out, or a version of another document: nothing
        $this->actingAs($this->outsider())->get(route('hq.mdm.documents.file', [$doc, $version, 'view']))->assertForbidden();
        $other = $this->upload($owner, $ng, 'policy');
        $this->actingAs($viewer)->get(route('hq.mdm.documents.file', [$doc, $other->currentVersion, 'download']))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->get(route('hq.mdm.documents.file', [$doc, $version, 'view']))->assertRedirect();
        $this->assertSame(2, $this->audits('COMPANY_DOCUMENT_VIEWED')->count() + $this->audits('COMPANY_DOCUMENT_DOWNLOADED')->count());

        // A Word file never opens in the browser: it downloads
        $word = $this->upload($owner, $ng, 'agreement', ['title' => 'Office lease'],
            UploadedFile::fake()->create('lease.docx', 30, Documents::TYPES['docx']));
        $this->actingAs($viewer)->get(route('hq.mdm.documents.file', [$word, $word->currentVersion, 'view']))
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename="Office lease (version 1).docx"');
    }

    public function test_replacing_the_file_keeps_every_version(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $doc = $this->upload($owner, $ng, 'licence', ['title' => 'Business premises permit', 'expires_on' => '2026-12-31']);
        $first = $doc->currentVersion;

        $editor = $this->editor();
        $this->actingAs($editor);
        Livewire::test(CompanyDocumentPage::class, ['document' => $doc])
            ->call('openReplace')->assertSee('The new file becomes version 2')
            ->set('replacement', $this->pdf('permit-2027.pdf'))
            ->call('replaceFile')->assertHasNoErrors()
            ->assertSee('Version 2 uploaded. The earlier versions are kept below.')
            ->assertSee('permit-2027.pdf')->assertSee('certificate.pdf');

        $doc->refresh();
        $this->assertSame(2, $doc->currentVersion->number);
        $this->assertSame($editor->id, $doc->currentVersion->uploaded_by);
        $this->assertSame([2, 1], CompanyDocumentVersion::where('document_id', $doc->id)->orderByDesc('number')->pluck('number')->all());
        Storage::disk('local')->assertExists($first->path);
        $this->actingAs($editor)->get(route('hq.mdm.documents.file', [$doc, $first, 'download']))->assertOk();
        $this->assertCount(1, $this->audits('COMPANY_DOCUMENT_REPLACED'));
    }

    public function test_editing_details_and_archiving_keeps_everything(): void
    {
        $ng = $this->company('NG');
        $cm = $this->company('CM');
        $owner = $this->owner();
        $doc = $this->upload($owner, $ng, 'policy', ['title' => 'Travel policy']);

        $editor = $this->editor();
        $this->actingAs($editor);
        Livewire::test(CompanyDocumentPage::class, ['document' => $doc])
            ->call('startEdit')->assertSet('form.company', $ng)
            ->set('form.title', 'Travel and expenses policy')->set('form.company', $cm)->set('form.expires_on', '2027-06-30')->set('form.remind_days', '60')
            ->call('saveDetails')->assertHasNoErrors()->assertSee('Details saved.')
            // No mdm.delete: no archive
            ->assertDontSee('Archive document')
            ->call('askArchive')->assertForbidden();

        $doc->refresh();
        $this->assertSame(['Travel and expenses policy', $cm, '2027-06-30', 60], [$doc->title, $doc->legal_entity_id, $doc->expires_on->format('Y-m-d'), $doc->remind_days]);
        $edited = $this->audits('COMPANY_DOCUMENT_EDITED')->first();
        $this->assertEquals(['legal_entity_id' => $ng, 'title' => 'Travel policy', 'expires_on' => null, 'remind_days' => 30], json_decode($edited->old_values, true));

        $this->actingAs($owner);
        Livewire::test(CompanyDocumentPage::class, ['document' => $doc])
            ->call('askArchive')->assertSee('Archive ‘Travel and expenses policy’?')
            ->call('archiveDocument')->assertSee('Document archived.')->assertSee('Restore document');
        $this->assertNotNull($doc->fresh()->archived_at);
        Storage::disk('local')->assertExists($doc->currentVersion->path); // never deleted

        Livewire::test(CompanyDocuments::class)->assertDontSee('Travel and expenses policy')->assertSee('Show archived (1)')
            ->set('archived', true)->assertSee('Travel and expenses policy');

        Livewire::test(CompanyDocumentPage::class, ['document' => $doc->fresh()])->call('restoreDocument');
        $this->assertNull($doc->fresh()->archived_at);
        $this->assertSame(1, $this->audits('COMPANY_DOCUMENT_ARCHIVED')->count());
        $this->assertSame(1, $this->audits('COMPANY_DOCUMENT_RESTORED')->count());
        $this->assertSame(1, CompanyDocument::count());
    }

    public function test_master_data_view_only_sees_and_downloads_but_changes_nothing(): void
    {
        $ng = $this->company('NG');
        $doc = $this->upload($this->owner(), $ng, 'policy', ['title' => 'Travel policy']);
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get(route('hq.mdm.documents'))->assertOk()->assertSee('Travel policy')->assertDontSee('Upload a document');
        $this->actingAs($viewer)->get(route('hq.mdm.documents.show', $doc))->assertOk()->assertDontSee('Replace file')->assertDontSee('Edit details');
        $this->actingAs($viewer);
        Livewire::test(CompanyDocuments::class)->call('openUpload')->assertForbidden();
        Livewire::test(CompanyDocuments::class)->call('newDeadline')->assertForbidden();
        Livewire::test(CompanyDocumentPage::class, ['document' => $doc])->call('startEdit')->assertForbidden();
        Livewire::test(CompanyDocumentPage::class, ['document' => $doc])->call('openReplace')->assertForbidden();
        Livewire::test(CompanyDocumentPage::class, ['document' => $doc])->call('archiveDocument')->assertForbidden();
        Livewire::test(CompanyDocumentPage::class, ['document' => $doc])->call('applySuggestions')->assertForbidden();

        // Without Master data access the screens are closed
        $outsider = $this->outsider();
        $this->actingAs($outsider)->get(route('hq.mdm.documents'))->assertForbidden();
        $this->actingAs($outsider)->get(route('hq.mdm.documents.show', $doc))->assertForbidden();
    }

    public function test_the_list_by_company_with_expired_and_due_soon_first(): void
    {
        $ng = $this->company('NG');
        $cm = $this->company('CM');
        $owner = $this->owner();
        $this->ai->unconfigured();
        $this->upload($owner, $ng, 'policy', ['title' => 'Alpha policy']);
        $this->upload($owner, $ng, 'tax_certificate', ['title' => 'Tax clearance 2025', 'expires_on' => '2026-10-31']);
        $this->upload($owner, $cm, 'licence', ['title' => 'Douala trading licence', 'expires_on' => '2026-11-20']);
        $this->upload($owner, Companies::GROUP, 'insurance', ['title' => 'Group insurance', 'expires_on' => '2027-09-30']);

        $this->actingAs($owner);
        Livewire::test(CompanyDocuments::class)
            ->assertSeeInOrder(['Tax clearance 2025', 'Douala trading licence', 'Group insurance', 'Alpha policy'])
            ->assertSee('Expired')->assertSee('Expires in 15 days')->assertSee('Valid')->assertSee('No expiry date')
            ->assertSee('Needs attention:')->assertSee('1 document has expired and 1 document expires soon')
            ->assertSee('JBS Praxis LLC (NG)')->assertSee('JBS Praxis SARL (CM)')->assertSee('Head office (group-wide)')
            ->set('company', $cm)
            ->assertSee('Douala trading licence')->assertDontSee('Tax clearance 2025')->assertDontSee('Group insurance')
            ->set('company', Companies::GROUP)
            ->assertSee('Group insurance')->assertDontSee('Douala trading licence');

        // Each company on Legal entities links to its documents, and the rail lists the page
        $this->actingAs($owner)->get(route('hq.mdm.legal'))->assertOk()
            ->assertSee(route('hq.mdm.documents', ['company' => $ng]), false)->assertSee('Documents (2)')
            ->assertSee(route('hq.mdm.documents'), false);
        Livewire::test(LegalEntityRegistry::class)->assertSee('Documents (1)');
        $this->actingAs($owner)->get(route('hq.mdm.documents', ['company' => $cm]))->assertOk()->assertSee('Douala trading licence')->assertDontSee('Alpha policy');
    }
}
