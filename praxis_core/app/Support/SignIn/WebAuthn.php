<?php

namespace App\Support\SignIn;

use RuntimeException;

/**
 * Passkey ceremonies (W3C WebAuthn level 2), built and checked in PHP with OpenSSL: the server has no Composer
 * (docs/PWA.md, the same reason WebPush is written here). No database: App\Support\SignIn\Passkeys keeps the
 * credentials. The decisions, in the order a reviewer should check them (docs/SIGN_IN.md section 3):
 *
 *  1. The fingerprint, face or screen lock is required, at creation and at every use (userVerification
 *     "required", and refused when the UV flag is missing). A passkey stands in for the password, so it has to be
 *     two factors on its own: the device, and the person unlocking it.
 *  2. The relying party ID is the host itself (hq.jbspraxis.com, portal.jbspraxis.com, from config/app.php
 *     'domains'), and the origin is checked against it, never taken from the request. A passkey made on HQ works
 *     only on HQ, and a portal one only on the portal.
 *  3. Passkeys are discoverable (residentKey "required"), so signing in needs nothing typed, and options for
 *     signing in name no credentials: they reveal nothing about whether an email has a passkey.
 *  4. The same device cannot add a second passkey for the same person: their passkeys are excluded at creation.
 *  5. The signature counter must go up when the authenticator keeps one (a counter that went back is a cloned
 *     key). Passkeys synced by a password manager report 0 every time, and that is allowed.
 *  6. Attestation is not asked for ("none"): which make of authenticator someone uses is not checked.
 */
final class WebAuthn
{
    /** How long the browser waits for the fingerprint or face, in milliseconds */
    public const TIMEOUT_MS = 90_000;

    /** ES256, then RS256 (Windows Hello) */
    public const ES256 = -7;
    public const RS256 = -257;

    private const FLAG_UP = 0x01;
    private const FLAG_UV = 0x04;
    private const FLAG_BS = 0x10;
    private const FLAG_AT = 0x40;

    public static function challenge(): string
    {
        return self::b64(random_bytes(32));
    }

