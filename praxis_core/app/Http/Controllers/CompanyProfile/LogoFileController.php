<?php

namespace App\Http\Controllers\CompanyProfile;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile\ClientLogo;
use App\Support\CompanyProfile\LogoLibrary;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The bytes of one client logo, for HQ (docs/COMPANY_PROFILE.md section 2). The files are on the private disk
 * and never on a public path, so even the picture of a logo is behind company_profile.view; the website is
 * served its own copies through the signed Site API instead.
 */
class LogoFileController extends Controller
{
    private const KINDS = ['web' => 'web_path', 'png' => 'png_path', 'print' => 'print_path'];

    public function show(Request $request, string $logo, string $kind): Response
    {
        abort_unless($request->user()?->can('company_profile.view'), 403);
        abort_unless(isset(self::KINDS[$kind]), 404);

        $row = ClientLogo::findOrFail($logo);
        $path = $row->{self::KINDS[$kind]};
        $bytes = LogoLibrary::bytes($path);
        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => $this->mediaType((string) $path),
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=600',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function mediaType(string $path): string
    {
        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
