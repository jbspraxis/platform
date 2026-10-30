<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * English at /, French at /fr/ (docs/LAUNCH_SET.md section 1). French pages keep the English slugs after /fr/,
 * and every route() or url() built on a French page gets the /fr prefix, so the views name routes once.
 * The locale also reaches HQ (X-Site-Locale), which answers and emails visitors in that language.
 */
class SiteLocale
{
    public const LOCALES = ['en', 'fr'];

    // Paths that exist once, outside /fr (email links stay as sent; robots and sitemap cover both languages)
    private const SHARED = ['tracking.open', 'unsubscribe', 'unsubscribe.store', 'robots', 'sitemap', 'llms'];

    // Global middleware, so a page no route matched (a 404 under /fr) is in the right language too
    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::fromPath($request);
        app()->setLocale($locale);

        URL::formatPathUsing($locale === 'fr'
            ? function (string $path, $route = null) {
                if ($route && in_array($route->getName(), self::SHARED, true)) {
                    return $path;
                }
                if ($path === '/fr' || str_starts_with($path, '/fr/')) {
                    return $path;
                }

                return '/fr' . ($path === '/' ? '' : $path);
            }
            : fn (string $path) => $path);

        // The page is rendered by now: put URL building back to plain paths, so nothing that runs later in the
        // same process (the next request in a test) gets /fr added
        try {
            return $next($request);
        } finally {
            URL::formatPathUsing(fn (string $path) => $path);
        }
    }

    public static function fromPath(Request $request): string
    {
        $path = trim($request->path(), '/');

        return $path === 'fr' || str_starts_with($path, 'fr/') ? 'fr' : 'en';
    }

    /** The same page in each language (absolute URLs, no query string), for hreflang and the switch. */
    public static function alternates(Request $request): array
    {
        $path = '/' . trim($request->path(), '/');
        $english = preg_replace('~^/fr(?=/|$)~', '', $path) ?: '/';
        $root = rtrim($request->root(), '/');

        return [
            'en' => $root . $english,
            'fr' => $root . '/fr' . ($english === '/' ? '' : $english),
        ];
    }
}
