<?php

namespace Tests\Feature\Mdm;

use App\Livewire\Hq\Mdm\CompanyDocuments;
use App\Models\CompanyDeadline;
use App\Models\CompanyDocument;
use App\Support\CompanyDocuments\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Each company's compliance calendar and the compliance bell (owner's decision 3, 4 Nov 2026;
 * docs/COMPANY_DOCUMENTS.md): deadlines once or yearly, done with who and when (a yearly one adds next year's),
 * archived never deleted; mdm:compliance-bell rings the owner and everyone with mdm.edit once per item and date, on
 * working days at 07:30 Lagos time.
 */
class ComplianceCalendarTest extends TestCase
{
    use CompanyDocumentFixtures, UsesThrowawayDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 06:30:00', 'UTC')); // Thursday, 07:30 in Lagos
        Storage::fake('local');
        FakeAi::install()->unconfigured();
    }

    public function test_deadlines_are_added_edited_done_and_archived(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $editor = $this->editor();

        $this->actingAs($editor);
        $list = Livewire::withQueryParams(['company' => $ng, 'tab' => 'calendar'])->test(CompanyDocuments::class)
            ->assertSee('No open deadlines')
            ->call('newDeadline')->assertSet('deadlineForm.company', $ng)->assertSet('deadlineForm.repeats', 'yearly')
            ->set('deadlineForm.title', '')->call('saveDeadline')->assertHasErrors(['deadlineForm.title', 'deadlineForm.due_on'])
            ->set('deadlineForm.title', 'CAC annual return')->set('deadlineForm.due_on', '2026-11-20')
            ->call('saveDeadline')->assertHasNoErrors()
            ->assertSee('‘CAC annual return’ added to the calendar, due 20 Nov 2026.')
            ->assertSee('Due in 15 days')->assertSee('Every year')->assertSee('Bell 30 days before')
            ->assertSee('Needs attention:')->assertSee('1 deadline is due soon');
        $deadline = CompanyDeadline::sole();

        $list->call('editDeadline', $deadline->id)->set('deadlineForm.notes', 'Filed by the company secretary (test)')->call('saveDeadline')->assertHasNoErrors();
        $this->assertSame('Filed by the company secretary (test)', $deadline->fresh()->notes);

        // Done: who and when, and next year's is added
        $list->call('askDone', $deadline->id)->assertSee('Mark ‘CAC annual return’ done?')->assertSee('Next year’s is added, due 20 Nov 2027.')
            ->call('markDone')->assertSee('‘CAC annual return’ marked done. Next year’s is in the calendar, due 20 Nov 2027.')
            ->assertSee('Recently done')->assertSee('Done on 5 Nov 2026 by Finance Test');
        $deadline->refresh();
        $this->assertSame($editor->id, $deadline->done_by);
        $next = CompanyDeadline::whereNull('done_at')->sole();
        $this->assertSame(['CAC annual return', '2027-11-20', 'yearly', 30], [$next->title, $next->due_on->format('Y-m-d'), $next->repeats, $next->remind_days]);

        // Archive needs mdm.delete; nothing is deleted
        $list->call('askArchive', $next->id)->assertForbidden();
        $this->actingAs($owner);
        Livewire::withQueryParams(['tab' => 'calendar'])->test(CompanyDocuments::class)
            ->call('askArchive', $next->id)->assertSee('Archive ‘CAC annual return’?')
            ->call('archiveDeadline')->assertSee('archived. It stays in the archive; nothing was deleted.')->assertSee('No open deadlines');
        $this->assertNotNull($next->fresh()->archived_at);
        $this->assertSame(2, CompanyDeadline::count());
        $this->assertSame(['COMPANY_DEADLINE_ADDED' => 1, 'COMPANY_DEADLINE_ARCHIVED' => 1, 'COMPANY_DEADLINE_DONE' => 1, 'COMPANY_DEADLINE_EDITED' => 1],
            DB::table('system_audit_logs')->where('action', 'like', 'COMPANY_DEADLINE_%')->selectRaw('action, COUNT(*) n')->groupBy('action')->orderBy('action')->pluck('n', 'action')->map(fn ($n) => (int) $n)->all());

        // mdm.view only: no adding, editing or marking done
        $this->actingAs($this->viewer());
        Livewire::test(CompanyDocuments::class)->call('newDeadline')->assertForbidden();
        Livewire::test(CompanyDocuments::class)->call('askDone', $deadline->id)->assertForbidden();
        Livewire::test(CompanyDocuments::class)->call('editDeadline', $deadline->id)->assertForbidden();
    }

    public function test_the_compliance_bell_rings_the_owner_and_editors_once_per_item(): void
    {
        $ng = $this->company('NG');
        $owner = $this->owner();
        $editor = $this->editor();
        $viewer = $this->viewer();
        $by = $owner->id;

        $soon = CompanyDocument::create(['legal_entity_id' => $ng, 'kind' => 'tax_certificate', 'title' => 'Tax clearance certificate', 'expires_on' => '2026-12-05', 'remind_days' => 30, 'created_by' => $by]);
        CompanyDocument::create(['legal_entity_id' => $ng, 'kind' => 'licence', 'title' => 'Permit, later', 'expires_on' => '2027-03-01', 'remind_days' => 30, 'created_by' => $by]);
        CompanyDocument::create(['legal_entity_id' => $ng, 'kind' => 'licence', 'title' => 'Permit, no bell', 'expires_on' => '2026-11-10', 'remind_days' => null, 'created_by' => $by]);
        CompanyDocument::create(['legal_entity_id' => $ng, 'kind' => 'licence', 'title' => 'Archived permit', 'expires_on' => '2026-11-10', 'remind_days' => 30, 'created_by' => $by, 'archived_at' => now()]);
        CompanyDeadline::create(['legal_entity_id' => $ng, 'title' => 'First accounts due', 'due_on' => '2026-11-01', 'repeats' => 'once', 'remind_days' => 30, 'created_by' => $by]);
        CompanyDeadline::create(['legal_entity_id' => null, 'title' => 'Group tax filing', 'due_on' => '2026-11-12', 'repeats' => 'yearly', 'remind_days' => 7, 'created_by' => $by]);
        CompanyDeadline::create(['legal_entity_id' => $ng, 'title' => 'Done filing', 'due_on' => '2026-11-06', 'repeats' => 'once', 'remind_days' => 30, 'created_by' => $by, 'done_at' => now(), 'done_by' => $by]);
        CompanyDeadline::create(['legal_entity_id' => $ng, 'title' => 'Not yet', 'due_on' => '2026-11-20', 'repeats' => 'once', 'remind_days' => 7, 'created_by' => $by]);

        Artisan::call('mdm:compliance-bell', ['--dry-run' => true]);
        $this->assertStringContainsString('Dry run: nobody was rung.', Artisan::output());
        $this->assertSame(0, DB::table('notifications')->count());

        Artisan::call('mdm:compliance-bell');
        $titles = DB::table('notifications')->where('user_id', $owner->id)->orderBy('title')->pluck('title')->all();
        $this->assertSame(['First accounts due is overdue', 'Group tax filing is due in 7 days', 'Tax clearance certificate expires in 30 days'], $titles);
        $this->assertSame(3, DB::table('notifications')->where('user_id', $editor->id)->count()); // mdm.edit
        $this->assertSame(0, DB::table('notifications')->where('user_id', $viewer->id)->count()); // mdm.view only

        $bell = DB::table('notifications')->where('user_id', $owner->id)->where('title', 'like', 'Tax clearance%')->first();
        $this->assertSame('JBS Praxis LLC: Tax clearance certificate expires on Saturday 5 December 2026. Upload the renewed document in Master data › Company documents.', $bell->message);
        $this->assertSame(route('hq.mdm.documents.show', $soon, false), $bell->link_url);
        $group = DB::table('notifications')->where('user_id', $owner->id)->where('title', 'like', 'Group tax%')->first();
        $this->assertSame(route('hq.mdm.documents', ['company' => Companies::GROUP, 'tab' => 'calendar'], false), $group->link_url);

        // Once: running again rings nobody; a renewed document (a new expiry date) rings again when its time comes
        Artisan::call('mdm:compliance-bell');
        $this->assertSame(6, DB::table('notifications')->count());
        $soon->update(['expires_on' => '2027-12-05']);
        $this->travelTo(CarbonImmutable::parse('2027-11-05 06:30:00', 'UTC'));
        Artisan::call('mdm:compliance-bell');
        $this->assertSame(1, DB::table('notifications')->where('user_id', $owner->id)->where('title', 'Tax clearance certificate expires in 30 days')->where('created_at', '>=', '2027-01-01')->count());

        // Scheduled on working days at 07:30 Lagos time
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'mdm:compliance-bell'));
        $this->assertNotNull($event);
        $this->assertSame('30 7 * * 1-5', $event->expression);
        $this->assertSame('Africa/Lagos', $event->timezone);
    }
}
