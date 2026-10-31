<?php

namespace App\Support\Ai;

use App\Models\AiRun;
use App\Support\Integrations;
use Carbon\CarbonImmutable;

/**
 * The monthly AI budget (docs/OPPORTUNITIES.md 6.2): what ai_runs cost this month against the cap the owner sets in
 * System › Integrations (ai.monthly_budget_usd, $5 to start). A job that could take the month over the cap doesn't
 * start, so HQ stops before the Claude Console's own spend limit is hit. Months run on Lagos time.
 */
final class Budget
{
    public static function cap(): float
    {
        return max(0.0, (float) (Integrations::get('ai.monthly_budget_usd') ?? config('ai.monthly_budget_usd', 5)));
    }

    public static function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::now('Africa/Lagos')->startOfMonth()->utc();
    }

    public static function spent(): float
    {
        return round((float) AiRun::where('created_at', '>=', self::monthStart())->sum('cost_usd'), 6);
    }

    /** @return array<string, array{runs: int, cost: float}> this month, per purpose, dearest first */
    public static function spentByPurpose(): array
    {
        return AiRun::where('created_at', '>=', self::monthStart())
            ->selectRaw('purpose, COUNT(*) AS runs, SUM(cost_usd) AS cost')->groupBy('purpose')->orderByDesc('cost')->get()
            ->mapWithKeys(fn ($r) => [$r->purpose => ['runs' => (int) $r->runs, 'cost' => round((float) $r->cost, 6)]])->all();
    }

    public static function reached(): bool
    {
        return self::spent() >= self::cap();
    }

    /** The most a request can cost: its input (about 3.5 characters a token, none of it cached) plus max_tokens of output */
    public static function worstCase(AiRequest $request): float
    {
        $input = (int) ceil($request->inputChars() / 3.5);

        return Pricing::cost([['model' => $request->model, 'input' => $input, 'output' => $request->maxTokens, 'cache_read' => 0, 'cache_write' => 0]]);
    }

    public static function allows(AiRequest $request): bool
    {
        return self::spent() + self::worstCase($request) <= self::cap();
    }
}
