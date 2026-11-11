<?php

namespace Tests\Feature\Dashboards;

use App\Livewire\Hq\Operations\QaHub;
use App\Livewire\Hq\Operations\ScrumMaster;
use App\Livewire\Hq\Operations\SignoffManager;
use App\Livewire\Hq\Operations\SilentTimers;
use App\Livewire\Hq\Operations\TrackerMatrix;
use App\Models\TrackerUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Operations screens after the performance pass (11 Nov 2026): Time tracking, Quality and issues and Sign-offs keep
 * no rows in the page's state and read only the open tab; the planner's poll draws nothing while the AI hasn't
 * moved on; the task force tracker shows the latest messages, with earlier ones on request.
 */
class OperationsSpeedTest extends TestCase
{
    use ProjectFixtures, UsesThrowawayDatabase;

    private const LONG = 'LONG-DESCRIPTION ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'UTC'));
    }

    /** Queries one request of the page runs */
    private function queriesFor(\Closure $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** Someone with a timer running for 30 minutes, at their own hourly rate */
    private function running(string $taskId, string $slug, float $rate): void
    {
        $person = $this->staff($slug, ['engineer']);
        $this->insertRow('compensation_history', ['user_id' => $person->id, 'hourly_rate' => $rate, 'effective_from' => '2026-01-01']);
        DB::table('task_time_logs')->insert(['task_id' => $taskId, 'user_id' => $person->id, 'start_time' => now()->subMinutes(30), 'created_at' => now()]);
    }

    public function test_time_tracking_reads_the_open_tab_only_with_each_persons_own_rate_and_keeps_no_rows_in_its_state(): void
    {
        $owner = $this->staff('own', ['owner']);
        $phase = $this->phase($this->project(['title' => 'Payroll system (test)']), 1, 100);
        $task = $this->task($phase, ['title' => 'Payslip export', 'description' => self::LONG . str_repeat('x', 2000)]);
        $this->running($task, 'ada', 40);
        $this->running($task, 'ben', 60);
        DB::table('task_time_logs')->insert(['task_id' => $task, 'user_id' => $owner->id, 'start_time' => now()->subHours(3), 'end_time' => now()->subHours(2),
            'duration_minutes' => 60, 'cost_calculated' => 50, 'created_at' => now()]);

        // 30 minutes at each person's own rate (the rate their time is costed at when the timer stops)
        $page = Livewire::actingAs($owner)->test(SilentTimers::class)
            ->assertSeeInOrder(['Ada', '$20.00'])->assertSeeInOrder(['Ben', '$30.00'])
            ->assertDontSee('$25.00');
        $this->assertSame('live_burn', $page->snapshot['data']['activeTab']);
        foreach (['liveBurnData', 'anomaliesData', 'ledgerData', 'correctionRequests'] as $gone) {
            $this->assertArrayNotHasKey($gone, $page->snapshot['data']);
        }
        $this->assertStringNotContainsString('LONG-DESCRIPTION', json_encode($page->snapshot));

        // One poll costs the same with two or with six people running a timer (their rates are read together)
        $two = $this->queriesFor(fn () => $page->call('$refresh'));
        foreach (['cleo', 'dan', 'eve', 'fay'] as $slug) {
            $this->running($task, $slug, 30);
        }
        $six = $this->queriesFor(fn () => $page->call('$refresh'));
        $this->assertSame($two, $six);
        $page->assertSee('Fay');

        // The time log is read on its own tab
        $page->set('activeTab', 'ledger')->assertSee('Own Test')->assertSee('Payslip export')->assertDontSee('Ada Test');
    }

    public function test_the_qa_hub_keeps_no_tickets_in_its_state_and_its_list_follows_each_change(): void
    {
        $owner = $this->staff('own', ['owner']);
        $project = $this->project(['title' => 'Payroll system (test)']);
        $ids = [];
        foreach (['Open' => 'Totals are wrong', 'Resolved' => 'Logo is blurred'] as $status => $title) {
            $ids[$status] = (string) Str::uuid();
            $this->insertRow('qa_tickets', ['id' => $ids[$status], 'project_id' => $project, 'task_id' => null, 'submitted_by' => $owner->id,
                'submitted_by_type' => 'staff', 'title' => $title, 'description' => self::LONG . $title, 'status' => $status, 'severity' => 'Medium',
                'resolved_by' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null, 'created_at' => now()->subHour()]);
        }

        $hub = Livewire::actingAs($owner)->test(QaHub::class)->assertSee('Totals are wrong')->assertDontSee('Logo is blurred');
        $this->assertArrayNotHasKey('tickets', $hub->snapshot['data']);
        $this->assertStringNotContainsString('LONG-DESCRIPTION', json_encode($hub->snapshot));

        // The filter, and a ticket that someone starts on, show at once
        $hub->set('filterStatus', 'All')->assertSee('Logo is blurred');
        $hub->set('filterStatus', 'Open')->call('openTicket', $ids['Open']);
        $this->assertSame('Investigating', DB::table('qa_tickets')->where('id', $ids['Open'])->value('status'));
        $this->assertSame('Investigating', collect($hub->viewData('tickets'))->firstWhere('id', $ids['Open'])['status']);
    }

    public function test_sign_offs_keep_no_rows_in_their_state_and_a_draft_adds_up_the_project_in_the_database(): void
    {
        $owner = $this->staff('own', ['owner']);
        $project = $this->project(['title' => 'Payroll system (test)']);
        $phase = $this->phase($project, 1, 100);
        $a = $this->task($phase, ['story_points' => 3, 'status' => 'Done']);
        $b = $this->task($phase, ['story_points' => 5, 'status' => 'Done']);
        foreach ([[$a, 90, 0], [$b, 60, 1], [$b, 30, 0]] as [$task, $minutes, $rework]) {
            DB::table('task_time_logs')->insert(['task_id' => $task, 'user_id' => $owner->id, 'start_time' => now()->subHours(5), 'end_time' => now()->subHours(4),
                'duration_minutes' => $minutes, 'is_rework' => $rework, 'created_at' => now()]);
        }
        $this->insertRow('qa_tickets', ['id' => (string) Str::uuid(), 'project_id' => $project, 'task_id' => null, 'submitted_by' => $owner->id,
            'submitted_by_type' => 'staff', 'title' => 'Bug', 'description' => 'x', 'status' => 'Resolved', 'resolved_by' => null,
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);
        $this->insertRow('project_signoffs', ['id' => (string) Str::uuid(), 'project_id' => $project, 'token' => hash('sha256', 'old'), 'signatory_email' => 'cfo@client.test',
            'executive_summary' => self::LONG . 'summary', 'status' => 'Signed', 'signed_at' => now(), 'created_at' => now()]);

        $prompt = null;
        Http::fake(function ($request) use (&$prompt) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? null;

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['executive_summary' => 'Delivered.', 'roi_metrics' => []])]]]]]]);
        });

        $page = Livewire::actingAs($owner)->test(SignoffManager::class)->assertSee('cfo@client.test');
        foreach (['signoffs', 'eligibleProjects', 'portfolioLedger'] as $gone) {
            $this->assertArrayNotHasKey($gone, $page->snapshot['data']);
        }
        $this->assertStringNotContainsString('LONG-DESCRIPTION', json_encode($page->snapshot) . $page->html());

        $page->set('draftProjectId', $project)->set('draftSignatoryEmail', 'ceo@client.test')->call('generateSignoffDraft')
            ->assertDispatched('praxis-toast', $this->toast('Sign-off drafted.'))->assertSee('ceo@client.test');
        // 8 story points; 180 minutes, 60 of them rework; one issue
        $this->assertStringContainsString('Story points delivered: 8', $prompt);
        $this->assertStringContainsString('Hours worked: 3', $prompt);
        $this->assertStringContainsString('Rework hours (cost of poor quality): 1', $prompt);
        $this->assertStringContainsString('Issues reported and resolved: 1', $prompt);
    }

    public function test_the_planner_poll_draws_nothing_until_the_ai_moves_on(): void
    {
        $owner = $this->staff('own', ['owner']);
        $project = $this->project(['title' => 'Payroll system (test)']);
        $phase = $this->phase($project, 1, 100);
        $this->task($phase, ['title' => 'Existing task']);
        $run = (string) Str::uuid();
        DB::table('ai_runs')->insert(['id' => $run, 'purpose' => 'plan_milestone', 'provider' => 'claude', 'model' => 'test', 'subject_type' => 'milestone_plan',
            'status' => 'running', 'progress' => 40, 'input' => json_encode(['pass' => 1, 'scope' => str_repeat('scope ', 1000)]), 'created_at' => now(), 'updated_at' => now()]);
        $plan = (string) Str::uuid();
        DB::table('milestone_plans')->insert(['id' => $plan, 'project_id' => $project, 'phase_id' => $phase, 'scope' => 'Scope', 'status' => 'drafting',
            'ai_run_id' => $run, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ai_runs')->where('id', $run)->update(['subject_id' => $plan]);

        $page = Livewire::actingAs($owner)->test(ScrumMaster::class, ['id' => $project])->assertSee('aria-valuenow="40"', false);

        // Nothing new: a couple of small reads and no page
        $queries = $this->queriesFor(fn () => $page->call('check'));
        $this->assertArrayNotHasKey('html', $page->effects);
        $this->assertLessThanOrEqual(5, $queries);

        // The AI moved on: the page draws its progress
        DB::table('ai_runs')->where('id', $run)->update(['progress' => 70]);
        $page->call('check');
        $this->assertArrayHasKey('html', $page->effects);
        $page->assertSee('aria-valuenow="70"', false);
        $page->call('check');
        $this->assertArrayNotHasKey('html', $page->effects);

        // It finished (here: failed): the poll draws the page, which files it
        DB::table('ai_runs')->where('id', $run)->update(['status' => 'failed', 'error' => 'ai.errors.failed', 'finished_at' => now()]);
        $page->call('check');
        $this->assertArrayHasKey('html', $page->effects);
        $this->assertSame('failed', DB::table('milestone_plans')->where('id', $plan)->value('status'));
        $page->assertDontSee('wire:poll', false);
    }

    public function test_the_tracker_shows_the_latest_messages_with_earlier_ones_on_request_and_counts_progress_in_one_query(): void
    {
        $lead = TrackerUser::forceCreate(['id' => (string) Str::uuid(), 'name' => 'Lead (test)', 'email' => 'lead@local.test', 'password' => 'x', 'role' => 'superadmin', 'is_active' => 1]);
        $tracker = (string) Str::uuid();
        DB::table('trackers')->insert(['id' => $tracker, 'unique_token' => Str::random(40), 'pin_code' => '1234', 'project_name' => 'Task force (test)',
            'client_name' => 'Client', 'created_by' => $lead->id, 'created_at' => now()]);
        $milestone = (string) Str::uuid();
        DB::table('tracker_milestones')->insert(['id' => $milestone, 'tracker_id' => $tracker, 'title' => 'Phase 1', 'sort_order' => 1, 'created_at' => now()]);
        $tasks = [];
        foreach (['done', 'todo', 'todo', 'in_progress'] as $i => $status) {
            $tasks[] = $id = (string) Str::uuid();
            DB::table('tracker_tasks')->insert(['id' => $id, 'milestone_id' => $milestone, 'title' => "Tracker task $i", 'status' => $status, 'created_at' => now()]);
        }
        $total = TrackerMatrix::MESSAGES_PAGE + 5;
        for ($i = 1; $i <= $total; $i++) {
            DB::table('tracker_messages')->insert(['id' => (string) Str::uuid(), 'tracker_id' => $tracker, 'sender_type' => 'client',
                'body' => sprintf('Message number %04d', $i), 'created_at' => now()->subMinutes($total - $i)]);
        }

        $page = Livewire::actingAs($lead, 'tracker')->test(TrackerMatrix::class, ['id' => $tracker])
            ->assertSee('Show earlier messages')
            ->assertSeeInOrder(['Message number 0006', sprintf('Message number %04d', $total)])
            ->assertDontSee('Message number 0005');

        $page->call('showEarlierMessages')->assertSee('Message number 0001')->assertDontSee('Show earlier messages');
        $this->assertSame(TrackerMatrix::MESSAGES_PAGE * 2, $page->get('messageLimit'));

        // Two of four tasks done
        $page->call('updateTaskStatus', $tasks[1], 'done');
        $this->assertEquals(50.0, (float) DB::table('trackers')->where('id', $tracker)->value('progress_percentage'));
    }
}
