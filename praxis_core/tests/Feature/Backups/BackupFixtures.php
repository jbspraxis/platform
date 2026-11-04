<?php

namespace Tests\Feature\Backups;

use App\Support\Backups\Envelope;
use Illuminate\Support\Facades\Process;
use PDO;
use Tests\Support\Google\FakeDrive;

/**
 * Server backups fixtures (docs/BACKUPS.md 9): a throwaway "cPanel home" in the temp folder with fictional upload
 * folders, and small MySQL databases standing in for the five sites (praxis_test_bk_*), filled with fictional rows.
 * MySQL is reached as the test connection's own user through a client file, as the backup user is on the server.
 * Needs mysqldump and mysql (PATH, or BACKUP_MYSQLDUMP / BACKUP_MYSQL); skipped without them.
 */
trait BackupFixtures
{
    protected string $home;

    protected FakeDrive $drive;

    private static array $testKey = [];

    /** praxis has a view and a trigger; lpc two databases; wp belongs to no site */
    protected const DATABASES = ['praxis_test_bk_praxis_core', 'praxis_test_bk_lpc', 'praxis_test_bk_lpc_mail', 'praxis_test_bk_nfelia_core',
        'praxis_test_bk_hodlc', 'praxis_test_bk_smartls', 'praxis_test_bk_wp'];

    protected const SCRATCH = 'praxis_test_bk_scratch';

    protected const BACKUP_USER = 'praxis_test_bk_bkread';

    protected function setUpBackups(): void
    {
        foreach (['mysqldump', 'mysql'] as $bin) {
            $path = (string) (getenv('BACKUP_' . strtoupper($bin)) ?: $bin);
            exec(escapeshellarg($path) . ' --version 2>&1', $out, $code);
            if ($code !== 0) {
                $this->markTestSkipped("Needs {$bin} (set BACKUP_" . strtoupper($bin) . ' to its path).');
            }
            config(["backups.bin.{$bin}" => $path]);
        }

        $this->home = str_replace('\\', '/', sys_get_temp_dir()) . '/bk-home-' . bin2hex(random_bytes(4));
        mkdir($this->home, 0700, true);
        $db = config('database.connections.' . config('database.default'));
        file_put_contents($this->home . '/.backup-my.cnf', "[client]\nuser={$db['username']}\npassword=\"{$db['password']}\"\nhost={$db['host']}\nport={$db['port']}\n");
        [, $public] = self::testKey();
        file_put_contents($this->home . '/owner-public-key.pem', $public);

        config([
            'backups.home' => $this->home,
            'backups.dir' => $this->home . '/backups',
            'backups.mysql_cnf' => $this->home . '/.backup-my.cnf',
            'backups.cpanel_user' => 'praxis_test_bk',
            'backups.databases_from' => 'mysql',
            'backups.database_prefix' => 'praxis_test_bk_',
            'backups.scratch_database' => self::SCRATCH,
            'backups.public_key' => $this->home . '/owner-public-key.pem',
            'backups.sites.praxis.databases' => ['praxis_test_bk_praxis*'],
            'backups.sites.lpc.databases' => ['praxis_test_bk_lpc*'],
            'backups.sites.nfelia.databases' => ['praxis_test_bk_nfelia*'],
            'backups.sites.hodlc.databases' => ['praxis_test_bk_hodlc*'],
            'backups.sites.smartls.databases' => ['praxis_test_bk_smartls*'],
            'backups.sites.praxis.files' => ['praxis_core/storage/app/private', 'praxis_core/storage/app/public'],
            'backups.sites.lpc.files' => ['public_html/bureau.lpc.cm/uploads'],
            'backups.sites.nfelia.files' => [],
            'backups.sites.hodlc.files' => [],
            'backups.sites.smartls.files' => ['public_html/uploads', 'uploads'],
            'backups.drive.chunk_bytes' => 256 * 1024,
        ]);

        $this->makeDatabases();
        $this->makeUploads();
    }

    protected function tearDownBackups(): void
    {
        if (! isset($this->home)) {
            return;
        }
        $pdo = $this->root();
        foreach ([...self::DATABASES, self::SCRATCH] as $name) {
            $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        }
        $pdo->exec("DROP USER IF EXISTS '" . self::BACKUP_USER . "'@'%'");
        self::removeTree($this->home);
    }

    /**
     * uapi as cPanel answers it, doing on the test server what cPanel does: create_user really creates the MySQL user
     * with that password, set_privileges_on_database really grants. $databaseLimit is the plan's number of databases:
     * beyond it, create_database is refused with cPanel's own words, as on live on 4 Nov 2026 (5 of 5 used).
     */
    protected function fakeUapi(array &$calls, array &$users, array &$databases, ?int $databaseLimit = null): void
    {
        $root = $this->root();
        Process::fake(function ($process) use (&$calls, &$users, &$databases, $databaseLimit, $root) {
            $args = array_slice($process->command, 2);
            $calls[] = $args;
            [$module, $function] = $args;
            $params = [];
            foreach (array_slice($args, 2) as $pair) {
                [$k, $v] = explode('=', $pair, 2);
                $params[$k] = rawurldecode($v);
            }
            if ("{$module}::{$function}" === 'Mysql::create_database' && $databaseLimit !== null && count($databases) >= $databaseLimit) {
                return Process::result(json_encode(['result' => ['status' => 0, 'data' => null,
                    'errors' => ["You have reached your maximum allotment of databases ({$databaseLimit})."]]]));
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
                'Mysql::create_database' => (function () use (&$databases, $params, $root) {
                    $root->exec("CREATE DATABASE `{$params['name']}`");
                    $databases[] = ['database' => $params['name'], 'users' => []];

                    return null;
                })(),
                default => null,
            };

            return Process::result(json_encode(['result' => ['status' => 1, 'data' => $data, 'errors' => null]]));
        });
    }

