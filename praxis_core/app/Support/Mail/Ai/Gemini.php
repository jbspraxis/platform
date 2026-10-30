<?php

namespace App\Support\Mail\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gemini for mail (docs/MAIL.md 7.6): the only provider mail ever goes to, never DeepSeek. The key travels
 * in a header, never in the URL, and neither the key nor the mail is logged.
 */
final class Gemini
{
    public static function configured(): bool
    {
        return (string) config('services.gemini.api_key') !== '';
    }

    /** The model's text, or null when it is unavailable (logged without content) */
    public static function generate(string $system, string $prompt, int $maxTokens = 1024, bool $json = false): ?string
    {
        if (! self::configured()) {
            return null;
        }
        $model = (string) config('mail_team.ai_model', 'gemini-2.5-flash');
        $timeout = (int) config('mail_team.ai_timeout', 30);

        try {
            $response = Http::timeout($timeout)->connectTimeout(5)->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => config('services.gemini.api_key'),
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => array_filter([
                    'temperature' => 0.2,
                    'maxOutputTokens' => $maxTokens,
                    'responseMimeType' => $json ? 'application/json' : null,
                ]),
            ]);
        } catch (Throwable $e) {
            Log::warning('Mail AI: Gemini unreachable', ['model' => $model, 'error' => class_basename($e)]);

            return null;
        }

        $text = $response->successful() ? $response->json('candidates.0.content.parts.0.text') : null;
        if (! is_string($text) || trim($text) === '') {
            Log::warning('Mail AI: Gemini gave no answer', ['model' => $model, 'status' => $response->status()]);

            return null;
        }

        return $text;
    }

    /** Decoded JSON from a json=true answer (tolerates a ```json fence) */
    public static function json(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }
}
