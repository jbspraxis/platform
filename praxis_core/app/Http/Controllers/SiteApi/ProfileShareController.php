<?php

namespace App\Http\Controllers\SiteApi;

use App\Support\CompanyProfile\CompanyProfileRenderer;
use App\Support\CompanyProfile\MissingProfile;
use App\Support\CompanyProfile\Shares;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * What jbspraxis.com/profile/{token} reads (docs/COMPANY_PROFILE.md section 14).
 *
 * One route, two answers. A link that is still open answers with the **PDF itself**, rendered through the
 * same renderer as the HQ download, so the held/enabled and naming rules of company-profile/CONTRACT.md hold
 * here exactly as they do everywhere else. A link that has been revoked, has expired, or was never given out
 * answers `410 link_ended` with the state and nothing else — the same shape for all three, so a visitor
 * cannot tell a revoked link from a token nobody was ever given.
 *
 * The open is counted here, where the PDF is actually handed over, so a link cannot be counted as opened by
 * someone who was refused it.
 */
class ProfileShareController extends SiteApiController
{
    public function show(Request $request, string $token)
    {
        $link = Shares::find($token);

        // A token nobody was given, a revoked link and an expired one all answer alike
        if (! $link || ! $link->isOpen()) {
            return $this->error(410, ['code' => 'link_ended', 'state' => $link?->state() ?? 'not_found']);
        }

        if (Shares::tooMany($link)) {
            return $this->error(429, ['code' => 'too_many', 'retry_after' => 300]);
        }

        try {
            $data = Shares::data($link);
        } catch (MissingProfile) {
            // The profile it pointed at is gone: say the link has ended rather than leak what happened
            return $this->error(410, ['code' => 'link_ended', 'state' => 'not_found']);
        }

        $pdf = app(CompanyProfileRenderer::class)->pdf($data);

        Shares::record($link, $request->userAgent(), $request->header('X-Site-Visitor-Ip') ?: $request->ip());

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . Shares::filename($data, $link->locale) . '"',
            // Never cached by anything in between: the link can be revoked at any moment
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
