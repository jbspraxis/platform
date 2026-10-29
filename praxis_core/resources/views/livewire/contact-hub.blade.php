<div x-data="{ isDiagnostic: @entangle('isDiagnosticMode') }" id="intake-hub">
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - INTAKE HUB (CONTACT) CSS ARCHITECTURE 
           ========================================================================== */

        /* --- VIEWPORT-LOCKED IMMERSIVE BACKGROUND --- */
        .immersive-bg, .immersive-overlay, .scanline-grid {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none; z-index: -10;
        }

        .immersive-bg {
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
            filter: grayscale(100%) brightness(0.3); 
            transition: all 0.5s ease;
        }
        html.light-mode .immersive-bg { filter: grayscale(100%) brightness(1) opacity(0.3); }

        .immersive-overlay {
            background: radial-gradient(circle at center, rgba(10,17,40,0.7) 0%, var(--midnight-navy) 100%);
            transition: background 0.5s ease;
        }
        html.light-mode .immersive-overlay { background: radial-gradient(circle at center, rgba(255,255,255,0.7) 0%, var(--ghost-white) 100%); }

        .scanline-grid {
            background-image: linear-gradient(rgba(0, 229, 255, 0.03) 1px, transparent 1px), 
                              linear-gradient(90deg, rgba(0, 229, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }
        html.light-mode .scanline-grid {
            background-image: linear-gradient(rgba(10, 17, 40, 0.05) 1px, transparent 1px), 
                              linear-gradient(90deg, rgba(10, 17, 40, 0.05) 1px, transparent 1px);
        }

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes periodicScan { 0% { top: -5%; opacity: 0; } 2% { opacity: 0.5; } 18% { top: 105%; opacity: 0.5; } 20%, 100% { opacity: 0; top: 105%; } }

        .pulse-dot { width: 6px; height: 6px; background-color: var(--luminous-teal); border-radius: 50%; box-shadow: 0 0 10px var(--luminous-teal); animation: pulse 2s infinite; display: inline-block; }
        html.light-mode .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.4); }

        .terminal-action-btn { display: inline-block; width: 100%; padding: 16px 24px; background-color: var(--luminous-teal) !important; color: var(--midnight-navy) !important; font-weight: bold; font-family: 'Inter', sans-serif; font-size: 0.9rem; cursor: pointer; border: none; border-radius: 4px; transition: all 0.3s ease; text-decoration: none; text-transform: uppercase; text-align: center; }
        .terminal-action-btn:hover { background-color: var(--stark-white) !important; box-shadow: 0 0 20px rgba(255, 255, 255, 0.4); transform: translateY(-2px); }

        /* --- THE HUB WRAPPER --- */
        .hub-wrapper {
            flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 120px 24px 100px 24px; position: relative; z-index: 10;
            min-height: 100vh;
        }

        /* --- HIGH-END SEGMENTED CONTROL --- */
        .segment-control {
            display: inline-flex; position: relative; background: rgba(10, 17, 40, 0.8);
            padding: 6px; border-radius: 8px; border: 1px solid rgba(0, 229, 255, 0.2);
            margin-bottom: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            z-index: 20; backdrop-filter: blur(10px);
        }
        html.light-mode .segment-control { background: rgba(255,255,255,0.9); border-color: rgba(10,17,40,0.1); box-shadow: 0 10px 30px rgba(10,17,40,0.05); }

        .segment-btn {
            position: relative; z-index: 2; padding: 12px 30px; font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); letter-spacing: 1px;
            cursor: pointer; border: none; background: transparent; transition: color 0.4s ease;
            text-transform: uppercase;
        }
        .segment-btn.active { color: var(--midnight-navy); }
        html.light-mode .segment-btn.active { color: var(--stark-white); }

        .segment-pill {
            position: absolute; top: 6px; bottom: 6px; width: calc(50% - 6px);
            background: var(--accent-color); border-radius: 4px; z-index: 1;
            transition: transform 0.4s cubic-bezier(0.85, 0, 0.15, 1);
            box-shadow: 0 0 15px rgba(0, 229, 255, 0.3);
        }
        html.light-mode .segment-pill { background: var(--midnight-navy); box-shadow: 0 5px 15px rgba(10,17,40,0.2); }
        
        .segment-pill.pos-left { transform: translateX(0); }
        .segment-pill.pos-right { transform: translateX(100%); }

        /* --- THE INTAKE MATRIX (FORM) --- */
        .intake-matrix {
            background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(0, 229, 255, 0.2); border-top: 3px solid var(--accent-color);
            border-radius: 8px; padding: 60px; width: 100%; max-width: 850px;
            position: relative; z-index: 10; box-shadow: 0 40px 100px rgba(0,0,0,0.8), inset 0 0 30px rgba(0, 229, 255, 0.02);
            transition: all 0.5s ease; overflow: hidden;
        }
        html.light-mode .intake-matrix { 
            background: rgba(255, 255, 255, 0.95); border-color: rgba(10,17,40,0.1); 
            border-top-color: var(--midnight-navy); box-shadow: 0 40px 100px rgba(10,17,40,0.1); 
        }
        
        /* Scanning Form Laser */
        .intake-matrix::after { content: ""; position: absolute; top: -5%; left: 0; width: 100%; height: 3px; background: var(--luminous-teal); box-shadow: 0 0 15px var(--luminous-teal); z-index: 100; opacity: 0; pointer-events: none; animation: periodicScan 12s infinite linear; }
        html.light-mode .intake-matrix::after { background: #1E3A8A; box-shadow: 0 0 15px #1E3A8A; }

        .form-section-title {
            font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--luminous-teal);
            letter-spacing: 2px; margin-bottom: 10px; border-bottom: 1px solid rgba(0, 229, 255, 0.2); padding-bottom: 12px;
            text-transform: uppercase; margin-top: 40px; font-weight: bold;
        }
        html.light-mode .form-section-title { color: var(--midnight-navy); border-bottom-color: rgba(10,17,40,0.1); }
        .form-section-title:first-child { margin-top: 0; }

        .input-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        .input-group { display: flex; flex-direction: column; gap: 8px; position: relative; }
        
        .matrix-label { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: 600; color: var(--stark-white); letter-spacing: 0.5px; text-transform: uppercase; }
        html.light-mode .matrix-label { color: var(--midnight-navy); }
        
        .matrix-input, .matrix-select {
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: var(--stark-white); padding: 16px 20px; font-family: 'Inter', sans-serif;
            font-size: 0.95rem; border-radius: 4px; outline: none; transition: all 0.3s ease; appearance: none; -webkit-appearance: none;
        }
        html.light-mode .matrix-input, html.light-mode .matrix-select { background: rgba(0,0,0,0.02); color: var(--midnight-navy); border-color: rgba(10,17,40,0.15); }
        
        .matrix-input:focus, .matrix-select:focus { border-color: var(--accent-color); box-shadow: inset 0 0 10px rgba(0, 229, 255, 0.1); }
        html.light-mode .matrix-input:focus, html.light-mode .matrix-select:focus { border-color: var(--midnight-navy); box-shadow: inset 0 0 10px rgba(10, 17, 40, 0.05); }
        
        .matrix-select { cursor: pointer; padding-right: 40px; }
        .matrix-select option { background-color: #0A1128; color: var(--stark-white); padding: 10px; }
        html.light-mode .matrix-select option { background-color: var(--stark-white); color: var(--midnight-navy); }
        
        .select-icon { position: absolute; right: 16px; top: 38px; width: 16px; height: 16px; stroke: var(--text-secondary); pointer-events: none; transition: stroke 0.3s ease; }
        .matrix-select:focus + .select-icon { stroke: var(--accent-color); }
        html.light-mode .matrix-select:focus + .select-icon { stroke: var(--midnight-navy); }

        .phone-group { display: flex; gap: 10px; }
        .country-code-wrapper { position: relative; width: 130px; flex-shrink: 0; }
        .country-code-wrapper .matrix-select { padding-right: 30px; }

        textarea.matrix-input { resize: vertical; min-height: 140px; font-family: 'Inter', sans-serif; line-height: 1.6; }
        .error-text { color: var(--terminal-red); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-top: -5px; }

        /* THE SYSTEM LOG IN DRAWER */
        .system-log-console { margin-top: 25px; padding: 15px; background: rgba(0,0,0,0.4); border-left: 2px solid var(--accent-color); font-family: 'Roboto Mono', monospace; border-radius: 0 4px 4px 0; }
        html.light-mode .system-log-console { background: rgba(10, 17, 40, 0.05); border-left-color: var(--midnight-navy); }
        .log-text { font-size: 0.7rem; color: var(--accent-color); opacity: 0.8; transition: opacity 0.2s ease; }
        html.light-mode .log-text { color: var(--midnight-navy); font-weight: bold; }

        .loader-ring { width: 20px; height: 20px; border: 2px solid rgba(10,17,40,0.2); border-top-color: var(--midnight-navy); border-radius: 50%; animation: spin 0.6s linear infinite; display: none; margin: 0 auto; }
        html.light-mode .loader-ring { border-color: rgba(255,255,255,0.3); border-top-color: var(--stark-white); }

        /* Success State */
        .success-panel { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center; width: 100%; min-height: 400px; }
        .check-icon { width: 80px; height: 80px; background: var(--accent-color); color: var(--midnight-navy); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; margin: 0 auto 30px; box-shadow: 0 0 30px rgba(0, 229, 255, 0.3); }

        @media (max-width: 850px) {
            .hub-wrapper { padding: 100px 20px 60px 20px; }
            .intake-matrix { padding: 30px 20px; }
            .input-grid-2 { grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
            .segment-btn { padding: 12px 15px; font-size: 0.75rem; }
            .phone-group { flex-direction: row; }
        }
    </style>
    @endpush

    <div class="immersive-bg"></div>
    <div class="immersive-overlay"></div>
    <div class="scanline-grid"></div>

    <main class="hub-wrapper">
        
        <div class="segment-control">
            <div class="segment-pill" :class="isDiagnostic ? 'pos-right' : 'pos-left'"></div>
            <button type="button" class="segment-btn" :class="!isDiagnostic ? 'active' : ''" wire:click="toggleMode">QUICK INQUIRY</button>
            <button type="button" class="segment-btn" :class="isDiagnostic ? 'active' : ''" wire:click="toggleMode">FULL DIAGNOSTIC</button>
        </div>

        <div class="intake-matrix" id="matrix-form">
            
            @if($isSuccess)
                <div class="success-panel" style="animation: fadeIn 0.5s ease forwards;">
                    <div class="check-icon">✓</div>
                    <h2 style="margin-bottom: 15px; font-size: 2rem; color: var(--stark-white);">Intelligence Received.</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 35px; font-size: 1.05rem; line-height: 1.7; max-width: 500px;">
                        > Your diagnostic data has been securely transmitted to the Strike Team.<br><br>
                        Please check your inbox immediately for your secure calendar link to schedule your executive consultation.
                    </p>
                    <button class="terminal-action-btn" onclick="window.location.href='/schedule'" wire:navigate>[ PROCEED TO CALENDAR ]</button>
                </div>
            @else
                
                <form wire:submit.prevent="transmitIntelligence" onsubmit="document.getElementById('matrix-form').scrollIntoView({ behavior: 'smooth', block: 'start' })">
                    
                    <div x-show="isDiagnostic" 
                         x-transition:enter="transition ease-out duration-400" 
                         x-transition:enter-start="opacity-0 translate-y-4" 
                         x-transition:enter-end="opacity-100 translate-y-0" 
                         style="margin-bottom: 40px; display: none;">
                         
                        <div class="form-section-title">> STEP 1: IDENTIFY YOUR CURRENT ARCHITECTURE</div>
                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 25px; line-height: 1.6;">We engineer solutions for every stage of growth. Transmit your current operational reality.</p>
                        
                        <div class="input-grid-2">
                            <div class="input-group">
                                <label class="matrix-label">Enterprise Stage & Scale</label>
                                <select class="matrix-select" wire:model="stage" onfocus="updateContactLog('CALIBRATING_ENTERPRISE_SCALE')">
                                    <option value="" disabled selected>Select Stage...</option>
                                    <option>Pre-Revenue / Concept Stage</option>
                                    <option>Scaling SME</option>
                                    <option>Established Enterprise</option>
                                </select>
                                <svg class="select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                                @error('stage') <span class="error-text">> {{ $message }}</span> @enderror
                            </div>

                            <div class="input-group">
                                <label class="matrix-label">Primary Operational Bottleneck</label>
                                <select class="matrix-select" wire:model.live="bottleneck" onfocus="updateContactLog('ANALYZING_SYSTEM_FRICTION')">
                                    <option value="" disabled selected>Select Bottleneck...</option>
                                    <option>Starting from scratch (No systems)</option>
                                    <option>Fragmented Data & Manual Workflows</option>
                                    <option>Corporate Governance & Leakage</option>
                                    <option>Outdated Legacy Software</option>
                                    <option>Strategy & Compliance (ISO)</option>
                                    <option value="other">Other...</option>
                                </select>
                                <svg class="select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                                @error('bottleneck') <span class="error-text">> {{ $message }}</span> @enderror
                            </div>
                        </div>

                        @if($bottleneck === 'other')
                            <div class="input-group" style="animation: fadeIn 0.3s ease; margin-bottom: 25px;">
                                <input type="text" class="matrix-input" wire:model="otherBottleneck" placeholder="> Specify your exact operational bottleneck..." onfocus="updateContactLog('LOGGING_CUSTOM_VULNERABILITY')">
                                @error('otherBottleneck') <span class="error-text">> {{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="input-grid-2">
                            <div class="input-group">
                                <label class="matrix-label">Current Technology Reality</label>
                                <select class="matrix-select" wire:model="techReality" onfocus="updateContactLog('MAPPING_TECH_STACK')">
                                    <option value="" disabled selected>Select Tech Reality...</option>
                                    <option>Entirely Manual (Paper, Excel)</option>
                                    <option>Disconnected SaaS Apps</option>
                                    <option>Custom / Legacy System (Failing)</option>
                                    <option>Ready to Build Bespoke ERP</option>
                                </select>
                                <svg class="select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                            </div>

                            <div class="input-group">
                                <label class="matrix-label">Governance & Compliance Status</label>
                                <select class="matrix-select" wire:model="govStatus" onfocus="updateContactLog('AUDITING_COMPLIANCE_PROTOCOLS')">
                                    <option value="" disabled selected>Select Status...</option>
                                    <option>Informal (No written SOPs)</option>
                                    <option>Paper-Based SOPs (Not enforced)</option>
                                    <option>Digitally Enforced Workflows</option>
                                    <option>Audit Mode (ISO/ESG Maintenance)</option>
                                </select>
                                <svg class="select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="form-section-title" x-text="isDiagnostic ? '> STEP 2: EXECUTIVE CREDENTIALS' : '> EXECUTIVE CREDENTIALS'"></div>
                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 25px; line-height: 1.6;">Provide your clearances to engage the Strike Team.</p>
                        
                        <div class="input-grid-2">
                            <div class="input-group">
                                <label class="matrix-label">FULL NAME</label>
                                <input type="text" class="matrix-input" wire:model="fullName" placeholder="John Doe" onfocus="updateContactLog('VERIFYING_IDENTITY')">
                                @error('fullName') <span class="error-text">> {{ $message }}</span> @enderror
                            </div>
                            <div class="input-group">
                                <label class="matrix-label">CORPORATE EMAIL</label>
                                <input type="email" class="matrix-input" wire:model="email" placeholder="executive@enterprise.com" onfocus="updateContactLog('ESTABLISHING_SECURE_COMMS')">
                                @error('email') <span class="error-text">> {{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="input-grid-2">
                            <div class="input-group">
                                <label class="matrix-label">EXECUTIVE TITLE</label>
                                <input type="text" class="matrix-input" wire:model="title" placeholder="e.g., Founder / CEO" onfocus="updateContactLog('LOGGING_CLEARANCE_LEVEL')">
                            </div>
                            <div class="input-group">
                                <label class="matrix-label">TELEPHONE</label>
                                <div class="phone-group">
                                    <div class="country-code-wrapper">
                                        <select class="matrix-select" wire:model="countryCode" onfocus="updateContactLog('CALIBRATING_GEO_LOCATION')">
                                            <option value="+234">🇳🇬 +234</option>
                                            <option value="+237">🇨🇲 +237</option>
                                            <option value="+1">🇺🇸 +1</option>
                                            <option value="+44">🇬🇧 +44</option>
                                            <option value="+27">🇿🇦 +27</option>
                                            <option value="+971">🇦🇪 +971</option>
                                        </select>
                                        <svg class="select-icon" style="right: 12px; top: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                                    </div>
                                    <input type="tel" class="matrix-input" wire:model="phone" placeholder="Number" style="flex: 1;" onfocus="updateContactLog('ROUTING_COMM_CHANNEL')">
                                </div>
                            </div>
                        </div>

                        <div class="input-grid-2">
                            <div class="input-group">
                                <label class="matrix-label">ENTERPRISE NAME</label>
                                <input type="text" class="matrix-input" wire:model="companyName" placeholder="Enterprise Inc." onfocus="updateContactLog('SCANNING_ENTERPRISE_REGISTRY')">
                                @error('companyName') <span class="error-text">> {{ $message }}</span> @enderror
                            </div>
                            <div class="input-group">
                                <label class="matrix-label">CURRENT CITY</label>
                                <input type="text" class="matrix-input" wire:model="city" placeholder="Lagos" onfocus="updateContactLog('MAPPING_OPERATIONAL_BASE')">
                            </div>
                        </div>

                        <div class="input-grid-2" x-show="isDiagnostic" x-transition:enter="transition ease-out duration-400">
                            <div class="input-group">
                                <label class="matrix-label">PREFERRED LANGUAGE</label>
                                <select class="matrix-select" wire:model="language" onfocus="updateContactLog('SETTING_TRANSLATION_MATRIX')">
                                    <option>English (EN)</option>
                                    <option>Français (FR)</option>
                                    <option>Español (ES)</option>
                                </select>
                                <svg class="select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                            </div>
                            <div class="input-group">
                                <label class="matrix-label">ESTIMATED DEPLOYMENT BUDGET</label>
                                <select class="matrix-select" wire:model="budget" onfocus="updateContactLog('CALCULATING_CAPITAL_ALLOCATION')">
                                    <option value="" disabled selected>Select Capital Allocation...</option>
                                    <option>Under $5,000</option>
                                    <option>$5,000 - $15,000</option>
                                    <option>$15,000 - $50,000</option>
                                    <option>$50,000+</option>
                                    <option>Undetermined / Need Audit First</option>
                                </select>
                                <svg class="select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                            </div>
                        </div>

                        <div class="input-group" style="margin-bottom: 35px;">
                            <label class="matrix-label">DEFINE THE EXACT OUTCOME YOU REQUIRE</label>
                            <textarea class="matrix-input" wire:model="message" placeholder="> Be specific. E.g., 'I need to build a bespoke logistics app to track our fleet,' or 'We are losing capital in procurement and need an IS Audit.'" onfocus="updateContactLog('RECORDING_ARCHITECTURAL_REQUIREMENTS')"></textarea>
                            @error('message') <span class="error-text">> {{ $message }}</span> @enderror
                        </div>

                        <button type="submit" class="terminal-action-btn" onclick="submitContactFX(event)">
                            <span id="contactSubmitText" wire:loading.remove wire:target="transmitIntelligence">AUTHORIZE TRANSMISSION</span>
                            <div wire:loading wire:target="transmitIntelligence" class="loader-ring" style="display: block;"></div>
                        </button>

                        <div class="system-log-console">
                            <div id="contactSystemLog" class="log-text">
                                > AWAITING ENTERPRISE DATA...
                            </div>
                        </div>

                    </div>
                </form>

            @endif
        </div>
    </main>

    <script>
        // The Terminal Gamification Engine for the Contact Hub
        window.updateContactLog = function(message) {
            const log = document.getElementById('contactSystemLog');
            if(!log) return;
            log.style.opacity = '0.4';
            setTimeout(() => {
                log.innerText = `> ${message}... OK.`;
                log.style.opacity = '1';
            }, 100);
        };

        window.submitContactFX = function(e) {
            const btn = e.currentTarget;
            const btnText = document.getElementById('contactSubmitText');
            if(btnText) btnText.innerText = 'ENCRYPTING TRANSMISSION...';
            updateContactLog('ROUTING_TO_STRIKE_TEAM_COMMAND');
        };
    </script>
</div>