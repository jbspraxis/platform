<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Hosts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Opening a push notification (docs/PWA.md): the person's own notification is marked read and they
 * go to its screen. Only HQ paths are followed; anything else, and anyone else's notification, goes
 * to My workspace without marking anything.
 */
class NotificationOpenController extends Controller
{
    public function __invoke(string $notification)
    {
        $row = DB::table('notifications')->where('id', $notification)->where('user_id', Auth::id())->first(['id', 'link_url']);
        if (! $row) {
            return redirect()->route('hq.dash.workspace');
        }

        DB::table('notifications')->where('id', $row->id)->update(['is_read' => 1]);
        $path = self::hqPath($row->link_url);

        return $path !== null ? redirect()->to($path) : redirect()->route('hq.dash.workspace');
    }

    /**
     * A notification link as a path on HQ, or null. Links are stored as HQ paths or full HQ addresses;
     * other hosts, scripts, protocol-relative and empty links ("#") are never followed.
     */
    public static function hqPath(?string $link): ?string
    {
        $link = trim((string) $link);
        // Backslashes and control characters: browsers read "/\evil.com" as "//evil.com"
        if ($link === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $link)) {
            return null;
        }
        if ($link[0] === '/') {
            return str_starts_with($link, '//') ? null : $link;
        }

        $parts = parse_url($link);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || strtolower($parts['host'] ?? '') !== strtolower(Hosts::hq()) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return '/' . ltrim($parts['path'] ?? '', '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
