<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The lead marked an opportunity's questionnaire complete (docs/OPPORTUNITIES.md 8.5): its answers are locked and
 * the proposal (PR 4) reads them through OpportunityContext.
 */
class QuestionnaireCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $questionnaireId,
        public readonly string $opportunityId,
        public readonly ?string $completedBy,
    ) {
    }
}
