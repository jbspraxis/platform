<?php

namespace App\Listeners\Opportunities;

use App\Events\Ai\AiRunFinished;
use App\Support\Opportunities\Proposals;

/** A proposal draft (or its "what changed") finished: it goes in at once, and a draft rings the lead */
class FileProposalAi
{
    public function handle(AiRunFinished $event): void
    {
        Proposals::fileAiOutput($event->run);
    }
}
