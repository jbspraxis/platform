{{-- Client Portal password reset from an emailed link: one card on Mist, like the setup page (docs/BRAND.md sections 9, 12 and 14) --}}
<main class="auth-page">
    <div class="auth-card">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <img src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" width="168" height="32" class="block h-8 w-auto">
            @include('livewire.client-os.partials.language-switch')
        </div>

        @if ($expired)
            <h1 class="page-title">{{ __('portal.reset.expired_title') }}</h1>
            <p class="page-subtitle mb-6">{{ __('portal.reset.expired', ['minutes' => \App\Support\SignIn\PasswordResets::MINUTES]) }}</p>
            <a href="{{ route('client.login', ['forgot' => 1]) }}" class="btn btn-primary w-full whitespace-normal">{{ __('portal.reset.request_new') }}</a>
        @else
            <h1 class="page-title">{{ __('portal.reset.title') }}</h1>
            <p class="page-subtitle mb-6">{{ __('portal.reset.intro', ['email' => $email]) }}</p>

            <form wire:submit.prevent="save" class="flex flex-col gap-4">
                <div class="field">
                    <label for="reset-password" class="label">{{ __('portal.reset.password') }}</label>
                    <input type="password" id="reset-password" wire:model="password" class="input" required autocomplete="new-password" aria-describedby="reset-password-help">
                    <span id="reset-password-help" class="help">{{ __('portal.setup.password_help') }}</span>
                    @error('password') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="reset-password-confirm" class="label">{{ __('portal.setup.confirm') }}</label>
                    <input type="password" id="reset-password-confirm" wire:model="passwordConfirmation" class="input" required autocomplete="new-password">
                    @error('passwordConfirmation') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="btn btn-primary mt-2 w-full whitespace-normal" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ __('portal.reset.submit') }}</span>
                    <span wire:loading wire:target="save">{{ __('portal.reset.submitting') }}</span>
                </button>

                <p class="help flex items-start gap-2">
                    <i data-lucide="lock" class="mt-0.5 h-4 w-4"></i>
                    <span>{{ __('portal.setup.privacy') }}</span>
                </p>
            </form>
        @endif
    </div>
</main>
