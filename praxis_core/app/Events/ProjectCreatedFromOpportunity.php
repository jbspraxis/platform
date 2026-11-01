<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The lead clicked Create project on the project draft (docs/OPPORTUNITIES.md 8.5): its plan is written and the opportunity points at it */
class ProjectCreatedFromOpportunity
{
    use Dispatchable;

    public function __construct(
        public readonly string $projectId,
        public readonly string $opportunityId,
        public readonly string $createdBy,
    ) {
    }
}
