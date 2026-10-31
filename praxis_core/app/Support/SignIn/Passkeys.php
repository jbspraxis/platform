<?php

namespace App\Support\SignIn;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * People's passkeys (table passkeys), for HQ and the Client Portal (docs/SIGN_IN.md). WebAuthn checks the
 * browser's answers; this keeps the credentials and the one-time challenges.
 *
 * A challenge lives in the session, is good for one answer and for two minutes, and belongs to one purpose
 * (adding or signing in) and one guard, so an answer made for one can never be spent on another.
 */
final class Passkeys
{
    /** At most this many passkeys per account (a phone, a laptop, a password manager, a spare) */
    public const MAX_PER_ACCOUNT = 10;

    private const CHALLENGE_SECONDS = 120;

    /** @return Collection<int, object> newest first */
    public static function of(string $guard, string $ownerId): Collection
    {
        return DB::table('passkeys')->where('guard', $guard)->where('owner_id', $ownerId)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'name', 'backed_up', 'created_at', 'last_used_at']);
    }

    public static function has(string $guard, string $ownerId): bool
    {
        return DB::table('passkeys')->where('guard', $guard)->where('owner_id', $ownerId)->exists();
    }

    /** Options for adding a passkey to this person; their own passkeys are excluded, so one device adds one */
    public static function creationOptions(string $guard, Authenticatable $who): array
    {
        $ownerId = (string) $who->getAuthIdentifier();
        $exclude = DB::table('passkeys')->where('guard', $guard)->where('owner_id', $ownerId)->get(['credential_id', 'transports'])
            ->map(fn ($p) => ['credential_id' => $p->credential_id, 'transports' => $p->transports ? json_decode($p->transports, true) : null])->all();
        $name = trim(($who->first_name ?? '') . ' ' . ($who->last_name ?? ''));

        return WebAuthn::creationOptions(
            self::issue('create', $guard), SignIn::rpId($guard), SignIn::rpName($guard),
            SignIn::userHandle($guard, $ownerId), (string) $who->email, $name, $exclude,
        );
    }

    /**
     * Keeps the passkey the browser just made, after checking it answers this session's challenge.
     *
     * @throws PasskeyRefused
     */
    public static function add(string $guard, Authenticatable $who, array $credential, ?string $userAgent): object
    {
        $challenge = self::spend('create', $guard);
        $ownerId = (string) $who->getAuthIdentifier();
        try {
            $made = WebAuthn::checkCreation($credential, $challenge, SignIn::rpId($guard), SignIn::origin($guard));
        } catch (RuntimeException $e) {
            Log::warning('Passkey creation refused: ' . $e->getMessage(), ['guard' => $guard, 'owner' => $ownerId]);
            throw new PasskeyRefused('failed');
        }

        if (DB::table('passkeys')->where('credential_id', $made['credential_id'])->exists()) {
            throw new PasskeyRefused('already_added');
        }
        if (DB::table('passkeys')->where('guard', $guard)->where('owner_id', $ownerId)->count() >= self::MAX_PER_ACCOUNT) {
            throw new PasskeyRefused('too_many');
        }

        $id = DB::table('passkeys')->insertGetId([
            'guard' => $guard,
            'owner_id' => $ownerId,
            'credential_id' => $made['credential_id'],
            'public_key' => $made['public_key'],
            'algorithm' => $made['algorithm'],
            'sign_count' => $made['sign_count'],
            'transports' => $made['transports'] ? json_encode($made['transports']) : null,
            'backed_up' => $made['backed_up'],
            'name' => DeviceName::from($userAgent),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('passkeys')->where('id', $id)->first();
    }

    /** Options for signing in: no credentials named, so they say nothing about any account */
    public static function requestOptions(string $guard): array
    {
        return WebAuthn::requestOptions(self::issue('get', $guard), SignIn::rpId($guard));
    }

    /**
     * The account a passkey answer proves, after checking it against this session's challenge and the stored key.
     * The counter is kept with a guarded update, so the same answer can't sign in twice.
     *
     * @throws PasskeyRefused
     */
    public static function signIn(string $guard, array $credential): Authenticatable
    {
        $challenge = self::spend('get', $guard);
        $credentialId = WebAuthn::credentialIdOf($credential);
        $stored = $credentialId ? DB::table('passkeys')->where('guard', $guard)->where('credential_id', $credentialId)->first() : null;
        if (! $stored) {
            throw new PasskeyRefused('unknown');
        }

        try {
            $count = WebAuthn::checkAssertion(
                $credential, $challenge, SignIn::rpId($guard), SignIn::origin($guard),
                $stored->public_key, (int) $stored->algorithm, (int) $stored->sign_count,
                SignIn::userHandle($guard, $stored->owner_id),
            );
        } catch (RuntimeException $e) {
            Log::warning('Passkey sign-in refused: ' . $e->getMessage(), ['guard' => $guard, 'passkey' => $stored->id]);
            throw new PasskeyRefused('failed');
        }

        $kept = DB::table('passkeys')->where('id', $stored->id)->where('sign_count', $stored->sign_count)
            ->update(['sign_count' => $count, 'last_used_at' => now(), 'updated_at' => now()]);
        if ($kept !== 1 && $count > 0) {
            throw new PasskeyRefused('failed');
        }

        $account = SignIn::account($guard, $stored->owner_id);
        if (! $account) {
            throw new PasskeyRefused('inactive');
        }

        return $account;
    }

    public static function remove(string $guard, string $ownerId, int $id): ?object
    {
        $passkey = DB::table('passkeys')->where('id', $id)->where('guard', $guard)->where('owner_id', $ownerId)->first(['id', 'name']);
        if ($passkey) {
            DB::table('passkeys')->where('id', $passkey->id)->delete();
        }

        return $passkey;
    }

    private static function issue(string $purpose, string $guard): string
    {
        $challenge = WebAuthn::challenge();
        session(["sign_in.challenge.{$purpose}.{$guard}" => ['value' => $challenge, 'expires' => now()->getTimestamp() + self::CHALLENGE_SECONDS]]);

        return $challenge;
    }

    /** The challenge this session was given, once: a second answer finds none */
    private static function spend(string $purpose, string $guard): string
    {
        $held = session()->pull("sign_in.challenge.{$purpose}.{$guard}");
        if (! is_array($held) || ! is_string($held['value'] ?? null) || (int) ($held['expires'] ?? 0) < now()->getTimestamp()) {
            throw new PasskeyRefused('expired');
        }

        return $held['value'];
    }
}
