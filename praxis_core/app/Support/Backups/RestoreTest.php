<?php

namespace App\Support\Backups;

/**
 * The weekly restore test (owner's decision 7), before encryption, since the server can't decrypt afterwards: the
 * fresh dump is imported into the scratch database, then compared with what the dump holds, table by table and row
 * by row, and with the live database's list of tables. The scratch database is emptied before and after.
 *
 * The rows a dump holds are counted while it is fed to mysql: each "INSERT INTO `t` VALUES (...),(...);" line, its
 * strings skipped (they may hold parentheses). DEFINER clauses are dropped on the way in: the backup user may not
 * create views or triggers in another user's name, and the test is about the data, not who owns a view.
 */
final class RestoreTest
{
    private const DEFINER = '/\bDEFINER\s*=\s*(?:`(?:[^`]|``)*`|\'[^\']*\'|[^\s@]+)@(?:`(?:[^`]|``)*`|\'[^\']*\'|[^\s*]+)/';

    /** @return array{ok: bool, tables: int, views: int, rows: int, mismatches: list<string>, seconds: float} */
    public static function run(MysqlClient $mysql, string $database, string $gzPath): array
    {
        $started = microtime(true);
        $scratch = (string) config('backups.scratch_database');
        $mysql->emptyDatabase($scratch);

        try {
            [$rows, $views] = self::import($mysql, $scratch, $gzPath);

            $mismatches = [];
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
        } finally {
            $mysql->emptyDatabase($scratch);
        }

        return [
            'ok' => $mismatches === [],
            'tables' => count($rows),
            'views' => count($views),
            'rows' => array_sum($rows),
            'mismatches' => array_slice($mismatches, 0, 20),
            'seconds' => round(microtime(true) - $started, 1),
        ];
    }

    /** @return array{0: array<string, int>, 1: array<string, true>} rows per table in the dump, and its views */
    private static function import(MysqlClient $mysql, string $scratch, string $gzPath): array
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
        $broken = false;
        $gz = gzopen($gzPath, 'rb');
        $line = '';
        while (! $broken && ($chunk = gzgets($gz, 1 << 20)) !== false) {
            $line .= $chunk;
            if (! str_ends_with($chunk, "\n") && ! gzeof($gz)) {
                continue;
            }
            $broken = ! self::feed($pipes[0], self::inspect($line, $rows, $views));
            $line = '';
        }
        if (! $broken && $line !== '') {
            self::feed($pipes[0], self::inspect($line, $rows, $views));
        }
        gzclose($gz);
        fclose($pipes[0]);
        $code = proc_close($process);
        $stderr = Programs::tail($errors);
        @unlink($errors);

        if ($code !== 0 || $broken) {
            throw new BackupFailed('restore_test', 'The dump did not import into the scratch database: ' . (Programs::lastLine($stderr) ?: "mysql stopped with code {$code}"));
        }

        return [$rows, $views];
    }

    /** Counts what the line creates or inserts; returns it as mysql should get it */
    private static function inspect(string $line, array &$rows, array &$views): string
    {
        if (str_starts_with($line, 'INSERT INTO `')) {
            if (preg_match('/^INSERT INTO `((?:[^`]|``)+)`/', $line, $m)) {
                $values = strpos($line, ' VALUES ', strlen($m[0]));
                $table = str_replace('``', '`', $m[1]);
                $rows[$table] = ($rows[$table] ?? 0) + ($values === false ? 0 : self::tuples($line, $values + 8));
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
        $n = strlen($s);
        $i = $from;
        $depth = 0;
        $count = 0;
        while ($i < $n) {
            $i += strcspn($s, "'()", $i);
            if ($i >= $n) {
                break;
            }
            $c = $s[$i];
            if ($c === "'") {
                $i++;
                while ($i < $n) {
                    $i += strcspn($s, "'\\", $i);
                    if ($i >= $n) {
                        break;
                    }
                    if ($s[$i] === '\\') {
                        $i += 2;

                        continue;
                    }
                    break;
                }
            } elseif ($c === '(') {
                if ($depth++ === 0) {
                    $count++;
                }
            } else {
                $depth = max(0, $depth - 1);
            }
            $i++;
        }

        return $count;
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
