<?php

namespace App\Support\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A stamp per mailbox that changes whenever what the Mail page shows of it may have changed: a conversation's counts,
 * flags or teamwork, a folder's counts, who opened a message. Every such write goes through ThreadCounters, a saved
 * MailThread or MailActions, which bump it here. The Mail page's minute poll compares the stamps it was drawn with
 * and skips the whole render (list, counts, reader) when none moved: most minutes, nothing has.
 *
 * Kept in the app cache; losing it (a cache clear) only makes the next poll render once.
 */
final class MailVersion
{
    /** @param  iterable<string|null>|string|null  $accountIds */
    public static function bump(iterable|string|null $accountIds): void
    {
        $ids = array_values(array_unique(array_filter(is_iterable($accountIds) ? [...$accountIds] : [$accountIds])));
        if (! $ids) {
            return;
        }
        try {
            $stamp = Str::random(12);
            Cache::putMany(array_fill_keys(array_map(self::key(...), $ids), $stamp), now()->addDays(30));
        } catch (\Throwable) {
            // a stamp is a convenience: the next real render shows the change
        }
    }

    /**
     * The stamps of these mailboxes, as one value.
     *
     * @param  list<string>  $accountIds
     */
    public static function of(array $accountIds): string
    {
        if (! $accountIds) {
            return '';
        }
        sort($accountIds);
        try {
            $stamps = Cache::many(array_map(self::key(...), $accountIds));
        } catch (\Throwable) {
            return Str::random(12); // unknown: render
        }

        return md5(implode('|', array_map(fn ($k) => $k . '=' . ($stamps[$k] ?? ''), array_keys($stamps))));
    }

    private static function key(string $accountId): string
    {
        return 'mail-version:' . $accountId;
    }
}
