<?php

namespace App\Support\Backups;

/**
 * Running mysqldump, mysql and tar without a shell (no quoting, no password on a command line), at the lowest CPU and
 * disk priority the server allows, so the shared server's other sites don't feel the backups.
 */
final class Programs
{
    /** The command behind `nice -n 19` (and `ionice -c3`, idle disk priority) where they exist; as is on Windows */
    public static function niced(array $command): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $command;
        }
        $prefix = [];
        if ($nice = self::find('nice')) {
            array_push($prefix, $nice, '-n', '19');
        }
        if ($ionice = self::find('ionice')) {
            array_push($prefix, $ionice, '-c3');
        }

        return [...$prefix, ...$command];
    }

    /** Lower this PHP process's own priority too (the encryption and the uploads run in it) */
    public static function lowerOwnPriority(): void
    {
        if (function_exists('proc_nice') && PHP_OS_FAMILY !== 'Windows') {
            @proc_nice(10);
        }
    }

    /**
     * Runs a command to the end. Its output goes to temporary files, not memory, and only the last part is kept.
     *
     * @return array{0: int, 1: string, 2: string} exit code, end of stdout, end of stderr
     */
    public static function run(array $command, ?string $cwd = null): array
    {
        $out = tempnam(sys_get_temp_dir(), 'bko');
        $err = tempnam(sys_get_temp_dir(), 'bke');
        try {
            $process = proc_open($command, [0 => ['file', self::nullDevice(), 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']], $pipes, $cwd);
            if (! is_resource($process)) {
                throw new BackupFailed('run', 'Could not start ' . basename((string) $command[0]) . '.');
            }
            $code = proc_close($process);

            return [$code, self::tail($out), self::tail($err)];
        } finally {
            @unlink($out);
            @unlink($err);
        }
    }

    public static function nullDevice(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }

    /** The last few lines of a file, for an error message */
    public static function tail(string $path, int $bytes = 1500): string
    {
        $size = @filesize($path) ?: 0;
        if ($size === 0) {
            return '';
        }
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - $bytes));
        $text = (string) fread($handle, $bytes);
        fclose($handle);

        return trim($text);
    }

    /** The last meaningful line of a program's error output */
    public static function lastLine(string $text): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));

        return mb_substr((string) end($lines), 0, 300);
    }

    private static function find(string $name): ?string
    {
        foreach (['/usr/bin/', '/bin/', '/usr/local/bin/'] as $dir) {
            if (is_executable($dir . $name)) {
                return $dir . $name;
            }
        }

        return null;
    }
}
