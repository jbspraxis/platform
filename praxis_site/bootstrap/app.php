<?php

use App\Exceptions\Hq\HqApiException;
use App\Exceptions\Hq\HqConflict;
use App\Exceptions\Hq\HqNotFound;
use App\Exceptions\Hq\HqRateLimited;
use App\Exceptions\Hq\HqValidationException;
use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\FormGuard;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SiteLocale;
use App\Support\HqApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // www → apex first; then the language from the path (/fr/…), before any URL is built
        $middleware->prepend(CanonicalHost::class);
        $middleware->append(SiteLocale::class);
        $middleware->append(SecurityHeaders::class);
        // Every form: honeypot, signed page-load time (3 s minimum), Turnstile when its keys are set
        $middleware->alias(['guard' => FormGuard::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // HqApi logs what matters (without secrets); these are expected states, not bugs
        $exceptions->dontReport([HqApiException::class]);

        // 422 from HQ: field messages next to the inputs, HQ's summary above the form, values kept
        $exceptions->render(function (HqValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->summary ?: __('site.errors.check_fields'), 'errors' => $e->errors()], 422);
            }

            return redirect()->to(url()->previous())
                ->withInput($request->except(FormGuard::NOT_KEPT))
                ->withErrors($e->errors())
                ->with('form_summary', $e->summary);
        });

        // The site's own limits (throttle:forms, throttle:chat…): the calm text for the chat's JSON call too
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('site.errors.throttled_body')], 429, $e->getHeaders());
            }

            return null; // errors/429.blade.php
        });

        // HQ down, refusing, rate-limiting or not finding: a calm message with the contact email from the last good settings
        $exceptions->render(function (HqApiException $e, Request $request) {
            $email = app(HqApi::class)->contactEmail();

            if ($e instanceof HqNotFound) {
                return response()->view('errors.404', ['email' => $email], 404);
            }

            // HQ's own calm text (in the visitor's language) when it sends one
            $message = match (true) {
                $e instanceof HqRateLimited => $e->hqMessage ?: __('site.errors.too_many', ['email' => $email]),
                $e instanceof HqConflict => $e->hqMessage ?: __('site.errors.conflict', ['email' => $email]),
                default => $request->isMethod('GET') ? __('site.errors.unavailable_body', ['email' => $email]) : __('site.errors.unavailable_form', ['email' => $email]),
            };

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], $e instanceof HqRateLimited ? 429 : 503);
            }
            if (! $request->isMethod('GET')) {
                return redirect()->to(url()->previous())
                    ->withInput($request->except(FormGuard::NOT_KEPT))
                    ->with('hq_error', $message);
            }

            return response()
                ->view('errors.hq-unavailable', ['message' => $message, 'email' => $email], $e instanceof HqRateLimited ? 429 : 503)
                ->header('Retry-After', $e instanceof HqRateLimited ? (string) $e->retryAfter : '120');
        });
    })->create();
