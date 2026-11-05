<?php

namespace App\Http\Controllers;

use App\Exceptions\Hq\HqGone;
use App\Exceptions\Hq\HqRateLimited;
use App\Support\HqApi;
use Symfony\Component\HttpFoundation\Response;

/**
 * /profile/{token}: a tracked share link for the company profile (docs/COMPANY_PROFILE.md section 14).
 *
 * The address is private rather than secret-keeping: it carries a 48-character token and nothing else, so it
 * cannot be guessed, and it is never indexed. A link that is still open streams the PDF straight through from
 * HQ, so the visitor never needs an address on hq.jbspraxis.com. A link that has ended gets a plain branded
 * page saying so - the same page whether it was revoked, has expired, or was never a link at all, so a
 * stranger who tries a token learns nothing from the difference.
 */
class ProfileShareController extends Controller
{
    public function show(string $token, HqApi $api): Response
    {
        try {
            $pdf = $api->profileShare($token);
        } catch (HqGone $e) {
            return response()->view('pages.profile-link', ['state' => $e->state()], 410);
        } catch (HqRateLimited) {
            return response()->view('pages.profile-link', ['state' => 'too_many'], 429);
        }

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="JBS Praxis company profile.pdf"',
            // The link can be revoked at any moment, so nothing in between may keep a copy
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
