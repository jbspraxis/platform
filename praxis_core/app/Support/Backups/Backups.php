<?php

namespace App\Support\Backups;

use App\Support\Google\GoogleUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

/**
 * The server backups (owner's decisions, 4 Nov 2026; docs/BACKUPS.md). backups:run every night at 02:00 Lagos time:
 * every database the cPanel account owns, each dumped, restore-tested on Sundays (before encryption: the server
 * can't decrypt afterwards), encrypted with the owner's public key, kept on the server (the last 7) and uploaded to
 * the site's daily/ folder on Drive (the last 30), the month's first also in monthly/ (the last 12). backups:files
 * every Sunday at 03:00: each site's upload folders, into files/ (the last 8). One server_backups row per file.
 *
 * Plain dumps exist only in ~/backups/tmp (700) while a file is being made, and are removed straight after; a run
 * starts by removing any left by a run that died. Runs never overlap (a lock file); the second one waits.
 */
class Backups
{
    public function __construct(
        private MysqlClient $mysql,
        private Cpanel $cpanel,
        private DriveStore $drive,
        private Readme $readme,
    ) {}

    public static function today(): string
    {
        return now('Africa/Lagos')->format('Y-m-d');
    }

    /** Set up = backups:setup has written the read-only user's client file */
    public function setUp(): bool
    {
        return $this->mysql->configured();
    }

    /**
     * Every database. Options: date (Y-m-d), site (one site only), restore_test (true/false; default: on the test
     * day, or when the last test is a week old), upload (false: keep the encrypted files on the server only).
     *
     * @return list<string> what happened, a line per database
     */
    public function databases(array $options = []): array
    {
        return $this->locked(function () use ($options) {
            if (! $this->setUp()) {
                return ['The backups are not set up on this server yet: run "php artisan backups:setup" (docs/BACKUPS.md, section 4). Nothing was backed up.'];
            }
            Programs::lowerOwnPriority();
            $this->cleanTmp();
            $date = $options['date'] ?? self::today();
            $lines = [];

            try {
                $databases = $this->listDatabases($lines);
            } catch (Throwable $e) {
                Bells::owners('Server backups could not start', 'The list of databases could not be read, so nothing was backed up tonight: ' . mb_substr($e->getMessage(), 0, 300));

                return [...$lines, 'Could not list the databases: ' . $e->getMessage()];
            }
            $groups = Sites::group($databases);
            if (! empty($options['site'])) {
                $groups = array_intersect_key($groups, [$options['site'] => true]);
            }
            $restoreTest = $options['restore_test'] ?? $this->restoreTestDue($date);
            $driveError = ($options['upload'] ?? true) ? $this->openDrive() : false;
            $key = Keys::publicKey();

            $failed = [];
            foreach ($groups as $site => $names) {
                foreach ($names as $database) {
                    $name = Sites::dbFileName($site, $database, count($names) > 1, $date);
                    $row = $this->backupDatabase($site, $database, $name, $date, $key, $restoreTest, $driveError);
                    $lines[] = $this->describe($row);
                    if ($row['status'] !== 'ok') {
                        $failed[] = $row;
                    }
                    if (Sites::isOther($site)) {
                        Bells::once('other:' . $database, 24 * 30, "A new database was backed up: {$database}",
                            "{$database} belongs to none of the five sites, so it went to other/{$database} on Drive. Add it to a site in config/backups.php (docs/BACKUPS.md, section 2).");
                    }
                }
                if ($driveError === null) {
                    $lines = [...$lines, ...$this->pruneDrive($site, ['daily', 'monthly'])];
                }
                $this->pruneLocal($site, 'db', (int) config('backups.local_keep.db', 7));
            }

            if ($failed) {
                $this->ringFailures('database', $failed);
            }

            return $lines;
        });
    }

    /**
     * Every site's uploaded files. Options: date, site, upload.
     *
     * @return list<string>
     */
    public function files(array $options = []): array
    {
        return $this->locked(function () use ($options) {
            if (! $this->setUp()) {
                return ['The backups are not set up on this server yet: run "php artisan backups:setup" (docs/BACKUPS.md, section 4). Nothing was backed up.'];
            }
            Programs::lowerOwnPriority();
            $this->cleanTmp();
            $date = $options['date'] ?? self::today();
            $driveError = ($options['upload'] ?? true) ? $this->openDrive() : false;
            $key = Keys::publicKey();
            $lines = [];
            $failed = [];

            foreach (Sites::all() as $site => $config) {
                if (! empty($options['site']) && $options['site'] !== $site) {
                    continue;
                }
                if ($config['files'] === []) {
                    $lines[] = "{$site}: no upload folders in config/backups.php yet, so no files backup.";

                    continue;
                }
                $row = $this->backupFiles($site, $config, Sites::filesFileName($site, $date), $date, $key, $driveError);
                $lines[] = $this->describe($row);
                if ($row['status'] !== 'ok') {
                    $failed[] = $row;
                }
                if ($driveError === null) {
                    $lines = [...$lines, ...$this->pruneDrive($site, ['files'])];
                }
                $this->pruneLocal($site, 'files', (int) config('backups.local_keep.files', 2));
            }

            if ($failed) {
                $this->ringFailures('files', $failed);
            }

            return $lines;
        });
    }

