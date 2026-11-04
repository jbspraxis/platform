<?php

namespace App\Support\Backups;

/**
 * The weekly restore test (owner's decision 7), before encryption, since the server can't decrypt afterwards: the
 * fresh dump is imported into the scratch database, then compared with what the dump holds, table by table and row
 * by row, and with the live database's list of tables. The scratch database is emptied before and after.
 *
 * Without a scratch database (cPanel's plan allows 5 databases and the five sites use them all, 4 Nov 2026) it is a
 * dump check instead, and says so: the whole .gz is decompressed with its checksum verified, every table's rows are
 * counted, every live table and view must be in it and mysqldump's closing line must be there. Nothing is imported.
 * Row counts against the live database are shown, never failed on: writes after the dump make them differ.
 *
 * The rows a dump holds are counted as it is read, its strings skipped (they may hold parentheses and semicolons), in
 * both of mysqldump's layouts: MySQL's, every row of an INSERT on its line ("INSERT INTO `t` VALUES (...),(...);"),
 * and MariaDB 10.11's on the server, "INSERT INTO `t` VALUES" alone, then one "(...)," line per row until the one
 * ending in ";". A dump with no rows for a table that has rows live (and had them before the dump) fails: the backup
 * lost its data. DEFINER clauses are dropped on the way into the import: the backup user may not create views or
 * triggers in another user's name, and the test is about the data, not who owns a view.
 */
final class RestoreTest
{
    public const IMPORT = 'import';

    public const DUMP_CHECK = 'dump_check';

    private const DEFINER = '/\bDEFINER\s*=\s*(?:`(?:[^`]|``)*`|\'[^\']*\'|[^\s@]+)@(?:`(?:[^`]|``)*`|\'[^\']*\'|[^\s*]+)/';

    private const CUT = 'the dump has no "Dump completed" line at its end: it was cut off';

    /** No INSERT statement open: the state inspect() carries from line to line */
    private const NO_INSERT = ['table' => null, 'quote' => false, 'depth' => 0];

    /**
     * @param  array<string, int>|null  $before  rows per table just before the dump, when counted: a table empty then
     *                                           and filled since is not a lost table
     * @return array{ok: bool, mode: string, tables: int, views: int, rows: int, mismatches: list<string>, seconds: float,
     *               live_rows?: int, differences?: list<string>, reason?: string}
     */
    public static function run(MysqlClient $mysql, string $database, string $gzPath, ?array $before = null): array
    {
        $scratch = (string) config('backups.scratch_database');

        return $mysql->canUse($scratch) ? self::import($mysql, $database, $scratch, $gzPath, $before) : self::dumpCheck($mysql, $database, $gzPath, $before);
    }

    /** What a test result says, for System health and the run's output: never "restore test" when nothing was imported */
    public static function label(?array $test): ?string
    {
        if (! $test) {
            return null;
        }

        return (self::isDumpCheck($test) ? 'Dump check ' : 'Restore test ') . (($test['ok'] ?? false) ? 'passed' : 'failed');
    }

    public static function isDumpCheck(?array $test): bool
    {
        return ($test['mode'] ?? self::IMPORT) === self::DUMP_CHECK;
    }

    private static function import(MysqlClient $mysql, string $database, string $scratch, string $gzPath, ?array $before): array
    {
        $started = microtime(true);
        $mysql->emptyDatabase($scratch);

        try {
            [$rows, $views, $complete] = self::feedMysql($mysql, $scratch, $gzPath);

            $mismatches = $complete ? [] : [self::CUT];
            $imported = $mysql->objects($scratch);
            foreach ($rows as $table => $expected) {
                if (! in_array($table, $imported['tables'], true)) {
                    $mismatches[] = "{$table}: not created by the import";

                    continue;
                }
                $actual = $mysql->countRows($scratch, $table);
                if ($actual !== $expected) {
                    $mismatches[] = "{$table}: {$actual} rows imported, {$expected} in the dump";
                }
            }
            foreach (array_diff(array_keys($views), $imported['views']) as $view) {
                $mismatches[] = "view {$view}: not created by the import";
            }
            $live = $mysql->objects($database);
            foreach (array_diff($live['tables'], array_keys($rows)) as $table) {
                $mismatches[] = "{$table}: in the live database but not in the dump";
            }
            // A dump without the data imports "correctly" into empty tables: compare with what is live too
            array_push($mismatches, ...self::emptied($rows, $mysql->rowCounts($database), $before));
        } finally {
            $mysql->emptyDatabase($scratch);
        }

        return [
            'ok' => $mismatches === [],
            'mode' => self::IMPORT,
            'tables' => count($rows),
            'views' => count($views),
            'rows' => array_sum($rows),
            'mismatches' => array_slice($mismatches, 0, 20),
            'seconds' => round(microtime(true) - $started, 1),
        ];
    }

