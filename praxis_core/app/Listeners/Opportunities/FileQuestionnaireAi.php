<?php

namespace App\Listeners\Opportunities;

use App\Events\Ai\AiRunFinished;
use App\Support\Opportunities\Questionnaires;

/** A questionnaire draft finished: its questions go in at once and the lead gets a bell */
class FileQuestionnaireAi
{
    public function handle(AiRunFinished $event): void
    {
        Questionnaires::fileAiOutput($event->run);
    }
}
