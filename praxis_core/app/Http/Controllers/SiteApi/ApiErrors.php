<?php

namespace App\Http\Controllers\SiteApi;

use App\Http\Middleware\VerifySiteSignature;
use App\Services\Site\LimitReached;
use App\Services\Site\SiteText;
use App\Support\Hosts;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Errors on the site API in the envelope of docs/SITE_API.md sections 2 and 6.3. Registered in
 * bootstrap/app.php; the exception handler has already reported anything that is not an HTTP
 * error (so it shows in HQ → System Health). Never a stack trace. A 4xx without its own code
 * (419, 403, 400, ...) is 400 bad_request; anything else is 500 server.
 */
final class ApiErrors
{
    public static function isSiteApi(Request $request): bool
    {
        return Hosts::isHq($request) && $request->is('api/site/v1', 'api/site/v1/*');
    }

    public static function render(Throwable $e, Request $request): ?Response
    {
        if (!self::isSiteApi($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return VerifySiteSignature::error(422, [
                'code' => 'invalid',
                'message' => SiteText::get('invalid'),
                'fields' => $e->errors(),
            ]);
        }

        if ($e instanceof LimitReached) {
            return VerifySiteSignature::tooMany($e->retryAfter);
        }

        if ($e instanceof PostTooLargeException) {
            return VerifySiteSignature::error(413, ['code' => 'too_large']);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return match (true) {
                $status === 401 => VerifySiteSignature::error(401, ['code' => 'unauthorised']),
                in_array($status, [404, 405], true) => VerifySiteSignature::error(404, ['code' => 'not_found']),
                $status === 413 => VerifySiteSignature::error(413, ['code' => 'too_large']),
                $status === 429 => VerifySiteSignature::tooMany(max(1, (int) ($e->getHeaders()['Retry-After'] ?? 60))),
                $status === 503 => VerifySiteSignature::error(503, ['code' => 'unavailable']),
                $status >= 400 && $status < 500 => VerifySiteSignature::error(400, ['code' => 'bad_request']),
                default => VerifySiteSignature::error(500, ['code' => 'server']),
            };
        }

        return VerifySiteSignature::error(500, ['code' => 'server']);
    }
}
