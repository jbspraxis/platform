<div>
    <!-- Workspace Tabs -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <a href="{{ route('hq.dash.admin') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ GLOBAL_TELEMETRY ]</a>
            
            <a href="{{ route('hq.dash.workspace') }}" wire:navigate class="ws-tab active" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); padding: 5px 15px; background: rgba(0, 229, 255, 0.05); border: 1px solid var(--erp-teal); border-radius: 4px; text-decoration: none;">[ MY_WORKSPACE ]</a>
            
            <a href="{{ route('hq.dash.investor') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ INVESTOR_TERMINAL ]</a>
            
            <a href="{{ route('hq.dash.audit') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ AUDIT_TERMINAL ]</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 350px; gap: 30px; align-items: start;">
        
        <!-- LEFT COLUMN: Tasks -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            
            <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
                    > MY ASSIGNED TASKS
                </div>

                @if(count($activeTasks) > 0)
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        @foreach($activeTasks as $task)
                            <div style="border: 1px solid var(--erp-border-dark); background: rgba(255,255,255,0.02); padding: 15px; border-left: 3px solid var(--erp-teal); border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; margin-bottom: 5px;">{{ $task->project_title }} | {{ $task->phase_title }}</div>
                                    <div style="font-weight: 600; font-size: 0.95rem; color: var(--erp-white);">{{ $task->title }}</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <span style="background: rgba(255,255,255,0.05); padding: 4px 8px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; border-radius: 2px;">{{ $task->story_points }} PTS</span>
                                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; padding: 4px 8px; border: 1px solid var(--erp-border-dark); color: {{ $task->status === 'Failed' ? 'var(--erp-red)' : 'var(--erp-muted)' }};">{{ strtoupper($task->status) }}</span>
                                    <button style="background: transparent; border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-teal)'; this.style.color='#0A1128';" onmouseout="this.style.background='transparent'; this.style.color='var(--erp-teal)';">INITIATE TASK</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="text-align: center; padding: 40px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border: 1px dashed var(--erp-border-dark);">
                        > NO ACTIVE TASKS IN QUEUE.
                    </div>
                @endif
            </div>

        </div>

        <!-- RIGHT COLUMN: Telemetry & Approvals -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            
            <!-- Active Timer Block -->
            <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid {{ $activeTimer ? '#00FF66' : 'var(--erp-muted)' }}; padding: 25px; border-radius: 4px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 15px; text-transform: uppercase;">> CHRONOMETER STATUS</div>
                
                @if($activeTimer)
                    <div style="color: #00FF66; font-family: 'Roboto Mono', monospace; font-size: 1.5rem; font-weight: bold; text-shadow: 0 0 10px rgba(0,255,102,0.3); margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <span style="width: 8px; height: 8px; background: #00FF66; border-radius: 50%; box-shadow: 0 0 10px #00FF66; animation: blink 1.5s infinite;"></span>
                        ACTIVE
                    </div>
                    <div style="font-size: 0.8rem; color: var(--erp-white); margin-bottom: 15px;">Logging time for:<br><strong style="color: var(--erp-teal);">{{ $activeTimer->task_title }}</strong></div>
                    <button style="width: 100%; background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-red)'; this.style.color='#FFF';" onmouseout="this.style.background='transparent'; this.style.color='var(--erp-red)';">HALT TIMER</button>
                @else
                    <div style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 1.2rem; font-weight: bold; margin-bottom: 10px;">IDLE</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Initiate a task to begin financial logging.</div>
                @endif
            </div>

            <!-- Pending Approvals (command-level: dashboard.view) -->
            @can('dashboard.view')
            <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #FFBD2E; margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">
                    > PENDING AUTHORIZATIONS
                </div>
                
                @if(count($pendingApprovals) > 0)
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        @foreach($pendingApprovals as $approval)
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); padding: 10px; border-left: 2px solid #FFBD2E;">
                                <div style="font-size: 0.7rem; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; margin-bottom: 3px;">{{ $approval->type }} | {{ $approval->client_name }}</div>
                                <div style="font-size: 0.85rem; font-weight: bold; color: var(--erp-white);">{{ $approval->title }}</div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                                    <span style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">{{ $approval->currency }} {{ number_format($approval->total_value) }}</span>
                                    <button style="background: transparent; border: none; color: #FFBD2E; font-size: 0.7rem; font-weight: bold; cursor: pointer;">REVIEW ></button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">> ZERO OUTSTANDING AUTHORIZATIONS.</div>
                @endif
            </div>
            @endcan

        </div>
    </div>
</div>