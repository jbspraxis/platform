<?php

namespace App\Support\Pwa;

use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Sends Web Push messages with OpenSSL only (the server has no Composer, so no packages):
 * RFC 8291 payload encryption (aes128gcm, RFC 8188, one record) and RFC 8292 VAPID (an ES256 JWT
 * per push service, signed with the key pair in VapidKeys). Owner: the "push" part (docs/PWA.md).
 */
class WebPush
{
    // RFC 8188 record size. One record carries the whole message.
    public const RECORD_SIZE = 4096;

    // Push services take at most 4,096 bytes of body: the 86-byte header, the 16-byte tag and the
    // padding delimiter leave this much for the message itself
    public const MAX_PAYLOAD = 3993;

    // Answers meaning the device will never take a message again: unsubscribed or expired (404, 410),
    // or keys that no longer match (400, 403)
    public const GONE = [400, 403, 404, 410];

    // DER of a SubjectPublicKeyInfo for an uncompressed P-256 point, up to the point itself
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    // A signed JWT is reused for the same push service for an hour; each one is valid for 12 hours
    private const JWT_REUSE = 3600;
    private const JWT_LIFETIME = 43200;

    private static array $jwts = [];

    /**
     * Sends messages in one pool, at most config('pwa.push.concurrency') at a time. Each message:
     * ['endpoint' => url, 'p256dh' => base64url, 'auth' => base64url, 'payload' => string,
     *  'urgency' => 'normal'|'high' (optional), 'ttl' => seconds (optional)].
     * Returns the same keys, each ['status' => HTTP status, or 0 without an answer, 'ok' => bool,
     * 'gone' => bool (forget the device), 'error' => ?string]. One bad device never stops the others;
     * a problem on this side (keys, OpenSSL) throws, because no device is to blame for it.
     */
    public static function send(array $messages): array
    {
        $results = [];
        $requests = [];
        foreach ($messages as $key => $message) {
            $payload = (string) $message['payload'];
            if (strlen($payload) > self::MAX_PAYLOAD) {
                // 413, as a push service would answer: the message is at fault, not the device
                $results[$key] = self::failure(413, false, 'The message is too long for a push (' . strlen($payload) . ' bytes).');
                continue;
            }
            $audience = self::audience((string) $message['endpoint']);
            if ($audience === null) {
                // Checked here, one message at a time: an address the HTTP client can't parse would
                // otherwise make the whole pool throw, and no other device would get its message
                $results[$key] = self::failure(0, true, 'The device address is not a valid https address.');
                continue;
            }
            try {
                $body = self::encrypt($payload, VapidKeys::b64uDecode((string) $message['p256dh']), VapidKeys::b64uDecode((string) $message['auth']));
            } catch (\InvalidArgumentException $e) {
                // Keys that can never work: the browser has to subscribe again
                $results[$key] = self::failure(0, true, $e->getMessage());
                continue;
            }
            $requests[(string) $key] = [
                'url' => (string) $message['endpoint'],
                'body' => $body,
                'headers' => [
                    'TTL' => (string) max(0, (int) ($message['ttl'] ?? config('pwa.push.ttl'))),
                    'Urgency' => ($message['urgency'] ?? 'normal') === 'high' ? 'high' : 'normal',
                    'Content-Encoding' => 'aes128gcm',
                    'Authorization' => self::authorization($audience),
                ],
            ];
        }

        if ($requests) {
            $timeout = max(1, (int) config('pwa.push.timeout'));
            $responses = Http::pool(function (Pool $pool) use ($requests, $timeout) {
                foreach ($requests as $key => $request) {
                    $pool->as((string) $key)
                        ->timeout($timeout)
                        ->connectTimeout(min(5, $timeout))
                        // Push services answer 201; a redirect could lead anywhere, even off the allowed hosts
                        ->withoutRedirecting()
                        ->withHeaders($request['headers'])
                        ->withBody($request['body'], 'application/octet-stream')
                        ->post($request['url']);
                }
            }, max(1, (int) config('pwa.push.concurrency')));

            foreach (array_keys($requests) as $key) {
                $results[$key] = self::result($responses[$key] ?? null);
            }
        }

        // In the order the messages came
        return array_replace(array_intersect_key($messages, $results), $results);
    }

