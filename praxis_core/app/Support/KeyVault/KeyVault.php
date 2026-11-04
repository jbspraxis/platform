<?php

namespace App\Support\KeyVault;

use App\Mail\KeyVaultMail;
use App\Models\KeyVaultItem;
use App\Models\User;
use App\Support\Access;
use App\Support\Signing\SigningCodes;
use App\Support\SignIn\DeviceName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Settings and access › Key vault (docs/KEY_VAULT.md): the owner's own secrets, locked in the owner's browser.
 *
 * Owner decisions, 4 Nov 2026:
 *   - zero-knowledge: the browser encrypts and decrypts (crypto.js); HQ keeps only the Envelope, never the
 *     passphrase or the secret, so nobody with the server or the database can read one;
 *   - a reveal needs, in order, a sign-in less than ten minutes old (or the password again), a one-time code
 *     emailed to the owner (SigningCodes: 6 digits, 10 minutes, 3 codes per 15 minutes, 5 wrong codes), and
 *     the passphrase, typed in the browser;
 *   - the protected Owner role only: not a permission on Roles and permissions, so no role can be given it;
 *   - every add, change, delete, code and reveal is in system_audit_logs, and each reveal emails the owner.
 *
 * Items belong to the owner who locked them: a second Owner account sees only its own.
 */
final class KeyVault
{
    /** SigningCodes' $kind: its own counters, separate from proposals and statements of work */
    public const CODE_KIND = 'key-vault';

    /** After a code (or right after adding), the locked copy can be downloaded for this long */
    public const UNLOCKED_MINUTES = 5;

    public const NAME_MAX = 120;

    private const SESSION_CODE = 'key_vault.code';

    private const SESSION_UNLOCKED = 'key_vault.unlocked';

    /** The gate 'key-vault': an active account holding the protected Owner role (App\Support\Access::isOwner) */
    public static function allows(mixed $user): bool
    {
        return $user instanceof User && $user->status === 'Active' && Access::isOwner($user);
    }

    /** @return Collection<int, KeyVaultItem> the owner's own items, by name */
    public static function items(User $owner): Collection
    {
        return KeyVaultItem::query()->where('user_id', $owner->id)->orderBy('name')->get();
    }

    /** One of the owner's own items; another owner's or a missing one is null */
    public static function find(User $owner, ?string $id): ?KeyVaultItem
    {
        return $id ? KeyVaultItem::query()->where('user_id', $owner->id)->whereKey($id)->first() : null;
    }

    /**
     * Keeps a secret the browser has just locked.
     *
     * @throws InvalidArgumentException
     */
    public static function add(User $owner, string $name, Envelope $envelope): KeyVaultItem
    {
        $name = self::name($owner, $name);
        if (KeyVaultItem::query()->whereKey($envelope->id)->exists()) {
            throw new InvalidArgumentException('That locked secret is already in the vault. Reload the page and try again.');
        }

        $item = DB::transaction(function () use ($owner, $name, $envelope) {
            $item = KeyVaultItem::query()->create(['id' => $envelope->id, 'user_id' => $owner->id, 'name' => $name] + $envelope->columns());
            self::audit($owner, 'key_vault.added', "Added “{$name}” to the key vault (fingerprint " . self::fingerprint($item) . ')', $item, new: ['name' => $name]);

            return $item;
        });
        // The browser that locked it may save the locked copy straight away: it holds nothing it was not just typed
        self::unlock($item);

        return $item;
    }

    /** @throws InvalidArgumentException */
    public static function rename(User $owner, KeyVaultItem $item, string $name): void
    {
        $old = $item->name;
        $name = self::name($owner, $name, $item->id);
        if ($name === $old) {
            return;
        }
        DB::transaction(function () use ($owner, $item, $old, $name) {
            $item->forceFill(['name' => $name])->save();
            self::audit($owner, 'key_vault.renamed', "Renamed “{$old}” to “{$name}” in the key vault", $item, ['name' => $old], ['name' => $name]);
        });
    }

