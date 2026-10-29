<?php

namespace App\Livewire\Tracker\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.client-light')] // We can reuse the light layout or a blank one
class Login extends Component
{
    public $email = '';
    public $password = '';

    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Attempt login using the strictly isolated 'tracker' guard
        if (Auth::guard('tracker')->attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => 1])) {
            
            $user = Auth::guard('tracker')->user();
            
            // Find the first project this user is assigned to
            $firstAccess = DB::table('tracker_user_access')->where('user_id', $user->id)->first();
            
            if ($firstAccess) {
                // Redirect directly to their assigned matrix
                return redirect()->route('taskforce.matrix', $firstAccess->tracker_id);
            }

            Auth::guard('tracker')->logout();
            session()->flash('error', 'You have no active project assignments. Contact HQ.');
            return redirect()->route('taskforce.login');
        }

        $this->addError('email', 'Invalid credentials or inactive account.');
    }

    public function render()
    {
        return view('livewire.tracker.auth.login');
    }
}