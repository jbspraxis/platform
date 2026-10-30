<?php

namespace App\Exceptions\Hq;

use Throwable;

/**
 * 409 from HQ: "slot_taken" when a booking time has just gone, "already_booked" when this email or
 * organisation already has an open request. $hqMessage is HQ's calm text in the visitor's language.
 */
class HqConflict extends HqApiException
{
    public function __construct(
        public readonly string $errorCode = 'conflict',
        public readonly ?string $hqMessage = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct('HQ conflict: ' . $errorCode, 409, $previous);
    }
}
