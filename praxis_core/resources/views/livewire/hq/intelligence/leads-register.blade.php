<div style="position: relative; overflow: hidden; min-height: 85vh;">
    <style>
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid var(--erp-border-dark); background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }

        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 15px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; background: rgba(255,255,255,0.02); position: sticky; top: 0; z-index: 10; }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; cursor: pointer; transition: 0.2s; }
        .bloomberg-table tr:hover td { background: rgba(0, 229, 255, 0.03); }

        .tier-1-glow { box-shadow: inset 4px 0 0 var(--erp-red); background: linear-gradient(90deg, rgba(255,51,102,0.05) 0%, transparent 100%); }
        .tier-2-glow { box-shadow: inset 4px 0 0 var(--erp-teal); }
        
        /* DUPLICATE ROW STYLING */
        .duplicate-row td { background: rgba(255, 51, 102, 0.08) !important; }
        .duplicate-row { border-left: 4px solid var(--erp-red); }
        .duplicate-badge { background: var(--erp-red); color: #fff; font-size: 0.6rem; padding: 2px 6px; border-radius: 2px; margin-left: 8px; font-weight: bold; letter-spacing: 1px; }

        .drawer-overlay { position: absolute; inset: 0; background: rgba(3, 5, 10, 0.6); backdrop-filter: blur(4px); z-index: 9990; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .drawer-overlay.active { opacity: 1; visibility: visible; }
        .slide-drawer { position: absolute; top: 0; right: 0; width: 650px; max-width: 100%; height: 100%; background: var(--erp-bg-main); border-left: 1px solid var(--erp-teal); box-shadow: -20px 0 50px rgba(0,0,0,0.8); z-index: 9999; transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
        .slide-drawer.active { transform: translateX(0); }
        .drawer-header { padding: 25px 30px; border-bottom: 1px solid var(--erp-border-dark); display: flex; justify-content: space-between; align-items: flex-start; background: rgba(0, 229, 255, 0.02); }
        .drawer-body { padding: 30px; overflow-y: auto; flex-grow: 1; }

        /* MODAL Z-INDEX AND SCROLL OVERRIDE */
        .glass-modal { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.9); backdrop-filter: blur(10px); z-index: 999999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .modal-box { background: var(--erp-bg-main); border: 1px solid var(--erp-teal); border-top: 4px solid var(--erp-teal); border-radius: 4px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; }
        .cyber-input, .cyber-select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; }
        .cyber-input:focus, .cyber-select:focus { outline: none; border-color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); }
        .cyber-select option { background-color: var(--erp-bg-main); }

        /* LOADING STATES */
        button:disabled { opacity: 0.5; cursor: not-allowed !important; pointer-events: none; }
        
        /* ==========================================================================
           KANBAN BOARD ARCHITECTURE
           ========================================================================== */
        .kanban-board { 
            display: flex; 
            gap: 20px; 
            overflow-x: auto; 
            padding-bottom: 20px; 
            height: calc(100vh - 220px); 
            align-items: flex-start; 
        }
        
        .kanban-board::-webkit-scrollbar { height: 6px; }
        .kanban-board::-webkit-scrollbar-thumb { background: rgba(0, 229, 255, 0.2); border-radius: 3px; }

        .kanban-col { 
            min-width: 320px; 
            width: 320px; 
            background: rgba(10, 17, 40, 0.4); 
            border: 1px solid var(--erp-border-dark); 
            border-radius: 4px; 
            display: flex; 
            flex-direction: column; 
            max-height: 100%; 
            box-shadow: inset 0 0 20px rgba(0,0,0,0.3);
        }

        .kanban-header { 
            padding: 15px 20px; 
            border-bottom: 1px solid var(--erp-border-dark); 
            font-family: 'Roboto Mono', monospace; 
            font-size: 0.75rem; 
            font-weight: bold; 
            color: var(--erp-white); 
            text-transform: uppercase; 
            background: rgba(0, 229, 255, 0.02); 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .kanban-count {
            background: rgba(0, 229, 255, 0.1);
            color: var(--erp-teal);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
        }

        .kanban-cards { 
            padding: 15px; 
            overflow-y: auto; 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
            flex-grow: 1; 
        }
        
        .kanban-cards::-webkit-scrollbar { width: 4px; }
        .kanban-cards::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); }

        .k-card { 
            background: rgba(0,0,0,0.6); 
            border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 4px; 
            padding: 18px; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            display: flex; 
            flex-direction: column; 
            position: relative;
        }

        .k-card:hover { 
            border-color: var(--erp-teal); 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(0,0,0,0.5); 
        }
    </style>

    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">INTELLIGENCE DESK</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ LEADS_REGISTER ] : PIPELINE & DIAGNOSTIC VAULT</p>
        </div>
        <div style="display: flex; gap: 10px;">
            @can('crm.create')
            <button wire:click="openIntakeModal" wire:loading.attr="disabled" wire:target="openIntakeModal" class="nav-pill" style="border-color: #FFBD2E; color: #FFBD2E; box-shadow: 0 0 10px rgba(255,189,46,0.1);">
                <span wire:loading.remove wire:target="openIntakeModal">[+ INITIATE MANUAL PITCH]</span>
                <span wire:loading wire:target="openIntakeModal">LOADING...</span>
            </button>
            @endcan
            @can('crm.export')
            <button wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" class="nav-pill">
                <span wire:loading.remove wire:target="exportCsv">EXPORT SECURE CSV</span>
                <span wire:loading wire:target="exportCsv">GENERATING...</span>
            </button>
            @endcan
        </div>
    </div>

    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <button wire:click="toggleView('grid')" class="nav-pill {{ $viewMode === 'grid' ? 'active' : '' }}">BLOOMBERG GRID</button>
        <button wire:click="toggleView('kanban')" class="nav-pill {{ $viewMode === 'kanban' ? 'active' : '' }}">PIPELINE KANBAN</button>
    </div>

    @if($viewMode === 'grid')
        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5); overflow-x: auto; height: calc(100vh - 220px); overflow-y: auto;">
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">Enterprise / Prospect</th>
                        <th style="min-width: 150px;">Contact Vector</th>
                        <th style="min-width: 150px;">AI Score</th>
                        <th style="min-width: 180px;">Pipeline Stage</th>
                        <th style="text-align: right; min-width: 150px;">Calculated COPQ</th>
                        <th style="min-width: 180px;">Ownership / Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                    <tr class="{{ $lead->lead_tier == 1 ? 'tier-1-glow' : ($lead->lead_tier == 2 ? 'tier-2-glow' : '') }} {{ $lead->is_duplicate_suspect ? 'duplicate-row' : '' }}" wire:click="openDrawer('{{ $lead->org_id }}')">
                        <td>
                            <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.9rem; color: var(--erp-white); display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                                {{ strtoupper($lead->company_name) }}
                                @if($lead->is_duplicate_suspect) 
                                    <span class="duplicate-badge">DUPLICATE</span> 
                                @endif
                                
                                @if($lead->status === 'Lead')
                                    <button wire:click.stop="requestConsultation('{{ $lead->org_id }}')" title="Initiate Ad-Hoc Consultation" style="background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); font-family: 'Fira Code', monospace; font-size: 0.6rem; padding: 2px 6px; cursor: pointer; border-radius: 2px; font-weight: bold; transition: 0.2s;" onmouseover="this.style.background='var(--erp-teal)'; this.style.color='#000';" onmouseout="this.style.background='rgba(0,229,255,0.1)'; this.style.color='var(--erp-teal)';">
                                        >_
                                    </button>
                                @endif
                            </div>
                            <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 4px;">Ind: {{ $lead->copq_industry ?? 'Unknown' }}</div>
                        </td>
                        <td>
                            <div style="font-size: 0.8rem; color: var(--erp-white);">{{ $lead->executive_name }}</div>
                            <div style="font-size: 0.7rem; color: var(--erp-teal); margin-top: 2px;">{{ $lead->corporate_email }}</div>
                        </td>
                        <td>
                            @if($lead->lead_tier == 1) <span style="color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; border: 1px solid var(--erp-red); padding: 2px 6px; border-radius: 2px;">[TIER 1] ENTERPRISE</span>
                            @elseif($lead->lead_tier == 2) <span style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; border: 1px solid var(--erp-teal); padding: 2px 6px; border-radius: 2px;">[TIER 2] MID-MARKET</span>
                            @else <span style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; border: 1px solid var(--erp-muted); padding: 2px 6px; border-radius: 2px;">[TIER 3] SME</span> @endif
                        </td>
                        <td>
                            <span style="display: inline-block; background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 2px; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; color: var(--erp-white); border: 1px solid rgba(255,255,255,0.1);">{{ strtoupper($lead->pipeline_stage) }}</span>
                        </td>
                        <td style="text-align: right; font-family: 'Roboto Mono', monospace;">
                            @if($lead->calculated_leakage)
                                <span style="font-size: 1.1rem; font-weight: bold; color: {{ $lead->lead_tier == 1 ? 'var(--erp-red)' : '#FFBD2E' }};">{{ $lead->copq_currency }} {{ number_format($lead->calculated_leakage) }}</span>
                            @else
                                <span style="font-size: 0.8rem; color: var(--erp-muted);">[ UNSCORED ]</span>
                            @endif
                        </td>
                        <td onclick="event.stopPropagation();">
                            @if($lead->owner_id)
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 24px; height: 24px; background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; color: var(--erp-teal); font-weight: bold;">{{ substr($lead->owner_first, 0, 1) }}{{ substr($lead->owner_last, 0, 1) }}</div>
                                    <span style="font-size: 0.75rem; color: var(--erp-white);">{{ $lead->owner_first }}</span>
                                </div>
                            @else
                                <button wire:click="claimLead('{{ $lead->org_id }}')" wire:loading.attr="disabled" wire:target="claimLead('{{ $lead->org_id }}')" style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 6px 12px; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; font-weight: bold; cursor: pointer;">
                                    <span wire:loading.remove wire:target="claimLead('{{ $lead->org_id }}')">CLAIM LEAD</span>
                                    <span wire:loading wire:target="claimLead('{{ $lead->org_id }}')">CLAIMING...</span>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding: 60px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">> PIPELINE IS EMPTY.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    
    @else
        <div class="kanban-board">
            @foreach($stages as $stage)
                <div class="kanban-col">
                    <div class="kanban-header">
                        <span>{{ $stage }}</span>
                        <span class="kanban-count">{{ collect($leads)->where('pipeline_stage', $stage)->count() }}</span>
                    </div>
                    
                    <div class="kanban-cards">
                        @forelse(collect($leads)->where('pipeline_stage', $stage) as $lead)
                            <div class="k-card {{ $lead->lead_tier == 1 ? 'tier-1-glow' : '' }}" wire:click="openDrawer('{{ $lead->org_id }}')" style="{{ $lead->is_duplicate_suspect ? 'border-top: 2px solid var(--erp-red); background: rgba(255,51,102,0.05);' : '' }}">
                                
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); font-weight: bold; line-height: 1.3;">
                                        {{ strtoupper($lead->company_name) }}
                                        @if($lead->is_duplicate_suspect) 
                                            <span style="color: var(--erp-red); font-size: 0.55rem; background: rgba(255,51,102,0.1); padding: 2px 4px; margin-left: 5px; vertical-align: middle;">DUPLICATE</span> 
                                        @endif
                                    </div>
                                    
                                    @if($lead->status === 'Lead')
                                        <button wire:click.stop="requestConsultation('{{ $lead->org_id }}')" title="Initiate Ad-Hoc Consultation" style="background: rgba(0,229,255,0.05); border: 1px solid var(--erp-teal); color: var(--erp-teal); font-family: 'Fira Code', monospace; font-size: 0.6rem; padding: 3px 6px; cursor: pointer; border-radius: 2px; font-weight: bold; transition: 0.2s;" onmouseover="this.style.background='var(--erp-teal)'; this.style.color='#000';" onmouseout="this.style.background='rgba(0,229,255,0.05)'; this.style.color='var(--erp-teal)';">
                                            >_
                                        </button>
                                    @endif
                                </div>
                                
                                <div style="font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 15px;">
                                    <i class="fa-solid fa-user" style="font-size: 0.6rem; margin-right: 4px;"></i> {{ $lead->executive_name }}
                                </div>
                                
                                @if($lead->calculated_leakage)
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 1rem; color: {{ $lead->lead_tier == 1 ? 'var(--erp-red)' : '#FFBD2E' }}; font-weight: bold; margin-bottom: 12px; letter-spacing: -0.5px;">
                                        <span style="font-size: 0.65rem; color: var(--erp-muted);">COPQ:</span><br>
                                        {{ $lead->copq_currency }} {{ number_format($lead->calculated_leakage) }}
                                    </div>
                                @else
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 12px; padding: 4px 0; border-top: 1px dashed rgba(255,255,255,0.1); border-bottom: 1px dashed rgba(255,255,255,0.1);">
                                        [ UNSCORED ]
                                    </div>
                                @endif

                                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 12px; margin-top: auto;" onclick="event.stopPropagation();">
                                    @if($lead->owner_id)
                                        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); text-transform: uppercase; display: flex; align-items: center; gap: 5px;">
                                            <i class="fa-solid fa-lock" style="font-size: 0.55rem;"></i> {{ $lead->owner_first }}
                                        </span>
                                    @else
                                        <button wire:click="claimLead('{{ $lead->org_id }}')" wire:loading.attr="disabled" wire:target="claimLead('{{ $lead->org_id }}')" style="background: transparent; border: none; color: var(--erp-red); font-size: 0.65rem; font-family: 'Roboto Mono', monospace; font-weight: bold; cursor: pointer; text-decoration: underline; padding: 0;">
                                            <span wire:loading.remove wire:target="claimLead('{{ $lead->org_id }}')">CLAIM LEAD</span>
                                            <span wire:loading wire:target="claimLead('{{ $lead->org_id }}')">CLAIMING...</span>
                                        </button>
                                    @endif
                                    
                                    <span style="font-family: 'Inter', sans-serif; font-size: 0.6rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($lead->created_at)->diffForHumans(null, true, true) }}</span>
                                </div>
                            </div>
                        @empty
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-align: center; padding: 20px; border: 1px dashed rgba(255,255,255,0.1); border-radius: 4px;">
                                NO LEADS IN STAGE
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="drawer-overlay {{ $showDrawer ? 'active' : '' }}" wire:click="closeDrawer"></div>
    <div class="slide-drawer {{ $showDrawer ? 'active' : '' }}">
        @if($selectedLead)
            <div class="drawer-header">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                        <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; color: var(--erp-white); letter-spacing: -0.5px;">{{ strtoupper($selectedLead->company_name) }}</h2>
                        @if($selectedLead->lead_tier == 1) <span style="background: var(--erp-red); color: #FFF; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; padding: 2px 6px; font-weight: bold;">TIER 1</span>
                        @elseif($selectedLead->lead_tier == 2) <span style="background: var(--erp-teal); color: #000; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; padding: 2px 6px; font-weight: bold;">TIER 2</span> @endif
                    </div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal);">
                        {{ strtoupper($selectedLead->executive_name) }} | {{ strtolower($selectedLead->corporate_email) }}
                    </div>
                </div>
                <button wire:click="closeDrawer" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.5rem;">&times;</button>
            </div>
            
            <div class="drawer-body">
                
                @if($selectedLead->is_duplicate_suspect)
                    <div style="background: rgba(255, 51, 102, 0.1); border: 1px dashed var(--erp-red); padding: 15px; margin-bottom: 25px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-red); font-weight: bold;">> DUPLICATE RECORD DETECTED</span>
                            <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 4px;">Multiple entries found for this organization. Resolution required.</div>
                        </div>
                        <button wire:click="openDiffViewer('{{ $selectedLead->org_id }}')" wire:loading.attr="disabled" wire:target="openDiffViewer('{{ $selectedLead->org_id }}')" style="background: var(--erp-red); color: #fff; border: none; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; padding: 8px 15px; border-radius: 2px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.boxShadow='0 0 10px rgba(255,51,102,0.5)'" onmouseout="this.style.boxShadow='none'">
                            <span wire:loading.remove wire:target="openDiffViewer('{{ $selectedLead->org_id }}')">RESOLVE MERGE</span>
                            <span wire:loading wire:target="openDiffViewer('{{ $selectedLead->org_id }}')">LOADING...</span>
                        </button>
                    </div>
                @endif

                <div style="display: flex; gap: 10px; margin-bottom: 25px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="flex-grow: 1;">
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 5px;">PIPELINE STAGE</label>
                        <select wire:model="selectedLead.pipeline_stage" wire:change="updateStage('{{ $selectedLead->org_id }}', $event.target.value)" style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 8px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; border-radius: 2px;">
                            @foreach($stages as $stage)
                                <option value="{{ $stage }}">{{ $stage }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(!$selectedLead->owner_id)
                        <button wire:click="claimLead('{{ $selectedLead->org_id }}')" wire:loading.attr="disabled" wire:target="claimLead('{{ $selectedLead->org_id }}')" style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; padding: 0 20px; cursor: pointer; margin-top: 18px;">
                            <span wire:loading.remove wire:target="claimLead('{{ $selectedLead->org_id }}')">CLAIM LEAD</span>
                            <span wire:loading wire:target="claimLead('{{ $selectedLead->org_id }}')">CLAIMING...</span>
                        </button>
                    @endif
                </div>

                @if($selectedLead->calculated_leakage)
                    <div style="margin-bottom: 30px;">
                        <h4 style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #FFBD2E; margin-bottom: 10px; border-bottom: 1px dashed rgba(255,189,46,0.3); padding-bottom: 5px;">> COPQ DIAGNOSTIC SUMMARY</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                            <div style="background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); padding: 10px; border-left: 2px solid var(--erp-teal);">
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted);">TURNOVER BASELINE</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); font-weight: bold;">{{ $selectedLead->copq_currency }} {{ number_format($selectedLead->copq_turnover) }}</div>
                            </div>
                            <div style="background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); padding: 10px; border-left: 2px solid #FFBD2E;">
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted);">PRIMARY BOTTLENECK</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-white); font-weight: bold;">{{ strtoupper($selectedLead->bottleneck) }}</div>
                            </div>
                        </div>

                        <div style="background: linear-gradient(135deg, rgba(255,51,102,0.1) 0%, transparent 100%); border: 1px solid var(--erp-red); padding: 20px; margin-bottom: 15px; border-radius: 4px;">
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); margin-bottom: 5px;">SYSTEMIC CAPITAL LEAKAGE (ANNUAL)</div>
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 2.5rem; font-weight: bold; color: var(--erp-white); line-height: 1;">
                                <span style="font-size: 1.2rem; color: var(--erp-muted);">{{ $selectedLead->copq_currency }}</span> {{ number_format($selectedLead->calculated_leakage) }}
                            </div>
                        </div>

                        <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05); padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 5px;">AI FRICTION ANALYSIS</div>
                            <p style="font-size: 0.8rem; color: var(--erp-muted); line-height: 1.6;">{{ $selectedLead->ai_friction_analysis }}</p>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <a href="{{ route('dossier.show', ['token' => $selectedLead->report_token]) }}?exec=true" target="_blank" style="flex: 1; text-align: center; background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; padding: 12px; text-decoration: none; border-radius: 2px;">VIEW FULL DOSSIER</a>
                            
                            @if($showDiffModal && $diffOld && $diffNew)
                                <div class="glass-modal">
                                    <div class="modal-box" style="max-width: 900px; background: rgba(10, 17, 40, 0.95); border: 1px solid #FFBD2E; border-top: 4px solid #FFBD2E;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; margin-bottom: 20px;">
                                            <div>
                                                <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #FFBD2E; font-weight: bold;">> MERGE PROTOCOL INITIATED</span>
                                                <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 4px;">Select the definitive data points to stitch into the master record.</div>
                                            </div>
                                            <button wire:click="closeDiffViewer" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
                                        </div>
                        
                                        <div style="display: grid; grid-template-columns: 150px 1fr 1fr; gap: 15px; margin-bottom: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal); text-transform: uppercase; padding: 0 15px;">
                                            <div>Data Field</div>
                                            <div style="padding: 10px; background: rgba(255,255,255,0.05); border-radius: 4px;">Archived Record<br><span style="font-size: 0.6rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($diffOld->created_at)->format('M d, Y H:i') }}</span></div>
                                            <div style="padding: 10px; background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); border-radius: 4px;">Newest Submission<br><span style="font-size: 0.6rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($diffNew->created_at)->format('M d, Y H:i') }}</span></div>
                                        </div>
                        
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            @foreach($diffFields as $field => $label)
                                                <div style="display: grid; grid-template-columns: 150px 1fr 1fr; gap: 15px; align-items: center; background: rgba(0,0,0,0.3); padding: 12px 15px; border: 1px solid rgba(255,255,255,0.05); border-radius: 4px;">
                                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted);">{{ $label }}</div>
                                                    
                                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 8px; border-radius: 4px; transition: 0.2s; border: 1px solid {{ $mergeSelections[$field] === 'old' ? '#FFBD2E' : 'transparent' }}; background: {{ $mergeSelections[$field] === 'old' ? 'rgba(255,189,46,0.1)' : 'transparent' }};">
                                                        <input type="radio" wire:model="mergeSelections.{{ $field }}" value="old" style="accent-color: #FFBD2E;">
                                                        <span style="font-size: 0.8rem; color: {{ $diffOld->$field != $diffNew->$field ? 'var(--erp-white)' : 'var(--erp-muted)' }}; font-family: 'Inter', sans-serif;">
                                                            {{ is_numeric($diffOld->$field) && in_array($field, ['copq_turnover', 'calculated_leakage']) ? number_format($diffOld->$field) : $diffOld->$field }}
                                                        </span>
                                                    </label>
                        
                                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 8px; border-radius: 4px; transition: 0.2s; border: 1px solid {{ $mergeSelections[$field] === 'new' ? 'var(--erp-teal)' : 'transparent' }}; background: {{ $mergeSelections[$field] === 'new' ? 'rgba(0,229,255,0.1)' : 'transparent' }};">
                                                        <input type="radio" wire:model="mergeSelections.{{ $field }}" value="new" style="accent-color: var(--erp-teal);">
                                                        <span style="font-size: 0.8rem; color: {{ $diffOld->$field != $diffNew->$field ? 'var(--erp-white)' : 'var(--erp-muted)' }}; font-family: 'Inter', sans-serif;">
                                                            {{ is_numeric($diffNew->$field) && in_array($field, ['copq_turnover', 'calculated_leakage']) ? number_format($diffNew->$field) : $diffNew->$field }}
                                                        </span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                        
                                        <div style="display: flex; gap: 10px; margin-top: 25px;">
                                            <button wire:click="executeMerge" wire:loading.attr="disabled" wire:target="executeMerge" style="flex: 2; background: #FFBD2E; color: #000; border: none; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; padding: 14px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.boxShadow='0 0 15px rgba(255,189,46,0.4)'" onmouseout="this.style.boxShadow='none'">
                                                <span wire:loading.remove wire:target="executeMerge">STITCH & FINALIZE MASTER RECORD</span>
                                                <span wire:loading wire:target="executeMerge">PROCESSING...</span>
                                            </button>
                                            <button wire:click="closeDiffViewer" style="flex: 1; background: transparent; border: 1px solid var(--erp-muted); color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; padding: 14px; border-radius: 2px; cursor: pointer;">CANCEL</button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div style="padding: 30px; border: 1px dashed var(--erp-border-dark); text-align: center; margin-bottom: 30px; border-radius: 4px;">
                        <span style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> LEAD UNSCORED (MANUAL ENTRY)</span><br>
                        <button style="margin-top: 15px; background: transparent; border: 1px solid var(--erp-teal); color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; padding: 6px 15px; cursor: pointer; border-radius: 2px;">RUN COPQ DIAGNOSTIC</button>
                    </div>
                @endif
                
                <h4 style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal); margin-bottom: 10px; border-bottom: 1px dashed rgba(0,229,255,0.3); padding-bottom: 5px;">> RAW TELEMETRY</h4>
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-white); line-height: 2; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05);">
                    <span style="color: var(--erp-muted);">ORG_ID:</span> {{ $selectedLead->org_id }}<br>
                    <span style="color: var(--erp-muted);">CAPTURED:</span> {{ \Carbon\Carbon::parse($selectedLead->created_at)->format('Y-m-d H:i:s') }}<br>
                    <span style="color: var(--erp-muted);">LOCKED_TO:</span> {{ $selectedLead->owner_id ? $selectedLead->owner_first . ' ' . $selectedLead->owner_last : 'UNCLAIMED' }}<br>
                    <span style="color: var(--erp-muted);">UTM_SOURCE:</span> ORGANIC / DIRECT<br>
                </div>
            </div>
        @endif
    </div>

    @if($showIntakeModal)
        <div class="drawer-overlay {{ $showIntakeModal ? 'active' : '' }}" wire:click="closeIntakeModal"></div>

    <div class="slide-drawer {{ $showIntakeModal ? 'active' : '' }}" style="width: 800px;">
        <div class="drawer-header">
            <div>
                <span style="font-family: 'Roboto Mono', monospace; font-size: 1.1rem; color: #FFBD2E; font-weight: bold;">> INITIATE MANUAL PITCH</span>
                <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 4px;">Inject lead telemetry directly into the COPQ diagnostic vault.</div>
            </div>
            <button wire:click="closeIntakeModal" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>

        <div class="drawer-body">
            <form wire:submit.prevent="submitManualPitch" style="display: flex; flex-direction: column; gap: 20px;">
                
                <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">EXECUTIVE NAME</label>
                            <input type="text" wire:model="name" required class="cyber-input" placeholder="John Doe">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">CORPORATE EMAIL</label>
                            <input type="email" wire:model="email" required class="cyber-input" placeholder="john@enterprise.com">
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">COMPANY NAME</label>
                        <input type="text" wire:model="company" required class="cyber-input" placeholder="Enterprise LLC">
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">CURRENCY</label>
                        <select wire:model="currency" required class="cyber-select">
                            @foreach($dbCurrencies as $curr)
                                <option value="{{ $curr->currency_code }}">{{ $curr->currency_code }} - {{ $curr->currency_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">INDUSTRY</label>
                        <select wire:model="industry" required class="cyber-select">
                            <option value="">Select...</option>
                            @foreach($dbIndustries as $ind) 
                                <option value="{{ $ind->industry_name }}">{{ $ind->industry_name }}</option> 
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">TECH STACK</label>
                        <select wire:model="tool" required class="cyber-select">
                            <option value="">Select...</option>
                            @foreach($dbArchitectures as $arch) 
                                <option value="{{ $arch->tech_key }}">{{ $arch->display_name }}</option> 
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">BOTTLENECK</label>
                        <select wire:model="bottleneck" required class="cyber-select">
                            <option value="">Select...</option>
                            @foreach($dbBottlenecks as $bn) 
                                <option value="{{ $bn->bottleneck_name }}">{{ $bn->bottleneck_name }}</option> 
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="background: rgba(0,0,0,0.3); padding: 20px; border: 1px solid rgba(255,255,255,0.05); border-left: 2px solid #FFBD2E; border-radius: 4px;">
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 5px;">ANNUAL TURNOVER</label>
                            <input type="number" wire:model="turnover" required min="0" class="cyber-input" placeholder="e.g. 5000000">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 5px;">AVG CONTRACT (ACV)</label>
                            <input type="number" wire:model="acv" required min="0" class="cyber-input" placeholder="e.g. 25000">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 5px;">TOTAL HEADCOUNT</label>
                            <input type="number" wire:model="headcount" required min="1" class="cyber-input" placeholder="e.g. 150">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 5px;">MGMT NODES</label>
                            <input type="number" wire:model="mgmtNodes" required min="1" class="cyber-input" placeholder="e.g. 15">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 5px;">MANUAL HRS / WEEK</label>
                            <input type="number" wire:model="manualHrs" required min="0" class="cyber-input" placeholder="e.g. 5">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 5px;">HOURLY MGMT COST</label>
                            <input type="number" wire:model="hourlyCost" required min="0" class="cyber-input" placeholder="e.g. 50">
                        </div>
                        <div>
                            <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 5px;">ERROR RATE (%)</label>
                            <input type="number" step="0.1" wire:model="errorRate" required min="0" max="100" class="cyber-input" placeholder="e.g. 3.5">
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="submit" wire:loading.attr="disabled" wire:target="submitManualPitch" style="flex: 2; background: #FFBD2E; color: #000; border: none; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; padding: 15px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.boxShadow='0 0 15px rgba(255,189,46,0.4)'" onmouseout="this.style.boxShadow='none'">
                        <span wire:loading.remove wire:target="submitManualPitch">CALCULATE COPQ & REGISTER LEAD</span>
                        <span wire:loading wire:target="submitManualPitch">CALCULATING DIAGNOSTIC...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
    
    <script>
        document.addEventListener('livewire:navigated', () => {
            if(typeof lucide !== 'undefined') lucide.createIcons();
        });
    </script>
</div>