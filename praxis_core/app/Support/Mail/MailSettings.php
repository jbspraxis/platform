<?php

namespace App\Support\Mail;

use Illuminate\Support\Facades\DB;

/**
 * HQ Mail's own settings (mail_settings, key => JSON value). PR 1 uses `open_to` ('owner' until the owner opens
 * Mail to everyone). PR 3 keeps the company signature settings here under keys starting `signature.`, PR 4 its
 * own under `team.` and `ai.` (docs/MAIL.md, "Settings keys").
 */
final class MailSettings
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, self::$cache)) {
            $row = DB::table('mail_settings')->where('key', $key)->value('value');
            self::$cache[$key] = $row === null ? null : json_decode($row, true);
        }

        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, ?string $userId = null): void
    {
        DB::table('mail_settings')->upsert(
            [['key' => $key, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_by' => $userId, 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_by', 'updated_at'],
        );
        self::$cache[$key] = $value;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
