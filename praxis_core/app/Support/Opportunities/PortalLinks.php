<?php

namespace App\Support\Opportunities;

use App\Models\Opportunity;
use Illuminate\Support\Facades\URL;

/**
 * Links a client opens from an email (docs/OPPORTUNITIES.md 9: portal pages by signed, expiring token links).
 * The signature covers the path and query only (signed:relative), so the link checks the same behind the live
 * proxy as locally; the portal's origin comes from route(), as in every other email.
 */
final class PortalLinks
{
    public const DAYS = 60;

    /**
     * The client's meetings page for this opportunity: upcoming meetings, the minutes they were sent, and booking a
     * follow-up (free times or "propose times"). $clientUserId fills in who is booking; no other personal data in it.
     */
    public static function meetings(Opportunity $opportunity, ?string $clientUserId = null): string
    {
        $parameters = array_filter(['opportunity' => $opportunity->id, 'c' => $clientUserId]);
        $relative = URL::temporarySignedRoute('portal.meetings', now()->addDays(self::DAYS), $parameters, absolute: false);
        $origin = preg_replace('~^(https?://[^/]+).*$~', '$1', route('portal.meetings', ['opportunity' => $opportunity->id]));

        return $origin . $relative;
    }
}
