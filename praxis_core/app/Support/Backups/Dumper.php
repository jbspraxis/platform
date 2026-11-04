<?php

namespace App\Support\Backups;

/**
 * One database to one .sql.gz (owner's decision 1): mysqldump --single-transaction --quick --routines --triggers
 * --events, at the lowest priority, streamed through gzip 1 MB at a time, so a large database never sits in memory.
 * A dump without mysqldump's closing "Dump completed" line is refused: it was cut off.
 */
final class Dumper
{
    /** @return array{bytes: int, sha256: string} of the .sql.gz */
    public static function dump(MysqlClient $mysql, string $database, string $gzPath): array
    {
        $command = Programs::niced([
            (string) config('backups.bin.mysqldump'),
            ...$mysql->clientOptions(),
            '--single-transaction', '--quick', '--routines', '--triggers', '--events', '--no-tablespaces',
            $database,
        ]);
        $errors = tempnam(sys_get_temp_dir(), 'bkd');
        $process = proc_open($command, [0 => ['file', Programs::nullDevice(), 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']], $pipes);
        if (! is_resource($process)) {
            @unlink($errors);
            throw new BackupFailed('dump', 'Could not start mysqldump.');
        }

        $gz = gzopen($gzPath, 'wb6');
        $tail = '';
        $written = true;
        while (! feof($pipes[1])) {
            $chunk = fread($pipes[1], 1 << 20);
            if ($chunk === false || $chunk === '') {
                continue;
            }
            $written = $written && gzwrite($gz, $chunk) === strlen($chunk);
            $tail = substr($tail . $chunk, -400);
        }
        fclose($pipes[1]);
        gzclose($gz);
        $code = proc_close($process);
        $stderr = Programs::tail($errors);
        @unlink($errors);

        if ($code !== 0) {
            throw new BackupFailed('dump', "mysqldump stopped with code {$code}: " . (Programs::lastLine($stderr) ?: 'no message'));
        }
        if (! $written) {
            throw new BackupFailed('dump', 'The dump could not be written to the server’s disk (is the account’s disk quota full?).');
        }
        if (! str_contains($tail, '-- Dump completed')) {
            throw new BackupFailed('dump', 'The dump is incomplete: mysqldump did not finish it.' . ($stderr !== '' ? ' ' . Programs::lastLine($stderr) : ''));
        }

        return ['bytes' => (int) filesize($gzPath), 'sha256' => hash_file('sha256', $gzPath)];
    }
}
