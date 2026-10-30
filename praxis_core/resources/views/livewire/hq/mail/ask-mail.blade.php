<div class="am">
    @if ($enabled)
        <style>
            /* Mail → Ask about your mail (PR 4). Side panel on desktop, full screen on phones. Brand tokens only. */
            .am-backdrop { position: fixed; inset: 0; z-index: 60; background: rgba(12, 20, 43, 0.4); display: flex; justify-content: flex-end; }
            .am-panel { width: min(480px, 100vw); height: 100%; background: var(--color-paper); border-left: 1px solid var(--color-line); display: flex; flex-direction: column; box-shadow: 0 8px 32px rgba(12, 20, 43, 0.16); }
            .am-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--color-line); }
            .am-head h2 { margin: 0; font-size: 1.0625rem; font-weight: 600; color: var(--color-ink); display: flex; align-items: center; gap: 8px; }
            .am-head h2 svg { width: 18px; height: 18px; color: var(--color-teal); }
            .am-head p { margin: 4px 0 0; }
            .am-close { background: none; border: 0; padding: 6px; border-radius: 6px; color: var(--color-muted); cursor: pointer; display: inline-flex; }
            .am-close:hover { background: var(--color-mist); color: var(--color-ink); }
            .am-close svg { width: 18px; height: 18px; }
            .am-body { flex: 1 1 auto; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 16px; }
            .am-asked { align-self: flex-end; max-width: 90%; background: var(--color-teal-tint); color: var(--color-ink); padding: 8px 12px; border-radius: 12px 12px 2px 12px; font-size: 0.9375rem; overflow-wrap: anywhere; }
            .am-answer { color: var(--color-fg-body); font-size: 0.9375rem; line-height: 1.5rem; }
            .am-answer p { margin: 0 0 8px; }
            .am-answer ul, .am-empty ul { margin: 0 0 8px; padding-left: 18px; list-style: disc; }
            .am-cite { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 4px; margin: 0 1px; border-radius: 4px; background: var(--color-teal-tint); color: var(--color-petrol); font-size: 0.6875rem; font-weight: 700; text-decoration: none; vertical-align: 1px; }
            .am-cite:hover, .am-cite:focus-visible { background: var(--color-petrol); color: var(--color-fg-on-dark); }
            .am-sources h3 { margin: 0 0 6px; font-size: 0.8125rem; font-weight: 600; color: var(--color-muted); }
            .am-sources ol { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
            .am-sources a { display: grid; grid-template-columns: 22px minmax(0, 1fr); gap: 8px; padding: 8px; border: 1px solid var(--color-line); border-radius: var(--radius-control); text-decoration: none; color: inherit; }
            .am-sources a:hover, .am-sources a:focus-visible { border-color: var(--color-teal); background: var(--color-mist); }
            .am-sources .am-n { font-size: 0.75rem; font-weight: 700; color: var(--color-petrol); padding-top: 2px; }
            .am-sources strong { display: block; color: var(--color-ink); font-weight: 600; font-size: 0.875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .am-sources span.help { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .am-empty { color: var(--color-muted); font-size: 0.9375rem; }
            .am-empty ul { margin: 8px 0 0; padding-left: 18px; }
            .am-foot { border-top: 1px solid var(--color-line); padding: 12px 20px 16px; display: flex; flex-direction: column; gap: 8px; }
            .am-foot .textarea { min-height: 64px; resize: vertical; }
            .am-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
            .am-scope { display: flex; align-items: center; gap: 8px; font-size: 0.8125rem; color: var(--color-muted); }
            .am-scope .select { width: auto; padding-top: 4px; padding-bottom: 4px; font-size: 0.8125rem; }
            .am-busy { display: none; align-items: center; gap: 8px; color: var(--color-muted); font-size: 0.875rem; }
            .am-busy svg { width: 16px; height: 16px; animation: am-spin 1s linear infinite; }
            .am-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
            @keyframes am-spin { to { transform: rotate(360deg); } }
            @media (prefers-reduced-motion: reduce) { .am-busy svg { animation: none; } }
            @media (max-width: 640px) {
                .am-panel { width: 100vw; border-left: 0; }
                .am-head, .am-body, .am-foot { padding-left: 16px; padding-right: 16px; }
                .am-open-label { display: none; }
            }
        </style>

        <button type="button" class="btn btn-secondary" wire:click="openPanel" aria-haspopup="dialog" aria-label="Ask about your mail" title="Ask about your mail">
            <i data-lucide="sparkles"></i> <span class="am-open-label">Ask about your mail</span>
        </button>

        @if ($open)
            <div class="am-backdrop" wire:click.self="closePanel" wire:keydown.escape.window="closePanel">
                <div class="am-panel" role="dialog" aria-modal="true" aria-labelledby="am-title"
                     x-data x-init="$nextTick(() => $refs.q?.focus())">
                    <div class="am-head">
                        <div>
                            <h2 id="am-title"><i data-lucide="sparkles"></i> Ask about your mail</h2>
                            <p class="help">Praxis AI answers from the mail you can read in HQ and shows the messages it used.</p>
                        </div>
                        <button type="button" class="am-close" wire:click="closePanel" aria-label="Close" title="Close"><i data-lucide="x"></i></button>
                    </div>

                    <div class="am-body" aria-live="polite">
                        @if ($answerHtml === null && ! $error)
                            <div class="am-empty">
                                <p>Ask in your own words, for example:</p>
                                <ul>
                                    <li>What did Ada say about the Q4 proposal?</li>
                                    <li>Has anyone sent us a purchase order this month?</li>
                                    <li>When is the Lagos workshop?</li>
                                </ul>
                            </div>
                        @endif
                        @if ($asked)
                            <div class="am-asked">{{ $asked }}</div>
                        @endif
                        @if ($answerHtml !== null)
                            <div class="am-answer">{!! $answerHtml !!}</div>
                            @if ($sources)
                                <div class="am-sources">
                                    <h3>Messages used</h3>
                                    <ol>
                                        @foreach ($sources as $s)
                                            <li wire:key="src-{{ $s['n'] }}">
                                                <a href="{{ $s['url'] }}" wire:navigate>
                                                    <span class="am-n">{{ $s['n'] }}</span>
                                                    <span>
                                                        <strong>{{ $s['subject'] }}</strong>
                                                        <span class="help">{{ $s['from'] }}{{ $s['date'] ? ' · ' . \Illuminate\Support\Carbon::parse($s['date'])->timezone('Africa/Lagos')->format('j M Y') : '' }}</span>
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                                <p class="help">Praxis AI can be wrong: open the messages before you act on an answer.</p>
                            @endif
                        @endif
                        @if ($error)
                            <div class="alert alert-warning" role="alert"><i data-lucide="triangle-alert"></i><span>{{ $error }}</span></div>
                        @endif
                        <div class="am-busy" wire:loading.flex wire:target="ask" role="status"><i data-lucide="loader-circle"></i> Reading your mail…</div>
                    </div>

                    <form class="am-foot" wire:submit="ask">
                        <label for="am-q" class="am-hidden">Your question</label>
                        <textarea id="am-q" x-ref="q" class="textarea" wire:model="question" rows="2" maxlength="500"
                                  placeholder="Ask about your mail" x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.ask() }"></textarea>
                        @error('question') <p class="field-error">{{ $message }}</p> @enderror
                        <div class="am-row">
                            @if ($manyBoxes && $box)
                                <div class="am-scope">
                                    <label for="am-scope">Search</label>
                                    <select id="am-scope" class="select" wire:model="scope">
                                        <option value="all">All my mailboxes</option>
                                        <option value="box">{{ $box->email }} only</option>
                                    </select>
                                </div>
                            @else
                                <span class="help">Enter to ask · Shift+Enter for a new line</span>
                            @endif
                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="ask"><i data-lucide="send"></i> Ask</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
