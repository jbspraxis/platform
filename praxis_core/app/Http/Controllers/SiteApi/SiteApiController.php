<?php

namespace App\Http\Controllers\SiteApi;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifySiteSignature;
use App\Services\Site\SiteText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Base of the site API controllers (docs/SITE_API.md): the success envelope, validation in the
 * request's language, and the visitor IP that VerifySiteSignature checked.
 */
abstract class SiteApiController extends Controller
{
    /** {"data": ...}; cacheable for $maxAge seconds when given, otherwise not stored */
    protected function data($data, int $status = 200, ?int $maxAge = null): JsonResponse
    {
        return response()->json(['data' => $data], $status, [
            'Cache-Control' => $maxAge ? 'max-age=' . $maxAge : 'no-store',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function error(int $status, array $error): JsonResponse
    {
        return VerifySiteSignature::error($status, $error);
    }

    /**
     * Validates the request (422 with field messages on failure) and returns only the named fields.
     *
     * @param  list<string>  $fields
     */
    protected function input(Request $request, array $rules, array $fields, array $messages = []): array
    {
        $data = $request->all();
        SiteText::validate($data, $rules, $messages);

        return Arr::only($data, $fields);
    }

    protected function visitorIp(Request $request): string
    {
        return (string) $request->attributes->get('site_visitor_ip', $request->ip());
    }
}
