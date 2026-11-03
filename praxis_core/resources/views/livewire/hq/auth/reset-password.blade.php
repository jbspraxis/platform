{{-- Staff password reset from an emailed link: the content of the auth card (components/layouts/auth draws the card and logo) --}}
<div x-data="{ show: false }">
    @if ($expired)
        <h1 class="page-title">This link no longer works</h1>
        <p class="page-subtitle" style="margin-bottom: 24px;">
            Reset links work once, for {{ \App\Support\SignIn\PasswordResets::MINUTES }} minutes. Ask for a new one on the sign-in page.
        </p>
        <a href="{{ route('login', ['forgot' => 1]) }}" class="btn btn-primary" style="width: 100%;">Ask for a new link</a>
    @else
        <h1 class="page-title">Choose a new password</h1>
        <p class="page-subtitle" style="margin-bottom: 24px;">
            Choose a new password for your JBS Praxis HQ account, <strong class="text-ink">{{ $email }}</strong>.
        </p>

        <form wire:submit="save" class="flex flex-col gap-4">
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

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save password and sign in</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </form>
    @endif
</div>
