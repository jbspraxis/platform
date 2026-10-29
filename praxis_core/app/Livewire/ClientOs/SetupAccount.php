<?php

namespace App\Livewire\ClientOs;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

#[Layout('components.layouts.portal')]
class SetupAccount extends Component
{
    public $token;
    public $user;
    
    public $password = '';
    public $passwordConfirmation = '';

    public function mount($token)
    {
        $this->token = $token;
        
        // Strict lookup using the correct lifecycle column
        $this->user = DB::table('users')
            ->where('invitation_token', $this->token)
            ->where('lifecycle_stage', 'Onboarding') // FIXED
            ->first();
            
        if (!$this->user) {
            abort(404, 'CRYPTOGRAPHIC LINK INVALID, EXPIRED, OR ALREADY CONSUMED.');
        }
    }

    public function lockAndAuthenticate()
    {
        $this->validate([
            'password' => 'required|string|min:8',
            'passwordConfirmation' => 'required|same:password'
        ], [
            'passwordConfirmation.same' => '> ERR: Cryptographic keys do not match.'
        ]);

        DB::transaction(function () {
            DB::table('users')->where('id', $this->user->id)->update([
                'password' => Hash::make($this->password),
                'lifecycle_stage' => 'Active', // Graduate them
                'invitation_token' => null, 
                'email_verified_at' => now(),
                'updated_at' => now()
            ]);
        });

        // Secure Authentication & Session Regeneration
        Auth::loginUsingId($this->user->id);
        Session::regenerate();

        return redirect()->route('client.dashboard');
    }

    public function render() 
    { 
        return view('livewire.client-os.setup-account'); 
    }
}