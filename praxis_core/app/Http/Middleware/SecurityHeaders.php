<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every response, and noindex on private areas: all of
 * HQ and the client portal (their own hosts), plus the old private paths on the
 * public site, which now only redirect.
 */
class SecurityHeaders
{
    private const PRIVATE_PATHS = ['hq', 'hq/*', 'client', 'client/*', 'client-portal', 'taskforce', 'taskforce/*', 'portal/*', 'signoff/*', 'verify/*', 'dossier/*', 'files/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=(), publickey-credentials-create=(self), publickey-credentials-get=(self)');
        $headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $headers->remove('X-Powered-By');

        if (\App\Support\Hosts::isHq($request) || \App\Support\Hosts::isPortal($request) || $request->is(...self::PRIVATE_PATHS)) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