    /** The backups folder (or a folder in it), made 700 */
    public function dir(string $sub = ''): string
    {
        $path = rtrim((string) config('backups.dir'), '/\\') . ($sub !== '' ? '/' . $sub : '');
        if (! is_dir($path)) {
            @mkdir($path, 0700, true);
        }
        @chmod($path, 0700);

        return $path;
    }

    // ---- One file -----------------------------------------------------------------------------------------------

    private function backupDatabase(string $site, string $database, string $name, string $date, ?string $key, bool $restoreTest, string|false|null $driveError): array
    {
        $row = $this->start('db', $site, $database, $name, $date);
        $plain = $this->dir('tmp') . '/' . substr($name, 0, -4);
        $stage = 'dump';
        try {
            // On test nights, each table's rows just before the dump: a table empty then and filled since is no loss
            $before = null;
            if ($restoreTest) {
                try {
                    $before = $this->mysql->rowCounts($database);
                } catch (Throwable) {
                    // Counted again after the dump; without these, any table empty in the dump but not live fails
                }
            }
            $dump = Dumper::dump($this->mysql, $database, $plain);
            $row = $this->save($row, ['plain_bytes' => $dump['bytes'], 'plain_sha256' => $dump['sha256']]);

            $test = null;
            if ($restoreTest) {
                $stage = 'restore_test';
                try {
                    $test = RestoreTest::run($this->mysql, $database, $plain, $before);
                } catch (BackupFailed $e) {
                    $mode = $this->mysql->canUse((string) config('backups.scratch_database')) ? RestoreTest::IMPORT : RestoreTest::DUMP_CHECK;
                    $test = ['ok' => false, 'mode' => $mode, 'tables' => 0, 'views' => 0, 'rows' => 0, 'mismatches' => [$e->getMessage()], 'seconds' => 0];
                }
                $row = $this->save($row, ['restore_test' => json_encode($test, JSON_UNESCAPED_UNICODE)]);
            }

            $stage = 'encrypt';
            $row = $this->encryptAndUpload($row, $plain, $key, $driveError, 'daily', ['site' => $site, 'kind' => 'db', 'database' => $database, 'date' => $date], $stage);

            if ($test !== null && ! $test['ok']) {
                return $this->save($row, ['status' => 'failed', 'stage' => 'restore_test', 'finished_at' => now(),
                    'error' => RestoreTest::label($test) . ': ' . implode('; ', array_slice($test['mismatches'], 0, 3)) . '. The backup was still encrypted and kept.']);
            }

            return $this->save($row, ['status' => 'ok', 'stage' => null, 'error' => null, 'finished_at' => now()]);
        } catch (Throwable $e) {
            return $this->fail($row, $e, $stage);
        } finally {
            if (is_file($plain)) {
                unlink($plain);
            }
        }
    }

    private function backupFiles(string $site, array $config, string $name, string $date, ?string $key, string|false|null $driveError): array
    {
        $row = $this->start('files', $site, 'files', $name, $date);
        $plain = $this->dir('tmp') . '/' . substr($name, 0, -4);
        $stage = 'archive';
        try {
            $archive = Archiver::tar($config['files'], [...(array) config('backups.exclude'), ...$config['exclude']], $plain);
            $row = $this->save($row, ['plain_bytes' => $archive['bytes'], 'plain_sha256' => $archive['sha256']]);
            $stage = 'encrypt';
            $row = $this->encryptAndUpload($row, $plain, $key, $driveError, 'files', ['site' => $site, 'kind' => 'files', 'folders' => $config['files'], 'date' => $date], $stage);
            if ($archive['missing']) {
                return $this->save($row, ['status' => 'failed', 'stage' => 'archive', 'finished_at' => now(),
                    'error' => 'Backed up, but these folders were not found on the server: ' . implode(', ', $archive['missing']) . ' (config/backups.php).']);
            }

            return $this->save($row, ['status' => 'ok', 'stage' => null, 'error' => $archive['warning'] ? 'tar: ' . $archive['warning'] : null, 'finished_at' => now()]);
        } catch (Throwable $e) {
            return $this->fail($row, $e, $stage);
        } finally {
            if (is_file($plain)) {
                unlink($plain);
            }
        }
    }

