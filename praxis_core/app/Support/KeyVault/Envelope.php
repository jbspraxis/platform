<?php

namespace App\Support\KeyVault;

use App\Models\KeyVaultItem;
use InvalidArgumentException;

/**
 * A secret as the owner's browser locked it (docs/KEY_VAULT.md section 4), and the only form in which HQ ever
 * holds one. resources/key-vault/crypto.js makes it:
 *
 *   key        = PBKDF2-HMAC-SHA-256(passphrase in Unicode NFC, salt, iterations) → 32 bytes
 *   ciphertext = AES-256-GCM(key, iv, additional data "JBS Praxis key vault v1|<id>") → the encrypted bytes, then the 16-byte tag
 *
 * The server checks the shape (names, sizes, at least 600,000 iterations) and nothing else: it cannot tell a
 * good ciphertext from a bad one, because it never has the passphrase.
 */
final class Envelope
{
    public const FORMAT = 'jbspraxis-key-vault';

    public const VERSION = 1;

    public const AAD_PREFIX = 'JBS Praxis key vault v1|';

    /** OWASP's figure for PBKDF2-HMAC-SHA-256 (2023); the browser uses exactly this many */
    public const MIN_ITERATIONS = 600000;

    public const MAX_ITERATIONS = 10000000;

    /** The longest secret the browser will lock: an RSA-4096 private key in PEM is about 3,300 */
    public const MAX_SECRET_BYTES = 20000;

    public const TAG_BYTES = 16;

    public const IV_BYTES = 12;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private function __construct(
        public readonly string $id,
        public readonly int $iterations,
        public readonly string $salt,
        public readonly string $iv,
        public readonly string $ciphertext,
    ) {
    }

    /**
     * What the browser sent, checked. The message says what is wrong in words the page can show.
     *
     * @throws InvalidArgumentException
     */
    public static function fromBrowser(mixed $data): self
    {
        if (! is_array($data)) {
            throw new InvalidArgumentException('The locked secret did not arrive. Reload the page and try again.');
        }
        $kdf = is_array($data['kdf'] ?? null) ? $data['kdf'] : [];
        $cipher = is_array($data['cipher'] ?? null) ? $data['cipher'] : [];

        $bad = match (true) {
            ($data['format'] ?? null) !== self::FORMAT, ($data['v'] ?? null) !== self::VERSION => 'format',
            ! is_string($data['id'] ?? null) || ! preg_match(self::UUID, $data['id']) => 'id',
            ($kdf['name'] ?? null) !== 'PBKDF2', ($kdf['hash'] ?? null) !== 'SHA-256' => 'kdf',
            ! is_int($kdf['iterations'] ?? null) || $kdf['iterations'] < self::MIN_ITERATIONS || $kdf['iterations'] > self::MAX_ITERATIONS => 'iterations',
            ($cipher['name'] ?? null) !== 'AES-GCM', ($cipher['length'] ?? null) !== 256, ($cipher['tagLength'] ?? null) !== 128 => 'cipher',
            default => null,
        };
        if ($bad === null) {
            $salt = self::bytes($kdf['salt'] ?? null);
            $iv = self::bytes($cipher['iv'] ?? null);
            $ciphertext = self::bytes($data['ciphertext'] ?? null);
            $bad = match (true) {
                $salt === null || strlen($salt) < 16 || strlen($salt) > 32 => 'salt',
                $iv === null || strlen($iv) !== self::IV_BYTES => 'iv',
                $ciphertext === null || strlen($ciphertext) <= self::TAG_BYTES => 'ciphertext',
                strlen($ciphertext) > self::MAX_SECRET_BYTES + self::TAG_BYTES => 'too_long',
                default => null,
            };
        }
        if ($bad !== null) {
            throw new InvalidArgumentException($bad === 'too_long'
                ? 'That secret is too long for the vault (at most 20,000 characters).'
                : "The locked secret is not in the vault's format ({$bad}). Reload the page and try again.");
        }

        return new self($data['id'], $kdf['iterations'], $kdf['salt'], $cipher['iv'], $data['ciphertext']);
    }

    public static function fromItem(KeyVaultItem $item): self
    {
        return new self($item->id, (int) $item->kdf_iterations, (string) $item->kdf_salt, (string) $item->iv, (string) $item->ciphertext);
    }

    /** The columns of key_vault_items that hold it */
    public function columns(): array
    {
        return [
            'format_version' => self::VERSION,
            'kdf' => 'PBKDF2-SHA-256',
            'kdf_iterations' => $this->iterations,
            'kdf_salt' => $this->salt,
            'cipher' => 'AES-256-GCM',
            'iv' => $this->iv,
            'ciphertext' => $this->ciphertext,
        ];
    }

    /** What the browser and the locked copy read: the same shape crypto.js made */
    public function toArray(): array
    {
        return [
            'format' => self::FORMAT,
            'v' => self::VERSION,
            'id' => $this->id,
            'kdf' => ['name' => 'PBKDF2', 'hash' => 'SHA-256', 'iterations' => $this->iterations, 'salt' => $this->salt],
            'cipher' => ['name' => 'AES-GCM', 'length' => 256, 'tagLength' => 128, 'iv' => $this->iv],
            'aad' => self::AAD_PREFIX . $this->id,
            'ciphertext' => $this->ciphertext,
        ];
    }

    /** Strict, canonical base64 (what the browser's btoa makes), else null */
    private static function bytes(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2 * (self::MAX_SECRET_BYTES + self::TAG_BYTES)) {
            return null;
        }
        $bytes = base64_decode($value, true);

        return $bytes !== false && base64_encode($bytes) === $value ? $bytes : null;
    }
}
