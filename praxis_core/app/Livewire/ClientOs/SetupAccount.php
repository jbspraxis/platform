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
    // Locked: the browser cannot swap the token. Only display fields are kept in
    // component state; the user row is looked up again from the token on save.
    #[Locked]
    public $token;

    #[Locked]
    public $firstName = '';

    public $password = '';
    public $passwordConfirmation = '';

    public function mount($token)
    {
        $this->token = $token;

        $user = $this->invitedUser();
        if (!$user) {
            abort(404, 'CRYPTOGRAPHIC LINK INVALID, EXPIRED, OR ALREADY CONSUMED.');
        }
        $this->firstName = $user->first_name;
    }

    private function invitedUser()
    {
        return DB::table('client_users')
            ->where('invitation_token', $this->token)
            ->where('lifecycle_stage', 'Onboarding')
            ->first();
    }

    public function lockAndAuthenticate()
    {
        $this->validate([
            'password' => 'required|string|min:8',
            'passwordConfirmation' => 'required|same:password'
        ], [
            'passwordConfirmation.same' => '> ERR: Cryptographic keys do not match.'
        ]);

        $user = $this->invitedUser();
        if (!$user) {
            abort(404, 'CRYPTOGRAPHIC LINK INVALID, EXPIRED, OR ALREADY CONSUMED.');
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
        return view('livewire.client-os.setup-account'); 
    }
}