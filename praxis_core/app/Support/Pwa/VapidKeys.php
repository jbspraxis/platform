<?php

namespace App\Support\Pwa;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The VAPID key pair (RFC 8292) that signs every push. Without keys in .env, HQ makes a P-256 pair on
 * first use and keeps it in one pwa_settings row: the public key as base64url (browsers need it), the
 * private key as PEM encrypted with APP_KEY. If APP_KEY changes, the old key can't be read: a new pair
 * is made, push services refuse the old devices (they are removed) and browsers subscribe again with
 * the new key on their next visit.
 */
class VapidKeys
{
    private const ROW = 'vapid';

    private static ?array $pair = null;

    /** base64url of the 65-byte uncompressed public point (the applicationServerKey). */
    public static function publicKey(): string
    {
        return self::pair()['public'];
    }

    public static function privateKeyPem(): string
    {
        return self::pair()['private'];
    }

    public static function subject(): string
    {
        return (string) config('pwa.vapid.subject');
    }

    /** ['public' => base64url, 'private' => PEM] */
    public static function pair(): array
    {
        if (self::$pair !== null) {
            return self::$pair;
        }

        $public = config('pwa.vapid.public_key');
        $private = config('pwa.vapid.private_key');
        if ($public && $private) {
            return self::$pair = ['public' => $public, 'private' => self::toPem($private, $public)];
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $stored = DB::table('pwa_settings')->where('key', self::ROW)->value('value');
            if ($stored !== null) {
                $data = json_decode($stored, true);
                try {
                    return self::$pair = ['public' => $data['public'], 'private' => Crypt::decryptString($data['private'])];
                } catch (DecryptException|\TypeError $e) {
                    // Written with another APP_KEY (or damaged): replace it, unless another process already did
                    DB::table('pwa_settings')->where('key', self::ROW)->where('value', $stored)->delete();
                }
            }

            $new = self::generate();
            DB::table('pwa_settings')->insertOrIgnore([
                'key' => self::ROW,
                'value' => json_encode(['public' => $new['public'], 'private' => Crypt::encryptString($new['private'])]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Loop: read back whichever pair was stored first (another request may have won the race)
        }

        throw new \RuntimeException('The VAPID keys could not be stored in pwa_settings.');
    }

    /** A new P-256 key pair: ['public' => base64url uncompressed point, 'private' => PEM]. */
    public static function generate(): array
    {
        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'] + self::opensslConfig();
        $key = openssl_pkey_new($options);
        if ($key === false || ! openssl_pkey_export($key, $pem, null, self::opensslConfig())) {
            throw new \RuntimeException('OpenSSL could not make a P-256 key: ' . (openssl_error_string() ?: 'unknown error'));
        }

        $ec = openssl_pkey_get_details($key)['ec'];

        return ['public' => self::b64u("\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)), 'private' => $pem];
    }

    /**
     * PHP on Windows (local testing) often can't find openssl.cnf; point it at the copy PHP ships with.
     * Linux (the server) needs nothing.
     */
    public static function opensslConfig(): array
    {
        if (PHP_OS_FAMILY !== 'Windows' || getenv('OPENSSL_CONF')) {
            return [];
        }
        $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';

        return is_file($cnf) ? ['config' => $cnf] : [];
    }

    public static function b64u(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $text): string
    {
        $decoded = base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4), true);

        return $decoded === false ? '' : $decoded;
    }

    /** A private key from .env: PEM as is, or the base64url 32-byte secret (as other tools print it) made into PEM. */
    private static function toPem(string $private, string $public): string
    {
        $private = str_replace('\n', "\n", trim($private));
        if (str_contains($private, '-----BEGIN')) {
            return $private;
        }

        $d = str_pad(self::b64uDecode($private), 32, "\0", STR_PAD_LEFT);
        $point = self::b64uDecode($public);
        // SEC 1 ECPrivateKey: version 1, the secret, the P-256 curve name, the public point
        $der = "\x30\x77\x02\x01\x01\x04\x20" . $d . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" . "\xa1\x44\x03\x42\x00" . $point;

        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    /** Forget the loaded pair (tests). */
    public static function flush(): void
    {
        self::$pair = null;
    }
}
