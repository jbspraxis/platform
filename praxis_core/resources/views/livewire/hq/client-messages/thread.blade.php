{{-- HQ's side of a client's conversation (docs/CHAT.md): App\Livewire\Hq\ClientMessages\Thread. The same messages
     the client sees, plus internal notes; on a phone it covers the screen and Back returns to the list. --}}
@php
    $chatKey = $this->getId();
    $fileUrl = fn (int $id) => route('hq.chat.file', $id);
    $config = [
        'id' => $chatKey,
        'embedded' => true,
        'poll' => \App\Support\Chat\ProjectChat::POLL_MS,
        'idle' => \App\Support\Chat\ProjectChat::IDLE_POLL_MS,
        'maxVoiceMs' => \App\Support\Chat\ProjectChat::MAX_VOICE_SECONDS * 1000,
        'strings' => [
            'typingOne' => __('chat.typing_one'), 'typingMany' => __('chat.typing_many'), 'micDenied' => __('chat.mic_denied'),
            'micUnsupported' => __('chat.mic_unsupported'), 'and' => ' and ',
            'newOne' => trans_choice('chat.new_below', 1), 'newMany' => trans_choice('chat.new_below', 2, ['count' => '__N__']),
        ],
    ];
    $clientInitials = collect(preg_split('/\s+/u', trim((string) ($project->client ?? ''))))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $readersText = count($readers) === 0
        ? 'Nobody at ' . ($project->client ?? 'the client') . ' has a Client Portal account that reads this conversation yet. Invite someone from Clients.'
        : (count($readers) === 1 ? $readers[0] . ' reads this conversation in the Client Portal.'
            : implode(', ', array_slice($readers, 0, -1)) . ' and ' . end($readers) . ' read this conversation in the Client Portal.');
@endphp
<div class="chat-hq-thread" x-data="chatRoom(@js($config))" data-unread="{{ $unread }}" data-seen="{{ $seen }}" data-typing="{{ json_encode(array_values($typing)) }}"
     @chat-state.window="onState($event.detail)" @chat-changed.window="onChanged($event.detail)" @chat-sent.window="onSent($event.detail)"
     @chat-focus.window="onFocusAsked($event.detail)" @chat-reveal.window="onReveal($event.detail)"
     @chat-jump-to.window="if ($event.detail.project === @js($project->id ?? '')) jump($event.detail.message)">
    <section class="chat-panel is-embedded {{ $inLauncher ? 'in-launcher' : '' }}" x-ref="panel" aria-labelledby="chat-{{ $chatKey }}-title" :style="viewportStyle">
        <header class="chat-head">
            <button type="button" class="chat-head-btn chat-back {{ $inLauncher ? 'is-always' : '' }}" @click="close()" aria-label="Back to the conversations" title="Back to the conversations"><i data-lucide="arrow-left"></i></button>
            <span class="chat-head-mark is-client" aria-hidden="true">{{ $clientInitials ?: '·' }}</span>
            <div class="chat-head-text">
                <h2 id="chat-{{ $chatKey }}-title" class="chat-head-title">{{ $project->client ?? '' }}</h2>
                <p class="chat-head-sub" aria-live="polite">
                    <span class="chat-typing" x-show="typingText" x-text="typingText" style="display: none;"></span>
                    <span x-show="!typingText">{{ $project->title ?? '' }}</span>
                </p>
            </div>
            @if ($inLauncher)
                <a href="{{ route('hq.ops.messages', ['project' => $projectId]) }}" class="chat-head-btn" wire:navigate title="Open in Client messages" aria-label="Open in Client messages"><i data-lucide="maximize-2"></i></a>
            @endif
            @if (\App\Support\Projects\ProjectRules::canView($projectId))
                <a href="{{ route('hq.ops.project', ['project' => $projectId]) }}" class="chat-head-btn" wire:navigate title="Open the project page" aria-label="Open the project page"><i data-lucide="folder-kanban"></i></a>
            @endif
        </header>
        <div class="chat-readers {{ count($readers) === 0 ? 'is-warning' : '' }}">
            <i data-lucide="{{ count($readers) === 0 ? 'circle-alert' : 'users' }}" aria-hidden="true"></i>
            <span>{{ $readersText }}</span>
        </div>

        <div class="chat-scroll" x-ref="scroll" @scroll.passive="onScroll()" role="log" aria-live="polite" aria-relevant="additions"
             aria-label="Conversation with {{ $project->client ?? 'the client' }}" tabindex="0">
            <div class="chat-thread">
                @include('partials.chat.conversation', ['party' => 'staff', 'me' => $user->id, 'canWrite' => $canWrite, 'canApprove' => false])
            </div>
        </div>

        <button type="button" class="chat-jump" x-show="!atBottom" style="display: none;" @click="scrollToBottom(true)" aria-label="{{ __('chat.jump_latest') }}">
            <span x-show="newBelow > 0" x-text="newBelowText" class="chat-jump-count"></span>
            <i data-lucide="chevron-down" aria-hidden="true"></i>
        </button>

        @include('partials.chat.composer', ['party' => 'staff', 'me' => $user->id, 'canWrite' => $canWrite, 'internal' => $internal])
    </section>
</div>
