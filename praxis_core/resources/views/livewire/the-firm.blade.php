<div>
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - ABOUT US MASTER CSS ARCHITECTURE 
           ========================================================================== */

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
        @keyframes fadeDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        .section-header { margin-bottom: 60px; text-align: center; }
        .relative-z { position: relative; z-index: 10; }
        
        .pulse-dot { width: 6px; height: 6px; background-color: var(--luminous-teal); border-radius: 50%; box-shadow: 0 0 10px var(--luminous-teal); animation: pulse 2s infinite; }
        
        .terminal-teal-text { color: var(--luminous-teal); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 15px; }
        .terminal-teal-text strong { letter-spacing: 2px; font-weight: 800; }

        html.light-mode .terminal-teal-text, html.light-mode .pulse-dot { color: #2D6A4F; }
        html.light-mode .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.4); }

        .btn-outline { background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color); text-decoration: none; padding: 12px 24px; display: inline-block; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; transition: all 0.3s ease; border-radius: 2px; }
        .btn-outline:hover { background: var(--accent-color); color: var(--midnight-navy); box-shadow: 0 0 20px rgba(0, 229, 255, 0.3); }
        html.light-mode .btn-outline { border-color: var(--midnight-navy); color: var(--midnight-navy); }
        html.light-mode .btn-outline:hover { background-color: var(--midnight-navy); color: var(--stark-white); }

        /* --- SECTION 1: HERO --- */
        .hero-section { position: relative; height: 100vh; display: flex; align-items: center; justify-content: center; padding-top: 80px; overflow: visible; }
        .hero-bg, .scanline-overlay, .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .hero-bg { background-image: url('{{ asset("assets/img/about.svg") }}'); background-size: cover; background-position: center; z-index: -3; }
        .hero-overlay { background: linear-gradient(180deg, rgba(10,17,40,0.7) 0%, var(--midnight-navy) 100%); z-index: -2; }
        html.light-mode .hero-overlay { background: linear-gradient(180deg, rgba(255,255,255,0.8) 0%, var(--stark-white) 100%); }
        .scanline-overlay { background: linear-gradient(to bottom, transparent 50%, rgba(0, 229, 255, 0.03) 51%); background-size: 100% 4px; z-index: -1; pointer-events: none; }
        
        .hero-content { position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; max-width: 900px; width: 100%; text-align: center; }
        .tech-badge { display: inline-flex; align-items: center; gap: 8px; font-size: 0.65rem; font-weight: 700; letter-spacing: 2px; margin-bottom: 15px; padding: 5px 12px; background: rgba(0, 229, 255, 0.05); border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 20px; color: var(--luminous-teal); }
        html.light-mode .tech-badge { color: #1E3A8A; border-color: rgba(30, 58, 138, 0.2); }
        .brand-title { font-size: clamp(3rem, 6vw, 5.5rem); font-weight: 900; letter-spacing: -2px; line-height: 1.1; margin-bottom: 5px; background: linear-gradient(to right, #FFFFFF, #8A9bb2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        html.light-mode .brand-title { background: linear-gradient(to right, var(--midnight-navy), #4A5568); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .brand-slogan { font-size: clamp(1rem, 2vw, 1.5rem); color: var(--luminous-teal); letter-spacing: 4px; margin-bottom: 25px; font-weight: 400; }
        html.light-mode .brand-slogan { color: var(--accent-color); }
        .hero-desc { font-size: clamp(0.95rem, 1.2vw, 1.1rem); max-width: 750px; margin-bottom: 40px; padding: 0 15px; color: var(--text-secondary); }

        /* COMMAND NEXUS CTA */
        .nexus-wrapper { position: relative; perspective: 1000px; display: flex; flex-direction: column; align-items: center; z-index: 100; }
        .nexus-trigger { padding: 18px 35px; font-size: 0.9rem; display: flex; align-items: center; gap: 15px; background: rgba(10, 17, 40, 0.6); border: 1px solid var(--accent-color); color: var(--stark-white); box-shadow: 0 0 20px rgba(0, 229, 255, 0.1); backdrop-filter: blur(10px); z-index: 2; border-radius: 2px; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; }
        html.light-mode .nexus-trigger { background: var(--midnight-navy); border-color: var(--midnight-navy); }
        .prompt-arrow { color: var(--luminous-teal); animation: blink 1s infinite; }
        .nexus-trigger:hover { background: var(--accent-color); color: var(--midnight-navy); box-shadow: 0 0 30px rgba(0, 229, 255, 0.4); }
        .nexus-trigger:hover .prompt-arrow { color: var(--midnight-navy); }

        .nexus-matrix { position: absolute; top: calc(100% + 15px); left: 50%; transform: translateX(-50%) translateY(10px); opacity: 0; visibility: hidden; background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(0, 229, 255, 0.2); border-top: 2px solid var(--accent-color); border-radius: 6px; padding: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); transition: all 0.3s ease; z-index: 100; }
        html.light-mode .nexus-matrix { background: rgba(255, 255, 255, 0.95); border-color: rgba(10, 17, 40, 0.1); border-top-color: var(--midnight-navy); }
        .nexus-wrapper:hover .nexus-matrix, .nexus-wrapper:focus-within .nexus-matrix { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }
        
        .compact-grid { display: flex; gap: 10px; }
        .compact-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 12px 20px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; }
        html.light-mode .compact-card { background: rgba(0,0,0,0.02); border-color: rgba(0,0,0,0.05); color: var(--midnight-navy); }
        .compact-card:hover { background: rgba(0, 229, 255, 0.08); border-color: var(--accent-color); }
        .highlight-card { background: var(--accent-color); color: var(--midnight-navy); border: none; }
        .highlight-card:hover { background: var(--stark-white); color: var(--midnight-navy); }

        /* --- SECTION 2: DNA --- */
        .dna-section { padding: 100px 0; position: relative; }
        .dna-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; }
        .dna-text h3 { font-size: 2.2rem; margin-bottom: 25px; color: var(--stark-white); letter-spacing: -1px; }
        html.light-mode .dna-text h3 { color: var(--midnight-navy); }
        .dna-text p { font-size: 1.05rem; margin-bottom: 20px; line-height: 1.8; color: var(--text-secondary); }
        .dna-quote { font-family: 'Roboto Mono', monospace; font-size: 0.95rem; color: var(--luminous-teal); padding: 25px; border-left: 3px solid var(--accent-color); background: rgba(0, 229, 255, 0.05); margin-top: 35px; border-radius: 0 4px 4px 0; line-height: 1.6; }
        html.light-mode .dna-quote { background: rgba(10, 17, 40, 0.05); color: var(--midnight-navy); border-left-color: var(--midnight-navy); }
        .dna-visual img { width: 100%; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); filter: grayscale(100%) contrast(1.2); transition: filter 0.5s ease; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        .dna-visual img:hover { filter: grayscale(0%) contrast(1); }
        html.light-mode .dna-visual img { border-color: rgba(10,17,40,0.1); box-shadow: 0 20px 40px rgba(10,17,40,0.1); }

        /* --- SECTION 3: MISSION & VISION MANDATE --- */
        .mandate-section { padding: 80px 0; position: relative; background-image: linear-gradient(var(--border-color) 1px, transparent 1px), linear-gradient(90deg, var(--border-color) 1px, transparent 1px); background-size: 50px 50px; background-position: center; }
        .mandate-section::before { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at center, transparent 0%, var(--midnight-navy) 90%); z-index: 0; }
        html.light-mode .mandate-section::before { background: radial-gradient(circle at center, transparent 0%, var(--stark-white) 90%); }
        
        .mandate-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; position: relative; z-index: 2; }
        .mandate-card { background: rgba(10, 17, 40, 0.8); backdrop-filter: blur(15px); border: 1px solid rgba(0, 229, 255, 0.2); border-top: 3px solid var(--accent-color); padding: 50px 40px; border-radius: 6px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); transition: transform 0.3s ease; }
        html.light-mode .mandate-card { background: rgba(255, 255, 255, 0.9); border-color: rgba(10, 17, 40, 0.1); border-top-color: var(--midnight-navy); box-shadow: 0 20px 40px rgba(10, 17, 40, 0.05); }
        .mandate-card:hover { transform: translateY(-5px); border-color: var(--accent-color); }
        .mandate-card h3 { font-size: 1.8rem; margin-bottom: 20px; color: var(--stark-white); }
        html.light-mode .mandate-card h3 { color: var(--midnight-navy); }
        .mandate-card p { font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin: 0; }

        /* --- SECTION 4: PILLARS --- */
        .pillars-section { padding: 100px 0; background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05); }
        html.light-mode .pillars-section { background: var(--ghost-white); border-color: rgba(10,17,40,0.05); }
        .pillars-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-top: 50px; }
        .pillar-card { background: var(--bg-secondary); padding: 40px 30px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.03); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        html.light-mode .pillar-card { background: #FFFFFF; border-color: rgba(10,17,40,0.05); }
        .pillar-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.4); border-color: var(--accent-color); background: rgba(0, 229, 255, 0.03); }
        html.light-mode .pillar-card:hover { border-color: var(--midnight-navy); background: rgba(10, 17, 40, 0.02); box-shadow: 0 20px 40px rgba(10,17,40,0.1); }
        .pillar-icon { font-size: 2rem; color: var(--luminous-teal); margin-bottom: 20px; font-family: 'Roboto Mono', monospace; font-weight: bold; }
        html.light-mode .pillar-icon { color: var(--midnight-navy); }
        .pillar-card h4 { font-size: 1.1rem; margin-bottom: 15px; color: var(--stark-white); font-weight: 700; text-transform: uppercase; }
        html.light-mode .pillar-card h4 { color: var(--midnight-navy); }
        .pillar-card p { font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); margin: 0; }

        /* --- SECTION 5: MANIFESTO & AXIOMS --- */
        .manifesto-section { padding: 120px 0; text-align: center; }
        .manifesto-title { font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 30px; letter-spacing: -1px; }
        .manifesto-body { max-width: 800px; margin: 0 auto 80px; font-size: 1.15rem; line-height: 1.8; color: var(--text-secondary); }
        .manifesto-highlight { font-size: 1.4rem; color: var(--stark-white); font-weight: 600; margin: 30px 0; padding: 20px 0; border-top: 1px solid rgba(255,255,255,0.1); border-bottom: 1px solid rgba(255,255,255,0.1); }
        html.light-mode .manifesto-highlight { color: var(--midnight-navy); border-color: rgba(10,17,40,0.1); }

        .axioms-list { display: flex; flex-direction: column; gap: 20px; max-width: 900px; margin: 0 auto 60px auto; text-align: left; }
        .axiom-item { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-left: 3px solid transparent; padding: 35px 40px; border-radius: 4px; display: flex; gap: 30px; transition: all 0.3s ease; }
        html.light-mode .axiom-item { background: #FFFFFF; border-color: rgba(10,17,40,0.05); }
        .axiom-item:hover { border-left-color: var(--accent-color); background: rgba(0, 229, 255, 0.03); box-shadow: 0 15px 30px rgba(0,0,0,0.2); transform: translateX(5px); }
        html.light-mode .axiom-item:hover { border-left-color: var(--midnight-navy); background: rgba(10, 17, 40, 0.02); box-shadow: 0 15px 30px rgba(10,17,40,0.05); }
        
        .axiom-number { font-family: 'Roboto Mono', monospace; color: var(--luminous-teal); font-weight: bold; font-size: 1.5rem; min-width: 40px; padding-top: 2px; }
        html.light-mode .axiom-number { color: var(--midnight-navy); }
        .axiom-content h4 { font-size: 1.2rem; color: var(--stark-white); margin-bottom: 10px; font-family: 'Roboto Mono', monospace; text-transform: uppercase; }
        html.light-mode .axiom-content h4 { color: var(--midnight-navy); }
        .axiom-content p { font-size: 1rem; color: var(--text-secondary); line-height: 1.7; margin: 0; }

        /* ==========================================================================
           MOBILE RESPONSIVE MASTER (ALIGNMENT & SPACING PERFECTION)
           ========================================================================== */
        @media (max-width: 850px) {
            /* 1. Global Spacing Consistency */
            .container { padding-left: 20px !important; padding-right: 20px !important; }
            section { padding: 60px 0 !important; } 
            
            /* 2. Strict Left-Alignment for all structural content */
            .section-header, .manifesto-body { text-align: left !important; }
            .section-header p, .manifesto-body p { margin-left: 0 !important; margin-right: 0 !important; text-align: left !important; }
            .terminal-teal-text { justify-content: flex-start !important; text-align: left !important; margin-left: 0 !important; }
            .manifesto-title, .manifesto-highlight { text-align: left !important; }
            
            /* 3. Keep Hero Center-Aligned */
            .hero-content { text-align: center !important; }
            
            /* 4. Nexus Matrix Stacking */
            .nexus-matrix { position: relative; top: 0; left: 0; transform: none; width: 100%; background: transparent; border: none; box-shadow: none; padding: 15px 0 0 0; display: none; }
            .nexus-wrapper:hover .nexus-matrix, .nexus-wrapper:focus-within .nexus-matrix, .nexus-wrapper:active .nexus-matrix { display: block; animation: fadeDown 0.3s ease forwards; }
            .compact-grid { flex-direction: column; width: 100%; }
            .compact-card { width: 100%; text-align: center; padding: 15px; }
            
            /* 5. Grids to Vertical Stacks */
            .dna-grid, .mandate-grid { grid-template-columns: 1fr; gap: 40px; }
            .pillars-grid { grid-template-columns: 1fr; gap: 20px; margin-top: 30px; }
            
            /* 6. Card & Item Adjustments */
            .mandate-card { padding: 30px 25px; }
            .axiom-item { flex-direction: column; gap: 15px; padding: 25px 20px; }
            .axiom-number { font-size: 1.2rem; }
        }
        /* --- THE JIREH RESOLUTION BLOCK (HEARTBEAT EDITION) --- */
        .jireh-resolution-block {
            position: relative;
            margin: 60px auto;
            padding: 50px 40px;
            background: rgba(0, 229, 255, 0.015);
            border: 1px solid rgba(0, 229, 255, 0.15);
            border-radius: 8px;
            max-width: 850px;
            text-align: center; /* Centered Alignment */
            transform-origin: center center;
            animation: systemHeartbeat 2s infinite; /* The Biological Double-Thump */
        }
        
        /* The Dark Mode Heartbeat */
        @keyframes systemHeartbeat {
            0% { transform: scale(1); box-shadow: 0 0 10px rgba(0, 229, 255, 0.05); }
            15% { transform: scale(1.015); box-shadow: 0 0 30px rgba(0, 229, 255, 0.2); }
            30% { transform: scale(1); box-shadow: 0 0 15px rgba(0, 229, 255, 0.1); }
            45% { transform: scale(1.015); box-shadow: 0 0 40px rgba(0, 229, 255, 0.3); }
            60% { transform: scale(1); box-shadow: 0 0 10px rgba(0, 229, 255, 0.05); }
            100% { transform: scale(1); box-shadow: 0 0 10px rgba(0, 229, 255, 0.05); }
        }

        .resolution-tag {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 25px;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            justify-content: center; /* Centers the tag and pulse dot */
            gap: 10px;
        }

        .resolution-text {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 900;
            color: #E5E4E2; /* True Platinum Color for Dark Mode */
            line-height: 1.3;
            margin: 0;
            letter-spacing: -1px;
        }

        .teal-glow {
            color: var(--luminous-teal);
            text-shadow: 0 0 20px rgba(0, 229, 255, 0.5);
        }

        /* --- LIGHT MODE: THE PROVISION GREEN --- */
        html.light-mode .jireh-resolution-block {
            background: rgba(45, 106, 79, 0.02); /* Subtle Green Tint */
            border-color: rgba(45, 106, 79, 0.2);
            animation: systemHeartbeatLight 2s infinite;
        }

        /* The Light Mode Heartbeat */
        @keyframes systemHeartbeatLight {
            0% { transform: scale(1); box-shadow: 0 0 10px rgba(45, 106, 79, 0.05); }
            15% { transform: scale(1.015); box-shadow: 0 0 30px rgba(45, 106, 79, 0.15); }
            30% { transform: scale(1); box-shadow: 0 0 15px rgba(45, 106, 79, 0.1); }
            45% { transform: scale(1.015); box-shadow: 0 0 40px rgba(45, 106, 79, 0.25); }
            60% { transform: scale(1); box-shadow: 0 0 10px rgba(45, 106, 79, 0.05); }
            100% { transform: scale(1); box-shadow: 0 0 10px rgba(45, 106, 79, 0.05); }
        }

        html.light-mode .resolution-text { 
            color: var(--midnight-navy); 
        }
        
        html.light-mode .teal-glow { 
            color: #2D6A4F; /* Deep Provision Green */
            text-shadow: 0 0 20px rgba(45, 106, 79, 0.3); 
        }
        
        html.light-mode .resolution-tag { color: #2D6A4F; }
        html.light-mode .resolution-tag .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.4); }

        /* Mobile Padding Adjustment */
        @media (max-width: 850px) {
            .jireh-resolution-block { padding: 35px 25px; margin: 40px 0; }
        }
    </style>
    @endpush

    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="scanline-overlay"></div>
        <div class="hero-overlay"></div>
        
        <div class="container hero-content text-center">
            <div class="tech-badge mono-font">
                <span class="pulse-dot"></span> {{ __('// THE FIRM : CORE DNA') }}
            </div>
            
            <h1 class="brand-title">{{ __('PRAXIS') }}</h1>
            <h2 class="brand-slogan mono-font">{{ __('/ˈpraksɪs/ (noun)') }}</h2>
            
            <p class="hero-desc">{{ __('The process by which a theory, lesson, or skill is enacted, embodied, or realized. At JBS, this is where knowledge becomes unbreakable structure.') }}</p>
            
            <div class="nexus-wrapper">
                <button class="btn nexus-trigger">
                    {{ __('EXPLORE THE FIRM') }} <span class="mono-font prompt-arrow">>_</span>
                </button>
                
                <div class="nexus-matrix">
                    <div class="compact-grid">
                        <div class="compact-card" onclick="window.scrollTo({top: document.querySelector('.dna-section').offsetTop, behavior: 'smooth'})">
                            {{ __('The Architecture') }}
                        </div>
                        <div class="compact-card" onclick="window.scrollTo({top: document.querySelector('.manifesto-section').offsetTop, behavior: 'smooth'})">
                            {{ __('The Manifesto') }}
                        </div>
                        <div class="compact-card highlight-card" onclick="window.location.href='/schedule'" wire:navigate>
                            {{ __('Engage Strike Team') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="dna-section">
        <div class="container">
            <div class="section-header text-center">
                <h4 class="terminal-teal-text mono-font">
                    <span class="pulse-dot"></span> <strong>{{ __('// ORIGIN LOG') }}</strong>
                </h4>
                <h2>{{ __('Forged in Pressure.') }}</h2>
            </div>

            <div class="dna-grid">
                <div class="dna-text">
                    <p>{{ __('Our foundation was not built in a vacuum but was forged in the highest-pressure environments of the global economy. The DNA of JBS Praxis is a fusion of over 15 years of combined, cross-sector enterprise experience.') }}</p>
                    <p>{{ __('Our leadership matrix integrates elite backgrounds from Tier-1 Telecommunications, Top-Tier Banking, Multinational Consulting Firms, Government Audit Agencies, Software Engineering firms, Logistics, Construction, and Oil & Gas.') }}</p>
                    
                    <div class="dna-quote">
                        > {{ __('"Organizations do not fail because they lack ideas or talent. They fail because their systems are weak."') }}
                    </div>
                </div>
                <div class="dna-visual">
                    <img src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800&auto=format&fit=crop" alt="{{ __('Corporate Architecture Data Lines') }}" style="max-width: 100%; height: auto;">
                </div>
            </div>
        </div>
    </section>

    <section class="mandate-section">
        <div class="container">
            <div class="mandate-grid">
                <div class="mandate-card">
                    <div class="tech-badge mono-font" style="margin-bottom: 20px;">{{ __('// PROTOCOL : MISSION') }}</div>
                    <h3>{{ __('Eradicate Chaos.') }}</h3>
                    <p>{{ __('To eradicate "post-mortem" consulting and operational chaos. We bridge the fatal gap between theoretical boardroom strategy and hardcoded server-room execution by engineering bespoke, predictive digital operating systems that neutralize bottlenecks and capital leakage before they occur.') }}</p>
                </div>
                <div class="mandate-card">
                    <div class="tech-badge mono-font" style="margin-bottom: 20px;">{{ __('// PROTOCOL : VISION') }}</div>
                    <h3>{{ __('Global Standard.') }}</h3>
                    <p>{{ __('To establish Anticipatory Governance as the global standard for enterprise operations, empowering organizations across Africa and the globe to bypass legacy systems and scale flawlessly through predictive, data-driven dominance.') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="pillars-section">
        <div class="container">
            <div class="section-header text-center">
                <h4 class="terminal-teal-text mono-font">
                    <span class="pulse-dot"></span> <strong>{{ __('// STRUCTURAL CAPABILITIES') }}</strong>
                </h4>
                <h2>{{ __('The Four Pillars of Expertise') }}</h2>
                <p style="max-width: 600px; margin: 0 auto; margin-top: 15px;">{{ __('We do not operate in silos. We deploy as a unified architectural front.') }}</p>
            </div>

            <div class="pillars-grid">
                <div class="pillar-card">
                    <div class="pillar-icon">01</div>
                    <h4>{{ __('The Management Pillar') }}</h4>
                    <p>{{ __('Driving lean performance, corporate governance, and operational strategy.') }}</p>
                </div>
                <div class="pillar-card">
                    <div class="pillar-icon">02</div>
                    <h4>{{ __('The Data Pillar') }}</h4>
                    <p>{{ __('Architecting high-velocity PostgreSQL databases and guaranteeing absolute data integrity.') }}</p>
                </div>
                <div class="pillar-card">
                    <div class="pillar-icon">03</div>
                    <h4>{{ __('The Full-Stack Pillar') }}</h4>
                    <p>{{ __('Deploying unbreakable, bespoke enterprise software and secure API integrations.') }}</p>
                </div>
                <div class="pillar-card">
                    <div class="pillar-icon">04</div>
                    <h4>{{ __('The Audit Pillar') }}</h4>
                    <p>{{ __('Enforcing compliance, managing risk matrices, and ensuring Total Quality Management.') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="manifesto-section container">
        <h4 class="terminal-teal-text mono-font">
            <span class="pulse-dot"></span> <strong>{{ __('// THE FOUNDATION') }}</strong>
        </h4>
        <h2 class="manifesto-title">{{ __('The Jireh Manifesto') }}</h2>
        
        <div class="manifesto-body">
            <p>{!! __('JBS stands for <strong>Jireh Business Solutions.</strong>') !!}</p>
            <p>{{ __('We know that businesses cry for help every day. CEOs cry when they cannot trust their numbers. Boards cry when compliance fails. Founders cry when their operations spiral out of control.') }}</p>
            <div class="jireh-resolution-block">
            <div class="resolution-tag">
                <span class="pulse-dot"></span> {{ __('// SYSTEM.RESOLUTION_FOUND') }}
            </div>
            <p class="resolution-text">
                <span class="teal-glow">Jireh</span>—{{ __('The Provider') }}—<br>
                {{ __('is the answer to that cry.') }}
            </p>
        </div>
            <p>{{ __('But in the corporate landscape, we believe provision is not mystical; it is structured. We deliver that provision not in sermons, but in unbreakable digital systems.') }}</p>
        </div>

        <h4 class="terminal-teal-text mono-font" style="margin-bottom: 40px;">
            <span class="pulse-dot"></span> <strong>{{ __('// THE PRAXIS AXIOMS') }}</strong>
        </h4>
        
        <div class="axioms-list">
            <div class="axiom-item">
                <div class="axiom-number">01</div>
                <div class="axiom-content">
                    <h4>{{ __('Zero-Latency Truth') }}</h4>
                    <p>{{ __('We reject silos, fragmented Excel sheets, and departmental assumptions. We believe in a Single Source of Truth, where enterprise data is centralized, sanitized, and instantly actionable. We do not digitize confusion; we eliminate it.') }}</p>
                </div>
            </div>
            
            <div class="axiom-item">
                <div class="axiom-number">02</div>
                <div class="axiom-content">
                    <h4>{{ __('Anticipatory Action') }}</h4>
                    <p>{{ __('Analyzing failure after the fact is an autopsy, not a strategy. We value foresight over reaction, utilizing augmented intelligence to predict and neutralize systemic risks and financial leakage before they impact the bottom line.') }}</p>
                </div>
            </div>
            
            <div class="axiom-item">
                <div class="axiom-number">03</div>
                <div class="axiom-content">
                    <h4>{{ __('Code as Law') }}</h4>
                    <p>{{ __('Human willpower is finite; engineered systems are infinite. We believe that corporate policies and ISO compliance should not be mere suggestions in a PDF, but immutable, hardcoded logic gates that make deviations mathematically impossible.') }}</p>
                </div>
            </div>

            <div class="axiom-item">
                <div class="axiom-number">04</div>
                <div class="axiom-content">
                    <h4>{{ __('Sovereign Empowerment') }}</h4>
                    <p>{{ __('We build fortresses, but we hand over the keys. We are committed to the total talent development of our partners, ensuring seamless handover and executive fluency so that our clients command their own architecture.') }}</p>
                </div>
            </div>

            <div class="axiom-item">
                <div class="axiom-number">05</div>
                <div class="axiom-content">
                    <h4>{{ __('Kaizen (Continuous Evolution)') }}</h4>
                    <p>{{ __('Code is a living organism. We are dedicated to the relentless optimization of our systems, our people, and our clients\' infrastructure. As market dynamics shift, we dynamically refine the architecture to guarantee lifetime systemic resilience.') }}</p>
                </div>
            </div>
        </div>

        <a href="/schedule" class="btn-outline" style="padding: 15px 40px; font-size: 1rem; margin-top: 20px;" wire:navigate>{{ __('ENGAGE THE ARCHITECTS') }}</a>
    </section>
</div>