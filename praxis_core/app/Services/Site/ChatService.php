<?php

namespace App\Services\Site;

use App\Support\WebsiteSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The website's AI assistant: Praxis AI on the site API (POST /chat, App\Services\Site\PraxisAi).
 * Its budget keeps the old chat widget's limiter keys (docs/SITE_API.md section 6.2): 10 questions per
 * 10 minutes and 30 a day per visitor, 20 per conversation, 300 an hour for everyone, and the AI budget of
 * 500 model calls a day shared with the calculator. Gemini first, DeepSeek if Gemini is busy or fails;
 * keys and models come from .env through config/services.php and never leave HQ. Log lines keep the
 * "AiAgent:" prefix, which System Health counts (App\Support\Monitor\HealthChecks).
 */
final class ChatService
{
    /** How many recent turns the AI sees */
    public const HISTORY_TURNS = 12;

    // What Praxis AI says on the site API, in the visitor's language (the pages go in "links")
    private const LIMIT = [
        'en' => 'You’ve reached the Praxis AI limit for now. Try again later, or book a free audit and demo.',
        'fr' => 'Vous avez atteint la limite de Praxis AI pour le moment. Réessayez plus tard, ou réservez un audit et une démonstration offerts.',
    ];

    private const FALLBACK = [
        'en' => 'Praxis AI isn’t available right now. Book a free audit and demo, or email us at :email.',
        'fr' => 'Praxis AI n’est pas disponible pour le moment. Réservez un audit et une démonstration offerts, ou écrivez-nous à :email.',
    ];

    /** Praxis AI's reply when the visitor (or everyone) has used the chat budget */
    public static function limitMessage(string $locale): string
    {
        return self::LIMIT[$locale] ?? self::LIMIT['en'];
    }

    /** Praxis AI's reply when no AI provider answers; the email comes from Website settings */
    public static function fallbackMessage(string $locale): string
    {
        $email = WebsiteSettings::publicPayload()['contact']['email'] ?? null;

        return str_replace(':email', $email ?: 'info@jbspraxis.com', self::FALLBACK[$locale] ?? self::FALLBACK['en']);
    }

    /** Links that go with the limit and fallback replies */
    public static function limitLinks(string $locale): array
    {
        return PraxisAi::links(['/schedule'], $locale);
    }

    public static function fallbackLinks(string $locale): array
    {
        return PraxisAi::links(['/schedule', '/contact'], $locale);
    }

    /**
     * True when this visitor may not ask now: past the conversation's questions ($questionNumber counts the new
     * one), the visitor's or everyone's allowance, or the day's AI budget.
     */
    public function limitReached(string $ip, int $questionNumber): bool
    {
        if ($questionNumber > (int) config('site_api.chat_questions_per_conversation', 20) || !SiteLimits::aiBudgetLeft()) {
            return true;
        }
        try {
            SiteLimits::check('chat', $ip);
        } catch (LimitReached $e) {
            return true;
        }

        return false;
    }

    /** Counts one question against the visitor's and everyone's budget */
    public function hit(string $ip): void
    {
        SiteLimits::hit('chat', $ip);
    }

    /**
     * Praxis AI's answer on the site API: the reply (light markdown) and up to 3 site map links, or null when no
     * provider answers within the time HQ allows on this path (25 s in all).
     *
     * @return array{reply: string, links: list<array{label: string, path: string}>}|null
     */
    public function praxisAi(array $geminiHistory, string $locale): ?array
    {
        $text = $this->ask(PraxisAi::systemPrompt($locale), $geminiHistory, (float) config('site_api.chat_ai_seconds', 25));
        if ($text === null) {
            return null;
        }
        $answer = PraxisAi::shape($text, $locale);

        return $answer['reply'] === '' ? null : $answer;
    }

