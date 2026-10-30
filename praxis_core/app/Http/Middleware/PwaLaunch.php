<?php

namespace App\Http\Middleware;

use App\Support\Hosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notices a cold start of the installed HQ app, so the launch animation (<x-pwa.launch />) plays once
 * (docs/PWA.md). The app opens at its start_url, /?source=pwa, and "/" always redirects (to the sign-in
 * page or My workspace), so the launch is carried over the redirect in a one-request flash and the page
 * at the end of it plays the animation. A page that answers ?source=pwa itself plays it and leaves
 * nothing behind, so the next page never plays it again.
 */
class PwaLaunch
{
    /** Request attribute: this page is the first one of a launch */
    public const ATTRIBUTE = 'pwa.launch';

    /** Session flash that carries a launch over a redirect to the next page */
    public const FLASH = 'pwa_launch';

    public function handle(Request $request, Closure $next)
    {
        if (! self::isPage($request) || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        if ($request->query('source') !== 'pwa' && $session->get(self::FLASH) !== true) {
            return $next($request);
        }

        $request->attributes->set(self::ATTRIBUTE, true);
        try {
            $response = $next($request);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            // A guest opening the installed portal: the sign-in page that check sends them to plays the launch
            $session->flash(self::FLASH, true);

            throw $e;
        }

        // A redirect on HQ (or a chain of them) hands the launch to the next page; anything else ends it
        if (self::redirectsHere($request, $response)) {
            $session->flash(self::FLASH, true);
        } else {
            $session->forget(self::FLASH);
        }

        return $response;
    }

    /** Whether the current request is the first page of a launch (the launch component renders only then). */
    public static function active(?Request $request = null): bool
    {
        return ($request ?? request())->attributes->get(self::ATTRIBUTE) === true;
    }

    /**
     * A page the browser opens on the HQ host: a plain GET, not Livewire (updates or wire:navigate),
     * not JSON or XHR, not a prefetch, not a frame or a subresource.
     */
    private static function isPage(Request $request): bool
    {
        // The two apps: HQ and the Client Portal (each with its own start_url, /?source=pwa)
        if (! $request->isMethod('GET') || ! (Hosts::isHq($request) || Hosts::isPortal($request))) {
            return false;
        }

        if ($request->hasHeader('X-Livewire') || $request->hasHeader('X-Livewire-Navigate') || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        $dest = $request->headers->get('Sec-Fetch-Dest');
        $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose')));

        return ($dest === null || $dest === 'document') && ! str_contains($purpose, 'prefetch');
    }

    /** A redirect (301, 302, 303, 307, 308) to a page on this same host. */
    private static function redirectsHere(Request $request, Response $response): bool
    {
        $location = $response->headers->get('Location');
        if ($location === null || ! in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)) {
            return false;
        }

        $host = parse_url($location, PHP_URL_HOST);

        return $host === null || ($host !== false && strcasecmp($host, $request->getHost()) === 0);
    }
}
