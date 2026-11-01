<?php

namespace Tests\Feature\Projects;

use App\Livewire\Hq\Operations\ScrumMaster;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\MilestonePlan;
use App\Models\PlanSuggestion;
use App\Models\StatementOfWork;
use App\Models\User;
use App\Support\Ai\AiRequest;
use App\Support\Projects\Planner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Opportunities\OpportunityFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Plan milestones (PR 5b, the owner's decisions of 1 Nov 2026): on the AI service, one milestone at a time on a running
 * project, the scope assembled from the opportunity, one meeting's transcript read only when the AI asks and the person
 * may, suggestions ticked and saved through Tasks, nothing removed, no milestone added, the same rights as the screens.
 * FakeAi answers and no request reaches Gemini or Anthropic.
 */
class PlannerTest extends TestCase
{
    use OpportunityFixtures, ProjectFixtures, UsesThrowawayDatabase;

    private const EMPTY = ['read_transcript' => ['meeting_number' => 0, 'why' => ''], 'new_tasks' => [], 'edits' => [], 'notes' => []];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Http::preventStrayRequests(); // no Gemini, ever
    }

    /**
     * MyCity's shape (12 milestones, 148 tasks) with its opportunity: an imported signed SOW, two client meetings with
     * notes and transcripts, an approved change report. Returns [project, phases, opportunity, meetings].
     */
    private function myCity(?User $lead = null): array
    {
        [$project, $phases] = $this->myCityShape(['lead_user_id' => $lead?->id]);
        $org = $this->organisation(['name' => 'MyCity App Ltd (test)']);
        DB::table('projects')->where('id', $project)->update(['organization_id' => $org]);
        $o = $this->opportunityFor($org, ['title' => 'MyCity App ERP', 'stage' => 'won', 'project_id' => $project, 'lead_user_id' => $lead?->id]);
        StatementOfWork::create(['opportunity_id' => $o->id, 'reference' => 'SOW/JBSP/MCA-ERP/2026-01', 'version' => 1, 'status' => 'signed', 'signed_at' => '2026-10-02',
            'sections' => [['key' => 'scope', 'title' => 'Scope', 'body' => 'An HR ERP: recruitment, offers, onboarding, payroll.']]]);
        $meetings = [];
        foreach ([['Discovery', 'Need payroll by grade.', 'Ada (10:01): secret one, payroll first.'], ['M4 handover', 'Offer letters need the grade.', 'Ada (10:05): secret two, the offer letter needs the grade and the start date.']] as $i => [$title, $summary, $text]) {
            $m = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'client', 'title' => $title, 'starts_at' => '2026-10-0' . ($i + 5) . ' 09:00:00',
                'ends_at' => '2026-10-0' . ($i + 5) . ' 10:00:00', 'status' => 'held', 'transcript_status' => 'ready']);
            MeetingTranscript::create(['meeting_id' => $m->id, 'source' => 'meet_api', 'text' => $text, 'collected_at' => now(),
                'notes' => ['summary' => $summary, 'decisions' => [], 'needs' => [], 'risks' => [], 'open_questions' => [], 'next_steps' => []]]);
            $meetings[] = $m;
        }
        DB::table('change_reports')->insert(['id' => (string) Str::uuid(), 'project_id' => $project, 'number' => 2, 'title' => 'Exit interviews', 'description' => 'Add exit interviews.',
            'price' => 100000, 'currency' => 'NGN', 'timeline_days' => 5, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);

        return [$project, $phases, $o, $meetings];
    }

    /** The T-number the AI sees for a task title */
    private function number(string $project, string $phase, string $title): int
    {
        [, $ids] = Planner::adopted($project, $phase);

        return array_search(DB::table('tasks')->where('title', $title)->value('id'), $ids, true);
    }

    public function test_a_milestone_of_a_mycity_like_plan_gets_suggestions_and_only_the_ticked_ones_are_saved(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $dev = $this->staff('dev', ['engineer']);
        $this->actingAs($owner);
        [$project, $phases] = $this->myCity();
        $m4 = $phases[4];
        DB::table('tasks')->where('title', 'M4 task 4')->update(['assigned_to' => $dev->id, 'estimated_hours' => 3]);
        $ai = FakeAi::install()->respond('plan_milestone', ['read_transcript' => ['meeting_number' => 0, 'why' => ''],
            'new_tasks' => [
                ['title' => 'Build the offer letter template', 'detail' => "- Grade and start date filled in\n- Approved by HR", 'estimate_hours' => 6],
                ['title' => 'M4 task 16', 'detail' => 'Already in the plan', 'estimate_hours' => 1],   // a task the plan has: dropped
            ],
            'edits' => [
                ['task_number' => $this->number($project, $m4, 'M4 task 4'), 'title' => 'Screen candidates by grade', 'detail' => '- Shortlist by grade', 'estimate_hours' => 5, 'why' => 'The title said nothing.'],
                ['task_number' => $this->number($project, $m4, 'M1 task 1'), 'title' => 'Not M4', 'detail' => 'x', 'estimate_hours' => 1, 'why' => 'Another milestone: dropped'],
            ],
            'notes' => [['task_number' => $this->number($project, $m4, 'M2 task 2'), 'note' => 'Looks like a duplicate of M2 task 14.']],
        ]);

        $page = Livewire::test(ScrumMaster::class, ['id' => $project])
            ->assertSee('Sources used: signed statement of work SOW/JBSP/MCA-ERP/2026-01')->assertSee('1 approved change report')
            ->assertSee('Suggest tasks for this milestone')->assertDontSee('Draft the plan')
            ->set('phaseId', $m4)->call('suggest')->assertHasNoErrors()
            ->assertSee('Suggested edit')->assertSee('Screen candidates by grade')->assertSee('Build the offer letter template')
            ->assertSee('Looks like a duplicate of M2 task 14.')->assertSee('2 suggestions')->assertSee('Expand all');

        $plan = MilestonePlan::sole();
        $this->assertSame(['ready', 1, $m4], [$plan->status, $plan->pass, $plan->phase_id]);
        $this->assertSame(['new', 'edit', 'note'], PlanSuggestion::orderBy('position')->pluck('kind')->all(), 'The duplicate and the other milestone’s edit are dropped');
        // CONTEXT: the SOW first, the notes (never a transcript), the change report; MATERIAL: the adopted plan and the meetings by number
        $ai->assertRan('plan_milestone', function (AiRequest $r) {
            $system = implode("\n", $r->system);

            return strpos($system, 'STATEMENT OF WORK') < strpos($system, 'Need payroll by grade.') && str_contains($system, 'CR-02 Exit interviews')
                && ! str_contains($system . $r->prompt, 'secret') && str_contains($r->prompt, '— TO PLAN') && str_contains($r->prompt, 'M4 task 4')
                && str_contains($r->prompt, '1. Discovery') && str_contains($r->prompt, '2. M4 handover');
        });

        // Only the edit is ticked: it is written, the new task is set aside, the note stays a note
        $edit = PlanSuggestion::where('kind', 'edit')->sole();
        $page->set('ticked', [$edit->id])->call('save')->assertHasNoErrors()->assertSee('Saved 1 suggestion.');
        $task = DB::table('tasks')->where('assigned_to', $dev->id)->first();
        $this->assertSame(['Screen candidates by grade', '- Shortlist by grade', '5.00'], [$task->title, $task->description, (string) $task->estimated_hours]);
        $this->assertSame([148, 12], [DB::table('tasks')->count(), DB::table('project_phases')->where('project_id', $project)->count()], 'Nothing removed, no milestone added');
        $this->assertSame(['accepted', 'rejected'], [$edit->fresh()->status, PlanSuggestion::where('kind', 'new')->sole()->status]);
        $this->assertSame('saved', $plan->fresh()->status);
        $this->assertTrue(DB::table('system_audit_logs')->where('action', 'PLAN_SAVED')->where('target_id', $project)->exists());
        $this->assertSame(['The plan for “MyCity App ERP” changed 1 of your tasks.'], DB::table('notifications')->where('user_id', $dev->id)->pluck('message')->all(), 'One summary bell');

        // A second request on the same milestone: the new task, ticked this time, joins M4
        FakeAi::install()->respond('plan_milestone', ['new_tasks' => [['title' => 'Build the offer letter template', 'detail' => '- Grade filled in', 'estimate_hours' => 6]]] + self::EMPTY);
        $page->set('phaseId', $m4)->call('suggest');
        $new = PlanSuggestion::where('kind', 'new')->where('status', 'pending')->sole();
        $page->set('ticked', [$new->id])->call('save')->assertHasNoErrors();
        $created = DB::table('tasks')->where('title', 'Build the offer letter template')->first();
        $this->assertSame([$m4, '6.00', null], [$created->phase_id, (string) $created->estimated_hours, $created->assigned_to], 'No suggested lead');
        $this->assertSame(149, DB::table('tasks')->count());
    }

    public function test_the_ai_reads_one_meetings_transcript_only_when_it_asks_and_the_person_may_read_it(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        [$project, $phases, , $meetings] = $this->myCity($owner);
        $ai = FakeAi::install()
            ->respond('plan_milestone', ['read_transcript' => ['meeting_number' => 2, 'why' => 'Which fields the offer letter needs.']] + self::EMPTY)
            ->respond('plan_milestone', ['new_tasks' => [['title' => 'Add the start date to offer letters', 'detail' => '- On every letter', 'estimate_hours' => 2]],
                'read_transcript' => ['meeting_number' => 1, 'why' => 'Asked again: ignored on the second pass']] + self::EMPTY);

        Livewire::test(ScrumMaster::class, ['id' => $project])->set('phaseId', $phases[4])->call('suggest')->assertSee('Add the start date to offer letters');

        $requests = $ai->requestsFor('plan_milestone');
        $this->assertCount(2, $requests, 'Two passes, never a third');
        $this->assertStringNotContainsString('secret', $requests[0]->prompt . implode('', $requests[0]->system), 'No transcript up front');
        $this->assertStringContainsString('secret two', $requests[1]->prompt, 'The one transcript asked for');
        $this->assertStringNotContainsString('secret one', $requests[1]->prompt . implode('', $requests[1]->system), 'Only that one');
        $plan = MilestonePlan::sole();
        $this->assertSame([2, $meetings[1]->id, 'Which fields the offer letter needs.', 'ready'], [$plan->pass, $plan->transcript_meeting_id, $plan->transcript_reason, $plan->status]);
        $this->assertSame(['Add the start date to offer letters'], PlanSuggestion::pluck('title')->all());
    }

    public function test_someone_who_may_not_read_transcripts_never_gets_one_read_and_sees_only_what_the_project_shows(): void
    {
        $lead = $this->staff('lead', ['engineer']); // the project's lead, without opportunity rights
        $this->actingAs($lead);
        [$project, $phases] = $this->myCity($lead);
        DB::table('opportunities')->update(['lead_user_id' => null]);
        $ai = FakeAi::install()->respond('plan_milestone', ['read_transcript' => ['meeting_number' => 2, 'why' => 'x'],
            'new_tasks' => [['title' => 'Train the HR team', 'detail' => '- Two sessions', 'estimate_hours' => 4]]] + self::EMPTY);

        $page = Livewire::test(ScrumMaster::class, ['id' => $project]);
        $this->assertStringNotContainsString('Need payroll by grade.', $page->get('scope'), 'Meeting notes need the opportunity');
        $this->assertStringContainsString('STATEMENT OF WORK', $page->get('scope'));
        $page->set('phaseId', $phases[4])->call('suggest')->assertSee('Train the HR team');
        $this->assertCount(1, $ai->requestsFor('plan_milestone'));
        $this->assertStringNotContainsString('MEETINGS', $ai->requestsFor('plan_milestone')[0]->prompt);
        $this->assertSame([1, null], [MilestonePlan::sole()->pass, MilestonePlan::sole()->transcript_meeting_id]);
    }

    public function test_planning_needs_the_project_lead_or_project_rights_checked_on_every_request(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $dev = $this->staff('dev', ['engineer']);
        $sales = $this->staff('sales', ['sales']);
        [$project, $phases] = $this->myCity($owner);
        DB::table('tasks')->where('phase_id', $phases[4])->limit(1)->update(['assigned_to' => $dev->id]); // on the project's work, not its lead

        // The project page offers Plan milestones only to whoever may plan
        $this->actingAs($owner)->get(route('hq.ops.project', $project))->assertOk()->assertSee(route('hq.ops.scrum', $project), false);
        $this->actingAs($dev)->get(route('hq.ops.project', $project))->assertOk()->assertDontSee(route('hq.ops.scrum', $project), false);
        Livewire::test(ScrumMaster::class, ['id' => $project])->assertForbidden();
        $this->actingAs($sales);
        Livewire::test(ScrumMaster::class, ['id' => $project])->assertForbidden();

        // The lead plans; when they stop being the lead, the next request is refused
        $this->actingAs($dev);
        DB::table('projects')->where('id', $project)->update(['lead_user_id' => $dev->id]);
        $page = Livewire::test(ScrumMaster::class, ['id' => $project])->assertOk();
        DB::table('projects')->where('id', $project)->update(['lead_user_id' => $owner->id]);
        $page->call('suggest')->assertForbidden();
        $this->assertSame(0, MilestonePlan::count());
    }

    public function test_a_page_that_is_behind_saves_nothing(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        [$project, $phases] = $this->myCity($owner);
        FakeAi::install()->respond('plan_milestone', ['new_tasks' => [['title' => 'Train the HR team', 'detail' => '', 'estimate_hours' => 4], ['title' => 'Write the guide', 'detail' => '', 'estimate_hours' => 3]]] + self::EMPTY);
        $first = Livewire::test(ScrumMaster::class, ['id' => $project])->set('phaseId', $phases[4])->call('suggest');
        $second = Livewire::test(ScrumMaster::class, ['id' => $project]);
        $third = Livewire::test(ScrumMaster::class, ['id' => $project]);
        [$a, $b] = PlanSuggestion::orderBy('position')->pluck('id')->all();

        $first->set('ticked', [$a])->call('save')->assertHasNoErrors();
        // Saved in another tab: the next round trip says so, and saving writes nothing
        $second->set('ticked', [$b])->assertHasErrors('save')->assertSee(__('planner.errors.gone'))->call('save');
        $third->call('save')->assertHasErrors('save')->assertSee(__('planner.errors.gone'));
        $this->assertSame(['accepted', 'rejected'], [PlanSuggestion::find($a)->status, PlanSuggestion::find($b)->status]);
        $this->assertSame(0, DB::table('tasks')->where('title', 'Write the guide')->count());
        // An old revision is refused even before the plan is saved
        $plan = MilestonePlan::sole();
        $plan->forceFill(['status' => 'ready'])->save();
        $this->assertSame('stale', Planner::save($plan, [$b], $owner, $plan->revision - 1));
    }

    public function test_a_project_without_milestones_or_opportunity_gets_a_whole_plan_on_the_ai_service_once(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $project = $this->project(['title' => 'Internal tool']);
        $ai = FakeAi::install()->respond('plan_project', ['milestones' => [
            ['title' => 'Discovery', 'goal' => 'Agreed needs', 'tasks' => [['title' => 'Interview users', 'detail' => '- Five interviews', 'estimate_hours' => 8], ['title' => 'Write it up', 'detail' => '', 'estimate_hours' => 2]]],
            ['title' => 'Build', 'goal' => 'The tool', 'tasks' => [['title' => 'Build the forms', 'detail' => '', 'estimate_hours' => 16]]],
        ]]);

        $page = Livewire::test(ScrumMaster::class, ['id' => $project])->assertSee('No opportunity holds this project’s scope')->assertDontSee('Suggest tasks for this milestone')
            ->set('scope', str_repeat('An internal tool for the finance team to record cash requests. ', 2))->call('draftPlan')
            ->assertSee('Interview users')->call('removeDrafted', 0, 1)->assertDontSee('Write it up');
        $ai->assertRan('plan_project', fn (AiRequest $r) => str_contains(implode("\n", $r->system), 'cash requests'));

        $page->call('saveWhole')->assertHasNoErrors()->assertRedirect(route('hq.ops.project', ['project' => $project, 'tab' => 'milestones']));
        $this->assertSame(['Discovery', 'Build'], DB::table('project_phases')->where('project_id', $project)->orderBy('phase_number')->pluck('title')->all());
        $this->assertSame(['Build the forms', 'Interview users'], DB::table('tasks')->orderBy('title')->pluck('title')->all());
        // Saved once; the project now has milestones, so a whole plan is refused and it is planned a milestone at a time
        $this->assertSame('state', Planner::saveWhole(MilestonePlan::sole(), $owner, MilestonePlan::sole()->revision));
        $this->assertSame('milestone', Planner::mode($project));
        Livewire::test(ScrumMaster::class, ['id' => $project])->set('scope', str_repeat('An internal tool for the finance team to record cash requests. ', 2))
            ->call('draftPlan')->assertHasErrors('plan');
        $this->assertSame(2, DB::table('project_phases')->where('project_id', $project)->count());
    }

    public function test_english_and_french_have_the_same_strings(): void
    {
        $en = array_keys(Arr::dot(require lang_path('en/planner.php')));
        $fr = array_keys(Arr::dot(require lang_path('fr/planner.php')));
        sort($en);
        sort($fr);
        $this->assertSame($en, $fr);
    }
}
