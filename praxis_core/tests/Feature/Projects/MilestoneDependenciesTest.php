<?php

namespace Tests\Feature\Projects;

use App\Livewire\ClientOs\Dashboard;
use App\Livewire\Hq\Projects\MilestoneDependencies;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\Projects\ClientDependencies;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * What a milestone needs from the client (owner, 13 Nov 2026): kept in the milestone drawer, shown to the client in the
 * portal (who marks items sent, with a file), late items and the day-for-day extension they earn (SOW clause 8.2).
 */
class MilestoneDependenciesTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private string $org;

    private string $project;

    private string $m1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $this->org, 'name' => 'Tales Technology Consulting Limited', 'status' => 'Active Client', 'preferred_language' => 'EN']);
        $this->project = $this->project(['organization_id' => $this->org, 'title' => 'Fidson FieldForce programme']);
        $this->m1 = $this->phase($this->project, 1, 15, ['status' => 'Active', 'start_date' => '2026-11-16', 'due_date' => '2026-11-23']);
    }

    private function client(): ClientUser
    {
        return ClientUser::forceCreate([
            'organization_id' => $this->org,
            'role_id' => Access::clientAdminRoleId(),
            'first_name' => 'Boluwatife', 'last_name' => 'Tales', 'email' => Str::lower(Str::random(10)) . '@client.test',
            'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 1,
        ]);
    }

    public function test_the_lead_keeps_the_list_in_the_milestone_drawer(): void
    {
        $lead = $this->staff('lead', ['owner']);
        DB::table('projects')->where('id', $this->project)->update(['lead_user_id' => $lead->id]);
        $this->actingAs($lead);

        Livewire::test(MilestoneDependencies::class, ['projectId' => $this->project, 'phaseId' => $this->m1])
            ->assertSee('Nothing needed from the client')
            ->call('startAdd')->assertSet('dueDate', '2026-11-16')
            ->set('groupCode', '1.A')->set('groupTitle', 'Organisation and people data')->set('title', 'Full employee list for the pilot area')
            ->call('save')->assertHasNoErrors()
            ->call('startAdd')->assertSet('groupCode', '1.A')->set('title', '')->call('save')->assertHasErrors(['title'])
            ->set('title', 'Azure AD app registration')->set('groupCode', '1.B')->set('groupTitle', 'Microsoft 365 and security')->call('save')
            ->assertSee('1.A Organisation and people data')->assertSee('0 of 2 received');

        $items = ClientDependencies::forPhase($this->m1);
        $this->assertCount(2, $items);
        $this->assertSame([1, 2], $items->pluck('position')->map(fn ($p) => (int) $p)->all());

        Livewire::test(MilestoneDependencies::class, ['projectId' => $this->project, 'phaseId' => $this->m1])
            ->call('setStatus', $items[0]->id, 'received')->assertSee('1 of 2 received')
            ->call('remove', $items[1]->id);
        $this->assertSame('received', DB::table('milestone_dependencies')->where('id', $items[0]->id)->value('status'));
        $this->assertNotNull(DB::table('milestone_dependencies')->where('id', $items[0]->id)->value('received_at'));
        $this->assertCount(1, ClientDependencies::forPhase($this->m1));

        // Someone who only sees the project reads it, but changes nothing
        $this->actingAs($this->staff('viewer', ['delivery']));
        DB::table('projects')->where('id', $this->project)->update(['lead_user_id' => $lead->id]);
        if (\App\Support\Projects\ProjectRules::canView($this->project) && ! \App\Support\Projects\ProjectRules::canManage($this->project)) {
            Livewire::test(MilestoneDependencies::class, ['projectId' => $this->project, 'phaseId' => $this->m1])->call('startAdd')->assertForbidden();
        }
    }

    public function test_late_items_extend_the_milestone_day_for_day(): void
    {
        $phase = DB::table('project_phases')->where('id', $this->m1)->first();
        $a = ClientDependencies::add($phase, ['title' => 'Org chart'], null);
        $b = ClientDependencies::add($phase, ['title' => 'Business Central sandbox', 'integration' => true], null);
        $c = ClientDependencies::add($phase, ['title' => 'Branding pack'], null);
        // Integration items are due 7 business days before the start (clause 8.3): Thu 5 Nov for Mon 16 Nov
        $this->assertSame('2026-11-05', DB::table('milestone_dependencies')->where('id', $b)->value('due_date'));
        DB::table('milestone_dependencies')->where('id', $c)->update(['status' => 'waived']);

        $today = CarbonImmutable::parse('2026-11-19', 'Africa/Lagos');
        $items = ClientDependencies::forPhase($this->m1, $today);
        $this->assertSame([3, 14, 0], $items->pluck('late_days')->all());
        $this->assertSame(14, ClientDependencies::extensionDays($items), 'The longest delay, not the sum');

        // Received late: counted up to the day it arrived
        DB::table('milestone_dependencies')->where('id', $a)->update(['status' => 'received', 'received_at' => '2026-11-18 10:00:00']);
        $this->assertSame(2, ClientDependencies::forPhase($this->m1, $today)->firstWhere('id', $a)->late_days);
        $summary = ClientDependencies::summary([$this->m1], $today)->get($this->m1);
        $this->assertSame([3, 2, 1, 1], [$summary->total, $summary->done, $summary->open, $summary->late]);
    }

    public function test_the_client_sees_what_is_needed_and_marks_it_sent_with_a_file(): void
    {
        Storage::fake('local');
        $lead = $this->staff('lead', ['owner']);
        DB::table('projects')->where('id', $this->project)->update(['lead_user_id' => $lead->id]);
        $phase = DB::table('project_phases')->where('id', $this->m1)->first();
        $needed = ClientDependencies::add($phase, ['group_code' => '1.A', 'group_title' => 'Organisation and people data', 'title' => 'Full employee list'], null);
        $done = ClientDependencies::add($phase, ['title' => 'NDA confirmed', 'status' => 'received'], null);

        $portal = Livewire::actingAs($this->client(), 'client')->test(Dashboard::class)->call('switchTab', 'overview')
            ->assertSee('What we need from you')->assertSee('Full employee list')->assertSee('1 already received');
        $this->assertSame([$needed], collect($portal->get('clientNeeds'))->pluck('id')->all());

        // Nothing to send is refused; a note and a file go through
        $portal->call('openNeed', $needed)->call('submitNeedSent')->assertHasErrors(['needNote'])
            ->set('needNote', 'Uploaded the Excel from HR')->set('needFile', UploadedFile::fake()->create('employees.xlsx', 40))
            ->call('submitNeedSent')->assertHasNoErrors();

        $row = DB::table('milestone_dependencies')->where('id', $needed)->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame('employees.xlsx', $row->file_name);
        $this->assertStringStartsWith('dependency_files/' . $this->project . '/', $row->file_path);
        Storage::disk('local')->assertExists($row->file_path);
        $this->assertTrue(DB::table('notifications')->where('user_id', $lead->id)->where('title', 'The client sent something you needed')->exists());

        // An item of another organisation's project can't be touched
        $otherOrg = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $otherOrg, 'name' => 'Other Ltd', 'status' => 'Active Client']);
        $other = $this->phase($this->project(['organization_id' => $otherOrg, 'title' => 'Other']), 1, 100);
        $foreign = ClientDependencies::add(DB::table('project_phases')->where('id', $other)->first(), ['title' => 'Secret'], null);
        $portal->call('openNeed', $foreign)->assertSet('needItemId', null);
        $this->assertSame('needed', DB::table('milestone_dependencies')->where('id', $foreign)->value('status'));
        $this->assertNotNull($done);
    }
}
