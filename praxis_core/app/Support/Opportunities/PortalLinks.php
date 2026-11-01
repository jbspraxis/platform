<?php

namespace App\Support\Opportunities;

use App\Models\Opportunity;
use App\Models\Proposal;
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

    /**
     * One version of the proposal in the portal: read it, comment by section, approve it or ask for changes, book a
     * follow-up. $clientUserId fills in who is reviewing. Valid as long as the proposal (validUntil(), which can be
     * longer than DAYS); before it is sent, DAYS.
     */
    public static function proposal(Proposal $proposal, ?string $clientUserId = null): string
    {
        return self::signed('portal.proposals.review', array_filter(['proposal' => $proposal->id, 'c' => $clientUserId]), self::proposalExpiry($proposal));
    }

    /** The PDF of that version, from the portal page; it expires with the proposal's link */
    public static function proposalPdf(Proposal $proposal): string
    {
        return self::signed('portal.proposals.pdf', ['proposal' => $proposal->id], self::proposalExpiry($proposal));
    }

    private static function proposalExpiry(Proposal $proposal): \DateTimeInterface
    {
        return $proposal->validUntil() ?? now()->addDays(self::DAYS);
    }

    private static function signed(string $route, array $parameters, \DateTimeInterface $expires): string
    {
        $relative = URL::temporarySignedRoute($route, $expires, $parameters, absolute: false);
        $origin = preg_replace('~^(https?://[^/]+).*$~', '$1', route($route, ['proposal' => $parameters['proposal']]));

        return $origin . $relative;
    }
}
