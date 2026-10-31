{{-- A person's passkeys and PIN (App\Livewire\Account\SignInMethods, docs/SIGN_IN.md section 2).
     On HQ's My profile and in the Client Portal's Sign-in settings; every word in lang/<locale>/sign_in.php. --}}
<div class="flex flex-col gap-6"
     x-data="{
         ok: !!(window.praxisPasskey && window.praxisPasskey.supported()),
         busy: false,
         async add() {
             if (this.busy) return;
             this.busy = true;
             try {
                 const options = await this.$wire.passkeyCreationOptions();
                 if (options) await this.$wire.savePasskey(await window.praxisPasskey.create(options));
             } catch (err) {
                 await this.$wire.passkeyProblem(window.praxisPasskey.problem(err));
             } finally {
                 this.busy = false;
             }
         },
     }">
    @if ($flash)
        <div class="alert alert-success m-0" role="status"><i data-lucide="circle-check"></i><span>{{ $flash }}</span></div>
    @endif

    {{-- The password again, when the last sign-in was more than 10 minutes ago --}}
    @if ($needsPassword)
        <form wire:submit="confirmIdentity" class="flex flex-col gap-3 rounded-lg border border-line p-4" novalidate>
            <div>
                <h3 class="m-0 text-base font-semibold text-ink">{{ __('sign_in.settings.confirm_title') }}</h3>
                <p class="help m-0 mt-1">{{ __('sign_in.settings.confirm_help') }}</p>
            </div>
            <div class="field max-w-[360px]">
                <label class="label" for="sim-{{ $guard }}-password">{{ __('sign_in.settings.password') }}</label>
                <input id="sim-{{ $guard }}-password" class="input" type="password" autocomplete="current-password" wire:model="confirmPassword" autofocus
                       @error('confirmPassword') aria-invalid="true" aria-describedby="sim-{{ $guard }}-password-error" @enderror>
                @error('confirmPassword') <p class="field-error" id="sim-{{ $guard }}-password-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="confirmIdentity">
                    <span wire:loading.remove wire:target="confirmIdentity">{{ __('sign_in.settings.confirm') }}</span>
                    <span wire:loading wire:target="confirmIdentity">{{ __('sign_in.settings.confirming') }}</span>
                </button>
                <button type="button" class="btn btn-ghost" wire:click="cancelConfirm">{{ __('sign_in.settings.cancel') }}</button>
            </div>
        </form>
    @endif

    {{-- Passkeys --}}
    <div>
        <h3 class="m-0 text-base font-semibold text-ink">{{ __('sign_in.settings.passkeys_title') }}</h3>
        <p class="help m-0 mt-1">{{ __('sign_in.settings.passkeys_help') }}</p>

        @if ($passkeys->isEmpty())
            <p class="m-0 mt-3 text-sm text-slate">{{ __('sign_in.settings.passkeys_none') }}</p>
        @else
            <ul class="m-0 mt-3 flex list-none flex-col gap-2 p-0">
                @foreach ($passkeys as $passkey)
                    <li class="flex flex-wrap items-center gap-3 rounded-lg border border-line px-4 py-3" wire:key="passkey-{{ $passkey->id }}">
                        <i data-lucide="fingerprint" class="h-5 w-5 shrink-0 text-deep-teal" aria-hidden="true"></i>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 font-medium text-ink">
                                <span class="break-words">{{ $passkey->name }}</span>
                                @if ($passkey->synced) <span class="pill pill-info">{{ __('sign_in.settings.synced') }}</span> @endif
                            </div>
                            <div class="text-xs text-muted">
                                {{ __('sign_in.settings.added', ['date' => $passkey->added]) }}@if ($passkey->used), {{ __('sign_in.settings.used', ['date' => $passkey->used]) }}@endif
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="removePasskey({{ $passkey->id }})"
                                wire:confirm="{{ __('sign_in.settings.remove_passkey_confirm', ['name' => $passkey->name]) }}">
                            <i data-lucide="trash-2"></i> {{ __('sign_in.settings.remove_passkey') }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        @error('passkey') <p class="field-error mt-3" role="alert">{{ $message }}</p> @enderror

        @if ($passkeys->count() < $maxPasskeys)
            <div class="mt-3" x-show="ok">
                <button type="button" class="btn btn-secondary" x-on:click="add()" x-bind:disabled="busy" x-bind:aria-busy="busy.toString()">
                    <i data-lucide="fingerprint"></i>
                    <span x-show="!busy">{{ __('sign_in.settings.add_passkey') }}</span>
                    <span x-show="busy" style="display: none;">{{ __('sign_in.settings.adding_passkey') }}</span>
                </button>
            </div>
            <p class="help m-0 mt-3" x-show="!ok" x-cloak>{{ __('sign_in.settings.passkeys_unsupported') }}</p>
        @endif
    </div>

    {{-- PIN --}}
    <div>
        <h3 class="m-0 text-base font-semibold text-ink">{{ __('sign_in.settings.pin_title') }}</h3>
        <p class="help m-0 mt-1">{{ __('sign_in.settings.pin_help') }}</p>

        @if ($settingPin)
            <form wire:submit="savePin" class="mt-3 flex flex-col gap-3" novalidate>
                <div class="grid gap-4 sm:grid-cols-2" style="max-width: 480px;">
                    <div class="field">
                        <label class="label" for="sim-{{ $guard }}-pin">{{ __('sign_in.settings.new_pin') }}</label>
                        <input id="sim-{{ $guard }}-pin" class="input tracking-[0.5em]" type="password" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="newPin" autofocus
                               @error('newPin') aria-invalid="true" aria-describedby="sim-{{ $guard }}-pin-error" @else aria-describedby="sim-{{ $guard }}-pin-help" @enderror>
                        @error('newPin') <p class="field-error" id="sim-{{ $guard }}-pin-error">{{ $message }}</p>
                        @else <p class="help" id="sim-{{ $guard }}-pin-help">{{ __('sign_in.settings.new_pin_help') }}</p> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="sim-{{ $guard }}-pin-again">{{ __('sign_in.settings.new_pin_again') }}</label>
                        <input id="sim-{{ $guard }}-pin-again" class="input tracking-[0.5em]" type="password" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="newPinAgain"
                               @error('newPinAgain') aria-invalid="true" aria-describedby="sim-{{ $guard }}-pin-again-error" @enderror>
                        @error('newPinAgain') <p class="field-error" id="sim-{{ $guard }}-pin-again-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="savePin"><i data-lucide="save"></i> {{ __('sign_in.settings.save_pin') }}</button>
                    <button type="button" class="btn btn-ghost" wire:click="cancelPin">{{ __('sign_in.settings.cancel') }}</button>
                </div>
            </form>
        @else
            <p class="m-0 mt-3 text-sm {{ $pinStatus === 'off' ? 'text-danger' : 'text-slate' }}">
                {{ __('sign_in.settings.pin_' . $pinStatus) }}
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-secondary" wire:click="startPin">
                    <i data-lucide="grid-3x3"></i> {{ $pinStatus === 'on' ? __('sign_in.settings.change_pin') : __('sign_in.settings.set_pin') }}
                </button>
                @if ($pinStatus !== 'none')
                    <button type="button" class="btn btn-ghost" wire:click="removePin" wire:confirm="{{ __('sign_in.settings.remove_pin_confirm') }}">
                        {{ __('sign_in.settings.remove_pin') }}
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
