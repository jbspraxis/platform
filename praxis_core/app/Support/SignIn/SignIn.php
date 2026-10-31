<?php

namespace App\Support\SignIn;

use App\Models\ClientUser;
use App\Models\User;
use App\Support\Hosts;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * What the two sign-ins share for passkeys and PINs (docs/SIGN_IN.md): which accounts a guard holds, where its
 * passkeys belong, the "fresh sign-in" window and the audit trail.
 *
 *   web     HQ staff (users), hq.jbspraxis.com
 *   client  Client Portal accounts (client_users), portal.jbspraxis.com
 */
final class SignIn
{
    public const HQ = 'web';
    public const CLIENT = 'client';
    public const GUARDS = [self::HQ, self::CLIENT];

    /** Minutes after signing in (or typing the password again) during which a passkey or PIN can be set up */
    public const FRESH_MINUTES = 10;

    public static function valid(string $guard): bool
    {
        return in_array($guard, self::GUARDS, true);
    }

    /** The passkey's relying party: the host itself, so a passkey works only where it was made */
    public static function rpId(string $guard): string
    {
        return (string) (config("sign_in.rp_id.{$guard}") ?: ($guard === self::CLIENT ? Hosts::portal() : Hosts::hq()));
    }

    /** The only origin a passkey answer may come from (from configuration, never from the request) */
    public static function origin(string $guard): string
    {
        return rtrim((string) (config("sign_in.origin.{$guard}") ?: 'https://' . self::rpId($guard)), '/');
    }

    public static function rpName(string $guard): string
    {
        return $guard === self::CLIENT ? 'JBS Praxis Client Portal' : 'JBS Praxis HQ';
    }

    /** The account a guard's id names, if it may sign in now */
    public static function account(string $guard, string $id): ?Authenticatable
    {
        return self::accounts($guard)->whereKey($id)->first();
    }

    /** The account an email address names, if it may sign in now */
    public static function accountByEmail(string $guard, string $email): ?Authenticatable
    {
        return self::accounts($guard)->where('email', trim($email))->first();
    }

    /** Accounts that may sign in: active staff; active client accounts that have set a password (leads have none) */
    private static function accounts(string $guard)
    {
        return match ($guard) {
            self::HQ => User::query()->where('status', 'Active'),
            self::CLIENT => ClientUser::query()->where('status', 'Active')->whereNotNull('password'),
        };
    }

    /** The passkey's user handle: opaque, stable, and never the email address */
    public static function userHandle(string $guard, string $ownerId): string
    {
        return $guard . ':' . $ownerId;
    }

    /** Any sign-in, and typing the password again, opens the window for setting up a passkey or PIN */
    public static function markFresh(): void
    {
        session(['sign_in.fresh_at' => now()->getTimestamp()]);
    }

    public static function isFresh(): bool
    {
        $at = (int) session('sign_in.fresh_at', 0);

        return $at > 0 && now()->getTimestamp() - $at <= self::FRESH_MINUTES * 60;
    }

    /** One line in the audit log. Staff rows carry their user id; a client's names their account as the target. */
    public static function audit(string $guard, string $ownerId, string $action, string $description): void
    {
        rescue(fn () => DB::table('system_audit_logs')->insert([
            'user_id' => $guard === self::HQ ? $ownerId : null,
            'action' => $action,
            'description' => $description,
            'target_table' => $guard === self::HQ ? 'users' : 'client_users',
            'target_id' => $ownerId,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]), null, false);
    }

    /** When an account is deleted for good: its passkeys and PIN go with it */
    public static function forget(string $guard, string $ownerId): void
    {
        DB::table('passkeys')->where('guard', $guard)->where('owner_id', $ownerId)->delete();
        DB::table('sign_in_pins')->where('guard', $guard)->where('owner_id', $ownerId)->delete();
    }
}
