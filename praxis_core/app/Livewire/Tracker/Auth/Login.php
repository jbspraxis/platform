<?php

namespace App\Livewire\Tracker\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Sign-in for the delivery team's project tracker (staff and consultants): the same card on Mist as the HQ sign-in
#[Layout('components.layouts.auth')]
#[Title('Sign in to the project tracker')]
class Login extends Component
{
    use \App\Livewire\Concerns\ThrottlesLogins;

    public $email = '';
    public $password = '';

    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($this->tooManyLoginAttempts('tracker')) {
            return;
        }

        // Attempt login using the strictly isolated 'tracker' guard
        if (Auth::guard('tracker')->attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => 1])) {
            $this->clearLoginAttempts('tracker');
            session()->regenerate();

            $user = Auth::guard('tracker')->user();

            // Find the first project this person is assigned to
            $firstAccess = DB::table('tracker_user_access')->where('user_id', $user->id)->first();

            if ($firstAccess) {
                // Go straight to that project's tracker
                return redirect()->route('taskforce.matrix', $firstAccess->tracker_id);
            }

            Auth::guard('tracker')->logout();
            session()->flash('warning', 'You’re not assigned to a project yet. Ask your project lead to add you to one, then sign in again.');
            return redirect()->route('taskforce.login');
        }

        $this->recordFailedLogin('tracker');
        $this->addError('email', 'That email address and password don’t match an active account. Check them and try again, or ask your project lead for help.');
    }

    public function render()
    {
        return view('livewire.tracker.auth.login');
    }
}
