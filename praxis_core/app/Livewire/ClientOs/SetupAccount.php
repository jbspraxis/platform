<?php

namespace App\Livewire\ClientOs;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

#[Layout('components.layouts.portal')]
class SetupAccount extends Component
{
    use PortalLanguage;

    // Locked: the browser cannot swap the token. Only display fields are kept in
    // component state; the user row is looked up again from the token on save.
    #[Locked]
    public $token;

    #[Locked]
    public $firstName = '';

    #[Locked]
    public $lastName = '';

    public $password = '';
    public $passwordConfirmation = '';

    public function mount($token)
    {
        $this->token = $token;

        $user = $this->invitedUser();
        if (!$user) {
            abort(404, __('portal.errors.setup_link'));
        }
        $this->firstName = $user->first_name;
        $this->lastName = (string) $user->last_name;

        // The organisation's preferred language, unless the person already chose one
        $this->applyOrganisationLocale(DB::table('organizations')->where('id', $user->organization_id)->value('preferred_language'));
    }

    private function invitedUser()
    {
        return DB::table('client_users')
            ->where('invitation_token', $this->token)
            ->where('lifecycle_stage', 'Onboarding')
            ->first();
    }

    protected function messages(): array
    {
        return (array) __('portal.validation.setup');
    }

    protected function languageReturnUrl(): string
    {
        return route('client.setup', ['token' => $this->token]);
    }

    public function lockAndAuthenticate()
    {
        $this->validate([
            'password' => 'required|string|min:8',
            'passwordConfirmation' => 'required|same:password'
        ], $this->messages());

        $user = $this->invitedUser();
        if (!$user) {
            abort(404, __('portal.errors.setup_link'));
        }

        DB::transaction(function () use ($user) {
            DB::table('client_users')->where('id', $user->id)->update([
                'password' => Hash::make($this->password),
                'lifecycle_stage' => 'Active', // Graduate them
                'invitation_token' => null,
                'email_verified_at' => now(),
                'updated_at' => now()
            ]);
        });

        // Secure Authentication & Session Regeneration
        Auth::guard('client')->loginUsingId($user->id);
        Session::regenerate();

        return redirect()->route('client.dashboard');
    }

    public function render()
    {
        return view('livewire.client-os.setup-account')->title(__('portal.setup.page_title'));
    }
}
