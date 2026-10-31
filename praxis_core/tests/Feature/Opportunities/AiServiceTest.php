<?php

namespace Tests\Feature\Opportunities;

use App\Models\AiRun;
use App\Support\Ai\Ai;
use App\Support\Ai\AiRequest;
use App\Support\Ai\AiUnavailable;
use App\Support\Ai\Budget;
use App\Support\Ai\CompanyBrief;
use App\Support\Ai\Pricing;
use App\Support\Integrations as Settings;
use App\Support\Monitor\HealthChecks;
use Illuminate\Support\Facades\App;
use LogicException;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * The AI service (docs/OPPORTUNITIES.md 8.3) with FakeAi: runs, progress, cost, schema checks, refusals, cut-off
 * answers, the monthly budget, and runs left behind by a worker. No request reaches a provider.
 */
class AiServiceTest extends TestCase
{
    use OpportunityFixtures, UsesThrowawayDatabase;

    private function notes(): array
    {
        return ['summary' => 'The client wants one ledger.', 'decisions' => ['Start with finance'], 'needs' => ['IFRS reporting'],
            'risks' => ['Data quality'], 'open_questions' => ['Who owns payroll?'], 'next_steps' => [['action' => 'Send the agenda', 'owner' => 'JBS Praxis', 'due' => '']]];
    }

    public function test_a_queued_run_reads_the_brief_then_the_context_and_saves_checked_output(): void
    {
        $sales = $this->staff('sales', ['sales']);
        $this->actingAs($sales);
        $opportunity = $this->opportunityFor($this->organisation());
        $ai = FakeAi::install()->respond('meeting_notes', $this->notes(), 12000, 900, 0, 1800);

        $run = Ai::queue('meeting_notes', $opportunity, [
            'context' => 'ORGANISATION\nName: Acme', 'material' => 'Ada: we need one ledger.', 'instructions' => 'Keep it short.', 'language' => 'fr',
        ]);

        $this->assertSame(['done', 100, 'meeting_notes', 'claude', 'claude-haiku-5-5', 'opportunity', $opportunity->id, $sales->id],
            [$run->status, $run->progress, $run->purpose, $run->provider, $run->model, $run->subject_type, $run->subject_id, $run->requested_by]);
        $this->assertEquals($this->notes(), $run->output); // MySQL's JSON column orders keys its own way
        $this->assertSame([12000, 900, 0, 1800], [$run->input_tokens, $run->output_tokens, $run->cache_read_tokens, $run->cache_write_tokens]);
        // Haiku: 12,000 × $0.10 + 900 × $0.50 + 1,800 × $0.125 per million
        $this->assertEqualsWithDelta(0.001875, $run->cost_usd, 0.0000001);
        $this->assertTrue($run->subject->is($opportunity));

        $ai->assertRan('meeting_notes', function (AiRequest $r) {
            return $r->system[0] === CompanyBrief::text()
                && str_starts_with($r->system[1], 'THE OPPORTUNITY') && str_contains($r->system[1], 'Name: Acme')
                && str_contains($r->prompt, 'LANGUAGE: Write in French') && str_contains($r->prompt, 'INSTRUCTIONS FROM THE LEAD:' . "\n" . 'Keep it short.')
                && str_ends_with($r->prompt, 'Ada: we need one ledger.')
                && $r->effort === 'medium' && $r->stream === false && $r->schema === Ai::purpose('meeting_notes')['schema'];
        });
    }

    public function test_refusals_cut_off_and_off_schema_answers_fail_but_keep_their_cost(): void
    {
        $ai = FakeAi::install()->refuse('meeting_notes')->truncate('proposal')->garbage('client_minutes');
        $subject = ['type' => 'meeting_transcript', 'id' => null];

        $refused = Ai::queue('meeting_notes', $subject, ['material' => 'x']);
        $this->assertSame(['failed', 'refusal', 'ai.errors.refused', 900], [$refused->status, $refused->stop_reason, $refused->error, $refused->input_tokens]);
        $this->assertGreaterThan(0, $refused->cost_usd);
        $this->assertSame('Claude declined to write this. Reword the instructions, then try again.', $refused->errorMessage());

        $cut = Ai::queue('proposal', $subject, ['material' => 'x']);
        $this->assertSame(['failed', 'max_tokens', 'ai.errors.too_long'], [$cut->status, $cut->stop_reason, $cut->error]);
        $this->assertSame(32000, $cut->output_tokens);

        $bad = Ai::now('client_minutes', $subject, ['material' => 'x']);
        $this->assertSame(['failed', 'ai.errors.invalid'], [$bad->status, $bad->error]);
        $this->assertNull($bad->output);

        App::setLocale('fr');
        $this->assertSame('Le brouillon est revenu incomplet ; il n’a pas été enregistré. Réessayez.', str_replace("\u{202F}", ' ', $bad->errorMessage()));
    }

