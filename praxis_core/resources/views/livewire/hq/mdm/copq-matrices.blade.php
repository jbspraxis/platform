<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Cost of poor quality tables</h1>
            <p class="page-subtitle">The rates and wording behind the cost of poor quality calculator and report: baselines per industry, per current system and per department.</p>
        </div>
        @if($activeTab === 'industries')
            <button type="button" wire:click="openIndModal" class="btn btn-primary"><i data-lucide="plus"></i> Add industry</button>
        @elseif($activeTab === 'architectures')
            <button type="button" wire:click="openArchModal" class="btn btn-primary"><i data-lucide="plus"></i> Add system</button>
        @else
            <button type="button" wire:click="openBnModal" class="btn btn-primary"><i data-lucide="plus"></i> Add department</button>
        @endif
    </div>

    @include('livewire.hq.mdm.partials.tabs')

    {{-- A segmented choice, not a second primary button (app.css .segmented: the selected option is tinted) --}}
    <div class="segmented" style="margin-bottom: 24px;" role="group" aria-label="Show">
        <button type="button" wire:click="switchTab('industries')" class="segmented-item" aria-pressed="{{ $activeTab === 'industries' ? 'true' : 'false' }}">Industries</button>
        <button type="button" wire:click="switchTab('architectures')" class="segmented-item" aria-pressed="{{ $activeTab === 'architectures' ? 'true' : 'false' }}">Current systems</button>
        <button type="button" wire:click="switchTab('bottlenecks')" class="segmented-item" aria-pressed="{{ $activeTab === 'bottlenecks' ? 'true' : 'false' }}">Departments</button>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-wrap">

        @if($activeTab === 'industries')
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">Industry</th>
                        <th style="min-width: 240px;">Compliance standard and main risk</th>
                        <th style="min-width: 260px;">Cost context and opportunity</th>
                        <th class="num text-right" style="min-width: 110px;">Base error rate</th>
                        <th style="width: 90px;"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($industries as $ind)
                    <tr>
                        <td>
                            <div class="text-ink" style="font-weight: 600;">{{ $ind->industry_name }}</div>
                            <div class="flex flex-wrap gap-2" style="margin-top: 6px;">
                                <span class="pill pill-neutral">{{ $ind->organization_type }}</span>
                                <span class="pill pill-neutral">Value: {{ $ind->value_term }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="text-ink" style="font-weight: 500;">{{ $ind->compliance_standard }}</div>
                            <div class="help" style="margin-top: 2px;">Main risk: {{ $ind->risk_type }}</div>
                        </td>
                        <td>
                            <div><span class="muted">Cost context:</span> {{ $ind->deficit_context }}</div>
                            <div style="margin-top: 2px;"><span class="muted">Opportunity:</span> {{ $ind->opportunity_focus }}</div>
                        </td>
                        <td class="num text-ink" style="font-weight: 600;">{{ number_format($ind->error_floor, 2) }}%</td>
                        <td style="text-align: right;">
                            <button type="button" wire:click="openIndModal({{ $ind->id }})" class="btn btn-ghost btn-sm">Edit</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <strong>No industries yet</strong>
                                The calculator needs a baseline for each industry it offers. Select Add industry to create the first one.
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($activeTab === 'architectures')
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">System</th>
                        <th style="min-width: 200px;">Main symptom</th>
                        <th style="min-width: 320px;">What we recommend</th>
                        <th class="num text-right" style="min-width: 120px;">Error multiplier</th>
                        <th style="width: 90px;"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($architectures as $arch)
                    <tr>
                        <td>
                            <div class="text-ink" style="font-weight: 600;">{{ $arch->display_name }}</div>
                            <div class="help" style="margin-top: 2px;">Key: {{ $arch->tech_key }}</div>
                        </td>
                        <td class="text-ink" style="font-weight: 500;">{{ $arch->symptom_title }}</td>
                        <td>{{ $arch->intervention_strategy }}</td>
                        <td class="num text-ink" style="font-weight: 600;">{{ number_format($arch->error_multiplier, 2) }}×</td>
                        <td style="text-align: right;">
                            <button type="button" wire:click="openArchModal({{ $arch->id }})" class="btn btn-ghost btn-sm">Edit</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <strong>No systems yet</strong>
                                Add the systems organisations run on today, such as spreadsheets or an older ERP, with the error multiplier for each. Select Add system to start.
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

        @else
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">Department</th>
                        <th style="min-width: 320px;">Summary used in the report</th>
                        <th style="min-width: 220px;">Multipliers</th>
                        <th class="num text-right" style="min-width: 100px;">Risk weight</th>
                        <th style="width: 90px;"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bottlenecks as $bn)
                    <tr>
                        <td class="text-ink" style="font-weight: 600;">{{ $bn->bottleneck_name }}</td>
                        <td>{{ $bn->bottleneck_narrative }}</td>
                        <td style="font-variant-numeric: tabular-nums;">
                            <div><span class="muted">Appraisal</span> {{ number_format($bn->appraisal_multiplier, 2) }}</div>
                            <div><span class="muted">Failure</span> {{ number_format($bn->failure_multiplier, 2) }}</div>
                            <div><span class="muted">Opportunity</span> {{ number_format($bn->opportunity_multiplier, 2) }}</div>
                        </td>
                        <td class="num text-ink" style="font-weight: 600;">+{{ $bn->risk_weight }}%</td>
                        <td style="text-align: right;">
                            <button type="button" wire:click="openBnModal({{ $bn->id }})" class="btn btn-ghost btn-sm">Edit</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <strong>No departments yet</strong>
                                Add the departments where poor quality builds up, such as finance or HR, with their multipliers. Select Add department to start.
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
        </div>
    </div>

    @if($showIndModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="ind-modal-title" style="max-width: 760px;">
                <div class="flex items-start justify-between gap-3">
                    <h2 id="ind-modal-title" class="modal-title">{{ $indEditMode ? 'Edit industry' : 'Add an industry' }}</h2>
                    <button type="button" wire:click="$set('showIndModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>
                <form wire:submit.prevent="saveIndustry" class="flex flex-col gap-4" style="margin-top: 8px;">
                    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                        <div class="field">
                            <label class="label" for="ind-name">Industry name</label>
                            <input id="ind-name" type="text" wire:model="industry_name" required class="input">
                            @error('industry_name') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="ind-floor">Base error rate (%)</label>
                            <input id="ind-floor" type="number" step="0.01" wire:model="error_floor" required class="input" style="font-variant-numeric: tabular-nums;">
                            @error('error_floor') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="field">
                            <label class="label" for="ind-standard">Compliance standard</label>
                            <input id="ind-standard" type="text" wire:model="compliance_standard" required class="input">
                            @error('compliance_standard') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="ind-risk">Main risk</label>
                            <input id="ind-risk" type="text" wire:model="risk_type" required class="input">
                            @error('risk_type') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="field">
                            <label class="label" for="ind-deficit">Cost context</label>
                            <input id="ind-deficit" type="text" wire:model="deficit_context" required class="input">
                            @error('deficit_context') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="ind-erosion">What the cost reduces</label>
                            <input id="ind-erosion" type="text" wire:model="erosion_target" required class="input" placeholder="e.g. margins">
                            @error('erosion_target') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="ind-opportunity">Where the opportunity is</label>
                        <input id="ind-opportunity" type="text" wire:model="opportunity_focus" required class="input">
                        @error('opportunity_focus') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ind-fallback">Standard summary</label>
                        <textarea id="ind-fallback" wire:model="fallback_diagnostic" rows="3" required class="textarea" aria-describedby="ind-fallback-help"></textarea>
                        <span id="ind-fallback-help" class="help">Used in the report when the assistant can’t write a summary. Write {COMPANY_NAME} where the company’s name goes.</span>
                    </div>
                    <fieldset class="grid gap-4 sm:grid-cols-3" style="background: var(--color-mist); border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; margin: 0;">
                        <legend class="label" style="padding: 0 4px;">Words used in the report</legend>
                        <div class="field">
                            <label class="label" for="ind-org">Organisation type</label>
                            <input id="ind-org" type="text" wire:model="organization_type" required class="input">
                        </div>
                        <div class="field">
                            <label class="label" for="ind-value">Value term</label>
                            <input id="ind-value" type="text" wire:model="value_term" required class="input">
                        </div>
                        <div class="field">
                            <label class="label" for="ind-mult">Multiplier term</label>
                            <input id="ind-mult" type="text" wire:model="multiplier_term" required class="input">
                        </div>
                    </fieldset>
                    <div class="modal-actions">
                        <button type="button" wire:click="$set('showIndModal', false)" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">{{ $indEditMode ? 'Save changes' : 'Add industry' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showArchModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="arch-modal-title" style="max-width: 680px;">
                <div class="flex items-start justify-between gap-3">
                    <h2 id="arch-modal-title" class="modal-title">{{ $archEditMode ? 'Edit system' : 'Add a system' }}</h2>
                    <button type="button" wire:click="$set('showArchModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>
                <form wire:submit.prevent="saveArchitecture" class="flex flex-col gap-4" style="margin-top: 8px;">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="field">
                            <label class="label" for="arch-key">Key</label>
                            <input id="arch-key" type="text" wire:model="tech_key" required class="input" {{ $archEditMode ? 'readonly' : '' }} aria-describedby="arch-key-help">
                            <span id="arch-key-help" class="help">Lower case, used by the calculator. It can’t be changed later.</span>
                            @error('tech_key') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="arch-mult">Error multiplier</label>
                            <input id="arch-mult" type="number" step="0.01" wire:model="error_multiplier" required class="input" style="font-variant-numeric: tabular-nums;" placeholder="e.g. 1.5">
                            @error('error_multiplier') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="arch-name">Name shown</label>
                        <input id="arch-name" type="text" wire:model="display_name" required class="input">
                        @error('display_name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="arch-symptom">Main symptom</label>
                        <input id="arch-symptom" type="text" wire:model="symptom_title" required class="input">
                        @error('symptom_title') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="arch-strategy">What we recommend</label>
                        <textarea id="arch-strategy" wire:model="intervention_strategy" rows="4" required class="textarea"></textarea>
                        @error('intervention_strategy') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="modal-actions">
                        <button type="button" wire:click="$set('showArchModal', false)" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">{{ $archEditMode ? 'Save changes' : 'Add system' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showBnModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="bn-modal-title" style="max-width: 600px;">
                <div class="flex items-start justify-between gap-3">
                    <h2 id="bn-modal-title" class="modal-title">{{ $bnEditMode ? 'Edit department' : 'Add a department' }}</h2>
                    <button type="button" wire:click="$set('showBnModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>
                <form wire:submit.prevent="saveBottleneck" class="flex flex-col gap-4" style="margin-top: 8px;">

                    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                        <div class="field">
                            <label class="label" for="bn-name">Department</label>
                            <input id="bn-name" type="text" wire:model="bottleneck_name" required class="input" placeholder="e.g. HR and recruitment">
                            @error('bottleneck_name') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="bn-risk">Risk weight (+%)</label>
                            <input id="bn-risk" type="number" wire:model="risk_weight" required class="input" style="font-variant-numeric: tabular-nums;">
                            @error('risk_weight') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <fieldset class="grid gap-4 sm:grid-cols-3" style="background: var(--color-mist); border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; margin: 0;">
                        <legend class="label" style="padding: 0 4px;">Cost of poor quality multipliers</legend>
                        <div class="field">
                            <label class="label" for="bn-appraisal">Appraisal (time)</label>
                            <input id="bn-appraisal" type="number" step="0.01" wire:model="appraisal_multiplier" required class="input" style="font-variant-numeric: tabular-nums;">
                        </div>
                        <div class="field">
                            <label class="label" for="bn-failure">Failure (rework)</label>
                            <input id="bn-failure" type="number" step="0.01" wire:model="failure_multiplier" required class="input" style="font-variant-numeric: tabular-nums;">
                        </div>
                        <div class="field">
                            <label class="label" for="bn-opportunity">Opportunity (lost revenue)</label>
                            <input id="bn-opportunity" type="number" step="0.01" wire:model="opportunity_multiplier" required class="input" style="font-variant-numeric: tabular-nums;">
                        </div>
                    </fieldset>
                    @error('appraisal_multiplier') <span class="field-error">{{ $message }}</span> @enderror
                    @error('failure_multiplier') <span class="field-error">{{ $message }}</span> @enderror
                    @error('opportunity_multiplier') <span class="field-error">{{ $message }}</span> @enderror

                    <div class="field">
                        <label class="label" for="bn-narrative">Summary used in the report</label>
                        <textarea id="bn-narrative" wire:model="bottleneck_narrative" rows="4" required class="textarea" aria-describedby="bn-narrative-help"></textarea>
                        <span id="bn-narrative-help" class="help">Describe what poor quality in this department costs. Write {COMPANY_NAME} where the company’s name goes.</span>
                        @error('bottleneck_narrative') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="modal-actions">
                        <button type="button" wire:click="$set('showBnModal', false)" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">{{ $bnEditMode ? 'Save changes' : 'Add department' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
