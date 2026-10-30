<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile (docs/SITE_API.md section 6.6). Off while TURNSTILE_SITE_KEY or
 * TURNSTILE_SECRET_KEY is empty. When on, every form shows the widget and the token is checked with
 * Cloudflare (siteverify) before anything is sent to HQ. The secret is never logged.
 */
final class Turnstile
{
    /** The form field Cloudflare's widget fills in. */
    public const FIELD = 'cf-turnstile-response';

    public static function enabled(): bool
    {
        return self::siteKey() !== '' && (string) config('services.turnstile.secret_key', '') !== '';
    }

    public static function siteKey(): string
    {
        return trim((string) config('services.turnstile.site_key', ''));
    }

    /**
     * True when Cloudflare confirms the token. A missing token, a refusal or no answer from Cloudflare
     * is false: the form is not sent and the visitor is asked to try again.
     */
    public static function verify(mixed $token, string $visitorIp): bool
    {
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout', 5))
                ->connectTimeout(3)
                ->withoutRedirecting()
                ->post((string) config('services.turnstile.verify_url'), [
                    'secret' => (string) config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $visitorIp,
                ]);
        } catch (Throwable) {
            Log::warning('Turnstile: Cloudflare did not answer');

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile: Cloudflare answered with an error', ['status' => $response->status()]);

            return false;
        }

        return $response->json('success') === true;
    }
}
