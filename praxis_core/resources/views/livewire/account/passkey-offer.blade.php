{{-- "Sign in faster next time" (App\Livewire\Account\PasskeyOffer, docs/SIGN_IN.md section 2). The browser decides
     whether to show it: only with a fingerprint, face or screen lock built in, and not within 14 days of "Not now". --}}
<div>
    @if ($done)
        <div class="alert alert-success mb-4" role="status" x-data x-init="setTimeout(() => $el.remove(), 8000)">
            <i data-lucide="circle-check"></i><span>{{ __('sign_in.offer.done') }}</span>
        </div>
    @elseif ($show)
        <section class="card mb-4" aria-labelledby="passkey-offer-{{ $guard }}" x-cloak
                 x-data="{
                     key: 'praxis-passkey-offer-later-{{ $guard }}',
                     open: false,
                     busy: false,
                     async init() {
                         let later = 0;
                         try { later = Number(localStorage.getItem(this.key) || 0) } catch (e) {}
                         if (Date.now() - later < 14 * 24 * 3600 * 1000) return;
                         this.open = !!window.praxisPasskey && await window.praxisPasskey.platform();
                     },
                     later() {
                         try { localStorage.setItem(this.key, String(Date.now())) } catch (e) {}
                         this.open = false;
                         this.$wire.dismiss();
                     },
                     async add() {
                         if (this.busy) return;
                         this.busy = true;
                         try {
                             const options = await this.$wire.creationOptions();
                             if (options) await this.$wire.save(await window.praxisPasskey.create(options));
                             else this.open = false;
                         } catch (err) {
                             await this.$wire.problem(window.praxisPasskey.problem(err));
                         } finally {
                             this.busy = false;
                         }
                     },
                 }"
                 x-show="open">
            <div class="flex flex-wrap items-center gap-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-mist text-deep-teal" aria-hidden="true"><i data-lucide="fingerprint"></i></span>
                <div class="min-w-0 flex-1" style="flex-basis: 220px;">
                    <h2 id="passkey-offer-{{ $guard }}" class="m-0 text-base font-semibold text-ink">{{ __('sign_in.offer.title') }}</h2>
                    <p class="m-0 mt-1 text-sm text-slate">{{ __('sign_in.offer.body') }}</p>
                    @error('passkey') <p class="field-error m-0 mt-2" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn btn-ghost" x-on:click="later()" x-bind:disabled="busy">{{ __('sign_in.offer.later') }}</button>
                    <button type="button" class="btn btn-primary" x-on:click="add()" x-bind:disabled="busy" x-bind:aria-busy="busy.toString()">
                        <i data-lucide="fingerprint"></i>
                        <span x-show="!busy">{{ __('sign_in.offer.add') }}</span>
                        <span x-show="busy" style="display: none;">{{ __('sign_in.settings.adding_passkey') }}</span>
                    </button>
                </div>
            </div>
        </section>
    @endif
</div>
