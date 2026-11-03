<?php

namespace App\Support\Campaigns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What the links in a broadcast record (docs/CAMPAIGNS.md 4.5). Each recipient's row (broadcast_logs) has a random
 * 40-character token, and every link in their email carries it: the open pixel, each counted link and the
 * unsubscribe link. Nobody can open, click or unsubscribe for someone else without their email.
 *
 * Opens are approximate: Apple Mail loads every image when the email arrives (Mail Privacy Protection), and some
 * inboxes never load images. A click also counts as an open.
 */
final class Tracking
{
    public static function newToken(): string
    {
        return Str::random(40);
    }

    public static function log(string $token): ?object
    {
        return strlen($token) === 40 && ctype_alnum($token) ? DB::table('broadcast_logs')->where('token', $token)->first() : null;
    }

    public static function unsubscribeUrl(string $token): string
    {
        return route('email.unsubscribe', ['token' => $token]);
    }

    public static function openUrl(string $token): string
    {
        return route('email.open', ['token' => $token]);
    }

    public static function clickUrl(string $token, int $linkId): string
    {
        return route('email.click', ['token' => $token, 'link' => $linkId]);
    }

    /** Stops all marketing to the address (the do-not-email list), and notes it on this email */
    public static function unsubscribe(object $log, string $how): void
    {
        Marketing::suppress($log->email, 'unsubscribed', ['source' => 'broadcast', 'broadcast_id' => $log->campaign_id, 'note' => $how === 'one_click' ? 'Inbox unsubscribe button' : 'Unsubscribe page']);
        DB::table('broadcast_logs')->where('id', $log->id)->whereNull('unsubscribed_at')->update(['unsubscribed_at' => now()]);
    }

    public static function isUnsubscribed(object $log): bool
    {
        return $log->unsubscribed_at !== null || ! Marketing::canEmail($log->email);
    }

    public static function opened(object $log): void
    {
        // The app's clock (UTC), never the database's: they can differ on the server
        $now = now();
        DB::table('broadcast_logs')->where('id', $log->id)->update(['open_count' => DB::raw('open_count + 1'), 'last_opened_at' => $now]);
        DB::table('broadcast_logs')->where('id', $log->id)->whereNull('first_opened_at')->update(['first_opened_at' => $now]);
        if ((int) $log->open_count === 0) {
            DB::table('broadcast_campaigns')->where('id', $log->campaign_id)->increment('opened_count');
        }
    }

    /** Counts a click on one of the broadcast's own links and returns its address; null when the link isn't this email's */
    public static function clicked(object $log, int $linkId): ?string
    {
        $link = DB::table('broadcast_links')->where('id', $linkId)->where('campaign_id', $log->campaign_id)->first();
        if (! $link) {
            return null;
        }
        $now = now();
        DB::table('broadcast_clicks')->insert(['campaign_id' => $log->campaign_id, 'log_id' => $log->id, 'link_id' => $link->id, 'created_at' => $now]);
        DB::table('broadcast_links')->where('id', $link->id)->increment('clicks');
        DB::table('broadcast_logs')->where('id', $log->id)->update(['click_count' => DB::raw('click_count + 1')]);
        DB::table('broadcast_logs')->where('id', $log->id)->whereNull('first_clicked_at')->update(['first_clicked_at' => $now]);
        if ($log->first_opened_at === null) {
            self::opened($log);
        }

        return $link->url;
    }

    /** "a••••@example.com": enough for the person to recognise their address, not enough to read it off a shared screen */
    public static function masked(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 1) . str_repeat('•', max(2, min(6, mb_strlen($name) - 1))) . '@' . $domain;
    }
}
