<?php

namespace App\Livewire\Hq\System;

use App\Models\KeyVaultItem;
use App\Models\User;
use App\Support\KeyVault\Envelope;
use App\Support\KeyVault\KeyVault;
use App\Support\SignIn\SignIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * HQ › Settings and access › Key vault (docs/KEY_VAULT.md), for the protected Owner role only.
 *
 * The secret and the passphrase never reach this component: the page's script (resources/key-vault/page.js)
 * locks a secret in the browser and calls add() or replace() with the envelope; reveal() hands an envelope
 * back after the emailed code, and the browser opens it with the passphrase. No public property here can
 * hold either, and none is bound to the secret's or the passphrase's inputs.
 *
 * Every action needs a sign-in less than ten minutes old (SignIn::isFresh), else the password again.
 */
#[Layout('components.layouts.hq')]
class KeyVaultPage extends Component
{
    /** The HQ password, typed again when the sign-in is more than ten minutes old */
    public string $confirmPassword = '';

    public bool $needsPassword = false;

    /** What to finish once the password is right: ['rename'] or ['delete']; the script's own steps wait for key-vault-fresh */
    #[Locked]
    public ?string $pending = null;

    public ?string $renaming = null;

    public string $newName = '';

    #[Locked]
    public ?string $confirmingDelete = null;

    /** Which item's Replace form is open */
    public ?string $replacing = null;

    #[Locked]
    public ?string $flash = null;

    public function mount(): void
    {
        $this->owner();
    }

    /** True when the sign-in is fresh; else the password modal opens and the script waits for it */
    public function ensureFresh(): bool
    {
        $this->owner();
        if (SignIn::isFresh()) {
            return true;
        }
        $this->pending = null;
        $this->needsPassword = true;

        return false;
    }

