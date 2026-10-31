<?php

namespace App\Livewire\Concerns;

use App\Support\SignIn\PasskeyRefused;
use App\Support\SignIn\Passkeys;
use App\Support\SignIn\Pins;
use App\Support\SignIn\SignIn;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Signing in with a passkey or the PIN, on the HQ and Client Portal sign-in pages (docs/SIGN_IN.md section 2).
 * The page opens the session itself (finishSignIn), the same way as after a password.
 *
 * The browser asks passkeyOptions(), lets the person unlock their device, then hands the answer to
 * signInWithPasskey(). The PIN goes with the email address to signInWithPin(), where Pins counts wrong tries per
 * account and this counts them per connection (30 in 15 minutes), so one machine can't try a PIN on many accounts.
 */
trait SignsInWithPasskeyOrPin
{
    public bool $usePin = false;

    public string $pin = '';

    /** 'web' (HQ) or 'client' (Client Portal) */
    abstract protected function signInGuard(): string;

    /** Opens the session for an account a passkey or PIN proved; adds an error instead when it may not sign in */
    abstract protected function finishSignIn(Authenticatable $account, string $via);

    /** The error the page shows above its form */
    abstract protected function signInErrorField(): string;

    /** Switches the form between the password and the PIN */
    public function choosePin(bool $on): void
    {
        $this->usePin = $on;
        $this->pin = '';
        $this->password = '';
        $this->resetErrorBag();
    }

    public function passkeyOptions(): array
    {
        $this->resetErrorBag();

        return Passkeys::requestOptions($this->signInGuard());
    }

    public function signInWithPasskey($credential)
    {
        if (! is_array($credential)) {
            $this->addError($this->signInErrorField(), __('sign_in.passkey.failed'));

            return null;
        }
        try {
            $account = Passkeys::signIn($this->signInGuard(), $credential);
        } catch (PasskeyRefused $e) {
            $this->addError($this->signInErrorField(), __('sign_in.passkey.' . $e->reason));

            return null;
        }

        return $this->finishSignIn($account, 'passkey');
    }

    /** The browser couldn't make an answer: cancelled, timed out, or no passkeys here */
    public function passkeyProblem($kind): void
    {
        $this->resetErrorBag();
        $this->addError($this->signInErrorField(), __('sign_in.passkey.' . ($kind === 'unsupported' ? 'unsupported' : 'cancelled')));
    }

    public function signInWithPin()
    {
        $this->resetErrorBag();
        $field = $this->signInErrorField();
        $email = trim((string) $this->email);
        $pin = (string) $this->pin;

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', __($email === '' ? 'sign_in.login.email_required' : 'sign_in.login.email_invalid'));

            return null;
        }
        if (! preg_match('/^\d{4}$/', $pin)) {
            $this->addError('pin', __('sign_in.errors.pin_digits'));

            return null;
        }

        $guard = $this->signInGuard();
        $connection = 'sign-in-pin:' . $guard . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($connection, 30)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($connection) / 60));
            $this->addError($field, trans_choice('sign_in.errors.too_many', $minutes, ['minutes' => $minutes]));

            return null;
        }

        $account = SignIn::accountByEmail($guard, $email);
        if (! $account) {
            Pins::spendTime($pin);
            $outcome = ['result' => 'unavailable', 'wait' => 0, 'left' => 0];
        } else {
            $outcome = Pins::check($guard, (string) $account->getAuthIdentifier(), $pin);
        }

        if ($outcome['result'] === 'ok') {
            return $this->finishSignIn($account, 'pin');
        }

        $this->pin = '';
        if ($outcome['result'] === 'wait') {
            $this->addError($field, __('sign_in.errors.pin_wait', ['wait' => self::sayWait($outcome['wait'])]));

            return null;
        }

        RateLimiter::hit($connection, 900);
        if ($outcome['result'] === 'switched_off') {
            SignIn::audit($guard, (string) $account->getAuthIdentifier(), 'PIN_SWITCHED_OFF', 'Their sign-in PIN was switched off after too many wrong tries.');
            $this->addError($field, __('sign_in.errors.pin_switched_off'));
        } elseif ($outcome['result'] === 'wrong' && $outcome['left'] <= Pins::FREE_TRIES) {
            $this->addError($field, trans_choice('sign_in.errors.pin_wrong_left', $outcome['left'], ['left' => $outcome['left']]));
        } else {
            $this->addError($field, __('sign_in.errors.pin_failed'));
        }

        return null;
    }

    /** "45 seconds", "3 minutes" */
    private static function sayWait(int $seconds): string
    {
        return $seconds < 60
            ? trans_choice('sign_in.errors.seconds', $seconds, ['count' => $seconds])
            : trans_choice('sign_in.errors.minutes', $minutes = (int) ceil($seconds / 60), ['count' => $minutes]);
    }
}
