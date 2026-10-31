{{-- Under the sign-in form on HQ and the Client Portal (docs/SIGN_IN.md section 2): a passkey (only where the
     browser can use one) and the switch between the password and the PIN. The component uses SignsInWithPasskeyOrPin. --}}
<div class="mt-6 flex flex-col gap-3">
    <div class="flex items-center gap-3 text-sm text-muted" aria-hidden="true">
        <span class="h-px flex-1 bg-line"></span>
        <span>{{ __('sign_in.login.or') }}</span>
        <span class="h-px flex-1 bg-line"></span>
    </div>

    <div x-data="{
            ok: !!(window.praxisPasskey && window.praxisPasskey.supported()),
            busy: false,
            async go() {
                if (this.busy) return;
                this.busy = true;
                try {
                    const options = await this.$wire.passkeyOptions();
                    const answer = await window.praxisPasskey.get(options);
                    await this.$wire.signInWithPasskey(answer);
                } catch (err) {
                    await this.$wire.passkeyProblem(window.praxisPasskey.problem(err));
                } finally {
                    this.busy = false;
                }
            },
         }" x-show="ok" x-cloak>
        <button type="button" class="btn btn-secondary w-full whitespace-normal" @click="go()" :disabled="busy" :aria-busy="busy.toString()" aria-describedby="sign-in-passkey-help">
            <i data-lucide="fingerprint"></i>
            <span x-show="!busy">{{ __('sign_in.login.passkey') }}</span>
            <span x-show="busy" style="display: none;">{{ __('sign_in.login.passkey_busy') }}</span>
        </button>
        <p class="help mt-1 text-center" id="sign-in-passkey-help">{{ __('sign_in.login.passkey_help') }}</p>
    </div>

    @if ($usePin)
        <button type="button" class="btn btn-ghost w-full whitespace-normal" wire:click="choosePin(false)">
            <i data-lucide="key-round"></i> {{ __('sign_in.login.password') }}
        </button>
    @else
        <button type="button" class="btn btn-ghost w-full whitespace-normal" wire:click="choosePin(true)">
            <i data-lucide="grid-3x3"></i> {{ __('sign_in.login.pin') }}
        </button>
    @endif
</div>