    /**
     * A new secret or a new passphrase for an item: the browser locked it again under the same id.
     *
     * @throws InvalidArgumentException
     */
    public static function replace(User $owner, KeyVaultItem $item, Envelope $envelope): void
    {
        if ($envelope->id !== $item->id) {
            throw new InvalidArgumentException('The locked secret was made for another item. Reload the page and try again.');
        }
        $before = self::fingerprint($item);
        DB::transaction(function () use ($owner, $item, $envelope, $before) {
            $item->forceFill($envelope->columns())->save();
            self::audit($owner, 'key_vault.replaced', "Replaced the secret or passphrase of “{$item->name}” in the key vault (fingerprint {$before} → " . self::fingerprint($item) . ')', $item, ['fingerprint' => $before], ['fingerprint' => self::fingerprint($item)]);
        });
        self::unlock($item);
    }

    public static function delete(User $owner, KeyVaultItem $item): void
    {
        DB::transaction(function () use ($owner, $item) {
            self::audit($owner, 'key_vault.deleted', "Deleted “{$item->name}” from the key vault", $item, ['name' => $item->name, 'added' => $item->created_at?->toIso8601String()]);
            $item->delete();
        });
        $unlocked = (array) session(self::SESSION_UNLOCKED, []);
        unset($unlocked[$item->id]);
        session([self::SESSION_UNLOCKED => $unlocked]);
        if ((session(self::SESSION_CODE)['item'] ?? null) === $item->id) {
            session()->forget(self::SESSION_CODE);
        }
    }

    /**
     * Emails the owner a code that opens this one item, in this browser session only. Null when it was sent;
     * else [reason, minutes to wait]: too_many_codes, too_many_codes_email or email_failed.
     *
     * @return array{string, int}|null
     */
    public static function sendCode(User $owner, KeyVaultItem $item): ?array
    {
        if ($refused = SigningCodes::claimSend(self::CODE_KIND, (string) $owner->id, (string) $owner->email)) {
            return $refused;
        }
        $code = SigningCodes::newCode();
        session([self::SESSION_CODE => [
            'item' => $item->id,
            'hash' => SigningCodes::hash($code),
            'expires_at' => now()->addMinutes(SigningCodes::MINUTES)->toIso8601String(),
        ]]);

        try {
            Mail::to($owner->email)->send(KeyVaultMail::code($code, $item->name, $owner->first_name, SigningCodes::MINUTES)->locale('en'));
        } catch (Throwable $e) {
            // Never log the code itself; the unsent code is cancelled, so it can't be used
            session()->forget(self::SESSION_CODE);
            Log::error('Key vault code email failed', ['item' => $item->id, 'message' => $e->getMessage()]);

            return ['email_failed', 0];
        }
        self::audit($owner, 'key_vault.code_sent', "Emailed a code to open “{$item->name}” to " . self::mask($owner->email), $item);

        return null;
    }

    /**
     * The code typed: right, it releases the item's envelope to this browser (audited first, then the owner is
     * told by email), and opens the locked-copy download for a few minutes. Wrong: [reason, minutes to wait],
     * reason being wrong, no_code (none sent for this item, or it was used), locked or cancel.
     *
     * @return array{envelope: array, name: string}|array{string, int}
     */
    public static function reveal(User $owner, KeyVaultItem $item, string $code): array
    {
        $held = (array) session(self::SESSION_CODE, []);
        if (($held['item'] ?? null) !== $item->id) {
            self::audit($owner, 'key_vault.code_wrong', "A code was typed to open “{$item->name}”, but none was sent for it in this session (or it was used)", $item);

            return ['no_code', 0];
        }
        $refused = SigningCodes::check(self::CODE_KIND, (string) $owner->id, $held['hash'] ?? null, $held['expires_at'] ?? null, $code);
        if ($refused) {
            if ($refused[0] === 'cancel') {
                session()->forget(self::SESSION_CODE);
            }
            if ($refused[0] !== 'locked') {
                self::audit($owner, 'key_vault.code_wrong', "A wrong or expired code was typed to open “{$item->name}”", $item);
            }

            return $refused;
        }
        session()->forget(self::SESSION_CODE);

        // The audit row first: if it can't be written, nothing leaves
        DB::transaction(function () use ($owner, $item) {
            $item->forceFill(['last_revealed_at' => now()])->save();
            self::audit($owner, 'key_vault.revealed', "Revealed “{$item->name}” from the key vault (" . DeviceName::from(request()?->userAgent()) . ')', $item);
        });
        self::unlock($item);

        try {
            Mail::to($owner->email)->send(KeyVaultMail::revealed($item->name, $owner->first_name, now(), request()?->ip(), DeviceName::from(request()?->userAgent()))->locale('en'));
        } catch (Throwable $e) {
            Log::warning('Key vault reveal notice email failed', ['item' => $item->id, 'message' => $e->getMessage()]);
        }

        return ['envelope' => $item->envelope()->toArray(), 'name' => $item->name];
    }

