{{-- Client Portal sign-in, password reset request and urgent support for organisations that are not clients yet.
     One card on Mist (docs/BRAND.md sections 9, 12 and 14). --}}
<main class="auth-page" x-data="{ sosOpen: false }" @keydown.escape.window="sosOpen = false">
    <div class="w-full max-w-[420px]">
        <div class="auth-card">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <img src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" width="168" height="32" class="block h-8 w-auto">
                @include('livewire.client-os.partials.language-switch')
            </div>

            @if ($viewState === 'login' || $viewState === 'redline_success')
                <h1 class="page-title">{{ __('portal.login.title') }}</h1>
                <p class="page-subtitle mb-6">{{ __('portal.login.intro') }}</p>

                <form wire:submit.prevent="authenticate" class="flex flex-col gap-4">
                    @error('email')
                        <div class="alert alert-danger" role="alert">
                            <i data-lucide="circle-alert"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <div class="field">
                        <label for="client-email" class="label">{{ __('portal.login.email') }}</label>
                        <input type="email" id="client-email" wire:model="email" class="input" required autocomplete="username" autofocus>
                    </div>

                    <div class="field" x-data="{ show: false }">
                        <div class="flex items-center justify-between gap-3">
                            <label for="client-password" class="label">{{ __('portal.login.password') }}</label>
                            <button type="button" wire:click="switchState('forgot_password')" class="btn btn-ghost btn-sm">{{ __('portal.login.forgot') }}</button>
                        </div>
                        <div class="relative">
                            <input type="password" :type="show ? 'text' : 'password'" id="client-password" wire:model="password" class="input pr-11" required autocomplete="current-password">
                            <button type="button" @click="show = !show" :aria-pressed="show.toString()" aria-label="{{ __('portal.login.show_password') }}" title="{{ __('portal.login.show_password') }}"
                                    class="absolute inset-y-0 right-0 flex w-11 cursor-pointer items-center justify-center border-0 bg-transparent text-muted hover:text-ink">
                                <i data-lucide="eye" class="h-4 w-4"></i>
                            </button>
                        </div>
                        @error('password') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary mt-2 w-full whitespace-normal" wire:loading.attr="disabled" wire:target="authenticate">
                        <span wire:loading.remove wire:target="authenticate">{{ __('portal.login.submit') }}</span>
                        <span wire:loading wire:target="authenticate">{{ __('portal.login.submitting') }}</span>
                    </button>
                </form>

            @elseif ($viewState === 'forgot_password')
                <h1 class="page-title">{{ __('portal.login.reset_title') }}</h1>
                <p class="page-subtitle mb-6">{{ __('portal.login.reset_intro') }}</p>

                <form wire:submit.prevent="sendResetLink" class="flex flex-col gap-4">
                    @if ($authMessage)
                        <div class="alert alert-info" role="status">
                            <i data-lucide="info"></i>
                            <span>{{ $authMessage }}</span>
                        </div>
                    @endif

                    <div class="field">
                        <label for="reset-email" class="label">{{ __('portal.login.email') }}</label>
                        <input type="email" id="reset-email" wire:model="resetEmail" class="input" required autocomplete="username">
                        @error('resetEmail') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-full whitespace-normal" wire:loading.attr="disabled" wire:target="sendResetLink">
                        <span wire:loading.remove wire:target="sendResetLink">{{ __('portal.login.reset_submit') }}</span>
                        <span wire:loading wire:target="sendResetLink">{{ __('portal.login.reset_submitting') }}</span>
                    </button>

                    <button type="button" wire:click="switchState('login')" class="btn btn-ghost self-center">
                        <i data-lucide="arrow-left"></i> {{ __('portal.login.back') }}
                    </button>
                </form>
            @endif
        </div>

        <div class="mt-6 flex flex-col gap-2 text-center text-sm text-muted">
            <p class="m-0">
                {{ __('portal.sos.prompt') }}
                <button type="button" @click="sosOpen = true" class="cursor-pointer border-0 bg-transparent p-0 font-medium text-deep-teal underline">{{ __('portal.sos.open') }}</button>
            </p>
            <p class="m-0">
                {{ __('portal.login.staff_prompt') }}
                <a href="{{ route('login') }}" class="font-medium text-deep-teal underline">{{ __('portal.login.staff_link') }}</a>
            </p>
        </div>
    </div>

    {{-- Urgent support (organisations that are not clients yet) --}}
    <div x-show="sosOpen" x-cloak class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="sos-title">
        <div class="modal" style="max-width: 600px;">
            <div class="mb-2 flex items-start justify-between gap-4">
                <h2 id="sos-title" class="modal-title">{{ $viewState === 'redline_success' ? __('portal.sos.sent_title') : __('portal.sos.title') }}</h2>
                <button type="button" @click="sosOpen = false; $wire.set('viewState', 'login')" class="btn btn-ghost btn-sm" aria-label="{{ __('portal.common.close') }}" title="{{ __('portal.common.close') }}">
                    <i data-lucide="x"></i>
                </button>
            </div>

            @if ($viewState === 'redline_success')
                <div class="alert alert-success" role="status">
                    <i data-lucide="circle-check"></i>
                    <span>{{ __('portal.sos.sent_body') }}</span>
                </div>
                <div class="modal-actions">
                    <a href="{{ route('schedule') }}" class="btn btn-secondary"><i data-lucide="calendar"></i> {{ __('portal.sos.book') }}</a>
                    <button type="button" wire:click="switchState('login')" @click="sosOpen = false" class="btn btn-primary">{{ __('portal.common.close') }}</button>
                </div>
            @else
                <p class="mb-4 text-sm text-slate">{{ __('portal.sos.intro') }}</p>

                <x-flash :keys="['redline_error']" class="mb-4" />

                <form wire:submit.prevent="submitRedlineSos" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="field">
                        <label for="sos-name" class="label">{{ __('portal.sos.name') }}</label>
                        <input type="text" id="sos-name" wire:model="sosName" class="input" required autocomplete="name">
                        @error('sosName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="sos-title-field" class="label">{{ __('portal.sos.job_title') }}</label>
                        <input type="text" id="sos-title-field" wire:model="sosTitle" class="input" required autocomplete="organization-title">
                        @error('sosTitle') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field sm:col-span-2">
                        <label for="sos-company" class="label">{{ __('portal.sos.company') }}</label>
                        <input type="text" id="sos-company" wire:model="sosCompany" class="input" required autocomplete="organization">
                        @error('sosCompany') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="sos-email" class="label">{{ __('portal.sos.email') }}</label>
                        <input type="email" id="sos-email" wire:model="sosEmail" class="input" required autocomplete="email">
                        @error('sosEmail') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="sos-phone" class="label">{{ __('portal.sos.phone') }}</label>
                        <input type="tel" id="sos-phone" wire:model="sosPhone" class="input" placeholder="{{ __('portal.sos.phone_placeholder') }}" pattern="^\+[0-9 \-\(\)]{7,20}$" title="{{ __('portal.sos.phone_format') }}" required autocomplete="tel" aria-describedby="sos-phone-help">
                        <span id="sos-phone-help" class="help">{{ __('portal.sos.phone_help') }}</span>
                        @error('sosPhone') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field sm:col-span-2">
                        <label for="sos-description" class="label">{{ __('portal.sos.description') }}</label>
                        <textarea id="sos-description" wire:model="sosDescription" class="textarea" rows="4" required aria-describedby="sos-description-help"></textarea>
                        <span id="sos-description-help" class="help">{{ __('portal.sos.description_help') }}</span>
                        @error('sosDescription') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="modal-actions flex-wrap sm:col-span-2" style="margin-top: 8px;">
                        <button type="button" @click="sosOpen = false" class="btn btn-secondary">{{ __('portal.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="submitRedlineSos">
                            <span wire:loading.remove wire:target="submitRedlineSos">{{ __('portal.sos.submit') }}</span>
                            <span wire:loading wire:target="submitRedlineSos">{{ __('portal.common.sending') }}</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</main>
