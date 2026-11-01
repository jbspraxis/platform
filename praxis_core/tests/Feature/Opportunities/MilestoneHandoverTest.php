<?php

namespace Tests\Feature\Opportunities;

use App\Livewire\Hq\Operations\QaHub;
use App\Livewire\Hq\Operations\SprintBoard;
use App\Livewire\Hq\Opportunities\MeetingsTab;
use App\Mail\MeetingInvitationMail;
use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MeetingPoint;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\Ai\AiRequest;
use App\Support\Google\MeetingRequest;
use App\Support\Opportunities\Handovers;
use App\Support\Opportunities\Meetings;
use App\Support\Opportunities\Transcripts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\Support\Google\FakeGoogle;
use Tests\TestCase;

/**
 * Milestone handovers (docs/OPPORTUNITIES.md section 12, PR 6): the handover meeting of a milestone scheduled from the
 * sprint board through Meetings::schedule (meetings.phase_id), the AI's points from its transcript (handover_points,
 * through FakeAi) linked to the milestone's tasks, the lead's review that turns each confirmed point into a QA ticket
 * (once, under a lock), the ticket's status back on the meeting and in the sign-off text, permissions (the
 * opportunity's team included) and limits checked on the server, French.
 */
class MilestoneHandoverTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private const POINTS = ['points' => [
        ['title' => 'Stock report export stops at 5,000 rows', 'asked' => 'Exporting the stock report to Excel stops after about 5,000 rows; the client needs every row.',
            'raised_by' => 'Ada Obi', 'severity' => 'Critical', 'task_number' => 1],
        ['title' => 'Dashboard totals in naira', 'asked' => 'Show the sales dashboard totals in naira, not dollars.', 'raised_by' => 'Musa Bello', 'severity' => 'Medium', 'task_number' => 2],
        ['title' => 'Call the audit trail “History”', 'asked' => 'Rename the screen.', 'raised_by' => '', 'severity' => 'Low', 'task_number' => 99],
        ['title' => '   ', 'asked' => 'An empty title is dropped', 'raised_by' => '', 'severity' => 'Low', 'task_number' => 0],
    ]];

    private const NOTES = ['summary' => 'M3 handed over.', 'decisions' => [], 'needs' => [], 'risks' => [], 'open_questions' => [], 'next_steps' => []];

    private const MINUTES = ['subject' => 'Handover', 'summary' => 'We handed over M3.', 'decisions' => [], 'next_steps' => []];

    private FakeGoogle $google;

    private FakeAi $ai;

    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 5 November 2026, 13:00 in Lagos
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
        $this->google = FakeGoogle::install();
        $this->ai = FakeAi::install()->respond('meeting_notes', self::NOTES)->respond('client_minutes', self::MINUTES)->respond('handover_points', self::POINTS, 9000, 400);
    }

    public function test_the_lead_schedules_a_milestones_handover_from_the_sprint_board(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $s = $this->handover(lead: $owner);
        $this->actingAs($owner);

        // The sprint holds the M3 milestone's tasks: the board offers M3's handover
        $link = route('hq.crm.opportunity', ['opportunity' => $s['o']->id, 'tab' => 'meetings', 'handover' => $s['milestone']]);
        Livewire::test(SprintBoard::class, ['id' => $s['project']])
            ->assertSee('Milestone handover')->assertSee('M3 Reports')->assertSee('No handover meeting for this milestone yet.')
            ->assertSee('Schedule handover')->assertSeeHtml(e($link))->assertDontSee('Sprint handover');

        // The link opens the Meetings tab's form for that milestone: a client meeting, titled and set out from it
        $form = Livewire::withQueryParams(['handover' => $s['milestone']])->test(MeetingsTab::class, ['opportunityId' => $s['o']->id])
            ->assertSet('formOpen', true)->assertSet('milestoneId', $s['milestone'])->assertSet('kind', 'client')->assertSet('handover', '')
            ->assertSet('title', 'Milestone handover: M3 Reports')->assertSet('contacts', [$s['contact']])
            ->assertSee('Schedule the milestone handover')->assertSee('This meeting hands over the milestone M3 Reports.')->assertDontSeeHtml('wire:model.live="kind"');
        $this->assertStringContainsString("Handing over the milestone M3 Reports.\n\nWhat this milestone delivered:\n- Stock report export\n- Sales dashboard\n- Audit trail", $form->get('agenda'));

        // The kind can't be switched to internal, nor the milestone swapped from the browser
        $form->set('kind', 'internal')->assertSet('kind', 'client');
        try {
            $form->set('milestoneId', (string) Str::uuid());
            $this->fail('milestoneId should be locked');
        } catch (CannotUpdateLockedPropertyException) {
            // expected
        }

        $form->set('date', '2026-11-09')->set('time', '10:00')->call('save')->assertHasNoErrors()->assertSee('Meeting scheduled');

        $m = Meeting::sole();
        $this->assertSame([$s['milestone'], 'client', 'scheduled', $s['o']->id], [$m->phase_id, $m->kind, $m->status, $m->opportunity_id]);
        $this->assertSame('https://meet.google.com/abc-aaaa-xyz', $m->meet_link);
        $this->google->assertMeetingCreated(fn (MeetingRequest $r) => $r->title === 'Milestone handover: M3 Reports' && str_contains((string) $r->description, '- Sales dashboard'));
        Mail::assertSent(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo('ada@mycity.test') && $mail->variant === 'invite');

        // Back on the board: the meeting, a link to it, and no second "Schedule" until it has been held
        Livewire::test(SprintBoard::class, ['id' => $s['project']])
            ->assertSee('Milestone handover: M3 Reports')->assertSee('9 November 2026, 10:00 WAT')->assertSee('Open the meeting')
            ->assertSeeHtml(e(route('hq.crm.opportunity', ['opportunity' => $s['o']->id, 'tab' => 'meetings', 'meeting' => $m->id])))
            ->assertDontSee('Schedule handover')->assertDontSee('Schedule another');
    }

    public function test_a_sprint_holding_two_milestones_offers_a_handover_for_each_and_the_points_follow_the_milestone(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $s = $this->handover(lead: $owner);
        $this->actingAs($owner);
        // M4 starts in the same sprint; M3 also has a task left out of any sprint
        $m4 = $this->milestone($s['project'], 4, 'M4 Payroll');
        $this->task($m4, $s['sprint'], 'Payroll run', 0);
        $late = $this->task($s['milestone'], null, 'Year-end report', 20);

        $board = Livewire::test(SprintBoard::class, ['id' => $s['project']])->assertSee('Milestone handovers')->assertSeeInOrder(['M3 Reports', 'M4 Payroll']);
        foreach ([$s['milestone'], $m4] as $milestone) {
            $board->assertSeeHtml(e(route('hq.crm.opportunity', ['opportunity' => $s['o']->id, 'tab' => 'meetings', 'handover' => $milestone])));
        }

        // M3's handover: the AI numbers M3's tasks (all of them, whichever sprint), never M4's
        $m = $this->heldHandover($s);
        Transcripts::fromText($m, 'Ada Obi (10:02): The stock export stops at 5,000 rows.', 'paste');
        $this->ai->assertRan('handover_points', fn (AiRequest $r) => str_contains($r->prompt, "Milestone: M3 Reports\n")
            && str_contains($r->prompt, "Milestone tasks:\n1. Stock report export\n2. Sales dashboard\n3. Audit trail\n4. Year-end report\n") && ! str_contains($r->prompt, 'Payroll run'));

        // The task picker offers the milestone's tasks
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->assertSeeHtml('value="' . $late . '"')->assertSee('Milestone task')->assertDontSee('Payroll run');
    }

    public function test_without_an_opportunity_the_right_milestone_or_the_permission_nothing_is_scheduled(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $s = $this->handover(lead: $owner);
        $this->actingAs($owner);

        // A project no opportunity holds says why
        $orphan = $this->project($this->organisation(), 'Orphan project');
        $orphanSprint = $this->sprint($orphan, 'Sprint 1');
        $elsewhere = $this->milestone($orphan, 1, 'M1 Elsewhere');
        $this->task($elsewhere, $orphanSprint, 'Their task', 0);
        Livewire::test(SprintBoard::class, ['id' => $orphan])
            ->assertSee('This project has no opportunity, so its milestone handovers can’t be scheduled here')->assertDontSee('Schedule handover');

        // A sprint that holds no task yet has nothing to hand over
        $empty = $this->sprint($s['project'], 'Sprint 9', 'Planned');
        Livewire::test(SprintBoard::class, ['id' => $s['project']])->set('activeSprintId', $empty)
            ->assertSee('This sprint holds no milestone’s tasks yet')->assertDontSee('Schedule handover');

        // Another project's milestone
        Livewire::withQueryParams(['handover' => $elsewhere])->test(MeetingsTab::class, ['opportunityId' => $s['o']->id])
            ->assertSet('formOpen', false)->assertSee('That milestone isn’t on this opportunity’s project.');

        // The milestone goes between opening the form and saving it: nothing reaches Google
        $form = Livewire::withQueryParams(['handover' => $s['milestone']])->test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->assertSet('formOpen', true);
        DB::table('project_phases')->where('id', $s['milestone'])->update(['deleted_at' => now()]);
        $form->set('time', '10:00')->call('save')->assertHasErrors('milestone');
        $this->assertSame(0, Meeting::count());
        $this->assertSame([], $this->google->created);
        DB::table('project_phases')->where('id', $s['milestone'])->update(['deleted_at' => null]);

        // A delivery lead sees the board and the opportunity but isn't on its team and doesn't manage opportunities
        $delivery = $this->staff('delivery', ['delivery']);
        $this->actingAs($delivery);
        Livewire::test(SprintBoard::class, ['id' => $s['project']])
            ->assertSee('Someone on the opportunity’s team, or who manages opportunities, schedules it.')->assertDontSee('Schedule handover');
        Livewire::withQueryParams(['handover' => $s['milestone']])->test(MeetingsTab::class, ['opportunityId' => $s['o']->id])
            ->assertSet('formOpen', false)->assertSee('You can’t schedule meetings for this opportunity.');
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('openHandover', $s['milestone'])->assertForbidden();
    }

    public function test_once_the_transcript_is_filed_the_ai_lists_the_points_with_their_tasks_and_nothing_is_created(): void
    {
        $s = $this->handover();
        $m = $this->heldHandover($s);
        $plain = Meetings::schedule($s['o'], ['kind' => 'client', 'title' => 'Weekly call', 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2),
            'staff' => [$s['lead']->id], 'contacts' => [$s['contact']]], brandedEmail: false)->meeting;

        Transcripts::fromText($m, "Ada Obi (10:02): The stock export stops at 5,000 rows.\nMusa Bello (10:05): Totals should be in naira.", 'paste');
        Transcripts::fromText($plain, 'Ada Obi (09:00): All good this week.', 'paste');

        // One Haiku run, for the handover only: the milestone's tasks numbered, then the transcript
        $this->assertCount(1, $this->ai->requestsFor('handover_points'));
        $this->ai->assertRan('handover_points', fn (AiRequest $r) => $r->model === 'claude-haiku-5-5'
            && str_contains($r->prompt, "Milestone tasks:\n1. Stock report export\n2. Sales dashboard\n3. Audit trail")
            && str_contains($r->prompt, 'Ada Obi (10:02): The stock export stops at 5,000 rows.') && ! str_contains($r->prompt, 'INSTRUCTIONS FROM THE LEAD'));

        $points = $m->points()->get();
        $this->assertSame(['Stock report export stops at 5,000 rows', 'Dashboard totals in naira', 'Call the audit trail “History”'], $points->pluck('title')->all());
        $this->assertSame([$s['tasks'][0], $s['tasks'][1], null], $points->pluck('task_id')->all());   // 99 is no task
        $this->assertSame(['Critical', 'Medium', 'Low'], $points->pluck('severity')->all());
        $this->assertSame(['Ada Obi', 'Musa Bello', null], $points->pluck('raised_by')->all());
        $this->assertSame(['draft'], $points->pluck('status')->unique()->values()->all());
        $this->assertSame(0, DB::table('qa_tickets')->count());   // nothing until the lead confirms
        $this->assertSame(0, $plain->points()->count());

        // Everyone who sees the meeting sees the points; only the lead (or the owner) can act on them
        $this->actingAs($this->staff('finance', ['finance']));
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->assertSee('3 points to review')->assertSee('Stock report export stops at 5,000 rows')->assertSee('Waiting for the lead to confirm.')
            ->assertDontSee('Create the issue');
    }

    public function test_the_lead_confirms_points_as_qa_tickets_once_and_the_meeting_shows_how_each_was_closed(): void
    {
        $s = $this->handover();
        $engineer = $this->staff('engineer', ['engineer']);
        DB::table('tasks')->where('id', $s['tasks'][0])->update(['assigned_to' => $engineer->id]);
        $m = $this->heldHandover($s);
        Transcripts::fromText($m, 'Ada Obi (10:02): The stock export stops at 5,000 rows.', 'paste');
        [$export, $naira, $rename] = $m->points()->get()->all();
        $this->actingAs($s['lead']);

        $tab = Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->assertSee('Points raised')->assertSee('Create all 3 issues')->assertSet("points.{$export->id}.title", 'Stock report export stops at 5,000 rows');

        // Edits are saved as the lead leaves each field, within limits; a value that can't be saved is never saved
        $tab->set("points.{$export->id}.severity", 'Medium')->assertHasNoErrors()
            ->set("points.{$export->id}.title", str_repeat('x', 201))->assertHasErrors("points.{$export->id}.title")
            ->set("points.{$export->id}.severity", 'Urgent')->assertHasErrors("points.{$export->id}.severity")
            ->set("points.{$export->id}.task_id", $this->otherProjectTask())->assertHasErrors("points.{$export->id}.task_id")
            ->set("points.{$export->id}.raised_by", ['Ada'])->assertHasErrors("points.{$export->id}.raised_by");
        $this->assertSame(['Stock report export stops at 5,000 rows', 'Medium', $s['tasks'][0], 'Ada Obi'],
            [$export->fresh()->title, $export->fresh()->severity, $export->fresh()->task_id, $export->fresh()->raised_by]);

        // While anything on screen is wrong, the issue isn't created
        $tab->call('confirmPoint', $export->id)->assertHasErrors(["points.{$export->id}.title", "points.{$export->id}.severity", "points.{$export->id}.task_id"]);
        $this->assertSame(0, DB::table('qa_tickets')->count());
        $tab->set("points.{$export->id}.title", 'Stock export stops at 5,000 rows')->set("points.{$export->id}.severity", 'Medium')
            ->set("points.{$export->id}.task_id", $s['tasks'][0])->set("points.{$export->id}.raised_by", 'Ada Obi')->assertHasNoErrors();

        // Create the issue: one ticket on the project, linked to the task
        $tab->call('confirmPoint', $export->id)->assertHasNoErrors()->assertSee('Issue created: “Stock export stops at 5,000 rows”.');
        $export->refresh();
        $ticket = DB::table('qa_tickets')->where('id', $export->qa_ticket_id)->first();
        $this->assertSame('confirmed', $export->status);
        $this->assertSame([$s['project'], $s['tasks'][0], 'Stock export stops at 5,000 rows', 'Medium', 'Open', $s['lead']->id, 'staff'],
            [$ticket->project_id, $ticket->task_id, $ticket->title, $ticket->severity, $ticket->status, $ticket->submitted_by, $ticket->submitted_by_type]);
        $this->assertSame("Exporting the stock report to Excel stops after about 5,000 rows; the client needs every row.\n\nRaised by Ada Obi in the milestone handover “Milestone handover: M3 Reports” on 5 November 2026.", $ticket->description);
        $this->assertDatabaseHas('notifications', ['user_id' => $engineer->id, 'title' => 'Milestone handover point on your task', 'link_url' => route('hq.ops.qa', ['ticket' => $ticket->id])]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'HANDOVER_POINT_CONFIRMED', 'target_id' => $s['o']->id, 'user_id' => $s['lead']->id]);

        // A second click, or a second person, creates nothing more
        $tab->call('confirmPoint', $export->id)->assertSee('This point was already confirmed or left out.');
        $this->assertNull(Handovers::confirm($export));
        $this->assertSame(1, DB::table('qa_tickets')->count());

        // Leave one out, bring it back, leave it out; then create the rest as shown (the task changed on screen)
        $tab->call('dismissPoint', $rename->id)->assertSee('left out: no issue created')
            ->call('restorePoint', $rename->id)->call('dismissPoint', $rename->id)
            ->set("points.{$naira->id}.task_id", $s['tasks'][0])
            ->call('confirmAll', $m->id)->assertSee('1 issue created.')->assertSee('Left out (1)')->assertDontSee('Create the issue');
        $this->assertSame(['confirmed', 'dismissed'], [$naira->fresh()->status, $rename->fresh()->status]);
        $this->assertNull($rename->fresh()->qa_ticket_id);
        $this->assertSame(2, DB::table('qa_tickets')->where('task_id', $s['tasks'][0])->count());

        // The issues are in Quality and issues and on the sprint board's task
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);
        $this->assertEqualsCanonicalizing([$export->qa_ticket_id, $naira->fresh()->qa_ticket_id], collect(Livewire::test(QaHub::class)->get('tickets'))->pluck('id')->all());
        $board = Livewire::test(SprintBoard::class, ['id' => $s['project']])->assertSee('2 open issues')->assertSee('No points to review')->assertSee('2 issues (2 open)');
        $this->assertSame(2, collect($board->get('tasks')['To-Do'])->firstWhere('id', $s['tasks'][0])['qa_ticket_count']);

        // Fixed and replied to: the meeting and the sign-off text say how each point was closed
        DB::table('qa_tickets')->where('id', $export->qa_ticket_id)->update(['status' => 'Resolved', 'resolved_at' => '2026-11-06 09:00:00', 'resolved_by' => $owner->id]);
        $this->insertRow('qa_comments', ['id' => (string) Str::uuid(), 'ticket_id' => $export->qa_ticket_id, 'user_id' => $owner->id, 'author_type' => 'staff',
            'comment_text' => 'The export now pages through every row.', 'is_internal_only' => 0, 'created_at' => now()->subMinute()]);
        $this->insertRow('qa_comments', ['id' => (string) Str::uuid(), 'ticket_id' => $export->qa_ticket_id, 'user_id' => $owner->id, 'author_type' => 'staff',
            'comment_text' => 'Internal: check the memory limit.', 'is_internal_only' => 1, 'created_at' => now()]);
        $this->actingAs($s['lead']);
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->assertSee('Resolved on 6 November 2026')->assertSee('Issue: Open')->assertSee('Last reply: The export now pages through every row.')
            ->assertDontSee('Internal: check the memory limit.')->assertSee('Copy for the sign-off');
        $this->assertSame(implode("\n", [
            'Points raised in “Milestone handover: M3 Reports” (5 November 2026)',
            '1. Stock export stops at 5,000 rows (Medium): Resolved on 6 November 2026. Last reply: “The export now pages through every row.”',
            '2. Dashboard totals in naira (Medium): Open.',
        ]), Handovers::signoffText($m->fresh()));
    }

    public function test_reviewing_points_and_drafting_them_check_permissions_and_limits_on_the_server(): void
    {
        $s = $this->handover();
        $m = $this->heldHandover($s);
        $t = Transcripts::fromText($m, 'Ada Obi (10:02): The stock export stops at 5,000 rows.', 'paste');
        $point = $m->points()->first();

        // A colleague who manages opportunities but isn't this one's lead (nor the owner) can't create issues
        $colleague = $this->staff('colleague', ['sales']);
        $this->actingAs($colleague);
        $tab = Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)->assertDontSee('Create the issue');
        $tab->call('confirmPoint', $point->id)->assertForbidden();
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('confirmAll', $m->id)->assertForbidden();
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('dismissPoint', $point->id)->assertForbidden();
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->set("points.{$point->id}.title", 'Changed')->assertForbidden();
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('openAddPoint', $m->id)->assertForbidden();
        $this->assertSame(['draft', 'Stock report export stops at 5,000 rows'], [$point->fresh()->status, $point->fresh()->title]);
        $this->assertSame(0, DB::table('qa_tickets')->count());

        // Listing the points again reads the transcript: it needs the right to read it
        DB::table('role_permissions')->where('role_id', DB::table('roles')->where('audience', 'staff')->where('slug', 'sales')->value('id'))
            ->whereIn('permission_id', DB::table('permissions')->where('module', 'opportunities')->where('action', 'transcripts')->pluck('id'))->delete();
        $this->actingAs($this->staff('reader', ['sales']));
        $runs = AiRun::count();
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('draftPoints', $t->id)->assertForbidden();
        $this->assertSame($runs, AiRun::count());

        // Another opportunity's point can't be reached through this one
        $this->actingAs($s['lead']);
        $other = $this->handover();
        $otherMeeting = $this->heldHandover($other);
        Transcripts::fromText($otherMeeting, 'Ada Obi (10:02): Another point.', 'paste');
        $foreign = $otherMeeting->points()->first();
        try {
            Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('confirmPoint', $foreign->id);
            $this->fail('Another opportunity’s point should not be found');
        } catch (ModelNotFoundException) {
            // expected: a 404
        }
        $this->assertSame('draft', $foreign->fresh()->status);

        // Points added by hand: a title, a known severity, and no more than a meeting holds
        $tab = Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('openAddPoint', $m->id)
            ->set('newPointTitle', '')->call('addPoint')->assertHasErrors('newPointTitle')
            ->set('newPointTitle', 'Print the stock report')->set('newPointSeverity', 'Urgent')->call('addPoint')->assertHasErrors('newPointSeverity')
            ->set('newPointSeverity', 'Low')->call('addPoint')->assertHasNoErrors()->assertSet('addingPointTo', '');
        $this->assertDatabaseHas('meeting_points', ['meeting_id' => $m->id, 'title' => 'Print the stock report', 'origin' => 'lead', 'status' => 'draft', 'created_by' => $s['lead']->id]);
        while ($m->points()->count() < Handovers::MAX_POINTS) {
            Handovers::addPoint($m, ['title' => 'Filler']);
        }
        $tab->call('openAddPoint', $m->id)->set('newPointTitle', 'One too many')->call('addPoint')->assertHasErrors('newPointTitle');
        $this->assertSame(Handovers::MAX_POINTS, $m->points()->count());
    }

    public function test_the_opportunitys_team_works_on_its_handovers_whatever_their_roles(): void
    {
        // Engineers have no opportunity permission: the lead and a team member get theirs from this opportunity's team
        $lead = $this->staff('lead', ['engineer']);
        $s = $this->handover(lead: $lead);
        $member = $this->staff('member', ['engineer']);
        $outsider = $this->staff('outsider', ['engineer']);
        DB::table('opportunity_members')->insert(['id' => (string) Str::uuid(), 'opportunity_id' => $s['o']->id, 'user_id' => $member->id, 'created_at' => now()]);
        // Each may see the sprint board through a task of their own
        foreach ([$lead, $member, $outsider] as $i => $person) {
            DB::table('tasks')->where('id', $s['tasks'][$i])->update(['assigned_to' => $person->id]);
        }

        // A member schedules the handover from the board
        $this->actingAs($member);
        Livewire::test(SprintBoard::class, ['id' => $s['project']])->assertSee('Schedule handover');
        Livewire::withQueryParams(['handover' => $s['milestone']])->test(MeetingsTab::class, ['opportunityId' => $s['o']->id])
            ->assertSet('formOpen', true)->set('date', '2026-11-09')->set('time', '10:00')->call('save')->assertHasNoErrors();
        $this->assertSame($s['milestone'], Meeting::sole()->phase_id);

        // Someone off the team, without the permission, sees the board's handover but can't open or schedule it
        $this->actingAs($outsider);
        Livewire::test(SprintBoard::class, ['id' => $s['project']])->assertSee('Milestone handover: M3 Reports')
            ->assertDontSee('Open the meeting')->assertDontSee('Schedule another')->assertDontSee('Schedule handover');
        $this->get(route('hq.crm.opportunity', ['opportunity' => $s['o']->id, 'tab' => 'meetings', 'handover' => $s['milestone']]))->assertForbidden();

        // Points: the member reads them but neither lists them again (no transcript right) nor confirms them (not the lead)
        $m = $this->heldHandover($s);
        $t = Transcripts::fromText($m, 'Ada Obi (10:02): The stock export stops at 5,000 rows.', 'paste');
        $point = $m->points()->first();
        $this->actingAs($member);
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->assertSee('Stock report export stops at 5,000 rows')->assertSee('Waiting for the lead to confirm.')->assertDontSee('List the points again');
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('confirmPoint', $point->id)->assertForbidden();
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('draftPoints', $t->id)->assertForbidden();
        $this->assertSame(0, DB::table('qa_tickets')->count());

        // The lead does both
        $this->actingAs($lead);
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->call('confirmPoint', $point->id)->assertSee('Issue created')
            ->call('draftPoints', $t->id)->assertHasNoErrors();
        $this->assertSame(1, DB::table('qa_tickets')->count());
        $this->assertCount(2, $this->ai->requestsFor('handover_points'));
    }

    public function test_listing_again_replaces_only_the_points_still_to_review_and_says_what_is_recorded(): void
    {
        $s = $this->handover();
        $m = $this->heldHandover($s);
        $this->ai->respond('handover_points', ['points' => [['title' => 'Sales dashboard loads slowly', 'asked' => 'Over 10 seconds.', 'raised_by' => 'Musa Bello', 'severity' => 'Medium', 'task_number' => 2]]]);
        $t = Transcripts::fromText($m, 'Ada Obi (10:02): The stock export stops at 5,000 rows.', 'paste');
        [$export, $naira, $rename] = $m->points()->get()->all();
        $this->actingAs($s['lead']);
        Handovers::confirm($export);
        Handovers::dismiss($rename);
        $mine = Handovers::addPoint($m, ['title' => 'Print the stock report', 'severity' => 'Low']);

        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->assertSee('List the points again')->call('draftPoints', $t->id)->assertHasNoErrors();

        $last = $this->ai->requestsFor('handover_points')[1];
        $this->assertStringContainsString("INSTRUCTIONS FROM THE LEAD:\nAlready recorded (do not list them again):\n- Stock report export stops at 5,000 rows\n- Call the audit trail “History”\n- Print the stock report", $last->prompt);
        $this->assertSoftDeleted('meeting_points', ['id' => $naira->id]);
        $this->assertSame(['Stock report export stops at 5,000 rows', 'Call the audit trail “History”', 'Print the stock report', 'Sales dashboard loads slowly'],
            $m->points()->get()->pluck('title')->all());
        $this->assertSame(['confirmed', 'dismissed', 'draft', 'draft'], $m->points()->get()->pluck('status')->all());
        $this->assertSame($mine->id, $m->points()->get()[2]->id);

        // Filing the same run twice adds nothing
        $run = AiRun::where('purpose', 'handover_points')->latest('created_at')->latest('id')->first();
        Handovers::fileAiOutput($run);
        $this->assertSame(4, $m->points()->count());
    }

    public function test_when_the_ai_fails_or_has_no_key_the_lead_can_ask_again(): void
    {
        $this->ai = FakeAi::install()->respond('meeting_notes', self::NOTES)->respond('client_minutes', self::MINUTES)
            ->fail('handover_points', 'busy')->respond('handover_points', self::POINTS);
        $s = $this->handover();
        $m = $this->heldHandover($s);
        $t = Transcripts::fromText($m, 'Ada Obi (10:02): The stock export stops at 5,000 rows.', 'paste');
        $this->assertSame(0, $m->points()->count());
        $this->actingAs($s['lead']);

        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->assertSee('The AI couldn’t list the points:')->assertSee('Try again')
            ->call('draftPoints', $t->id)->assertSee('3 points to review');

        $this->ai->unconfigured();
        $quiet = $this->heldHandover($s);
        $t2 = Transcripts::fromText($quiet, 'Ada Obi (10:02): All fine.', 'paste');
        $tab = Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $quiet->id)
            ->assertSee('The points raised haven’t been listed yet.')->assertSee('List the points');
        $tab->call('draftPoints', $t2->id)->assertSee('Claude');
        $this->assertSame(0, $quiet->points()->count());
    }

    public function test_french_opportunity(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $s = $this->handover(lead: $owner, language: 'fr');
        $this->actingAs($owner);

        $form = Livewire::withQueryParams(['handover' => $s['milestone']])->test(MeetingsTab::class, ['opportunityId' => $s['o']->id])
            ->assertSet('title', "Livraison du jalon\u{00A0}: M3 Reports");
        $this->assertStringStartsWith("Livraison du jalon M3 Reports.\n\nCe que ce jalon a livré\u{00A0}:", $form->get('agenda'));

        $m = $this->heldHandover($s);
        Transcripts::fromText($m, 'Ada Obi (10:02): L’export s’arrête à 5 000 lignes.', 'paste');
        $this->ai->assertRan('handover_points', fn (AiRequest $r) => str_contains($r->prompt, 'Write in French'));
        $point = $m->points()->first();
        Handovers::confirm($point);
        $this->assertStringEndsWith("Soulevé par Ada Obi lors de la livraison du jalon «\u{202F}Milestone handover: M3 Reports\u{202F}» du 5 novembre 2026.",
            DB::table('qa_tickets')->where('id', $point->fresh()->qa_ticket_id)->value('description'));
        $this->assertSame("Points soulevés lors de «\u{202F}Milestone handover: M3 Reports\u{202F}» (5 novembre 2026)\n1. Stock report export stops at 5,000 rows (Critique)\u{00A0}: Ouverte.",
            Handovers::signoffText($m->fresh()));

        $this->app->setLocale('fr');
        Livewire::test(MeetingsTab::class, ['opportunityId' => $s['o']->id])->call('toggle', $m->id)
            ->assertSee('Points soulevés')->assertSee("Incident\u{00A0}: Ouverte")->assertSee('Copier pour la recette')->assertSee('Tâche du jalon');
        Livewire::test(SprintBoard::class, ['id' => $s['project']])->assertSee('Livraison du jalon')->assertSee('En planifier une autre');

        $en = array_keys(Arr::dot(require lang_path('en/handover.php')));
        $fr = array_keys(Arr::dot(require lang_path('fr/handover.php')));
        $this->assertSame([], array_values(array_diff($en, $fr)), 'Missing in French');
        $this->assertSame([], array_values(array_diff($fr, $en)), 'Missing in English');
    }

    // ---- Fixtures --------------------------------------------------------------------------------------------------

    /**
     * An opportunity holding a project whose milestone "M3 Reports" has three tasks (in order), all in the sprint
     * "Sprint 3" (MyCity's shape: one milestone per sprint), its lead and a client contact
     */
    private function handover(?User $lead = null, string $language = 'en'): array
    {
        $lead ??= $this->staff('lead', ['sales']);
        $org = $this->organisation(['name' => 'MyCity Test ' . Str::random(4), 'preferred_language' => strtoupper($language)]);
        $contact = $this->clientContact($org, ['first_name' => 'Ada', 'last_name' => 'Obi', 'email' => 'ada.' . Str::lower(Str::random(4)) . '@mycity.test']);
        if (! DB::table('client_users')->where('email', 'ada@mycity.test')->exists()) {
            DB::table('client_users')->where('id', $contact)->update(['email' => 'ada@mycity.test']);
        }
        $project = $this->project($org, 'MyCity App ERP');
        $o = $this->opportunityFor($org, ['lead_user_id' => $lead->id, 'language' => $language, 'stage' => 'won', 'project_id' => $project]);
        $milestone = $this->milestone($project, 3, 'M3 Reports');
        $sprint = $this->sprint($project, 'Sprint 3');
        $tasks = [];
        foreach (['Stock report export', 'Sales dashboard', 'Audit trail'] as $i => $title) {
            $tasks[] = $this->task($milestone, $sprint, $title, 10 + $i);
        }

        return ['lead' => $lead, 'o' => $o, 'project' => $project, 'milestone' => $milestone, 'sprint' => $sprint, 'tasks' => $tasks, 'contact' => $contact];
    }

    /** The handover meeting of the fixture's milestone, held from 10:00 to 11:00 UTC today */
    private function heldHandover(array $s): Meeting
    {
        $starts = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
        $m = Meetings::schedule($s['o'], ['kind' => 'client', 'title' => 'Milestone handover: M3 Reports', 'starts_at' => $starts, 'ends_at' => $starts->addHour(),
            'staff' => [$s['lead']->id], 'contacts' => [$s['contact']], 'phase_id' => $s['milestone']], brandedEmail: false)->meeting;
        $this->google->created = [];

        return $m;
    }

    private function project(string $organisationId, string $title): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('projects', ['id' => $id, 'organization_id' => $organisationId, 'title' => $title, 'status' => 'Active']);

        return $id;
    }

    /** A milestone (project_phases row) */
    private function milestone(string $projectId, int $number, string $title): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('project_phases', ['id' => $id, 'project_id' => $projectId, 'phase_number' => $number, 'title' => $title, 'weight_percentage' => 20]);

        return $id;
    }

    private function sprint(string $projectId, string $title, string $status = 'Active'): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('sprints', ['id' => $id, 'project_id' => $projectId, 'title' => $title, 'start_date' => '2026-10-20', 'end_date' => '2026-11-02', 'status' => $status]);

        return $id;
    }

    /** A task of a milestone, in a sprint or none; $order sets which comes first (created_at) */
    private function task(string $milestoneId, ?string $sprintId, string $title, int $order): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('tasks', ['id' => $id, 'phase_id' => $milestoneId, 'sprint_id' => $sprintId, 'title' => $title, 'status' => 'To-Do', 'created_at' => now()->subHour()->addMinutes($order)]);

        return $id;
    }

    private function otherProjectTask(): string
    {
        $project = $this->project($this->organisation(), 'Another project');
        $phase = (string) Str::uuid();
        $this->insertRow('project_phases', ['id' => $phase, 'project_id' => $project, 'phase_number' => 1, 'title' => 'Build', 'weight_percentage' => 100]);
        $task = (string) Str::uuid();
        $this->insertRow('tasks', ['id' => $task, 'phase_id' => $phase, 'title' => 'Someone else’s task', 'status' => 'To-Do']);

        return $task;
    }
}
