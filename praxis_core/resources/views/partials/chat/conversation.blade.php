{{-- The messages of a project's conversation (docs/CHAT.md), shared by the Client Portal and HQ.
     $party: who is reading ('client' | 'staff'); $me: their id; $messages, $more: ProjectChat::messages();
     $lastReadAtOpen: where "Unread messages" goes; $canWrite: may reply; $canApprove: a client who may approve fixes;
     $fileUrl: fn (int $attachmentId) => url; $chatKey: unique per chat on the page (element ids).
     Icons chosen in PHP below, listed for scripts/icons.mjs: data-lucide="file-text" data-lucide="file-spreadsheet"
     data-lucide="file-archive" data-lucide="file" data-lucide="search" data-lucide="circle-check" data-lucide="badge-check"
     data-lucide="check-check" data-lucide="info" --}}
@php
    $tz = 'Africa/Lagos';
    $today = now()->setTimezone($tz)->startOfDay();
    $dayLabel = function ($at) use ($today) {
        $day = $at->copy()->startOfDay();
        if ($day->equalTo($today)) return __('chat.today');
        if ($day->equalTo($today->copy()->subDay())) return __('chat.yesterday');
        return $at->copy()->locale(app()->getLocale())->isoFormat($day->year === $today->year ? 'dddd D MMMM' : 'dddd D MMMM YYYY');
    };
    $size = function (int $bytes) {
        $fr = app()->getLocale() === 'fr';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, $fr ? ',' : '.', '') . ($fr ? "\u{202F}Mo" : ' MB');
        return max(1, (int) round($bytes / 1024)) . ($fr ? "\u{202F}Ko" : ' KB');
    };
    $duration = fn (?int $ms) => $ms === null ? '' : sprintf('%d:%02d', intdiv(intdiv($ms, 1000), 60), intdiv($ms, 1000) % 60);
    $pills = ['Open' => 'pill-warning', 'Investigating' => 'pill-info', 'Resolved' => 'pill-success', 'Closed' => 'pill-neutral'];
    $severityPills = ['Low' => 'pill-neutral', 'Medium' => 'pill-info', 'Critical' => 'pill-danger'];
    $fileIcon = fn (string $mime, string $name) => match (true) {
        str_contains($mime, 'pdf') => 'file-text',
        str_contains($mime, 'sheet') || str_contains($mime, 'excel') || str_ends_with(strtolower($name), '.csv') => 'file-spreadsheet',
        str_contains($mime, 'zip') => 'file-archive',
        default => 'file',
    };
    // The first message that is new for this reader since the chat was opened
    $unreadFrom = collect($messages)->first(fn ($m) => $m['id'] > $lastReadAtOpen && ! $m['imported'] && $m['kind'] !== 'event'
        && ! ($m['party'] === $party && $m['author_id'] === $me) && ($party === 'client' || $m['party'] === 'client'))['id'] ?? null;
    $previous = null;
@endphp

@if ($more)
    <div class="chat-earlier">
        <button type="button" class="btn btn-ghost btn-sm" wire:click="loadEarlier" wire:loading.attr="disabled" wire:target="loadEarlier">
            <i data-lucide="arrow-up"></i> {{ __('chat.earlier') }}
        </button>
    </div>
@endif

