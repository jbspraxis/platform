<?php

namespace App\Livewire\ClientOs;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

#[Layout('components.layouts.portal')]
class LoginMatrix extends Component
{
    use \App\Livewire\Concerns\ThrottlesLogins;

    public $viewState = 'login'; // login, forgot_password, redline_sos, redline_success

    // Auth State
    public $email;
    public $password;

    // Forgot Password State
    public $resetEmail;
    public $authMessage = '';

    // Redline SOS State
    public $sosName = '';
    public $sosCompany = '';
    public $sosEmail = '';
    public $sosPhone = '';
    public $sosTitle = '';
    public $sosDescription = '';

    public function switchState($state)
    {
        $this->viewState = $state;
        $this->authMessage = '';
    }

    // --- 1. AUTHENTICATION ENGINE ---
    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($this->tooManyLoginAttempts('client')) {
            return;
        }

        // Client accounts only (client_users). Leads have no password, so they cannot sign in.
        if (Auth::guard('client')->attempt(['email' => $this->email, 'password' => $this->password, 'status' => 'Active'])) {
            $this->clearLoginAttempts('client');
            Auth::guard('client')->user()->forceFill(['last_login_at' => now()])->save();
            session()->regenerate();
            return redirect()->route('client.dashboard');
        } else {
            $this->recordFailedLogin('client');
            $this->addError('email', '> ERR: INVALID CREDENTIALS OR SUSPENDED NODE.');
        }
    }

    // There is no self-service reset page yet, so a request alerts HQ, who re-send
    // a setup link. The reply is the same whether or not the email has an account.
    public function sendResetLink()
    {
        $this->validate(['resetEmail' => 'required|email']);

        $rateKey = 'client-reset:' . request()->ip();
        if (! RateLimiter::tooManyAttempts($rateKey, 3)) {
            RateLimiter::hit($rateKey, 3600);

            // Only portal accounts (a password set), not leads
            $client = DB::table('client_users')
                ->where('email', $this->resetEmail)
                ->whereNotNull('password')
                ->whereNull('deleted_at')
                ->select('first_name', 'last_name', 'email')
                ->first();
            if ($client) {
                $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
                $notifications = [];
                foreach ($admins as $admin) {
                    $notifications[] = [
                        'id' => (string) Str::uuid(), 'user_id' => $admin->id,
                        'title' => 'CLIENT PASSWORD RESET REQUESTED',
                        'message' => "Client {$client->first_name} {$client->last_name} ({$client->email}) asked to reset their Client OS password. Send them a new setup link.",
                        'type' => 'System', 'created_at' => now(),
                    ];
                }
                if (!empty($notifications)) DB::table('notifications')->insert($notifications);
            }
        }

        $this->authMessage = '> REQUEST RECEIVED. If this email has a Client OS account, the JBS Praxis team will send you a new access link.';
        $this->resetEmail = '';
    }

    // --- 2. THE REDLINE SOS LEAD INJECTION (ARMORED) ---
    public function submitRedlineSos()
    {
        $this->validate([
            'sosName' => 'required|string|max:100',
            'sosCompany' => 'required|string|max:150',
            'sosEmail' => 'required|email|max:100',
            'sosPhone' => 'required|string|max:30',
            'sosTitle' => 'required|string|max:100',
            'sosDescription' => 'required|string'
        ]);

        // THE DDOS SHIELD: 1 Request per 24 Hours per IP
        $ip = Request::ip();
        $rateKey = 'redline-sos-limit:' . $ip;

        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            session()->flash('redline_error', '> SECURITY HALT: Redline Protocol locked. Maximum 1 transmission per 24 hours allowed from this node.');
            return;
        }

        DB::transaction(function () {
            // 1. Inject into CRM (Organizations Table)
            $orgId = (string) Str::uuid();
            DB::table('organizations')->insert([
                'id' => $orgId,
                'name' => $this->sosCompany,
                'status' => 'Lead',
                'pipeline_stage' => 'Redline Emergency',
                'disqualification_reason' => 'REDLINE INITIATION: ' . Str::limit($this->sosDescription, 150),
                'created_at' => now()
            ]);

            // 2. Inject into Redline SOS Requests
            DB::table('redline_sos_requests')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'unregistered_email' => $this->sosEmail,
                'unregistered_company' => $this->sosCompany,
                'problem_description' => "CONTACT: {$this->sosName} ({$this->sosTitle}) | PHONE: {$this->sosPhone}\n\nISSUE: " . $this->sosDescription,
                'status' => 'Pending Triage',
                'created_at' => now()
            ]);

            // 3. Notify L4/L5 Command immediately
            $admins = DB::table('users')
                ->join('roles', 'users.role_id', '=', 'roles.id')
                ->where('roles.clearance_level', '>=', 4)
                ->select('users.id')
                ->get();
            
            $notifications = [];
            foreach($admins as $admin) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 
                    'user_id' => $admin->id,
                    'title' => 'CRITICAL: REDLINE SOS RECEIVED', 
                    'message' => "Emergency request from {$this->sosCompany}. Priority Triage Required.",
                    'type' => 'System', 
                    'created_at' => now()
                ];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });

        // Record the attempt and lock the IP for 24 hours (86400 seconds)
        RateLimiter::hit($rateKey, 86400);

        // Reset form and show success state
        $this->reset(['sosName', 'sosCompany', 'sosEmail', 'sosPhone', 'sosTitle', 'sosDescription']);
        $this->viewState = 'redline_success';
    }

    public function render()
    {
        return view('livewire.client-os.login-matrix');
    }
}