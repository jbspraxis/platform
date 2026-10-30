<?php

namespace App\Exceptions\Hq;

use RuntimeException;

/**
 * Base for every failure talking to the Site API on HQ. Pages turn these into calm messages
 * (bootstrap/app.php); the message text here is for logs only and never holds a secret.
 */
abstract class HqApiException extends RuntimeException
{
}
