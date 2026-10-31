<?php

namespace App\Support\SignIn;

use RuntimeException;

/**
 * A passkey answer that was not accepted. $reason picks the sentence people see (lang sign_in.passkey.*):
 * failed, expired, unknown, inactive, already_added, too_many. The detail stays in the log.
 */
final class PasskeyRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
