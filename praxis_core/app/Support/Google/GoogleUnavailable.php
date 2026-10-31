<?php

namespace App\Support\Google;

use RuntimeException;

/**
 * Google could not do what HQ asked (docs/OPPORTUNITIES.md 8.2). $reason says why; getMessage() is fit for a
 * toast, in the current language (lang/{en,fr}/integrations.php google.errors).
 *
 *   not_connected  no client or no connection yet          revoked  access was withdrawn: reconnect
 *   quota          Google's rate limit: try again later      scopes   a permission was not granted: reconnect
 *   not_found      the event, recording or file is gone      client   the client ID or secret is wrong
 *   failed         anything else (network, Google error)
 */
class GoogleUnavailable extends RuntimeException
{
    public const REASONS = ['not_connected', 'revoked', 'quota', 'scopes', 'not_found', 'client', 'failed'];

    public function __construct(public readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(__('integrations.google.errors.' . (in_array($reason, self::REASONS, true) ? $reason : 'failed')), 0, $previous);
    }
}
