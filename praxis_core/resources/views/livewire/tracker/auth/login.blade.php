{{-- Sign-in for the delivery team's project tracker: the content of the auth card (components/layouts/auth draws the card and logo) --}}
<div>
    <h1 class="page-title">Sign in to the project tracker</h1>
    <p class="page-subtitle" style="margin-bottom: 24px;">For the delivery team: JBS Praxis staff and consultants working on a client project.</p>

    <form wire:submit.prevent="authenticate" class="flex flex-col gap-4">

        <div class="field">
            <label for="tracker-email" class="label">Email address</label>
            <input type="email" id="tracker-email" wire:model="email" class="input" required autocomplete="username" autofocus>
            @error('email') <span class="field-error" role="alert">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="tracker-password" class="label">Password</label>
            <input type="password" id="tracker-password" wire:model="password" class="input" required autocomplete="current-password">
            @error('password') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;" wire:loading.attr="disabled">
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in…</span>
        </button>
    </form>
</div>