    /** The locked-copy download is open for this item in this session */
    public static function isUnlocked(KeyVaultItem $item): bool
    {
        $until = (int) (((array) session(self::SESSION_UNLOCKED, []))[$item->id] ?? 0);

        return $until > 0 && now()->getTimestamp() <= $until;
    }

    /** Which item the code just emailed opens (the page shows the code box under it) */
    public static function codeItem(): ?string
    {
        $held = (array) session(self::SESSION_CODE, []);

        return isset($held['item'], $held['expires_at']) && now()->lessThanOrEqualTo($held['expires_at']) ? $held['item'] : null;
    }

    /**
     * The locked copy: one HTML file that opens offline in any browser with the passphrase (LockedCopy).
     * Audited, like a reveal: it carries the same envelope.
     */
    public static function lockedCopy(User $owner, KeyVaultItem $item): string
    {
        $html = LockedCopy::html($item);
        self::audit($owner, 'key_vault.locked_copy', "Downloaded a locked copy of “{$item->name}” (fingerprint " . self::fingerprint($item) . ')', $item);

        return $html;
    }

    /** A short label for this version of the ciphertext ("3f9a 0c41 7be2 d5aa"), shown on the page and in the locked copy */
    public static function fingerprint(KeyVaultItem $item): string
    {
        return trim(chunk_split(substr(hash('sha256', (string) $item->ciphertext), 0, 16), 4, ' '));
    }

    /** "o•••@gmail.com" */
    public static function mask(?string $email): string
    {
        [$local, $domain] = array_pad(explode('@', (string) $email, 2), 2, '');

        return mb_substr($local, 0, 1) . '•••@' . $domain;
    }

    private static function unlock(KeyVaultItem $item): void
    {
        $unlocked = array_filter((array) session(self::SESSION_UNLOCKED, []), fn ($until) => (int) $until >= now()->getTimestamp());
        $unlocked[$item->id] = now()->addMinutes(self::UNLOCKED_MINUTES)->getTimestamp();
        session([self::SESSION_UNLOCKED => $unlocked]);
    }

    /** @throws InvalidArgumentException */
    private static function name(User $owner, string $name, ?string $except = null): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Give it a name, such as “Server backup private key”.');
        }
        if (mb_strlen($name) > self::NAME_MAX) {
            throw new InvalidArgumentException('Keep the name under ' . self::NAME_MAX . ' characters.');
        }
        $taken = KeyVaultItem::query()->where('user_id', $owner->id)->where('name', $name)
            ->when($except, fn ($q) => $q->whereKeyNot($except))->exists();
        if ($taken) {
            throw new InvalidArgumentException('Another item in your vault already has that name.');
        }

        return $name;
    }

    /** One line in system_audit_logs, never with any part of the envelope. Not rescued: a vault action that can't be logged doesn't happen. */
    private static function audit(User $owner, string $action, string $description, KeyVaultItem $item, array $old = [], array $new = []): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => $owner->id,
            'action' => $action,
            'description' => Str::limit($description, 1900, ''),
            'target_table' => 'key_vault_items',
            'target_id' => $item->id,
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
