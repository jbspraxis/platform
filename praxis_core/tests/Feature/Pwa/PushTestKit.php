<?php

namespace Tests\Feature\Pwa;

use App\Models\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Helpers for the push tests: what a browser does (a device key pair, decrypting a push) and
 * HQ fixtures (staff, devices, bell notifications). The crypto helpers need no database.
 */
trait PushTestKit
{
    public static function b64u(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $text): string
    {
        $text = preg_replace('/\s+/', '', $text);

        return base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4), true);
    }

    /** A browser's push keys: ['private' => key, 'public' => 65-byte point, 'auth' => 16 bytes]. */
    public static function deviceKeys(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'] + \App\Support\Pwa\VapidKeys::opensslConfig());
        $ec = openssl_pkey_get_details($key)['ec'];

        return [
            'private' => $key,
            'public' => "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT),
            'auth' => random_bytes(16),
        ];
    }

    /** A P-256 private key from its raw 32-byte secret and 65-byte public point (SEC 1 ECPrivateKey). */
    public static function keyFromRaw(string $secret, string $point): \OpenSSLAsymmetricKey
    {
        $der = "\x30\x77\x02\x01\x01\x04\x20" . $secret . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\xa1\x44\x03\x42\x00" . $point;

        return openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n");
    }

    public static function publicPem(string $point): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** What the browser does with an aes128gcm push body (RFC 8291, RFC 8188), written independently of WebPush. */
    public static function decryptPush(string $body, \OpenSSLAsymmetricKey $devicePrivate, string $devicePublic, string $auth): string
    {
        $salt = substr($body, 0, 16);
        $recordSize = unpack('N', substr($body, 16, 4))[1];
        $idLength = ord($body[20]);
        $serverPublic = substr($body, 21, $idLength);
        $record = substr($body, 21 + $idLength);
        if ($recordSize < 18 || strlen($record) > $recordSize || $idLength !== 65) {
            throw new \RuntimeException('Not a single aes128gcm record with a P-256 key id.');
        }

        $secret = openssl_pkey_derive(openssl_pkey_get_public(self::publicPem($serverPublic)), $devicePrivate);
        $prkKey = hash_hmac('sha256', $secret, $auth, true);
        $ikm = substr(hash_hmac('sha256', "WebPush: info\x00" . $devicePublic . $serverPublic . "\x01", $prkKey, true), 0, 32);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        $plain = openssl_decrypt(substr($record, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($record, -16));
        if ($plain === false) {
            throw new \RuntimeException('The push body did not decrypt (wrong key or damaged).');
        }
        $plain = rtrim($plain, "\x00");
        if (substr($plain, -1) !== "\x02") {
            throw new \RuntimeException('The last record must end with the 0x02 delimiter.');
        }

        return substr($plain, 0, -1);
    }

    /** A JWS signature (R || S) as the DER that openssl_verify expects. */
    public static function rawToDer(string $raw): string
    {
        $integer = function (string $bytes) {
            $bytes = ltrim($bytes, "\x00");
            if ($bytes === '' || ord($bytes[0]) & 0x80) {
                $bytes = "\x00" . $bytes;
            }

            return "\x02" . chr(strlen($bytes)) . $bytes;
        };
        $sequence = $integer(substr($raw, 0, 32)) . $integer(substr($raw, 32, 32));

        return "\x30" . chr(strlen($sequence)) . $sequence;
    }

    // ---------------------------------------------------------------- HQ fixtures (database)

    protected function staff(array $attributes = [], ?string $role = null): User
    {
        $user = User::forceCreate($attributes + [
            'organization_id' => CoreSeeder::INTERNAL_ORG_ID,
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'email' => Str::lower(Str::random(10)) . '@jbspraxis.test',
            'password' => Str::random(24),
            'status' => 'Active',
        ]);
        if ($role !== null) {
            DB::table('staff_roles')->insert([
                'user_id' => $user->id,
                'role_id' => DB::table('roles')->where('audience', 'staff')->where('slug', $role)->value('id'),
            ]);
        }

        return $user;
    }

    /** A device with real keys: ['id', 'endpoint', 'private', 'public', 'auth']. */
    protected function device(User $user, string $endpoint = 'https://fcm.googleapis.com/fcm/send/device-1', array $attributes = []): array
    {
        $keys = self::deviceKeys();
        $id = DB::table('push_subscriptions')->insertGetId($attributes + [
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => self::b64u($keys['public']),
            'auth_token' => self::b64u($keys['auth']),
            'content_encoding' => 'aes128gcm',
            'device' => 'Test device',
            'standalone' => 1,
            'last_seen_at' => now(),
            'failures' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $keys + ['id' => $id, 'endpoint' => $endpoint];
    }

    protected function notification(User $user, array $attributes = []): string
    {
        $id = $attributes['id'] ?? (string) Str::uuid();
        DB::table('notifications')->insert($attributes + [
            'id' => $id,
            'user_id' => $user->id,
            'title' => 'Cash request to approve',
            'message' => 'Cash request CR-1042 needs approval. Review it in Cash request approvals.',
            'link_url' => '/finance/manager',
            'type' => 'Financial',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        return $id;
    }

    /** The decrypted payloads sent to one device, oldest first. */
    protected function pushedTo(array $device): array
    {
        return \Illuminate\Support\Facades\Http::recorded(fn ($request) => $request->url() === $device['endpoint'])
            ->map(fn ($pair) => json_decode(self::decryptPush($pair[0]->body(), $device['private'], $device['public'], $device['auth']), true))
            ->values()->all();
    }
}
