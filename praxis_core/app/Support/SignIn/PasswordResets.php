<?php

namespace App\Support\SignIn;

use App\Mail\PasswordResetMail;
use App\Mail\UserInvitation;
use App\Support\MailLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Forgotten passwords on HQ and the Client Portal (docs/SIGN_IN.md section 8).
 *
 * "Forgot your password?" emails a link that works once, for an hour; Accounts can send the same link. Only a hash
 * of the token is stored, one live link per account (a new one replaces it), apart from invitations: someone who
 * hasn't accepted their invitation yet gets that invitation again instead, so their link keeps working.
 */
final class PasswordResets
{
    /** How long a link works */
    public const MINUTES = 60;

    /** Requests an hour from the sign-in page: per email address, and per connection */
    private const PER_EMAIL = 3;
    private const PER_IP = 10;

    /**
     * Counts one request from the sign-in page. Minutes to wait when the email address or the connection has asked
     * too often this hour (whether or not it has an account, so the answer says nothing), else 0.
     */
    public static function throttle(string $guard, string $email): int
    {
        $limits = [
            "password-reset:{$guard}:" . Str::lower(trim($email)) => self::PER_EMAIL,
            "password-reset:{$guard}:ip:" . request()->ip() => self::PER_IP,
        ];
        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
            }
        }
        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 3600);
        }

        return 0;
    }

    /**
     * The sign-in page's request. An account that may sign in gets a reset link; one still waiting on its
     * invitation gets that invitation again (the same link). An unknown, suspended or removed address, or a lead
     * with no portal account, gets nothing. Returns what was sent, for tests: the page says the same every time.
     */
    public static function request(string $guard, string $email): ?string
    {
        $email = trim($email);
        $row = self::table($guard)->where('email', $email)->first();
        if ($row && $row->status === 'Active' && $row->lifecycle_stage === 'Onboarding' && $row->invitation_token) {
            Mail::to($row->email)->locale(self::locale($guard, $row))
                ->send(new UserInvitation($row->first_name, $row->invitation_token, $guard === SignIn::CLIENT));
            SignIn::audit($guard, $row->id, 'INVITATION_RESENT', 'Asked for a password reset before setting up the account: the invitation was sent again.');

            return 'invitation';
        }

        $account = SignIn::accountByEmail($guard, $email);
        if (! $account) {
            return null;
        }
        self::send($guard, $account);
        SignIn::audit($guard, $account->id, 'PASSWORD_RESET_REQUESTED', 'Asked for a password reset link on the sign-in page.');

        return 'reset';
    }

    /** Emails a new reset link. $by: the staff member sending it from Accounts. */
    public static function send(string $guard, object $account, ?string $by = null): void
    {
        $token = self::issue($guard, (string) $account->id, $by);
        Mail::to($account->email)->locale(self::locale($guard, $account))
            ->send(new PasswordResetMail($account->first_name, $token, $guard === SignIn::CLIENT));
    }

    /** A new link for this account; earlier unused ones stop working. Returns the token for the email. */
    public static function issue(string $guard, string $ownerId, ?string $by = null): string
    {
        $token = Str::random(64);
        DB::transaction(function () use ($guard, $ownerId, $by, $token) {
            DB::table('password_reset_links')->where('guard', $guard)->where('owner_id', $ownerId)->whereNull('used_at')->delete();
            DB::table('password_reset_links')->insert([
                'guard' => $guard, 'owner_id' => $ownerId, 'token_hash' => self::hash($token), 'requested_by' => $by,
                'ip_address' => request()?->ip(), 'expires_at' => now()->addMinutes(self::MINUTES), 'created_at' => now(),
            ]);
        });

        return $token;
    }

    /** The account a link opens, while the link works and the account may sign in */
    public static function account(string $guard, string $token): ?object
    {
        $link = self::link($guard, $token)->first();

        return $link ? SignIn::account($guard, $link->owner_id) : null;
    }

    /**
     * Sets the new password and uses the link up, in one transaction (the link row is locked, so two tabs can't
     * both use it). A new remember token ends "remember me" sign-ins elsewhere. Returns the account, or null when
     * the link no longer works.
     */
    public static function complete(string $guard, string $token, string $password): ?object
    {
        $account = DB::transaction(function () use ($guard, $token, $password) {
            $link = self::link($guard, $token)->lockForUpdate()->first();
            $account = $link ? SignIn::account($guard, $link->owner_id) : null;
            if (! $account) {
                return null;
            }
            $account->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('password_reset_links')->where('id', $link->id)->update(['used_at' => now()]);
            DB::table('password_reset_links')->where('guard', $guard)->where('owner_id', $account->id)->whereNull('used_at')->delete();

            return $account;
        });
        if ($account) {
            SignIn::audit($guard, $account->id, 'PASSWORD_RESET', 'Chose a new password from an emailed reset link.');
        }

        return $account;
    }

    private static function link(string $guard, string $token)
    {
        return DB::table('password_reset_links')->where('guard', $guard)->where('token_hash', self::hash($token))
            ->whereNull('used_at')->where('expires_at', '>', now());
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function table(string $guard)
    {
        return $guard === SignIn::CLIENT
            ? DB::table('client_users')->whereNull('deleted_at')
            : DB::table('users');
    }

    /** Staff read English; a client reads their organisation's language, as with invitations (Accounts) */
    private static function locale(string $guard, object $account): string
    {
        return MailLocale::forRecipient($guard === SignIn::CLIENT
            ? DB::table('organizations')->where('id', $account->organization_id)->value('preferred_language')
            : null);
    }
}
