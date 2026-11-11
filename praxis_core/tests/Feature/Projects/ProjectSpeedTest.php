<?php

namespace Tests\Feature\Projects;

use App\Livewire\Hq\Operations\ActiveDeployments;
use App\Livewire\Hq\Operations\SprintBoard;
use App\Livewire\Hq\Projects\ProjectPage;
use App\Livewire\Hq\Projects\TaskDrawer;
use App\Support\ProjectAccess;
use App\Support\Projects\ProjectPlan;
use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * How much work the project screens do (the speed pass): All projects reads every project's milestones, hours and
 * invoices in a few queries whatever the number of projects, and keeps the lists out of its Livewire state; a drawer
 * change redraws the page and the milestone board only where it shows; the task drawer's pickers don't work the team
 * out again on every keystroke; the project styles go out once per page. What the screens show is unchanged.
 */
class ProjectSpeedTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    /** [number of queries, result, the SQL] */
    private function queries(callable $run): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = $run();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return [count($log), $result, array_column($log, 'query')];
    }

    /** A project with two milestones, hours on its tasks and three invoices */
    private function busyProject(string $title): string
    {
        $project = $this->project(['title' => $title, 'total_budget' => 1000000]);
        $m1 = $this->phase($project, 1, 60, ['status' => 'Active']);
        $m2 = $this->phase($project, 2, 40);
        $this->task($m1, ['status' => 'Done', 'actual_hours_logged' => 2]);
        $this->task($m1, ['status' => 'Done', 'actual_hours_logged' => 3]);
        $this->task($m2, ['status' => 'Done']);
        $this->task($m2);
        $this->task($m2, ['actual_hours_logged' => 1, 'deleted_at' => now()]); // deleted: its hours still count, as before
        DB::table('phase_dependencies')->insert(['phase_id' => $m2, 'depends_on_phase_id' => $m1, 'created_at' => now()]);
        $this->invoice($project, null, 'Pending', 1000);
        $this->invoice($project, null, 'Overdue', 500);
        $this->invoice($project, $m1, 'Paid', 9999);

        return $project;
    }

    public function test_all_projects_reads_the_same_few_queries_however_many_projects_there_are(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $first = $this->busyProject('Alpha rollout');
        $this->busyProject('Beta rollout');
        $empty = $this->project(['title' => 'Gamma rollout']); // no milestones yet

        $page = Livewire::test(ActiveDeployments::class);
        $alpha = collect($page->viewData('matrixProjects'))->firstWhere('id', $first);
        // Progress: 60% × M1 (2 of 2 done) + 40% × M2 (1 of 2 done); spend: 6 hours × US$150; unpaid: pending and overdue
        $this->assertSame([80, 3, 4, 900.0, 0.0, true], [$alpha['progress_percent'], $alpha['completed_points'], $alpha['total_points'],
            (float) $alpha['spent_amount'], (float) $alpha['burn_percent'], $alpha['needs_midpoint_funds']]);
        $this->assertEquals(1500, $alpha['unpaid_invoices']);
        $gamma = collect($page->viewData('matrixProjects'))->firstWhere('id', $empty);
        $this->assertSame([0, 0, 0], [$gamma['progress_percent'], $gamma['total_points'], $gamma['completed_points']]);
        $this->assertSame(3, $page->viewData('kpiActiveDeployments'));
        // The milestones as the project page reads them, "waits on" included
        $this->assertSame(ProjectPlan::milestones($first)->pluck('waits_on')->toJson(), ProjectPlan::milestonesFor([$first, $empty])->get($first)->pluck('waits_on')->toJson());
        $this->assertTrue(ProjectPlan::milestonesFor([$empty])->get($empty)->isEmpty());

        // The projects are drawn, not kept in the component's state (which goes up and down on every 30-second poll)
        $page->assertSee('Alpha rollout');
        $this->assertStringNotContainsString('Alpha rollout', json_encode($page->snapshot));
        [$few] = $this->queries(fn () => $page->call('loadData'));

        foreach (range(1, 4) as $n) {
            $this->busyProject("Rollout {$n}");
        }
        [$many] = $this->queries(fn () => $page->call('loadData'));
        $this->assertCount(7, $page->viewData('matrixProjects'));
        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(15, $many);

        // The export still sends what the tab shows, without drawing the page again
        $page->call('exportCsv')->assertFileDownloaded();
    }

    public function test_the_create_panel_loads_its_clients_only_when_it_opens(): void
    {
        DB::table('organizations')->insert(['id' => (string) Str::uuid(), 'name' => 'Zeta Holdings (test)', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->staff('owner', ['owner']));
        Livewire::test(ActiveDeployments::class)->assertDontSee('Zeta Holdings (test)')
            ->set('creating', true)->assertSee('Zeta Holdings (test)')
            ->set('creating', false)->assertDontSee('Zeta Holdings (test)');

        // Someone who can't create projects never gets the list
        $this->actingAs($this->staff('engineer', ['engineer']));
        Livewire::test(ActiveDeployments::class)->set('creating', true)->assertDontSee('Zeta Holdings (test)');
    }

    public function test_steps_and_comments_redraw_only_the_overview_and_never_the_board(): void
    {
        [$projectId, $phases] = $this->myCityShape();
        $lead = $this->staff('lead', ['engineer']);
        $task = $this->task($phases[4], ['assigned_to' => $lead->id, 'title' => 'Vacancy form']);
        DB::table('task_steps')->insert(['task_id' => $task, 'position' => 1, 'text' => 'Draft it', 'created_at' => now(), 'updated_at' => now()]);
        $step = DB::table('task_steps')->where('task_id', $task)->value('id');
        $this->actingAs($lead);

        // The drawer says what changed
        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $projectId])->dispatch('open-task', id: $task);
        $drawer->call('toggleStep', $step)->assertDispatched('project-changed', kind: 'activity');
        $drawer->set('comment', 'Started')->call('addComment')->assertDispatched('project-changed', kind: 'activity');
        $drawer->set('status', 'In-Progress')->assertDispatched('project-changed', kind: 'task');
        // Who ticked the step is still named
        $drawer->assertSee(__('projects.task.step_done', ['name' => trim($lead->first_name . ' ' . $lead->last_name), 'date' => '']), false);

        // Off the Overview, a step or a comment leaves the page as it is: only the access check runs
        $backlog = Livewire::withQueryParams(['tab' => 'backlog'])->test(ProjectPage::class, ['project' => $projectId]);
        [$skipped] = $this->queries(fn () => $backlog->call('refresh', 'activity'));
        [$reportSkipped] = $this->queries(fn () => $backlog->call('refresh', 'report'));
        [$drawn] = $this->queries(fn () => $backlog->call('refresh', 'task'));
        $this->assertLessThanOrEqual(3, $skipped);
        $this->assertLessThanOrEqual(3, $reportSkipped);
        $this->assertGreaterThan($skipped + 5, $drawn);
        // On the Overview the recent activity shows it, so the page draws again
        $overview = Livewire::withQueryParams(['tab' => 'overview'])->test(ProjectPage::class, ['project' => $projectId]);
        [$overviewDrawn] = $this->queries(fn () => $overview->call('refresh', 'activity'));
        $this->assertGreaterThan($skipped + 5, $overviewDrawn);

        // The board shows no steps or comments; a status change moves the card without reading the milestones again
        $board = Livewire::test(SprintBoard::class, ['id' => $projectId, 'milestone' => $phases[4]]);
        [$boardSkipped] = $this->queries(fn () => $board->call('refreshFromProject', 'activity'));
        $this->assertLessThanOrEqual(3, $boardSkipped);
        DB::table('tasks')->where('id', $task)->update(['status' => 'Failed']);
        [, , $sql] = $this->queries(fn () => $board->call('refreshFromProject', 'task'));
        $this->assertContains($task, collect($board->get('tasks')['Failed'])->pluck('id')->all());
        $this->assertEmpty(array_filter($sql, fn ($q) => str_contains($q, 'from `phase_dependencies`')));
        // A milestone change updates the menu
        DB::table('project_phases')->where('id', $phases[4])->update(['title' => 'Recruitment, end to end']);
        $board->call('refreshFromProject', 'milestone');
        $this->assertSame('Recruitment, end to end', collect($board->get('milestones'))->firstWhere('id', $phases[4])->name);
    }

    public function test_typing_in_a_picker_does_not_work_the_team_out_again(): void
    {
        [$projectId, $phases] = $this->myCityShape();
        $manager = $this->staff('delivery', ['delivery']);
        $engineer = $this->staff('engineer', ['engineer']);
        $task = $this->task($phases[4]);
        $this->actingAs($manager);
        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $projectId])->dispatch('open-task', id: $task);

        [, , $sql] = $this->queries(fn () => $drawer->set('leadSearch', 'engin'));
        $drawer->assertSee(trim($engineer->first_name . ' ' . $engineer->last_name));
        $this->assertEmpty(array_filter($sql, fn ($q) => str_contains($q, 'role_permissions')), 'The team (staff with project rights) was worked out again');
        // Every account's name isn't read to name the few people on the task
        $this->assertEmpty(array_filter($sql, fn ($q) => preg_match('/^select [^;]* from `users`$/', $q)));

        // Choosing still checks the team as it is now
        $drawer->call('chooseLead', $this->staff('hr', ['hr'])->id)->assertSet('leadId', '');
        $drawer->call('chooseLead', $engineer->id)->assertSet('leadId', $engineer->id);
    }

    public function test_the_board_reads_the_team_only_when_a_card_can_be_given_to_someone(): void
    {
        $manager = $this->staff('delivery', ['delivery']);
        $engineer = $this->staff('engineer', ['engineer']);
        $project = $this->project();
        $phase = $this->phase($project, 1, 100);
        $task = $this->task($phase, ['assigned_to' => $engineer->id, 'description' => str_repeat('Long brief. ', 500)]);
        $this->actingAs($manager);

        $board = Livewire::test(SprintBoard::class, ['id' => $project, 'milestone' => $phase]);
        $this->assertTrue($board->viewData('team')->isEmpty());
        // A card's description isn't kept in the board's state
        $this->assertStringNotContainsString('Long brief.', json_encode($board->snapshot));

        DB::table('tasks')->where('id', $task)->update(['assigned_to' => null]);
        $board->call('refreshFromProject', 'task');
        $this->assertContains($engineer->id, $board->viewData('team')->pluck('id')->all());
    }

    public function test_the_project_styles_go_out_once_per_page(): void
    {
        [$projectId] = $this->myCityShape();
        $this->actingAs($this->staff('owner', ['owner']));

        $html = $this->get(route('hq.ops.project', ['project' => $projectId]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '.pj-drawer-backdrop {'));
        // An update of the page sends no styles again
        $page = Livewire::test(ProjectPage::class, ['project' => $projectId]);
        $this->assertStringNotContainsString('.pj-drawer-backdrop {', $page->call('refresh', 'task')->html());
    }

    public function test_the_projects_a_person_sees_are_the_ones_they_lead_or_work_on(): void
    {
        $person = $this->staff('engineer', ['engineer']);
        [$leads, $assigned, $sharing, $watching, $other] = [
            $this->project(['lead_user_id' => $person->id]), $this->project(), $this->project(), $this->project(), $this->project(),
        ];
        $this->task($this->phase($assigned, 1, 100), ['assigned_to' => $person->id]);
        foreach ([$sharing => 'sharing', $watching => 'watcher'] as $project => $role) {
            $task = $this->task($this->phase($project, 1, 100));
            DB::table('task_people')->insert(['task_id' => $task, 'user_id' => $person->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->task($this->phase($other, 1, 100), ['assigned_to' => $this->staff('colleague', ['engineer'])->id]);

        $this->actingAs($person);
        $seen = ProjectAccess::assignedProjectIds();
        sort($seen);
        $expected = [$leads, $assigned, $sharing, $watching];
        sort($expected);
        $this->assertSame($expected, $seen);
        $this->assertFalse(ProjectAccess::maySee($other));
        $this->assertNotContains(CoreSeeder::INTERNAL_ORG_ID, $seen);
    }
}