    /** Encrypts into the site's folder on the server, removes the plain file, uploads and checks it */
    private function encryptAndUpload(array $row, string $plain, ?string $key, string|false|null $driveError, string $folder, array $meta, string &$stage): array
    {
        if ($key === null) {
            throw new BackupFailed('key', 'There is no backup key on the server yet, so nothing could be encrypted or uploaded. The owner makes one on the PC (docs/BACKUPS.md, section 3).');
        }
        $local = $this->dir($row['site']) . '/' . $row['file_name'];
        $enc = Envelope::encrypt($plain, $local, $key, $meta + ['name' => basename($plain)]);
        unlink($plain);
        @chmod($local, 0600);
        $row = $this->save($row, ['size_bytes' => $enc['bytes'], 'md5' => $enc['md5']]);
        if ($driveError === false) {
            return $row;
        }

        $stage = 'upload';
        if ($driveError !== null) {
            throw new BackupFailed('upload', $driveError . ' The encrypted file is kept on the server.');
        }
        $file = $this->drive->put($local, $row['file_name'], $this->drive->folder($row['site'], $folder), $enc['md5'],
            ['site' => $row['site'], 'kind' => $row['kind'], 'sha256' => (string) $row['plain_sha256']]);
        $row = $this->save($row, ['drive_file_id' => $file['id'], 'drive_folder' => $folder]);
        if ($row['kind'] === 'db') {
            $stage = 'monthly';
            $row = $this->save($row, ['monthly_file_id' => $this->drive->monthly($row['site'], $file['id'], $row['file_name'], $enc['md5'])]);
        }

        return $row;
    }

    // ---- Drive and the server's copies ----------------------------------------------------------------------------

    /** null when Drive is ready (folders and the guide in place), else why not */
    private function openDrive(): ?string
    {
        if (! $this->drive->ready()) {
            return 'Drive backups are not allowed yet: in HQ › Settings and access › Integrations, the owner clicks Reconnect Google and allows Drive.';
        }
        try {
            $this->drive->prepare();
            $this->drive->readme($this->readme);
        } catch (GoogleUnavailable $e) {
            return 'Drive could not be reached: ' . $e->getMessage();
        } catch (Throwable $e) {
            Log::warning('Backups: the Drive folder could not be prepared', ['message' => mb_substr($e->getMessage(), 0, 300)]);

            return 'Drive could not be prepared: ' . mb_substr($e->getMessage(), 0, 200);
        }

        return null;
    }

    private function pruneDrive(string $site, array $kinds): array
    {
        $lines = [];
        foreach ($kinds as $kind) {
            try {
                $removed = $this->drive->prune($this->drive->folder($site, $kind), (int) config('backups.retention.' . $kind));
                if ($removed) {
                    $lines[] = "{$site}/{$kind}: moved " . count($removed) . ' older backup(s) to Drive’s bin.';
                }
            } catch (Throwable $e) {
                $lines[] = "{$site}/{$kind}: could not prune Drive: " . mb_substr($e->getMessage(), 0, 200);
            }
        }

        return $lines;
    }

    /** Keeps the newest $keep dates of a kind (db or files) in the site's folder on the server */
    private function pruneLocal(string $site, string $kind, int $keep): void
    {
        $files = array_filter(glob($this->dir($site) . '/*_' . $kind . '_*.enc') ?: [], fn ($f) => Sites::dateIn(basename($f)) !== null);
        $dates = array_unique(array_map(fn ($f) => Sites::dateIn(basename($f)), $files));
        rsort($dates);
        $kept = array_slice($dates, 0, max(1, $keep));
        foreach ($files as $file) {
            if (! in_array(Sites::dateIn(basename($file)), $kept, true)) {
                @unlink($file);
            }
        }
    }

    // ---- Bookkeeping --------------------------------------------------------------------------------------------

    /** @param  list<string>  $lines  grants made on the way */
    private function listDatabases(array &$lines): array
    {
        $exclude = [(string) config('backups.scratch_database'), ...(array) config('backups.exclude_databases')];
        if (config('backups.databases_from') !== 'cpanel') {
            return array_values(array_diff($this->mysql->databases((string) config('backups.database_prefix')), $exclude));
        }

        // A database added since the setup is granted to the backup user here, so it is in tonight's backup
        $user = (string) config('backups.mysql_user');
        $names = [];
        foreach ($this->cpanel->databases() as $db) {
            if (in_array($db['database'], $exclude, true)) {
                continue;
            }
            if (! in_array($user, $db['users'], true)) {
                $this->cpanel->grant($user, $db['database'], Cpanel::READ_PRIVILEGES);
                $lines[] = "Gave the backup user read access to the new database {$db['database']}.";
            }
            $names[] = $db['database'];
        }

        return $names;
    }

