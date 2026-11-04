<?php

namespace App\Support\Backups;

/**
 * A site's uploaded files to one .tar.gz (owner's decision 2): its folders under the home, at the lowest priority,
 * without caches, logs, sessions and installed packages. Paths in the archive start from the home
 * (praxis_core/storage/app/private/...), so a restore puts each file back where it was.
 */
final class Archiver
{
    /**
     * @param  list<string>  $folders  under the home
     * @return array{bytes: int, sha256: string, missing: list<string>, warning: ?string}
     */
    public static function tar(array $folders, array $exclude, string $outPath): array
    {
        $home = rtrim((string) config('backups.home'), '/\\');
        $present = array_values(array_filter($folders, fn ($f) => is_dir($home . '/' . $f)));
        $missing = array_values(array_diff($folders, $present));
        if ($present === []) {
            throw new BackupFailed('archive', 'None of the site’s upload folders exist on the server: ' . implode(', ', $folders) . ' (config/backups.php).');
        }

        $command = [(string) config('backups.bin.tar'), '-czf', $outPath];
        foreach (array_unique($exclude) as $pattern) {
            $command[] = '--exclude=' . $pattern;
        }
        array_push($command, '-C', $home, ...$present);
        [$code, , $stderr] = Programs::run(Programs::niced($command));

        // GNU tar's 1 means a file changed while it was read: the archive is complete, that file may be mid-change
        if ($code > 1 || ! is_file($outPath) || filesize($outPath) === 0) {
            throw new BackupFailed('archive', "tar stopped with code {$code}: " . (Programs::lastLine($stderr) ?: 'no message'));
        }

        return [
            'bytes' => (int) filesize($outPath),
            'sha256' => hash_file('sha256', $outPath),
            'missing' => $missing,
            'warning' => $code === 1 ? Programs::lastLine($stderr) : null,
        ];
    }
}
