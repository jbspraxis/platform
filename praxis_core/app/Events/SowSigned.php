<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The client signed a version of the SOW with the one-time code (docs/OPPORTUNITIES.md 8.5, decision 10): the
 * opportunity is Won, the deposit invoice is raised on the project made for it, and the project draft is queued.
 */
class SowSigned
{
    use Dispatchable;

    public function __construct(
        public readonly string $sowId,
        public readonly string $opportunityId,
        public readonly string $projectId,
    ) {
    }
}