    /** On the test day, or whenever the last restore test is more than a week old (the first night, too) */
    private function restoreTestDue(string $date): bool
    {
        if (strtolower(CarbonImmutable::parse($date)->englishDayOfWeek) === strtolower((string) config('backups.restore_test_day'))) {
            return true;
        }

        return ! DB::table('server_backups')->where('kind', 'db')->whereNotNull('restore_test')->where('created_at', '>=', now()->subDays(7))->exists();
    }

    /** Plain dumps a run that died left behind */
    private function cleanTmp(): void
    {
        foreach (glob($this->dir('tmp') . '/*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function locked(callable $work): array
    {
        $handle = fopen($this->dir() . '/.lock', 'c');
        for ($waited = 0; ! flock($handle, LOCK_EX | LOCK_NB); $waited += 30) {
            if ($waited >= 4 * 3600) {
                fclose($handle);

                return ['Another backup run has held the lock for 4 hours, so this one gave up.'];
            }
            Sleep::for(30)->seconds();
        }
        try {
            return $work();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function start(string $kind, string $site, string $source, string $name, string $date): array
    {
        $row = ['kind' => $kind, 'site' => $site, 'source' => $source, 'backup_date' => $date, 'file_name' => $name,
            'status' => 'running', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        $row['id'] = DB::table('server_backups')->insertGetId($row);

        return $row;
    }

    private function save(array $row, array $values): array
    {
        DB::table('server_backups')->where('id', $row['id'])->update($values + ['updated_at' => now()]);

        return array_merge($row, $values);
    }

    private function fail(array $row, Throwable $e, string $stage): array
    {
        $stage = $e instanceof BackupFailed ? $e->stage : $stage;
        if (! $e instanceof BackupFailed && ! $e instanceof GoogleUnavailable) {
            Log::error('Backups: ' . $row['file_name'] . ' failed at ' . $stage, ['message' => mb_substr($e->getMessage(), 0, 500)]);
        }

        return $this->save($row, ['status' => 'failed', 'stage' => $stage, 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
    }

    private function describe(array $row): string
    {
        $where = $row['kind'] === 'db' ? $row['source'] : 'files';
        if ($row['status'] === 'ok') {
            $test = isset($row['restore_test']) ? json_decode((string) $row['restore_test'], true) : null;

            return "{$row['site']} ({$where}): {$row['file_name']}, " . self::size((int) ($row['size_bytes'] ?? 0))
                . (! empty($row['drive_file_id']) ? ', on Drive' : ', on the server only')
                . ($test ? ', ' . lcfirst((string) RestoreTest::label($test)) . (RestoreTest::isDumpCheck($test) ? ', not imported: ' . ($test['reason'] ?? 'no scratch database') . ';' : ' (')
                    . " {$test['tables']} " . Str::plural('table', $test['tables']) . ", {$test['rows']} " . Str::plural('row', $test['rows'])
                    . (RestoreTest::isDumpCheck($test) ? ' in the dump' . (isset($test['live_rows']) ? ", {$test['live_rows']} live now" : '') : ')') : '') . '.';
        }

        return "{$row['site']} ({$where}): FAILED at {$row['stage']}: {$row['error']}";
    }

    private function ringFailures(string $what, array $failed): void
    {
        $sites = implode(', ', array_unique(array_column($failed, 'site')));
        $first = $failed[0];
        $checksum = collect($failed)->contains(fn ($r) => in_array($r['stage'], ['verify'], true));
        Bells::owners(
            ($checksum ? 'A backup upload did not match on Drive: ' : ($what === 'files' ? 'Files backup failed: ' : 'Backup failed: ')) . $sites,
            count($failed) . ' of tonight’s backups failed. ' . $first['file_name'] . ' (' . $first['stage'] . '): ' . mb_substr((string) $first['error'], 0, 300),
        );
    }

    public static function size(int $bytes): string
    {
        return match (true) {
            $bytes >= 1 << 30 => number_format($bytes / (1 << 30), 1) . ' GB',
            $bytes >= 1 << 20 => number_format($bytes / (1 << 20), 1) . ' MB',
            $bytes >= 1 << 10 => number_format($bytes / (1 << 10), 0) . ' KB',
            default => $bytes . ' B',
        };
    }
}
