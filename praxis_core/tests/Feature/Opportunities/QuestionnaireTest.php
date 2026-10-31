<?php

namespace Tests\Feature\Opportunities;

use App\Events\QuestionnaireCompleted;
use App\Livewire\Hq\Opportunities\OpportunityPage;
use App\Livewire\Hq\Opportunities\QuestionnaireTab;
use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\User;
use App\Support\Ai\Ai;
use App\Support\Ai\AiRequest;
use App\Support\Opportunities\OpportunityContext;
use App\Support\Opportunities\Questionnaires;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * The opportunity's Questionnaire tab (docs/OPPORTUNITIES.md section 4, row 3; owner decision 6): only the lead
 * drafts and answers; 3 to 25 questions, each with at least 3 suggested answers and one recommended; answers save as
 * they go; one question redrafted with only its own context; the lead's own questions; Mark complete locks it for the
 * proposal. FakeAi answers: no request reaches Anthropic.
 */
class QuestionnaireTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    /** A question as the AI writes it (config/ai.php questionnaire schema) */
    private function aiQuestion(string $question, array $options = ['Head office only', 'Head office and the two Lagos branches', 'Every branch at once'], int $recommended = 1): array
    {
        return ['question' => $question, 'why_it_matters' => 'It sets the scope and the price.', 'options' => $options,
            'recommended_option' => $recommended, 'recommendation_reason' => 'The client said the branches close late.'];
    }

    /** An opportunity led by $lead, with a client meeting whose notes and transcript are filed */
    private function opportunityWithNotes(User $lead, array $values = []): Opportunity
    {
        $org = $this->organisation(['name' => 'Kilo Pharma', 'industry' => 'Pharmaceuticals']);
        $o = $this->opportunityFor($org, $values + ['title' => 'Month-end close', 'lead_user_id' => $lead->id]);
        $meeting = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'client', 'title' => 'Discovery',
            'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 10:00:00', 'status' => 'held', 'transcript_status' => 'ready']);
        MeetingTranscript::create(['meeting_id' => $meeting->id, 'source' => 'meet_api', 'text' => 'Ngozi (10:01): our month-end close takes 15 days.',
            'notes' => ['summary' => 'Month-end close takes 15 days.', 'decisions' => [], 'needs' => ['A faster close'], 'risks' => [], 'open_questions' => ['Which branches?'], 'next_steps' => []],
            'collected_at' => '2026-10-05 10:30:00']);

        return $o;
    }

    private function openQuestionnaire(Opportunity $o, User $lead, int $count = 3): Questionnaire
    {
        $questionnaire = Questionnaire::create(['opportunity_id' => $o->id, 'question_count' => $count, 'status' => 'open',
            'focus' => ['areas' => ['timeline'], 'note' => ''], 'created_by' => $lead->id]);
        foreach (range(1, $count) as $i) {
            QuestionnaireQuestion::create(['questionnaire_id' => $questionnaire->id, 'position' => $i, 'question' => "Question number {$i}?",
                'why_it_matters' => "Why {$i}", 'options' => ["Answer {$i}A", "Answer {$i}B", "Answer {$i}C"], 'recommended_option' => 1,
                'recommendation_reason' => "Because {$i}", 'origin' => 'ai']);
        }

        return $questionnaire;
    }

    public function test_the_lead_drafts_the_questionnaire_from_the_opportunity(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead);
        $ai = FakeAi::install()->respond('questionnaire', ['questions' => [
            $this->aiQuestion('Which sites does the close cover?'),
            $this->aiQuestion('Only two suggestions?', ['Yes', 'No']),                          // dropped: fewer than 3
            $this->aiQuestion('Who signs off the close?', ['The CFO', 'the cfo ', 'The board', 'The group controller'], 1), // duplicate folded
            $this->aiQuestion('A recommendation that points nowhere?', recommended: 7),         // dropped
            $this->aiQuestion('Which ledger is the source of truth?'),
            $this->aiQuestion('When should the first faster close happen?'),
            $this->aiQuestion('One too many?'),                                                   // beyond the 4 asked for
        ]], 9000, 2400, 0, 3000);

        $tab = Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
            ->assertSet('canAnswer', true)
            ->assertSee('Draft the questionnaire')->assertSee('The questions are written in English')->assertDontSee('No meeting notes are filed yet')
            ->set('questionCount', 2)->call('draft')->assertHasErrors(['questionCount'])->assertSee('Choose between 3 and 25 questions.')
            ->set('questionCount', 26)->call('draft')->assertHasErrors(['questionCount'])
            ->set('questionCount', 4)->set('focusAreas', ['timeline', 'systems'])->set('focusNote', '  Payroll is out of scope  ')
            ->call('draft')->assertHasNoErrors()->assertDispatched('opportunity-stage-changed');
        $ai->assertRan('questionnaire', fn (AiRequest $r) => $r->model === 'claude-sonnet-5-5' && $r->effort === 'medium' && $r->stream
            && str_contains($r->system[1], 'Name: Kilo Pharma') && str_contains($r->system[1], 'Month-end close takes 15 days.')
            && ! str_contains($r->system[1], 'our month-end close takes 15 days') // notes, not the transcript (cost rule 4)
            && str_contains($r->prompt, "INSTRUCTIONS FROM THE LEAD:\nDraft exactly 4 questions.\nFocus on: Systems and data; Timeline and milestones.\nThe lead adds: Payroll is out of scope")
            && str_contains($r->prompt, 'LANGUAGE: Write in British English'));

        $questionnaire = Questionnaires::current($o);
        $this->assertSame(['open', 4, ['areas' => ['systems', 'timeline'], 'note' => 'Payroll is out of scope']],
            [$questionnaire->status, $questionnaire->question_count, Arr::sortRecursive($questionnaire->focus)]);
        $this->assertSame('questionnaire', AiRun::find($questionnaire->ai_run_id)->subject_type);
        $questions = $questionnaire->questions;
        $this->assertSame(['Which sites does the close cover?', 'Who signs off the close?', 'Which ledger is the source of truth?', 'When should the first faster close happen?'],
            $questions->pluck('question')->all());
        $this->assertSame([1, 2, 3, 4], $questions->pluck('position')->all());
        $this->assertSame(['The CFO', 'The board', 'The group controller'], $questions[1]->options);
        $this->assertSame(0, $questions[1]->recommended_option); // "the cfo " was the same answer as "The CFO"
        foreach ($questions as $q) {
            $this->assertGreaterThanOrEqual(3, count($q->options));
            $this->assertArrayHasKey($q->recommended_option, $q->options);
            $this->assertSame(['ai', null], [$q->origin, $q->answer_option]);
        }

        $tab->assertSee('4 questions drafted.')->assertSee('Which sites does the close cover?')->assertSee('Recommended')
            ->assertSee('Why the AI recommends it:')->assertSee('The client said the branches close late.')->assertSee('0 of 4 questions answered')
            ->assertSee('My own answer');
        $o->refresh();
        $this->assertSame('questionnaire', $o->stage);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'QUESTIONNAIRE_REQUESTED', 'target_table' => 'opportunities', 'target_id' => $o->id, 'user_id' => $lead->id]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'OPPORTUNITY_STAGE_CHANGED', 'target_id' => $o->id]);

        // A second click asks for nothing
        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->call('draft');
        $this->assertCount(1, $ai->requestsFor('questionnaire'));
    }

    public function test_only_the_lead_drafts_and_answers(): void
    {
        $lead = $this->staff('lead', ['sales']);
        $o = $this->opportunityWithNotes($lead);
        FakeAi::install()->respond('questionnaire', ['questions' => [$this->aiQuestion('Q?'), $this->aiQuestion('R?'), $this->aiQuestion('S?')]]);

        foreach ([$this->staff('sales', ['sales']), $this->staff('owner', ['owner']), $this->staff('finance', ['finance'])] as $other) {
            $this->actingAs($other);
            Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
                ->assertSet('canAnswer', false)->assertSee('No questionnaire yet')->assertSee('Lead Test, the lead, drafts and answers this questionnaire.')
                ->assertDontSee('Draft the questions')
                ->set('questionCount', 3)->call('draft')->assertForbidden();
        }
        $this->assertNull(Questionnaires::current($o));

        $questionnaire = $this->openQuestionnaire($o, $lead);
        $first = $questionnaire->questions->first();
        $this->actingAs($this->staff('sales2', ['sales']));
        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
            ->assertSee('Question number 1?')->assertSee('Only Lead Test, the lead, answers this questionnaire. You can read it.')
            ->assertDontSee('Mark complete')->assertDontSee('Redraft')
            ->set("answers.{$first->id}.choice", '0')->assertForbidden();
        $this->assertNull($first->fresh()->answer_option);

        // Without a lead, nobody answers; without opportunities.view, nobody opens it
        $o->forceFill(['lead_user_id' => null])->save();
        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->assertSee('Name a lead in Edit details');
        $this->actingAs($this->staff('engineer', ['engineer']));
        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->assertForbidden();
    }

    public function test_answers_save_as_the_lead_goes(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead);
        [$q1, $q2, $q3] = $this->openQuestionnaire($o, $lead)->questions->all();

        $tab = Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->assertSee('0 of 3 questions answered')
            ->set("answers.{$q1->id}.choice", '2');
        $this->assertSame([2, null, $lead->id], [$q1->fresh()->answer_option, $q1->fresh()->answer_text, $q1->fresh()->answered_by]);
        $this->assertNotNull($q1->fresh()->answered_at);

        $tab->assertSee('Add detail (optional)')->set("answers.{$q1->id}.text", '  Only the two Lagos branches  ');
        $this->assertSame([2, 'Only the two Lagos branches'], [$q1->fresh()->answer_option, $q1->fresh()->answer_text]);

        // Their own words: nothing is answered until they write something
        $tab->set("answers.{$q2->id}.choice", 'own')->assertSee('Your answer');
        $this->assertFalse($q2->fresh()->isAnswered());
        $tab->set("answers.{$q2->id}.text", 'We build the reports ourselves');
        $this->assertSame([null, 'We build the reports ourselves'], [$q2->fresh()->answer_option, $q2->fresh()->answer_text]);

        // A suggestion that doesn't exist is no answer; an answer too long isn't saved
        $tab->set("answers.{$q3->id}.choice", '9');
        $this->assertFalse($q3->fresh()->isAnswered());
        $tab->set("answers.{$q3->id}.choice", 'own')->set("answers.{$q3->id}.text", str_repeat('x', 5001))
            ->assertHasErrors(["answers.{$q3->id}.text"])->assertSee('Shorten the answer to 5,000 characters or fewer');
        $this->assertNull($q3->fresh()->answer_text);

        $tab->assertSee('2 of 3 questions answered');
        // Back on the page later, the answers are there
        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
            ->assertSet("answers.{$q1->id}", ['choice' => '2', 'text' => 'Only the two Lagos branches'])
            ->assertSet("answers.{$q2->id}", ['choice' => 'own', 'text' => 'We build the reports ourselves']);
    }

    public function test_redrafting_one_question_sends_only_that_question(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead);
        [$q1, $q2, $q3] = $this->openQuestionnaire($o, $lead)->questions->all();
        $q2->forceFill(['answer_option' => 0, 'answered_by' => $lead->id, 'answered_at' => now()])->save();
        $ai = FakeAi::install()->respond('questionnaire_question',
            $this->aiQuestion('Which branches close the month last?', ['Ikeja', 'Victoria Island', 'Both'], 2), 1500, 500);

        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
            ->call('openRedraft', $q2->id)->assertSee('Redraft question 2')->assertSee('Its answer will be cleared.')
            ->set('redraftNote', 'Ask about the branches')->call('redraft')->assertHasNoErrors()
            ->assertSet('redraftingId', null)->assertSee('Which branches close the month last?')->assertDontSee('Question number 2?');

        $ai->assertRan('questionnaire_question', function (AiRequest $r) {
            $all = implode("\n", $r->system) . $r->prompt;

            return $r->model === 'claude-sonnet-5-5' && $r->effort === 'medium'
                && str_contains($r->system[1], 'Name: Kilo Pharma') && ! str_contains($all, 'Month-end close takes 15 days') // not the meetings
                && str_contains($r->prompt, "THE QUESTION TO REPLACE:\nQuestion: Question number 2?") && str_contains($r->prompt, 'Suggested answer: Answer 2B')
                && str_contains($r->prompt, "- Question number 1?\n- Question number 3?") && str_contains($r->prompt, 'THE QUESTIONNAIRE’S FOCUS: Timeline and milestones')
                && str_contains($r->prompt, "INSTRUCTIONS FROM THE LEAD:\nAsk about the branches");
        });
        $q2->refresh();
        $this->assertSame(['Which branches close the month last?', ['Ikeja', 'Victoria Island', 'Both'], 2, 2, 'ai', null, null, null],
            [$q2->question, $q2->options, $q2->recommended_option, $q2->position, $q2->origin, $q2->answer_option, $q2->answered_by, $q2->ai_run_id]);
        $this->assertSame('Question number 1?', $q1->fresh()->question);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'QUESTIONNAIRE_QUESTION_REDRAFTED', 'target_id' => $o->id]);
    }

    public function test_a_redraft_runs_in_the_background_and_can_fail(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead);
        [, , $q3] = $this->openQuestionnaire($o, $lead)->questions->all();
        FakeAi::install()->respond('questionnaire_question', $this->aiQuestion('Who approves the new close calendar?'))->refuse('questionnaire_question');
        config(['ai.queue.connection' => 'database']); // the job waits for the worker, as on the server

        $tab = Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->call('openRedraft', $q3->id)->call('redraft')
            ->assertSee('The AI is redrafting this question…')->assertSeeHtml('wire:poll.3s="check"');
        $run = AiRun::where('purpose', 'questionnaire_question')->sole();
        $this->assertSame(['queued', $run->id], [$run->status, $q3->fresh()->ai_run_id]);

        // While it runs, the question can't be answered (and the lead is told so) or redrafted again
        $tab->set("answers.{$q3->id}.choice", '0')->assertHasErrors(['answer'])
            ->assertSee('Not saved: this question is being redrafted');
        $tab->call('openRedraft', $q3->id)->call('redraft');
        $this->assertFalse($q3->fresh()->isAnswered());
        $this->assertSame(1, AiRun::where('purpose', 'questionnaire_question')->count());

        // The worker runs it; the next check applies it
        Ai::run($run);
        $tab->call('check')->assertSee('Who approves the new close calendar?')->assertDontSee('The AI is redrafting')->assertDontSeeHtml('wire:poll');
        $this->assertNull($q3->fresh()->ai_run_id);

        // A refused redraft says so on the question, which keeps its wording, until dismissed
        $tab->call('openRedraft', $q3->id)->call('redraft');
        Ai::run(AiRun::where('purpose', 'questionnaire_question')->where('status', 'queued')->sole());
        $tab->call('check')->assertSee('The redraft didn’t work. Claude declined to write this.')->assertSee('Who approves the new close calendar?');
        $tab->call('dismissFailure', $q3->id)->assertDontSee('The redraft didn’t work');
        $this->assertNull($q3->fresh()->ai_run_id);
    }

    public function test_the_lead_adds_their_own_question_and_asks_for_suggestions(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead);
        [$q1] = $this->openQuestionnaire($o, $lead)->questions->all();
        $ai = FakeAi::install()->respond('questionnaire_question', $this->aiQuestion('A different wording?', ['Weekly', 'Monthly', 'Quarterly'], 1));

        $tab = Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
            ->call('openAdd')->assertSee('Add your own question')
            ->set('newQuestion', '   ')->call('addQuestion')->assertHasErrors(['newQuestion'])->assertSee('Write the question you want to answer.')
            ->set('newQuestion', 'How often should the board see the numbers?')->set('newWhy', '')->call('addQuestion')->assertHasNoErrors()
            ->assertSet('adding', false)->assertSee('Question 4 added.')->assertSee('Added by the lead')
            ->assertSee('No suggested answers yet. Write your answer, or ask the AI to suggest some.');
        $own = QuestionnaireQuestion::where('origin', 'lead')->sole();
        $this->assertSame([4, [], null, $lead->id], [$own->position, $own->options, $own->recommended_option, $own->created_by]);

        $tab->set("answers.{$own->id}.text", 'Monthly, from January');
        $this->assertSame([null, 'Monthly, from January'], [$own->fresh()->answer_option, $own->fresh()->answer_text]);

        // Suggestions keep the lead's wording and answer
        $tab->call('openRedraft', $own->id)->assertSee('Suggest answers to question 4')->call('redraft')->assertHasNoErrors()
            ->assertSee('Weekly')->assertSee('Suggest other answers');
        $ai->assertRan('questionnaire_question', fn (AiRequest $r) => str_contains($r->prompt, "THE LEAD’S OWN QUESTION (keep its wording exactly and suggest the answers):\nQuestion: How often should the board see the numbers?"));
        $own->refresh();
        $this->assertSame(['How often should the board see the numbers?', ['Weekly', 'Monthly', 'Quarterly'], 1, 'It sets the scope and the price.', 'Monthly, from January', 'lead'],
            [$own->question, $own->options, $own->recommended_option, $own->why_it_matters, $own->answer_text, $own->origin]);

        // Removing a question renumbers the rest
        $tab->call('remove', $q1->id)->assertSee('Question 1 removed.');
        $this->assertSame(['Question number 2?', 'Question number 3?', 'How often should the board see the numbers?'],
            QuestionnaireQuestion::orderBy('position')->pluck('question')->all());
        $this->assertSame([1, 2, 3], QuestionnaireQuestion::orderBy('position')->pluck('position')->all());
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'QUESTIONNAIRE_QUESTION_ADDED', 'target_id' => $o->id]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'QUESTIONNAIRE_QUESTION_REMOVED', 'target_id' => $o->id]);
    }

    public function test_mark_complete_locks_the_answers_for_the_proposal(): void
    {
        Event::fake([QuestionnaireCompleted::class]);
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead, ['stage' => 'questionnaire']);
        $questionnaire = $this->openQuestionnaire($o, $lead, 2);
        [$q1, $q2] = $questionnaire->questions->all();

        $tab = Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
            ->assertSee('Answer every question, or remove the ones that don’t apply, to mark it complete.')
            ->set("answers.{$q1->id}.choice", '1')->set("answers.{$q1->id}.text", 'From the March close')
            ->call('complete')->assertHasErrors(['complete'])->assertSee('1 question still needs an answer.');
        $this->assertSame('open', $questionnaire->fresh()->status);
        Event::assertNotDispatched(QuestionnaireCompleted::class);

        $tab->set("answers.{$q2->id}.choice", 'own')->set("answers.{$q2->id}.text", 'The group controller')
            ->call('complete')->assertHasNoErrors()->assertDispatched('opportunity-stage-changed')
            ->assertSee('Questionnaire complete.')->assertSee('Completed by Sales Test on')
            ->assertDontSee('Mark complete')->assertDontSee('Add a question')->assertDontSee('Start again');
        $questionnaire->refresh();
        $this->assertSame(['complete', $lead->id], [$questionnaire->status, $questionnaire->completed_by]);
        $this->assertNotNull($questionnaire->completed_at);
        $this->assertSame('proposal', $o->fresh()->stage);
        Event::assertDispatched(QuestionnaireCompleted::class, fn ($e) => $e->questionnaireId === $questionnaire->id && $e->opportunityId === $o->id && $e->completedBy === $lead->id);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'QUESTIONNAIRE_COMPLETED', 'target_id' => $o->id, 'user_id' => $lead->id]);

        // Locked: nothing changes any more
        $tab->set("answers.{$q1->id}.choice", '0')->call('remove', $q2->id)->call('restart');
        $this->assertSame([1, 2, 'complete'], [$q1->fresh()->answer_option, QuestionnaireQuestion::count(), $questionnaire->fresh()->status]);

        // The proposal (PR 4) reads the answers
        $context = OpportunityContext::for($o->fresh());
        $this->assertStringContainsString('QUESTIONNAIRE (answered by our team, completed', $context);
        $this->assertStringContainsString("Q1. Question number 1?\nAnswer: Answer 1B — From the March close", $context);
        $this->assertStringContainsString("Q2. Question number 2?\nAnswer: The group controller", $context);
    }

    public function test_a_failed_draft_says_why_and_can_be_tried_again(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityFor($this->organisation(['name' => 'Mike Mining']), ['lead_user_id' => $lead->id]);

        // No key: nothing is saved or spent
        $ai = FakeAi::install()->unconfigured();
        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->assertSee('No meeting notes are filed yet')
            ->set('questionCount', 5)->call('draft')->assertHasErrors(['draft'])->assertSee('The AI isn’t set up yet');
        $ai->assertNothingRan();
        $this->assertNull(Questionnaires::current($o));
        $this->assertSame('discovery', $o->fresh()->stage);

        // Refused, then an answer with nothing usable in it, then a good one: one questionnaire throughout
        $ai = FakeAi::install()->refuse('questionnaire')
            ->respond('questionnaire', ['questions' => [$this->aiQuestion('Only two?', ['Yes', 'No'])]])
            ->respond('questionnaire', ['questions' => [$this->aiQuestion('A?'), $this->aiQuestion('B?'), $this->aiQuestion('C?')]]);
        $tab = Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
            ->set('questionCount', 5)->set('focusAreas', ['risks'])->call('draft')
            ->assertSee('The last draft didn’t work. Claude declined to write this.')->assertSee('Draft the questions');
        $questionnaire = Questionnaires::current($o);
        $this->assertSame(['drafting', 5], [$questionnaire->status, $questionnaire->question_count]);

        // Coming back later, the form keeps what was asked
        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->assertSet('questionCount', 5)->assertSet('focusAreas', ['risks'])
            ->assertSee('The last draft didn’t work.');

        $tab->call('draft')->assertSee('The last draft didn’t work. The draft came back incomplete');
        $this->assertSame('failed', AiRun::find(Questionnaires::current($o)->ai_run_id)->status);

        $tab->call('draft')->assertSee('The AI drafted 3 of the 5 questions you asked for.')->assertSee('A?');
        $this->assertSame([$questionnaire->id, 'open', 3], [Questionnaires::current($o)->id, Questionnaires::current($o)->status, QuestionnaireQuestion::count()]);
        $this->assertSame(1, Questionnaire::withTrashed()->count());
        $this->assertCount(3, $ai->requestsFor('questionnaire'));
    }

    public function test_drafting_in_the_background_shows_progress_until_it_is_done(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead);
        FakeAi::install()->respond('questionnaire', ['questions' => array_map(fn ($i) => $this->aiQuestion("Question {$i}?"), range(1, 5))]);
        config(['ai.queue.connection' => 'database']);

        $tab = Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->set('questionCount', 5)->call('draft')
            ->assertSee('Drafting 5 questions…')->assertSee('Waiting for the AI to start.')->assertSeeHtml('wire:poll.3s="check"')
            ->call('draft');
        $this->assertSame(1, AiRun::count());

        // Someone else looking meanwhile sees it being drafted
        $this->actingAs($this->staff('finance', ['finance']));
        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->assertSee('Drafting 5 questions…');
        $this->actingAs($lead);

        Ai::run(AiRun::sole());
        $tab->call('check')->assertSee('5 questions drafted.')->assertSee('Question 5?')->assertDontSeeHtml('wire:poll');
        $this->assertSame(5, QuestionnaireQuestion::count());
    }

    public function test_start_again_sets_the_questionnaire_aside(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead);
        $old = $this->openQuestionnaire($o, $lead, 4);

        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])->call('restart')
            ->assertSee('The questionnaire was set aside.')->assertSee('Draft the questionnaire')->assertSet('questionCount', 4)->assertSet('focusAreas', ['timeline']);
        $this->assertNull(Questionnaires::current($o));
        $this->assertSoftDeleted('questionnaires', ['id' => $old->id, 'deleted_by' => $lead->id]);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'QUESTIONNAIRE_RESTARTED', 'target_id' => $o->id]);
    }

    public function test_the_opportunity_page_shows_the_tab(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead);
        $this->openQuestionnaire($o, $lead);

        $page = Livewire::test(OpportunityPage::class, ['opportunity' => $o])->call('show', 'questionnaire')
            ->assertSeeLivewire(QuestionnaireTab::class)->assertSee('Question number 3?');

        // The tab moved the stage: the header and Move to follow
        $o->forceFill(['stage' => 'proposal'])->save();
        $page->dispatch('opportunity-stage-changed')->assertSet('stage', 'proposal')->assertSee('Proposal in progress');
    }

    public function test_french(): void
    {
        $lead = $this->staff('sales', ['sales']);
        $this->actingAs($lead);
        $o = $this->opportunityWithNotes($lead, ['language' => 'fr']);
        $ai = FakeAi::install()->respond('questionnaire', ['questions' => [
            $this->aiQuestion('Quels sites la clôture couvre-t-elle ?', ['Le siège', 'Le siège et les agences', 'Tout le groupe']),
            $this->aiQuestion('Qui valide ?', ['Le DAF', 'Le conseil', 'Le contrôleur']),
            $this->aiQuestion('Quand ?', ['Mars', 'Juin', 'Septembre']),
        ]]);
        App::setLocale('fr');

        Livewire::test(QuestionnaireTab::class, ['opportunityId' => $o->id])
            ->assertSee('Rédiger le questionnaire')->assertSee('Les questions sont rédigées en français, la langue de cette opportunité.')
            ->set('questionCount', 2)->call('draft')->assertSee("Choisissez entre 3 et 25\u{00A0}questions.")
            ->set('questionCount', 3)->call('draft')
            ->assertSee("3\u{00A0}questions rédigées.")->assertSee('Recommandée')->assertSee('Ma propre réponse')
            ->assertSee("Réponses\u{00A0}: 0 sur 3\u{00A0}questions")->assertSee('Marquer comme terminé');
        $ai->assertRan('questionnaire', fn (AiRequest $r) => str_contains($r->prompt, 'LANGUAGE: Write in French'));

        // Every key in one language is in the other
        $en = array_keys(Arr::dot(require lang_path('en/questionnaire.php')));
        $fr = array_keys(Arr::dot(require lang_path('fr/questionnaire.php')));
        sort($en);
        sort($fr);
        $this->assertSame($en, $fr, 'lang/en/questionnaire.php and lang/fr/questionnaire.php have different keys');
    }
}
