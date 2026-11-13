<?php

namespace Tests\Feature\Controls;

use App\Livewire\Hq\Operations\QaHub;
use App\Livewire\Hq\Operations\SprintBoard;
use App\Livewire\Hq\Operations\SprintBoardDirectory;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ → Operations: sprint boards and QA tickets follow one rule (App\Support\ProjectAccess). Managers and
 * auditors see every project; everyone else only projects where they have an assigned task. The rule is
 * checked when a board or ticket opens and again on every change, since access can end while a page is open.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class OperationsControlsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private User $engineer;
    private string $ownProject;
    private string $otherProject;
    private string $ownTask;
    private string $openTask;
    private string $ownTicket;
    private string $otherTicket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engineer = $this->staff('engineer', ['engineer']);

        $this->ownProject = $this->project('Assigned project (test)');
        $this->otherProject = $this->project('Other project (test)');
        $ownPhase = $this->phase($this->ownProject);
        $this->phase($this->otherProject);

        $this->ownTask = $this->task($ownPhase, $this->engineer->id);
        $this->openTask = $this->task($ownPhase, null);
        $this->ownTicket = $this->ticket($this->ownProject, 'Issue on the assigned project');
        $this->otherTicket = $this->ticket($this->otherProject, 'Issue on another project');
    }

    private function project(string $title): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('projects', [
            'id' => $id, 'organization_id' => CoreSeeder::INTERNAL_ORG_ID, 'title' => $title, 'status' => 'Active',
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null, 'financial_override_by' => null,
        ]);

        return $id;
    }

    private function phase(string $projectId): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('project_phases', ['id' => $id, 'project_id' => $projectId, 'phase_number' => 1, 'title' => 'Build',
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);

        return $id;
    }

    private function task(string $phaseId, ?string $assignee): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('tasks', ['id' => $id, 'phase_id' => $phaseId, 'sprint_id' => null, 'assigned_to' => $assignee,
            'title' => 'Task (test)', 'status' => 'To-Do', 'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);

        return $id;
    }

    private function ticket(string $projectId, string $title): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('qa_tickets', ['id' => $id, 'project_id' => $projectId, 'task_id' => null, 'submitted_by' => (string) Str::uuid(),
            'submitted_by_type' => 'client', 'title' => $title, 'description' => 'Test issue', 'status' => 'Investigating',
            'resolved_by' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);

        return $id;
    }

    public function test_a_board_opens_only_for_people_who_may_see_its_project(): void
    {
        $this->actingAs($this->engineer);
        Livewire::test(SprintBoard::class, ['id' => $this->ownProject])->assertSet('projectId', $this->ownProject);
        Livewire::test(SprintBoard::class, ['id' => $this->otherProject])->assertForbidden();
        $listed = collect(Livewire::test(SprintBoardDirectory::class)->get('projects'))->pluck('id')->all();
        $this->assertContains($this->ownProject, $listed);
        $this->assertNotContains($this->otherProject, $listed);

        $this->actingAs($this->staff('delivery', ['delivery']));
        Livewire::test(SprintBoard::class, ['id' => $this->otherProject])->assertSet('projectId', $this->otherProject);
    }

    public function test_board_access_is_checked_again_on_every_change(): void
    {
        $this->actingAs($this->engineer);
        $board = Livewire::test(SprintBoard::class, ['id' => $this->ownProject]);

        // Their only task on the project goes to someone else while the board is open
        DB::table('tasks')->where('id', $this->ownTask)->update(['assigned_to' => $this->staff('engineer2', ['engineer'])->id]);

        $board->call('assignToMe', $this->openTask)->assertForbidden();
        $this->assertNull(DB::table('tasks')->where('id', $this->openTask)->value('assigned_to'));
    }

    public function test_an_open_board_stops_refreshing_once_access_ends(): void
    {
        $this->actingAs($this->engineer);
        $refresh = Livewire::test(SprintBoard::class, ['id' => $this->ownProject]);
        $switch = Livewire::test(SprintBoard::class, ['id' => $this->ownProject]);

        DB::table('tasks')->where('id', $this->ownTask)->update(['assigned_to' => null]);

        $refresh->call('loadBoardData')->assertForbidden();
        $switch->call('switchMilestone', (string) Str::uuid())->assertForbidden();
    }

    public function test_the_milestone_menu_shows_that_milestones_tasks(): void
    {
        // Sprints are folded into milestones (docs/PROJECTS.md): the board opens on the milestone in progress, and the
        // menu moves it to another at once
        [$active, $done] = [(string) Str::uuid(), (string) Str::uuid()];
        foreach ([[$active, 4, 'Active'], [$done, 2, 'Completed']] as [$id, $number, $status]) {
            $this->insertRow('project_phases', ['id' => $id, 'project_id' => $this->ownProject, 'phase_number' => $number, 'title' => "M{$number}", 'status' => $status,
                'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);
        }
        DB::table('tasks')->where('id', $this->ownTask)->update(['phase_id' => $active]);
        DB::table('tasks')->where('id', $this->openTask)->update(['phase_id' => $done]);
        $ids = fn ($board) => collect($board->get('tasks'))->flatten(1)->pluck('id')->all();

        $this->actingAs($this->engineer);
        $board = Livewire::test(SprintBoard::class, ['id' => $this->ownProject])->assertSet('activeMilestoneId', $active);
        $this->assertSame([$this->ownTask], $ids($board));

        $board->set('activeMilestoneId', $done);
        $this->assertSame([$this->openTask], $ids($board));

        // Another project's milestone (or any made-up id) falls back to the one in progress
        $board->set('activeMilestoneId', (string) Str::uuid())->assertSet('activeMilestoneId', $active);
        $this->assertSame([$this->ownTask], $ids($board));

        // A kanban card's link opens the board on that milestone
        Livewire::test(SprintBoard::class, ['id' => $this->ownProject, 'milestone' => $done])->assertSet('activeMilestoneId', $done);
    }

    public function test_the_qa_hub_lists_and_opens_only_tickets_the_person_may_see(): void
    {
        $this->actingAs($this->engineer);

        $hub = Livewire::test(QaHub::class);
        $listed = collect($hub->get('tickets'))->pluck('id')->all();
        $this->assertSame([$this->ownTicket], $listed);

        $hub->call('openTicket', $this->otherTicket)->assertSet('selectedTicket', null);
        Livewire::withQueryParams(['ticket' => $this->otherTicket])->test(QaHub::class)->assertSet('selectedTicket', null);

        try {
            $hub->set('selectedTicket', ['id' => $this->otherTicket, 'status' => 'Open', 'task_id' => null]);
            $this->fail('selectedTicket should be locked');
        } catch (CannotUpdateLockedPropertyException) {
            // expected
        }

        // A QA approver sees every ticket
        $this->actingAs($this->staff('delivery', ['delivery']));
        $this->assertEqualsCanonicalizing([$this->ownTicket, $this->otherTicket], collect(Livewire::test(QaHub::class)->get('tickets'))->pluck('id')->all());
    }

    public function test_qa_actions_check_access_again(): void
    {
        $this->actingAs($this->engineer);
        $hub = Livewire::test(QaHub::class)->call('openTicket', $this->ownTicket)->assertSet('selectedTicket.id', $this->ownTicket);

        DB::table('tasks')->where('id', $this->ownTask)->delete();

        $hub->set('newCommentText', 'Looking into it')->call('postComment')
            ->assertDispatched('praxis-toast', $this->toast('We couldn’t find that issue. Refresh the list and open it again.'));
        $this->assertSame(0, DB::table('chat_messages')->where('qa_ticket_id', $this->ownTicket)->where('kind', 'text')->count());
    }
}
