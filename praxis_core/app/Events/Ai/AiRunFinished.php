<?php

namespace App\Events\Ai;

use App\Models\AiRun;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An AI job finished, done or failed (App\Support\Ai\Ai::run). Listeners file a purpose's output where it belongs,
 * so a job started by the scheduler lands without a screen open.
 */
class AiRunFinished
{
    use Dispatchable;

    public function __construct(public readonly AiRun $run) {}
}
