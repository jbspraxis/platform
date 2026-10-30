<?php

namespace App\Http\Controllers;

use App\Exceptions\Hq\HqNotFound;
use App\Exceptions\Hq\HqUnavailable;
use App\Exceptions\Hq\HqValidationException;
use App\Http\Middleware\FormGuard;
use App\Support\HqApi;
use App\Support\VisitorIp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Links in broadcast emails: the open pixel and the unsubscribe page (same paths as today). New links
 * carry a token "t" (and the campaign, for unsubscribe) that HQ checks (docs/SITE_API.md 6.4); the site
 * passes them through unchanged. Links in campaigns sent before keep working without them.
 */
class TrackingController extends Controller
{
    /** A transparent 1x1 PNG. */
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * HQ records the open (2 s, failures ignored); the email client always gets its image. The country is
     * Cloudflare's CF-IPCountry, believed only when the request really came through Cloudflare.
     * Mail image proxies (Gmail, Outlook) fetch for many readers from a few addresses, so past the
     * per-visitor limit the image is still returned and only the call to HQ is skipped.
     */
    public function open(Request $request, string $campaign_id, string $user_id, HqApi $api): Response
    {
        $limit = max(1, (int) config('site.limits.pixel_per_minute', 60));
        $key = 'pixel|' . VisitorIp::key($request);
        if (! RateLimiter::tooManyAttempts($key, $limit)) {
            RateLimiter::hit($key, 60);
            $api->trackOpen($campaign_id, $user_id, VisitorIp::country($request), self::token($request->query('t')));
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /** Unsubscribing needs a click: email security scanners open links on their own. */
    public function confirm(Request $request, string $user_id)
    {
        return view('pages.unsubscribe', [
            'userId' => $user_id,
            'campaign' => self::campaign(old('campaign', $request->query('campaign'))),
            'token' => self::token(old('t', $request->query('t'))),
            'done' => session('sent') === 'unsubscribe',
        ]);
    }

    public function unsubscribe(Request $request, string $user_id, HqApi $api)
    {
        $campaign = self::campaign($request->input('campaign'));
        $token = self::token($request->input('t'));
        $back = route('unsubscribe', array_filter(['user_id' => $user_id, 'campaign' => $campaign, 't' => $token]));

        try {
            $api->unsubscribe($user_id, $campaign, $token);
        } catch (HqNotFound|HqValidationException) {
            return $this->invalidLink($request, $back, $api);
        } catch (HqUnavailable $e) {
            // 400 or 403: HQ did not accept this link's token; anything else is the usual calm message
            if (in_array($e->getCode(), [400, 403, 410], true)) {
                return $this->invalidLink($request, $back, $api);
            }
            throw $e;
        }

        return redirect()->to(route('unsubscribe', ['user_id' => $user_id]))->with('sent', 'unsubscribe');
    }

    private function invalidLink(Request $request, string $back, HqApi $api)
    {
        return redirect()->to($back)
            ->withInput($request->except(FormGuard::NOT_KEPT))
            ->with('hq_error', __('site.unsubscribe.invalid_link', ['email' => $api->contactEmail()]));
    }

    /** The link's token: 32 hex characters, or null. */
    private static function token(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Fa-f0-9]{32}$/', $value) ? strtolower($value) : null;
    }

    private static function campaign(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $value) ? $value : null;
    }
}
