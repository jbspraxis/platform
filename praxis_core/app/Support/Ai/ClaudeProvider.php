<?php

namespace App\Support\Ai;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\RequestOptions;
use App\Support\Integrations;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;

/**
 * Claude through the official PHP SDK (anthropic-ai/sdk, docs/OPPORTUNITIES.md 6.2). Every request: adaptive
 * thinking, the purpose's effort, structured output with its JSON schema, the system blocks cached (company brief,
 * then opportunity context), no temperature. Long documents stream. On Sonnet, a request its safety checks
 * decline is retried server-side on another model (fallbacks: 'default'); Haiku has no fallback, so its decline
 * comes back as stop_reason refusal. The key comes from System › Integrations (ANTHROPIC_API_KEY as a fallback).
 * Nothing here logs a prompt, an answer or the key.
 */
class ClaudeProvider implements AiProvider
{
    /** $transporter: a PSR-18 client for tests (Guzzle's MockHandler); null = the SDK's own discovery */
    public function __construct(private readonly ?ClientInterface $transporter = null) {}

    public function configured(): bool
    {
        return Integrations::get('anthropic.api_key') !== null;
    }

    public function generate(AiRequest $request, ?callable $progress = null): AiResult
    {
        $key = Integrations::get('anthropic.api_key');
        if ($key === null) {
            throw new AiUnavailable('not_configured');
        }

        $client = new Client(
            apiKey: $key,
            requestOptions: RequestOptions::with(
                timeout: (float) config('ai.providers.claude.timeout', 600),
                maxRetries: (int) config('ai.providers.claude.max_retries', 2),
                transporter: $this->transporter,
                streamingTransporter: $this->transporter,
            ),
            openTelemetry: false,
        );

        $params = $this->params($request);

        try {
            $message = $request->stream ? $this->stream($client, $params, $request, $progress) : $client->beta->messages->create(...$params);
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw new AiUnavailable('key', previous: $e);
        } catch (RateLimitException $e) {
            throw new AiUnavailable('busy', previous: $e);
        } catch (BadRequestException $e) {
            // The Console answers 400 "credit balance is too low" when the prepaid credit has run out
            if (stripos($e->getMessage(), 'credit') !== false) {
                throw new AiUnavailable('credit', previous: $e);
            }
            Log::warning('AI: Claude refused the request', ['purpose' => $request->purpose, 'model' => $request->model, 'status' => 400]);
            throw new AiUnavailable('failed', previous: $e);
        } catch (InternalServerException $e) {
            throw new AiUnavailable('busy', previous: $e);
        } catch (APIStatusException $e) {
            Log::warning('AI: Claude returned an error', ['purpose' => $request->purpose, 'model' => $request->model, 'status' => $e->status]);
            throw new AiUnavailable('failed', previous: $e);
        } catch (APIConnectionException $e) {
            throw new AiUnavailable('unreachable', previous: $e);
        }

        return $this->result($message, $request->model);
    }

    /** The named arguments of messages->create(): wire names in camelCase, as the SDK takes them */
    public function params(AiRequest $request): array
    {
        $params = [
            'model' => $request->model,
            'maxTokens' => $request->maxTokens,
            'system' => array_values(array_map(
                fn (string $block) => ['type' => 'text', 'text' => $block, 'cacheControl' => ['type' => 'ephemeral']],
                array_filter($request->system, fn ($b) => trim($b) !== ''),
            )),
            'messages' => [['role' => 'user', 'content' => $request->prompt]],
            'thinking' => ['type' => 'adaptive'],
            'outputConfig' => [
                'effort' => $request->effort,
                'format' => ['type' => 'json_schema', 'schema' => $request->schema],
            ],
        ];
        if (! $params['system']) {
            unset($params['system']);
        }
        if (in_array($request->model, (array) config('ai.providers.claude.fallback_models', []), true)) {
            $params['fallbacks'] = 'default';
            $params['betas'] = [config('ai.providers.claude.fallback_beta')];
        }

        return $params;
    }

    private function stream(Client $client, array $params, AiRequest $request, ?callable $progress): BetaMessage
    {
        $accumulator = MessageAccumulator::forBetaMessages();
        $chars = 0;
        foreach ($client->beta->messages->createStream(...$params) as $event) {
            $accumulator->accumulate($event);
            if ($progress && ($event->type ?? null) === 'content_block_delta' && ($event->delta->type ?? null) === 'text_delta') {
                $chars += mb_strlen($event->delta->text);
                $progress(min(95, 10 + (int) (85 * $chars / max(1, $request->expectedChars))));
            }
        }

        $message = $accumulator->message();
        assert($message instanceof BetaMessage);

        return $message;
    }

    /** $requested: the model asked for, which a 'message' iteration ran on (its usage names no model) */
    private function result(BetaMessage $message, string $requested): AiResult
    {
        $text = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }

        // Per model when the request ran on more than one (a server-side fallback); else the totals
        $iterations = [];
        foreach ($message->usage->iterations ?? [] as $it) {
            if (in_array($it->type ?? null, ['message', 'fallback_message'], true)) {
                $iterations[] = [
                    'model' => (string) ($it->model ?? $requested),
                    'input' => (int) $it->inputTokens,
                    'output' => (int) $it->outputTokens,
                    'cache_read' => (int) ($it->cacheReadInputTokens ?? 0),
                    'cache_write' => (int) ($it->cacheCreationInputTokens ?? 0),
                ];
            }
        }

        return new AiResult(
            text: trim($text) === '' ? null : $text,
            stopReason: (string) ($message->stopReason ?? 'end_turn'),
            model: $message->model,
            inputTokens: $message->usage->inputTokens,
            outputTokens: $message->usage->outputTokens,
            cacheReadTokens: (int) ($message->usage->cacheReadInputTokens ?? 0),
            cacheWriteTokens: (int) ($message->usage->cacheCreationInputTokens ?? 0),
            iterations: $iterations,
            refusalCategory: $message->stopDetails?->category,
        );
    }
}
