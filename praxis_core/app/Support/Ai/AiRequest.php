<?php

namespace App\Support\Ai;

/**
 * What a provider is asked: the system blocks (the company brief, then the opportunity context; each is a cache
 * breakpoint, so redrafts reread them at a tenth of the price), then one user message that holds everything that
 * changes (task, language, the lead's instructions, the material). The answer must follow $schema.
 */
final class AiRequest
{
    /**
     * @param  list<string>  $system
     */
    public function __construct(
        public readonly string $purpose,
        public readonly string $model,
        public readonly string $effort,
        public readonly int $maxTokens,
        public readonly array $system,
        public readonly string $prompt,
        public readonly array $schema,
        public readonly bool $stream = false,
        public readonly int $expectedChars = 4000,
    ) {}

    /** Characters sent, for the budget's estimate of the input tokens */
    public function inputChars(): int
    {
        return array_sum(array_map('mb_strlen', $this->system)) + mb_strlen($this->prompt) + mb_strlen((string) json_encode($this->schema));
    }
}
