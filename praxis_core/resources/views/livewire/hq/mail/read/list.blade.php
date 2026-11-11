@php
    $first = $total ? ($page - 1) * $perPage + 1 : 0;
    $last = min($total, $page * $perPage);
    $lastPage = max(1, (int) ceil($total / $perPage));
    $searching = $search !== '';
    $toLine = in_array($role, ['sent', 'drafts'], true);
    $filters = ['all' => 'All', 'unread' => 'Unread'] + ($shared ? ['mine' => 'Assigned to me', 'open' => 'Open', 'done' => 'Done'] : []);
@endphp
<section class="mr-list" aria-label="Conversations">
    <div class="mr-list-head">
        <div class="mr-pickers">
            @if ($accounts->count() > 1)
                <label class="sr-only" for="mr-box-pick">Mailbox</label>
                <select id="mr-box-pick" class="select" x-on:change="$wire.selectBox($event.target.value)">
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}" @selected($a->id === $account->id)>{{ $a->email }}{{ ($boxUnread[$a->id] ?? 0) > 0 ? ' (' . $boxUnread[$a->id] . ')' : '' }}</option>
                    @endforeach
                </select>
            @endif
            <label class="sr-only" for="mr-folder-pick">Folder</label>
            <select id="mr-folder-pick" class="select" x-on:change="$wire.selectFolder($event.target.value)">
                @foreach ($folders as $f)
                    @php $n = $f->role === 'drafts' ? $f->total_count : $f->unread_count; @endphp
                    <option value="{{ $f->role === 'other' ? $f->id : $f->role }}" @selected($currentFolder && $f->id === $currentFolder->id)>{{ $f->label() }}{{ $n > 0 ? " ({$n})" : '' }}</option>
                @endforeach
            </select>
        </div>

        <form class="mr-search" role="search" @submit.prevent>
            <label class="sr-only" for="mr-search">Search mail</label>
            @include('livewire.hq.mail.read.icon', ['name' => 'search'])
            <input id="mr-search" type="search" class="input" placeholder="Search this mailbox" wire:model.live.debounce.400ms="search"
                   autocomplete="off" spellcheck="false" @keydown.escape="$wire.clearSearch(); $el.blur()">
            @if ($searching)
                <button type="button" class="mr-icon-btn" wire:click="clearSearch" aria-label="Clear search">@include('livewire.hq.mail.read.icon', ['name' => 'x'])</button>
            @endif
        </form>

        @if (count($filters) > 1)
            <div class="mr-filters" role="group" aria-label="Show">
                @foreach ($filters as $key => $label)
                    <button type="button" class="mr-filter {{ $filter === $key ? 'is-active' : '' }}" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        @endif

        <div class="mr-list-meta">
            <span>
                @if ($searching)
                    {{ $total }} {{ \Illuminate\Support\Str::plural('conversation', $total) }} found
                @elseif ($total)
                    {{ $first }}–{{ $last }} of {{ number_format($total) }}
                @else
                    {{ $currentFolder?->label() }}
                @endif
            </span>
            <span class="mr-pager">
                @if ($canOrganise && in_array($role, ['trash', 'spam'], true) && ! $searching && $total)
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="askConfirm('empty')">Empty {{ $currentFolder->label() }}</button>
                @endif
                @if ($lastPage > 1)
                    <button type="button" class="mr-icon-btn" wire:click="goToPage({{ $page - 1 }})" @disabled($page <= 1) aria-label="Newer">@include('livewire.hq.mail.read.icon', ['name' => 'chevron-left'])</button>
                    <button type="button" class="mr-icon-btn" wire:click="goToPage({{ $page + 1 }})" @disabled($page >= $lastPage) aria-label="Older">@include('livewire.hq.mail.read.icon', ['name' => 'chevron-right'])</button>
                @endif
            </span>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="mr-empty">
            @if ($searching)
                <strong>Nothing in HQ's copy matches "{{ \Illuminate\Support\Str::limit($search, 60) }}"</strong>
                HQ keeps the last 12 months. Search the mail server for older mail below.
            @elseif ($filter !== 'all')
                <strong>Nothing to show with this filter</strong>
                <button type="button" class="btn btn-ghost btn-sm mt-2" wire:click="setFilter('all')">Show all</button>
            @elseif ($currentFolder && ! $currentFolder->initial_synced_at)
                <strong>HQ is copying this folder</strong>
                The newest mail arrives first. This page refreshes by itself.
            @else
                <strong>No mail in {{ $currentFolder?->label() ?? 'this folder' }}</strong>
                @if ($role === 'inbox') New mail shows here as it arrives. @endif
            @endif
        </div>
    @else
        <ul class="mr-rows" role="list">
            @foreach ($rows as $t)
                @php
                    $unread = (int) $t->row_unread > 0;
                    $active = $openThread && $openThread->id === $t->id;
                    $names = \App\Livewire\Hq\Mail\Inbox::people($t->participants, $account->email) ?: $account->email;
                    $folderTags = $searching ? collect(explode(',', (string) $t->row_folders))->map(fn ($id) => $folderNames[(int) $id] ?? null)->filter()->unique()->take(2) : collect();
                @endphp
                <li wire:key="t-{{ $t->id }}">
                    {{-- The row shows as open and read at once, before the server answers (the answer then confirms it) --}}
                    <button type="button" class="mr-row {{ $unread ? 'is-unread' : '' }} {{ $active ? 'is-active' : '' }}" wire:click="openThread({{ $t->id }})"
                            @click="$el.closest('.mr-rows').querySelectorAll('.mr-row.is-active').forEach((r) => { r.classList.remove('is-active'); r.setAttribute('aria-current', 'false') }); $el.classList.add('is-active'); $el.classList.remove('is-unread'); $el.setAttribute('aria-current', 'true')"
                            data-thread-row="{{ $t->id }}" aria-current="{{ $active ? 'true' : 'false' }}">
                        <span class="mr-row-from">{{ $toLine ? 'To: ' : '' }}{{ $names }}@if ($t->message_count > 1)<span class="mr-count">{{ $t->message_count }}</span>@endif</span>
                        <span class="mr-row-date" title="{{ $t->row_last_at ? \Illuminate\Support\Carbon::parse($t->row_last_at)->timezone(\App\Livewire\Hq\Mail\Inbox::TZ)->format('j M Y, H:i') : '' }}">{{ $t->row_last_at ? \App\Livewire\Hq\Mail\Inbox::when(\Illuminate\Support\Carbon::parse($t->row_last_at)) : '' }}</span>
                        <span class="mr-row-subject">@if ($unread)<span class="sr-only">Unread: </span>@endif{{ $t->subject ?: '(no subject)' }}</span>
                        <span class="mr-row-snippet">{{ $t->snippet }}</span>
                        <span class="mr-row-badges">
                            @foreach ($folderTags as $label)<span class="tag">{{ $label }}</span>@endforeach
                            @if ($shared && $t->status === 'done')<span class="pill pill-success">Done</span>@endif
                            @if ($shared && $t->assignee)<span class="tag" title="Assigned to {{ $t->assignee->name }}">{{ $t->assigned_to === $user->id ? 'You' : $t->assignee->first_name }}</span>@endif
                            @if ($t->row_attach)<span title="Has attachments">@include('livewire.hq.mail.read.icon', ['name' => 'paperclip'])<span class="sr-only">Has attachments</span></span>@endif
                            @if ($t->row_flagged)<span class="is-flag" title="Flagged">@include('livewire.hq.mail.read.icon', ['name' => 'flag'])<span class="sr-only">Flagged</span></span>@endif
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Past HQ's copy: older mail and server search, read live from the mail server --}}
    @php $liveHere = ! empty($live) && ($live['folder'] ?? null) === $currentFolder?->id; @endphp
    @if ($liveHere)
        <div class="mr-live-head" wire:key="live-head">
            <span>
                @if ($live['mode'] === 'older')
                    Older than 12 months, from the mail server
                @else
                    On the mail server: {{ count($live['items']) }} {{ \Illuminate\Support\Str::plural('result', count($live['items'])) }} in {{ $currentFolder->label() }}
                @endif
            </span>
            <button type="button" class="mr-icon-btn" style="width: 28px; height: 28px;" wire:click="closeLive" aria-label="Hide these results">@include('livewire.hq.mail.read.icon', ['name' => 'x'])</button>
        </div>
        @if (empty($live['items']))
            <div class="mr-empty">
                <strong>{{ $live['mode'] === 'older' ? 'No older mail in this folder' : 'Nothing on the mail server matches either' }}</strong>
            </div>
        @else
            <ul class="mr-rows" role="list">
                @foreach ($live['items'] as $item)
                    @php $activeLive = $liveUid === $item['uid']; @endphp
                    <li wire:key="live-{{ $item['uid'] }}">
                        <button type="button" class="mr-row {{ $item['is_seen'] ? '' : 'is-unread' }} {{ $activeLive ? 'is-active' : '' }}" wire:click="openLive({{ $item['uid'] }})" aria-current="{{ $activeLive ? 'true' : 'false' }}">
                            <span class="mr-row-from">{{ $item['from_name'] ?: $item['from_email'] ?: 'Unknown sender' }}</span>
                            <span class="mr-row-date">{{ $item['received_at'] ? \App\Livewire\Hq\Mail\Inbox::when(\Illuminate\Support\Carbon::parse($item['received_at'])) : '' }}</span>
                            <span class="mr-row-subject">{{ $item['subject'] ?: '(no subject)' }}</span>
                            <span class="mr-row-snippet"></span>
                            <span class="mr-row-badges">
                                @if ($item['has_attachments'])@include('livewire.hq.mail.read.icon', ['name' => 'paperclip'])@endif
                                @if ($item['is_flagged'])<span class="is-flag">@include('livewire.hq.mail.read.icon', ['name' => 'flag'])</span>@endif
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    <div class="mr-list-foot">
        @if ($searching && $currentFolder && ! ($liveHere && $live['mode'] === 'server' && $live['query'] === $search))
            <button type="button" class="btn btn-secondary btn-sm" wire:click="searchServer" wire:loading.attr="disabled" wire:target="searchServer">
                @include('livewire.hq.mail.read.icon', ['name' => 'server'])
                <span wire:loading.remove wire:target="searchServer">Search older mail on the server</span>
                <span wire:loading wire:target="searchServer">Searching the mail server…</span>
            </button>
            <p class="help">Looks through all of {{ $currentFolder->label() }}, any date. It takes a little longer.</p>
        @elseif (! $searching && $currentFolder && $page >= $lastPage && ($currentFolder->initial_synced_at || $total) && (! $liveHere || $live['mode'] !== 'older' || $live['next']))
            <button type="button" class="btn btn-secondary btn-sm" wire:click="loadOlder" wire:loading.attr="disabled" wire:target="loadOlder">
                @include('livewire.hq.mail.read.icon', ['name' => 'clock'])
                <span wire:loading.remove wire:target="loadOlder">{{ $liveHere && $live['mode'] === 'older' ? 'Load more older mail' : 'Load older mail' }}</span>
                <span wire:loading wire:target="loadOlder">Reading the mail server…</span>
            </button>
            @unless ($liveHere)<p class="help">HQ keeps the last 12 months. Older mail is read from the server.</p>@endunless
        @endif
    </div>
</section>
