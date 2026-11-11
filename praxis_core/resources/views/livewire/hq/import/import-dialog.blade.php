<div>
    @php
        [$one, $many] = $definition->noun();
        [$addOne, $addMany] = $definition->addedNoun();
        $plural = fn (int $n, string $a, string $b) => number_format($n) . ' ' . ($n === 1 ? $a : $b);
        $id = 'import-' . $definition->key();
        $statusPill = [
            'ready' => ['pill-success', 'Ready'], 'imported' => ['pill-success', 'Imported'], 'fix' => ['pill-danger', 'Needs fixing'],
            'exists' => ['pill-neutral', 'Already exists'], 'add' => ['pill-info', 'Already exists'], 'added' => ['pill-info', 'Added to it'],
        ];
    @endphp

    <button type="button" class="btn btn-secondary" wire:click="show"><i data-lucide="upload"></i> {{ $definition->title() }}</button>

    @if ($open)
        @assets
        <style>
            .im { max-width: 560px; }
            .im.is-wide { max-width: 1120px; }
            .im-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
            .im-steps { list-style: none; margin: 16px 0 0; padding: 0; display: flex; flex-direction: column; gap: 20px; }
            .im-steps li { display: grid; grid-template-columns: 28px 1fr; gap: 12px; }
            .im-num { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: var(--color-teal-tint); color: var(--color-petrol); font-weight: 600; font-size: 0.875rem; }
            .im-steps h3 { margin: 2px 0 4px; font-size: 0.9375rem; font-weight: 600; color: var(--color-ink); }
            .im-steps p { margin: 0 0 10px; font-size: 0.875rem; color: var(--color-slate); }
            .im-drop { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 20px 16px; border: 1px dashed var(--color-line-strong); border-radius: var(--radius-card); background: var(--color-mist); text-align: center; cursor: pointer; font-size: 0.875rem; color: var(--color-slate); }
            .im-drop:hover, .im-drop:focus-within { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
            .im-drop svg { width: 22px; height: 22px; color: var(--color-deep-teal); }
            .im-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
            .im-drop strong { color: var(--color-ink); }
            .im-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 4px 0 16px; font-size: 0.875rem; color: var(--color-slate); }
            .im-summary strong { color: var(--color-ink); overflow-wrap: anywhere; }
            .im-table { max-height: min(48vh, 520px); overflow: auto; border: 1px solid var(--color-line); border-radius: var(--radius-card); margin-top: 12px; }
            .im-table .table td, .im-table .table th { font-size: 0.8125rem; padding: 8px 10px; }
            .im-table .table td { white-space: nowrap; }
            .im-table td.im-why { white-space: normal; min-width: 260px; max-width: 360px; }
            .im-table td.im-red { background: var(--color-danger-tint); box-shadow: inset 0 0 0 1px var(--color-danger); color: var(--color-danger); font-weight: 600; }
            .im-table td.im-red:empty::after { content: 'empty'; font-weight: 400; font-style: italic; }
            .im-why ul { margin: 0; padding-left: 16px; color: var(--color-danger); }
            .im-note { color: var(--color-slate); }
            .im-options { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--color-line); }
            .im-optional { font-weight: 400; color: var(--color-muted); }
            .im-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; margin-top: 20px; }
            @media (max-width: 600px) {
                .im-actions .btn { flex: 1 1 100%; }
                .im-table { max-height: none; }
                .im-table td.im-why { min-width: 200px; }
            }
        </style>
        @endassets

        <div class="modal-backdrop" style="z-index: 60" wire:keydown.escape.window="close">
            <div @class(['modal', 'im', 'is-wide' => (bool) $analysis]) role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
                <div class="im-head">
                    <h2 id="{{ $id }}-title" class="modal-title">{{ $definition->title() }}</h2>
                    <button type="button" wire:click="close" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                @if (! $analysis)
                    {{-- Step 1: the template, then the file --}}
                    <ol class="im-steps">
                        <li>
                            <span class="im-num" aria-hidden="true">1</span>
                            <div>
                                <h3>Download the template</h3>
                                <p>Its first sheet explains each column and marks the required ones. Fill in the second sheet, ‘{{ $definition->sheetName() }}’.</p>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="downloadTemplate" wire:loading.attr="disabled" wire:target="downloadTemplate">
                                    <i data-lucide="download"></i> Download the template
                                </button>
                            </div>
                        </li>
                        <li>
                            <span class="im-num" aria-hidden="true">2</span>
                            <div>
                                <h3>Upload it here</h3>
                                <p>An Excel workbook (.xlsx) or a CSV file, up to {{ number_format(\App\Support\Import\Importer::MAX_ROWS) }} rows. You’ll see every row before anything is imported.</p>
                                <label class="im-drop" for="{{ $id }}-file" wire:loading.remove wire:target="file">
                                    <i data-lucide="file-spreadsheet"></i>
                                    <strong>Choose a file</strong>
                                    <span>.xlsx or .csv, 5 MB at most</span>
                                    <input id="{{ $id }}-file" type="file" wire:model="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                                           @error('file') aria-invalid="true" aria-describedby="{{ $id }}-file-error" @enderror>
                                </label>
                                <div class="im-drop" wire:loading.flex wire:target="file" aria-live="polite">
                                    <strong>Reading the file…</strong>
                                    <span>Checking every row against what is already in HQ.</span>
                                </div>
                                @error('file') <span class="field-error" id="{{ $id }}-file-error" role="alert">{{ $message }}</span> @enderror
                            </div>
                        </li>
                    </ol>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="close">Cancel</button>
                    </div>
                @else
                    {{-- Step 2 (the preview) and step 3 (done) --}}
                    @if ($done)
                        @php $r = $analysis->result; @endphp
                        <div class="alert alert-success" role="status">
                            <i data-lucide="circle-check"></i>
                            <div>
                                <strong>{{ $r['created'] ? $plural($r['created'], $one, $many) . ' added' : 'No new ' . $many . ' added' }}.</strong>
                                @if ($r['added'])
                                    {{ ucfirst($plural($r['added'], $addOne, $addMany)) }} added to {{ $plural($r['addedTo'], $one, $many) }} already in HQ.
                                @endif
                            </div>
                        </div>
                        @if ($counts['fix'])
                            <div class="alert alert-warning" style="margin-top: 12px">
                                <i data-lucide="triangle-alert"></i>
                                <div>{{ ucfirst($plural($counts['fix'], 'row wasn’t', 'rows weren’t')) }} imported. Download {{ $counts['fix'] === 1 ? 'it' : 'them' }}, fix the red cells, and import that file: rows already imported won’t come in twice.</div>
                            </div>
                        @endif
                    @else
                        <div class="im-summary">
                            <span><strong>{{ $analysis->fileName }}</strong>: {{ $plural($counts['total'], 'row', 'rows') }}.</span>
                            @if ($counts['ready'])
                                <span class="pill pill-success">{{ $plural($counts['ready'], 'row', 'rows') }} ready</span>
                                <span>{{ $plural($counts['records'], 'new ' . $one, 'new ' . $many) }}</span>
                            @endif
                            @if ($counts['exists'])
                                <span class="pill pill-neutral">{{ number_format($counts['exists']) }} already {{ $counts['exists'] === 1 ? 'exists' : 'exist' }}</span>
                                @if ($counts['add']) <span>{{ $plural($counts['add'], 'new ' . $addOne, 'new ' . $addMany) }} to add</span> @endif
                            @endif
                            @if ($counts['fix'])
                                <span class="pill pill-danger">{{ $plural($counts['fix'], 'row needs', 'rows need') }} fixing</span>
                            @endif
                        </div>
                        @if ($analysis->ignored)
                            <p class="help" style="margin: -8px 0 12px">Not read (not in the template): {{ collect($analysis->ignored)->map(fn ($h) => '‘' . $h . '’')->implode(', ') }}.</p>
                        @endif
                    @endif

                    <div class="segmented" role="group" aria-label="Show rows">
                        @foreach (['all' => 'All', 'ready' => $done ? 'Imported' : 'Ready', 'fix' => 'Needs fixing', 'exists' => 'Already exist'] as $key => $label)
                            @php $n = match ($key) { 'all' => $counts['total'], 'ready' => $done ? $counts['imported'] : $counts['ready'], 'fix' => $counts['fix'], default => $counts['exists'] }; @endphp
                            <button type="button" class="segmented-item" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" wire:click="$set('filter', '{{ $key }}')">{{ $label }} ({{ number_format($n) }})</button>
                        @endforeach
                    </div>

                    <div class="im-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">Row</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">What to fix</th>
                                    @foreach ($columns as $c)
                                        <th scope="col">{{ $c->header() }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $line => $row)
                                    <tr wire:key="{{ $id }}-row-{{ $line }}">
                                        <td class="num">{{ $line }}</td>
                                        <td><span class="pill {{ $statusPill[$row['status']][0] }}">{{ $statusPill[$row['status']][1] }}</span></td>
                                        <td class="im-why">
                                            @if ($row['reasons'])
                                                <ul>@foreach ($row['reasons'] as $why)<li>{{ $why }}</li>@endforeach</ul>
                                            @elseif (in_array($row['status'], ['exists', 'add', 'added'], true))
                                                <span class="im-note">
                                                    @if ($row['url'])<a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['note'] }}</a>@else{{ $row['note'] }}@endif:
                                                    {{ match ($row['status']) { 'add' => 'new ' . $addOne . ', added on import', 'added' => $addOne . ' added', default => 'nothing new' } }}
                                                </span>
                                            @endif
                                        </td>
                                        @foreach ($columns as $c)
                                            @php $v = $row['values'][$c->key] ?? ''; @endphp
                                            <td @class(['im-red' => in_array($c->key, $row['red'], true)])>{{ $c->choices[$v] ?? $v }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ count($columns) + 3 }}"><div class="empty-state" style="padding: 16px"><strong>No rows here</strong>Choose another filter above.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($more)
                        <button type="button" class="btn btn-ghost btn-sm" style="margin-top: 8px" wire:click="showMore">Show {{ number_format(min($more, 100)) }} more</button>
                    @endif

                    @if (! $done)
                        {{-- The schema's choices --}}
                        @if ($choices)
                            <div class="im-options">
                                @foreach ($choices as $o)
                                    @php $fid = $id . '-opt-' . $o->key; $err = $errors->first('options.' . $o->key); @endphp
                                    <div class="field" wire:key="{{ $fid }}">
                                        <label class="label" for="{{ $fid }}">{{ $o->label }}@if ($o->optional && $o->type !== 'date') <span class="im-optional">(optional)</span>@endif</label>
                                        @if ($o->type === 'staff')
                                            <select id="{{ $fid }}" class="select" wire:model="options.{{ $o->key }}" @if ($err) aria-invalid="true" @endif>
                                                @if ($o->nullable)<option value="">{{ $o->none }}</option>@endif
                                                @foreach ($staff as $sid => $name)
                                                    <option value="{{ $sid }}">{{ $name }}@if ($sid === auth()->id()) (you)@endif</option>
                                                @endforeach
                                            </select>
                                        @elseif ($o->type === 'choice')
                                            <select id="{{ $fid }}" class="select" wire:model="options.{{ $o->key }}" @if ($err) aria-invalid="true" @endif>
                                                @foreach ($o->choices as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($o->type === 'date')
                                            <input id="{{ $fid }}" type="date" class="input" wire:model="options.{{ $o->key }}" @if ($err) aria-invalid="true" @endif>
                                        @else
                                            <input id="{{ $fid }}" type="text" class="input" wire:model="options.{{ $o->key }}" maxlength="{{ $o->max }}" @if ($err) aria-invalid="true" @endif>
                                        @endif
                                        @if ($err)<span class="field-error">{{ $err }}</span>@elseif ($o->help)<span class="help">{{ $o->help }}</span>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @error('import') <div class="alert alert-danger" style="margin-top: 16px" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div> @enderror
                    @endif

                    <div class="im-actions">
                        <button type="button" class="btn btn-ghost" wire:click="startOver">{{ $done ? 'Import another file' : 'Choose another file' }}</button>
                        @if ($counts['fix'])
                            <button type="button" class="btn btn-secondary" wire:click="downloadFixes" wire:loading.attr="disabled" wire:target="downloadFixes">
                                <i data-lucide="download"></i> Download {{ $plural($counts['fix'], 'row', 'rows') }} to fix
                            </button>
                        @endif
                        @if ($done)
                            <button type="button" class="btn btn-primary" wire:click="close">Done</button>
                        @else
                            <button type="button" class="btn btn-primary" wire:click="import" wire:loading.attr="disabled" wire:target="import" @disabled(! $analysis->hasWork())>
                                <span wire:loading.remove wire:target="import">
                                    @if ($counts['records'])
                                        Import {{ $plural($counts['records'], $one, $many) }}@if ($counts['add']) and {{ $plural($counts['add'], $addOne, $addMany) }}@endif
                                    @elseif ($counts['add'])
                                        Add {{ $plural($counts['add'], $addOne, $addMany) }}
                                    @else
                                        Nothing to import yet
                                    @endif
                                </span>
                                <span wire:loading wire:target="import">Importing…</span>
                            </button>
                        @endif
                    </div>
                    @if (! $done && ! $analysis->hasWork())
                        <p class="help" style="text-align: right; margin: 8px 0 0">Fix the rows in your file (or download them to fix), then choose it again.</p>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
