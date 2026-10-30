{{-- Client Portal account setup from an invitation link: one card on Mist (docs/BRAND.md sections 9, 12 and 14) --}}
<main class="auth-page">
    <div class="auth-card">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <img src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" width="168" height="32" class="block h-8 w-auto">
            @include('livewire.client-os.partials.language-switch')
        </div>

        <h1 class="page-title">{{ __('portal.setup.title') }}</h1>
        <p class="page-subtitle mb-6">{{ __('portal.setup.intro', ['name' => trim($firstName . ' ' . $lastName)]) }}</p>

        <form wire:submit.prevent="lockAndAuthenticate" class="flex flex-col gap-4">
            <div class="field">
                <label for="setup-password" class="label">{{ __('portal.setup.password') }}</label>
                <input type="password" id="setup-password" wire:model="password" class="input" required autocomplete="new-password" aria-describedby="setup-password-help">
                <span id="setup-password-help" class="help">{{ __('portal.setup.password_help') }}</span>
                @error('password') <span class="field-error" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="setup-password-confirm" class="label">{{ __('portal.setup.confirm') }}</label>
                <input type="password" id="setup-password-confirm" wire:model="passwordConfirmation" class="input" required autocomplete="new-password">
                @error('passwordConfirmation') <span class="field-error" role="alert">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-primary mt-2 w-full whitespace-normal" wire:loading.attr="disabled" wire:target="lockAndAuthenticate">
                <span wire:loading.remove wire:target="lockAndAuthenticate">{{ __('portal.setup.submit') }}</span>
                <span wire:loading wire:target="lockAndAuthenticate">{{ __('portal.setup.submitting') }}</span>
            </button>

            <p class="help flex items-start gap-2">
                <i data-lucide="lock" class="mt-0.5 h-4 w-4"></i>
                <span>{{ __('portal.setup.privacy') }}</span>
            </p>
        </form>
    </div>
</main>
