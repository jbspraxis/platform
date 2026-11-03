<?php

namespace App\Livewire\ClientOs;

use App\Support\SignIn\PasswordResets;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A password reset link for a Client Portal account (/reset/{token}, docs/SIGN_IN.md section 8): choose a new
 * password and sign in. A link that has expired or was used shows how to ask for a new one.
 */
#[Layout('components.layouts.portal')]
class ResetPassword extends Component
{
    use PortalLanguage;

    // Locked: the browser cannot swap the token. The account is looked up again from the token on save.
    #[Locked]
    public $token;

    #[Locked]
    public $email = '';

    #[Locked]
    public $expired = false;

    public $password = '';
    public $passwordConfirmation = '';

    public function mount($token)
    {
        $this->token = $token;

        $account = PasswordResets::account(SignIn::CLIENT, $token);
        $this->expired = $account === null;
        $this->email = $account?->email ?? '';

        // The organisation's preferred language, unless the person already chose one
        $this->applyOrganisationLocale($account ? DB::table('organizations')->where('id', $account->organization_id)->value('preferred_language') : null);
    }

    protected function messages(): array
    {
        return (array) __('portal.validation.setup');
    }

    protected function languageReturnUrl(): string
    {
        return route('client.password.reset', ['token' => $this->token]);
    }

    public function save()
    {
        $this->validate([
            'password' => 'required|string|min:8',
            'passwordConfirmation' => 'required|same:password',
        ], $this->messages());

        $client = PasswordResets::complete(SignIn::CLIENT, $this->token, $this->password);
        if (! $client) {
            $this->reset('password', 'passwordConfirmation');
            $this->expired = true;

            return null;
        }

        Auth::guard('client')->login($client);
        Session::regenerate();
        SignIn::markFresh();
        $client->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('client.dashboard');
    }

    public function render()
    {
        return view('livewire.client-os.reset-password')->title(__('portal.reset.page_title'));
    }
}