@forelse ($messages as $m)
    @php
        $mine = $m['party'] === $party;
        $self = $mine && $m['author_id'] === $me;
        $newDay = ! $previous || ! $previous['at']->isSameDay($m['at']);
        // Messages of one person within 5 minutes form a group: the name on the first, the avatar on the last
        $joinsPrevious = $previous && ! $newDay && $previous['kind'] !== 'event' && $m['kind'] !== 'event'
            && $previous['party'] === $m['party'] && $previous['author_id'] === $m['author_id'] && $previous['internal'] === $m['internal']
            && $m['at']->diffInMinutes($previous['at'], true) < 5 && $m['kind'] !== 'issue' && $previous['kind'] !== 'issue' && $m['id'] !== $unreadFrom;
        $next = $messages[$loop->index + 1] ?? null;
        $joinsNext = $next && $next['at']->isSameDay($m['at']) && $next['kind'] !== 'event' && $m['kind'] !== 'event'
            && $next['party'] === $m['party'] && $next['author_id'] === $m['author_id'] && $next['internal'] === $m['internal']
            && $next['at']->diffInMinutes($m['at'], true) < 5 && $next['kind'] !== 'issue' && $m['kind'] !== 'issue' && $next['id'] !== $unreadFrom;
        $previous = $m;
        $domId = 'chat-' . $chatKey . '-m-' . $m['id'];
        $time = $m['at']->format('H:i');
        $stamp = $m['at']->copy()->locale(app()->getLocale())->isoFormat(__('portal.formats.datetime')) . ' (WAT)';
    @endphp

    @if ($newDay)
        <div class="chat-day" role="separator"><span>{{ $dayLabel($m['at']) }}</span></div>
    @endif
    @if ($m['id'] === $unreadFrom)
        <div class="chat-unread-line" role="separator" id="chat-{{ $chatKey }}-unread"><span>{{ __('chat.unread_divider') }}</span></div>
    @endif

    @if ($m['kind'] === 'event')
        {{-- An issue changed: a line across the conversation --}}
        <div class="chat-event" id="{{ $domId }}" data-id="{{ $m['id'] }}" wire:key="m-{{ $m['id'] }}">
            <span>
                <i data-lucide="{{ ['investigating' => 'search', 'resolved' => 'circle-check', 'fix_approved' => 'badge-check', 'task_approved' => 'check-check'][$m['event']] ?? 'info' }}" aria-hidden="true"></i>
                {{ __('chat.events.' . $m['event'] . '.line', ['name' => $self ? $m['full_name'] : $m['name'], 'title' => $m['ticket']['title'] ?? ($m['quote']['title'] ?? '')]) }}
                <time datetime="{{ $m['at']->toIso8601String() }}" title="{{ $stamp }}">· {{ $time }}</time>
            </span>
        </div>
        @continue
    @endif

    <div class="chat-row {{ $mine ? 'is-mine' : 'is-theirs' }} {{ $joinsPrevious ? 'joins-previous' : '' }} {{ $joinsNext ? 'joins-next' : '' }}"
         id="{{ $domId }}" data-id="{{ $m['id'] }}" wire:key="m-{{ $m['id'] }}">
        @if (! $mine)
            <div class="chat-avatar-slot" aria-hidden="true">
                @unless ($joinsNext)
                    <span class="chat-avatar {{ $m['party'] === 'staff' ? 'is-staff' : 'is-client' }}">{{ $m['initials'] }}</span>
                @endunless
            </div>
        @endif

        <div class="chat-bubble {{ $m['kind'] === 'issue' ? 'is-issue' : '' }} {{ $m['internal'] ? 'is-internal' : '' }}">
            <span class="sr-only">{{ $self ? __('chat.you') : $m['name'] }}, {{ $stamp }}:</span>
            @if (! $joinsPrevious && ! $self && $m['kind'] !== 'issue')
                <div class="chat-name" aria-hidden="true">
                    {{ $m['name'] }}
                    @if ($m['party'] === 'staff' && $party === 'client')<span class="chat-org">{{ __('chat.jbs') }}</span>@endif
                </div>
            @endif
            @if ($m['internal'])
                <div class="chat-internal-tag"><i data-lucide="lock" aria-hidden="true"></i> {{ __('chat.internal') }}</div>
            @endif

            {{-- The message it answers --}}
            @if ($m['quote'] && $m['kind'] !== 'issue')
                @if ($m['quote']['missing'] ?? false)
                    <div class="chat-quote is-missing">{{ __('chat.quote_missing') }}</div>
                @else
                    <button type="button" class="chat-quote {{ $m['quote']['party'] === 'staff' ? 'is-staff' : 'is-client' }}" @click="jump({{ $m['quote']['id'] }})">
                        <span class="chat-quote-name">{{ $m['quote']['party'] === $party && $m['quote']['author_id'] === $me ? __('chat.you') : $m['quote']['name'] }}</span>
                        <span class="chat-quote-text">
                            @if ($m['quote']['kind'] === 'issue')
                                <i data-lucide="shield-alert" aria-hidden="true"></i> {{ $m['quote']['title'] }}
                            @elseif ($m['quote']['text'] !== '')
                                {{ $m['quote']['text'] }}
                            @elseif ($m['quote']['file'])
                                {{ __('chat.summary.' . $m['quote']['file']) }}
                            @endif
                        </span>
                    </button>
                @endif
            @endif

            {{-- An issue: its card --}}
            @if ($m['kind'] === 'issue' && $m['ticket'])
                @php $t = $m['ticket']; @endphp
                <div class="chat-issue-head">
                    <span class="chat-issue-label"><i data-lucide="shield-alert" aria-hidden="true"></i> {{ __('chat.issue.label') }}</span>
                    <span class="pill {{ $pills[$t['status']] ?? 'pill-neutral' }}">{{ __('chat.status.' . $t['status']) }}</span>
                    <span class="pill {{ $severityPills[$t['severity']] ?? 'pill-neutral' }}">{{ __('chat.severity.' . $t['severity']) }}</span>
                </div>
                <div class="chat-issue-title">{{ $t['title'] }}</div>
                <div class="chat-issue-meta">
                    {{ $self ? __('chat.you') : $m['name'] }}@if ($t['task']) · {{ __('chat.issue.task_value', ['task' => $t['task']]) }}@endif
                </div>
            @endif

            {{-- Files: photos as previews, voice notes as a player, the rest as a line to download --}}
            @if ($m['files'])
                @php $images = collect($m['files'])->where('kind', 'image'); @endphp
                @if ($images->isNotEmpty())
                    <div class="chat-images {{ $images->count() > 1 ? 'is-grid' : '' }}">
                        @foreach ($images as $f)
                            <a href="{{ $fileUrl($f['id']) }}" target="_blank" rel="noopener" class="chat-image" aria-label="{{ __('chat.open_image', ['name' => $f['name']]) }}">
                                <img src="{{ $fileUrl($f['id']) }}" alt="{{ $f['name'] }}" loading="lazy" decoding="async">
                            </a>
                        @endforeach
                    </div>
                @endif
                @foreach (collect($m['files'])->where('kind', 'voice') as $f)
                    <div class="chat-voice" x-data="chatVoice({{ (int) ($f['duration'] ?? 0) }})" wire:ignore wire:key="voice-{{ $f['id'] }}">
                        <button type="button" class="chat-voice-play" @click="toggle()" :aria-label="playing ? @js(__('chat.voice_pause')) : @js(__('chat.voice_play'))" aria-label="{{ __('chat.voice_play') }}">
                            <i data-lucide="play" x-show="!playing" aria-hidden="true"></i><i data-lucide="pause" x-show="playing" style="display: none;" aria-hidden="true"></i>
                        </button>
                        <input type="range" class="chat-voice-bar" min="0" max="1000" step="1" x-model.number="position" @input="seek()" aria-label="{{ __('chat.voice_position') }}" :style="'--chat-voice-fill:' + (position / 10) + '%'">
                        <span class="chat-voice-time" x-text="label()">{{ $duration($f['duration']) }}</span>
                        <audio preload="none" x-ref="audio" src="{{ $fileUrl($f['id']) }}" @timeupdate="sync()" @ended="ended()" @loadedmetadata="loaded()"></audio>
                        <span class="sr-only">{{ __('chat.voice_label') }}</span>
                    </div>
                @endforeach
                @foreach (collect($m['files'])->where('kind', 'file') as $f)
                    <a href="{{ $fileUrl($f['id']) }}" class="chat-file" target="_blank" rel="noopener" aria-label="{{ __('chat.download', ['name' => $f['name']]) }}">
                        <span class="chat-file-icon"><i data-lucide="{{ $fileIcon($f['mime'], $f['name']) }}" aria-hidden="true"></i></span>
                        <span class="chat-file-text"><span class="chat-file-name">{{ $f['name'] }}</span><span class="chat-file-size">{{ $size($f['size']) }}</span></span>
                        <i data-lucide="download" class="chat-file-dl" aria-hidden="true"></i>
                    </a>
                @endforeach
            @endif

            @if ($m['body'] !== '')
                <div class="chat-text" dir="auto">{!! \App\Support\Chat\ProjectChat::html($m['body']) !!}</div>
            @endif

            {{-- An issue's state and what the reader can do about it --}}
            @if ($m['kind'] === 'issue' && $m['ticket'])
                <div class="chat-issue-foot">
                    <span class="chat-issue-replies">{{ trans_choice('chat.issue.replies', $t['replies'], ['count' => $t['replies']]) }}</span>
                    @if ($party === 'client' && $canApprove && $t['status'] === 'Resolved')
                        <span class="chat-issue-note">{{ __('chat.issue.resolved_note') }}</span>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="approveFix('{{ $t['id'] }}')" wire:loading.attr="disabled">
                            <i data-lucide="circle-check"></i> {{ __('chat.issue.approve_fix') }}
                        </button>
                    @elseif ($party === 'client' && $canApprove && $t['task_id'] && $t['task'] && in_array($t['status'], ['Open', 'Investigating'], true))
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="approveTask('{{ $t['id'] }}')" wire:confirm="{{ __('chat.issue.approve_task_confirm') }}">
                            <i data-lucide="check"></i> {{ __('chat.issue.approve_task') }}
                        </button>
                    @endif
                    @if ($party === 'staff')
                        <a href="{{ route('hq.ops.qa', ['ticket' => $t['id']]) }}" class="btn btn-ghost btn-sm" wire:navigate>
                            <i data-lucide="shield-alert"></i> {{ __('chat.issue.open_hub') }}
                        </a>
                    @endif
                </div>
            @endif

            <div class="chat-meta">
                <time datetime="{{ $m['at']->toIso8601String() }}" title="{{ $stamp }}">{{ $time }}</time>
                @if ($mine && ! $m['internal'])
                    {{-- Sent, then Seen once someone on the other side has read up to here --}}
                    <span class="chat-tick" :class="{ 'is-seen': seen >= {{ $m['id'] }} }">
                        <i data-lucide="check" x-show="seen < {{ $m['id'] }}" aria-hidden="true" @if ($seen >= $m['id']) style="display: none;" @endif></i>
                        <i data-lucide="check-check" x-show="seen >= {{ $m['id'] }}" aria-hidden="true" @if ($seen < $m['id']) style="display: none;" @endif></i>
                        <span class="sr-only" x-text="seen >= {{ $m['id'] }} ? @js(__('chat.seen')) : @js(__('chat.sent'))">{{ $seen >= $m['id'] ? __('chat.seen') : __('chat.sent') }}</span>
                    </span>
                @endif
            </div>
        </div>

        @if ($canWrite)
            <div class="chat-actions">
                <button type="button" class="chat-action" wire:click="reply({{ $m['id'] }})" title="{{ __('chat.reply_to', ['name' => $self ? __('chat.you') : $m['name']]) }}"
                        aria-label="{{ __('chat.reply_to', ['name' => $self ? __('chat.you') : $m['name']]) }}">
                    <i data-lucide="reply" aria-hidden="true"></i>
                </button>
            </div>
        @endif
    </div>
@empty
    <div class="chat-empty">
        <span class="chat-empty-icon" aria-hidden="true"><i data-lucide="messages-square"></i></span>
        <strong>{{ $party === 'client' ? __('chat.empty_title') : __('chat.title_hq') }}</strong>
        <p>{{ $party === 'client' ? __('chat.empty_body') : __('chat.empty_hq') }}</p>
    </div>
@endforelse
