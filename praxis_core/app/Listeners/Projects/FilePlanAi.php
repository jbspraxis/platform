<?php

namespace App\Listeners\Projects;

use App\Events\Ai\AiRunFinished;
use App\Support\Projects\Planner;

/** A planning job finished (Plan milestones, PR 5b): its suggestions go in at once, or its second pass starts */
class FilePlanAi
{
    public function handle(AiRunFinished $event): void
    {
        Planner::fileAiOutput($event->run);
    }
}
