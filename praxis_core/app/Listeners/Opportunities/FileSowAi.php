<?php

namespace App\Listeners\Opportunities;

use App\Events\Ai\AiRunFinished;
use App\Support\Opportunities\ProjectDrafts;
use App\Support\Opportunities\Sows;

/** A SOW draft or a project draft finished: it goes in at once, and the lead gets a bell */
class FileSowAi
{
    public function handle(AiRunFinished $event): void
    {
        Sows::fileAiOutput($event->run);
        ProjectDrafts::fileAiOutput($event->run);
    }
}
