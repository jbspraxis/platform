{{-- The client's conversation with its delivery team (docs/CHAT.md): the floating button, and the chat as a panel on
     computers or the whole screen on phones (safe areas, keyboard). App\Livewire\ClientOs\DeliveryChat. --}}
@php
    $chatKey = $this->getId();
    $fileUrl = fn (int $id) => route('portal.chat.file', $id);
    $config = [
        'id' => $chatKey,
        'startOpen' => $startOpen,
        'poll' => \App\Support\Chat\ProjectChat::POLL_MS,
        'idle' => \App\Support\Chat\ProjectChat::IDLE_POLL_MS,
        'maxVoiceMs' => \App\Support\Chat\ProjectChat::MAX_VOICE_SECONDS * 1000,
        'strings' => [
            'typingOne' => __('chat.typing_one'), 'typingMany' => __('chat.typing_many'), 'micDenied' => __('chat.mic_denied'),
            'micUnsupported' => __('chat.mic_unsupported'), 'and' => app()->getLocale() === 'fr' ? ' et ' : ' and ',
            'newOne' => trans_choice('chat.new_below', 1), 'newMany' => trans_choice('chat.new_below', 2, ['count' => '__N__']),
        ],
    ];
@endphp
<div class="chat-root" x-data="chatRoom(@js($config))" data-unread="{{ $unread }}" data-seen="{{ $seen }}" data-typing="{{ json_encode(array_values($typing)) }}"
     @open-delivery-chat.window="open($event.detail || {})" @chat-state.window="onState($event.detail)" @chat-changed.window="onChanged($event.detail)"
     @chat-sent.window="onSent($event.detail)" @chat-focus.window="onFocusAsked($event.detail)" @chat-reveal.window="onReveal($event.detail)"
     @keydown.escape="if (!emojiOpen && !recording) close()">

    {{-- The floating button, from any section --}}
    <button type="button" class="chat-fab" x-ref="fab" x-show="!isOpen" @click="open()" aria-haspopup="dialog" :aria-expanded="isOpen.toString()"
            :aria-label="unread > 0 ? @js(__('chat.open_unread', ['count' => '__N__'])).replace('__N__', unread) : @js(__('chat.open'))"
            aria-label="{{ $unread > 0 ? __('chat.open_unread', ['count' => $unread]) : __('chat.open') }}">
        <i data-lucide="message-circle" aria-hidden="true"></i>
        <span class="chat-fab-label">{{ __('chat.open') }}</span>
        <span class="chat-fab-badge" x-show="unread > 0" x-text="unread > 99 ? '99+' : unread" aria-hidden="true" @if (! $unread) style="display: none;" @endif>{{ $unread > 99 ? '99+' : $unread }}</span>
    </button>

    {{-- The chat --}}
    <section class="chat-panel" x-show="isOpen" style="display: none;" x-ref="panel"
             x-transition:enter="chat-enter" x-transition:enter-start="chat-enter-start" x-transition:enter-end="chat-enter-end"
             x-transition:leave="chat-leave" x-transition:leave-start="chat-enter-end" x-transition:leave-end="chat-enter-start"
             x-trap.noscroll="isOpen && phone" role="dialog" :aria-modal="phone ? 'true' : 'false'" aria-labelledby="chat-{{ $chatKey }}-title" :style="viewportStyle">
        <header class="chat-head">
            <button type="button" class="chat-head-btn chat-back" @click="close()" aria-label="{{ __('chat.back') }}" title="{{ __('chat.back') }}"><i data-lucide="arrow-left"></i></button>
            <span class="chat-head-mark" aria-hidden="true"><img src="/assets/img/logo/jbs-praxis-monogram-colour.svg" alt="" width="28" height="28"></span>
            <div class="chat-head-text">
                <h2 id="chat-{{ $chatKey }}-title" class="chat-head-title">{{ __('chat.title') }}</h2>
                <p class="chat-head-sub" aria-live="polite">
                    <span class="chat-typing" x-show="typingText" x-text="typingText" style="display: none;"></span>
                    <span x-show="!typingText" class="truncate">{{ $project->title ?? '' }}</span>
                </p>
            </div>
            <button type="button" class="chat-head-btn chat-close" @click="close()" aria-label="{{ __('chat.close') }}" title="{{ __('chat.close') }}"><i data-lucide="x"></i></button>
        </header>

        <div class="chat-scroll" x-ref="scroll" @scroll.passive="onScroll()" role="log" aria-live="polite" aria-relevant="additions"
             aria-label="{{ __('chat.log_label', ['team' => __('chat.title')]) }}" tabindex="0">
            <div class="chat-thread">
                @include('partials.chat.conversation', ['party' => 'client', 'me' => $client->id, 'canWrite' => $canWrite, 'canApprove' => $canApprove])
            </div>
        </div>

        <button type="button" class="chat-jump" x-show="!atBottom" style="display: none;" @click="scrollToBottom(true)" aria-label="{{ __('chat.jump_latest') }}">
            <span x-show="newBelow > 0" x-text="newBelowText" class="chat-jump-count"></span>
            <i data-lucide="chevron-down" aria-hidden="true"></i>
        </button>

        @include('partials.chat.composer', ['party' => 'client', 'me' => $client->id, 'canWrite' => $canWrite, 'issueMode' => $issueMode, 'tasks' => $tasks])
    </section>
</div>
