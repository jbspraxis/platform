<?php

namespace App\Http\Controllers\SiteApi;

use App\Http\Middleware\VerifySiteSignature;

/**
 * The last route of routes/site-api.php: any other path or method under /api/site/v1 on HQ is
 * 404 not_found (docs/SITE_API.md section 6.3), so nothing falls through to web.php (no 419, no 500).
 * Not signed: it says nothing and does nothing.
 */
class NotFoundController
{
    public function __invoke()
    {
        return VerifySiteSignature::error(404, ['code' => 'not_found']);
    }
}
