<div x-data="{ openCreateDrawer: false }" style="position: relative; min-height: 100vh; padding-bottom: 50px;">
    <style>
        /* Base Praxis Typography & Elements */
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid var(--erp-border-dark); background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }
        
        .cyber-input, .cyber-select, .cyber-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 10px 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { border-color: var(--erp-teal); background: rgba(0,229,255,0.02); }
        .cyber-select option { background-color: var(--erp-bg-main); color: var(--erp-white); }
        .form-label { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; display: block; }

        .btn-action { background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 8px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; border-radius: 2px; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-action:hover { border-color: var(--erp-teal); color: var(--erp-teal); background: rgba(0,229,255,0.05); }
        .btn-primary { background: var(--erp-teal); color: var(--erp-bg-main); border: 1px solid var(--erp-teal); padding: 12px 25px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; }
        .btn-primary:hover:not(:disabled) { background: var(--erp-white); box-shadow: 0 0 15px rgba(0,229,255,0.4); }
        .btn-danger { color: var(--erp-red); border-color: var(--erp-red); }
        .btn-danger:hover { background: rgba(255,51,102,0.1); color: var(--erp-red); border-color: var(--erp-red); }

        /* Metrics & Matrix Cards */
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .metric-card { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); padding: 20px; border-left: 2px solid var(--erp-teal); }
        .metric-title { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; }
        .metric-val { font-family: 'Roboto Mono', monospace; font-size: 1.5rem; color: var(--erp-white); font-weight: bold; }

        .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .project-card { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px; display: flex; flex-direction: column; transition: 0.3s; position: relative; overflow: hidden; }
        .project-card:hover { border-color: var(--erp-teal); box-shadow: 0 10px 30px rgba(0,0,0,0.5); transform: translateY(-2px); }
        
        .card-locked { border-top: 3px solid var(--erp-red); }
        .card-warning { border-top: 3px solid #FFBD2E; }
        .card-active { border-top: 3px solid var(--erp-teal); }

        .client-name { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal); text-transform: uppercase; margin-bottom: 5px; letter-spacing: 1px; }
        .project-title { font-family: 'Montserrat', sans-serif; font-size: 1.1rem; color: var(--erp-white); font-weight: bold; margin-bottom: 15px; line-height: 1.3; }

        .status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; border: 1px solid transparent; }
        .pill-active { background: rgba(0, 229, 255, 0.1); color: var(--erp-teal); border-color: var(--erp-teal); }
        .pill-locked { background: rgba(255, 51, 102, 0.1); color: var(--erp-red); border-color: var(--erp-red); animation: pulse-red 2s infinite; }
        .pill-warning { background: rgba(255, 189, 46, 0.1); color: #FFBD2E; border-color: #FFBD2E; }
        .pill-scoping { background: rgba(138, 155, 178, 0.1); color: #8A9BB2; border-color: #8A9BB2; }
        .pill-completed { background: rgba(0, 255, 102, 0.1); color: #00FF66; border-color: #00FF66; }

        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(255, 51, 102, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0); } }

        .ring-container { position: relative; width: 60px; height: 60px; display: flex; justify-content: center; align-items: center; flex-shrink: 0; }
        .progress-ring { transform: rotate(-90deg); }
        .progress-ring__circle { transition: stroke-dashoffset 0.5s ease-in-out; }
        .ring-text { position: absolute; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; color: var(--erp-white); }

        .stats-row { display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 2px; margin-top: 15px; border: 1px solid rgba(255,255,255,0.05); }
        .stat-block { display: flex; flex-direction: column; }
        .stat-label { font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-bottom: 2px; }
        .stat-value { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); font-weight: bold; }

        /* Bloomberg Table */
        .bloomberg-table { width: 100%; border-collapse: collapse; text-align: left; }
        .bloomberg-table th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-teal); text-transform: uppercase; background: rgba(0,229,255,0.05); position: sticky; top: 0; z-index: 10; }
        .bloomberg-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; font-size: 0.85rem; color: #E2E8F0; }
        .bloomberg-table tr:hover td { background: rgba(0, 229, 255, 0.03); }

        /* Off-Canvas Drawer */
        .drawer-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(3px); z-index: 999; display: none; }
        .drawer-panel { position: fixed; top: 0; right: -500px; width: 100%; max-width: 500px; height: 100%; background: #0A1128; border-left: 1px solid var(--erp-teal); box-shadow: -10px 0 30px rgba(0,0,0,0.8); z-index: 1000; transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1); overflow-y: auto; display: flex; flex-direction: column; }
        
        [x-cloak] { display: none !important; }
    </style>

    <!-- HEADER & TABS -->
    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px;">
            <div>
                <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">DEPLOYMENT ARCHITECTURE</h1>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ OPERATIONS MATRIX & MASTER LEDGER ]</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                @if($activeTab === 'matrix')
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); display: flex; align-items: center; gap: 5px; margin-right: 15px;">
                        <span style="display: inline-block; width: 6px; height: 6px; background: #00FF66; border-radius: 50%; box-shadow: 0 0 5px #00FF66; animation: pulse-red 2s infinite;"></span>
                        LIVE RADAR (POLLING 30s)
                    </div>
                @endif
                <button wire:click="switchTab('matrix')" class="nav-pill {{ $activeTab === 'matrix' ? 'active' : '' }}">LIVE MATRIX</button>
                <button wire:click="switchTab('ledger')" class="nav-pill {{ $activeTab === 'ledger' ? 'active' : '' }}">MASTER LEDGER</button>
            </div>
        </div>

        <!-- GLOBAL ACTIONS & FILTERS -->
        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(10, 17, 40, 0.6); padding: 15px; border: 1px solid var(--erp-border-dark); border-radius: 4px;">
            <div style="display: flex; gap: 15px; flex: 1;">
                <input type="text" wire:model.live.debounce.300ms="searchQuery" class="cyber-input" style="max-width: 250px;" placeholder="Search projects or clients...">
                
                <select wire:model.live="filterStatus" class="cyber-select" style="max-width: 200px;">
                    <option value="">-- ANY STATUS --</option>
                    @if($activeTab === 'ledger')
                        <option value="Scoping">Scoping</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    @endif
                    <option value="Active">Active</option>
                    <option value="Financially Locked">Guillotine Locked</option>
                </select>

                @if($canSeeMoney && $activeTab === 'matrix')
                    <select wire:model.live="filterHealth" class="cyber-select" style="max-width: 200px;">
                        <option value="">-- ALL FINANCIAL HEALTH --</option>
                        <option value="Healthy">Healthy Margins</option>
                        <option value="Bleeding">Bleeding (Margin Alert)</option>
                    </select>
                @endif

                @if($canSeeAll && $activeTab === 'matrix')
                    <select wire:model.live="filterAssignee" class="cyber-select" style="max-width: 200px;">
                        <option value="">-- ALL PERSONNEL --</option>
                        @foreach($availableAssignees as $user)
                            <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div style="display: flex; gap: 10px;">
                @if($canExport)
                    <button wire:click="exportCsv" class="btn-action">
                        <i data-lucide="download" style="width: 14px; height: 14px;"></i> EXPORT CSV
                    </button>
                @endif

                @if($canCreate)
                    <button @click="openCreateDrawer = true" class="btn-action" style="background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold;">
                        <i data-lucide="plus-square" style="width: 14px; height: 14px;"></i> INITIALIZE DEPLOYMENT
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if (session()->has('success'))
        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('error') }}</div>
    @endif

    <!-- TAB 1: LIVE MATRIX -->
    @if($activeTab === 'matrix')
        <div wire:poll.30s="loadData">
            <!-- KPIs -->
            <div class="metrics-grid" style="grid-template-columns: repeat({{ $canSeeMoney ? '4' : '2' }}, 1fr);">
                <div class="metric-card">
                    <div class="metric-title">ACTIVE FIRM DEPLOYMENTS</div>
                    <div class="metric-val" style="color: var(--erp-teal);">{{ $kpiActiveDeployments }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">GLOBAL SPRINT VELOCITY (7D)</div>
                    <div class="metric-val">{{ $kpiSprintVelocity }} <span style="font-size: 0.8rem; color: var(--erp-muted);">PTS</span></div>
                </div>
                
                @if($canSeeMoney)
                    <div class="metric-card" style="{{ $kpiCapitalAtRisk > 0 ? 'border-left-color: #FFBD2E;' : '' }}">
                        <div class="metric-title">PENDING CAPITAL AT RISK</div>
                        <div class="metric-val" style="color: {{ $kpiCapitalAtRisk > 0 ? '#FFBD2E' : '#00FF66' }};">${{ number_format($kpiCapitalAtRisk, 2) }}</div>
                    </div>
                    <div class="metric-card" style="{{ $kpiBleedingProjects > 0 ? 'border-left-color: var(--erp-red);' : '' }}">
                        <div class="metric-title">BLEEDING PROJECTS (MARGIN ALERT)</div>
                        <div class="metric-val" style="color: {{ $kpiBleedingProjects > 0 ? 'var(--erp-red)' : '#00FF66' }};">{{ $kpiBleedingProjects }}</div>
                    </div>
                @endif
            </div>

            <!-- Matrix Grid -->
            <div class="project-grid">
                @forelse($matrixProjects as $p)
                    @php
                        $cardClass = 'card-active';
                        if ($p['is_financially_locked']) $cardClass = 'card-locked';
                        elseif ($p['needs_midpoint_funds']) $cardClass = 'card-warning';

                        $circumference = 150.79;
                        $offset = $circumference - ($p['progress_percent'] / 100) * $circumference;
                        $ringColor = $p['progress_percent'] == 100 ? '#00FF66' : 'var(--erp-teal)';
                    @endphp

                    <div class="project-card {{ $cardClass }}">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                            <div style="flex: 1; padding-right: 15px;">
                                <div class="client-name">> {{ $p['client_name'] }}</div>
                                <div class="project-title">{{ $p['title'] }}</div>
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    @if($p['is_financially_locked'])
                                        <span class="status-pill pill-locked"><i data-lucide="lock" style="width: 10px; height: 10px;"></i> GUILLOTINE LOCKED</span>
                                    @else
                                        <span class="status-pill pill-active"><i data-lucide="activity" style="width: 10px; height: 10px;"></i> ACTIVE</span>
                                    @endif

                                    @if($p['needs_midpoint_funds'] && !$p['is_financially_locked'])
                                        <span class="status-pill pill-warning"><i data-lucide="alert-triangle" style="width: 10px; height: 10px;"></i> AWAITING FUNDS</span>
                                    @endif
                                </div>
                            </div>

                            <div class="ring-container">
                                <svg class="progress-ring" width="60" height="60">
                                    <circle stroke="rgba(255,255,255,0.05)" stroke-width="4" fill="transparent" r="24" cx="30" cy="30"/>
                                    <circle class="progress-ring__circle" stroke="{{ $ringColor }}" stroke-width="4" stroke-linecap="round" fill="transparent" r="24" cx="30" cy="30" style="stroke-dasharray: {{ $circumference }}; stroke-dashoffset: {{ $offset }};"/>
                                </svg>
                                <div class="ring-text">{{ $p['progress_percent'] }}%</div>
                            </div>
                        </div>

                        <div class="stats-row">
                            <div class="stat-block">
                                <span class="stat-label">STORY POINTS</span>
                                <span class="stat-value" style="color: var(--erp-teal);">{{ $p['completed_points'] }} / {{ $p['total_points'] }}</span>
                            </div>
                            @if($canSeeMoney)
                                <div class="stat-block" style="text-align: right;">
                                    <span class="stat-label">BUDGET BURN</span>
                                    <span class="stat-value" style="color: {{ $p['is_bleeding'] ? 'var(--erp-red)' : ($p['burn_percent'] > $p['progress_percent'] ? '#FFBD2E' : '#00FF66') }};">
                                        {{ $p['burn_percent'] }}% (${{ number_format($p['spent_amount'], 0) }})
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div style="margin-top: auto; padding-top: 15px;">
                            @if($canCreate)
                                <a href="{{ route('hq.ops.scrum', $p['id']) }}" class="btn-action" style="width: 100%; text-decoration: none; margin-bottom: 10px;">
                                    <i data-lucide="terminal" style="width: 14px; height: 14px;"></i> SCRUM MASTER
                                </a>
                            @endif

                            <a href="{{ route('hq.ops.board', $p['id']) }}" class="btn-action" style="width: 100%; text-decoration: none; margin-bottom: {{ $canLock ? '10px' : '0' }};">
                                <i data-lucide="layout-dashboard" style="width: 14px; height: 14px;"></i> SPRINT BOARD
                            </a>

                            @if($canLock)
                                @if($p['is_financially_locked'])
                                    <button wire:click="toggleFinancialLock('{{ $p['id'] }}')" class="btn-action" style="width: 100%; color: #00FF66; border-color: #00FF66;">
                                        <i data-lucide="unlock" style="width: 14px; height: 14px;"></i> L5 OVERRIDE: UNLOCK
                                    </button>
                                @else
                                    <button wire:click="toggleFinancialLock('{{ $p['id'] }}')" class="btn-action btn-danger" style="width: 100%;">
                                        <i data-lucide="lock" style="width: 14px; height: 14px;"></i> L5: ENFORCE LOCK
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="grid-column: 1 / -1; background: rgba(0,0,0,0.4); border: 1px dashed var(--erp-border-dark); padding: 60px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">
                        NO ACTIVE DEPLOYMENTS DETECTED MATCHING QUERY.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- TAB 2: MASTER LEDGER -->
    @if($activeTab === 'ledger')
        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px; overflow-x: auto;">
            <table class="bloomberg-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Client Enterprise</th>
                        <th>Project Title</th>
                        <th>Guillotine</th>
                        <th>Total Budget</th>
                        <th>Timeline</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgerProjects as $p)
                        <tr>
                            <td>
                                @if($p['status'] === 'Active') <span class="status-pill pill-active">ACTIVE</span>
                                @elseif($p['status'] === 'Scoping') <span class="status-pill pill-scoping">SCOPING</span>
                                @elseif($p['status'] === 'Completed') <span class="status-pill pill-completed">COMPLETED</span>
                                @elseif($p['status'] === 'Locked') <span class="status-pill pill-locked">LOCKED</span>
                                @else <span class="status-pill" style="border-color: var(--erp-muted);">{{ strtoupper($p['status']) }}</span>
                                @endif
                            </td>
                            <td style="font-family: 'Roboto Mono', monospace; color: var(--erp-teal);">{{ $p['client_name'] }}</td>
                            <td style="font-weight: bold;">{{ $p['title'] }}</td>
                            <td>
                                @if($p['is_financially_locked']) <span style="color: var(--erp-red); font-family: monospace; font-size: 0.75rem;">ENGAGED</span>
                                @else <span style="color: var(--erp-muted); font-family: monospace; font-size: 0.75rem;">DISENGAGED</span>
                                @endif
                            </td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                @if($canSeeMoney)
                                    {{ number_format($p['total_budget'], 2) }} <span style="color: var(--erp-muted);">{{ $p['currency'] }}</span>
                                @else
                                    <span style="color: var(--erp-muted);">[ CLASSIFIED ]</span>
                                @endif
                            </td>
                            <td style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #B0B8C4;">
                                {{ $p['start_date'] ?? 'TBD' }} <br> <span style="color: var(--erp-muted);">to</span> {{ $p['target_end_date'] ?? 'TBD' }}
                            </td>
                            <td style="display: flex; flex-direction: column; gap: 5px;">
                                @if($canCreate)
                                    <a href="{{ route('hq.ops.scrum', $p['id']) }}" class="btn-action" style="padding: 4px 8px; font-size: 0.65rem;">
                                        SCRUM MASTER
                                    </a>
                                @endif
                                <a href="{{ route('hq.ops.boards.directory', ['project' => $p['id']]) }}" class="btn-action" style="padding: 4px 8px; font-size: 0.65rem;">
                                    SPRINT BOARD
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">NO PROJECTS DETECTED IN THE MASTER LEDGER.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- OFF-CANVAS DRAWER: PROJECT CREATION -->
    <div x-cloak x-show="openCreateDrawer" class="drawer-overlay" style="display: none;" @click="openCreateDrawer = false" x-transition.opacity></div>
    
    <div class="drawer-panel" :style="openCreateDrawer ? 'right: 0;' : 'right: -500px;'">
        <div style="padding: 25px; border-bottom: 1px solid var(--erp-border-dark); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.3);">
            <div>
                <h3 style="font-family: 'Montserrat', sans-serif; font-size: 1.1rem; color: var(--erp-teal); margin: 0;">INITIALIZE DEPLOYMENT</h3>
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-top: 5px;">ESTABLISH NEW PROJECT PARAMETERS</div>
            </div>
            <button @click="openCreateDrawer = false" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer;"><i data-lucide="x"></i></button>
        </div>

        <div style="padding: 25px; flex: 1; overflow-y: auto;">
            <div style="margin-bottom: 20px;">
                <label class="form-label">Client Enterprise</label>
                <select wire:model="newOrgId" class="cyber-select">
                    <option value="">-- Select Organization --</option>
                    @foreach($availableOrganizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
                @error('newOrgId') <span style="color: var(--erp-red); font-size: 0.7rem; font-family: monospace;">{{ $message }}</span> @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label class="form-label">Project Title</label>
                <input type="text" wire:model="newTitle" class="cyber-input" placeholder="e.g., Q3 Cloud Migration Matrix">
                @error('newTitle') <span style="color: var(--erp-red); font-size: 0.7rem; font-family: monospace;">{{ $message }}</span> @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label class="form-label">Initial Status</label>
                <select wire:model="newStatus" class="cyber-select">
                    <option value="Scoping">Scoping (Pre-Contract)</option>
                    <option value="Active">Active (Live Operations)</option>
                </select>
                @error('newStatus') <span style="color: var(--erp-red); font-size: 0.7rem; font-family: monospace;">{{ $message }}</span> @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label class="form-label">Total Allocated Budget (USD)</label>
                <input type="number" wire:model="newBudget" class="cyber-input" placeholder="0.00" step="1000">
                @error('newBudget') <span style="color: var(--erp-red); font-size: 0.7rem; font-family: monospace;">{{ $message }}</span> @enderror
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label class="form-label">Start Date</label>
                    <input type="date" wire:model="newStartDate" class="cyber-input">
                    @error('newStartDate') <span style="color: var(--erp-red); font-size: 0.7rem; font-family: monospace;">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="form-label">Target End Date</label>
                    <input type="date" wire:model="newEndDate" class="cyber-input">
                    @error('newEndDate') <span style="color: var(--erp-red); font-size: 0.7rem; font-family: monospace;">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="margin-bottom: 25px;">
                <label class="form-label">Scope Description</label>
                <textarea wire:model="newDescription" class="cyber-textarea" style="min-height: 100px;" placeholder="Brief overview of deployment objectives..."></textarea>
            </div>

            <button wire:click="createProject" class="btn-primary" @click="setTimeout(() => { if(!$wire.errors.length) openCreateDrawer = false; }, 500)">
                <i data-lucide="hard-drive" style="width: 16px; height: 16px;"></i> ESTABLISH DEPLOYMENT
            </button>
        </div>
    </div>

    <script> 
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); }); 
        document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
    </script>
</div>