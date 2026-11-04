<?php

namespace App\Http\Controllers;

use App\Support\KeyVault\KeyVault;
use App\Support\KeyVault\LockedCopy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Settings and access › Key vault (docs/KEY_VAULT.md): the page's script, and "Download a locked copy".
 * Both sit behind the 'key-vault' gate (the protected Owner role) in routes/web.php.
 */
class KeyVaultController extends Controller
{
    private const SCRIPTS = ['crypto.js', 'page.js'];

    /** crypto.js then page.js: plain script, no build step, loaded once with Livewire's @assets */
    public function script()
    {
        $body = '';
        foreach (self::SCRIPTS as $file) {
            $body .= file_get_contents(resource_path('key-vault/' . $file)) . "\n";
        }

        return response($body, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The ?v= of the script's address, so a new version is fetched at once */
    public static function scriptVersion(): string
    {
        return (string) max(array_map(fn ($file) => (int) @filemtime(resource_path('key-vault/' . $file)), self::SCRIPTS));
    }

    /**
     * The locked copy of one of the owner's own items. Open only for a few minutes after the emailed code (or
     * right after adding the item), in the same browser session; otherwise back to the page with a word why.
     */
    public function lockedCopy(Request $request, string $item)
    {
        $owner = Auth::user();
        $found = KeyVault::find($owner, $item);
        abort_unless($found !== null, 404);

        if (! KeyVault::isUnlocked($found)) {
            return redirect()->route('hq.system.key-vault')->with('error',
                'The locked copy can be downloaded for ' . KeyVault::UNLOCKED_MINUTES . ' minutes after your emailed code. Reveal it again, then download.');
        }

        return response(KeyVault::lockedCopy($owner, $found), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . LockedCopy::filename($found) . '"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
