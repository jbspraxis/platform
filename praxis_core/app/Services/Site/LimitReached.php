<?php

namespace App\Services\Site;

use RuntimeException;

/**
 * A visitor, an identity (an email address) or everyone together has used the allowance for an action
 * (App\Services\Site\SiteLimits). The site API answers 429 too_many with retry_after.
 * Expected behaviour, so never reported as an error.
 */
final class LimitReached extends RuntimeException
{
    /**
     * @param  string  $scope  visitor, email, global, ...
     */
    public function __construct(public readonly string $action, public readonly string $scope, public readonly int $retryAfter)
    {
        parent::__construct("Website limit reached: {$action} ({$scope})");
    }

    /** Not an error: nothing goes to the log or to System health */
    public function report(): void
    {
    }
}
