<?php

namespace Tests\Support;

use App\Support\SignIn\WebAuthn;

/**
 * A passkey authenticator in software, for tests: it answers WebAuthn options the way a phone or laptop does
 * (attestation "none", user present and verified), with a real P-256 or RSA key, so the server's checks run
 * against genuine signatures. Each method returns what window.praxisPasskey hands to Livewire.
 */
final class SoftAuthenticator
{
    public string $credentialId;

    public int $counter = 0;

    public bool $verified = true;

    private \OpenSSLAsymmetricKey $key;

    public function __construct(public string $origin, private string $alg = 'es256', ?string $credentialId = null)
    {
        $this->credentialId = $credentialId ?? random_bytes(32);
        $this->key = $alg === 'rs256'
            ? openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048])
            : openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    }

    public function id(): string
    {
        return WebAuthn::b64($this->credentialId);
    }

    /** The answer to navigator.credentials.create({ publicKey: $options }) */
    public function create(array $options, ?string $rpId = null): array
    {
        $rpId ??= $options['rp']['id'];
        $clientData = json_encode(['type' => 'webauthn.create', 'challenge' => $options['challenge'], 'origin' => $this->origin, 'crossOrigin' => false]);
        $authData = $this->authData($rpId, 0x40) . str_repeat("\0", 16) . pack('n', strlen($this->credentialId)) . $this->credentialId . self::cbor($this->cose());

        return [
            'id' => $this->id(), 'rawId' => $this->id(), 'type' => 'public-key',
            'response' => [
                'clientDataJSON' => WebAuthn::b64($clientData),
                'attestationObject' => WebAuthn::b64(self::cbor(['fmt' => 'none', 'attStmt' => [], 'authData' => new CborBytes($authData)])),
                'transports' => ['internal', 'hybrid'],
            ],
        ];
    }

    /** The answer to navigator.credentials.get({ publicKey: $options }) */
    public function get(array $options, ?string $userHandle = null, ?string $rpId = null, string $type = 'webauthn.get'): array
    {
        $rpId ??= $options['rpId'];
        $this->counter++;
        $clientData = json_encode(['type' => $type, 'challenge' => $options['challenge'], 'origin' => $this->origin]);
        $authData = $this->authData($rpId, 0);
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return [
            'id' => $this->id(), 'rawId' => $this->id(), 'type' => 'public-key',
            'response' => [
                'clientDataJSON' => WebAuthn::b64($clientData),
                'authenticatorData' => WebAuthn::b64($authData),
                'signature' => WebAuthn::b64($signature),
                'userHandle' => $userHandle === null ? null : WebAuthn::b64($userHandle),
            ],
        ];
    }

    private function authData(string $rpId, int $extraFlags): string
    {
        $flags = 0x01 | ($this->verified ? 0x04 : 0) | $extraFlags;

        return hash('sha256', $rpId, true) . chr($flags) . pack('N', $this->counter);
    }

    private function cose(): array
    {
        $details = openssl_pkey_get_details($this->key);
        if ($this->alg === 'rs256') {
            return [1 => 3, 3 => -257, -1 => new CborBytes($details['rsa']['n']), -2 => new CborBytes($details['rsa']['e'])];
        }

        return [1 => 2, 3 => -7, -1 => 1,
            -2 => new CborBytes(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
            -3 => new CborBytes(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT))];
    }

    /** Just enough CBOR to write what an authenticator sends: ints, text, bytes, lists and maps */
    public static function cbor(mixed $value): string
    {
        $head = function (int $major, int $n): string {
            if ($n < 24) {
                return chr(($major << 5) | $n);
            }
            if ($n < 0x100) {
                return chr(($major << 5) | 24) . chr($n);
            }
            if ($n < 0x10000) {
                return chr(($major << 5) | 25) . pack('n', $n);
            }

            return chr(($major << 5) | 26) . pack('N', $n);
        };

        return match (true) {
            $value instanceof CborBytes => $head(2, strlen($value->bytes)) . $value->bytes,
            is_int($value) && $value >= 0 => $head(0, $value),
            is_int($value) => $head(1, -1 - $value),
            is_string($value) => $head(3, strlen($value)) . $value,
            is_bool($value) => chr($value ? 0xf5 : 0xf4),
            $value === null => chr(0xf6),
            is_array($value) && array_is_list($value) && $value !== [] => $head(4, count($value)) . implode('', array_map([self::class, 'cbor'], $value)),
            is_array($value) => $head(5, count($value)) . implode('', array_map(fn ($k, $v) => self::cbor($k) . self::cbor($v), array_keys($value), $value)),
        };
    }
}

