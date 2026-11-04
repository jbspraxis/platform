<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Connect Google (HQ › System › Integrations, docs/OPPORTUNITIES.md 6.1): sends the owner to Google's consent page
 * and takes them back on /integrations/google/callback, the redirect URI registered on the OAuth client. A random
 * state in the session ties the two together, so a callback HQ didn't start is refused.
 */
class GoogleOAuthController extends Controller
{
    private const STATE = 'integrations.google.state';

    public function connect(Request $request, GoogleWorkspace $google): RedirectResponse
    {
        if (! $google->hasClient()) {
            return redirect()->route('hq.system.integrations')->with('error', __('integrations.google.flash.client_first'));
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE, $state);

        return redirect()->away($google->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleWorkspace $google): RedirectResponse
    {
        $expected = (string) $request->session()->pull(self::STATE, '');
        $back = redirect()->route('hq.system.integrations');

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state', ''))) {
            return $back->with('error', __('integrations.google.flash.state'));
        }
        if ($request->filled('error')) {
            return $back->with('error', __('integrations.google.flash.cancelled'));
        }
        if (! is_string($request->query('code')) || $request->query('code') === '') {
            return $back->with('error', __('integrations.google.flash.failed'));
        }

        try {
            $email = $google->connect($request->query('code'));
        } catch (GoogleUnavailable $e) {
            return $back->with('error', $e->getMessage());
        }

        OpportunityAccess::log('INTEGRATION_GOOGLE_CONNECTED', "Connected Google as {$email}.", 'integration_settings', 'google.refresh_token',
            [], ['account' => $email, 'scopes' => $google->grantedScopes()]);

        $flash = $back->with('success', __('integrations.google.flash.connected', ['email' => $email]));
        $missing = $google->missingScopes();
        $expectedAccount = (string) config('services.google.account');
        if ($missing) {
            $flash->with('warning', __('integrations.google.flash.missing_scopes'));
        } elseif ($expectedAccount !== '' && strcasecmp($email, $expectedAccount) !== 0) {
            $flash->with('warning', __('integrations.google.flash.other_account', ['email' => $email, 'expected' => $expectedAccount]));
        } elseif (! $google->canUseDrive()) {
            // Meetings work; only the server backups (docs/BACKUPS.md) wait for the Drive box to be ticked
            $flash->with('warning', __('integrations.google.drive.missing'));
        }

        return $flash;
    }
}
