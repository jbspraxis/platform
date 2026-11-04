<div class="cd">
    <style>
        .cd-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 16px; margin-bottom: 16px; }
        .cd-tools .segmented { flex: 1 1 auto; }
        .cd-tools .segmented-item .tag { padding: 0 6px; }
        .cd-company-select { display: none; }
        .cd-attention { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin-bottom: 20px; }
        .cd-attention strong { color: var(--color-ink); }
        .cd-list { list-style: none; margin: 0; padding: 0; }
        /* A row wraps to the space the card has (the rail and a narrow window leave less than the viewport suggests):
           the title keeps 260px at least, then dates, status and actions follow on the next line */
        .cd-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 20px; padding: 14px 20px; border-top: 1px solid var(--color-line); }
        .cd-main { flex: 1 1 260px; min-width: 0; }
        .cd-dates { flex: 0 0 160px; }
        .cd-status { flex: 0 0 150px; }
        .cd-actions { flex: 0 1 auto; margin-left: auto; }
        .cd-row:first-child { border-top: 0; }
        .cd-row.is-expired, .cd-row.is-overdue { box-shadow: inset 3px 0 0 var(--color-danger); }
        .cd-row.is-soon { box-shadow: inset 3px 0 0 var(--color-warning); }
        .cd-title { font-weight: 600; color: var(--color-ink); text-decoration: none; overflow-wrap: anywhere; }
        a.cd-title:hover { color: var(--color-deep-teal); text-decoration: underline; }
        .cd-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; margin-top: 4px; font-size: 0.8125rem; color: var(--color-muted); }
        .cd-dates { font-size: 0.875rem; color: var(--color-ink); font-variant-numeric: tabular-nums; }
        .cd-dates .muted { display: inline-block; min-width: 56px; }
        .cd-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; }
        .cd-actions .btn { min-height: 36px; }
        .cd-note { margin-top: 4px; font-size: 0.8125rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .cd-section { margin: 28px 0 10px; font-size: 0.9375rem; font-weight: 600; color: var(--color-ink); }
        .cd-drop { position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 20px 16px; border: 1px dashed var(--color-line-strong); border-radius: var(--radius-card); background: var(--color-mist); text-align: center; cursor: pointer; font-size: 0.875rem; color: var(--color-slate); }
        .cd-drop:hover, .cd-drop:focus-within { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .cd-drop svg { width: 22px; height: 22px; color: var(--color-deep-teal); }
        .cd-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .cd-drop strong { color: var(--color-ink); overflow-wrap: anywhere; }
        .cd-form { display: flex; flex-direction: column; gap: 14px; }
        .cd-form .cd-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .cd .modal { max-width: 600px; }
        @media (max-width: 599px) {
            .cd-row { padding: 14px 16px; gap: 6px 16px; }
            .cd-dates, .cd-status { flex: 0 1 auto; }
            .cd-actions { flex: 1 1 100%; justify-content: flex-start; margin-left: -8px; }
            .cd-tools .segmented { display: none; }
            .cd-company-select { display: flex; flex: 1 1 100%; }
            .cd-form .cd-pair { grid-template-columns: 1fr; }
            .cd .modal-backdrop { padding: 12px; }
            .cd .modal { padding: 20px 16px; }
            .cd .modal-actions { flex-wrap: wrap-reverse; }
            .cd .modal-actions .btn { flex: 1 1 auto; }
        }
    </style>

    @php
        $kindLabel = fn ($k) => $kinds[$k] ?? 'Other';
        $companyLabel = fn ($id) => $labels[$id ?? \App\Support\CompanyDocuments\Companies::GROUP] ?? 'A former company';
        $day = fn ($d) => $d ? \App\Support\CompanyDocuments\Documents::day($d->format('Y-m-d')) : null;
        $types = ['application/pdf' => 'PDF', 'image/jpeg' => 'JPG', 'image/png' => 'PNG', \App\Support\CompanyDocuments\Documents::TYPES['docx'] => 'Word'];
        $attentionTotal = array_sum($attention);
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Company documents</h1>
            <p class="page-subtitle">Each company’s registration, tax and bank papers, with their renewal dates and its compliance calendar. Every view and download is logged.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($tab === 'calendar' && $canCreate)
                <button type="button" class="btn btn-primary" wire:click="newDeadline"><i data-lucide="calendar-plus"></i> Add a deadline</button>
            @elseif ($canCreate)
                <button type="button" class="btn btn-primary" wire:click="openUpload"><i data-lucide="upload"></i> Upload a document</button>
            @endif
        </div>
    </div>

    {{-- Company: segmented on wide screens, a select on phones --}}
    <div class="cd-tools">
        <div class="segmented" role="group" aria-label="Company">
            <button type="button" class="segmented-item" aria-pressed="{{ $company === '' ? 'true' : 'false' }}" wire:click="$set('company', '')">All companies</button>
            @foreach ($companies as $c)
                <button type="button" class="segmented-item" aria-pressed="{{ $company === $c['key'] ? 'true' : 'false' }}" wire:click="$set('company', '{{ $c['key'] }}')" wire:key="co-{{ $c['key'] }}">
                    {{ $c['label'] }} <span class="tag">{{ $counts[$c['id'] ?? ''] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
        <div class="field cd-company-select">
            <label class="label" for="cd-company">Company</label>
            <select id="cd-company" class="select" wire:model.live="company">
                <option value="">All companies</option>
                @foreach ($companies as $c)
                    <option value="{{ $c['key'] }}">{{ $c['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($flash)
        <div class="alert alert-success mb-4" role="status"><i data-lucide="circle-check"></i><div>{{ $flash }}</div></div>
    @endif

    {{-- Expired and due-soon items, counted for the company filtered on --}}
    @if ($attentionTotal > 0)
        <div class="alert alert-warning cd-attention" role="status">
            <i data-lucide="triangle-alert"></i>
            <div>
                <strong>Needs attention:</strong>
                {{ collect([
                    $attention['expired'] ? $attention['expired'] . ' ' . ($attention['expired'] === 1 ? 'document has' : 'documents have') . ' expired' : null,
                    $attention['expiring'] ? $attention['expiring'] . ' ' . ($attention['expiring'] === 1 ? 'document expires' : 'documents expire') . ' soon' : null,
                    $attention['overdue'] ? $attention['overdue'] . ' ' . ($attention['overdue'] === 1 ? 'deadline is' : 'deadlines are') . ' overdue' : null,
                    $attention['due'] ? $attention['due'] . ' ' . ($attention['due'] === 1 ? 'deadline is' : 'deadlines are') . ' due soon' : null,
                ])->filter()->join(', ', ' and ') }}.
            </div>
        </div>
    @endif

    <nav class="tabs" aria-label="Company documents">
        <button type="button" class="tab {{ $tab === 'documents' ? 'is-active' : '' }}" wire:click="$set('tab', 'documents')" aria-current="{{ $tab === 'documents' ? 'page' : 'false' }}">
            Documents ({{ $archived ? $archivedCount : $documents->count() }})
        </button>
        <button type="button" class="tab {{ $tab === 'calendar' ? 'is-active' : '' }}" wire:click="$set('tab', 'calendar')" aria-current="{{ $tab === 'calendar' ? 'page' : 'false' }}">
            Compliance calendar ({{ $open->count() }})
        </button>
    </nav>

    @if ($tab === 'documents')
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <p class="help" style="margin: 0;">{{ $archived ? 'Archived documents: kept with all their versions, out of the list.' : 'Expired documents first, then those due for renewal.' }}</p>
            @if ($archived || $archivedCount > 0)
                <button type="button" class="btn btn-ghost btn-sm" wire:click="$toggle('archived')">{{ $archived ? 'Show current documents' : "Show archived ({$archivedCount})" }}</button>
            @endif
        </div>

        <div class="card" style="padding: 0;">
            @if ($documents->isEmpty())
                <div class="empty-state">
                    @if ($archived)
                        <strong>No archived documents</strong>
                        Archived documents stay here with all their versions.
                    @else
                        <strong>No documents {{ $company === '' ? 'yet' : 'for ' . ($labels[$company] ?? 'this company') . ' yet' }}</strong>
                        Start with the certificate of incorporation: HQ reads it and suggests the company’s registration details for you to check.
                        @if ($canCreate)
                            <div style="margin-top: 16px;"><button type="button" class="btn btn-primary" wire:click="openUpload"><i data-lucide="upload"></i> Upload a document</button></div>
                        @endif
                    @endif
                </div>
            @else
                <ul class="cd-list">
                    @foreach ($documents as $d)
                        @php $v = $versions[$d->current_version_id] ?? null; @endphp
                        <li class="cd-row is-{{ $d->status['key'] }}" wire:key="doc-{{ $d->id }}">
                            <div class="cd-main">
                                <a class="cd-title" href="{{ route('hq.mdm.documents.show', $d) }}" wire:navigate>{{ $d->title }}</a>
                                <div class="cd-meta">
                                    <span class="tag">{{ $kindLabel($d->kind) }}</span>
                                    @if ($company === '') <span>{{ $companyLabel($d->legal_entity_id) }}</span> @endif
                                    @if ($v) <span>Version {{ $v->number }} · {{ $types[$v->mime] ?? 'File' }}, {{ \App\Support\CompanyDocuments\Documents::size($v->size) }}</span> @endif
                                </div>
                            </div>
                            <div class="cd-dates">
                                <div><span class="muted">Issued</span> {{ $day($d->issued_on) ?? '—' }}</div>
                                <div><span class="muted">Expires</span> {{ $day($d->expires_on) ?? '—' }}</div>
                            </div>
                            <div class="cd-status"><span class="pill {{ $d->status['pill'] }}">{{ $d->status['label'] }}</span></div>
                            <div class="cd-actions">
                                @if ($v)
                                    @if ($v->opensInline())
                                        <a class="btn btn-ghost btn-sm" href="{{ route('hq.mdm.documents.file', [$d, $v, 'view']) }}" target="_blank" rel="noopener" aria-label="View {{ $d->title }} (opens in a new tab)"><i data-lucide="eye"></i> View</a>
                                    @endif
                                    <a class="btn btn-ghost btn-sm" href="{{ route('hq.mdm.documents.file', [$d, $v, 'download']) }}" aria-label="Download {{ $d->title }}"><i data-lucide="download"></i> Download</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        {{-- Compliance calendar --}}
        <div class="card" style="padding: 0;">
            @if ($open->isEmpty())
                <div class="empty-state">
                    <strong>No open deadlines {{ $company === '' ? '' : 'for ' . ($labels[$company] ?? 'this company') }}</strong>
                    Add the CAC annual return and the tax filings, or upload the CAC status report and apply the dates it gives.
                    @if ($canCreate)
                        <div style="margin-top: 16px;"><button type="button" class="btn btn-primary" wire:click="newDeadline"><i data-lucide="calendar-plus"></i> Add a deadline</button></div>
                    @endif
                </div>
            @else
                <ul class="cd-list">
                    @foreach ($open as $d)
                        <li class="cd-row is-{{ $d->status['key'] }}" wire:key="dl-{{ $d->id }}">
                            <div class="cd-main">
                                <span class="cd-title">{{ $d->title }}</span>
                                <div class="cd-meta">
                                    @if ($company === '') <span>{{ $companyLabel($d->legal_entity_id) }}</span> @endif
                                    <span class="tag">{{ \App\Models\CompanyDeadline::REPEATS[$d->repeats] }}</span>
                                    <span>{{ $d->remind_days ? 'Bell ' . $d->remind_days . ($d->remind_days === 1 ? ' day' : ' days') . ' before' : 'No bell' }}</span>
                                </div>
                                @if ($d->notes) <div class="cd-note">{{ $d->notes }}</div> @endif
                            </div>
                            <div class="cd-dates"><div><span class="muted">Due</span> {{ $day($d->due_on) }}</div></div>
                            <div class="cd-status"><span class="pill {{ $d->status['pill'] }}">{{ $d->status['label'] }}</span></div>
                            <div class="cd-actions">
                                @if ($canEdit)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="askDone('{{ $d->id }}')"><i data-lucide="check"></i> Mark done</button>
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="editDeadline('{{ $d->id }}')">Edit</button>
                                @endif
                                @if ($canDelete)
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="askArchive('{{ $d->id }}')">Archive</button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($done->isNotEmpty())
            <h2 class="cd-section">Recently done</h2>
            <div class="card" style="padding: 0;">
                <ul class="cd-list">
                    @foreach ($done as $d)
                        <li class="cd-row" wire:key="dd-{{ $d->id }}">
                            <div class="cd-main">
                                <span class="cd-title">{{ $d->title }}</span>
                                <div class="cd-meta">
                                    @if ($company === '') <span>{{ $companyLabel($d->legal_entity_id) }}</span> @endif
                                    <span>Done on {{ $d->done_at->timezone('Africa/Lagos')->format('j M Y') }} by {{ $people[$d->done_by] ?? 'a former staff member' }}</span>
                                </div>
                            </div>
                            <div class="cd-dates"><div><span class="muted">Due</span> {{ $day($d->due_on) }}</div></div>
                            <div class="cd-status"><span class="pill pill-success">Done</span></div>
                            <div class="cd-actions"></div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    {{-- Upload a document --}}
    @if ($uploadOpen)
        <div class="modal-backdrop" wire:key="upload-dialog">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cd-upload-title" x-data x-init="$nextTick(() => $el.querySelector('select, input')?.focus())" @keydown.escape.window="$wire.closeUpload()">
                <h2 id="cd-upload-title" class="modal-title">Upload a document</h2>
                <p class="help" style="margin: 0 0 16px;">PDF, JPG, PNG or Word, up to 20 MB. The file stays private to people with Master data access. Certificates, the memorandum and articles, status reports and tax certificates are read by Claude, which suggests details for you to check: nothing in HQ changes until you apply them.</p>
                <form wire:submit="saveUpload" class="cd-form">
                    <div class="cd-pair">
                        <div class="field">
                            <label class="label" for="cd-up-company">Company</label>
                            <select id="cd-up-company" class="select" wire:model="newDoc.company" @error('newDoc.company') aria-invalid="true" aria-describedby="cd-up-company-error" @enderror>
                                @foreach ($companies as $c)
                                    <option value="{{ $c['key'] }}">{{ $c['label'] }}</option>
                                @endforeach
                            </select>
                            @error('newDoc.company') <span id="cd-up-company-error" class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="cd-up-kind">Kind of document</label>
                            <select id="cd-up-kind" class="select" wire:model.live="newDoc.kind" @error('newDoc.kind') aria-invalid="true" aria-describedby="cd-up-kind-error" @enderror>
                                <option value="">Choose a kind</option>
                                @foreach ($kinds as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('newDoc.kind') <span id="cd-up-kind-error" class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="cd-up-title">Title</label>
                        <input id="cd-up-title" type="text" class="input" wire:model="newDoc.title" maxlength="190" @error('newDoc.title') aria-invalid="true" aria-describedby="cd-up-title-error" @enderror>
                        @error('newDoc.title') <span id="cd-up-title-error" class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <span class="label" id="cd-up-file-label">File</span>
                        <label class="cd-drop" for="cd-up-file">
                            <i data-lucide="file-text"></i>
                            @if ($file)
                                <strong>{{ $file->getClientOriginalName() }}</strong>
                                <span>{{ \App\Support\CompanyDocuments\Documents::size((int) $file->getSize()) }}. Choose another file to change it.</span>
                            @else
                                <strong>Choose a file</strong>
                                <span>PDF, JPG, PNG or Word (.docx), up to 20 MB</span>
                            @endif
                            <input id="cd-up-file" type="file" wire:model="file" aria-labelledby="cd-up-file-label"
                                   accept=".pdf,.jpg,.jpeg,.png,.docx,application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                   @error('file') aria-invalid="true" aria-describedby="cd-up-file-error" @enderror>
                        </label>
                        <span class="help" wire:loading wire:target="file">Uploading the file…</span>
                        @error('file') <span id="cd-up-file-error" class="field-error" role="alert">{{ $message }}</span> @enderror
                    </div>
                    <div class="cd-pair">
                        <div class="field">
                            <label class="label" for="cd-up-issued">Issue date (optional)</label>
                            <input id="cd-up-issued" type="date" class="input" wire:model="newDoc.issued_on" @error('newDoc.issued_on') aria-invalid="true" aria-describedby="cd-up-issued-error" @enderror>
                            @error('newDoc.issued_on') <span id="cd-up-issued-error" class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="cd-up-expires">Expiry or renewal date (optional)</label>
                            <input id="cd-up-expires" type="date" class="input" wire:model="newDoc.expires_on" @error('newDoc.expires_on') aria-invalid="true" aria-describedby="cd-up-expires-error" @enderror>
                            @error('newDoc.expires_on') <span id="cd-up-expires-error" class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="cd-up-remind">Bell before the expiry date (days, optional)</label>
                        <input id="cd-up-remind" type="number" min="1" max="365" class="input" style="max-width: 140px;" wire:model="newDoc.remind_days" aria-describedby="cd-up-remind-help{{ $errors->has('newDoc.remind_days') ? ' cd-up-remind-error' : '' }}">
                        <span id="cd-up-remind-help" class="help">The owner and Master data editors get a bell this many days before it expires. Leave it empty for no bell.</span>
                        @error('newDoc.remind_days') <span id="cd-up-remind-error" class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="cd-up-notes">Notes (optional)</label>
                        <textarea id="cd-up-notes" class="textarea" rows="2" wire:model="newDoc.notes" maxlength="2000"></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeUpload">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveUpload, file">
                            <span wire:loading.remove wire:target="saveUpload">Upload document</span>
                            <span wire:loading wire:target="saveUpload">Uploading…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Add or edit a deadline --}}
    @if ($deadlineOpen)
        <div class="modal-backdrop" wire:key="deadline-dialog">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cd-dl-title" x-data x-init="$nextTick(() => $el.querySelector('#cd-dl-name')?.focus())" @keydown.escape.window="$wire.closeDeadline()">
                <h2 id="cd-dl-title" class="modal-title">{{ $deadlineId ? 'Edit the deadline' : 'Add a deadline' }}</h2>
                <form wire:submit="saveDeadline" class="cd-form">
                    <div class="field">
                        <label class="label" for="cd-dl-company">Company</label>
                        <select id="cd-dl-company" class="select" wire:model="deadlineForm.company">
                            @foreach ($companies as $c)
                                <option value="{{ $c['key'] }}">{{ $c['label'] }}</option>
                            @endforeach
                        </select>
                        @error('deadlineForm.company') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="cd-dl-name">What is due</label>
                        <input id="cd-dl-name" type="text" class="input" wire:model="deadlineForm.title" maxlength="190" placeholder="e.g. CAC annual return" @error('deadlineForm.title') aria-invalid="true" aria-describedby="cd-dl-name-error" @enderror>
                        @error('deadlineForm.title') <span id="cd-dl-name-error" class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="cd-pair">
                        <div class="field">
                            <label class="label" for="cd-dl-due">Due date</label>
                            <input id="cd-dl-due" type="date" class="input" wire:model="deadlineForm.due_on" @error('deadlineForm.due_on') aria-invalid="true" aria-describedby="cd-dl-due-error" @enderror>
                            @error('deadlineForm.due_on') <span id="cd-dl-due-error" class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="cd-dl-repeats">Repeats</label>
                            <select id="cd-dl-repeats" class="select" wire:model="deadlineForm.repeats">
                                @foreach (\App\Models\CompanyDeadline::REPEATS as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="cd-dl-remind">Bell before the due date (days, optional)</label>
                        <input id="cd-dl-remind" type="number" min="1" max="365" class="input" style="max-width: 140px;" wire:model="deadlineForm.remind_days" aria-describedby="cd-dl-remind-help">
                        <span id="cd-dl-remind-help" class="help">Every year: once it is marked done, HQ adds next year’s with the same bell.</span>
                        @error('deadlineForm.remind_days') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="cd-dl-notes">Notes (optional)</label>
                        <textarea id="cd-dl-notes" class="textarea" rows="2" wire:model="deadlineForm.notes" maxlength="2000" placeholder="e.g. Filed by the company secretary"></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeDeadline">Cancel</button>
                        <button type="submit" class="btn btn-primary">{{ $deadlineId ? 'Save deadline' : 'Add deadline' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Confirmations, named after the deadline --}}
    @if ($confirmDone)
        <div class="modal-backdrop" wire:key="confirm-done">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cd-done-title" @keydown.escape.window="$wire.cancelConfirm()">
                <h2 id="cd-done-title" class="modal-title">Mark ‘{{ $confirmDone->title }}’ done?</h2>
                <p class="muted">HQ notes who marked it done and when, and its bell stops.
                    @if ($confirmDone->repeats === 'yearly') Next year’s is added, due {{ \App\Support\CompanyDocuments\Documents::day($confirmDone->due_on->copy()->addYearNoOverflow()->format('Y-m-d')) }}. @endif
                </p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="cancelConfirm">Keep it open</button>
                    <button type="button" class="btn btn-primary" wire:click="markDone">Mark done</button>
                </div>
            </div>
        </div>
    @endif
    @if ($confirmArchive)
        <div class="modal-backdrop" wire:key="confirm-archive">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cd-arch-title" @keydown.escape.window="$wire.cancelConfirm()">
                <h2 id="cd-arch-title" class="modal-title">Archive ‘{{ $confirmArchive->title }}’?</h2>
                <p class="muted">It leaves the calendar and rings no more bells. It stays in the archive and the audit log; nothing is deleted.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="cancelConfirm">Keep it</button>
                    <button type="button" class="btn btn-danger" wire:click="archiveDeadline">Archive deadline</button>
                </div>
            </div>
        </div>
    @endif
</div>
