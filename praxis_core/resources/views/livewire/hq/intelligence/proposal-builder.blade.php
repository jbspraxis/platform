<div x-data="{ showTechModal: false, showLedger: false }">
    @php
        // Display labels for stored values (docs/BRAND.md section 12); the stored values stay as they are.
        $statusLabel = fn ($status) => match ($status) {
            'Draft' => ['Draft', 'pill-neutral'],
            'Pending Auth' => ['Awaiting signature', 'pill-warning'],
            'Authorized', 'Converted' => ['Signed', 'pill-success'],
            'Expired' => ['Expired', 'pill-danger'],
            default => [$status ?: 'Draft', 'pill-neutral'],
        };
        $orgStatus = fn ($status) => match ($status) { 'Lead' => 'lead', 'Active Client' => 'client', 'Lost' => 'lost', default => strtolower((string) $status) };
        $money = fn ($currency, $amount, $decimals = 2) => \App\Livewire\Hq\HqMoney::format($currency, $amount, $decimals);
        $scheduleLabels = [
            '40_30_30' => '40% at the start, 30% midway, 30% at handover',
            '50_50' => '50% at the start, 50% at handover',
            '20_20_40_20' => 'By phase: 20%, 20%, 40%, 20%',
            '100' => '100% upfront (audit or retainer)',
            'custom' => 'Custom (deposit only)',
        ];
        // Services in scope: stored values (proposals.archetype_id, also sent to the assistant) => labels
        $serviceOptions = [
            'Process Lean-Out (Six Sigma)' => 'Process improvement (Lean Six Sigma)',
            'COPQ Diagnostics (PAF Matrix)' => 'Cost of poor quality diagnosis',
            'Relational Data Vault Architecture' => 'Data consolidation into one source',
            'Custom Enterprise Software / ERP' => 'Custom software or ERP build',
            'Predictive Algorithmic Engine (AI)' => 'Forecasting and AI',
            'Digital Poka-Yoke (Governance)' => 'Controls built into the system',
            'ISO & Regulatory Gap Analysis' => 'ISO and regulatory gap analysis',
            'Sovereign Cloud & DevOps Execution' => 'Local hosting and DevOps',
            'Digital Escrow & Financial Routing' => 'Payment verification and money flows',
            'Continuous Resilience (Kaizen SLA)' => 'Ongoing support and improvement (Kaizen)',
        ];
        $phaseTitles = ['Phase 1: Diagnosis and design', 'Phase 2: Build', 'Phase 3: Go-live', 'Phase 4: Testing and handover'];
        $tabs = ['ledger' => 'All proposals', 'setup' => '1. Setup', 'ai_engine' => '2. Narrative and phases', 'preview' => '3. Review and sign'];
    @endphp

    <style>
        .pb-lines td { vertical-align: middle; }
        .pb-lines .input { padding: 6px 8px; }
        .pb-doc-wrap { margin-top: 24px; padding: 24px; background: var(--color-mist); border: 1px solid var(--color-line); border-radius: var(--radius-card); overflow-x: auto; }
        .pb-doc { width: 210mm; max-width: 100%; min-height: 297mm; margin: 0 auto; padding: 48px 56px; background: var(--color-paper); border: 1px solid var(--color-line); color: var(--color-slate); font-size: 0.875rem; line-height: 1.375rem; }
        .pb-doc h1 { font-family: var(--font-serif); font-weight: 500; font-size: 1.75rem; line-height: 2.25rem; color: var(--color-ink); margin: 0 0 24px; }
        .pb-doc h2 { font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); margin: 28px 0 8px; }
        .pb-doc p { margin: 0; white-space: pre-wrap; }
        .pb-doc .table th, .pb-doc .table td { padding: 8px 10px; }
        .pb-sign { border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; display: flex; flex-direction: column; gap: 6px; }
        .pb-sign.is-signed { border-color: var(--color-success); background: var(--color-success-tint); }
        .pb-sign dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 12px; margin: 0; font-size: 0.8125rem; }
        .pb-sign dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; }
        .pb-hash { font-size: 0.75rem; word-break: break-all; font-variant-numeric: tabular-nums; color: var(--color-muted); border-top: 1px solid var(--color-line); padding-top: 8px; }
        .pb-sig-pad { position: relative; height: 200px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); }
        canvas#hqSignaturePad { width: 100%; height: 100%; cursor: crosshair; touch-action: none; display: block; }
        .pb-panel-backdrop { position: fixed; inset: 0; z-index: 40; background: rgba(12, 20, 43, 0.4); }
        .pb-panel { position: fixed; top: 0; right: 0; bottom: 0; z-index: 41; width: 100%; max-width: 480px; display: flex; flex-direction: column; background: var(--color-paper); border-left: 1px solid var(--color-line); box-shadow: var(--shadow-float); }
        .pb-slide-enter { transition: transform var(--motion-standard) var(--ease-enter); }
        .pb-slide-leave { transition: transform var(--motion-quick) var(--ease-exit); }
        .pb-slide-out { transform: translateX(100%); }
        .pb-slide-in { transform: translateX(0); }
        @media (max-width: 768px) { .pb-doc { padding: 24px; } }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Proposals</h1>
            <p class="page-subtitle">Write a proposal, set the price and payment plan, then sign it and send the client the signing link.</p>
        </div>
    </div>

    {{-- Steps --}}
    <div class="tabs" role="tablist" aria-label="Proposal steps">
        <button type="button" role="tab" wire:click="switchTab('ledger')" class="tab {{ $activeTab === 'ledger' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'ledger' ? 'true' : 'false' }}">{{ $tabs['ledger'] }}</button>
        @if($proposalId)
            @foreach(['setup', 'ai_engine', 'preview'] as $tabKey)
                <button type="button" role="tab" wire:click="switchTab('{{ $tabKey }}')" class="tab {{ $activeTab === $tabKey ? 'is-active' : '' }}" aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}">{{ $tabs[$tabKey] }}</button>
            @endforeach
        @endif
    </div>

    {{-- Messages --}}
    <x-flash :keys="['success', 'ai_success', 'error', 'dispatch_success']" :tones="['ai_success' => 'info']" style="margin-bottom: 16px;" />

    @if($isLocked && $activeTab !== 'ledger')
        <div class="alert alert-warning" style="margin-bottom: 16px; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div class="flex items-center gap-2">
                <i data-lucide="lock"></i>
                <span>This proposal is signed and locked, so it can’t be edited.</span>
            </div>
            @if($proposal->status === 'Pending Auth')
                <button type="button" wire:click="unlockProposal" class="btn btn-secondary btn-sm" wire:loading.attr="disabled" wire:target="unlockProposal">
                    <span wire:loading.remove wire:target="unlockProposal">Unlock and edit</span>
                    <span wire:loading wire:target="unlockProposal">Unlocking…</span>
                </button>
            @endif
        </div>
    @endif

    {{-- ========================= All proposals ========================= --}}
    @if($activeTab === 'ledger')
        <section class="card" style="margin-bottom: 24px;">
            <h2 class="card-title" style="margin-bottom: 12px;">Start a proposal</h2>
            <div class="flex flex-wrap items-end gap-3" style="max-width: 640px;">
                <div class="field" style="flex: 1; min-width: 240px;">
                    <label class="label" for="pb-lead">Organisation</label>
                    <select id="pb-lead" wire:model="selectedLeadId" class="select">
                        <option value="">Choose an organisation</option>
                        @foreach($availableLeads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->name }} ({{ $orgStatus($lead->status) }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" wire:click="initiateForLead" class="btn btn-primary" wire:loading.attr="disabled" wire:target="initiateForLead">
                    <span wire:loading.remove wire:target="initiateForLead" class="inline-flex items-center gap-2"><i data-lucide="plus"></i> Create draft</span>
                    <span wire:loading wire:target="initiateForLead">Creating…</span>
                </button>
            </div>
        </section>

        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">All proposals</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Client</th>
                            <th>Created</th>
                            <th class="num" style="text-align: right;">Value</th>
                            <th>Status</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proposalsLedger as $prop)
                            @php [$label, $pill] = $statusLabel($prop->status); @endphp
                            <tr>
                                <td style="font-variant-numeric: tabular-nums;" class="text-ink">{{ $prop->proposal_ref ?: 'No reference' }}</td>
                                <td class="font-semibold text-ink">{{ $prop->client_name }}</td>
                                <td style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($prop->created_at)->format('j M Y') }}</td>
                                <td class="num">{{ $money($prop->currency, $prop->total_value) }}</td>
                                <td><span class="pill {{ $pill }}">{{ $label }}</span></td>
                                <td style="text-align: right;">
                                    <button type="button" wire:click="loadExistingProposal('{{ $prop->id }}')" class="btn btn-secondary btn-sm">Open</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <strong>No proposals yet</strong>
                                        Choose an organisation above and create a draft. Closing a free audit also drafts a proposal for you.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- ========================= 1. Setup ========================= --}}
    @if($activeTab === 'setup' && $proposalId)
        <section class="card" style="margin-bottom: 24px;">
            <h2 class="card-title" style="margin-bottom: 16px;">Document details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="field md:col-span-2">
                    <label class="label" for="pb-title">Title</label>
                    <input id="pb-title" type="text" wire:model.live.blur="title" class="input" {{ $isLocked ? 'disabled' : '' }}>
                </div>
                <div class="field">
                    <label class="label" for="pb-client">Prepared for</label>
                    <input id="pb-client" type="text" class="input" value="{{ $organization->name ?? 'Unknown organisation' }}" disabled>
                </div>
                <div class="field">
                    <label class="label" for="pb-validity">Valid for (days)</label>
                    <input id="pb-validity" type="number" wire:model.live.blur="validityDays" class="input" {{ $isLocked ? 'disabled' : '' }}>
                    <span class="help">The client’s signing link stops working after this.</span>
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom: 24px;">
            <h2 class="card-title" style="margin-bottom: 16px;">Price and payment</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" style="margin-bottom: 24px;">
                <div class="field">
                    <label class="label" for="pb-currency">Currency</label>
                    <select id="pb-currency" wire:model.live="currency" class="select" {{ $isLocked ? 'disabled' : '' }}>
                        @foreach($exchangeRates as $rate)
                            <option value="{{ $rate->currency_code }}">{{ $rate->currency_code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <span class="label" id="pb-vat">VAT</span>
                    <p class="help" style="margin: 0" aria-labelledby="pb-vat">{{ $vatNote ?: ($vatApplies ? 'VAT at ' . $vatRate . '%, as signed.' : 'No VAT, as signed.') }}</p>
                </div>
                <div class="field">
                    <label class="label" for="pb-schedule">Payment plan</label>
                    <select id="pb-schedule" wire:model.live="paymentSchedule" class="select" {{ $isLocked ? 'disabled' : '' }}>
                        @foreach($scheduleLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($paymentSchedule === 'custom')
                    <div class="field">
                        <label class="label" for="pb-deposit">Deposit (%)</label>
                        <input id="pb-deposit" type="number" step="0.01" wire:model.live.blur="customDepositPercent" class="input" {{ $isLocked ? 'disabled' : '' }}>
                    </div>
                @endif
            </div>

            <div class="card-header" style="flex-wrap: wrap;">
                <h3 class="card-title">Line items</h3>
                <select wire:change="addLineItem($event.target.value); $event.target.value='';" class="select" style="width: auto; max-width: 100%;" aria-label="Add a line from the financial dictionary" {{ $isLocked ? 'disabled' : '' }}>
                    <option value="">Add from the financial dictionary</option>
                    @foreach($financialDictionary as $dict)
                        <option value="{{ $dict->id }}">{{ $dict->item_code }}: {{ Str::limit($dict->description, 40) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="table-wrap">
                <table class="table pb-lines">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Code</th>
                            <th style="width: 45%;">Description</th>
                            <th style="width: 15%;">Quantity</th>
                            <th style="width: 20%; text-align: right;" class="num">Unit price</th>
                            <th style="width: 5%;"><span class="sr-only">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lineItems as $index => $item)
                            <tr>
                                <td><input type="text" wire:model.live.blur="lineItems.{{ $index }}.code" class="input" aria-label="Code" {{ $isLocked ? 'disabled' : '' }}></td>
                                <td><input type="text" wire:model.live.blur="lineItems.{{ $index }}.description" class="input" aria-label="Description" {{ $isLocked ? 'disabled' : '' }}></td>
                                <td><input type="text" wire:model.live.blur="lineItems.{{ $index }}.quantity" class="input" aria-label="Quantity" {{ $isLocked ? 'disabled' : '' }}></td>
                                <td><input type="number" step="0.01" wire:model.live.blur="lineItems.{{ $index }}.unit_price" wire:change="updateLine({{ $index }}, 'unit_price', $event.target.value)" class="input" style="text-align: right; font-variant-numeric: tabular-nums;" aria-label="Unit price" {{ $isLocked ? 'disabled' : '' }}></td>
                                <td>
                                    <button type="button" wire:click="removeLineItem({{ $index }})" class="btn btn-ghost btn-sm" aria-label="Remove line" title="Remove line" {{ $isLocked ? 'disabled' : '' }}><i data-lucide="trash-2"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state" style="padding: 24px;">No line items yet. Add one from the financial dictionary, or add a blank line.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <button type="button" wire:click="addLineItem()" class="btn btn-secondary" style="margin-top: 12px;" {{ $isLocked ? 'disabled' : '' }}><i data-lucide="plus"></i> Add blank line</button>

            <dl class="text-small" style="display: grid; grid-template-columns: 1fr auto; gap: 4px 0; margin: 20px 0 0 auto; max-width: 360px; font-variant-numeric: tabular-nums;">
                @if ($vatApplies)
                    <dt class="text-muted">Subtotal</dt><dd style="margin: 0; padding-left: 24px; text-align: right;">{{ $money($currency, $subtotal) }}</dd>
                    <dt class="text-muted">VAT ({{ $vatRate }}%)</dt><dd style="margin: 0; padding-left: 24px; text-align: right;">{{ $money($currency, $taxAmount) }}</dd>
                @endif
                <dt class="font-semibold text-ink" style="padding-top: 8px; border-top: 1px solid var(--color-line);">Total</dt>
                <dd class="font-semibold text-ink" style="margin: 0; padding-left: 24px; text-align: right; padding-top: 8px; border-top: 1px solid var(--color-line); font-size: 1.125rem;">{{ $money($currency, $grandTotal) }}</dd>
            </dl>
        </section>

        <div class="flex justify-end">
            <button type="button" wire:click="switchTab('ai_engine')" class="btn btn-primary">Continue to narrative <i data-lucide="arrow-right"></i></button>
        </div>
    @endif

    {{-- ========================= 2. Narrative and phases ========================= --}}
    @if($activeTab === 'ai_engine' && $proposalId)
        <section class="card" style="margin-bottom: 24px;">
            <div class="card-header" style="flex-wrap: wrap; align-items: flex-start;">
                <div>
                    <h2 class="card-title">Narrative</h2>
                    <p class="help" style="margin: 4px 0 0;">Write it yourself, or let the assistant draft it. A new draft replaces the text below; you can restore the previous text afterwards.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($previousAiState)
                        <button type="button" wire:click="revertAI" class="btn btn-secondary btn-sm">
                            <i data-lucide="rotate-ccw"></i> Restore previous text
                        </button>
                    @endif
                    <button type="button" wire:click="triggerAI" class="btn btn-primary btn-sm" {{ $isLocked ? 'disabled' : '' }} wire:loading.attr="disabled" wire:target="triggerAI">
                        <span wire:loading.remove wire:target="triggerAI" class="inline-flex items-center gap-2"><i data-lucide="sparkles"></i> Draft with the assistant</span>
                        <span wire:loading wire:target="triggerAI">Drafting…</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4" style="margin-bottom: 20px;">
                <div class="field">
                    <label class="label" for="pb-archetype">Services in scope</label>
                    <select id="pb-archetype" wire:model.live.blur="archetype" class="select" multiple style="height: 196px;" {{ $isLocked ? 'disabled' : '' }}>
                        @foreach($serviceOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="help">Hold Ctrl (Cmd on a Mac) to choose more than one.</span>
                </div>
                <div class="field lg:col-span-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="label">Technology</span>
                        <button type="button" @click="showTechModal = true" class="btn btn-ghost btn-sm">Choose technology</button>
                    </div>
                    <div style="height: 196px; overflow-y: auto; padding: 12px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-mist);">
                        @if(count($selectedTech) > 0)
                            @php
                                // Flatten the catalog to retrieve names by ID
                                $flatTechDisplay = collect($techCatalog)->flatten(1);
                                $activeNamesDisplay = $flatTechDisplay->whereIn('id', $selectedTech)->pluck('tech_name');
                            @endphp
                            <div class="flex flex-wrap gap-2">
                                @foreach($activeNamesDisplay as $name)
                                    <span class="pill pill-info" title="{{ $name }}">{{ $name }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="flex items-center justify-center text-small text-muted" style="height: 100%;">No technology chosen yet.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="field">
                    <label class="label" for="pb-summary">Executive summary</label>
                    <textarea id="pb-summary" wire:model.live.blur="aiExecutiveSummary" class="textarea" style="min-height: 140px;" {{ $isLocked ? 'disabled' : '' }}></textarea>
                </div>
                <div class="field">
                    <label class="label" for="pb-strategy">How we will do the work</label>
                    <textarea id="pb-strategy" wire:model.live.blur="aiOperationalStrategy" class="textarea" style="min-height: 180px;" {{ $isLocked ? 'disabled' : '' }}></textarea>
                </div>
                <div class="field">
                    <label class="label" for="pb-exclusions">What is not included</label>
                    <textarea id="pb-exclusions" wire:model.live.blur="exclusions" class="textarea" style="min-height: 96px;" placeholder="e.g. Data entry of old records; cloud hosting fees" {{ $isLocked ? 'disabled' : '' }}></textarea>
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom: 24px;">
            <h2 class="card-title">Phases</h2>
            <p class="help" style="margin: 4px 0 16px;">The weight (0 to 4) sets each phase’s share of the timeline.</p>

            @foreach([1, 2, 3, 4] as $n)
                <div class="grid gap-4" style="grid-template-columns: 96px 1fr; align-items: start; margin-bottom: 16px;">
                    <div class="field">
                        <label class="label" for="pb-w{{ $n }}">Weight</label>
                        <input id="pb-w{{ $n }}" type="number" step="0.5" wire:model.live.blur="w{{ $n }}" class="input" style="text-align: center; font-variant-numeric: tabular-nums;" {{ $isLocked ? 'disabled' : '' }}>
                    </div>
                    <div class="field">
                        <label class="label" for="pb-p{{ $n }}">{{ $phaseTitles[$n - 1] }}</label>
                        <textarea id="pb-p{{ $n }}" wire:model.live.blur="p{{ $n }}Content" class="textarea" style="min-height: 80px;" {{ $isLocked ? 'disabled' : '' }}></textarea>
                    </div>
                </div>
            @endforeach
        </section>

        <div class="flex flex-wrap justify-between gap-3">
            <button type="button" wire:click="switchTab('setup')" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Back to setup</button>
            <button type="button" wire:click="switchTab('preview')" class="btn btn-primary" wire:loading.attr="disabled" wire:target="switchTab('preview')">
                <span wire:loading.remove wire:target="switchTab('preview')" class="inline-flex items-center gap-2">Save and review <i data-lucide="arrow-right"></i></span>
                <span wire:loading wire:target="switchTab('preview')">Saving…</span>
            </button>
        </div>

        {{-- Technology catalogue (slide-over) --}}
        <div x-show="showTechModal" style="display: none;" wire:ignore.self>
            <div class="pb-panel-backdrop" x-show="showTechModal" x-transition.opacity @click="showTechModal = false"></div>

            <aside class="pb-panel" role="dialog" aria-modal="true" aria-labelledby="pb-tech-title"
                 x-show="showTechModal"
                 x-transition:enter="pb-slide-enter"
                 x-transition:enter-start="pb-slide-out"
                 x-transition:enter-end="pb-slide-in"
                 x-transition:leave="pb-slide-leave"
                 x-transition:leave-start="pb-slide-in"
                 x-transition:leave-end="pb-slide-out">

                <div class="flex items-center justify-between gap-4" style="padding: 20px 24px; border-bottom: 1px solid var(--color-line);">
                    <h3 id="pb-tech-title" class="section-title" style="margin: 0;">Technology catalogue</h3>
                    <button type="button" @click="showTechModal = false" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                <div style="padding: 24px; overflow-y: auto; flex: 1;">
                    @foreach($techCatalog as $category => $techs)
                        <fieldset style="border: 0; padding: 0; margin: 0 0 24px;">
                            <legend class="card-title" style="margin-bottom: 12px;">{{ $category }}</legend>
                            <div class="flex flex-col gap-3">
                                @foreach($techs as $tech)
                                    <label class="flex items-start gap-3" style="cursor: pointer;">
                                        <input type="checkbox" wire:model.live="selectedTech" value="{{ $tech->id }}" style="margin-top: 3px;" {{ $isLocked ? 'disabled' : '' }}>
                                        <span>
                                            <span class="block font-semibold text-ink text-small">{{ $tech->tech_name }}</span>
                                            <span class="block text-caption text-muted">{{ $tech->description }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <div style="padding: 16px 24px; border-top: 1px solid var(--color-line); text-align: right;">
                    <button type="button" @click="showTechModal = false" class="btn btn-primary">Done</button>
                </div>
            </aside>
        </div>
    @endif

    {{-- ========================= 3. Review and sign ========================= --}}
    @if($activeTab === 'preview' && $proposalId)
        @if(!$isLocked)
            <section class="card">
                <div class="flex items-start gap-3" style="margin-bottom: 16px;">
                    <i data-lucide="pen-tool" style="width: 24px; height: 24px; color: var(--color-deep-teal);"></i>
                    <div>
                        <h2 class="card-title">Sign and lock the proposal</h2>
                        <p class="text-small text-muted" style="margin: 4px 0 0; max-width: 45rem;">Check the document below. When it is right, sign it to lock it. We record a SHA-256 fingerprint of the signed proposal, so any change before the client signs can be detected.</p>
                    </div>
                </div>

                <form id="hq-sign-form" class="flex flex-col gap-4" style="max-width: 520px;" onsubmit="return false;">
                    <div class="field">
                        <label class="label" for="pb-signer">Your name</label>
                        <input id="pb-signer" type="text" wire:model.defer="hqSignerName" class="input" required autocomplete="name">
                        @error('hqSignerName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <div class="flex items-center justify-between">
                            <span class="label">Your signature</span>
                            <button type="button" onclick="clearHqCanvas()" class="btn btn-ghost btn-sm">Clear</button>
                        </div>
                        <div class="pb-sig-pad" wire:ignore>
                            <canvas id="hqSignaturePad" aria-label="Signature pad: draw your signature"></canvas>
                        </div>
                        @error('hqSignatureBase64') <span class="field-error">Draw your signature in the box, then sign again.</span> @enderror
                    </div>
                    <div>
                        <button type="button" onclick="submitHqSignature()" class="btn btn-primary" wire:loading.attr="disabled" wire:target="compileAndSecure">
                            <span wire:loading.remove wire:target="compileAndSecure" class="inline-flex items-center gap-2"><i data-lucide="lock"></i> Sign and lock</span>
                            <span wire:loading wire:target="compileAndSecure">Signing…</span>
                        </button>
                    </div>
                </form>
            </section>
        @else
            <section class="card">
                <div class="flex items-start gap-3" style="margin-bottom: 16px;">
                    <i data-lucide="check-circle" style="width: 24px; height: 24px; color: var(--color-success);"></i>
                    <div>
                        <h2 class="card-title">Ready to send</h2>
                        <p class="text-small text-muted" style="margin: 4px 0 0;">The proposal is signed and locked. Send the client this link to review and sign it.</p>
                    </div>
                </div>

                <div class="field" style="max-width: 720px; margin-bottom: 16px;">
                    <label class="label" for="magicLinkField">Signing link</label>
                    <input type="text" readonly value="{{ $magicLinkUrl }}" class="input" id="magicLinkField" style="font-variant-numeric: tabular-nums;">
                </div>

                @php
                    $mailSubject = 'Your proposal from JBS Praxis is ready for signature';
                    $mailBody = "Hello,\n\nYour proposal is ready. You can review and sign it here:\n" . $magicLinkUrl . "\n\nKind regards,\nThe JBS Praxis team";
                @endphp
                <div class="flex flex-wrap gap-3">
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('magicLinkField').value); alert('Link copied.');" class="btn btn-secondary">
                        <i data-lucide="copy"></i> Copy link
                    </button>
                    <a href="mailto:{{ $organization->email ?? '' }}?subject={{ rawurlencode($mailSubject) }}&body={{ rawurlencode($mailBody) }}" target="_blank" class="btn btn-primary">
                        <i data-lucide="mail"></i> Email the client
                    </a>
                    <a href="https://wa.me/?text={{ urlencode('Your proposal from JBS Praxis is ready. You can review and sign it here: ' . $magicLinkUrl) }}" target="_blank" rel="noopener" class="btn btn-secondary">
                        <i data-lucide="message-circle"></i> Send on WhatsApp
                    </a>
                </div>
            </section>
        @endif

        {{-- The document as the client sees it --}}
        <div class="pb-doc-wrap">
            <article class="pb-doc" id="renderPdfTarget">
                <header class="flex justify-between items-start gap-6" style="padding-bottom: 20px; margin-bottom: 32px; border-bottom: 1px solid var(--color-line);">
                    <img src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" style="height: 32px; width: auto;">
                    <dl style="display: grid; grid-template-columns: auto auto; gap: 2px 12px; margin: 0; font-size: 0.8125rem; text-align: right; font-variant-numeric: tabular-nums;">
                        <dt class="text-muted">Reference</dt><dd style="margin: 0;" class="text-ink font-semibold">{{ $proposal->proposal_ref ?? 'Draft' }}</dd>
                        <dt class="text-muted">Date</dt><dd style="margin: 0;" class="text-ink">{{ \Carbon\Carbon::parse($proposal->updated_at ?? now())->format('j F Y') }}</dd>
                    </dl>
                </header>

                <h1>{{ $title }}</h1>

                @php
                    // Same headings as the document the client receives (lang/en/gateway.php), so staff check what the client reads
                    $doc = fn ($key, $replace = []) => __('gateway.proposal.' . $key, $replace, 'en');
                @endphp
                <dl style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 24px; margin: 0 0 8px; padding: 16px; background: var(--color-mist); border-radius: var(--radius-card); font-size: 0.8125rem;">
                    <div><dt class="text-muted">{{ $doc('doc.organisation') }}</dt><dd style="margin: 0;" class="text-ink font-semibold">{{ $organization->name ?? 'Unknown organisation' }}</dd></div>
                    <div><dt class="text-muted">{{ $doc('doc.currency') }}</dt><dd style="margin: 0;" class="text-ink font-semibold">{{ $currency }}</dd></div>
                    <div><dt class="text-muted">{{ $doc('validity') }}</dt><dd style="margin: 0;" class="text-ink font-semibold">{{ trans_choice('gateway.proposal.validity_days', (int) $validityDays, ['days' => (int) $validityDays], 'en') }}</dd></div>
                    <div><dt class="text-muted">{{ $doc('payment_terms') }}</dt><dd style="margin: 0;" class="text-ink font-semibold">{{ $scheduleLabels[$paymentSchedule] ?? str_replace('_', '/', $paymentSchedule) }}</dd></div>
                </dl>

                <h2>{{ $doc('doc.executive_summary') }}</h2>
                <p>{{ $aiExecutiveSummary ?: 'Not written yet.' }}</p>

                <h2>{{ $doc('approach_title') }}</h2>
                <p>{{ $aiOperationalStrategy ?: 'Not written yet.' }}</p>

                @if(count($selectedTech) > 0)
                    <h2>{{ $doc('tech_title') }}</h2>
                    @php
                        // Flatten and match tech names for the preview
                        $flatTech = collect($techCatalog)->flatten(1);
                        $activeNames = $flatTech->whereIn('id', $selectedTech)->pluck('tech_name');
                    @endphp
                    <ul style="margin: 0; padding-left: 20px; columns: 2; column-gap: 24px;">
                        @foreach($activeNames as $name)
                            <li>{{ $name }}</li>
                        @endforeach
                    </ul>
                @endif

                <h2>{{ $doc('plan_title') }}</h2>
                @php $previewPhases = [$p1Content, $p2Content, $p3Content, $p4Content]; @endphp
                @if(collect($previewPhases)->filter(fn ($body) => trim((string) $body) !== '')->isNotEmpty())
                    @foreach($previewPhases as $i => $body)
                        <div style="margin-bottom: 10px;">
                            <div class="text-ink font-semibold">{{ $phaseTitles[$i] }}</div>
                            <p>{{ trim((string) $body) !== '' ? $body : 'Not written yet.' }}</p>
                        </div>
                    @endforeach
                @else
                    <p>Not written yet.</p>
                @endif

                <h2>{{ $doc('fees_title') }}</h2>
                <table class="table" style="font-size: 0.8125rem;">
                    <thead>
                        <tr>
                            <th>{{ $doc('code') }}</th>
                            <th>{{ $doc('description') }}</th>
                            <th style="text-align: center;">{{ $doc('quantity') }}</th>
                            <th class="num" style="text-align: right;">{{ $doc('amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lineItems as $line)
                            <tr>
                                <td class="text-ink" style="font-variant-numeric: tabular-nums;">{{ $line['code'] }}</td>
                                <td>{{ $line['description'] }}</td>
                                <td style="text-align: center;">{{ $line['quantity'] }}</td>
                                <td class="num text-ink">{{ $money($currency, $line['total_price']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <dl style="display: grid; grid-template-columns: 1fr auto; gap: 4px 0; margin: 12px 0 0 auto; max-width: 320px; font-size: 0.8125rem; font-variant-numeric: tabular-nums;">
                    @if ($vatApplies)
                        <dt class="text-muted">{{ $doc('subtotal') }}</dt><dd style="margin: 0; padding-left: 24px; text-align: right;">{{ $money($currency, $subtotal) }}</dd>
                        <dt class="text-muted">VAT ({{ $vatRate }}%)</dt><dd style="margin: 0; padding-left: 24px; text-align: right;">{{ $money($currency, $taxAmount) }}</dd>
                    @endif
                    <dt class="text-ink font-semibold" style="padding-top: 6px; border-top: 1px solid var(--color-line);">{{ $doc('total') }}</dt>
                    <dd class="text-ink font-semibold" style="margin: 0; padding-left: 24px; text-align: right; padding-top: 6px; border-top: 1px solid var(--color-line);">{{ $money($currency, $grandTotal) }}</dd>
                </dl>

                @if($exclusions)
                    <h2>{{ $doc('doc.exclusions') }}</h2>
                    <p>{{ $exclusions }}</p>
                @endif

                <h2>{{ $doc('doc.signatures') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- JBS Praxis --}}
                    <div class="pb-sign {{ $isLocked ? 'is-signed' : '' }}">
                        <div class="font-semibold text-ink">{{ $isLocked ? 'Signed for JBS Praxis' : 'Not signed by JBS Praxis yet' }}</div>
                        @if($isLocked)
                            <div style="height: 60px; display: flex; justify-content: center;">
                                <img src="{{ $proposal->hq_signature_b64 }}" alt="Signature of {{ $proposal->hq_signer_name }}" style="max-height: 100%; object-fit: contain;">
                            </div>
                            <dl>
                                <dt class="text-muted">Signed by</dt><dd>{{ $proposal->hq_signer_name }}</dd>
                                <dt class="text-muted">Signed on</dt><dd>{{ \Carbon\Carbon::parse($proposal->hq_signed_at)->format('j M Y, H:i') }}</dd>
                                <dt class="text-muted">IP address</dt><dd>{{ $proposal->hq_ip_address }}</dd>
                            </dl>
                            <div class="pb-hash">Document fingerprint (SHA-256): {{ $proposal->hq_signature_hash }}</div>
                        @else
                            <div class="text-caption text-muted">Sign above to lock the proposal.</div>
                        @endif
                    </div>

                    {{-- Client --}}
                    {{-- The client signing page stores 'Authorized' (ProposalGateway); 'Converted' is kept for older records --}}
                    @php $clientSigned = in_array($proposal->status, ['Authorized', 'Converted'], true); @endphp
                    <div class="pb-sign {{ $clientSigned ? 'is-signed' : '' }}">
                        <div class="font-semibold text-ink">{{ $clientSigned ? 'Signed by the client' : 'Not signed by the client yet' }}</div>
                        @if($clientSigned)
                            @if(!empty($proposal->client_signature_b64))
                                <div style="height: 60px; display: flex; justify-content: center;">
                                    <img src="{{ $proposal->client_signature_b64 }}" alt="Signature of {{ $proposal->signed_by_name }}" style="max-height: 100%; object-fit: contain;">
                                </div>
                            @endif
                            <dl>
                                <dt class="text-muted">Signatory</dt><dd>{{ $proposal->signed_by_name }}</dd>
                                <dt class="text-muted">Signed on</dt><dd>{{ \Carbon\Carbon::parse($proposal->signed_at)->format('j M Y, H:i') }}</dd>
                            </dl>
                            <div class="pb-hash">Document fingerprint (SHA-256): {{ $proposal->client_signature_hash ?? 'Not recorded' }}</div>
                        @else
                            <div class="text-caption text-muted">The client signs through the signing link.</div>
                        @endif
                    </div>
                </div>

                @if($isLocked)
                    @php
                        $baseUrl = str(route('portal.verify.contract', ['hash' => 'HASH']))->beforeLast('/HASH')->toString();
                        $qrData = "URL:{$baseUrl}/{$proposal->hq_signature_hash} | ORG:{$proposal->hq_signature_hash}";
                    @endphp
                    <div class="flex items-center gap-4" style="margin-top: 32px; padding-top: 16px; border-top: 1px solid var(--color-line);">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($qrData) }}&color=0C142B&bgcolor=FFFFFF" alt="QR code to check this proposal" style="width: 80px; height: 80px; border: 1px solid var(--color-line); padding: 4px; background: #fff;">
                        <div>
                            <div class="font-semibold text-ink">Signed in JBS Praxis HQ</div>
                            <div class="text-caption text-muted">The code holds the document fingerprint (SHA-256) recorded when JBS Praxis signed.</div>
                        </div>
                    </div>
                @endif
            </article>
        </div>
    @endif

    <script>
        document.addEventListener('livewire:navigated', () => { initHqSignature(); });
        document.addEventListener('DOMContentLoaded', () => { initHqSignature(); });

        // Sets up the signature pad when the review step opens
        window.addEventListener('initialize-canvas', () => {
            setTimeout(() => {
                initHqSignature();
            }, 150); // 150ms delay ensures Livewire has finished painting the DOM
        });

        // --- HQ SIGNATURE PAD ---
        let hqCanvas, hqCtx, isHqDrawing = false;
        function initHqSignature() {
            hqCanvas = document.getElementById('hqSignaturePad');
            if(!hqCanvas) return;
            hqCtx = hqCanvas.getContext('2d');

            // Resize canvas accurately
            hqCanvas.width = hqCanvas.parentElement.offsetWidth;
            hqCanvas.height = hqCanvas.parentElement.offsetHeight;

            hqCtx.lineWidth = 2; hqCtx.lineCap = 'round'; hqCtx.strokeStyle = '#0C142B'; // Ink

            const startPos = (e) => { isHqDrawing = true; draw(e); };
            const finishPos = () => { isHqDrawing = false; hqCtx.beginPath(); };
            const draw = (e) => {
                if (!isHqDrawing) return;
                e.preventDefault();
                let rect = hqCanvas.getBoundingClientRect();
                let x = (e.clientX || e.touches[0].clientX) - rect.left;
                let y = (e.clientY || e.touches[0].clientY) - rect.top;
                hqCtx.lineTo(x, y); hqCtx.stroke(); hqCtx.beginPath(); hqCtx.moveTo(x, y);
            };

            hqCanvas.addEventListener('mousedown', startPos); hqCanvas.addEventListener('mouseup', finishPos);
            hqCanvas.addEventListener('mousemove', draw); hqCanvas.addEventListener('touchstart', startPos, {passive: false});
            hqCanvas.addEventListener('touchend', finishPos); hqCanvas.addEventListener('touchmove', draw, {passive: false});
        }

        function clearHqCanvas() { if(hqCtx && hqCanvas) { hqCtx.clearRect(0, 0, hqCanvas.width, hqCanvas.height); hqCtx.beginPath(); } }

        function submitHqSignature() {
            if(!hqCanvas) {
                alert('The signature pad did not load. Reload the page and try again.');
                return;
            }

            const dataUrl = hqCanvas.toDataURL('image/png');
            const blank = document.createElement('canvas');
            blank.width = hqCanvas.width;
            blank.height = hqCanvas.height;

            if(dataUrl === blank.toDataURL()) {
                alert('Draw your signature in the box, then sign again.');
                return;
            }

            // Send the signature to Livewire and sign
            @this.set('hqSignatureBase64', dataUrl);
            @this.call('compileAndSecure');
        }
    </script>
</div>
