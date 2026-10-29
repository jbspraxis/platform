<div>
    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <a href="{{ route('hq.dash.admin') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ GLOBAL_TELEMETRY ]</a>
            <a href="{{ route('hq.dash.workspace') }}" wire:navigate class="ws-tab active" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); padding: 5px 15px; background: rgba(0, 229, 255, 0.05); border: 1px solid var(--erp-teal); border-radius: 4px; text-decoration: none;">[ MY_WORKSPACE ]</a>
            <a href="{{ route('hq.dash.investor') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ INVESTOR_TERMINAL ]</a>
            <a href="{{ route('hq.dash.audit') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ AUDIT_TERMINAL ]</a>
        </div>
        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white);">
            IDENTITY: <span style="color: var(--erp-teal);">{{ Auth::user()->first_name ?? 'OPERATOR' }}</span>
        </div>
    </div>

    @if(session()->has('error'))
        <div style="background: rgba(255, 51, 102, 0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
            ⚠ {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px;">
        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); padding: 15px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">> MY ACTIVE TASKS</div>
            <div style="font-size: 1.5rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white);">{{ str_pad($stats['tasks_open'], 2, '0', STR_PAD_LEFT) }}</div>
        </div>
        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); padding: 15px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">> HOURS LOGGED (WEEK)</div>
            <div style="font-size: 1.5rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-teal);">{{ $stats['hours_this_week'] }}H</div>
        </div>
        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-bottom: 2px solid #FFBD2E; padding: 15px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px;">> PENDING SIGN-OFFS</div>
            <div style="font-size: 1.5rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: #FFBD2E;">{{ str_pad($stats['approvals_count'], 2, '0', STR_PAD_LEFT) }}</div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 5px; justify-content: center;">
            <button style="background: var(--erp-teal); color: #0A1128; border: none; padding: 8px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; cursor: pointer; border-radius: 2px;">+ LOG CLIENT INTEL</button>
            <button style="background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-white); padding: 8px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; cursor: pointer; border-radius: 2px;">+ DRAFT SOW</button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 300px 300px; gap: 20px; align-items: start;">
        
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
                    > MY ASSIGNED SPRINT BACKLOG
                </div>

                @if(count($activeTasks) > 0)
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        @foreach($activeTasks as $task)
                            <div style="border: 1px solid var(--erp-border-dark); background: rgba(255,255,255,0.02); padding: 15px; border-left: 3px solid {{ $task->status === 'In-Progress' ? '#00FF66' : 'var(--erp-teal)' }}; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 0.7rem; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; margin-bottom: 5px;">{{ $task->project_title }} | {{ $task->phase_title }}</div>
                                    <div style="font-weight: 600; font-size: 0.9rem; color: var(--erp-white);">{{ $task->title }}</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; padding: 4px 8px; border: 1px solid var(--erp-border-dark); color: {{ $task->status === 'In-Progress' ? '#00FF66' : 'var(--erp-muted)' }};">{{ strtoupper($task->status) }}</span>
                                    
                                    @if($activeTimer && $activeTimer->task_id === $task->id)
                                        <button wire:click="stopTimer" style="background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; cursor: pointer;">HALT</button>
                                    @else
                                        <button wire:click="startTimer('{{ $task->id }}')" wire:loading.attr="disabled" style="background: transparent; border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-teal)'; this.style.color='#0A1128';" onmouseout="this.style.background='transparent'; this.style.color='var(--erp-teal)';">INITIATE</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="text-align: center; padding: 30px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border: 1px dashed var(--erp-border-dark);">
                        > NO ACTIVE TASKS IN QUEUE.
                    </div>
                @endif
            </div>

            @if(Auth::user()->role && Auth::user()->role->clearance_level >= 4)
            <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #FFBD2E; margin-bottom: 20px; border-bottom: 1px dashed var(--erp-border-dark); padding-bottom: 10px;">
                    > PENDING AUTHORIZATIONS
                </div>
                
                @if(count($pendingApprovals) > 0)
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        @foreach($pendingApprovals as $approval)
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); padding: 12px; border-left: 2px solid #FFBD2E;">
                                <div style="font-size: 0.7rem; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; margin-bottom: 3px;">{{ $approval->type }} | {{ $approval->client_name }}</div>
                                <div style="font-size: 0.85rem; font-weight: bold; color: var(--erp-white);">{{ \Illuminate\Support\Str::limit($approval->title, 40) }}</div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                                    <span style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold;">{{ $approval->currency }} {{ number_format($approval->total_value) }}</span>
                                    <button style="background: transparent; border: 1px solid #FFBD2E; color: #FFBD2E; padding: 4px 8px; font-size: 0.65rem; font-weight: bold; font-family: 'Roboto Mono', monospace; cursor: pointer;">REVIEW</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">> ZERO OUTSTANDING AUTHORIZATIONS.</div>
                @endif
            </div>
            @endif
        </div>

        <div style="display: flex; flex-direction: column; gap: 20px;">
            
            <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid {{ $activeTimer ? '#00FF66' : 'var(--erp-muted)' }}; padding: 25px; border-radius: 4px; text-align: center;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 15px; text-transform: uppercase; text-align: left;">> CHRONOMETER STATUS</div>
                
                @if($activeTimer)
                    <div style="color: #00FF66; font-family: 'Roboto Mono', monospace; font-size: 1.5rem; font-weight: bold; text-shadow: 0 0 10px rgba(0,255,102,0.3); margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <span style="width: 8px; height: 8px; background: #00FF66; border-radius: 50%; box-shadow: 0 0 10px #00FF66; animation: blink 1.5s infinite;"></span>
                        ACTIVE
                    </div>
                    <div style="font-size: 0.75rem; color: var(--erp-white); margin-bottom: 20px; text-align: left;">Logging time for:<br><strong style="color: var(--erp-teal);">{{ \Illuminate\Support\Str::limit($activeTimer->task_title, 35) }}</strong></div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 15px; text-align: left;">Started: {{ \Carbon\Carbon::parse($activeTimer->start_time)->format('H:i T') }}</div>
                    
                    <button wire:click="stopTimer" style="width: 100%; background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-red)'; this.style.color='#FFF';" onmouseout="this.style.background='transparent'; this.style.color='var(--erp-red)';">HALT TIMER</button>
                @else
                    <div style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 1.2rem; font-weight: bold; margin-bottom: 10px;">IDLE</div>
                    <div style="font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 10px;">Initiate a task in the backlog to begin financial logging.</div>
                @endif
            </div>

            <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 20px;">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-white); margin-bottom: 15px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
                    > SCHEDULE LOG
                </div>
                @forelse($upcomingAppointments as $appt)
                    <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal); margin-bottom: 3px;">{{ \Carbon\Carbon::parse($appt->start_time)->format('M d - H:i') }}</div>
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--erp-white);">{{ $appt->title }}</div>
                        <div style="font-size: 0.7rem; color: var(--erp-muted);">{{ $appt->client_name ?? 'Internal' }}</div>
                    </div>
                @empty
                    <div style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">> NO UPCOMING APPOINTMENTS.</div>
                @endforelse
            </div>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 20px; height: fit-content;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-white); margin-bottom: 15px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
                > SYSTEM DISPATCHES
            </div>
            
            @forelse($recentNotifications as $note)
                <div style="margin-bottom: 15px; display: flex; gap: 10px;">
                    <div style="margin-top: 3px;">
                        @if($note->type === 'Financial')
                            <span style="color: #00FF66;">$</span>
                        @elseif($note->type === 'Project')
                            <span style="color: var(--erp-teal);">⚡</span>
                        @else
                            <span style="color: var(--erp-muted);">ℹ</span>
                        @endif
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; font-weight: bold; color: var(--erp-white); margin-bottom: 2px;">{{ $note->title }}</div>
                        <div style="font-size: 0.7rem; color: var(--erp-muted); line-height: 1.3;">{{ $note->message }}</div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #555; margin-top: 4px;">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <div style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">> ZERO NEW DISPATCHES.</div>
            @endforelse
        </div>

    </div>

    <style>
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }
    </style>
</div>