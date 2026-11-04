<?php

namespace Tests\Feature\Backups;

use App\Support\Backups\Dumper;
use App\Support\Backups\MysqlClient;
use App\Support\Backups\RestoreTest;
use Illuminate\Support\Sleep;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The server's mysqldump is MariaDB 10.11's (live, 4 Nov 2026): "INSERT INTO `t` VALUES" alone on its line, then one
 * "(...)," line per row until the one ending in ";". The dump check counted 0 rows in all five sites and passed. CI's
 * mysqldump (MySQL 8) writes every row on the INSERT line, so the MariaDB layout is a hand-written fixture here,
 * checked against a live table with the same fictional rows. docs/BACKUPS.md 5.
 */
class MariaDbDumpTest extends TestCase
{
    use BackupFixtures;
    use UsesThrowawayDatabase;

    private const LIVE = 'praxis_test_bk_smartls';

    /** As MariaDB 10.11's mysqldump writes it: the escapes are mysqldump's (\' and \n), the ), ( and ; are in strings */
    private const MARIADB_DUMP = <<<'SQL'
        -- MariaDB dump 10.19  Distrib 10.11.19-MariaDB, for Linux (x86_64)
        --
        -- Host: localhost    Database: praxis_test_bk_smartls
        -- ------------------------------------------------------
        -- Server version	10.11.19-MariaDB

        /*!40101 SET NAMES utf8mb4 */;
        /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

        --
        -- Table structure for table `mdb_rows`
        --

