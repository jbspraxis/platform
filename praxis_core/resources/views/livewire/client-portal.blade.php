<div x-data="{ state: @entangle('viewState'), qaSuccess: @entangle('qaSuccess') }" x-init="$watch('qaSuccess', value => { if(value) setTimeout(() => qaSuccess = false, 4000) })">
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - SECURE CLIENT PORTAL MASTER CSS
           ========================================================================== */

        .portal-canvas-wrapper { 
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; 
            z-index: -10; /* This pushes the canvas BEHIND your global logo and header */
            pointer-events: none; 
            background-image: url('{{ asset("assets/img/hero_home.webp") }}'); 
            background-size: cover; background-position: center; 
            filter: grayscale(100%) brightness(0.15); transition: all 0.5s ease; 
        }
        html.light-mode .portal-canvas-wrapper { filter: grayscale(90%) brightness(0.9) opacity(0.4); }
        
        #techCanvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; opacity: 0.6; }
        .scanline { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, transparent 50%, rgba(0, 229, 255, 0.05) 51%); background-size: 100% 4px; z-index: 2; pointer-events: none; }
        .vignette { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle, transparent 40%, #050914 100%); z-index: 3; pointer-events: none; transition: all 0.5s ease; }
        html.light-mode .vignette { background: radial-gradient(circle, transparent 40%, var(--ghost-white) 100%); }

        .portal-main { position: relative; z-index: 10; flex: 1; display: flex; flex-direction: column; padding-top: 80px; min-height: 100vh; }
        
        /* --- GLOBAL ANIMATIONS --- */
        @keyframes pulseTeal { 0% { box-shadow: 0 0 0 0 rgba(0, 229, 255, 0.4); } 70% { box-shadow: 0 0 0 15px rgba(0, 229, 255, 0); } 100% { box-shadow: 0 0 0 0 rgba(0, 229, 255, 0); } }
        @keyframes blinkLive { 50% { opacity: 0.3; } }
        @keyframes slideIn { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes spin { to { transform: rotate(360deg); } }

        .loader-ring { width: 24px; height: 24px; border: 3px solid rgba(10,17,40,0.2); border-top-color: var(--midnight-navy); border-radius: 50%; animation: spin 0.6s linear infinite; margin: 0 auto; }
        html.light-mode .loader-ring { border-color: rgba(255,255,255,0.3); border-top-color: var(--stark-white); }

        /* --- AUTHENTICATION STATES (GATEKEEPER) --- */
        .gatekeeper-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; min-height: 80vh; }
        
        .login-matrix { background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border: 1px solid rgba(0, 229, 255, 0.3); border-top: 3px solid var(--accent-color); border-radius: 8px; padding: 60px 50px; width: 100%; max-width: 550px; text-align: center; box-shadow: 0 40px 100px rgba(0,0,0,0.8), inset 0 0 30px rgba(0, 229, 255, 0.05); position: relative; overflow: hidden; }
        html.light-mode .login-matrix { background: rgba(255, 255, 255, 0.95); border-color: rgba(10,17,40,0.1); border-top-color: var(--midnight-navy); box-shadow: 0 40px 100px rgba(10,17,40,0.1); }

        .login-box-title { position: relative; z-index: 1; margin-bottom: 40px; }
        .login-box-title h2 { font-size: 2.2rem; margin-bottom: 10px; color: var(--stark-white); letter-spacing: -1px; text-shadow: 0 0 20px rgba(0, 229, 255, 0.3); }
        html.light-mode .login-box-title h2 { color: var(--midnight-navy); text-shadow: none; }
        .geo-typing { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--luminous-teal); min-height: 40px; letter-spacing: 1px; }
        html.light-mode .geo-typing { color: var(--midnight-navy); opacity: 0.8; }

        .auth-inputs { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 30px; margin-bottom: 30px; }
        .minimal-input { width: 100%; background: transparent; border: none; border-bottom: 1px solid rgba(255,255,255,0.2); color: var(--stark-white); font-family: 'Roboto Mono', monospace; font-size: 1.1rem; padding: 10px 5px; outline: none; transition: all 0.3s ease; text-align: center; letter-spacing: 2px; }
        html.light-mode .minimal-input { border-bottom-color: rgba(10,17,40,0.2); color: var(--midnight-navy); }
        .minimal-input:focus { border-bottom-color: var(--accent-color); box-shadow: 0 10px 10px -10px rgba(0, 229, 255, 0.4); }
        html.light-mode .minimal-input:focus { border-bottom-color: var(--midnight-navy); box-shadow: 0 10px 10px -10px rgba(10,17,40,0.1); }
        .minimal-input::placeholder { color: var(--text-secondary); opacity: 0.5; font-size: 0.9rem; }
        
        .error-txt { color: var(--terminal-red); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; text-align: center; display: block; margin-top: -15px; }

        .btn-auth { width: 100%; padding: 18px; background: var(--luminous-teal); color: var(--midnight-navy); font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1rem; letter-spacing: 2px; border: none; border-radius: 4px; cursor: pointer; transition: all 0.3s ease; position: relative; z-index: 1; text-transform: uppercase; }
        .btn-auth:hover { background: var(--stark-white); box-shadow: 0 0 30px rgba(0, 229, 255, 0.5); transform: translateY(-2px); }
        html.light-mode .btn-auth { background: var(--midnight-navy); color: var(--stark-white); }
        html.light-mode .btn-auth:hover { background: var(--accent-color); color: var(--midnight-navy); box-shadow: 0 10px 20px rgba(10,17,40,0.1); }
        
        .auth-links { margin-top: 25px; font-size: 0.8rem; font-family: 'Roboto Mono', monospace; position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: center; }
        .auth-links span { color: var(--luminous-teal); opacity: 0.8; }
        html.light-mode .auth-links span { color: var(--midnight-navy); font-weight: bold; }
        .auth-links a { color: var(--text-secondary); transition: color 0.3s; cursor: pointer; border-bottom: 1px dashed transparent; }
        .auth-links a:hover { color: var(--accent-color); border-bottom-color: var(--accent-color); }
        html.light-mode .auth-links a:hover { color: var(--midnight-navy); border-bottom-color: var(--midnight-navy); }

        /* --- DASHBOARD STATE --- */
        .dashboard-container { width: 100%; max-width: 1600px; margin: 0 auto; padding: 40px 20px 100px 20px; display: flex; flex-direction: column; gap: 30px; position: relative; z-index: 10; }

        .exec-summary-bar { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(0, 229, 255, 0.2); border-left: 4px solid var(--accent-color); padding: 25px 30px; border-radius: 6px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        html.light-mode .exec-summary-bar { background: rgba(255,255,255,0.95); border-color: rgba(10,17,40,0.1); border-left-color: var(--midnight-navy); box-shadow: 0 20px 40px rgba(10,17,40,0.05); }
        
        .summary-node { display: flex; flex-direction: column; gap: 5px; }
        .summary-label { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }
        .summary-value { font-size: 1.1rem; font-weight: 700; color: var(--stark-white); text-transform: uppercase; letter-spacing: -0.5px; }
        html.light-mode .summary-value { color: var(--midnight-navy); }
        .summary-value.highlight { color: var(--accent-color); text-shadow: 0 0 10px rgba(0,229,255,0.4); }
        html.light-mode .summary-value.highlight { color: #1E3A8A; text-shadow: none; font-weight: 900; }

        /* --- THE SPRINT TIMELINE --- */
        .sprint-timeline { background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(0, 229, 255, 0.2); padding: 50px 40px; border-radius: 6px; overflow-x: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        html.light-mode .sprint-timeline { background: rgba(255,255,255,0.95); border-color: rgba(10,17,40,0.1); box-shadow: 0 20px 40px rgba(10,17,40,0.05); }
        
        .timeline-wrapper { display: flex; justify-content: space-between; position: relative; min-width: 800px; }
        .timeline-track { position: absolute; top: 20px; left: 0; width: 100%; height: 2px; background: rgba(255,255,255,0.1); z-index: 1; }
        html.light-mode .timeline-track { background: rgba(10,17,40,0.1); }
        .timeline-progress { position: absolute; top: 20px; left: 0; height: 2px; background: var(--accent-color); z-index: 2; box-shadow: 0 0 15px var(--accent-color); transition: width 1s cubic-bezier(0.16, 1, 0.3, 1); }
        html.light-mode .timeline-progress { background: var(--midnight-navy); box-shadow: 0 0 10px rgba(10,17,40,0.2); }
        
        .phase-node { position: relative; z-index: 3; display: flex; flex-direction: column; align-items: center; gap: 15px; flex: 1; text-align: center; }
        .phase-dot { width: 42px; height: 42px; border-radius: 50%; background: var(--midnight-navy); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; transition: all 0.4s ease; color: var(--text-secondary); }
        html.light-mode .phase-dot { background: var(--stark-white); border-color: rgba(10,17,40,0.2); color: var(--text-secondary); }
        
        .phase-title { font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }
        
        .phase-node.completed .phase-dot { background: var(--stark-white); color: var(--midnight-navy); border-color: var(--stark-white); box-shadow: 0 0 15px rgba(255,255,255,0.2); }
        html.light-mode .phase-node.completed .phase-dot { background: var(--midnight-navy); color: var(--stark-white); border-color: var(--midnight-navy); }
        .phase-node.completed .phase-title { color: var(--stark-white); }
        html.light-mode .phase-node.completed .phase-title { color: var(--midnight-navy); }
        
        .phase-node.active .phase-dot { background: rgba(0,229,255,0.1); border-color: var(--accent-color); color: var(--accent-color); box-shadow: 0 0 20px rgba(0,229,255,0.4); animation: pulseTeal 2s infinite; }
        html.light-mode .phase-node.active .phase-dot { background: rgba(30,58,138,0.05); border-color: var(--midnight-navy); color: var(--midnight-navy); box-shadow: none; }
        .phase-node.active .phase-title { color: var(--accent-color); }
        html.light-mode .phase-node.active .phase-title { color: var(--midnight-navy); }

        /* --- DASHBOARD SPLIT GRID --- */
        .dash-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; align-items: stretch; }
        
        .terminal-panel { background: rgba(5, 9, 20, 0.9); backdrop-filter: blur(10px); border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 6px; display: flex; flex-direction: column; height: 550px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); overflow: hidden; }
        html.light-mode .terminal-panel { background: rgba(255,255,255,0.95); border-color: rgba(10,17,40,0.1); box-shadow: 0 20px 40px rgba(10,17,40,0.05); }
        
        .panel-header { padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0, 229, 255, 0.02); display: flex; justify-content: space-between; align-items: center; }
        html.light-mode .panel-header { border-bottom-color: rgba(10,17,40,0.1); background: rgba(10,17,40,0.02); }
        
        .panel-title { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--accent-color); letter-spacing: 2px; display: flex; align-items: center; gap: 10px; font-weight: bold; }
        html.light-mode .panel-title { color: var(--midnight-navy); }
        .live-indicator { width: 8px; height: 8px; background: var(--terminal-red); border-radius: 50%; box-shadow: 0 0 10px var(--terminal-red); animation: blinkLive 1.5s infinite; }

        /* LOG FEED */
        .log-feed { padding: 25px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; }
        .log-item { padding-left: 20px; border-left: 2px solid rgba(255,255,255,0.1); animation: slideIn 0.4s ease forwards; }
        html.light-mode .log-item { border-left-color: rgba(10,17,40,0.1); }
        .log-time { color: var(--accent-color); margin-bottom: 5px; font-size: 0.75rem; opacity: 0.8; letter-spacing: 1px; }
        html.light-mode .log-time { color: var(--midnight-navy); }
        .log-text { color: var(--stark-white); line-height: 1.6; }
        html.light-mode .log-text { color: var(--midnight-navy); }

        /* QA FORM */
        .qa-form { padding: 30px 25px; display: flex; flex-direction: column; gap: 20px; flex: 1; overflow-y: auto;}
        .qa-desc { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 10px; line-height: 1.5; }
        
        .custom-input, .custom-select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--stark-white); padding: 15px 20px; font-family: 'Inter', sans-serif; font-size: 0.9rem; outline: none; border-radius: 4px; transition: all 0.3s; }
        html.light-mode .custom-input, html.light-mode .custom-select { background: rgba(0,0,0,0.02); color: var(--midnight-navy); border-color: rgba(10,17,40,0.15); }
        .custom-select { appearance: none; cursor: pointer; }
        .custom-input:focus, .custom-select:focus { border-color: var(--accent-color); box-shadow: inset 0 0 10px rgba(0, 229, 255, 0.1); }
        html.light-mode .custom-input:focus, html.light-mode .custom-select:focus { border-color: var(--midnight-navy); box-shadow: none; }
        .custom-select option { background: #0A1128; color: var(--stark-white); }
        html.light-mode .custom-select option { background: var(--stark-white); color: var(--midnight-navy); }
        
        .custom-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--stark-white); padding: 15px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; outline: none; border-radius: 4px; resize: vertical; min-height: 120px; transition: all 0.3s; }
        html.light-mode .custom-textarea { background: rgba(0,0,0,0.02); color: var(--midnight-navy); border-color: rgba(10,17,40,0.15); }
        .custom-textarea:focus { border-color: var(--accent-color); box-shadow: inset 0 0 10px rgba(0, 229, 255, 0.1); }
        html.light-mode .custom-textarea:focus { border-color: var(--midnight-navy); box-shadow: none; }

        .btn-qa { background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color); padding: 16px; font-family: 'Roboto Mono', monospace; font-weight: bold; text-transform: uppercase; cursor: pointer; transition: all 0.3s; width: 100%; border-radius: 4px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-qa:hover { background: var(--accent-color); color: var(--midnight-navy); box-shadow: 0 0 20px rgba(0, 229, 255, 0.3); }
        html.light-mode .btn-qa { border-color: var(--midnight-navy); color: var(--midnight-navy); }
        html.light-mode .btn-qa:hover { background: var(--midnight-navy); color: var(--stark-white); box-shadow: none; }

        /* --- REDLINE & FOOTER BAR --- */
        .redline-wrapper { position: fixed; bottom: 30px; right: 30px; z-index: 2000; }
        .btn-redline { background: rgba(255, 51, 102, 0.1); border: 1px solid var(--terminal-red); color: var(--stark-white); padding: 12px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.3s ease; backdrop-filter: blur(10px); border-radius: 4px; text-transform: uppercase; box-shadow: 0 0 15px rgba(255, 51, 102, 0.3); }
        html.light-mode .btn-redline { color: var(--midnight-navy); background: rgba(255, 255, 255, 0.9); }
        .btn-redline:hover { background: var(--terminal-red); box-shadow: 0 0 30px rgba(255, 51, 102, 0.6); color: var(--stark-white) !important; }
        
        .telemetry-quote-bar { position: fixed; bottom: 0; left: 0; width: 100%; background: rgba(5, 9, 20, 0.95); border-top: 1px solid rgba(255,255,255,0.05); padding: 12px 20px; display: flex; justify-content: center; z-index: 100; backdrop-filter: blur(10px); }
        html.light-mode .telemetry-quote-bar { background: rgba(255, 255, 255, 0.95); border-top-color: rgba(10,17,40,0.1); }
        .quote-content { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--text-secondary); letter-spacing: 2px; opacity: 1; transition: opacity 0.5s ease; text-transform: uppercase; font-weight: bold; }

        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        ::-webkit-scrollbar-thumb { background: var(--luminous-teal); border-radius: 2px; }
        html.light-mode ::-webkit-scrollbar-thumb { background: var(--midnight-navy); }

        @media (max-width: 1024px) {
            .exec-summary-bar { grid-template-columns: repeat(3, 1fr); }
            .dash-grid { grid-template-columns: 1fr; }
            .terminal-panel { height: auto; min-height: 400px; }
        }
        @media (max-width: 850px) {
            .login-matrix { padding: 40px 20px; }
            .login-box-title h2 { font-size: 1.8rem; }
            .exec-summary-bar { grid-template-columns: 1fr; gap: 15px; }
            .sprint-timeline { padding: 30px 20px; }
            .telemetry-quote-bar { display: none; } /* Hide on small mobile to save space */
            .redline-wrapper { bottom: 20px; right: 20px; }
        }
    </style>
    @endpush

    <div class="portal-canvas-wrapper">
        <canvas id="techCanvas"></canvas>
        <div class="scanline"></div>
        <div class="vignette"></div>
    </div>

    <main class="portal-main">
        
        <div x-show="state === 'gatekeeper'" 
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="gatekeeper-container">
             
            <div class="login-matrix">
                <div class="login-box-title">
                    <h2 class="brand-title">LIVE SPRINT TRACKING</h2>
                    <div class="geo-typing" id="geoWelcome">> INITIATING SECURE HANDSHAKE...</div>
                </div>

                <form wire:submit.prevent="executeAuthentication">
                    <div class="auth-inputs">
                        <div>
                            <input type="email" class="minimal-input" wire:model="clientId" placeholder="CORPORATE EMAIL ID" onfocus="document.getElementById('geoWelcome').innerText='> VERIFYING CLEARANCE LEVEL...'">
                            @error('clientId') <span class="error-txt">> {{ $message }}</span> @enderror
                        </div>
                        <div>
                            <input type="password" class="minimal-input" wire:model="projectId" placeholder="CRYPTOGRAPHIC KEY" onfocus="document.getElementById('geoWelcome').innerText='> AWAITING DECRYPTION KEY...'">
                            @error('projectId') <span class="error-txt">> {{ $message }}</span> @enderror
                        </div>
                    </div>

                    <button type="submit" class="btn-auth" onclick="document.getElementById('geoWelcome').innerText='> AUTHENTICATING...'">
                        <span wire:loading.remove wire:target="executeAuthentication">INITIATE TRACKING</span>
                        <div wire:loading wire:target="executeAuthentication" class="loader-ring" style="display: block;"></div>
                    </button>
                    
                    <div class="auth-links">
                        <span>🔒 E2E Encrypted</span>
                        <a wire:click="triggerForgotPassword">Recover Key ></a>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="state === 'forgot_password'" style="display: none;"
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="gatekeeper-container">
             
            <div class="login-matrix">
                <div class="auth-header">
                    <h2 class="brand-title">SYSTEM OVERRIDE</h2>
                    <div class="geo-typing">> DISPATCH RECOVERY PROTOCOL</div>
                </div>

                <form wire:submit.prevent="sendResetLink">
                    @if($authMessage)
                        <div style="background: rgba(0, 229, 255, 0.1); border: 1px solid var(--accent-color); color: var(--accent-color); padding: 15px; margin-bottom: 25px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border-radius: 4px;">
                            > {{ $authMessage }}
                        </div>
                    @endif

                    <div class="auth-inputs">
                        <div>
                            <input type="email" class="minimal-input" wire:model="resetEmail" placeholder="ENTER REGISTERED EMAIL">
                            @error('resetEmail') <span class="error-txt">> {{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <button type="button" class="btn-outline" style="flex: 0.5;" wire:click="cancelReset">
                            ABORT
                        </button>
                        <button type="submit" class="btn-auth" style="flex: 1;">
                            <span wire:loading.remove wire:target="sendResetLink">DISPATCH LINK</span>
                            <div wire:loading wire:target="sendResetLink" class="loader-ring" style="display: block;"></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="state === 'dashboard'" style="display: none;"
             x-transition:enter="transition ease-out duration-500 delay-500"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">
             
            @if($project)
            <div class="dashboard-container">
                
                <div class="exec-summary-bar">
                    <div class="summary-node">
                        <span class="summary-label">Project Designation</span>
                        <span class="summary-value highlight">{{ $project->title }}</span>
                    </div>
                    <div class="summary-node">
                        <span class="summary-label">Client Origin</span>
                        <span class="summary-value">{{ $organization->name }}</span>
                    </div>
                    <div class="summary-node">
                        <span class="summary-label">Overall Progress</span>
                        <span class="summary-value" style="color: #00FF66; text-shadow: 0 0 10px rgba(0,255,102,0.4);">{{ $overallProgress }}% COMPLETE</span>
                    </div>
                    <div class="summary-node">
                        <span class="summary-label">Financial Allocation</span>
                        <span class="summary-value">{{ $project->currency }} {{ number_format($project->total_budget) }}</span>
                    </div>
                    <div class="summary-node">
                        <span class="summary-label">Strike Team Lead</span>
                        <span class="summary-value">{{ $leadArchitect }}</span>
                    </div>
                </div>

                <div class="sprint-timeline">
                    <div class="timeline-wrapper">
                        <div class="timeline-track"></div>
                        <div class="timeline-progress" style="width: {{ $overallProgress }}%;"></div> 
                        
                        @foreach($phases as $phase)
                            <div class="phase-node {{ $phase['status'] === 'Completed' ? 'completed' : ($phase['status'] === 'Active' ? 'active' : '') }}">
                                <div class="phase-dot">
                                    {{ $phase['status'] === 'Completed' ? '✓' : str_pad($phase['phase_number'], 2, '0', STR_PAD_LEFT) }}
                                </div>
                                <span class="phase-title">{{ $phase['title'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="dash-grid">
                    
                    <div class="terminal-panel">
                        <div class="panel-header">
                            <span class="panel-title">
                                <span class="pulse-dot"></span> > LIVE ENGINEERING BACKLOG
                            </span>
                            <span class="live-indicator"></span>
                        </div>
                        <div class="log-feed">
                            @forelse($liveLogs as $log)
                                <div class="log-item">
                                    <div class="log-time">[{{ $log['time'] }}] BY: {{ $log['author'] }}</div>
                                    <div class="log-text">> {!! $log['text'] !!}</div>
                                </div>
                            @empty
                                <div class="log-item" style="border-left-color: var(--terminal-red);">
                                    <div class="log-text" style="color: var(--text-secondary);">> AWAITING INITIAL SPRINT LOGS...</div>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="terminal-panel">
                        <div class="panel-header">
                            <span class="panel-title" style="color: var(--stark-white);">> SUBMIT QA / VULNERABILITY</span>
                        </div>
                        <div class="qa-form">
                            <p class="qa-desc">Log structural issues directly into the active sprint backlog. The engineering team will be paged instantly.</p>
                            
                            <form wire:submit.prevent="submitQA" style="display: flex; flex-direction: column; gap: 20px; height: 100%;">
                                
                                @if($qaSuccess)
                                    <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 0.85rem; text-align: center;">
                                        > INTELLIGENCE RECEIVED. BACKLOG UPDATED.
                                    </div>
                                @endif

                                <div style="position: relative;">
                                    <select class="custom-select" wire:model="qaTaskId">
                                        <option value="" selected>General Project Feedback</option>
                                        @foreach($activeTasks as $task)
                                            <option value="{{ $task->id }}">{{ $task->phase_title }} : {{ $task->title }}</option>
                                        @endforeach
                                    </select>
                                    <span style="position: absolute; right: 15px; top: 15px; pointer-events: none; color: var(--text-secondary); font-size: 0.8rem;">▼</span>
                                </div>

                                <div>
                                    <input type="text" class="custom-input" wire:model="qaTitle" placeholder="Vulnerability Title (e.g. Broken API Link)">
                                    @error('qaTitle') <span class="error-txt" style="text-align: left; margin-top: 5px;">> {{ $message }}</span> @enderror
                                </div>

                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <textarea class="custom-textarea" style="flex: 1;" wire:model="qaDescription" placeholder="Define the parameters of the failure or requested optimization..."></textarea>
                                    @error('qaDescription') <span class="error-txt" style="text-align: left; margin-top: 5px;">> {{ $message }}</span> @enderror
                                </div>

                                <button type="submit" class="btn-qa" style="position: relative;">
                                    <span wire:loading.remove wire:target="submitQA">TRANSMIT TO BACKLOG</span>
                                    <div wire:loading wire:target="submitQA" class="loader-ring" style="display: block;"></div>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="telemetry-quote-bar">
                <span class="quote-content" id="quoteText">> PROVISION IS NOT MYSTICAL. IT IS STRUCTURED.</span>
            </div>

            <div class="redline-wrapper">
                <div class="btn-redline armed" onclick="alert('> ERR: REDLINE PROTOCOL REQUIRES LEVEL 5 AUTHORIZATION.')">
                    ⚠️ INITIATE EMERGENCY REDLINE
                </div>
            </div>
            @endif
        </div>

    </main>

    @push('scripts')
    <script>
        // --- GEOLOCATION API --- 
        async function fetchLocation() {
            const geoText = document.getElementById('geoWelcome');
            if(!geoText) return;
            try {
                const response = await fetch('https://ipapi.co/json/');
                const data = await response.json();
                if(data.city && data.country_name) {
                    typeWriter(geoText, `> CONNECTION ESTABLISHED.\n> ROUTING FROM: ${data.city.toUpperCase()}, ${data.country_name.toUpperCase()}.`, 40);
                } else {
                    typeWriter(geoText, `> SECURE CONNECTION ESTABLISHED.\n> WAITING FOR CREDENTIALS...`, 40);
                }
            } catch (error) {
                typeWriter(geoText, `> SECURE CONNECTION ESTABLISHED.\n> WAITING FOR CREDENTIALS...`, 40);
            }
        }

        function typeWriter(element, text, speed) {
            element.innerHTML = '';
            let i = 0;
            function type() {
                if (i < text.length) {
                    if(text.charAt(i) === '\n') {
                        element.innerHTML += '<br>';
                    } else {
                        element.innerHTML += text.charAt(i);
                    }
                    i++;
                    setTimeout(type, speed);
                }
            }
            type();
        }

        // --- ELITE QUOTE ROTATOR --- 
        const axioms = [
            "Provision is not mystical. It is structured.",
            "A policy written on paper is a suggestion. A policy written in PostgreSQL is a law.",
            "We do not digitize confusion; we eliminate it.",
            "Chaos is a tax on un-engineered growth.",
            "Top-line revenue without backend architecture is corporate suicide.",
            "Compliance is not a department. It is an architecture."
        ];

        let quoteIndex = 0;
        function startQuoteRotator() {
            const quoteEl = document.getElementById('quoteText');
            if(!quoteEl) return;
            setInterval(() => {
                quoteEl.style.opacity = 0;
                setTimeout(() => {
                    quoteIndex = (quoteIndex + 1) % axioms.length;
                    quoteEl.innerText = `> ${axioms[quoteIndex].toUpperCase()}`;
                    quoteEl.style.opacity = 1;
                }, 500);
            }, 10000);
        }

        // --- BACKGROUND RADAR CANVAS --- 
        const canvas = document.getElementById('techCanvas');
        let ctx, particlesArray;
        let scanLineY = 0; 
        let animationId;
        let isDashboardState = false;

        document.addEventListener('livewire:initialized', () => {
            Livewire.on('portal-authenticated', () => {
                isDashboardState = true;
                startQuoteRotator();
            });
        });

        function initCanvas() {
            if(!canvas) return;
            ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            particlesArray = [];
            
            class NodeParticle {
                constructor() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.vx = (Math.random() - 0.5) * 0.5;
                    this.vy = (Math.random() - 0.5) * 0.5;
                    this.size = Math.random() * 2;
                }
                update() {
                    this.x += this.vx;
                    this.y += this.vy;
                    if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
                    if (this.y < 0 || this.y > canvas.height) this.vy *= -1;
                }
                draw() {
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(0, 229, 255, 0.5)';
                    ctx.fill();
                }
            }

            const numParticles = Math.floor((canvas.width * canvas.height) / 15000);
            for (let i = 0; i < numParticles; i++) {
                particlesArray.push(new NodeParticle());
            }
            
            if(animationId) cancelAnimationFrame(animationId);
            animateCanvas();
        }

        function animateCanvas() {
            if(!ctx) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (!isDashboardState) {
                // Pre-Login: Floating Network Nodes
                particlesArray.forEach(p => { p.update(); p.draw(); });
                for (let i = 0; i < particlesArray.length; i++) {
                    for (let j = i; j < particlesArray.length; j++) {
                        const dx = particlesArray[i].x - particlesArray[j].x;
                        const dy = particlesArray[i].y - particlesArray[j].y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        if (distance < 100) {
                            ctx.beginPath();
                            ctx.strokeStyle = `rgba(0, 229, 255, ${0.2 - distance/500})`;
                            ctx.lineWidth = 0.5;
                            ctx.moveTo(particlesArray[i].x, particlesArray[i].y);
                            ctx.lineTo(particlesArray[j].x, particlesArray[j].y);
                            ctx.stroke();
                        }
                    }
                }
            } else {
                // Post-Login: Architectural Blueprint Grid
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.03)';
                ctx.lineWidth = 1;
                const gridSize = 50;
                for(let x = 0; x < canvas.width; x += gridSize) {
                    ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, canvas.height); ctx.stroke();
                }
                for(let y = 0; y < canvas.height; y += gridSize) {
                    ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(canvas.width, y); ctx.stroke();
                }

                // Vertical Radar Sweep
                scanLineY += 2;
                if(scanLineY > canvas.height) scanLineY = 0;
                
                ctx.fillStyle = 'rgba(0, 229, 255, 0.05)';
                ctx.fillRect(0, scanLineY - 50, canvas.width, 50);
                
                ctx.beginPath();
                ctx.strokeStyle = 'rgba(0, 229, 255, 0.5)';
                ctx.lineWidth = 1;
                ctx.moveTo(0, scanLineY);
                ctx.lineTo(canvas.width, scanLineY);
                ctx.stroke();
            }

            animationId = requestAnimationFrame(animateCanvas);
        }

        document.addEventListener('livewire:navigated', () => {
            fetchLocation();
            initCanvas();
        });
        
        window.addEventListener('resize', () => {
            if(canvas) {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
                initCanvas();
            }
        });
    </script>
    @endpush
</div>