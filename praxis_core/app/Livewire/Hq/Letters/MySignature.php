<?php

namespace App\Livewire\Hq\Letters;

use App\Support\Letters\Signatures;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * HQ › Documents › My signature (docs/DOCUMENTS.md section 5). Each person draws their signature once with a
 * finger or a mouse, or uploads a photograph of one on paper. The browser turns either into a JPEG on white and
 * sends it here; it is stored encrypted, and nobody else can ever apply it.
 *
 * Saving one needs a sign-in less than ten minutes old, or the password typed again, like adding a passkey.
 */
#[Layout('components.layouts.hq')]
class MySignature extends Component
{
    /** data:image/jpeg;base64,… from the signature pad or the file the person chose */
    public string $image = '';

    public string $kind = 'drawn';

    public ?int $width = null;

    public ?int $height = null;

    public string $signatoryName = '';

    public string $signatoryTitle = '';

    public bool $confirmingRemoval = false;

    public string $confirmPassword = '';

    public bool $needsPassword = false;

    #[Locked]
    public ?string $flash = null;

    public function mount(): void
    {
        $user = Auth::user();
        $signature = Signatures::forUser($user);
        $this->signatoryName = $signature->signatory_name ?? trim($user->first_name . ' ' . $user->last_name);
        $this->signatoryTitle = $signature->signatory_title ?? (string) $user->job_title;
    }

    /** The pad and the file picker both end here */
    public function setImage(string $dataUri, string $kind, int $width, int $height): void
    {
        $this->image = $dataUri;
        $this->kind = $kind === 'uploaded' ? 'uploaded' : 'drawn';
        $this->width = $width;
        $this->height = $height;
        $this->resetErrorBag('image');
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $this->validate([
            'signatoryName' => ['required', 'string', 'max:150'],
            'signatoryTitle' => ['required', 'string', 'max:150'],
        ], [], ['signatoryName' => 'name', 'signatoryTitle' => 'role']);

        if ($this->image === '') {
            $this->addError('image', 'Draw your signature or choose an image of it first.');

            return;
        }
        if (! SignIn::isFresh()) {
            $this->needsPassword = true;

            return;
        }

        try {
            Signatures::save(Auth::user(), $this->image, $this->signatoryName, $this->signatoryTitle, $this->kind, $this->width, $this->height);
        } catch (Throwable $e) {
            $this->addError('image', $e->getMessage());

            return;
        }

        $this->image = '';
        $this->flash = 'Saved. You can sign documents with it now.';
    }

    public function remove(): void
    {
        Signatures::forget(Auth::user());
        $this->confirmingRemoval = false;
        $this->flash = 'Removed. Documents you have already signed keep the signature that was on them.';
    }

    public function confirmIdentity(): void
    {
        $user = Auth::user();
        $key = 'letters-signature-confirm:' . $user->id;
        $this->resetErrorBag();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('confirmPassword', __('sign_in.pin.password_too_many', ['minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60))]));

            return;
        }
        if ($this->confirmPassword === '') {
            $this->addError('confirmPassword', __('sign_in.pin.password_required'));

            return;
        }
        if (! Hash::check($this->confirmPassword, (string) $user->getAuthPassword())) {
            RateLimiter::hit($key, 600);
            $this->confirmPassword = '';
            $this->addError('confirmPassword', __('sign_in.pin.password_wrong'));

            return;
        }

        RateLimiter::clear($key);
        SignIn::markFresh();
        $this->confirmPassword = '';
        $this->needsPassword = false;
        $this->save();
    }

    public function cancelConfirm(): void
    {
        $this->needsPassword = false;
        $this->confirmPassword = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.hq.letters.signature', [
            'signature' => Signatures::forUser(Auth::user()),
            'canSign' => (bool) Auth::user()?->can('letters.sign'),
        ])->title('My signature');
    }
}
