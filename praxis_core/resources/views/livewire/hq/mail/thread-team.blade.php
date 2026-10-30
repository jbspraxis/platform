<div class="mt" @if ($thread) wire:poll.20s="heartbeat" @endif>
    @if ($thread)
        <style>
            /* Mail → teamwork bar in the reader (PR 4). Brand tokens only. */
            .mt { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
            .mt-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 8px 12px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-mist); }
            .mt-assign { display: flex; align-items: center; gap: 8px; min-width: 0; }
            .mt-assign .select { width: auto; min-width: 0; max-width: 220px; padding-top: 4px; padding-bottom: 4px; font-size: 0.875rem; }
            .mt-label { font-size: 0.8125rem; color: var(--color-muted); white-space: nowrap; }
            .mt-who { font-size: 0.875rem; color: var(--color-ink); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .mt-spacer { flex: 1 1 auto; }
            .mt-tools { display: flex; flex-wrap: wrap; gap: 6px; }
            .mt-tools .btn[aria-pressed="true"] { background: var(--color-teal-tint); border-color: var(--color-teal); color: var(--color-petrol); }
            .mt-count { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--color-petrol); color: var(--color-fg-on-dark); font-size: 0.6875rem; font-weight: 700; }
            .mt-presence { display: flex; align-items: center; gap: 8px; font-size: 0.875rem; padding: 8px 12px; border-radius: var(--radius-control); }
            .mt-presence svg { width: 16px; height: 16px; flex-shrink: 0; }
            .mt-presence.is-replying { background: var(--color-warning-tint); color: var(--color-warning); font-weight: 600; }
            .mt-presence.is-viewing { color: var(--color-muted); padding-top: 0; padding-bottom: 0; }
            .mt-panel { border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); padding: 12px 16px; }
            .mt-panel h3 { margin: 0 0 8px; font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
            .mt-notes, .mt-activity { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
            .mt-notes li { padding: 10px 0; border-bottom: 1px solid var(--color-line); }
            .mt-notes li:first-child { padding-top: 0; }
            .mt-note-head { display: flex; align-items: baseline; gap: 8px; font-size: 0.8125rem; color: var(--color-muted); }
            .mt-note-head strong { color: var(--color-ink); font-weight: 600; }
            .mt-note-head button { margin-left: auto; background: none; border: 0; padding: 0; color: var(--color-muted); cursor: pointer; font-size: 0.8125rem; }
            .mt-note-head button:hover, .mt-note-head button:focus-visible { color: var(--color-danger); text-decoration: underline; }
            .mt-note-body { margin: 4px 0 0; white-space: pre-wrap; overflow-wrap: anywhere; color: var(--color-fg-body); font-size: 0.9375rem; }
            .mt-note-form { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
            .mt-note-form .textarea { min-height: 72px; }
            .mt-note-actions { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
            .mt-activity li { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 0.875rem; border-bottom: 1px solid var(--color-line); }
            .mt-activity li:last-child, .mt-notes li:last-child { border-bottom: 0; }
            .mt-activity time { color: var(--color-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
            .mt-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
            @media (max-width: 640px) {
                .mt-bar { padding: 8px; }
                .mt-spacer { display: none; }
                .mt-assign { flex: 1 1 100%; flex-wrap: wrap; }
                .mt-assign .select { flex: 1 1 150px; max-width: none; }
                .mt-tools { flex: 1 1 100%; display: grid; grid-template-columns: repeat(auto-fit, minmax(96px, 1fr)); }
                .mt-tools .btn { justify-content: center; padding-left: 6px; padding-right: 6px; }
            }
        </style>

        @php
            $tz = 'Africa/Lagos';
            $when = fn ($at) => $at ? ($at->timezone($tz)->isToday() ? $at->timezone($tz)->format('H:i') : $at->timezone($tz)->format('j M, H:i')) : '';
            $replying = array_values(array_filter($others, fn ($o) => $o['state'] === 'replying'));
            $viewing = array_values(array_filter($others, fn ($o) => $o['state'] === 'viewing'));
            $names = fn ($list) => collect($list)->pluck('name')->join(', ', ' and ');
        @endphp

        <div class="mt-bar" role="group" aria-label="Teamwork on this conversation">
            @if ($thread->status === 'done')
                <span class="pill pill-success" title="{{ $doneBy ? 'Marked done by ' . $doneBy->name : '' }}">Done{{ $thread->done_at ? ' · ' . $when($thread->done_at) : '' }}</span>
            @else
                <span class="pill pill-info">Open</span>
            @endif

            <div class="mt-assign">
                @if ($canSend)
                    <label for="mt-assign-{{ $thread->id }}" class="mt-label">Assigned to</label>
                    @if ($assignee) <x-hq.avatar :user="$assignee" :size="24" /> @endif
                    <select id="mt-assign-{{ $thread->id }}" class="select" wire:change="assign($event.target.value)" wire:loading.attr="disabled" wire:target="assign,assignToMe">
                        <option value="" @selected(! $thread->assigned_to)>Nobody</option>
                        @foreach ($assignees as $person)
                            <option value="{{ $person->id }}" @selected($thread->assigned_to === $person->id)>{{ $person->id === $me->id ? $person->name . ' (you)' : $person->name }}</option>
                        @endforeach
                        @if ($assignee && ! $assignees->contains('id', $assignee->id))
                            <option value="{{ $assignee->id }}" selected>{{ $assignee->name }} (no longer sends)</option>
                        @endif
                    </select>
                    @if ($thread->assigned_to !== $me->id)
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="assignToMe" wire:loading.attr="disabled" wire:target="assign,assignToMe"><i data-lucide="user-plus"></i> Assign to me</button>
                    @endif
                @else
                    <span class="mt-label">Assigned to</span>
                    @if ($assignee) <x-hq.avatar :user="$assignee" :size="24" /> @endif
                    <span class="mt-who">{{ $assignee ? ($assignee->id === $me->id ? 'You' : $assignee->name) : 'Nobody' }}</span>
                @endif
            </div>

            <span class="mt-spacer"></span>

            <div class="mt-tools">
                @if ($canSend)
                    @if ($thread->status === 'done')
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="reopen" wire:loading.attr="disabled" wire:target="reopen"><i data-lucide="rotate-ccw"></i> Open again</button>
                    @else
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="markDone" wire:loading.attr="disabled" wire:target="markDone"><i data-lucide="check"></i> Mark done</button>
                    @endif
                @endif
                <button type="button" class="btn btn-ghost btn-sm" wire:click="togglePanel('notes')" aria-pressed="{{ $panel === 'notes' ? 'true' : 'false' }}" aria-controls="mt-notes-{{ $thread->id }}">
                    <i data-lucide="message-square"></i> Notes @if ($notes->count()) <span class="mt-count" aria-label="{{ $notes->count() }} notes">{{ $notes->count() }}</span> @endif
                </button>
                <button type="button" class="btn btn-ghost btn-sm" wire:click="togglePanel('activity')" aria-pressed="{{ $panel === 'activity' ? 'true' : 'false' }}" aria-controls="mt-activity-{{ $thread->id }}">
                    <i data-lucide="clock"></i> Activity
                </button>
            </div>
        </div>

        @if ($error)
            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $error }}</span></div>
        @endif

        @if ($replying)
            <div class="mt-presence is-replying" role="status">
                <i data-lucide="pen-line"></i>
                @foreach ($replying as $o) <x-hq.avatar :user="$o['avatar'] ?? null" :size="22" /> @endforeach
                <span>{{ $names($replying) }} {{ count($replying) > 1 ? 'are' : 'is' }} writing a reply. Check with them before you send one.</span>
            </div>
        @endif
        @if ($viewing)
            <div class="mt-presence is-viewing" role="status">
                <i data-lucide="eye"></i>
                @foreach ($viewing as $o) <x-hq.avatar :user="$o['avatar'] ?? null" :size="22" /> @endforeach
                <span>{{ $names($viewing) }} {{ count($viewing) > 1 ? 'are' : 'is' }} also looking at this.</span>
            </div>
        @endif

        @if ($panel === 'notes')
            <section class="mt-panel" id="mt-notes-{{ $thread->id }}" aria-labelledby="mt-notes-title-{{ $thread->id }}">
                <h3 id="mt-notes-title-{{ $thread->id }}">Team notes</h3>
                @if ($notes->isEmpty())
                    <p class="help">No notes yet. Leave one for whoever picks this up next.</p>
                @else
                    <ul class="mt-notes">
                        @foreach ($notes as $n)
                            <li wire:key="note-{{ $n->id }}">
                                <div class="mt-note-head">
                                    <x-hq.avatar :user="$n->user" :size="24" />
                                    <strong>{{ $n->user_id === $me->id ? 'You' : ($n->user?->name ?? 'Former colleague') }}</strong>
                                    <time datetime="{{ $n->created_at?->toIso8601String() }}">{{ $when($n->created_at) }}</time>
                                    @if ($n->user_id === $me->id || $canManage)
                                        <button type="button" wire:click="deleteNote({{ $n->id }})" wire:confirm="Delete this note? Nobody will see it any more.">Delete</button>
                                    @endif
                                </div>
                                <p class="mt-note-body">{{ $n->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form class="mt-note-form" wire:submit="addNote">
                    <label for="mt-note-{{ $thread->id }}" class="mt-hidden">New note</label>
                    <textarea id="mt-note-{{ $thread->id }}" class="textarea" wire:model="note" rows="3" maxlength="{{ \App\Support\Mail\Team\MailTeam::NOTE_MAX }}" placeholder="For example: called them, they’ll send the PO on Monday"></textarea>
                    @error('note') <p class="field-error">{{ $message }}</p> @enderror
                    <div class="mt-note-actions">
                        <span class="help">Your team sees this note; the client never does.</span>
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="addNote"><i data-lucide="plus"></i> Add note</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($panel === 'activity')
            <section class="mt-panel" id="mt-activity-{{ $thread->id }}" aria-labelledby="mt-activity-title-{{ $thread->id }}">
                <h3 id="mt-activity-title-{{ $thread->id }}">Activity</h3>
                @if (! $activity)
                    <p class="help">Nothing has happened to this conversation in HQ yet.</p>
                @else
                    <ul class="mt-activity">
                        @foreach ($activity as $a)
                            <li><span>{{ $a['text'] }}</span><time datetime="{{ $a['at']->toIso8601String() }}">{{ $when($a['at']) }}</time></li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    @endif
</div>
