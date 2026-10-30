<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response, and noindex while the site is a preview (SITE_NOINDEX).
 * The CSP allows only this origin: no inline scripts or styles, no third parties (fonts are self-hosted), except
 * Cloudflare Turnstile and Google Analytics when they are configured (Analytics loads only after consent).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $self = "'self'";
        $dev = '';
        if (app()->environment('local') && Vite::isRunningHot()) {
            // npm run dev: the Vite server serves the assets and pushes reloads over a websocket
            $hot = trim((string) @file_get_contents(public_path('hot')));
            $ws = preg_replace('~^http~', 'ws', $hot);
            $dev = ' ' . $hot . ' ' . $ws;
        }

        // Cloudflare Turnstile, only when its keys are set: its script and its frame, nothing else
        $turnstile = Turnstile::enabled() ? ' ' . (string) config('services.turnstile.origin', 'https://challenges.cloudflare.com') : '';

        // Google Analytics 4 (GA4_ID): its tag script, and where it sends hits
        $ga = config('site.ga4_id') !== '';
        $gaScript = $ga ? ' https://www.googletagmanager.com' : '';
        $gaSend = $ga ? ' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com' : '';

        $csp = implode('; ', [
            "default-src {$self}",
            "script-src {$self}" . $turnstile . $gaScript . $dev,
            "style-src {$self}" . $dev,
            "img-src {$self} data:" . $gaSend,
            "font-src {$self}" . $dev,
            "connect-src {$self}" . $gaSend . $dev,
            "frame-src {$self}" . $turnstile,
            "form-action {$self}",
            "frame-ancestors 'none'",
            "base-uri 'none'",
            "object-src 'none'",
        ]);

        $headers = $response->headers;
        $headers->set('Content-Security-Policy', $csp);
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if (config('site.noindex')) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
