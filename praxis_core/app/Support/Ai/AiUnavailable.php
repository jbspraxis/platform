<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * An AI job can't run or didn't produce a usable answer. $reason picks the message (lang/{en,fr}/ai.php errors):
 *
 *   budget          the month's AI budget is spent        not_configured  no key in System › Integrations
 *   key             the provider refused the key          credit          the Claude credit has run out
 *   busy            the provider is overloaded            unreachable     the provider could not be reached
 *   refused         the model declined the request        too_long        the answer hit its length limit
 *   invalid         the answer didn't follow the schema   stuck           the worker stopped (Ai::expireStuck)
 *   failed          anything else
 */
class AiUnavailable extends RuntimeException
{
    public const REASONS = ['budget', 'not_configured', 'key', 'credit', 'busy', 'unreachable', 'refused', 'too_long', 'invalid', 'stuck', 'failed'];

    public function __construct(public readonly string $reason, public readonly array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct(self::message($reason, $params), 0, $previous);
    }

    /** The translation key stored in ai_runs.error */
    public function key(): string
    {
        return 'ai.errors.' . (in_array($this->reason, self::REASONS, true) ? $this->reason : 'failed');
    }

    public static function message(string $reason, array $params = []): string
    {
        return __('ai.errors.' . (in_array($reason, self::REASONS, true) ? $reason : 'failed'), $params);
    }
}
