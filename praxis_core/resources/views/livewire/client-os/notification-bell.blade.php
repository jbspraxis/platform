{{-- The Client Portal's bell (docs/PWA.md, "Client Portal"): the same look as HQ's. "Notification settings" opens
     "This device" inside the menu (the portal has no settings panel); hq-pwa.js draws both of its boxes. --}}
<div x-data="{ open: false, device: false }" class="relative flex items-center"
     @keydown.escape.window="open = false"
     @open-notification-settings.window="open = true; device = true">
    <button type="button" class="btn btn-ghost relative" style="padding: 8px;" @click="open = !open" data-hq-bell-button
            :aria-expanded="open.toString()" aria-haspopup="true" title="{{ __('portal.inbox.title') }}"
            aria-label="{{ __('portal.inbox.title') }}{{ $unreadTotal > 0 ? ', ' . __('portal.inbox.unread', ['count' => $unreadTotal]) : '' }}">
        <i data-lucide="bell" style="width: 20px; height: 20px; color: var(--color-slate);"></i>
        @if ($unreadTotal > 0)
            <span aria-hidden="true" style="position: absolute; top: 0; right: 0; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--color-danger); color: #fff; font-size: 0.6875rem; line-height: 18px; font-weight: 600; text-align: center; font-variant-numeric: tabular-nums;">{{ $unreadTotal > 9 ? '9+' : $unreadTotal }}</span>
        @endif
    </button>

    <div x-show="open" x-transition.opacity @click.outside="open = false" role="dialog" aria-label="{{ __('portal.inbox.title') }}"
         class="bg-paper border border-line rounded-card shadow-float"
         style="display: none; position: absolute; right: 0; top: calc(100% + 8px); width: min(360px, calc(100vw - 32px)); z-index: 60; overflow: hidden;">
        <div class="flex items-center justify-between gap-3 border-b border-line" style="padding: 12px 16px;">
            <span class="text-ink" style="font-size: 0.875rem; font-weight: 600;">{{ __('portal.inbox.title') }}</span>
            @if ($unreadTotal > 0)
                <button type="button" class="btn btn-ghost btn-sm" wire:click="markAllRead">{{ __('portal.inbox.mark_all') }}</button>
            @else
                <span class="help num">{{ __('portal.inbox.unread', ['count' => 0]) }}</span>
            @endif
        </div>

        <div style="max-height: 400px; overflow-y: auto;">
            @forelse ($items as $item)
                <a href="{{ route('portal.pwa.open', ['notification' => $item['id']]) }}"
                   class="block w-full text-left border-b border-line hover:bg-mist {{ $item['read'] ? 'bg-paper' : 'bg-teal-tint' }}"
                   style="padding: 12px 16px; text-decoration: none; {{ $item['read'] ? '' : 'box-shadow: inset 2px 0 0 var(--color-deep-teal);' }}">
                    <span class="flex items-center justify-between gap-2" style="margin-bottom: 4px;">
                        <span class="pill pill-neutral" style="font-size: 0.75rem; padding: 0 8px;">{{ $item['label'] }}</span>
                        @unless ($item['read'])
                            <span class="help" style="color: var(--color-deep-teal); font-weight: 600;">{{ __('portal.inbox.new') }}</span>
                        @endunless
                    </span>
                    <span class="block text-ink" style="font-size: 0.875rem; line-height: 1.25rem; font-weight: 600;">{{ $item['title'] }}</span>
                    <span class="block" style="font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-slate); margin-top: 2px;">{{ $item['body'] }}</span>
                    <span class="block help" style="margin-top: 6px;">{{ $item['when'] }}</span>
                </a>
            @empty
                <div class="empty-state" style="padding: 32px 20px;">
                    <strong>{{ __('portal.inbox.empty_title') }}</strong>
                    {{ __('portal.inbox.empty_body') }}
                </div>
            @endforelse
        </div>

        <div class="hqp-bell-foot">
            <div wire:ignore data-hq-pwa="bell-actions" x-on:hq-pwa-close-menu="open = false"></div>
            <button type="button" class="hqp-menu-item" x-on:click="device = !device" :aria-expanded="device.toString()" aria-controls="portal-this-device">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/></svg>
                {{ __('portal.inbox.settings') }}
            </button>
            {{-- This device: its state, turn on or off, a test, how to allow notifications again (hq-pwa.js) --}}
            <div id="portal-this-device" x-show="device" style="display: none; padding: 4px 16px 12px;">
                <p class="help" style="margin: 0 0 4px; font-weight: 600;">{{ __('portal.inbox.this_device') }}</p>
                <div wire:ignore data-hq-pwa="settings-device"></div>
            </div>
        </div>
    </div>
</div>
