<?php

namespace Tests\Feature\Backups;

use App\Livewire\Hq\System\Integrations as IntegrationsScreen;
use App\Livewire\Hq\System\SystemHealth;
use App\Support\Backups\Status;
use App\Support\Monitor\ErrorMonitor;
use App\Support\Monitor\HealthChecks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Google\FakeDrive;
use Tests\TestCase;

/**
 * The server backups end to end (owner's decisions, 4 Nov 2026; docs/BACKUPS.md): real mysqldump and mysql on the
 * test MySQL server, real tar and encryption, a fake Drive. Fictional databases and files only.
 */
class ServerBackupsTest extends TestCase
{
    use BackupFixtures;
    use UsesThrowawayDatabase;

    private const NIGHT = '2026-11-01 02:00'; // a Sunday and the 1st: restore test and monthly copy

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBackups();
        Sleep::fake();
    }

    protected function tearDown(): void
    {
        $this->tearDownBackups();
        parent::tearDown();
    }

    private function night(string $at = self::NIGHT): void
    {
        $this->travelTo(CarbonImmutable::parse($at, 'Africa/Lagos'));
    }

    public function test_a_night_backs_up_every_database_into_its_sites_folder_restore_tested_and_encrypted(): void
    {
        $owner = $this->owner();
        $this->drive = FakeDrive::install();
        $this->night();

        $this->artisan('backups:run')->assertSuccessful();

        $root = 'JBS Praxis – Server backups';
        $this->assertSame([
            "{$root}/READ ME FIRST – how to restore.pdf",
            "{$root}/hodlc/daily/hodlc_db_2026-11-01.sql.gz.enc",
            "{$root}/hodlc/monthly/hodlc_db_2026-11-01.sql.gz.enc",
            "{$root}/lpc/daily/lpc_db_2026-11-01_lpc.sql.gz.enc",
            "{$root}/lpc/daily/lpc_db_2026-11-01_lpc_mail.sql.gz.enc",
            "{$root}/lpc/monthly/lpc_db_2026-11-01_lpc.sql.gz.enc",
            "{$root}/lpc/monthly/lpc_db_2026-11-01_lpc_mail.sql.gz.enc",
            "{$root}/nfelia/daily/nfelia_db_2026-11-01.sql.gz.enc",
            "{$root}/nfelia/monthly/nfelia_db_2026-11-01.sql.gz.enc",
            "{$root}/other/praxis_test_bk_wp/daily/wp_db_2026-11-01.sql.gz.enc",
            "{$root}/other/praxis_test_bk_wp/monthly/wp_db_2026-11-01.sql.gz.enc",
            "{$root}/praxis/daily/praxis_db_2026-11-01.sql.gz.enc",
            "{$root}/praxis/monthly/praxis_db_2026-11-01.sql.gz.enc",
            "{$root}/smartls/daily/smartls_db_2026-11-01.sql.gz.enc",
            "{$root}/smartls/monthly/smartls_db_2026-11-01.sql.gz.enc",
        ], $this->drive->paths());
        // Every site's three folders exist from the first night, files/ too
        $folders = array_column(array_filter($this->drive->files, fn ($f) => $f['name'] === 'files'), 'name');
        $this->assertCount(5, $folders);
        $this->assertStringStartsWith('%PDF', (string) $this->drive->bytes("{$root}/READ ME FIRST – how to restore.pdf"));

        // Each file: the encrypted dump, which the private key opens into the database's own SQL
        $sql = gzdecode($this->open((string) $this->drive->bytes("{$root}/praxis/daily/praxis_db_2026-11-01.sql.gz.enc")));
        $this->assertStringContainsString('CREATE TABLE `clients`', $sql);
        $this->assertStringContainsString('O\\\'Brien & Sons (Test)', $sql);
        $this->assertStringContainsString('TRIGGER', $sql);
        $this->assertStringContainsString('-- Dump completed', $sql);
        $this->assertStringNotContainsString('O\\\'Brien', (string) $this->drive->bytes("{$root}/praxis/daily/praxis_db_2026-11-01.sql.gz.enc"));

        // One row per database, all good, restore tested against the dump's own counts
        $rows = DB::table('server_backups')->orderBy('id')->get();
        $this->assertSame(7, $rows->count());
        $this->assertSame(['ok'], $rows->pluck('status')->unique()->values()->all());
        $praxis = $rows->firstWhere('source', 'praxis_test_bk_praxis_core');
        $test = json_decode($praxis->restore_test, true);
        $this->assertTrue($test['ok'], implode('; ', $test['mismatches']));
        $this->assertSame([3, 1, 3], [$test['tables'], $test['views'], $test['rows']]);   // clients 3, audit and empty_table 0
        $this->assertSame(300, json_decode($rows->firstWhere('source', 'praxis_test_bk_lpc')->restore_test, true)['rows']);
        $this->assertSame(md5((string) $this->drive->bytes("{$root}/praxis/daily/praxis_db_2026-11-01.sql.gz.enc")), $praxis->md5);
        $this->assertNotNull($praxis->monthly_file_id);
        // The scratch database is empty again
        $this->assertSame([], $this->root(self::SCRATCH)->query('SHOW TABLES')->fetchAll());

        // The server keeps the encrypted copies, never a plain dump
        $this->assertContains('praxis/praxis_db_2026-11-01.sql.gz.enc', $this->serverFiles());
        $this->assertSame([], $this->serverFiles('tmp'));
        $this->assertSame([], array_filter($this->serverFiles(), fn ($f) => ! str_ends_with($f, '.enc') && ! str_starts_with(basename($f), '.')));

        // The database no site claims rings the owner once
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'title' => 'A new database was backed up: praxis_test_bk_wp']);
        $this->assertSame(1, DB::table('notifications')->count());

        // System health: every site good
        $sites = collect(Status::sites())->keyBy('site');
        $this->assertSame('ok', $sites['praxis']['state'], implode(' ', $sites['praxis']['notes']));
        $this->assertSame('warn', $sites['nfelia']['state']);   // no upload folders set for it in this test
    }

    public function test_the_next_night_adds_to_daily_skips_the_test_and_a_second_run_replaces_the_same_name(): void
    {
        $this->owner();
        $this->drive = FakeDrive::install();
        $this->night();
        $this->artisan('backups:run --site=praxis')->assertSuccessful();
        $this->night('2026-11-02 02:00');
        $this->artisan('backups:run --site=praxis')->assertSuccessful();
        $this->artisan('backups:run --site=praxis')->assertSuccessful();

        $root = 'JBS Praxis – Server backups/praxis';
        $this->assertSame(["{$root}/daily/praxis_db_2026-11-01.sql.gz.enc", "{$root}/daily/praxis_db_2026-11-02.sql.gz.enc", "{$root}/monthly/praxis_db_2026-11-01.sql.gz.enc"],
            array_values(array_filter($this->drive->paths(), fn ($p) => str_starts_with($p, $root))));
        $this->assertSame(['praxis_db_2026-11-02.sql.gz.enc'], $this->drive->trashed());
        // Monday: the restore test ran on Sunday, so not again
        $this->assertNull(DB::table('server_backups')->where('backup_date', '2026-11-02')->value('restore_test'));
    }

    public function test_drive_keeps_30_nights_and_12_months_and_the_server_7(): void
    {
        $this->owner();
        $this->drive = FakeDrive::install();
        $start = CarbonImmutable::parse('2026-11-01');
        for ($i = 1; $i <= 35; $i++) {
            $this->drive->seed('praxis/daily', 'praxis_db_' . $start->subDays($i)->format('Y-m-d') . '.sql.gz.enc');
        }
        for ($i = 1; $i <= 14; $i++) {
            $this->drive->seed('praxis/monthly', 'praxis_db_' . $start->subMonths($i)->format('Y-m-d') . '.sql.gz.enc');
        }
        // A file on Drive that isn't a dated backup is never touched
        $this->drive->seed('praxis/daily', 'notes from the owner.txt');
        @mkdir($this->home . '/backups/praxis', 0700, true);
        for ($i = 1; $i <= 9; $i++) {
            file_put_contents($this->home . '/backups/praxis/praxis_db_' . $start->subDays($i)->format('Y-m-d') . '.sql.gz.enc', 'old');
        }
        $this->night();

        $this->artisan('backups:run --site=praxis --no-restore-test')->assertSuccessful();

        $daily = array_map('basename', array_filter($this->drive->paths(), fn ($p) => str_contains($p, '/praxis/daily/')));
        $this->assertCount(31, $daily);   // 30 nights and the owner's note
        $this->assertContains('notes from the owner.txt', $daily);
        $this->assertContains('praxis_db_2026-10-03.sql.gz.enc', $daily);
        $this->assertNotContains('praxis_db_2026-10-02.sql.gz.enc', $daily);
        $monthly = array_map('basename', array_filter($this->drive->paths(), fn ($p) => str_contains($p, '/praxis/monthly/')));
        $this->assertCount(12, $monthly);
        $this->assertContains('praxis_db_2026-11-01.sql.gz.enc', $monthly);
        $this->assertNotContains('praxis_db_2025-11-01.sql.gz.enc', $monthly);
        $this->assertCount(7, glob($this->home . '/backups/praxis/*.enc'));
        $this->assertFileExists($this->home . '/backups/praxis/praxis_db_2026-11-01.sql.gz.enc');
    }

    public function test_an_interrupted_upload_resumes_where_drive_got_to(): void
    {
        $this->owner();
        $this->drive = FakeDrive::install();
        $this->drive->failChunks = 2;
        $this->drive->failOnly = 'lpc_db_2026-11-01_lpc.sql';
        $this->night();

        $this->artisan('backups:run --site=lpc --no-restore-test')->assertSuccessful();

        $row = DB::table('server_backups')->where('source', 'praxis_test_bk_lpc')->first();
        $this->assertSame('ok', $row->status, (string) $row->error);
        $this->assertGreaterThan(3 * 256 * 1024, $row->size_bytes);
        $this->assertSame($row->md5, md5((string) $this->drive->bytes('JBS Praxis – Server backups/lpc/daily/lpc_db_2026-11-01_lpc.sql.gz.enc')));
        $this->assertNotEmpty(array_filter($this->drive->calls, fn ($c) => str_ends_with($c, 'bytes */' . $row->size_bytes)), 'asked Drive where it got to');
        Sleep::assertSleptTimes(2);
    }

    public function test_a_checksum_mismatch_is_sent_again_then_fails_and_rings_the_owner(): void
    {
        $owner = $this->owner();
        $this->drive = FakeDrive::install();
        $this->night();

        // Once: the second upload matches
        $bad = 1;
        $this->drive->corrupt = function ($name) use (&$bad) {
            return str_starts_with($name, 'praxis_db') && $bad-- > 0;
        };
        $this->artisan('backups:run --site=praxis --no-restore-test')->assertSuccessful();
        $this->assertSame('ok', DB::table('server_backups')->value('status'));

        // Always: the backup fails at verify, stays on the server, and the owner hears of it
        $this->drive->corrupt = fn ($name) => str_starts_with($name, 'praxis_db');
        $this->night('2026-11-02 02:00');
        $this->artisan('backups:run --site=praxis --no-restore-test')->assertSuccessful();
        $row = DB::table('server_backups')->where('backup_date', '2026-11-02')->first();
        $this->assertSame(['failed', 'verify'], [$row->status, $row->stage]);
        $this->assertFileExists($this->home . '/backups/praxis/praxis_db_2026-11-02.sql.gz.enc');
        $this->assertNotContains('JBS Praxis – Server backups/praxis/daily/praxis_db_2026-11-02.sql.gz.enc', $this->drive->paths());
        $bell = DB::table('notifications')->where('user_id', $owner->id)->first();
        $this->assertSame('A backup upload did not match on Drive: praxis', $bell->title);
        $this->assertSame('/system/health', $bell->link_url);
        $this->assertSame('bad', collect(Status::sites())->firstWhere('site', 'praxis')['state']);
    }

    public function test_a_weekly_files_backup_tars_each_sites_folders_without_caches_logs_sessions_or_archives(): void
    {
        $this->owner();
        $this->drive = FakeDrive::install();
        $this->night('2026-11-01 03:00');

        $this->artisan('backups:files')->assertSuccessful()
            ->expectsOutputToContain('nfelia: no upload folders in config/backups.php yet')
            ->expectsOutputToContain('hodlc: no upload folders in config/backups.php yet');

        $root = 'JBS Praxis – Server backups';
        $tar = $this->open((string) $this->drive->bytes("{$root}/praxis/files/praxis_files_2026-11-01.tar.gz.enc"));
        $list = $this->tarList($tar);
        $this->assertContains('praxis_core/storage/app/private/proposals/PRP-TEST-1.pdf', $list);
        $this->assertContains('praxis_core/storage/app/public/logos/test-logo.png', $list);
        $this->assertEmpty(preg_grep('#/cache/|\.log$#', $list));
        $lpc = $this->tarList($this->open((string) $this->drive->bytes("{$root}/lpc/files/lpc_files_2026-11-01.tar.gz.enc")));
        $this->assertContains('public_html/bureau.lpc.cm/uploads/receipts/R-TEST-1.pdf', $lpc);
        $this->assertEmpty(preg_grep('#/sessions/|\.zip$|_backup_#', $lpc));
        // smartls.cm: only its two upload folders, never the docroot around them or the exports in it
        $smartls = array_values(array_filter($this->tarList($this->open((string) $this->drive->bytes("{$root}/smartls/files/smartls_files_2026-11-01.tar.gz.enc"))), fn ($p) => str_contains(basename($p), '.')));
        $this->assertSame(['public_html/uploads/portfolio/P-TEST-1.jpg', 'uploads/quote-TEST-1.pdf'], $smartls);
        $this->assertSame(['ok', 'ok', 'ok'], DB::table('server_backups')->where('kind', 'files')->pluck('status')->all());
        $this->assertSame([], $this->serverFiles('tmp'));
    }

    public function test_a_missing_upload_folder_fails_the_sites_files_backup_and_rings(): void
    {
        $owner = $this->owner();
        $this->drive = FakeDrive::install();
        config(['backups.sites.lpc.files' => ['public_html/bureau.lpc.cm/uploads', 'public_html/bureau.lpc.cm/storage']]);
        $this->night('2026-11-01 03:00');

        $this->artisan('backups:files --site=lpc')->assertSuccessful();

        $row = DB::table('server_backups')->where('kind', 'files')->first();
        $this->assertSame(['failed', 'archive'], [$row->status, $row->stage]);
        $this->assertStringContainsString('public_html/bureau.lpc.cm/storage', $row->error);
        $this->assertNotNull($row->drive_file_id, 'what exists is still backed up');
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'title' => 'Files backup failed: lpc']);
    }

    public function test_without_drive_permission_or_a_key_nothing_leaves_the_server_and_the_owner_is_told(): void
    {
        $owner = $this->owner();
        $this->drive = FakeDrive::install(withDrive: false);
        $this->night();

        $this->artisan('backups:run --site=praxis --no-restore-test')->assertSuccessful();
        $row = DB::table('server_backups')->first();
        $this->assertSame(['failed', 'upload'], [$row->status, $row->stage]);
        $this->assertStringContainsString('Reconnect Google', $row->error);
        $this->assertSame([], $this->drive->paths());
        $this->assertFileExists($this->home . '/backups/praxis/praxis_db_2026-11-01.sql.gz.enc');
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'title' => 'Backup failed: praxis']);
        $this->assertSame('bad', collect(HealthChecks::all())->firstWhere('key', 'backups')['state']);

        // No key: nothing is encrypted, so nothing is kept or sent (the plain dump is removed)
        $this->drive->allowDrive();
        unlink($this->home . '/owner-public-key.pem');
        $this->night('2026-11-02 02:00');
        $this->artisan('backups:run --site=praxis --no-restore-test')->assertSuccessful();
        $row = DB::table('server_backups')->where('backup_date', '2026-11-02')->first();
        $this->assertSame(['failed', 'key'], [$row->status, $row->stage]);
        $this->assertSame([], $this->serverFiles('tmp'));
        $this->assertSame([], array_filter($this->drive->paths(), fn ($p) => str_contains($p, '2026-11-02')));
    }

    public function test_before_the_setup_nothing_runs_or_rings_and_health_says_so(): void
    {
        $this->owner();
        unlink($this->home . '/.backup-my.cnf');
        $this->night();

        $this->artisan('backups:run')->assertSuccessful()->expectsOutputToContain('not set up on this server yet');
        $this->artisan('backups:check')->assertSuccessful()->expectsOutputToContain('Not set up yet');

        $this->assertSame(0, DB::table('server_backups')->count());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame('warn', Status::check()['state']);
    }

    public function test_a_site_whose_last_good_backup_is_over_26_hours_old_rings_the_owner_once_a_day(): void
    {
        $owner = $this->owner();
        $this->drive = FakeDrive::install();
        $this->night();
        $this->artisan('backups:run --no-restore-test')->assertSuccessful();
        DB::table('notifications')->delete();
        ErrorMonitor::setState('backups.setup_at', now()->subDays(3)->toDateTimeString());

        // Monday 03:17: 25 hours since Sunday's backup
        $this->travelTo(CarbonImmutable::parse('2026-11-02 03:17', 'Africa/Lagos'));
        $this->artisan('backups:check')->assertSuccessful()->expectsOutputToContain('Every site is within its limits');
        $this->assertSame(0, DB::table('notifications')->count());

        // Monday's run never happened: at 04:17 every site rings, once
        $this->travelTo(CarbonImmutable::parse('2026-11-02 04:17', 'Africa/Lagos'));
        $this->artisan('backups:check')->assertSuccessful();
        $this->artisan('backups:check')->assertSuccessful();
        $bells = DB::table('notifications')->where('user_id', $owner->id)->pluck('title')->all();
        $this->assertContains('HQ, jbspraxis.com and the portal: no backup for over 26 hours', $bells);
        $this->assertCount(6, $bells);   // the five sites and other/praxis_test_bk_wp, once each
        $this->assertSame('bad', Status::check()['state']);

        // A day later, still nothing: they ring again
        $this->travelTo(CarbonImmutable::parse('2026-11-03 04:17', 'Africa/Lagos'));
        $this->artisan('backups:check')->assertSuccessful();
        $this->assertSame(12, DB::table('notifications')->count());
    }

    public function test_system_health_shows_each_sites_last_backups_and_integrations_asks_for_drive(): void
    {
        $owner = $this->owner();
        $this->drive = FakeDrive::install(withDrive: false);
        $this->actingAs($owner);
        Livewire::test(IntegrationsScreen::class)->assertSee('Drive backups: reconnect Google to allow');

        $this->drive->allowDrive();
        Livewire::test(IntegrationsScreen::class)->assertSee('Drive backups: allowed')->assertDontSee('Drive backups: reconnect');

        $this->night();
        $this->artisan('backups:run --site=praxis')->assertSuccessful();
        Livewire::test(SystemHealth::class)
            ->assertSee('Server backups')
            ->assertSee('HQ, jbspraxis.com and the portal')
            ->assertSee('Passed')
            ->assertSee('Sun 1 Nov, 02:00')
            ->assertSee('on Drive');
    }

    public function test_setup_creates_the_read_only_user_through_uapi_and_never_shows_its_password(): void
    {
        $this->owner();
        unlink($this->home . '/.backup-my.cnf');
        $db = config('database.connections.' . config('database.default'));
        config(['backups.databases_from' => 'cpanel', 'backups.mysql_user' => self::BACKUP_USER, 'backups.mysql_host' => $db['host'], 'backups.mysql_port' => $db['port']]);
        $root = $this->root();
        $calls = [];
        $users = [];
        $databases = [['database' => 'praxis_test_bk_praxis_core', 'users' => []], ['database' => 'praxis_test_bk_wp', 'users' => []]];
        // uapi as cPanel answers, doing on the test server what cPanel does: the user really exists, with that password
        Process::fake(function ($process) use (&$calls, &$users, &$databases, $root) {
            $args = array_slice($process->command, 2);
            $calls[] = $args;
            [$module, $function] = $args;
            $params = [];
            foreach (array_slice($args, 2) as $pair) {
                [$k, $v] = explode('=', $pair, 2);
                $params[$k] = rawurldecode($v);
            }
            $data = match ("{$module}::{$function}") {
                'Mysql::list_users' => array_map(fn ($u) => ['user' => $u], $users),
                'Mysql::list_databases' => $databases,
                'Mysql::create_user' => (function () use (&$users, $params, $root) {
                    $root->prepare("CREATE USER '" . self::BACKUP_USER . "'@'%' IDENTIFIED BY ?")->execute([$params['password']]);
                    $users[] = $params['name'];

                    return null;
                })(),
                'Mysql::set_privileges_on_database' => (function () use ($params, $root) {
                    $root->exec("GRANT {$params['privileges']} ON `{$params['database']}`.* TO '" . self::BACKUP_USER . "'@'%'");

                    return null;
                })(),
                'Mysql::create_database' => (function () use (&$databases, $params) { $databases[] = ['database' => $params['name'], 'users' => []]; return null; })(),
                default => null,
            };

            return Process::result(json_encode(['result' => ['status' => 1, 'data' => $data, 'errors' => null]]));
        });

        $this->artisan('backups:setup --dry-run')->assertSuccessful()->expectsOutputToContain('Would create the read-only MySQL user praxis_test_bk_bkread');
        $this->assertFileDoesNotExist($this->home . '/.backup-my.cnf');
        $this->assertSame([], array_filter($calls, fn ($c) => ! str_starts_with($c[1], 'list_')));

        $this->artisan('backups:setup')->assertSuccessful()
            ->expectsOutputToContain('praxis_test_bk_praxis_core → praxis')
            ->expectsOutputToContain('praxis_test_bk_wp → other/praxis_test_bk_wp')
            ->expectsOutputToContain('Signed in to MySQL as praxis_test_bk_bkread')
            ->doesntExpectOutputToContain('password=');
        $create = collect($calls)->first(fn ($c) => $c[1] === 'create_user');
        $password = rawurldecode(substr($create[3], strlen('password=')));
        $this->assertSame(40, strlen($password));
        $cnf = (string) file_get_contents($this->home . '/.backup-my.cnf');
        $this->assertStringContainsString("user=praxis_test_bk_bkread\npassword=\"{$password}\"\nhost={$db['host']}\nport={$db['port']}", $cnf);
        $this->assertStringNotContainsString($password, implode("\n", array_map(fn ($c) => implode(' ', $c), array_filter($calls, fn ($c) => $c[1] !== 'create_user'))));
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame('0600', substr(sprintf('%o', fileperms($this->home . '/.backup-my.cnf')), -4));
            $this->assertSame('0700', substr(sprintf('%o', fileperms($this->home . '/backups')), -4));
        }
        $grants = collect($calls)->filter(fn ($c) => $c[1] === 'set_privileges_on_database')->map(fn ($c) => rawurldecode(substr($c[3], 9)) . ' ' . rawurldecode(substr($c[4], 11)))->values()->all();
        $this->assertSame([
            self::SCRATCH . ' ALL PRIVILEGES',
            'praxis_test_bk_praxis_core SELECT,SHOW VIEW,LOCK TABLES,TRIGGER,EVENT',
            'praxis_test_bk_wp SELECT,SHOW VIEW,LOCK TABLES,TRIGGER,EVENT',
        ], $grants);
        $this->assertTrue(collect($calls)->contains(fn ($c) => $c[1] === 'create_database' && $c[2] === 'name=' . self::SCRATCH));
        $this->assertNotNull(ErrorMonitor::state('backups.setup_at'));

        // Once done, the one-shot cron line does nothing
        $before = count($calls);
        $this->artisan('backups:setup --once')->assertSuccessful();
        $this->assertCount($before, $calls);
    }

    public function test_the_nightly_run_grants_a_database_added_since_the_setup(): void
    {
        $this->owner();
        $this->drive = FakeDrive::install();
        config(['backups.databases_from' => 'cpanel', 'backups.mysql_user' => self::BACKUP_USER]);
        $calls = [];
        Process::fake(function ($process) use (&$calls) {
            $calls[] = array_slice($process->command, 2);
            $data = $process->command[3] === 'list_databases' ? [
                ['database' => 'praxis_test_bk_praxis_core', 'users' => ['praxis_test_bk_bkread']],
                ['database' => 'praxis_test_bk_nfelia_core', 'users' => []],
                ['database' => self::SCRATCH, 'users' => ['praxis_test_bk_bkread']],
            ] : null;

            return Process::result(json_encode(['result' => ['status' => 1, 'data' => $data]]));
        });
        $this->night('2026-11-03 02:00');

        $this->artisan('backups:run --no-restore-test')->assertSuccessful()
            ->expectsOutputToContain('Gave the backup user read access to the new database praxis_test_bk_nfelia_core');

        $this->assertSame([['Mysql', 'list_databases'], ['Mysql', 'set_privileges_on_database', 'user=praxis_test_bk_bkread', 'database=praxis_test_bk_nfelia_core', 'privileges=SELECT%2CSHOW%20VIEW%2CLOCK%20TABLES%2CTRIGGER%2CEVENT']], $calls);
        $this->assertSame(['praxis_test_bk_nfelia_core', 'praxis_test_bk_praxis_core'], DB::table('server_backups')->orderBy('source')->pluck('source')->all());
    }

    /** @return list<string> the paths in a .tar.gz */
    private function tarList(string $tarGz): array
    {
        $file = tempnam(sys_get_temp_dir(), 'bktar') . '.tar.gz';
        file_put_contents($file, $tarGz);
        try {
            exec(escapeshellarg((string) config('backups.bin.tar')) . ' -tzf ' . escapeshellarg($file), $out, $code);
            $this->assertSame(0, $code);

            return array_values(array_filter(array_map(fn ($l) => rtrim(str_replace('\\', '/', $l), '/'), $out)));
        } finally {
            @unlink($file);
        }
    }

    private function owner(): \App\Models\User
    {
        return $this->staff('owner', ['owner']);
    }
}
