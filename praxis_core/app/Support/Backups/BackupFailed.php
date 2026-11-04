<?php

namespace App\Support\Backups;

use RuntimeException;

/**
 * A backup step failed. $stage says which (dump, restore_test, encrypt, upload, verify, archive, setup, key); the
 * message is fit for System health and the owner's bell, and never holds a password or backed-up data.
 */
class BackupFailed extends RuntimeException
{
    public function __construct(public readonly string $stage, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
