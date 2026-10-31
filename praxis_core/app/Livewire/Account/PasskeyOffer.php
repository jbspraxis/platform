<?php

namespace App\Livewire\Account;

use App\Support\SignIn\PasskeyRefused;
use App\Support\SignIn\Passkeys;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The card after a password sign-in by someone with no passkey (owner, 31 Oct 2026; docs/SIGN_IN.md section 2):
 * "Sign in faster next time", on HQ (top of every screen) and the Client Portal (top of the dashboard). It shows
 * only while the sign-in is fresh (10 minutes) and only on a device with a fingerprint, face or screen lock built
 * in. "Not now" hides it on this device for 14 days (the browser keeps that), like the install card.
 */
class PasskeyOffer extends Component
{
    #[Locked]
    public string $guard = SignIn::HQ;

    #[Locked]
    public bool $done = false;

    public function mount(string $guard = SignIn::HQ): void
    {
        abort_unless(SignIn::valid($guard), 404);
        $this->guard = $guard;
    }

    /** Whether this session has a card to offer; the layouts ask before placing the component */
    public static function due(string $guard): bool
    {
        return (bool) session('sign_in.offer_passkey.' . $guard);
    }

    public function creationOptions(): ?array
    {
        $account = Auth::guard($this->guard)->user();
        if (! $account || ! SignIn::isFresh()) {
            $this->dismiss();

            return null;
        }

        return Passkeys::creationOptions($this->guard, $account);
    }

    public function save($credential): void
    {
        $account = Auth::guard($this->guard)->user();
        if (! $account || ! SignIn::isFresh()) {
            $this->dismiss();

            return;
        }
        try {
            $passkey = Passkeys::add($this->guard, $account, is_array($credential) ? $credential : [], request()->userAgent());
        } catch (PasskeyRefused $e) {
            $this->addError('passkey', __('sign_in.passkey.' . $e->reason));

            return;
        }
        session()->forget('sign_in.offer_passkey.' . $this->guard);
        SignIn::audit($this->guard, (string) $account->getAuthIdentifier(), 'PASSKEY_ADDED', "Added a passkey ({$passkey->name}) from the card after signing in.");
        $this->done = true;
    }

    public function problem($kind): void
    {
        $this->addError('passkey', __('sign_in.passkey.' . (in_array($kind, ['exists', 'unsupported'], true) ? $kind : 'cancelled')));
    }

    public function dismiss(): void
    {
        session()->forget('sign_in.offer_passkey.' . $this->guard);
    }

    public function render()
    {
        $account = Auth::guard($this->guard)->user();
        $show = $this->done || ($account && session('sign_in.offer_passkey.' . $this->guard) && SignIn::isFresh()
            && ! Passkeys::has($this->guard, (string) $account->getAuthIdentifier()));

        return view('livewire.account.passkey-offer', ['show' => $show]);
    }
}
