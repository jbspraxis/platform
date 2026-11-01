<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The client asked for changes to a version of the proposal in the portal (docs/OPPORTUNITIES.md 8.5): their
 * comments are on it, and the lead starts the next version.
 */
class ProposalChangesRequested
{
    use Dispatchable;

    public function __construct(
        public readonly string $proposalId,
        public readonly string $opportunityId,
    ) {
    }
}