    private static function dumpCheck(MysqlClient $mysql, string $database, string $gzPath, ?array $before): array
    {
        $started = microtime(true);
        $rows = [];
        $views = [];
        $insert = self::NO_INSERT;
        $complete = false;
        $mismatches = [];
        try {
            foreach (self::lines($gzPath) as $line) {
                self::inspect($line, $rows, $views, $insert);
                if (trim($line) !== '') {
                    $complete = str_starts_with($line, '-- Dump completed');
                }
            }
            if (! $complete) {
                $mismatches[] = self::CUT;
            }
        } catch (BackupFailed $e) {
            $mismatches[] = $e->getMessage();
        }

        $live = $mysql->objects($database);
        foreach (array_diff($live['tables'], array_keys($rows)) as $table) {
            $mismatches[] = "{$table}: in the live database but not in the dump";
        }
        foreach (array_diff($live['views'], array_keys($views)) as $view) {
            $mismatches[] = "view {$view}: in the live database but not in the dump";
        }

        // Shown, not judged: the live database moved on after the dump. Only a table emptied in the dump fails.
        $counts = $mysql->rowCounts($database);
        array_push($mismatches, ...self::emptied($rows, $counts, $before));
        $differences = [];
        foreach ($counts as $table => $now) {
            if (isset($rows[$table]) && $rows[$table] !== $now && ! self::lost($rows, $table, $now, $before) && count($differences) < 10) {
                $differences[] = "{$table}: {$rows[$table]} in the dump, {$now} live now";
            }
        }
        $liveRows = array_sum($counts);

        return [
            'ok' => $mismatches === [],
            'mode' => self::DUMP_CHECK,
            'reason' => 'no scratch database (the cPanel plan’s database limit)',
            'tables' => count($rows),
            'views' => count($views),
            'rows' => array_sum($rows),
            'live_rows' => $liveRows,
            'differences' => $differences,
            'mismatches' => array_slice($mismatches, 0, 20),
            'seconds' => round(microtime(true) - $started, 1),
        ];
    }

    /** @return array{0: array<string, int>, 1: array<string, true>, 2: bool} rows per table, views, whether it ends complete */
    private static function feedMysql(MysqlClient $mysql, string $scratch, string $gzPath): array
    {
        $command = Programs::niced([(string) config('backups.bin.mysql'), ...$mysql->clientOptions(), '--max-allowed-packet=1G', $scratch]);
        $errors = tempnam(sys_get_temp_dir(), 'bkr');
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', Programs::nullDevice(), 'w'], 2 => ['file', $errors, 'w']], $pipes);
        if (! is_resource($process)) {
            @unlink($errors);
            throw new BackupFailed('restore_test', 'Could not start mysql for the restore test.');
        }

        $rows = [];
        $views = [];
        $insert = self::NO_INSERT;
        $complete = false;
        $broken = false;
        $unreadable = null;
        try {
            foreach (self::lines($gzPath) as $line) {
                if (! self::feed($pipes[0], self::inspect($line, $rows, $views, $insert))) {
                    $broken = true;

                    break;
                }
                if (trim($line) !== '') {
                    $complete = str_starts_with($line, '-- Dump completed');
                }
            }
        } catch (BackupFailed $e) {
            $unreadable = $e;
        }
        fclose($pipes[0]);
        $code = proc_close($process);
        $stderr = Programs::tail($errors);
        @unlink($errors);

        if ($unreadable) {
            throw $unreadable;
        }
        if ($code !== 0 || $broken) {
            throw new BackupFailed('restore_test', 'The dump did not import into the scratch database: ' . (Programs::lastLine($stderr) ?: "mysql stopped with code {$code}"));
        }

        return [$rows, $views, $complete];
    }

    /**
     * The dump's lines, decompressed with zlib's own checks: a damaged file (bad data or checksum) or one cut short
     * (its gzip stream never ends) throws, where gzopen would quietly stop early.
     *
     * @return \Generator<string>
     */
    public static function lines(string $gzPath): \Generator
    {
        $in = fopen($gzPath, 'rb');
        $zlib = inflate_init(ZLIB_ENCODING_GZIP);
        $buffer = '';
        try {
            while (! feof($in) && inflate_get_status($zlib) !== ZLIB_STREAM_END) {
                $plain = @inflate_add($zlib, (string) fread($in, 1 << 20), ZLIB_SYNC_FLUSH);
                if ($plain === false) {
                    throw new BackupFailed('restore_test', 'The dump does not decompress: its .gz file is damaged.');
                }
                $buffer .= $plain;
                for ($at = 0; ($newline = strpos($buffer, "\n", $at)) !== false; $at = $newline + 1) {
                    yield substr($buffer, $at, $newline + 1 - $at);
                }
                $buffer = substr($buffer, $at);
            }
            if (inflate_get_status($zlib) !== ZLIB_STREAM_END) {
                throw new BackupFailed('restore_test', 'The dump is cut short: its .gz file ends before the compressed data does.');
            }
            if ($buffer !== '') {
                yield $buffer;
            }
        } finally {
            fclose($in);
        }
    }

