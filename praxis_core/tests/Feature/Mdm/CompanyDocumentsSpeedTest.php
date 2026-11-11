<?php

namespace Tests\Feature\Mdm;

use App\Livewire\Hq\Mdm\CompanyDocumentPage;
use App\Livewire\Hq\Mdm\CompanyDocuments;
use App\Models\AiRun;
use App\Support\CompanyDocuments\Reader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Company documents do less on each request (performance pass, 11 Nov 2026): while Claude is still reading, the
 * three-second check sends nothing back; and the calendar reads every open deadline but only the last ten done.
 */
class CompanyDocumentsSpeedTest extends TestCase
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

    public function test_the_reading_check_sends_nothing_back_until_claude_has_read_the_document(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $this->ai->respond(Reader::PURPOSE, $this->certificateReading());
        $doc = $this->upload($owner, $ng, 'certificate_of_incorporation', ['title' => 'Certificate of incorporation']);

        // Claude still at it
        $run = AiRun::firstOrFail();
        $output = $run->output;
        $run->forceFill(['status' => 'running', 'output' => null])->save();
        $page = Livewire::test(CompanyDocumentPage::class, ['document' => $doc->fresh()])->assertSeeHtml('wire:poll.3s="checkReading"');

        $page->call('checkReading');
        $this->assertArrayNotHasKey('html', $page->effects, 'Nothing changed, so nothing is drawn or sent');

        // Read: the next check draws the panel and stops polling
        $run->forceFill(['status' => 'done', 'output' => $output])->save();
        $page->call('checkReading');
        $this->assertArrayHasKey('html', $page->effects);
        $page->assertDontSeeHtml('wire:poll.3s="checkReading"')->assertSee('Read by Claude from version 1');
    }

    public function test_the_calendar_shows_every_open_deadline_and_only_the_last_ten_done(): void
    {
        $this->company('NG');
        $this->actingAs($this->owner());
        foreach (range(1, 14) as $i) {
            $this->insertRow('company_deadlines', ['id' => (string) Str::uuid(), 'title' => sprintf('Done filing %02d', $i), 'due_on' => '2026-01-01',
                'done_at' => now()->subDays(30 - $i), 'archived_at' => null]);
        }
        $this->insertRow('company_deadlines', ['id' => (string) Str::uuid(), 'title' => 'Annual return', 'due_on' => '2026-12-01', 'done_at' => null, 'archived_at' => null]);

        DB::enableQueryLog();
        $page = Livewire::test(CompanyDocuments::class)->set('tab', 'calendar');
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();

        $page->assertSee('Annual return')->assertSee('Done filing 14')->assertSee('Done filing 05')->assertDontSee('Done filing 04');
        $this->assertStringContainsString('`done_at` is not null order by `done_at` desc, `due_on` asc limit 10', $sql);
    }
}
