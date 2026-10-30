<?php

namespace App\Support\Pwa;

use App\Models\User;

/**
 * The groups a person can mute in their notification settings (docs/PWA.md). A bell notification
 * belongs to the HQ section its link opens, the same sections as the rail; without a link,
 * its stored type decides.
 */
class PushCategories
{
    public const LABELS = [
        'leads' => 'Leads and proposals',
        'projects' => 'Projects',
        'finance' => 'Finance',
        'team' => 'Team',
        'marketing' => 'Marketing',
        'mail' => 'Mail',
        'system' => 'System health',
        'other' => 'Other updates',
    ];

    // Path prefixes on the HQ host (links are stored as full HQ addresses or as host-relative paths)
    private const PATHS = [
        'intelligence/' => 'leads',
        'operations/' => 'projects',
        'finance/' => 'finance',
        'hr/' => 'team',
        'mdm/employees' => 'team',
        'marketing/' => 'marketing',
        'system/' => 'system',
        'mail' => 'mail',
    ];

    private const TYPES = [
        'Financial' => 'finance',
        'Project' => 'projects',
        'QA_Update' => 'projects',
        'System_Alert' => 'system',
    ];

    public static function forNotification(?string $link, ?string $type): string
    {
        $path = ltrim((string) parse_url((string) $link, PHP_URL_PATH), '/');
        if ($path !== '') {
            foreach (self::PATHS as $prefix => $category) {
                if (str_starts_with($path, $prefix)) {
                    return $category;
                }
            }
        }

        return self::TYPES[(string) $type] ?? 'other';
    }

    /** The groups listed in this person's settings: System health only for those who can open it. */
    public static function for(User $user): array
    {
        return array_filter(self::LABELS, fn ($label, $key) => $key !== 'system' || $user->can('system.view'), ARRAY_FILTER_USE_BOTH);
    }
}
