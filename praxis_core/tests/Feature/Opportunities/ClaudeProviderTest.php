<?php

namespace Tests\Feature\Opportunities;

use App\Support\Ai\Ai;
use App\Support\Ai\AiResult;
use App\Support\Ai\AiUnavailable;
use App\Support\Ai\ClaudeProvider;
use App\Support\Ai\Pricing;
use App\Support\Integrations as Settings;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The Claude provider through the real SDK (anthropic-ai/sdk) against a mocked HTTP transport: what goes over the
 * wire (docs/OPPORTUNITIES.md 6.2: adaptive thinking, effort, JSON schema, cached system blocks, Sonnet's
 * server-side fallback, streaming) and how answers, refusals and errors come back. Nothing reaches Anthropic.
 */
class ClaudeProviderTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const KEY = 'sk-ant-api03-FAKEKEYFORTESTSONLY_0123456789abcdef';

    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    private array $sent = [];

    private function provider(Response ...$responses): ClaudeProvider
    {
        Settings::set('anthropic.api_key', self::KEY);
        config(['ai.providers.claude.max_retries' => 0]);
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        return new ClaudeProvider(new Guzzle(['handler' => $stack]));
    }

    private function body(int $i = 0): array
    {
        $request = $this->sent[$i]['request'];
        $request->getBody()->rewind();

        return json_decode((string) $request->getBody(), true);
    }

    private function message(array $values = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($values + [
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => '{"subject":"Minutes","summary":"S","decisions":[],"next_steps":[]}']],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 1500, 'output_tokens' => 120, 'cache_read_input_tokens' => 900, 'cache_creation_input_tokens' => 0],
        ]));
    }

    private function sse(array $events): Response
    {
        $body = '';
        foreach ($events as $event) {
            $body .= 'event: ' . $event['type'] . "\ndata: " . json_encode($event) . "\n\n";
        }

        return new Response(200, ['Content-Type' => 'text/event-stream'], $body);
    }

    public function test_a_short_job_on_haiku(): void
    {
        $provider = $this->provider($this->message());
        $request = Ai::request('client_minutes', ['context' => 'ORGANISATION', 'material' => 'Ada: hello', 'language' => 'en']);

        $result = $provider->generate($request);

        $this->assertInstanceOf(AiResult::class, $result);
        $this->assertSame(['end_turn', 'claude-haiku-5-5', 1500, 120, 900], [$result->stopReason, $result->model, $result->inputTokens, $result->outputTokens, $result->cacheReadTokens]);
        $this->assertSame(['subject' => 'Minutes', 'summary' => 'S', 'decisions' => [], 'next_steps' => []], json_decode($result->text, true));

        $http = $this->sent[0]['request'];
        $this->assertSame('https://api.anthropic.com/v1/messages', (string) $http->getUri()->withQuery(''));
        // SDK 1.x (API version 2026-10-21) sends the key as a bearer token
        $this->assertSame('Bearer ' . self::KEY, $http->getHeaderLine('Authorization'));
        $this->assertSame('2026-10-21', $http->getHeaderLine('anthropic-version'));
        $this->assertSame('', $http->getHeaderLine('anthropic-beta'), 'Haiku has no server-side fallback');

        $body = $this->body();
        $this->assertSame('claude-haiku-5-5', $body['model']);
        $this->assertSame(4000, $body['max_tokens']);
        $this->assertSame(['type' => 'adaptive'], $body['thinking']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame(Ai::purpose('client_minutes')['schema'], $body['output_config']['format']['schema']);
        $this->assertCount(2, $body['system']);
        $this->assertSame(['type' => 'ephemeral'], $body['system'][0]['cache_control'], 'The company brief is cached');
        $this->assertSame(['type' => 'ephemeral'], $body['system'][1]['cache_control'], 'The opportunity context is cached');
        $this->assertStringStartsWith('COMPANY: JBS Praxis', $body['system'][0]['text']);
        $this->assertSame('user', $body['messages'][0]['role']);
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertArrayNotHasKey('fallbacks', $body);
        $this->assertArrayNotHasKey('stream', $body);
    }

    public function test_a_long_document_streams_on_sonnet_with_the_server_side_fallback(): void
    {
        $json = json_encode(['title' => 'T', 'sections' => [['key' => 'summary', 'title' => 'We recommend one ledger', 'body' => 'Body']], 'suggested_line_items' => []]);
        $chunks = str_split($json, 20);
        $events = [
            ['type' => 'message_start', 'message' => ['id' => 'msg_s', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5', 'content' => [],
                'stop_reason' => null, 'stop_sequence' => null, 'usage' => ['input_tokens' => 30000, 'output_tokens' => 1, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 2500]]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']],
        ];
        foreach ($chunks as $chunk) {
            $events[] = ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => $chunk]];
        }
        $events[] = ['type' => 'content_block_stop', 'index' => 0];
        $events[] = ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => 9000]];
        $events[] = ['type' => 'message_stop'];

        $provider = $this->provider($this->sse($events));
        $progress = [];
        $result = $provider->generate(Ai::request('proposal', ['context' => 'C', 'language' => 'fr']), function (int $p) use (&$progress) {
            $progress[] = $p;
        });

        $this->assertSame($json, $result->text);
        $this->assertSame(['end_turn', 30000, 9000, 2500], [$result->stopReason, $result->inputTokens, $result->outputTokens, $result->cacheWriteTokens]);
        // Progress rises with the text received and stays below 100 until the run is saved
        $this->assertNotEmpty($progress);
        $sorted = $progress;
        sort($sorted);
        $this->assertSame($sorted, $progress);
        $this->assertLessThanOrEqual(95, max($progress));

        $http = $this->sent[0]['request'];
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $http->getHeaderLine('anthropic-beta'));
        $body = $this->body();
        $this->assertSame(['claude-sonnet-5-5', true, 'default', 'high', 32000], [$body['model'], $body['stream'], $body['fallbacks'], $body['output_config']['effort'], $body['max_tokens']]);
    }

    public function test_a_fallback_is_priced_per_model(): void
    {
        // Sonnet's documents stream: the refused attempt and the fallback arrive as iterations in the final usage
        $provider = $this->provider($this->sse([
            ['type' => 'message_start', 'message' => ['id' => 'msg_f', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5', 'content' => [],
                'stop_reason' => null, 'stop_sequence' => null, 'usage' => ['input_tokens' => 2000, 'output_tokens' => 1]]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => '{"questions":[]}']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => 400, 'iterations' => [
                ['type' => 'message', 'input_tokens' => 2000, 'output_tokens' => 0, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
                ['type' => 'fallback_message', 'model' => 'claude-sonnet-5', 'input_tokens' => 2000, 'output_tokens' => 400, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
            ]]],
            ['type' => 'message_stop'],
        ]));

        $result = $provider->generate(Ai::request('questionnaire', []));

        $this->assertSame(['claude-sonnet-5-5', 'claude-sonnet-5'], array_column($result->usage(), 'model'));
        $this->assertEqualsWithDelta((2000 * 2 + 2000 * 2 + 400 * 10) / 1_000_000, Pricing::cost($result->usage()), 0.0000001);
    }

    public function test_a_refusal_comes_back_as_such(): void
    {
        $provider = $this->provider($this->message(['content' => [], 'stop_reason' => 'refusal',
            'stop_details' => ['type' => 'refusal', 'category' => 'cyber', 'explanation' => null]]));

        $result = $provider->generate(Ai::request('meeting_notes', []));

        $this->assertSame(['refusal', 'cyber', null], [$result->stopReason, $result->refusalCategory, $result->text]);
    }

    public function test_errors_become_reasons(): void
    {
        $error = fn (int $status, string $type, string $message) => new Response($status, ['Content-Type' => 'application/json'],
            json_encode(['type' => 'error', 'error' => ['type' => $type, 'message' => $message]]));

        foreach ([
            'key' => $error(401, 'authentication_error', 'invalid x-api-key'),
            'credit' => $error(400, 'invalid_request_error', 'Your credit balance is too low to access the Anthropic API.'),
            'busy' => $error(529, 'overloaded_error', 'Overloaded'),
            'failed' => $error(400, 'invalid_request_error', 'messages: field required'),
        ] as $reason => $response) {
            $this->sent = [];
            try {
                $this->provider($response)->generate(Ai::request('client_minutes', []));
                $this->fail("Expected AiUnavailable({$reason})");
            } catch (AiUnavailable $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
    }

    public function test_no_key_no_request(): void
    {
        $provider = new ClaudeProvider(new Guzzle(['handler' => HandlerStack::create(new MockHandler([]))]));

        $this->assertFalse($provider->configured());
        $this->expectExceptionObject(new AiUnavailable('not_configured'));
        $provider->generate(Ai::request('client_minutes', []));
    }
}
