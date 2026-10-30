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
     * Switches the card between sign-in and the password reset request.
     */
    public function toggleView($mode)
    {
        $this->viewMode = $mode;
        $this->resetErrorBag();
        $this->recoveryMessage = '';
        $this->password = ''; // Purge password field for security
    }

    /**
     * Signs a staff member in.
     */
    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required'
        ], $this->fieldMessages());

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
                $this->addError('client_redirect', 'This sign-in is for JBS Praxis staff. Clients sign in to the Client Portal.');
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
        $this->addError('auth', "That email address and password don’t match. Check them and try again.");
    }

    /**
     * Records a password reset request (the reset email is not built yet, see the TODO below).
     */
    public function initiateRecovery()
    {
        $this->validate(['email' => 'required|email'], $this->fieldMessages());

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

        // The same message whether or not the address has an account, so nobody can test which emails exist.
        // No email is sent yet, so the message says so plainly instead of promising a link.
        $this->recoveryMessage = "Thank you. Password reset by email isn’t available yet, so ask an administrator to reset your password.";
    }

    /** Plain validation messages (docs/BRAND.md section 12). */
    protected function fieldMessages(): array
    {
        return [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter an email address in the format name@example.com.',
            'password.required' => 'Enter your password.',
        ];
    }

    /** Field names in validation messages: the labels people see, not the property names (docs/BRAND.md sections 5 and 12) */
    protected function validationAttributes(): array
    {
        return [
            'email' => 'email address',
            'password' => 'password',
        ];
    }

    public function render()
    {
        return view('livewire.hq.auth.login-terminal');
    }
}