<div style="min-height: 100vh; background-color: var(--erp-bg-main); color: var(--erp-white); font-family: 'Inter', sans-serif; display: flex; flex-direction: column;" x-data="{ showTechModal: false, showLedger: false }">

    <style>
        :root { --erp-bg-base: #03050A; --erp-bg-main: #0A1128; --erp-teal: #00E5FF; --erp-red: #FF3366; --erp-amber: #FFBD2E; --erp-success: #00FF66; --erp-muted: #8A9BB2; --erp-border-dark: rgba(255, 255, 255, 0.1); }
        
        .wizard-tabs { display: flex; background: var(--erp-bg-base); border-bottom: 1px solid var(--erp-border-dark); padding: 0 40px; }
        .wizard-tab { padding: 20px 30px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.3s; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .wizard-tab:hover { color: var(--erp-white); background: rgba(0, 229, 255, 0.02); }
        .wizard-tab.active { color: var(--erp-teal); border-bottom-color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); }

        .workspace-area { flex: 1; padding: 40px; overflow-y: auto; background: var(--erp-bg-main); }
        
        .section-box { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .section-title { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-label { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); text-transform: uppercase; }
        .form-input, .form-select { width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; }
        .form-input:focus, .form-select:focus { border-color: var(--erp-teal); background: rgba(0,229,255,0.05); }
        .form-select option { background: var(--erp-bg-base); }
        .form-textarea { width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 15px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; resize: vertical; min-height: 120px; line-height: 1.6; }
        .form-textarea:focus { border-color: var(--erp-teal); }

        .btn-action { background: var(--erp-teal); color: var(--erp-bg-main); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; padding: 12px 25px; cursor: pointer; transition: 0.3s; border: 1px solid var(--erp-teal); border-radius: 2px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-action:hover { background: transparent; color: var(--erp-teal); box-shadow: 0 0 15px rgba(0, 229, 255, 0.3); }
        .btn-danger { background: rgba(255,51,102,0.1); color: var(--erp-red); border: 1px solid var(--erp-red); }
        .btn-danger:hover { background: var(--erp-red); color: #fff; }

        /* LINE ITEMS */
        .line-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .line-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-align: left; padding: 10px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; }
        .line-table td { padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.02); }
        .line-input { width: 100%; background: transparent; border: none; border-bottom: 1px solid var(--erp-border-dark); color: var(--erp-white); font-family: 'Inter', sans-serif; font-size: 0.85rem; padding: 5px; outline: none; }
        .line-input:focus { border-bottom-color: var(--erp-teal); }

        /* TECH MODAL */
        .glass-modal { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.9); backdrop-filter: blur(10px); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .tech-modal-box { background: var(--erp-bg-base); border: 1px solid var(--erp-teal); border-radius: 4px; width: 100%; max-width: 1000px; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,229,255,0.15); }
        .tech-category { margin-bottom: 30px; }
        .tech-category h4 { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal); border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 5px; margin-bottom: 15px; }
        .tech-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .tech-checkbox { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; }
        .tech-checkbox input { margin-top: 3px; accent-color: var(--erp-teal); width: 16px; height: 16px; }
        .tech-info { display: flex; flex-direction: column; }
        .tech-name { font-size: 0.85rem; color: var(--erp-white); font-weight: bold; }
        .tech-desc { font-size: 0.65rem; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; }

        /* A4 PREVIEW */
        .a4-wrapper { display: flex; justify-content: center; background: #050810; padding: 40px; overflow-y: auto; border: 1px solid var(--erp-border-dark); border-radius: 4px; margin-top: 20px; }
        .a4-page { width: 210mm; min-height: 297mm; background-color: var(--erp-bg-main); background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 0h40v40H0V0zm20 20h20v20H20V20zM0 20h20v20H0V20z' fill='%23ffffff' fill-opacity='0.01' fill-rule='evenodd'/%3E%3C/svg%3E"); color: white; position: relative; padding: 50px 60px; box-shadow: 0 25px 50px -12px rgba(0, 229, 255, 0.15); border: 1px solid rgba(0, 229, 255, 0.05); flex-shrink: 0; }
        
        /* SIGNATURE CANVAS */
        .sig-canvas-container { border: 1px dashed var(--erp-teal); background: #010308; border-radius: 4px; height: 200px; position: relative; margin-top: 10px; }
        canvas#hqSignaturePad { width: 100%; height: 100%; cursor: crosshair; touch-action: none; }
        
        /* OFFCANVAS STYLES & TRANSITIONS */
        .offcanvas-backdrop { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.8); backdrop-filter: blur(5px); z-index: 99998; }
        .offcanvas-panel { position: fixed; top: 0; right: 0; bottom: 0; width: 100%; max-width: 500px; background: var(--erp-bg-main); border-left: 1px solid var(--erp-teal); z-index: 99999; display: flex; flex-direction: column; box-shadow: -20px 0 60px rgba(0,229,255,0.15); }
        .slide-enter-active, .slide-leave-active { transition: transform 0.3s ease-in-out; }
        .slide-enter-start, .slide-leave-end { transform: translateX(100%); }
        .slide-enter-end, .slide-leave-start { transform: translateX(0); }
    </style>

    <!-- TOP WIZARD NAVIGATION -->
    <div class="wizard-tabs">
        <div wire:click="switchTab('ledger')" class="wizard-tab {{ $activeTab === 'ledger' ? 'active' : '' }}">
            <i data-lucide="database" style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;"></i> LEDGER VAULT
        </div>
        @if($proposalId)
            <div wire:click="switchTab('setup')" class="wizard-tab {{ $activeTab === 'setup' ? 'active' : '' }}">1. PARAMETRIC SETUP</div>
            <div wire:click="switchTab('ai_engine')" class="wizard-tab {{ $activeTab === 'ai_engine' ? 'active' : '' }}">2. AI & SPRINT ENGINE</div>
            <div wire:click="switchTab('preview')" class="wizard-tab {{ $activeTab === 'preview' ? 'active' : '' }}">3. REVIEW & DISPATCH</div>
        @endif
    </div>

    <!-- MAIN WORKSPACE -->
    <div class="workspace-area">
        
        <!-- GLOBAL MESSAGES -->
        @if(session()->has('success')) <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 15px; font-family: 'Roboto Mono'; font-size: 0.8rem; margin-bottom: 20px;">{{ session('success') }}</div> @endif
        @if(session()->has('ai_success')) <div style="background: rgba(0,229,255,0.1); border: 1px solid #00E5FF; color: #00E5FF; padding: 15px; font-family: 'Roboto Mono'; font-size: 0.8rem; margin-bottom: 20px;">{{ session('ai_success') }}</div> @endif
        @if(session()->has('error')) <div style="background: rgba(255,51,102,0.1); border: 1px solid #FF3366; color: #FF3366; padding: 15px; font-family: 'Roboto Mono'; font-size: 0.8rem; margin-bottom: 20px;">{{ session('error') }}</div> @endif
        @if(session()->has('dispatch_success')) <div style="background: rgba(255,189,46,0.1); border: 1px solid #FFBD2E; color: #FFBD2E; padding: 15px; font-family: 'Roboto Mono'; font-size: 0.8rem; margin-bottom: 20px; font-weight: bold;">{{ session('dispatch_success') }}</div> @endif

        @if($isLocked)
            <div style="background: rgba(255,51,102,0.1); border: 1px solid #FF3366; color: #FF3366; padding: 15px; font-family: 'Roboto Mono'; font-size: 0.85rem; margin-bottom: 20px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="lock"></i> DOCUMENT CRYPTOGRAPHICALLY LOCKED. EDITS DISABLED.
                </div>
                @if($proposal->status === 'Pending Auth')
                    <button wire:click="unlockProposal" style="background: var(--erp-red); color: #FFF; border: none; padding: 6px 12px; font-family: 'Roboto Mono'; font-size: 0.7rem; cursor: pointer; border-radius: 2px;" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="unlockProposal">REVOKE DISPATCH & UNLOCK</span>
                        <span wire:loading wire:target="unlockProposal">REVOKING...</span>
                    </button>
                @endif
            </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 0: LEDGER VAULT                        -->
        <!-- ========================================== -->
        @if($activeTab === 'ledger')
            <div class="section-box">
                <div class="section-title">
                    <span>> INITIATE OR LOAD BLUEPRINT</span>
                </div>
                
                <div style="display: flex; gap: 15px; max-width: 600px; margin-bottom: 40px;">
                    <select wire:model="selectedLeadId" class="form-select">
                        <option value="">-- SELECT TARGET ENTERPRISE --</option>
                        @foreach($availableLeads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->name }} ({{ $lead->status }})</option>
                        @endforeach
                    </select>
                    <button wire:click="initiateForLead" class="btn-action" style="white-space: nowrap;" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="initiateForLead"><i data-lucide="zap" style="width: 16px; height: 16px; display: inline-block; vertical-align: text-bottom;"></i> INITIATE DRAFT</span>
                        <span wire:loading wire:target="initiateForLead">INITIALIZING...</span>
                    </button>
                </div>

                <div class="section-title">> SECURED DEPLOYMENTS VAULT</div>
                <table class="line-table" style="font-family: 'Inter', sans-serif;">
                    <thead>
                        <tr>
                            <th>SOW REF</th>
                            <th>CLIENT</th>
                            <th>DATE</th>
                            <th>VALUE</th>
                            <th>STATUS</th>
                            <th style="text-align: right;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proposalsLedger as $prop)
                            <tr>
                                <td style="font-family: 'Roboto Mono', monospace; color: var(--erp-teal);">{{ $prop->proposal_ref }}</td>
                                <td style="font-weight: bold;">{{ $prop->client_name }}</td>
                                <td style="font-size: 0.75rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($prop->created_at)->format('M d, Y') }}</td>
                                <td style="font-family: 'Roboto Mono', monospace;">{{ $prop->currency }} {{ number_format($prop->total_value, 2) }}</td>
                                <td>
                                    @if(in_array($prop->status, ['Authorized', 'Converted'])) <span style="color: #00FF66; font-family: 'Roboto Mono'; font-size: 0.7rem; font-weight: bold;">SECURED</span>
                                    @elseif($prop->status === 'Pending Auth') <span style="color: #FFBD2E; font-family: 'Roboto Mono'; font-size: 0.7rem; font-weight: bold;">PENDING</span>
                                    @else <span style="color: var(--erp-muted); font-family: 'Roboto Mono'; font-size: 0.7rem; font-weight: bold;">DRAFT</span> @endif
                                </td>
                                <td style="text-align: right;">
                                    <button wire:click="loadExistingProposal('{{ $prop->id }}')" class="btn-action" style="padding: 6px 12px; font-size: 0.65rem; background: transparent; border-color: var(--erp-muted); color: var(--erp-muted);">OPEN</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align: center; color: var(--erp-muted); padding: 30px;">NO SOWS IN VAULT</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 1: PARAMETRIC SETUP                    -->
        <!-- ========================================== -->
        @if($activeTab === 'setup' && $proposalId)
            
            <div class="section-box">
                <div class="section-title"><span>> I. METADATA & CLEARANCES</span></div>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">DOCUMENT TITLE</label>
                        <input type="text" wire:model.blur="title" class="form-input" {{ $isLocked ? 'disabled' : '' }}>
                    </div>
                    <div class="form-group">
                        <label class="form-label">PREPARED FOR (LOCKED)</label>
                        <input type="text" class="form-input" value="{{ $organization->name ?? 'Unknown' }}" disabled style="opacity: 0.5; border-style: dashed;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">TOKEN VALIDITY (DAYS)</label>
                        <input type="number" wire:model.blur="validityDays" class="form-input" {{ $isLocked ? 'disabled' : '' }}>
                    </div>
                </div>
            </div>

            <div class="section-box">
                <div class="section-title"><span>> II. COMMERCIAL MATRIX</span></div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">CURRENCY</label>
                        <select wire:model.live="currency" class="form-select" {{ $isLocked ? 'disabled' : '' }}>
                            @foreach($exchangeRates as $rate)
                                <option value="{{ $rate->currency_code }}">{{ $rate->currency_code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">VAT RATE (%)</label>
                        <input type="number" step="0.01" wire:model.blur="vatRate" class="form-input" {{ $isLocked ? 'disabled' : '' }}>
                    </div>
                    <div class="form-group">
                        <label class="form-label">PAYMENT SCHEDULE</label>
                        <select wire:model.live="paymentSchedule" class="form-select" {{ $isLocked ? 'disabled' : '' }}>
                            <option value="40_30_30">40% Mobilization | 30% Mid | 30% Handover</option>
                            <option value="50_50">50% Mobilization | 50% Handover</option>
                            <option value="20_20_40_20">20% Ph1 | 20% Ph2 | 40% Ph3 | 20% Ph4</option>
                            <option value="100">100% Upfront (Audit/Retainer)</option>
                            <option value="custom">Custom (Deposit Anchor Only)</option>
                        </select>
                    </div>
                    @if($paymentSchedule === 'custom')
                        <div class="form-group">
                            <label class="form-label" style="color: var(--erp-amber);">INITIAL DEPOSIT (%)</label>
                            <input type="number" step="0.01" wire:model.blur="customDepositPercent" class="form-input" style="border-color: var(--erp-amber);" {{ $isLocked ? 'disabled' : '' }}>
                        </div>
                    @endif
                </div>

                <div class="section-title" style="margin-top: 30px;">
                    <span>> LINE ITEMS INJECTION</span>
                    <select wire:change="addLineItem($event.target.value); $event.target.value='';" class="form-select" style="width: auto; padding: 4px 10px; font-size: 0.7rem; border-color: var(--erp-teal);" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">+ ADD FROM MDM DICTIONARY...</option>
                        @foreach($financialDictionary as $dict)
                            <option value="{{ $dict->id }}">{{ $dict->item_code }} - {{ Str::limit($dict->description, 40) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <table class="line-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">CODE</th>
                            <th style="width: 45%;">DESCRIPTION</th>
                            <th style="width: 15%;">QTY/METRIC</th>
                            <th style="width: 20%;">UNIT PRICE</th>
                            <th style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lineItems as $index => $item)
                            <tr>
                                <td><input type="text" wire:model.blur="lineItems.{{ $index }}.code" class="line-input" {{ $isLocked ? 'disabled' : '' }}></td>
                                <td><input type="text" wire:model.blur="lineItems.{{ $index }}.description" class="line-input" {{ $isLocked ? 'disabled' : '' }}></td>
                                <td><input type="text" wire:model.blur="lineItems.{{ $index }}.quantity" class="line-input" {{ $isLocked ? 'disabled' : '' }}></td>
                                <td><input type="number" step="0.01" wire:model.blur="lineItems.{{ $index }}.unit_price" wire:change="updateLine({{ $index }}, 'unit_price', $event.target.value)" class="line-input" {{ $isLocked ? 'disabled' : '' }}></td>
                                <td>
                                    <button wire:click="removeLineItem({{ $index }})" class="btn-action btn-danger" style="padding: 6px; font-size: 1rem;" {{ $isLocked ? 'disabled' : '' }}>×</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO FINANCIAL PARAMETERS INJECTED.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <button wire:click="addLineItem()" class="btn-action" style="background: transparent; color: var(--erp-muted); border: 1px dashed var(--erp-muted); width: 100%; justify-content: center;" {{ $isLocked ? 'disabled' : '' }}>+ ADD CUSTOM BLANK ROW</button>

                <div style="text-align: right; margin-top: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted);">
                    <div>SUBTOTAL: {{ $currency }} {{ number_format($subtotal, 2) }}</div>
                    <div>VAT ({{ $vatRate }}%): {{ $currency }} {{ number_format($taxAmount, 2) }}</div>
                    <div style="font-size: 1.2rem; color: var(--erp-teal); font-weight: bold; margin-top: 10px;">TOTAL: {{ $currency }} {{ number_format($grandTotal, 2) }}</div>
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end;">
                <button wire:click="switchTab('ai_engine')" class="btn-action">PROCEED TO NARRATIVE & SPRINTS ➔</button>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 2: AI ENGINE & NARRATIVE               -->
        <!-- ========================================== -->
        @if($activeTab === 'ai_engine' && $proposalId)
            
            <div class="section-box" style="background: rgba(0, 229, 255, 0.02); border-color: var(--erp-teal);">
                <div class="section-title">
                    <span style="color: var(--erp-teal);">> AI HYBRID NARRATIVE GENERATOR</span>
                    <div style="display: flex; gap: 10px;">
                        @if($previousAiState)
                            <button wire:click="revertAI" class="btn-action" style="background: transparent; border: 1px solid var(--erp-amber); color: var(--erp-amber); padding: 6px 12px; font-size: 0.7rem;">
                                <i data-lucide="rotate-ccw" style="width: 14px; height: 14px;"></i> REVERT TO PREVIOUS
                            </button>
                        @endif
                        <button wire:click="triggerAI" class="btn-action" style="padding: 6px 12px; font-size: 0.7rem;" {{ $isLocked ? 'disabled' : '' }} wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="triggerAI"><i data-lucide="cpu" style="width: 14px; height: 14px;"></i> TRIGGER AI OVERWRITE</span>
                            <span wire:loading wire:target="triggerAI">ANALYZING MATRIX...</span>
                        </button>
                    </div>
                </div>
                
                <div class="form-grid" style="grid-template-columns: 1fr 2fr;">
                    <div class="form-group">
                        <label class="form-label" style="color: var(--erp-teal);">TARGET ARCHETYPES (CTRL/CMD + CLICK TO MULTI-SELECT)</label>
                        <select wire:model.blur="archetype" class="form-select" multiple style="height: 180px; border-color: var(--erp-teal);" {{ $isLocked ? 'disabled' : '' }}>
                            <option value="Process Lean-Out (Six Sigma)">1. Process Lean-Out (Six Sigma Mapping)</option>
                            <option value="COPQ Diagnostics (PAF Matrix)">2. COPQ Diagnostics (PAF Matrix)</option>
                            <option value="Relational Data Vault Architecture">3. Relational Data Vault Architecture</option>
                            <option value="Custom Enterprise Software / ERP">4. Custom Enterprise Software / ERP Build</option>
                            <option value="Predictive Algorithmic Engine (AI)">5. Predictive Algorithmic Engine (AI Foresight)</option>
                            <option value="Digital Poka-Yoke (Governance)">6. Digital Poka-Yoke (Code as Law/Governance)</option>
                            <option value="ISO & Regulatory Gap Analysis">7. ISO & Regulatory Gap Analysis</option>
                            <option value="Sovereign Cloud & DevOps Execution">8. Sovereign Cloud & DevOps Execution</option>
                            <option value="Digital Escrow & Financial Routing">9. Digital Escrow & Financial Routing</option>
                            <option value="Continuous Resilience (Kaizen SLA)">10. Continuous Resilience (Kaizen Handover)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; justify-content: space-between;">
                            <span>TECH STACK CONSTRAINT</span>
                            <button @click="showTechModal = true" style="background: transparent; border: none; color: var(--erp-teal); font-family: 'Roboto Mono'; font-size: 0.65rem; cursor: pointer; text-decoration: underline;" type="button">[ OPEN CATALOG ]</button>
                        </label>
                        
                        <div style="background: rgba(0,0,0,0.5); padding: 12px; border: 1px solid rgba(255,255,255,0.1); border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); height: 180px; overflow-y: auto;">
                            @if(count($selectedTech) > 0)
                                @php
                                    // Flatten the catalog to retrieve names by ID
                                    $flatTechDisplay = collect($techCatalog)->flatten(1);
                                    $activeNamesDisplay = $flatTechDisplay->whereIn('id', $selectedTech)->pluck('tech_name');
                                @endphp
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;">
                                    @foreach($activeNamesDisplay as $name)
                                        <div style="background: rgba(0, 229, 255, 0.05); border: 1px solid rgba(0, 229, 255, 0.15); padding: 6px; border-radius: 2px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $name }}">
                                            {{ $name }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div style="display: flex; height: 100%; align-items: center; justify-content: center; color: var(--erp-muted);">
                                    No architectural constraints selected.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">EXECUTIVE SUMMARY</label>
                    <textarea wire:model.blur="aiExecutiveSummary" class="form-textarea" {{ $isLocked ? 'disabled' : '' }}></textarea>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">OPERATIONAL STRATEGY (TECHNICAL DIRECTIVE)</label>
                    <textarea wire:model.blur="aiOperationalStrategy" class="form-textarea" style="min-height: 180px;" {{ $isLocked ? 'disabled' : '' }}></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="color: var(--erp-amber);">BOUNDARIES & EXCLUSIONS</label>
                    <textarea wire:model.blur="exclusions" class="form-textarea" style="border-color: rgba(255,189,46,0.3); min-height: 80px;" placeholder="List what is explicitly out of scope to protect the SLA..." {{ $isLocked ? 'disabled' : '' }}></textarea>
                </div>
            </div>

            <div class="section-box">
                <div class="section-title"><span>> DEPLOYMENT SPRINTS (PHASES 1-4)</span></div>
                
                <div class="form-grid" style="grid-template-columns: 80px 1fr; align-items: start; margin-bottom: 15px;">
                    <div class="form-group"><label class="form-label">WT(0-4)</label><input type="number" step="0.5" wire:model.blur="w1" class="form-input" style="text-align: center;" {{ $isLocked ? 'disabled' : '' }}></div>
                    <div class="form-group"><label class="form-label text-erp-teal">PHASE 1: DIAGNOSTIC & BLUEPRINT</label><textarea wire:model.blur="p1Content" class="form-textarea" style="min-height: 80px;" {{ $isLocked ? 'disabled' : '' }}></textarea></div>
                </div>
                <div class="form-grid" style="grid-template-columns: 80px 1fr; align-items: start; margin-bottom: 15px;">
                    <div class="form-group"><label class="form-label">WT(0-4)</label><input type="number" step="0.5" wire:model.blur="w2" class="form-input" style="text-align: center;" {{ $isLocked ? 'disabled' : '' }}></div>
                    <div class="form-group"><label class="form-label text-erp-teal">PHASE 2: ARCHITECTURE BUILD</label><textarea wire:model.blur="p2Content" class="form-textarea" style="min-height: 80px;" {{ $isLocked ? 'disabled' : '' }}></textarea></div>
                </div>
                <div class="form-grid" style="grid-template-columns: 80px 1fr; align-items: start; margin-bottom: 15px;">
                    <div class="form-group"><label class="form-label">WT(0-4)</label><input type="number" step="0.5" wire:model.blur="w3" class="form-input" style="text-align: center;" {{ $isLocked ? 'disabled' : '' }}></div>
                    <div class="form-group"><label class="form-label text-erp-teal">PHASE 3: EXECUTION ENGINE</label><textarea wire:model.blur="p3Content" class="form-textarea" style="min-height: 80px;" {{ $isLocked ? 'disabled' : '' }}></textarea></div>
                </div>
                <div class="form-grid" style="grid-template-columns: 80px 1fr; align-items: start; margin-bottom: 15px;">
                    <div class="form-group"><label class="form-label">WT(0-4)</label><input type="number" step="0.5" wire:model.blur="w4" class="form-input" style="text-align: center;" {{ $isLocked ? 'disabled' : '' }}></div>
                    <div class="form-group"><label class="form-label text-erp-teal">PHASE 4: QA & HANDOVER</label><textarea wire:model.blur="p4Content" class="form-textarea" style="min-height: 80px;" {{ $isLocked ? 'disabled' : '' }}></textarea></div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between;">
                <button wire:click="switchTab('setup')" class="btn-action" style="background: transparent; color: var(--erp-white); border-color: var(--erp-border-dark);">BACK TO SETUP</button>
                <button wire:click="switchTab('preview')" class="btn-action" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="switchTab('preview')">SAVE & COMPILE DOCUMENT ➔</span>
                    <span wire:loading wire:target="switchTab('preview')">COMPILING MATRIX...</span>
                </button>
            </div>

            <!-- ALPINE MODAL: TECH STACK -->
            <div style="position: relative; z-index: 99999;" x-show="showTechModal" style="display: none;" wire:ignore.self>
                <div class="offcanvas-backdrop" 
                     x-show="showTechModal" 
                     x-transition.opacity 
                     @click="showTechModal = false"></div>
                     
                <div class="offcanvas-panel" 
                     x-show="showTechModal" 
                     x-transition:enter="slide-enter-active" 
                     x-transition:enter-start="slide-enter-start" 
                     x-transition:enter-end="slide-enter-end" 
                     x-transition:leave="slide-leave-active" 
                     x-transition:leave-start="slide-leave-start" 
                     x-transition:leave-end="slide-leave-end">
                    
                    <div style="padding: 25px 30px; border-bottom: 1px solid var(--erp-border-dark); background: var(--erp-bg-base); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-family: 'Roboto Mono', monospace; font-size: 1rem; color: var(--erp-teal); margin: 0;">> MDM ARCHITECTURE CATALOG</h3>
                        <button @click="showTechModal = false" style="background: transparent; border: none; color: var(--erp-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
                    </div>
                    
                    <div style="padding: 30px; overflow-y: auto; flex: 1;">
                        @foreach($techCatalog as $category => $techs)
                            <div class="tech-category">
                                <h4>{{ $category }}</h4>
                                <div class="tech-grid" style="grid-template-columns: 1fr;"> 
                                    @foreach($techs as $tech)
                                        <label class="tech-checkbox">
                                            <input type="checkbox" wire:model.live="selectedTech" value="{{ $tech->id }}" {{ $isLocked ? 'disabled' : '' }}>
                                            <div class="tech-info">
                                                <span class="tech-name">{{ $tech->tech_name }}</span>
                                                <span class="tech-desc" style="display: block; margin-top: 2px;">{{ $tech->description }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div style="padding: 20px 30px; border-top: 1px solid var(--erp-border-dark); background: var(--erp-bg-base); text-align: right;">
                        <button @click="showTechModal = false" class="btn-action">CONFIRM SELECTION</button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- TAB 3: PREVIEW & DISPATCH (CRYPTOGRAPHY)   -->
        <!-- ========================================== -->
        @if($activeTab === 'preview' && $proposalId)
            
            @if(!$isLocked)
                <div class="section-box" style="background: rgba(255, 189, 46, 0.05); border-color: var(--erp-amber); text-align: center;">
                    <i data-lucide="fingerprint" style="width: 48px; height: 48px; color: var(--erp-amber); margin: 0 auto 15px auto;"></i>
                    <h3 style="font-family: 'Montserrat'; font-size: 1.5rem; color: var(--erp-white); margin-bottom: 10px; text-transform: uppercase;">HQ ORIGINATOR AUTHORIZATION</h3>
                    <p style="font-size: 0.85rem; color: var(--erp-muted); max-width: 600px; margin: 0 auto 20px auto;">Review the A4 document below. If correct, provide your cryptographic signature to secure the SHA-256 hash. This prevents tampering prior to client signature.</p>
                    
                    <form id="hq-sign-form" style="max-width: 500px; margin: 0 auto; text-align: left;">
                        <input type="text" wire:model.defer="hqSignerName" class="form-input" style="text-align: center; font-weight: bold; letter-spacing: 1px;" placeholder="Level 4/5 Originator Name" required>
                        <div class="sig-canvas-container" wire:ignore>
                            <span style="position: absolute; top: 10px; left: 10px; font-family: 'Roboto Mono'; font-size: 0.65rem; color: var(--erp-amber);">DRAW SIGNATURE:</span>
                            <button type="button" onclick="clearHqCanvas()" style="position: absolute; top: 10px; right: 10px; background: transparent; border: none; color: var(--erp-muted); font-size: 0.65rem; font-family: 'Roboto Mono'; cursor: pointer; z-index: 10;">CLEAR</button>
                            <canvas id="hqSignaturePad"></canvas>
                        </div>
                        <button type="button" onclick="submitHqSignature()" class="btn-action" style="width: 100%; justify-content: center; background: var(--erp-amber); color: #000; border-color: var(--erp-amber); margin-top: 10px;" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="compileAndSecure">LOCK DOCUMENT & GENERATE MAGIC LINK</span>
                            <span wire:loading wire:target="compileAndSecure">ENCRYPTING...</span>
                        </button>
                    </form>
                </div>
            @else
                <div class="section-box" style="background: rgba(0, 255, 102, 0.05); border-color: #00FF66; text-align: center;">
                    <i data-lucide="check-circle" style="width: 48px; height: 48px; color: #00FF66; margin: 0 auto 15px auto;"></i>
                    <h3 style="font-family: 'Montserrat'; font-size: 1.5rem; color: var(--erp-white); margin-bottom: 10px; text-transform: uppercase;">DOCUMENT SECURED & READY FOR DISPATCH</h3>
                    <p style="font-size: 0.85rem; color: var(--erp-muted); margin-bottom: 20px;">The Cryptographic Hash has been sealed. Dispatch this Magic Link to the client's executive team.</p>
                    
                    <input type="text" readonly value="{{ $magicLinkUrl }}" class="form-input" style="text-align: center; color: var(--erp-teal); font-family: 'Roboto Mono'; font-size: 1.1rem; max-width: 700px; margin: 0 auto 20px auto; display: block;" id="magicLinkField">
                    
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <button onclick="navigator.clipboard.writeText(document.getElementById('magicLinkField').value); alert('Link Copied to Clipboard!');" class="btn-action" style="background: transparent; border-color: var(--erp-muted); color: var(--erp-white);">
                            <i data-lucide="copy" style="width: 16px; height: 16px;"></i> COPY LINK
                        </button>
                        
                        <a href="mailto:{{ $organization->email ?? '' }}?subject=Secure Statement of Work - JBS Praxis&body=Please review and authorize the attached architectural proposal: {{ $magicLinkUrl }}" target="_blank" class="btn-action" style="background: var(--erp-teal); border-color: var(--erp-teal); color: #000; text-decoration: none;">
                            <i data-lucide="mail" style="width: 16px; height: 16px;"></i> EMAIL CLIENT
                        </a>

                        <a href="https://wa.me/?text={{ urlencode('Secure SOW Link from JBS Praxis: ' . $magicLinkUrl) }}" target="_blank" class="btn-action" style="background: #25D366; border-color: #25D366; color: #FFF; text-decoration: none;">
                            <i data-lucide="message-circle" style="width: 16px; height: 16px;"></i> WHATSAPP
                        </a>
                    </div>
                </div>
            @endif

            <!-- THE A4 RENDER (Mirrors the Client Gateway output) -->
            <div class="a4-wrapper">
                <div class="a4-page" id="renderPdfTarget">
                    <div class="doc-watermark" style="top: 40px; right: -30px; font-family: 'Roboto Mono', monospace; font-size: 8rem; color: rgba(255,255,255,0.015); font-weight: 900; pointer-events: none; transform: rotate(-90deg); transform-origin: top right; position: absolute; letter-spacing: -2px;">SOW.BINDING</div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; position: relative; z-index: 10;">
                        <div style="display: flex; items-center: center; gap: 15px;">
                            <div style="width: 40px; height: 40px; background: var(--erp-teal); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-family: 'Montserrat'; font-weight: 900; color: var(--erp-main); font-size: 1.2rem;">P</div>
                            <div>
                                <div style="font-family: 'Montserrat'; font-weight: 900; letter-spacing: 2px; font-size: 1rem; color: #FFF;">JBS PRAXIS</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-teal); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Enterprise Architecture</div>
                            </div>
                        </div>
                        <div style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted);">
                            <div style="margin-bottom: 4px;">REF: <span style="color: #FFF; font-weight: bold;">{{ $proposal->proposal_ref ?? 'DRAFT' }}</span></div>
                            <div>DATE: <span style="color: #FFF;">{{ \Carbon\Carbon::parse($proposal->updated_at ?? now())->format('F j, Y') }}</span></div>
                        </div>
                    </div>

                    <div style="position: relative; z-index: 10;">
                        <h1 style="font-family: 'Montserrat'; font-size: 1.8rem; font-weight: 900; color: #FFF; text-transform: uppercase; border-bottom: 2px solid var(--erp-teal); padding-bottom: 15px; margin-bottom: 30px; letter-spacing: -0.5px;">{{ $title }}</h1>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; background: rgba(255,255,255,0.02); padding: 20px; border: 1px solid rgba(255,255,255,0.05); font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">
                            <div><span style="color: var(--erp-muted);">CLIENT:</span> <span style="color: #FFF; font-weight: bold;">{{ strtoupper($organization->name ?? 'UNKNOWN') }}</span></div>
                            <div><span style="color: var(--erp-muted);">CURRENCY:</span> <span style="color: var(--erp-teal); font-weight: bold;">{{ $currency }}</span></div>
                            <div><span style="color: var(--erp-muted);">VALIDITY:</span> <span style="color: #FFF; font-weight: bold;">{{ $validityDays }} DAYS</span></div>
                            <div><span style="color: var(--erp-muted);">PAYMENT PLAN:</span> <span style="color: #FFF; font-weight: bold;">{{ str_replace('_', '/', $paymentSchedule) }}</span></div>
                        </div>

                        <!-- EXECUTIVE SUMMARY -->
                        <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 10px; text-transform: uppercase;">I. EXECUTIVE SUMMARY</h2>
                        <p style="font-size: 0.8rem; color: #B0B8C4; line-height: 1.6; margin-bottom: 25px; white-space: pre-wrap; text-align: justify;">{{ $aiExecutiveSummary ?: 'Pending AI Generation...' }}</p>

                        <!-- OPERATIONAL STRATEGY -->
                        <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 10px; text-transform: uppercase;">II. OPERATIONAL STRATEGY</h2>
                        <p style="font-size: 0.8rem; color: #B0B8C4; line-height: 1.6; margin-bottom: 20px; white-space: pre-wrap; text-align: justify;">{{ $aiOperationalStrategy ?: 'Pending AI Generation...' }}</p>

                        <!-- TECH STACK PRINT OUT -->
                        @if(count($selectedTech) > 0)
                            <div style="background: rgba(0,229,255,0.02); border-left: 2px solid var(--erp-teal); padding: 15px; margin-bottom: 25px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem;">
                                <strong style="color: var(--erp-teal); display: block; margin-bottom: 10px; text-transform: uppercase;">> ARCHITECTURE & COMPLIANCE STACK:</strong>
                                <ul style="list-style-type: none; padding: 0; margin: 0; color: #E2E8F0; display: grid; grid-template-columns: 1fr 1fr; gap: 5px;">
                                    @php
                                        // Flatten and match tech names for the preview
                                        $flatTech = collect($techCatalog)->flatten(1);
                                        $activeNames = $flatTech->whereIn('id', $selectedTech)->pluck('tech_name');
                                    @endphp
                                    @foreach($activeNames as $name)
                                        <li>- {{ $name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- COMMERCIALS -->
                        <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 10px; text-transform: uppercase;">III. COMMERCIAL BLUEPRINT</h2>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">
                            <thead>
                                <tr style="background: rgba(0, 229, 255, 0.1); color: var(--erp-teal);">
                                    <th style="padding: 10px; border: 1px solid rgba(255,255,255,0.1); text-align: left;">CODE</th>
                                    <th style="padding: 10px; border: 1px solid rgba(255,255,255,0.1); text-align: left;">DESCRIPTION</th>
                                    <th style="padding: 10px; border: 1px solid rgba(255,255,255,0.1); text-align: center;">QTY</th>
                                    <th style="padding: 10px; border: 1px solid rgba(255,255,255,0.1); text-align: right;">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lineItems as $line)
                                    <tr>
                                        <td style="padding: 10px; border: 1px solid rgba(255,255,255,0.05); color: #FFF; font-weight: bold;">{{ $line['code'] }}</td>
                                        <td style="padding: 10px; border: 1px solid rgba(255,255,255,0.05); color: var(--erp-muted);">{{ $line['description'] }}</td>
                                        <td style="padding: 10px; border: 1px solid rgba(255,255,255,0.05); color: #FFF; text-align: center;">{{ $line['quantity'] }}</td>
                                        <td style="padding: 10px; border: 1px solid rgba(255,255,255,0.05); color: #FFF; text-align: right;">{{ number_format($line['total_price'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 40px;">
                            <div style="margin-bottom: 5px;">SUBTOTAL: {{ $currency }} {{ number_format($subtotal, 2) }}</div>
                            <div style="margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">TAX ({{ $vatRate }}%): {{ $currency }} {{ number_format($taxAmount, 2) }}</div>
                            <div style="font-size: 1.1rem; font-weight: bold; color: var(--erp-teal);">TOTAL: {{ $currency }} {{ number_format($grandTotal, 2) }}</div>
                        </div>

                        <!-- EXCLUSIONS -->
                        @if($exclusions)
                            <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-red); margin-bottom: 10px; text-transform: uppercase;">IV. BOUNDARIES & EXCLUSIONS</h2>
                            <p style="font-size: 0.75rem; color: #FF8A8A; line-height: 1.6; margin-bottom: 30px; white-space: pre-wrap;">{{ $exclusions }}</p>
                        @endif

                        <!-- DUAL SIGNATURE BLOCK -->
                        <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 15px; text-transform: uppercase;">{{ $exclusions ? 'V.' : 'IV.' }} EXECUTIVE AUTHORIZATION</h2>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
                            
                            <!-- PRAXIS STAMP (HQ) -->
                            <div style="border: 1px solid {{ $isLocked ? 'rgba(0, 229, 255, 0.4)' : 'rgba(255,255,255,0.1)' }}; background: {{ $isLocked ? 'rgba(0, 229, 255, 0.02)' : 'rgba(0,0,0,0.2)' }}; padding: 25px; display: flex; flex-direction: column;">
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; margin-bottom: 15px; border-bottom: 1px dashed rgba(255,255,255,0.2); padding-bottom: 10px; color: {{ $isLocked ? 'var(--erp-teal)' : 'var(--erp-muted)' }}; text-align: center;">
                                    {{ $isLocked ? '✓ ORIGINATOR SECURED' : 'AWAITING HQ BINDING' }}
                                </div>
                                @if($isLocked)
                                    <div style="height: 60px; margin-bottom: 10px; display: flex; justify-content: center;">
                                        <img src="{{ $proposal->hq_signature_b64 }}" style="max-height: 100%; object-fit: contain;">
                                    </div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>DISPATCHER:</span><span>{{ strtoupper($proposal->hq_signer_name) }}</span></div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>TIMESTAMP:</span><span>{{ \Carbon\Carbon::parse($proposal->hq_signed_at)->format('Y-m-d H:i:s') }}</span></div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>NODE IP:</span><span>{{ $proposal->hq_ip_address }}</span></div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.55rem; color: var(--erp-teal); font-weight: bold; word-break: break-all; margin-top: 10px; border-top: 1px dashed rgba(0,229,255,0.3); padding-top: 10px; text-align: center;">
                                        {{ $proposal->hq_signature_hash }}
                                    </div>
                                @else
                                    <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--erp-muted); font-size: 0.7rem; font-family: 'Roboto Mono';">Sign form above to bind.</div>
                                @endif
                            </div>

                            <!-- CLIENT STAMP -->
                            <div style="border: 1px solid {{ ($isLocked && $proposal->status === 'Converted') ? '#00FF66' : 'rgba(255,255,255,0.1)' }}; background: {{ ($isLocked && $proposal->status === 'Converted') ? 'rgba(0, 255, 102, 0.05)' : 'rgba(0,0,0,0.2)' }}; padding: 25px; display: flex; flex-direction: column;">
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; margin-bottom: 15px; border-bottom: 1px dashed rgba(255,255,255,0.2); padding-bottom: 10px; color: {{ ($isLocked && $proposal->status === 'Converted') ? '#00FF66' : 'var(--erp-muted)' }}; text-align: center;">
                                    {{ ($isLocked && $proposal->status === 'Converted') ? '✓ COUNTER-SIGNED' : 'AWAITING CLIENT BINDING' }}
                                </div>
                                @if($isLocked && $proposal->status === 'Converted')
                                    <div style="height: 60px; margin-bottom: 10px; display: flex; justify-content: center;">
                                        <img src="{{ $proposal->client_signature_b64 ?? '' }}" style="max-height: 100%; object-fit: contain;">
                                    </div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>SIGNATORY:</span><span>{{ strtoupper($proposal->signed_by_name) }}</span></div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>TIMESTAMP:</span><span>{{ \Carbon\Carbon::parse($proposal->signed_at)->format('Y-m-d H:i:s') }}</span></div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.55rem; color: #00FF66; font-weight: bold; word-break: break-all; margin-top: 10px; border-top: 1px dashed rgba(0,255,102,0.3); padding-top: 10px; text-align: center;">
                                        {{ $proposal->client_signature_hash ?? 'HASH_PENDING' }}
                                    </div>
                                @else
                                    <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--erp-muted); font-size: 0.7rem; font-family: 'Roboto Mono'; text-align: center;">Awaiting authorization via Magic Link terminal.</div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- QR CODE GENERATION -->
                        @if($isLocked)
                            <div style="margin-top: 30px; padding: 20px; border: 1px dashed rgba(255,255,255,0.1); background: rgba(0,0,0,0.3); display: flex; gap: 20px; align-items: center;">
                                @php
                                    $baseUrl = url('/verify/contract');
                                    $qrData = "URL:{$baseUrl}/{$proposal->hq_signature_hash} | ORG:{$proposal->hq_signature_hash}";
                                @endphp
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($qrData) }}&color=00E5FF&bgcolor=03050A" style="width: 80px; height: 80px; border: 2px solid var(--erp-teal); padding: 4px; background: #03050A;">
                                <div style="font-family: 'Roboto Mono', monospace;">
                                    <div style="font-size: 1rem; font-weight: bold; color: var(--erp-white); letter-spacing: 2px;">JBS PRAXIS HQ</div>
                                    <div style="font-size: 0.7rem; color: #00FF66; margin-bottom: 5px;">✓ SYSTEM VERIFIED & SECURED</div>
                                    <div style="font-size: 0.6rem; color: var(--erp-muted);">Scan cryptographic matrix to verify origin immutability on Praxis servers.</div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        @endif

    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); initHqSignature(); });
        document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); initHqSignature(); });

        // NEW: Wakes up the canvas specifically when Tab 3 is opened
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
            
            hqCtx.lineWidth = 2; hqCtx.lineCap = 'round'; hqCtx.strokeStyle = '#FFBD2E'; // Amber ink for HQ

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
                alert("System Error: Canvas not initialized. Please refresh.");
                return;
            }
            
            const dataUrl = hqCanvas.toDataURL('image/png');
            const blank = document.createElement('canvas'); 
            blank.width = hqCanvas.width; 
            blank.height = hqCanvas.height;
            
            if(dataUrl === blank.toDataURL()) { 
                alert("SYSTEM HALT: Please draw your signature to secure the payload."); 
                return; 
            }
            
            // Inject Base64 to Livewire and fire transaction
            @this.set('hqSignatureBase64', dataUrl);
            @this.call('compileAndSecure');
        }
    </script>
</div>