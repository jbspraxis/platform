<?php

namespace App\Support\Monitor;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reads the server's log files from where the last run stopped and records new
 * warnings and errors in the monitor. It catches what captureException() cannot:
 * warnings the code writes itself (failed emails, AI provider errors) and PHP
 * errors from before Laravel starts (cPanel's error_log files).
 *
 * Exceptions Laravel logs with a stack trace were normally recorded already by
 * ErrorMonitor::captureException(); they are only imported here when no event
 * for that exception class exists within a few seconds of the log time.
 */
class LogScanner
{
    private const CHUNK = 2 * 1024 * 1024;        // read at most this much per file per run
    private const LEVELS = ['WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /** Returns the number of entries recorded. */
    public static function scanAll(): int
    {
        $home = dirname(base_path());
        $files = array_merge(
            glob(storage_path('logs/laravel-*.log')) ?: [],
            [storage_path('logs/laravel.log'), base_path('error_log'), $home . '/public_html/PRAXIS/error_log']
        );

        $recorded = 0;
        foreach (array_unique($files) as $file) {
            if (is_file($file) && is_readable($file)) {
                $recorded += self::scanFile($file);
            }
        }
        return $recorded;
    }

    private static function scanFile(string $file): int
    {
        $key = 'log_offset:' . ErrorMonitor::relativePath($file);
        $size = filesize($file);
        $offset = ErrorMonitor::state($key);

        // A file seen for the first time: start at its end (do not import history)
        if ($offset === null) {
            ErrorMonitor::setState($key, $size);
            return 0;
        }
        $offset = (int) $offset;
        if ($size < $offset) {
            $offset = 0; // the file was rotated or emptied
        }
        if ($size === $offset) {
            return 0;
        }

        $fh = fopen($file, 'rb');
        fseek($fh, $offset);
        $chunk = fread($fh, self::CHUNK);
        fclose($fh);

        // Only process complete lines; the rest is read next time
        $lastNewline = strrpos($chunk, "\n");
        if ($lastNewline === false) {
            return 0;
        }
        $chunk = substr($chunk, 0, $lastNewline + 1);
        ErrorMonitor::setState($key, $offset + strlen($chunk));

        return str_ends_with($file, 'error_log') ? self::importPhpLog($chunk) : self::importLaravelLog($chunk);
    }

    private static function importLaravelLog(string $text): int
    {
        // Entries start with "[2026-10-29 11:52:31] production.WARNING: message {context}"
        $parts = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*\] \w+\.[A-Z]+:)/m', $text, -1, PREG_SPLIT_NO_EMPTY);
        $count = 0;
        foreach ($parts as $entry) {
            if (!preg_match('/^\[([^\]]+)\] \w+\.([A-Z]+): (.*)$/s', $entry, $m) || !in_array($m[2], self::LEVELS, true)) {
                continue;
            }
            $time = self::time($m[1]);
            $body = $m[3];
            $level = strtolower($m[2]);

            // An exception logged by Laravel: "message {"exception":"[object] (Class(code: 0): msg at /path/file.php:12)"} [stacktrace] ..."
            if (preg_match('/\{"exception":"\[object\] \(([^(]+)\(code: [^)]*\): (.*?) at (\S+?):(\d+)\)/s', $body, $x)) {
                $class = stripslashes(trim($x[1]));
                if (self::alreadyRecorded($class, $time)) {
                    continue;
                }
                $trace = str_contains($body, '[stacktrace]') ? trim(substr($body, strpos($body, '[stacktrace]') + 12)) : null;
                ErrorMonitor::record([
                    'source' => 'log', 'level' => $level, 'exception_class' => $class,
                    'message' => stripslashes($x[2]), 'file' => stripslashes($x[3]), 'line' => (int) $x[4],
                    'trace' => $trace ? implode("\n", array_slice(explode("\n", $trace), 0, 40)) : null,
                    'occurred_at' => $time,
                ]);
                $count++;
                continue;
            }

            // A plain warning or error line written by the code: "message {json context}"
            $message = trim(preg_replace('/\s*(\{.*\}|\[\])\s*(\[\])?\s*$/s', '', strtok($body, "\n")) ?: $body);
            $context = preg_match('/(\{.*\})\s*(\[\])?\s*$/s', strtok($body, "\n") ?: '', $c) ? json_decode($c[1], true) : null;
            ErrorMonitor::record([
                'source' => 'log', 'level' => $level, 'exception_class' => null,
                'message' => $message . (is_array($context) && isset($context['error']) ? ' (' . $context['error'] . ')' : ''),
                'file' => null, 'line' => null, 'occurred_at' => $time,
                'context' => is_array($context) ? $context : null,
            ]);
            $count++;
        }
        return $count;
    }

    private static function importPhpLog(string $text): int
    {
        // "[29-Oct-2026 11:00:00 UTC] PHP Fatal error:  Uncaught Error: ... in /home/x/file.php:12"
        $count = 0;
        foreach (preg_split('/^(?=\[\d{2}-\w{3}-\d{4} )/m', $text, -1, PREG_SPLIT_NO_EMPTY) as $entry) {
            if (!preg_match('/^\[([^\]]+)\] PHP (Fatal error|Parse error|Warning|Recoverable fatal error):\s+(.*)$/s', $entry, $m)) {
                continue; // notices and deprecations are not worth an alert
            }
            $first = strtok($m[3], "\n");
            $file = null;
            $line = null;
            if (preg_match('/ in (\S+?)(?::| on line )(\d+)/', $first, $w)) {
                $file = $w[1];
                $line = (int) $w[2];
            }
            ErrorMonitor::record([
                'source' => 'php', 'level' => $m[2] === 'Warning' ? 'warning' : 'critical',
                'exception_class' => 'PHP ' . $m[2],
                'message' => trim(preg_replace('/ in \S+?(?::| on line )\d+.*$/s', '', $first)),
                'file' => $file, 'line' => $line,
                'trace' => trim(substr($m[3], strlen($first))) ?: null,
                'occurred_at' => self::time($m[1]),
            ]);
            $count++;
        }
        return $count;
    }

    private static function alreadyRecorded(string $class, Carbon $time): bool
    {
        return DB::table('monitor_error_events')
            ->join('monitor_error_groups', 'monitor_error_groups.id', '=', 'monitor_error_events.group_id')
            ->where('monitor_error_groups.exception_class', $class)
            ->whereBetween('monitor_error_events.occurred_at', [$time->copy()->subSeconds(5), $time->copy()->addSeconds(5)])
            ->exists();
    }

    private static function time(string $stamp): Carbon
    {
        try {
            return Carbon::parse($stamp, config('app.timezone'));
        } catch (\Throwable $e) {
            return now();
        }
    }
}
