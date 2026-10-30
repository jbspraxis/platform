<?php

namespace App\Events\Mail;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Two or more conversations turned out to be one (a reply arrived that links them). Their messages, notes,
 * activity and outbox rows already point at $intoId and the others are deleted; anything else that keeps
 * thread ids (PR 2–4 caches, open screens) should follow.
 */
class MailThreadsMerged
{
    use Dispatchable;

    /** @param  list<int>  $fromIds */
    public function __construct(
        public readonly array $fromIds,
        public readonly int $intoId,
    ) {
    }
}
