<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The client approved a version of the proposal in the portal (docs/OPPORTUNITIES.md 8.5, decision 9): name, title
 * and email are on the proposal. The SOW (PR 5) is drafted from it and is what gets signed.
 */
class ProposalApprovedByClient
{
    use Dispatchable;

    public function __construct(
        public readonly string $proposalId,
        public readonly string $opportunityId,
    ) {
    }
}
