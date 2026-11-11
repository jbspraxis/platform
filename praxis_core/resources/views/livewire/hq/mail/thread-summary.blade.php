<div class="ms">
    @if ($thread)
        @assets
        <style>
            /* Mail → Summarise card above a conversation (PR 4). Brand tokens only. */
            .ms { margin-bottom: 16px; }
            .ms-card { border: 1px solid var(--color-line); border-left: 3px solid var(--color-teal); border-radius: var(--radius-control); background: var(--color-paper); padding: 12px 16px; }
            .ms-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .ms-head h3 { margin: 0; font-size: 0.875rem; font-weight: 600; color: var(--color-petrol); display: inline-flex; align-items: center; gap: 6px; }
            .ms-head h3 svg { width: 16px; height: 16px; }
            .ms-head .help { margin: 0; }
            .ms-head button.ms-toggle { margin-left: auto; background: none; border: 0; padding: 4px; color: var(--color-muted); cursor: pointer; display: inline-flex; border-radius: 4px; }
            .ms-head button.ms-toggle svg { width: 16px; height: 16px; transition: transform 150ms ease-out; }
            .ms-head button.ms-toggle[aria-expanded="false"] svg { transform: rotate(-90deg); }
            .ms-gist { margin: 8px 0 0; color: var(--color-ink); font-size: 0.9375rem; line-height: 1.5rem; }
            .ms-cols { display: grid; gap: 12px 24px; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); margin-top: 10px; }
            .ms-cols h4 { margin: 0 0 4px; font-size: 0.8125rem; font-weight: 600; color: var(--color-muted); }
            .ms-cols ul { margin: 0; padding-left: 18px; list-style: disc; font-size: 0.875rem; color: var(--color-fg-body); }
            .ms-cols li { margin: 2px 0; }
            .ms-foot { margin: 10px 0 0; font-size: 0.75rem; color: var(--color-muted); }
            .ms-run { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .ms-busy { display: none; align-items: center; gap: 6px; font-size: 0.875rem; color: var(--color-muted); }
            .ms-busy svg { width: 16px; height: 16px; animation: ms-spin 1s linear infinite; }
            @keyframes ms-spin { to { transform: rotate(360deg); } }
            @media (prefers-reduced-motion: reduce) { .ms-busy svg { animation: none; } .ms-head button.ms-toggle svg { transition: none; } }
        </style>
        @endassets

        @if ($summary)
            <section class="ms-card" aria-labelledby="ms-title-{{ $thread->id }}">
                <div class="ms-head">
                    <h3 id="ms-title-{{ $thread->id }}"><i data-lucide="sparkles"></i> Summary</h3>
                    <button type="button" class="ms-toggle" wire:click="toggle" aria-expanded="{{ $collapsed ? 'false' : 'true' }}" aria-controls="ms-body-{{ $thread->id }}" title="{{ $collapsed ? 'Show the summary' : 'Hide the summary' }}" aria-label="{{ $collapsed ? 'Show the summary' : 'Hide the summary' }}">
                        <i data-lucide="chevron-down"></i>
                    </button>
                </div>
                @unless ($collapsed)
                    <div id="ms-body-{{ $thread->id }}">
                        <p class="ms-gist">{{ $summary['gist'] ?? '' }}</p>
                        @if (! empty($summary['key_points']) || ! empty($summary['asked_of_you']))
                            <div class="ms-cols">
                                @if (! empty($summary['key_points']))
                                    <div>
                                        <h4>Key points</h4>
                                        <ul>@foreach ($summary['key_points'] as $p)<li>{{ $p }}</li>@endforeach</ul>
                                    </div>
                                @endif
                                @if (! empty($summary['asked_of_you']))
                                    <div>
                                        <h4>Waiting on us</h4>
                                        <ul>@foreach ($summary['asked_of_you'] as $p)<li>{{ $p }}</li>@endforeach</ul>
                                    </div>
                                @endif
                            </div>
                        @endif
                        <p class="ms-foot">Written by Praxis AI from this conversation{{ $thread->ai_summary_at ? ', ' . $thread->ai_summary_at->timezone('Africa/Lagos')->format('j M, H:i') : '' }}. Check the messages before you act on it. A new message clears it.</p>
                    </div>
                @endunless
            </section>
        @else
            <div class="ms-run">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="summarise" wire:loading.remove wire:target="summarise"><i data-lucide="sparkles"></i> Summarise</button>
                <span class="ms-busy" wire:loading.flex wire:target="summarise" role="status"><i data-lucide="loader-circle"></i> Praxis AI is reading the conversation…</span>
                @if ($error)
                    <span class="help" role="alert">{{ $error }}</span>
                @endif
            </div>
        @endif
    @endif
</div>
