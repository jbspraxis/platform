<?php

namespace App\Http\Controllers\SiteApi;

use App\Support\WebsiteSettings;

/** GET /settings: the public Website settings (HQ → Marketing → Website settings) */
class SettingsController extends SiteApiController
{
    public function show()
    {
        return $this->data(WebsiteSettings::publicPayload(), 200, 300);
    }
}
