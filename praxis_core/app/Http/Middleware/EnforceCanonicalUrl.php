<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class EnforceCanonicalUrl
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only enforce 301 redirects on standard GET requests (ignore API/POSTs)
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $redirect = false;
        $host = $request->header('host');
        $path = $request->path();
        $queryString = $request->getQueryString();
        $scheme = $request->secure() ? 'https://' : 'http://';

        // 1. Force HTTPS in Production
        if (!$request->secure() && app()->environment('production')) {
            $redirect = true;
            $scheme = 'https://';
        }

        // 2. Strip the 'www.' prefix
        if (substr($host, 0, 4) === 'www.') {
            $redirect = true;
            $host = substr($host, 4);
        }

        // 3. Strip trailing slashes (except for the root domain)
        if ($path !== '/' && preg_match('/\/$/', $request->getPathInfo())) {
            $redirect = true;
            $path = rtrim($path, '/');
        }

        // Execute 301 Permanent Redirect if any anomaly was detected
        if ($redirect) {
            $newUrl = $scheme . $host . '/' . ($path === '/' ? '' : $path);
            $newUrl .= $queryString ? '?' . $queryString : '';
            
            // PRAXIS OVERRIDE: Correct Laravel 301 Redirect Syntax
            return Redirect::to($newUrl, 301);
        }

        return $next($request);
    }
}