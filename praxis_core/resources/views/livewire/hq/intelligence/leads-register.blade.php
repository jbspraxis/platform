<div>
    @php
        // Display labels for stored values (docs/BRAND.md section 12); the stored values stay as they are.
        $stageLabels = [
            'Lead Captured' => 'New',
            'Qualifying' => 'Qualifying',
            'In Consultation' => 'Free audit',
            'Proposal Sent' => 'Proposal sent',
            'Won / Lost' => 'Won or lost',
        ];
        $stageLabel = fn ($stage) => $stageLabels[$stage] ?? $stage;
        $tierLabel = fn ($tier) => match ((int) $tier) { 1 => 'Tier 1: enterprise', 2 => 'Tier 2: mid-market', default => 'Tier 3: small business' };
        // The tier is a category, not a status: a plain tag (app.css .tag), tinted for tier 1
        $tierStyle = fn ($tier) => (int) $tier === 1 ? 'background: var(--color-teal-tint); color: var(--color-petrol);' : '';
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount, 0);
    @endphp

    <style>
        .lr-row { cursor: pointer; }
        .lr-board { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 8px; align-items: flex-start; }
        .lr-col { flex: 0 0 280px; display: flex; flex-direction: column; max-height: calc(100vh - 260px); border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-mist); }
        .lr-col-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid var(--color-line); font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .lr-col-cards { display: flex; flex-direction: column; gap: 12px; padding: 12px; overflow-y: auto; }
        .lr-card { display: flex; flex-direction: column; gap: 8px; padding: 16px; background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-card); cursor: pointer; }
        .lr-card:hover { border-color: var(--color-line-strong); }
        .lr-backdrop { position: fixed; inset: 0; z-index: 40; background: rgba(12, 20, 43, 0.4); }
        .lr-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 41; width: 640px; max-width: 100%; display: flex; flex-direction: column; background: var(--color-paper); border-left: 1px solid var(--color-line); box-shadow: var(--shadow-float); }
        .lr-drawer-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 20px 24px; border-bottom: 1px solid var(--color-line); }
        .lr-drawer-body { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 24px; }
        .lr-merge-row { display: grid; grid-template-columns: 160px 1fr 1fr; gap: 12px; align-items: center; }
        .lr-choice { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--color-line); border-radius: var(--radius-control); cursor: pointer; font-size: 0.875rem; }
        .lr-choice.is-chosen { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        @media (max-width: 640px) { .lr-merge-row { grid-template-columns: 1fr; } }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Leads</h1>
            <p class="page-subtitle">Organisations in the sales pipeline, with their cost of poor quality estimate and who is following them up.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @can('crm.export')
            <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" class="btn btn-secondary">
                <i data-lucide="download"></i>
                <span wire:loading.remove wire:target="exportCsv">Download CSV</span>
                <span wire:loading wire:target="exportCsv">Preparing file…</span>
            </button>
            @endcan
            @can('crm.create')
            <button type="button" wire:click="openIntakeModal" wire:loading.attr="disabled" wire:target="openIntakeModal" class="btn btn-primary">
                <i data-lucide="plus"></i>
                <span>Add lead</span>
            </button>
            @endcan
        </div>
    </div>

    <div class="tabs" role="tablist" aria-label="Layout">
        <button type="button" role="tab" wire:click="toggleView('grid')" class="tab {{ $viewMode === 'grid' ? 'is-active' : '' }}" aria-selected="{{ $viewMode === 'grid' ? 'true' : 'false' }}">Table</button>
        <button type="button" role="tab" wire:click="toggleView('kanban')" class="tab {{ $viewMode === 'kanban' ? 'is-active' : '' }}" aria-selected="{{ $viewMode === 'kanban' ? 'true' : 'false' }}">Board by stage</button>
    </div>

    @if($viewMode === 'grid')
        <div class="card" style="padding: 0;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Organisation</th>
                            <th style="min-width: 180px;">Contact</th>
                            <th>Tier</th>
                            <th>Stage</th>
                            <th class="num" style="text-align: right;">Cost of poor quality (a year)</th>
                            <th style="min-width: 160px;">Owner</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leads as $lead)
                        <tr class="lr-row" wire:click="openDrawer('{{ $lead->org_id }}')">
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-ink">{{ $lead->company_name }}</span>
                                    @if($lead->is_duplicate_suspect)
                                        <span class="pill pill-warning">Possible duplicate</span>
                                    @endif
                                </div>
                                <div class="text-caption text-muted mt-1">{{ $lead->copq_industry ?? 'Industry not given' }}</div>
                                @if($lead->status === 'Lead')
                                    <button type="button" wire:click.stop="requestConsultation('{{ $lead->org_id }}')" class="btn btn-ghost btn-sm mt-1" style="padding-left: 0;" title="Book a free audit for this lead, starting in 15 minutes">
                                        <i data-lucide="calendar-plus"></i> Book free audit
                                    </button>
                                @endif
                            </td>
                            <td>
                                <div class="text-ink">{{ $lead->executive_name }}</div>
                                <div class="text-caption text-muted">{{ $lead->corporate_email }}</div>
                            </td>
                            <td><span class="tag" style="{{ $tierStyle($lead->lead_tier) }}">{{ $tierLabel($lead->lead_tier) }}</span></td>
                            <td>{{ $stageLabel($lead->pipeline_stage) }}</td>
                            <td class="num">
                                @if($lead->calculated_leakage)
                                    <span class="font-semibold text-ink">{{ $money($lead->copq_currency, $lead->calculated_leakage) }}</span>
                                @else
                                    <span class="text-muted">Not scored</span>
                                @endif
                            </td>
                            <td onclick="event.stopPropagation();">
                                @if($lead->owner_id)
                                    <div class="flex items-center gap-2">
                                        <span class="avatar" style="width: 28px; height: 28px; font-size: 0.75rem;" aria-hidden="true">{{ substr($lead->owner_first, 0, 1) }}{{ substr($lead->owner_last, 0, 1) }}</span>
                                        <span class="text-ink">{{ $lead->owner_first }}</span>
                                    </div>
                                @else
                                    <button type="button" wire:click="claimLead('{{ $lead->org_id }}')" wire:loading.attr="disabled" wire:target="claimLead('{{ $lead->org_id }}')" class="btn btn-secondary btn-sm">
                                        <span wire:loading.remove wire:target="claimLead('{{ $lead->org_id }}')">Assign to me</span>
                                        <span wire:loading wire:target="claimLead('{{ $lead->org_id }}')">Assigning…</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>No leads yet</strong>
                                    Leads appear here when someone uses the cost of poor quality calculator on the website. You can also add a lead yourself.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @else
        <div class="lr-board">
            @foreach($stages as $stage)
                @php $inStage = collect($leads)->where('pipeline_stage', $stage); @endphp
                <section class="lr-col" aria-label="{{ $stageLabel($stage) }}">
                    <div class="lr-col-head">
                        <span>{{ $stageLabel($stage) }}</span>
                        <span class="pill pill-neutral num">{{ $inStage->count() }}</span>
                    </div>

                    <div class="lr-col-cards">
                        @forelse($inStage as $lead)
                            <div class="lr-card" wire:click="openDrawer('{{ $lead->org_id }}')">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-semibold text-ink">{{ $lead->company_name }}</span>
                                    @if($lead->status === 'Lead')
                                        <button type="button" wire:click.stop="requestConsultation('{{ $lead->org_id }}')" class="btn btn-ghost btn-sm" title="Book a free audit for this lead, starting in 15 minutes" aria-label="Book a free audit for {{ $lead->company_name }}">
                                            <i data-lucide="calendar-plus"></i>
                                        </button>
                                    @endif
                                </div>
                                @if($lead->is_duplicate_suspect)
                                    <span class="pill pill-warning self-start">Possible duplicate</span>
                                @endif
                                <div class="text-caption text-muted flex items-center gap-1"><i data-lucide="user" style="width: 14px; height: 14px;"></i> {{ $lead->executive_name }}</div>

                                @if($lead->calculated_leakage)
                                    <div>
                                        <div class="stat-label">Cost of poor quality (a year)</div>
                                        <div class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ $money($lead->copq_currency, $lead->calculated_leakage) }}</div>
                                    </div>
                                @else
                                    <div class="text-caption text-muted">Not scored</div>
                                @endif

                                <div class="flex items-center justify-between gap-2 pt-2" style="border-top: 1px solid var(--color-line);" onclick="event.stopPropagation();">
                                    @if($lead->owner_id)
                                        <span class="text-caption text-slate">Owner: {{ $lead->owner_first }}</span>
                                    @else
                                        <button type="button" wire:click="claimLead('{{ $lead->org_id }}')" wire:loading.attr="disabled" wire:target="claimLead('{{ $lead->org_id }}')" class="btn btn-ghost btn-sm" style="padding-left: 0;">
                                            <span wire:loading.remove wire:target="claimLead('{{ $lead->org_id }}')">Assign to me</span>
                                            <span wire:loading wire:target="claimLead('{{ $lead->org_id }}')">Assigning…</span>
                                        </button>
                                    @endif
                                    <span class="text-caption text-muted">{{ \Carbon\Carbon::parse($lead->created_at)->diffForHumans(null, true, true) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-caption text-muted" style="padding: 16px; text-align: center;">No leads at this stage</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    {{-- Lead details (slide-over) --}}
    @if($showDrawer && $selectedLead)
        <div class="lr-backdrop" wire:click="closeDrawer"></div>
        <aside class="lr-drawer" role="dialog" aria-modal="true" aria-labelledby="lead-drawer-title">
            <div class="lr-drawer-head">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="lead-drawer-title" class="section-title" style="margin: 0;">{{ $selectedLead->company_name }}</h2>
                        <span class="tag" style="{{ $tierStyle($selectedLead->lead_tier) }}">{{ $tierLabel($selectedLead->lead_tier) }}</span>
                    </div>
                    <div class="text-small text-muted mt-1">{{ $selectedLead->executive_name }}@if($selectedLead->corporate_email) · {{ strtolower($selectedLead->corporate_email) }}@endif</div>
                </div>
                <button type="button" wire:click="closeDrawer" class="btn btn-ghost btn-sm" aria-label="Close lead details"><i data-lucide="x"></i></button>
            </div>

            <div class="lr-drawer-body">
                @if($selectedLead->is_duplicate_suspect)
                    <div class="alert alert-warning" style="justify-content: space-between; align-items: center;">
                        <div>
                            <strong>Possible duplicate</strong>
                            <div>This organisation has more than one assessment. Review them and keep one set of values.</div>
                        </div>
                        <button type="button" wire:click="openDiffViewer('{{ $selectedLead->org_id }}')" wire:loading.attr="disabled" wire:target="openDiffViewer('{{ $selectedLead->org_id }}')" class="btn btn-secondary btn-sm">
                            <span wire:loading.remove wire:target="openDiffViewer('{{ $selectedLead->org_id }}')">Review duplicates</span>
                            <span wire:loading wire:target="openDiffViewer('{{ $selectedLead->org_id }}')">Opening…</span>
                        </button>
                    </div>
                @endif

                <div class="flex flex-wrap items-end gap-3">
                    <div class="field" style="flex: 1; min-width: 200px;">
                        <label class="label" for="lead-stage">Pipeline stage</label>
                        <select id="lead-stage" wire:model="selectedLead.pipeline_stage" wire:change="updateStage('{{ $selectedLead->org_id }}', $event.target.value)" class="select">
                            @foreach($stages as $stage)
                                <option value="{{ $stage }}">{{ $stageLabel($stage) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(!$selectedLead->owner_id)
                        <button type="button" wire:click="claimLead('{{ $selectedLead->org_id }}')" wire:loading.attr="disabled" wire:target="claimLead('{{ $selectedLead->org_id }}')" class="btn btn-secondary">
                            <span wire:loading.remove wire:target="claimLead('{{ $selectedLead->org_id }}')">Assign to me</span>
                            <span wire:loading wire:target="claimLead('{{ $selectedLead->org_id }}')">Assigning…</span>
                        </button>
                    @endif
                </div>

                @if($selectedLead->calculated_leakage)
                    <section>
                        <h3 class="card-title" style="margin-bottom: 12px;">Cost of poor quality estimate</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" style="margin-bottom: 12px;">
                            <div class="card" style="padding: 16px;">
                                <div class="stat-label">Annual turnover</div>
                                <div class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ $money($selectedLead->copq_currency, $selectedLead->copq_turnover) }}</div>
                            </div>
                            <div class="card" style="padding: 16px;">
                                <div class="stat-label">Main bottleneck</div>
                                <div class="font-semibold text-ink">{{ $selectedLead->bottleneck }}</div>
                            </div>
                        </div>

                        <div class="callout" style="margin-bottom: 12px;">
                            <div class="stat-label">Estimated cost of poor quality, a year</div>
                            <div class="stat-value">{{ $money($selectedLead->copq_currency, $selectedLead->calculated_leakage) }}</div>
                        </div>

                        @if($selectedLead->ai_friction_analysis)
                            <div style="margin-bottom: 16px;">
                                <div class="stat-label">Summary</div>
                                <p class="text-small" style="margin-top: 4px;">{{ $selectedLead->ai_friction_analysis }}</p>
                            </div>
                        @endif

                        <a href="{{ route('dossier.show', ['token' => $selectedLead->report_token]) }}?exec=true" target="_blank" rel="noopener" class="btn btn-secondary">
                            <i data-lucide="external-link"></i> Open cost of poor quality report
                        </a>
                    </section>
                @else
                    <div class="card empty-state" style="padding: 24px;">
                        <strong>Not scored yet</strong>
                        This lead has no cost of poor quality estimate yet. Calculate one from Free audits, or book a free audit to collect the figures.
                    </div>
                @endif

                <section>
                    <h3 class="card-title" style="margin-bottom: 12px;">Record details</h3>
                    <dl class="grid gap-2 text-small" style="grid-template-columns: max-content 1fr; column-gap: 16px; margin: 0;">
                        <dt class="text-muted">Organisation ID</dt>
                        <dd style="margin: 0; font-variant-numeric: tabular-nums; word-break: break-all;">{{ $selectedLead->org_id }}</dd>
                        <dt class="text-muted">Added</dt>
                        <dd style="margin: 0; font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($selectedLead->created_at)->format('j M Y, H:i') }}</dd>
                        <dt class="text-muted">Owner</dt>
                        <dd style="margin: 0;">{{ $selectedLead->owner_id ? $selectedLead->owner_first . ' ' . $selectedLead->owner_last : 'Not assigned' }}</dd>
                    </dl>
                </section>
            </div>
        </aside>
    @endif

    {{-- Merge duplicate assessments --}}
    @if($showDiffModal && $diffOld && $diffNew)
        <div class="modal-backdrop" style="z-index: 60;">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="merge-title" style="max-width: 880px;">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="merge-title" class="modal-title">Merge duplicate records</h2>
                        <p class="text-small text-muted" style="margin: 0;">Choose which value to keep for each field. The newest record is updated with your choices and the duplicate flag is cleared.</p>
                    </div>
                    <button type="button" wire:click="closeDiffViewer" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                <div class="lr-merge-row text-small font-semibold text-ink" style="margin: 20px 0 8px;">
                    <div>Field</div>
                    <div>Earlier record <span class="block font-normal text-muted" style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($diffOld->created_at)->format('j M Y, H:i') }}</span></div>
                    <div>Newest record <span class="block font-normal text-muted" style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($diffNew->created_at)->format('j M Y, H:i') }}</span></div>
                </div>

                <div class="flex flex-col gap-2">
                    @foreach($diffFields as $field => $label)
                        @php $differs = $diffOld->$field != $diffNew->$field; @endphp
                        <div class="lr-merge-row" style="padding: 8px 0; border-top: 1px solid var(--color-line);">
                            <div class="text-small text-muted">{{ $label }}</div>

                            <label class="lr-choice {{ ($mergeSelections[$field] ?? '') === 'old' ? 'is-chosen' : '' }}">
                                <input type="radio" wire:model="mergeSelections.{{ $field }}" value="old">
                                <span class="{{ $differs ? 'text-ink' : 'text-muted' }}" @if(in_array($field, ['copq_turnover', 'calculated_leakage'])) style="font-variant-numeric: tabular-nums;" @endif>
                                    {{ is_numeric($diffOld->$field) && in_array($field, ['copq_turnover', 'calculated_leakage']) ? number_format($diffOld->$field) : $diffOld->$field }}
                                </span>
                            </label>

                            <label class="lr-choice {{ ($mergeSelections[$field] ?? '') === 'new' ? 'is-chosen' : '' }}">
                                <input type="radio" wire:model="mergeSelections.{{ $field }}" value="new">
                                <span class="{{ $differs ? 'text-ink' : 'text-muted' }}" @if(in_array($field, ['copq_turnover', 'calculated_leakage'])) style="font-variant-numeric: tabular-nums;" @endif>
                                    {{ is_numeric($diffNew->$field) && in_array($field, ['copq_turnover', 'calculated_leakage']) ? number_format($diffNew->$field) : $diffNew->$field }}
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="modal-actions">
                    <button type="button" wire:click="closeDiffViewer" class="btn btn-secondary">Cancel</button>
                    <button type="button" wire:click="executeMerge" wire:loading.attr="disabled" wire:target="executeMerge" class="btn btn-primary">
                        <span wire:loading.remove wire:target="executeMerge">Merge records</span>
                        <span wire:loading wire:target="executeMerge">Merging…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Add a lead by hand --}}
    @if($showIntakeModal)
        <div class="lr-backdrop" wire:click="closeIntakeModal"></div>
        <aside class="lr-drawer" role="dialog" aria-modal="true" aria-labelledby="intake-title" style="width: 760px;">
            <div class="lr-drawer-head">
                <div>
                    <h2 id="intake-title" class="section-title" style="margin: 0;">Add lead</h2>
                    <p class="text-small text-muted" style="margin: 4px 0 0;">Enter the organisation’s figures. We calculate its cost of poor quality estimate and add it to the pipeline.</p>
                </div>
                <button type="button" wire:click="closeIntakeModal" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
            </div>

            <div class="lr-drawer-body">
                <form wire:submit.prevent="submitManualPitch" class="flex flex-col gap-6">
                    <fieldset class="flex flex-col gap-4" style="border: 0; padding: 0; margin: 0;">
                        <legend class="card-title" style="margin-bottom: 12px;">Contact and organisation</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="field">
                                <label class="label" for="lead-name">Contact name</label>
                                <input id="lead-name" type="text" wire:model="name" required class="input" autocomplete="off">
                                @error('name') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-email">Work email</label>
                                <input id="lead-email" type="email" wire:model="email" required class="input" placeholder="e.g. name@company.com" autocomplete="off">
                                @error('email') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-company">Organisation name</label>
                                <input id="lead-company" type="text" wire:model="company" required class="input" autocomplete="off">
                                @error('company') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-currency">Currency</label>
                                <select id="lead-currency" wire:model="currency" required class="select">
                                    @foreach($dbCurrencies as $curr)
                                        <option value="{{ $curr->currency_code }}">{{ $curr->currency_code }}: {{ $curr->currency_name }}</option>
                                    @endforeach
                                </select>
                                @error('currency') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="field">
                                <label class="label" for="lead-industry">Industry</label>
                                <select id="lead-industry" wire:model="industry" required class="select">
                                    <option value="">Choose an industry</option>
                                    @foreach($dbIndustries as $ind)
                                        <option value="{{ $ind->industry_name }}">{{ $ind->industry_name }}</option>
                                    @endforeach
                                </select>
                                @error('industry') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-tool">Systems in use</label>
                                <select id="lead-tool" wire:model="tool" required class="select">
                                    <option value="">Choose the main system</option>
                                    @foreach($dbArchitectures as $arch)
                                        <option value="{{ $arch->tech_key }}">{{ $arch->display_name }}</option>
                                    @endforeach
                                </select>
                                @error('tool') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-bottleneck">Main bottleneck</label>
                                <select id="lead-bottleneck" wire:model="bottleneck" required class="select">
                                    <option value="">Choose a bottleneck</option>
                                    @foreach($dbBottlenecks as $bn)
                                        <option value="{{ $bn->bottleneck_name }}">{{ $bn->bottleneck_name }}</option>
                                    @endforeach
                                </select>
                                @error('bottleneck') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="flex flex-col gap-4" style="border: 0; border-top: 1px solid var(--color-line); padding: 16px 0 0; margin: 0;">
                        <legend class="card-title" style="float: left; width: 100%; margin-bottom: 4px;">Figures for the estimate</legend>
                        <p class="help" style="margin: 0;">All amounts are in the currency chosen above.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="field">
                                <label class="label" for="lead-turnover">Annual turnover</label>
                                <input id="lead-turnover" type="number" wire:model="turnover" required min="0" class="input" placeholder="e.g. 5000000">
                                @error('turnover') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-acv">Average contract value</label>
                                <input id="lead-acv" type="number" wire:model="acv" required min="0" class="input" placeholder="e.g. 25000">
                                @error('acv') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-headcount">Total headcount</label>
                                <input id="lead-headcount" type="number" wire:model="headcount" required min="1" class="input" placeholder="e.g. 150">
                                @error('headcount') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-managers">Managers who approve work</label>
                                <input id="lead-managers" type="number" wire:model="mgmtNodes" required min="1" class="input" placeholder="e.g. 15">
                                @error('mgmtNodes') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="field">
                                <label class="label" for="lead-hours">Manual hours a week, per manager</label>
                                <input id="lead-hours" type="number" wire:model="manualHrs" required min="0" class="input" placeholder="e.g. 5">
                                @error('manualHrs') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-hourly">Hourly cost of a manager</label>
                                <input id="lead-hourly" type="number" wire:model="hourlyCost" required min="0" class="input" placeholder="e.g. 50">
                                @error('hourlyCost') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lead-error-rate">Error rate (%)</label>
                                <input id="lead-error-rate" type="number" step="0.1" wire:model="errorRate" required min="0" max="100" class="input" placeholder="e.g. 3.5">
                                @error('errorRate') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </fieldset>

                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="closeIntakeModal" class="btn btn-secondary">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="submitManualPitch" class="btn btn-primary">
                            <span wire:loading.remove wire:target="submitManualPitch">Calculate and add lead</span>
                            <span wire:loading wire:target="submitManualPitch">Calculating…</span>
                        </button>
                    </div>
                </form>
            </div>
        </aside>
    @endif
</div>
