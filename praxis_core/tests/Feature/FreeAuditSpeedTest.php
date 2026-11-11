<?php

namespace Tests\Feature;

use App\Livewire\Hq\Intelligence\LiveConsultation;
use App\Livewire\Hq\Intelligence\ProposalBuilder;
use App\Support\Ai\Ai;
use App\Support\Ai\AiRequest;
use App\Support\Ai\AiUnavailable;
use App\Support\Ai\ClaudeProvider;
use App\Support\Integrations as Settings;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * Free audits and the older Proposals screen keep ids in the page's state and read rows when they draw (they kept every
 * appointment, organisation, proposal and signature image, sent back and forth on every click); a request to the AI
 * that a page waits for gives up after a short time and is never retried.
 */
class FreeAuditSpeedTest extends TestCase
{
    use UsesThrowawayDatabase;

    private function organisation(string $name): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $id, 'name' => $name, 'status' => 'Lead', 'preferred_language' => 'EN', 'industry' => 'Logistics']);

        return $id;
    }

    private function appointments(string $orgId, string $status, int $n): string
    {
        for ($i = 0; $i < $n; $i++) {
            $id = (string) Str::uuid();
            $this->insertRow('appointments', ['id' => $id, 'organization_id' => $orgId, 'title' => 'Free audit and demo', 'status' => $status,
                'start_time' => now()->addDays($i + 1)->format('Y-m-d 10:00:00'), 'end_time' => now()->addDays($i + 1)->format('Y-m-d 11:00:00')]);
        }

        return $id;
    }

    private function state($page): array
    {
        return $page->snapshot['data'];
    }

    public function test_the_free_audits_page_keeps_ids_in_its_state_and_reads_rows_when_it_draws(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        $org = $this->organisation('Kilo Haulage Ltd (test)');
        $appt = $this->appointments($org, 'Scheduled', 2);
        $small = strlen(json_encode($this->state(Livewire::test(LiveConsultation::class)->assertSee('Kilo Haulage Ltd (test)'))));

        $this->appointments($this->organisation('Lima Foods Ltd (test)'), 'Scheduled', 30);
        $this->appointments($this->organisation('Mike Steel Ltd (test)'), 'Requested', 20);
        $page = Livewire::test(LiveConsultation::class)->assertSee('Lima Foods Ltd (test)')->assertSee('Mike Steel Ltd (test)')
            ->assertViewHas('scheduledAppointments', fn ($rows) => $rows->count() === 32)->assertViewHas('requestedAppointments', fn ($rows) => $rows->count() === 20);
        $this->assertSame($small, strlen(json_encode($this->state($page))), 'more appointments, the same state');
        foreach (['requestedAppointments', 'scheduledAppointments', 'leadData', 'copqData', 'historyData', 'dbIndustries', 'dbCurrencies'] as $gone) {
            $this->assertArrayNotHasKey($gone, $this->state($page));
        }

        // The free audit: its organisation and its estimate are read when the page draws
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'We estimate the approvals cost them a week a month.']]]]]])]);
        $page->call('enterTerminal', $org, $appt)->assertSee('Kilo Haulage Ltd (test)')->assertSee('Calculate estimate')->assertSet('lineWaiting', false)
            ->set('inlineIndustry', 'Logistics')->set('inlineTool', 'excel')->set('inlineBottleneck', 'Approvals')->set('inlineTurnover', 2000000)
            ->call('generateInlineCopq')->assertHasNoErrors()
            ->assertSee('Estimated cost a year')->assertSee('We estimate the approvals cost them a week a month.')->assertDontSee('Calculate estimate');
        $this->assertSame('Kilo Haulage Ltd (test)', DB::table('copq_assessments')->where('organization_id', $org)->value('company_name'));
        $this->assertArrayNotHasKey('leadData', $this->state($page));
        $this->assertLessThan(2000, strlen(json_encode($this->state($page))));
    }

    public function test_the_older_proposals_screen_keeps_no_lists_or_signature_images_in_its_state(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        $org = $this->organisation('Kilo Haulage Ltd (test)');
        $image = 'data:image/png;base64,' . str_repeat('iVBORw0KGgo', 4000);
        $signed = (string) Str::uuid();
        $this->insertRow('proposals', ['id' => $signed, 'organization_id' => $org, 'title' => 'Signed scope (test)', 'proposal_ref' => 'SOW-TEST-SIGNED',
            'token_hash' => 'TKN-' . strtoupper(Str::random(10)), 'status' => 'Pending Auth', 'payment_schedule_type' => '40_30_30', 'created_by' => null, 'project_id' => null,
            'hq_signer_name' => 'Tess Test', 'hq_signature_b64' => $image, 'hq_signature_hash' => 'SHA256-TEST', 'hq_signed_at' => now()]);
        DB::table('proposal_ai_narratives')->insert(['proposal_id' => $signed, 'ai_executive_summary' => '', 'ai_operational_strategy' => '', 'ai_phases_json' => '[]', 'generated_at' => now()]);

        $ledger = Livewire::test(ProposalBuilder::class)->assertSee('SOW-TEST-SIGNED')->assertSee('Kilo Haulage Ltd (test)');
        foreach (['proposalsLedger', 'availableLeads', 'financialDictionary', 'exchangeRates', 'techCatalog'] as $gone) {
            $this->assertArrayNotHasKey($gone, $this->state($ledger));
        }
        $this->assertStringNotContainsString('iVBORw0KGgo', json_encode($ledger->snapshot));

        // The signed preview shows the signature; the page's state doesn't carry it
        $preview = Livewire::test(ProposalBuilder::class, ['id' => $signed])->assertSet('isLocked', true)->call('switchTab', 'preview')
            ->assertSee('Signed for JBS Praxis')->assertSeeHtml('src="' . $image . '"')->assertSee('SHA256-TEST');
        $this->assertStringNotContainsString('iVBORw0KGgo', json_encode($preview->snapshot));
        $this->assertLessThan(10000, strlen(json_encode($preview->snapshot)));
    }

    public function test_a_request_a_page_waits_for_gives_up_quickly_and_is_never_retried(): void
    {
        $ai = FakeAi::install()->respond('consultation_prep', ['summary' => 'Open with the depot.'])
            ->respond('client_minutes', ['subject' => 'Minutes', 'summary' => 'S', 'decisions' => [], 'next_steps' => []]);

        // While the page waits (Ai::now): sync. A sync purpose queued (client minutes of a whole transcript): not.
        Ai::now('consultation_prep', ['type' => 'organization', 'id' => null], ['material' => 'Notes']);
        Ai::queue('client_minutes', ['type' => 'test', 'id' => null], ['material' => 'Ada: hello']);
        $waited = $ai->requestsFor('consultation_prep')[0];
        $queued = $ai->requestsFor('client_minutes')[0];
        $this->assertTrue($waited->sync);
        $this->assertFalse($queued->sync);
        $this->assertSame([(float) AiRequest::SYNC_TIMEOUT, 0], (new ClaudeProvider)->limits($waited));
        $this->assertSame([600.0, 2], (new ClaudeProvider)->limits($queued));

        // Over the wire: an overloaded API is tried again for a background job, given up at once while a page waits
        Settings::set('anthropic.api_key', 'sk-ant-api03-FAKEKEYFORTESTSONLY_0123456789abcdef');
        $busy = fn () => new Response(529, ['Content-Type' => 'application/json', 'retry-after-ms' => '1'], json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]));
        $ok = fn () => new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5', 'content' => [['type' => 'text', 'text' => '{"summary":"S"}']],
            'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]));
        $provider = function (array $responses, array &$sent): ClaudeProvider {
            $stack = HandlerStack::create(new MockHandler($responses));
            $stack->push(Middleware::history($sent));

            return new ClaudeProvider(new Guzzle(['handler' => $stack]));
        };

        $sent = [];
        $this->assertSame('{"summary":"S"}', $provider([$busy(), $busy(), $ok()], $sent)->generate(Ai::request('consultation_prep', ['material' => 'Notes']))->text);
        $this->assertCount(3, $sent);

        $sent = [];
        try {
            $provider([$busy(), $ok()], $sent)->generate(Ai::request('consultation_prep', ['material' => 'Notes'], waiting: true));
            $this->fail('A page waiting for an overloaded API gives up');
        } catch (AiUnavailable $e) {
            $this->assertSame('ai.errors.busy', $e->key());
        }
        $this->assertCount(1, $sent);
    }
}
