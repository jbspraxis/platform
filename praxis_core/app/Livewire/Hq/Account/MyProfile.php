<?php

namespace App\Livewire\Hq\Account;

use App\Support\Avatars;
use App\Support\Mail\Compose\Signatures;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * HQ → avatar → My profile (owner, 30 Oct 2026): your photo (HQ only, App\Support\Avatars), who you are in HQ
 * (name, job title, email, your primary role and the roles that only add access; read-only, from your HR record
 * and Settings → Accounts), your phone numbers (the same ones your email signature uses,
 * App\Support\Mail\Compose\Signatures) and your password. Every staff member can open it.
 */
#[Layout('components.layouts.hq')]
#[Title('My profile')]
class MyProfile extends Component
{
    use WithFileUploads;

    /** The photo, already cropped square in the browser */
    public $photo;

    #[Locked]
    public ?string $photoFlash = null;

    public string $mobile = '';
    public string $office = '';

    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    #[Locked]
    public ?string $numbersFlash = null;
    #[Locked]
    public ?string $passwordFlash = null;

    public function mount(): void
    {
        $numbers = Signatures::numbers(Auth::user());
        $this->mobile = (string) ($numbers['mobile'] ?? '');
        $this->office = (string) ($numbers['phone'] ?? '');
    }

    public function saveNumbers(): void
    {
        $this->numbersFlash = null;
        try {
            Signatures::setNumbers(Auth::user(), $this->office, $this->mobile);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key === 'phone' ? 'office' : $key, $messages[0]);
            }

            return;
        }
        $this->resetErrorBag(['office', 'mobile']);
        $this->audit('PROFILE_NUMBERS_UPDATED', 'Updated their phone numbers on My profile.');
        $this->numbersFlash = 'Phone numbers saved. Your email signature uses them from now on.';
    }

    public function savePhoto(): void
    {
        $this->photoFlash = null;
        $this->validate(['photo' => 'required|image|max:5120'], [
            'photo.required' => 'Choose a photo.',
            'photo.image' => 'Use a JPG, PNG or WebP photo.',
            'photo.max' => 'Use a photo under 5 MB.',
        ]);

        try {
            Avatars::store(Auth::user(), $this->photo);
        } catch (ValidationException $e) {
            $this->addError('photo', $e->errors()['photo'][0] ?? 'That photo couldn’t be saved.');

            return;
        } finally {
            rescue(fn () => $this->photo?->delete(), null, false);
            $this->photo = null;
        }

        $this->audit('PROFILE_PHOTO_UPDATED', 'Changed their photo on My profile.');
        $this->photoFlash = 'Photo saved. It shows next to your name across HQ.';
    }

    public function removePhoto(): void
    {
        Avatars::remove(Auth::user());
        $this->audit('PROFILE_PHOTO_REMOVED', 'Removed their photo on My profile.');
        $this->photoFlash = 'Photo removed. HQ shows your initials again.';
    }

    public function changePassword(): void
    {
        $this->passwordFlash = null;
        $user = Auth::user();
        $key = 'profile-password:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('currentPassword', 'Too many tries. Wait ' . ceil(RateLimiter::availableIn($key) / 60) . ' minutes and try again.');

            return;
        }

        $this->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:8|different:currentPassword',
            'newPasswordConfirmation' => 'required|same:newPassword',
        ], [
            'currentPassword.required' => 'Enter your current password.',
            'newPassword.required' => 'Choose a new password.',
            'newPassword.min' => 'Use at least 8 characters.',
            'newPassword.different' => 'Choose a password different from the current one.',
            'newPasswordConfirmation.required' => 'Type your new password again to confirm it.',
            'newPasswordConfirmation.same' => 'The two new passwords don’t match. Type the same password in both fields.',
        ]);

        if (! Hash::check($this->currentPassword, $user->password)) {
            RateLimiter::hit($key, 600);
            $this->reset('currentPassword');
            $this->addError('currentPassword', 'That isn’t your current password. Check it and try again.');

            return;
        }

        RateLimiter::clear($key);
        $user->forceFill(['password' => Hash::make($this->newPassword)])->save();
        session()->regenerate();
        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        $this->audit('PASSWORD_CHANGED', 'Changed their own password on My profile.');
        $this->passwordFlash = 'Password changed. Use the new one next time you sign in.';
    }

    public function render()
    {
        $user = Auth::user()->fresh(['roles']);
        $person = Signatures::person($user);
        $primary = $user->primaryRole();

        return view('livewire.hq.account.my-profile', [
            'user' => $user,
            'person' => $person,
            'primaryRole' => $primary?->name ?: 'Staff',
            'otherRoles' => $user->roles->reject(fn ($r) => $primary && $r->id === $primary->id)->pluck('name')->implode(', '),
            'hrPhone' => trim((string) $user->phone),
            'lastSignIn' => $user->last_login_at?->timezone('Africa/Lagos')->format('j F Y, H:i'),
            'canUseMail' => MailAccess::canUse($user),
        ]);
    }

    private function audit(string $action, string $description): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'target_table' => 'users',
            'target_id' => Auth::id(),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
