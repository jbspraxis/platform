@use('App\Livewire\Portal\Concerns\GatewayFormat', 'F')
@php
    $progress = round((float) ($tracker->progress_percentage ?? 0));
    $replyingTaskTitle = $replyingToTask
        ? optional($milestones->flatMap(fn ($m) => $m->tasks)->firstWhere('id', $replyingToTask))->title
        : null;
    $statusPill = ['todo' => 'pill-neutral', 'in_progress' => 'pill-warning', 'done' => 'pill-success'];
@endphp
{{-- The client's project tracker (mobile first): PIN screen, then milestones and tasks, and messages with the
     delivery team (works offline: messages wait in the browser). Behaviour lives in App\Livewire\Portal\ProjectTracker. --}}
<div x-data="executiveTracker()" x-init="initEngine()" class="app-wrapper" :class="{ 'is-offline': !isOnline }">

    {{-- Static styles: sent once with the page, not again on every round trip --}}
    @assets
    <style>
        .app-wrapper { min-height: 100vh; max-width: 600px; margin: 0 auto; position: relative; overflow-x: hidden; background: var(--color-mist); color: var(--color-slate); }
        @media (min-width: 600px) { .app-wrapper { border-left: 1px solid var(--color-line); border-right: 1px solid var(--color-line); } }
        .app-wrapper .gateway-header-inner { padding: 12px 16px; }

        /* Offline notice */
        .offline-banner { display: none; position: sticky; top: 0; z-index: 200; gap: 8px; align-items: flex-start; padding: 10px 16px; background: var(--color-warning-tint); color: var(--color-warning); font-size: 0.8125rem; line-height: 1.25rem; font-weight: 500; border-bottom: 1px solid var(--color-line); }
        .offline-banner svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px; }
        .is-offline .offline-banner { display: flex; }

        /* PIN screen */
        .auth-screen { min-height: calc(100vh - 61px); display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 32px 16px; text-align: center; }
        .auth-panel { width: 100%; max-width: 400px; background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 32px 24px; }
        .auth-kicker { font-size: 0.8125rem; font-weight: 600; color: var(--color-deep-teal); margin-bottom: 4px; }
        .auth-panel .page-title { text-wrap: balance; }
        .auth-panel p { margin: 8px 0 24px; color: var(--color-slate); font-size: 0.9375rem; line-height: 1.5rem; }
        .pin-wrapper { position: relative; margin: 0 auto 16px; width: max-content; cursor: text; }
        .hidden-pin { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: text; z-index: 10; font-size: 16px; }
        .pin-dots-container { display: flex; gap: 16px; justify-content: center; pointer-events: none; padding: 8px; }
        .pin-dot { width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--color-line-strong); background: transparent; transition: background var(--motion-instant), border-color var(--motion-instant); }
        .pin-dot.filled { background: var(--color-ink); border-color: var(--color-ink); }
        .pin-dot.error { border-color: var(--color-danger); }
        .pin-dot.error.filled { background: var(--color-danger); }
        .pin-wrapper:focus-within .pin-dots-container { outline: 2px solid var(--color-focus); outline-offset: 2px; border-radius: var(--radius-control); }
        .auth-error-text { min-height: 20px; color: var(--color-danger); font-size: 0.8125rem; line-height: 1.25rem; }

        /* Header */
        .tracker-header { background: var(--color-paper); border-bottom: 1px solid var(--color-line); padding: 20px 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .client-name { display: flex; align-items: center; gap: 6px; font-size: 0.8125rem; font-weight: 500; color: var(--color-muted); margin-bottom: 4px; }
        .client-name svg { width: 14px; height: 14px; }
        .project-title { margin: 0 0 12px; font-size: 1.25rem; line-height: 1.75rem; font-weight: 600; color: var(--color-ink); }
        .meta-tags-container { display: flex; gap: 8px 12px; flex-wrap: wrap; align-items: center; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .progress-ring { position: relative; width: 68px; height: 68px; flex-shrink: 0; }
        .progress-ring-svg { transform: rotate(-90deg); }
        .progress-circle-bg { fill: transparent; stroke: var(--color-line); stroke-width: 5; }
        .progress-circle-value { fill: transparent; stroke: var(--color-teal); stroke-width: 5; stroke-linecap: round; }
        .progress-text { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; color: var(--color-ink); font-variant-numeric: tabular-nums; }

        /* Milestones and tasks */
        .timeline-container { padding: 24px 16px 120px; }
        .milestone-block { position: relative; padding-left: 28px; margin-bottom: 40px; }
        .milestone-block::before { content: ""; position: absolute; left: 7px; top: 22px; bottom: -40px; width: 2px; background: var(--color-line); }
        .milestone-block:last-child::before { display: none; }
        .milestone-dot { position: absolute; left: 0; top: 4px; width: 16px; height: 16px; border-radius: 50%; background: var(--color-paper); border: 2px solid var(--color-line-strong); }
        .milestone-dot.completed { background: var(--color-teal); border-color: var(--color-teal); }
        .milestone-header { margin-bottom: 12px; }
        .milestone-title { margin: 0 0 2px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
        .sprint-dates { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .milestone-header .pill { margin-top: 6px; }
        .task-card { background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 14px 16px; margin-bottom: 8px; display: flex; gap: 12px; align-items: center; }
        .task-content { flex-grow: 1; min-width: 0; }
        .task-name { font-size: 0.9375rem; font-weight: 500; line-height: 1.375rem; color: var(--color-ink); margin-bottom: 8px; }
        .task-footer { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .task-assignee { display: flex; align-items: center; gap: 6px; }
        .assignee-avatar { width: 20px; height: 20px; border-radius: 50%; background: var(--color-teal-tint); color: var(--color-petrol); display: flex; align-items: center; justify-content: center; font-size: 0.6875rem; font-weight: 600; }
        .task-deadline { font-variant-numeric: tabular-nums; }
        .task-deadline.is-late { color: var(--color-danger); font-weight: 500; }
        .btn-reply { width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--color-line); background: var(--color-paper); color: var(--color-deep-teal); flex-shrink: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .btn-reply:hover { background: var(--color-teal-tint); }
        .btn-reply svg { width: 18px; height: 18px; }

        /* Messages button */
        .fab-chat { position: fixed; bottom: 24px; right: max(16px, calc(50vw - 300px + 16px)); width: 56px; height: 56px; border-radius: 50%; border: none; background: var(--color-deep-teal); color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-float); z-index: 90; cursor: pointer; }
        .fab-chat:hover { background: var(--color-petrol); }
        .fab-chat svg { width: 24px; height: 24px; }
        .unread-badge { position: absolute; top: -2px; right: -2px; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 11px; background: var(--color-danger); color: #fff; font-size: 0.6875rem; font-weight: 600; display: flex; align-items: center; justify-content: center; border: 2px solid var(--color-paper); font-variant-numeric: tabular-nums; }

        /* Messages sheet */
        .chat-sheet { position: fixed; inset: 0; z-index: 100; pointer-events: none; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; }
        .chat-sheet.open { pointer-events: auto; }
        .chat-backdrop { position: absolute; inset: 0; background: rgba(12, 20, 43, 0.5); opacity: 0; transition: opacity 200ms ease; }
        .chat-sheet.open .chat-backdrop { opacity: 1; }
        .chat-panel { position: relative; width: 100%; max-width: 600px; height: 85vh; display: flex; flex-direction: column; background: var(--color-paper); border-radius: var(--radius-dialog) var(--radius-dialog) 0 0; box-shadow: var(--shadow-float); transform: translateY(100%); transition: transform 250ms ease; }
        .chat-sheet.open .chat-panel { transform: translateY(0); }
        @media (prefers-reduced-motion: reduce) { .chat-backdrop, .chat-panel { transition: none; } }
        .chat-sheet-header { display: flex; align-items: center; gap: 12px; padding: 16px; border-bottom: 1px solid var(--color-line); }
        .chat-sheet-header .title { font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
        .chat-sheet-header .sub { font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
        .btn-close-sheet { width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--color-line); background: var(--color-paper); color: var(--color-ink); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
        .btn-close-sheet:hover { background: var(--color-mist); }
        .chat-scroller { flex-grow: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; background: var(--color-mist); }
        .bubble { max-width: 85%; padding: 10px 14px; border-radius: 12px; font-size: 0.9375rem; line-height: 1.375rem; color: var(--color-ink); }
        .bubble-client { align-self: flex-end; background: var(--color-teal-tint); border-bottom-right-radius: 4px; }
        .bubble-staff { align-self: flex-start; background: var(--color-paper); border: 1px solid var(--color-line); border-bottom-left-radius: 4px; }
        .bubble-sender { font-size: 0.8125rem; font-weight: 600; margin-bottom: 4px; color: var(--color-deep-teal); }
        .bubble-context { display: flex; align-items: center; gap: 6px; padding: 6px 10px; margin-bottom: 8px; border-left: 2px solid var(--color-deep-teal); border-radius: var(--radius-control); background: rgba(255, 255, 255, 0.6); font-size: 0.8125rem; font-weight: 500; color: var(--color-ink); }
        .bubble-staff .bubble-context { background: var(--color-mist); }
        .bubble-context svg { width: 14px; height: 14px; flex-shrink: 0; }
        .bubble-meta { margin-top: 6px; text-align: right; font-size: 0.75rem; color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .offline-tag { color: var(--color-warning); }
        .chat-audio { width: 100%; max-width: 240px; height: 36px; }
        .chat-image { max-width: 100%; border-radius: var(--radius-control); border: 1px solid var(--color-line); }
        .chat-doc { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--radius-control); background: var(--color-paper); border: 1px solid var(--color-line); color: var(--color-deep-teal); font-size: 0.875rem; font-weight: 500; text-decoration: none; }
        .chat-doc svg { width: 20px; height: 20px; }
        .chat-empty { margin: 32px auto 0; max-width: 280px; text-align: center; color: var(--color-muted); font-size: 0.875rem; line-height: 1.375rem; }

        /* Message box */
        .chat-input-wrapper { display: flex; flex-direction: column; gap: 10px; padding: 12px 16px 20px; border-top: 1px solid var(--color-line); background: var(--color-paper); }
        .reply-banner, .media-banner { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 8px 12px; border-radius: var(--radius-control); font-size: 0.875rem; }
        .reply-banner { background: var(--color-teal-tint); border-left: 2px solid var(--color-deep-teal); }
        .reply-banner .label-small { font-size: 0.75rem; color: var(--color-muted); }
        .reply-banner .task { font-weight: 600; color: var(--color-ink); }
        .media-banner { background: var(--color-mist); color: var(--color-ink); font-weight: 500; }
        .media-banner span { display: flex; align-items: center; gap: 6px; }
        .media-banner svg, .reply-banner svg { width: 16px; height: 16px; }
        .icon-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 0; border-radius: var(--radius-control); background: transparent; color: var(--color-muted); cursor: pointer; flex-shrink: 0; }
        .icon-btn:hover { background: rgba(12, 20, 43, 0.06); color: var(--color-ink); }
        .input-row { display: flex; align-items: flex-end; gap: 8px; }
        .btn-round { width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--color-line-strong); background: var(--color-paper); color: var(--color-ink); }
        .btn-round svg { width: 20px; height: 20px; }
        .btn-round:hover { background: var(--color-mist); }
        .btn-send { border-color: transparent; background: var(--color-deep-teal); color: #fff; }
        .btn-send:hover { background: var(--color-petrol); }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-mic.recording { border-color: transparent; background: var(--color-danger); color: #fff; }
        .chat-textarea { flex-grow: 1; min-height: 44px; max-height: 120px; padding: 10px 14px; border: 1px solid var(--color-line-strong); border-radius: 22px; background: var(--color-paper); color: var(--color-ink); font-family: var(--font-sans); font-size: 1rem; line-height: 1.375rem; resize: none; }
        .chat-textarea::placeholder { color: var(--color-muted); }
        .chat-textarea:focus { outline: 2px solid var(--color-focus); outline-offset: 1px; }
        .archived-lock { display: flex; align-items: flex-start; gap: 8px; padding: 16px; border-top: 1px solid var(--color-line); background: var(--color-mist); color: var(--color-slate); font-size: 0.875rem; line-height: 1.375rem; }
        .archived-lock svg { width: 16px; height: 16px; margin-top: 3px; flex-shrink: 0; }
        .spin { animation: tracker-spin 0.8s linear infinite; }
        @keyframes tracker-spin { to { transform: rotate(360deg); } }
        .chat-earlier { align-self: center; }
    </style>
    @endassets

    <div class="offline-banner" role="status">
        <i data-lucide="wifi-off"></i>
        <span>{{ __('gateway.tracker.offline') }}</span>
    </div>

    <x-portal.gateway-header :kicker="__('gateway.tracker.kicker')" :width="600" />

    @if(!$isAuthorized)
        <div class="auth-screen">
            <div class="auth-panel">
                <div class="auth-kicker">{{ __('gateway.tracker.kicker') }}</div>
                <h1 class="page-title">{{ $tracker->project_name }}</h1>
                <p>{{ __('gateway.tracker.pin_intro') }}</p>

                <div class="pin-wrapper" @click="focusPin()">
                    <div class="pin-dots-container">
                        <template x-for="i in 4" :key="i">
                            <div class="pin-dot" :class="{ 'filled': pinCode.length >= i, 'error': @json($pinError) }"></div>
                        </template>
                    </div>

                    <input type="password" inputmode="numeric" maxlength="4" autocomplete="one-time-code"
                           class="hidden-pin" x-ref="hiddenPin" aria-label="{{ __('gateway.tracker.pin_label') }}"
                           @if($pinError) aria-invalid="true" aria-describedby="pin-error" @endif
                           x-model="pinCode" @input="handlePinInput" autofocus>
                </div>

                <div class="auth-error-text" id="pin-error" role="alert">
                    @if($pinError)
                        {{ __('gateway.tracker.pin_error') }}
                    @endif
                </div>
            </div>
        </div>

    @else
        <div class="tracker-header">
            <div class="min-w-0">
                <div class="client-name">
                    <i data-lucide="building-2"></i>
                    {{ $tracker->client_company ?? $tracker->client_name }}
                </div>

                <h1 class="project-title">{{ $tracker->project_name }}</h1>

                <div class="meta-tags-container">
                    <span class="pill {{ $isArchived ? 'pill-neutral' : 'pill-success' }}">
                        {{ $isArchived ? __('gateway.tracker.status_archived') : __('gateway.tracker.status_active') }}
                    </span>
                    @if($projectDeadline)
                        <span>{{ __('gateway.tracker.planned_end', ['date' => F::date($projectDeadline, 'date_medium')]) }}</span>
                    @endif
                </div>
            </div>

            <div class="progress-ring" role="img" aria-label="{{ __('gateway.tracker.progress', ['percent' => F::percent($progress)]) }}">
                <svg class="progress-ring-svg" width="68" height="68" aria-hidden="true">
                    <circle class="progress-circle-bg" cx="34" cy="34" r="30"></circle>
                    <circle class="progress-circle-value" cx="34" cy="34" r="30"
                            stroke-dasharray="188.49"
                            stroke-dashoffset="{{ 188.49 - (188.49 * $tracker->progress_percentage) / 100 }}"></circle>
                </svg>
                <div class="progress-text" aria-hidden="true">{{ F::percent($progress) }}</div>
            </div>
        </div>

        <div class="timeline-container">
            @forelse($milestones as $milestone)
                @php
                    $allDone = $milestone->tasks->count() > 0 && $milestone->tasks->every(fn($t) => $t->status === 'done');
                @endphp
                <div class="milestone-block">
                    <div class="milestone-dot {{ $allDone ? 'completed' : '' }}"></div>

                    <div class="milestone-header">
                        <h2 class="milestone-title">{{ $milestone->title }}</h2>
                        @if($milestone->start_date && $milestone->end_date)
                            <div class="sprint-dates">
                                {{ F::date($milestone->start_date, 'date_short') }} – {{ F::date($milestone->end_date, 'date_medium') }}
                            </div>
                        @endif
                        @if($milestone->is_payment_trigger)
                            <span class="pill pill-info">{{ __('gateway.tracker.payment_milestone', ['percent' => F::percent($milestone->payment_percentage)]) }}</span>
                        @endif
                    </div>

                    @foreach($milestone->tasks as $task)
                        @php
                            $isLate = $task->due_date && $task->status !== 'done' && \Carbon\Carbon::parse($task->due_date)->endOfDay()->isPast();
                            $statusKey = 'gateway.tracker.task_status.' . $task->status;
                            $statusLabel = \Illuminate\Support\Facades\Lang::has($statusKey) ? __($statusKey) : $task->status;
                        @endphp
                        <div class="task-card">
                            <div class="task-content">
                                <div class="task-name">{{ $task->title }}</div>

                                <div class="task-footer">
                                    <span class="pill {{ $statusPill[$task->status] ?? 'pill-neutral' }}">{{ $statusLabel }}</span>
                                    <span class="task-assignee">
                                        <span class="assignee-avatar" aria-hidden="true">{{ mb_substr($task->assignee->name ?? '–', 0, 1) }}</span>
                                        {{ $task->assignee->name ?? __('gateway.tracker.unassigned') }}
                                    </span>
                                    @if($task->due_date)
                                        <span class="task-deadline {{ $isLate ? 'is-late' : '' }}">
                                            {{ __('gateway.tracker.due', ['date' => F::date($task->due_date, 'date_short')]) }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if(!$isArchived)
                                <button type="button" class="btn-reply" wire:click="setReplyContext('{{ $task->id }}')" title="{{ __('gateway.tracker.discuss_task') }}" aria-label="{{ __('gateway.tracker.discuss_task') }}">
                                    <i data-lucide="message-square-reply"></i>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="card empty-state">
                    <strong>{{ __('gateway.tracker.empty_title') }}</strong>
                    {{ __('gateway.tracker.empty_body') }}
                </div>
            @endforelse
        </div>

        <button type="button" class="fab-chat" @click="openChat()" aria-label="{{ __('gateway.tracker.open_messages') }}">
            <i data-lucide="message-circle"></i>
            <span class="unread-badge" x-show="unreadCount > 0" x-cloak x-text="unreadCount"></span>
        </button>

        {{-- inert while closed: out of the Tab order and hidden from screen readers --}}
        <div class="chat-sheet" :class="{ 'open': chatOpen }" :aria-hidden="!chatOpen" :inert="!chatOpen">
            <div class="chat-backdrop" @click="chatOpen = false; unreadCount = 0"></div>

            <div class="chat-panel" role="dialog" aria-modal="true" aria-labelledby="chat-title">
                <div class="chat-sheet-header">
                    <button type="button" class="btn-close-sheet" @click="chatOpen = false; unreadCount = 0" aria-label="{{ __('gateway.tracker.close_messages') }}">
                        <i data-lucide="chevron-down"></i>
                    </button>
                    <div>
                        <div class="title" id="chat-title">{{ __('gateway.tracker.messages_title') }}</div>
                        <div class="sub">{{ __('gateway.tracker.messages_subtitle') }}</div>
                    </div>
                </div>

                <div class="chat-scroller" x-ref="chatBody">
                    @if($hasEarlierMessages)
                        <button type="button" class="btn btn-ghost btn-sm chat-earlier" wire:click="showEarlierMessages" wire:loading.attr="disabled" wire:target="showEarlierMessages">
                            {{ __('gateway.tracker.show_earlier') }}
                        </button>
                    @endif

                    @forelse($messages as $msg)
                        <div class="bubble {{ $msg->sender_type === 'client' ? 'bubble-client' : 'bubble-staff' }}">

                            @if($msg->sender_type === 'staff')
                                <div class="bubble-sender">{{ $msg->sender->name ?? __('gateway.tracker.team_fallback') }}</div>
                            @endif

                            @if($msg->task_id)
                                <div class="bubble-context">
                                    <i data-lucide="link"></i> {{ $msg->task->title ?? __('gateway.tracker.task_fallback') }}
                                </div>
                            @endif

                            @if($msg->media_type === 'voice')
                                <audio controls class="chat-audio">
                                    <source src="{{ asset('storage/' . $msg->media_path) }}" type="audio/mpeg">
                                </audio>
                            @elseif($msg->media_type === 'image')
                                <a href="{{ asset('storage/' . $msg->media_path) }}" target="_blank" rel="noopener">
                                    <img src="{{ asset('storage/' . $msg->media_path) }}" class="chat-image" alt="{{ __('gateway.tracker.open_attachment') }}">
                                </a>
                            @elseif($msg->media_type === 'document')
                                <a href="{{ asset('storage/' . $msg->media_path) }}" target="_blank" rel="noopener" class="chat-doc">
                                    <i data-lucide="file-text"></i>
                                    <span>{{ __('gateway.tracker.open_attachment') }}</span>
                                </a>
                            @else
                                <div style="white-space: pre-wrap; word-break: break-word;">{{ $msg->body }}</div>
                            @endif

                            <div class="bubble-meta">{{ F::time($msg->created_at) }}</div>
                        </div>
                    @empty
                        <p class="chat-empty">{{ __('gateway.tracker.messages_empty') }}</p>
                    @endforelse

                    <template x-for="(offMsg, index) in offlineMessages" :key="index">
                        <div class="bubble bubble-client">
                            <div class="bubble-context" x-show="offMsg.taskTitle"><span x-text="offMsg.taskTitle"></span></div>
                            <div style="white-space: pre-wrap; word-break: break-word;" x-text="offMsg.body"></div>
                            <div class="bubble-meta offline-tag">{{ __('gateway.tracker.queued') }}</div>
                        </div>
                    </template>
                </div>

                @if(!$isArchived)
                    <div class="chat-input-wrapper">

                        @if($replyingToTask)
                            <div class="reply-banner">
                                <div class="min-w-0">
                                    <div class="label-small">{{ __('gateway.tracker.replying_about') }}</div>
                                    <div class="task truncate">{{ $replyingTaskTitle ?? __('gateway.tracker.task_fallback') }}</div>
                                </div>
                                <button type="button" wire:click="clearReplyContext" class="icon-btn" aria-label="{{ __('gateway.tracker.cancel_reply') }}" title="{{ __('gateway.tracker.cancel_reply') }}"><i data-lucide="x"></i></button>
                            </div>
                        @endif

                        @if($mediaUpload)
                            <div class="media-banner">
                                <span><i data-lucide="paperclip"></i> {{ __('gateway.tracker.file_ready') }}</span>
                                <button type="button" wire:click="$set('mediaUpload', null)" class="icon-btn" aria-label="{{ __('gateway.tracker.remove_file') }}" title="{{ __('gateway.tracker.remove_file') }}"><i data-lucide="x"></i></button>
                            </div>
                        @endif

                        @error('mediaUpload') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                        @error('chatMessage') <span class="field-error" role="alert">{{ $message }}</span> @enderror

                        <div class="input-row">
                            <input type="file" id="clientMedia" wire:model="mediaUpload" accept="image/*, application/pdf" style="display: none;">
                            <button type="button" class="btn-round" onclick="document.getElementById('clientMedia').click()" aria-label="{{ __('gateway.tracker.attach') }}" title="{{ __('gateway.tracker.attach') }}">
                                <i data-lucide="paperclip"></i>
                            </button>

                            <label for="tracker-message" class="sr-only">{{ __('gateway.tracker.message_label') }}</label>
                            <textarea id="tracker-message" class="chat-textarea" x-model="localMessage" placeholder="{{ __('gateway.tracker.message_placeholder') }}" rows="1"
                                      @input="resizeTextarea" x-ref="chatInput"
                                      @keydown.enter.prevent="sendProtocol"></textarea>

                            <button type="button" class="btn-round btn-mic" :class="{ 'recording': isRecording }"
                                    aria-label="{{ __('gateway.tracker.record') }}" title="{{ __('gateway.tracker.record') }}" :aria-pressed="isRecording"
                                    x-show="localMessage.length === 0 && !@this.mediaUpload"
                                    @touchstart.prevent="startRecording"
                                    @touchend.prevent="stopRecording"
                                    @mousedown.prevent="startRecording"
                                    @mouseup.prevent="stopRecording">
                                <i data-lucide="mic"></i>
                            </button>

                            <button type="button" class="btn-round btn-send" wire:loading.attr="disabled" wire:target="transmitMessage, mediaUpload"
                                    aria-label="{{ __('gateway.tracker.send') }}" title="{{ __('gateway.tracker.send') }}"
                                    x-show="localMessage.length > 0 || @this.mediaUpload"
                                    @click="sendProtocol">
                                <span wire:loading.remove wire:target="transmitMessage, mediaUpload" class="flex"><i data-lucide="arrow-up"></i></span>
                                <span wire:loading wire:target="transmitMessage, mediaUpload" class="flex spin"><i data-lucide="loader-circle"></i></span>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="archived-lock">
                        <i data-lucide="lock"></i>
                        <span>{{ __('gateway.tracker.archived') }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('alpine:init', () => {
            const trackerText = {
                voiceUnsupported: @js(__('gateway.tracker.voice_unsupported')),
                micBlocked: @js(__('gateway.tracker.mic_blocked')),
            };

            Alpine.data('executiveTracker', () => ({
                chatOpen: false,
                localMessage: '',
                unreadCount: 0,

                // PIN State
                pinCode: '',

                // Offline: messages wait in this browser until the connection is back
                isOnline: navigator.onLine,
                offlineMessages: JSON.parse(localStorage.getItem('jbs_tracker_offline_{{ $tracker->id }}') || '[]'),

                // Voice messages
                isRecording: false,
                mediaRecorder: null,
                audioChunks: [],

                initEngine() {
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        this.syncOfflineData();
                    });
                    window.addEventListener('offline', () => { this.isOnline = false; });

                    Livewire.on('message-transmitted', () => {
                        this.localMessage = '';
                        this.resizeTextarea();
                        this.scrollToBottom();
                    });

                    Livewire.on('focus-chat-input', () => {
                        this.openChat();
                    });

                    Livewire.on('system-alert', (data) => {
                        alert(data[0].message);
                    });
                },

                handlePinInput() {
                    this.pinCode = this.pinCode.replace(/[^0-9]/g, '').substring(0, 4);

                    if (this.pinCode.length === 4) {
                        // Cleared once the server has answered, so a wrong PIN can be typed again
                        @this.verifyPin(this.pinCode).then(() => { if(@this.pinError) this.pinCode = ''; });
                    }
                },

                focusPin() {
                    if (this.$refs.hiddenPin) {
                        this.$refs.hiddenPin.focus();
                    }
                },

                openChat() {
                    this.chatOpen = true;
                    this.unreadCount = 0;
                    this.scrollToBottom();
                    setTimeout(() => {
                        if(this.$refs.chatInput) this.$refs.chatInput.focus();
                    }, 300);
                },

                scrollToBottom() {
                    setTimeout(() => {
                        if(this.$refs.chatBody) {
                            this.$refs.chatBody.scrollTop = this.$refs.chatBody.scrollHeight;
                        }
                    }, 150);
                },

                resizeTextarea() {
                    const el = this.$refs.chatInput;
                    if(el) {
                        el.style.height = 'auto';
                        el.style.height = (el.scrollHeight) + 'px';
                    }
                },

                sendProtocol() {
                    if (!this.localMessage.trim() && !@this.mediaUpload) return;

                    if (this.isOnline) {
                        @this.set('chatMessage', this.localMessage);
                        @this.transmitMessage();
                        this.localMessage = '';
                        this.resizeTextarea();
                    } else {
                        const payload = {
                            body: this.localMessage,
                            taskId: @this.replyingToTask,
                            taskTitle: @this.replyingToTaskTitle ? @this.replyingToTaskTitle : null,
                            timestamp: new Date().toISOString()
                        };
                        this.offlineMessages.push(payload);
                        localStorage.setItem('jbs_tracker_offline_{{ $tracker->id }}', JSON.stringify(this.offlineMessages));

                        this.localMessage = '';
                        this.resizeTextarea();
                        this.scrollToBottom();
                        @this.clearReplyContext();
                    }
                },

                syncOfflineData() {
                    if (this.offlineMessages.length > 0) {
                        @this.syncOfflinePayload(this.offlineMessages).then(() => {
                            this.offlineMessages = [];
                            localStorage.removeItem('jbs_tracker_offline_{{ $tracker->id }}');
                            this.scrollToBottom();
                        });
                    }
                },

                async startRecording() {
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        alert(trackerText.voiceUnsupported);
                        return;
                    }
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.mediaRecorder = new MediaRecorder(stream);
                        this.audioChunks = [];

                        this.mediaRecorder.ondataavailable = e => {
                            if (e.data.size > 0) this.audioChunks.push(e.data);
                        };

                        this.mediaRecorder.onstop = () => {
                            const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                            const audioFile = new File([audioBlob], 'voice-message.webm', { type: 'audio/webm' });

                            @this.upload('mediaUpload', audioFile, () => {
                                @this.transmitMessage();
                            });
                        };

                        this.mediaRecorder.start();
                        this.isRecording = true;
                    } catch (err) {
                        alert(trackerText.micBlocked);
                    }
                },

                stopRecording() {
                    if (this.mediaRecorder && this.isRecording) {
                        this.mediaRecorder.stop();
                        this.isRecording = false;
                        this.mediaRecorder.stream.getTracks().forEach(track => track.stop());
                    }
                }
            }));
        });
    </script>
</div>
