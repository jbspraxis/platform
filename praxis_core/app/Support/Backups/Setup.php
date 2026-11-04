<?php

namespace App\Support\Backups;

use App\Support\Monitor\ErrorMonitor;
use Illuminate\Support\Str;

/**
 * backups:setup (owner's decision 3; docs/BACKUPS.md 4), run once on the server by a one-shot cron line, and safe to
 * run again. Through cPanel's uapi, as the account itself:
 *   1. the backups folder ~/backups (700);
 *   2. the read-only MySQL user, with a random password nobody sees: it goes straight into ~/.backup-my.cnf (600),
 *      never to the screen, a log or the repository (an existing user whose file was lost gets a new password);
 *   3. read access (SELECT, SHOW VIEW, LOCK TABLES, TRIGGER, EVENT) to every database the account owns;
 *   4. the scratch database of the restore test, which only the backup user may write to, when the plan allows one
 *      more database. When it doesn't (5 of 5 used, 4 Nov 2026) the setup still succeeds and says so plainly.
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
            $lines[] = ($dryRun ? 'Would create' : 'Created') . " the read-only MySQL user {$user}; its password is only in {$cnf} (600).";
        } elseif (! is_file($cnf)) {
            if (! $dryRun) {
                $password = self::password();
                $this->cpanel->setPassword($user, $password);
                MysqlClient::writeCnf($cnf, self::client($user, $password));
                unset($password);
            }
            $lines[] = ($dryRun ? 'Would give' : 'Gave') . " {$user} a new password (its client file {$cnf} was missing).";
        } else {
            $lines[] = "The MySQL user {$user} and its client file are already in place.";
        }

        $databases = $this->cpanel->databases();
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
        $lines[] = ($dryRun ? 'Would give' : 'Gave') . " {$user} read access to {$granted} database(s): " . implode(', ', Cpanel::READ_PRIVILEGES) . '.';

        array_push($lines, ...$this->scratch($user, $scratch, in_array($scratch, array_column($databases, 'database'), true), $dryRun));

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
            $count = $dryRun ? 0 : count($this->mysql->objects($db['database'])['tables']);
            $tables = $dryRun ? '' : " ({$count} " . Str::plural('table', $count) . ')';
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

    /**
     * The restore test's scratch database, if the plan allows one more database. Without it the backups still run:
     * the weekly test is a dump check (docs/BACKUPS.md 5), and a later backups:setup brings the import test back.
     */
    /** @return list<string> */
    private function scratch(string $user, string $scratch, bool $exists, bool $dryRun): array
    {
        if ($dryRun) {
            return [$exists ? "The scratch database {$scratch} exists: the weekly restore test imports each dump into it."
                : "Would create the scratch database {$scratch} for the weekly restore test, if the cPanel plan allows one more database (if not, the weekly test is a dump check)."];
        }
        if (! $exists) {
            try {
                $this->cpanel->createDatabase($scratch);
            } catch (BackupFailed $e) {
                $limit = (bool) preg_match('/maximum|allotment|limit|quota/i', $e->getMessage());

                return [
                    "Could not create the scratch database {$scratch}" . ($limit ? ': the cPanel plan allows no more databases.' : '.')
                        . ' cPanel said: ' . preg_replace('/^cPanel refused \S+: /', '', $e->getMessage()),
                    'The backups still run every night. Until there is a scratch database, the weekly restore test is a dump check:'
                        . ' each dump is decompressed to the end, counted table by table and checked against the live tables and views, but not imported.',
                    'When the host allows one more database, run backups:setup again: the full import test then comes back by itself.',
                ];
            }
        }
        $this->cpanel->grant($user, $scratch, ['ALL PRIVILEGES']);

        return [($exists ? "The scratch database {$scratch} is in place" : "Created the scratch database {$scratch}")
            . ': the weekly restore test imports each dump into it (only the backup user may write to it).'];
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
