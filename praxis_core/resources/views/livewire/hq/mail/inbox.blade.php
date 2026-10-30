@php
    use App\Livewire\Hq\Mail\Inbox;
    $hasReader = $openThread || $liveMessage;
    $role = $currentFolder?->role;
    $shared = $account?->isShared();
    $latest = collect($messages)->last();
    $collapsed = collect($messages)->where('open', false)->count();
    $levelLabel = ['owner' => 'Your mailbox', 'manage' => 'Manager', 'send' => 'Read and send', 'read' => 'Read only'][$level] ?? null;
@endphp
<div class="mr {{ $hasReader ? 'has-reader' : '' }} {{ $flash ? 'has-flash' : '' }}" wire:poll.60s.visible="tick"
     x-data="mailPage" @keydown.window="key($event)"
     data-account="{{ $account?->id }}" data-thread="{{ $openThread?->id }}" data-latest="{{ $latest['id'] ?? '' }}"
     data-draft="{{ ($latest['is_draft'] ?? false) ? '1' : '' }}" data-folder="{{ $role }}"
     data-organise="{{ $canOrganise ? '1' : '' }}" data-send="{{ $canSend ? '1' : '' }}" data-flagged="{{ $openThread && collect($messages)->contains(fn ($m) => $m['model']->is_flagged) ? '1' : '' }}">
    <style>
        /* HQ → Mail (PR 2). Brand tokens from app.css only; shared classes (btn, pill, tag, input, alert, modal) where they fit. */
        .mr { --mr-pane-h: calc(100dvh - 204px); }
        .mr.has-flash { --mr-pane-h: calc(100dvh - 262px); }
        .mr .lucide.mr-i { width: 18px; height: 18px; flex-shrink: 0; }
        .mr-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px 16px; margin-bottom: 16px; }
        .mr-head .page-title { display: flex; align-items: center; gap: 10px; }
        .mr-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .mr-icon-btn { display: inline-grid; place-items: center; width: 36px; height: 36px; padding: 0; border-radius: var(--radius-control); border: 1px solid transparent; background: transparent; color: var(--color-slate); cursor: pointer; text-decoration: none; }
        .mr-icon-btn:hover { background: var(--color-mist); color: var(--color-ink); }
        .mr-icon-btn.is-on { color: var(--color-warning); }
        .mr-icon-btn[disabled] { opacity: 0.45; cursor: default; }
        .mr-flash { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .mr-flash .alert { flex: 1 1 auto; align-items: center; }
        .mr-flash-actions { margin-left: auto; display: flex; gap: 4px; }

        .mr-panes { display: grid; grid-template-columns: 208px minmax(300px, 360px) minmax(0, 1fr); height: var(--mr-pane-h); min-height: 480px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-paper); overflow: hidden; }
        .mr-nav, .mr-list, .mr-reader { min-width: 0; min-height: 0; overflow-y: auto; }
        .mr-nav { border-right: 1px solid var(--color-line); background: var(--color-mist); padding: 12px 8px; }
        .mr-list { border-right: 1px solid var(--color-line); display: flex; flex-direction: column; }
        .mr-nav h2 { margin: 12px 8px 6px; font-size: 0.75rem; line-height: 1rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: var(--color-muted); }
        .mr-nav h2:first-child { margin-top: 0; }
        .mr-nav ul { list-style: none; margin: 0; padding: 0; }
        .mr-nav-btn { display: flex; align-items: center; gap: 8px; width: 100%; padding: 7px 8px; border: 0; border-radius: var(--radius-control); background: none; color: var(--color-slate); font: inherit; font-size: 0.875rem; line-height: 1.25rem; text-align: left; cursor: pointer; }
        .mr-nav-btn:hover { background: var(--color-paper); color: var(--color-ink); }
        .mr-nav-btn.is-active { background: var(--color-teal-tint); color: var(--color-petrol); font-weight: 600; }
        .mr-nav-btn span.mr-nav-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mr-nav-count { font-size: 0.75rem; font-weight: 600; color: var(--color-ink); font-variant-numeric: tabular-nums; }
        .mr-nav-btn.is-active .mr-nav-count { color: var(--color-petrol); }
        .mr-nav-sub { padding-left: 20px; }
        .mr-box-email { display: block; font-size: 0.75rem; color: var(--color-muted); font-weight: 400; overflow: hidden; text-overflow: ellipsis; }

        .mr-list-head { position: sticky; top: 0; z-index: 2; background: var(--color-paper); border-bottom: 1px solid var(--color-line); padding: 12px; display: flex; flex-direction: column; gap: 10px; }
        .mr-pickers { display: none; gap: 8px; }
        .mr-pickers .select { min-width: 0; flex: 1; }
        .mr-search { position: relative; }
        .mr-search .lucide.mr-i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--color-muted); pointer-events: none; }
        .mr-search .input { padding-left: 36px; padding-right: 36px; }
        .mr-search .mr-icon-btn { position: absolute; right: 2px; top: 50%; transform: translateY(-50%); width: 32px; height: 32px; }
        .mr-filters { display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; }
        .mr-filter { padding: 4px 10px; border-radius: 999px; border: 1px solid var(--color-line); background: var(--color-paper); color: var(--color-slate); font: inherit; font-size: 0.8125rem; line-height: 1.25rem; white-space: nowrap; cursor: pointer; }
        .mr-filter:hover { border-color: var(--color-line-strong); }
        .mr-filter.is-active { background: var(--color-ink); border-color: var(--color-ink); color: var(--color-paper); }
        .mr-list-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.8125rem; color: var(--color-muted); }
        .mr-pager { display: flex; align-items: center; gap: 2px; }
        .mr-pager .mr-icon-btn { width: 30px; height: 30px; }

        .mr-rows { list-style: none; margin: 0; padding: 0; }
        .mr-row { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 8px; width: 100%; padding: 10px 12px 10px 24px; border: 0; border-bottom: 1px solid var(--color-line); background: var(--color-paper); color: var(--color-slate); font: inherit; text-align: left; cursor: pointer; }
        .mr-row:hover { background: var(--color-mist); }
        .mr-row.is-active { background: var(--color-teal-tint); box-shadow: inset 3px 0 0 var(--color-teal); }
        .mr-row-from, .mr-row-subject, .mr-row-snippet { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mr-row-from { font-size: 0.875rem; line-height: 1.25rem; color: var(--color-ink); }
        .mr-row-date { font-size: 0.75rem; line-height: 1.25rem; color: var(--color-muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .mr-row-subject { grid-column: 1 / -1; font-size: 0.875rem; line-height: 1.25rem; color: var(--color-ink); }
        .mr-row-snippet { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .mr-row-badges { display: flex; align-items: center; justify-content: flex-end; gap: 6px; color: var(--color-muted); }
        .mr-row-badges .lucide.mr-i { width: 15px; height: 15px; }
        .mr-row-badges .is-flag { color: var(--color-warning); }
        .mr-row .tag, .mr-row .pill { font-size: 0.75rem; line-height: 1rem; padding-top: 1px; padding-bottom: 1px; }
        .mr-row.is-unread .mr-row-from, .mr-row.is-unread .mr-row-subject { font-weight: 700; }
        .mr-row.is-unread::before { content: ""; position: absolute; left: 9px; top: 16px; width: 8px; height: 8px; border-radius: 50%; background: var(--color-teal); }
        .mr-count { color: var(--color-muted); font-weight: 400; margin-left: 4px; font-size: 0.8125rem; }
        .mr-list-foot { padding: 16px 12px; display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }
        .mr-list-foot .help { margin: 0; }
        .mr-live-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 12px; background: var(--color-mist); border-bottom: 1px solid var(--color-line); border-top: 1px solid var(--color-line); font-size: 0.8125rem; font-weight: 600; color: var(--color-ink); }
        .mr-live-head .lucide.mr-i { width: 16px; height: 16px; }
        .mr-empty { padding: 32px 16px; text-align: center; color: var(--color-muted); font-size: 0.875rem; }
        .mr-empty strong { display: block; color: var(--color-ink); margin-bottom: 4px; }

        .mr-reader { display: flex; flex-direction: column; background: var(--color-paper); container-type: inline-size; }
        .mr-toolbar { position: sticky; top: 0; z-index: 3; display: flex; align-items: center; gap: 2px; flex-wrap: wrap; padding: 6px 12px; border-bottom: 1px solid var(--color-line); background: var(--color-paper); }
        .mr-toolbar .mr-sep { width: 1px; height: 20px; background: var(--color-line); margin: 0 6px; }
        .mr-toolbar .mr-grow { flex: 1; }
        .mr-toolbar .select { width: auto; max-width: 150px; padding-top: 6px; padding-bottom: 6px; font-size: 0.8125rem; }
        .mr-back { display: none; }
        .mr-reader-body { padding: 20px 24px 32px; display: flex; flex-direction: column; gap: 12px; }
        .mr-subject { margin: 0; font-size: 1.25rem; line-height: 1.75rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .mr-subject-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 6px; }
        .mr-expand { background: none; border: 0; padding: 0; color: var(--color-fg-link); font: inherit; font-size: 0.8125rem; font-weight: 600; cursor: pointer; }

        .mr-msg { border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-paper); }
        .mr-msg-head { display: grid; grid-template-columns: 36px minmax(0, 1fr) auto; gap: 2px 12px; align-items: center; width: 100%; padding: 12px 16px; border: 0; background: none; font: inherit; text-align: left; color: inherit; cursor: pointer; border-radius: var(--radius-card); }
        .mr-msg-head:hover { background: var(--color-mist); }
        .mr-avatar { grid-row: span 2; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; background: var(--color-teal-tint); color: var(--color-petrol); font-size: 0.8125rem; font-weight: 600; }
        .mr-msg-from { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.875rem; color: var(--color-ink); }
        .mr-msg-from strong { font-weight: 600; }
        .mr-msg-from span { color: var(--color-muted); }
        .mr-msg-date { font-size: 0.75rem; color: var(--color-muted); white-space: nowrap; display: flex; align-items: center; gap: 6px; }
        .mr-msg-date .lucide.mr-i { width: 15px; height: 15px; color: var(--color-warning); }
        .mr-msg-sub { grid-column: 2 / -1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.8125rem; color: var(--color-muted); }
        .mr-msg.is-unread .mr-msg-from strong::after { content: ""; display: inline-block; width: 7px; height: 7px; margin-left: 6px; border-radius: 50%; background: var(--color-teal); vertical-align: middle; }
        .mr-msg-info { display: flex; flex-wrap: wrap; gap: 4px 16px; padding: 0 16px 0 64px; font-size: 0.8125rem; color: var(--color-muted); }
        .mr-msg-info b { font-weight: 500; color: var(--color-slate); }
        .mr-msg-body { padding: 12px 16px 16px 64px; }
        .mr-frame { display: block; width: 100%; height: 120px; border: 0; background: var(--color-paper); }
        .mr-text { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 0.875rem; line-height: 1.55; color: var(--color-ink); }
        .mr-text a, .mr-msg-body a { color: var(--color-fg-link); }
        .mr .hq-quote { margin-top: 12px; }
        .mr .hq-quote > summary { cursor: pointer; display: inline-block; padding: 2px 10px; border-radius: 999px; background: var(--color-mist); color: var(--color-muted); font-size: 0.75rem; list-style: none; }
        .mr .hq-quote > summary::-webkit-details-marker { display: none; }
        .mr .hq-quote[open] > summary { margin-bottom: 8px; }
        .mr .hq-quote .mr-text { color: var(--color-slate); border-left: 2px solid var(--color-line); padding-left: 12px; }
        .mr-images { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin-bottom: 12px; padding: 8px 12px; border-radius: var(--radius-control); background: var(--color-mist); font-size: 0.8125rem; color: var(--color-slate); }
        .mr-images .lucide.mr-i { width: 16px; height: 16px; }
        .mr-images-actions { display: flex; gap: 4px; margin-left: auto; }
        .mr-files { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--color-line); }
        .mr-file { display: flex; align-items: center; gap: 8px; max-width: 100%; padding: 6px 6px 6px 10px; border: 1px solid var(--color-line); border-radius: var(--radius-control); font-size: 0.8125rem; color: var(--color-ink); }
        .mr-file-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
        .mr-file-size { color: var(--color-muted); white-space: nowrap; }
        .mr-file .mr-icon-btn { width: 30px; height: 30px; }
        .mr-msg-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
        .mr-reader-empty { flex: 1; display: grid; place-items: center; padding: 32px; text-align: center; color: var(--color-muted); font-size: 0.875rem; }
        .mr-reader-empty .lucide.mr-i { width: 40px; height: 40px; color: var(--color-line-strong); margin: 0 auto 8px; display: block; }
        .mr-kbd { display: inline-block; min-width: 22px; padding: 1px 6px; border: 1px solid var(--color-line); border-bottom-width: 2px; border-radius: 4px; background: var(--color-paper); font-family: var(--font-sans); font-size: 0.75rem; line-height: 1.125rem; text-align: center; color: var(--color-ink); }
        .mr-keys { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 8px 16px; margin: 16px 0 0; font-size: 0.875rem; }
        .mr-keys dt { white-space: nowrap; }
        .mr-keys dd { margin: 0; color: var(--color-slate); }
        .mr .modal { width: calc(100vw - 32px); }

        /* A narrow reader: messages use its whole width */
        @container (max-width: 640px) {
            .mr-msg-info, .mr-msg-body { padding-left: 16px; }
            .mr-reader-body { padding-left: 16px; padding-right: 16px; }
        }
        @media (max-width: 1400px) {
            .mr-panes { grid-template-columns: minmax(280px, 340px) minmax(0, 1fr); }
            .mr-nav { display: none; }
            .mr-pickers { display: flex; }
        }
        @media (max-width: 820px) {
            .mr-panes { display: block; height: auto; min-height: 0; border: 0; border-radius: 0; background: none; overflow: visible; }
            .mr-list, .mr-reader { border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-paper); overflow: visible; }
            .mr-list { overflow: hidden; }
            .mr.has-reader .mr-list, .mr:not(.has-reader) .mr-reader { display: none; }
            .mr-back { display: inline-grid; }
            .mr-list-head { position: static; }
            .mr-toolbar { top: 0; border-radius: var(--radius-card) var(--radius-card) 0 0; }
            .mr-reader-body { padding: 16px 12px 24px; }
            .mr-msg-head { padding: 12px; grid-template-columns: 32px minmax(0, 1fr) auto; }
            .mr-avatar { width: 32px; height: 32px; }
            .mr-msg-info, .mr-msg-body { padding-left: 12px; padding-right: 12px; }
            .mr-head-actions .mr-hide-phone { display: none; }
            .mr-toolbar .select { max-width: 110px; }
            .mr-file-name { max-width: 150px; }
        }
    </style>

    <div class="mr-head">
        <div style="min-width: 0;">
            <h1 class="page-title">{{ $account?->display_name ?? 'Mail' }}</h1>
            <p class="page-subtitle">
                @if ($account)
                    {{ $account->email }}@if ($levelLabel) · {{ $levelLabel }}@endif
                    @if ($account->status === 'needs_reconnect') · <span style="color: var(--color-danger);">needs its password again</span>@endif
                @else
                    Read and send your mail in HQ.
                @endif
            </p>
        </div>
        <div class="mr-head-actions">
            {{-- PR 4: Ask about your mail --}}
            <livewire:hq.mail.ask-mail :account-id="$account?->id" :key="'ask-'.($account?->id ?? 'none')" />
            @if ($account)
                <button type="button" class="mr-icon-btn mr-hide-phone" @click="help = true" title="Keyboard shortcuts (?)" aria-label="Keyboard shortcuts">@include('livewire.hq.mail.read.icon', ['name' => 'keyboard'])</button>
                <button type="button" class="mr-icon-btn" wire:click="refreshMailbox" wire:loading.attr="disabled" wire:target="refreshMailbox" title="Check for new mail" aria-label="Check for new mail">
                    <span wire:loading.remove wire:target="refreshMailbox">@include('livewire.hq.mail.read.icon', ['name' => 'refresh'])</span>
                    <span wire:loading wire:target="refreshMailbox"><i data-lucide="loader-circle" class="animate-spin"></i></span>
                </button>
                <a href="{{ route('hq.mail.mailboxes', ['box' => $account->id]) }}" class="mr-icon-btn" wire:navigate title="Mailboxes" aria-label="Mailboxes">@include('livewire.hq.mail.read.icon', ['name' => 'settings'])</a>
                @if ($canSend)
                    <button type="button" class="btn btn-primary" @click="compose('new')">@include('livewire.hq.mail.read.icon', ['name' => 'pen']) New mail</button>
                @endif
            @endif
        </div>
    </div>

    @if ($flash)
        <div class="mr-flash" wire:key="flash-{{ md5($flash) }}">
            <div class="alert {{ $flashKind === 'error' ? 'alert-danger' : 'alert-success' }}" role="{{ $flashKind === 'error' ? 'alert' : 'status' }}">
                @include('livewire.hq.mail.read.icon', ['name' => $flashKind === 'error' ? 'circle-alert' : 'check'])
                <span>{{ $flash }}</span>
                <span class="mr-flash-actions">
                    @if ($undo)
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="undoMove">Undo</button>
                    @endif
                    <button type="button" class="mr-icon-btn" style="width: 28px; height: 28px;" wire:click="dismissFlash" aria-label="Dismiss">@include('livewire.hq.mail.read.icon', ['name' => 'x'])</button>
                </span>
            </div>
        </div>
    @endif

    @if (! $account)
        <div class="card">
            <div class="empty-state">
                <strong>No mailbox connected yet</strong>
                Connect your work mailbox to read your mail here.
                <div class="mt-4"><a href="{{ route('hq.mail.mailboxes') }}" class="btn btn-primary" wire:navigate>@include('livewire.hq.mail.read.icon', ['name' => 'plug']) Connect your mailbox</a></div>
            </div>
        </div>
    @else
        <div class="mr-panes">
            {{-- Mailboxes and folders (desktop); phones and tablets use the pickers above the list --}}
            <nav class="mr-nav" aria-label="Mailboxes and folders">
                @if ($accounts->count() > 1)
                    <h2>Mailboxes</h2>
                    <ul>
                        @foreach ($accounts as $a)
                            <li wire:key="box-{{ $a->id }}">
                                <button type="button" class="mr-nav-btn {{ $a->id === $account->id ? 'is-active' : '' }}" wire:click="selectBox('{{ $a->id }}')" @if ($a->id === $account->id) aria-current="true" @endif>
                                    @include('livewire.hq.mail.read.icon', ['name' => $a->isShared() ? 'users' : 'user'])
                                    <span class="mr-nav-name">{{ $a->isShared() ? $a->display_name : 'Your mailbox' }}<span class="mr-box-email">{{ $a->email }}</span></span>
                                    @if (($boxUnread[$a->id] ?? 0) > 0)<span class="mr-nav-count" aria-label="{{ $boxUnread[$a->id] }} unread">{{ $boxUnread[$a->id] }}</span>@endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <h2>Folders</h2>
                <ul>
                    @forelse ($folders as $f)
                        @php
                            $count = $f->role === 'drafts' ? $f->total_count : $f->unread_count;
                            $depth = max(0, substr_count(preg_replace('/^INBOX' . preg_quote((string) $f->delimiter, '/') . '/i', '', $f->path), (string) ($f->delimiter ?: '.')));
                            $icon = ['inbox' => 'inbox', 'sent' => 'send', 'drafts' => 'file-text', 'archive' => 'archive', 'spam' => 'shield-alert', 'trash' => 'trash'][$f->role] ?? 'folder';
                        @endphp
                        <li wire:key="folder-{{ $f->id }}" @if ($f->role === 'other' && $depth > 0) style="padding-left: {{ min($depth, 3) * 12 }}px" @endif>
                            <button type="button" class="mr-nav-btn {{ $currentFolder && $f->id === $currentFolder->id ? 'is-active' : '' }}"
                                    wire:click="selectFolder('{{ $f->role === 'other' ? $f->id : $f->role }}')" @if ($currentFolder && $f->id === $currentFolder->id) aria-current="true" @endif>
                                @include('livewire.hq.mail.read.icon', ['name' => $icon])
                                <span class="mr-nav-name">{{ $f->label() }}</span>
                                @if ($count > 0)<span class="mr-nav-count" aria-label="{{ $count }} {{ $f->role === 'drafts' ? 'drafts' : 'unread' }}">{{ $count }}</span>@endif
                            </button>
                        </li>
                    @empty
                        <li class="help" style="padding: 4px 8px;">HQ is reading this mailbox's folders.</li>
                    @endforelse
                </ul>
            </nav>

            @include('livewire.hq.mail.read.list')

            <section class="mr-reader" aria-label="Reader" @if ($hasReader) x-init="$nextTick(() => { if (window.innerWidth <= 820) $el.scrollIntoView({ block: 'start' }) })" @endif>
                @if ($liveMessage)
                    @include('livewire.hq.mail.read.live', ['item' => $liveMessage])
                @elseif ($openThread)
                    @include('livewire.hq.mail.read.toolbar')
                    <div class="mr-reader-body" wire:key="reader-{{ $openThread->id }}">
                        <div>
                            <h2 class="mr-subject">{{ $openThread->subject ?: '(no subject)' }}</h2>
                            <div class="mr-subject-meta">
                                @if ($openThread->message_count > 1)<span class="tag">{{ $openThread->message_count }} messages</span>@endif
                                @if ($shared && $openThread->status === 'done')<span class="pill pill-success">Done</span>@endif
                                @if ($collapsed > 0)
                                    <button type="button" class="mr-expand" wire:click="expandAll">Open all {{ count($messages) }}</button>
                                @endif
                            </div>
                        </div>

                        {{-- Mount points (docs/MAIL.md 6.2): PR 4's teamwork (shared mailboxes) and summary (not in Spam) --}}
                        @if ($shared)
                            <livewire:hq.mail.thread-team :thread-id="$openThread->id" :key="'team-'.$openThread->id" />
                        @endif
                        @if ($role !== 'spam')
                            <livewire:hq.mail.thread-summary :thread-id="$openThread->id" :key="'sum-'.$openThread->id" />
                        @endif

                        @foreach ($messages as $m)
                            @include('livewire.hq.mail.read.message', ['m' => $m])
                        @endforeach
                    </div>
                @else
                    <div class="mr-reader-empty">
                        <div>
                            @include('livewire.hq.mail.read.icon', ['name' => 'mail-open'])
                            Pick a conversation to read it.
                            <p class="help" style="margin-top: 4px;">Press <span class="mr-kbd">?</span> for keyboard shortcuts.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    @endif

    {{-- Keyboard shortcuts --}}
    <div class="modal-backdrop" x-show="help" x-cloak x-transition.opacity @click.self="help = false" @keydown.escape.window="help = false">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="mr-keys-title" style="max-width: 520px;">
            <h2 class="modal-title" id="mr-keys-title">Keyboard shortcuts</h2>
            <p class="help" style="margin: 0;">They work when you are not typing in a box.</p>
            <dl class="mr-keys">
                <dt><span class="mr-kbd">j</span> <span class="mr-kbd">k</span></dt><dd>Next and previous conversation</dd>
                <dt><span class="mr-kbd">u</span> <span class="mr-kbd">Esc</span></dt><dd>Back to the list</dd>
                <dt><span class="mr-kbd">/</span></dt><dd>Search</dd>
                <dt><span class="mr-kbd">c</span></dt><dd>New mail</dd>
                <dt><span class="mr-kbd">r</span> <span class="mr-kbd">a</span> <span class="mr-kbd">f</span></dt><dd>Reply, reply all, forward</dd>
                <dt><span class="mr-kbd">e</span></dt><dd>Archive</dd>
                <dt><span class="mr-kbd">#</span></dt><dd>Move to Trash (in Trash: delete for good)</dd>
                <dt><span class="mr-kbd">!</span></dt><dd>Move to Spam</dd>
                <dt><span class="mr-kbd">s</span></dt><dd>Flag or remove the flag</dd>
                <dt><span class="mr-kbd">Shift</span> <span class="mr-kbd">u</span></dt><dd>Mark as unread</dd>
                <dt><span class="mr-kbd">?</span></dt><dd>Show these shortcuts</dd>
            </dl>
            <div class="modal-actions"><button type="button" class="btn btn-secondary" @click="help = false">Close</button></div>
        </div>
    </div>

    {{-- Named confirmations for what can't be undone --}}
    @if ($confirm && $currentFolder)
        <div class="modal-backdrop" wire:key="confirm-{{ $confirm }}" @keydown.escape.window="$wire.askConfirm('')">
            <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="mr-confirm-title" style="max-width: 480px;">
                @if ($confirm === 'empty')
                    <h2 class="modal-title" id="mr-confirm-title">Empty {{ $currentFolder->label() }}?</h2>
                    <p class="muted" style="margin: 0;">Every message in {{ $currentFolder->label() }} on the mail server is deleted for good, including mail older than 12 months. This can't be undone.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="askConfirm('')">Keep them</button>
                        <button type="button" class="btn btn-danger" wire:click="emptyFolder" wire:loading.attr="disabled">Empty {{ $currentFolder->label() }}</button>
                    </div>
                @else
                    <h2 class="modal-title" id="mr-confirm-title">Delete this conversation for good?</h2>
                    <p class="muted" style="margin: 0;">"{{ \Illuminate\Support\Str::limit($openThread?->subject ?: '(no subject)', 80) }}" is deleted from {{ $currentFolder->label() }} on the mail server. This can't be undone.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="askConfirm('')">Keep it</button>
                        <button type="button" class="btn btn-danger" wire:click="act('delete')" wire:loading.attr="disabled">Delete for good</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Mount points (docs/MAIL.md 6.2): PR 3's composer, once on the page --}}
    <livewire:hq.mail.composer />
</div>

@script
<script>
    // Mail page behaviour: keyboard shortcuts, opening the composer (PR 3, event mail-compose), and the reader's frames.
    Alpine.data('mailPage', () => ({
        help: false,
        get data() { return this.$root.dataset; },
        compose(mode, messageId = null, to = null) {
            const detail = { mode, accountId: this.data.account };
            if (messageId) detail.messageId = Number(messageId);
            if (to) detail.to = to;
            Livewire.dispatch('mail-compose', detail);
        },
        rows() { return Array.from(this.$root.querySelectorAll('[data-thread-row]')); },
        step(by) {
            const rows = this.rows();
            if (!rows.length) return;
            const at = rows.findIndex(r => r.getAttribute('aria-current') === 'true');
            const next = rows[Math.min(rows.length - 1, Math.max(0, at === -1 ? 0 : at + by))];
            next.focus();
            next.click();
        },
        key(e) {
            const t = e.target;
            if (e.ctrlKey || e.metaKey || e.altKey || e.defaultPrevented) return;
            if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
            if (document.querySelector('[aria-modal="true"]:not([x-cloak])') && this.help === false && e.key !== 'Escape') {
                // a dialog of another part (the composer) is open: leave its keys alone
                const open = Array.from(document.querySelectorAll('[aria-modal="true"]')).some(d => d.offsetParent !== null);
                if (open) return;
            }
            const d = this.data, thread = d.thread !== '', organise = d.organise === '1', send = d.send === '1';
            const run = (fn) => { e.preventDefault(); fn(); };
            switch (e.key) {
                case 'j': return run(() => this.step(1));
                case 'k': return run(() => this.step(-1));
                case '/': return run(() => this.$root.querySelector('#mr-search')?.focus());
                case '?': return run(() => { this.help = true; });
                case 'c': return send && run(() => this.compose('new'));
                case 'u': case 'Escape': return thread && !this.help && run(() => this.$wire.closeThread());
                case 'U': return thread && run(() => this.$wire.act('unread'));
                case 'e': return thread && organise && d.folder !== 'archive' && run(() => this.$wire.act('archive'));
                case '!': return thread && organise && d.folder !== 'spam' && run(() => this.$wire.act('spam'));
                case '#': return thread && organise && run(() => ['trash', 'spam'].includes(d.folder) ? this.$wire.askConfirm('delete') : this.$wire.act('trash'));
                case 's': return thread && organise && run(() => this.$wire.act(d.flagged === '1' ? 'unflag' : 'flag'));
                case 'r': return thread && send && d.latest && run(() => this.compose(d.draft ? 'draft' : 'reply', d.latest));
                case 'a': return thread && send && d.latest && run(() => this.compose('reply_all', d.latest));
                case 'f': return thread && send && d.latest && run(() => this.compose('forward', d.latest));
            }
        },
    }));

    // A message's HTML in a sandboxed frame (no scripts): fit its height, follow quote folding, and open
    // mailto: links in the composer.
    Alpine.data('mailFrame', () => ({
        init() {
            const frame = this.$el;
            const fit = () => {
                try {
                    const doc = frame.contentDocument;
                    if (doc && doc.body) frame.style.height = Math.max(40, doc.documentElement.scrollHeight) + 'px';
                } catch (e) { /* not ready */ }
            };
            const wire = () => {
                const doc = frame.contentDocument;
                if (!doc || !doc.body || frame.dataset.wired === doc.URL + doc.body.childElementCount) return;
                frame.dataset.wired = doc.URL + doc.body.childElementCount;
                fit();
                if (window.ResizeObserver) new ResizeObserver(fit).observe(doc.body);
                doc.addEventListener('toggle', fit, true);
                doc.querySelectorAll('img').forEach(img => img.addEventListener('load', fit));
                doc.addEventListener('click', (e) => {
                    const a = e.target.closest && e.target.closest('a[href^="mailto:"]');
                    if (!a) return;
                    e.preventDefault();
                    const root = frame.closest('.mr');
                    if (root && root.dataset.send === '1') {
                        Livewire.dispatch('mail-compose', { mode: 'new', accountId: root.dataset.account, to: decodeURIComponent(a.getAttribute('href').slice(7).split('?')[0]) });
                    }
                });
            };
            frame.addEventListener('load', wire);
            if (frame.contentDocument && frame.contentDocument.readyState === 'complete') wire();
        },
    }));
</script>
@endscript
