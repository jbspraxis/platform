<div>
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - SERVICES MASTER CSS ARCHITECTURE 
           ========================================================================== */

        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
        @keyframes fadeDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scrollMarquee        { 0% { transform: translateX(0%); }    100% { transform: translateX(-50%); } }
        @keyframes scrollMarqueeReverse { 0% { transform: translateX(-50%); }  100% { transform: translateX(0%); } }
        @keyframes spin        { 100% { transform: rotate(360deg); } }
        @keyframes spinReverse { 100% { transform: rotate(-360deg); } }

        .section-header { margin-bottom: 60px; text-align: center; }
        .relative-z { position: relative; z-index: 10; }
        .pulse-dot { width: 6px; height: 6px; background-color: var(--luminous-teal); border-radius: 50%; box-shadow: 0 0 10px var(--luminous-teal); animation: pulse 2s infinite; display: inline-block; }
        .terminal-teal-text { color: var(--luminous-teal); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 15px; }
        .terminal-teal-text strong { letter-spacing: 2px; font-weight: 800; }
        html.light-mode .terminal-teal-text, html.light-mode .pulse-dot { color: #2D6A4F; }
        html.light-mode .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45,106,79,0.4); }

        .btn-outline { background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color); text-decoration: none; padding: 12px 24px; display: inline-block; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; transition: all 0.3s ease; border-radius: 2px; cursor: pointer; }
        .btn-outline:hover { background: var(--accent-color); color: var(--midnight-navy); box-shadow: 0 0 20px rgba(0,229,255,0.3); }
        html.light-mode .btn-outline { border-color: var(--midnight-navy); color: var(--midnight-navy); }
        html.light-mode .btn-outline:hover { background-color: var(--midnight-navy); color: var(--stark-white); }

        .terminal-action-btn { display: inline-block; padding: 12px 24px; background-color: var(--luminous-teal) !important; color: var(--midnight-navy) !important; font-weight: bold; font-family: 'Inter', sans-serif; font-size: 0.8rem; cursor: pointer; border: none; border-radius: 2px; transition: background 0.3s ease; text-decoration: none; text-transform: uppercase; }
        .terminal-action-btn:hover { background-color: var(--stark-white) !important; box-shadow: 0 0 20px rgba(255,255,255,0.4); }

        .reveal { opacity: 0; transform: translateY(40px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .reveal.active { opacity: 1; transform: translateY(0); }

        /* --- HERO --- */
        .hero-section { position: relative; height: 100vh; display: flex; align-items: center; justify-content: center; padding-top: 80px; overflow: hidden; }
        .hero-bg, .scanline-overlay, .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .hero-bg { background-size: cover; background-position: center; z-index: -3; }
        .hero-overlay { background: linear-gradient(180deg, rgba(10,17,40,0.8) 0%, var(--midnight-navy) 100%); z-index: -2; }
        html.light-mode .hero-overlay { background: linear-gradient(180deg, rgba(255,255,255,0.9) 0%, var(--stark-white) 100%); }
        .scanline-overlay { background: linear-gradient(to bottom, transparent 50%, rgba(0,229,255,0.03) 51%); background-size: 100% 4px; z-index: -1; pointer-events: none; }
        .hero-content { position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; max-width: 900px; width: 100%; text-align: center; }
        .tech-badge { display: inline-flex; align-items: center; gap: 8px; font-size: 0.65rem; font-weight: 700; letter-spacing: 2px; margin-bottom: 15px; padding: 5px 12px; background: rgba(0,229,255,0.05); border: 1px solid rgba(0,229,255,0.2); border-radius: 20px; color: var(--luminous-teal); }
        html.light-mode .tech-badge { color: #1E3A8A; border-color: rgba(30,58,138,0.2); }
        .brand-title { font-size: clamp(3rem, 6vw, 5.5rem); font-weight: 900; letter-spacing: -2px; line-height: 1.1; margin-bottom: 5px; background: linear-gradient(to right, #FFFFFF, #8A9bb2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        html.light-mode .brand-title { background: linear-gradient(to right, var(--midnight-navy), #4A5568); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .brand-slogan { font-size: clamp(1rem, 2vw, 1.5rem); color: var(--luminous-teal); letter-spacing: 4px; margin-bottom: 25px; font-weight: 400; }
        html.light-mode .brand-slogan { color: var(--accent-color); }
        .hero-desc { font-size: clamp(0.95rem, 1.2vw, 1.1rem); max-width: 750px; margin-bottom: 40px; padding: 0 15px; color: var(--text-secondary); }

        /* --- FUNNEL --- */
        .funnel-section { padding: 60px 0; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); position: relative; z-index: 20; }
        html.light-mode .funnel-section { background: var(--ghost-white); border-color: rgba(10,17,40,0.05); }
        .funnel-container { max-width: 800px; margin: 0 auto; text-align: center; }
        .funnel-label { font-family: 'Roboto Mono', monospace; color: var(--accent-color); font-size: 0.9rem; margin-bottom: 20px; display: block; letter-spacing: 2px; }
        html.light-mode .funnel-label { color: var(--midnight-navy); }
        .custom-select-wrapper { position: relative; width: 100%; }
        .funnel-select { width: 100%; padding: 20px 25px; font-size: 1.05rem; font-family: 'Inter', sans-serif; font-weight: 600; color: var(--stark-white); background: rgba(10,17,40,0.9); border: 2px solid var(--border-color); border-radius: 4px; appearance: none; -webkit-appearance: none; cursor: pointer; box-shadow: 0 15px 30px rgba(0,0,0,0.3); transition: all 0.3s ease; }
        .funnel-select:focus { outline: none; border-color: var(--accent-color); box-shadow: 0 0 20px rgba(0,229,255,0.2); }
        .funnel-select option { background-color: #0A1128; color: var(--stark-white); padding: 10px; font-size: 1rem; }
        html.light-mode .funnel-select { background: #FFFFFF; color: var(--midnight-navy); border-color: rgba(10,17,40,0.2); box-shadow: 0 10px 20px rgba(10,17,40,0.05); }
        html.light-mode .funnel-select:focus { border-color: var(--midnight-navy); }
        html.light-mode .funnel-select option { background-color: var(--stark-white); color: var(--midnight-navy); }
        .select-arrow { position: absolute; right: 25px; top: 50%; transform: translateY(-50%); color: var(--accent-color); pointer-events: none; font-family: 'Roboto Mono', monospace; }
        html.light-mode .select-arrow { color: var(--midnight-navy); }
        .trace-line-container { position: absolute; left: 50%; transform: translateX(-50%); width: 2px; height: 0; background: var(--accent-color); top: 100%; z-index: 30; transition: height 0.8s cubic-bezier(0.85,0,0.15,1); box-shadow: 0 0 15px var(--accent-color); }
        html.light-mode .trace-line-container { background: var(--midnight-navy); box-shadow: 0 0 10px var(--midnight-navy); }

        /* --- ARCHITECTURE NODES --- */
        .architecture-node { padding: 120px 0; position: relative; border-bottom: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
        html.light-mode .architecture-node { border-bottom: 1px solid rgba(0,0,0,0.05); }
        .arch-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; position: relative; z-index: 2; }
        .architecture-node:nth-child(even) .arch-grid { direction: rtl; }
        .architecture-node:nth-child(even) .arch-text, .architecture-node:nth-child(even) .arch-visual { direction: ltr; }
        .arch-watermark { position: absolute; top: 50%; transform: translateY(-50%); font-size: 25rem; font-family: 'Inter', sans-serif; font-weight: 900; color: var(--stark-white); opacity: 0.02; z-index: 0; pointer-events: none; line-height: 0; }
        html.light-mode .arch-watermark { color: var(--midnight-navy); opacity: 0.03; }
        .architecture-node:nth-child(odd)  .arch-watermark { right: -5%; }
        .architecture-node:nth-child(even) .arch-watermark { left: -5%; }
        .arch-text h2 { font-size: clamp(2rem, 4vw, 2.5rem); margin-bottom: 15px; line-height: 1.2; letter-spacing: -1px; }
        .arch-text > p { font-size: 1.1rem; color: var(--text-secondary); margin-bottom: 40px; font-weight: 300; line-height: 1.7; }
        .arch-header-row { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .arch-num { font-family: 'Roboto Mono', monospace; font-size: 1.2rem; color: var(--luminous-teal); background: rgba(0,229,255,0.1); padding: 5px 15px; border: 1px solid var(--border-color); border-radius: 2px; }
        html.light-mode .arch-num { background: rgba(10,17,40,0.05); color: var(--midnight-navy); border-color: rgba(10,17,40,0.2); }
        .feature-list { display: flex; flex-direction: column; gap: 15px; margin-bottom: 40px; }
        .feature-item { background: rgba(255,255,255,0.02); border-left: 2px solid var(--accent-color); padding: 25px 20px; transition: all 0.3s ease; border-radius: 0 4px 4px 0; }
        html.light-mode .feature-item { background: rgba(0,0,0,0.02); border-left-color: var(--midnight-navy); }
        .feature-item:hover { background: rgba(0,229,255,0.05); transform: translateX(10px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        html.light-mode .feature-item:hover { background: rgba(10,17,40,0.05); box-shadow: 0 10px 20px rgba(10,17,40,0.05); }
        .feature-item h4 { font-size: 1.05rem; margin-bottom: 8px; color: var(--stark-white); font-family: 'Roboto Mono', monospace; }
        html.light-mode .feature-item h4 { color: var(--midnight-navy); }
        .feature-item p { font-size: 0.95rem; color: var(--text-secondary); margin: 0; line-height: 1.6; }
        .arch-visual { position: relative; height: 100%; min-height: 400px; background: #0A1128; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 30px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 30px 60px rgba(0,0,0,0.5), inset 0 0 40px rgba(0,229,255,0.05); overflow-x: auto; background-image: linear-gradient(rgba(18,16,16,0) 50%, rgba(0,0,0,0.2) 50%); background-size: 100% 4px; }
        html.light-mode .arch-visual { background: var(--ghost-white); border-color: rgba(10,17,40,0.1); box-shadow: 0 30px 60px rgba(10,17,40,0.1); background-image: none; }
        .faux-code { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #E2E8F0; line-height: 1.8; white-space: pre-wrap; word-break: break-word; }
        html.light-mode .faux-code { color: var(--midnight-navy); font-weight: 500; }
        .faux-highlight { color: var(--luminous-teal); text-shadow: 0 0 10px rgba(0,229,255,0.4); }
        html.light-mode .faux-highlight { color: #2D6A4F; text-shadow: none; }
        .faux-error { color: var(--terminal-red); }

        /* ══════════════════════════════════════════════════
           TECH STACK & FRAMEWORKS MARQUEE — DUAL ROWS
        ══════════════════════════════════════════════════ */
        .tech-showcase {
            padding: 0; background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            overflow: hidden; display: flex; flex-direction: column; gap: 0;
        }
        html.light-mode .tech-showcase { background: var(--ghost-white); border-color: rgba(10,17,40,0.05); }

        .marquee-row {
            display: flex; overflow: hidden; padding: 18px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            position: relative;
        }
        html.light-mode .marquee-row { border-color: rgba(10,17,40,0.04); }
        .marquee-row:last-child { border-bottom: none; }

        /* Fade edges */
        .marquee-row::before,
        .marquee-row::after {
            content: ''; position: absolute; top: 0; width: 120px; height: 100%; z-index: 2; pointer-events: none;
        }
        .marquee-row::before { left: 0;  background: linear-gradient(to right, var(--bg-secondary), transparent); }
        .marquee-row::after  { right: 0; background: linear-gradient(to left,  var(--bg-secondary), transparent); }
        html.light-mode .marquee-row::before { background: linear-gradient(to right, var(--ghost-white), transparent); }
        html.light-mode .marquee-row::after  { background: linear-gradient(to left,  var(--ghost-white), transparent); }

        .marquee-track {
            display: flex; gap: 60px; white-space: nowrap; flex-shrink: 0;
            animation: scrollMarquee 40s linear infinite;
        }
        .marquee-track.reverse { animation: scrollMarqueeReverse 50s linear infinite; }
        .marquee-track.slow    { animation-duration: 55s; }

        .tech-item {
            font-family: 'Roboto Mono', monospace; font-size: 0.82rem; font-weight: 600;
            color: var(--text-secondary); display: inline-flex; align-items: center; gap: 8px;
            opacity: 0.55; transition: opacity 0.3s ease; white-space: nowrap;
        }
        .tech-item:hover { opacity: 1; color: var(--luminous-teal); }
        html.light-mode .tech-item:hover { color: var(--midnight-navy); }
        .tech-item::before { content: '>'; color: var(--accent-color); opacity: 0.7; }
        html.light-mode .tech-item::before { color: var(--midnight-navy); }

        /* Framework items get a different accent */
        .framework-item {
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: 500;
            color: var(--text-secondary); display: inline-flex; align-items: center; gap: 8px;
            opacity: 0.5; transition: opacity 0.3s ease; white-space: nowrap;
        }
        .framework-item:hover { opacity: 1; color: var(--luminous-teal); }
        html.light-mode .framework-item:hover { color: var(--midnight-navy); }
        .framework-item::before { content: '◈'; color: rgba(0,229,255,0.5); font-size: 0.65rem; }
        html.light-mode .framework-item::before { color: rgba(10,17,40,0.4); }

        /* Row labels */
        .marquee-row-label {
            position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            font-family: 'Roboto Mono', monospace; font-size: 0.55rem; letter-spacing: 0.2em;
            color: var(--accent-color); opacity: 0.35; writing-mode: vertical-lr;
            text-transform: uppercase; z-index: 3; padding: 0 6px;
        }

        /* ══════════════════════════════════════════════════
           INTEL ACQUISITION — REBRANDED WHITEPAPER SECTION
        ══════════════════════════════════════════════════ */
        .whitepaper-section { padding: 120px 0; position: relative; }
        .whitepaper-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('{{ asset("assets/img/hero_home.webp") }}'); background-size: cover; background-position: center; z-index: 0; filter: grayscale(100%); }
        .wp-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10,17,40,0.92); backdrop-filter: blur(8px); z-index: 1; }
        html.light-mode .wp-overlay { background: rgba(243,244,246,0.92); }
        .wp-box { position: relative; z-index: 10; max-width: 680px; margin: 0 auto; background: rgba(0,229,255,0.02); border: 1px solid rgba(0,229,255,0.2); border-top: 3px solid var(--accent-color); padding: 50px; border-radius: 8px; text-align: center; box-shadow: 0 40px 80px rgba(0,0,0,0.6); }
        html.light-mode .wp-box { background: rgba(255,255,255,0.9); border-color: rgba(10,17,40,0.1); border-top-color: var(--midnight-navy); box-shadow: 0 40px 80px rgba(10,17,40,0.1); }

        /* What's inside grid */
        .wp-intel-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
            margin: 24px 0 32px; text-align: left;
        }
        .wp-intel-item {
            display: flex; align-items: flex-start; gap: 9px;
            font-family: 'Roboto Mono', monospace; font-size: 0.71rem;
            color: var(--text-secondary); line-height: 1.5;
            padding: 8px 10px;
            background: rgba(0,229,255,0.03);
            border: 1px solid rgba(0,229,255,0.07);
            border-radius: 3px;
            transition: all 0.2s ease;
        }
        .wp-intel-item:hover { background: rgba(0,229,255,0.06); border-color: rgba(0,229,255,0.18); color: var(--stark-white); }
        html.light-mode .wp-intel-item { background: rgba(10,17,40,0.02); border-color: rgba(10,17,40,0.07); }
        html.light-mode .wp-intel-item:hover { background: rgba(10,17,40,0.04); color: var(--midnight-navy); }
        .wp-intel-arrow { color: var(--luminous-teal); flex-shrink: 0; font-size: 0.75rem; margin-top: 1px; }
        html.light-mode .wp-intel-arrow { color: #2D6A4F; }

        .wp-input-group { display: flex; margin-top: 10px; position: relative; }
        .wp-input { flex: 1; padding: 18px 20px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--stark-white); font-family: 'Roboto Mono', monospace; border-radius: 4px 0 0 4px; outline: none; transition: border-color 0.3s; }
        html.light-mode .wp-input { background: rgba(0,0,0,0.02); color: var(--midnight-navy); border-color: rgba(10,17,40,0.2); }
        .wp-input:focus { border-color: var(--accent-color); box-shadow: inset 0 0 10px rgba(0,229,255,0.1); }
        .wp-btn { border-radius: 0 4px 4px 0; white-space: nowrap; margin-top: 0; }

        /* ==========================================================================
           MOBILE RESPONSIVE
           ========================================================================== */
        @media (max-width: 850px) {
            .container { padding-left: 20px !important; padding-right: 20px !important; }
            section { padding: 60px 0 !important; }
            .architecture-node { padding: 80px 0 !important; }
            .arch-text, .arch-text h2, .arch-text p { text-align: left !important; }
            .arch-header-row { justify-content: flex-start !important; }
            .terminal-teal-text { justify-content: flex-start !important; text-align: left !important; margin-left: 0 !important; }
            .hero-content, .funnel-container, .wp-box { text-align: center !important; }
            .arch-grid { grid-template-columns: 1fr; gap: 40px; }
            .architecture-node:nth-child(even) .arch-grid { direction: ltr !important; }
            .arch-visual { min-height: 250px; padding: 20px; }
            .faux-code { font-size: 0.75rem; word-break: break-all; }
            .wp-input-group { flex-direction: column; }
            .wp-input { border-radius: 4px; margin-bottom: 15px; text-align: center; }
            .wp-btn { border-radius: 4px; width: 100%; }
            .wp-intel-grid { grid-template-columns: 1fr; }
            .marquee-row-label { display: none; }
        }
    </style>
    @endpush

    {{-- HERO --}}
    <section class="hero-section">
        <div class="hero-bg"></div>
        <canvas id="matrixCanvas" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:-2;"></canvas>
        <div class="scanline-overlay"></div>
        <div class="hero-overlay"></div>
        <div class="container hero-content text-center reveal active">
            <div class="tech-badge mono-font">
                <span class="pulse-dot"></span> {{ __('// SYSTEM ARCHITECTURE') }}
            </div>
            <h1 class="brand-title">{{ __('THE PREDICTIVE PRAXIS FRAMEWORK') }}</h1>
            <h2 class="brand-slogan mono-font">{{ __('ENGINEERED RESILIENCE') }}</h2>
            <p class="hero-desc">{{ __('We sit at the intersection of high-stakes corporate audit, relational data engineering, and bespoke full-stack development. Discover how we eliminate operational chaos.') }}</p>
        </div>
    </section>

    {{-- FUNNEL --}}
    <section class="funnel-section">
        <div class="container funnel-container reveal active">
            <span class="funnel-label">> {{ __('IDENTIFY YOUR OPERATIONAL VULNERABILITY') }}</span>
            <div class="custom-select-wrapper">
                <select class="funnel-select" id="vulnerabilitySelect" onchange="triggerArchitectureRouting()">
                    <option value="" disabled selected>{{ __('Select your primary friction point...') }}</option>
                    <option value="node-governance">{{ __('We are losing capital to inefficient, undocumented workflows.') }}</option>
                    <option value="node-data">{{ __('Our enterprise data is fragmented, slow, and unprotected.') }}</option>
                    <option value="node-systems">{{ __('We need a custom digital system to govern our operations.') }}</option>
                    <option value="node-resilience">{{ __('We have the strategy reports, but no one to build the solution.') }}</option>
                    <option value="node-agentic-ai">{{ __('We need AI agents that actually execute — not just chatbots.') }}</option>
                    <option value="node-devops">{{ __('Our deployment process is manual, slow, and a security risk.') }}</option>
                </select>
                <div class="select-arrow">▼</div>
            </div>
            <div class="trace-line-container" id="traceLine"></div>
        </div>
    </section>

    {{-- NODE 01 — GOVERNANCE --}}
    <section class="architecture-node" id="node-governance">
        <div class="arch-watermark">01</div>
        <div class="container arch-grid reveal">
            <div class="arch-text">
                <div class="arch-header-row">
                    <span class="arch-num">I.</span>
                    <h4 class="mono-font" style="color:var(--luminous-teal);margin:0;text-transform:uppercase;">{{ __('Governance & Audit') }}</h4>
                </div>
                <h2>{{ __('We engineer efficiency and enforce compliance.') }}</h2>
                <p>{{ __('We do not just look for flaws; we hunt for wasted capital and time. Our Management Consulting wing focuses on extreme performance efficiency, leaning out processes and designing strict Internal Control Systems (ICS).') }}</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <h4>{{ __('Performance & Waste Reduction') }}</h4>
                        <p>{{ __('Re-engineering operations to remove bottlenecks via Six Sigma and continuous improvement principles.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Compliance & QMS Design') }}</h4>
                        <p>{{ __('Structuring your enterprise to seamlessly pass ISO, ESG, and regulatory audits.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('The Execution Partner') }}</h4>
                        <p>{{ __('Translating theoretical advice from Big 4 strategy reports into our engineered reality.') }}</p>
                    </div>
                </div>
                <button class="btn-outline" onclick="window.location.href='/contact'" wire:navigate>{{ __('Initiate Diagnostic') }}</button>
            </div>
            <div class="arch-visual">
                <div class="faux-code">
{!! __('> INITIATING AUDIT PROTOCOL...') !!}
<span class="faux-highlight">{!! __('[OK]') !!}</span> {{ __('Mapping procurement workflows') }}
<span class="faux-highlight">{!! __('[OK]') !!}</span> {{ __('Identifying capital leakage') }}
<span class="faux-highlight">{!! __('[OK]') !!}</span> {{ __('Designing ICS ruleset') }}

<span class="faux-error">{{ __('! WARNING: 3 Manual Approval Bottlenecks Detected.') }}</span>

{{ __('> COMPILING SOLUTION:') }}
{{ __('> Applying "Code as Law" architecture...') }}
{{ __('> ISO 9001 Compliance:') }} <span class="faux-highlight">{{ __('HARDCODED') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- NODE 02 — DATA --}}
    <section class="architecture-node" id="node-data">
        <div class="arch-watermark">02</div>
        <div class="container arch-grid reveal">
            <div class="arch-visual">
                <div class="faux-code">
CREATE TABLE enterprise_vault (
    id UUID PRIMARY KEY,
    transaction_hash VARCHAR(256) <span class="faux-highlight">UNIQUE NOT NULL</span>,
    capital_flow NUMERIC(15, 2),
    audit_trail JSONB,
    created_at TIMESTAMP <span class="faux-highlight">DEFAULT NOW()</span>
);

> {{ __('EXECUTING DATA MIGRATION...') }}
> {{ __('Extracted 14,204 rows from legacy.xlsx') }}
> <span class="faux-highlight">{{ __('MIGRATION COMPLETE.') }}</span> {{ __('Data Integrity: 100%') }}
                </div>
            </div>
            <div class="arch-text">
                <div class="arch-header-row">
                    <span class="arch-num">II.</span>
                    <h4 class="mono-font" style="color:var(--luminous-teal);margin:0;text-transform:uppercase;">{{ __('Data Engineering') }}</h4>
                </div>
                <h2>{{ __('Your enterprise, live on one screen.') }}</h2>
                <p>{{ __('Your data is both your greatest asset and your highest risk. We act as the architects of your digital nervous system, dismantling data silos and building an unbreakable digital vault.') }}</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <h4>{{ __('Enterprise Data Migration') }}</h4>
                        <p>{{ __('Extracting fragmented data from excel, manual records, and legacy systems into secure environments.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('PostgreSQL Relational Vault') }}</h4>
                        <p>{{ __('Designing high-performance backend infrastructure for complex, high-velocity corporate data.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Live Executive Dashboards') }}</h4>
                        <p>{{ __('Bypass end-of-month reporting delays. Connect data streams to monitor risk and capital in real-time.') }}</p>
                    </div>
                </div>
                <button class="btn-outline" onclick="window.location.href='/contact'" wire:navigate>{{ __('Request Data Review') }}</button>
            </div>
        </div>
    </section>

    {{-- NODE 03 — SYSTEMS --}}
    <section class="architecture-node" id="node-systems">
        <div class="arch-watermark">03</div>
        <div class="container arch-grid reveal">
            <div class="arch-text">
                <div class="arch-header-row">
                    <span class="arch-num">III.</span>
                    <h4 class="mono-font" style="color:var(--luminous-teal);margin:0;text-transform:uppercase;">{{ __('Full-Stack Engineering') }}</h4>
                </div>
                <h2>{{ __('Custom architecture, scaled to your class.') }}</h2>
                <p>{{ __('We do not force your business to fit into pre-packaged software; we build high-speed software that bends to your exact corporate rules.') }}</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <h4>{{ __('Enterprise Resource Planning (ERP)') }}</h4>
                        <p>{{ __('Bespoke, all-in-one operating systems designed to govern operations, HR, procurement, and finance.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Agile & High-Speed Deployment') }}</h4>
                        <p>{{ __('Utilizing modern stacks (JS, Laravel, Tailwind) for rapid scaling and instant UI updates.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('API & Third-Party Bridges') }}</h4>
                        <p>{{ __('Building secure digital bridges to payment gateways, logistics maps, and external banking platforms.') }}</p>
                    </div>
                </div>
                <button class="btn-outline" onclick="window.location.href='/contact'" wire:navigate>{{ __('Commission Custom Stack') }}</button>
            </div>
            <div class="arch-visual">
                <div class="faux-code">
<span style="color:#8A9BB2;">// {{ __('API Endpoint: Initiate Logistics Bridge') }}</span>
<span class="faux-highlight">const</span> initializeDeployment = <span class="faux-highlight">async</span> (payload) => {
    <span class="faux-highlight">try</span> {
        <span class="faux-highlight">const</span> res = <span class="faux-highlight">await</span> PraxisAPI.post('/deploy', payload);
        <span class="faux-highlight">if</span> (res.status === 200) {
            System.trigger('{{ __('Zero-Latency Interface') }}');
            <span class="faux-highlight">return</span> res.data.secure_token;
        }
    } <span class="faux-highlight">catch</span> (error) {
        System.block('{{ __('Unauthorized Operation') }}');
    }
};
                </div>
            </div>
        </div>
    </section>

    {{-- NODE 04 — RESILIENCE --}}
    <section class="architecture-node" id="node-resilience">
        <div class="arch-watermark">04</div>
        <div class="container arch-grid reveal">
            <div class="arch-visual" style="align-items:center;justify-content:center;">
                <div style="width:200px;height:200px;border-radius:50%;border:2px solid var(--accent-color);display:flex;align-items:center;justify-content:center;position:relative;">
                    <div style="position:absolute;inset:10px;border-radius:50%;border:1px dashed rgba(0,229,255,0.5);animation:spin 15s linear infinite;"></div>
                    <div style="position:absolute;inset:25px;border-radius:50%;border:2px solid rgba(255,255,255,0.1);animation:spinReverse 20s linear infinite;"></div>
                    <div style="position:absolute;font-family:'Roboto Mono',monospace;font-weight:900;color:var(--stark-white);font-size:2rem;text-shadow:0 0 15px rgba(0,229,255,0.5);">
                        <span style="color:var(--luminous-teal);">99.9</span>%
                    </div>
                </div>
            </div>
            <div class="arch-text">
                <div class="arch-header-row">
                    <span class="arch-num">IV.</span>
                    <h4 class="mono-font" style="color:var(--luminous-teal);margin:0;text-transform:uppercase;">{{ __('Continuous Resilience') }}</h4>
                </div>
                <h2>{{ __('Technology is never finished.') }}</h2>
                <p>{{ __('Choose your level of operational permanence. We act as your retained technical board to ensure your systems evolve as you capture market share.') }}</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <h4>{{ __('Tier 1: Standard Maintenance') }}</h4>
                        <p>{{ __('Routine patching, security updates, and automated compliance monitoring.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Tier 2: Advanced Scaling') }}</h4>
                        <p>{{ __('Agile integration of new modules and API connections as your enterprise enters new markets.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Tier 3: Elite Co-Managed Intelligence') }}</h4>
                        <p>{{ __('Retained access to the full JBS Management and Engineering strike team acting as your tech board.') }}</p>
                    </div>
                </div>
                <button class="btn-outline" onclick="window.location.href='/contact'" wire:navigate>{{ __('View Maintenance Tiers') }}</button>
            </div>
        </div>
    </section>


    {{-- NODE 05 — AGENTIC AI & RAG SYSTEMS --}}
    <section class="architecture-node" id="node-agentic-ai">
        <div class="arch-watermark">05</div>
        <div class="container arch-grid reveal">
            <div class="arch-text">
                <div class="arch-header-row">
                    <span class="arch-num">V.</span>
                    <h4 class="mono-font" style="color:var(--luminous-teal);margin:0;text-transform:uppercase;">{{ __('Agentic AI & RAG Systems') }}</h4>
                </div>
                <h2>{{ __('Your enterprise does not need more software. It needs a brain.') }}</h2>
                <p>{{ __('We deploy autonomous AI agents that do not just surface information — they execute decisions, generate institutional-grade outputs, and operate inside your live data architecture. Powered by Retrieval-Augmented Generation (RAG), every agent response is grounded in your verified enterprise knowledge, not generic model hallucination.') }}</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <h4>{{ __('Commercial Execution Agents') }}</h4>
                        <p>{{ __('Agents that ingest raw client briefs and meeting recordings, cross-reference your historical project data, and autonomously generate benchmarked, institutional-grade commercial tenders in minutes — not weeks.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Project Engineering Agents (Autonomous Scrum)') }}</h4>
                        <p>{{ __('Signed contracts become 14-day execution sprints automatically. Our Autonomous Scrum Agents translate complex scopes of work into database-backed sprint plans — eliminating the mobilisation delay and staging the battlespace on day one.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Revenue Assurance & Predictive Operations') }}</h4>
                        <p>{{ __('Financial agents live inside your transaction architecture, reconciling invoices, pausing anomalous outflows before they clear, and running simulation models that stress-test your business against real-world volatility — before it hits.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('RAG Architecture & Enterprise Knowledge Vaults') }}</h4>
                        <p>{{ __('We engineer the vector database infrastructure that makes AI deterministic in your context. Your SOPs, contracts, project archives, and institutional knowledge become a queryable, always-current intelligence layer — not a dead document folder.') }}</p>
                    </div>
                </div>
                <button class="btn-outline" onclick="window.location.href='/contact'" wire:navigate>{{ __('Deploy Your First Agent') }}</button>
            </div>

            <div class="arch-visual">
                <div class="faux-code">
<span style="color:#8A9BB2;">// {{ __('Praxis Cognitive Layer — Agent Runtime') }}</span>

<span class="faux-highlight">const</span> agent = <span class="faux-highlight">new</span> PraxisAgent({
    model: <span class="faux-highlight">'claude-sonnet'</span>,
    retriever: EnterpriseVault.connect({
        index: <span class="faux-highlight">'project_history_847k'</span>,
        threshold: <span class="faux-highlight">0.82</span>
    }),
    tools: [
        <span class="faux-highlight">'tender_generator'</span>,
        <span class="faux-highlight">'sprint_planner'</span>,
        <span class="faux-highlight">'revenue_guard'</span>,
        <span class="faux-highlight">'compliance_monitor'</span>
    ]
});

> {{ __('INITIALIZING RAG PIPELINE...') }}
<span class="faux-highlight">[OK]</span> {{ __('Vector index loaded: 847,293 docs') }}
<span class="faux-highlight">[OK]</span> {{ __('Context window: verified enterprise data') }}
<span class="faux-highlight">[OK]</span> {{ __('Hallucination guard: ACTIVE') }}

> {{ __('AGENT DEPLOYED. Monitoring 14 workflows...') }}
<span class="faux-error">{{ __('! Anomaly in AP_OUTFLOW_0441: PAUSED') }}</span>
> {{ __('Tender draft generated in:') }} <span class="faux-highlight">{{ __('4 minutes 12 seconds') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- NODE 06 — DEVOPS & CLOUD ENGINEERING --}}
    <section class="architecture-node" id="node-devops">
        <div class="arch-watermark">06</div>
        <div class="container arch-grid reveal">
            <div class="arch-visual">
                <div class="faux-code">
<span style="color:#8A9BB2;">## {{ __('praxis-deploy.yml — Production Pipeline') }}</span>

{{ __('name') }}: Praxis CI/CD
{{ __('on') }}: [push] → main

{{ __('jobs') }}:
  security_scan:
    <span class="faux-highlight">{{ __('status') }}</span>: {{ __('PASSED') }} ✓
    {{ __('vulnerabilities') }}: <span class="faux-highlight">0</span>

  test_suite:
    <span class="faux-highlight">{{ __('coverage') }}</span>: 97.4%
    {{ __('passed') }}: <span class="faux-highlight">312/312</span>

  deploy_production:
    {{ __('provider') }}: <span class="faux-highlight">AWS / GCP</span>
    {{ __('container') }}: <span class="faux-highlight">Docker → K8s</span>
    {{ __('rollback') }}: <span class="faux-highlight">{{ __('INSTANT') }}</span>
    <span class="faux-highlight">{{ __('status') }}</span>: <span class="faux-highlight">{{ __('LIVE') }}</span> ✓

> {{ __('Uptime:') }} <span class="faux-highlight">99.97%</span> | {{ __('Incidents:') }} <span class="faux-highlight">0</span>
> {{ __('Last deploy:') }} <span class="faux-highlight">{{ __('2 minutes ago') }}</span>
> {{ __('Active nodes:') }} <span class="faux-highlight">{{ __('Auto-scaling') }} ↑</span>
                </div>
            </div>

            <div class="arch-text">
                <div class="arch-header-row">
                    <span class="arch-num">VI.</span>
                    <h4 class="mono-font" style="color:var(--luminous-teal);margin:0;text-transform:uppercase;">{{ __('DevOps & Cloud Engineering') }}</h4>
                </div>
                <h2>{{ __('Infrastructure that deploys itself and heals itself.') }}</h2>
                <p>{{ __('We architect the cloud backbone that your AI agents, ERPs, and customer-facing systems run on — designed for zero-downtime deployment, auto-scaling under load, and instant rollback when anything deviates from baseline. Your infrastructure becomes as governed as your operations.') }}</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <h4>{{ __('CI/CD Pipeline Architecture') }}</h4>
                        <p>{{ __('We engineer automated build, test, and deployment pipelines via GitHub Actions, Docker, and Kubernetes — eliminating manual release cycles and human deployment error entirely.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Cloud Infrastructure & Auto-Scaling') }}</h4>
                        <p>{{ __('Multi-cloud deployment across AWS, GCP, and Azure with Terraform-managed infrastructure-as-code. Your environment scales with demand and shrinks with inactivity — automatically, without human intervention.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Security Hardening & Penetration Testing') }}</h4>
                        <p>{{ __('Every deployment passes through automated vulnerability scanning and manual penetration testing before it touches production. SSL enforcement, WAF rules, DDoS mitigation, and zero-trust network segmentation built in by default.') }}</p>
                    </div>
                    <div class="feature-item">
                        <h4>{{ __('Observability & Incident Response') }}</h4>
                        <p>{{ __('Centralised logging, real-time performance dashboards, and automated alerting mean your team sees anomalies before clients do. Critical incidents trigger automated rollbacks and notify your on-call engineer within 30 minutes — guaranteed.') }}</p>
                    </div>
                </div>
                <button class="btn-outline" onclick="window.location.href='/contact'" wire:navigate>{{ __('Architect Your Infrastructure') }}</button>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════
         TECH STACK & FRAMEWORKS MARQUEE — THREE ROWS
    ══════════════════════════════════════════════════ --}}
    <section class="tech-showcase" aria-label="{{ __('Technology stack and methodology frameworks') }}">

        {{-- ROW 1: Engineering & Infrastructure Stack (scrolls left, fast) --}}
        <div class="marquee-row">
            <div class="marquee-track" aria-hidden="true">
                <span class="tech-item">PostgreSQL</span>
                <span class="tech-item">Laravel</span>
                <span class="tech-item">Python</span>
                <span class="tech-item">FastAPI</span>
                <span class="tech-item">React.js</span>
                <span class="tech-item">Next.js</span>
                <span class="tech-item">Vue.js</span>
                <span class="tech-item">Alpine.js</span>
                <span class="tech-item">Livewire</span>
                <span class="tech-item">Tailwind CSS</span>
                <span class="tech-item">.NET / C#</span>
                <span class="tech-item">Node.js</span>
                <span class="tech-item">TypeScript</span>
                <span class="tech-item">Flutter</span>
                <span class="tech-item">Swift</span>
                <span class="tech-item">Kotlin</span>
                <span class="tech-item">GraphQL</span>
                <span class="tech-item">RESTful APIs</span>
                <span class="tech-item">WebSockets</span>
                <span class="tech-item">gRPC</span>
                {{-- Duplicate for seamless loop --}}
                <span class="tech-item">PostgreSQL</span>
                <span class="tech-item">Laravel</span>
                <span class="tech-item">Python</span>
                <span class="tech-item">FastAPI</span>
                <span class="tech-item">React.js</span>
                <span class="tech-item">Next.js</span>
                <span class="tech-item">Vue.js</span>
                <span class="tech-item">Alpine.js</span>
                <span class="tech-item">Livewire</span>
                <span class="tech-item">Tailwind CSS</span>
                <span class="tech-item">.NET / C#</span>
                <span class="tech-item">Node.js</span>
                <span class="tech-item">TypeScript</span>
                <span class="tech-item">Flutter</span>
                <span class="tech-item">Swift</span>
                <span class="tech-item">Kotlin</span>
                <span class="tech-item">GraphQL</span>
                <span class="tech-item">RESTful APIs</span>
                <span class="tech-item">WebSockets</span>
                <span class="tech-item">gRPC</span>
            </div>
        </div>

        {{-- ROW 2: AI / Cognitive & Data Layer (scrolls right, slower) --}}
        <div class="marquee-row">
            <div class="marquee-track reverse slow" aria-hidden="true">
                <span class="tech-item">LangChain</span>
                <span class="tech-item">LangGraph</span>
                <span class="tech-item">RAG Architecture</span>
                <span class="tech-item">OpenAI API</span>
                <span class="tech-item">Anthropic Claude</span>
                <span class="tech-item">Gemini API</span>
                <span class="tech-item">Hugging Face</span>
                <span class="tech-item">Pinecone</span>
                <span class="tech-item">ChromaDB</span>
                <span class="tech-item">Weaviate</span>
                <span class="tech-item">Vector Databases</span>
                <span class="tech-item">Agentic Workflows</span>
                <span class="tech-item">n8n</span>
                <span class="tech-item">Apache Kafka</span>
                <span class="tech-item">dbt</span>
                <span class="tech-item">Elasticsearch</span>
                <span class="tech-item">Redis</span>
                <span class="tech-item">MongoDB</span>
                <span class="tech-item">MySQL</span>
                <span class="tech-item">Supabase</span>
                <span class="tech-item">Power BI</span>
                <span class="tech-item">Metabase</span>
                <span class="tech-item">Apache Superset</span>
                {{-- Duplicate --}}
                <span class="tech-item">LangChain</span>
                <span class="tech-item">LangGraph</span>
                <span class="tech-item">RAG Architecture</span>
                <span class="tech-item">OpenAI API</span>
                <span class="tech-item">Anthropic Claude</span>
                <span class="tech-item">Gemini API</span>
                <span class="tech-item">Hugging Face</span>
                <span class="tech-item">Pinecone</span>
                <span class="tech-item">ChromaDB</span>
                <span class="tech-item">Weaviate</span>
                <span class="tech-item">Vector Databases</span>
                <span class="tech-item">Agentic Workflows</span>
                <span class="tech-item">n8n</span>
                <span class="tech-item">Apache Kafka</span>
                <span class="tech-item">dbt</span>
                <span class="tech-item">Elasticsearch</span>
                <span class="tech-item">Redis</span>
                <span class="tech-item">MongoDB</span>
                <span class="tech-item">MySQL</span>
                <span class="tech-item">Supabase</span>
                <span class="tech-item">Power BI</span>
                <span class="tech-item">Metabase</span>
                <span class="tech-item">Apache Superset</span>
            </div>
        </div>

        {{-- ROW 3: DevOps, Cloud & Management Frameworks (scrolls left) --}}
        <div class="marquee-row">
            <div class="marquee-track" aria-hidden="true">
                <span class="tech-item">AWS</span>
                <span class="tech-item">Google Cloud</span>
                <span class="tech-item">Azure</span>
                <span class="tech-item">Docker</span>
                <span class="tech-item">Kubernetes</span>
                <span class="tech-item">Terraform</span>
                <span class="tech-item">GitHub Actions</span>
                <span class="tech-item">CI/CD Pipelines</span>
                <span class="tech-item">Nginx</span>
                <span class="tech-item">Cloudflare</span>
                <span class="tech-item">Linux</span>
                <span class="tech-item">ISO 9001:2015</span>
                <span class="framework-item">Lean Six Sigma</span>
                <span class="framework-item">TOGAF</span>
                <span class="framework-item">ITIL v4</span>
                <span class="framework-item">COBIT 2019</span>
                <span class="framework-item">COSO Framework</span>
                <span class="framework-item">PMBOK 7</span>
                <span class="framework-item">Agile / Scrum</span>
                <span class="framework-item">OKRs</span>
                <span class="framework-item">Balanced Scorecard</span>
                <span class="framework-item">ISO 27001</span>
                <span class="framework-item">ISO 31000</span>
                <span class="framework-item">ESG Framework</span>
                <span class="framework-item">DMAIC</span>
                <span class="framework-item">Design Thinking</span>
                <span class="framework-item">Kaizen</span>
                {{-- Duplicate --}}
                <span class="tech-item">AWS</span>
                <span class="tech-item">Google Cloud</span>
                <span class="tech-item">Azure</span>
                <span class="tech-item">Docker</span>
                <span class="tech-item">Kubernetes</span>
                <span class="tech-item">Terraform</span>
                <span class="tech-item">GitHub Actions</span>
                <span class="tech-item">CI/CD Pipelines</span>
                <span class="tech-item">Nginx</span>
                <span class="tech-item">Cloudflare</span>
                <span class="tech-item">Linux</span>
                <span class="tech-item">ISO 9001:2015</span>
                <span class="framework-item">Lean Six Sigma</span>
                <span class="framework-item">TOGAF</span>
                <span class="framework-item">ITIL v4</span>
                <span class="framework-item">COBIT 2019</span>
                <span class="framework-item">COSO Framework</span>
                <span class="framework-item">PMBOK 7</span>
                <span class="framework-item">Agile / Scrum</span>
                <span class="framework-item">OKRs</span>
                <span class="framework-item">Balanced Scorecard</span>
                <span class="framework-item">ISO 27001</span>
                <span class="framework-item">ISO 31000</span>
                <span class="framework-item">ESG Framework</span>
                <span class="framework-item">DMAIC</span>
                <span class="framework-item">Design Thinking</span>
                <span class="framework-item">Kaizen</span>
            </div>
        </div>

    </section>

    {{-- ══════════════════════════════════════════════════
         INTEL ACQUISITION — REBRANDED & AI-FORWARD
    ══════════════════════════════════════════════════ --}}
    <section class="whitepaper-section">
        <div class="whitepaper-bg"></div>
        <div class="wp-overlay"></div>
        <div class="container relative-z">
            <div class="wp-box">
                <h4 class="terminal-teal-text mono-font">
                    <span class="pulse-dot"></span> <strong>{{ __('// INTEL ACQUISITION') }}</strong>
                </h4>
                <h2 style="font-size:clamp(1.8rem,4vw,2.5rem);margin-bottom:12px;color:var(--stark-white);">
                    {{ __('The Predictive Praxis Framework™') }}
                </h2>
                <p style="color:var(--text-secondary);margin-bottom:0;line-height:1.7;font-size:0.97rem;">
                    {{ __('A classified intelligence document for C-Suite leadership. Get the proprietary methodology behind our RAG architecture, autonomous agent deployment, COPQ diagnostics, and zero-defect governance — before your competitors do.') }}
                </p>

                {{-- What's inside --}}
                <div class="wp-intel-grid">
                    <div class="wp-intel-item">
                        <span class="wp-intel-arrow">&gt;_</span>
                        {{ __('Agentic AI Deployment Blueprint') }}
                    </div>
                    <div class="wp-intel-item">
                        <span class="wp-intel-arrow">&gt;_</span>
                        {{ __('RAG Architecture for Enterprises') }}
                    </div>
                    <div class="wp-intel-item">
                        <span class="wp-intel-arrow">&gt;_</span>
                        {{ __('COPQ Diagnostic Methodology') }}
                    </div>
                    <div class="wp-intel-item">
                        <span class="wp-intel-arrow">&gt;_</span>
                        {{ __('Zero-Defect Governance Framework') }}
                    </div>
                    <div class="wp-intel-item">
                        <span class="wp-intel-arrow">&gt;_</span>
                        {{ __('Data Vault Engineering Playbook') }}
                    </div>
                    <div class="wp-intel-item">
                        <span class="wp-intel-arrow">&gt;_</span>
                        {{ __('Enterprise AI Readiness Scorecard') }}
                    </div>
                </div>

                @if($isWhitepaperSuccess)
                    <div style="margin-top:25px;padding:30px;border:1px solid var(--accent-color);background:rgba(10,17,40,0.95);font-family:'Inter',sans-serif;animation:fadeDown 0.5s ease;border-radius:8px;text-align:left;box-shadow:0 20px 40px rgba(0,0,0,0.5);">
                        <h4 style="color:var(--luminous-teal);font-family:'Roboto Mono',monospace;font-size:0.9rem;margin-bottom:20px;">> {{ __('CLEARANCE GRANTED') }}</h4>
                        <div style="display:flex;flex-wrap:wrap;gap:30px;align-items:center;justify-content:center;">
                            <div style="flex:1;min-width:250px;">
                                <h3 style="color:var(--stark-white);font-size:1.3rem;margin-bottom:10px;font-weight:800;text-transform:uppercase;">{{ __('THE AGENTIC ENTERPRISE BRIEF™') }}</h3>
                                <p style="color:var(--text-secondary);font-size:0.95rem;margin-bottom:20px;">{{ __('Your secure access has been generated. Choose your deployment method below:') }}</p>
                                <div style="display:flex;flex-direction:column;gap:15px;">
                                    <a href="/framework" target="_blank" class="terminal-action-btn" style="text-align:center;width:100%;">
                                        {{ __('ACCESS FULL BRIEF') }}
                                    </a>
                                    <button wire:click="dispatchEmail" class="btn-outline" style="width:100%;">
                                        <span wire:loading.remove wire:target="dispatchEmail">{{ __('DISPATCH TO INBOX') }}</span>
                                        <span wire:loading wire:target="dispatchEmail">{{ __('ENCRYPTING & SENDING...') }}</span>
                                    </button>
                                    @if($emailSent)
                                        <span style="color:var(--luminous-teal);font-family:'Roboto Mono',monospace;font-size:0.8rem;">> {{ __('SECURE EMAIL TRANSMITTED') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div style="text-align:center;background:var(--stark-white);padding:15px;border-radius:4px;flex-shrink:0;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://jbspraxis.com/framework?exec=true&margin=0" alt="QR Code" style="width:130px;height:130px;margin-bottom:10px;">
                                <div style="font-family:'Roboto Mono',monospace;font-size:0.7rem;color:var(--midnight-navy);font-weight:800;">{{ __('SCAN FOR MOBILE') }}<br>{{ __('EXECUTIVE SUMMARY') }}</div>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- FIX: wire:model.lazy fires only on blur, not every keystroke — stops scroll jump --}}
                    <form wire:submit.prevent="processWhitepaper">
                        <div class="wp-input-group">
                            <input type="email"
                                   class="wp-input"
                                   placeholder="{{ __('Corporate Email Address') }}"
                                   wire:model.lazy="email"
                                   required
                                   autocomplete="email">
                            <button type="submit" class="terminal-action-btn wp-btn">
                                <span wire:loading.remove wire:target="processWhitepaper">{{ __('SECURE DOWNLOAD') }}</span>
                                <span wire:loading wire:target="processWhitepaper">{{ __('TRANSMITTING...') }}</span>
                            </button>
                        </div>
                        @error('email')
                            <span style="color:var(--terminal-red);font-family:'Roboto Mono',monospace;font-size:0.75rem;margin-top:15px;display:block;text-align:left;">> {{ __('ERR:') }} {{ $message }}</span>
                        @enderror
                    </form>
                @endif
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('livewire:navigated', () => {
            const reveals = document.querySelectorAll('.reveal');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) entry.target.classList.add('active');
                });
            }, { threshold: 0.1 });
            reveals.forEach(reveal => observer.observe(reveal));
            if (document.getElementById('matrixCanvas')) initMatrixCanvas();
        });

        function triggerArchitectureRouting() {
            const select   = document.getElementById('vulnerabilitySelect');
            const targetId = select.value;
            const traceLine = document.getElementById('traceLine');
            if (targetId) {
                traceLine.style.height = "150px";
                setTimeout(() => {
                    const el = document.getElementById(targetId);
                    const offsetPosition = el.getBoundingClientRect().top + window.pageYOffset - 80;
                    window.scrollTo({ top: offsetPosition, behavior: "smooth" });
                    setTimeout(() => { traceLine.style.height = "0"; }, 1000);
                }, 400);
            }
        }

        function initMatrixCanvas() {
            const canvas = document.getElementById('matrixCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let particlesArray = [];
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            class Particle {
                constructor(x, y, dX, dY, size) { this.x=x; this.y=y; this.directionX=dX; this.directionY=dY; this.size=size; }
                draw() { ctx.beginPath(); ctx.arc(this.x,this.y,this.size,0,Math.PI*2,false); ctx.fillStyle='#00E5FF'; ctx.fill(); }
                update() {
                    if(this.x>canvas.width||this.x<0) this.directionX=-this.directionX;
                    if(this.y>canvas.height||this.y<0) this.directionY=-this.directionY;
                    this.x+=this.directionX; this.y+=this.directionY; this.draw();
                }
            }

            function init() {
                particlesArray=[];
                let n=(canvas.height*canvas.width)/12000;
                for(let i=0;i<n;i++) {
                    let s=(Math.random()*2)+1;
                    particlesArray.push(new Particle(
                        Math.random()*(innerWidth-s*4)+s*2,
                        Math.random()*(innerHeight-s*4)+s*2,
                        (Math.random()*0.8)-0.4,
                        (Math.random()*0.8)-0.4, s
                    ));
                }
            }

            function connect() {
                for(let a=0;a<particlesArray.length;a++) {
                    for(let b=a;b<particlesArray.length;b++) {
                        let d=((particlesArray[a].x-particlesArray[b].x)**2)+((particlesArray[a].y-particlesArray[b].y)**2);
                        if(d<(canvas.width/7)*(canvas.height/7)) {
                            ctx.strokeStyle='rgba(0,229,255,'+(1-(d/20000))+')';
                            ctx.lineWidth=1;
                            ctx.beginPath();
                            ctx.moveTo(particlesArray[a].x,particlesArray[a].y);
                            ctx.lineTo(particlesArray[b].x,particlesArray[b].y);
                            ctx.stroke();
                        }
                    }
                }
            }

            function animate() {
                requestAnimationFrame(animate);
                ctx.clearRect(0,0,innerWidth,innerHeight);
                particlesArray.forEach(p=>p.update());
                connect();
            }

            window.addEventListener('resize',()=>{ canvas.width=innerWidth; canvas.height=innerHeight; init(); });
            init(); animate();
        }
    </script>
</div>