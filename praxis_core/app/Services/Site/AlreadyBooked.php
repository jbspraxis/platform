<?php

namespace App\Services\Site;

use RuntimeException;

/**
 * The visitor's email or organisation already has a free audit request waiting for confirmation
 * (one open request each, docs/SITE_API.md section 6.4). The site API answers 409 already_booked.
 */
final class AlreadyBooked extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A free audit request is already waiting for this email or organisation');
    }

    /** Not an error: nothing goes to the log or to System health */
    public function report(): void
    {
    }
}
