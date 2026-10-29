<div style="position: relative; min-height: 100vh; padding-bottom: 40px;" 
     x-data="{
         notes: { context: '', vision: '', pain: '', sla: '' },
         orgId: @entangle('activeOrgId'),
         isSynthesizing: false,
         
         init() {
             this.$watch('orgId', (val) => {
                 if(val) this.loadNotes();
             });
             if(this.orgId) this.loadNotes();
         },
         saveNotes() {
             if(!this.orgId) return;
             localStorage.setItem('praxis_notes_' + this.orgId, JSON.stringify(this.notes));
         },
         loadNotes() {
             let saved = localStorage.getItem('praxis_notes_' + this.orgId);
             if(saved) { this.notes = JSON.parse(saved); } 
             else { this.notes = { context: '', vision: '', pain: '', sla: '' }; }
         },
         clearNotes() {
             if(!confirm('Erase all unsaved notes in this buffer?')) return;
             localStorage.removeItem('praxis_notes_' + this.orgId);
             this.notes = { context: '', vision: '', pain: '', sla: '' };
         },
         dispatchWhatsApp(token) {
             if(!token) {
                 alert('ERROR: System token missing.');
                 return;
             }
             const url = @js(str(route('dossier.show', ['token' => 'TOKEN']))->beforeLast('TOKEN')->toString()) + token + '?exec=true';
             const message = encodeURIComponent('JBS Praxis | Executive Diagnostic.\n\nYour Cost of Poor Quality (COPQ) dossier has been compiled. Access the secure brief here:\n\n' + url);
             window.open('https://wa.me/?text=' + message, '_blank');
             document.getElementById('transmissionModal').style.display = 'none';
         },
         async closeAndSynthesize() {
             if(!this.notes.context && !this.notes.pain) {
                 alert('Warning: Buffer is empty. Please enter notes before synthesizing.');
                 return;
             }
             
             this.isSynthesizing = true;
             document.getElementById('synthesisModal').style.display = 'flex';
             
             // Reset Matrix Animation text
             const mo = document.getElementById('matrixOutput');
             mo.innerHTML = '> Extracting raw consultation variables...<br><span style=\'opacity: 0.5;\'>> Translating operational pain into architectural strategy...</span><br>';
             
             setTimeout(() => mo.innerHTML += '> Injecting JBS Praxis corporate DNA...<br>', 1500);
             setTimeout(() => mo.innerHTML += '<span style=\'color:#FFBD2E;\'>> Generating JSON blueprint payload...</span><br>', 3000);
             setTimeout(() => mo.innerHTML += '> Structuring Service Level Agreements...<br>', 4500);
             
             // Call Backend via native Livewire integration
             await $wire.closeAndSynthesize(this.notes);
         }
     }"
     @consultation-closed.window="isSynthesizing = false; if(orgId) localStorage.removeItem('praxis_notes_' + orgId); document.getElementById('synthesisModal').style.display = 'none';"
     @open-transmission-modal.window="document.getElementById('transmissionModal').style.display = 'flex';"
     @consultation-error.window="isSynthesizing = false; document.getElementById('synthesisModal').style.display = 'none';"
