<?php

namespace App\Support\SignIn;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The 4-digit sign-in PIN (table sign_in_pins), for HQ and the Client Portal (docs/SIGN_IN.md section 4).
 *
 * Four digits are 10,000 codes, so a wrong PIN is counted like a wrong password and then some:
 *
 *  - the first 5 wrong in a row cost nothing; then each waits twice as long as the last (2, 4, 8 ... seconds,
 *    at most 15 minutes), counted from the last wrong try. Never a lockout of the account;
 *  - 10 wrong in a row switch the PIN off. The password still works, and the person sets a new PIN; nothing
 *    needs an administrator;
 *  - 20 wrong in any 30 days switch it off too. The right PIN ends a run but not the 30 days, so someone who
 *    keeps under ten while the owner's own sign-ins reset the run still runs out, at twenty guesses a month.
 *
 * With the common shapes refused when it is set (weakReason), ten tries is about a one in a thousand chance.
 */
final class Pins
{
    public const OFF_IN_A_ROW = 10;
    public const OFF_IN_WINDOW = 20;
    public const WINDOW_DAYS = 30;
    public const FREE_TRIES = 5;
    public const MAX_WAIT_SECONDS = 900;

    /** Codes at the top of every published list of real PINs, beyond the shapes weakReason refuses */
    private static ?string $dummy = null;

    private const COMMON = ['1004', '2000', '2001', '1998', '1999', '2468', '1357', '2580', '1212', '6969', '1122'];

    /**
     * Why a new PIN is refused (a key under sign_in.pin.weak), or null when it is fine.
     */
    public static function weakReason(string $pin): ?string
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            return 'digits';
        }
        $d = array_map('intval', str_split($pin));
        if (count(array_unique($d)) === 1) {
            return 'repeated';
        }
        $up = $down = true;
        for ($i = 1; $i < 4; $i++) {
            $up = $up && $d[$i] === $d[$i - 1] + 1;
            $down = $down && $d[$i] === $d[$i - 1] - 1;
        }
        if ($up || $down) {
            return 'run';
        }
        if ($d[0] === $d[2] && $d[1] === $d[3]) {
            return 'pair';
        }
        if ($d[0] === $d[1] && $d[2] === $d[3]) {
            return 'doubles';
        }
        if (in_array($pin, self::COMMON, true)) {
            return 'common';
        }

        return null;
    }

    /** 'on', 'off' (switched off by wrong tries: set a new one) or 'none' */
    public static function status(string $guard, string $ownerId): string
    {
        $row = self::row($guard, $ownerId);
        if (! $row) {
            return 'none';
        }

        return $row->pin_hash ? 'on' : ($row->switched_off_at ? 'off' : 'none');
    }

    /** Sets (or replaces) the PIN and starts its counts afresh. The caller has checked weakReason. */
    public static function set(string $guard, string $ownerId, string $pin): void
    {
        DB::table('sign_in_pins')->updateOrInsert(
            ['guard' => $guard, 'owner_id' => $ownerId],
            ['pin_hash' => Hash::make($pin), 'failed_count' => 0, 'last_failed_at' => null, 'recent_failures' => null,
                'switched_off_at' => null, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public static function remove(string $guard, string $ownerId): bool
    {
        return DB::table('sign_in_pins')->where('guard', $guard)->where('owner_id', $ownerId)->delete() > 0;
    }

    /**
     * Checks a PIN typed to sign in. The row is locked while it is checked and counted, so two tries at once are
     * both counted.
     *
     * @return array{result: 'ok'|'wrong'|'wait'|'switched_off'|'unavailable', wait: int, left: int}
     */
    public static function check(string $guard, string $ownerId, string $pin): array
    {
        return DB::transaction(function () use ($guard, $ownerId, $pin) {
            $row = DB::table('sign_in_pins')->where('guard', $guard)->where('owner_id', $ownerId)->lockForUpdate()->first();
            if (! $row || ! $row->pin_hash) {
                // The same work as a real check, so the answer's timing doesn't say whether a PIN exists
                self::spendTime($pin);

                return ['result' => 'unavailable', 'wait' => 0, 'left' => 0];
            }

            $now = CarbonImmutable::now();
            $wait = self::waitSeconds((int) $row->failed_count, $row->last_failed_at, $now);
            if ($wait > 0) {
                return ['result' => 'wait', 'wait' => $wait, 'left' => self::triesLeft($row, $now)];
            }

            if (Hash::check($pin, $row->pin_hash)) {
                DB::table('sign_in_pins')->where('id', $row->id)->update(['failed_count' => 0, 'last_failed_at' => null, 'updated_at' => now()]);

                return ['result' => 'ok', 'wait' => 0, 'left' => 0];
            }

            $failures = self::recentFailures($row, $now);
            $failures[] = $now->toIso8601String();
            $failures = array_slice($failures, -self::OFF_IN_WINDOW);
            $count = (int) $row->failed_count + 1;
            $off = $count >= self::OFF_IN_A_ROW || count($failures) >= self::OFF_IN_WINDOW;

            DB::table('sign_in_pins')->where('id', $row->id)->update([
                'failed_count' => $count,
                'last_failed_at' => $now,
                'recent_failures' => json_encode($failures),
                'pin_hash' => $off ? null : $row->pin_hash,
                'switched_off_at' => $off ? $now : null,
                'updated_at' => now(),
            ]);

            if ($off) {
                return ['result' => 'switched_off', 'wait' => 0, 'left' => 0];
            }

            return [
                'result' => 'wrong',
                'wait' => self::waitSeconds($count, $now, $now),
                'left' => max(0, min(self::OFF_IN_A_ROW - $count, self::OFF_IN_WINDOW - count($failures))),
            ];
        });
    }

    /** The work of one PIN check, for an email address that has no account: the answer takes as long either way */
    public static function spendTime(string $pin): void
    {
        Hash::check($pin, self::$dummy ??= Hash::make('dummy-pin'));
    }

    /** Seconds before the next try is allowed: nothing for the first 5 wrong, then 2, 4, 8 ... up to 15 minutes */
    public static function waitSeconds(int $failures, mixed $lastFailedAt, ?CarbonImmutable $now = null): int
    {
        if ($failures <= self::FREE_TRIES || ! $lastFailedAt) {
            return 0;
        }
        $now ??= CarbonImmutable::now();
        $required = min(2 ** ($failures - self::FREE_TRIES), self::MAX_WAIT_SECONDS);
        $elapsed = $now->getTimestamp() - CarbonImmutable::parse($lastFailedAt)->getTimestamp();

        return max(0, $required - $elapsed);
    }

    private static function triesLeft(object $row, CarbonImmutable $now): int
    {
        return max(0, min(self::OFF_IN_A_ROW - (int) $row->failed_count, self::OFF_IN_WINDOW - count(self::recentFailures($row, $now))));
    }

    /** @return list<string> wrong tries in the last 30 days */
    private static function recentFailures(object $row, CarbonImmutable $now): array
    {
        $since = $now->subDays(self::WINDOW_DAYS)->getTimestamp();
        $times = json_decode((string) $row->recent_failures, true);

        return array_values(array_filter(is_array($times) ? $times : [], fn ($t) => is_string($t) && strtotime($t) !== false && strtotime($t) > $since));
    }

    private static function row(string $guard, string $ownerId): ?object
    {
        return DB::table('sign_in_pins')->where('guard', $guard)->where('owner_id', $ownerId)->first();
    }
}