    /**
     * The aes128gcm body for one message (RFC 8291 section 3, RFC 8188): salt, record size, the
     * application server's public key, then the encrypted payload. $userPublicKey (65 bytes) and
     * $userAuth (16 bytes) are raw bytes. $salt and $serverKey are for tests only (the RFC example);
     * otherwise every message gets a fresh salt and a fresh key pair.
     */
    public static function encrypt(string $payload, string $userPublicKey, string $userAuth, ?string $salt = null, ?\OpenSSLAsymmetricKey $serverKey = null): string
    {
        if (strlen($userPublicKey) !== 65 || $userPublicKey[0] !== "\x04") {
            throw new \InvalidArgumentException('The device key is not an uncompressed P-256 point.');
        }
        if (strlen($userAuth) !== 16) {
            throw new \InvalidArgumentException('The device auth secret is not 16 bytes.');
        }
        if (strlen($payload) > self::MAX_PAYLOAD) {
            throw new \LengthException('The message is too long for a push.');
        }
        if ($salt !== null && strlen($salt) !== 16) {
            throw new \LengthException('The salt must be 16 bytes.');
        }
        self::clearOpensslErrors();

        // OpenSSL calls are silenced (@) where bad input only means "false": the reason is thrown instead
        $peer = @openssl_pkey_get_public(self::publicKeyPem($userPublicKey));
        if ($peer === false) {
            throw new \InvalidArgumentException('The device key is not a point on the P-256 curve.');
        }

        $serverKey ??= self::newKey();
        $serverPublic = self::publicPoint($serverKey);
        $secret = @openssl_pkey_derive($peer, $serverKey);
        if ($secret === false || strlen($secret) !== 32) {
            throw new \RuntimeException('OpenSSL could not derive the shared secret: ' . (openssl_error_string() ?: 'unknown error'));
        }

        // RFC 8291 section 3.3: mix the auth secret into the shared secret, bound to both public keys
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\x00" . $userPublicKey . $serverPublic, $userAuth);
        // RFC 8188 section 2.2 and 2.3: the content encryption key and the nonce
        $salt ??= random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // One record: the payload, then 0x02, the delimiter that marks the last record (no padding)
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('OpenSSL could not encrypt the message: ' . (openssl_error_string() ?: 'unknown error'));
        }

