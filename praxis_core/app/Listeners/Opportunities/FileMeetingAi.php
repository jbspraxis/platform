<?php

namespace App\Listeners\Opportunities;

use App\Events\Ai\AiRunFinished;
use App\Support\Opportunities\Transcripts;

/** A meeting's notes or minutes finished: they go onto the transcript */
class FileMeetingAi
{
    public function handle(AiRunFinished $event): void
    {
        Transcripts::fileAiOutput($event->run);
    }
}
