<div style="position: relative; min-height: 100vh; padding-bottom: 50px;">
    <style>
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid var(--erp-border-dark); background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }
        
        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-teal); text-transform: uppercase; background: rgba(0,229,255,0.05); position: sticky; top: 0; z-index: 10; }
        .bloomberg-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; font-size: 0.85rem; color: #E2E8F0; }
        .bloomberg-table tr:hover td { background: rgba(0, 229, 255, 0.03); }

        .status-badge { padding: 5px 10px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .status-queued { background: rgba(255, 189, 46, 0.1); color: #FFBD2E; border: 1px solid #FFBD2E; }
        .status-sent { background: rgba(0, 255, 102, 0.1); color: #00FF66; border: 1px solid #00FF66; }
        .status-failed { background: rgba(255, 51, 102, 0.1); color: var(--erp-red); border: 1px solid var(--erp-red); }

        .btn-action { background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; border-radius: 2px; transition: 0.3s; }
        .btn-action:hover:not(:disabled) { border-color: var(--erp-teal); color: var(--erp-teal); }
        .btn-action:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .btn-primary { background: var(--erp-teal); color: var(--erp-bg-main); border: 1px solid var(--erp-teal); padding: 15px 30px; font-family: 'Roboto Mono', monospace; font-size: 0.9rem; font-weight: bold; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; }
        .btn-primary:hover:not(:disabled) { background: var(--erp-white); box-shadow: 0 0 15px rgba(0,229,255,0.4); }
        .btn-primary:disabled { background: rgba(0,229,255,0.2); color: rgba(255,255,255,0.3); border-color: transparent; cursor: not-allowed; box-shadow: none; }

        .btn-ai { background: rgba(212, 175, 55, 0.1); color: var(--erp-gold); border: 1px solid var(--erp-gold); padding: 10px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-ai:hover:not(:disabled) { background: var(--erp-gold); color: var(--erp-bg-main); box-shadow: 0 0 15px rgba(212,175,55,0.4); }

        .cyber-input, .cyber-select, .cyber-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { border-color: var(--erp-teal); background: rgba(0,229,255,0.02); }
        .cyber-select option { background-color: var(--erp-bg-main); }
        .form-label { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; display: block; }
        
        .cyber-checkbox { appearance: none; width: 16px; height: 16px; border: 1px solid var(--erp-teal); background: rgba(0,0,0,0.5); border-radius: 2px; cursor: pointer; position: relative; display: inline-block; vertical-align: middle; }
        .cyber-checkbox:checked { background: var(--erp-teal); }
        .cyber-checkbox:checked::after { content: '✓'; position: absolute; color: var(--erp-bg-main); font-size: 12px; font-weight: bold; top: -1px; left: 2px; }

        .split-layout { display: flex; gap: 30px; align-items: stretch; margin-bottom: 30px; }
        .pane-left { flex: 1; background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); padding: 30px; border-radius: 4px; }
        .pane-right { flex: 2; background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); padding: 30px; border-radius: 4px; }
        .pane-full { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); padding: 30px; border-radius: 4px; margin-bottom: 30px; }

        .system-lock-banner { background: rgba(255, 189, 46, 0.1); border: 1px solid #FFBD2E; padding: 20px; text-align: center; border-radius: 4px; margin-bottom: 25px; animation: pulse-border 2s infinite; }
        @keyframes pulse-border { 0% { box-shadow: 0 0 0 0 rgba(255, 189, 46, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(255, 189, 46, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 189, 46, 0); } }

        .var-pill { display: inline-block; background: rgba(0, 229, 255, 0.1); border: 1px dashed var(--erp-teal); color: var(--erp-teal); padding: 2px 6px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; border-radius: 2px; user-select: all; margin-right: 5px; margin-bottom: 5px; }
    </style>

    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">THE BROADCAST LEDGER</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ INTELLIGENCE MASS DISPATCH & TELEMETRY ]</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="switchTab('composer')" class="nav-pill {{ $activeTab === 'composer' ? 'active' : '' }}">AI COMPOSER & DISPATCH</button>
            <button wire:click="switchTab('queued')" class="nav-pill {{ $activeTab === 'queued' ? 'active' : '' }}">SMTP QUEUE ({{ count($queuedLogs) }})</button>
            <button wire:click="switchTab('sent')" class="nav-pill {{ $activeTab === 'sent' ? 'active' : '' }}">DELIVERED & TRACKED</button>
            <button wire:click="switchTab('failed')" class="nav-pill {{ $activeTab === 'failed' ? 'active' : '' }}" style="{{ count($failedLogs) > 0 ? 'color: var(--erp-red); border-color: var(--erp-red);' : '' }}">BOUNCED / FAILED</button>
        </div>
    </div>

    @if (session()->has('success'))
        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('error') }}</div>
    @endif

    @if($activeTab === 'composer')
        
        @if($activeLock)
            <div class="system-lock-banner">
                <i data-lucide="lock" style="width: 32px; height: 32px; color: #FFBD2E; margin: 0 auto 10px auto;"></i>
                <h3 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFBD2E; margin-bottom: 5px;">SMTP PORT LOCKED</h3>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white);">
                    A broadcast is currently processing in the background to prevent server blacklisting.<br>
                    Estimated Unlocking Time: <span style="font-weight: bold; color: #FFBD2E;">{{ $lockEta }}</span>
                </p>
            </div>
        @endif

        <div class="split-layout">
            <!-- PANE LEFT: QUERY ENGINE -->
            <div class="pane-left">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal); margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">> CRM QUERY ENGINE</div>
                
                <div style="margin-bottom: 15px;">
                    <label class="form-label">CLIENT STATUS</label>
                    <select wire:model.live="filterStatus" class="cyber-select">
                        <option value="">-- ALL SUBSCRIBED USERS --</option>
                        <option value="Lead">Strictly Leads</option>
                        <option value="Active Client">Active Clients</option>
                        <option value="Past Client">Past Clients</option>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="form-label">INDUSTRY ISOLATION</label>
                    <select wire:model.live="filterIndustry" class="cyber-select">
                        <option value="">-- ANY INDUSTRY --</option>
                        @foreach($availableIndustries as $ind)
                            <option value="{{ $ind }}">{{ $ind }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="form-label">TURNOVER BRACKET</label>
                    <select wire:model.live="filterTurnover" class="cyber-select">
                        <option value="">-- ANY TURNOVER --</option>
                        @foreach($availableTurnovers as $turn)
                            <option value="{{ $turn }}">{{ $turn }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 25px;">
                    <label class="form-label">PIPELINE STAGE</label>
                    <select wire:model.live="filterStage" class="cyber-select">
                        <option value="">-- ANY STAGE --</option>
                        @foreach($availableStages as $stage)
                            <option value="{{ $stage }}">{{ $stage }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="background: rgba(0,0,0,0.4); border: 1px solid var(--erp-border-dark); padding: 20px; text-align: center;">
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">TARGETS MATCHING QUERY</div>
                    <div style="font-family: 'Montserrat', sans-serif; font-size: 2.5rem; font-weight: 900; color: var(--erp-teal); line-height: 1;">{{ count($targetUsers) }}</div>
                </div>
            </div>

            <!-- PANE RIGHT: COMPOSER -->
            <div class="pane-right">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal); margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">> INTELLIGENCE DESK COMPOSER</div>
                
                <div style="margin-bottom: 20px;">
                    <label class="form-label">SUBJECT LINE (WILL BE SENT EXACTLY AS TYPED)</label>
                    <input type="text" wire:model="subject" class="cyber-input" placeholder="e.g. Strategic Update regarding your operations at @{{ company_name }}">
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="form-label">SUPPORTED DYNAMIC INJECTIONS (CLICK TO COPY)</label>
                    <div style="margin-bottom: 10px;">
                        <span class="var-pill">@{{ first_name }}</span>
                        <span class="var-pill">@{{ company_name }}</span>
                        <span class="var-pill">@{{ industry }}</span>
                        <span class="var-pill">@{{ pipeline_stage }}</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label class="form-label">RAW DRAFT (TYPE MESSY THOUGHTS HERE)</label>
                        <textarea wire:model="rawBody" class="cyber-textarea" style="min-height: 250px;" placeholder="Hey @{{ first_name }}, just checking in to see if @{{ company_name }} wants to do the audit we talked about..."></textarea>
                        @error('rawBody') <div style="color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-top: 5px;">> {{ $message }}</div> @enderror
                        
                        <div style="margin-top: 15px;">
                            <label class="form-label">GEMINI TONE DIRECTIVE</label>
                            <select wire:model="selectedTone" class="cyber-select" style="margin-bottom: 10px; border-color: var(--erp-gold);">
                                <option value="Consultative & Authoritative">Consultative & Authoritative (Default)</option>
                                <option value="Aggressive Follow-up">Aggressive / Urgent Follow-up</option>
                                <option value="Highly Technical & Analytical">Highly Technical & Analytical</option>
                                <option value="Polite & Soft Check-in">Polite Check-in</option>
                            </select>
                            
                            <button wire:click="enhanceWithGemini" class="btn-ai" wire:loading.attr="disabled" wire:target="enhanceWithGemini">
                                <span wire:loading.remove wire:target="enhanceWithGemini"><i data-lucide="sparkles" style="width: 16px; height: 16px;"></i> ENHANCE WITH GEMINI</span>
                                <span wire:loading wire:target="enhanceWithGemini">PROCESSING VIA AI...</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" style="color: var(--erp-teal);">FINAL HTML BODY (REVIEW & EDIT BEFORE DISPATCH)</label>
                        <textarea wire:model="enhancedBody" class="cyber-textarea" style="min-height: 380px; border-color: var(--erp-teal); font-family: monospace; font-size: 0.8rem; background: rgba(0, 229, 255, 0.02);" placeholder="Awaiting AI generation..."></textarea>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-top: 5px;">
                            *This box contains the final text sent to the client. The tracking pixel and unsubscribe link will be automatically appended.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PANE FULL: TARGET LEDGER & DISPATCH -->
        <div class="pane-full">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">
                <div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal);">> TARGET ACQUISITION LEDGER</div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-top: 4px;">Select or deselect specific executives. Maximum 200 records displayed.</div>
                </div>
                <div>
                    <button wire:click="toggleSelectAll" class="btn-action" style="margin-right: 15px;">
                        <i data-lucide="check-square" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle;"></i> TOGGLE SELECT ALL
                    </button>
                    <span style="font-family: 'Montserrat', sans-serif; font-size: 1.1rem; color: var(--erp-white); font-weight: bold;">
                        <span style="color: var(--erp-teal);">{{ $recipientCount }}</span> TARGETS SELECTED
                    </span>
                </div>
            </div>

            <div style="max-height: 400px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 25px; background: rgba(0,0,0,0.2);">
                <table class="bloomberg-table">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">INC</th>
                            <th>Target Executive</th>
                            <th>Enterprise Identity</th>
                            <th>Designation / Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($targetUsers as $user)
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" wire:model.live="selectedTargets" value="{{ $user['id'] }}" class="cyber-checkbox">
                                </td>
                                <td>
                                    <div style="font-weight: bold; color: var(--erp-white);">{{ $user['first_name'] }} {{ $user['last_name'] }}</div>
                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted);">{{ $user['email'] }}</div>
                                </td>
                                <td>{{ $user['company_name'] }}</td>
                                <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: {{ empty($user['job_title']) ? 'var(--erp-muted)' : 'var(--erp-teal)' }};">
                                    {{ empty($user['job_title']) ? '[ ROLE UNDEFINED ]' : strtoupper($user['job_title']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO TARGETS MATCH THE CURRENT QUERY PARAMETERS</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(!$activeLock)
                <button wire:click="dispatchCampaign" class="btn-primary" @if($recipientCount === 0 || empty($enhancedBody)) disabled @endif>
                    <i data-lucide="send" style="width: 18px; height: 18px;"></i> INITIATE SECURE DISPATCH ({{ $recipientCount }} EMAILS)
                </button>
                @if(empty($enhancedBody))
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); text-align: center; margin-top: 10px;">AI Enhancement Required Before Dispatch.</div>
                @endif
                @if($recipientCount > 0)
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-amber); text-align: center; margin-top: 10px;">
                        Estimated Send Time: {{ ceil($recipientCount / 30) * 5 }} Minutes
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if($activeTab === 'queued')
        <div class="pane-full" style="min-height: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">> EMAILS AWAITING BACKGROUND DISPATCH</div>
                <button wire:click="checkSystemLock" class="btn-action"><i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i> REFRESH QUEUE STATUS</button>
            </div>
            
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>Queued Timestamp</th>
                        <th>Target Executive</th>
                        <th>Target Enterprise</th>
                        <th>Subject Line</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($queuedLogs as $log)
                        <tr>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <div style="font-weight: bold; color: var(--erp-white);">{{ $log->first_name }} {{ $log->last_name }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal);">{{ $log->email }}</div>
                            </td>
                            <td>{{ $log->company_name }}</td>
                            <td style="font-size: 0.8rem; color: #B0B8C4;">{{ Str::limit($log->subject, 50) }}</td>
                            <td><span class="status-badge status-queued">QUEUED (THROTTLED)</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO EMAILS IN PIPELINE</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($activeTab === 'sent')
        <div class="pane-full" style="min-height: 500px;">
            <!-- UI For Filters (Ready to be wired up later) -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 15px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">> SUCCESSFUL DISPATCHES & PIXEL TELEMETRY (Top 200)</div>
                <div style="display: flex; gap: 15px; width: 50%;">
                    <input type="text" class="cyber-input" style="padding: 8px;" placeholder="Search by Subject or Enterprise...">
                    <input type="date" class="cyber-input" style="padding: 8px; width: 150px;">
                </div>
            </div>
            
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>Dispatch Time</th>
                        <th>Recipient</th>
                        <th>Enterprise</th>
                        <th>Subject Line</th>
                        <th style="text-align: center;">Opens</th>
                        <th>Geo-Location / IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sentLogs as $log)
                        <tr>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($log->updated_at)->format('Y-m-d H:i') }}</td>
                            <td>
                                <div style="font-weight: bold; color: var(--erp-white);">{{ $log->first_name }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal);">{{ $log->email }}</div>
                            </td>
                            <td>{{ $log->company_name }}</td>
                            <td style="font-size: 0.8rem; color: #B0B8C4;">{{ Str::limit($log->subject, 40) }}</td>
                            <td style="text-align: center; font-family: 'Roboto Mono', monospace;">
                                @if(isset($log->open_count) && $log->open_count > 0)
                                    <span style="background: rgba(0, 255, 102, 0.1); color: #00FF66; border: 1px solid #00FF66; padding: 2px 6px; border-radius: 2px;">{{ $log->open_count }}</span>
                                @else
                                    <span style="color: var(--erp-muted);">0</span>
                                @endif
                            </td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">
                                @if(isset($log->last_open_geo) && $log->last_open_geo)
                                    <div style="color: var(--erp-white);">{{ $log->last_open_geo }}</div>
                                    <div style="color: var(--erp-muted);">{{ $log->last_open_ip }}</div>
                                @else
                                    <span style="color: var(--erp-muted);">Awaiting Telemetry...</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO DISPATCH HISTORY DETECTED</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($activeTab === 'failed')
        <div class="pane-full" style="min-height: 500px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-red); margin-bottom: 20px;">> DELIVERY FAILURES & BOUNCE LEDGER</div>
            
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>Failure Time</th>
                        <th>Target Identity</th>
                        <th>Target Enterprise</th>
                        <th>SMTP Error Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($failedLogs as $log)
                        <tr>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($log->updated_at)->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <div style="font-weight: bold; color: var(--erp-white);">{{ $log->first_name }} {{ $log->last_name }}</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-red);">{{ $log->email }}</div>
                            </td>
                            <td>{{ $log->company_name }}</td>
                            <td style="font-size: 0.75rem; color: var(--erp-red); font-family: 'Roboto Mono', monospace; background: rgba(255,51,102,0.05); padding: 10px;">
                                {{ $log->error_message ?? 'Unknown SMTP bounce' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">SYSTEM HEALTH NOMINAL. NO BOUNCES DETECTED.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <script> 
        document.addEventListener('livewire:navigated', () => { 
            if(typeof lucide !== 'undefined') lucide.createIcons(); 
        }); 
        
        // Ensure standard load works too
        document.addEventListener('DOMContentLoaded', () => { 
            if(typeof lucide !== 'undefined') lucide.createIcons(); 
        });
    </script>
</div>