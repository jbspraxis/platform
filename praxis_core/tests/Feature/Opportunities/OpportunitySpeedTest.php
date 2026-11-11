<?php

namespace Tests\Feature\Opportunities;

use App\Livewire\Hq\Opportunities\MeetingsTab;
use App\Livewire\Hq\Opportunities\OpportunityPage;
use App\Livewire\Hq\Opportunities\ProjectDraftTab;
use App\Livewire\Hq\Opportunities\ProposalsTab;
use App\Livewire\Hq\Opportunities\QuestionnaireTab;
use App\Livewire\Hq\Opportunities\SowTab;
use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\ProjectDraft;
use App\Models\Proposal;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\StatementOfWork;
use App\Models\User;
use App\Support\Ai\Ai;
use App\Support\Opportunities\Proposals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * The opportunity screens do less on each request (performance pass, 11 Nov 2026): the Proposals, SOW, Questionnaire,
 * Project and Meetings tabs poll with a few small queries and are drawn again only when the AI's work moved on; the
 * screens read where an AI run stands without its input (the whole prompt context, full transcripts included); a blur
 * in the proposal editor writes the line items only when they changed, in one statement; the SOW and Project tabs read
 * no drawn signatures; the Meetings tab reads a transcript's text only for the open meeting; the opportunity page
 * counts its tabs in one query and reads the Overview's cards only while it is shown; the tabs' styles are sent once
 * per page. Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class OpportunitySpeedTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private const DRAFT = [
        'title' => 'A faster month-end close',
        'sections' => [
            ['key' => 'executive_summary', 'title' => 'A close in five days is within reach', 'body' => 'We recommend three phases.'],
            ['key' => 'approach', 'title' => 'We start with the ledger', 'body' => 'Map the close.'],
        ],
        'suggested_line_items' => [['code' => '#1292P', 'quantity' => 2, 'reason' => 'The close needs a diagnostic first.']],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        Mail::fake();
    }

    private function opportunity(User $lead, string $stage = 'questionnaire'): Opportunity
    {
        $org = $this->organisation(['name' => 'Kilo Pharma', 'country' => 'Nigeria']);
        $this->clientContact($org, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'email' => 'ngozi@kilo.test']);

        return $this->opportunityFor($org, ['title' => 'Month-end close', 'lead_user_id' => $lead->id, 'stage' => $stage]);
    }

    /** A run under way, its input as big as a draft's with hour-long transcripts in it */
    private function aiRun(string $purpose, string $type, string $subjectId, string $status = 'running', int $progress = 20): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('ai_runs', ['id' => $id, 'purpose' => $purpose, 'provider' => 'claude', 'model' => 'test', 'subject_type' => $type, 'subject_id' => $subjectId,
            'status' => $status, 'progress' => $progress, 'input' => json_encode(['context' => str_repeat('Ngozi: our close takes 15 days. ', 5000)]),
            'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** The SQL a call ran */
    private function queries(callable $fn): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $sql = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $sql;
    }

    private function matching(array $sql, string $pattern): int
    {
        return count(array_filter($sql, fn ($q) => preg_match($pattern, $q)));
    }

    public function test_the_proposal_poll_draws_the_tab_only_when_the_draft_moved_on(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        FakeAi::install()->respond('proposal', self::DRAFT);
        config(['ai.queue.connection' => 'database']); // the job waits for the worker, as on the server

        $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->call('start', true)->assertSeeHtml('wire:poll.3s="check"');
        $run = AiRun::where('status', 'queued')->sole();

        // Nothing moved: a few small queries, nothing drawn, and the run's input and output are never read
        $sql = $this->queries(fn () => $tab->call('check'));
        $this->assertArrayNotHasKey('html', $tab->effects, 'Nothing moved: nothing drawn');
        $this->assertLessThanOrEqual(6, count($sql), implode("\n", $sql));
        $this->assertSame(0, $this->matching($sql, '/select \* from `ai_runs`|`input`|`output`/'));

        // The progress moved: drawn again
        DB::table('ai_runs')->where('id', $run->id)->update(['progress' => 55]);
        $tab->call('check')->assertSeeHtml('aria-valuenow="55"');
        $tab->call('check');
        $this->assertArrayNotHasKey('html', $tab->effects);

        // The worker files the draft: the next check draws the editor with it, and the poll stops
        Ai::run($run->fresh());
        $tab->call('check')->assertSet('title', 'A faster month-end close')->assertSee('Suggested by the AI: The close needs a diagnostic first.')
            ->assertDontSeeHtml('wire:poll');
    }

    public function test_screens_read_where_a_run_stands_without_its_input_or_output(): void
    {
        $columns = Schema::getColumnListing('ai_runs');
        $this->assertSame([], array_diff(AiRun::RESULT, $columns), 'Every name is a column');
        $this->assertNotContains('input', AiRun::RESULT);
        $this->assertNotContains('output', AiRun::LIGHT);

        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $p = Proposals::start($o, 'USD', $lead);
        $p->forceFill(['ai_run_id' => $this->aiRun('proposal', 'proposal', $p->id), 'changes_run_id' => $this->aiRun('proposal_changes', 'proposal', $p->id)])->save();
        $sow = StatementOfWork::create(['opportunity_id' => $o->id, 'reference' => 'SOW-1', 'version' => 1, 'status' => 'draft', 'sections' => [], 'milestones' => []]);
        $sow->forceFill(['ai_run_id' => $this->aiRun('sow', 'statement_of_work', $sow->id)])->save();
        $q = Questionnaire::create(['opportunity_id' => $o->id, 'question_count' => 3, 'focus' => ['areas' => [], 'note' => ''], 'status' => 'open']);
        $q->forceFill(['ai_run_id' => $this->aiRun('questionnaire', 'questionnaire', $q->id, 'done', 100)])->save();
        $question = QuestionnaireQuestion::create(['questionnaire_id' => $q->id, 'position' => 1, 'question' => 'Which branches?', 'options' => ['A', 'B', 'C'], 'origin' => 'ai']);
        $question->forceFill(['ai_run_id' => $this->aiRun('questionnaire_question', 'questionnaire_question', $question->id)])->save();
        $draft = ProjectDraft::create(['opportunity_id' => $o->id, 'sow_id' => $sow->id, 'payload' => [], 'status' => 'drafting', 'revision' => 1]);
        $draft->forceFill(['ai_run_id' => $this->aiRun('project_draft', 'project_draft', $draft->id)])->save();

        $light = fn (?AiRun $run) => array_keys($run->getAttributes());
        foreach ([$p->fresh()->aiRun, $p->fresh()->changesRun, $sow->fresh()->aiRun, $q->fresh()->aiRun, $question->fresh()->aiRun, $draft->fresh()->aiRun] as $run) {
            $this->assertSame(AiRun::LIGHT, $light($run));
        }
        // Eager loads too, and the progress bar has what it shows
        $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertSeeHtml('aria-valuenow="20"');
        $this->assertSame(AiRun::LIGHT, $light($tab->viewData('proposal')->aiRun));
    }

    public function test_a_blur_in_the_proposal_editor_writes_the_line_items_only_when_they_changed(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $lines = fn (int $n) => array_map(fn ($i) => ['code' => 'CUSTOM', 'description' => "Workshop {$i}", 'quantity' => '2 days', 'unit_price' => 100 + $i], range(1, $n));
        $blur = function (int $n, string $title) use ($o, $lead, $lines) {
            Proposal::where('opportunity_id', $o->id)->forceDelete();
            $p = Proposals::start($o, 'USD', $lead);
            Proposals::save($p, ['title' => 'A faster close', 'sections' => [['key' => 'summary', 'title' => 'Summary', 'body' => 'We recommend it.']], 'lines' => $lines($n)], $lead);
            $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->assertSet('loaded', $p->id);

            return [$tab, $p, $this->queries(fn () => $tab->set('title', $title))];
        };

        // A new title: the lines stay as they are, and the opportunity is read once (plus once for the VAT, under the lock)
        [, $p3, $three] = $blur(3, 'A faster close, in three');
        [$tab, $p, $thirty] = $blur(30, 'A faster close, in thirty');
        $this->assertSame(count($three), count($thirty), 'A blur costs the same with 3 lines or 30');
        $this->assertSame(0, $this->matching($thirty, '/^(delete from|insert into) `proposal_line_items`/'));
        $this->assertSame(2, $this->matching($thirty, '/^select \* from `opportunities`/'));
        $this->assertSame('A faster close, in thirty', $p->fresh()->title);
        $this->assertSame(30, Proposals::lines($p)->count());

        // A changed price: the lines are written again, in one statement, and the totals follow
        $sql = $this->queries(fn () => $tab->set('lines.1.unit_price', 250));
        $this->assertSame(1, $this->matching($sql, '/^delete from `proposal_line_items`/'));
        $this->assertSame(1, $this->matching($sql, '/^insert into `proposal_line_items`/'));
        $stored = Proposals::lines($p);
        $this->assertSame(['Workshop 2', '2 days', '250.00', '500.00'], [$stored[1]->description, $stored[1]->quantity, $stored[1]->unit_price, $stored[1]->total_price]);
        $this->assertSame(array_map(fn ($i) => "Workshop {$i}", range(1, 30)), $stored->pluck('description')->all(), 'In the same order');
        $expected = array_sum(array_map(fn ($i) => 2 * ($i === 2 ? 250 : 100 + $i), range(1, 30)));
        $this->assertSame((float) $expected, (float) $p->fresh()->subtotal);
        $this->assertSame((float) $expected, (float) $p->fresh()->total_value);
    }

    public function test_the_sow_and_project_tabs_poll_cheaply_and_read_no_drawn_signatures(): void
    {
        $this->assertEqualsCanonicalizing(array_values(array_diff(Schema::getColumnListing('statements_of_work'), ['hq_signature_b64', 'client_signature_b64'])),
            StatementOfWork::WITHOUT_SIGNATURES, 'Every column but the two signatures: a new column belongs in the list');

        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead, 'sow');
        $png = 'data:image/png;base64,' . str_repeat('A', 100000);
        $signed = StatementOfWork::create(['opportunity_id' => $o->id, 'reference' => 'SOW-1', 'version' => 1, 'status' => 'superseded', 'sections' => [], 'milestones' => [],
            'hq_signature_b64' => $png, 'hq_signature_hash' => 'hq-hash', 'client_signature_b64' => $png]);
        $sow = StatementOfWork::create(['opportunity_id' => $o->id, 'reference' => 'SOW-1', 'version' => 2, 'status' => 'draft', 'sections' => [], 'milestones' => []]);
        $runId = $this->aiRun('sow', 'statement_of_work', $sow->id);
        $sow->forceFill(['ai_run_id' => $runId])->save();

        $tab = Livewire::test(SowTab::class, ['opportunityId' => $o->id])->assertSeeHtml('wire:poll.3s="check"');
        foreach ($tab->viewData('versions') as $version) {
            $this->assertArrayNotHasKey('hq_signature_b64', $version->getAttributes());
            $this->assertArrayNotHasKey('client_signature_b64', $version->getAttributes());
        }
        $this->assertSame('hq-hash', $tab->viewData('versions')->firstWhere('id', $signed->id)->hq_signature_hash);
        $sql = $this->queries(fn () => $tab->call('check'));
        $this->assertArrayNotHasKey('html', $tab->effects);
        $this->assertLessThanOrEqual(6, count($sql), implode("\n", $sql));
        DB::table('ai_runs')->where('id', $runId)->update(['status' => 'failed', 'progress' => 100, 'error' => 'ai.errors.refused']);
        $tab->call('check')->assertSee('Claude declined to write this.')->assertDontSeeHtml('wire:poll');

        // The project draft names the SOW it comes from without reading its signatures
        $draft = ProjectDraft::create(['opportunity_id' => $o->id, 'sow_id' => $signed->id, 'payload' => ['project' => ['title' => 'Close'], 'milestones' => [], 'roles' => []],
            'status' => 'drafting', 'revision' => 1]);
        $draftRun = $this->aiRun('project_draft', 'project_draft', $draft->id);
        $draft->forceFill(['ai_run_id' => $draftRun])->save();
        $tab = Livewire::test(ProjectDraftTab::class, ['opportunityId' => $o->id])->assertSeeHtml('wire:poll.3s="check"')->assertSee('SOW-1');
        $this->assertArrayNotHasKey('hq_signature_b64', $tab->viewData('draft')->sow->getAttributes());
        $tab->call('check');
        $this->assertArrayNotHasKey('html', $tab->effects);
        DB::table('ai_runs')->where('id', $draftRun)->update(['progress' => 70]);
        $tab->call('check')->assertSeeHtml('aria-valuenow="70"');
        DB::table('ai_runs')->where('id', $draftRun)->update(['status' => 'failed', 'progress' => 100, 'error' => 'ai.errors.refused']);
        $tab->call('check')->assertDontSeeHtml('wire:poll');
        $this->assertSame('ready', $draft->fresh()->status, 'Filed on the check that saw it end');
    }

    public function test_the_questionnaire_poll_draws_the_tab_only_when_the_draft_moved_on(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $q = Questionnaire::create(['opportunity_id' => $o->id, 'question_count' => 5, 'focus' => ['areas' => [], 'note' => ''], 'status' => 'drafting']);
        $runId = $this->aiRun('questionnaire', 'questionnaire', $q->id, 'queued', 0);
        $q->forceFill(['ai_run_id' => $runId])->save();

        $tab = Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->assertSee('Drafting 5 questions…')->assertSeeHtml('wire:poll.3s="check"');
        $sql = $this->queries(fn () => $tab->call('check'));
        $this->assertArrayNotHasKey('html', $tab->effects);
        $this->assertLessThanOrEqual(3, count($sql), implode("\n", $sql));
        DB::table('ai_runs')->where('id', $runId)->update(['status' => 'running', 'progress' => 40]);
        $tab->call('check')->assertSeeHtml('aria-valuenow="40"')->assertDontSee('Waiting for the AI to start.');
        DB::table('ai_runs')->where('id', $runId)->update(['status' => 'failed', 'error' => 'ai.errors.refused']);
        $tab->call('check')->assertSee('The last draft didn’t work.')->assertDontSeeHtml('wire:poll');
    }

    public function test_the_meetings_tab_reads_only_the_open_meetings_text_and_polls_cheaply(): void
    {
        $this->assertEqualsCanonicalizing(array_values(array_diff(Schema::getColumnListing('meeting_transcripts'), ['text', 'entries'])),
            MeetingTranscript::WITHOUT_TEXT, 'Every column but the text and its lines: a new column belongs in the list');

        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $meetings = $transcripts = [];
        foreach (['First', 'Second'] as $i => $title) {
            $meetings[$i] = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $o->organization_id, 'kind' => 'client', 'title' => "{$title} talk",
                'starts_at' => now()->subDays(5 - $i), 'ends_at' => now()->subDays(5 - $i)->addHour(), 'status' => 'held', 'transcript_status' => 'ready']);
            $transcripts[$i] = MeetingTranscript::create(['meeting_id' => $meetings[$i]->id, 'source' => 'paste', 'text' => "Ngozi: the {$title} transcript.",
                'entries' => [['speaker' => 'Ngozi', 'text' => "the {$title} transcript."]]]);
        }

        $tab = Livewire::test(MeetingsTab::class, ['opportunityId' => $o->id]);
        $loaded = fn ($tab) => collect($tab->viewData('past'))->flatMap->transcripts->filter(fn ($t) => array_key_exists('text', $t->getAttributes()))->pluck('meeting_id')->all();
        $this->assertSame([], $loaded($tab), 'Closed meetings: no transcript text read');
        $this->assertFalse(collect($tab->viewData('past'))->flatMap->transcripts->contains(fn ($t) => array_key_exists('entries', $t->getAttributes())));
        $tab->call('toggle', $meetings[0]->id)->assertSee('the First transcript.')->assertDontSee('the Second transcript.');
        $this->assertSame([$meetings[0]->id], $loaded($tab));

        // Without the right to read transcripts, not even the open one's
        $this->actingAs($this->staff('finance', ['finance']));
        $other = Livewire::withQueryParams(['meeting' => $meetings[0]->id])->test(MeetingsTab::class, ['opportunityId' => $o->id])->assertSee('First talk');
        $this->assertSame([], $loaded($other));
        $this->actingAs($lead);

        // The notes being written: the poll draws the tab only when the run moved on or the notes came
        $runId = $this->aiRun('meeting_notes', 'meeting_transcript', $transcripts[0]->id);
        $tab = Livewire::withQueryParams(['meeting' => $meetings[0]->id])->test(MeetingsTab::class, ['opportunityId' => $o->id])->assertSeeHtml('wire:poll.4s="check"');
        $sql = $this->queries(fn () => $tab->call('check'));
        $this->assertArrayNotHasKey('html', $tab->effects, 'Nothing moved: nothing drawn');
        $this->assertLessThanOrEqual(4, count($sql), implode("\n", $sql));
        DB::table('ai_runs')->where('id', $runId)->update(['progress' => 60]);
        $tab->call('check');
        $this->assertArrayHasKey('html', $tab->effects);
        DB::table('ai_runs')->where('id', $runId)->update(['status' => 'done', 'progress' => 100]);
        DB::table('meeting_transcripts')->where('id', $transcripts[0]->id)->update(['notes' => json_encode(['summary' => 'They count stock on paper.']), 'updated_at' => now()->addSecond()]);
        $tab->call('check')->assertSee('They count stock on paper.')->assertDontSeeHtml('wire:poll');
    }

    public function test_the_opportunity_page_counts_its_tabs_in_one_query_and_reads_the_overview_only_when_shown(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        $meeting = fn (string $status) => Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $o->organization_id, 'kind' => 'client', 'title' => 'Talk',
            'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour(), 'status' => $status, 'transcript_status' => 'ready']);
        $meeting('held');
        $meeting('cancelled');
        Proposals::start($o, 'USD', $lead);
        DB::table('system_audit_logs')->insert(['user_id' => $lead->id, 'action' => 'OPPORTUNITY_UPDATED', 'description' => 'Changed the details.',
            'target_table' => 'opportunities', 'target_id' => $o->id, 'created_at' => now()]);

        $page = Livewire::test(OpportunityPage::class, ['opportunity' => $o])->assertSee('Meetings (2)')->assertSee('Proposals (1)')
            ->assertDontSee('Statement of work (');
        $click = $this->queries(fn () => $page->call('show', 'project'));
        $this->assertSame(0, $this->matching($click, '/system_audit_logs|copq_assessments/'), 'The Overview\'s cards: not read on another tab');
        $this->assertSame(1, $this->matching($click, '/count\(\*\)/'), 'The three tab counts in one query');

        // More meetings and a SOW: the same queries, and the counts follow
        foreach (range(1, 5) as $i) {
            $meeting('held');
        }
        StatementOfWork::create(['opportunity_id' => $o->id, 'reference' => 'SOW-1', 'version' => 1, 'status' => 'draft', 'sections' => [], 'milestones' => []]);
        $this->assertSame(count($click), count($this->queries(fn () => $page->call('show', 'project'))));
        $page->assertSee('Meetings (7)')->assertSee('Statement of work (1)');

        $overview = $this->queries(fn () => $page->call('show', 'overview'));
        $this->assertSame(1, $this->matching($overview, '/system_audit_logs/'));
        $page->assertSee('Changed the details.');
    }

    public function test_the_tabs_styles_are_sent_once_per_page_not_with_each_update(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunity($lead);
        Proposals::start($o, 'USD', $lead);

        // The page as loaded has them (in its head)
        $this->get(route('hq.crm.opportunity', ['opportunity' => $o->id, 'tab' => 'proposals']))->assertOk()
            ->assertSee('.opp-meta {', false)->assertSee('.pp-progress', false);

        // An update of the tab or the page sends none
        $tab = Livewire::test(ProposalsTab::class, ['opportunityId' => $o->id])->set('title', 'A faster close');
        $this->assertStringNotContainsString('<style>', $tab->effects['html']);
        $page = Livewire::test(OpportunityPage::class, ['opportunity' => $o])->call('show', 'meetings');
        $this->assertStringNotContainsString('<style>', $page->effects['html']);
    }
}
