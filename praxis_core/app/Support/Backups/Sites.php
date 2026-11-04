<?php

namespace App\Support\Backups;

/**
 * The five sites of config/backups.php and which of the account's databases belongs to which (docs/BACKUPS.md 2).
 * A database no site claims is "other/<database>": still backed up, in its own Drive folder, and the owner is rung.
 */
final class Sites
{
    public const OTHER = 'other';

    /** @return array<string, array{label: string, databases: list<string>, files: list<string>, exclude: list<string>}> */
    public static function all(): array
    {
        $out = [];
        foreach ((array) config('backups.sites') as $key => $site) {
            $out[$key] = [
                'label' => (string) ($site['label'] ?? $key),
                'databases' => array_values((array) ($site['databases'] ?? [])),
                'files' => array_values((array) ($site['files'] ?? [])),
                'exclude' => array_values((array) ($site['exclude'] ?? [])),
            ];
        }

        return $out;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $site): string
    {
        return self::all()[$site]['label'] ?? $site;
    }

    /** 'praxis' for smartqaq_praxis_core; 'other/smartqaq_wp1' for a database no site names */
    public static function forDatabase(string $database): string
    {
        foreach (self::all() as $key => $site) {
            foreach ($site['databases'] as $pattern) {
                if (fnmatch(strtolower($pattern), strtolower($database))) {
                    return $key;
                }
            }
        }

        return self::OTHER . '/' . $database;
    }

    public static function isOther(string $site): bool
    {
        return str_starts_with($site, self::OTHER . '/');
    }

    /**
     * Groups the databases by site, sorted, so file names can tell a site's databases apart only when it has several
     *
     * @param  list<string>  $databases
     * @return array<string, list<string>>
     */
    public static function group(array $databases): array
    {
        $groups = [];
        sort($databases);
        foreach ($databases as $db) {
            $groups[self::forDatabase($db)][] = $db;
        }

        return $groups;
    }

    /**
     * The file name on Drive and on the server: it starts with the site and the date.
     *   praxis_db_2026-11-05.sql.gz.enc                     the site's only database
     *   lpc_db_2026-11-05_lpc_mail.sql.gz.enc               one of several (the name without the cPanel prefix)
     *   wp1_db_2026-11-05.sql.gz.enc                        under other/smartqaq_wp1
     *   praxis_files_2026-11-08.tar.gz.enc
     */
    public static function dbFileName(string $site, string $database, bool $several, string $date): string
    {
        $short = self::short($database);
        if (self::isOther($site)) {
            return self::slug($short) . "_db_{$date}.sql.gz.enc";
        }

        return $site . "_db_{$date}" . ($several ? '_' . self::slug($short) : '') . '.sql.gz.enc';
    }

    public static function filesFileName(string $site, string $date): string
    {
        return $site . "_files_{$date}.tar.gz.enc";
    }

    /** The date in a backup's file name, or null when it isn't one of ours */
    public static function dateIn(string $name): ?string
    {
        return preg_match('/_(?:db|files)_(\d{4}-\d{2}-\d{2})(?:[_.])/', $name, $m) ? $m[1] : null;
    }

    /** smartqaq_lpc_mail -> lpc_mail */
    public static function short(string $database): string
    {
        $prefix = (string) config('backups.cpanel_user') . '_';

        return str_starts_with($database, $prefix) ? substr($database, strlen($prefix)) : $database;
    }

    private static function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($value)), '-') ?: 'db';
    }
}
