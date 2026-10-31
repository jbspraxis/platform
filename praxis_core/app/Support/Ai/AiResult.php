<?php

namespace App\Support\Ai;

/**
 * A provider's answer. $text is the JSON the schema asked for (null when nothing usable came back);
 * $stopReason is end_turn, max_tokens or refusal (with $refusalCategory). $iterations lists the token use per
 * model when a request ran on more than one (Claude's server-side fallback), for the cost.
 *
 * @phpstan-type Usage array{model: string, input: int, output: int, cache_read: int, cache_write: int}
 */
final class AiResult
{
    /**
     * @param  list<Usage>  $iterations
     */
    public function __construct(
        public readonly ?string $text,
        public readonly string $stopReason,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheReadTokens = 0,
        public readonly int $cacheWriteTokens = 0,
        public readonly array $iterations = [],
        public readonly ?string $refusalCategory = null,
    ) {}

    /** @return list<Usage> */
    public function usage(): array
    {
        return $this->iterations ?: [[
            'model' => $this->model, 'input' => $this->inputTokens, 'output' => $this->outputTokens,
            'cache_read' => $this->cacheReadTokens, 'cache_write' => $this->cacheWriteTokens,
        ]];
    }
}
