<?php

namespace Tests\Feature\Projects;

use App\Livewire\ClientOs\Dashboard;
use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Livewire\Hq\Operations\SprintBoard;
use App\Livewire\Hq\Projects\ChangeReportDrawer;
use App\Livewire\Hq\Projects\MilestoneDrawer;
use App\Livewire\Hq\Projects\TaskDrawer;
use App\Livewire\Hq\TimerPresence;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\Pwa\ClientNotify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The lead chat's audit of PR 7 (docs/PROJECTS.md section 9): internal tasks stay out of the portal, the 45-minute
 * check on every page, bells in the recipient's language, milestone amounts that never drift, deleted milestones out
 * of the portal, acceptance asked only when accepting, and people who left or took the lead.
 */
class AuditFixesTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private function client(string $organisationId): ClientUser
    {
        return ClientUser::forceCreate([
            'organization_id' => $organisationId,
            'role_id' => Access::clientAdminRoleId(),
            'first_name' => 'Oyebode', 'last_name' => 'Client', 'email' => Str::lower(Str::random(10)) . '@client.test',
            'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 1,
        ]);
    }

    public function test_internal_tasks_and_staff_comments_never_reach_the_client(): void
    {
        $org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $org, 'name' => 'MyCity App Ltd', 'status' => 'Active Client', 'preferred_language' => 'EN']);
        $project = $this->project(['organization_id' => $org]);
        $phase = $this->phase($project, 4, 100, ['status' => 'Active']);
        $deleted = $this->phase($project, 9, 0, ['deleted_at' => now(), 'title' => 'M9 · Removed milestone']);
        $shown = $this->task($phase, ['title' => 'Vacancy form', 'status' => 'QA_Review']);
        $internal = $this->task($phase, ['title' => 'Refactor the queue', 'status' => 'QA_Review', 'is_client_visible' => 0]);
        $staff = $this->staff('delivery', ['delivery']);
        $client = $this->client($org);
        DB::table('task_comments')->insert([
            ['task_id' => $shown, 'user_id' => $client->id, 'author_type' => 'client', 'comment_text' => 'Approved by the client', 'created_at' => now()],
            ['task_id' => $shown, 'user_id' => $staff->id, 'author_type' => 'staff', 'comment_text' => 'Internal: the queue is slow', 'created_at' => now()],
        ]);

        // The portal: the client's own comment, the task shown to them, never the internal one or a deleted milestone
        $portal = Livewire::actingAs($client, 'client')->test(Dashboard::class)->call('switchTab', 'overview');
        $this->assertSame(['Approved by the client'], collect($portal->get('liveLogs'))->pluck('text')->all());
        $this->assertSame([$phase], collect($portal->get('phases'))->pluck('id')->all());
        $this->assertNotContains($deleted, collect($portal->get('phases'))->pluck('id')->all());
        // The task list is the Progress section's (Overview no longer loads it)
        $this->assertSame([$shown], collect($portal->call('switchTab', 'sprints')->get('activeTasks'))->pluck('id')->all());
        $portal->call('approveTask', $internal);
        $this->assertSame('QA_Review', DB::table('tasks')->where('id', $internal)->value('status'));
        // Reporting an issue (in the conversation, docs/CHAT.md) offers the task shown to the client only, and refuses the other
        Livewire::actingAs($client, 'client')->test(\App\Livewire\ClientOs\DeliveryChat::class, ['projectId' => $project])->call('startIssue')
            ->assertViewHas('tasks', fn ($tasks) => collect($tasks)->pluck('id')->all() === [$shown])
            ->set('issueTaskId', $internal)->set('issueTitle', 'Queue slow')->set('draft', 'It is slow')->call('send')->assertHasErrors('issueTaskId');
        $this->assertSame(0, DB::table('qa_tickets')->where('project_id', $project)->count());

        // No portal notice for an internal task in client review; one for the task shown to the client
        $this->assertSame(0, ClientNotify::task($internal));
        $this->assertGreaterThan(0, ClientNotify::task($shown));

        // A comment written in HQ is the staff's
        $this->actingAs($staff);
        Livewire::test(TaskDrawer::class, ['projectId' => $project])->dispatch('open-task', id: $shown)->set('comment', 'Checked on staging')->call('addComment');
        $this->assertSame('staff', DB::table('task_comments')->where('comment_text', 'Checked on staging')->value('author_type'));
    }

    public function test_the_45_minute_check_follows_a_timer_started_anywhere(): void
    {
        $engineer = $this->staff('engineer', ['engineer']);
        $project = $this->project();
        $phase = $this->phase($project, 4, 100, ['status' => 'Active']);
        $task = $this->task($phase, ['assigned_to' => $engineer->id]);
        $this->actingAs($engineer);

        Livewire::test(TimerPresence::class)->assertDontSee(__('projects.presence.title'));
        // Started from the task drawer, not the board
        Livewire::test(TaskDrawer::class, ['projectId' => $project])->dispatch('open-task', id: $task)->set('status', 'In-Progress')->assertDispatched('timers-changed');
        $presence = Livewire::test(TimerPresence::class)->assertSee(__('projects.presence.title'));

        // Unanswered: timers stop, the task goes back to To do, the page is told
        $presence->call('pause')->assertSee(__('projects.presence.paused'))->assertDispatched('project-changed')->assertDontSee(__('projects.presence.title'));
        $this->assertSame('To-Do', DB::table('tasks')->where('id', $task)->value('status'));
        $this->assertSame(0, DB::table('task_time_logs')->where('user_id', $engineer->id)->whereNull('end_time')->count());

        // Every HQ page carries the check
        $this->get(route('hq.ops.project', $project))->assertOk()->assertSee('hq.timer-presence', false);
    }

    public function test_bells_are_written_in_hq_language_not_the_actors_screen(): void
    {
        config(['app.staff_locale' => 'fr']);
        app()->setLocale('en'); // the person acting is on an English screen
        [$lead, $watcher] = [$this->staff('lead', ['engineer']), $this->staff('watcher', ['engineer'])];
        $project = $this->project();
        $task = $this->task($this->phase($project, 4, 100), ['assigned_to' => $lead->id, 'title' => 'Offer letter']);
        DB::table('task_people')->insert(['task_id' => $task, 'user_id' => $watcher->id, 'role' => 'watcher', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($lead);

        Livewire::test(TaskDrawer::class, ['projectId' => $project])->dispatch('open-task', id: $task)->set('status', 'Done');

        $bell = DB::table('notifications')->where('user_id', $watcher->id)->first();
        $this->assertSame(__('projects.bell.moved_title', [], 'fr'), $bell->title);
        $this->assertStringContainsString('Terminé', $bell->message);
    }

    public function test_milestone_amounts_stay_exact_through_change_reports(): void
    {
        $project = $this->project(['total_budget' => 3700000, 'title' => 'MyCity App ERP']);
        $m1 = $this->phase($project, 1, 12, ['status' => 'Completed', 'amount' => 444000]);
        $this->phase($project, 2, 88, ['amount' => 3256000]);
        $this->actingAs($this->staff('owner', ['owner']));

        foreach ([['Mailbox', '250000'], ['Payroll exports', '137500']] as [$title, $price]) {
            $drawer = Livewire::test(ChangeReportDrawer::class, ['projectId' => $project])->dispatch('new-report')
                ->set('title', $title)->set('price', $price)->call('save')->assertHasNoErrors();
            $drawer->set('decision', 'approved')->set('decidedBy', 'Oyebode')->call('reviewDecision')->call('recordDecision')->assertHasNoErrors();
        }

        $phases = DB::table('project_phases')->where('project_id', $project)->orderBy('phase_number')->get();
        $this->assertEquals([444000, 3256000, 250000, 137500], $phases->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertEquals(4087500, DB::table('projects')->where('id', $project)->value('total_budget'));
        $this->assertEquals(100.0, round($phases->sum('weight_percentage'), 2));

        // Finance's Create invoice for M1 is pre-filled with its agreed amount, to the unit
        $ledger = Livewire::withQueryParams(['create' => 1, 'project' => $project, 'phase' => $m1])->test(InvoiceLedger::class);
        $this->assertEquals(444000.0, $ledger->get('lines')[0]['amount']);
        // A new weight sets a new amount; other edits leave it alone
        Livewire::test(MilestoneDrawer::class, ['projectId' => $project])->dispatch('open-milestone', id: $m1)->set('notes', 'Signed off')->call('save')->assertHasNoErrors();
        $this->assertEquals(444000, DB::table('project_phases')->where('id', $m1)->value('amount'));
    }

    public function test_an_accepted_milestone_without_its_acceptance_details_can_still_be_edited(): void
    {
        $project = $this->project();
        $m1 = $this->phase($project, 1, 100, ['status' => 'Completed', 'accepted_at' => null, 'accepted_by_name' => null]);
        $this->actingAs($this->staff('delivery', ['delivery']));

        Livewire::test(MilestoneDrawer::class, ['projectId' => $project])->dispatch('open-milestone', id: $m1)
            ->set('notes', 'Accepted on the M1 call')->call('save')->assertHasNoErrors();
        $this->assertSame(['Completed', 'Accepted on the M1 call'], array_values((array) DB::table('project_phases')->where('id', $m1)->first(['status', 'notes'])));

        // Moving one to Accepted still asks who and when
        $m2 = $this->phase($project, 2, 0, ['status' => 'QA']);
        Livewire::test(MilestoneDrawer::class, ['projectId' => $project])->dispatch('open-milestone', id: $m2)
            ->set('column', 'accepted')->set('acceptedAt', '')->call('save')->assertHasErrors(['acceptedAt', 'acceptedBy']);
    }

    public function test_people_who_left_dont_block_a_save_and_the_lead_never_shares(): void
    {
        $manager = $this->staff('delivery', ['delivery']);
        [$gone, $sharer] = [$this->staff('gone', ['engineer']), $this->staff('sharer', ['engineer'])];
        $project = $this->project();
        $phase = $this->phase($project, 4, 100);
        $task = $this->task($phase, ['assigned_to' => $gone->id, 'title' => 'Offer letter']);
        DB::table('task_people')->insert(['task_id' => $task, 'user_id' => $sharer->id, 'role' => 'sharing', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->whereIn('id', [$gone->id, $sharer->id])->update(['status' => 'Offboarded']);
        $this->actingAs($manager);

        Livewire::test(TaskDrawer::class, ['projectId' => $project])->dispatch('open-task', id: $task)->set('title', 'Offer letter v2')->call('save')->assertHasNoErrors();
        $this->assertSame([$gone->id, 'Offer letter v2'], array_values((array) DB::table('tasks')->where('id', $task)->first(['assigned_to', 'title'])));
        $this->assertTrue(DB::table('task_people')->where('task_id', $task)->where('user_id', $sharer->id)->exists());

        // Someone sharing a task who takes it over stops sharing it (drawer and board)
        $member = $this->staff('member', ['engineer']);
        $open = $this->task($phase);
        DB::table('task_people')->insert(['task_id' => $open, 'user_id' => $member->id, 'role' => 'sharing', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($member);
        Livewire::test(SprintBoard::class, ['id' => $project, 'milestone' => $phase])->call('assignToMe', $open);
        $this->assertSame($member->id, DB::table('tasks')->where('id', $open)->value('assigned_to'));
        $this->assertFalse(DB::table('task_people')->where('task_id', $open)->where('user_id', $member->id)->exists());
    }
}
