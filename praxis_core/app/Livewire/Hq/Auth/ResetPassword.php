<?php

namespace App\Livewire\Hq\Auth;

use App\Support\SignIn\PasswordResets;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A password reset link for staff (/reset/{token}, docs/SIGN_IN.md section 8): choose a new password and sign in.
 * A link that has expired or was used shows how to ask for a new one. Clients use ClientOs\ResetPassword.
 */
#[Layout('components.layouts.auth')]
#[Title('Choose a new password')]
class ResetPassword extends Component
{
    // Locked: the browser cannot swap the token. The account is looked up again from the token on save.
    #[Locked]
    public $token;

    #[Locked]
    public $firstName = '';

    #[Locked]
    public $email = '';

    #[Locked]
    public $expired = false;

    public $password = '';
    public $passwordConfirmation = '';

    public function mount($token)
    {
        $this->token = $token;

        $account = PasswordResets::account(SignIn::HQ, $token);
        $this->expired = $account === null;
        $this->firstName = $account?->first_name ?? '';
        $this->email = $account?->email ?? '';
    }

    public function save()
    {
        $this->validate([
            'password' => 'required|string|min:8',
            'passwordConfirmation' => 'required|same:password',
        ], [
            'password.required' => 'Choose a password.',
            'password.min' => 'Use at least 8 characters.',
            'passwordConfirmation.required' => 'Type your password again to confirm it.',
            'passwordConfirmation.same' => "The two passwords don’t match. Type the same password in both fields.",
        ]);

        $account = PasswordResets::complete(SignIn::HQ, $this->token, $this->password);
        if (! $account) {
            $this->reset('password', 'passwordConfirmation');
            $this->expired = true;

            return null;
        }

        Auth::guard('web')->login($account);
        Session::regenerate();
        SignIn::markFresh();
        $account->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('hq.dash.workspace');
    }

    public function render()
    {
        return view('livewire.hq.auth.reset-password');
    }
}
