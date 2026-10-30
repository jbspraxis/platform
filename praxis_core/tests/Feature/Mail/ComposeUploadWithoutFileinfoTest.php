<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\Composer;
use App\Models\Mail\MailOutbox;
use App\Support\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner report, 30 Oct 2026: PDFs would not attach on live. The live web PHP has no fileinfo extension, and Laravel's
 * local disk needs it ('Class "finfo" not found'). App\Support\FilesystemManager falls back to the file extension.
 */
class ComposeUploadWithoutFileinfoTest extends TestCase
{
    use MailTestKit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        FilesystemManager::$withoutFileinfo = true;
        $this->app->forgetInstance('filesystem');
        Storage::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        FilesystemManager::$withoutFileinfo = false;
        parent::tearDown();
    }

    public function test_local_disks_work_and_tell_types_from_the_extension(): void
    {
        $disk = Storage::disk('local');
        $disk->put('fileinfo-check/invoice.pdf', '%PDF-1.4 test');

        $this->assertSame('application/pdf', $disk->mimeType('fileinfo-check/invoice.pdf'));
        $disk->deleteDirectory('fileinfo-check');
    }

    public function test_a_pdf_attaches_in_the_composer(): void
    {
        $owner = $this->staff('own', ['owner']);
        $account = $this->mailbox('own@jbspraxis.com', 'personal', $owner);

        $page = Livewire::actingAs($owner)->test(Composer::class)
            ->dispatch('mail-compose', mode: 'new', accountId: $account->id);
        $page->set('uploads', [UploadedFile::fake()->createWithContent('JBS-Praxis_Invoice-002_Milestone-1.pdf', str_repeat('%PDF-1.4 ', 70000))])
            ->assertHasNoErrors();

        $outbox = MailOutbox::firstOrFail();
        $files = $outbox->payload['attachments'] ?? [];
        $this->assertCount(1, $files);
        $this->assertSame('JBS-Praxis_Invoice-002_Milestone-1.pdf', $files[0]['name']);
        $this->assertSame('application/pdf', $files[0]['mime']);
    }
}
