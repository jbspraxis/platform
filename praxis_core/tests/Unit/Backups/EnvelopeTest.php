<?php

namespace Tests\Unit\Backups;

use App\Support\Backups\BackupFailed;
use App\Support\Backups\Envelope;
use App\Support\Backups\RestoreTest;
use PHPUnit\Framework\TestCase;

/**
 * The backups' encryption (docs/BACKUPS.md 7): a file opens only with the private key, any change to it is caught
 * before a byte is trusted, and its 1 MB chunks make one continuous AES-256-CTR stream, as `openssl enc` reads it.
 * Test keys are made here and thrown away; no real key or data.
 */
class EnvelopeTest extends TestCase
{
    private static array $keys = [];

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/envelope-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** @return array{0: string, 1: string} private and public PEM of a 3072-bit test key */
    private static function key(int $n = 0): array
    {
        if (! isset(self::$keys[$n])) {
            $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$keys[$n] = [$private, openssl_pkey_get_details($key)['key']];
        }

        return self::$keys[$n];
    }

    public function test_a_file_opens_with_the_private_key_and_matches_byte_for_byte(): void
    {
        [$private, $public] = self::key();
        // Over 2 MB, so it spans three chunks, the last one short
        file_put_contents("{$this->dir}/plain.sql.gz", random_bytes(2 * 1048576 + 12345));
        $made = Envelope::encrypt("{$this->dir}/plain.sql.gz", "{$this->dir}/plain.sql.gz.enc", $public, ['site' => 'praxis', 'kind' => 'db', 'name' => 'plain.sql.gz', 'date' => '2026-11-05']);

        $this->assertSame(md5_file("{$this->dir}/plain.sql.gz.enc"), $made['md5']);
        $this->assertSame(filesize("{$this->dir}/plain.sql.gz.enc"), $made['bytes']);
        $header = Envelope::header("{$this->dir}/plain.sql.gz.enc");
        $this->assertSame(['praxis', 'db', 2 * 1048576 + 12345, Envelope::fingerprint($public)], [$header['site'], $header['kind'], $header['size'], $header['key']]);
        $this->assertStringNotContainsString(substr((string) file_get_contents("{$this->dir}/plain.sql.gz"), 5000, 64), (string) file_get_contents("{$this->dir}/plain.sql.gz.enc"));

        Envelope::decrypt("{$this->dir}/plain.sql.gz.enc", "{$this->dir}/opened.sql.gz", $private);
        $this->assertFileEquals("{$this->dir}/plain.sql.gz", "{$this->dir}/opened.sql.gz");
    }

    public function test_chunks_continue_one_counter_even_when_it_carries_over(): void
    {
        $key = random_bytes(32);
        $data = random_bytes(3 * 1048576 + 77);
        // Low bytes near the top, so the counter carries past 32 and 64 bits inside the data
        $counter = hex2bin('00112233445566778ffffffffffffff0');
        $chunked = '';
        $c = $counter;
        foreach (str_split($data, 1048576) as $chunk) {
            $chunked .= openssl_encrypt($chunk, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $c);
            $c = Envelope::addBlocks($c, intdiv(strlen($chunk), 16));
        }

        $this->assertSame(openssl_encrypt($data, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $counter), $chunked);
        $this->assertSame('00112233445566780000000000000000', bin2hex(Envelope::addBlocks(hex2bin('00112233445566778fffffffffffffff'), 0x7000000000000001)));
    }

    public function test_a_changed_or_cut_file_and_the_wrong_key_are_refused_and_nothing_is_written(): void
    {
        [$private, $public] = self::key();
        [$otherPrivate] = self::key(1);
        file_put_contents("{$this->dir}/plain.bin", random_bytes(300000));
        Envelope::encrypt("{$this->dir}/plain.bin", "{$this->dir}/a.enc", $public, ['site' => 'lpc', 'kind' => 'files', 'name' => 'plain.bin', 'date' => '2026-11-08']);
        $bytes = (string) file_get_contents("{$this->dir}/a.enc");

        $flipped = $bytes;
        $flipped[200000] = chr(ord($flipped[200000]) ^ 1);
        file_put_contents("{$this->dir}/flipped.enc", $flipped);
        file_put_contents("{$this->dir}/cut.enc", substr($bytes, 0, -100));
        $header = $bytes;
        $header[30] = $header[30] === 'a' ? 'b' : 'a';
        file_put_contents("{$this->dir}/header.enc", $header);

        foreach (['flipped.enc' => $private, 'cut.enc' => $private, 'header.enc' => $private, 'a.enc' => $otherPrivate] as $file => $key) {
            try {
                Envelope::decrypt("{$this->dir}/{$file}", "{$this->dir}/out-{$file}", $key);
                $this->fail("{$file} opened");
            } catch (BackupFailed $e) {
                $this->assertSame('decrypt', $e->stage);
                $this->assertFileDoesNotExist("{$this->dir}/out-{$file}");
            }
        }
    }

    public function test_a_small_or_missing_key_is_refused(): void
    {
        $small = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        file_put_contents("{$this->dir}/p", 'x');
        foreach ([openssl_pkey_get_details($small)['key'], '', 'not a key'] as $pem) {
            try {
                Envelope::encrypt("{$this->dir}/p", "{$this->dir}/p.enc", $pem, ['site' => 'x', 'kind' => 'db', 'name' => 'p']);
                $this->fail('encrypted with an unfit key');
            } catch (BackupFailed $e) {
                $this->assertSame('encrypt', $e->stage);
            }
        }
    }

    public function test_the_restore_test_counts_rows_without_being_fooled_by_strings(): void
    {
        $this->assertSame(3, RestoreTest::tuples("INSERT INTO `t` VALUES (1,'a'),(2,'b (c), d'),(3,'O\\'Brien \\\\'),", 22));
        $this->assertSame(2, RestoreTest::tuples("(1,'),(',NULL),(2,_binary 'x\\\\')"));
        $this->assertSame(1, RestoreTest::tuples("(1,'it''s')"));
        $this->assertSame(0, RestoreTest::tuples(''));
    }
}
