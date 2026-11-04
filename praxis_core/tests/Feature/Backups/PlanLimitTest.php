<?php

namespace Tests\Feature\Backups;

use App\Livewire\Hq\System\SystemHealth;
use App\Support\Backups\Dumper;
use App\Support\Backups\MysqlClient;
use App\Support\Backups\RestoreTest;
use App\Support\Backups\Status;
use App\Support\Monitor\ErrorMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Google\FakeDrive;
use Tests\TestCase;

/**
 * The cPanel plan allows 5 databases and the five sites use them all (live, 4 Nov 2026: backups:setup stopped at
 * "You have reached your maximum allotment of databases (5)"). Without the scratch database the backups still run,
 * and the weekly test is a dump check that says so (docs/BACKUPS.md 5). Fictional databases only.
 */
class PlanLimitTest extends TestCase
{
    use BackupFixtures;
    use UsesThrowawayDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBackups();
        Sleep::fake();
        // As on live: there is no scratch database
        $this->root()->exec('DROP DATABASE `' . self::SCRATCH . '`');
    }

    protected function tearDown(): void
    {
        $this->tearDownBackups();
        parent::tearDown();
    }

    public function test_setup_goes_on_without_the_scratch_database_and_a_rerun_finishes_then_brings_it_back(): void
    {
        $this->owner();
        unlink($this->home . '/.backup-my.cnf');
        $db = config('database.connections.' . config('database.default'));
        config(['backups.databases_from' => 'cpanel', 'backups.mysql_user' => self::BACKUP_USER, 'backups.mysql_host' => $db['host'], 'backups.mysql_port' => $db['port']]);
        $calls = [];
        $users = [];
        $databases = array_map(fn ($name) => ['database' => $name, 'users' => []],
            ['praxis_test_bk_praxis_core', 'praxis_test_bk_lpc', 'praxis_test_bk_nfelia_core', 'praxis_test_bk_hodlc', 'praxis_test_bk_smartls']);
        $this->fakeUapi($calls, $users, $databases, databaseLimit: 5);

        // First run, as on live: the user and its client file are made, the scratch database is refused
        $this->artisan('backups:setup')->assertSuccessful()
            ->expectsOutputToContain('Created the read-only MySQL user praxis_test_bk_bkread')
            ->expectsOutputToContain('Gave praxis_test_bk_bkread read access to 5 database(s)')
            ->expectsOutputToContain('Could not create the scratch database ' . self::SCRATCH . ': the cPanel plan allows no more databases. cPanel said: You have reached your maximum allotment of databases (5).')
            ->expectsOutputToContain('the weekly restore test is a dump check')
            ->expectsOutputToContain('run backups:setup again: the full import test then comes back by itself')
            ->expectsOutputToContain('Signed in to MySQL as praxis_test_bk_bkread')
            ->expectsOutputToContain('Set up.');
        $this->assertSame(5, collect($calls)->where(1, 'set_privileges_on_database')->count(), 'the five databases, no scratch grant');
        $this->assertFileExists($this->home . '/backups/.setup-done');
        $this->assertNotNull(ErrorMonitor::state('backups.setup_at'));

        // A rerun now that the user and the client file exist (as live will be): nothing new is made, it finishes
        unlink($this->home . '/backups/.setup-done');
        ErrorMonitor::setState('backups.setup_at', '');
        $calls = [];
        $this->artisan('backups:setup')->assertSuccessful()
            ->expectsOutputToContain('The MySQL user praxis_test_bk_bkread and its client file are already in place.')
            ->expectsOutputToContain('Could not create the scratch database')
            ->expectsOutputToContain('praxis_test_bk_hodlc → hodlc (1 table)');
        $this->assertSame([], collect($calls)->whereIn(1, ['create_user', 'set_password'])->all());
        $this->assertSame(5, collect($calls)->where(1, 'set_privileges_on_database')->count());
        $this->assertFileExists($this->home . '/backups/.setup-done');
        $this->assertNotSame('', ErrorMonitor::state('backups.setup_at'));
        $this->artisan('backups:setup --once')->assertSuccessful()->doesntExpectOutputToContain('Could not');

        // The host allows a sixth database: the same command brings the import test back
        $calls = [];
        $this->fakeUapi($calls, $users, $databases, databaseLimit: 6);
        $this->artisan('backups:setup')->assertSuccessful()
            ->expectsOutputToContain('Created the scratch database ' . self::SCRATCH . ': the weekly restore test imports each dump into it');
        $this->assertTrue(collect($calls)->contains(fn ($c) => $c[1] === 'set_privileges_on_database' && $c[3] === 'database=' . self::SCRATCH && $c[4] === 'privileges=ALL%20PRIVILEGES'));
        $this->assertTrue(app(MysqlClient::class)->canUse(self::SCRATCH));
    }

    public function test_the_weekly_test_is_an_honest_dump_check_without_a_scratch_database_and_rings_nobody(): void
    {
        $this->owner();
        $this->drive = FakeDrive::install();
        $this->travelTo(CarbonImmutable::parse('2026-11-08 02:00', 'Africa/Lagos'));   // a Sunday

        $this->artisan('backups:run --site=praxis')->assertSuccessful()
            ->expectsOutputToContain('dump check passed, not imported: no scratch database (the cPanel plan’s database limit); 3 tables, 3 rows in the dump, 3 live now.');

        $row = DB::table('server_backups')->first();
        $this->assertSame('ok', $row->status);
        $test = json_decode($row->restore_test, true);
        $this->assertSame([RestoreTest::DUMP_CHECK, true, 3, 1, 3, 3, []], [$test['mode'], $test['ok'], $test['tables'], $test['views'], $test['rows'], $test['live_rows'], $test['mismatches']]);
        $this->assertNotNull($row->drive_file_id);

        // System health: "Dump check passed", never "Restore test passed"; the site stays green; no bell
        $site = collect(Status::sites())->firstWhere('site', 'praxis');
        $this->assertSame('ok', $site['state'], implode(' ', $site['notes']));
        $this->assertSame('Dump check passed', RestoreTest::label($site['restore_result']));
        $this->assertStringContainsString('The weekly test is a dump check, not an import', Status::check()['detail']);
        $this->actingAs($this->owner());
        Livewire::test(SystemHealth::class)
            ->assertSee('Dump check passed')
            ->assertSee('3 rows in the dump')
            ->assertSee('Not imported: no scratch database (the cPanel plan’s database limit).')
            ->assertDontSee('Restore test passed');
        ErrorMonitor::setState('backups.setup_at', now()->subHours(2)->toDateTimeString());
        $this->artisan('backups:check')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_the_dump_check_catches_a_missing_table_a_cut_file_and_a_missing_trailer(): void
    {
        $mysql = app(MysqlClient::class);
        $dump = $this->home . '/praxis.sql.gz';
        Dumper::dump($mysql, 'praxis_test_bk_praxis_core', $dump);
        $sql = (string) gzdecode((string) file_get_contents($dump));
        $this->assertTrue(RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $dump)['ok']);

        // A table made after the dump is not in it
        $this->root('praxis_test_bk_praxis_core')->exec('CREATE TABLE later (id INT PRIMARY KEY)');
        $test = RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $dump);
        $this->assertFalse($test['ok']);
        $this->assertSame(['later: in the live database but not in the dump'], $test['mismatches']);
        $this->root('praxis_test_bk_praxis_core')->exec('DROP TABLE later');

        // The .gz cut short: its compressed stream never ends
        file_put_contents($this->home . '/cut.sql.gz', substr((string) file_get_contents($dump), 0, (int) (filesize($dump) * 0.6)));
        $test = RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $this->home . '/cut.sql.gz');
        $this->assertFalse($test['ok']);
        $this->assertStringContainsString('The dump is cut short', $test['mismatches'][0]);

        // A damaged byte: zlib's checksum refuses it
        $bytes = (string) file_get_contents($dump);
        $bytes[intdiv(strlen($bytes), 2)] = chr(ord($bytes[intdiv(strlen($bytes), 2)]) ^ 0xFF);
        file_put_contents($this->home . '/damaged.sql.gz', $bytes);
        $this->assertFalse(RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $this->home . '/damaged.sql.gz')['ok']);

        // A dump without mysqldump's closing line (a well-formed .gz of an unfinished dump)
        file_put_contents($this->home . '/untrailed.sql.gz', gzencode((string) preg_replace('/^-- Dump completed.*$/m', '', $sql)));
        $test = RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $this->home . '/untrailed.sql.gz');
        $this->assertFalse($test['ok']);
        $this->assertSame(['the dump has no "Dump completed" line at its end: it was cut off'], $test['mismatches']);
    }

    public function test_rows_written_after_the_dump_are_shown_and_never_fail_the_check(): void
    {
        $mysql = app(MysqlClient::class);
        $dump = $this->home . '/praxis.sql.gz';
        Dumper::dump($mysql, 'praxis_test_bk_praxis_core', $dump);
        $this->root('praxis_test_bk_praxis_core')->exec("INSERT INTO clients VALUES (4, 'Later Test', NULL, NULL)");

        $test = RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $dump);

        $this->assertTrue($test['ok']);
        $this->assertSame([3, 5], [$test['rows'], $test['live_rows']]);   // the trigger wrote an audit row too
        $this->assertContains('clients: 3 in the dump, 4 live now', $test['differences']);
    }

    public function test_with_a_scratch_database_a_cut_dump_fails_the_import_test_too(): void
    {
        $this->root()->exec('CREATE DATABASE `' . self::SCRATCH . '`');
        $mysql = app(MysqlClient::class);
        $dump = $this->home . '/praxis.sql.gz';
        Dumper::dump($mysql, 'praxis_test_bk_praxis_core', $dump);
        $test = RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $dump);
        $this->assertSame([RestoreTest::IMPORT, true], [$test['mode'], $test['ok']]);

        file_put_contents($this->home . '/cut.sql.gz', substr((string) file_get_contents($dump), 0, (int) (filesize($dump) * 0.6)));
        try {
            RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $this->home . '/cut.sql.gz');
            $this->fail('a cut dump passed the import test');
        } catch (\App\Support\Backups\BackupFailed $e) {
            $this->assertStringContainsString('The dump is cut short', $e->getMessage());
        }
        $this->assertSame([], $this->root(self::SCRATCH)->query('SHOW TABLES')->fetchAll(), 'the scratch database is emptied after a failed test');
    }

    private function owner(): \App\Models\User
    {
        return $this->staff('owner', ['owner']);
    }
}
