<?php

namespace App\Exceptions\Hq;

use Throwable;

/** 429 from HQ: too many requests from this visitor (or for this key). $hqMessage is HQ's calm text, if any. */
class HqRateLimited extends HqApiException
{
    public function __construct(
        public readonly int $retryAfter = 60,
        public readonly ?string $hqMessage = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct('HQ rate limit reached', 429, $previous);
    }
}
