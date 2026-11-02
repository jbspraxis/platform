<?php

namespace App\Support\Opportunities;

use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\StatementOfWork;
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

    /**
     * One version of the SOW in the portal (PR 5): read it, sign it with the one-time code or ask for changes, and its
     * PDF. Signed until the version's validity ends, as proposals are; before it is sent, DAYS.
     */
    public static function sow(StatementOfWork $sow, ?string $clientUserId = null): string
    {
        return self::signed('portal.sow', array_filter(['sow' => $sow->id, 'c' => $clientUserId]), self::sowExpiry($sow));
    }

    public static function sowPdf(StatementOfWork $sow): string
    {
        return self::signed('portal.sow.pdf', ['sow' => $sow->id], self::sowExpiry($sow));
    }

    private static function sowExpiry(StatementOfWork $sow): \DateTimeInterface
    {
        return $sow->validUntil() ?? now()->addDays(self::DAYS);
    }

    /** How long a link made by fromPortal() stays good */
    public const HOURS = 4;

    /**
     * A sent proposal or SOW (its page or its PDF) opened from the signed-in portal's Documents: made each time the
     * page is drawn, so a signed SOW stays reachable after the email's link has expired.
     * $route: portal.proposals.review, portal.proposals.pdf, portal.sow, portal.sow.pdf or portal.meetings.
     */
    public static function fromPortal(string $route, string $id, ?string $clientUserId = null): string
    {
        $key = match (true) {
            str_starts_with($route, 'portal.sow') => 'sow',
            $route === 'portal.meetings' => 'opportunity',
            default => 'proposal',
        };

        return self::signed($route, array_filter([$key => $id, 'c' => $clientUserId]), now()->addHours(self::HOURS));
    }

    private static function signed(string $route, array $parameters, \DateTimeInterface $expires): string
    {
        $relative = URL::temporarySignedRoute($route, $expires, $parameters, absolute: false);
        $key = array_key_first($parameters);
        $origin = preg_replace('~^(https?://[^/]+).*$~', '$1', route($route, [$key => $parameters[$key]]));

        return $origin . $relative;
    }
}
