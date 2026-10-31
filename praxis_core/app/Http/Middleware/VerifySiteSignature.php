<?php

namespace App\Http\Middleware;

use App\Services\Site\LimitReached;
use App\Services\Site\SiteLimits;
use App\Services\Site\SiteSignature;
use App\Services\Site\SiteText;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The site API's door (docs/SITE_API.md sections 1 and 6.1):
 *   1. a connecting address with 30 failed authentications this minute gets 429 at once (nothing hashed or logged);
 *   2. the six X-Site-* headers must be present and well formed before anything is hashed;
 *   3. known key, 300-second window, the 9-line HMAC signature, then the one-time nonce.
 * A failure is 401 unauthorised with no detail; the reason is logged at most once a minute per reason.
 * Reads are then limited per visitor (120 a minute, 1200 for everyone); the forms and chat are limited
 * in their services (App\Services\Site\SiteLimits), on the old pages' limiter keys.
 * On success the app locale is X-Site-Locale and the visitor IP is the 'site_visitor_ip' request attribute.
 *
 *   ->middleware('site.signed:read')    // or :write, :chat
 */
class VerifySiteSignature
{
    public function handle(Request $request, Closure $next, string $bucket = 'read'): Response
    {
        $failures = 'site-api:auth-fail:' . SiteLimits::visitor($request->ip());
        if (RateLimiter::tooManyAttempts($failures, (int) config('site_api.auth_failures_per_minute', 30))) {
            return self::tooMany(max(1, RateLimiter::availableIn($failures)));
        }

        $keys = (array) config('site_api.keys', []);
        $keyId = (string) $request->headers->get('X-Site-Key', '');
        $timestamp = (string) $request->headers->get('X-Site-Timestamp', '');
        $nonce = (string) $request->headers->get('X-Site-Nonce', '');
        $visitorIp = (string) $request->headers->get('X-Site-Visitor-Ip', '');
        $locale = (string) $request->headers->get('X-Site-Locale', '');
        $signature = (string) $request->headers->get('X-Site-Signature', '');

        // Shape first (cheap), then the key and the window; only then is the body hashed
        $reason = match (true) {
            $keys === [] => 'no key configured',
            !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId) => 'bad key id',
            !preg_match('/^\d{1,12}$/', $timestamp) => 'bad timestamp',
            !preg_match('/^[0-9a-f]{16,128}$/i', $nonce) => 'bad nonce',
            filter_var($visitorIp, FILTER_VALIDATE_IP) === false => 'bad visitor IP',
            !in_array($locale, ['en', 'fr'], true) => 'bad locale',
            !preg_match('/^[0-9a-f]{64}$/', $signature) => 'bad signature format',
            !array_key_exists($keyId, $keys) => 'unknown key',
            abs(time() - (int) $timestamp) > (int) config('site_api.window', 300) => 'timestamp outside the window',
            default => null,
        };

        if ($reason === null) {
            $bodyHash = SiteSignature::bodyHash($request);
            if ($bodyHash === null) {
                $reason = 'form that cannot be signed'; // array field, duplicate name, file missing
            } else {
                $canonical = SiteSignature::canonical($request->getMethod(), $request->getRequestUri(), $timestamp, $nonce, $visitorIp, $locale,
                    $keyId, SiteSignature::mediaType($request), $bodyHash);
                if (!hash_equals(SiteSignature::sign((string) $keys[$keyId], $canonical), $signature)) {
                    $reason = 'signature mismatch';
                } elseif (!Cache::add('site-api:nonce:' . $keyId . ':' . strtolower($nonce), 1, (int) config('site_api.nonce_ttl', 600))) {
                    $reason = 'nonce replayed';
                }
            }
        }

        if ($reason !== null) {
            RateLimiter::hit($failures, 60);
            // The reason goes to the log only (once a minute per reason); the response says nothing about which check failed
            if (Cache::add('site-api:refusal-logged:' . md5($reason), 1, 60)) {
                Log::info('Site API: request refused', ['reason' => $reason, 'path' => '/' . ltrim($request->path(), '/')]);
            }

            return self::error(401, ['code' => 'unauthorised']);
        }

        app()->setLocale($locale);
        $request->attributes->set('site_visitor_ip', $visitorIp);
        $request->attributes->set('site_key_id', $keyId);

        // Reads (and the tracking calls): 120 a minute per visitor, 1200 for everyone
        if ($bucket === 'read') {
            try {
                SiteLimits::attempt('read', $visitorIp);
            } catch (LimitReached $e) {
                return self::tooMany($e->retryAfter);
            }
        }

        return $next($request);
    }

    /** 429 too_many with retry_after and a calm message in the request's language */
    public static function tooMany(int $retryAfter): JsonResponse
    {
        return self::error(429, ['code' => 'too_many', 'retry_after' => $retryAfter, 'message' => SiteText::get('too_many')], ['Retry-After' => (string) $retryAfter]);
    }

    public static function error(int $status, array $error, array $headers = []): JsonResponse
    {
        return response()->json(['error' => $error], $status, $headers + ['Cache-Control' => 'no-store'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
