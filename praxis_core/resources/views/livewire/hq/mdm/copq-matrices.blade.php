<div>
    <style>
        /* Sub-Nav Styles (Inherited from Financial Dictionary) */
        .ws-tab {
            font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); 
            padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;
        }
        .ws-tab:hover { color: #FFF; }
        .ws-tab.active {
            color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); 
            border: 1px solid var(--erp-teal); border-radius: 4px;
        }

        /* Internal Module Tabs */
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid var(--erp-border-dark); background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }

        /* Inputs & Modals */
        .cyber-input, .cyber-select, .cyber-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 12px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border-radius: 2px; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { border-color: var(--erp-teal); outline: none; background: rgba(0,0,0,0.5); }
        .cyber-select option { background-color: var(--erp-bg-main); color: var(--erp-white); padding: 10px; }

        .glass-modal { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.9); backdrop-filter: blur(10px); z-index: 9990; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .modal-box { background: var(--erp-bg-main); border: 1px solid var(--erp-teal); border-top: 4px solid var(--erp-teal); border-radius: 4px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; }

        /* Bloomberg Data Grid */
        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 15px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; background: rgba(255,255,255,0.02); position: sticky; top: 0; z-index: 10; }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: top; }
        .bloomberg-table tr:hover { background: rgba(0, 229, 255, 0.03); }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        @php
            $baseStyle = "font-family: 'Roboto Mono', monospace; font-size: 0.85rem; padding: 5px 15px; text-decoration: none; transition: 0.3s;";
            $activeStyle = "color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); border: 1px solid var(--erp-teal); border-radius: 4px;";
            $inactiveStyle = "color: var(--erp-muted); border: 1px solid transparent;";
        @endphp

        <a href="{{ route('hq.mdm.dictionary') }}" wire:navigate style="{{ $baseStyle }} {{ request()->routeIs('hq.mdm.dictionary') ? $activeStyle : $inactiveStyle }}">[ FINANCIAL_DICTIONARY ]</a>
        <a href="{{ route('hq.mdm.copq') }}" wire:navigate style="{{ $baseStyle }} {{ request()->routeIs('hq.mdm.copq') ? $activeStyle : $inactiveStyle }}">[ COPQ_MATRICES ]</a>
        <a href="{{ route('hq.mdm.tax') }}" wire:navigate style="{{ $baseStyle }} {{ request()->routeIs('hq.mdm.tax') ? $activeStyle : $inactiveStyle }}">[ TAX_&_COMPLIANCE ]</a>
        <a href="{{ route('hq.mdm.remittance') }}" wire:navigate style="{{ $baseStyle }} {{ request()->routeIs('hq.mdm.remittance') ? $activeStyle : $inactiveStyle }}">[ TREASURY_ACCOUNTS ]</a>
    </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div style="display: flex; gap: 10px;">
            <button wire:click="switchTab('industries')" class="nav-pill {{ $activeTab === 'industries' ? 'active' : '' }}">INDUSTRY BASELINES</button>
            <button wire:click="switchTab('architectures')" class="nav-pill {{ $activeTab === 'architectures' ? 'active' : '' }}">TECH ARCHITECTURES</button>
            <button wire:click="switchTab('bottlenecks')" class="nav-pill {{ $activeTab === 'bottlenecks' ? 'active' : '' }}">FRICTION ANALYSIS (BOTTLENECKS)</button>
        </div>
        
        @if($activeTab === 'industries')
            <button wire:click="openIndModal" style="background: var(--erp-teal); color: #0A1128; border: none; padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.75rem; border-radius: 2px; cursor: pointer; transition: 0.3s; box-shadow: 0 0 10px rgba(0,229,255,0.2);">[+ ADD INDUSTRY]</button>
        @elseif($activeTab === 'architectures')
            <button wire:click="openArchModal" style="background: var(--erp-teal); color: #0A1128; border: none; padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.75rem; border-radius: 2px; cursor: pointer; transition: 0.3s; box-shadow: 0 0 10px rgba(0,229,255,0.2);">[+ ADD ARCHITECTURE]</button>
        @else
            <button wire:click="openBnModal" style="background: var(--erp-teal); color: #0A1128; border: none; padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.75rem; border-radius: 2px; cursor: pointer; transition: 0.3s; box-shadow: 0 0 10px rgba(0,229,255,0.2);">[+ ADD BOTTLENECK]</button>
        @endif
    </div>

    <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; max-height: 65vh; overflow-y: auto;">
        
        @if($activeTab === 'industries')
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">Industry Profile</th>
                        <th style="min-width: 250px;">Compliance & Risk Vector</th>
                        <th style="min-width: 250px;">Context & Erosion Target</th>
                        <th style="text-align: right; min-width: 100px;">Error Floor</th>
                        <th style="text-align: center; width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($industries as $ind)
                    <tr>
                        <td>
                            <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.9rem; color: var(--erp-white);">{{ $ind->industry_name }}</div>
                            <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 4px; display: flex; gap: 8px;">
                                <span style="background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 2px;">{{ $ind->organization_type }}</span>
                                <span style="background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 2px;">Value: {{ $ind->value_term }}</span>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 0.75rem; color: var(--erp-teal); font-weight: bold; margin-bottom: 2px;">{{ $ind->compliance_standard }}</div>
                            <div style="font-size: 0.75rem; color: var(--erp-red); font-family: 'Roboto Mono', monospace;">Risk: {{ $ind->risk_type }}</div>
                        </td>
                        <td>
                            <div style="font-size: 0.75rem; color: var(--erp-white); line-height: 1.4; margin-bottom: 4px;"><strong>Deficit:</strong> {{ $ind->deficit_context }}</div>
                            <div style="font-size: 0.75rem; color: var(--erp-muted); line-height: 1.4;"><strong>Focus:</strong> {{ $ind->opportunity_focus }}</div>
                        </td>
                        <td style="text-align: right; font-family: 'Roboto Mono', monospace;">
                            <span style="font-size: 1.1rem; font-weight: bold; color: #FFBD2E;">{{ number_format($ind->error_floor, 2) }}%</span>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <button wire:click="openIndModal({{ $ind->id }})" style="background: rgba(255,189,46,0.1); border: 1px solid #FFBD2E; color: #FFBD2E; padding: 6px 15px; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s;">EDIT</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding: 40px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> NO INDUSTRIES IN DATABASE.</td></tr>
                    @endforelse
                </tbody>
            </table>
        
        @elseif($activeTab === 'architectures')
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">Tech Key / Display Name</th>
                        <th style="min-width: 200px;">Symptom Title</th>
                        <th style="min-width: 350px;">Intervention Strategy</th>
                        <th style="text-align: right; min-width: 120px;">Friction Multiplier</th>
                        <th style="text-align: center; width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($architectures as $arch)
                    <tr>
                        <td>
                            <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.9rem; color: var(--erp-teal);">{{ strtoupper($arch->tech_key) }}</div>
                            <div style="font-size: 0.75rem; color: var(--erp-white); margin-top: 4px;">{{ $arch->display_name }}</div>
                        </td>
                        <td>
                            <div style="font-size: 0.8rem; color: var(--erp-red); font-weight: bold;">{{ $arch->symptom_title }}</div>
                        </td>
                        <td>
                            <div style="font-size: 0.75rem; color: var(--erp-muted); line-height: 1.5;">{{ $arch->intervention_strategy }}</div>
                        </td>
                        <td style="text-align: right; font-family: 'Roboto Mono', monospace;">
                            <span style="font-size: 1.1rem; font-weight: bold; color: #FFBD2E;">{{ number_format($arch->error_multiplier, 2) }}x</span>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <button wire:click="openArchModal({{ $arch->id }})" style="background: rgba(255,189,46,0.1); border: 1px solid #FFBD2E; color: #FFBD2E; padding: 6px 15px; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s;">EDIT</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding: 40px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> NO ARCHITECTURES IN DATABASE.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @else
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">Bottleneck Department</th>
                        <th style="min-width: 350px;">AI Fallback Narrative</th>
                        <th style="text-align: center; min-width: 200px;">Chaos Tax Multipliers</th>
                        <th style="text-align: right; min-width: 100px;">Risk Weight</th>
                        <th style="text-align: center; width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bottlenecks as $bn)
                    <tr>
                        <td>
                            <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.9rem; color: var(--erp-white);">{{ strtoupper($bn->bottleneck_name) }}</div>
                        </td>
                        <td>
                            <div style="font-size: 0.75rem; color: var(--erp-muted); line-height: 1.5; font-style: italic;">"{{ $bn->bottleneck_narrative }}"</div>
                        </td>
                        <td style="text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">
                            <div style="display: flex; gap: 5px; justify-content: center;">
                                <span style="background: rgba(255,189,46,0.1); color: #FFBD2E; padding: 2px 6px; border-radius: 2px;" title="Appraisal Multiplier">A: {{ number_format($bn->appraisal_multiplier, 2) }}</span>
                                <span style="background: rgba(255,51,102,0.1); color: var(--erp-red); padding: 2px 6px; border-radius: 2px;" title="Failure Multiplier">F: {{ number_format($bn->failure_multiplier, 2) }}</span>
                                <span style="background: rgba(0,255,102,0.1); color: #00FF66; padding: 2px 6px; border-radius: 2px;" title="Opportunity Multiplier">O: {{ number_format($bn->opportunity_multiplier, 2) }}</span>
                            </div>
                        </td>
                        <td style="text-align: right; font-family: 'Roboto Mono', monospace;">
                            <span style="font-size: 1.1rem; font-weight: bold; color: var(--erp-red);">+{{ $bn->risk_weight }}%</span>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <button wire:click="openBnModal({{ $bn->id }})" style="background: rgba(255,189,46,0.1); border: 1px solid #FFBD2E; color: #FFBD2E; padding: 6px 15px; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s;">EDIT</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding: 40px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> NO BOTTLENECKS IN DATABASE.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    @if($showIndModal)
        <div class="glass-modal">
            <div class="modal-box">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; margin-bottom: 20px;">
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-teal);">> {{ $indEditMode ? 'MODIFY' : 'REGISTER' }} INDUSTRY BASELINE</span>
                    <button wire:click="$set('showIndModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
                </div>
                <form wire:submit.prevent="saveIndustry" style="display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">INDUSTRY NAME</label>
                            <input type="text" wire:model="industry_name" required class="cyber-input">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 5px;">BASE ERROR FLOOR (%)</label>
                            <input type="number" step="0.01" wire:model="error_floor" required class="cyber-input" style="color: #FFBD2E; font-weight: bold; border-color: rgba(0,229,255,0.3);">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">COMPLIANCE STANDARD</label>
                            <input type="text" wire:model="compliance_standard" required class="cyber-input">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">PRIMARY RISK VECTOR</label>
                            <input type="text" wire:model="risk_type" required class="cyber-input">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">DEFICIT CONTEXT</label>
                            <input type="text" wire:model="deficit_context" required class="cyber-input">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">EROSION TARGET</label>
                            <input type="text" wire:model="erosion_target" required class="cyber-input">
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">OPPORTUNITY FOCUS</label>
                        <input type="text" wire:model="opportunity_focus" required class="cyber-input">
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 5px;">AI FALLBACK NARRATIVE (Use {COMPANY_NAME})</label>
                        <textarea wire:model="fallback_diagnostic" rows="3" required class="cyber-textarea"></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05);">
                        <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 5px;">ORG TYPE</label><input type="text" wire:model="organization_type" required class="cyber-input"></div>
                        <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 5px;">VALUE TERM</label><input type="text" wire:model="value_term" required class="cyber-input"></div>
                        <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 5px;">MULTIPLIER TERM</label><input type="text" wire:model="multiplier_term" required class="cyber-input"></div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" style="flex: 2; background: var(--erp-teal); color: #0A1128; border: none; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; border-radius: 2px; cursor: pointer;">{{ $indEditMode ? 'OVERWRITE INDUSTRY' : 'COMMIT INDUSTRY TO DB' }}</button>
                        <button type="button" wire:click="$set('showIndModal', false)" style="flex: 1; background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer;">CANCEL</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showArchModal)
        <div class="glass-modal">
            <div class="modal-box">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; margin-bottom: 20px;">
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-teal);">> {{ $archEditMode ? 'MODIFY' : 'REGISTER' }} TECH ARCHITECTURE</span>
                    <button wire:click="$set('showArchModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
                </div>
                <form wire:submit.prevent="saveArchitecture" style="display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">TECH KEY</label>
                            <input type="text" wire:model="tech_key" required class="cyber-input" {{ $archEditMode ? 'readonly style=opacity:0.5;' : '' }}>
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 5px;">ERROR MULTIPLIER (e.g. 1.5x)</label>
                            <input type="number" step="0.01" wire:model="error_multiplier" required class="cyber-input" style="color: #FFBD2E; font-weight: bold; border-color: rgba(0,229,255,0.3);">
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">DISPLAY NAME</label>
                        <input type="text" wire:model="display_name" required class="cyber-input">
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">SYMPTOM TITLE</label>
                        <input type="text" wire:model="symptom_title" required class="cyber-input">
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">INTERVENTION STRATEGY</label>
                        <textarea wire:model="intervention_strategy" rows="4" required class="cyber-textarea"></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" style="flex: 2; background: var(--erp-teal); color: #0A1128; border: none; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; border-radius: 2px; cursor: pointer;">{{ $archEditMode ? 'OVERWRITE ARCHITECTURE' : 'COMMIT ARCH TO DB' }}</button>
                        <button type="button" wire:click="$set('showArchModal', false)" style="flex: 1; background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer;">CANCEL</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showBnModal)
        <div class="glass-modal">
            <div class="modal-box" style="max-width: 600px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; margin-bottom: 20px;">
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-teal);">> {{ $bnEditMode ? 'MODIFY' : 'REGISTER' }} BOTTLENECK PROFILE</span>
                    <button wire:click="$set('showBnModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
                </div>
                <form wire:submit.prevent="saveBottleneck" style="display: flex; flex-direction: column; gap: 15px;">
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">DEPARTMENT / BOTTLENECK NAME</label>
                            <input type="text" wire:model="bottleneck_name" required class="cyber-input" placeholder="e.g. HR & Talent Management">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); margin-bottom: 5px;">BASE RISK WEIGHT (+%)</label>
                            <input type="number" wire:model="risk_weight" required class="cyber-input" style="color: var(--erp-red); font-weight: bold;">
                        </div>
                    </div>

                    <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05);">
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 10px; border-bottom: 1px dashed rgba(0,229,255,0.3); padding-bottom: 5px;">THE CHAOS TAX MULTIPLIERS</label>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 5px;">APPRAISAL (Time)</label>
                                <input type="number" step="0.01" wire:model="appraisal_multiplier" required class="cyber-input" style="color: #FFBD2E;">
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-red); margin-bottom: 5px;">FAILURE (Rework)</label>
                                <input type="number" step="0.01" wire:model="failure_multiplier" required class="cyber-input" style="color: var(--erp-red);">
                            </div>
                            <div>
                                <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #00FF66; margin-bottom: 5px;">OPPORTUNITY (Loss)</label>
                                <input type="number" step="0.01" wire:model="opportunity_multiplier" required class="cyber-input" style="color: #00FF66;">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 5px;">DEPARTMENTAL NARRATIVE (Use {COMPANY_NAME})</label>
                        <textarea wire:model="bottleneck_narrative" rows="4" required class="cyber-textarea" placeholder="Describe the specific pain this department causes..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" style="flex: 2; background: var(--erp-teal); color: #0A1128; border: none; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; border-radius: 2px; cursor: pointer;">{{ $bnEditMode ? 'OVERWRITE BOTTLENECK' : 'COMMIT TO DB' }}</button>
                        <button type="button" wire:click="$set('showBnModal', false)" style="flex: 1; background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer;">CANCEL</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>