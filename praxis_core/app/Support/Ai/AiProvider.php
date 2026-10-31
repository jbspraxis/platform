<?php

namespace App\Support\Ai;

/** A model provider behind App\Support\Ai\Ai (Claude, Gemini; Tests\Support\Ai\FakeAi in tests) */
interface AiProvider
{
    /** Whether a key is set */
    public function configured(): bool;

    /**
     * Run the request. $progress receives 0–100 while a streamed answer arrives.
     *
     * @throws AiUnavailable when the provider refuses or can't be reached (key, credit, busy, unreachable, failed)
     */
    public function generate(AiRequest $request, ?callable $progress = null): AiResult;
}
