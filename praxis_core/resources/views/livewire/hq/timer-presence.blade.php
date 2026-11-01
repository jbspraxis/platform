<div>
    {{-- The 45-minute presence check on every HQ page (App\Livewire\Hq\TimerPresence). The key restarts the clock when a timer starts. --}}
    @if ($running)
        <div wire:key="presence-on" x-data="{
                open: false, ask: null, stop: null,
                init() {
                    this.ask = setTimeout(() => {
                        this.open = true;
                        this.stop = setTimeout(() => { this.open = false; $wire.pause() }, 300000); // 5 minutes to answer
                    }, 2700000); // 45 minutes
                },
                destroy() { clearTimeout(this.ask); clearTimeout(this.stop) },
                stillHere() { clearTimeout(this.ask); clearTimeout(this.stop); this.open = false; this.init() },
            }">
            <div class="modal-backdrop" x-show="open" x-cloak x-transition.opacity style="z-index: 80">
                <div class="modal text-center" role="alertdialog" aria-modal="true" aria-labelledby="presence-title" aria-describedby="presence-text" x-trap.noscroll="open">
                    <i data-lucide="clock" class="mx-auto mb-3 h-8 w-8 text-deep-teal"></i>
                    <h2 id="presence-title" class="modal-title">{{ __('projects.presence.title') }}</h2>
                    <p id="presence-text" class="text-small text-slate">{{ __('projects.presence.text') }}</p>
                    <div class="modal-actions justify-center">
                        <button type="button" x-on:click="stillHere()" class="btn btn-primary">{{ __('projects.presence.keep') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($notice)
        <div class="alert alert-warning" role="alert" style="position: fixed; top: 80px; right: 24px; z-index: 70; max-width: 420px; box-shadow: var(--shadow-float)" wire:key="presence-notice">
            <i data-lucide="triangle-alert"></i>
            <span style="flex: 1">{{ $notice }}</span>
            <button type="button" class="btn btn-ghost btn-sm" wire:click="dismiss" aria-label="{{ __('projects.drawer.close') }}"><i data-lucide="x"></i></button>
        </div>
    @endif
</div>
