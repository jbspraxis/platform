<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The signed page-load time every form carries (docs/SITE_API.md section 6.6): "<unix seconds>.<hmac>",
 * signed with the app key so it cannot be made up. A submit sooner than site.form_min_seconds after the
 * page loaded, or without a valid stamp, is treated like a filled honeypot.
 */
final class FormStamp
{
    public const FIELD = 'form_ts';

    public static function make(?CarbonInterface $at = null): string
    {
        $time = (string) ($at ?? now())->getTimestamp();

        return $time . '.' . self::mac($time);
    }

    /** Seconds since the page was loaded, or null when the stamp is missing or not ours. */
    public static function age(mixed $stamp): ?int
    {
        if (! is_string($stamp) || ! preg_match('/^(\d{9,11})\.([0-9a-f]{40})$/', $stamp, $m)) {
            return null;
        }
        if (! hash_equals(self::mac($m[1]), $m[2])) {
            return null;
        }

        return now()->getTimestamp() - (int) $m[1];
    }

    /** True when the stamp is valid and at least the minimum time has passed. */
    public static function passes(mixed $stamp): bool
    {
        $age = self::age($stamp);

        return $age !== null && $age >= (int) config('site.form_min_seconds', 3);
    }

    private static function mac(string $time): string
    {
        return substr(hash_hmac('sha256', 'form-stamp|' . $time, (string) config('app.key')), 0, 40);
    }
}
