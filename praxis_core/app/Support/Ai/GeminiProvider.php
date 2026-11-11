<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini for any purpose config/ai.php gives provider 'gemini' (the website chat and Mail keep their own clients).
 * The key travels in a header, never in the URL; the answer is JSON (responseMimeType) and is checked against the
 * schema like Claude's. Its tokens are logged in ai_runs; Google bills them, not the Claude credit.
 */
class GeminiProvider implements AiProvider
{
    public function configured(): bool
    {
        return (string) config('services.gemini.api_key') !== '';
    }

    public function generate(AiRequest $request, ?callable $progress = null): AiResult
    {
        if (! $this->configured()) {
            throw new AiUnavailable('not_configured');
        }
        $model = str_starts_with($request->model, 'gemini') ? $request->model : (string) config('ai.providers.gemini.model');

        try {
            // A page waiting for the answer (AiRequest::$sync) waits AiRequest::SYNC_TIMEOUT seconds at most
            $timeout = $request->sync ? AiRequest::SYNC_TIMEOUT : (int) config('ai.providers.gemini.timeout', 120);
            $response = Http::timeout($timeout)->connectTimeout(5)->withHeaders([
                'x-goog-api-key' => config('services.gemini.api_key'),
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => array_map(fn ($b) => ['text' => $b], array_values($request->system))],
                'contents' => [['role' => 'user', 'parts' => [
                    ...array_map(fn (array $a) => ['inline_data' => ['mime_type' => $a['media_type'], 'data' => $a['data']]], $request->attachments),
                    ['text' => $request->prompt . "\n\nAnswer with JSON only, following this JSON schema:\n" . json_encode($request->schema, JSON_UNESCAPED_SLASHES)],
                ]]],
                'generationConfig' => ['maxOutputTokens' => $request->maxTokens, 'responseMimeType' => 'application/json'],
            ]);
        } catch (ConnectionException $e) {
            throw new AiUnavailable('unreachable', previous: $e);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new AiUnavailable('key');
        }
        if (in_array($response->status(), [429, 500, 503], true)) {
            throw new AiUnavailable('busy');
        }
        if (! $response->successful()) {
            Log::warning('AI: Gemini returned an error', ['purpose' => $request->purpose, 'model' => $model, 'status' => $response->status()]);
            throw new AiUnavailable('failed');
        }

        $finish = (string) $response->json('candidates.0.finishReason');
        $usage = $response->json('usageMetadata') ?? [];
        $cached = (int) ($usage['cachedContentTokenCount'] ?? 0);

        return new AiResult(
            text: $response->json('candidates.0.content.parts.0.text'),
            stopReason: match ($finish) {
                'MAX_TOKENS' => 'max_tokens',
                'SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION' => 'refusal',
                default => 'end_turn',
            },
            model: $model,
            inputTokens: max(0, (int) ($usage['promptTokenCount'] ?? 0) - $cached),
            outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0),
            cacheReadTokens: $cached,
            refusalCategory: in_array($finish, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'], true) ? strtolower($finish) : null,
        );
    }
}