    /** The test connection's user, on its own connection (DDL here must not end the test's transaction) */
    protected function root(?string $database = null): PDO
    {
        $db = config('database.connections.' . config('database.default'));

        return new PDO("mysql:host={$db['host']};port={$db['port']}" . ($database ? ";dbname={$database}" : '') . ';charset=utf8mb4',
            $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function makeDatabases(): void
    {
        $pdo = $this->root();
        foreach ([...self::DATABASES, self::SCRATCH] as $name) {
            $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
            $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $praxis = $this->root('praxis_test_bk_praxis_core');
        $praxis->exec("CREATE TABLE clients (id INT PRIMARY KEY, name VARCHAR(120), note TEXT, photo BLOB) ENGINE=InnoDB");
        $praxis->exec("INSERT INTO clients VALUES (1, 'Ada Test', 'Lagos (Ikeja), office 2', NULL),
            (2, 'O''Brien & Sons (Test)', 'Says \"hi\"; owes ₦12,000.00 (two invoices)', X'00FF27285C'),
            (3, 'Zoë Exemple', 'Line one\nLine two', NULL)");
        $praxis->exec('CREATE TABLE audit (id INT AUTO_INCREMENT PRIMARY KEY, client_id INT, at DATETIME) ENGINE=InnoDB');
        $praxis->exec('CREATE TRIGGER clients_audit AFTER INSERT ON clients FOR EACH ROW INSERT INTO audit (client_id, at) VALUES (NEW.id, NOW())');
        $praxis->exec('CREATE VIEW client_names AS SELECT id, name FROM clients');
        $praxis->exec('CREATE TABLE empty_table (id INT PRIMARY KEY)');

        $lpc = $this->root('praxis_test_bk_lpc');
        $lpc->exec('CREATE TABLE invoices (id INT AUTO_INCREMENT PRIMARY KEY, ref VARCHAR(40), body TEXT)');
        // About a megabyte of text that gzip can't shrink much: the upload takes several 256 KB chunks
        $insert = $lpc->prepare('INSERT INTO invoices (ref, body) VALUES (?, ?)');
        for ($i = 1; $i <= 300; $i++) {
            $insert->execute(["LPC-TEST-{$i}", base64_encode(random_bytes(3000))]);
        }
        $this->root('praxis_test_bk_lpc_mail')->exec("CREATE TABLE mail (id INT PRIMARY KEY, subject VARCHAR(100)); INSERT INTO mail VALUES (1, 'Test (fictional)')");
        foreach (['praxis_test_bk_nfelia_core', 'praxis_test_bk_hodlc', 'praxis_test_bk_smartls', 'praxis_test_bk_wp'] as $name) {
            $this->root($name)->exec("CREATE TABLE things (id INT PRIMARY KEY, label VARCHAR(50)); INSERT INTO things VALUES (1, 'one'), (2, 'two')");
        }
    }

    private function makeUploads(): void
    {
        $files = [
            'praxis_core/storage/app/private/proposals/PRP-TEST-1.pdf' => '%PDF-1.4 fictional proposal',
            'praxis_core/storage/app/private/cache/thumb.bin' => 'cache, left out',
            'praxis_core/storage/app/public/logos/test-logo.png' => 'fictional logo',
            'praxis_core/storage/app/public/debug.log' => 'a log, left out',
            'public_html/bureau.lpc.cm/uploads/receipts/R-TEST-1.pdf' => '%PDF-1.4 fictional receipt',
            'public_html/bureau.lpc.cm/uploads/sessions/sess_x' => 'a session, left out',
            'public_html/bureau.lpc.cm/uploads/old_export.zip' => 'an archive, left out',
            'public_html/bureau.lpc.cm/uploads/receipts_backup_2026/R-OLD.pdf' => 'an old backup copy, left out',
            // smartls.cm: the docroot is public_html itself, beside the other sites and old exports
            'public_html/uploads/portfolio/P-TEST-1.jpg' => 'fictional portfolio image',
            'uploads/quote-TEST-1.pdf' => '%PDF-1.4 fictional private file',
            'public_html/index.php' => 'the site, never in a files backup',
            'public_html/site-export.tar.gz' => 'an old export, never in a files backup',
        ];
        foreach ($files as $path => $content) {
            @mkdir(dirname($this->home . '/' . $path), 0700, true);
            file_put_contents($this->home . '/' . $path, $content);
        }
    }

    /** @return array{0: string, 1: string} the test key pair (private, public PEM): fictional, made once per run */
    protected static function testKey(): array
    {
        if (! self::$testKey) {
            $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if ($key === false) {
                self::markTestSkipped('PHP’s OpenSSL could not make a test key here (set OPENSSL_CONF to an openssl.cnf).');
            }
            openssl_pkey_export($key, $private);
            self::$testKey = [$private, openssl_pkey_get_details($key)['key']];
        }

        return self::$testKey;
    }

    /** Opens an encrypted backup's bytes with the test private key */
    protected function open(string $encrypted): string
    {
        $in = tempnam(sys_get_temp_dir(), 'bkt');
        $out = $in . '.out';
        file_put_contents($in, $encrypted);
        try {
            Envelope::decrypt($in, $out, self::testKey()[0]);

            return (string) file_get_contents($out);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    protected function serverFiles(string $sub = ''): array
    {
        $files = [];
        $base = $this->home . '/backups' . ($sub !== '' ? '/' . $sub : '');
        if (! is_dir($base)) {
            return [];
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/');
        }
        sort($files);

        return $files;
    }

    private static function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
