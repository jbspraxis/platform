<?php

namespace Tests\Feature\Projects;

use App\Livewire\Hq\Finance\InvoiceLedger;
use App\Livewire\Hq\Operations\QaHub;
use App\Livewire\Hq\Operations\SprintBoard;
use App\Livewire\Hq\Projects\ChangeReportDrawer;
use App\Livewire\Hq\Projects\MilestoneDrawer;
use App\Livewire\Hq\Projects\ProjectPage;
use App\Livewire\Hq\Projects\TaskDrawer;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The project page (docs/PROJECTS.md): who opens and edits it (lead, manager, team member, outsider), its tabs on
 * MyCity's shape in English and French, the milestone kanban (status columns, Accepted, Invoiced via Finance) and the
 * links in from All projects, the milestone boards, the QA hub and Finance. Runs on a throwaway MySQL database.
 */
class ProjectPageTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private string $projectId;

    private array $phases;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->projectId, $this->phases] = $this->myCityShape();
    }

    private function engineerOnTask(): array
    {
        $member = $this->staff('member', ['engineer']);
        $task = $this->task($this->phases[4], ['assigned_to' => $member->id, 'title' => 'Vacancy form']);

        return [$member, $task];
    }

    public function test_every_tab_works_on_mycitys_shape(): void
    {
        $this->actingAs($this->staff('delivery', ['delivery']));
        $page = Livewire::test(ProjectPage::class, ['project' => $this->projectId])
            ->assertOk()->assertSee('MyCity App ERP')->assertSee('M4')->assertSee('Workstream 4');

        // 148 tasks, M1 paid: progress = M1 12% + M2 9% + M3 5% + M9 7% done = 33%, plus a third of M4 is in progress, not done
        $this->assertSame(33.0, $page->viewData('progress'));
        $this->assertSame(100.0, $page->viewData('weightTotal'));
        $this->assertCount(12, $page->viewData('milestones'));
        $this->assertSame('paid', $page->viewData('milestones')->firstWhere('phase_number', 1)->column);
        $this->assertSame('client_review', $page->viewData('milestones')->firstWhere('phase_number', 2)->column);

        foreach (['milestones' => 'Client review', 'backlog' => 'M4 task', 'qa' => 'No QA tickets', 'money' => 'Change reports'] as $tab => $text) {
            $page->call('show', $tab)->assertOk()->assertSee($text);
        }
        // A kanban card opens its milestone's board
        $page->call('show', 'milestones')->assertSee(route('hq.ops.project', ['project' => $this->projectId, 'tab' => 'board', 'board' => $this->phases[4]]));
        $page->call('show', 'backlog');
        $this->assertSame(148, $page->viewData('taskCount'));
        $page->set('filterStatus', 'In-Progress');
        $this->assertSame(4, $page->viewData('taskCount'));

        // The Milestone board tab holds the board, opened on the milestone the link names
        Livewire::withQueryParams(['tab' => 'board', 'board' => $this->phases[4]])->test(ProjectPage::class, ['project' => $this->projectId])
            ->assertOk()->assertSet('boardMilestone', $this->phases[4])->assertSeeLivewire(SprintBoard::class);
        $board = Livewire::test(SprintBoard::class, ['id' => $this->projectId, 'milestone' => $this->phases[4]])->assertSet('activeMilestoneId', $this->phases[4]);
        $this->assertCount(12, collect($board->get('tasks'))->flatten(1));
    }

    public function test_hq_no_longer_says_sprint(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        foreach (ProjectPage::TABS as $tab) {
            Livewire::withQueryParams(['tab' => $tab])->test(ProjectPage::class, ['project' => $this->projectId])->assertDontSeeText('Sprint')->assertDontSeeText('sprint ');
        }
        Livewire::test(SprintBoard::class, ['id' => $this->projectId])->assertDontSeeText('Sprint')->assertSee('M4 · Workstream 4');
        $this->get(route('hq.ops.boards.directory'))->assertOk()->assertDontSeeText('Sprint')->assertSeeText('Milestone boards');
        $this->get(route('hq.ops.deployments'))->assertOk()->assertDontSeeText('Sprint');
        $this->get(route('hq.ops.scrum', $this->projectId))->assertOk()->assertDontSeeText('Sprint')->assertSeeText('Plan milestones');
    }

    public function test_the_page_and_its_drawers_read_in_french(): void
    {
        app()->setLocale('fr');
        $this->actingAs($this->staff('delivery', ['delivery']));
        Livewire::test(ProjectPage::class, ['project' => $this->projectId])->assertSee('Vue d’ensemble')->assertSee('Jalons')
            ->call('show', 'milestones')->assertSee('Revue client')->assertSee('Payé');
        Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('new-task', phase: $this->phases[4])->assertSee('Ajouter une tâche');
        Livewire::test(MilestoneDrawer::class, ['projectId' => $this->projectId])->dispatch('open-milestone', id: $this->phases[1])->assertSee('Facture');
        Livewire::test(SprintBoard::class, ['id' => $this->projectId, 'milestone' => $this->phases[4]])->assertSee('Jalon')->assertSee('Détails du jalon');
        Livewire::test(ChangeReportDrawer::class, ['projectId' => $this->projectId])->dispatch('new-report')->assertSee('Rédiger un rapport de modification');
    }

    public function test_english_and_french_have_the_same_keys(): void
    {
        $en = array_keys(Arr::dot(require lang_path('en/projects.php')));
        $fr = array_keys(Arr::dot(require lang_path('fr/projects.php')));
        sort($en);
        sort($fr);
        $this->assertSame($en, $fr);
    }

    public function test_an_outsider_cannot_open_the_page_or_a_drawer(): void
    {
        $this->actingAs($this->staff('outsider', ['engineer']));
        Livewire::test(ProjectPage::class, ['project' => $this->projectId])->assertForbidden();
        $this->get(route('hq.ops.project', $this->projectId))->assertForbidden();

        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $this->task($this->phases[4]));
        $drawer->assertSet('open', false);
        Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('new-task')->assertForbidden();
        Livewire::test(MilestoneDrawer::class, ['projectId' => $this->projectId])->dispatch('new-milestone')->assertForbidden();
        Livewire::test(ChangeReportDrawer::class, ['projectId' => $this->projectId])->dispatch('new-report')->assertForbidden();
    }

    public function test_a_team_member_reads_the_plan_and_works_only_on_their_own_tasks(): void
    {
        [$member, $own] = $this->engineerOnTask();
        $other = $this->task($this->phases[4], ['assigned_to' => $this->staff('colleague', ['engineer'])->id]);
        $this->actingAs($member);

        Livewire::test(ProjectPage::class, ['project' => $this->projectId])->assertOk()->assertSet('projectId', $this->projectId)
            ->assertDontSee(__('projects.milestones.new'))
            ->call('moveMilestone', $this->phases[5], 'in_progress')->assertForbidden();
        $this->assertSame('Pending', DB::table('project_phases')->where('id', $this->phases[5])->value('status'));

        // Their own task: status, steps, comments; not the details
        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $own)->assertSet('open', true);
        $drawer->set('status', 'In-Progress');
        $this->assertSame('In-Progress', DB::table('tasks')->where('id', $own)->value('status'));
        $drawer->set('newStep', 'Write the tests')->call('addStep')->assertHasNoErrors();
        $drawer->set('comment', 'Started on the form.')->call('addComment')->assertHasNoErrors();
        $drawer->set('title', 'Renamed')->call('save')->assertForbidden();
        $this->assertSame('Vacancy form', DB::table('tasks')->where('id', $own)->value('title'));

        // Someone else's: read only
        $theirs = Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $other);
        $theirs->set('status', 'Done')->assertSet('status', 'To-Do');
        $this->assertSame('To-Do', DB::table('tasks')->where('id', $other)->value('status'));
        $theirs->set('comment', 'Not mine')->call('addComment')->assertForbidden();
        Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $other)
            ->set('newStep', 'Not mine')->call('addStep')->assertForbidden();
        $this->assertSame(0, DB::table('task_comments')->where('task_id', $other)->count());
    }

    public function test_sharing_a_task_lets_a_member_move_it_on_the_board_too(): void
    {
        [$member] = $this->engineerOnTask();
        $shared = $this->task($this->phases[4], ['assigned_to' => $this->staff('owner2', ['engineer'])->id]);
        DB::table('task_people')->insert(['task_id' => $shared, 'user_id' => $member->id, 'role' => 'sharing', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($member);

        $board = Livewire::test(SprintBoard::class, ['id' => $this->projectId, 'milestone' => $this->phases[4]])->assertSet('activeMilestoneId', $this->phases[4]);
        $card = collect($board->get('tasks'))->flatten(1)->firstWhere('id', $shared);
        $this->assertTrue($card['can_move']);
        $board->call('updateTaskStatus', $shared, 'In-Progress');
        $this->assertSame('In-Progress', DB::table('tasks')->where('id', $shared)->value('status'));
        // The timer started for them, as before
        $this->assertTrue(DB::table('task_time_logs')->where('task_id', $shared)->where('user_id', $member->id)->whereNull('end_time')->exists());
    }

    public function test_the_project_lead_edits_everything_without_a_manager_role(): void
    {
        $lead = $this->staff('lead', ['engineer']);
        DB::table('projects')->where('id', $this->projectId)->update(['lead_user_id' => $lead->id]);
        $this->actingAs($lead);

        Livewire::test(ProjectPage::class, ['project' => $this->projectId])->assertOk()->assertSee(__('projects.backlog.new_task'))
            ->call('moveMilestone', $this->phases[5], 'in_progress');
        $this->assertSame('Active', DB::table('project_phases')->where('id', $this->phases[5])->value('status'));

        Livewire::test(MilestoneDrawer::class, ['projectId' => $this->projectId])->dispatch('new-milestone')
            ->set('title', 'Data migration')->set('weight', '0')->call('save')->assertHasNoErrors();
        $this->assertTrue(DB::table('project_phases')->where('project_id', $this->projectId)->where('title', 'Data migration')->where('phase_number', 12)->exists());

        $task = $this->task($this->phases[6], ['assigned_to' => $this->staff('someone', ['engineer'])->id]);
        Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $task)
            ->set('title', 'Payroll rules')->set('dueDate', '2026-11-27')->call('save')->assertHasNoErrors();
        $this->assertSame('Payroll rules', DB::table('tasks')->where('id', $task)->value('title'));

        // A milestone carries its own dates and goal (sprints are folded into milestones)
        Livewire::test(MilestoneDrawer::class, ['projectId' => $this->projectId])->dispatch('open-milestone', id: $this->phases[5])
            ->set('goal', 'Offers out')->set('dueDate', '2026-11-24')->call('save')->assertHasNoErrors();
        $this->assertSame(['Offers out', '2026-11-24'], array_values((array) DB::table('project_phases')->where('id', $this->phases[5])->first(['goal', 'due_date'])));
    }

    public function test_a_manager_sets_the_lead_and_an_auditor_only_reads(): void
    {
        $delivery = $this->staff('delivery', ['delivery']);
        $engineer = $this->staff('engineer', ['engineer']);
        $this->actingAs($delivery);
        Livewire::test(ProjectPage::class, ['project' => $this->projectId])->set('leadUserId', $engineer->id)->call('saveLead')->assertHasNoErrors();
        $this->assertSame($engineer->id, DB::table('projects')->where('id', $this->projectId)->value('lead_user_id'));
        $this->assertTrue(DB::table('system_audit_logs')->where('action', 'PROJECT_LEAD_CHANGED')->where('target_id', $this->projectId)->exists());

        $this->actingAs($this->staff('auditor', ['auditor']));
        Livewire::test(ProjectPage::class, ['project' => $this->projectId])->assertOk()->assertDontSee(__('projects.backlog.new_task'))
            ->set('leadUserId', $delivery->id)->call('saveLead')->assertForbidden();
        Livewire::test(MilestoneDrawer::class, ['projectId' => $this->projectId])->dispatch('open-milestone', id: $this->phases[4])
            ->assertSet('open', true)->set('title', 'Changed')->call('save')->assertForbidden();
    }

    public function test_the_kanban_moves_status_columns_and_sends_invoicing_to_finance(): void
    {
        $delivery = $this->staff('delivery', ['delivery']);
        $this->actingAs($delivery);
        $page = Livewire::test(ProjectPage::class, ['project' => $this->projectId])->call('show', 'milestones');

        $page->call('moveMilestone', $this->phases[5], 'client_review');
        $this->assertSame('QA', DB::table('project_phases')->where('id', $this->phases[5])->value('status'));
        $this->assertTrue(DB::table('system_audit_logs')->where('action', 'MILESTONE_MOVED')->where('target_id', $this->phases[5])->exists());

        // Accepted asks who accepted it, in the drawer
        $page->call('moveMilestone', $this->phases[2], 'accepted')->assertDispatched('open-milestone', id: $this->phases[2], accept: true);
        $this->assertSame('QA', DB::table('project_phases')->where('id', $this->phases[2])->value('status'));
        $drawer = Livewire::test(MilestoneDrawer::class, ['projectId' => $this->projectId])->dispatch('open-milestone', id: $this->phases[2], accept: true)
            ->assertSet('column', 'accepted')->set('acceptedBy', '')->call('save')->assertHasErrors('acceptedBy');
        $drawer->set('acceptedBy', 'Oyebode, CTO')->set('acceptedAt', '2026-10-22')->call('save')->assertHasNoErrors();
        $this->assertSame(['Completed', '2026-10-22', 'Oyebode, CTO'], array_values((array) DB::table('project_phases')->where('id', $this->phases[2])->first(['status', 'accepted_at', 'accepted_by_name'])));

        // Delivery has no finance.create: told who can, nothing changes
        $page->call('moveMilestone', $this->phases[2], 'invoiced')->assertNoRedirect()->assertDispatched('praxis-toast', $this->toast('Invoices are raised in Finance'));

        // Paid follows Finance; an invoiced card stays where its invoice puts it
        $page->call('moveMilestone', $this->phases[2], 'paid');
        $page->call('moveMilestone', $this->phases[1], 'in_progress');
        $this->assertSame('Completed', DB::table('project_phases')->where('id', $this->phases[1])->value('status'));

        $this->actingAs($this->staff('owner', ['owner']));
        Livewire::test(ProjectPage::class, ['project' => $this->projectId])->call('moveMilestone', $this->phases[2], 'invoiced')
            ->assertRedirect(route('hq.finance.invoices', ['create' => 1, 'project' => $this->projectId, 'phase' => $this->phases[2]]));
    }

    public function test_finance_create_invoice_opens_prefilled_for_the_milestone(): void
    {
        DB::table('project_phases')->where('id', $this->phases[2])->update(['status' => 'Completed']);
        $this->actingAs($this->staff('owner', ['owner']));

        $ledger = Livewire::withQueryParams(['create' => 1, 'project' => $this->projectId, 'phase' => $this->phases[2]])->test(InvoiceLedger::class)
            ->assertSet('showCreateModal', true)->assertSet('new_project_id', $this->projectId)->assertSet('picked', [$this->phases[2]]);
        $this->assertEquals(333000.0, $ledger->get('lines')[0]['amount']); // 9% of ₦3,700,000
        // Accepted milestones are offered; the one already invoiced (M1) is not
        $offered = collect($ledger->get('projectPhases'))->pluck('id');
        $this->assertTrue($offered->contains($this->phases[2]));
        $this->assertFalse($offered->contains($this->phases[1]));
    }

    public function test_the_ways_in_lead_to_the_project_page(): void
    {
        $this->actingAs($this->staff('delivery', ['delivery']));
        $url = route('hq.ops.project', ['project' => $this->projectId]);
        $this->get(route('hq.ops.deployments'))->assertOk()->assertSee($url, false);
        $this->get(route('hq.ops.boards.directory'))->assertOk()->assertSee(route('hq.ops.project', ['project' => $this->projectId, 'tab' => 'board']), false);
        // The old sprint board addresses (bells, bookmarks) lead to the new pages
        $this->get(route('hq.ops.board', $this->projectId))->assertRedirect(route('hq.ops.project', ['project' => $this->projectId, 'tab' => 'board']));
        $this->get(str_replace('/milestone-boards', '/sprint-boards', route('hq.ops.boards.directory')))->assertRedirect();

        // The QA hub filtered to the project, from its QA tab
        $ticket = (string) Str::uuid();
        $this->insertRow('qa_tickets', ['id' => $ticket, 'project_id' => $this->projectId, 'task_id' => null, 'submitted_by' => (string) Str::uuid(),
            'submitted_by_type' => 'client', 'title' => 'Leave year crosses New Year', 'description' => 'x', 'status' => 'Open',
            'resolved_by' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);
        $other = $this->project(['title' => 'Other project']);
        $this->insertRow('qa_tickets', ['id' => (string) Str::uuid(), 'project_id' => $other, 'task_id' => null, 'submitted_by' => (string) Str::uuid(),
            'submitted_by_type' => 'client', 'title' => 'Elsewhere', 'description' => 'x', 'status' => 'Open',
            'resolved_by' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);
        $hub = Livewire::withQueryParams(['project' => $this->projectId])->test(QaHub::class);
        $this->assertSame([$ticket], collect($hub->get('tickets'))->pluck('id')->all());
        $hub->call('showEveryProject');
        $this->assertCount(2, $hub->get('tickets'));
        Livewire::test(ProjectPage::class, ['project' => $this->projectId])->call('show', 'qa')->assertSee('Leave year crosses New Year');
    }
}
