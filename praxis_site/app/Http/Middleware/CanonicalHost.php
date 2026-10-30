<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** www.jbspraxis.com → jbspraxis.com, path and query kept (one address per page for search engines). */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        if (str_starts_with($host, 'www.')) {
            $query = $request->getQueryString();

            return redirect()->away($request->getScheme() . '://' . substr($host, 4) . $request->getPathInfo() . ($query ? '?' . $query : ''), 301);
        }

        return $next($request);
    }
}
