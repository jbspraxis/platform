{{-- Notification settings (docs/PWA.md): a slide-over opened from the bell menu (browser event
     open-notification-settings). hq-pwa.js draws "This device" (wire:ignore keeps it); the person's devices and
     the groups come from the server when the panel opens. Focus stays inside while it is open and goes back where
     it came from when it closes; Escape closes it. The two outer layers keep their own attributes when Livewire
     redraws the panel (wire:ignore.self), so Alpine alone shows and hides them. --}}
<div x-data="{
        open: false,
        confirming: null,
        removing: null,
        returnTo: null,
        subscribed: null,
        async show() {
            if (this.open) return;
            this.returnTo = document.activeElement;
            this.confirming = null;
            this.open = true;
            await this.load();
        },
        async load() {
            const hash = window.hqPwa ? await window.hqPwa.deviceHash() : null;
            await this.$wire.open(hash);
        },
        close() {
            if (! this.open) return;
            this.open = false;
            this.confirming = null;
            const back = this.returnTo;
            this.$nextTick(() => {
                const el = back && back.isConnected && back.getClientRects().length ? back : document.querySelector('[data-hq-bell-button]');
                if (el) el.focus();
            });
        },
        escape() {
            if (! this.open) return;
            if (this.confirming !== null) this.keep(this.confirming); else this.close();
        },
        ask(id) {
            this.confirming = id;
            this.$nextTick(() => this.$refs['keep' + id] && this.$refs['keep' + id].focus());
        },
        keep(id) {
            this.confirming = null;
            this.$nextTick(() => this.$refs['remove' + id] && this.$refs['remove' + id].focus());
        },
        async remove(id, isThisDevice) {
            if (this.removing !== null) return;
            this.removing = id;
            try {
                await this.$wire.removeDevice(id);
                if (isThisDevice && window.hqPwa) await window.hqPwa.turnOff({ server: false });
            } finally {
                this.removing = null;
                this.confirming = null;
                this.$nextTick(() => this.$refs.devicesTitle.focus());
            }
        },
        stateChanged(detail) {
            const changed = this.subscribed !== null && this.subscribed !== detail.subscribed;
            this.subscribed = detail.subscribed;
            if (this.open && changed) this.load();
        },
     }"
     x-on:open-notification-settings.window="show()"
     x-on:close-notification-settings.window="close()"
     x-on:keydown.escape.window="escape()"
     x-on:hq-pwa-state.window="stateChanged($event.detail)">

    <div class="hqp-backdrop" wire:ignore.self x-show="open" style="display: none;" x-on:click="close()"
         x-transition:enter="hqp-t-enter" x-transition:enter-start="hqp-t-fade-start" x-transition:enter-end="hqp-t-fade-end"
         x-transition:leave="hqp-t-leave" x-transition:leave-start="hqp-t-fade-end" x-transition:leave-end="hqp-t-fade-start"></div>

    <aside class="hqp-panel" wire:ignore.self role="dialog" aria-modal="true" aria-labelledby="hqp-settings-title"
           x-show="open" style="display: none;" x-trap.inert.noscroll.noreturn="open"
           x-transition:enter="hqp-t-enter" x-transition:enter-start="hqp-t-panel-start" x-transition:enter-end="hqp-t-panel-end"
           x-transition:leave="hqp-t-leave" x-transition:leave-start="hqp-t-panel-end" x-transition:leave-end="hqp-t-panel-start">
        <div class="hqp-panel-head">
            <div>
                <h2 id="hqp-settings-title" class="hqp-panel-title">Notification settings</h2>
                <p class="hqp-panel-intro">Everything that arrives in your HQ bell can also reach your phone or computer.</p>
            </div>
            <button type="button" class="hqp-icon-btn" x-on:click="close()" aria-label="Close notification settings">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <div class="hqp-panel-body">
            <section aria-labelledby="hqp-this-title">
                <h3 id="hqp-this-title" class="hqp-section-title">This device</h3>
                <div wire:ignore data-hq-pwa="settings-device" aria-live="polite">
                    <p class="help">Reload the page to see whether notifications are on for this device.</p>
                </div>
            </section>

            <section aria-labelledby="hqp-devices-title">
                <h3 id="hqp-devices-title" class="hqp-section-title" x-ref="devicesTitle" tabindex="-1">Your devices</h3>
                <p class="help">Phones and computers where you turned on notifications. Each one gets every notification you haven’t muted.</p>

                @if (! $loaded)
                    <p class="hqp-loading">Loading your devices…</p>
                @elseif ($devices->isEmpty())
                    <div class="hqp-empty">
                        <strong>No devices yet</strong>
                        Turn on notifications on this device, or in the HQ app on your phone, and it appears here.
                    </div>
                @else
                    <ul class="hqp-devices">
                        @foreach ($devices as $device)
                            <li class="hqp-device" wire:key="device-{{ $device['id'] }}">
                                <div class="hqp-device-row">
                                    <span class="hqp-device-icon" aria-hidden="true">
                                        @if ($device['phone'])
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                                        @else
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>
                                        @endif
                                    </span>
                                    <div class="hqp-device-text">
                                        <p class="hqp-device-name">
                                            {{ $device['label'] }}
                                            @if ($device['this'])
                                                <span class="tag">This device</span>
                                            @endif
                                        </p>
                                        <p class="hqp-device-meta">Added {{ $device['added'] }} · Last active {{ $device['seen'] }}</p>
                                    </div>
                                    <button type="button" class="btn btn-ghost btn-sm" x-ref="remove{{ $device['id'] }}"
                                            x-show="confirming !== {{ $device['id'] }}" x-on:click="ask({{ $device['id'] }})"
                                            aria-label="Remove {{ $device['label'] }}">Remove</button>
                                </div>
                                <div class="hqp-confirm" role="group" aria-labelledby="hqp-confirm-{{ $device['id'] }}"
                                     x-show="confirming === {{ $device['id'] }}" style="display: none;">
                                    <p id="hqp-confirm-{{ $device['id'] }}">
                                        @if ($device['this'])
                                            Remove {{ $device['label'] }}? This device stops getting HQ notifications until you turn them on again.
                                        @else
                                            Remove {{ $device['label'] }}? It stops getting HQ notifications. If you use HQ on it again with notifications allowed, it’s added back.
                                        @endif
                                    </p>
                                    <div class="hqp-confirm-actions">
                                        <button type="button" class="btn btn-danger btn-sm" x-on:click="remove({{ $device['id'] }}, @js($device['this']))"
                                                x-bind:aria-disabled="removing === {{ $device['id'] }} ? 'true' : null"
                                                x-text="removing === {{ $device['id'] }} ? 'Removing…' : 'Remove device'">Remove device</button>
                                        <button type="button" class="btn btn-secondary btn-sm" x-ref="keep{{ $device['id'] }}" x-on:click="keep({{ $device['id'] }})">Keep device</button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section aria-labelledby="hqp-groups-title">
                <h3 id="hqp-groups-title" class="hqp-section-title">What to notify me about</h3>
                <p class="help">Muted groups still appear in the bell.</p>
                @if ($loaded)
                    <ul class="hqp-switches">
                        @foreach ($labels as $group => $label)
                            <li wire:key="group-{{ $group }}">
                                <label class="hqp-switch-row">
                                    <span>{{ $label }}</span>
                                    <span class="hqp-switch">
                                        <input type="checkbox" role="switch" wire:model.live="groups.{{ $group }}">
                                        <span class="hqp-switch-track" aria-hidden="true"></span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <p class="hqp-version">HQ app version {{ $version }}</p>
        </div>
    </aside>
</div>
