<?php

namespace App\Support\Pwa;

use Illuminate\Support\Facades\DB;

/** Which notification groups a person has muted (push_preferences.muted; nothing is muted by default). */
class PushPreferences
{
    public static function muted(string $userId): array
    {
        $raw = DB::table('push_preferences')->where('user_id', $userId)->value('muted');
        $list = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($list) ? array_values(array_intersect(array_keys(PushCategories::LABELS), $list)) : [];
    }

    public static function isMuted(string $userId, string $category): bool
    {
        return in_array($category, self::muted($userId), true);
    }

    /** Turn one group on or off for this person; returns the muted groups afterwards. */
    public static function set(string $userId, string $category, bool $on): array
    {
        if (! isset(PushCategories::LABELS[$category])) {
            throw new \InvalidArgumentException("Unknown notification group: {$category}");
        }

        $muted = self::muted($userId);
        $muted = $on ? array_diff($muted, [$category]) : array_unique([...$muted, $category]);
        $muted = array_values(array_intersect(array_keys(PushCategories::LABELS), $muted));

        DB::table('push_preferences')->upsert(
            [['user_id' => $userId, 'muted' => json_encode($muted), 'created_at' => now(), 'updated_at' => now()]],
            ['user_id'],
            ['muted', 'updated_at'],
        );

        return $muted;
    }
}
