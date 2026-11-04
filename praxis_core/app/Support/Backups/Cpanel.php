<?php

namespace App\Support\Backups;

use Illuminate\Support\Facades\Process;

/**
 * cPanel's own command line on the server (uapi, run as the account): the account's databases, and creating the
 * read-only backup user, its grants and the scratch database (docs/BACKUPS.md 4). Values are URI-encoded, as uapi
 * expects on a command line. A password passed here never appears in an error message.
 */
class Cpanel
{
    /** What the backup user may do on each database: read it, its views, triggers and events, nothing else */
    public const READ_PRIVILEGES = ['SELECT', 'SHOW VIEW', 'LOCK TABLES', 'TRIGGER', 'EVENT'];

    /** @return list<array{database: string, users: list<string>}> every database the account owns */
    public function databases(): array
    {
        return array_values(array_map(fn ($db) => [
            'database' => (string) $db['database'],
            'users' => array_values(array_map('strval', (array) ($db['users'] ?? []))),
        ], array_filter($this->call('Mysql', 'list_databases'), fn ($db) => isset($db['database']))));
    }

    /** @return list<string> the account's MySQL users */
    public function users(): array
    {
        return array_values(array_map(fn ($u) => (string) $u['user'], array_filter($this->call('Mysql', 'list_users'), fn ($u) => isset($u['user']))));
    }

    public function createUser(string $name, string $password): void
    {
        $this->call('Mysql', 'create_user', ['name' => $name, 'password' => $password], $password);
    }

    public function setPassword(string $user, string $password): void
    {
        $this->call('Mysql', 'set_password', ['user' => $user, 'password' => $password], $password);
    }

    public function createDatabase(string $name): void
    {
        $this->call('Mysql', 'create_database', ['name' => $name]);
    }

    /** @param  list<string>  $privileges  e.g. self::READ_PRIVILEGES, or ['ALL PRIVILEGES'] */
    public function grant(string $user, string $database, array $privileges): void
    {
        $this->call('Mysql', 'set_privileges_on_database', ['user' => $user, 'database' => $database, 'privileges' => implode(',', $privileges)]);
    }

    /** One uapi call; its data, or BackupFailed('setup') with cPanel's own error */
    public function call(string $module, string $function, array $args = [], ?string $secret = null): array
    {
        $command = [(string) config('backups.bin.uapi'), '--output=json', $module, $function];
        foreach ($args as $key => $value) {
            $command[] = $key . '=' . rawurlencode((string) $value);
        }

        $result = Process::timeout(120)->run($command);
        $json = json_decode($result->output(), true);
        $hide = fn (string $text) => $secret !== null && $secret !== '' ? str_replace([$secret, rawurlencode($secret)], '***', $text) : $text;
        if (! is_array($json) || ! isset($json['result']) || ! is_array($json['result'])) {
            throw new BackupFailed('setup', $hide("cPanel's uapi {$module}::{$function} did not answer (exit code {$result->exitCode()}): " . Programs::lastLine($result->errorOutput() ?: $result->output())));
        }
        if ((int) ($json['result']['status'] ?? 0) !== 1) {
            $errors = implode(' ', array_map('strval', (array) ($json['result']['errors'] ?? []))) ?: 'no reason given';
            throw new BackupFailed('setup', $hide("cPanel refused {$module}::{$function}: " . mb_substr($errors, 0, 300)));
        }

        return (array) ($json['result']['data'] ?? []);
    }
}
