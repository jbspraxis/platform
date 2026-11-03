{{-- A lead's Emails tab (App\Livewire\Hq\Leads\EmailsTab, docs/LEADS.md section 9). HQ is English. --}}
<div class="le">
    <style>
        .le-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px 16px; margin-bottom: 16px; }
        .le-head p { margin: 0; max-width: 560px; }
        .le-write { display: flex; flex-wrap: wrap; gap: 8px; }
        .le-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .le-list > li { border-top: 1px solid var(--color-line); }
        .le-list > li:first-child { border-top: 0; }
        .le-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; gap: 4px 12px; align-items: start; padding: 12px 4px; color: inherit; text-decoration: none; border-radius: var(--radius-control); }
        a.le-row:hover, a.le-row:focus-visible { background: var(--color-mist); }
        .le-dir { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: var(--color-mist); color: var(--color-slate); }
        .le-dir svg { width: 14px; height: 14px; }
        .le-dir.is-out { background: var(--color-teal-tint); color: var(--color-petrol); }
        .le-who { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); overflow-wrap: anywhere; }
        .le-subject { display: block; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .le-snippet { display: block; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-slate); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .le-meta { font-size: 0.75rem; line-height: 1.25rem; color: var(--color-muted); text-align: right; white-space: nowrap; display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
        .le-meta svg { width: 14px; height: 14px; }
        @media (max-width: 600px) {
            .le-row { grid-template-columns: 28px minmax(0, 1fr); }
            .le-meta { grid-column: 2; text-align: left; align-items: flex-start; flex-direction: row; flex-wrap: wrap; gap: 4px 8px; white-space: normal; }
            .le-write .btn { flex: 1 1 100%; justify-content: center; }
        }
    </style>
    @php
        $tz = 'Africa/Lagos';
        $when = fn ($at) => $at->copy()->setTimezone($tz)->format($at->copy()->setTimezone($tz)->isSameYear(now($tz)) ? 'D j M, H:i' : 'j M Y, H:i');
        $named = fn ($name, $email) => trim((string) $name) !== '' ? trim($name) : $email;
    @endphp

    @if ($notice)
        <div class="alert alert-success mb-6" role="status" wire:key="le-notice"><i data-lucide="circle-check"></i><span>{{ $notice }}</span></div>
    @endif

    <section class="card" aria-labelledby="le-title">
        <div class="le-head">
            <div>
                <h2 class="card-title" id="le-title" style="margin: 0 0 4px">Emails</h2>
                <p class="help">Mail in HQ to and from {{ $lead->name }}’s contacts, newest first, from the mailboxes you can read. The timeline shows who wrote to whom, never the text.</p>
            </div>
            @if ($canSend && $reachable->isNotEmpty())
                <div class="le-write">
                    @foreach ($reachable->take(3) as $c)
                        <button type="button" class="btn {{ $loop->first ? 'btn-primary' : 'btn-secondary' }}" wire:click="emailThem('{{ $c->id }}')" wire:key="le-write-{{ $c->id }}"
                                aria-label="Email {{ $c->full_name ?: $c->email }}">
                            <i data-lucide="mail-plus"></i> Email {{ $reachable->count() === 1 ? 'them' : (\Illuminate\Support\Str::before(trim($c->full_name), ' ') ?: $c->email) }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if (! $canUseMail)
            <div class="empty-state"><strong>HQ Mail isn’t open to you yet</strong>Emails with this lead show here once you can use Mail. Ask the owner to open it to you.</div>
        @elseif ($reachable->isEmpty())
            <div class="empty-state"><strong>No contact has an email yet</strong>Add one to a contact on the Overview: mail to and from that address then shows here, both ways.</div>
        @elseif ($emails->isEmpty())
            <div class="empty-state">
                <strong>No emails yet</strong>
                {{ $canSend ? 'Write to them with the button above: what you send and what they answer shows here.' : 'Mail to and from their contacts shows here once someone writes.' }}
            </div>
        @else
            <ul class="le-list">
                @foreach ($emails as $e)
                    @php
                        $out = $e->direction === 'out';
                        $to = collect($e->to)->map(fn ($a) => $named($a['name'] ?? null, $a['email'] ?? ''))->filter()->take(3)->implode(', ');
                    @endphp
                    <li wire:key="le-{{ $e->id }}">
                        <a class="le-row" href="{{ route('hq.mail.inbox', array_filter(['box' => $e->account_id, 'thread' => $e->thread_id])) }}" wire:navigate>
                            <span class="le-dir {{ $out ? 'is-out' : '' }}" aria-hidden="true"><i data-lucide="{{ $out ? 'arrow-up-right' : 'arrow-down-left' }}"></i></span>
                            <span class="le-who">
                                <span class="le-subject">{{ $e->subject }}</span>
                                <span>{{ $out ? 'To ' . ($to ?: $e->contact_email) : 'From ' . $named($e->from_name, $e->from_email) }}</span>
                                @if (filled($e->snippet))<span class="le-snippet">{{ $e->snippet }}</span>@endif
                            </span>
                            <span class="le-meta">
                                <time datetime="{{ $e->at->toIso8601String() }}">{{ $when($e->at) }}</time>
                                <span>{{ $out ? 'Sent' : 'Received' }} · {{ $e->mailbox }}</span>
                                @if ($e->has_attachments)<span><i data-lucide="paperclip" aria-label="With attachments"></i></span>@endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($emails->count() >= $limit)
                <p class="help" style="margin: 12px 0 0">The latest {{ $limit }} are shown. Search Mail for older ones.</p>
            @endif
        @endif
    </section>

    @if ($canSend)
        {{-- HQ Mail's composer (docs/MAIL.md 6.2), opened by Email them --}}
        <livewire:hq.mail.composer :key="'le-composer-' . $lead->id" />
    @endif
</div>
