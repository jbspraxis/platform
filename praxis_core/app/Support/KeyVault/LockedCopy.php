<?php

namespace App\Support\KeyVault;

use App\Models\KeyVaultItem;
use Illuminate\Support\Str;

/**
 * "Download a locked copy" (docs/KEY_VAULT.md section 5): one HTML file holding an item's envelope and a small
 * page that opens it offline with the passphrase. It must work with HQ gone, because that is when the backup
 * key is needed: everything is inside the file (crypto.js, the page's script and its styles), and its
 * Content-Security-Policy allows only that script and that style, by hash, and no connection of any kind.
 */
final class LockedCopy
{
    public static function html(KeyVaultItem $item): string
    {
        $envelope = $item->envelope()->toArray() + [
            'name' => $item->name,
            'made_at' => now()->utc()->toIso8601String(),
        ];
        $script = self::script();
        $style = self::style();

        return view('key-vault.locked-copy', [
            'name' => $item->name,
            'fingerprint' => KeyVault::fingerprint($item),
            'madeAt' => now()->setTimezone('Africa/Lagos')->locale('en')->isoFormat('D MMMM YYYY, HH:mm') . ' (Lagos time)',
            'lockedAt' => ($item->updated_at ?? $item->created_at ?? now())->copy()->setTimezone('Africa/Lagos')->locale('en')->isoFormat('D MMMM YYYY'),
            'iterations' => number_format($envelope['kdf']['iterations']),
            // Safe inside <script type="application/json">: no "<", ">", "&" or quotes survive unescaped
            'envelopeJson' => json_encode($envelope, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'envelopePretty' => json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'script' => $script,
            'style' => $style,
            'csp' => self::policy($script, $style),
        ])->render();
    }

    /** "key-vault-server-backup-private-key-locked-copy-2026-11-04.html" */
    public static function filename(KeyVaultItem $item): string
    {
        $slug = Str::slug($item->name) ?: 'secret';

        return 'key-vault-' . Str::limit($slug, 60, '') . '-locked-copy-' . now()->setTimezone('Africa/Lagos')->format('Y-m-d') . '.html';
    }

    /** crypto.js then the page's own script, as one inline script */
    public static function script(): string
    {
        return "\n" . trim(self::read('crypto.js')) . "\n" . trim(self::read('locked-copy.js')) . "\n";
    }

    public static function style(): string
    {
        return "\n" . trim(self::read('locked-copy.css')) . "\n";
    }

    /** Only the file's own script and style may run; nothing may be fetched, framed, sent or submitted */
    public static function policy(string $script, string $style): string
    {
        $hash = fn (string $text) => "'sha256-" . base64_encode(hash('sha256', $text, true)) . "'";

        return "default-src 'none'; script-src {$hash($script)}; style-src {$hash($style)}; img-src 'none'; connect-src 'none'; "
            . "form-action 'none'; base-uri 'none'; frame-src 'none'; object-src 'none'";
    }

    private static function read(string $file): string
    {
        $text = (string) file_get_contents(resource_path('key-vault/' . $file));

        // Windows checkouts may carry CRLF; the hash must match what is in the file byte for byte, so use one form
        return str_replace("\r\n", "\n", $text);
    }
}
