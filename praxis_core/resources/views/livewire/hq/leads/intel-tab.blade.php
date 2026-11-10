{{-- A lead's Intel tab (docs/LEADS.md section 13): App\Livewire\Hq\Leads\IntelTab --}}
@php
    $money = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone('Africa/Lagos')->format('j M Y') : '';
    $kb = fn ($bytes) => $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
    $roleTone = fn ($r) => $r === 'decision_maker' ? 'pill-success' : ($r === 'unknown' ? 'pill-neutral' : 'pill-info');
    $confTone = fn ($c) => $c === 'high' ? 'pill-success' : ($c === 'medium' ? 'pill-warning' : 'pill-neutral');
    $briefRunning = $brief && ! $brief->isFinished();
@endphp
<div class="in" @if ($busy) wire:poll.4s @endif>
    <style>
        .in-head { display: flex; flex-wrap: wrap; gap: 12px 16px; align-items: flex-start; justify-content: space-between; }
        .in-head p { margin: 4px 0 0; max-width: 62ch; color: var(--color-slate); font-size: 0.875rem; }
        .in-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .in-stack { display: flex; flex-direction: column; gap: 16px; margin-top: 16px; }
        .in-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; }
        .in-title { margin: 0; font-size: 1rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .in-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; margin-top: 4px; font-size: 0.8125rem; color: var(--color-muted); }
        .in-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-line); }
        .in-sec { padding: 16px 0; border-top: 1px solid var(--color-line); }
        .in-sec:first-of-type { border-top: 0; }
        .in-sec h3 { margin: 0 0 8px; font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase; color: var(--color-slate); }
        .in-none { margin: 0; color: var(--color-muted); font-size: 0.875rem; }
        .in-dl { display: grid; grid-template-columns: 140px 1fr; gap: 6px 16px; margin: 0; font-size: 0.875rem; }
        .in-dl dt { color: var(--color-muted); }
        .in-dl dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; white-space: pre-line; }
        .in-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; font-size: 0.875rem; }
        .in-list strong { color: var(--color-ink); }
        .in-sub { display: block; color: var(--color-slate); overflow-wrap: anywhere; white-space: pre-line; }
        .in-text { margin: 0; font-size: 0.875rem; color: var(--color-ink); white-space: pre-line; overflow-wrap: anywhere; }
        .in-scroll { overflow-x: auto; }
        .in-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; min-width: 560px; }
        .in-table th { text-align: left; font-weight: 600; color: var(--color-slate); padding: 6px 12px 6px 0; border-bottom: 1px solid var(--color-line); }
        .in-table td { padding: 8px 12px 8px 0; border-bottom: 1px solid var(--color-line); vertical-align: top; overflow-wrap: anywhere; }
        .in-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .in-total { margin: 12px 0 0; font-size: 0.875rem; color: var(--color-ink); }
        .in-total small { display: block; margin-top: 2px; color: var(--color-muted); font-size: 0.8125rem; }
        .in-orig { margin-top: 8px; font-size: 0.8125rem; }
        .in-orig pre { margin: 8px 0 0; padding: 12px; max-height: 320px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; background: var(--color-mist); border-radius: var(--radius-control); font: inherit; }
        .in-prog { height: 4px; border-radius: 2px; background: var(--color-line); overflow: hidden; margin-top: 8px; max-width: 320px; }
        .in-prog > span { display: block; height: 100%; background: var(--color-deep-teal); }
        .in-form { padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-mist); margin-top: 16px; }
        .in-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .in-row-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .in-card-edit { padding: 12px; margin-bottom: 12px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: #fff; }
        .in-card-edit .btn { margin-top: 8px; }
        .in-hint { font-size: 0.8125rem; color: var(--color-muted); margin: 4px 0 0; }
        @media (max-width: 700px) {
            .in-dl { grid-template-columns: 1fr; gap: 2px; }
            .in-dl dd { margin-bottom: 8px; }
            .in-row, .in-row-3 { grid-template-columns: 1fr; }
            .in-actions .btn { flex: 1 1 100%; }
        }
    </style>

    @if ($notice)
        <div class="alert alert-success mb-6" role="status" wire:key="in-notice"><i data-lucide="circle-check"></i><span>{{ $notice }}</span></div>
    @endif
    @if ($warning)
        <div class="alert alert-warning mb-6" role="alert" wire:key="in-warning"><i data-lucide="triangle-alert"></i><span>{{ $warning }}</span></div>
    @endif

    <section class="card" aria-labelledby="in-title">
        <div class="in-head">
            <div style="min-width: 0">
                <h2 class="card-title" id="in-title">Intel reports</h2>
                <p>Research on this company: the tools it uses and what they cost, who decides, what hurts. Upload a file or paste notes and Claude puts them into one layout. Check it against the original, then proposals, the consultation line and the pitch brief use it. Only people who can edit leads see this tab, and nothing here reaches the client.</p>
            </div>
            <div class="in-actions">
                <button type="button" class="btn btn-primary" wire:click="openForm('upload')"><i data-lucide="upload"></i> Upload a report</button>
                <button type="button" class="btn btn-secondary" wire:click="openForm('paste')"><i data-lucide="pencil"></i> Paste or type notes</button>
            </div>
        </div>

        @if ($form === 'upload')
            <form class="in-form" wire:submit="saveUpload" aria-label="Upload an intel report">
                <div class="field">
                    <label for="in-file" class="label">Report file</label>
                    <input id="in-file" type="file" class="input" wire:model="file" accept=".pdf,.docx,.txt,.md" @error('file') aria-invalid="true" aria-describedby="in-file-error" @enderror>
                    <span class="in-hint">PDF, Word (.docx), text or Markdown, up to {{ $maxMb }} MB.</span>
                    <span class="in-hint" wire:loading wire:target="file">Uploading…</span>
                    @error('file') <span class="field-error" id="in-file-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="in-title-up" class="label">Title <span class="ld-optional">(optional, the file name is used otherwise)</span></label>
                    <input id="in-title-up" type="text" class="input" wire:model="title" maxlength="200" placeholder="For example: Findings, first round">
                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="in-actions">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveUpload,file">
                        <span wire:loading.remove wire:target="saveUpload">Upload and read it</span><span wire:loading wire:target="saveUpload">Uploading…</span>
                    </button>
                    <button type="button" class="btn btn-ghost" wire:click="closeForm">Cancel</button>
                </div>
            </form>
        @elseif ($form === 'paste')
            <form class="in-form" wire:submit="savePaste" aria-label="Paste intel notes">
                <div class="field">
                    <label for="in-paste" class="label">Notes</label>
                    <textarea id="in-paste" class="textarea" rows="10" wire:model="pasteText" maxlength="{{ \App\Support\Leads\LeadIntel::MAX_TEXT * 2 }}" @error('pasteText') aria-invalid="true" aria-describedby="in-paste-error" @enderror></textarea>
                    <span class="in-hint">Anything you found: tools, costs, people, problems, how to approach them. Rough notes are fine.</span>
                    @error('pasteText') <span class="field-error" id="in-paste-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="in-title-paste" class="label">Title <span class="ld-optional">(optional)</span></label>
                    <input id="in-title-paste" type="text" class="input" wire:model="title" maxlength="200" placeholder="For example: Notes from the interview">
                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="in-actions">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="savePaste">
                        <span wire:loading.remove wire:target="savePaste">Add and read it</span><span wire:loading wire:target="savePaste">Adding…</span>
                    </button>
                    <button type="button" class="btn btn-ghost" wire:click="closeForm">Cancel</button>
                </div>
            </form>
        @endif
    </section>

    @if ($reports->isEmpty() && $form === '')
        <div class="card empty-state" style="margin-top: 16px">
            <strong>No intel on this lead yet</strong>
            Upload what you found, or paste your notes, and Claude lays it out for you and the AI.
        </div>
    @endif

    <div class="in-stack">
        @foreach ($reports as $x)
            @php
                $r = $x->report;
                $st = $x->state;
                $s = $r->structured;
                $isOpen = $open === $r->id;
                $isEditing = $editing === $r->id;
            @endphp
            <article class="card" wire:key="in-r-{{ $r->id }}" aria-labelledby="in-t-{{ $r->id }}">
                <div class="in-top">
                    <div style="min-width: 0">
                        <h3 class="in-title" id="in-t-{{ $r->id }}">{{ $r->title }}</h3>
                        <div class="in-meta">
                            <span>Added {{ $day($r->created_at) }}@if ($r->author) by {{ trim($r->author->first_name . ' ' . $r->author->last_name) }}@endif</span>
                            @if ($r->input_kind === 'file')
                                <span><i data-lucide="file-text"></i> {{ $r->original_name }} · {{ $kb($r->size) }}</span>
                            @else
                                <span><i data-lucide="pencil"></i> Typed or pasted</span>
                            @endif
                            @if ($st['state'] === 'reading')
                                <span class="pill pill-info">Claude is reading it…</span>
                            @elseif ($st['state'] === 'failed')
                                <span class="pill pill-danger">Could not be read</span>
                            @elseif ($st['state'] === 'none')
                                <span class="pill pill-neutral">Not read yet</span>
                            @endif
                            <span @class(['pill', 'pill-success' => $r->use_for_ai, 'pill-neutral' => ! $r->use_for_ai])>{{ $r->use_for_ai ? 'The AI reads this' : 'The AI ignores this' }}</span>
                        </div>
                        @if ($st['state'] === 'reading')
                            <div class="in-prog" role="progressbar" aria-label="Claude is reading the report" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $st['progress'] }}"><span style="width: {{ max(5, $st['progress']) }}%"></span></div>
                        @endif
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="toggle('{{ $r->id }}')" aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
                        {{ $isOpen ? 'Hide' : 'Show' }}
                    </button>
                </div>

                @if ($st['state'] === 'failed')
                    <div class="alert alert-warning" style="margin-top: 12px" role="alert"><i data-lucide="triangle-alert"></i><span>{{ $st['message'] }} The original is kept. @if ($r->input_kind === 'text') It is shown below. @endif</span></div>
                @endif

                @if ($isOpen)
                    @if ($isEditing)
                        <form wire:submit="saveEdit" style="margin-top: 16px" aria-label="Edit the report">
                            <div class="field">
                                <label for="in-e-title" class="label">Title</label>
                                <input id="in-e-title" type="text" class="input" wire:model="edit.title" maxlength="200" @error('edit.title') aria-invalid="true" @enderror>
                                @error('edit.title') <span class="field-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="in-sec">
                                <h3>1 · Snapshot</h3>
                                <div class="field"><label for="in-e-sum" class="label">What they do</label><textarea id="in-e-sum" class="textarea" rows="3" wire:model="edit.snapshot.summary"></textarea></div>
                                <div class="in-row">
                                    <div class="field"><label for="in-e-size" class="label">Size</label><input id="in-e-size" type="text" class="input" wire:model="edit.snapshot.size"></div>
                                    <div class="field"><label for="in-e-loc" class="label">Where</label><input id="in-e-loc" type="text" class="input" wire:model="edit.snapshot.location"></div>
                                </div>
                                <div class="field"><label for="in-e-own" class="label">Owners</label><input id="in-e-own" type="text" class="input" wire:model="edit.snapshot.owners"></div>
                            </div>

                            <div class="in-sec">
                                <h3>2 · Decision makers</h3>
                                @foreach ($edit['decision_makers'] ?? [] as $i => $p)
                                    <div class="in-card-edit" wire:key="in-e-p-{{ $i }}">
                                        <div class="in-row">
                                            <div class="field"><label for="in-e-pn-{{ $i }}" class="label">Name</label><input id="in-e-pn-{{ $i }}" type="text" class="input" wire:model="edit.decision_makers.{{ $i }}.name"></div>
                                            <div class="field"><label for="in-e-pr-{{ $i }}" class="label">Job title</label><input id="in-e-pr-{{ $i }}" type="text" class="input" wire:model="edit.decision_makers.{{ $i }}.role"></div>
                                        </div>
                                        <div class="in-row">
                                            <div class="field"><label for="in-e-pd-{{ $i }}" class="label">Role in the decision</label>
                                                <select id="in-e-pd-{{ $i }}" class="select" wire:model="edit.decision_makers.{{ $i }}.deal_role">
                                                    @foreach ($dealRoles as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                                                </select></div>
                                            <div class="field"><label for="in-e-pc-{{ $i }}" class="label">Business email or phone</label><input id="in-e-pc-{{ $i }}" type="text" class="input" wire:model="edit.decision_makers.{{ $i }}.contact"></div>
                                        </div>
                                        <div class="field"><label for="in-e-pa-{{ $i }}" class="label">How to approach them</label><textarea id="in-e-pa-{{ $i }}" class="textarea" rows="2" wire:model="edit.decision_makers.{{ $i }}.approach"></textarea></div>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="removeRow('decision_makers', {{ $i }})" aria-label="Remove this person"><i data-lucide="trash-2"></i> Remove person</button>
                                    </div>
                                @endforeach
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="addRow('decision_makers')"><i data-lucide="plus"></i> Add a person</button>
                            </div>

                            <div class="in-sec">
                                <h3>3 · Tools they use today</h3>
                                <div class="field" style="max-width: 220px">
                                    <label for="in-e-cur" class="label">Currency of the costs <span class="ld-optional">(three letters)</span></label>
                                    <input id="in-e-cur" type="text" class="input" wire:model="edit.tools.currency" maxlength="3" placeholder="NGN" @error('edit.tools.currency') aria-invalid="true" @enderror>
                                    @error('edit.tools.currency') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                @foreach ($edit['tools']['items'] ?? [] as $i => $t)
                                    <div class="in-card-edit" wire:key="in-e-t-{{ $i }}">
                                        <div class="in-row">
                                            <div class="field"><label for="in-e-tn-{{ $i }}" class="label">Tool</label><input id="in-e-tn-{{ $i }}" type="text" class="input" wire:model="edit.tools.items.{{ $i }}.tool"></div>
                                            <div class="field"><label for="in-e-tu-{{ $i }}" class="label">Used for</label><input id="in-e-tu-{{ $i }}" type="text" class="input" wire:model="edit.tools.items.{{ $i }}.used_for"></div>
                                        </div>
                                        <div class="in-row in-row-3">
                                            <div class="field"><label for="in-e-tb-{{ $i }}" class="label">Cost is</label>
                                                <select id="in-e-tb-{{ $i }}" class="select" wire:model="edit.tools.items.{{ $i }}.cost_basis">
                                                    @foreach ($costBases as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                                                </select></div>
                                            <div class="field"><label for="in-e-ta-{{ $i }}" class="label">Amount</label>
                                                <input id="in-e-ta-{{ $i }}" type="text" inputmode="decimal" class="input" wire:model="edit.tools.items.{{ $i }}.cost_amount" @error("edit.tools.items.$i.cost_amount") aria-invalid="true" @enderror>
                                                @error("edit.tools.items.$i.cost_amount") <span class="field-error">{{ $message }}</span> @enderror</div>
                                            <div class="field"><label for="in-e-tp-{{ $i }}" class="label">Paid</label>
                                                <select id="in-e-tp-{{ $i }}" class="select" wire:model="edit.tools.items.{{ $i }}.cost_period">
                                                    <option value="month">a month</option><option value="year">a year</option>
                                                </select></div>
                                        </div>
                                        <div class="field"><label for="in-e-tx-{{ $i }}" class="label">Note <span class="ld-optional">(optional)</span></label><input id="in-e-tx-{{ $i }}" type="text" class="input" wire:model="edit.tools.items.{{ $i }}.note"></div>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="removeRow('tools', {{ $i }})" aria-label="Remove this tool"><i data-lucide="trash-2"></i> Remove tool</button>
                                    </div>
                                @endforeach
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="addRow('tools')"><i data-lucide="plus"></i> Add a tool</button>
                                <p class="in-hint">A cost without an amount counts as not known. Free tools have no amount. Only stated and estimated amounts are added up.</p>
                            </div>

                            <div class="in-sec">
                                <h3>4 · Pain points and signals</h3>
                                @foreach ($edit['pain_points'] ?? [] as $i => $p)
                                    <div class="in-card-edit" wire:key="in-e-k-{{ $i }}">
                                        <div class="field"><label for="in-e-kp-{{ $i }}" class="label">Pain point</label><input id="in-e-kp-{{ $i }}" type="text" class="input" wire:model="edit.pain_points.{{ $i }}.point"></div>
                                        <div class="field"><label for="in-e-kd-{{ $i }}" class="label">Detail</label><textarea id="in-e-kd-{{ $i }}" class="textarea" rows="2" wire:model="edit.pain_points.{{ $i }}.detail"></textarea></div>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="removeRow('pain_points', {{ $i }})" aria-label="Remove this pain point"><i data-lucide="trash-2"></i> Remove pain point</button>
                                    </div>
                                @endforeach
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="addRow('pain_points')"><i data-lucide="plus"></i> Add a pain point</button>
                            </div>

                            <div class="in-sec">
                                <h3>5 · How we can help</h3>
                                <div class="field"><label for="in-e-help" class="label" style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">How we can help</label><textarea id="in-e-help" class="textarea" rows="4" wire:model="edit.how_we_can_help"></textarea></div>
                            </div>
                            <div class="in-sec">
                                <h3>6 · Meeting approach</h3>
                                <div class="field"><label for="in-e-appr" class="label" style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Meeting approach</label><textarea id="in-e-appr" class="textarea" rows="3" wire:model="edit.meeting_approach"></textarea></div>
                            </div>
                            <div class="in-sec">
                                <h3>7 · Source and confidence</h3>
                                <div class="field"><label for="in-e-how" class="label">How the information was obtained</label><textarea id="in-e-how" class="textarea" rows="2" wire:model="edit.source.how"></textarea></div>
                                <div class="in-row">
                                    <div class="field"><label for="in-e-conf" class="label">Confidence</label>
                                        <select id="in-e-conf" class="select" wire:model="edit.source.confidence">
                                            @foreach ($confidence as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                                        </select></div>
                                    <div class="field"><label for="in-e-snote" class="label">Gaps or doubts</label><input id="in-e-snote" type="text" class="input" wire:model="edit.source.note"></div>
                                </div>
                            </div>
                            <div class="in-sec">
                                <h3>8 · Open questions</h3>
                                <div class="field"><label for="in-e-oq" class="label" style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Open questions, one per line</label><textarea id="in-e-oq" class="textarea" rows="4" wire:model="edit.open_questions"></textarea>
                                    <span class="in-hint">One question per line.</span></div>
                            </div>
                            <div class="in-sec">
                                <h3>Other findings</h3>
                                <div class="field"><label for="in-e-oth" class="label" style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Other findings</label><textarea id="in-e-oth" class="textarea" rows="3" wire:model="edit.other_findings"></textarea></div>
                            </div>

                            <div class="in-actions">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveEdit">
                                    <span wire:loading.remove wire:target="saveEdit">Save changes</span><span wire:loading wire:target="saveEdit">Saving…</span>
                                </button>
                                <button type="button" class="btn btn-ghost" wire:click="cancelEdit">Cancel</button>
                            </div>
                        </form>
                    @elseif ($s)
                        <p class="in-hint" style="margin-top: 12px">Claude’s reading: check it against the original before you rely on it. Edit anything that is wrong.</p>

                        <div style="margin-top: 8px">
                            <div class="in-sec">
                                <h3>1 · Snapshot</h3>
                                @if (collect($s['snapshot'])->filter()->isEmpty())
                                    <p class="in-none">Not in the report.</p>
                                @else
                                    <dl class="in-dl">
                                        @foreach (['summary' => 'What they do', 'size' => 'Size', 'location' => 'Where', 'owners' => 'Owners'] as $key => $label)
                                            @if ($s['snapshot'][$key] !== '') <dt>{{ $label }}</dt><dd>{{ $s['snapshot'][$key] }}</dd> @endif
                                        @endforeach
                                    </dl>
                                @endif
                            </div>

                            <div class="in-sec">
                                <h3>2 · Decision makers</h3>
                                @forelse ($s['decision_makers'] as $p)
                                    @if ($loop->first)<ul class="in-list">@endif
                                        <li>
                                            <strong>{{ $p['name'] ?: 'Name not given' }}</strong>@if ($p['role'] !== '') · {{ $p['role'] }}@endif
                                            <span class="pill {{ $roleTone($p['deal_role']) }}" style="margin-left: 6px">{{ $dealRoles[$p['deal_role']] ?? 'Role not known' }}</span>
                                            @if ($p['contact'] !== '')<span class="in-sub">{{ $p['contact'] }}</span>@endif
                                            @if ($p['approach'] !== '')<span class="in-sub">How to approach: {{ $p['approach'] }}</span>@endif
                                        </li>
                                    @if ($loop->last)</ul>@endif
                                @empty
                                    <p class="in-none">No one named in the report.</p>
                                @endforelse
                            </div>

                            <div class="in-sec">
                                <h3>3 · Tools they use today</h3>
                                @if (empty($s['tools']['items']))
                                    <p class="in-none">No tools named in the report.</p>
                                @else
                                    @php $cur = $s['tools']['currency']; @endphp
                                    <div class="in-scroll">
                                        <table class="in-table">
                                            <thead><tr><th scope="col">Tool</th><th scope="col">Used for</th><th scope="col" class="num">Cost{{ $cur !== '' ? ' (' . $cur . ')' : '' }}</th><th scope="col">Basis</th></tr></thead>
                                            <tbody>
                                                @foreach ($s['tools']['items'] as $t)
                                                    <tr>
                                                        <td><strong>{{ $t['tool'] }}</strong>@if ($t['note'] !== '')<span class="in-sub">{{ $t['note'] }}</span>@endif</td>
                                                        <td>{{ $t['used_for'] ?: '—' }}</td>
                                                        <td class="num">
                                                            @if ($t['cost_basis'] === 'free') Free
                                                            @elseif ($t['cost_basis'] === 'unknown') —
                                                            @else {{ $money($t['cost_amount']) }} a {{ $t['cost_period'] }}
                                                            @endif
                                                        </td>
                                                        <td><span @class(['pill', 'pill-success' => $t['cost_basis'] === 'stated', 'pill-warning' => $t['cost_basis'] === 'estimated', 'pill-neutral' => in_array($t['cost_basis'], ['free', 'unknown'], true)])>{{ $costBases[$t['cost_basis']] }}</span></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if ($x->totals && $x->totals['counted'] > 0 && $x->totals['currency'] !== '')
                                        <p class="in-total">
                                            <strong>About {{ $x->totals['currency'] }} {{ number_format($x->totals['monthly']) }} a month, {{ number_format($x->totals['yearly']) }} a year</strong>
                                            for the {{ $x->totals['counted'] }} {{ \Illuminate\Support\Str::plural('tool', $x->totals['counted']) }} with a price{{ $x->totals['estimate'] ? ' (partly estimated)' : '' }}.
                                            <small>
                                                @if ($x->totals['free'] > 0){{ $x->totals['free'] }} {{ $x->totals['free'] === 1 ? 'is' : 'are' }} free. @endif
                                                @if ($x->totals['unknown'] > 0){{ $x->totals['unknown'] }} {{ $x->totals['unknown'] === 1 ? 'has' : 'have' }} no price, so the real bill is higher. @endif
                                            </small>
                                        </p>
                                    @endif
                                @endif
                            </div>

                            <div class="in-sec">
                                <h3>4 · Pain points and signals</h3>
                                @forelse ($s['pain_points'] as $p)
                                    @if ($loop->first)<ul class="in-list">@endif
                                        <li><strong>{{ $p['point'] }}</strong>@if ($p['detail'] !== '')<span class="in-sub">{{ $p['detail'] }}</span>@endif</li>
                                    @if ($loop->last)</ul>@endif
                                @empty
                                    <p class="in-none">None named in the report.</p>
                                @endforelse
                            </div>

                            <div class="in-sec">
                                <h3>5 · How we can help</h3>
                                @if ($s['how_we_can_help'] !== '')<p class="in-text">{{ $s['how_we_can_help'] }}</p>@else<p class="in-none">The report gives no basis yet.</p>@endif
                            </div>
                            <div class="in-sec">
                                <h3>6 · Meeting approach</h3>
                                @if ($s['meeting_approach'] !== '')<p class="in-text">{{ $s['meeting_approach'] }}</p>@else<p class="in-none">Not in the report.</p>@endif
                            </div>
                            <div class="in-sec">
                                <h3>7 · Source and confidence</h3>
                                @if ($s['source']['how'] === '' && $s['source']['note'] === '')
                                    <p class="in-none">The report does not say where the information came from.</p>
                                @else
                                    <p class="in-text">{{ $s['source']['how'] }} <span class="pill {{ $confTone($s['source']['confidence']) }}" style="margin-left: 6px">{{ $confidence[$s['source']['confidence']] }} confidence</span></p>
                                    @if ($s['source']['note'] !== '')<p class="in-hint">{{ $s['source']['note'] }}</p>@endif
                                @endif
                            </div>
                            <div class="in-sec">
                                <h3>8 · Open questions</h3>
                                @forelse ($s['open_questions'] as $q)
                                    @if ($loop->first)<ul class="in-list" style="gap: 6px; list-style: disc; padding-left: 20px">@endif
                                        <li>{{ $q }}</li>
                                    @if ($loop->last)</ul>@endif
                                @empty
                                    <p class="in-none">None listed.</p>
                                @endforelse
                            </div>
                            @if ($s['other_findings'] !== '')
                                <div class="in-sec"><h3>Other findings</h3><p class="in-text">{{ $s['other_findings'] }}</p></div>
                            @endif
                        </div>
                    @elseif ($st['state'] === 'reading')
                        <p class="in-none" style="margin-top: 12px">Claude is reading the report. The layout appears here in a minute or two.</p>
                    @endif

                    @if ($r->input_kind === 'text' && ! $isEditing)
                        <details class="in-orig"><summary>Show the original text</summary><pre>{{ $r->raw_text }}</pre></details>
                    @endif

                    @if (! $isEditing)
                        <div class="in-bar">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="startEdit('{{ $r->id }}')"><i data-lucide="pencil"></i> {{ $s ? 'Edit the layout' : 'Fill the layout by hand' }}</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="readAgain('{{ $r->id }}')" wire:loading.attr="disabled" wire:target="readAgain('{{ $r->id }}')"
                                    @if ($s) wire:confirm="Read it again? Claude’s new layout replaces the one below, including any edits you made." @endif
                                    @disabled($st['state'] === 'reading')><i data-lucide="refresh-cw"></i> Read it again</button>
                            @if ($r->input_kind === 'file')
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="download('{{ $r->id }}')"><i data-lucide="download"></i> Download the original</button>
                            @endif
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="toggleAi('{{ $r->id }}')"><i data-lucide="{{ $r->use_for_ai ? 'eye-off' : 'eye' }}"></i> {{ $r->use_for_ai ? 'Stop the AI reading it' : 'Let the AI read it' }}</button>
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="remove('{{ $r->id }}')"
                                    wire:confirm="Remove this report and its file? This cannot be undone. The lead’s activity keeps a line saying one was removed."><i data-lucide="trash-2"></i> Remove report</button>
                        </div>
                    @endif
                @endif
            </article>
        @endforeach
    </div>

    <section class="card" style="margin-top: 16px" aria-labelledby="in-brief-title">
        <div class="in-head">
            <div style="min-width: 0">
                <h2 class="card-title" id="in-brief-title">Pitch brief</h2>
                <p>Talking points, demo ideas, questions to ask and what to avoid, written from this lead’s profile, contacts and the reports the AI may read. Internal: never send it to the client.</p>
            </div>
            <div class="in-actions">
                <button type="button" class="btn btn-secondary" wire:click="makeBrief" wire:loading.attr="disabled" wire:target="makeBrief" @disabled($briefRunning)>
                    <i data-lucide="sparkles"></i> {{ $brief && $brief->succeeded() ? 'Write a new brief' : 'Write the pitch brief' }}
                </button>
            </div>
        </div>

        @if ($briefRunning)
            <p class="in-none" style="margin-top: 12px">Claude is writing the brief. It shows here in a minute or two.</p>
        @elseif ($brief && $brief->status === 'failed')
            <div class="alert alert-warning" style="margin-top: 12px" role="alert"><i data-lucide="triangle-alert"></i><span>{{ $brief->errorMessage() }}</span></div>
        @elseif ($brief && $brief->succeeded())
            @php $b = (array) $brief->output; @endphp
            <div style="margin-top: 12px">
                <p class="in-hint">Written {{ $day($brief->finished_at ?? $brief->created_at) }}. It does not update by itself: write a new one after the intel changes.</p>
                @if (! empty($b['opening']))
                    <div class="in-sec"><h3>How to open</h3><p class="in-text">{{ $b['opening'] }}</p></div>
                @endif
                @foreach (['talking_points' => 'Talking points', 'demo_ideas' => 'Demo ideas', 'questions_to_ask' => 'Questions to ask', 'avoid' => 'What to avoid'] as $key => $label)
                    <div class="in-sec">
                        <h3>{{ $label }}</h3>
                        @forelse ($b[$key] ?? [] as $item)
                            @if ($loop->first)<ul class="in-list" style="gap: 6px; list-style: disc; padding-left: 20px">@endif
                                <li>{{ $item }}</li>
                            @if ($loop->last)</ul>@endif
                        @empty
                            <p class="in-none">Nothing to add.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
