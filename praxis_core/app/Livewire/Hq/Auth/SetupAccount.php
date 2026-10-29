<?php

namespace App\Livewire\Hq\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

#[Layout('components.layouts.auth')]
class SetupAccount extends Component
{
    public $token;
    public $user;
    
    public $password = '';
    public $passwordConfirmation = '';

    public function mount($token)
    {
        $this->token = $token;
        
        // Strict lookup: Token must match, and lifecycle must be Onboarding.
        $this->user = DB::table('users')
            ->where('invitation_token', $this->token)
            ->where('lifecycle_stage', 'Onboarding')
            ->first();
            
        if (!$this->user) {
            abort(404, 'CRYPTOGRAPHIC LINK INVALID, EXPIRED, OR ALREADY CONSUMED.');
        }

        // Security check: Ensure they are an HQ user, not a client.
        $role = DB::table('roles')->where('id', $this->user->role_id)->first();
        if ($role && strtolower($role->name) === 'client') {
            abort(403, 'UNAUTHORIZED CLEARANCE. Client nodes must route through Client OS.');
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
                'lifecycle_stage' => 'Active',
                'status' => 'Active',
                'invitation_token' => null, // Burn the token
                'email_verified_at' => now(),
                'updated_at' => now()
            ]);

            // Log the initialization in the forensic audit
            DB::table('system_audit_logs')->insert([
                'user_id' => $this->user->id,
                'action' => 'AUTH_INIT_KEY',
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });

        // Elevate into secure session
        Auth::loginUsingId($this->user->id);
        Session::regenerate();

        return redirect()->route('hq.dash.admin');
    }

    public function render() 
    { 
        return view('livewire.hq.auth.setup-account'); 
    }
}