<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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

        // 3. HQ ACCESS: staff only (clients and leads share the 'web' login)
        $middleware->alias([
            'hq.staff' => \App\Http\Middleware\HqStaff::class,
        ]);

        // 4. DYNAMIC SESSION EXPIRATION ROUTING
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('client') || $request->is('client/*')) {
                return route('client.login');
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
    })->create();