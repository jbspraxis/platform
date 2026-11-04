<?php

namespace App\Support\Backups;

use App\Support\Monitor\ErrorMonitor;

/**
 * backups:setup (owner's decision 3; docs/BACKUPS.md 4), run once on the server by a one-shot cron line, and safe to
 * run again. Through cPanel's uapi, as the account itself:
 *   1. the backups folder ~/backups (700);
 *   2. the read-only MySQL user, with a random password nobody sees: it goes straight into ~/.backup-my.cnf (600),
 *      never to the screen, a log or the repository (an existing user whose file was lost gets a new password);
 *   3. the scratch database of the restore test, which only the backup user may write to;
 *   4. read access (SELECT, SHOW VIEW, LOCK TABLES, TRIGGER, EVENT) to every database the account owns.
 * Then it signs in as that user, and lists each database with its site and each site's upload folders.
 */
class Setup
{
    public function __construct(private Cpanel $cpanel, private MysqlClient $mysql, private Backups $backups) {}

    /** @return list<string> */
    public function run(bool $dryRun = false): array
    {
        if (config('backups.databases_from') !== 'cpanel') {
            throw new BackupFailed('setup', 'backups:setup works through cPanel (BACKUP_DATABASES_FROM=cpanel). Off the server, write the client file by hand (docs/BACKUPS.md, section 9).');
        }
        $would = $dryRun ? 'Would ' : '';
        $user = (string) config('backups.mysql_user');
        $scratch = (string) config('backups.scratch_database');
        $cnf = $this->mysql->cnf();
        $lines = [];

        $dir = $dryRun ? (string) config('backups.dir') : $this->backups->dir();
        $lines[] = ($dryRun ? 'Backups folder: ' : 'Backups folder ready: ') . $dir . ' (700)';

        if (! in_array($user, $this->cpanel->users(), true)) {
            if (! $dryRun) {
                $password = self::password();
                $this->cpanel->createUser($user, $password);
                MysqlClient::writeCnf($cnf, self::client($user, $password));
                unset($password);
            }
            $lines[] = $would . "create the read-only MySQL user {$user}; its password is only in {$cnf} (600).";
        } elseif (! is_file($cnf)) {
            if (! $dryRun) {
                $password = self::password();
                $this->cpanel->setPassword($user, $password);
                MysqlClient::writeCnf($cnf, self::client($user, $password));
                unset($password);
            }
            $lines[] = $would . "give {$user} a new password (its client file {$cnf} was missing).";
        } else {
            $lines[] = "The MySQL user {$user} and its client file are already in place.";
        }

        $databases = $this->cpanel->databases();
        if (! in_array($scratch, array_column($databases, 'database'), true)) {
            if (! $dryRun) {
                $this->cpanel->createDatabase($scratch);
            }
            $lines[] = $would . "create the scratch database {$scratch} for the weekly restore test.";
        }
        if (! $dryRun) {
            $this->cpanel->grant($user, $scratch, ['ALL PRIVILEGES']);
        }

        $granted = 0;
        foreach ($databases as $db) {
            if ($db['database'] === $scratch || in_array($db['database'], (array) config('backups.exclude_databases'), true)) {
                continue;
            }
            if (! $dryRun) {
                $this->cpanel->grant($user, $db['database'], Cpanel::READ_PRIVILEGES);
            }
            $granted++;
        }
        $lines[] = $would . "give {$user} read access to {$granted} database(s): " . implode(', ', Cpanel::READ_PRIVILEGES) . '.';

        if (! $dryRun) {
            $this->mysql->pdo();
            $lines[] = "Signed in to MySQL as {$user}.";
        }

        $lines[] = '';
        $lines[] = 'Databases and their sites:';
        foreach ($databases as $db) {
            if ($db['database'] === $scratch) {
                continue;
            }
            $site = Sites::forDatabase($db['database']);
            $tables = $dryRun ? '' : ' (' . count($this->mysql->objects($db['database'])['tables']) . ' tables)';
            $lines[] = "  {$db['database']} → " . (Sites::isOther($site) ? "{$site}  ← no site claims it: add it in config/backups.php" : $site) . $tables;
        }
        $claimed = array_unique(array_map(fn ($d) => Sites::forDatabase($d['database']), $databases));
        foreach (Sites::keys() as $site) {
            if (! in_array($site, $claimed, true)) {
                $lines[] = "  {$site}: no database matches " . implode(', ', Sites::all()[$site]['databases']) . ' ← check config/backups.php';
            }
        }

        $lines[] = '';
        $lines[] = 'Upload folders (weekly files backup):';
        $home = rtrim((string) config('backups.home'), '/');
        foreach (Sites::all() as $site => $config) {
            if ($config['files'] === []) {
                $lines[] = "  {$site}: none in config/backups.php yet";
            }
            foreach ($config['files'] as $folder) {
                $lines[] = "  {$site}: {$folder} " . (is_dir($home . '/' . $folder) ? '(found)' : '← NOT FOUND');
            }
        }
        $candidates = self::candidates($home);
        if ($candidates) {
            $lines[] = 'Folders that look like uploads (to add to a site if they hold its files):';
            foreach ($candidates as $folder) {
                $lines[] = '  ' . $folder;
            }
        }

        if (! $dryRun) {
            ErrorMonitor::setState('backups.setup_at', now()->toDateTimeString());
            @touch($dir . '/.setup-done');
        }

        return $lines;
    }

    /** The [client] section: the user, its password, and where MySQL is (the socket on localhost, on the server) */
    private static function client(string $user, string $password): array
    {
        return array_filter(['user' => $user, 'password' => $password, 'host' => (string) config('backups.mysql_host', 'localhost'),
            'port' => config('backups.mysql_port') ? (string) config('backups.mysql_port') : null]);
    }

    public function done(): bool
    {
        return is_file(rtrim((string) config('backups.dir'), '/\\') . '/.setup-done') && $this->mysql->configured();
    }

    /** 40 random letters, digits and -_. : strong for cPanel's meter, and nothing a shell or an option file misreads */
    public static function password(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_.';
        $out = '';
        for ($i = 0; $i < 40; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // cPanel's meter wants every kind of character
        return preg_match('/[A-Z]/', $out) && preg_match('/[a-z]/', $out) && preg_match('/\d/', $out) && preg_match('/[-_.]/', $out) ? $out : self::password();
    }

    /**
     * Folders under the docroots and apps that usually hold uploaded files, for filling config/backups.php
     *
     * @return list<string> relative to the home
     */
    public static function candidates(string $home): array
    {
        $names = ['uploads', 'upload', 'files', 'media', 'attachments', 'documents', 'storage', 'wp-content/uploads', 'storage/app'];
        $found = [];
        foreach ([...(glob($home . '/public_html/*', GLOB_ONLYDIR) ?: []), $home . '/public_html', ...(glob($home . '/*', GLOB_ONLYDIR) ?: [])] as $base) {
            foreach ($names as $name) {
                if (is_dir($base . '/' . $name)) {
                    $found[] = ltrim(substr($base . '/' . $name, strlen($home)), '/');
                }
            }
        }

        return array_values(array_unique($found));
    }
}
