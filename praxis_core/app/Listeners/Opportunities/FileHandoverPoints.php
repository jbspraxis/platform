<?php

namespace App\Listeners\Opportunities;

use App\Events\Ai\AiRunFinished;
use App\Support\Opportunities\Handovers;

/** A handover meeting's points finished drafting: they wait on the meeting for the lead */
class FileHandoverPoints
{
    public function handle(AiRunFinished $event): void
    {
        Handovers::fileAiOutput($event->run);
    }
}