    /**
     * Site API history (role user|assistant, text) plus the new question, in Gemini's format: the last
     * HISTORY_TURNS turns, tags stripped, as the old widget sent them.
     */
    public static function geminiHistory(array $history, string $message): array
    {
        $turns = [];
        foreach ($history as $turn) {
            $turns[] = ['role' => ($turn['role'] ?? '') === 'user' ? 'user' : 'model', 'parts' => [['text' => strip_tags((string) ($turn['text'] ?? ''))]]];
        }
        $turns[] = ['role' => 'user', 'parts' => [['text' => strip_tags($message)]]];

        return array_values(array_slice($turns, -self::HISTORY_TURNS));
    }

    /**
     * One AI answer: Gemini first, DeepSeek if Gemini is busy or fails (two providers, so one outage cannot
     * take the chat down). Each call comes out of the shared daily AI budget. With $seconds, all calls together
     * stay within that time.
     */
    private function ask(string $systemPrompt, array $history, ?float $seconds): ?string
    {
        $providers = array_filter([
            config('services.gemini.api_key') ? ['gemini', config('services.gemini.chat_model'), 12] : null,
            config('services.deepseek.api_key') ? ['deepseek', config('services.deepseek.model'), 20] : null,
        ]);

        if (empty($providers)) {
            Log::error('AiAgent: no AI provider key is set (GEMINI_API_KEY / DEEPSEEK_API_KEY).');
        }

        @set_time_limit(60);
        $deadline = $seconds !== null ? microtime(true) + $seconds : null;
        $aiText = null;
        foreach ($providers as [$provider, $model, $timeout]) {
            if ($deadline !== null) {
                $left = (int) floor($deadline - microtime(true));
                if ($left < 2) {
                    break;
                }
                $timeout = min($timeout, $left);
            }
            if (!SiteLimits::aiCall()) {
                break; // the day's AI budget is used up (System health has the alert)
            }
            try {
                $aiText = $provider === 'gemini'
                    ? $this->askGemini($model, $timeout, $systemPrompt, $history)
                    : $this->askDeepSeek($model, $timeout, $systemPrompt, $history);
                if ($aiText) {
                    break;
                }
            } catch (\Exception $e) {
                Log::warning('AiAgent: AI request error', ['provider' => $provider, 'model' => $model, 'message' => $e->getMessage()]);
            }
        }

        return $aiText ?: null;
    }

    // Gemini generateContent; the key travels in a header, not in the URL
    private function askGemini(string $model, int $timeout, string $systemPrompt, array $history): ?string
    {
        $response = Http::timeout($timeout)->connectTimeout(min(5, $timeout))->withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => config('services.gemini.api_key'),
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
            "system_instruction" => [
                "parts" => [["text" => $systemPrompt]]
            ],
            "contents" => $history,
            "generationConfig" => [
                "temperature" => 0.25,
                "maxOutputTokens" => 800,
            ]
        ]);

        $text = $response->successful() ? $response->json('candidates.0.content.parts.0.text') : null;
        if (! $text) {
            Log::warning('AiAgent: Gemini request failed', ['model' => $model, 'status' => $response->status(), 'error' => $response->json('error.message')]);
        }
        return $text;
    }

    // DeepSeek chat completions (OpenAI format); history is converted from Gemini's format
    private function askDeepSeek(string $model, int $timeout, string $systemPrompt, array $history): ?string
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'user' ? 'user' : 'assistant',
                'content' => $turn['parts'][0]['text'] ?? '',
            ];
        }

        $response = Http::timeout($timeout)->connectTimeout(min(5, $timeout))
            ->withToken(config('services.deepseek.api_key'))
            ->post(rtrim(config('services.deepseek.base_url'), '/') . '/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.25,
                'max_tokens' => 800,
            ]);

        $text = $response->successful() ? $response->json('choices.0.message.content') : null;
        if (! $text) {
            Log::warning('AiAgent: DeepSeek request failed', ['model' => $model, 'status' => $response->status(), 'error' => $response->json('error.message')]);
        }
        return $text;
    }
}
