{{-- Staff account setup: the content of the auth card (components/layouts/auth draws the card and logo) --}}
<div x-data="{ show: false }">
    <h1 class="page-title">Choose your password</h1>
    <p class="page-subtitle" style="margin-bottom: 24px;">
        Welcome, {{ $firstName }}. Choose a password to finish setting up your JBS Praxis HQ account for <strong class="text-ink">{{ $email }}</strong>.
    </p>

    <form wire:submit="lockAndAuthenticate" class="flex flex-col gap-4">
        <div class="field">
            <label for="password" class="label">New password</label>
            <input id="password" type="password" :type="show ? 'text' : 'password'" wire:model="password" class="input" required autofocus autocomplete="new-password" aria-describedby="password-help">
            <span id="password-help" class="help">At least 8 characters.</span>
            @error('password') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="passwordConfirmation" class="label">Confirm password</label>
            <input id="passwordConfirmation" type="password" :type="show ? 'text' : 'password'" wire:model="passwordConfirmation" class="input" required autocomplete="new-password">
            @error('passwordConfirmation') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <label class="flex items-center gap-2 help" style="cursor: pointer;">
            <input type="checkbox" x-model="show"> Show passwords
        </label>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;" wire:loading.attr="disabled" wire:target="lockAndAuthenticate">
            <span wire:loading.remove wire:target="lockAndAuthenticate">Save password and sign in</span>
            <span wire:loading wire:target="lockAndAuthenticate">Saving…</span>
        </button>
    </form>
</div>
