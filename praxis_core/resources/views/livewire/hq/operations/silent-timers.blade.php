<div wire:poll.60s="loadAllData" style="display: flex; flex-direction: column; min-height: 100vh; padding-bottom: 50px;">
    <style>
        /* Fluid Layout (No clipping) */
        .page-header { margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end; }
        
        .tab-nav { display: flex; gap: 5px; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 25px; }
        .tab-btn { background: transparent; border: 1px solid transparent; border-bottom: none; color: var(--erp-muted); padding: 12px 25px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; cursor: pointer; transition: 0.3s; font-weight: bold; outline: none; }
        .tab-btn:hover { color: var(--erp-teal); background: rgba(0,229,255,0.02); }
        .tab-btn.active { color: var(--erp-teal); border-color: var(--erp-border-dark); background: rgba(10, 17, 40, 0.8); border-top: 2px solid var(--erp-teal); border-radius: 4px 4px 0 0; }

        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; background: rgba(10, 17, 40, 0.4); border: 1px solid var(--erp-border-dark); }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-teal); text-transform: uppercase; background: rgba(0,229,255,0.05); }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; font-size: 0.8rem; color: #E2E8F0; }
        .bloomberg-table tbody tr:hover td { background: rgba(0, 229, 255, 0.05); }
        
        /* Group Headers for Ledger */
        .group-header td { background: rgba(255,255,255,0.02); color: var(--erp-gold); font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; border-top: 2px solid var(--erp-border-dark); }

        .status-badge { padding: 4px 8px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; display: inline-block; border: 1px solid transparent; }
        .badge-live { background: rgba(0, 255, 102, 0.1); color: #00FF66; border-color: #00FF66; animation: pulse-green 2s infinite; }
        .badge-rework { background: rgba(255, 189, 46, 0.1); color: #FFBD2E; border-color: #FFBD2E; }
        .badge-terminated { background: rgba(255, 51, 102, 0.1); color: var(--erp-red); border-color: var(--erp-red); }
        .badge-anomaly { background: rgba(255, 51, 102, 0.1); color: var(--erp-red); border-color: var(--erp-red); }

        @keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(0, 255, 102, 0.4); } 70% { box-shadow: 0 0 0 4px rgba(0, 255, 102, 0); } 100% { box-shadow: 0 0 0 0 rgba(0, 255, 102, 0); } }

        .btn-action { background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 8px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; border-radius: 2px; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; font-weight: bold; }
        .btn-action:hover { border-color: var(--erp-teal); color: var(--erp-teal); }
        .btn-danger { color: var(--erp-red); border-color: var(--erp-red); }
        .btn-danger:hover { background: rgba(255,51,102,0.1); color: var(--erp-red); }
        .btn-success { color: #00FF66; border-color: #00FF66; }
        .btn-success:hover { background: rgba(0,255,102,0.1); color: #00FF66; }

        .cyber-select { background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-teal); padding: 8px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; outline: none; transition: 0.3s; font-weight: bold; }
        .cyber-select option { background-color: var(--erp-bg-main); }
        .cyber-input { background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 8px 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; width: 100%; }
        .cyber-input:focus { border-color: var(--erp-teal); }

        .god-mode-box { background: rgba(255, 51, 102, 0.05); border: 1px dashed var(--erp-red); padding: 25px; margin-top: 40px; border-radius: 4px; }
        .god-mode-header { font-family: 'Montserrat', sans-serif; font-size: 1.1rem; color: var(--erp-red); font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
    </style>

    <div class="page-header">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">SILENT TIMERS & EOD MANAGER</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ LIVE MARGIN TELEMETRY & LEDGER AUDIT ]</p>
        </div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00FF66; display: flex; align-items: center; gap: 5px;">
                <span style="display: inline-block; width: 6px; height: 6px; background: #00FF66; border-radius: 50%; box-shadow: 0 0 5px #00FF66; animation: pulse-green 2s infinite;"></span> LIVE RADAR (60s)
            </div>
        </div>
    </div>

    @if (session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('error') }}</div>
    @endif
    @if (session()->has('success'))
        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('success') }}</div>
    @endif

    <div class="tab-nav">
        @if($userClearance >= 4)
            <button wire:click="$set('activeTab', 'live_burn')" class="tab-btn {{ $activeTab === 'live_burn' ? 'active' : '' }}">
                <i data-lucide="activity" style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;"></i> LIVE BURN
            </button>
            <button wire:click="$set('activeTab', 'anomalies')" class="tab-btn {{ $activeTab === 'anomalies' ? 'active' : '' }}">
                <i data-lucide="alert-triangle" style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;"></i> ANOMALIES
            </button>
        @endif
        
        <button wire:click="$set('activeTab', 'ledger')" class="tab-btn {{ $activeTab === 'ledger' ? 'active' : '' }}">
            <i data-lucide="book-open" style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;"></i> MASTER LEDGER
        </button>
        
        @if($userClearance >= 4)
            <button wire:click="$set('activeTab', 'corrections')" class="tab-btn {{ $activeTab === 'corrections' ? 'active' : '' }}">
                <i data-lucide="clipboard-check" style="width: 14px; height: 14px; display: inline-block; vertical-align: text-bottom;"></i> CORRECTIONS QUEUE
            </button>
        @endif
    </div>

    <!-- TAB 1: LIVE BURN -->
    @if($activeTab === 'live_burn' && $userClearance >= 4)
        <div style="overflow-x: auto;">
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>ENGINEER (NODE)</th>
                        <th>BATTLESPACE (PROJECT & TASK)</th>
                        <th>START TIME</th>
                        <th>ELAPSED</th>
                        <th>CURRENT MARGIN BURN</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($liveBurnData as $burn)
                        <tr>
                            <td style="font-weight: bold; color: var(--erp-white);">{{ $burn['first_name'] }} {{ $burn['last_name'] }}</td>
                            <td>
                                <div style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-bottom: 3px;">> {{ $burn['project_title'] }}</div>
                                <div>{{ $burn['task_title'] }}</div>
                            </td>
                            <td style="font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($burn['start_time'])->format('M d, H:i') }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; color: #FFBD2E; font-weight: bold;">{{ $burn['live_duration'] }}</td>
                            <td style="font-family: 'Roboto Mono', monospace; color: var(--erp-red); font-weight: bold;">- ${{ number_format($burn['live_cost'], 2) }}</td>
                            <td>
                                @if($burn['is_rework'])
                                    <span class="status-badge badge-rework">COPQ REWORK</span>
                                @else
                                    <span class="status-badge badge-live">ACTIVE EXECUTION</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--erp-muted);">NO ACTIVE TIMERS IN THE MATRIX.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- TAB 2: ANOMALIES -->
    @if($activeTab === 'anomalies' && $userClearance >= 4)
        <div style="overflow-x: auto;">
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>ENGINEER (NODE)</th>
                        <th>TASK TITLE</th>
                        <th>ESTIMATED (HOURS)</th>
                        <th>ACTUAL LOGGED</th>
                        <th>FLAG REASON</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($anomaliesData as $anomaly)
                        @php
                            $estimate = $anomaly->estimated_hours ?: ($anomaly->story_points * 3);
                            $actual = $anomaly->actual_hours_logged;
                            $reason = $actual > ($estimate * 1.5) ? 'EXCEEDS 1.5X ESTIMATE' : 'CONTINUOUS LOG > 4 HRS';
                        @endphp
                        <tr>
                            <td style="font-weight: bold; color: var(--erp-white);">{{ $anomaly->first_name }} {{ $anomaly->last_name }}</td>
                            <td>{{ $anomaly->title }}</td>
                            <td style="font-family: 'Roboto Mono', monospace;">{{ number_format($estimate, 2) }} HRS</td>
                            <td style="font-family: 'Roboto Mono', monospace; color: var(--erp-red); font-weight: bold;">{{ number_format($actual, 2) }} HRS</td>
                            <td><span class="status-badge badge-anomaly">{{ $reason }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--erp-muted);">SYSTEM NOMINAL. NO ANOMALIES DETECTED.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- TAB 3: MASTER LEDGER -->
    @if($activeTab === 'ledger')
        @if($userClearance >= 4)
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal);">> LEDGER GROUPING PREFERENCE</div>
                <select wire:model.live="ledgerGroupBy" class="cyber-select">
                    <option value="engineer">VIEW BY NODE (Engineer)</option>
                    <option value="project">VIEW BY BATTLESPACE (Project)</option>
                </select>
            </div>
        @endif

        <div style="overflow-x: auto;">
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>{{ $ledgerGroupBy === 'engineer' ? 'PROJECT / TASK' : 'ENGINEER / TASK' }}</th>
                        <th>START TIME</th>
                        <th>END TIME</th>
                        <th>DURATION</th>
                        @if($userClearance >= 4) <th>MARGIN COST</th> @endif
                        <th>FLAGS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgerData as $groupName => $logs)
                        <tr class="group-header">
                            <td colspan="{{ $userClearance >= 4 ? '6' : '5' }}">> {{ strtoupper($groupName) }}</td>
                        </tr>
                        @foreach($logs as $log)
                            <tr>
                                <td>
                                    @if($ledgerGroupBy === 'engineer')
                                        <div style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-bottom: 3px;">{{ $log->project_title }}</div>
                                        <div>{{ $log->task_title }}</div>
                                    @else
                                        <div style="font-weight: bold; color: var(--erp-white); margin-bottom: 3px;">{{ $log->first_name }} {{ $log->last_name }}</div>
                                        <div style="font-size: 0.75rem; color: var(--erp-muted);">{{ $log->task_title }}</div>
                                    @endif
                                </td>
                                <td style="font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($log->start_time)->format('M d, H:i') }}</td>
                                <td style="font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($log->end_time)->format('M d, H:i') }}</td>
                                <td style="font-family: 'Roboto Mono', monospace; font-weight: bold;">{{ number_format($log->duration_minutes / 60, 2) }} HRS</td>
                                
                                @if($userClearance >= 4)
                                    <td style="font-family: 'Roboto Mono', monospace; color: {{ $log->is_rework ? 'var(--erp-red)' : 'var(--erp-muted)' }};">
                                        ${{ number_format($log->cost_calculated, 2) }}
                                    </td>
                                @endif
                                
                                <td>
                                    @if($log->is_rework)
                                        <span class="status-badge badge-rework" style="margin-right: 5px;">COPQ REWORK</span>
                                    @endif
                                    @if($log->is_system_terminated)
                                        <span class="status-badge badge-terminated">SYSTEM TERMINATED</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="{{ $userClearance >= 4 ? '6' : '5' }}" style="text-align: center; padding: 40px; color: var(--erp-muted);">NO HISTORICAL LOGS FOUND.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- TAB 4: CORRECTIONS QUEUE & GOD MODE -->
    @if($activeTab === 'corrections' && $userClearance >= 4)
        <div style="margin-bottom: 40px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #FFBD2E; margin-bottom: 15px;">> PENDING L4 TIME CORRECTION REQUESTS</div>
            <div style="overflow-x: auto;">
                <table class="bloomberg-table">
                    <thead>
                        <tr>
                            <th>ENGINEER</th>
                            <th>TASK</th>
                            <th>REQUESTED WINDOW</th>
                            <th>EXPLANATION</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($correctionRequests as $req)
                            <tr>
                                <td style="font-weight: bold; color: var(--erp-white);">{{ $req->first_name }} {{ $req->last_name }}</td>
                                <td>{{ $req->task_title }}</td>
                                <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">
                                    {{ \Carbon\Carbon::parse($req->requested_start_time)->format('M d, H:i') }}<br>
                                    to {{ \Carbon\Carbon::parse($req->requested_end_time)->format('M d, H:i') }}
                                </td>
                                <td><div style="max-width: 250px; font-size: 0.75rem; color: var(--erp-muted);">{{ $req->reason_for_correction }}</div></td>
                                <td>
                                    <button wire:click="approveCorrection('{{ $req->id }}')" class="btn-action btn-success" style="margin-right: 5px;">
                                        <i data-lucide="check" style="width: 14px; height: 14px;"></i> APPROVE
                                    </button>
                                    <button wire:click="rejectCorrection('{{ $req->id }}')" class="btn-action btn-danger">
                                        <i data-lucide="x" style="width: 14px; height: 14px;"></i> REJECT
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--erp-muted);">NO PENDING CORRECTION REQUESTS.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($userClearance == 5)
            <div class="god-mode-box">
                <div class="god-mode-header">
                    <i data-lucide="shield-alert" style="width: 24px; height: 24px;"></i> L5 SUPER ADMIN OVERRIDE (GOD MODE INJECTION)
                </div>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 20px;">Forcefully inject time into the General Ledger bypassing the request queue. This action is permanently audited.</p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                    <div>
                        <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); display: block; margin-bottom: 5px;">SELECT NODE (USER)</label>
                        <select wire:model="godModeUserId" class="cyber-select" style="width: 100%;">
                            <option value="">-- Select Engineer --</option>
                            @foreach($allUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); display: block; margin-bottom: 5px;">SELECT TASK</label>
                        <select wire:model="godModeTaskId" class="cyber-select" style="width: 100%;">
                            <option value="">-- Select Task --</option>
                            @foreach($allTasks as $t)
                                <option value="{{ $t->id }}">{{ \Illuminate\Support\Str::limit($t->title, 30) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); display: block; margin-bottom: 5px;">START TIME</label>
                        <input type="datetime-local" wire:model="godModeStartTime" class="cyber-input">
                    </div>
                    <div>
                        <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); display: block; margin-bottom: 5px;">END TIME</label>
                        <input type="datetime-local" wire:model="godModeEndTime" class="cyber-input">
                    </div>
                    <div>
                        <button wire:click="executeGodMode" class="btn-action btn-danger" onclick="confirm('Execute God Mode? This permanently alters the financial ledger.') || event.stopImmediatePropagation()">
                            <i data-lucide="zap" style="width: 16px; height: 16px;"></i> INJECT TIME
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <script> document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); </script>
</div>