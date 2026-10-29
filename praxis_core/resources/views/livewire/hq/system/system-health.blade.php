<div class="sh">
    <style>
        /* System Health: only what the shared component classes don't cover. Brand tokens only. */
        .sh-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 16px; }
        .sh-fix { margin-top: 12px; padding-left: 10px; border-left: 2px solid var(--color-warning); color: var(--color-ink); }
        .sh-row { cursor: pointer; }
        .sh-row:focus-visible { outline: 2px solid var(--color-deep-teal); outline-offset: -2px; }
        .sh-where { color: var(--color-muted); font-size: 0.8125rem; margin-top: 2px; word-break: break-all; }
        .sh-drawer-bg { position: fixed; inset: 0; background: rgba(12, 20, 43, 0.45); z-index: 10000; }
        .sh-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(760px, 100vw); background: var(--color-paper); border-left: 1px solid var(--color-line); box-shadow: var(--shadow-float); z-index: 10001; overflow-y: auto; padding: 24px; color: var(--color-slate); }
        .sh-section { margin-top: 24px; }
        .sh-section h3 { margin: 0 0 8px; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); }
        /* Code and stack traces keep a system monospace so columns line up; this is code, not brand text */
        .sh-pre { background: var(--color-mist); border: 1px solid var(--color-line); border-radius: var(--radius-control); padding: 12px; overflow-x: auto; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 0.75rem; line-height: 1.55; white-space: pre; color: var(--color-ink); margin: 0; }
        .sh-events { list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; }
        .sh-events li { padding: 6px 0; border-bottom: 1px solid var(--color-line); color: var(--color-slate); word-break: break-all; font-variant-numeric: tabular-nums; }
        @media (max-width: 700px) { .sh-hide-sm { display: none; } }
        [x-cloak] { display: none !important; }
    </style>

    @php
        $stateLabels = ['ok' => 'All good', 'warn' => 'Needs attention', 'bad' => 'Problem', 'unknown' => 'Unknown'];
        $statePills = ['ok' => 'pill-success', 'warn' => 'pill-warning', 'bad' => 'pill-danger', 'unknown' => 'pill-neutral'];
        $levelPills = ['warning' => 'pill-warning', 'error' => 'pill-danger', 'critical' => 'pill-danger', 'alert' => 'pill-danger', 'emergency' => 'pill-danger'];
        $statusLabels = ['new' => 'New', 'investigating' => 'Investigating', 'fixed' => 'Fixed', 'ignored' => 'Ignored'];
        $statusPills = ['new' => 'pill-info', 'investigating' => 'pill-warning', 'fixed' => 'pill-success', 'ignored' => 'pill-neutral'];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">System Health</h1>
            <p class="page-subtitle">Errors, scheduled jobs and alerts for jbspraxis.com.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="pill {{ $statePills[$overall] ?? 'pill-neutral' }}">{{ $stateLabels[$overall] ?? 'Unknown' }}</span>
            <button type="button" class="btn btn-secondary" wire:click="scanNow" wire:loading.attr="disabled" wire:target="scanNow">
                <i data-lucide="refresh-cw"></i>
                <span wire:loading.remove wire:target="scanNow">Scan logs now</span>
                <span wire:loading wire:target="scanNow">Scanning…</span>
            </button>
        </div>
    </div>

    @if($flash)
        <div class="alert alert-info mb-6" role="status">
            <i data-lucide="info"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    <div class="tabs" role="tablist">
        <button type="button" role="tab" class="tab {{ $tab === 'health' ? 'is-active' : '' }}" aria-selected="{{ $tab === 'health' ? 'true' : 'false' }}" wire:click="setTab('health')">Health checks</button>
        <button type="button" role="tab" class="tab {{ $tab === 'errors' ? 'is-active' : '' }}" aria-selected="{{ $tab === 'errors' ? 'true' : 'false' }}" wire:click="setTab('errors')">
            Errors <span class="num">({{ ($counts['new'] ?? 0) + ($counts['investigating'] ?? 0) }} open)</span>
        </button>
    </div>

    @if($tab === 'health')
        <div class="sh-grid">
            @foreach($checks as $c)
                <div class="card">
                    <div class="flex items-start justify-between gap-3" style="margin-bottom: 6px;">
                        <h2 class="card-title" style="font-size: 0.9375rem;">{{ $c['label'] }}</h2>
                        <span class="pill {{ $statePills[$c['state']] ?? 'pill-neutral' }}">{{ $stateLabels[$c['state']] ?? 'Unknown' }}</span>
                    </div>
                    <p class="help" style="margin: 0; color: var(--color-slate);">{{ $c['detail'] }}</p>
                    @if(!empty($c['fix']))
                        <p class="help sh-fix" style="margin-bottom: 0;">{{ $c['fix'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="help" style="margin: 16px 0 0;">
            {{ $heartbeat ? 'The monitor last ran ' . \App\Support\Monitor\HealthChecks::ago($heartbeat) . '.' : 'The monitor has not run yet.' }}
            Alerts and the 8am summary go to {{ $alertEmail ?: 'nobody yet (set MONITOR_ALERT_EMAIL on the server)' }}.
        </p>
    @else
        <div class="flex flex-wrap items-center gap-3" style="margin-bottom: 16px;">
            <div class="flex flex-wrap gap-2" role="group" aria-label="Filter by status">
                @foreach(['open' => 'Open', 'all' => 'All', 'fixed' => 'Fixed', 'ignored' => 'Ignored'] as $k => $label)
                    <button type="button" class="btn btn-sm {{ $statusFilter === $k ? 'btn-primary' : 'btn-secondary' }}" aria-pressed="{{ $statusFilter === $k ? 'true' : 'false' }}" wire:click="setFilter('{{ $k }}')">{{ $label }}</button>
                @endforeach
            </div>
            <label class="sr-only" for="sh-search">Search errors</label>
            <input id="sh-search" type="search" class="input" style="width: auto; min-width: min(240px, 100%); flex: 1 1 220px; max-width: 360px;" placeholder="Search by message, file or class" wire:model.live.debounce.400ms="search">
            <span style="flex: 1;"></span>
            <button type="button" class="btn btn-primary" wire:click="exportSelected">
                <i data-lucide="download"></i>
                {{ count($selected) ? 'Export ' . count($selected) . ' for AI (.md)' : 'Export open errors for AI (.md)' }}
            </button>
            @if(count($selected))
                <button type="button" class="btn btn-secondary" wire:click="bulkStatus('fixed')">Mark as fixed</button>
                <button type="button" class="btn btn-secondary" wire:click="bulkStatus('ignored')">Ignore</button>
            @endif
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            @if($groups->isEmpty())
                <div class="empty-state">
                    @if($statusFilter === 'open' && trim($search) === '')
                        <strong>No open errors</strong>
                        When something breaks, it appears here within 5 minutes.
                    @else
                        <strong>No errors match this filter</strong>
                        Try another status or clear the search.
                    @endif
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 36px;"><span class="sr-only">Select</span></th>
                                <th>Error</th>
                                <th class="num sh-hide-sm">Times</th>
                                <th>Last seen</th>
                                <th class="sh-hide-sm">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $g)
                                <tr class="sh-row" wire:key="g-{{ $g->id }}" wire:click="openGroup({{ $g->id }})" tabindex="0" @keydown.enter="$wire.openGroup({{ $g->id }})">
                                    <td @click.stop>
                                        <input type="checkbox" value="{{ $g->id }}" wire:model.live="selected" class="checkbox" aria-label="Select this error">
                                    </td>
                                    <td>
                                        <span class="pill {{ $levelPills[$g->level] ?? 'pill-neutral' }}">{{ ucfirst($g->level) }}</span>
                                        <div class="text-ink" style="font-weight: 500; word-break: break-word; margin-top: 4px;">{{ \Illuminate\Support\Str::limit(trim($g->message), 160) }}</div>
                                        <div class="sh-where">{{ $g->exception_class ? class_basename($g->exception_class) . ' · ' : '' }}{{ $g->file ? $g->file . ($g->line ? ':' . $g->line : '') : $g->source }}</div>
                                    </td>
                                    <td class="num sh-hide-sm">{{ number_format($g->occurrences) }}</td>
                                    <td style="white-space: nowrap;">{{ \App\Support\Monitor\HealthChecks::ago($g->last_seen_at) }}</td>
                                    <td class="sh-hide-sm"><span class="pill {{ $statusPills[$g->status] ?? 'pill-neutral' }}">{{ $statusLabels[$g->status] ?? ucfirst($g->status) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if($open)
        @php($g = $open['group'])
        {{-- Teleported to <body> so the HQ top bar and content wrapper cannot clip or cover it --}}
        @teleport('body')
        <div class="sh">
        <div class="sh-drawer-bg" wire:click="closeGroup"></div>
        <aside class="sh-drawer" role="dialog" aria-modal="true" aria-labelledby="sh-drawer-title" x-data="{ copied: false }" @keydown.escape.window="$wire.closeGroup()">
            <div class="flex items-start justify-between gap-3">
                <div style="min-width: 0;">
                    <span class="pill {{ $levelPills[$g->level] ?? 'pill-neutral' }}">{{ ucfirst($g->level) }}</span>
                    <h2 id="sh-drawer-title" class="modal-title" style="margin-top: 8px; word-break: break-word;">{{ trim($g->message) }}</h2>
                    <div class="sh-where">{{ $g->exception_class }}</div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="closeGroup"><i data-lucide="x"></i> Close</button>
            </div>

            <div class="sh-section flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-primary btn-sm"
                        @click="navigator.clipboard.writeText($refs.brief.value).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                    <i data-lucide="copy"></i>
                    <span x-show="!copied">Copy for AI</span><span x-show="copied" x-cloak>Copied</span>
                </button>
                <span class="help" style="margin: 0 4px;">Status:</span>
                @foreach(['new' => 'New', 'investigating' => 'Investigating', 'fixed' => 'Fixed', 'ignored' => 'Ignore'] as $k => $label)
                    <button type="button" class="btn btn-sm {{ $g->status === $k ? 'btn-primary' : 'btn-secondary' }}"
                            wire:click="setStatus({{ $g->id }}, '{{ $k }}')" aria-pressed="{{ $g->status === $k ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
                <textarea x-ref="brief" class="sr-only" aria-hidden="true" tabindex="-1" readonly>{{ $open['brief'] }}</textarea>
            </div>

            @if($open['hint'])
                <div class="sh-section">
                    <h3>What it means</h3>
                    <div class="callout">{{ $open['hint'] }}</div>
                </div>
            @endif

            <div class="sh-section">
                <h3>Where and how often</h3>
                <p style="margin: 0; font-size: 0.875rem; line-height: 1.6;">
                    <span class="sh-where" style="display: block;">{{ $g->file ? $g->file . ($g->line ? ':' . $g->line : '') : 'Location not recorded (' . $g->source . ')' }}</span>
                    <span style="font-variant-numeric: tabular-nums;">{{ number_format($g->occurrences) }} {{ $g->occurrences == 1 ? 'time' : 'times' }}. First {{ \Illuminate\Support\Carbon::parse($g->first_seen_at)->tz('Africa/Lagos')->format('j M Y, H:i') }},
                    last {{ \Illuminate\Support\Carbon::parse($g->last_seen_at)->tz('Africa/Lagos')->format('j M Y, H:i') }} (WAT).</span>
                </p>
            </div>

            <div class="sh-section">
                <h3>Recent occurrences</h3>
                <ul class="sh-events">
                    @forelse($open['events'] as $ev)
                        <li>{{ \Illuminate\Support\Carbon::parse($ev->occurred_at)->tz('Africa/Lagos')->format('j M H:i:s') }} · {{ trim(($ev->method ?? '') . ' ' . ($ev->url ?? '–')) }}{{ $ev->component ? ' · ' . $ev->component : '' }}{{ $ev->user_id ? ' · signed in' : ' · not signed in' }}</li>
                    @empty
                        <li>No details were recorded for these occurrences.</li>
                    @endforelse
                </ul>
            </div>

            @if($open['code'])
                <div class="sh-section">
                    <h3>Code around the line</h3>
                    <pre class="sh-pre">{{ $open['code'] }}</pre>
                </div>
            @endif

            @if($g->trace)
                <div class="sh-section" x-data="{ show: false }">
                    <h3 class="flex items-center gap-2">Stack trace <button type="button" class="btn btn-ghost btn-sm" @click="show = !show" x-text="show ? 'Hide' : 'Show'" :aria-expanded="show.toString()"></button></h3>
                    <pre class="sh-pre" x-show="show" x-cloak>{{ $g->trace }}</pre>
                </div>
            @endif

            <div class="sh-section">
                <h3>Notes</h3>
                @if($g->notes)
                    <pre class="sh-pre" style="white-space: pre-wrap; font-family: inherit; font-size: 0.8125rem; margin-bottom: 12px;">{{ trim($g->notes) }}</pre>
                @endif
                <div class="field">
                    <label class="label" for="sh-note">Add a note</label>
                    <textarea id="sh-note" class="textarea" wire:model="noteText" aria-describedby="sh-note-help" style="min-height: 72px;"></textarea>
                    <span id="sh-note-help" class="help">What was found or done. The team sees it, and Copy for AI includes it.</span>
                    @error('noteText') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <button type="button" class="btn btn-secondary" style="margin-top: 8px;" wire:click="addNote">Add note</button>
            </div>
        </aside>
        </div>
        @endteleport
    @endif
</div>
