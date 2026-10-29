<div id="intake-hub">
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - SCHEDULE / COMMAND CENTER CSS ARCHITECTURE 
           ========================================================================== */

        /* --- VIEWPORT-LOCKED IMMERSIVE BACKGROUND --- */
        .immersive-bg, .immersive-overlay, .scanline-grid {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none; z-index: -10;
        }

        .immersive-bg {
            background-image: url('{{ asset("assets/img/about.svg") }}');
            background-size: cover; background-position: center;
            filter: grayscale(85%) brightness(0.25); 
            transition: all 0.5s ease;
        }
        html.light-mode .immersive-bg { filter: grayscale(80%) brightness(0.9) opacity(0.5); }

        .immersive-overlay {
            background: radial-gradient(circle at center, rgba(10,17,40,0.5) 0%, var(--midnight-navy) 100%);
            transition: background 0.5s ease;
        }
        html.light-mode .immersive-overlay { background: radial-gradient(circle at center, rgba(255,255,255,0.6) 0%, var(--ghost-white) 100%); }

        .scanline-grid {
            background-image: linear-gradient(rgba(0, 229, 255, 0.02) 1px, transparent 1px), 
                              linear-gradient(90deg, rgba(0, 229, 255, 0.02) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        html.light-mode .scanline-grid {
            background-image: linear-gradient(rgba(10, 17, 40, 0.03) 1px, transparent 1px), 
                              linear-gradient(90deg, rgba(10, 17, 40, 0.03) 1px, transparent 1px);
        }

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .pulse-dot { width: 6px; height: 6px; background-color: var(--accent-color); border-radius: 50%; box-shadow: 0 0 10px var(--accent-color); animation: pulse 2s infinite; display: inline-block; }
        html.light-mode .pulse-dot { background-color: #00FF66; box-shadow: 0 0 10px #00FF66; }

        .terminal-action-btn { display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 16px 24px; background-color: var(--luminous-teal) !important; color: var(--midnight-navy) !important; font-weight: bold; font-family: 'Inter', sans-serif; font-size: 0.9rem; cursor: pointer; border: none; border-radius: 4px; transition: all 0.3s ease; text-decoration: none; text-transform: uppercase; }
        .terminal-action-btn:hover:not(:disabled) { background-color: var(--stark-white) !important; box-shadow: 0 0 20px rgba(255, 255, 255, 0.4); transform: translateY(-2px); }
        .terminal-action-btn:disabled { opacity: 0.5; cursor: not-allowed; background-color: rgba(255,255,255,0.1) !important; color: var(--text-secondary) !important; }

        /* --- LAYOUT WRAPPER --- */
        .schedule-wrapper {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 100px 24px; position: relative; z-index: 10; min-height: 100vh;
        }

        /* --- THE GLASS COMMAND CENTER --- */
        .glass-panel {
            width: 100%; max-width: 1100px; position: relative;
            background: linear-gradient(145deg, rgba(10, 17, 40, 0.8) 0%, rgba(3, 5, 10, 0.95) 100%);
            backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(0, 229, 255, 0.2); border-top: 3px solid var(--accent-color);
            border-radius: 8px; box-shadow: 0 40px 100px rgba(0,0,0,0.8), inset 0 0 30px rgba(0, 229, 255, 0.02);
            overflow: hidden; display: flex; flex-direction: column;
            animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        html.light-mode .glass-panel {
            background: rgba(255, 255, 255, 0.95); border-color: rgba(10, 17, 40, 0.1);
            border-top-color: var(--midnight-navy); box-shadow: 0 40px 100px rgba(10,17,40,0.1);
        }

        /* --- PANEL CONTROLS (CLOSE & HOME) --- */
        .panel-controls { position: absolute; top: 30px; right: 30px; display: flex; gap: 12px; z-index: 50; }
        .control-btn {
            width: 40px; height: 40px; border-radius: 4px; display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); color: var(--text-secondary);
            cursor: pointer; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .control-btn svg { width: 18px; height: 18px; stroke-width: 1.5; }
        .control-btn:hover { background: rgba(0, 229, 255, 0.1); color: var(--accent-color); transform: translateY(-2px); border-color: var(--accent-color); }
        
        html.light-mode .control-btn { background: rgba(0,0,0,0.02); border-color: rgba(0,0,0,0.05); color: var(--text-secondary); }
        html.light-mode .control-btn:hover { background: rgba(10, 17, 40, 0.05); color: var(--midnight-navy); border-color: var(--midnight-navy); }

        /* --- THE BRIEFING HEADER --- */
        .briefing-header {
            padding: 40px 50px; border-bottom: 1px solid rgba(0, 229, 255, 0.1);
            background: rgba(0, 229, 255, 0.02); display: flex; justify-content: space-between; align-items: flex-start;
        }
        html.light-mode .briefing-header { border-bottom-color: rgba(10,17,40,0.05); background: rgba(10,17,40,0.01); }

        .briefing-title { font-size: clamp(1.8rem, 3vw, 2.2rem); color: var(--stark-white); margin-bottom: 10px; line-height: 1.1; letter-spacing: -1px; font-weight: 900; }
        html.light-mode .briefing-title { color: var(--midnight-navy); }
        .briefing-desc { font-size: 1rem; color: var(--text-secondary); max-width: 500px; line-height: 1.6; margin: 0; }

        .status-indicator {
            display: flex; align-items: center; gap: 10px; font-family: 'Roboto Mono', monospace;
            font-size: 0.75rem; color: var(--accent-color); letter-spacing: 2px;
            padding: 10px 18px; border: 1px solid rgba(0, 229, 255, 0.2);
            border-radius: 4px; background: rgba(0,0,0,0.4); text-transform: uppercase; margin-right: 100px;
        }
        html.light-mode .status-indicator { background: #FFF; border-color: rgba(10,17,40,0.1); color: var(--midnight-navy); }

        /* --- SPLIT ARCHITECTURE --- */
        .panel-body { display: grid; grid-template-columns: 1.1fr 0.9fr; min-height: 600px; }
        .calendar-pane { padding: 50px; border-right: 1px dashed rgba(255, 255, 255, 0.1); }
        html.light-mode .calendar-pane { border-right-color: rgba(10,17,40,0.1); }
        .form-pane { padding: 50px; background: rgba(0,0,0,0.2); position: relative; }
        html.light-mode .form-pane { background: rgba(10,17,40,0.02); }

        .section-label { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--luminous-teal); letter-spacing: 2px; margin-bottom: 25px; display: block; text-transform: uppercase; font-weight: bold; }
        html.light-mode .section-label { color: var(--midnight-navy); }

        /* --- CALENDAR UI --- */
        .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 15px 20px; background: rgba(0, 229, 255, 0.05); border-radius: 4px; border: 1px solid rgba(0, 229, 255, 0.2); }
        html.light-mode .cal-header { background: rgba(10, 17, 40, 0.03); border-color: rgba(10, 17, 40, 0.1); }
        
        .cal-nav { cursor: pointer; color: var(--stark-white); transition: color 0.2s; font-size: 1rem; padding: 0 10px; }
        html.light-mode .cal-nav { color: var(--midnight-navy); }
        .cal-nav:hover { color: var(--accent-color); }
        
        .cal-grid-header { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 15px; font-weight: 600; letter-spacing: 1px; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
        
        .cal-cell { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-family: 'Roboto Mono', monospace; font-size: 0.9rem; border-radius: 4px; cursor: pointer; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.02); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
        html.light-mode .cal-cell { background: rgba(0,0,0,0.02); border-color: rgba(10,17,40,0.05); color: var(--midnight-navy); }
        .cal-cell:hover:not(.empty):not(.disabled) { border-color: var(--accent-color); background: rgba(0, 229, 255, 0.05); transform: scale(1.05); }
        .cal-cell.active { background: var(--accent-color); color: var(--midnight-navy); font-weight: 700; box-shadow: 0 5px 15px rgba(0, 229, 255, 0.3); transform: scale(1.05); border-color: var(--accent-color); }
        .cal-cell.empty { background: transparent; border-color: transparent; cursor: default; }
        .cal-cell.disabled { opacity: 0.2; cursor: not-allowed; background: transparent; border-color: transparent; }

        .time-slots { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 25px; }
        .time-slot { background: rgba(10, 17, 40, 0.8); border: 1px solid rgba(255,255,255,0.1); color: var(--stark-white); padding: 12px; text-align: center; cursor: pointer; border-radius: 4px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; transition: all 0.2s ease; }
        html.light-mode .time-slot { background: #FFF; color: var(--midnight-navy); border-color: rgba(10,17,40,0.1); }
        .time-slot:hover { border-color: var(--accent-color); color: var(--accent-color); transform: translateY(-2px); }
        .time-slot.active { background: var(--accent-color); color: var(--midnight-navy); font-weight: bold; box-shadow: 0 5px 15px rgba(0, 229, 255, 0.3); border-color: var(--accent-color); transform: translateY(-2px); }

        /* --- FORM UI --- */
        .matrix-label { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: 600; color: var(--stark-white); letter-spacing: 0.5px; margin-bottom: 6px; display: block; text-transform: uppercase; }
        html.light-mode .matrix-label { color: var(--midnight-navy); }
        
        .matrix-input, .matrix-select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--stark-white); padding: 14px 18px; font-family: 'Inter', sans-serif; font-size: 0.95rem; border-radius: 4px; outline: none; transition: all 0.3s ease; margin-bottom: 20px; }
        html.light-mode .matrix-input, html.light-mode .matrix-select { background: #FFF; color: var(--midnight-navy); border-color: rgba(10,17,40,0.15); }
        .matrix-input:focus, .matrix-select:focus { border-color: var(--accent-color); box-shadow: inset 0 0 10px rgba(0, 229, 255, 0.1); }
        html.light-mode .matrix-input:focus, html.light-mode .matrix-select:focus { border-color: var(--midnight-navy); box-shadow: inset 0 0 10px rgba(10, 17, 40, 0.05); }
        
        .matrix-input:disabled, .matrix-select:disabled { opacity: 0.3; cursor: not-allowed; }
        
        /* The specific fix for the Chevron Bug */
        .matrix-select { 
            appearance: none; -webkit-appearance: none; cursor: pointer; padding-right: 40px; 
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%238A9BB2' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' viewBox='0 0 24 24'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 15px center;
        }
        .matrix-select option { background: #0A1128; color: var(--stark-white); }
        html.light-mode .matrix-select option { background: var(--stark-white); color: var(--midnight-navy); }
        
        .error-msg { color: var(--terminal-red); font-size: 0.7rem; font-family: 'Roboto Mono', monospace; margin-top: -15px; margin-bottom: 15px; display: block; }

        .idle-state { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(5, 9, 20, 0.85); backdrop-filter: blur(10px); z-index: 20; transition: opacity 0.5s ease; border-radius: 0 0 6px 0; }
        html.light-mode .idle-state { background: rgba(248, 250, 252, 0.85); }

        /* THE SYSTEM LOG */
        .system-log-console { margin-top: 15px; padding: 15px; background: rgba(0,0,0,0.4); border-left: 2px solid var(--accent-color); font-family: 'Roboto Mono', monospace; border-radius: 0 4px 4px 0; }
        html.light-mode .system-log-console { background: rgba(10, 17, 40, 0.05); border-left-color: var(--midnight-navy); }
        .log-text { font-size: 0.7rem; color: var(--accent-color); opacity: 0.8; transition: opacity 0.2s ease; }
        html.light-mode .log-text { color: var(--midnight-navy); font-weight: bold; }

        /* --- SUCCESS STATE --- */
        .success-panel { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 40px; text-align: center; width: 100%; min-height: 600px; }
        .check-icon { font-size: 4rem; color: var(--accent-color); margin-bottom: 25px; line-height: 1; text-shadow: 0 0 30px rgba(0, 229, 255, 0.4); }
        .highlight-email { color: var(--luminous-teal); font-family: 'Roboto Mono', monospace; border-bottom: 1px dashed var(--accent-color); padding-bottom: 2px; }
        html.light-mode .highlight-email { color: var(--midnight-navy); border-bottom-color: var(--midnight-navy); }

        .loader-ring { width: 20px; height: 20px; border: 2px solid rgba(10,17,40,0.2); border-top-color: var(--midnight-navy); border-radius: 50%; animation: spin 0.6s linear infinite; display: none; margin-left: 10px; }
        html.light-mode .loader-ring { border-color: rgba(255,255,255,0.3); border-top-color: var(--stark-white); }

        /* ==========================================================================
           MOBILE RESPONSIVE MASTER
           ========================================================================== */
        @media (max-width: 950px) {
            .schedule-wrapper { padding: 40px 15px; }
            .briefing-header { flex-direction: column; align-items: flex-start; gap: 20px; padding: 30px 20px; }
            .briefing-title { font-size: 1.8rem; }
            .status-indicator { margin-right: 0; width: 100%; justify-content: center; margin-top: 10px; }
            .panel-controls { top: 20px; right: 20px; }
            .panel-body { grid-template-columns: 1fr; min-height: auto; }
            .calendar-pane { padding: 30px 20px; border-right: none; border-bottom: 1px dashed rgba(255, 255, 255, 0.1); }
            html.light-mode .calendar-pane { border-bottom-color: rgba(10,17,40,0.1); }
            .form-pane { padding: 30px 20px; }
            .success-panel { min-height: 500px; padding: 40px 20px; }
        }
    </style>
    @endpush

    <div class="immersive-bg"></div>
    <div class="immersive-overlay"></div>
    <div class="scanline-grid"></div>

    <div class="schedule-wrapper">
        <div class="glass-panel" id="command-center">
            
            <div class="panel-controls">
                @if($isSuccess || $selectedTime)
                    <button wire:click="$set('isSuccess', false); $set('selectedTime', null); $set('selectedDate', null);" class="control-btn" title="Restart Booking" aria-label="Restart Booking">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v5h5"/></svg>
                    </button>
                @endif
                <a href="/" wire:navigate class="control-btn" title="Return Home" aria-label="Return Home">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </a>
            </div>
            
            @if($isSuccess)
                <div class="success-panel" style="animation: fadeIn 0.6s ease forwards;">
                    <div class="check-icon">✓</div>
                    <h2 class="mono-font" style="font-size: 2.2rem; margin-bottom: 15px; color: var(--stark-white);">Coordinates Locked.</h2>
                    <p style="color: var(--text-secondary); max-width: 550px; margin: 0 auto 40px auto; font-size: 1.05rem; line-height: 1.7;">
                        > Your consultation request has been forwarded to our Intelligence Desk. It is under review.<br><br>
                        Once approved, a secure meeting link and calendar invite will be dispatched to <span class="highlight-email">{{ $email }}</span>.
                    </p>
                    <button class="terminal-action-btn" style="width: auto; padding: 16px 40px;" onclick="window.location.href='/'" wire:navigate>RETURN TO MAIN TERMINAL</button>
                </div>
            @else
                <div class="briefing-header">
                    <div>
                        <h1 class="briefing-title">EXECUTIVE CONSULTATION</h1>
                        <p class="briefing-desc">Select an available deployment window to interface directly with a Praxis Lead Architect. Zero obligations. Total clarity.</p>
                    </div>
                    <div class="status-indicator">
                        <span class="pulse-dot"></span> SECURE CONNECTION
                    </div>
                </div>

                <div class="panel-body">
                    <div class="calendar-pane">
                        <span class="section-label">> 01. SELECT DEPLOYMENT WINDOW</span>
                        
                        <div class="full-calendar">
                            <div class="cal-header">
                                <span class="cal-nav" wire:click="changeMonth(-1)">&#10094;</span>
                                <span class="mono-font" style="font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--stark-white);">
                                    {{ \Carbon\Carbon::create($currentYear, $currentMonth, 1)->format('F Y') }}
                                </span>
                                <span class="cal-nav" wire:click="changeMonth(1)">&#10095;</span>
                            </div>
                            
                            <div class="cal-grid-header mono-font">
                                <span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span>
                            </div>
                            
                            <div class="cal-grid">
                                @foreach($daysInMonth as $day)
                                    @if($day['day'] === null)
                                        <div class="cal-cell empty"></div>
                                    @elseif($day['is_past'])
                                        <div class="cal-cell disabled">{{ $day['day'] }}</div>
                                    @else
                                        <div class="cal-cell {{ $selectedDate === $day['full_date'] ? 'active' : '' }}" 
                                             wire:click="selectDate('{{ $day['full_date'] }}')" onfocus="updateScheduleLog('ISOLATING_TEMPORAL_COORDINATES')">
                                            {{ $day['day'] }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        @if($selectedDate)
                            <div style="margin-top: 35px; animation: fadeIn 0.4s ease;">
                                <span class="section-label" style="margin-bottom: 15px;">> AVAILABLE SLOTS (WAT)</span>
                                @if(count($availableSlots) > 0)
                                    <div class="time-slots">
                                        @foreach($availableSlots as $time)
                                            <div class="time-slot {{ $selectedTime === $time ? 'active' : '' }}" 
                                                 wire:click="selectTime('{{ $time }}')" onclick="updateScheduleLog('LOCKING_TIME_VECTOR')">
                                                {{ $time }}
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mono-font" style="color: var(--terminal-red); font-size: 0.75rem; padding: 15px; background: rgba(255,51,102,0.05); border: 1px dashed var(--terminal-red); text-align: center;">> ERR: ALL SLOTS BOOKED FOR THIS DATE.</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="form-pane">
                        @if(!$selectedTime)
                            <div class="idle-state">
                                <div style="text-align: center; color: var(--text-secondary);">
                                    <div style="font-size: 2rem; margin-bottom: 15px; opacity: 0.5;">⏱</div>
                                    <p class="mono-font" style="font-size: 0.8rem; letter-spacing: 2px;">> AWAITING TEMPORAL SELECTION...</p>
                                </div>
                            </div>
                        @endif

                        <span class="section-label">> 02. EXECUTIVE CLEARANCE</span>
                        
                        @if($selectedTime)
                            <p class="mono-font teal-text" style="font-size: 0.75rem; margin-bottom: 25px; border-left: 2px solid var(--accent-color); padding-left: 10px;">
                                > TARGET ACQUIRED: {{ \Carbon\Carbon::parse($selectedDate)->format('M d, Y') }} @ {{ $selectedTime }} WAT
                            </p>
                        @endif

                        <form wire:submit.prevent="processBooking" onsubmit="document.getElementById('command-center').scrollIntoView({ behavior: 'smooth', block: 'center' })">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label class="matrix-label">FULL NAME</label>
                                    <input type="text" class="matrix-input" wire:model="fullName" placeholder="John Doe" {{ !$selectedTime ? 'disabled' : '' }} onfocus="updateScheduleLog('VERIFYING_IDENTITY')">
                                    @error('fullName') <span class="error-msg">> {{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="matrix-label">DESIGNATION</label>
                                    <input type="text" class="matrix-input" wire:model="title" placeholder="e.g. CEO" {{ !$selectedTime ? 'disabled' : '' }} onfocus="updateScheduleLog('LOGGING_CORPORATE_RANK')">
                                    @error('title') <span class="error-msg">> {{ $message }}</span> @enderror
                                </div>
                            </div>

                            <label class="matrix-label">CORPORATE EMAIL</label>
                            <input type="email" class="matrix-input" wire:model="email" placeholder="executive@enterprise.com" {{ !$selectedTime ? 'disabled' : '' }} onfocus="updateScheduleLog('ESTABLISHING_SECURE_COMMS')">
                            @error('email') <span class="error-msg">> {{ $message }}</span> @enderror

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label class="matrix-label">ENTERPRISE NAME</label>
                                    <input type="text" class="matrix-input" wire:model="enterpriseName" placeholder="Acme Corp" {{ !$selectedTime ? 'disabled' : '' }} onfocus="updateScheduleLog('SCANNING_ENTERPRISE_REGISTRY')">
                                    @error('enterpriseName') <span class="error-msg">> {{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="matrix-label">PHONE / WHATSAPP</label>
                                    <input type="tel" class="matrix-input" wire:model="phone" placeholder="+234 ..." {{ !$selectedTime ? 'disabled' : '' }} onfocus="updateScheduleLog('ROUTING_COMM_CHANNEL')">
                                    @error('phone') <span class="error-msg">> {{ $message }}</span> @enderror
                                </div>
                            </div>

                            <label class="matrix-label">CONSULTATION FOCUS</label>
                            <select class="matrix-select" wire:model.live="focus" {{ !$selectedTime ? 'disabled' : '' }} onfocus="updateScheduleLog('SETTING_ARCHITECTURAL_OBJECTIVE')" style="margin-bottom: 10px;">
                                <option value="" disabled selected>Select Primary Objective...</option>
                                <option value="Performance Audit">Performance Audit / Compliance</option>
                                <option value="Data Architecture">Data Engineering Review</option>
                                <option value="Custom Software">Custom Software / ERP Build</option>
                                <option value="Retainer">Resilience / Maintenance Retainer</option>
                                <option value="Other">Other (Specify)</option>
                            </select>
                            @error('focus') <span class="error-msg">> {{ $message }}</span> @enderror
                            
                            @if($focus === 'Other')
                                <input type="text" class="matrix-input" wire:model="customFocus" placeholder="Specify objective (Max 40 chars)..." maxlength="40" style="margin-bottom: 20px; border-color: var(--erp-teal);">
                                @error('customFocus') <span class="error-msg">> {{ $message }}</span> @enderror
                            @else
                                <div style="margin-bottom: 20px;"></div> @endif
                            
                            <button type="submit" class="terminal-action-btn" {{ !$selectedTime ? 'disabled' : '' }} onclick="submitScheduleFX(event)">
                                <span id="scheduleSubmitText" wire:loading.remove wire:target="processBooking">REQUEST SECURE SLOT</span>
                                <div wire:loading.inline-block wire:target="processBooking" class="loader-ring"></div>
                            </button>

                            <div class="system-log-console">
                                <div id="scheduleSystemLog" class="log-text">
                                    > AWAITING CLEARANCE INPUT...
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        // The Terminal Gamification Engine for the Schedule Hub
        window.updateScheduleLog = function(message) {
            const log = document.getElementById('scheduleSystemLog');
            if(!log) return;
            log.style.opacity = '0.4';
            setTimeout(() => {
                log.innerText = `> ${message}... OK.`;
                log.style.opacity = '1';
            }, 100);
        };

        window.submitScheduleFX = function(e) {
            const btn = e.currentTarget;
            const btnText = document.getElementById('scheduleSubmitText');
            if(btnText) btnText.innerText = 'ENCRYPTING RESERVATION...';
            updateScheduleLog('ROUTING_TO_LOBBY_DATABASE');
        };
    </script>
</div>