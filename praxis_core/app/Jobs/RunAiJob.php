<?php

namespace App\Jobs;

use App\Models\AiRun;
use App\Support\Ai\Ai;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One AI job on the 'ai' queue (App\Support\Ai\Ai::queue). Tried once: a second try would pay for the whole
 * document again ("one draft per click", docs/OPPORTUNITIES.md 6.2); the person who asked clicks again instead.
 */
class RunAiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $runId) {}

    public function handle(): void
    {
        $run = AiRun::find($this->runId);
        if ($run) {
            Ai::run($run);
        }
    }

    public function failed(?Throwable $e): void
    {
        AiRun::whereKey($this->runId)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'error' => 'ai.errors.failed', 'finished_at' => now()]);
    }
}
