<div style="display: flex; flex-direction: column; min-height: 100vh; padding-bottom: 50px;">
    <style>
        /* SCROLL FIX: Removed overflow constraints. Board now expands naturally */
        .kanban-board { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 20px; flex-grow: 1; align-items: stretch; }
        .kanban-col { background: rgba(10, 17, 40, 0.4); border: 1px dashed var(--erp-border-dark); border-radius: 4px; min-width: 320px; max-width: 320px; display: flex; flex-direction: column; }
        .kanban-header { padding: 15px; border-bottom: 1px solid var(--erp-border-dark); font-family: 'Montserrat', sans-serif; font-weight: bold; font-size: 0.9rem; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
        .kanban-body { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; gap: 15px; min-height: 300px; }
        
        /* Column Specific Colors */
        .col-To-Do .kanban-header { color: #B0B8C4; border-top: 3px solid #B0B8C4; background: rgba(176,184,196,0.05); }
        .col-In-Progress .kanban-header { color: var(--erp-teal); border-top: 3px solid var(--erp-teal); background: rgba(0,229,255,0.05); }
        .col-QA_Review .kanban-header { color: #FFBD2E; border-top: 3px solid #FFBD2E; background: rgba(255,189,46,0.05); }
        .col-Failed .kanban-header { color: var(--erp-red); border-top: 3px solid var(--erp-red); background: rgba(255,51,102,0.05); }
        .col-Done .kanban-header { color: #00FF66; border-top: 3px solid #00FF66; background: rgba(0,255,102,0.05); }

        .task-card { background: rgba(0,0,0,0.6); border: 1px solid var(--erp-border-dark); border-radius: 2px; padding: 15px; cursor: grab; transition: 0.2s; position: relative; }
        .task-card:active { cursor: grabbing; transform: scale(0.98); opacity: 0.8; }
        .task-card:hover { border-color: var(--erp-teal); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        
        .task-phase { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-gold); text-transform: uppercase; margin-bottom: 5px; }
        .task-title { font-family: 'Inter', sans-serif; font-size: 0.85rem; color: var(--erp-white); font-weight: bold; margin-bottom: 15px; line-height: 1.4; }
        
        .task-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed rgba(255,255,255,0.05); padding-top: 10px; }
        .fibonacci-badge { background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; border-radius: 50%; }
        
        .assignee-badge { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #B0B8C4; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 2px; }
        .btn-checkout { background: transparent; border: 1px dashed var(--erp-teal); color: var(--erp-teal); padding: 6px 10px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; cursor: pointer; transition: 0.3s; width: 100%; text-align: center; display: block; }
        .btn-checkout:hover { background: var(--erp-teal); color: var(--erp-bg-main); }

        /* Capacity Radar */
        .capacity-bar { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); padding: 15px; margin-bottom: 20px; display: flex; gap: 20px; overflow-x: auto; align-items: center; }
        .capacity-pill { display: flex; align-items: center; gap: 10px; padding: 5px 15px; border-radius: 2px; border: 1px solid var(--erp-border-dark); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; background: rgba(0,0,0,0.3); }
        .capacity-safe { border-color: #00FF66; color: #00FF66; }
        .capacity-warn { border-color: #FFBD2E; color: #FFBD2E; }
        .capacity-danger { border-color: var(--erp-red); color: var(--erp-red); background: rgba(255,51,102,0.1); animation: pulse-red 2s infinite; }
        
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0.4); } 70% { box-shadow: 0 0 0 4px rgba(255, 51, 102, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 51, 102, 0); } }

        .cyber-select { background: rgba(0,0,0,0.3); border: 1px solid var(--erp-border-dark); color: var(--erp-teal); padding: 8px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; outline: none; transition: 0.3s; font-weight: bold; }
        
        .qa-alert-badge { position: absolute; top: -8px; right: -8px; background: rgba(255, 51, 102, 0.95); border: 1px solid var(--erp-red); color: #fff; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 10px rgba(255,51,102,0.8); animation: pulse-red 2s infinite; z-index: 10; cursor: help; }

        /* Presence Modal Overlay */
        .presence-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 9999; display: flex; align-items: center; justify-content: center; }
        .presence-box { background: rgba(10, 17, 40, 1); border: 2px solid var(--erp-teal); padding: 40px; text-align: center; max-width: 500px; box-shadow: 0 0 30px rgba(0,229,255,0.2); }
        
        /* QA Vulnerability Tooltip */
        .qa-alert-wrap { position: relative; display: inline-block; margin-top: 10px; width: 100%; }
        .qa-alert-btn { background: rgba(255, 51, 102, 0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 6px 10px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; border-radius: 2px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; transition: 0.3s; font-weight: bold; }
        .qa-alert-btn:hover { background: var(--erp-red); color: #fff; }
        
        .qa-tooltip { visibility: hidden; opacity: 0; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(10px); background: rgba(10, 17, 40, 0.95); backdrop-filter: blur(5px); border: 1px solid var(--erp-red); padding: 12px; width: 220px; border-radius: 4px; z-index: 100; transition: all 0.3s cubic-bezier(0.25, 1, 0.3, 1); pointer-events: none; box-shadow: 0 10px 25px rgba(255,51,102,0.3); text-align: left; }
        .qa-alert-wrap:hover .qa-tooltip { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(-5px); }
        
        .qa-tooltip-title { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); margin-bottom: 5px; border-bottom: 1px dashed rgba(255,51,102,0.3); padding-bottom: 5px; }
        .qa-tooltip-desc { font-family: 'Inter', sans-serif; font-size: 0.75rem; color: #E2E8F0; line-height: 1.4; }
    </style>

    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.3rem; color: var(--erp-white); letter-spacing: 1px;">OPERATIONS: SPRINT BOARD</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-top: 5px;">[ PROJECT: {{ strtoupper($project->title) }} ]</p>
        </div>
        
        <div style="display: flex; gap: 15px; align-items: center;">
            <select wire:model.live="activeSprintId" class="cyber-select" style="min-width: 250px;">
                @foreach($sprints as $sprint)
                    <option value="{{ $sprint->id }}">{{ $sprint->title }} ({{ \Carbon\Carbon::parse($sprint->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($sprint->end_date)->format('M d') }})</option>
                @endforeach
            </select>
            
            <a href="{{ route('hq.ops.deployments') }}" class="btn-checkout" style="padding: 9px 15px; width: auto; border-color: var(--erp-border-dark); color: var(--erp-white);" wire:navigate>
                <i data-lucide="arrow-left" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle;"></i> MATRIX
            </a>
        </div>
    </div>

    @if (session()->has('error'))
        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 15px;">> {{ session('error') }}</div>
    @endif

    @if(!empty($teamCapacity))
        <div class="capacity-bar">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted);">> TEAM CAPACITY RADAR:</div>
            @foreach($teamCapacity as $userId => $data)
                @php
                    $capClass = 'capacity-safe';
                    if ($data['hours_assigned'] > 45) $capClass = 'capacity-danger';
                    elseif ($data['hours_assigned'] >= 35) $capClass = 'capacity-warn';
                @endphp
                <div class="capacity-pill {{ $capClass }}">
                    <i data-lucide="user" style="width: 12px; height: 12px;"></i>
                    <span>{{ $data['name'] }}</span>
                    <strong>{{ $data['hours_assigned'] }} HRS</strong>
                </div>
            @endforeach
        </div>
    @endif

    @php
        // Detect if the logged-in user has an active task to trigger the Alpine monitor
        $hasActiveTasks = collect($tasks['In-Progress'])->where('assigned_to', auth()->id())->count() > 0;
    @endphp

    <div class="kanban-board" x-data="sprintBoard({{ $hasActiveTasks ? 'true' : 'false' }})">
        
        <!-- PRESENCE MODAL -->
        <div class="presence-overlay" x-show="showPresenceModal" style="display: none;" x-transition>
            <div class="presence-box">
                <i data-lucide="alert-triangle" style="width: 48px; height: 48px; color: var(--erp-teal); margin-bottom: 20px;"></i>
                <h2 style="font-family: 'Montserrat', sans-serif; color: var(--erp-white); font-size: 1.5rem; margin-bottom: 10px;">STILL EXECUTING?</h2>
                <p style="font-family: 'Roboto Mono', monospace; color: var(--erp-muted); font-size: 0.85rem; margin-bottom: 30px; line-height: 1.5;">
                    45 minutes of continuous execution logged.<br>Please verify presence within 5 minutes or the Silent Timer will auto-pause.
                </p>
                <button @click="confirmPresence()" style="background: var(--erp-teal); color: var(--erp-bg-main); font-weight: bold; border: none; padding: 15px 40px; font-family: 'Roboto Mono', monospace; cursor: pointer; transition: 0.3s;" onmouseover="this.style.boxShadow='0 0 20px rgba(0,229,255,0.6)'" onmouseout="this.style.boxShadow='none'">
                    > I AM EXECUTING
                </button>
            </div>
        </div>

        @foreach(['To-Do', 'In-Progress', 'QA_Review', 'Failed', 'Done'] as $column)
            <div class="kanban-col col-{{ $column }}" 
                 @dragover.prevent="dragover = true" 
                 @dragleave.prevent="dragover = false" 
                 @drop.prevent="dropTask($event, '{{ $column }}')"
                 x-bind:style="dragover && dragStatus === '{{ $column }}' ? 'background: rgba(0,229,255,0.05); border-color: var(--erp-teal);' : ''"
                 @dragenter="dragStatus = '{{ $column }}'">
                
                <div class="kanban-header">
                    {{ str_replace('_', ' ', $column) }}
                    <span style="background: rgba(0,0,0,0.5); padding: 2px 8px; border-radius: 10px; font-size: 0.65rem;">{{ count($tasks[$column]) }}</span>
                </div>

                <div class="kanban-body">
                    @foreach($tasks[$column] as $task)
                        @php
                            $isDraggable = ($task['assigned_to'] === auth()->id() || $userClearance >= 4) ? 'true' : 'false';
                        @endphp

                        <div class="task-card" 
                             draggable="{{ $isDraggable }}" 
                             @dragstart="dragStart($event, '{{ $task['id'] }}', '{{ $column }}')"
                             style="{{ $isDraggable === 'false' && $task['assigned_to'] ? 'opacity: 0.6; cursor: not-allowed;' : '' }}">
                            
                            <div class="task-phase">> {{ $task['phase_title'] }}</div>
                            <div class="task-title">{{ $task['title'] }}</div>

                            @if($column === 'QA_Review' && $task['qa_sla_expires_at'])
                                @php
                                    $expires = \Carbon\Carbon::parse($task['qa_sla_expires_at']);
                                    $isExpired = $expires->isPast();
                                @endphp
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; padding: 5px; margin-bottom: 10px; border: 1px solid {{ $isExpired ? 'var(--erp-red)' : '#FFBD2E' }}; color: {{ $isExpired ? 'var(--erp-red)' : '#FFBD2E' }}; background: rgba(0,0,0,0.5);">
                                    <i data-lucide="clock" style="width: 10px; height: 10px;"></i> 
                                    SLA: {{ $isExpired ? 'EXPIRED' : $expires->diffForHumans() }}
                                </div>
                            @endif

                            @if(!$task['assigned_to'])
                                <button wire:click="assignToMe('{{ $task['id'] }}')" class="btn-checkout">
                                    <i data-lucide="crosshair" style="width: 12px; height: 12px; display: inline-block; vertical-align: middle;"></i> ASSIGN TO ME
                                </button>
                            @else
                                <div class="task-footer">
                                    <div class="fibonacci-badge" title="{{ $task['story_points'] }} Story Points">{{ $task['story_points'] }}</div>
                                    <div class="assignee-badge">
                                        <i data-lucide="user" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"></i> 
                                        {{ substr($task['first_name'], 0, 1) }}. {{ $task['last_name'] }}
                                    </div>
                                </div>
                            @endif
                            
                            @if($task['has_active_qa'] ?? false)
                                <div class="qa-alert-wrap">
                                    <a href="/hq/operations/qa-hub?ticket={{ $task['qa_ticket_id'] }}" class="qa-alert-btn" wire:navigate>
                                        <i data-lucide="shield-alert" style="width: 14px; height: 14px;"></i> VULNERABILITY REPORTED
                                    </a>
                                    
                                    <div class="qa-tooltip">
                                        <div class="qa-tooltip-title">> {{ $task['qa_ticket_title'] ?? 'CLIENT VULNERABILITY LOG' }}</div>
                                        <div class="qa-tooltip-desc">
                                            "{{ $task['qa_ticket_description'] ?? 'Awaiting payload description...' }}"
                                        </div>
                                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); margin-top: 8px; text-align: right;">
                                            [ CLICK TO ENTER QA HUB ]
                                        </div>
                                    </div>
                                </div>
                            @endif    
                        </div>
                    @endforeach
                    
                    @if(count($tasks[$column]) === 0)
                        <div style="text-align: center; padding: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: rgba(255,255,255,0.1); border: 1px dashed rgba(255,255,255,0.05);">
                            DROP ZONE
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <script>
        function sprintBoard(hasActiveTasks) {
            return {
                dragover: false,
                dragStatus: null,
                draggedTaskId: null,
                sourceColumn: null,

                // Presence Monitor State
                showPresenceModal: false,
                presenceTimeout: null,
                warningTimeout: null,

                init() {
                    if (hasActiveTasks) {
                        this.startPresenceMonitor();
                    }
                },

                startPresenceMonitor() {
                    clearTimeout(this.presenceTimeout);
                    clearTimeout(this.warningTimeout);
                    this.showPresenceModal = false;
                    
                    // 45 minutes = 2700000 ms
                    this.presenceTimeout = setTimeout(() => {
                        this.showPresenceModal = true;
                        
                        // 5 minutes warning window = 300000 ms
                        this.warningTimeout = setTimeout(() => {
                            this.showPresenceModal = false;
                            @this.call('forcePauseTimers'); // Triggers Livewire Killswitch
                        }, 300000);
                        
                    }, 2700000);
                },

                confirmPresence() {
                    this.startPresenceMonitor(); // Resets the clocks
                },

                dragStart(event, taskId, sourceColumn) {
                    this.draggedTaskId = taskId;
                    this.sourceColumn = sourceColumn;
                    event.dataTransfer.effectAllowed = 'move';
                    setTimeout(() => { event.target.style.opacity = '0.5'; }, 0);
                },

                dropTask(event, targetColumn) {
                    this.dragover = false;
                    this.dragStatus = null;

                    let draggedElement = document.querySelector(`[draggable="true"][style*="0.5"]`);
                    if(draggedElement) draggedElement.style.opacity = '1';

                    if (this.draggedTaskId && this.sourceColumn !== targetColumn) {
                        @this.call('updateTaskStatus', this.draggedTaskId, targetColumn);
                    }
                }
            }
        }

        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
    </script>
</div>