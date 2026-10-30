<?php

namespace Tests\Feature;

use App\Console\Commands\DbShow;
use App\Console\Commands\DbTable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * "artisan db:show --counts" failed on the live server (29 Oct 2026, System health): its PHP has no intl extension,
 * and Laravel writes sizes and row counts with Illuminate\Support\Number, which needs it. HQ's DbShow and DbTable
 * replace the framework's commands and print JSON instead when intl is missing. Checked in a second PHP started
 * without intl (php -n, then every other extension this PHP loads), on a small SQLite database.
 */
class DbInspectWithoutIntlTest extends TestCase
{
    private ?string $database = null;

    protected function tearDown(): void
    {
        if ($this->database !== null) {
            @unlink($this->database);
        }
        parent::tearDown();
    }

    public function test_hq_replaces_the_framework_commands(): void
    {
        $commands = Artisan::all();

        $this->assertInstanceOf(DbShow::class, $commands['db:show']);
        $this->assertInstanceOf(DbTable::class, $commands['db:table']);
    }

    public function test_db_show_and_db_table_work_in_a_php_without_intl(): void
    {
        $php = $this->phpWithout('intl');
        if ($php === null) {
            $this->markTestSkipped('intl is built into this PHP: it cannot be left out');
        }

        $this->database = tempnam(sys_get_temp_dir(), 'dbi');
        $pdo = new \PDO('sqlite:' . $this->database);
        $pdo->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT)');
        $pdo->exec("INSERT INTO notes (body) VALUES ('one'), ('two')");
        $pdo = null;

        $artisan = fn (array $args) => Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database, 'DB_URL' => '',
                'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'null', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
            ])
            ->run([...$php, base_path('artisan'), ...$args, '--no-ansi', '--no-interaction']);

        $check = Process::run([...$php, '-r', 'echo json_encode([extension_loaded("intl"), extension_loaded("pdo_sqlite")]);']);
        $this->assertSame('[false,true]', trim($check->output()), 'the second PHP: no intl, with SQLite. ' . $check->errorOutput());

        $show = $artisan(['db:show', '--counts']);
        $this->assertTrue($show->successful(), $show->errorOutput() . $show->output());
        $this->assertStringContainsString('no intl extension', $show->errorOutput());
        $data = json_decode($show->output(), true);
        $this->assertIsArray($data, $show->output());
        $notes = collect($data['tables'])->firstWhere('table', 'notes');
        $this->assertSame(2, $notes['rows'] ?? null);

        $table = $artisan(['db:table', 'notes']);
        $this->assertTrue($table->successful(), $table->errorOutput() . $table->output());
        $data = json_decode($table->output(), true);
        $this->assertIsArray($data, $table->output());
        $this->assertSame(['id', 'body'], array_column($data['columns'], 'column'));

        // --json is untouched: no note
        $json = $artisan(['db:show', '--json']);
        $this->assertTrue($json->successful(), $json->errorOutput());
        $this->assertStringNotContainsString('no intl extension', $json->errorOutput());
    }

    public function test_with_intl_the_usual_layout_is_unchanged(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('this PHP has no intl');
        }

        $this->database = tempnam(sys_get_temp_dir(), 'dbi');
        $pdo = new \PDO('sqlite:' . $this->database);
        $pdo->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT)');
        $pdo = null;
        config(['database.connections.dbi' => ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '', 'foreign_key_constraints' => true]]);

        $this->assertSame(0, Artisan::call('db:show', ['--database' => 'dbi', '--counts' => true]));
        $out = Artisan::output();
        $this->assertStringContainsString('notes', $out);
        $this->assertNull(json_decode($out, true), 'the usual layout, not JSON');
    }

    /**
     * A PHP without $extension: this one when it already lacks it, else this one without php.ini (-n) plus the
     * extensions it loads except that one; null when the extension is compiled in.
     */
    private function phpWithout(string $extension): ?array
    {
        if (! extension_loaded($extension)) {
            return [PHP_BINARY];
        }

        $dir = (string) ini_get('extension_dir');
        if (! is_dir($dir)) {
            $dir = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $dir;
        }
        $shared = fn (string $name) => is_file("{$dir}/php_{$name}.dll") || is_file("{$dir}/{$name}.so");
        if (! $shared($extension)) {
            return null;
        }

        $command = [PHP_BINARY, '-n', '-d', 'extension_dir=' . $dir, '-d', 'memory_limit=512M'];
        $zend = array_map('strtolower', get_loaded_extensions(true));
        foreach (get_loaded_extensions() as $loaded) {
            $name = strtolower($loaded);
            // Compiled-in extensions stay without asking; Zend ones (OPcache, Xdebug) are not needed here
            if ($name !== $extension && ! in_array($name, $zend, true) && $shared($name)) {
                array_push($command, '-d', 'extension=' . $name);
            }
        }

        return $command;
    }
}
