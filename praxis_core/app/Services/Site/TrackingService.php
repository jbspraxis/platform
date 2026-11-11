<?php

namespace App\Services\Site;

use App\Support\Campaigns\Marketing;
use Illuminate\Support\Facades\DB;

/**
 * Broadcast email bookkeeping, for the old tracking routes (routes/web.php) and the site API
 * (GET /tracking/open, POST /tracking/unsubscribe).
 *
 * Links in broadcasts sent from now on carry a token (docs/SITE_API.md section 6.4):
 * t = the first 32 hex characters of hmac_sha256(APP_KEY, "campaign_id|user_id"). Such an email is marked
 * broadcast_logs.signed_links = 1, and its open pixel and unsubscribe link work only with the token.
 * Links in emails sent before keep working without it.
 */
final class TrackingService
{
    /** The token of one recipient's links in one campaign */
    public static function token(string $campaignId, string $userId): string
    {
        return substr(hash_hmac('sha256', $campaignId . '|' . $userId, (string) config('app.key')), 0, 32);
    }

    public static function tokenMatches(?string $campaignId, string $userId, ?string $token): bool
    {
        return is_string($campaignId) && $campaignId !== '' && is_string($token) && strlen($token) === 32
            && hash_equals(self::token($campaignId, $userId), strtolower($token));
    }

    /** The database can mark emails sent with tokens: broadcast_logs.signed_links (2026_10_30_400001) is there everywhere */
    public static function canSign(): bool
    {
        return true;
    }

    /**
     * Counts an open of a broadcast email; the campaign's opened count goes up on the first open.
     * Returns false when there is no such broadcast log, or its links need a token and this one is wrong.
     */
    public function open(string $campaignId, string $userId, ?string $ip, string $geo, ?string $token = null): bool
    {
        $log = DB::table('broadcast_logs')->where('campaign_id', $campaignId)->where('user_id', $userId)->first();
        if (!$log || (!empty($log->signed_links) && !self::tokenMatches($campaignId, $userId, $token))) {
            return false;
        }

        DB::table('broadcast_logs')->where('campaign_id', $campaignId)->where('user_id', $userId)->update([
            'open_count' => DB::raw('open_count + 1'), 'last_opened_at' => now(), 'last_open_ip' => $ip, 'last_open_geo' => $geo
        ]);

        if ((int) $log->open_count === 0) {
            DB::table('broadcast_campaigns')->where('id', $campaignId)->increment('opened_count');
        }

        return true;
    }

    /**
     * Stops broadcast emails to a contact (confirmed with a click), and puts the address on the do-not-email list. A link that names its campaign (every link
     * sent from now on) works when that email was sent to the contact and, if it was sent with tokens, the token
     * matches. A link without a campaign (emails from before tokens) works only while the contact has such an
     * email. Returns false when the link is not valid.
     */
    public function unsubscribe(string $userId, ?string $campaignId = null, ?string $token = null): bool
    {
        if ($campaignId !== null && $campaignId !== '') {
            $log = DB::table('broadcast_logs')->where('campaign_id', $campaignId)->where('user_id', $userId)->first();
            $allowed = $log && (empty($log->signed_links) || self::tokenMatches($campaignId, $userId, $token));
        } else {
            $allowed = $this->hasLinksWithoutToken($userId);
        }

        if (!$allowed) {
            return false;
        }

        DB::table('client_users')->where('id', $userId)->update(['is_subscribed' => 0, 'updated_at' => now()]);
        // One unsubscribe stops all marketing to the address, wherever it appears (docs/CAMPAIGNS.md 4.1)
        Marketing::suppress(DB::table('client_users')->where('id', $userId)->value('email'), 'unsubscribed',
            ['source' => 'legacy_link', 'broadcast_id' => $campaignId ?: null, 'note' => 'Unsubscribe link in a broadcast sent before 3 Nov 2026']);

        return true;
    }

    /** The contact was sent a broadcast before links had tokens */
    private function hasLinksWithoutToken(string $userId): bool
    {
        $sent = DB::table('broadcast_logs')->where('user_id', $userId)->where('status', 'Sent');

        return self::canSign() ? $sent->where('signed_links', 0)->exists() : $sent->exists();
    }
}
