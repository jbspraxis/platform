<div class="sh">
    <style>
        .sh { --sh-bg: #0A1128; --sh-panel: #0E1733; --sh-line: rgba(255,255,255,0.10); --sh-text: #FFFFFF; --sh-muted: #8A9BB2;
              --sh-teal: #00E5FF; --sh-ok: #00D26A; --sh-warn: #FFBD2E; --sh-bad: #FF3366; --sh-unknown: #8A9BB2;
              color: var(--sh-text); font-family: 'Inter', system-ui, sans-serif; }
        .sh-head { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .sh-head h1 { font-size: 1.5rem; font-weight: 700; margin: 0; letter-spacing: 0; }
        .sh-sub { color: var(--sh-muted); font-size: .9rem; margin-top: 4px; }
        .sh-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; font-size: .8rem; font-weight: 600; border: 1px solid var(--sh-line); }
        .sh-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; flex: none; }
        .st-ok { background: var(--sh-ok); } .st-warn { background: var(--sh-warn); } .st-bad { background: var(--sh-bad); } .st-unknown { background: var(--sh-unknown); }
        .sh-btn { background: transparent; border: 1px solid var(--sh-line); color: var(--sh-text); padding: 8px 14px; border-radius: 6px; font-size: .85rem; cursor: pointer; }
        .sh-btn:hover { border-color: var(--sh-teal); color: var(--sh-teal); }
        .sh-btn.primary { border-color: var(--sh-teal); color: var(--sh-teal); }
        .sh-btn.primary:hover { background: var(--sh-teal); color: #0A1128; }
        .sh-btn:focus-visible, .sh-tab:focus-visible, .sh-row:focus-visible { outline: 2px solid var(--sh-teal); outline-offset: 2px; }
        .sh-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--sh-line); margin-bottom: 20px; }
        .sh-tab { background: none; border: 0; color: var(--sh-muted); padding: 10px 14px; font-size: .9rem; cursor: pointer; border-bottom: 2px solid transparent; }
        .sh-tab.active { color: var(--sh-text); border-bottom-color: var(--sh-teal); font-weight: 600; }
        .sh-flash { background: rgba(0,229,255,.08); border: 1px solid rgba(0,229,255,.3); color: var(--sh-text); padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: .9rem; }
        .sh-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; }
        .sh-card { background: var(--sh-panel); border: 1px solid var(--sh-line); border-radius: 8px; padding: 16px; }
        .sh-card h3 { margin: 0 0 6px; font-size: .95rem; display: flex; align-items: center; gap: 8px; }
        .sh-card p { margin: 0; color: var(--sh-muted); font-size: .88rem; line-height: 1.5; }
        .sh-fix { margin-top: 10px !important; color: var(--sh-text) !important; border-left: 2px solid var(--sh-warn); padding-left: 10px; }
        .sh-meta { color: var(--sh-muted); font-size: .82rem; margin-top: 16px; }
        .sh-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
        .sh-seg { display: inline-flex; border: 1px solid var(--sh-line); border-radius: 6px; overflow: hidden; }
        .sh-seg button { background: none; border: 0; color: var(--sh-muted); padding: 7px 12px; font-size: .82rem; cursor: pointer; }
        .sh-seg button.on { background: rgba(0,229,255,.12); color: var(--sh-teal); font-weight: 600; }
        .sh-search { background: rgba(0,0,0,.25); border: 1px solid var(--sh-line); color: var(--sh-text); padding: 8px 12px; border-radius: 6px; min-width: 220px; font-size: .85rem; }
        .sh-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
        .sh-table th { text-align: left; color: var(--sh-muted); font-weight: 500; padding: 8px; border-bottom: 1px solid var(--sh-line); white-space: nowrap; }
        .sh-table td { padding: 10px 8px; border-bottom: 1px solid var(--sh-line); vertical-align: top; }
        .sh-row { cursor: pointer; } .sh-row:hover td { background: rgba(255,255,255,.03); }
        .sh-msg { font-weight: 500; word-break: break-word; }
        .sh-where { color: var(--sh-muted); font-family: 'Roboto Mono', monospace; font-size: .75rem; margin-top: 3px; word-break: break-all; }
        .sh-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .02em; }
        .lv-warning { background: rgba(255,189,46,.15); color: var(--sh-warn); }
        .lv-error { background: rgba(255,51,102,.15); color: var(--sh-bad); }
        .lv-critical, .lv-alert, .lv-emergency { background: var(--sh-bad); color: #fff; }
        .ss-new { color: var(--sh-teal); } .ss-investigating { color: var(--sh-warn); } .ss-fixed { color: var(--sh-ok); } .ss-ignored { color: var(--sh-muted); }
        .sh-empty { color: var(--sh-muted); padding: 32px; text-align: center; border: 1px dashed var(--sh-line); border-radius: 8px; }
        .sh-drawer-bg { position: fixed; inset: 0; background: rgba(1,3,8,.6); z-index: 10000; }
        .sh-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(760px, 100vw); background: var(--sh-bg); border-left: 1px solid var(--sh-line); z-index: 10001; overflow-y: auto; padding: 24px; }
        .sh-drawer h2 { font-size: 1.1rem; margin: 0 0 6px; word-break: break-word; }
        .sh-section { margin-top: 20px; }
        .sh-section h4 { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: var(--sh-muted); margin: 0 0 8px; }
        .sh-hint { background: rgba(0,210,106,.08); border: 1px solid rgba(0,210,106,.3); padding: 12px; border-radius: 6px; font-size: .9rem; line-height: 1.5; }
        .sh-pre { background: #050913; border: 1px solid var(--sh-line); border-radius: 6px; padding: 12px; overflow-x: auto; font-family: 'Roboto Mono', monospace; font-size: .76rem; line-height: 1.55; white-space: pre; color: #D7E0EA; }
        .sh-events { list-style: none; padding: 0; margin: 0; font-size: .84rem; }
        .sh-events li { padding: 6px 0; border-bottom: 1px solid var(--sh-line); color: var(--sh-muted); word-break: break-all; }
        .sh-note { width: 100%; min-height: 70px; background: rgba(0,0,0,.25); border: 1px solid var(--sh-line); color: var(--sh-text); border-radius: 6px; padding: 10px; font-size: .85rem; }
        @media (max-width: 700px) { .sh-hide-sm { display: none; } .sh-search { min-width: 0; width: 100%; } }
    </style>

    <div class="sh-head">
        <div>
            <h1>System Health</h1>
            <div class="sh-sub">Errors, scheduled jobs and alerts for jbspraxis.com</div>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <span class="sh-pill"><span class="sh-dot st-{{ $overall }}"></span>{{ ['ok' => 'All good', 'warn' => 'Needs attention', 'bad' => 'Problem', 'unknown' => 'Unknown'][$overall] }}</span>
            <button type="button" class="sh-btn" wire:click="scanNow" wire:loading.attr="disabled">Scan logs now</button>
        </div>
    </div>

    @if($flash)
        <div class="sh-flash" role="status">{{ $flash }}</div>
    @endif

    <div class="sh-tabs" role="tablist">
        <button type="button" role="tab" class="sh-tab {{ $tab === 'health' ? 'active' : '' }}" aria-selected="{{ $tab === 'health' ? 'true' : 'false' }}" wire:click="setTab('health')">Health</button>
        <button type="button" role="tab" class="sh-tab {{ $tab === 'errors' ? 'active' : '' }}" aria-selected="{{ $tab === 'errors' ? 'true' : 'false' }}" wire:click="setTab('errors')">
            Errors ({{ ($counts['new'] ?? 0) + ($counts['investigating'] ?? 0) }} open)
        </button>
    </div>

    @if($tab === 'health')
        <div class="sh-grid">
            @foreach($checks as $c)
                <div class="sh-card">
                    <h3><span class="sh-dot st-{{ $c['state'] }}" aria-hidden="true"></span>{{ $c['label'] }}
                        <span class="sr-only">({{ $c['state'] }})</span></h3>
                    <p>{{ $c['detail'] }}</p>
                    @if(!empty($c['fix']))
                        <p class="sh-fix">{{ $c['fix'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="sh-meta">
            Monitor last ran {{ $heartbeat ? \App\Support\Monitor\HealthChecks::ago($heartbeat) : 'never' }}.
            Alerts and the 8am digest go to {{ $alertEmail ?: 'nobody (set MONITOR_ALERT_EMAIL)' }}.
        </div>
    @else
        <div class="sh-tools">
            <div class="sh-seg" role="group" aria-label="Filter by status">
                @foreach(['open' => 'Open', 'all' => 'All', 'fixed' => 'Fixed', 'ignored' => 'Ignored'] as $k => $label)
                    <button type="button" class="{{ $statusFilter === $k ? 'on' : '' }}" wire:click="setFilter('{{ $k }}')">{{ $label }}</button>
                @endforeach
            </div>
            <label class="sr-only" for="sh-search">Search errors</label>
            <input id="sh-search" type="search" class="sh-search" placeholder="Search message, file or class" wire:model.live.debounce.400ms="search">
            <span style="flex:1"></span>
            <button type="button" class="sh-btn primary" wire:click="exportSelected">
                {{ count($selected) ? 'Export ' . count($selected) . ' for AI (.md)' : 'Export open errors for AI (.md)' }}
            </button>
            @if(count($selected))
                <button type="button" class="sh-btn" wire:click="bulkStatus('fixed')">Mark fixed</button>
                <button type="button" class="sh-btn" wire:click="bulkStatus('ignored')">Ignore</button>
            @endif
        </div>

        @if($groups->isEmpty())
            <div class="sh-empty">
                {{ $statusFilter === 'open' && trim($search) === '' ? 'No open errors. When something breaks, it shows up here within 5 minutes.' : 'No errors match this filter.' }}
            </div>
        @else
            <table class="sh-table">
                <thead>
                    <tr>
                        <th style="width:28px"><span class="sr-only">Select</span></th>
                        <th>Error</th>
                        <th class="sh-hide-sm">Times</th>
                        <th>Last seen</th>
                        <th class="sh-hide-sm">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $g)
                        <tr class="sh-row" wire:key="g-{{ $g->id }}" wire:click="openGroup({{ $g->id }})" tabindex="0" @keydown.enter="$wire.openGroup({{ $g->id }})">
                            <td @click.stop>
                                <input type="checkbox" value="{{ $g->id }}" wire:model.live="selected" aria-label="Select this error">
                            </td>
                            <td>
                                <span class="sh-badge lv-{{ $g->level }}">{{ $g->level }}</span>
                                <div class="sh-msg">{{ \Illuminate\Support\Str::limit(trim($g->message), 160) }}</div>
                                <div class="sh-where">{{ $g->exception_class ? class_basename($g->exception_class) . ' · ' : '' }}{{ $g->file ? $g->file . ($g->line ? ':' . $g->line : '') : $g->source }}</div>
                            </td>
                            <td class="sh-hide-sm">{{ number_format($g->occurrences) }}</td>
                            <td style="white-space:nowrap">{{ \App\Support\Monitor\HealthChecks::ago($g->last_seen_at) }}</td>
                            <td class="sh-hide-sm ss-{{ $g->status }}">{{ ucfirst($g->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if($open)
        @php($g = $open['group'])
        {{-- Teleported to <body> so the HQ top bar and content wrapper cannot clip or cover it --}}
        @teleport('body')
        <div class="sh">
        <div class="sh-drawer-bg" wire:click="closeGroup"></div>
        <aside class="sh-drawer" role="dialog" aria-modal="true" aria-label="Error details" x-data="{ copied: false }" @keydown.escape.window="$wire.closeGroup()">
            <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                <div>
                    <span class="sh-badge lv-{{ $g->level }}">{{ $g->level }}</span>
                    <h2 style="margin-top:8px">{{ trim($g->message) }}</h2>
                    <div class="sh-where">{{ $g->exception_class }}</div>
                </div>
                <button type="button" class="sh-btn" wire:click="closeGroup" aria-label="Close">Close</button>
            </div>

            <div class="sh-section" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                <button type="button" class="sh-btn primary"
                        @click="navigator.clipboard.writeText($refs.brief.value).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                    <span x-show="!copied">Copy for AI</span><span x-show="copied" x-cloak>Copied</span>
                </button>
                @foreach(['new' => 'New', 'investigating' => 'Investigating', 'fixed' => 'Fixed', 'ignored' => 'Ignore'] as $k => $label)
                    <button type="button" class="sh-btn" style="{{ $g->status === $k ? 'border-color: var(--sh-teal); color: var(--sh-teal);' : '' }}"
                            wire:click="setStatus({{ $g->id }}, '{{ $k }}')" aria-pressed="{{ $g->status === $k ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
                <textarea x-ref="brief" class="sr-only" aria-hidden="true" tabindex="-1" readonly>{{ $open['brief'] }}</textarea>
            </div>

            @if($open['hint'])
                <div class="sh-section">
                    <h4>What it means</h4>
                    <div class="sh-hint">{{ $open['hint'] }}</div>
                </div>
            @endif

            <div class="sh-section">
                <h4>Where and how often</h4>
                <p style="margin:0; font-size:.9rem; line-height:1.6">
                    <span class="sh-where" style="font-size:.85rem">{{ $g->file ? $g->file . ($g->line ? ':' . $g->line : '') : 'Location not recorded (' . $g->source . ')' }}</span><br>
                    {{ number_format($g->occurrences) }} time(s). First {{ \Illuminate\Support\Carbon::parse($g->first_seen_at)->tz('Africa/Lagos')->format('j M Y, H:i') }},
                    last {{ \Illuminate\Support\Carbon::parse($g->last_seen_at)->tz('Africa/Lagos')->format('j M Y, H:i') }} (Lagos time).
                </p>
            </div>

            <div class="sh-section">
                <h4>Recent occurrences</h4>
                <ul class="sh-events">
                    @forelse($open['events'] as $ev)
                        <li>{{ \Illuminate\Support\Carbon::parse($ev->occurred_at)->tz('Africa/Lagos')->format('j M H:i:s') }} · {{ trim(($ev->method ?? '') . ' ' . ($ev->url ?? '—')) }}{{ $ev->component ? ' · ' . $ev->component : '' }}{{ $ev->user_id ? ' · signed in' : ' · guest' }}</li>
                    @empty
                        <li>No occurrence details.</li>
                    @endforelse
                </ul>
            </div>

            @if($open['code'])
                <div class="sh-section">
                    <h4>Code around the line</h4>
                    <pre class="sh-pre">{{ $open['code'] }}</pre>
                </div>
            @endif

            @if($g->trace)
                <div class="sh-section" x-data="{ show: false }">
                    <h4>Stack trace <button type="button" class="sh-btn" style="padding:2px 8px; font-size:.72rem; margin-left:6px" @click="show = !show" x-text="show ? 'Hide' : 'Show'"></button></h4>
                    <pre class="sh-pre" x-show="show" x-cloak>{{ $g->trace }}</pre>
                </div>
            @endif

            <div class="sh-section">
                <h4>Notes</h4>
                @if($g->notes)
                    <pre class="sh-pre" style="white-space:pre-wrap; font-family:inherit; font-size:.84rem">{{ trim($g->notes) }}</pre>
                @endif
                <label class="sr-only" for="sh-note">Add a note</label>
                <textarea id="sh-note" class="sh-note" wire:model="noteText" placeholder="What was found or done (shown to the team and included in Copy for AI)"></textarea>
                @error('noteText') <div style="color: var(--sh-bad); font-size:.8rem">{{ $message }}</div> @enderror
                <button type="button" class="sh-btn" style="margin-top:8px" wire:click="addNote">Add note</button>
            </div>
        </aside>
        </div>
        @endteleport
    @endif

    <style>.sh .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; } [x-cloak] { display:none !important; }</style>
</div>
