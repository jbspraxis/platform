<?php

namespace App\Listeners\Campaigns;

use App\Events\Ai\AiRunFinished;
use App\Support\Campaigns\Outreach\Drafts;

/** An outreach email's draft finished (or failed): it goes onto the prospect's two emails */
class FileOutreachAi
{
    public function handle(AiRunFinished $event): void
    {
        Drafts::file($event->run);
    }
}