    /**
     * Options for navigator.credentials.create() (PublicKeyCredentialCreationOptionsJSON).
     *
     * @param  array<int, array{credential_id: string, transports: ?array}>  $exclude  the person's passkeys
     */
    public static function creationOptions(string $challenge, string $rpId, string $rpName, string $userHandle, string $userName, string $displayName, array $exclude): array
    {
        return [
            'challenge' => $challenge,
            'rp' => ['id' => $rpId, 'name' => $rpName],
            'user' => ['id' => self::b64($userHandle), 'name' => $userName, 'displayName' => $displayName !== '' ? $displayName : $userName],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => self::ES256], ['type' => 'public-key', 'alg' => self::RS256]],
            'timeout' => self::TIMEOUT_MS,
            'attestation' => 'none',
            'excludeCredentials' => array_map(fn ($p) => array_filter([
                'type' => 'public-key', 'id' => $p['credential_id'], 'transports' => $p['transports'] ?: null,
            ]), array_values($exclude)),
            'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'],
        ];
    }

    /** Options for navigator.credentials.get() (PublicKeyCredentialRequestOptionsJSON); no credentials named. */
    public static function requestOptions(string $challenge, string $rpId): array
    {
        return [
            'challenge' => $challenge,
            'rpId' => $rpId,
            'timeout' => self::TIMEOUT_MS,
            'userVerification' => 'required',
            'allowCredentials' => [],
        ];
    }

    /**
     * Checks the browser's answer to creationOptions().
     *
     * @return array{credential_id: string, public_key: string, algorithm: int, sign_count: int, transports: array, backed_up: bool}
     */
    public static function checkCreation(array $credential, string $challenge, string $rpId, string $origin): array
    {
        $response = self::responseOf($credential);
        self::checkClientData($response['clientDataJSON'] ?? null, 'webauthn.create', $challenge, $origin);

        $attestation = Cbor::decode(self::unb64($response['attestationObject'] ?? null));
        if (! is_array($attestation) || ! is_string($attestation['authData'] ?? null)) {
            throw new RuntimeException('attestation object without authenticator data');
        }
        $auth = self::authData($attestation['authData'], $rpId);
        if (! ($auth['flags'] & self::FLAG_AT) || $auth['credential_id'] === null) {
            throw new RuntimeException('no credential in the authenticator data');
        }
        $id = self::b64($auth['credential_id']);
        if (! hash_equals($id, self::credentialIdOf($credential) ?? '')) {
            throw new RuntimeException('credential id differs from the authenticator data');
        }
        [$pem, $alg] = self::coseToPem($auth['cose_key']);

        $transports = array_values(array_filter((array) ($response['transports'] ?? []), fn ($t) => is_string($t) && preg_match('/^[a-z-]{1,20}$/', $t)));

        return [
            'credential_id' => $id,
            'public_key' => $pem,
            'algorithm' => $alg,
            'sign_count' => $auth['sign_count'],
            'transports' => array_slice($transports, 0, 6),
            'backed_up' => (bool) ($auth['flags'] & self::FLAG_BS),
        ];
    }

    /**
     * Checks the browser's answer to requestOptions() against a stored passkey.
     *
     * @return int the new signature count to keep
     */
    public static function checkAssertion(array $credential, string $challenge, string $rpId, string $origin, string $publicKeyPem, int $algorithm, int $storedCount, ?string $userHandle = null): int
    {
        $response = self::responseOf($credential);
        $clientData = self::checkClientData($response['clientDataJSON'] ?? null, 'webauthn.get', $challenge, $origin);
        $authData = self::unb64($response['authenticatorData'] ?? null);
        $auth = self::authData($authData, $rpId);

        if ($userHandle !== null && isset($response['userHandle']) && $response['userHandle'] !== null && $response['userHandle'] !== ''
            && ! hash_equals(self::b64($userHandle), (string) $response['userHandle'])) {
            throw new RuntimeException('user handle differs from the passkey owner');
        }

        $signature = self::unb64($response['signature'] ?? null);
        $signed = $authData . hash('sha256', $clientData, true);
        if (! in_array($algorithm, [self::ES256, self::RS256], true)) {
            throw new RuntimeException('unsupported algorithm');
        }
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false || openssl_verify($signed, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('signature does not verify');
        }

        $count = $auth['sign_count'];
        if (($count > 0 || $storedCount > 0) && $count <= $storedCount) {
            throw new RuntimeException("signature counter went back ({$count} after {$storedCount}): a cloned key?");
        }

        return $count;
    }

    /** The credential id a browser answer names (base64url), or null. */
    public static function credentialIdOf(array $credential): ?string
    {
        $id = $credential['id'] ?? $credential['rawId'] ?? null;

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{16,340}$/', $id) ? $id : null;
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function unb64(mixed $text): string
    {
        if (! is_string($text) || ! preg_match('/^[A-Za-z0-9_-]*$/', $text)) {
            throw new RuntimeException('not base64url');
        }
        $bytes = base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4), true);
        if ($bytes === false) {
            throw new RuntimeException('not base64url');
        }

        return $bytes;
    }

    private static function responseOf(array $credential): array
    {
        if (($credential['type'] ?? null) !== 'public-key' || ! is_array($credential['response'] ?? null)) {
            throw new RuntimeException('not a public key credential');
        }

        return $credential['response'];
    }

    /** @return string the raw clientDataJSON, which the assertion's signature covers */
    private static function checkClientData(mixed $encoded, string $type, string $challenge, string $origin): string
    {
        $raw = self::unb64($encoded);
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new RuntimeException('client data is not JSON');
        }
        if (($data['type'] ?? null) !== $type) {
            throw new RuntimeException('wrong ceremony type');
        }
        if (! is_string($data['challenge'] ?? null) || ! hash_equals($challenge, $data['challenge'])) {
            throw new RuntimeException('challenge differs');
        }
        if (($data['origin'] ?? null) !== $origin) {
            throw new RuntimeException('origin differs: ' . substr((string) ($data['origin'] ?? ''), 0, 100));
        }
        if (($data['crossOrigin'] ?? false) === true) {
            throw new RuntimeException('cross-origin ceremony');
        }

        return $raw;
    }

    /** @return array{flags: int, sign_count: int, credential_id: ?string, cose_key: ?array} */
    private static function authData(string $data, string $rpId): array
    {
        if (strlen($data) < 37) {
            throw new RuntimeException('authenticator data too short');
        }
        if (! hash_equals(hash('sha256', $rpId, true), substr($data, 0, 32))) {
            throw new RuntimeException('relying party differs');
        }
        $flags = ord($data[32]);
        if (! ($flags & self::FLAG_UP)) {
            throw new RuntimeException('user not present');
        }
        if (! ($flags & self::FLAG_UV)) {
            throw new RuntimeException('user not verified');
        }
        $count = unpack('N', substr($data, 33, 4))[1];

        $credentialId = null;
        $cose = null;
        if ($flags & self::FLAG_AT) {
            if (strlen($data) < 55) {
                throw new RuntimeException('attested credential data too short');
            }
            $length = unpack('n', substr($data, 53, 2))[1];
            if ($length < 16 || $length > 1023 || strlen($data) < 55 + $length) {
                throw new RuntimeException('bad credential id length');
            }
            $credentialId = substr($data, 55, $length);
            [$cose] = Cbor::decodeAt($data, 55 + $length);
            if (! is_array($cose)) {
                throw new RuntimeException('public key is not a COSE map');
            }
        }

        return ['flags' => $flags, 'sign_count' => $count, 'credential_id' => $credentialId, 'cose_key' => $cose];
    }

    /**
     * A COSE public key (RFC 9053) as PEM, and its algorithm. ES256 on P-256 and RS256 only.
     *
     * @return array{0: string, 1: int}
     */
    public static function coseToPem(array $cose): array
    {
        $kty = $cose[1] ?? null;
        $alg = $cose[3] ?? null;

        if ($kty === 2 && $alg === self::ES256) {
            $x = $cose[-2] ?? null;
            $y = $cose[-3] ?? null;
            if (($cose[-1] ?? null) !== 1 || ! is_string($x) || ! is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
                throw new RuntimeException('not a P-256 key');
            }
            // SEQUENCE { SEQUENCE { id-ecPublicKey, prime256v1 }, BIT STRING (uncompressed point) }
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
        } elseif ($kty === 3 && $alg === self::RS256) {
            $n = $cose[-1] ?? null;
            $e = $cose[-2] ?? null;
            if (! is_string($n) || ! is_string($e) || strlen($n) < 256 || $e === '') {
                throw new RuntimeException('not an RSA key of 2048 bits or more');
            }
            $rsaKey = self::der(0x30, self::derInteger($n) . self::derInteger($e));
            // SEQUENCE { SEQUENCE { rsaEncryption, NULL }, BIT STRING (RSAPublicKey) }
            $der = self::der(0x30, hex2bin('300d06092a864886f70d0101010500') . self::der(0x03, "\x00" . $rsaKey));
        } else {
            throw new RuntimeException('unsupported key type or algorithm');
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        if (openssl_pkey_get_public($pem) === false) {
            throw new RuntimeException('OpenSSL cannot read the public key');
        }

        return [$pem, $alg];
    }

    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00" . $bytes;
        }

        return self::der(0x02, $bytes);
    }

    private static function der(int $tag, string $content): string
    {
        $length = strlen($content);
        if ($length < 0x80) {
            return chr($tag) . chr($length) . $content;
        }
        $lengthBytes = ltrim(pack('N', $length), "\x00");

        return chr($tag) . chr(0x80 | strlen($lengthBytes)) . $lengthBytes . $content;
    }
}
