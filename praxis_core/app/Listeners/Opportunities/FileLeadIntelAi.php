<?php

namespace App\Listeners\Opportunities;

use App\Events\Ai\AiRunFinished;
use App\Support\Leads\LeadIntel;

/** An intel report finished being read: Claude's layout is filed on the report, ready for a person to check */
class FileLeadIntelAi
{
    public function handle(AiRunFinished $event): void
    {
        LeadIntel::fileOutput($event->run);
    }
}
