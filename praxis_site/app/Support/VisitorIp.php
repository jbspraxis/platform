<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The visitor's IP address and country, as HQ and the site's limits use them (docs/SITE_API.md 6.2, 6.6).
 *
 * - Behind Cloudflare: CF-Connecting-IP and CF-IPCountry are believed only when the connecting address
 *   (REMOTE_ADDR) is in Cloudflare's published ranges (config/site.php). Anyone else can send those headers.
 * - Otherwise: request()->ip(), which follows X-Forwarded-For only from the proxies in TRUSTED_PROXIES.
 * - Limits count a "visitor" as one IPv4 address or one IPv6 /64 network (bucket()).
 */
final class VisitorIp
{
    /** The visitor IP sent to HQ as X-Site-Visitor-Ip: always a valid address. */
    public static function of(Request $request): string
    {
        if (self::viaCloudflare($request)) {
            $cf = trim((string) $request->headers->get('CF-Connecting-IP', ''));
            if (filter_var($cf, FILTER_VALIDATE_IP) !== false) {
                return $cf;
            }
        }

        $ip = (string) $request->ip();

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    /** The visitor's country from Cloudflare (two letters), or null when the request did not come through Cloudflare. */
    public static function country(Request $request): ?string
    {
        if (! self::viaCloudflare($request)) {
            return null;
        }
        $country = strtoupper(trim((string) $request->headers->get('CF-IPCountry', '')));

        // XX: unknown; T1: Tor
        return preg_match('/^[A-Z]{2}$/', $country) && ! in_array($country, ['XX', 'T1'], true) ? $country : null;
    }

    /** True when the connecting address is one of Cloudflare's published ranges. */
    public static function viaCloudflare(Request $request): bool
    {
        $remote = (string) $request->server->get('REMOTE_ADDR', '');

        return filter_var($remote, FILTER_VALIDATE_IP) !== false
            && IpUtils::checkIp($remote, self::cloudflareRanges());
    }

    /**
     * The key a limit counts on: the IPv4 address, or the /64 network of an IPv6 address (an IPv4-mapped
     * IPv6 address counts as its IPv4 address).
     */
    public static function bucket(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }
        $bin = (string) inet_pton($ip);
        if (str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($bin, 12));
        }

        return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    /** The rate-limit key for this request's visitor. */
    public static function key(Request $request): string
    {
        return self::bucket(self::of($request));
    }

    /**
     * TRUSTED_PROXIES as a list for TrustProxies::at(): "cloudflare" expands to the published ranges,
     * invalid entries are dropped, and "*" is refused (it would let anyone choose their visitor IP).
     *
     * @return list<string>
     */
    public static function trustedProxies(?string $setting = null): array
    {
        $setting ??= (string) config('site.trusted_proxies', '');
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $setting)), 'strlen') as $entry) {
            if (strtolower($entry) === 'cloudflare') {
                array_push($out, ...self::cloudflareRanges());
            } elseif (str_contains($entry, '*')) {
                Log::warning('TRUSTED_PROXIES: "*" is not accepted and was ignored. Use "cloudflare" or list the proxy addresses.');
            } elseif (self::isAddressOrRange($entry)) {
                $out[] = $entry;
            } else {
                Log::warning('TRUSTED_PROXIES: an entry that is not an IP address or range was ignored.');
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    public static function cloudflareRanges(): array
    {
        return array_values(array_filter((array) config('site.cloudflare_ranges', []), 'is_string'));
    }

    private static function isAddressOrRange(string $entry): bool
    {
        [$address, $bits] = array_pad(explode('/', $entry, 2), 2, null);
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($bits === null) {
            return true;
        }
        $max = str_contains($address, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits >= 1 && (int) $bits <= $max;
    }
}
