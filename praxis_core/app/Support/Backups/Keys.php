<?php

namespace App\Support\Backups;

/**
 * The owner's public backup key (owner's decision 4): resources/backups/owner-public-key.pem, made on the owner's PC
 * by deploy/backups/pc/New-BackupKey.ps1. The private half never comes near the repository or the server.
 */
final class Keys
{
    /** The PEM, or null while the owner hasn't made the key yet */
    public static function publicKey(): ?string
    {
        $file = (string) config('backups.public_key');
        $pem = is_file($file) ? trim((string) file_get_contents($file)) : '';
        if ($pem === '' || ! str_contains($pem, 'PUBLIC KEY')) {
            return null;
        }
        $key = openssl_pkey_get_public($pem);
        $details = $key ? openssl_pkey_get_details($key) : false;

        return $details && ($details['type'] ?? null) === OPENSSL_KEYTYPE_RSA && ($details['bits'] ?? 0) >= 3072 ? $pem : null;
    }

    /** The key's fingerprint in groups of four ("9e19 7502 …"), as New-BackupKey.ps1 shows it; null without a key */
    public static function fingerprint(): ?string
    {
        $pem = self::publicKey();

        return $pem === null ? null : implode(' ', str_split(substr(Envelope::fingerprint($pem), 0, 32), 4));
    }
}