        // Header: salt, record size, key id length, key id (the application server's public key)
        return $salt . pack('N', self::RECORD_SIZE) . chr(65) . $serverPublic . $cipher . $tag;
    }

    /** "vapid t=<JWT>, k=<public key>" for one push service (RFC 8292 section 3). */
    public static function authorization(string $audience): string
    {
        $public = VapidKeys::publicKey();
        $cacheKey = $audience . '|' . $public;
        $now = time();
        if (! isset(self::$jwts[$cacheKey]) || self::$jwts[$cacheKey]['at'] <= $now - self::JWT_REUSE) {
            self::$jwts[$cacheKey] = ['at' => $now, 'jwt' => self::jwt($audience, VapidKeys::privateKeyPem(), self::subject(), $now + self::JWT_LIFETIME)];
        }

        return 'vapid t=' . self::$jwts[$cacheKey]['jwt'] . ', k=' . $public;
    }

    /** A VAPID JWT (RFC 8292 section 2): ES256 over {aud, exp, sub}. */
    public static function jwt(string $audience, string $privateKeyPem, string $subject, int $expires): string
    {
        $input = VapidKeys::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            . '.' . VapidKeys::b64u(json_encode(['aud' => $audience, 'exp' => $expires, 'sub' => $subject], JSON_UNESCAPED_SLASHES));

        self::clearOpensslErrors();
        if (! @openssl_sign($input, $der, $privateKeyPem, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('OpenSSL could not sign the VAPID token: ' . (openssl_error_string() ?: 'unknown error'));
        }

        return $input . '.' . VapidKeys::b64u(self::derToRaw($der));
    }

    /**
     * scheme://host[:port] of a push endpoint (the JWT audience), or null if it isn't an https address.
     * Read the way the HTTP client reads it (Guzzle's Uri), so the token names the host the request goes
     * to; parse_url() accepts addresses the client refuses, such as "host:+443".
     */
    public static function audience(string $endpoint): ?string
    {
        // Laravel's HTTP client expands braces as a URI template, so the request would go somewhere else
        if (strpbrk($endpoint, '{}') !== false) {
            return null;
        }
        try {
            $uri = new Uri($endpoint);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
        if ($uri->getScheme() !== 'https' || $uri->getHost() === '') {
            return null;
        }

        // getPort() is null for 443, the https default
        return 'https://' . $uri->getHost() . ($uri->getPort() !== null ? ':' . $uri->getPort() : '');
    }

    /** Whether $point (raw bytes) is a key a browser could have made: an uncompressed point on P-256. */
    public static function isDeviceKey(string $point): bool
    {
        if (strlen($point) !== 65 || $point[0] !== "\x04") {
            return false;
        }
        $valid = @openssl_pkey_get_public(self::publicKeyPem($point)) !== false;
        self::clearOpensslErrors();

        return $valid;
    }

    /** OpenSSL's ECDSA signature (DER, a SEQUENCE of two INTEGERs) as the 64-byte R || S that JWS uses. */
    public static function derToRaw(string $der): string
    {
        $offset = 2;
        if (strlen($der) < 8 || ord($der[0]) !== 0x30 || (ord($der[1]) & 0x80) !== 0) {
            throw new \RuntimeException('OpenSSL returned a signature in an unexpected format.');
        }
        $raw = '';
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$offset] ?? "\0") !== 0x02) {
                throw new \RuntimeException('OpenSSL returned a signature in an unexpected format.');
            }
            $length = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\x00");
            if (strlen($integer) > 32) {
                throw new \RuntimeException('OpenSSL returned a signature in an unexpected format.');
            }
            $raw .= str_pad($integer, 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $raw;
    }

    /** Forget the signed JWTs (tests). */
    public static function flush(): void
    {
        self::$jwts = [];
    }

    private static function result($response): array
    {
        if ($response instanceof Response) {
            $status = $response->status();
            if ($status >= 200 && $status < 300) {
                return ['status' => $status, 'ok' => true, 'gone' => false, 'error' => null];
            }

            return self::failure($status, in_array($status, self::GONE, true), mb_substr(trim(strip_tags($response->body())), 0, 200) ?: "HTTP {$status}");
        }

        // No answer: a timeout, DNS or TLS failure (ConnectionException), or a refused request in tests
        return self::failure(0, false, $response instanceof \Throwable ? $response->getMessage() : 'No answer from the push service.');
    }

    private static function failure(int $status, bool $gone, string $error): array
    {
        // A device's address is private to its owner: never in messages or logs
        return ['status' => $status, 'ok' => false, 'gone' => $gone, 'error' => mb_substr(preg_replace('~https?://\S+~i', '(push service address)', $error), 0, 300)];
    }

    /** Push services want a mailto: or https: subject; a bare email address is made into a mailto: one. */
    private static function subject(): string
    {
        $subject = trim(VapidKeys::subject());

        return str_contains($subject, '@') && ! preg_match('/^(mailto|https):/i', $subject) ? 'mailto:' . $subject : $subject;
    }

    private static function newKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'] + VapidKeys::opensslConfig());
        if ($key === false) {
            throw new \RuntimeException('OpenSSL could not make a P-256 key: ' . (openssl_error_string() ?: 'unknown error'));
        }

        return $key;
    }

    /** The 65-byte uncompressed public point of a P-256 key. */
    private static function publicPoint(\OpenSSLAsymmetricKey $key): string
    {
        $ec = openssl_pkey_get_details($key)['ec'] ?? null;
        if (! isset($ec['x'], $ec['y'])) {
            throw new \RuntimeException('OpenSSL could not read the P-256 public key.');
        }

        return "\x04" . str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT);
    }

    private static function publicKeyPem(string $point): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX) . $point), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    // OpenSSL keeps old errors in a queue; empty it so a failure reports its own reason
    private static function clearOpensslErrors(): void
    {
        while (openssl_error_string() !== false) {
        }
    }
}
