<div class="mi" wire:poll.60s>
    <style>
        /* PR 1's placeholder list; PR 2 replaces this view with the full reader. Brand tokens only. */
        .mi-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .mi-list { list-style: none; margin: 0; padding: 0; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); }
        .mi-list li { display: grid; grid-template-columns: minmax(0, 14rem) minmax(0, 1fr) auto; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--color-line); align-items: baseline; }
        .mi-list li:last-child { border-bottom: 0; }
        .mi-list .is-unread { font-weight: 600; color: var(--color-ink); }
        .mi-from, .mi-subject { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mi-subject span { color: var(--color-muted); font-weight: 400; }
        @media (max-width: 640px) { .mi-list li { grid-template-columns: minmax(0, 1fr) auto; } .mi-subject { grid-column: 1 / -1; grid-row: 2; } }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $account ? $account->display_name : 'Mail' }}</h1>
            <p class="page-subtitle">
                @if ($account) {{ $account->email }} · {{ $currentFolder?->label() ?? 'Inbox' }}. Reading, replying and the full mailbox arrive with the next update. @else Read and send your mail in HQ. @endif
            </p>
        </div>
        <a href="{{ route('hq.mail.mailboxes') }}" class="btn btn-secondary" wire:navigate><i data-lucide="settings"></i> Mailboxes</a>
    </div>

    @if ($accounts->count() > 1)
        <nav class="mi-tabs" aria-label="Mailboxes">
            @foreach ($accounts as $a)
                <a href="{{ route('hq.mail.inbox', ['box' => $a->id]) }}" class="btn btn-sm {{ $account && $a->id === $account->id ? 'btn-primary' : 'btn-secondary' }}" wire:navigate>{{ $a->email }}</a>
            @endforeach
        </nav>
    @endif

    @if (! $account)
        <div class="empty-state">
            <strong>No mailbox connected yet</strong>
            Connect your work mailbox to read your mail here.
            <div class="mt-4"><a href="{{ route('hq.mail.mailboxes') }}" class="btn btn-primary" wire:navigate><i data-lucide="plug"></i> Connect your mailbox</a></div>
        </div>
    @elseif ($threads->isEmpty())
        <div class="empty-state">
            <strong>Nothing here yet</strong>
            HQ is copying this mailbox; the newest mail arrives first. This page refreshes by itself.
        </div>
    @else
        <ul class="mi-list" aria-label="Conversations">
            @foreach ($threads as $t)
                @php $first = $t->participants[0] ?? null; @endphp
                <li wire:key="t-{{ $t->id }}" class="{{ $t->unread_count > 0 ? 'is-unread' : '' }}">
                    <div class="mi-from">{{ $first['name'] ?? $first['email'] ?? 'Unknown sender' }} @if ($t->message_count > 1) <span class="text-muted">({{ $t->message_count }})</span> @endif</div>
                    <div class="mi-subject">{{ $t->subject ?: '(no subject)' }} <span>— {{ $t->snippet }}</span></div>
                    <div class="help num">{{ $t->last_message_at?->timezone('Africa/Lagos')->isToday() ? $t->last_message_at->timezone('Africa/Lagos')->format('H:i') : $t->last_message_at?->timezone('Africa/Lagos')->format('j M') }}</div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Mount points (docs/MAIL.md): PR 2 keeps these in its view; PR 3 and PR 4 fill the components --}}
    <livewire:hq.mail.composer />
</div>
