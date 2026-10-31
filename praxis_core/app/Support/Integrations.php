<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settings of HQ › System › Integrations (docs/OPPORTUNITIES.md 6.1, 6.2), in integration_settings. Secrets are
 * stored encrypted with Crypt and never shown again: the screen only says whether one is saved. Each key has an
 * .env fallback (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, ANTHROPIC_API_KEY) read only when nothing is saved here.
 *
 *   Integrations::get('google.client_id')       the value, or the .env fallback, or null
 *   Integrations::set('anthropic.api_key', $v)  encrypts secrets; '' or null removes the key
 */
final class Integrations
{
    public const SECRETS = ['google.client_secret', 'google.refresh_token', 'anthropic.api_key'];

    /** Keys and their config() fallback */
    private const FALLBACKS = [
        'google.client_id' => 'services.google.client_id',
        'google.client_secret' => 'services.google.client_secret',
        'anthropic.api_key' => 'services.anthropic.api_key',
        'ai.monthly_budget_usd' => 'ai.monthly_budget_usd',
    ];

    /** Values read in this request or queue job (a scoped instance: Laravel drops it between jobs) */
    private static function cache(): \ArrayObject
    {
        if (! app()->bound(self::class . '.cache')) {
            app()->scoped(self::class . '.cache', fn () => new \ArrayObject);
        }

        return app(self::class . '.cache');
    }

    public static function get(string $key): ?string
    {
        $cache = self::cache();
        if (! $cache->offsetExists($key)) {
            $stored = DB::table('integration_settings')->where('key', $key)->value('value');
            if ($stored !== null && in_array($key, self::SECRETS, true)) {
                try {
                    $stored = Crypt::decryptString($stored);
                } catch (DecryptException) {
                    // APP_KEY changed since it was saved: treat it as missing, so the screen asks for it again
                    Log::warning('Integrations: a saved secret could not be decrypted', ['key' => $key]);
                    $stored = null;
                }
            }
            $cache[$key] = $stored;
        }

        $value = $cache[$key];
        if (($value === null || $value === '') && isset(self::FALLBACKS[$key])) {
            $value = config(self::FALLBACKS[$key]);
        }

        return $value === null || $value === '' ? null : (string) $value;
    }

    /** Whether the value is saved in HQ (not only in .env) */
    public static function saved(string $key): bool
    {
        return DB::table('integration_settings')->where('key', $key)->whereNotNull('value')->exists();
    }

    public static function set(string $key, ?string $value, ?string $by = null): void
    {
        $value = $value === null ? null : trim($value);
        if ($value === null || $value === '') {
            DB::table('integration_settings')->where('key', $key)->delete();
        } else {
            $row = [
                'value' => in_array($key, self::SECRETS, true) ? Crypt::encryptString($value) : $value,
                'updated_by' => $by ?? Auth::id(),
                'updated_at' => now(),
            ];
            if (DB::table('integration_settings')->where('key', $key)->exists()) {
                DB::table('integration_settings')->where('key', $key)->update($row);
            } else {
                DB::table('integration_settings')->insert($row + ['key' => $key, 'created_at' => now()]);
            }
        }
        self::cache()->offsetUnset($key);
    }

    public static function updatedAt(string $key): ?string
    {
        return DB::table('integration_settings')->where('key', $key)->value('updated_at');
    }

    public static function flush(): void
    {
        self::cache()->exchangeArray([]);
    }
}
