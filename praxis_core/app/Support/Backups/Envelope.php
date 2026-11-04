<?php

namespace App\Support\Backups;

/**
 * Encryption that only the owner's PC can open (owner's decision 4; docs/BACKUPS.md 7). The server holds only the
 * public key. Each file gets its own random keys; they travel inside the file, locked with RSA-OAEP to the owner's
 * 4096-bit key, and the data is streamed 1 MB at a time, so a file of any size never sits in memory.
 *
 *   "JBSPRAXIS-BACKUP-1\n"                     19 bytes
 *   header length (4 bytes, big-endian), header JSON: site, kind, name, size and SHA-256 of the plain file, the key's
 *                                              fingerprint, the algorithms
 *   locked key length (2 bytes), locked key    RSA-OAEP (SHA-1, MGF1-SHA-1) of the AES key (32 bytes) + MAC key (32)
 *   counter (16 bytes)                         random; AES-256-CTR counts up from it, as `openssl enc -aes-256-ctr`
 *   ciphertext                                 as long as the plain file
 *   tag (32 bytes)                             HMAC-SHA-256, with the MAC key, of everything before it
 *
 * Opening it (deploy/backups/pc/Restore-Backup.ps1, or backups:decrypt) checks the tag before trusting a byte, then
 * the plain file's SHA-256 against the header. Plain OpenSSL can do the same (docs/BACKUPS.md 7.3).
 */
final class Envelope
{
    public const MAGIC = "JBSPRAXIS-BACKUP-1\n";

    private const CHUNK = 1 << 20;   // a multiple of 16, so each chunk's counter follows the last one's

    /**
     * @param  array  $meta  site, kind, name (of the plain file); size and sha256 are added
     * @return array{bytes: int, md5: string} of the encrypted file, for Drive's md5Checksum
     */
    public static function encrypt(string $plainPath, string $outPath, string $publicKeyPem, array $meta): array
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (! $details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ($details['bits'] ?? 0) < 3072) {
            throw new BackupFailed('encrypt', 'The backup public key is missing or is not an RSA key of 3072 bits or more.');
        }

