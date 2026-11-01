<?php

namespace Tests\Feature\Projects;

use App\Livewire\Hq\Projects\ChangeReportDrawer;
use App\Livewire\Hq\Projects\MilestoneDrawer;
use App\Livewire\Hq\Projects\TaskDrawer;
use App\Support\Projects\ChangeReports;
use App\Support\Projects\Dependencies;
use App\Support\Projects\ProjectPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The rules behind the project page (docs/PROJECTS.md, decisions 3, 5, 6 and 7): one weight and the progress it gives,
 * "waits on" that warns and refuses loops, overdue in Lagos, change reports (approved joins the plan and the total;
 * declined changes nothing), and the migrations' backfills, which only fill empty columns.
 */
class ProjectPlanTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    public function test_progress_is_the_weights_times_each_milestones_done_tasks(): void
    {
        $project = $this->project();
        $m0 = $this->phase($project, 0, 0, ['status' => 'Active']);
        $m1 = $this->phase($project, 1, 40);
        $m2 = $this->phase($project, 2, 60, ['status' => 'Completed']);
        foreach (['Done', 'Done', 'Done', 'To-Do'] as $status) {
            $this->task($m1, ['status' => $status]);
        }
        $this->task($m0, ['status' => 'Done']);
        $sprint = $this->sprint($project, 'Sprint 1', '2026-11-02', '2026-11-13', 'Active');
        $this->task($m1, ['status' => 'To-Do', 'sprint_id' => $sprint]);
        $this->task($m0, ['status' => 'To-Do', 'sprint_id' => $sprint]);

        $milestones = ProjectPlan::milestones($project)->keyBy('phase_number');
        $this->assertSame(60.0, $milestones[1]->progress); // 3 of 5
        $this->assertSame(100.0, $milestones[2]->progress); // accepted, no tasks
        $this->assertSame('accepted', $milestones[2]->column);
        // 40% × 60% + 60% × 100% + 0% × 50%
        $this->assertSame(84.0, ProjectPlan::progress($milestones->values()));
        $this->assertSame(100.0, ProjectPlan::weightTotal($milestones->values()));

        // A sprint shows the weight of the milestones it delivers
        $s = ProjectPlan::sprints($project, $milestones->values())->first();
        $this->assertSame(40.0, $s->weight);
        $this->assertSame(['M0', 'M1'], $s->milestones->pluck('code')->all());
    }

    public function test_the_kanban_column_follows_the_invoice_then_the_status(): void
    {
        $project = $this->project();
        $paid = $this->phase($project, 1, 50, ['status' => 'Completed']);
        $invoiced = $this->phase($project, 2, 30, ['status' => 'Completed']);
        $cancelled = $this->phase($project, 3, 20, ['status' => 'QA']);
        $this->invoice($project, $paid, 'Paid');
        $this->invoice($project, $invoiced, 'Overdue');
        $this->invoice($project, $cancelled, 'Cancelled');

        $columns = ProjectPlan::milestones($project)->pluck('column', 'phase_number')->all();
        $this->assertSame([1 => 'paid', 2 => 'invoiced', 3 => 'client_review'], $columns);
    }

    public function test_overdue_means_past_due_in_lagos_and_not_finished(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 23:30', 'UTC')); // 00:30 on 15 Nov in Lagos
        $project = $this->project();
        $phase = $this->phase($project, 4, 13, ['status' => 'Active', 'due_date' => '2026-11-13']);
        $accepted = $this->phase($project, 3, 5, ['status' => 'Completed', 'due_date' => '2026-10-30']);
        $late = $this->task($phase, ['due_date' => '2026-11-14', 'status' => 'In-Progress']);
        $done = $this->task($phase, ['due_date' => '2026-11-01', 'status' => 'Done']);
        $today = $this->task($phase, ['due_date' => '2026-11-15', 'status' => 'To-Do']);

        $this->assertSame([$late], ProjectPlan::overdueTasks($project)->pluck('id')->all());
        $this->assertTrue(ProjectPlan::taskOverdue(DB::table('tasks')->find($late)));
        $this->assertFalse(ProjectPlan::taskOverdue(DB::table('tasks')->find($done)));
        $this->assertFalse(ProjectPlan::taskOverdue(DB::table('tasks')->find($today)));
        $milestones = ProjectPlan::milestones($project)->keyBy('id');
        $this->assertTrue($milestones[$phase]->overdue);
        $this->assertFalse($milestones[$accepted]->overdue);
        Carbon::setTestNow();
    }

    public function test_waits_on_warns_but_never_blocks_and_refuses_loops(): void
    {
        $project = $this->project();
        $phase = $this->phase($project, 1, 100);
        [$a, $b, $c] = [$this->task($phase, ['title' => 'A']), $this->task($phase, ['title' => 'B']), $this->task($phase, ['title' => 'C'])];

        $this->assertNull(Dependencies::link('task', $a, $b));
        $this->assertNull(Dependencies::link('task', $b, $c));
        $this->assertSame('self', Dependencies::link('task', $a, $a));
        $this->assertSame('exists', Dependencies::link('task', $a, $b));
        $this->assertSame('loop', Dependencies::link('task', $b, $a));
        $this->assertSame('loop', Dependencies::link('task', $c, $a)); // through B
        $this->assertSame(2, DB::table('task_dependencies')->count());

        // A still moves while B is open: a warning on the card and in the drawer, nothing more
        $this->actingAs($this->staff('delivery', ['delivery']));
        $this->assertSame(['B'], ProjectPlan::openWaits([$a])->get($a)->pluck('title')->all());
        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $project])->dispatch('open-task', id: $a)->assertSee(__('projects.waits.warning'));
        $drawer->set('status', 'Done');
        $this->assertSame('Done', DB::table('tasks')->where('id', $a)->value('status'));

        // The drawer refuses a loop with its reason
        Livewire::test(TaskDrawer::class, ['projectId' => $project])->dispatch('open-task', id: $c)->set('waitOn', $a)->call('addWait')
            ->assertHasErrors('waitOn')->assertSee(__('projects.waits.errors.loop'));
    }

    public function test_milestones_wait_on_milestones_the_same_way(): void
    {
        $project = $this->project();
        [$m1, $m2] = [$this->phase($project, 1, 50), $this->phase($project, 2, 50)];
        $this->actingAs($this->staff('delivery', ['delivery']));

        Livewire::test(MilestoneDrawer::class, ['projectId' => $project])->dispatch('open-milestone', id: $m2)->set('waitOn', $m1)->call('addWait')->assertHasNoErrors();
        $this->assertSame(['M1'], ProjectPlan::milestones($project)->firstWhere('id', $m2)->waits_on->where('done', false)->pluck('code')->all());
        Livewire::test(MilestoneDrawer::class, ['projectId' => $project])->dispatch('open-milestone', id: $m1)->set('waitOn', $m2)->call('addWait')
            ->assertHasErrors('waitOn');
        $this->assertSame(1, DB::table('phase_dependencies')->count());

        // Accepting M1 settles the warning
        DB::table('project_phases')->where('id', $m1)->update(['status' => 'Completed']);
        $this->assertTrue(ProjectPlan::milestones($project)->firstWhere('id', $m2)->waits_on->first()->done);
    }

    public function test_an_approved_change_report_joins_the_plan_and_the_contract_total(): void
    {
        Mail::fake();
        $project = $this->project(['total_budget' => 3700000, 'target_end_date' => '2026-12-18']);
        $m1 = $this->phase($project, 1, 60);
        $m2 = $this->phase($project, 2, 40);
        $this->actingAs($this->staff('delivery', ['delivery']));

        $drawer = Livewire::test(ChangeReportDrawer::class, ['projectId' => $project])->dispatch('new-report')
            ->set('title', 'Mailbox')->set('price', '250000')->set('timelineDays', '5')->set('milestoneTitle', 'Mailbox and communications hub')
            ->set('newPlanTask', 'Shared inboxes')->call('addPlanTask')->set('newPlanTask', 'Signatures')->call('addPlanTask')
            ->call('save')->assertHasNoErrors();
        $report = DB::table('change_reports')->where('project_id', $project)->first();
        $this->assertSame(['CR-01', 'draft', 'NGN'], [ChangeReports::label($report), $report->status, $report->currency]);
        // Nothing joins the plan while it is a draft
        $this->assertSame(2, DB::table('project_phases')->where('project_id', $project)->count());

        $drawer->call('markSent')->set('decision', 'approved')->set('decidedBy', 'Oyebode Oluwasanmi')->set('decidedByTitle', 'CTO')->set('decidedOn', '2026-11-03')
            ->call('reviewDecision')->assertSet('confirming', true)->call('recordDecision')->assertHasNoErrors();

        $report = DB::table('change_reports')->find($report->id);
        $this->assertSame('approved', $report->status);
        $added = DB::table('project_phases')->find($report->phase_id);
        $this->assertSame([3, 'Mailbox and communications hub', 'Pending'], [$added->phase_number, $added->title, $added->status]);
        $this->assertSame(['Shared inboxes', 'Signatures'], DB::table('tasks')->where('phase_id', $added->id)->orderBy('title')->pluck('title')->all());
        $project = DB::table('projects')->find($project);
        $this->assertEquals(3950000, $project->total_budget);
        $this->assertSame('2026-12-23', $project->target_end_date);
        // Every milestone keeps its amount; the shares still add up to 100%
        $weights = DB::table('project_phases')->where('project_id', $project->id)->pluck('weight_percentage', 'id');
        $this->assertEquals(56.20, $weights[$m1]); // 60% of 3.7M = 2.22M = 56.20% of 3.95M
        $this->assertEquals(37.47, $weights[$m2]);
        $this->assertEquals(6.33, $weights[$added->id]);
        $this->assertEquals(100.0, round($weights->sum(), 2));

        // Decided: read only
        Livewire::test(ChangeReportDrawer::class, ['projectId' => $project->id])->dispatch('open-report', id: $report->id)
            ->set('price', '1')->call('save')->assertForbidden();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_a_declined_change_report_is_kept_and_changes_nothing(): void
    {
        $project = $this->project(['total_budget' => 3700000]);
        $this->phase($project, 1, 100);
        $this->actingAs($this->staff('delivery', ['delivery']));
        $drawer = Livewire::test(ChangeReportDrawer::class, ['projectId' => $project])->dispatch('new-report')
            ->set('title', 'Mailbox')->set('price', '250000')->set('newPlanTask', 'Shared inboxes')->call('addPlanTask')->call('save');

        $drawer->set('decision', 'declined')->set('decidedBy', 'MyCity')->call('reviewDecision')->call('recordDecision');

        $this->assertSame('declined', DB::table('change_reports')->where('project_id', $project)->value('status'));
        $this->assertEquals(3700000, DB::table('projects')->where('id', $project)->value('total_budget'));
        $this->assertSame(1, DB::table('project_phases')->where('project_id', $project)->count());
        $this->assertSame(0, DB::table('tasks')->where('title', 'Shared inboxes')->count());
    }

    public function test_the_backfill_fills_only_empty_dates_and_leads(): void
    {
        $lead = $this->staff('lead', ['delivery']);
        $creator = $this->staff('creator', ['delivery']);
        $project = $this->project(['created_by' => $creator->id]);
        $own = $this->project(['created_by' => $creator->id, 'lead_user_id' => $creator->id]);
        $orphan = $this->project(['created_by' => $creator->id]);
        $this->insertRow('opportunities', ['id' => (string) \Illuminate\Support\Str::uuid(), 'organization_id' => \Database\Seeders\CoreSeeder::INTERNAL_ORG_ID,
            'title' => 'MyCity', 'stage' => 'won', 'lead_user_id' => $lead->id, 'language' => 'en', 'source' => 'manual', 'project_id' => $project,
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);
        DB::table('projects')->where('id', $own)->update(['lead_user_id' => $lead->id]);
        $empty = $this->phase($project, 4, 13);
        $set = $this->phase($project, 5, 11, ['start_date' => '2026-11-16', 'due_date' => '2026-11-21']);
        $s4 = $this->sprint($project, 'M4', '2026-11-02', '2026-11-13');
        $s5 = $this->sprint($project, 'M5', '2026-11-16', '2026-11-20');
        $this->task($empty, ['sprint_id' => $s4]);
        $this->task($set, ['sprint_id' => $s5]);

        $migration = require base_path('database/migrations/2026_11_01_900000_add_project_planning.php');
        (fn () => $this->backfillMilestoneDates())->call($migration);
        (fn () => $this->backfillProjectLeads())->call($migration);

        $this->assertSame(['2026-11-02', '2026-11-13'], array_values((array) DB::table('project_phases')->where('id', $empty)->first(['start_date', 'due_date'])));
        $this->assertSame(['2026-11-16', '2026-11-21'], array_values((array) DB::table('project_phases')->where('id', $set)->first(['start_date', 'due_date'])));
        $this->assertSame($lead->id, DB::table('projects')->where('id', $project)->value('lead_user_id')); // the opportunity's lead
        $this->assertSame($lead->id, DB::table('projects')->where('id', $own)->value('lead_user_id')); // already set: kept
        $this->assertSame($creator->id, DB::table('projects')->where('id', $orphan)->value('lead_user_id')); // no opportunity: its creator
    }

    public function test_mycitys_change_report_01_is_added_once_as_declined_and_only_there(): void
    {
        $migration = require base_path('database/migrations/2026_11_01_900001_add_mycity_change_report_01.php');
        $migration->up();
        $this->assertSame(0, DB::table('change_reports')->count());

        $project = $this->project(['title' => 'MyCity App ERP', 'total_budget' => 3700000]);
        $migration->up();
        $migration->up();
        $reports = DB::table('change_reports')->get();
        $this->assertCount(1, $reports);
        $this->assertSame([$project, 1, 'declined', '2026-11-01', null], [$reports[0]->project_id, $reports[0]->number, $reports[0]->status, $reports[0]->decided_on, $reports[0]->phase_id]);
        $this->assertEquals(250000, $reports[0]->price);
        $this->assertEquals(3700000, DB::table('projects')->where('id', $project)->value('total_budget'));
    }
}
