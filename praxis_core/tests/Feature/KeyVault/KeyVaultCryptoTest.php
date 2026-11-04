<?php

namespace Tests\Feature\KeyVault;

use App\Models\KeyVaultItem;
use App\Support\KeyVault\Envelope;
use App\Support\KeyVault\LockedCopy;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The browser's lock against an independent one (docs/KEY_VAULT.md section 4): what crypto.js locks, PHP's
 * OpenSSL opens with the documented recipe, and the other way round; and a locked copy made by HQ opens with the
 * file's own script. Runs crypto.js on Node (tests/js), which CI installs for the asset build; without Node
 * those tests are skipped, never passed.
 */
class KeyVaultCryptoTest extends TestCase
{
    use KeyVaultFixtures;

    private function node(): string
    {
        $node = (new ExecutableFinder())->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is not installed: run node --test tests/js/key-vault-crypto.test.mjs where it is.');
        }

        return $node;
    }

    public function test_openssl_opens_what_the_browsers_lock_made(): void
    {
        $process = new Process([$this->node(), base_path('tests/js/key-vault-lock.mjs')], base_path(), [
            'KEY_VAULT_SECRET' => self::SECRET,
            'KEY_VAULT_PASSPHRASE' => self::PASSPHRASE,
        ]);
        $process->mustRun();
        $envelope = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        // The server takes it as it is, and the recipe opens it
        $checked = Envelope::fromBrowser($envelope)->toArray();
        $this->assertSame(Envelope::AAD_PREFIX . $envelope['id'], $checked['aad']);
        $this->assertSame(self::SECRET, $this->open($checked, self::PASSPHRASE));
        $this->assertFalse($this->open($checked, strtoupper(self::PASSPHRASE)));
    }

    public function test_the_fixed_vector_in_the_javascript_tests_is_what_openssl_makes(): void
    {
        $envelope = [
            'id' => '6f1c2a9e-3b4d-4c5e-8f70-1a2b3c4d5e6f',
            'kdf' => ['iterations' => 600000, 'salt' => 'AAECAwQFBgcICQoLDA0ODw=='],
            'cipher' => ['iv' => 'oKGio6Slpqeoqaqr'],
            'ciphertext' => '/dCczhmj6lbN4kMicTMA4xTBqwe9WogyeZDTj7S4s48bAwdalir1jIt7OuWy1fiXB9ZZyXe7JhlmB5/P8/l99qiaOSl3xZ9dIKhGYbAniYmDtIbsuUO9MkXjkNN/AFAhKQ==',
        ];
        $this->assertSame("-----BEGIN TEST KEY-----\nTHIS IS A TEST VECTOR, NOT A KEY\n-----END TEST KEY-----\n", $this->open($envelope, 'tidal-orbit-marble-quiet-lantern'));
    }

    public function test_the_javascript_tests_pass_and_a_locked_copy_opens_with_its_own_script(): void
    {
        $node = $this->node();
        $envelope = Envelope::fromBrowser($this->lock());
        $item = new KeyVaultItem(['id' => $envelope->id, 'user_id' => '00000000-0000-4000-8000-000000000000', 'name' => 'Server backup private key (test)'] + $envelope->columns());
        $item->created_at = $item->updated_at = now();

        $base = tempnam(sys_get_temp_dir(), 'kvcopy');
        $file = $base . '.html';
        file_put_contents($file, LockedCopy::html($item));
        try {
            $process = new Process([$node, '--test', base_path('tests/js/key-vault-crypto.test.mjs')], base_path(), [
                'KEY_VAULT_LOCKED_COPY' => $file,
                'KEY_VAULT_SECRET' => self::SECRET,
                'KEY_VAULT_PASSPHRASE' => self::PASSPHRASE,
            ], timeout: 180);
            $process->run();
        } finally {
            @unlink($file);
            @unlink($base);
        }

        $out = $process->getOutput() . $process->getErrorOutput();
        $this->assertSame(0, $process->getExitCode(), $out);
        $this->assertMatchesRegularExpression('/ℹ fail 0/u', $out);
        $this->assertMatchesRegularExpression('/ℹ skipped 0/u', $out, 'the locked copy test ran');
        $this->assertMatchesRegularExpression('/ℹ pass (\d+)/u', $out);
    }
}