        DROP TABLE IF EXISTS `mdb_rows`;
        CREATE TABLE `mdb_rows` (
          `id` int(11) NOT NULL,
          `note` text DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        --
        -- Dumping data for table `mdb_rows`
        --

        LOCK TABLES `mdb_rows` WRITE;
        /*!40000 ALTER TABLE `mdb_rows` DISABLE KEYS */;
        INSERT INTO `mdb_rows` VALUES
        (1,'LOGIN_SUCCESS (web)'),
        (2,'a quote \' and a ),( inside'),
        (3,'semi; colon; and a newline\nescaped'),
        (4,NULL);
        /*!40000 ALTER TABLE `mdb_rows` ENABLE KEYS */;
        UNLOCK TABLES;

        --
        -- Table structure for table `things`
        --

        DROP TABLE IF EXISTS `things`;
        CREATE TABLE `things` (
          `id` int(11) NOT NULL,
          `label` varchar(50) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        --
        -- Dumping data for table `things`
        --

        LOCK TABLES `things` WRITE;
        /*!40000 ALTER TABLE `things` DISABLE KEYS */;
        INSERT INTO `things` VALUES
        (1,'one'),
        (2,'two');
        /*!40000 ALTER TABLE `things` ENABLE KEYS */;
        UNLOCK TABLES;
        /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;

        -- Dump completed on 2026-11-04 20:14:05

        SQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBackups();
        Sleep::fake();
        $this->root()->exec('DROP DATABASE `' . self::SCRATCH . '`');
        // The live table behind the fixture: the same four fictional rows
        $live = $this->root(self::LIVE);
        $live->exec('CREATE TABLE mdb_rows (id INT PRIMARY KEY, note TEXT)');
        $insert = $live->prepare('INSERT INTO mdb_rows VALUES (?, ?)');
        foreach ([[1, 'LOGIN_SUCCESS (web)'], [2, "a quote ' and a ),( inside"], [3, "semi; colon; and a newline\nescaped"], [4, null]] as $row) {
            $insert->execute($row);
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownBackups();
        parent::tearDown();
    }

    private function fixture(string $sql, string $name = 'smartls.sql.gz'): string
    {
        $path = $this->home . '/' . $name;
        file_put_contents($path, gzencode($sql));

        return $path;
    }

    /** The fixture with its rows taken out, as an empty backup would be */
    private function withoutRows(): string
    {
        return (string) preg_replace('/^INSERT INTO .*?;\n/ms', '', self::MARIADB_DUMP);
    }

    public function test_the_dump_check_counts_mariadbs_rows_one_per_line(): void
    {
        $test = RestoreTest::run(app(MysqlClient::class), self::LIVE, $this->fixture(self::MARIADB_DUMP));

        $this->assertSame(RestoreTest::DUMP_CHECK, $test['mode']);
        $this->assertTrue($test['ok'], implode('; ', $test['mismatches']));
        $this->assertSame([2, 6, 6, []], [$test['tables'], $test['rows'], $test['live_rows'], $test['differences']]);
    }

    public function test_the_import_test_counts_and_imports_mariadbs_rows(): void
    {
        $this->root()->exec('CREATE DATABASE `' . self::SCRATCH . '`');

        $test = RestoreTest::run(app(MysqlClient::class), self::LIVE, $this->fixture(self::MARIADB_DUMP));

        $this->assertSame(RestoreTest::IMPORT, $test['mode']);
        $this->assertTrue($test['ok'], implode('; ', $test['mismatches']));
        $this->assertSame([2, 6], [$test['tables'], $test['rows']]);
        $this->assertSame([], $this->root(self::SCRATCH)->query('SHOW TABLES')->fetchAll());
    }

    public function test_a_dump_without_rows_fails_against_a_live_database_that_has_them_in_both_tests(): void
    {
        $empty = $this->fixture($this->withoutRows(), 'empty.sql.gz');
        $expected = ['the dump holds no rows at all, the live database 6', 'mdb_rows: 4 rows live but none in the dump', 'things: 2 rows live but none in the dump'];

        $check = RestoreTest::run(app(MysqlClient::class), self::LIVE, $empty);
        $this->assertFalse($check['ok']);
        $this->assertSame($expected, $check['mismatches']);
        $this->assertSame('Dump check failed', RestoreTest::label($check));

        // An import of it "works" (two empty tables): the live counts catch it all the same
        $this->root()->exec('CREATE DATABASE `' . self::SCRATCH . '`');
        $import = RestoreTest::run(app(MysqlClient::class), self::LIVE, $empty);
        $this->assertSame([RestoreTest::IMPORT, false, $expected], [$import['mode'], $import['ok'], $import['mismatches']]);
        $this->assertSame('Restore test failed', RestoreTest::label($import));
    }

    public function test_a_table_empty_before_the_dump_and_filled_since_is_no_loss(): void
    {
        $mysql = app(MysqlClient::class);
        $before = $mysql->rowCounts('praxis_test_bk_praxis_core');
        $dump = $this->home . '/praxis.sql.gz';
        Dumper::dump($mysql, 'praxis_test_bk_praxis_core', $dump);
        $this->root('praxis_test_bk_praxis_core')->exec('INSERT INTO empty_table VALUES (1)');

        $test = RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $dump, $before);
        $this->assertTrue($test['ok'], implode('; ', $test['mismatches']));
        $this->assertContains('empty_table: 0 in the dump, 1 live now', $test['differences']);

        // Without the counts from before the dump, an empty table in the dump with rows live is a loss
        $test = RestoreTest::run($mysql, 'praxis_test_bk_praxis_core', $dump);
        $this->assertSame(['empty_table: 1 row live but none in the dump'], $test['mismatches']);
    }

    public function test_both_layouts_count_the_same_for_the_same_rows(): void
    {
        // MySQL's layout of the same dump: every row on its INSERT line
        $mysqlLayout = (string) preg_replace_callback('/^INSERT INTO (`[^`]+`) VALUES\n(.*?);\n/ms',
            fn ($m) => 'INSERT INTO ' . $m[1] . ' VALUES ' . str_replace(",\n", ',', $m[2]) . ";\n", self::MARIADB_DUMP);
        $this->assertStringContainsString("INSERT INTO `things` VALUES (1,'one'),(2,'two');", $mysqlLayout);

        $one = RestoreTest::run(app(MysqlClient::class), self::LIVE, $this->fixture($mysqlLayout, 'mysql.sql.gz'));
        $two = RestoreTest::run(app(MysqlClient::class), self::LIVE, $this->fixture(self::MARIADB_DUMP, 'mariadb.sql.gz'));

        $this->assertSame([true, 6], [$one['ok'], $one['rows']]);
        $this->assertSame([true, 6], [$two['ok'], $two['rows']]);
    }
}
