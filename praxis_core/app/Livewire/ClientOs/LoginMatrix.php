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

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password, 'status' => 'Active'])) {
            return redirect()->route('client.dashboard');
        } else {
            $this->addError('email', '> ERR: INVALID CREDENTIALS OR SUSPENDED NODE.');
        }
    }

    public function sendResetLink()
    {
        $this->validate(['resetEmail' => 'required|email']);
        $status = Password::sendResetLink(['email' => $this->resetEmail]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->authMessage = '> RECOVERY PROTOCOL INITIATED. CHECK INBOX.';
            $this->resetEmail = '';
        } else {
            $this->addError('resetEmail', '> ERR: IDENTITY NOT FOUND IN VAULT.');
        }
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