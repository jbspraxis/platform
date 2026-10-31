<?php

namespace App\Support\Ai;

/** What a run cost in US dollars, from config/ai.php prices (per million tokens) */
final class Pricing
{
    /**
     * @param  list<array{model: string, input: int, output: int, cache_read: int, cache_write: int}>  $usage
     */
    public static function cost(array $usage): float
    {
        $total = 0.0;
        foreach ($usage as $u) {
            $rates = self::rates($u['model'], $u['input'] + $u['cache_read'] + $u['cache_write']);
            $total += ($u['input'] * $rates['input'] + $u['output'] * $rates['output']
                + $u['cache_read'] * $rates['cache_read'] + $u['cache_write'] * $rates['cache_write']) / 1_000_000;
        }

        return round($total, 6);
    }

    /** The model's rates for a prompt of $promptTokens; an unknown model gets the dearest known rates */
    public static function rates(string $model, int $promptTokens = 0): array
    {
        $prices = config('ai.prices', []);
        $rates = $prices[$model] ?? collect($prices)->sortByDesc('output')->first() ?? ['input' => 5, 'output' => 25, 'cache_read' => 0.5, 'cache_write' => 6.25];
        if (isset($rates['long_context']) && $promptTokens > $rates['long_context']['above']) {
            $rates = $rates['long_context'] + $rates;
        }

        return $rates;
    }
}
