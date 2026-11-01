<?php

namespace App\Support\Signing;

use Illuminate\Support\Facades\RateLimiter;

/**
 * The one-time code that signs a document in the portal (moved out of Portal\ProposalGateway for the SOW, PR 5):
 * a 6-digit code, emailed, valid 10 minutes. At most 3 codes every 15 minutes per document and per email address
 * (whichever device asks), and 5 wrong codes per document; the fifth also cancels the code, so it can't be guessed
 * after the wait. $kind names the document ('proposal' for legacy proposals, 'sow'); the email limit is shared,
 * since it protects one mailbox. Callers store the code on their row and turn the reasons into their own words.
 */
final class SigningCodes
{
    public const MAX_CODES = 3;

    public const MAX_WRONG_CODES = 5;

    public const LOCK_SECONDS = 900;

    /** How long a code works */
    public const MINUTES = 10;

    public static function sendKey(string $kind, string $id): string
    {
        return "{$kind}-otp-send:{$id}";
    }

    /** The email address is hashed so it isn't stored in the cache */
    public static function sendKeyForEmail(string $email): string
    {
        return 'proposal-otp-send-email:' . sha1(mb_strtolower(trim($email)));
    }

    public static function checkKey(string $kind, string $id): string
    {
        return "{$kind}-otp-check:{$id}";
    }

    public static function newCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    /**
     * Counts a code about to be sent. Null when it may go; else [reason, minutes to wait], the reason being
     * too_many_codes (this document) or too_many_codes_email (this address).
     *
     * @return array{string, int}|null
     */
    public static function claimSend(string $kind, string $id, string $email): ?array
    {
        $keys = ['too_many_codes' => self::sendKey($kind, $id), 'too_many_codes_email' => self::sendKeyForEmail($email)];
        foreach ($keys as $reason => $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_CODES)) {
                return [$reason, self::waitMinutes($key)];
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
        }

        return null;
    }

    /**
     * Checks an entered code against the one stored. Null when it is right (the wrong-code count is cleared); else
     * [reason, minutes to wait]: locked (too many wrong codes: wait), cancel (that was the fifth wrong code: the
     * caller clears the stored code) or wrong.
     *
     * @return array{string, int}|null
     */
    public static function check(string $kind, string $id, ?string $stored, $expiresAt, string $entered): ?array
    {
        $key = self::checkKey($kind, $id);
        if (RateLimiter::tooManyAttempts($key, self::MAX_WRONG_CODES)) {
            return ['locked', self::waitMinutes($key)];
        }
        if (! $stored || ! hash_equals($stored, $entered) || ! $expiresAt || now()->greaterThan($expiresAt)) {
            RateLimiter::hit($key, self::LOCK_SECONDS);

            return RateLimiter::tooManyAttempts($key, self::MAX_WRONG_CODES) ? ['cancel', self::waitMinutes($key)] : ['wrong', 0];
        }
        RateLimiter::clear($key);

        return null;
    }

    /** Minutes until the limiter lets the person try again (at least 1) */
    public static function waitMinutes(string $key): int
    {
        return max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
    }
}