    public function test_provider_errors_are_messages_not_crashes(): void
    {
        FakeAi::install()->fail('questionnaire', 'credit');

        $run = Ai::queue('questionnaire', ['type' => 'opportunity', 'id' => null], []);

        $this->assertSame(['failed', 'ai.errors.credit'], [$run->status, $run->error]);
        $this->assertStringContainsString('The Claude credit has run out', $run->errorMessage());
        $check = collect(HealthChecks::all())->firstWhere('key', 'integrations');
        $this->assertSame('bad', $check['state']);
    }

    public function test_the_budget_stops_jobs_before_they_start(): void
    {
        $ai = FakeAi::install()->respond('client_minutes', ['subject' => 'Minutes', 'summary' => 'S', 'decisions' => [], 'next_steps' => []]);
        Settings::set('ai.monthly_budget_usd', '0.50');
        AiRun::create(['purpose' => 'proposal', 'provider' => 'claude', 'model' => 'claude-sonnet-5-5', 'status' => 'done', 'cost_usd' => 0.30]);
        $this->assertEqualsWithDelta(0.30, Budget::spent(), 0.000001);

        // A proposal could cost up to 32,000 × $10 per million = $0.32 on its own: over the $0.20 left
        try {
            Ai::queue('proposal', ['type' => 'opportunity', 'id' => null], ['material' => 'x']);
            $this->fail('Expected the budget to stop the job');
        } catch (AiUnavailable $e) {
            $this->assertSame('budget', $e->reason);
        }
        $this->assertSame(1, AiRun::count(), 'Nothing was queued');
        $ai->assertNothingRan();

        // Minutes on Haiku fit
        $this->assertTrue(Ai::now('client_minutes', ['type' => 'meeting_transcript', 'id' => null], ['material' => 'x'])->succeeded());

        $check = collect(HealthChecks::all())->firstWhere('key', 'integrations');
        $this->assertSame('warn', $check['state'], 'Over 80% of the budget is spent');
        $this->assertStringContainsString('AI this month: $0.30 of $0.50', $check['detail']);

        Settings::set('ai.monthly_budget_usd', '0.30');
        $this->assertSame('bad', collect(HealthChecks::all())->firstWhere('key', 'integrations')['state']);
    }

    public function test_guards(): void
    {
        $ai = FakeAi::install()->unconfigured();
        try {
            Ai::queue('proposal', ['type' => 'opportunity', 'id' => null], []);
            $this->fail('Expected not_configured');
        } catch (AiUnavailable $e) {
            $this->assertSame('not_configured', $e->reason);
        }

        $this->expectException(LogicException::class);
        Ai::now('proposal', ['type' => 'opportunity', 'id' => null], []);
    }

    public function test_runs_left_behind_by_a_stopped_worker_fail(): void
    {
        $old = AiRun::create(['purpose' => 'proposal', 'provider' => 'claude', 'model' => 'claude-sonnet-5-5', 'status' => 'running']);
        $old->forceFill(['created_at' => now()->subHour()])->save();
        $fresh = AiRun::create(['purpose' => 'proposal', 'provider' => 'claude', 'model' => 'claude-sonnet-5-5', 'status' => 'queued']);

        $this->assertSame(1, Ai::expireStuck());
        $this->assertSame(['failed', 'ai.errors.stuck'], [$old->fresh()->status, $old->fresh()->error]);
        $this->assertSame('queued', $fresh->fresh()->status);
    }

    public function test_pricing(): void
    {
        $usage = fn (string $model, int $in, int $out, int $read = 0, int $write = 0) => [['model' => $model, 'input' => $in, 'output' => $out, 'cache_read' => $read, 'cache_write' => $write]];

        // A proposal over 3 meetings: about $0.20 on Sonnet (6.2)
        $this->assertEqualsWithDelta(0.19, Pricing::cost($usage('claude-sonnet-5-5', 40000, 11000)), 0.0001);
        // Cache reads cost a tenth
        $this->assertEqualsWithDelta(0.008, Pricing::cost($usage('claude-sonnet-5-5', 0, 0, 40000)), 0.000001);
        // Haiku over 100,000 tokens of prompt costs more per token: 120,000 × $0.50 + 1,000 × $2.50 per million
        $this->assertEqualsWithDelta(0.0625, Pricing::cost($usage('claude-haiku-5-5', 120000, 1000)), 0.000001);
        // An unknown model is priced as the dearest one, so the budget errs on the safe side
        $this->assertSame(Pricing::cost($usage('claude-opus-4-8', 1000, 1000)), Pricing::cost($usage('claude-future-9', 1000, 1000)));
    }

    public function test_the_fake_checks_scripted_answers_against_the_schema(): void
    {
        $this->expectException(ExpectationFailedException::class);
        FakeAi::install()->respond('meeting_notes', ['summary' => 'Missing everything else']);
    }
}
