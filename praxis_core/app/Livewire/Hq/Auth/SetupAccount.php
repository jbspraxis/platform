<?php

namespace App\Livewire\Hq\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Staff account setup link (/hq/setup/{token}): a newly invited staff member sets their
 * password. Clients use ClientOs\SetupAccount on the portal instead.
 */
#[Layout('components.layouts.auth')]
class SetupAccount extends Component
{
    // Locked: the browser cannot swap the token. Only display fields are kept in
    // component state; the user row is looked up again from the token on save.
    #[Locked]
    public $token;

    #[Locked]
    public $firstName = '';

    #[Locked]
    public $email = '';

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
        $this->email = $user->email;
    }

    private function invitedUser()
    {
        return DB::table('users')
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
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make($this->password),
                'lifecycle_stage' => 'Active',
                'status' => 'Active',
                'invitation_token' => null, // Burn the token
                'email_verified_at' => now(),
                'updated_at' => now()
            ]);

            // Log the initialization in the forensic audit
            DB::table('system_audit_logs')->insert([
                'user_id' => $user->id,
                'action' => 'AUTH_INIT_KEY',
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });

        Auth::guard('web')->loginUsingId($user->id);
        Session::regenerate();

        // The workspace is open to every staff member; the admin dashboard needs clearance 4
        return redirect()->route('hq.dash.workspace');
    }

    public function render()
    {
        return view('livewire.hq.auth.setup-account');
    }
}
