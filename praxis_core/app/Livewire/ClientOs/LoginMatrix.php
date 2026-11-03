<?php

namespace App\Livewire\ClientOs;

use App\Support\Access;
use App\Support\SignIn\Passkeys;
use App\Support\SignIn\PasswordResets;
use App\Support\SignIn\SignIn;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\App;
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
    // A passkey or the PIN instead of the password (docs/SIGN_IN.md)
    use \App\Livewire\Concerns\SignsInWithPasskeyOrPin;
    use PortalLanguage;

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

    public function mount()
    {
        // A language the person chose, else their browser's (English or French) for this page only: the
        // organisation's preference applies once they sign in, so nothing is stored in the session here.
        App::setLocale($this->chosenPortalLocale() ?? (request()->getPreferredLanguage(['en', 'fr']) === 'fr' ? 'fr' : 'en'));

        // ?forgot=1 (from an expired reset link) opens the reset request
        if (request()->boolean('forgot')) {
            $this->viewState = 'forgot_password';
        }
    }

    protected function messages(): array
    {
        return (array) __('portal.validation.login');
    }

    protected function languageReturnUrl(): string
    {
        return route('client.login');
    }

    public function switchState($state)
    {
        $this->viewState = $state;
        $this->authMessage = '';
        $this->usePin = false;
        $this->pin = '';
    }

    // --- 1. AUTHENTICATION ENGINE ---
    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($this->tooManyLoginAttempts('client')) {
            // The shared message is English only: say the same in the page's language
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($this->loginThrottleKey('client')) / 60));
            $this->resetErrorBag('email');
            $this->addError('email', trans_choice('portal.login.too_many', $minutes, ['minutes' => $minutes]));
            return;
        }

        // Client accounts only (client_users). Leads have no password, so they cannot sign in.
        if (Auth::guard('client')->attempt(['email' => $this->email, 'password' => $this->password, 'status' => 'Active'])) {
            return $this->openSession(Auth::guard('client')->user(), 'password');
        } else {
            $this->recordFailedLogin('client');
            $this->addError('email', __('portal.login.failed'));
        }
    }

    protected function signInGuard(): string
    {
        return SignIn::CLIENT;
    }

    protected function signInErrorField(): string
    {
        return 'email';
    }

    /** A passkey or the PIN proved who this is: the same session as after the password */
    protected function finishSignIn(Authenticatable $account, string $via)
    {
        Auth::guard('client')->login($account);

        return $this->openSession($account, $via);
    }

    private function openSession($client, string $via)
    {
        $this->clearLoginAttempts('client');
        $client->forceFill(['last_login_at' => now()])->save();
        session()->regenerate();
        SignIn::markFresh();
        // A password sign-in by someone with no passkey yet: offer one (the card on the dashboard)
        session(['sign_in.offer_passkey.client' => $via === 'password' && ! Passkeys::has(SignIn::CLIENT, (string) $client->id)]);
        if ($via !== 'password') {
            SignIn::audit(SignIn::CLIENT, (string) $client->id, 'AUTH_LOGIN', $via === 'pin' ? 'Signed in to the Client Portal with their PIN.' : 'Signed in to the Client Portal with a passkey.');
        }

        return redirect()->route('client.dashboard');
    }

    // "Forgot your password?": emails a reset link, or the invitation again to someone who hasn't set up their
    // account yet (App\Support\SignIn\PasswordResets, docs/SIGN_IN.md section 8). Leads have no account: nothing goes.
    public function sendResetLink()
    {
        $this->validate(['resetEmail' => 'required|email']);

        if ($minutes = PasswordResets::throttle(SignIn::CLIENT, $this->resetEmail)) {
            $this->addError('resetEmail', trans_choice('portal.login.reset_too_many', $minutes, ['minutes' => $minutes]));
            return;
        }

        // A failed send is logged; the page still says the same, so nobody can test which emails have an account
        rescue(fn () => PasswordResets::request(SignIn::CLIENT, $this->resetEmail));

        $this->authMessage = __('portal.login.reset_done', ['minutes' => PasswordResets::MINUTES]);
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
            session()->flash('redline_error', __('portal.sos.limit'));
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

            // 3. Notify the CRM team immediately
            $notifications = [];
            foreach(Access::staffWith('crm.edit') as $adminId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(),
                    'user_id' => $adminId,
                    'title' => 'Urgent support request from a new organisation',
                    'message' => "{$this->sosName} ({$this->sosTitle}, {$this->sosCompany}) asked for urgent support. Call them on {$this->sosPhone} or write to {$this->sosEmail}. The request is in Leads.",
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
        return view('livewire.client-os.login-matrix')->title(__('portal.login.page_title'));
    }
}