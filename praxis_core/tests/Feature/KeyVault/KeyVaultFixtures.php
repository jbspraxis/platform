<?php

namespace Tests\Feature\KeyVault;

use App\Models\User;
use App\Support\Hosts;
use App\Support\KeyVault\Envelope;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * What the key vault tests need (docs/KEY_VAULT.md): an owner to act as, and envelopes made the way the browser
 * makes them (PBKDF2-HMAC-SHA-256, 600,000 iterations, then AES-256-GCM bound to the item id), here with PHP's
 * OpenSSL: the recipe anyone can use without HQ. Nothing here is a real key or a real passphrase.
 */
trait KeyVaultFixtures
{
    /** Every fixture account shares one password, so a test can type it again for the fresh sign-in check */
    protected const PASSWORD = 'key-vault-fixture-password';

    protected const PASSPHRASE = 'tidal-orbit-marble-quiet-lantern';

    protected const SECRET = "-----BEGIN TEST KEY-----\nTHIS IS A KEY VAULT TEST, NOT A KEY\nZmFrZSBrZXkgbWF0ZXJpYWwgZm9yIHRlc3Rz\n-----END TEST KEY-----\n";

    /** PBKDF2 at 600,000 iterations takes a moment: one derived key per passphrase and salt for the whole run */
    private static array $derived = [];

    protected function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    protected function owner(string $first = 'Ada'): User
    {
        $user = $this->staff('owner', ['owner']);
        $user->forceFill(['first_name' => $first, 'last_name' => 'Test', 'password' => Hash::make(self::PASSWORD)])->save();
        SignIn::markFresh();

        return $user->fresh();
    }

    /** An envelope as crypto.js makes it */
    protected function lock(string $secret = self::SECRET, string $passphrase = self::PASSPHRASE, ?string $id = null, int $iterations = Envelope::MIN_ITERATIONS): array
    {
        $id ??= (string) Str::uuid();
        $salt = hash('sha256', $passphrase . '|salt', true);
        $salt = substr($salt, 0, 16);
        $iv = random_bytes(12);
        $key = self::$derived[$passphrase . '|' . $iterations] ??= hash_pbkdf2('sha256', $passphrase, $salt, $iterations, 32, true);
        $sealed = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, Envelope::AAD_PREFIX . $id, 16);

        return [
            'format' => Envelope::FORMAT,
            'v' => Envelope::VERSION,
            'id' => $id,
            'kdf' => ['name' => 'PBKDF2', 'hash' => 'SHA-256', 'iterations' => $iterations, 'salt' => base64_encode($salt)],
            'cipher' => ['name' => 'AES-GCM', 'length' => 256, 'tagLength' => 128, 'iv' => base64_encode($iv)],
            'ciphertext' => base64_encode($sealed . $tag),
        ];
    }

    /** Opens an envelope with OpenSSL; false when the passphrase (or anything in it) is wrong */
    protected function open(array $envelope, string $passphrase): string|false
    {
        if (class_exists(\Normalizer::class)) {
            $passphrase = \Normalizer::normalize($passphrase, \Normalizer::FORM_C);
        }
        $key = hash_pbkdf2('sha256', $passphrase, base64_decode($envelope['kdf']['salt']), $envelope['kdf']['iterations'], 32, true);
        $sealed = base64_decode($envelope['ciphertext']);

        return openssl_decrypt(substr($sealed, 0, -16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($envelope['cipher']['iv']),
            substr($sealed, -16), $envelope['aad'] ?? Envelope::AAD_PREFIX . $envelope['id']);
    }
}
