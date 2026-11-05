@php
    use App\Support\Campaigns\Broadcasts;
    $tone = fn (string $status) => match ($status) { 'Completed' => 'pill-success', 'Sending' => 'pill-info', 'Scheduled' => 'pill-warning', 'Cancelled' => 'pill-danger', default => 'pill-neutral' };
    $label = fn (string $status) => ['Completed' => 'Sent', 'Sending' => 'Sending', 'Scheduled' => 'Scheduled', 'Cancelled' => 'Cancelled', 'Draft' => 'Draft'][$status] ?? $status;
    $pct = fn ($n, $of) => $of > 0 ? round(100 * $n / $of) . '%' : '—';
    $filters = ['all' => 'All', 'drafts' => 'Drafts', 'scheduled' => 'Scheduled', 'sending' => 'Sending', 'sent' => 'Sent'];
    $countOf = ['all' => $counts->sum(), 'drafts' => $counts['Draft'] ?? 0, 'scheduled' => $counts['Scheduled'] ?? 0, 'sending' => $counts['Sending'] ?? 0, 'sent' => ($counts['Completed'] ?? 0) + ($counts['Cancelled'] ?? 0)];
@endphp
<div class="bcl">
    <style>
        /* Broadcasts list (docs/CAMPAIGNS.md): only what the shared component classes don't cover. Brand tokens only. */
        .bcl-layouts { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-top: 16px; }
        .bcl-layout { display: flex; flex-direction: column; gap: 6px; padding: 16px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-card); background: var(--color-paper); text-align: left; cursor: pointer; font: inherit; color: var(--color-slate); }
        .bcl-layout:hover { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .bcl-layout:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; }
        .bcl-layout strong { display: flex; align-items: center; gap: 8px; color: var(--color-ink); font-size: 0.9375rem; }
        .bcl-layout strong svg { width: 18px; height: 18px; color: var(--color-deep-teal); }
        .bcl-layout small { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .bcl-filters { margin-bottom: 16px; }
        .bcl-filters .segmented-item span { color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .bcl-table td a.bcl-title { font-weight: 600; color: var(--color-ink); text-decoration: none; }
        .bcl-table td a.bcl-title:hover { text-decoration: underline; }
        .bcl-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .bcl-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .bcl-modal { max-width: 720px; }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Broadcasts</h1>
            <p class="page-subtitle">Designed emails to people who already know us: clients, leads and subscribers. Each person gets the English or French version, and one click unsubscribes them from all our marketing.</p>
        </div>
        @if ($canCreate)
            <button type="button" class="btn btn-primary" wire:click="$set('choosing', true)"><i data-lucide="plus"></i> New broadcast</button>
        @endif
    </div>


    @if ($queued > 0)
        <div class="alert alert-info mb-6" role="status"><i data-lucide="send"></i>
            <span>{{ $queued }} {{ $queued === 1 ? 'email is' : 'emails are' }} waiting to go out, 30 every 5 minutes. The last should go at about {{ $lastAt->copy()->timezone('Africa/Lagos')->format('H:i') }} WAT.</span>
        </div>
    @endif

    <div class="segmented bcl-filters" role="group" aria-label="Show">
        @foreach ($filters as $key => $name)
            <button type="button" class="segmented-item" aria-pressed="{{ $show === $key ? 'true' : 'false' }}" wire:click="$set('show', '{{ $key }}')">{{ $name }} <span>{{ $countOf[$key] }}</span></button>
        @endforeach
    </div>

    <div class="card" style="padding: 0">
        @if ($broadcasts->isEmpty())
            <div class="empty-state">
                @if ($show === 'all')
                    <p>No broadcasts yet. A broadcast is a designed email, such as an announcement, a newsletter or an event invitation, sent to a segment of our clients, leads and subscribers.</p>
                    @if ($canCreate)
                        <button type="button" class="btn btn-primary mt-3" wire:click="$set('choosing', true)"><i data-lucide="plus"></i> New broadcast</button>
                    @endif
                @else
                    <p>No {{ mb_strtolower($filters[$show]) }} broadcasts. <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('show', 'all')">Show all</button></p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="table bcl-table">
                    <thead>
                        <tr>
                            <th scope="col">Broadcast</th>
                            <th scope="col">Status</th>
                            <th scope="col">Audience</th>
                            <th scope="col" class="bcl-num">Sent</th>
                            <th scope="col" class="bcl-num">Opened <span class="sr-only">(approximate)</span></th>
                            <th scope="col" class="bcl-num">Clicked</th>
                            <th scope="col" class="bcl-num">Unsubscribed</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($broadcasts as $b)
                            @php
                                $s = $stats[$b->id] ?? null;
                                $sent = (int) ($s->sent ?? $b->sent_count);
                                $editable = in_array($b->status, ['Draft', 'Scheduled'], true) && ! $b->isLegacy();
                                $when = match ($b->status) {
                                    'Scheduled' => 'Starts ' . Broadcasts::wat($b->scheduled_at),
                                    'Draft' => 'Edited ' . $b->updated_at?->copy()->timezone('Africa/Lagos')->format('j M Y'),
                                    default => ($b->started_at ?? $b->created_at)?->copy()->timezone('Africa/Lagos')->format('j M Y, H:i') . ' WAT',
                                };
                            @endphp
                            <tr wire:key="b-{{ $b->id }}">
                                <td>
                                    <a class="bcl-title" href="{{ $editable ? route('hq.campaigns.broadcast', ['broadcast' => $b->id]) : route('hq.campaigns.report', ['broadcast' => $b->id]) }}" wire:navigate>{{ $b->title() }}</a>
                                    <small>{{ $b->isLegacy() ? 'Before Campaigns' : ($layouts[$b->layout]['label'] ?? '') }} · {{ \App\Models\Campaigns\Broadcast::LANGUAGES[$b->language] ?? strtoupper($b->language) }} · {{ $when }}</small>
                                </td>
                                <td><span class="pill {{ $tone($b->status) }}">{{ $label($b->status) }}</span></td>
                                <td>{{ $b->segment?->name ?? ($b->isLegacy() ? 'Chosen by hand' : '—') }}@if ($b->total_recipients) <small>{{ $b->total_recipients }} {{ $b->total_recipients === 1 ? 'person' : 'people' }}</small>@endif</td>
                                <td class="bcl-num">{{ $b->status === 'Draft' || $b->status === 'Scheduled' ? '—' : $sent }}@if ($s && $s->queued > 0) <small>{{ $s->queued }} to go</small>@endif</td>
                                <td class="bcl-num">{{ $sent ? $pct($s->opened ?? $b->opened_count, $sent) : '—' }}</td>
                                <td class="bcl-num">{{ $sent && $s ? $pct($s->clicked, $sent) : '—' }}</td>
                                <td class="bcl-num">{{ $sent && $s ? (int) $s->unsubscribed : '—' }}</td>
                                <td class="bcl-num">
                                    @if ($b->status === 'Draft' && $canCreate)
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="deleteDraft('{{ $b->id }}')" wire:confirm="Delete the draft “{{ $b->title() }}”? This can’t be undone.">Delete</button>
                                    @elseif (! $editable)
                                        <a class="btn btn-ghost btn-sm" href="{{ route('hq.campaigns.report', ['broadcast' => $b->id]) }}" wire:navigate>Report</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $broadcasts->links() }}</div>
    <p class="help mt-4">Opens are approximate: Apple Mail opens every email it receives to protect privacy, and some inboxes hide images. Clicks are counted exactly.</p>

    @if ($choosing)
        <div class="modal-backdrop" wire:click.self="$set('choosing', false)" @keydown.escape.window="$wire.set('choosing', false)">
            <div class="modal bcl-modal" role="dialog" aria-modal="true" aria-labelledby="bcl-new">
                <h2 class="modal-title" id="bcl-new">Choose a layout</h2>
                <p class="help m-0">Every layout carries the JBS Praxis header and footer. You can add or remove blocks afterwards, and change the layout while you write.</p>
                <div class="bcl-layouts">
                    @foreach ($layouts as $key => $l)
                        <button type="button" class="bcl-layout" wire:click="create('{{ $key }}')">
                            <strong><i data-lucide="{{ $l['icon'] }}"></i> {{ $l['label'] }}</strong>
                            <small>{{ $l['hint'] }}</small>
                        </button>
                    @endforeach
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="$set('choosing', false)">Cancel</button>
                </div>
            </div>
        </div>
    @endif
</div>
