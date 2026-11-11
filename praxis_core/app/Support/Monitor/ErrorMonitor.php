<?php

namespace App\Support\Monitor;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The error side of the HQ System Health monitor.
 *
 *   captureException($e)   from bootstrap/app.php for every reported exception (never throws)
 *   safeMessage($e)        the message without personal data: database errors keep the SQL with ? placeholders
 *                          and lose the values (docs/SITE_API.md section 6.3)
 *   logSafely($e)          writes a database error to laravel.log the same way (bootstrap/app.php)
 *   record([...])          store one occurrence: grouped by fingerprint, latest 50 events kept
 *   explain($group)        a plain-English hint for common errors
 *   brief($group)          everything an AI needs to fix it, as Markdown ("Copy for AI")
 *   state() / setState()   small key-value store: heartbeat, log positions, alert throttles
 */
class ErrorMonitor
{
    private const EVENTS_KEPT = 50;
    private static bool $recording = false;

    public static function captureException(Throwable $e): void
    {
        if (self::$recording) {
            return; // an error while recording an error must not loop
        }
        self::$recording = true;

        try {
            [$file, $line] = self::appFrame($e);
            $request = app()->runningInConsole() ? null : request();

            self::record([
                'source' => 'exception',
                'level' => 'error',
                'exception_class' => get_class($e),
                'message' => self::safeMessage($e) ?: get_class($e),
                'file' => $file,
                'line' => $line,
                'trace' => self::trace($e),
                'occurred_at' => now(),
                'url' => $request ? self::cleanUrl($request->fullUrl()) : 'artisan ' . implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 3)),
                'method' => $request?->method(),
                'user_id' => Auth::id(),
                'component' => $request ? self::livewireComponent($request, $e) : null,
            ]);
        } catch (Throwable $ignored) {
            // The database may be the thing that is down; the log file still has the error
            // and the log scanner records it later.
        } finally {
            self::$recording = false;
        }
    }

    /** Store one occurrence. Returns the group id. */
    public static function record(array $e): int
    {
        $now = $e['occurred_at'] ?? now();
        $file = self::relativePath($e['file'] ?? null);
        $fingerprint = sha1(implode('|', [
            $e['source'], $e['exception_class'] ?? '', self::normalise($e['message']), $file ?? '', $e['line'] ?? '',
        ]));

        $group = DB::table('monitor_error_groups')->where('fingerprint', $fingerprint)->first();

        if (!$group) {
            $groupId = DB::table('monitor_error_groups')->insertGetId([
                'fingerprint' => $fingerprint,
                'source' => $e['source'],
                'level' => $e['level'],
                'exception_class' => $e['exception_class'] ?? null,
                'message' => mb_substr($e['message'], 0, 5000),
                'file' => $file,
                'line' => $e['line'] ?? null,
                'occurrences' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'status' => 'new',
                'trace' => isset($e['trace']) ? mb_substr($e['trace'], 0, 60000) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $groupId = $group->id;
            $update = [
                'occurrences' => DB::raw('occurrences + 1'),
                'last_seen_at' => $now,
                'updated_at' => now(),
            ];
            // A fixed error that comes back is a regression: reopen it and alert again
            if ($group->status === 'fixed') {
                $update['status'] = 'new';
                $update['alerted_at'] = null;
                $update['notes'] = trim(($group->notes ?? '') . "\n[" . now()->format('Y-m-d H:i') . "] Came back after being marked fixed.");
            }
            if (!$group->trace && !empty($e['trace'])) {
                $update['trace'] = mb_substr($e['trace'], 0, 60000);
            }
            DB::table('monitor_error_groups')->where('id', $groupId)->update($update);
        }

        DB::table('monitor_error_events')->insert([
            'group_id' => $groupId,
            'occurred_at' => $now,
            'url' => isset($e['url']) ? mb_substr($e['url'], 0, 1000) : null,
            'method' => $e['method'] ?? null,
            'user_id' => $e['user_id'] ?? null,
            'component' => $e['component'] ?? null,
            'context' => isset($e['context']) ? json_encode($e['context']) : null,
        ]);

        // Keep the latest events only (checked now and then, not on every insert)
        if (random_int(1, 20) === 1) {
            $keepFrom = DB::table('monitor_error_events')->where('group_id', $groupId)
                ->orderByDesc('id')->skip(self::EVENTS_KEPT)->value('id');
            if ($keepFrom) {
                DB::table('monitor_error_events')->where('group_id', $groupId)->where('id', '<=', $keepFrom)->delete();
            }
        }

        return $groupId;
    }

    /**
     * The message as it may be stored and shown: for a database error, the driver's message with any quoted
     * value removed, then the SQL with its ? placeholders (never the bound values, which hold visitors' data).
     */
    public static function safeMessage(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            $driver = $e->getPrevious() ? $e->getPrevious()->getMessage() : '';
            return trim(self::withoutValues($driver) . ' (Connection: ' . $e->getConnectionName() . ', SQL: ' . $e->getSql() . ')');
        }
        if ($e instanceof \PDOException) {
            return self::withoutValues($e->getMessage());
        }

        return $e->getMessage();
    }

    /**
     * A database error in laravel.log without bound values. bootstrap/app.php calls it for QueryException and stops
     * Laravel's own log line (which would hold them). The context is shaped like Laravel's, so the log scanner
     * recognises the error that captureException() already recorded.
     */
    public static function logSafely(Throwable $e): void
    {
        $message = self::safeMessage($e);
        $trace = [];
        foreach (array_slice($e->getTrace(), 0, 20) as $i => $f) {
            $trace[] = '#' . $i . ' ' . (isset($f['file']) ? self::relativePath($f['file']) . '(' . ($f['line'] ?? '?') . ')' : '[internal]') . ': '
                . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
        }
        Log::error($message, [
            'exception' => '[object] (' . get_class($e) . '(code: ' . $e->getCode() . '): ' . $message . ' at ' . $e->getFile() . ':' . $e->getLine() . ')',
            'trace' => $trace,
        ]);
    }

    /** Removes quoted values from a database driver message: Duplicate entry 'a@b.co' → Duplicate entry '?' */
    private static function withoutValues(string $message): string
    {
        // MySQL quotes values without escaping them, so a value runs to the quote before "for" / "at" or the end
        return (string) preg_replace('/\b(entry|value:?)\s+\'.*?\'(?=\s+(?:for|at)\b|\s*$)/is', '$1 \'?\'', $message);
    }

    // ---------------------------------------------------------------- state

    public static function state(string $key, $default = null)
    {
        $value = DB::table('monitor_state')->where('key', $key)->value('value');
        return $value === null ? $default : $value;
    }

    public static function setState(string $key, $value): void
    {
        DB::table('monitor_state')->updateOrInsert(['key' => $key], ['value' => (string) $value, 'updated_at' => now()]);
    }

    // ---------------------------------------------------------------- explaining

    /** A plain-English hint for the error, or null when there is no pattern for it. */
    public static function explain(object $group): ?string
    {
        $text = ($group->exception_class ?? '') . ' ' . $group->message;
        $hints = [
            '/Duplicate entry|SQLSTATE\[23000\]/i' => 'The database refused to save a second copy of something that must be unique (an email, a number, a link).',
            "/doesn't have a default value|SQLSTATE\[HY000\]: General error: 1364/i" => 'A required database column was left empty when saving. The code needs to fill it in.',
            '/Unknown column|SQLSTATE\[42S22\]/i' => 'The code asked for a database column that does not exist. A missing or out-of-date table change (migration)?',
            '/Base table or view not found|SQLSTATE\[42S02\]/i' => 'A database table is missing. A table change (migration) was probably not run on this server.',
            '/Access denied for user/i' => 'The app could not log in to the database. Check the database user, password and permissions in cPanel.',
            '/Too many connections/i' => 'The database ran out of connections. Usually a traffic spike or a stuck process.',
            '/CannotUpdateLockedPropertyException|CorruptComponentPayload/i' => 'Someone tried to change a protected value from the browser, usually a bot. The change was refused.',
            '/Undefined (variable|array key|index|offset)|Attempt to read property .* on (null|int|string|bool)|Trying to access array offset/i' => 'The code used a value that was not there (empty, missing or the wrong type). It needs a check before using it.',
            '/"intl" PHP extension|Class "(finfo|NumberFormatter|Collator|IntlDateFormatter)" not found|Call to undefined function (finfo_|sodium_|exif_|gmp_|idn_to_)/i' => 'The live web PHP is missing an extension (it has no fileinfo, intl, exif, sodium or gmp). Use code that works without it, as App\Support\FilesystemManager does for fileinfo (docs/MAIL.md, Fix 1 to 3).',
            '/Class "[^"]+" not found|Class \'[^\']+\' not found/i' =>'A PHP class is missing: a file that was not uploaded, or a missing "use" line.',
            '/syntax error|ParseError|unexpected end of file/i' => 'A PHP or Blade file has a syntax error, so the page cannot load. Check the file named below.',
            '/cURL error 28|timed out|Operation timed out/i' => 'A call to an outside service (AI, email, payment) took too long and was stopped.',
            '/high demand|overloaded|UNAVAILABLE|status":503/i' => 'The AI provider was busy. The chat retries on the backup provider automatically.',
            '/email failed|Connection could not be established|Expected response code|stream_socket_client/i' => 'An email could not be sent. Check the mail server settings (MAIL_* in .env) and the mailbox in cPanel.',
            '/Allowed memory size .* exhausted/i' => 'PHP ran out of memory, usually from loading too much data at once.',
            '/Maximum execution time/i' => 'A request ran too long and PHP stopped it.',
            // \b: "419" inside a user id (a29df726-14d8-4198-…) is not a 419 response
            '/\b419\b|Page Expired|TokenMismatch/i' => 'A form was sent after the page had expired (CSRF token). Usually harmless: the visitor reloads.',
            '/Scheduled command .* failed/i' => 'A scheduled job failed. Run it by hand on the server to see the full error.',
        ];
        foreach ($hints as $pattern => $hint) {
            if (preg_match($pattern, $text)) {
                return $hint;
            }
        }
        return null;
    }

    /** About 13 lines of code around the error line, if the file is on this server. */
    public static function codeExcerpt(?string $file, ?int $line, int $around = 6): ?string
    {
        if (!$file || !$line) {
            return null;
        }
        foreach ([base_path($file), dirname(base_path()) . '/' . ltrim($file, '/'), $file] as $candidate) {
            $real = @realpath($candidate);
            $home = @realpath(dirname(base_path()));
            if ($real && $home && str_starts_with($real, $home) && is_file($real) && is_readable($real)) {
                $lines = @file($real, FILE_IGNORE_NEW_LINES) ?: [];
                $from = max(1, $line - $around);
                $to = min(count($lines), $line + $around);
                $out = [];
                for ($n = $from; $n <= $to; $n++) {
                    $out[] = str_pad($n, 5, ' ', STR_PAD_LEFT) . ($n === $line ? ' > ' : '   ') . ($lines[$n - 1] ?? '');
                }
                return implode("\n", $out);
            }
        }
        return null;
    }

    /** Everything an AI needs to fix the error, as Markdown. */
    public static function brief(object $group, ?iterable $events = null): string
    {
        $events ??= DB::table('monitor_error_events')->where('group_id', $group->id)->orderByDesc('id')->limit(5)->get();
        $tz = 'Africa/Lagos';
        $fmt = fn ($t) => $t ? Carbon::parse($t)->tz($tz)->format('Y-m-d H:i') : '?';

        $md = "# Error on jbspraxis.com\n\n";
        $md .= "Stack: Laravel 12, PHP 8.3, Livewire 4, MySQL, on cPanel shared hosting. App folder: `praxis_core/`, web root: `public_html/PRAXIS/`.\n\n";
        $md .= "**What happened:** " . ($group->exception_class ? "`{$group->exception_class}`: " : '') . trim($group->message) . "\n\n";
        $md .= "**Where:** " . ($group->file ? "`{$group->file}" . ($group->line ? ":{$group->line}" : '') . '`' : 'not recorded') . "\n\n";
        $md .= "**How often:** {$group->occurrences} time" . ($group->occurrences == 1 ? '' : 's') . ", first {$fmt($group->first_seen_at)}, last {$fmt($group->last_seen_at)} (Lagos time). Source: {$group->source}, level: {$group->level}.\n\n";

        if ($hint = self::explain($group)) {
            $md .= "**Plain-English hint:** {$hint}\n\n";
        }

        $md .= "## Recent occurrences\n\n";
        foreach ($events as $ev) {
            $md .= '- ' . $fmt($ev->occurred_at) . ' ' . trim(($ev->method ?? '') . ' ' . ($ev->url ?? ''))
                . ($ev->component ? " (Livewire: {$ev->component})" : '')
                . ($ev->user_id ? ' (signed-in user)' : ' (guest)') . "\n";
        }

        if ($code = self::codeExcerpt($group->file, $group->line)) {
            $md .= "\n## Code around the line\n\n```php\n{$code}\n```\n";
        }

        if ($group->trace) {
            $md .= "\n## Stack trace (top)\n\n```\n" . implode("\n", array_slice(explode("\n", $group->trace), 0, 25)) . "\n```\n";
        }

        if (!empty($group->notes)) {
            $md .= "\n## Team notes\n\n" . trim($group->notes) . "\n";
        }

        $md .= "\n## Task\n\nFind the root cause and propose the smallest safe fix (file and code). Keep existing behaviour, say how to test it, and flag anything that needs a database change.\n";
        return $md;
    }

    // ---------------------------------------------------------------- helpers

    /** The first stack frame inside the app (not vendor), else where it was thrown. */
    private static function appFrame(Throwable $e): array
    {
        $base = base_path() . DIRECTORY_SEPARATOR;
        $frames = array_merge([['file' => $e->getFile(), 'line' => $e->getLine()]], $e->getTrace());
        foreach ($frames as $f) {
            $file = $f['file'] ?? '';
            if ($file && str_starts_with($file, $base) && !str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
                && !str_contains($file, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR)) {
                return [$file, $f['line'] ?? null];
            }
        }
        return [$e->getFile(), $e->getLine()];
    }

    private static function trace(Throwable $e): string
    {
        $lines = [];
        foreach (array_slice($e->getTrace(), 0, 40) as $i => $f) {
            $where = isset($f['file']) ? self::relativePath($f['file']) . ':' . ($f['line'] ?? '?') : '[internal]';
            $lines[] = "#{$i} {$where} " . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
        }
        if ($prev = $e->getPrevious()) {
            $lines[] = 'Caused by ' . get_class($prev) . ': ' . self::safeMessage($prev);
        }
        return implode("\n", $lines);
    }

    /** Paths relative to the home folder (praxis_core/... or public_html/...), never absolute. */
    public static function relativePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        $path = str_replace('\\', '/', $path);
        $home = str_replace('\\', '/', dirname(base_path())) . '/';
        return str_starts_with($path, $home) ? substr($path, strlen($home)) : $path;
    }

    /** The same error with different ids, numbers or values gets one group. */
    public static function normalise(string $message): string
    {
        $m = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '{uuid}', $message);
        $m = preg_replace("/'[^']{0,200}'|\"[^\"]{0,200}\"/", '{value}', $m);
        $m = preg_replace('/\b\d+(\.\d+)?\b/', '{n}', $m);
        return mb_substr(preg_replace('/\s+/', ' ', $m), 0, 500);
    }

    private static function cleanUrl(string $url): string
    {
        // Keep the path; drop query strings, which can hold tokens or personal data
        $parts = parse_url($url);
        return ($parts['path'] ?? '/');
    }

    /**
     * The Livewire component the error belongs to. One request can carry several components (a tab click and the
     * bell's push refresh in the same moment), so not simply the first: the one the failing method was called on
     * ("Public method [setTab] not found"), else the first asked to run a method rather than receive an event.
     */
    private static function livewireComponent($request, ?Throwable $e = null): ?string
    {
        if (!str_contains($request->path(), 'livewire') || !is_array($components = $request->input('components'))) {
            return null;
        }

        $named = [];
        foreach ($components as $component) {
            $snapshot = is_array($component) ? ($component['snapshot'] ?? null) : null;
            $name = is_string($snapshot) ? (json_decode($snapshot, true)['memo']['name'] ?? null) : null;
            if (is_string($name)) {
                $calls = is_array($component['calls'] ?? null) ? $component['calls'] : [];
                $named[] = [$name, array_column(array_filter($calls, 'is_array'), 'method')];
            }
        }

        $failed = $e && preg_match('/method \[([^\]]+)\]/', $e->getMessage(), $m) ? $m[1] : null;
        foreach ($named as [$name, $methods]) {
            if ($failed !== null && in_array($failed, $methods, true)) {
                return $name;
            }
        }
        foreach ($named as [$name, $methods]) {
            if (array_diff($methods, ['__dispatch'])) {
                return $name;
            }
        }

        return $named[0][0] ?? null;
    }
}
