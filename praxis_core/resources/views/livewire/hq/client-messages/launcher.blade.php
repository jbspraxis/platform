{{-- The floating "Client messages" button on every HQ page, and its panel: the conversations, then the one chosen
     (docs/CHAT.md). App\Livewire\Hq\ClientMessages\Launcher, kept across pages by @persist in the HQ layout. --}}
@php
    $today = now()->setTimezone('Africa/Lagos')->startOfDay();
    $when = function ($at) use ($today) {
        if (! $at) return '';
        if ($at->copy()->startOfDay()->equalTo($today)) return $at->format('H:i');
        if ($at->copy()->startOfDay()->equalTo($today->copy()->subDay())) return 'Yesterday';
        return $at->isoFormat($at->year === $today->year ? 'D MMM' : 'D MMM YYYY');
    };
    $initials = fn (string $name) => collect(preg_split('/\s+/u', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '·';
    $label = 'Client messages' . ($unread ? ', ' . $unread . ' unread' : '');
@endphp
<div class="chat-launcher"
     x-data="chatLauncher" x-show="!here" @chat-back="$wire.back()" @keydown.escape="if (!$event.defaultPrevented) { back = true; $wire.hide(); }"
     wire:poll.30s.visible="refresh">
    @unless ($open)
        <button type="button" class="chat-fab hq-lift" wire:click="show" aria-haspopup="dialog" aria-label="{{ $label }}" title="{{ $label }}"
                x-init="if (back) { $el.focus(); back = false; }">
            <i data-lucide="message-circle" aria-hidden="true"></i>
            <span class="chat-fab-label">Client messages</span>
            @if ($unread)
                <span class="chat-fab-badge" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
            @endif
        </button>
    @else
        <section class="chat-panel chat-launcher-panel" role="dialog" :aria-modal="phone ? 'true' : 'false'" aria-labelledby="chat-launcher-title"
                 x-trap.noscroll="phone" x-init="$nextTick(() => $el.querySelector('input, textarea')?.focus({ preventScroll: true }))">
            @if ($project === '')
                <header class="chat-head">
                    <button type="button" class="chat-head-btn chat-back" wire:click="hide" @click="back = true" aria-label="Close Client messages" title="Close"><i data-lucide="arrow-left"></i></button>
                    <span class="chat-head-mark" aria-hidden="true"><img src="/assets/img/logo/jbs-praxis-monogram-colour.svg" alt="" width="28" height="28"></span>
                    <div class="chat-head-text">
                        <h2 id="chat-launcher-title" class="chat-head-title">Client messages</h2>
                        <p class="chat-head-sub"><span>{{ $unread ? $unread . ' unread ' . ($unread === 1 ? 'message' : 'messages') : 'All caught up' }}</span></p>
                    </div>
                    <a href="{{ route('hq.ops.messages') }}" class="chat-head-btn" wire:navigate title="Open the Client messages page" aria-label="Open the Client messages page"><i data-lucide="maximize-2"></i></a>
                    <button type="button" class="chat-head-btn chat-close" wire:click="hide" @click="back = true" aria-label="Close Client messages" title="Close"><i data-lucide="x"></i></button>
                </header>
                <div class="chat-list-search">
                    <label for="chat-launcher-search" class="sr-only">Find a client or project</label>
                    <i data-lucide="search" aria-hidden="true"></i>
                    <input type="search" id="chat-launcher-search" class="input" wire:model.live.debounce.300ms="search" placeholder="Find a client or project" autocomplete="off">
                </div>
                <ul class="chat-list-items chat-launcher-list" role="list">
                    @forelse ($chats as $chat)
                        <li wire:key="launcher-chat-{{ $chat['id'] }}">
                            <button type="button" class="chat-list-item {{ $chat['unread'] ? 'is-unread' : '' }}" wire:click="choose('{{ $chat['id'] }}')">
                                <span class="chat-list-avatar" aria-hidden="true">{{ $initials($chat['client']) }}</span>
                                <span class="chat-list-text">
                                    <span class="chat-list-top">
                                        <span class="chat-list-client">{{ $chat['client'] }}</span>
                                        <span class="chat-list-time">{{ $when($chat['last']['at'] ?? null) }}</span>
                                    </span>
                                    <span class="chat-list-project">{{ $chat['title'] }}</span>
                                    <span class="chat-list-bottom">
                                        <span class="chat-list-last">
                                            @if ($chat['last'])
                                                @if ($chat['last']['internal'])<i data-lucide="lock" aria-hidden="true"></i>@endif
                                                {{ $chat['last']['who'] ? $chat['last']['who'] . ': ' : '' }}{{ $chat['last']['text'] }}
                                            @else
                                                No messages yet
                                            @endif
                                        </span>
                                        @if ($chat['unread'])
                                            <span class="chat-list-badge" aria-label="{{ $chat['unread'] }} unread">{{ $chat['unread'] > 99 ? '99+' : $chat['unread'] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </button>
                        </li>
                    @empty
                        <li class="chat-list-empty">
                            <strong>{{ $search !== '' ? 'No conversation matches' : 'No client conversations yet' }}</strong>
                            {{ $search !== '' ? 'Try another client or project name.' : 'A conversation appears here for each running client project you can see.' }}
                        </li>
                    @endforelse
                </ul>
            @else
                <livewire:hq.client-messages.thread :project-id="$project" :in-launcher="true" :key="'launcher-thread-' . $project" />
            @endif
        </section>
    @endunless
</div>
