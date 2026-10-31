<?php

namespace App\Livewire\Account;

use App\Support\SignIn\PasskeyRefused;
use App\Support\SignIn\Passkeys;
use App\Support\SignIn\Pins;
use App\Support\SignIn\SignIn;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A person's own passkeys and PIN (docs/SIGN_IN.md section 2): HQ → My profile (guard web) and the Client
 * Portal's Sign-in settings (guard client). Adding a passkey or setting a PIN needs a fresh sign-in (the last
 * 10 minutes) or the password typed again, so a session left open on someone's desk can't add a way in.
 * Removing needs nothing more: it only takes a way in away.
 */
class SignInMethods extends Component
{
    #[Locked]
    public string $guard = SignIn::HQ;

    public bool $settingPin = false;

    public string $newPin = '';

    public string $newPinAgain = '';

    public string $confirmPassword = '';

    /** What the password confirmation is for: 'passkey' or 'pin' */
    #[Locked]
    public ?string $needsPassword = null;

    #[Locked]
    public ?string $flash = null;

    public function mount(string $guard = SignIn::HQ): void
    {
        abort_unless(SignIn::valid($guard), 404);
        $this->guard = $guard;
        $this->account();
    }

    /** Options for adding a passkey, or null when the password is needed first */
    public function passkeyCreationOptions(): ?array
    {
        $this->flash = null;
        $this->resetErrorBag();
        if (! SignIn::isFresh()) {
            $this->askPassword('passkey');

            return null;
        }

        return Passkeys::creationOptions($this->guard, $this->account());
    }

    public function savePasskey($credential): void
    {
        $account = $this->account();
        if (! SignIn::isFresh()) {
            $this->askPassword('passkey');

            return;
        }
        try {
            $passkey = Passkeys::add($this->guard, $account, is_array($credential) ? $credential : [], request()->userAgent());
        } catch (PasskeyRefused $e) {
            $this->addError('passkey', __('sign_in.passkey.' . $e->reason));

            return;
        }
        session()->forget('sign_in.offer_passkey.' . $this->guard);
        SignIn::audit($this->guard, $this->ownerId(), 'PASSKEY_ADDED', "Added a passkey ({$passkey->name}).");
        $this->flash = __('sign_in.settings.passkey_added');
    }

    /** The browser made no passkey: cancelled, timed out, already one on this device, or not supported */
    public function passkeyProblem($kind): void
    {
        $this->addError('passkey', __('sign_in.passkey.' . (in_array($kind, ['exists', 'unsupported'], true) ? $kind : 'cancelled')));
    }

    public function removePasskey($id): void
    {
        $this->flash = null;
        $removed = Passkeys::remove($this->guard, $this->ownerId(), (int) $id);
        if ($removed) {
            SignIn::audit($this->guard, $this->ownerId(), 'PASSKEY_REMOVED', "Removed a passkey ({$removed->name}).");
            $this->flash = __('sign_in.settings.passkey_removed');
        }
    }

    public function startPin(): void
    {
        $this->flash = null;
        $this->resetErrorBag();
        if (! SignIn::isFresh()) {
            $this->askPassword('pin');

            return;
        }
        $this->settingPin = true;
    }

    public function cancelPin(): void
    {
        $this->settingPin = false;
        $this->reset('newPin', 'newPinAgain');
        $this->resetErrorBag();
    }

    public function savePin(): void
    {
        $this->flash = null;
        $this->resetErrorBag();
        if (! SignIn::isFresh()) {
            $this->settingPin = false;
            $this->askPassword('pin');

            return;
        }

        $why = Pins::weakReason($this->newPin);
        if ($why !== null) {
            $this->addError('newPin', __('sign_in.pin.weak.' . $why));

            return;
        }
        if ($this->newPinAgain !== $this->newPin) {
            $this->addError('newPinAgain', __('sign_in.pin.mismatch'));

            return;
        }

        $changed = Pins::status($this->guard, $this->ownerId()) === 'on';
        Pins::set($this->guard, $this->ownerId(), $this->newPin);
        SignIn::audit($this->guard, $this->ownerId(), $changed ? 'PIN_CHANGED' : 'PIN_SET', $changed ? 'Changed their sign-in PIN.' : 'Set a sign-in PIN.');
        $this->cancelPin();
        $this->flash = __('sign_in.settings.pin_saved');
    }

    public function removePin(): void
    {
        $this->flash = null;
        $this->cancelPin();
        if (Pins::remove($this->guard, $this->ownerId())) {
            SignIn::audit($this->guard, $this->ownerId(), 'PIN_REMOVED', 'Removed their sign-in PIN.');
            $this->flash = __('sign_in.settings.pin_removed');
        }
    }

    /** The password typed again: opens the 10-minute window, then carries on with what was asked */
    public function confirmIdentity(): void
    {
        $account = $this->account();
        $key = 'sign-in-confirm:' . $this->guard . ':' . $this->ownerId();
        $this->resetErrorBag();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('confirmPassword', __('sign_in.pin.password_too_many', ['minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60))]));

            return;
        }
        if ($this->confirmPassword === '') {
            $this->addError('confirmPassword', __('sign_in.pin.password_required'));

            return;
        }
        if (! Hash::check($this->confirmPassword, (string) $account->getAuthPassword())) {
            RateLimiter::hit($key, 600);
            $this->confirmPassword = '';
            $this->addError('confirmPassword', __('sign_in.pin.password_wrong'));

            return;
        }

        RateLimiter::clear($key);
        SignIn::markFresh();
        $this->confirmPassword = '';
        $for = $this->needsPassword;
        $this->needsPassword = null;
        // Creating a passkey needs a fresh press of the button (browsers ask for one), so only the PIN form opens by itself
        $this->settingPin = $for === 'pin';
    }

    public function cancelConfirm(): void
    {
        $this->needsPassword = null;
        $this->confirmPassword = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $date = fn ($value) => $value ? Carbon::parse($value)->timezone('Africa/Lagos')->locale(app()->getLocale())->isoFormat('D MMM YYYY') : null;

        return view('livewire.account.sign-in-methods', [
            'passkeys' => Passkeys::of($this->guard, $this->ownerId())->map(fn ($p) => (object) [
                'id' => $p->id,
                'name' => $p->name,
                'synced' => (bool) $p->backed_up,
                'added' => $date($p->created_at),
                'used' => $date($p->last_used_at),
            ]),
            'pinStatus' => Pins::status($this->guard, $this->ownerId()),
            'maxPasskeys' => Passkeys::MAX_PER_ACCOUNT,
        ]);
    }

    private function askPassword(string $for): void
    {
        $this->needsPassword = $for;
        $this->confirmPassword = '';
    }

    /** The signed-in person on this component's guard; nobody else's passkeys or PIN are ever reached */
    private function account(): Authenticatable
    {
        $account = Auth::guard($this->guard)->user();
        abort_unless($account && ($account->status ?? null) === 'Active', 403);

        return $account;
    }

    private function ownerId(): string
    {
        return (string) $this->account()->getAuthIdentifier();
    }
}
