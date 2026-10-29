<?php

namespace App\Livewire\Hq\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Layout('components.layouts.auth')]
class LoginTerminal extends Component
{
    use \App\Livewire\Concerns\ThrottlesLogins;

    // --- AUTH STATE ---
    #[Validate('required|email')]
    public $email = '';

    public $password = '';
    
    // --- UI STATE ---
    public $viewMode = 'login'; // 'login' or 'recovery'
    public $recoveryMessage = '';

    /**
     * Toggles the HUD between Login and Recovery modes.
     */
    public function toggleView($mode)
    {
        $this->viewMode = $mode;
        $this->resetErrorBag();
        $this->recoveryMessage = '';
        $this->password = ''; // Purge password field for security
    }

    /**
     * The primary authentication engine.
     */
    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($this->tooManyLoginAttempts('hq', 'auth')) {
            return;
        }

        // 1. Attempt login
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password, 'status' => 'Active'])) {
            $this->clearLoginAttempts('hq');
            $user = Auth::user();
            
            // ==========================================
            // SECURITY GATE: STAFF ONLY (blocks clients and leads)
            // ==========================================
            if (! \App\Http\Middleware\HqStaff::allows($user)) {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();
                
                // Throw the specific error that triggers the redirect button in the UI
                $this->addError('client_redirect', 'UNAUTHORIZED NODE: Client clearance detected. Please route through the Client Portal.');
                return;
            }
            // ==========================================

            // HQ CLEARANCE VERIFIED - Proceed with session
            session()->regenerate();
            
            // 2. Update Last Login Timestamp
            $user->update(['last_login_at' => now()]);

            // 3. Log the system action (Forensic Audit)
            DB::table('system_audit_logs')->insert([
                'user_id' => $user->id,
                'action' => 'AUTH_LOGIN',
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            // 4. Route to the Command Overview (dashboard.view) or the personal workspace
            return redirect()->route($user->can('dashboard.view') ? 'hq.dash.admin' : 'hq.dash.workspace');
        }

        // Brute force is handled by the rate limit above (5 tries per email and IP per 15 minutes)
        $this->recordFailedLogin('hq');
        $this->addError('auth', '> ERR: CREDENTIALS REJECTED OR CLEARANCE REVOKED.');
    }

    /**
     * Generates a Zero-Knowledge recovery token.
     */
    public function initiateRecovery()
    {
        $this->validate(['email' => 'required|email']);

        $user = DB::table('users')->where('email', $this->email)->first();

        // Every users row is staff (clients live in client_users and reset via Client OS)
        if ($user) {
            $token = Str::random(60);

            DB::table('users')->where('id', $user->id)->update([
                'invitation_token' => $token,
                'updated_at' => now()
            ]);

            // TODO: Hook this up to the HQ key recovery email later
            // Mail::to($user->email)->send(new \App\Mail\HqKeyRecovery($user->first_name, $token));
        }

        // Universal success message prevents bad actors from enumerating valid internal emails
        $this->recoveryMessage = '> RECOVERY TRANSMISSION SENT. If this node is registered, check your comms channel for the secure link.';
    }

    public function render()
    {
        return view('livewire.hq.auth.login-terminal');
    }
}