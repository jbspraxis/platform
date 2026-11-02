<?php

namespace Tests\Feature\Projects;

use App\Livewire\Hq\Operations\SprintBoard;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The milestone board card's "Unassigned" picker (owner, 2 Nov 2026; it replaced the card's Assign to me button):
 * anyone with projects.edit takes an unassigned task for themselves, the project's lead and managers give it to anyone
 * on the team, and a task that already has a lead is changed in the drawer only.
 */
class BoardAssignTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    public function test_a_manager_gives_an_unassigned_task_to_someone_on_the_team(): void
    {
        $manager = $this->staff('delivery', ['delivery']);
        $engineer = $this->staff('engineer', ['engineer']);
        $project = $this->project();
        $phase = $this->phase($project, 1, 100);
        $open = $this->task($phase, ['title' => 'Build the server interface']);

        $this->actingAs($manager);
        $board = Livewire::test(SprintBoard::class, ['id' => $project, 'milestone' => $phase])
            ->assertSee(__('projects.task.unassigned'))
            ->assertSee(__('projects.task.assign_me', ['name' => trim($manager->first_name . ' ' . $manager->last_name)]))
            ->assertSee(trim($engineer->first_name . ' ' . $engineer->last_name))
            ->assertDontSee(__('projects.task.assign_to_me'));

        $board->call('assignTo', $open, $engineer->id);
        $this->assertSame($engineer->id, DB::table('tasks')->where('id', $open)->value('assigned_to'));
        $this->assertTrue(DB::table('notifications')->where('user_id', $engineer->id)->where('title', __('projects.bell.lead_title'))->exists());
        $this->assertTrue(DB::table('system_audit_logs')->where('target_id', $open)->where('action', 'TASK_UPDATED')->exists());

        // Taken while the board was open: nothing changes, and the board says so
        $board->call('assignTo', $open, $manager->id);
        $this->assertSame($engineer->id, DB::table('tasks')->where('id', $open)->value('assigned_to'));
    }

    public function test_an_engineer_only_takes_a_task_for_themselves(): void
    {
        $engineer = $this->staff('engineer', ['engineer']);
        $colleague = $this->staff('colleague', ['engineer']);
        $project = $this->project();
        $phase = $this->phase($project, 1, 100);
        $this->task($phase, ['assigned_to' => $engineer->id]); // so the engineer can open this board
        $open = $this->task($phase);

        $this->actingAs($engineer);
        $board = Livewire::test(SprintBoard::class, ['id' => $project, 'milestone' => $phase])
            ->assertSee(__('projects.task.assign_me', ['name' => trim($engineer->first_name . ' ' . $engineer->last_name)]))
            ->assertDontSee(trim($colleague->first_name . ' ' . $colleague->last_name));

        $board->call('assignTo', $open, $colleague->id)->assertForbidden();
        $this->assertNull(DB::table('tasks')->where('id', $open)->value('assigned_to'));

        Livewire::test(SprintBoard::class, ['id' => $project, 'milestone' => $phase])->call('assignTo', $open, $engineer->id);
        $this->assertSame($engineer->id, DB::table('tasks')->where('id', $open)->value('assigned_to'));
    }

    public function test_only_active_people_on_the_team_and_only_this_projects_tasks(): void
    {
        $manager = $this->staff('delivery', ['delivery']);
        $gone = $this->staff('gone', ['engineer']);
        DB::table('users')->where('id', $gone->id)->update(['status' => 'Offboarded']);
        $project = $this->project();
        $phase = $this->phase($project, 1, 100);
        $open = $this->task($phase);
        $elsewhere = $this->task($this->phase($this->project(), 1, 100));

        $this->actingAs($manager);
        $board = Livewire::test(SprintBoard::class, ['id' => $project, 'milestone' => $phase]);
        $board->call('assignTo', $open, $gone->id);
        $board->call('assignTo', $elsewhere, $manager->id);
        $this->assertNull(DB::table('tasks')->where('id', $open)->value('assigned_to'));
        $this->assertNull(DB::table('tasks')->where('id', $elsewhere)->value('assigned_to'));
    }
}
