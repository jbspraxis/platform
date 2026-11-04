<div class="cdp" @if ($reading && $reading['state'] === 'reading') wire:poll.3s="checkReading" @endif>
    <style>
        .cdp-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 0.875rem; color: var(--color-deep-teal); text-decoration: none; }
        .cdp-back svg { width: 16px; height: 16px; }
        .cdp-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .cdp-head h1 { overflow-wrap: anywhere; }
        .cdp-tags { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; font-size: 0.875rem; color: var(--color-slate); }
        .cdp-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .cdp-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 24px; align-items: start; }
        .cdp-facts { display: grid; grid-template-columns: 160px minmax(0, 1fr); gap: 10px 16px; margin: 0; font-size: 0.875rem; }
        .cdp-facts dt { color: var(--color-muted); }
        .cdp-facts dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; white-space: pre-line; }
        .cdp-versions { list-style: none; margin: 0; padding: 0; }
        .cdp-versions li { padding: 12px 0; border-top: 1px solid var(--color-line); font-size: 0.875rem; }
        .cdp-versions li:first-child { border-top: 0; padding-top: 0; }
        .cdp-versions .cdp-file { color: var(--color-muted); overflow-wrap: anywhere; }
        .cdp-versions .cdp-links { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .cdp-form { display: flex; flex-direction: column; gap: 14px; }
        .cdp-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        /* Suggested details */
        .sg-group { margin-top: 20px; }
        .sg-group h3 { margin: 0 0 8px; font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .sg-head, .sg-row { display: grid; grid-template-columns: 36px minmax(150px, 0.9fr) minmax(0, 1fr) minmax(0, 1.3fr); gap: 4px 16px; align-items: start; }
        .sg-head { padding: 0 12px 6px; font-size: 0.75rem; font-weight: 600; color: var(--color-muted); text-transform: none; }
        .sg-row { padding: 12px; border: 1px solid var(--color-line); border-radius: var(--radius-card); margin-bottom: 8px; background: var(--color-paper); }
        .sg-row.is-differs { border-color: var(--color-warning); background: var(--color-warning-tint); }
        .sg-row.is-ticked { border-color: var(--color-deep-teal); }
        .sg-tick { display: flex; align-items: center; justify-content: center; min-height: 40px; }
        .sg-tick input { width: 20px; height: 20px; }
        .sg-label { font-size: 0.875rem; font-weight: 600; color: var(--color-ink); padding-top: 9px; }
        .sg-label .pill { margin-top: 6px; font-weight: 600; }
        .sg-hq { font-size: 0.875rem; color: var(--color-ink); padding-top: 9px; overflow-wrap: anywhere; }
        .cdp-icon-title { display: flex; align-items: center; gap: 8px; }
        .cdp-icon-title svg { width: 18px; height: 18px; flex: none; }
        .sg-hq .sg-empty { color: var(--color-muted); font-style: italic; }
        .sg-k { display: none; font-size: 0.75rem; font-weight: 600; color: var(--color-muted); margin-bottom: 2px; }
        .sg-note { grid-column: 2 / -1; margin: 4px 0 0; font-size: 0.8125rem; color: var(--color-ink); }
        .sg-source { grid-column: 2 / -1; margin: 2px 0 0; font-size: 0.8125rem; color: var(--color-muted); }
        .sg-dl { display: grid; grid-template-columns: 36px minmax(0, 1.4fr) 170px 150px; gap: 8px 12px; align-items: start; }
        .sg-foot { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--color-line); }
        .cdp .modal { max-width: 600px; }
        .cdp-drop { position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 20px 16px; border: 1px dashed var(--color-line-strong); border-radius: var(--radius-card); background: var(--color-mist); text-align: center; cursor: pointer; font-size: 0.875rem; color: var(--color-slate); }
        .cdp-drop:hover, .cdp-drop:focus-within { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .cdp-drop svg { width: 22px; height: 22px; color: var(--color-deep-teal); }
        .cdp-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .cdp-drop strong { color: var(--color-ink); overflow-wrap: anywhere; }
        /* Versions sit beside the panel only on wide screens; the panel follows its own width, not the window's */
        @media (max-width: 1279px) {
            .cdp-grid { grid-template-columns: minmax(0, 1fr); }
        }
        .cdp-suggest { container-type: inline-size; }
        @container (max-width: 640px) {
            .sg-head { display: none; }
            .sg-row { grid-template-columns: 32px minmax(0, 1fr); }
            .sg-label { padding-top: 10px; }
            .sg-hq, .sg-doc { grid-column: 2; padding-top: 4px; }
            .sg-k { display: block; }
            .sg-note, .sg-source { grid-column: 1 / -1; }
            .sg-dl { grid-template-columns: 32px minmax(0, 1fr); }
            .sg-dl .sg-dl-field { grid-column: 2; }
        }
        @media (max-width: 599px) {
            .cdp-facts { grid-template-columns: minmax(0, 1fr); gap: 2px; }
            .cdp-facts dd { margin-bottom: 10px; }
            .cdp-pair { grid-template-columns: 1fr; }
            .cdp-actions .btn { flex: 1 1 auto; }
            .sg-foot .btn { flex: 1 1 auto; }
            .cdp .modal-backdrop { padding: 12px; }
            .cdp .modal { padding: 20px 16px; }
            .cdp .modal-actions { flex-wrap: wrap-reverse; }
            .cdp .modal-actions .btn { flex: 1 1 auto; }
        }
    </style>

    @php
        $day = fn ($d) => $d ? \App\Support\CompanyDocuments\Documents::day($d->format('Y-m-d')) : null;
        $show = fn ($type, $value) => \App\Support\CompanyDocuments\Suggestions::show($type, $value);
        $statePill = [
            'fill' => ['pill-info', 'Fills a gap'],
            'same' => ['pill-success', 'Same as HQ'],
            'differs' => ['pill-warning', 'Different from HQ'],
            'absent' => ['pill-neutral', 'Not in the document'],
        ];
        $types = ['application/pdf' => 'PDF', 'image/jpeg' => 'JPG', 'image/png' => 'PNG', \App\Support\CompanyDocuments\Documents::TYPES['docx'] => 'Word file'];
        $who = fn ($id) => $people[$id] ?? 'a former staff member';
        $at = fn ($t) => $t?->copy()->timezone('Africa/Lagos')->format('j M Y, H:i');
    @endphp

    <a class="cdp-back" href="{{ route('hq.mdm.documents', array_filter(['company' => \App\Support\CompanyDocuments\Companies::keyFor($doc->legal_entity_id)])) }}" wire:navigate>
        <i data-lucide="arrow-left"></i> Company documents
    </a>

    <div class="cdp-head">
        <div style="min-width: 0;">
            <h1 class="page-title">{{ $doc->title }}</h1>
            <div class="cdp-tags">
                <span class="tag">{{ $kinds[$doc->kind] ?? 'Other' }}</span>
                <span>{{ $companyLabel }}</span>
                <span class="pill {{ $status['pill'] }}">{{ $status['label'] }}</span>
            </div>
        </div>
        <div class="cdp-actions">
            @if ($version)
                @if ($version->opensInline())
                    <a class="btn btn-secondary" href="{{ route('hq.mdm.documents.file', [$doc, $version, 'view']) }}" target="_blank" rel="noopener"><i data-lucide="eye"></i> View <span class="sr-only">(opens in a new tab)</span></a>
                @endif
                <a class="btn btn-secondary" href="{{ route('hq.mdm.documents.file', [$doc, $version, 'download']) }}"><i data-lucide="download"></i> Download</a>
            @endif
            @if ($canEdit && ! $doc->isArchived())
                <button type="button" class="btn btn-secondary" wire:click="openReplace"><i data-lucide="upload"></i> Replace file</button>
            @endif
        </div>
    </div>

    @if ($flash)
        <div class="alert alert-success mb-4" role="status"><i data-lucide="circle-check"></i><div>{{ $flash }}</div></div>
    @endif
    @if ($doc->isArchived())
        <div class="alert alert-info mb-4" role="status">
            <i data-lucide="info"></i>
            <div>Archived on {{ $at($doc->archived_at) }} by {{ $who($doc->archived_by) }}. It and all its versions are kept; it rings no bells.
                @if ($canDelete) <button type="button" class="btn btn-ghost btn-sm" wire:click="restoreDocument">Restore document</button> @endif
            </div>
        </div>
    @endif

    <div class="cdp-grid">
        <div class="flex flex-col gap-6" style="min-width: 0;">

            {{-- Suggested details: what the document says next to what HQ holds --}}
            @if ($reading)
                <section class="card cdp-suggest" aria-labelledby="sg-title">
                    <div class="card-header">
                        <h2 class="card-title cdp-icon-title" id="sg-title"><i data-lucide="sparkles"></i> Suggested details</h2>
                        @if ($reading['state'] === 'read' && ! $typing) <span class="help">Read by Claude from version {{ $version->number }}</span> @endif
                    </div>

                    @if ($result)
                        <div class="alert alert-success" role="status">
                            <i data-lucide="circle-check"></i>
                            <div>
                                <strong>Applied.</strong>
                                <ul class="mt-1 list-disc pl-5">@foreach ($result['applied'] as $line) <li>{{ $line }}</li> @endforeach</ul>
                                @if ($result['kept'])
                                    <p style="margin: 8px 0 0;"><strong>HQ kept its own value where you didn’t tick the document’s:</strong></p>
                                    <ul class="mt-1 list-disc pl-5">@foreach ($result['kept'] as $line) <li>{{ $line }}</li> @endforeach</ul>
                                @endif
                            </div>
                        </div>
                    @elseif (! $panelOpen)
                        {{-- Already applied or set aside --}}
                        <p class="muted" style="margin: 0;">
                            @if ($version->applied)
                                Details from version {{ $version->number }} were applied on {{ $at($version->reviewed_at) }} by {{ $who($version->reviewed_by) }}:
                                {{ collect($version->applied)->except('deadlines')->keys()->map(fn ($k) => mb_strtolower(\App\Support\CompanyDocuments\Suggestions::FIELDS[$k][1] ?? $k))
                                    ->merge(collect($version->applied['deadlines'] ?? [])->map(fn ($d) => '‘' . $d['title'] . '’ in the calendar'))->join(', ', ' and ') }}.
                            @else
                                HQ kept its details as they were for version {{ $version->number }} ({{ $at($version->reviewed_at) }}, {{ $who($version->reviewed_by) }}).
                            @endif
                        </p>
                        @if ($canEdit && ! $doc->isArchived())
                            <div style="margin-top: 12px;"><button type="button" class="btn btn-secondary btn-sm" wire:click="startReview">Review the suggestions again</button></div>
                        @endif
                    @elseif ($reading['state'] === 'reading' && ! $typing)
                        <div class="alert alert-info" role="status" aria-live="polite">
                            <i data-lucide="loader-circle"></i>
                            <div>Claude is reading the document. Its suggestions appear here in a minute or so; you can leave this page and come back.</div>
                        </div>
                    @elseif (! $built)
                        {{-- Not read: say why, and offer to type the details --}}
                        <div class="alert alert-warning" role="status">
                            <i data-lucide="circle-alert"></i>
                            <div>
                                <strong>Claude couldn’t read this document.</strong> {{ $reading['message'] ?? 'It hasn’t been read yet.' }}
                                @if ($canEdit) Type the details from the document instead, then apply the ones HQ should keep. @endif
                            </div>
                        </div>
                        @if ($canEdit && ! $doc->isArchived())
                            <div class="flex flex-wrap gap-2" style="margin-top: 12px;">
                                <button type="button" class="btn btn-primary btn-sm" wire:click="typeDetails"><i data-lucide="pencil"></i> Type the details</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="readAgain"><i data-lucide="refresh-cw"></i> Ask Claude to read it again</button>
                            </div>
                        @endif
                    @else
                        <p class="muted" style="margin: 0 0 4px;">
                            @if ($typing)
                                Type what the document says, tick the details HQ should take, then apply them.
                            @else
                                Check each detail against the document. Nothing in HQ changes until you apply. Where the document disagrees with HQ, HQ keeps its own value unless you tick the row.
                            @endif
                        </p>
                        @foreach ($built['notes'] as $note) <p class="help" style="margin: 4px 0 0;">{{ $note }}</p> @endforeach

                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert" style="margin-top: 12px;">
                                <i data-lucide="circle-alert"></i>
                                <div><strong>Nothing was applied.</strong> Fix the following and apply again:
                                    <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
                                </div>
                            </div>
                        @endif

                        @foreach (\App\Support\CompanyDocuments\Suggestions::GROUPS as $group => $groupName)
                            @php $rows = collect($built['rows'])->where('group', $group); @endphp
                            @continue($rows->isEmpty())
                            <div class="sg-group" role="group" aria-labelledby="sg-g-{{ $group }}">
                                <h3 id="sg-g-{{ $group }}">{{ $group === 'entity' ? 'Legal entities: ' . $companyLabel : ($group === 'website' ? 'Website settings (jbspraxis.com)' : 'This document') }}</h3>
                                <div class="sg-head" aria-hidden="true"><span>Apply</span><span>Detail</span><span>In HQ now</span><span>In the document</span></div>
                                @foreach ($rows as $row)
                                    @php
                                        $wk = str_replace('.', '__', $row['key']);
                                        $id = 'sg-' . str_replace(['.', '_'], '-', $row['key']);
                                        $ticked = (bool) ($ticks[$wk] ?? false);
                                        [$pill, $pillText] = $statePill[$typing ? 'absent' : $row['state']];
                                    @endphp
                                    <div class="sg-row is-{{ $typing ? 'typing' : $row['state'] }} {{ $ticked ? 'is-ticked' : '' }}" wire:key="sg-{{ $wk }}">
                                        <div class="sg-tick">
                                            @if ($canEdit && ! $row['locked'])
                                                <input type="checkbox" id="{{ $id }}-tick" class="checkbox" wire:model.live="ticks.{{ $wk }}" aria-label="Apply the document’s {{ mb_strtolower($row['label']) }}">
                                            @else
                                                <i data-lucide="lock" style="width: 16px; height: 16px; color: var(--color-muted);" aria-hidden="true"></i>
                                            @endif
                                        </div>
                                        <div class="sg-label">
                                            {{ $row['label'] }}
                                            @unless ($typing) <div><span class="pill {{ $pill }}">{{ $pillText }}</span></div> @endunless
                                        </div>
                                        <div class="sg-hq">
                                            <span class="sg-k">In HQ now</span>
                                            @if ($row['hq'] !== null && $row['hq'] !== '')
                                                {{ $row['prefix'] ? $row['prefix'] . ' ' : '' }}{{ $show($row['type'], $row['hq']) }}
                                            @else
                                                <span class="sg-empty">{{ $row['key'] === 'website.registration' ? 'Registration in progress' : 'Empty' }}</span>
                                            @endif
                                        </div>
                                        <div class="sg-doc">
                                            <label class="sg-k" for="{{ $id }}">In the document</label>
                                            @if ($canEdit && ! $row['locked'])
                                                @if ($row['type'] === 'textarea')
                                                    <textarea id="{{ $id }}" class="textarea" rows="2" wire:model="values.{{ $wk }}" aria-label="{{ $row['label'] }} in the document" @error('values.' . $wk) aria-invalid="true" @enderror></textarea>
                                                @else
                                                    <input id="{{ $id }}" type="{{ $row['type'] === 'date' ? 'date' : 'text' }}" class="input" wire:model="values.{{ $wk }}" aria-label="{{ $row['label'] }} in the document" @error('values.' . $wk) aria-invalid="true" @enderror>
                                                @endif
                                                @error('values.' . $wk) <span class="field-error">{{ $message }}</span> @enderror
                                            @else
                                                <span style="font-size: 0.875rem;">{{ $show($row['type'], $row['found']) ?: '—' }}</span>
                                            @endif
                                        </div>
                                        @if ($row['locked'])
                                            <p class="sg-note">{{ $row['locked'] }}</p>
                                        @elseif (! $typing && $row['state'] === 'differs')
                                            <p class="sg-note"><strong>The document disagrees with HQ.</strong> HQ keeps “{{ $show($row['type'], $row['hq']) }}” unless you tick this row.</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        @if ($suggestedDeadlines)
                            <div class="sg-group" role="group" aria-labelledby="sg-g-deadlines">
                                <h3 id="sg-g-deadlines">Compliance calendar</h3>
                                @foreach ($suggestedDeadlines as $i => $d)
                                    @php $meta = $built['deadlines'][$i] ?? ['source' => '', 'note' => null]; @endphp
                                    <div class="sg-row {{ ($d['tick'] ?? false) ? 'is-ticked' : '' }}" style="display: block;" wire:key="sg-dl-{{ $i }}">
                                        <div class="sg-dl">
                                            <div class="sg-tick">
                                                @if ($canEdit)
                                                    <input type="checkbox" id="sg-dl-{{ $i }}-tick" class="checkbox" wire:model.live="suggestedDeadlines.{{ $i }}.tick" aria-label="Add ‘{{ $d['title'] }}’ to the calendar">
                                                @endif
                                            </div>
                                            <div class="field sg-dl-field">
                                                <label class="label" for="sg-dl-{{ $i }}-title">What is due</label>
                                                <input id="sg-dl-{{ $i }}-title" type="text" class="input" wire:model="suggestedDeadlines.{{ $i }}.title" maxlength="190" @disabled(! $canEdit)>
                                                @error("deadlines.{$i}.title") <span class="field-error">{{ $message }}</span> @enderror
                                            </div>
                                            <div class="field sg-dl-field">
                                                <label class="label" for="sg-dl-{{ $i }}-due">Due date</label>
                                                <input id="sg-dl-{{ $i }}-due" type="date" class="input" wire:model="suggestedDeadlines.{{ $i }}.due_on" @disabled(! $canEdit)>
                                                @error("deadlines.{$i}.due_on") <span class="field-error">{{ $message }}</span> @enderror
                                            </div>
                                            <div class="field sg-dl-field">
                                                <label class="label" for="sg-dl-{{ $i }}-repeats">Repeats</label>
                                                <select id="sg-dl-{{ $i }}-repeats" class="select" wire:model="suggestedDeadlines.{{ $i }}.repeats" @disabled(! $canEdit)>
                                                    @foreach (\App\Models\CompanyDeadline::REPEATS as $key => $name) <option value="{{ $key }}">{{ $name }}</option> @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        @if ($meta['source']) <p class="sg-source" style="margin-left: 48px;">The document says: “{{ $meta['source'] }}”</p> @endif
                                        @if ($meta['note']) <p class="sg-note" style="margin-left: 48px;">{{ $meta['note'] }}</p> @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($canEdit)
                            <div class="sg-foot">
                                <button type="button" class="btn btn-secondary" wire:click="setAside">Keep HQ as it is</button>
                                <button type="button" class="btn btn-primary" wire:click="applySuggestions" wire:loading.attr="disabled" wire:target="applySuggestions">
                                    <span wire:loading.remove wire:target="applySuggestions">Apply ticked details</span>
                                    <span wire:loading wire:target="applySuggestions">Applying…</span>
                                </button>
                            </div>
                        @else
                            <p class="help" style="margin-top: 16px;"><i data-lucide="lock" style="width: 14px; height: 14px; vertical-align: -2px;"></i> People who can edit Master data compare these with HQ and apply them.</p>
                        @endif
                    @endif
                </section>
            @endif

            {{-- Details --}}
            <section class="card" aria-labelledby="cdp-details-title">
                <div class="card-header">
                    <h2 class="card-title" id="cdp-details-title">Details</h2>
                    @if ($canEdit && ! $editing && ! $doc->isArchived())
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="startEdit"><i data-lucide="pencil"></i> Edit details</button>
                    @endif
                </div>
                @if ($editing)
                    <form wire:submit="saveDetails" class="cdp-form">
                        <div class="cdp-pair">
                            <div class="field">
                                <label class="label" for="cdp-company">Company</label>
                                <select id="cdp-company" class="select" wire:model="form.company">
                                    @foreach ($companies as $c) <option value="{{ $c['key'] }}">{{ $c['label'] }}</option> @endforeach
                                </select>
                                @error('form.company') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="cdp-kind">Kind of document</label>
                                <select id="cdp-kind" class="select" wire:model="form.kind">
                                    @foreach ($kinds as $key => $name) <option value="{{ $key }}">{{ $name }}</option> @endforeach
                                </select>
                                @error('form.kind') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="cdp-title">Title</label>
                            <input id="cdp-title" type="text" class="input" wire:model="form.title" maxlength="190" @error('form.title') aria-invalid="true" @enderror>
                            @error('form.title') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="cdp-pair">
                            <div class="field">
                                <label class="label" for="cdp-issued">Issue date (optional)</label>
                                <input id="cdp-issued" type="date" class="input" wire:model="form.issued_on">
                                @error('form.issued_on') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="cdp-expires">Expiry or renewal date (optional)</label>
                                <input id="cdp-expires" type="date" class="input" wire:model="form.expires_on">
                                @error('form.expires_on') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="cdp-remind">Bell before the expiry date (days, optional)</label>
                            <input id="cdp-remind" type="number" min="1" max="365" class="input" style="max-width: 140px;" wire:model="form.remind_days" aria-describedby="cdp-remind-help">
                            <span id="cdp-remind-help" class="help">Leave it empty for no bell.</span>
                            @error('form.remind_days') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="cdp-notes">Notes (optional)</label>
                            <textarea id="cdp-notes" class="textarea" rows="3" wire:model="form.notes" maxlength="2000"></textarea>
                            @error('form.notes') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="button" class="btn btn-secondary" wire:click="cancelEdit">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save details</button>
                        </div>
                    </form>
                @else
                    <dl class="cdp-facts">
                        <dt>Company</dt><dd>{{ $companyLabel }}</dd>
                        <dt>Kind</dt><dd>{{ $kinds[$doc->kind] ?? 'Other' }}</dd>
                        <dt>Issue date</dt><dd>{{ $day($doc->issued_on) ?? 'Not given' }}</dd>
                        <dt>Expiry or renewal</dt><dd>{{ $day($doc->expires_on) ?? 'No expiry date' }}</dd>
                        <dt>Bell</dt><dd>{{ $doc->expires_on ? ($doc->remind_days ? $doc->remind_days . ($doc->remind_days === 1 ? ' day' : ' days') . ' before it expires, to the owner and Master data editors' : 'No bell') : 'Rings once it has an expiry date' }}</dd>
                        @if ($doc->notes) <dt>Notes</dt><dd>{{ $doc->notes }}</dd> @endif
                    </dl>
                @endif
            </section>

            @if ($canDelete && ! $doc->isArchived())
                <div>
                    <button type="button" class="btn btn-ghost btn-sm" style="color: var(--color-danger);" wire:click="askArchive">Archive document</button>
                </div>
            @endif
        </div>

        {{-- Versions: every file the document has had --}}
        <section class="card" aria-labelledby="cdp-versions-title">
            <div class="card-header">
                <h2 class="card-title cdp-icon-title" id="cdp-versions-title"><i data-lucide="history"></i> Versions</h2>
                <span class="help">{{ $versions->count() }}</span>
            </div>
            <ul class="cdp-versions">
                @foreach ($versions as $v)
                    <li wire:key="v-{{ $v->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="text-ink">Version {{ $v->number }}</strong>
                            @if ($v->id === $doc->current_version_id) <span class="pill pill-info">Current</span> @endif
                        </div>
                        <div class="muted">Uploaded {{ $at($v->created_at) }} by {{ $who($v->uploaded_by) }}</div>
                        <div class="cdp-file">{{ $v->original_name }} · {{ $types[$v->mime] ?? 'File' }}, {{ \App\Support\CompanyDocuments\Documents::size($v->size) }}</div>
                        @if ($v->reviewed_at)
                            <div class="help">{{ $v->applied ? 'Details applied' : 'Kept HQ as it was' }} on {{ $at($v->reviewed_at) }} by {{ $who($v->reviewed_by) }}</div>
                        @endif
                        <div class="cdp-links">
                            @if ($v->opensInline())
                                <a class="btn btn-ghost btn-sm" href="{{ route('hq.mdm.documents.file', [$doc, $v, 'view']) }}" target="_blank" rel="noopener" aria-label="View version {{ $v->number }} (opens in a new tab)"><i data-lucide="eye"></i> View</a>
                            @endif
                            <a class="btn btn-ghost btn-sm" href="{{ route('hq.mdm.documents.file', [$doc, $v, 'download']) }}" aria-label="Download version {{ $v->number }}"><i data-lucide="download"></i> Download</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    {{-- Replace the file --}}
    @if ($replaceOpen)
        <div class="modal-backdrop" wire:key="replace-dialog">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cdp-replace-title" @keydown.escape.window="$wire.closeReplace()">
                <h2 id="cdp-replace-title" class="modal-title">Replace the file</h2>
                <p class="muted" style="margin-top: 0;">The new file becomes version {{ ($versions->max('number') ?? 0) + 1 }}. Every earlier version stays here and can still be downloaded.
                    @if (\App\Support\CompanyDocuments\Reader::reads($doc)) Claude reads the new file and suggests details again. @endif</p>
                <form wire:submit="replaceFile" class="cdp-form">
                    <div class="field">
                        <span class="label" id="cdp-replace-label">New file</span>
                        <label class="cdp-drop" for="cdp-replace-file">
                            <i data-lucide="file-text"></i>
                            @if ($replacement)
                                <strong>{{ $replacement->getClientOriginalName() }}</strong>
                                <span>{{ \App\Support\CompanyDocuments\Documents::size((int) $replacement->getSize()) }}. Choose another file to change it.</span>
                            @else
                                <strong>Choose a file</strong>
                                <span>PDF, JPG, PNG or Word (.docx), up to 20 MB</span>
                            @endif
                            <input id="cdp-replace-file" type="file" wire:model="replacement" aria-labelledby="cdp-replace-label"
                                   accept=".pdf,.jpg,.jpeg,.png,.docx,application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        </label>
                        <span class="help" wire:loading wire:target="replacement">Uploading the file…</span>
                        @error('replacement') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeReplace">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="replaceFile, replacement">Upload new version</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($confirmArchive)
        <div class="modal-backdrop" wire:key="archive-dialog">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cdp-archive-title" @keydown.escape.window="$wire.cancelArchive()">
                <h2 id="cdp-archive-title" class="modal-title">Archive ‘{{ $doc->title }}’?</h2>
                <p class="muted">It leaves the list and rings no more bells. The document and its {{ $versions->count() === 1 ? 'file are' : $versions->count() . ' versions are' }} kept, and you can restore it at any time; nothing is deleted.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="cancelArchive">Keep it</button>
                    <button type="button" class="btn btn-danger" wire:click="archiveDocument">Archive document</button>
                </div>
            </div>
        </div>
    @endif
</div>
