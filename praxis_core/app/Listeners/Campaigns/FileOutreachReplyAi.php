<?php

namespace App\Listeners\Campaigns;

use App\Events\Ai\AiRunFinished;
use App\Support\Campaigns\Outreach\Sorting;

/** Claude's label for an outreach reply finished (or failed): it goes onto the reply, for a person to confirm */
class FileOutreachReplyAi
{
    public function handle(AiRunFinished $event): void
    {
        Sorting::file($event->run);
    }
}
