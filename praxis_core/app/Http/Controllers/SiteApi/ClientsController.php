<?php

namespace App\Http\Controllers\SiteApi;

use App\Models\CompanyProfile\ClientLogo;
use App\Support\CompanyProfile\LogoLibrary;
use Illuminate\Http\Response;

/**
 * The logo strip on jbspraxis.com, fed from the HQ library (docs/COMPANY_PROFILE.md section 4, docs/SITE_API.md).
 * A logo added in HQ and ticked for the website appears on the site by itself: the site asks for the list,
 * notices a key it has not got, fetches that one file and keeps it.
 *
 * `GET /clients` is the list, in the order the strip shows them. Each row carries the `sha256` of the WebP, so
 * the site caches one file per version and fetches it again only when it really changed.
 * `GET /clients/{key}.webp` is that file. Both are plain reads under the API's own read limit; the site caches
 * them and serves its last good copy when HQ cannot be reached.
 */
class ClientsController extends SiteApiController
{
    public function index()
    {
        $logos = LogoLibrary::shownOn('website')
            ->filter(fn (ClientLogo $logo) => (bool) $logo->web_path)
            ->values()
            ->map(fn (ClientLogo $logo) => [
                'key' => (string) $logo->key,
                'name' => (string) $logo->name,
                'sector' => $logo->sector,
                'sha256' => (string) $logo->web_sha256,
                'width' => $logo->width,
                'height' => $logo->height,
            ])->all();

        return $this->data(['clients' => $logos], 200, 600);
    }

    public function file(string $key)
    {
        $logo = ClientLogo::query()->live()->shownOn('website')->where('key', $key)->first();
        $bytes = $logo ? LogoLibrary::bytes($logo->web_path) : null;
        if ($bytes === null) {
            return $this->error(404, ['code' => 'not_found']);
        }

        return new Response($bytes, 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
