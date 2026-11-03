{{-- HQ sign-in and password reset request: the content of the auth card (components/layouts/auth draws the card and logo) --}}
<div>
    @if ($viewMode === 'login')
        <h1 class="page-title">Sign in to JBS Praxis HQ</h1>
        <p class="page-subtitle" style="margin-bottom: 24px;">For JBS Praxis staff. Clients sign in to the <a href="{{ route('client.login') }}" class="text-deep-teal underline">Client Portal</a>.</p>

        <form wire:submit="{{ $usePin ? 'signInWithPin' : 'authenticate' }}" class="flex flex-col gap-4">
            @error('auth')
                <div class="alert alert-danger" role="alert">
                    <i data-lucide="circle-alert"></i>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            @error('client_redirect')
                <div class="alert alert-warning" role="alert">
                    <i data-lucide="info"></i>
                    <span>
                        {{ $message }}
                        <a href="{{ route('client.login') }}" class="underline" style="display: block; margin-top: 4px; font-weight: 600;">Go to the Client Portal sign-in</a>
                    </span>
                </div>
            @enderror

            <div class="field">
                <label for="email" class="label">Email address</label>
                <input type="email" id="email" wire:model="email" class="input" required autocomplete="username" autofocus>
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            @if ($usePin)
                <div class="field">
                    <label for="pin" class="label">{{ __('sign_in.login.pin_label') }}</label>
                    <input type="password" id="pin" wire:model="pin" class="input" inputmode="numeric" maxlength="4" autocomplete="off" required
                           style="max-width: 160px; letter-spacing: 0.5em;" autofocus
                           @error('pin') aria-invalid="true" aria-describedby="pin-error" @else aria-describedby="pin-help" @enderror>
                    @error('pin') <span class="field-error" id="pin-error">{{ $message }}</span> @else <span class="help" id="pin-help">{{ __('sign_in.login.pin_help') }}</span> @enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;" wire:loading.attr="disabled" wire:target="signInWithPin">
                    <span wire:loading.remove wire:target="signInWithPin">{{ __('sign_in.login.pin_submit') }}</span>
                    <span wire:loading wire:target="signInWithPin">{{ __('sign_in.login.pin_submitting') }}</span>
                </button>
            @else
                <div class="field">
                    <div class="flex items-center justify-between gap-3">
                        <label for="password" class="label">Password</label>
                        <button type="button" wire:click.prevent="toggleView('recovery')" class="btn btn-ghost btn-sm">Forgot your password?</button>
                    </div>
                    <input type="password" id="password" wire:model="password" class="input" required autocomplete="current-password">
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;" wire:loading.attr="disabled" wire:target="authenticate">
                    <span wire:loading.remove wire:target="authenticate">Sign in</span>
                    <span wire:loading wire:target="authenticate">Signing in…</span>
                </button>
            @endif
        </form>

        {{-- A passkey or the PIN instead of the password (docs/SIGN_IN.md) --}}
        @include('livewire.sign-in.alternatives')
    @else
        <h1 class="page-title">Reset your password</h1>
        <p class="page-subtitle" style="margin-bottom: 24px;">Enter the email address you use to sign in to HQ. We’ll email you a link to choose a new password.</p>

        <form wire:submit="initiateRecovery" class="flex flex-col gap-4">
            @if ($recoveryMessage)
                <div class="alert alert-info" role="status">
                    <i data-lucide="info"></i>
                    <span>{{ $recoveryMessage }}</span>
                </div>
            @else
                <div class="field">
                    <label for="recovery-email" class="label">Email address</label>
                    <input type="email" id="recovery-email" wire:model="email" class="input" required autocomplete="username">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;" wire:loading.attr="disabled" wire:target="initiateRecovery">
                    <span wire:loading.remove wire:target="initiateRecovery">Email me a reset link</span>
                    <span wire:loading wire:target="initiateRecovery">Sending…</span>
                </button>
            @endif

            <button type="button" wire:click.prevent="toggleView('login')" class="btn btn-ghost" style="align-self: center;">
                <i data-lucide="arrow-left"></i> Back to sign in
            </button>
        </form>
    @endif
</div>