    /** From the script: a secret it has just locked, with the name the owner gave it */
    public function add(string $name, mixed $envelope = null): array
    {
        $owner = $this->owner();
        if (! $this->fresh()) {
            return ['ok' => false, 'error' => 'Type your password again, then save.'];
        }
        try {
            $item = KeyVault::add($owner, $name, Envelope::fromBrowser($envelope));
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $this->flash = "Saved “{$item->name}”. Now download its locked copy (below) and put it in the jbspraxisllc@gmail.com Google Drive.";

        return ['ok' => true, 'id' => $item->id];
    }

    /** From the script: an item locked again, with a new secret or a new passphrase */
    public function replace(string $id, mixed $envelope = null): array
    {
        $owner = $this->owner();
        $item = $this->item($id);
        if (! $this->fresh()) {
            return ['ok' => false, 'error' => 'Type your password again, then save.'];
        }
        try {
            KeyVault::replace($owner, $item, Envelope::fromBrowser($envelope));
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $this->replacing = null;
        $this->flash = "Replaced “{$item->name}”. Download a new locked copy and put it in the Drive in place of the old one: an old copy still opens, with the old passphrase, to the old secret.";

        return ['ok' => true];
    }

    /** Step 2 of a reveal: a code to the owner's email address */
    public function sendCode(string $id): array
    {
        $owner = $this->owner();
        $item = $this->item($id);
        if (! $this->fresh()) {
            return ['ok' => false, 'error' => 'Type your password again first.'];
        }
        if ($refused = KeyVault::sendCode($owner, $item)) {
            return ['ok' => false, 'error' => $this->refusal($refused)];
        }

        return ['ok' => true, 'sentTo' => KeyVault::mask($owner->email)];
    }

    /** Step 3: the code. Right, the envelope goes to the browser, which asks for the passphrase. */
    public function reveal(string $id, string $code = ''): array
    {
        $owner = $this->owner();
        $item = $this->item($id);
        if (! $this->fresh()) {
            return ['error' => 'Type your password again, then the code.'];
        }
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return ['error' => 'Type the 6 digits from the email.'];
        }
        $result = KeyVault::reveal($owner, $item, $code);
        if (isset($result['envelope'])) {
            return $result;
        }

        return ['error' => $this->refusal($result), 'again' => in_array($result[0], ['cancel', 'no_code'], true)];
    }

    public function startRename(string $id): void
    {
        $item = $this->item($id);
        $this->resetErrorBag();
        $this->renaming = $item->id;
        $this->newName = $item->name;
    }

    public function cancelRename(): void
    {
        $this->renaming = null;
        $this->newName = '';
        $this->resetErrorBag('newName');
    }

    public function rename(): void
    {
        $owner = $this->owner();
        $item = $this->item((string) $this->renaming);
        if (! $this->fresh('rename')) {
            return;
        }
        try {
            KeyVault::rename($owner, $item, $this->newName);
        } catch (InvalidArgumentException $e) {
            $this->addError('newName', $e->getMessage());

            return;
        }
        $this->renaming = null;
        $this->newName = '';
        $this->flash = 'Renamed. The locked copies keep the name they were made with.';
    }

    public function openReplace(string $id): void
    {
        $this->replacing = $this->item($id)->id;
    }

    public function closeReplace(): void
    {
        $this->replacing = null;
    }

    public function confirmDelete(string $id): void
    {
        $this->confirmingDelete = $this->item($id)->id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = null;
    }

    public function delete(): void
    {
        $owner = $this->owner();
        $item = $this->item((string) $this->confirmingDelete);
        if (! $this->fresh('delete')) {
            return;
        }
        $name = $item->name;
        KeyVault::delete($owner, $item);
        $this->confirmingDelete = null;
        $this->flash = "Deleted “{$name}” from the vault. Its locked copies still open with their passphrase: delete them from the Drive too if they should go.";
    }

    public function confirmIdentity(): void
    {
        $user = $this->owner();
        $key = 'key-vault-confirm:' . $user->id;
        $this->resetErrorBag('confirmPassword');

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
        $pending = $this->pending;
        $this->pending = null;

        match ($pending) {
            'rename' => $this->rename(),
            'delete' => $this->delete(),
            default => $this->dispatch('key-vault-fresh'),
        };
    }

    public function cancelConfirm(): void
    {
        $this->needsPassword = false;
        $this->confirmPassword = '';
        $this->pending = null;
        $this->resetErrorBag('confirmPassword');
        $this->dispatch('key-vault-cancel');
    }

    /** The signed-in owner; anyone else is refused, on every call, not only when the page opens */
    private function owner(): User
    {
        $user = Auth::user();
        abort_unless(KeyVault::allows($user), 403);

        return $user;
    }

    private function item(string $id): KeyVaultItem
    {
        $item = KeyVault::find($this->owner(), $id);
        abort_unless($item !== null, 404);

        return $item;
    }

    /** Fresh, or the password modal opens (and $then is finished after it) */
    private function fresh(?string $then = null): bool
    {
        if (SignIn::isFresh()) {
            return true;
        }
        $this->pending = $then;
        $this->needsPassword = true;

        return false;
    }

    /** @param array{string, int} $refused */
    private function refusal(array $refused): string
    {
        [$reason, $minutes] = $refused;
        $wait = $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');

        return match ($reason) {
            'too_many_codes', 'too_many_codes_email' => "Three codes were sent in the last 15 minutes. Wait {$wait}, then ask again.",
            'email_failed' => 'The email with the code could not be sent. Check Email sending in System health, then try again.',
            'cancel' => "That was the fifth wrong code, so it no longer works. Wait {$wait}, then ask for a new one.",
            'locked' => "Too many wrong codes. Wait {$wait}, then ask for a new one.",
            'no_code' => 'That code is not for this item, or it has been used already. Ask for a new one.',
            default => 'That code is not right, or it has expired (a code works for 10 minutes). Check the newest email.',
        };
    }

    public function render()
    {
        $owner = $this->owner();
        $items = KeyVault::items($owner);

        return view('livewire.hq.system.key-vault', [
            'items' => $items,
            'fingerprints' => $items->mapWithKeys(fn ($i) => [$i->id => KeyVault::fingerprint($i)]),
            'unlocked' => $items->filter(fn ($i) => KeyVault::isUnlocked($i))->pluck('id')->all(),
            'codeItem' => KeyVault::codeItem(),
            'sentTo' => KeyVault::mask($owner->email),
            'fresh' => SignIn::isFresh(),
        ])->title('Key vault');
    }
}
