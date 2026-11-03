<?php

namespace App\Support\Campaigns;

use Illuminate\Support\Facades\DB;

/**
 * The do-not-email list for all marketing (docs/CAMPAIGNS.md 4.1): Broadcasts and Outreach both ask canEmail()
 * before every send, and anything that learns an address must stop (an unsubscribe link, a bounce, a spam
 * complaint, a person's request to the team) calls suppress(). One row per address, whichever tool it came from.
 *
 *   Marketing::canEmail('ada@example.com')                          false once the address is on the list
 *   Marketing::suppress($email, 'unsubscribed', ['source' => 'outreach'])
 *
 * Transactional email (invoices, proposals, sign-in codes) never asks: the list only stops marketing.
 */
final class Marketing
{
    /** Why an address is on the list, as HQ shows it */
    public const REASONS = [
        'unsubscribed' => 'Unsubscribed',
        'bounced' => 'Bounced',
        'complained' => 'Marked as spam',
        'manual' => 'Added by hand',
    ];

    /** Only the person can undo these: HQ can't take the address off the list */
    public const PERSON_ONLY = ['unsubscribed', 'complained'];

    /** The form every address is stored and compared in */
    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    public static function isValid(?string $email): bool
    {
        return filter_var(self::normalize($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /** May marketing (a broadcast, an outreach email or its follow-up) go to this address? */
    public static function canEmail(?string $email): bool
    {
        return self::isValid($email) && self::entry($email) === null;
    }

    /** The list's row for this address, or null */
    public static function entry(?string $email): ?object
    {
        $email = self::normalize($email);

        return $email === '' ? null : DB::table('marketing_suppressions')->where('email', $email)->first();
    }

    /**
     * Puts an address on the list (or keeps it there with the stronger reason: an unsubscribe outranks a bounce or a
     * manual entry, so HQ can never lift it). Contacts and subscribers with that address are marked unsubscribed too.
     * $context: source (broadcast, outreach, legacy_link, hq…), broadcast_id, note, by (staff id).
     * Returns true when the address was not on the list before.
     */
    public static function suppress(?string $email, string $reason, array $context = []): bool
    {
        $email = self::normalize($email);
        if ($email === '' || ! isset(self::REASONS[$reason])) {
            return false;
        }

        $now = now();
        $added = DB::table('marketing_suppressions')->insertOrIgnore([
            'email' => mb_substr($email, 0, 190),
            'reason' => $reason,
            'source' => isset($context['source']) ? mb_substr((string) $context['source'], 0, 40) : null,
            'broadcast_id' => $context['broadcast_id'] ?? null,
            'note' => isset($context['note']) ? mb_substr((string) $context['note'], 0, 255) : null,
            'created_by' => $context['by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]) > 0;

        if (! $added && in_array($reason, self::PERSON_ONLY, true)) {
            DB::table('marketing_suppressions')->where('email', $email)->whereNotIn('reason', self::PERSON_ONLY)
                ->update(['reason' => $reason, 'source' => $context['source'] ?? null, 'broadcast_id' => $context['broadcast_id'] ?? null, 'updated_at' => $now]);
        }

        DB::table('client_users')->where('email', $email)->where('is_subscribed', 1)->update(['is_subscribed' => 0, 'updated_at' => $now]);
        DB::table('marketing_subscribers')->where('email', $email)->whereNull('unsubscribed_at')->update(['unsubscribed_at' => $now, 'updated_at' => $now]);

        return $added;
    }

    /**
     * Takes an address added by hand, or one that bounced, off the list (HQ › Campaigns › Audiences, broadcasts.approve).
     * Unsubscribes and spam complaints stay: false.
     */
    public static function lift(?string $email): bool
    {
        $entry = self::entry($email);
        if (! $entry || in_array($entry->reason, self::PERSON_ONLY, true)) {
            return false;
        }

        DB::table('marketing_suppressions')->where('id', $entry->id)->delete();
        DB::table('client_users')->where('email', $entry->email)->where('is_subscribed', 0)->update(['is_subscribed' => 1, 'updated_at' => now()]);
        DB::table('marketing_subscribers')->where('email', $entry->email)->whereNotNull('unsubscribed_at')->update(['unsubscribed_at' => null, 'updated_at' => now()]);

        return true;
    }
}
