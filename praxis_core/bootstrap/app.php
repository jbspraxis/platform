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

        // 2. LOCALE ENFORCER (Inject our new gatekeeper here)
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // 3. DYNAMIC SESSION EXPIRATION ROUTING
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('client') || $request->is('client/*')) {
                return route('client.login');
            }
            return route('login'); 
        });

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();