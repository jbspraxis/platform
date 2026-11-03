<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // The private site API on the HQ host (docs/SITE_API.md): stateless 'api' group, registered
        // before web.php so HQ's catch-all 404 does not answer its paths first
        api: __DIR__.'/../routes/site-api.php',
        apiPrefix: 'api/site/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        
        // 1. CANONICAL SEO ENFORCER
        $middleware->append(\App\Http\Middleware\EnforceCanonicalUrl::class);

        // 1b. BROWSER SECURITY HEADERS (+ noindex on private areas)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // 2. LOCALE ENFORCER (Inject our new gatekeeper here)
        //    + the System Health watchdog (alerts if the cron stops; runs after the response)
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\MonitorWatchdog::class,
        ]);

        // 3. HQ ACCESS: staff only (users table, 'web' guard); portal: active clients only ('client' guard)
        $middleware->alias([
            'hq.staff' => \App\Http\Middleware\HqStaff::class,
            'client.active' => \App\Http\Middleware\ClientActive::class,
            // Site API: HMAC signature, nonce and rate limits (site.signed:read|write|chat)
            'site.signed' => \App\Http\Middleware\VerifySiteSignature::class,
        ]);

        // Site API: inputs reach the shared services exactly as sent (as Livewire's do), and the
        // multipart signature is computed on the fields as sent
        $siteApi = fn (Request $request) => $request->is('api/site/v1/*') && \App\Support\Hosts::isHq($request);
        $middleware->trimStrings(except: [$siteApi]);
        $middleware->convertEmptyStringsToNull(except: [$siteApi]);

        // A broadcast's unsubscribe link is posted by inboxes themselves (one-click, RFC 8058), which can't send a CSRF
        // token; the link's own secret token is what proves it (routes/email-links.php)
        $middleware->validateCsrfTokens(except: ['email/unsubscribe/*']);

        // 4. DYNAMIC SESSION EXPIRATION ROUTING
        $middleware->redirectGuestsTo(function (Request $request) {
            // Each host has its own sign-in page: the portal for clients, HQ for staff
            if (\App\Support\Hosts::isPortal($request)) {
                return route('client.login');
            }
            if ($request->is('taskforce') || $request->is('taskforce/*')) {
                return route('taskforce.login');
            }
            return route('login');
        });

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Browsers (usually bots) trying to change protected Livewire values: the change is
        // refused, which is the system working, so it is not reported as an error.
        $exceptions->dontReport([
            \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class,
            \Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException::class,
        ]);

        // Every reported error also goes to HQ → System Health (grouped, with "Copy for AI")
        $exceptions->report(function (\Throwable $e) {
            \App\Support\Monitor\ErrorMonitor::captureException($e);
        });

        // Database errors: laravel.log gets the SQL with ? placeholders, never the bound values (visitors'
        // data, docs/SITE_API.md section 6.3); returning false stops Laravel's own log line, which has them
        $exceptions->report(function (\Illuminate\Database\QueryException $e) {
            \App\Support\Monitor\ErrorMonitor::logSafely($e);

            return false;
        });

        // An email link that expired or was altered (signed routes): say so, in the visitor's language, with the way back
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, Request $request) {
            return $request->expectsJson() ? null : response()->view('errors.link', ['exception' => $e], 403);
        });

        // Site API: errors in its JSON envelope (docs/SITE_API.md section 2), never a stack trace
        $exceptions->render(fn (\Throwable $e, Request $request) => \App\Http\Controllers\SiteApi\ApiErrors::render($e, $request));
    })->create();