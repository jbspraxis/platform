<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A version of an opportunity's proposal went to the client (docs/OPPORTUNITIES.md 8.5): the email with the PDF and
 * the portal link reached at least one person. Earlier versions the client had are superseded.
 */
class ProposalSent
{
    use Dispatchable;

    public function __construct(
        public readonly string $proposalId,
        public readonly string $opportunityId,
        public readonly ?string $sentBy,
    ) {
    }
}
