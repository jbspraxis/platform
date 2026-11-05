<?php

namespace App\Http\Controllers;

use App\Support\Clients;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One logo in the strip (docs/COMPANY_PROFILE.md section 4). The page's markup points here rather than at a
 * file, because the list is kept in HQ: the first request for a logo this server has not got fetches it once
 * and keeps it, and every request after that is served from here.
 *
 * `v` is the first twelve characters of the file's own hash, so a logo replaced in HQ is a new address and
 * nothing anywhere serves yesterday's picture. The response can be cached for a year for that reason.
 */
class ClientLogoController extends Controller
{
    public function show(Request $request, string $key): Response
    {
        $bytes = Clients::logo($key, (string) $request->query('v', ''));
        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => $request->query('v') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
