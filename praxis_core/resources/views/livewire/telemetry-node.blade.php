<div x-data="{ severityLevel: @entangle('severity') }" id="telemetry-node">
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - KAIZEN TELEMETRY NODE CSS ARCHITECTURE
           ========================================================================== */

        /* --- VIEWPORT-LOCKED IMMERSIVE BACKGROUND --- */
        .immersive-bg, .immersive-overlay, .scanline-grid {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none; z-index: -10;
        }

        .immersive-bg {
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
            filter: grayscale(100%) brightness(0.2); 
            transition: all 0.5s ease;
        }
        html.light-mode .immersive-bg { filter: grayscale(90%) brightness(0.9) opacity(0.4); }

        .immersive-overlay {
            background: radial-gradient(circle at center, rgba(10,17,40,0.6) 0%, var(--midnight-navy) 100%);
            transition: background 0.5s ease;
        }
        html.light-mode .immersive-overlay { background: radial-gradient(circle at center, rgba(255,255,255,0.7) 0%, var(--ghost-white) 100%); }

        .scanline-grid {
            background-image: linear-gradient(rgba(0, 229, 255, 0.03) 1px, transparent 1px), 
                              linear-gradient(90deg, rgba(0, 229, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        html.light-mode .scanline-grid {
            background-image: linear-gradient(rgba(10, 17, 40, 0.05) 1px, transparent 1px), 
                              linear-gradient(90deg, rgba(10, 17, 40, 0.05) 1px, transparent 1px);
        }

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes periodicScan { 0% { top: -5%; opacity: 0; } 2% { opacity: 0.5; } 18% { top: 105%; opacity: 0.5; } 20%, 100% { opacity: 0; top: 105%; } }

        .pulse-dot { width: 6px; height: 6px; background-color: var(--accent-color); border-radius: 50%; box-shadow: 0 0 10px var(--accent-color); animation: pulse 2s infinite; display: inline-block; }
        html.light-mode .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.4); }

        .terminal-action-btn { display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 18px 24px; background-color: var(--luminous-teal) !important; color: var(--midnight-navy) !important; font-weight: bold; font-family: 'Inter', sans-serif; font-size: 0.95rem; cursor: pointer; border: none; border-radius: 4px; transition: all 0.3s ease; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; }
        .terminal-action-btn:hover { background-color: var(--stark-white) !important; box-shadow: 0 0 30px rgba(0, 229, 255, 0.5); transform: translateY(-2px); }

        /* --- THE TELEMETRY WRAPPER --- */
        .telemetry-wrapper {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 120px 24px; position: relative; z-index: 10; min-height: 100vh;
        }

        /* --- THE MATRIX PANEL --- */
        .telemetry-matrix {
            width: 100%; max-width: 900px; position: relative;
            background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(0, 229, 255, 0.2); border-top: 3px solid var(--accent-color);
            border-radius: 8px; box-shadow: 0 40px 100px rgba(0,0,0,0.8), inset 0 0 30px rgba(0, 229, 255, 0.02);
            overflow: hidden; padding: 60px 80px;
            animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        html.light-mode .telemetry-matrix {
            background: rgba(255, 255, 255, 0.95); border-color: rgba(10, 17, 40, 0.1);
            border-top-color: var(--midnight-navy); box-shadow: 0 40px 100px rgba(10,17,40,0.1);
        }

        /* Scanning Laser */
        .telemetry-matrix::after { content: ""; position: absolute; top: -5%; left: 0; width: 100%; height: 3px; background: var(--luminous-teal); box-shadow: 0 0 15px var(--luminous-teal); z-index: 100; opacity: 0; pointer-events: none; animation: periodicScan 10s infinite linear; }
        html.light-mode .telemetry-matrix::after { background: var(--midnight-navy); box-shadow: 0 0 15px var(--midnight-navy); }

        /* Header / Preamble */
        .node-header { margin-bottom: 50px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 40px; }
        html.light-mode .node-header { border-bottom-color: rgba(10,17,40,0.1); }
        
        .node-title { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 900; color: var(--stark-white); letter-spacing: -1px; margin-bottom: 15px; text-transform: uppercase; }
        html.light-mode .node-title { color: var(--midnight-navy); }
        
        .node-desc { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.8; max-width: 700px; margin: 0 auto; }
        .node-desc strong { color: var(--accent-color); font-weight: bold; }
        html.light-mode .node-desc strong { color: var(--midnight-navy); }

        /* Form UI */
        .form-section-title { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--luminous-teal); letter-spacing: 2px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; font-weight: bold; }
        html.light-mode .form-section-title { color: var(--midnight-navy); }

        .input-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
        .input-group { display: flex; flex-direction: column; gap: 8px; position: relative; }
        
        .matrix-label { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: 600; color: var(--stark-white); letter-spacing: 0.5px; text-transform: uppercase; }
        html.light-mode .matrix-label { color: var(--midnight-navy); }
        
        .matrix-input, .matrix-select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--stark-white); padding: 16px 20px; font-family: 'Inter', sans-serif; font-size: 0.95rem; border-radius: 4px; outline: none; transition: all 0.3s ease; appearance: none; }
        html.light-mode .matrix-input, html.light-mode .matrix-select { background: rgba(0,0,0,0.02); color: var(--midnight-navy); border-color: rgba(10,17,40,0.15); }
        .matrix-input:focus, .matrix-select:focus { border-color: var(--accent-color); box-shadow: inset 0 0 10px rgba(0, 229, 255, 0.1); }
        html.light-mode .matrix-input:focus, html.light-mode .matrix-select:focus { border-color: var(--midnight-navy); box-shadow: inset 0 0 10px rgba(10,17,40,0.05); }
        
        .matrix-select { cursor: pointer; padding-right: 40px; }
        .matrix-select option { background-color: #0A1128; color: var(--stark-white); padding: 10px; }
        html.light-mode .matrix-select option { background-color: var(--stark-white); color: var(--midnight-navy); }
        
        .select-icon { position: absolute; right: 16px; top: 38px; width: 16px; height: 16px; stroke: var(--text-secondary); pointer-events: none; }

        textarea.matrix-input { resize: vertical; min-height: 160px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; line-height: 1.6; }
        
        .error-txt { color: var(--terminal-red); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-top: -5px; }

        /* --- THE SEVERITY INDEX CONTROL --- */
        .severity-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        
        .severity-btn { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); padding: 20px 15px; border-radius: 4px; text-align: center; cursor: pointer; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; gap: 8px; align-items: center; }
        html.light-mode .severity-btn { background: rgba(0,0,0,0.02); border-color: rgba(10,17,40,0.1); }
        
        .severity-btn:hover { border-color: var(--text-secondary); background: rgba(255,255,255,0.02); }
        
        .sev-level { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; letter-spacing: 1px; color: var(--text-secondary); }
        .sev-title { font-size: 0.85rem; font-weight: 600; color: var(--stark-white); }
        html.light-mode .sev-title { color: var(--midnight-navy); }

        /* Severity Active States */
        /* Level 1: Cosmetic (Yellow) */
        .severity-btn.active-1 { border-color: #FFBD2E; background: rgba(255, 189, 46, 0.05); box-shadow: 0 5px 20px rgba(255, 189, 46, 0.15); }
        .severity-btn.active-1 .sev-level, .severity-btn.active-1 .sev-title { color: #FFBD2E; }
        
        /* Level 2: Friction (Orange) */
        .severity-btn.active-2 { border-color: #FF8A00; background: rgba(255, 138, 0, 0.05); box-shadow: 0 5px 20px rgba(255, 138, 0, 0.15); }
        .severity-btn.active-2 .sev-level, .severity-btn.active-2 .sev-title { color: #FF8A00; }
        
        /* Level 3: Systemic (Red) */
        .severity-btn.active-3 { border-color: #FF3366; background: rgba(255, 51, 102, 0.05); box-shadow: 0 5px 20px rgba(255, 51, 102, 0.15); }
        .severity-btn.active-3 .sev-level, .severity-btn.active-3 .sev-title { color: #FF3366; }

        /* THE SYSTEM LOG */
        .system-log-console { margin-top: 25px; padding: 15px 20px; background: rgba(0,0,0,0.5); border-left: 2px solid var(--accent-color); font-family: 'Roboto Mono', monospace; border-radius: 0 4px 4px 0; }
        html.light-mode .system-log-console { background: rgba(10, 17, 40, 0.05); border-left-color: var(--midnight-navy); }
        .log-text { font-size: 0.75rem; color: var(--accent-color); opacity: 0.8; transition: opacity 0.2s ease; letter-spacing: 0.5px; }
        html.light-mode .log-text { color: var(--midnight-navy); font-weight: bold; }

        .loader-ring { width: 20px; height: 20px; border: 2px solid rgba(10,17,40,0.2); border-top-color: var(--midnight-navy); border-radius: 50%; animation: spin 0.6s linear infinite; display: none; margin: 0 auto; }

        /* --- SUCCESS STATE --- */
        .success-panel { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 40px; text-align: center; width: 100%; min-height: 500px; }
        .check-icon { font-size: 4rem; color: var(--accent-color); margin-bottom: 25px; line-height: 1; text-shadow: 0 0 30px rgba(0, 229, 255, 0.4); }
        
        .terminal-readout { background: #050914; border: 1px solid var(--border-color); padding: 30px; border-radius: 4px; text-align: left; width: 100%; max-width: 600px; margin-bottom: 40px; font-family: 'Roboto Mono', monospace; box-shadow: inset 0 0 20px rgba(0, 229, 255, 0.05); }
        html.light-mode .terminal-readout { background: rgba(248, 250, 252, 1); border-color: rgba(10,17,40,0.1); box-shadow: none; }
        .readout-line { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 10px; line-height: 1.6; }
        .readout-line strong { color: var(--stark-white); display: inline-block; width: 120px; }
        html.light-mode .readout-line strong { color: var(--midnight-navy); }
        .readout-highlight { color: var(--accent-color); }
        html.light-mode .readout-highlight { color: #1E3A8A; font-weight: bold; }

        @media (max-width: 850px) {
            .telemetry-wrapper { padding: 40px 15px; }
            .telemetry-matrix { padding: 40px 25px; }
            .node-title { font-size: 1.8rem; }
            .input-grid-2 { grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
            .severity-grid { grid-template-columns: 1fr; gap: 10px; }
            .severity-btn { flex-direction: row; justify-content: flex-start; padding: 15px 20px; gap: 15px; }
            .success-panel { padding: 40px 10px; min-height: 400px; }
            .terminal-readout { padding: 20px; }
            .readout-line strong { width: 100%; margin-bottom: 5px; }
        }
    </style>
    @endpush

    <div class="immersive-bg"></div>
    <div class="immersive-overlay"></div>
    <div class="scanline-grid"></div>

    <div class="telemetry-wrapper">
        <div class="telemetry-matrix" id="telemetry-form">
            
            @if($isSuccess)
                <div class="success-panel" style="animation: fadeIn 0.6s ease forwards;">
                    <div class="check-icon">✓</div>
                    <h2 class="mono-font" style="font-size: 2rem; margin-bottom: 30px; color: var(--stark-white); text-transform: uppercase;">[ TELEMETRY RECEIVED ]</h2>
                    
                    <div class="terminal-readout">
                        <div class="readout-line"><strong>STATUS:</strong> <span class="readout-highlight">KAIZEN LOOP INITIATED</span></div>
                        <div class="readout-line"><strong>ACTION:</strong> Data successfully routed to the PostgreSQL Vault.</div>
                        <div class="readout-line"><strong>TARGET:</strong> JBS Praxis Engineering Triad.</div>
                        <div class="readout-line" style="margin-top: 20px; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 20px; font-family: 'Inter', sans-serif; font-size: 0.95rem;">
                            Your input directly accelerates our systemic evolution. We do not ignore friction; we hardcode the solution.
                        </div>
                    </div>

                    <button class="terminal-action-btn" style="max-width: 300px;" onclick="window.location.href='/'" wire:navigate>RETURN TO COMMAND</button>
                </div>
            @else
                
                <div class="node-header">
                    <div class="terminal-teal-text mono-font" style="margin-bottom: 20px;">
                        <span class="pulse-dot"></span> CONTINUOUS OPTIMIZATION PROTOCOL
                    </div>
                    <h1 class="node-title">KAIZEN TELEMETRY NODE</h1>
                    <p class="node-desc">
                        At JBS Praxis, excellence is deterministic. We rely on raw operational telemetry to continuously refine our digital architecture. If you experienced UX latency, content ambiguity, or navigational friction within this nexus, log the anomaly below. <strong>We do not ignore friction; we hardcode the solution.</strong>
                    </p>
                </div>

                <form wire:submit.prevent="transmitTelemetry" onsubmit="document.getElementById('telemetry-form').scrollIntoView({ behavior: 'smooth', block: 'start' })">
                    
                    <div class="form-section-title">
                        <span class="mono-font" style="opacity: 0.5;">01 /</span> NODE ORIGIN (OPTIONAL)
                    </div>
                    <div class="input-grid-2">
                        <div class="input-group">
                            <label class="matrix-label">IDENTIFICATION / DESIGNATION</label>
                            <input type="text" class="matrix-input" wire:model="identity" placeholder="Anonymous or Corporate Title" onfocus="updateKaizenLog('LOGGING_NODE_ORIGIN')">
                        </div>
                        <div class="input-group">
                            <label class="matrix-label">SECURE COMM CHANNEL (EMAIL)</label>
                            <input type="email" class="matrix-input" wire:model="email" placeholder="Only if response is required" onfocus="updateKaizenLog('ESTABLISHING_OPTIONAL_COMMS')">
                            @error('email') <span class="error-txt">> {{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-section-title" style="margin-top: 40px;">
                        <span class="mono-font" style="opacity: 0.5;">02 /</span> ARCHITECTURAL PARAMETERS
                    </div>
                    
                    <div class="input-group" style="margin-bottom: 30px;">
                        <label class="matrix-label">FRICTION CATEGORY *</label>
                        <div style="position: relative;">
                            <select class="matrix-select" wire:model="category" onfocus="updateKaizenLog('CATEGORIZING_ANOMALY_TYPE')" required>
                                <option value="" disabled selected>Select Optimization Vector...</option>
                                <option value="UI/UX Latency">UI/UX Latency (Design / Speed / Visual Bug)</option>
                                <option value="Architectural Bottleneck">Architectural Bottleneck (Navigation / Broken Links)</option>
                                <option value="Content Ambiguity">Content Ambiguity (Unclear Copy / Methodology)</option>
                                <option value="Optimization Hypothesis">Optimization Hypothesis (Feature Suggestion / Idea)</option>
                            </select>
                            <svg class="select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                        </div>
                        @error('category') <span class="error-txt">> {{ $message }}</span> @enderror
                    </div>

                    <label class="matrix-label" style="margin-bottom: 10px;">SEVERITY INDEX *</label>
                    <div class="severity-grid">
                        <div class="severity-btn {{ $severity == 1 ? 'active-1' : '' }}" 
                             @click="severityLevel = 1; $wire.set('severity', 1); updateKaizenLog('SEVERITY_L1_REGISTERED')" data-level="1">
                            <span class="sev-level">LEVEL 1</span>
                            <span class="sev-title">Cosmetic Variance</span>
                        </div>
                        <div class="severity-btn {{ $severity == 2 ? 'active-2' : '' }}" 
                             @click="severityLevel = 2; $wire.set('severity', 2); updateKaizenLog('SEVERITY_L2_REGISTERED')" data-level="2">
                            <span class="sev-level">LEVEL 2</span>
                            <span class="sev-title">Operational Friction</span>
                        </div>
                        <div class="severity-btn {{ $severity == 3 ? 'active-3' : '' }}" 
                             @click="severityLevel = 3; $wire.set('severity', 3); updateKaizenLog('SEVERITY_L3_REGISTERED')" data-level="3">
                            <span class="sev-level">LEVEL 3</span>
                            <span class="sev-title">Systemic Failure</span>
                        </div>
                    </div>
                    @error('severity') <span class="error-txt">> {{ $message }}</span> @enderror

                    <div class="input-group" style="margin-bottom: 35px;">
                        <label class="matrix-label">ANOMALY DESCRIPTION / HYPOTHESIS *</label>
                        <textarea class="matrix-input" wire:model="description" placeholder="> Detail the friction point or optimization sequence..." onfocus="updateKaizenLog('RECORDING_TELEMETRY_DATA')" required></textarea>
                        @error('description') <span class="error-txt">> {{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="terminal-action-btn" onclick="submitKaizenFX(event)">
                        <span id="kaizenSubmitText" wire:loading.remove wire:target="transmitTelemetry">INITIATE KAIZEN PROTOCOL</span>
                        <span wire:loading wire:target="transmitTelemetry">ENCRYPTING & TRANSMITTING...</span>
                    </button>

                    <div class="system-log-console">
                        <div id="kaizenSystemLog" class="log-text">
                            > AWAITING TELEMETRY INPUT...
                        </div>
                    </div>

                </form>
            @endif
        </div>
    </div>

    <script>
        // The Terminal Gamification Engine for the Telemetry Node
        window.updateKaizenLog = function(message) {
            const log = document.getElementById('kaizenSystemLog');
            if(!log) return;
            log.style.opacity = '0.4';
            setTimeout(() => {
                log.innerText = `> ${message}... OK.`;
                log.style.opacity = '1';
            }, 100);
        };

        window.submitKaizenFX = function(e) {
            // Livewire will handle the actual loading state text via wire:loading
            updateKaizenLog('TRANSMITTING_TO_ENGINEERING_TRIAD');
        };
    </script>
</div>