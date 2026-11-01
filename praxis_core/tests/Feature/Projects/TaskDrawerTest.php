<?php

namespace Tests\Feature\Projects;

use App\Livewire\Hq\Operations\SprintBoard;
use App\Livewire\Hq\Projects\TaskDrawer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The task drawer (docs/PROJECTS.md section 3, decisions 4 and 8): a lead picked from the team, people sharing it and
 * watchers, steps, comments with a file, due-date proposals for the project lead, and who hears about what: the bell
 * for the people on the task, never an email to the client.
 */
class TaskDrawerTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private string $projectId;

    private string $phaseId;

    private string $sprintId;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectId = $this->project();
        $this->phaseId = $this->phase($this->projectId, 4, 100, ['status' => 'Active']);
        $this->sprintId = $this->sprint($this->projectId, 'M4 · Recruitment', '2026-11-02', '2026-11-13', 'Active');
        $this->manager = $this->staff('delivery', ['delivery']);
    }

    private function bells(User $user): array
    {
        return DB::table('notifications')->where('user_id', $user->id)->pluck('title')->all();
    }

    public function test_a_manager_adds_a_task_with_a_lead_people_and_watchers(): void
    {
        [$lead, $sharer, $watcher] = [$this->staff('lead', ['engineer']), $this->staff('sharer', ['engineer']), $this->staff('watcher', ['engineer'])];
        $this->actingAs($this->manager);

        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('new-task', phase: $this->phaseId, sprint: $this->sprintId)
            ->assertSet('phaseId', $this->phaseId)->assertSet('sprintId', $this->sprintId)
            ->set('title', 'Blind panel scoring')->set('leadId', $lead->id)->set('sharing', [$sharer->id])->set('watchers', [$watcher->id])
            ->set('dueDate', '2026-11-12')->set('hours', '6')->call('save')->assertHasNoErrors();

        $task = DB::table('tasks')->where('title', 'Blind panel scoring')->first();
        $this->assertSame([$lead->id, '2026-11-12', '6.00', $this->sprintId], [$task->assigned_to, $task->due_date, $task->estimated_hours, $task->sprint_id]);
        $this->assertSame($sharer->id, DB::table('task_people')->where('task_id', $task->id)->where('role', 'sharing')->value('user_id'));
        $this->assertSame($watcher->id, DB::table('task_people')->where('task_id', $task->id)->where('role', 'watcher')->value('user_id'));
        $this->assertContains(__('projects.bell.lead_title'), $this->bells($lead));
        $this->assertTrue(DB::table('system_audit_logs')->where('target_table', 'tasks')->where('target_id', $task->id)->where('action', 'TASK_CREATED')->exists());
        $drawer->assertSet('link', $task->id);

        // The lead is picked from the team: someone without project rights or a place on the project is refused
        $stranger = $this->staff('hr', ['hr']);
        $drawer->set('leadId', $stranger->id)->call('save')->assertHasErrors('leadId');
        $this->assertSame($lead->id, DB::table('tasks')->where('id', $task->id)->value('assigned_to'));
    }

    public function test_watchers_and_people_on_the_task_get_the_bell_and_the_client_gets_no_email(): void
    {
        Mail::fake();
        [$lead, $sharer, $watcher] = [$this->staff('lead', ['engineer']), $this->staff('sharer', ['engineer']), $this->staff('watcher', ['engineer'])];
        $task = $this->task($this->phaseId, ['assigned_to' => $lead->id, 'sprint_id' => $this->sprintId, 'title' => 'Offer letter']);
        DB::table('task_people')->insert([
            ['task_id' => $task, 'user_id' => $sharer->id, 'role' => 'sharing', 'created_at' => now(), 'updated_at' => now()],
            ['task_id' => $task, 'user_id' => $watcher->id, 'role' => 'watcher', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->actingAs($lead);
        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $task);

        $drawer->set('status', 'In-Progress');
        $this->assertSame([], $this->bells($watcher)); // only Client review, Done and comments
        $drawer->set('status', 'QA_Review');
        $drawer->set('comment', 'Ready for MyCity to look at.')->call('addComment');
        $drawer->set('status', 'Done');

        // Two moves (Client review, Done) and the comment, in any order (they share a second)
        $expected = [__('projects.bell.comment_title'), __('projects.bell.moved_title'), __('projects.bell.moved_title')];
        sort($expected);
        foreach ([$watcher, $sharer] as $person) {
            $got = array_values(array_diff($this->bells($person), ['Task ready for client review']));
            sort($got);
            $this->assertSame($expected, $got);
        }
        $this->assertSame([], array_values(array_diff($this->bells($lead), ['Task ready for client review']))); // they did it
        $link = DB::table('notifications')->where('user_id', $watcher->id)->value('link_url');
        $this->assertSame(route('hq.ops.project', ['project' => $this->projectId, 'tab' => 'backlog', 'task' => $task]), $link);

        // The sprint board's rules hold: the client review window opened and closed, the timer ran
        $this->assertNotNull(DB::table('tasks')->where('id', $task)->value('qa_sla_expires_at'));
        $this->assertSame(1, DB::table('task_time_logs')->where('task_id', $task)->whereNotNull('end_time')->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_steps_are_ticked_by_the_people_on_the_task(): void
    {
        $lead = $this->staff('lead', ['engineer']);
        $task = $this->task($this->phaseId, ['assigned_to' => $lead->id]);
        $this->actingAs($lead);
        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $task)
            ->set('newStep', 'Draft the template')->call('addStep')->set('newStep', 'Review with HR')->call('addStep')->assertSee('Steps 0/2');

        $first = DB::table('task_steps')->where('task_id', $task)->orderBy('position')->value('id');
        $drawer->call('toggleStep', $first)->assertSee('Steps 1/2');
        $this->assertSame($lead->id, DB::table('task_steps')->where('id', $first)->value('done_by'));
        $drawer->call('removeStep', $first)->assertSee('Steps 0/1');
        $this->assertSame(['TASK_STEP_ADDED', 'TASK_STEP_ADDED', 'TASK_STEP_DONE', 'TASK_STEP_REMOVED'],
            DB::table('system_audit_logs')->where('target_id', $task)->orderBy('id')->pluck('action')->all());
    }

    public function test_a_team_member_proposes_a_due_date_and_the_project_lead_decides(): void
    {
        $projectLead = $this->staff('plead', ['engineer']);
        DB::table('projects')->where('id', $this->projectId)->update(['lead_user_id' => $projectLead->id]);
        $member = $this->staff('member', ['engineer']);
        $task = $this->task($this->phaseId, ['assigned_to' => $member->id, 'due_date' => '2026-11-10', 'title' => 'Assessments']);

        $this->actingAs($member);
        Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $task)
            ->set('proposedDate', '2026-11-12')->call('proposeDue')->assertHasNoErrors();
        $this->assertSame(['2026-11-10', '2026-11-12'], array_values((array) DB::table('tasks')->where('id', $task)->first(['due_date', 'proposed_due_date'])));
        $this->assertContains(__('projects.bell.proposal_title'), $this->bells($projectLead));

        $this->actingAs($projectLead);
        Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $task)
            ->assertSee(__('projects.task.accept_proposal'))->call('decideProposal', true);
        $this->assertSame(['2026-11-12', null], array_values((array) DB::table('tasks')->where('id', $task)->first(['due_date', 'proposed_due_date'])));
    }

    public function test_assign_to_me_takes_an_open_task_and_anyone_can_watch(): void
    {
        $member = $this->staff('member', ['engineer']);
        $this->task($this->phaseId, ['assigned_to' => $member->id]); // on the project
        $open = $this->task($this->phaseId);
        $taken = $this->task($this->phaseId, ['assigned_to' => $this->staff('other', ['engineer'])->id]);
        $this->actingAs($member);

        Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $open)->call('assignToMe');
        $this->assertSame($member->id, DB::table('tasks')->where('id', $open)->value('assigned_to'));
        $drawer = Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $taken)->call('assignToMe')
            ->assertSee(__('projects.task.already_led'));
        $this->assertNotSame($member->id, DB::table('tasks')->where('id', $taken)->value('assigned_to'));

        $drawer->call('watch', true);
        $this->assertSame('watcher', DB::table('task_people')->where('task_id', $taken)->where('user_id', $member->id)->value('role'));
        $drawer->call('watch', false);
        $this->assertFalse(DB::table('task_people')->where('task_id', $taken)->where('user_id', $member->id)->exists());
    }

    public function test_a_comment_carries_a_file_only_people_on_the_project_can_download(): void
    {
        Storage::fake('local');
        $lead = $this->staff('lead', ['engineer']);
        $task = $this->task($this->phaseId, ['assigned_to' => $lead->id]);
        $this->actingAs($lead);
        Livewire::test(TaskDrawer::class, ['projectId' => $this->projectId])->dispatch('open-task', id: $task)
            ->set('comment', 'The signed offer template')->set('attachment', UploadedFile::fake()->create('offer.docx', 40))
            ->call('addComment')->assertHasNoErrors();

        $path = DB::table('task_comments')->where('task_id', $task)->value('attachment_path');
        $this->assertStringStartsWith('task_files/' . $task . '/', $path);
        $this->assertStringEndsWith('.docx', $path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($lead)->get(route('hq.file', ['path' => $path]))->assertOk()->assertHeader('content-disposition');
        $this->actingAs($this->staff('outsider', ['engineer']))->get(route('hq.file', ['path' => $path]))->assertForbidden();
    }

    public function test_the_board_card_opens_the_drawer_and_shows_due_dates_not_story_points(): void
    {
        $lead = $this->staff('lead', ['engineer']);
        $task = $this->task($this->phaseId, ['assigned_to' => $lead->id, 'sprint_id' => $this->sprintId, 'title' => 'CV parsing', 'due_date' => '2026-10-20', 'story_points' => 8]);
        $this->actingAs($lead);

        Livewire::test(SprintBoard::class, ['id' => $this->projectId])
            ->assertSee('CV parsing')->assertSee(__('projects.overdue'))->assertDontSee('story points')
            ->assertSeeHtml("\$dispatch('open-task', { id: '{$task}' })");
    }
}
