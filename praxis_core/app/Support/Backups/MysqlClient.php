<?php

namespace App\Support\Backups;

use PDO;
use Throwable;

/**
 * The backups' own way into MySQL: the read-only user in ~/.backup-my.cnf (backups:setup writes it, 600), never
 * HQ's database user. mysqldump and mysql read the file themselves (--defaults-extra-file), so its password is never
 * on a command line; PHP reads it here only to count tables and rows for the restore test.
 */
class MysqlClient
{
    private const SYSTEM = ['information_schema', 'performance_schema', 'mysql', 'sys'];

    /** @var array<string, PDO> */
    private array $connections = [];

    public function cnf(): string
    {
        return (string) config('backups.mysql_cnf');
    }

    public function configured(): bool
    {
        return is_file($this->cnf());
    }

    /** The options every mysql program gets first: the client file, then UTF-8 */
    public function clientOptions(): array
    {
        return ['--defaults-extra-file=' . $this->cnf(), '--default-character-set=utf8mb4'];
    }

    /** @return list<string> the databases this user sees whose names start with the prefix (local runs and tests) */
    public function databases(string $prefix): array
    {
        $names = $this->pdo()->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($names, fn ($n) => ! in_array(strtolower($n), self::SYSTEM, true)
            && ($prefix === '' || str_starts_with($n, $prefix))));
    }

    /** @return array{tables: list<string>, views: list<string>} */
    public function objects(string $database): array
    {
        $rows = $this->pdo()->prepare('SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME');
        $rows->execute([$database]);
        $out = ['tables' => [], 'views' => []];
        foreach ($rows->fetchAll(PDO::FETCH_NUM) as [$name, $type]) {
            $out[$type === 'VIEW' ? 'views' : 'tables'][] = $name;
        }

        return $out;
    }

    public function countRows(string $database, string $table): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM ' . self::quote($database) . '.' . self::quote($table))->fetchColumn();
    }

    /** Drops everything in the scratch database (its tables, views, routines and events), keeping the database */
    public function emptyDatabase(string $database): void
    {
        $pdo = $this->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $objects = $this->objects($database);
        foreach ($objects['views'] as $view) {
            $pdo->exec('DROP VIEW IF EXISTS ' . self::quote($database) . '.' . self::quote($view));
        }
        foreach ($objects['tables'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . self::quote($database) . '.' . self::quote($table));
        }
        $routines = $pdo->prepare('SELECT ROUTINE_TYPE, ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?');
        $routines->execute([$database]);
        foreach ($routines->fetchAll(PDO::FETCH_NUM) as [$type, $name]) {
            $pdo->exec('DROP ' . ($type === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE') . ' IF EXISTS ' . self::quote($database) . '.' . self::quote($name));
        }
        try {
            $events = $pdo->prepare('SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?');
            $events->execute([$database]);
            foreach ($events->fetchAll(PDO::FETCH_COLUMN) as $name) {
                $pdo->exec('DROP EVENT IF EXISTS ' . self::quote($database) . '.' . self::quote($name));
            }
        } catch (Throwable) {
            // No EVENT privilege on the scratch database: it can't hold events either
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function pdo(): PDO
    {
        $file = $this->cnf();
        if (isset($this->connections[$file])) {
            return $this->connections[$file];
        }
        if (! is_file($file)) {
            throw new BackupFailed('setup', 'The backups are not set up on this server yet: run "php artisan backups:setup" (docs/BACKUPS.md, section 4).');
        }
        $client = self::readCnf($file);
        $host = $client['host'] ?? 'localhost';
        $dsn = isset($client['socket']) ? 'mysql:unix_socket=' . $client['socket']
            : 'mysql:host=' . $host . (isset($client['port']) ? ';port=' . (int) $client['port'] : '');
        try {
            $pdo = new PDO($dsn . ';charset=utf8mb4', $client['user'] ?? '', $client['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10,
            ]);
        } catch (Throwable $e) {
            // PDO's message names the user and host, never the password
            throw new BackupFailed('setup', 'Could not sign in to MySQL with the backup user: ' . mb_substr($e->getMessage(), 0, 200));
        }

        return $this->connections[$file] = $pdo;
    }

    /** @return array<string, string> the [client] section of a MySQL option file */
    public static function readCnf(string $file): array
    {
        $client = [];
        $section = '';
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            if (preg_match('/^\[(.+)\]$/', $line, $m)) {
                $section = strtolower(trim($m[1]));

                continue;
            }
            if ($section === 'client' && str_contains($line, '=')) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && str_ends_with($value, $value[0])) {
                    $value = substr($value, 1, -1);
                }
                $client[strtolower($key)] = $value;
            }
        }

        return $client;
    }

    /** Writes a [client] option file readable by its owner only; the password is quoted, never logged */
    public static function writeCnf(string $file, array $client): void
    {
        $lines = ['# Server backups (docs/BACKUPS.md): the read-only MySQL user, written by backups:setup. Never share it.', '[client]'];
        foreach ($client as $key => $value) {
            $lines[] = $key . '=' . ($key === 'password' ? '"' . $value . '"' : $value);
        }
        $tmp = $file . '.new';
        $old = umask(0077);
        try {
            file_put_contents($tmp, implode("\n", $lines) . "\n");
            @chmod($tmp, 0600);
            if (! @rename($tmp, $file)) {
                @unlink($file);
                rename($tmp, $file);
            }
        } finally {
            umask($old);
        }
    }

    public static function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
