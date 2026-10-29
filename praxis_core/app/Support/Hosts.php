<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The three hosts (config/app.php 'domains'): the public site, HQ and the client portal.
 * Before the split everything lived on the site under /hq, /client, /portal and so on;
 * legacyRedirect() sends those old addresses (in emails already sent, bookmarks) to their
 * new host and path.
 */
class Hosts
{
    // Old single-host path => [host key, new path] ($1 = the captured rest)
    private const LEGACY = [
        '#^hq(?:/(.*))?$#' => ['hq', '$1'],
        '#^taskforce(?:/(.*))?$#' => ['hq', 'taskforce/$1'],
        '#^files/(.+)$#' => ['hq', 'files/$1'],
        '#^client-portal$#' => ['portal', 'login'],
        '#^client/login$#' => ['portal', 'login'],
        '#^client/dashboard$#' => ['portal', ''],
        '#^client/setup/(.+)$#' => ['portal', 'setup/$1'],
        '#^portal/proposal/(.+)$#' => ['portal', 'proposal/$1'],
        '#^portal/tracker/(.+)$#' => ['portal', 'tracker/$1'],
        '#^signoff/gateway/(.+)$#' => ['portal', 'signoff/$1'],
        '#^verify/contract/(.+)$#' => ['portal', 'verify/$1'],
        '#^dossier/(.+)$#' => ['portal', 'dossier/$1'],
    ];

    /** Path pattern for route registration: every old prefix above. */
    public const LEGACY_PATTERN = '(hq|taskforce|files|client-portal|client|portal|signoff|verify|dossier)(/.*)?';

    public static function site(): string
    {
        return config('app.domains.site');
    }

    public static function hq(): string
    {
        return config('app.domains.hq');
    }

    public static function portal(): string
    {
        return config('app.domains.portal');
    }

    public static function isHq(Request $request): bool
    {
        return $request->getHost() === self::hq();
    }

    public static function isPortal(Request $request): bool
    {
        return $request->getHost() === self::portal();
    }

    /** Absolute URL on $host, keeping the current request's scheme and port. */
    public static function url(string $host, string $path = '', ?Request $request = null): string
    {
        $request ??= request();
        $port = $request->getPort();
        $port = in_array((int) $port, [80, 443], true) || ! $port ? '' : ':' . $port;

        return $request->getScheme() . '://' . $host . $port . '/' . ltrim($path, '/');
    }

    /** 301 to where an old single-host address lives now, or 404 if it isn't one. */
    public static function legacyRedirect(Request $request)
    {
        $path = trim($request->path(), '/');

        foreach (self::LEGACY as $pattern => [$hostKey, $target]) {
            if (! preg_match($pattern, $path)) {
                continue;
            }
            $host = $hostKey === 'hq' ? self::hq() : self::portal();
            $newPath = rtrim(preg_replace($pattern, $target, $path), '/');
            if ($host === $request->getHost() && $newPath === $path) {
                break; // already the right address: it simply doesn't exist
            }
            $query = $request->getQueryString();

            return redirect()->away(self::url($host, $newPath, $request) . ($query ? '?' . $query : ''), 301);
        }

        abort(404);
    }
}
