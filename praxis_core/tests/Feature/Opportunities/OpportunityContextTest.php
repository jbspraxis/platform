<?php

namespace Tests\Feature\Opportunities;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\MeetingTranscript;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Support\Ai\CompanyBrief;
use App\Support\Opportunities\OpportunityContext;
use Illuminate\Support\Str;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * What the AI reads about an opportunity (docs/OPPORTUNITIES.md 8.4): everything in a fixed order, full transcripts
 * only when asked, internal meetings only when wanted, the same text every time (it is cached). And the brief.
 */
class OpportunityContextTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    public function test_context_holds_everything_in_order(): void
    {
        $org = $this->organisation(['name' => 'Lima Logistics', 'industry' => 'Logistics', 'country' => 'Cameroon', 'city' => 'Douala']);
        $this->insertRow('copq_assessments', ['id' => (string) Str::uuid(), 'organization_id' => $org, 'report_token' => 'PRX-T', 'executive_name' => 'X',
            'company_name' => 'Lima Logistics', 'corporate_email' => 'x@lima.test', 'bottleneck' => 'Manual reconciliations', 'copq_currency' => 'XAF',
            'calculated_leakage' => 125000000, 'created_at' => '2026-10-02 08:00:00']);
        $o = $this->opportunityFor($org, ['title' => 'OHADA finance', 'language' => 'fr']);

        $client = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'client', 'title' => 'Discovery',
            'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 10:00:00', 'status' => 'held', 'transcript_status' => 'ready']);
        MeetingAttendee::create(['meeting_id' => $client->id, 'email' => 'cfo@lima.test', 'name' => 'Paul Ndi', 'role' => 'client']);
        MeetingTranscript::create(['meeting_id' => $client->id, 'source' => 'meet_api', 'text' => 'Paul Ndi (10:01): We close the month in 15 days.',
            'notes' => ['summary' => 'Month-end takes 15 days.', 'decisions' => ['Start with the ledger'], 'needs' => [], 'risks' => ['Two ERPs'], 'open_questions' => [], 'next_steps' => []],
            'collected_at' => '2026-10-05 10:30:00']);
        $internal = Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'internal', 'title' => 'Team debrief',
            'starts_at' => '2026-10-06 09:00:00', 'ends_at' => '2026-10-06 09:30:00', 'status' => 'held', 'transcript_status' => 'ready']);
        MeetingTranscript::create(['meeting_id' => $internal->id, 'source' => 'paste', 'text' => 'We think the price should be lower.', 'collected_at' => '2026-10-06 10:00:00']);
        Meeting::create(['opportunity_id' => $o->id, 'organization_id' => $org, 'kind' => 'client', 'title' => 'Cancelled call',
            'starts_at' => '2026-10-07 09:00:00', 'ends_at' => '2026-10-07 10:00:00', 'status' => 'cancelled', 'transcript_status' => 'waiting']);

        $q = Questionnaire::create(['opportunity_id' => $o->id, 'question_count' => 2, 'status' => 'complete', 'completed_at' => '2026-10-08 12:00:00']);
        QuestionnaireQuestion::create(['questionnaire_id' => $q->id, 'position' => 1, 'question' => 'Which entity goes first?', 'options' => ['Douala', 'Yaoundé', 'Both'],
            'recommended_option' => 0, 'answer_option' => 2]);
        QuestionnaireQuestion::create(['questionnaire_id' => $q->id, 'position' => 2, 'question' => 'Who signs off?', 'options' => ['CFO', 'CEO', 'Board']]);

        $this->insertRow('proposals', ['id' => (string) Str::uuid(), 'organization_id' => $org, 'opportunity_id' => $o->id, 'proposal_ref' => 'PRX-7',
            'status' => 'Authorized', 'executive_summary' => 'We recommend one OHADA ledger.', 'subtotal' => 0, 'total_value' => 40000, 'payment_schedule_type' => 'Standard']);

        $text = OpportunityContext::for($o);

        $order = ['ORGANISATION', 'Name: Lima Logistics', 'THIS PIECE OF WORK', 'Language of the documents: French', 'COST OF POOR QUALITY',
            'Estimated cost of poor quality a year: XAF 125,000,000', 'MEETINGS (in date order)', '1. Client meeting: Discovery, Monday 5 October 2026, 10:00 (WAT) (held)',
            'Attendees: Paul Ndi (client)', 'Summary: Month-end takes 15 days.', 'Decisions: Start with the ledger', '2. Internal meeting: Team debrief',
            'QUESTIONNAIRE', 'Q1. Which entity goes first?', 'Answer: Both', 'Q2. Who signs off?', 'Answer: not answered yet',
            'APPROVED PROPOSAL PRX-7', 'We recommend one OHADA ledger.'];
        $at = -1;
        foreach ($order as $needle) {
            $pos = strpos($text, $needle);
            $this->assertNotFalse($pos, "Missing: {$needle}");
            $this->assertGreaterThan($at, $pos, "Out of order: {$needle}");
            $at = $pos;
        }
        $this->assertStringNotContainsString('Cancelled call', $text);
        $this->assertStringNotContainsString('We close the month in 15 days', $text, 'Full transcripts only when asked');
        $this->assertSame($text, OpportunityContext::for($o->fresh()), 'The same records give the same text (prompt caching)');

        $withTranscripts = OpportunityContext::for($o, [...OpportunityContext::DEFAULT, 'transcripts']);
        $this->assertStringContainsString('Paul Ndi (10:01): We close the month in 15 days.', $withTranscripts);

        $clientOnly = OpportunityContext::for($o, array_diff(OpportunityContext::DEFAULT, ['internal']));
        $this->assertStringNotContainsString('Team debrief', $clientOnly);
    }

    public function test_the_company_brief_carries_the_rules(): void
    {
        $brief = CompanyBrief::text();

        $this->assertStringStartsWith('COMPANY: JBS Praxis', $brief);
        $this->assertStringContainsString('Never name a client other than the one this work is for', $brief);
        $this->assertStringContainsString('Never invent a figure', $brief);
        $this->assertStringContainsString('Never guarantee an outcome', $brief);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $brief, 'No dates: the brief is cached');
        // LiveConsultation reads this one copy instead of its own
        $this->assertStringContainsString('CompanyBrief::text()', file_get_contents(app_path('Livewire/Hq/Intelligence/LiveConsultation.php')));
    }
}
