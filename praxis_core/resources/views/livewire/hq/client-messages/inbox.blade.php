{{-- Client messages (docs/CHAT.md): every client conversation this person may open, unread first; the chosen one on
     the right. Phones: the list, and the conversation over it. App\Livewire\Hq\ClientMessages\Inbox. --}}
@php
    $today = now()->setTimezone('Africa/Lagos')->startOfDay();
    $when = function ($at) use ($today) {
        if (! $at) return '';
        if ($at->copy()->startOfDay()->equalTo($today)) return $at->format('H:i');
        if ($at->copy()->startOfDay()->equalTo($today->copy()->subDay())) return 'Yesterday';
        return $at->isoFormat($at->year === $today->year ? 'D MMM' : 'D MMM YYYY');
    };
    $initials = fn (string $name) => collect(preg_split('/\s+/u', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '·';
    $unreadTotal = array_sum(array_column($chats, 'unread'));
@endphp
<div class="chat-inbox-page" @chat-back="$wire.back()">
    <div class="page-header">
        <div>
            <h1 class="page-title">Client messages</h1>
            <p class="page-subtitle">Each client project has one conversation with its delivery team: questions, files, voice notes and issues. What you write reaches the client in their Client Portal.</p>
        </div>
    </div>

    <div class="chat-inbox {{ $selected ? 'has-selection' : '' }}">
        <aside class="chat-list" aria-label="Conversations" wire:poll.15s.visible="refreshList">
            <div class="chat-list-search">
                <label for="chat-inbox-search" class="sr-only">Find a client or project</label>
                <i data-lucide="search" aria-hidden="true"></i>
                <input type="search" id="chat-inbox-search" class="input" wire:model.live.debounce.300ms="search" placeholder="Find a client or project" autocomplete="off">
            </div>
            @if ($unreadTotal > 0)
                <p class="chat-list-summary">{{ $unreadTotal }} unread {{ $unreadTotal === 1 ? 'message' : 'messages' }}</p>
            @endif
            <ul class="chat-list-items" role="list">
                @forelse ($chats as $chat)
                    <li wire:key="chat-{{ $chat['id'] }}">
                        <button type="button" class="chat-list-item {{ $selected === $chat['id'] ? 'is-active' : '' }} {{ $chat['unread'] ? 'is-unread' : '' }}"
                                wire:click="open('{{ $chat['id'] }}')" @if ($selected === $chat['id']) aria-current="true" @endif>
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
        </aside>

        <div class="chat-inbox-thread">
            @if ($selected)
                <livewire:hq.client-messages.thread :project-id="$selected" :key="'inbox-thread-' . $selected" />
            @else
                <div class="chat-inbox-placeholder">
                    <span class="chat-empty-icon" aria-hidden="true"><i data-lucide="messages-square"></i></span>
                    <strong>Choose a conversation</strong>
                    <p>Unread conversations come first. A client’s new message also reaches you as a notification.</p>
                </div>
            @endif
        </div>
    </div>
</div>