    /** Counts what the line creates or inserts; returns it as mysql should get it */
    /**
     * Counts what the line creates or inserts; returns it as mysql should get it (data lines exactly as they are).
     * $insert carries an INSERT that goes on over several lines (MariaDB's layout) from one line to the next: its
     * table, and whether the scan is inside a string or a row, until the ";" that ends the statement.
     */
    private static function inspect(string $line, array &$rows, array &$views, array &$insert): string
    {
        // A row of the open INSERT: "(...)," or "(...);". Only an unclosed string may start otherwise; any other line
        // means the statement ended without a ";" we could see, and it is read as the line it is.
        if ($insert['table'] !== null && ($insert['quote'] || $insert['depth'] > 0 || str_starts_with(ltrim($line), '('))) {
            [$count, $ended] = self::scan($line, 0, $insert);
            $rows[$insert['table']] += $count;
            if ($ended) {
                $insert = self::NO_INSERT;
            }

            return $line;
        }
        $insert = self::NO_INSERT;

        if (str_starts_with($line, 'INSERT INTO `')) {
            if (preg_match('/^INSERT INTO `((?:[^`]|``)+)`/', $line, $m)) {
                $table = str_replace('``', '`', $m[1]);
                $rows[$table] ??= 0;
                // " VALUES" ends the line in MariaDB's layout, "VALUES (" goes on in MySQL's
                $values = strpos($line, ' VALUES', strlen($m[0]));
                $insert = ['table' => $table] + self::NO_INSERT;
                [$count, $ended] = $values === false ? [0, false] : self::scan($line, $values + 7, $insert);
                $rows[$table] += $count;
                if ($ended) {
                    $insert = self::NO_INSERT;
                }
            }

            return $line;
        }
        if (str_starts_with($line, 'CREATE TABLE `') && preg_match('/^CREATE TABLE `((?:[^`]|``)+)`/', $line, $m)) {
            $rows[str_replace('``', '`', $m[1])] ??= 0;
        } elseif (str_starts_with($line, '/*!50001 VIEW `') && preg_match('/^\/\*!50001 VIEW `((?:[^`]|``)+)`/', $line, $m)) {
            $views[str_replace('``', '`', $m[1])] = true;
        }

        return str_contains($line, 'DEFINER') ? (string) preg_replace(self::DEFINER, '', $line) : $line;
    }

    /** How many "(...)" groups follow, outside quoted strings (mysqldump escapes quotes in strings with a backslash) */
    public static function tuples(string $s, int $from = 0): int
    {
        $state = self::NO_INSERT;

        return self::scan($s, $from, $state)[0];
    }

    /**
     * Reads INSERT data from $from: the "(...)" groups that open at depth 0 outside strings, and whether the
     * statement's ";" (outside strings and rows) was reached. $state carries an open string or row to the next line.
     *
     * @return array{0: int, 1: bool}
     */
    private static function scan(string $s, int $from, array &$state): array
    {
        $n = strlen($s);
        $i = $from;
        $count = 0;
        while ($i < $n) {
            if ($state['quote']) {
                $i += strcspn($s, "'\\", $i);
                if ($i >= $n) {
                    break;
                }
                if ($s[$i] === '\\') {
                    $i += 2;

                    continue;
                }
                $state['quote'] = false;
                $i++;

                continue;
            }
            $i += strcspn($s, "'();", $i);
            if ($i >= $n) {
                break;
            }
            $c = $s[$i];
            if ($c === "'") {
                $state['quote'] = true;
            } elseif ($c === '(') {
                if ($state['depth']++ === 0) {
                    $count++;
                }
            } elseif ($c === ')') {
                $state['depth'] = max(0, $state['depth'] - 1);
            } elseif ($state['depth'] === 0) {
                return [$count, true];
            }
            $i++;
        }

        return [$count, false];
    }

    /**
     * Tables the dump holds no rows for while they have rows live: the backup lost its data. With the counts from
     * just before the dump, a table empty then and filled since (a queue, the sessions) is not counted as lost.
     *
     * @return list<string>
     */
    private static function emptied(array $rows, array $live, ?array $before): array
    {
        $lost = [];
        foreach ($live as $table => $now) {
            if (self::lost($rows, $table, $now, $before)) {
                $lost[] = "{$table}: {$now} " . ($now === 1 ? 'row' : 'rows') . ' live but none in the dump';
            }
        }
        $liveTotal = array_sum($live);
        if (array_sum($rows) === 0 && $liveTotal > 0 && ($before === null || array_sum($before) > 0)) {
            array_unshift($lost, "the dump holds no rows at all, the live database {$liveTotal}");
        }

        return $lost;
    }

    private static function lost(array $rows, string $table, int $now, ?array $before): bool
    {
        return $now > 0 && array_key_exists($table, $rows) && $rows[$table] === 0 && ($before === null || ($before[$table] ?? 0) > 0);
    }

    /** Writes it all to mysql; false when mysql stopped reading (it failed: its error says why) */
    private static function feed($pipe, string $data): bool
    {
        $length = strlen($data);
        for ($done = 0; $done < $length;) {
            $written = @fwrite($pipe, $done === 0 ? $data : substr($data, $done));
            if ($written === false || $written === 0) {
                return false;
            }
            $done += $written;
        }

        return true;
    }
}