>
    <style>
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid var(--erp-border-dark); background: transparent; color: var(--erp-muted); display: inline-flex; align-items: center; justify-content: center; }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):not(:disabled):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }
        .nav-pill:disabled { opacity: 0.6; cursor: not-allowed; }

        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px 15px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; background: rgba(255,255,255,0.02); position: sticky; top: 0; z-index: 10; }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; }
        .bloomberg-table tr:hover td { background: rgba(0, 229, 255, 0.03); }

        .cyber-input, .cyber-select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; outline: none; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus { border-color: var(--erp-teal); background: rgba(0,229,255,0.05); }
        .cyber-select option { background-color: var(--erp-bg-main); }
        .cyber-textarea { width: 100%; background: rgba(255,255,255,0.02); border: 1px dashed var(--erp-border-dark); color: var(--erp-white); padding: 15px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; resize: vertical; min-height: 120px; line-height: 1.6; }
        .cyber-textarea:focus { border-style: solid; border-color: var(--erp-teal); background: rgba(0,229,255,0.02); }

        .glass-modal { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.9); backdrop-filter: blur(10px); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .modal-box { background: var(--erp-bg-main); border: 1px solid var(--erp-teal); border-top: 4px solid var(--erp-teal); border-radius: 4px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; }

        /* Privacy Mode Blurring */
        .privacy-on .sensitive-data { filter: blur(6px); transition: filter 0.3s; user-select: none; }
        .privacy-on .sensitive-data:hover { filter: blur(0px); }

        /* Split Engine Layout - Adjusted for unrestricted scrolling */
        .war-room { display: flex; height: 75vh; min-height: 600px; border: 1px solid var(--erp-border-dark); border-radius: 4px; overflow: hidden; background: rgba(10, 17, 40, 0.6); box-shadow: inset 0 0 20px rgba(0,0,0,0.5); }
        .war-room-left { flex: 1; border-right: 1px solid var(--erp-border-dark); overflow-y: auto; padding: 30px; background: rgba(0,0,0,0.2); }
        .war-room-right { flex: 1.2; overflow-y: auto; padding: 30px; }

        .section-label { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal); margin-bottom: 15px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 5px; text-transform: uppercase; }
        
        /* Scrollbars */
        .war-room-left::-webkit-scrollbar, .war-room-right::-webkit-scrollbar { width: 4px; }
        .war-room-left::-webkit-scrollbar-thumb, .war-room-right::-webkit-scrollbar-thumb { background: rgba(0, 229, 255, 0.2); border-radius: 2px; }

        /* Matrix Loading Animation */
        .matrix-loader { font-family: 'Roboto Mono', monospace; color: #00FF66; font-size: 0.85rem; line-height: 2; text-align: left; }
        .matrix-cursor { display: inline-block; width: 8px; height: 15px; background: #00FF66; animation: blink 1s step-end infinite; }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

        /* Spinner Animation */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spin-icon { animation: spin 1s linear infinite; }

        /* Transmission Options */
        .transmission-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 25px 15px; border: 1px dashed var(--erp-muted); border-radius: 4px; cursor: pointer; transition: all 0.3s ease; text-decoration: none; background: rgba(255,255,255,0.02); }
        .transmission-btn:hover { border-style: solid; transform: translateY(-3px); }
        .transmission-email:hover { border-color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); color: var(--erp-teal); }
        .transmission-wa:hover { border-color: #25D366; background: rgba(37, 211, 102, 0.05); color: #25D366; }
    </style>

    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">INTELLIGENCE DESK</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ LIVE_CONSULTATION_TERMINAL ] : SECURE WAR ROOM</p>
        </div>
        
        @if($activeView === 'terminal')
            <div style="display: flex; gap: 10px;">
                <button wire:click="togglePrivacy" class="nav-pill" style="border-color: {{ $privacyMode ? '#00FF66' : 'var(--erp-border-dark)' }}; color: {{ $privacyMode ? '#00FF66' : 'var(--erp-white)' }};">
                    <i data-lucide="{{ $privacyMode ? 'eye-off' : 'eye' }}" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> 
                    PRIVACY: {{ $privacyMode ? 'ON' : 'OFF' }}
                </button>
                <button onclick="document.getElementById('disqualifyModal').style.display = 'flex'" class="nav-pill" style="border-color: var(--erp-red); color: var(--erp-red); background: rgba(255,51,102,0.1);">[ DISQUALIFY LEAD ]</button>
                
                <!-- UPGRADED BUTTON STATE -->
                <button @click="closeAndSynthesize" x-bind:disabled="isSynthesizing" class="nav-pill" style="background: var(--erp-teal); color: #0A1128; border-color: var(--erp-teal); box-shadow: 0 0 15px rgba(0,229,255,0.4);">
                    <span x-show="!isSynthesizing">[ CLOSE & SYNTHESIZE AI ]</span>
                    <span x-show="isSynthesizing" style="display: flex; align-items: center; gap: 8px;">
                        <svg class="spin-icon" style="width: 14px; height: 14px; color: #0A1128;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        [ EXECUTING... ]
                    </span>
                </button>
            </div>
        @else
            <div style="display: flex; gap: 10px;">
                <a href="{{ route('hq.crm.leads') }}" wire:navigate class="nav-pill"><i data-lucide="arrow-left" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> BACK TO LEADS</a>
            </div>
        @endif
    </div>

    @if (session()->has('success'))
        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 20px; border-radius: 4px;">
            > {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 20px; border-radius: 4px;">
            > {{ session('error') }}
        </div>
    @endif

    @if($activeView === 'lobby')
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 20px;">
                <div class="section-label">> PENDING REQUESTS</div>
                <table class="bloomberg-table">
                    <tbody>
                        @forelse($requestedAppointments as $req)
                        <tr>
                            <td>
                                <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-white);">{{ $req->company_name }}</div>
                                <div style="font-size: 0.7rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($req->start_time)->format('M d, Y @ H:i') }}</div>
                            </td>
                            <td style="text-align: right;">
                                <button onclick="openApproveModal('{{ $req->id }}', '{{ $req->company_name }}')" style="background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; cursor: pointer; border-radius: 2px;">APPROVE & LINK</button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="2" style="padding: 40px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> NO PENDING REQUESTS</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 20px;">
                <div class="section-label">> SCHEDULED PROTOCOLS</div>
                <table class="bloomberg-table">
                    <tbody>
                        @forelse($scheduledAppointments as $sch)
                        <tr>
                            <td>
                                <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-white);">{{ $sch->company_name }}</div>
                                <div style="font-size: 0.7rem; color: var(--erp-teal);">{{ \Carbon\Carbon::parse($sch->start_time)->format('M d, Y @ H:i') }}</div>
                            </td>
                            <td style="text-align: right;">
                                <button wire:click="enterTerminal('{{ $sch->organization_id }}', '{{ $sch->id }}')" style="background: #FFBD2E; border: none; color: #000; padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; cursor: pointer; border-radius: 2px;">ENTER TERMINAL</button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="2" style="padding: 40px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> NO SCHEDULED MEETINGS</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($activeView === 'terminal')
        <div class="war-room {{ $privacyMode ? 'privacy-on' : '' }}">
            
            <div class="war-room-left">
                <div class="section-label">> TARGET DOSSIER</div>
                <div style="margin-bottom: 30px;">
                    <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.6rem; color: var(--erp-white); letter-spacing: -0.5px; margin-bottom: 5px;">{{ strtoupper($leadData->name) }}</h2>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">
                        INDUSTRY: <span style="color: var(--erp-white);">{{ strtoupper($leadData->industry) }}</span>
                    </div>
                </div>

                <div class="section-label">> DIAGNOSTIC CALCULUS (COPQ)</div>
                
                @if($copqData)
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted);">ANNUAL TURNOVER</span>
                            <span class="sensitive-data" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); font-weight: bold;">{{ $copqData->copq_currency }} {{ number_format($copqData->copq_turnover) }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted);">PRIMARY BOTTLENECK</span>
                            <span style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FFBD2E; font-weight: bold;">{{ strtoupper($copqData->bottleneck) }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 1px dashed var(--erp-border-dark); padding-top: 10px; margin-top: 10px;">
                            <span style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-red);">SYSTEMIC LEAKAGE (ANNUAL)</span>
                            <span class="sensitive-data" style="font-family: 'Roboto Mono', monospace; font-size: 1.1rem; color: var(--erp-red); font-weight: bold;">{{ $copqData->copq_currency }} {{ number_format($copqData->calculated_leakage) }}</span>
                        </div>
                    </div>
                    
                    <p style="font-size: 0.8rem; color: var(--erp-muted); line-height: 1.6; margin-bottom: 15px;">{{ $copqData->ai_friction_analysis }}</p>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 30px;">
                        <a href="{{ route('dossier.show', ['token' => $copqData->report_token]) }}?exec=true" target="_blank" style="flex: 1; text-align: center; background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; padding: 8px; text-decoration: none; border-radius: 2px;">OPEN DOSSIER</a>
                        <button wire:click="dispatchCopqEmail" style="flex: 1; background: transparent; border: 1px dashed var(--erp-muted); color: var(--erp-white); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; padding: 8px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.borderColor='var(--erp-teal)'; this.style.color='var(--erp-teal)';" onmouseout="this.style.borderColor='var(--erp-muted)'; this.style.color='var(--erp-white)';">TRANSMIT SECURELY</button>
                    </div>
                @else
                    <div style="background: rgba(0,0,0,0.4); border: 1px dashed #FFBD2E; padding: 20px; border-radius: 4px; margin-bottom: 30px;">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #FFBD2E; margin-bottom: 15px; font-weight: bold;">> LEAD UNSCORED. INITIATE INLINE DIAGNOSTIC.</div>
                        <form wire:submit.prevent="generateInlineCopq" style="display: flex; flex-direction: column; gap: 10px;">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 4px;">INDUSTRY</label><select wire:model="inlineIndustry" required class="cyber-select" style="padding: 6px;"><option value="">Select...</option>@foreach($dbIndustries as $ind) <option value="{{ $ind->industry_name }}">{{ $ind->industry_name }}</option> @endforeach</select></div>
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 4px;">BOTTLENECK</label><select wire:model="inlineBottleneck" required class="cyber-select" style="padding: 6px;"><option value="">Select...</option>@foreach($dbBottlenecks as $bn) <option value="{{ $bn->bottleneck_name }}">{{ $bn->bottleneck_name }}</option> @endforeach</select></div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 4px;">CURRENCY</label><select wire:model="inlineCurrency" required class="cyber-select" style="padding: 6px;">@foreach($dbCurrencies as $curr) <option value="{{ $curr->currency_code }}">{{ $curr->currency_code }}</option> @endforeach</select></div>
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 4px;">TECH STACK</label><select wire:model="inlineTool" required class="cyber-select" style="padding: 6px;"><option value="">Select...</option>@foreach($dbArchitectures as $arch) <option value="{{ $arch->tech_key }}">{{ $arch->display_name }}</option> @endforeach</select></div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-teal); margin-bottom: 4px;">TURNOVER</label><input type="number" wire:model="inlineTurnover" required class="cyber-input" style="padding: 6px;"></div>
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-teal); margin-bottom: 4px;">AVG CONTRACT (ACV)</label><input type="number" wire:model="inlineAcv" class="cyber-input" style="padding: 6px;"></div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 4px;">HEADCOUNT</label><input type="number" wire:model="inlineHeadcount" required class="cyber-input" style="padding: 6px;"></div>
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #FFBD2E; margin-bottom: 4px;">MGMT NODES</label><input type="number" wire:model="inlineMgmtNodes" required class="cyber-input" style="padding: 6px;"></div>
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 4px;">MANUAL HRS/WK</label><input type="number" wire:model="inlineManualHrs" required class="cyber-input" style="padding: 6px;"></div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 4px;">HOURLY COST</label><input type="number" wire:model="inlineHourlyCost" required class="cyber-input" style="padding: 6px;"></div>
                                <div><label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-red); margin-bottom: 4px;">ERROR RATE (%)</label><input type="number" step="0.1" wire:model="inlineErrorRate" required class="cyber-input" style="padding: 6px;"></div>
                            </div>
                            
                            <button type="submit" style="background: #FFBD2E; color: #000; border: none; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; padding: 10px; margin-top: 10px; border-radius: 2px; cursor: pointer;">CALCULATE & INJECT</button>
                        </form>
                    </div>
                @endif

                <div class="section-label">> HISTORICAL INTELLIGENCE</div>
                @if($historySummary)
                    <div style="background: rgba(0,229,255,0.05); border-left: 2px solid var(--erp-teal); padding: 15px; margin-bottom: 15px; border-radius: 2px;">
                        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); font-weight: bold; display: block; margin-bottom: 5px;">AI BRIEFING SUMMARY:</span>
                        <p style="font-size: 0.8rem; color: var(--erp-white); line-height: 1.5; margin: 0;">{{ $historySummary }}</p>
                    </div>
                @endif

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @forelse($historyData as $note)
                        <div style="background: rgba(255,255,255,0.02); padding: 10px 15px; border-radius: 2px; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted);">
                                <span>{{ strtoupper($note->interaction_type) }}</span>
                                <span>{{ \Carbon\Carbon::parse($note->created_at)->format('M d, Y') }}</span>
                            </div>
                            <p style="font-size: 0.75rem; color: #B0B8C4; margin: 0; line-height: 1.5;">{{ Str::limit($note->note_text, 150) }}</p>
                        </div>
                    @empty
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); text-align: center; padding: 20px;">No previous interactions logged.</div>
                    @endforelse
                </div>

            </div>

            <div class="war-room-right">
                <div class="section-label" style="display: flex; justify-content: space-between;">
                    <span>> CONSULTATION CAPTURE (AUTO-SAVING)</span>
                    <span @click="clearNotes" style="color: var(--erp-red); cursor: pointer; text-decoration: underline;">CLEAR BUFFER</span>
                </div>
                
                <p style="font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 25px;">
                    Type rapidly. Data is stored natively in your browser. Do not worry about formatting; the AI synthesis engine will restructure this payload upon closing.
                </p>

                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px;">
                            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-white); font-weight: bold;">1. CLIENT CONTEXT & OPERATIONS</label>
                            <span style="font-size: 0.65rem; color: var(--erp-muted); font-style: italic;">What do they do? Current infrastructure?</span>
                        </div>
                        <textarea x-model="notes.context" @input.debounce.500ms="saveNotes" class="cyber-textarea" placeholder="e.g., Telecom distributor using 5 fragmented Excel sheets..."></textarea>
                    </div>

                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px;">
                            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-white); font-weight: bold;">2. SCALE & EXPANSION VISION</label>
                            <span style="font-size: 0.65rem; color: var(--erp-muted); font-style: italic;">Where are they going in 5 years?</span>
                        </div>
                        <textarea x-model="notes.vision" @input.debounce.500ms="saveNotes" class="cyber-textarea" placeholder="e.g., Expanding into Chad/CAR next year. Need multi-currency..."></textarea>
                    </div>

                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px;">
                            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-red); font-weight: bold;">3. OPERATIONAL BOTTLENECKS (PAIN)</label>
                            <span style="font-size: 0.65rem; color: var(--erp-muted); font-style: italic;">Where is the bleeding?</span>
                        </div>
                        <textarea x-model="notes.pain" @input.debounce.500ms="saveNotes" class="cyber-textarea" style="border-color: rgba(255,51,102,0.3);" placeholder="e.g., CEO spends 10 hours a week approving paper POs..."></textarea>
                    </div>

                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px;">
                            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); font-weight: bold;">4. REQUIREMENTS, SLA & BUDGET</label>
                            <span style="font-size: 0.65rem; color: var(--erp-muted); font-style: italic;">What must we deliver? ISO? Cloud?</span>
                        </div>
                        <textarea x-model="notes.sla" @input.debounce.500ms="saveNotes" class="cyber-textarea" placeholder="e.g., Must be hosted locally. Budget capped at $40k..."></textarea>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modals -->
    <div class="glass-modal" id="approveModal" style="display: none;">
        <div class="modal-box" style="max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; margin-bottom: 20px;">
                <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-teal); font-weight: bold;">> SECURE ZOOM / TEAMS LINK</span>
                <button onclick="document.getElementById('approveModal').style.display = 'none'" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
            </div>
            <p style="font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 20px;">Approve consultation for <strong id="appClientName" style="color: var(--erp-white);"></strong>. Paste the unique meeting link below to dispatch the email.</p>
            <input type="hidden" id="appIdInput">
            <input type="url" id="appLinkInput" class="cyber-input" placeholder="https://zoom.us/j/..." style="margin-bottom: 20px;">
            <div style="display: flex; gap: 10px;">
                <button onclick="submitApproval()" style="flex: 2; background: var(--erp-teal); color: #0A1128; border: none; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; padding: 12px; border-radius: 2px; cursor: pointer;">APPROVE & DISPATCH</button>
            </div>
        </div>
    </div>

    <div class="glass-modal" id="disqualifyModal" style="display: none;">
        <div class="modal-box" style="max-width: 500px; border-color: var(--erp-red); border-top-color: var(--erp-red);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,51,102,0.2); padding-bottom: 15px; margin-bottom: 20px;">
                <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-red); font-weight: bold;">> INITIATE DISQUALIFICATION</span>
                <button onclick="document.getElementById('disqualifyModal').style.display = 'none'" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
            </div>
            <p style="font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 20px;">You are about to permanently archive this lead. State the reason for intelligence tracking.</p>
            <textarea id="dqReasonInput" class="cyber-textarea" style="min-height: 80px; margin-bottom: 20px;" placeholder="e.g. Budget too low, unresponsive, outside scope..."></textarea>
            <div style="display: flex; gap: 10px;">
                <button onclick="submitDisqualify()" style="flex: 2; background: var(--erp-red); color: #FFF; border: none; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; padding: 12px; border-radius: 2px; cursor: pointer;">KILL DEAL & ARCHIVE</button>
            </div>
        </div>
    </div>

    <!-- NEW: Transmission Dispatch Modal -->
    <div class="glass-modal" id="transmissionModal" style="display: none;">
        <div class="modal-box" style="max-width: 450px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; margin-bottom: 20px;">
                <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-teal); font-weight: bold;">> DISPATCH PROTOCOL</span>
                <button onclick="document.getElementById('transmissionModal').style.display = 'none'" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
            </div>
            
            <p style="font-size: 0.8rem; color: var(--erp-muted); margin-bottom: 25px; line-height: 1.6;">
                Select the transmission vector to deploy the COPQ diagnostic report to the client.
            </p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <button type="button" @click="dispatchWhatsApp('{{ $copqData->report_token ?? '' }}')" class="transmission-btn transmission-wa">
                    <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px;">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold;">WHATSAPP</span>
                    <span style="font-size: 0.6rem; margin-top: 5px; opacity: 0.7;">Direct Link Injection</span>
                </button>

                <button type="button" onclick="alert('Email Dispatch Logic Pending Verification'); document.getElementById('transmissionModal').style.display = 'none';" class="transmission-btn transmission-email">
                    <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 10px;">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold;">SECURE EMAIL</span>
                    <span style="font-size: 0.6rem; margin-top: 5px; opacity: 0.7;">SMTP PDF Delivery</span>
                </button>
            </div>
        </div>
    </div>

    <div class="glass-modal" id="synthesisModal" style="display: none; background: rgba(0,0,0,0.95);">
        <div style="width: 100%; max-width: 600px; text-align: center;">
            <i data-lucide="cpu" style="width: 48px; height: 48px; color: #00FF66; margin-bottom: 20px; animation: pulse 2s infinite;"></i>
            <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; color: var(--erp-white); letter-spacing: 2px; margin-bottom: 20px;">SYNTHESIZING NEURAL PAYLOAD</h2>
            
            <div style="background: rgba(10, 17, 40, 0.8); border: 1px solid rgba(0, 255, 102, 0.3); padding: 30px; border-radius: 4px; box-shadow: 0 0 30px rgba(0, 255, 102, 0.1);">
                <div class="matrix-loader" id="matrixOutput">
                    <!-- Javascript populates this via setTimeouts -->
                </div>
                <span class="matrix-cursor"></span>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:navigated', () => {
            if(typeof lucide !== 'undefined') lucide.createIcons();
        });

        // 1. Lobby Approval Trigger
        function openApproveModal(id, name) {
            document.getElementById('appIdInput').value = id;
            document.getElementById('appClientName').innerText = name;
            document.getElementById('approveModal').style.display = 'flex';
        }
        function submitApproval() {
            const id = document.getElementById('appIdInput').value;
            const link = document.getElementById('appLinkInput').value;
            @this.approveMeeting(id, link);
            document.getElementById('approveModal').style.display = 'none';
        }

        // 2. Kill Switch Trigger
        function submitDisqualify() {
            const reason = document.getElementById('dqReasonInput').value;
            @this.disqualifyLead(reason);
            document.getElementById('disqualifyModal').style.display = 'none';
        }
    </script>
</div>