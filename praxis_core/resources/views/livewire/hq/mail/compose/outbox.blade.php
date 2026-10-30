<div class="mo">
    <style>
        /* Mail → Scheduled and unsent (PR 3). Brand tokens only. */
        .mo { max-width: 960px; }
        .mo-section { margin-top: 28px; }
        .mo-section h2 { margin: 0 0 12px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
        .mo-list { list-style: none; margin: 0; padding: 0; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); }
        .mo-list li { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; padding: 12px 16px; border-bottom: 1px solid var(--color-line); }
        .mo-list li:last-child { border-bottom: 0; }
        .mo-main { min-width: 0; flex: 1 1 320px; }
        .mo-main strong { display: block; color: var(--color-ink); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mo-meta { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); overflow-wrap: anywhere; }
        .mo-error { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-danger); margin-top: 2px; }
        .mo-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        @media (max-width: 640px) { .mo-actions { width: 100%; } }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Scheduled and unsent</h1>
            <p class="page-subtitle">Your messages waiting to go, those that didn’t go, and drafts you haven’t finished. Only you see them.</p>
        </div>
        <div class="mo-actions">
            <a href="{{ route('hq.mail.inbox') }}" class="btn btn-secondary" wire:navigate><i data-lucide="mail"></i> Mail</a>
            <button type="button" class="btn btn-primary" x-on:click="Livewire.dispatch('mail-compose', { mode: 'new' })"><i data-lucide="pen-line"></i> Write a message</button>
        </div>
    </div>

    @if ($flash)
        <div class="alert alert-info mb-6" role="status"><i data-lucide="info"></i><span>{{ $flash }}</span></div>
    @endif

    @php
        $line = function ($row) {
            $p = $row->payload ?? [];
            $people = collect([...($p['to'] ?? []), ...($p['cc'] ?? []), ...($p['bcc'] ?? [])])->map(fn ($a) => $a['name'] ?: $a['email']);
            return [
                'subject' => trim((string) ($p['subject'] ?? '')) ?: '(no subject)',
                'to' => $people->isEmpty() ? 'No recipients yet' : 'To ' . $people->take(3)->implode(', ') . ($people->count() > 3 ? ' and ' . ($people->count() - 3) . ' more' : ''),
            ];
        };
    @endphp

    @if ($failed->isEmpty() && $scheduled->isEmpty() && $drafts->isEmpty())
        <div class="empty-state">
            <strong>Nothing waiting</strong>
            Messages you schedule, and any that don’t go, show here until they are sent.
        </div>
    @endif

    @foreach (['failed' => ['Not sent', $failed], 'scheduled' => ['Waiting to go', $scheduled], 'drafts' => ['Drafts', $drafts]] as $group => [$heading, $rows])
        @if ($rows->isNotEmpty())
            <section class="mo-section" aria-labelledby="mo-{{ $group }}">
                <h2 id="mo-{{ $group }}">{{ $heading }}</h2>
                <ul class="mo-list">
                    @foreach ($rows as $row)
                        @php $l = $line($row); @endphp
                        <li wire:key="mo-{{ $row->id }}">
                            <div class="mo-main">
                                <strong>{{ $l['subject'] }}</strong>
                                <div class="mo-meta">
                                    {{ $l['to'] }} · from {{ $row->account->email }} ·
                                    @if ($row->status === 'queued')
                                        {{ ! empty($row->payload['scheduled']) ? 'goes' : 'trying again' }} {{ $row->send_at?->timezone($tz)->format('D j M, H:i') }}
                                    @else
                                        changed {{ $row->updated_at?->timezone($tz)->format('j M, H:i') }}
                                    @endif
                                </div>
                                @if ($row->last_error)
                                    <div class="mo-error">{{ $row->last_error }}</div>
                                @endif
                            </div>
                            <div class="mo-actions">
                                @if ($confirmDiscard === $row->id)
                                    <span class="mo-meta">Discard this message?</span>
                                    <button type="button" class="btn btn-danger btn-sm" wire:click="discard('{{ $row->id }}')">Discard message</button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="askDiscard(null)">Keep it</button>
                                @else
                                    @if ($row->status === 'queued')
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="sendNow('{{ $row->id }}')"><i data-lucide="send"></i> Send now</button>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="cancelSchedule('{{ $row->id }}')"><i data-lucide="rotate-ccw"></i> Cancel schedule</button>
                                    @elseif ($row->status === 'failed')
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="sendNow('{{ $row->id }}')"><i data-lucide="send"></i> Send again</button>
                                    @endif
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="edit('{{ $row->id }}')"><i data-lucide="pencil"></i> Edit</button>
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="askDiscard('{{ $row->id }}')" aria-label="Discard {{ $l['subject'] }}"><i data-lucide="trash-2"></i></button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    <livewire:hq.mail.composer />
</div>
