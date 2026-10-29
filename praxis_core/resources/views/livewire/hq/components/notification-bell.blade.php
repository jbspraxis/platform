@php
    // Display labels for the stored notification types (the stored values stay as they are)
    $typeLabel = fn ($type) => match ($type ?? 'System') {
        'Financial' => 'Finance',
        'System', 'System_Alert' => 'System',
        'QA_Update' => 'Quality',
        'Project' => 'Project',
        default => \Illuminate\Support\Str::of($type)->replace('_', ' ')->lower()->ucfirst()->toString(),
    };
@endphp
<div x-data="{ open: false }" class="relative flex items-center" @keydown.escape.window="open = false">
    <button type="button" class="btn btn-ghost relative" style="padding: 8px;" @click="open = !open"
            :aria-expanded="open.toString()" aria-haspopup="true" title="Notifications"
            aria-label="Notifications{{ $unreadCount > 0 ? ', ' . $unreadCount . ' unread' : '' }}">
        <i data-lucide="bell" style="width: 20px; height: 20px; color: var(--color-slate);"></i>
        @if ($unreadCount > 0)
            <span aria-hidden="true" style="position: absolute; top: 0; right: 0; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--color-danger); color: #fff; font-size: 0.6875rem; line-height: 18px; font-weight: 600; text-align: center; font-variant-numeric: tabular-nums;">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-transition.opacity @click.outside="open = false" role="dialog" aria-label="Notifications"
         class="bg-paper border border-line rounded-card shadow-float"
         style="display: none; position: absolute; right: 0; top: calc(100% + 8px); width: min(360px, calc(100vw - 32px)); z-index: 60; overflow: hidden;">
        <div class="flex items-center justify-between gap-3 border-b border-line" style="padding: 12px 16px;">
            <span class="text-ink" style="font-size: 0.875rem; font-weight: 600;">Notifications</span>
            <span class="help num">{{ $unreadCount }} unread</span>
        </div>

        <div style="max-height: 400px; overflow-y: auto;">
            @forelse ($notifications as $note)
                <button type="button" wire:click="markAndRedirect(@js((string) $note->id), @js($note->link_url ?? '#'))"
                        class="block w-full text-left border-b border-line hover:bg-mist {{ $note->is_read ? 'bg-paper' : 'bg-teal-tint' }}"
                        style="padding: 12px 16px; cursor: pointer; {{ $note->is_read ? '' : 'box-shadow: inset 2px 0 0 var(--color-deep-teal);' }}">
                    <span class="flex items-center justify-between gap-2" style="margin-bottom: 4px;">
                        <span class="pill pill-neutral" style="font-size: 0.75rem; padding: 0 8px;">{{ $typeLabel($note->type ?? null) }}</span>
                        @unless ($note->is_read)
                            <span class="help" style="color: var(--color-deep-teal); font-weight: 600;">New</span>
                        @endunless
                    </span>
                    <span class="block text-ink" style="font-size: 0.875rem; line-height: 1.25rem; font-weight: 600;">{{ $note->title }}</span>
                    <span class="block" style="font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-slate); margin-top: 2px;">{{ $note->message }}</span>
                    <span class="block help" style="margin-top: 6px;">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</span>
                </button>
            @empty
                <div class="empty-state" style="padding: 32px 20px;">
                    <strong>No notifications</strong>
                    Payments, approvals and project updates that need you appear here.
                </div>
            @endforelse
        </div>
    </div>
</div>