        $aesKey = random_bytes(32);
        $macKey = random_bytes(32);
        $counter = random_bytes(16);
        if (! openssl_public_encrypt($aesKey . $macKey, $locked, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new BackupFailed('encrypt', 'OpenSSL could not lock the file key with the public key.');
        }

        $header = json_encode($meta + [
            'v' => 1,
            'size' => filesize($plainPath),
            'sha256' => hash_file('sha256', $plainPath),
            'key' => self::fingerprint($publicKeyPem),
            'created' => gmdate('Y-m-d\TH:i:s\Z'),
            'cipher' => 'AES-256-CTR',
            'mac' => 'HMAC-SHA256',
            'wrap' => 'RSA-OAEP-SHA1',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $prefix = self::MAGIC . pack('N', strlen($header)) . $header . pack('n', strlen($locked)) . $locked . $counter;

        $in = fopen($plainPath, 'rb');
        $out = fopen($outPath, 'wb');
        if (! $in || ! $out) {
            throw new BackupFailed('encrypt', 'Could not open the files to encrypt.');
        }
        $mac = hash_init('sha256', HASH_HMAC, $macKey);
        $md5 = hash_init('md5');
        $bytes = 0;
        $write = function (string $data) use ($out, $md5, &$bytes) {
            if (fwrite($out, $data) !== strlen($data)) {
                throw new BackupFailed('encrypt', 'The encrypted file could not be written to the server’s disk (is the disk quota full?).');
            }
            hash_update($md5, $data);
            $bytes += strlen($data);
        };

        try {
            $write($prefix);
            hash_update($mac, $prefix);
            while (($chunk = self::read($in, self::CHUNK)) !== '') {
                $cipher = openssl_encrypt($chunk, 'aes-256-ctr', $aesKey, OPENSSL_RAW_DATA, $counter);
                $write($cipher);
                hash_update($mac, $cipher);
                $counter = self::addBlocks($counter, intdiv(strlen($chunk), 16));
            }
            $write(hash_final($mac, true));
        } finally {
            fclose($in);
            fclose($out);
        }

        return ['bytes' => $bytes, 'md5' => hash_final($md5)];
    }

    /**
     * Opens a file with the private key (a PC, or a test). The output is written only once the tag is right.
     *
     * @return array the header
     */
    public static function decrypt(string $encPath, string $outPath, string $privateKeyPem): array
    {
        $in = fopen($encPath, 'rb');
        $size = filesize($encPath);
        try {
            [$header, $locked, $counter, $prefix] = self::readPrefix($in);
            $key = openssl_pkey_get_private($privateKeyPem);
            if (! $key || ! openssl_private_decrypt($locked, $keys, $key, OPENSSL_PKCS1_OAEP_PADDING) || strlen($keys) !== 64) {
                throw new BackupFailed('decrypt', 'This private key does not open this backup (it was made for key ' . ($header['key'] ?? '?') . ').');
            }
            [$aesKey, $macKey] = [substr($keys, 0, 32), substr($keys, 32)];
            $cipherBytes = $size - strlen($prefix) - 32;
            if ($cipherBytes < 0) {
                throw new BackupFailed('decrypt', 'The file is cut short.');
            }

            // First pass: the tag, before anything is decrypted
            $mac = hash_init('sha256', HASH_HMAC, $macKey);
            hash_update($mac, $prefix);
            for ($left = $cipherBytes; $left > 0;) {
                $chunk = self::read($in, min(self::CHUNK, $left));
                hash_update($mac, $chunk);
                $left -= strlen($chunk);
            }
            if (! hash_equals(hash_final($mac, true), self::read($in, 32))) {
                throw new BackupFailed('decrypt', 'The file was changed or damaged after it was made (its tag does not match).');
            }

            fseek($in, strlen($prefix));
            $tmp = $outPath . '.part';
            $out = fopen($tmp, 'wb');
            $sha = hash_init('sha256');
            for ($left = $cipherBytes; $left > 0;) {
                $chunk = self::read($in, min(self::CHUNK, $left));
                $plain = openssl_decrypt($chunk, 'aes-256-ctr', $aesKey, OPENSSL_RAW_DATA, $counter);
                fwrite($out, $plain);
                hash_update($sha, $plain);
                $counter = self::addBlocks($counter, intdiv(strlen($chunk), 16));
                $left -= strlen($chunk);
            }
            fclose($out);
            if (isset($header['sha256']) && ! hash_equals((string) $header['sha256'], hash_final($sha))) {
                @unlink($tmp);
                throw new BackupFailed('decrypt', 'The opened file does not match the checksum recorded when it was made.');
            }
            rename($tmp, $outPath);

            return $header;
        } finally {
            fclose($in);
        }
    }

    /** @return array the header of an encrypted file, without opening it */
    public static function header(string $encPath): array
    {
        $in = fopen($encPath, 'rb');
        try {
            return self::readPrefix($in)[0];
        } finally {
            fclose($in);
        }
    }

    /** SHA-256 of the key's DER form (SubjectPublicKeyInfo), as `openssl pkey -pubin -outform DER | sha256sum` gives */
    public static function fingerprint(string $publicKeyPem): string
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        $pem = $key ? (string) (openssl_pkey_get_details($key)['key'] ?? '') : '';
        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem));

        return hash('sha256', $der);
    }

    /** The 16-byte counter plus $blocks, as one 128-bit big-endian number (what OpenSSL's CTR mode does) */
    public static function addBlocks(string $counter, int $blocks): string
    {
        for ($i = 15; $i >= 0 && $blocks > 0; $i--) {
            $sum = ord($counter[$i]) + ($blocks & 0xFF);
            $counter[$i] = chr($sum & 0xFF);
            $blocks = ($blocks >> 8) + ($sum >> 8);
        }

        return $counter;
    }

    /** @return array{0: array, 1: string, 2: string, 3: string} header, locked key, counter, every byte before the data */
    private static function readPrefix($in): array
    {
        $magic = self::read($in, strlen(self::MAGIC));
        if ($magic !== self::MAGIC) {
            throw new BackupFailed('decrypt', 'This is not a JBS Praxis server backup.');
        }
        $lengthBytes = self::read($in, 4);
        $length = strlen($lengthBytes) === 4 ? unpack('N', $lengthBytes)[1] : 0;
        $json = $length > 0 && $length < 65536 ? self::read($in, $length) : '';
        $header = json_decode($json, true);
        $lockedLength = unpack('n', self::read($in, 2) ?: "\0\0")[1];
        $locked = self::read($in, $lockedLength);
        $counter = self::read($in, 16);
        if (! is_array($header) || strlen($locked) !== $lockedLength || strlen($counter) !== 16) {
            throw new BackupFailed('decrypt', 'The file’s header is damaged.');
        }

        return [$header, $locked, $counter, $magic . $lengthBytes . $json . pack('n', $lockedLength) . $locked . $counter];
    }

    /** Reads exactly $length bytes, or fewer only at the end of the file */
    private static function read($handle, int $length): string
    {
        $data = '';
        while (strlen($data) < $length && ! feof($handle)) {
            $part = fread($handle, $length - strlen($data));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }

        return $data;
    }
}
