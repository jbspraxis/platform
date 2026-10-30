<?php

namespace App\Http\Controllers\SiteApi;

use App\Services\Site\TrackingService;
use Illuminate\Http\Request;

/**
 * Broadcast email bookkeeping; the site serves the pixel and the confirmation page itself. Links in broadcasts
 * sent from now on carry a token t (docs/SITE_API.md section 6.4); older links keep working without it.
 */
class TrackingController extends SiteApiController
{
    /** GET /tracking/open/{campaign_id}/{user_id}?country=NG&t=… (the visitor's country, from Cloudflare when the site has it) */
    public function open(Request $request, string $campaign_id, string $user_id, TrackingService $tracking)
    {
        $in = $this->input($request, ['country' => 'nullable|string|max:64', 't' => 'nullable|string|max:64'], ['country', 't']);
        $geo = filled($in['country'] ?? null) ? $in['country'] : 'Unknown Location';

        return $this->data(['recorded' => $tracking->open($campaign_id, $user_id, $this->visitorIp($request), $geo, $in['t'] ?? null)]);
    }

    /** POST /tracking/unsubscribe/{user_id} with {"campaign": "…", "t": "…"}; 404 when the link is not valid */
    public function unsubscribe(Request $request, string $user_id, TrackingService $tracking)
    {
        $in = $this->input($request, ['campaign' => 'nullable|string|max:64', 't' => 'nullable|string|max:64'], ['campaign', 't']);

        if (!$tracking->unsubscribe($user_id, $in['campaign'] ?? null, $in['t'] ?? null)) {
            return $this->error(404, ['code' => 'not_found']);
        }

        return $this->data(['unsubscribed' => true]);
    }
}
