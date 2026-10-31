<?php

namespace App\Support\Ai;

use InvalidArgumentException;

/** Which class answers for a provider name in config/ai.php. Tests\Support\Ai\FakeAi replaces this registry. */
class Providers
{
    public function for(string $provider): AiProvider
    {
        return match ($provider) {
            'claude' => app(ClaudeProvider::class),
            'gemini' => app(GeminiProvider::class),
            default => throw new InvalidArgumentException("Unknown AI provider '{$provider}'"),
        };
    }
}
