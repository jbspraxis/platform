<div>
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - CAREERS MASTER CSS ARCHITECTURE 
           ========================================================================== */

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
        @keyframes fadeDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes periodicScan { 0% { top: -5%; opacity: 0; } 2% { opacity: 0.5; } 18% { top: 105%; opacity: 0.5; } 20%, 100% { opacity: 0; top: 105%; } }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .section-header { margin-bottom: 60px; text-align: center; }
        .relative-z { position: relative; z-index: 10; }
        
        .pulse-dot { width: 6px; height: 6px; background-color: var(--luminous-teal); border-radius: 50%; box-shadow: 0 0 10px var(--luminous-teal); animation: pulse 2s infinite; display: inline-block; }
        .terminal-teal-text { color: var(--luminous-teal); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 15px; }
        .terminal-teal-text strong { letter-spacing: 2px; font-weight: 800; }

        html.light-mode .terminal-teal-text, html.light-mode .pulse-dot { color: #2D6A4F; }
        html.light-mode .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.4); }

        /* Unified Button Architecture */
        .terminal-action-btn { display: inline-block; padding: 16px 24px; background-color: var(--luminous-teal) !important; color: var(--midnight-navy) !important; font-weight: bold; font-family: 'Inter', sans-serif; font-size: 0.9rem; cursor: pointer; border: none; border-radius: 4px; transition: all 0.3s ease; text-decoration: none; text-transform: uppercase; text-align: center; }
        .terminal-action-btn:hover { background-color: var(--stark-white) !important; box-shadow: 0 0 20px rgba(255, 255, 255, 0.4); transform: translateY(-2px); }
        
        .btn-outline { background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color); text-decoration: none; padding: 16px 35px; display: inline-flex; align-items: center; gap: 15px; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; transition: all 0.3s ease; border-radius: 2px; cursor: pointer; backdrop-filter: blur(10px); }
        .btn-outline:hover { background: var(--accent-color); color: var(--midnight-navy); box-shadow: 0 0 30px rgba(0, 229, 255, 0.4); }
        html.light-mode .btn-outline { border-color: var(--midnight-navy); color: var(--midnight-navy); }
        html.light-mode .btn-outline:hover { background-color: var(--midnight-navy); color: var(--stark-white); }
        
        .prompt-arrow { color: var(--luminous-teal); animation: blink 1s infinite; }
        .btn-outline:hover .prompt-arrow { color: var(--midnight-navy); }

        /* Scroll Reveal */
        .reveal { opacity: 0; transform: translateY(40px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .reveal.active { opacity: 1; transform: translateY(0); }

        /* --- SECTION 1: HERO --- */
        .hero-section { position: relative; height: 100vh; display: flex; align-items: center; justify-content: center; padding-top: 80px; overflow: hidden; }
        .hero-bg, .scanline-overlay, .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .hero-bg { background-image: url('https://images.unsplash.com/photo-1552664730-d307ca884978?q=80&w=2000&auto=format&fit=crop'); background-size: cover; background-position: center; z-index: -3; filter: grayscale(100%) brightness(0.5); }
        .hero-overlay { background: linear-gradient(180deg, rgba(10,17,40,0.85) 0%, var(--midnight-navy) 100%); z-index: -2; }
        html.light-mode .hero-overlay { background: linear-gradient(180deg, rgba(255,255,255,0.9) 0%, var(--stark-white) 100%); }
        .scanline-overlay { background: linear-gradient(to bottom, transparent 50%, rgba(0, 229, 255, 0.03) 51%); background-size: 100% 4px; z-index: -1; pointer-events: none; }
        
        .hero-content { position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; max-width: 900px; width: 100%; text-align: center; }
        .tech-badge { display: inline-flex; align-items: center; gap: 8px; font-size: 0.65rem; font-weight: 700; letter-spacing: 2px; margin-bottom: 15px; padding: 5px 12px; background: rgba(0, 229, 255, 0.05); border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 20px; color: var(--luminous-teal); }
        html.light-mode .tech-badge { color: #1E3A8A; border-color: rgba(30, 58, 138, 0.2); }
        
        .brand-title { font-size: clamp(3rem, 6vw, 5.5rem); font-weight: 900; letter-spacing: -2px; line-height: 1.1; margin-bottom: 5px; background: linear-gradient(to right, #FFFFFF, #8A9bb2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        html.light-mode .brand-title { background: linear-gradient(to right, var(--midnight-navy), #4A5568); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .brand-slogan { font-size: clamp(1rem, 2vw, 1.5rem); color: var(--luminous-teal); letter-spacing: 4px; margin-bottom: 25px; font-weight: 400; text-transform: uppercase; }
        html.light-mode .brand-slogan { color: var(--accent-color); }
        .hero-desc { font-size: clamp(0.95rem, 1.2vw, 1.1rem); max-width: 750px; margin-bottom: 40px; padding: 0 15px; color: var(--text-secondary); line-height: 1.7; }

        /* --- SECTION 2: THE OPEN DOOR (ROLES) --- */
        .dna-section { padding: 120px 0; position: relative; }
        .roles-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; position: relative; z-index: 2; }
        
        .role-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-top: 3px solid var(--accent-color); padding: 40px 30px; border-radius: 6px; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        html.light-mode .role-card { background: #FFFFFF; border-color: rgba(10,17,40,0.1); border-top-color: var(--midnight-navy); box-shadow: 0 15px 30px rgba(10,17,40,0.05); }
        .role-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.4); border-color: var(--accent-color); background: rgba(0, 229, 255, 0.03); }
        html.light-mode .role-card:hover { border-color: var(--midnight-navy); background: rgba(10, 17, 40, 0.02); box-shadow: 0 20px 40px rgba(10,17,40,0.1); }

        .role-card h3 { font-family: 'Roboto Mono', monospace; font-size: 1.2rem; color: var(--luminous-teal); margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed rgba(0, 229, 255, 0.3); display: block; letter-spacing: 1px; }
        html.light-mode .role-card h3 { color: var(--midnight-navy); border-bottom-color: rgba(10,17,40,0.2); }
        .role-card p { font-size: 1rem; color: var(--text-secondary); line-height: 1.7; margin: 0; }

        /* --- SECTION 3: THE DOSSIER VAULT (FORM) --- */
        .intake-section { padding: 80px 0 120px 0; position: relative; background-image: linear-gradient(var(--border-color) 1px, transparent 1px), linear-gradient(90deg, var(--border-color) 1px, transparent 1px); background-size: 50px 50px; background-position: center; }
        .intake-section::before { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at center, transparent 0%, var(--midnight-navy) 90%); z-index: 0; }
        html.light-mode .intake-section::before { background: radial-gradient(circle at center, transparent 0%, var(--stark-white) 90%); }
        
        .intake-matrix { background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(25px); border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 8px; padding: 60px; width: 100%; max-width: 900px; margin: 0 auto; position: relative; z-index: 10; box-shadow: 0 30px 60px rgba(0,0,0,0.6), inset 0 0 30px rgba(0, 229, 255, 0.02); overflow: hidden; }
        html.light-mode .intake-matrix { background: rgba(255, 255, 255, 0.95); border-color: rgba(10,17,40,0.1); box-shadow: 0 30px 60px rgba(10,17,40,0.05); }
        
        /* The Scanning Form Laser */
        .intake-matrix::after { content: ""; position: absolute; top: -5%; left: 0; width: 100%; height: 3px; background: var(--luminous-teal); box-shadow: 0 0 15px var(--luminous-teal); z-index: 100; opacity: 0; pointer-events: none; animation: periodicScan 12s infinite linear; }
        html.light-mode .intake-matrix::after { background: #1E3A8A; box-shadow: 0 0 15px #1E3A8A; }

        .form-section-title { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--luminous-teal); letter-spacing: 2px; margin-bottom: 25px; border-bottom: 1px solid rgba(0, 229, 255, 0.2); padding-bottom: 10px; margin-top: 40px; }
        html.light-mode .form-section-title { color: var(--midnight-navy); border-bottom-color: rgba(10, 17, 40, 0.1); }
        .form-section-title:first-child { margin-top: 0; }

        .input-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        .input-group { display: flex; flex-direction: column; gap: 8px; position: relative; }
        
        .matrix-label { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: 600; color: var(--stark-white); letter-spacing: 0.5px; }
        html.light-mode .matrix-label { color: var(--midnight-navy); }
        
        .matrix-input, .matrix-select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--stark-white); padding: 16px 20px; font-family: 'Inter', sans-serif; font-size: 0.95rem; border-radius: 4px; outline: none; transition: all 0.3s ease; appearance: none; -webkit-appearance: none; }
        html.light-mode .matrix-input, html.light-mode .matrix-select { background: rgba(0,0,0,0.02); color: var(--midnight-navy); border-color: rgba(10,17,40,0.15); }
        .matrix-input:focus, .matrix-select:focus { border-color: var(--accent-color); box-shadow: inset 0 0 10px rgba(0,229,255,0.1); }
        
        .matrix-select { cursor: pointer; }
        .matrix-select option { background-color: #0A1128; color: var(--stark-white); padding: 10px; }
        html.light-mode .matrix-select option { background-color: var(--stark-white); color: var(--midnight-navy); }
        .select-arrow { position: absolute; right: 20px; top: 40px; color: var(--accent-color); pointer-events: none; font-size: 0.8rem; font-family: 'Roboto Mono', monospace; }
        html.light-mode .select-arrow { color: var(--midnight-navy); }
        
        .phone-group { display: flex; gap: 10px; }
        textarea.matrix-input { resize: vertical; min-height: 140px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; line-height: 1.6; }

        /* UPLOAD ZONE */
        .upload-zone { width: 100%; border: 2px dashed rgba(0, 229, 255, 0.3); background: rgba(0, 229, 255, 0.02); padding: 40px 20px; text-align: center; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; position: relative; margin-bottom: 20px; }
        html.light-mode .upload-zone { background: rgba(10,17,40,0.02); border-color: rgba(10,17,40,0.2); }
        .upload-zone:hover { border-color: var(--accent-color); background: rgba(0, 229, 255, 0.08); }
        html.light-mode .upload-zone:hover { background: rgba(10,17,40,0.05); border-color: var(--midnight-navy); }
        
        .upload-icon { font-size: 2rem; color: var(--accent-color); margin-bottom: 15px; opacity: 0.7; }
        .upload-zone:hover .upload-icon { opacity: 1; transform: translateY(-2px); transition: all 0.3s ease; }
        html.light-mode .upload-icon { color: var(--midnight-navy); }
        .upload-text { font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--stark-white); font-weight: bold; }
        html.light-mode .upload-text { color: var(--midnight-navy); }
        .upload-subtext { font-size: 0.75rem; color: var(--text-secondary); margin-top: 8px; font-family: 'Roboto Mono', monospace; }
        
        .error-text { color: var(--terminal-red); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; margin-top: 5px; display: block; }

        /* THE DOSSIER SYSTEM LOG */
        .system-log-console { margin-top: 25px; padding: 15px; background: rgba(0,0,0,0.4); border-left: 2px solid var(--accent-color); font-family: 'Roboto Mono', monospace; border-radius: 0 4px 4px 0; }
        html.light-mode .system-log-console { background: rgba(10, 17, 40, 0.05); border-left-color: var(--midnight-navy); }
        .log-text { font-size: 0.7rem; color: var(--accent-color); opacity: 0.8; transition: opacity 0.2s ease; }
        html.light-mode .log-text { color: var(--midnight-navy); font-weight: bold; }

        /* SUCCESS OVERLAY */
        .success-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10, 17, 40, 0.98); backdrop-filter: blur(15px); z-index: 2000; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.4s ease; border-radius: 8px; }
        html.light-mode .success-overlay { background: rgba(255, 255, 255, 0.98); }
        .success-overlay.active { opacity: 1; visibility: visible; }
        .success-modal { text-align: center; max-width: 600px; width: 90%; padding: 40px; }
        .check-icon { width: 80px; height: 80px; background: var(--accent-color); color: var(--midnight-navy); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; margin: 0 auto 30px; box-shadow: 0 0 30px rgba(0, 229, 255, 0.3); }

        .loader-ring { width: 24px; height: 24px; border: 3px solid rgba(10,17,40,0.2); border-top-color: var(--midnight-navy); border-radius: 50%; animation: spin 0.6s linear infinite; margin: 0 auto; }
        html.light-mode .loader-ring { border-color: rgba(255,255,255,0.3); border-top-color: var(--stark-white); }

        /* ==========================================================================
           MOBILE RESPONSIVE MASTER
           ========================================================================== */
        @media (max-width: 850px) {
            .container { padding-left: 20px !important; padding-right: 20px !important; }
            section { padding: 60px 0 !important; }
            
            /* Strict Left-Alignment */
            .section-header { text-align: left !important; }
            .section-header p { margin-left: 0 !important; margin-right: 0 !important; text-align: left !important; }
            .terminal-teal-text { justify-content: flex-start !important; text-align: left !important; margin-left: 0 !important; }
            
            .hero-content { text-align: center !important; }
            
            .roles-grid { grid-template-columns: 1fr; gap: 20px; }
            .input-grid-2 { grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
            .intake-matrix { padding: 30px 20px; }
            
            .phone-group { flex-direction: row; } /* Keeps phone dropdown inline on mobile */
            .select-arrow { top: 38px; } /* Adjusts arrow position for stacked inputs */
        }
        
        /* Upload Animated Dots */
        @keyframes loadingDots {
            0% { content: ''; }
            25% { content: '.'; }
            50% { content: '..'; }
            75% { content: '...'; }
            100% { content: ''; }
        }
        .animated-dots::after {
            content: '';
            animation: loadingDots 1.5s infinite;
            display: inline-block;
            width: 20px; /* Prevents text jumping */
            text-align: left;
        }

        /* Subtle System Toast */
        .terminal-toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: rgba(10, 17, 40, 0.95);
            border: 1px solid rgba(0, 229, 255, 0.3);
            color: var(--luminous-teal);
            padding: 12px 24px;
            font-family: 'Roboto Mono', monospace;
            font-size: 0.75rem;
            letter-spacing: 1px;
            border-radius: 4px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            transform: translateY(20px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        html.light-mode .terminal-toast {
            background: var(--stark-white);
            border-color: rgba(10, 17, 40, 0.2);
            color: var(--midnight-navy);
            box-shadow: 0 10px 30px rgba(10, 17, 40, 0.1);
        }
        .terminal-toast.active {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
    </style>
    @endpush

    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="scanline-overlay"></div>
        <div class="hero-overlay"></div>
        
        <div class="container hero-content text-center reveal active">
            <div class="tech-badge mono-font">
                <span class="pulse-dot"></span> // TALENT ACQUISITION
            </div>
            
            <h1 class="brand-title">JOIN THE ARCHITECTURE</h1>
            <h2 class="brand-slogan mono-font">WE ARE A STRIKE TEAM</h2>
            
            <p class="hero-desc">We do not hire employees. We draft architects. JBS Praxis is the premier Management Consulting and Systems Engineering firm in the Pan-African enterprise sector.</p>
            
            <div class="nexus-wrapper">
                <button class="btn-outline" onclick="window.scrollTo({top: document.querySelector('.intake-section').offsetTop - 80, behavior: 'smooth'})">
                    INITIALIZE DRAFT <span class="mono-font prompt-arrow">>_</span>
                </button>
            </div>
        </div>
    </section>

    <section class="dna-section container reveal" id="roles">
        <div class="section-header text-center">
            <h4 class="terminal-teal-text mono-font">
                <span class="pulse-dot"></span> <strong>// THE OPEN DOOR POLICY</strong>
            </h4>
            <h2>Who We Keep In Our Pool</h2>
            <p style="max-width: 800px; margin: 0 auto; margin-top: 15px;">Elite talent does not always fit into a predefined job description. If you are looking for a standard 9-to-5 where you can hide in a cubicle, close this page. If you are a visionary, we want your data in our intelligence pool.</p>
        </div>

        <div class="roles-grid">
            <div class="role-card">
                <h3>THE BUILDERS</h3>
                <p>You do not just write code; you engineer logic. You understand that a beautiful frontend is useless without a zero-latency PostgreSQL vault backing it.</p>
            </div>
            <div class="role-card">
                <h3>THE ENFORCERS</h3>
                <p>You see the world in workflows, SOPs, and risk matrices. You know how to plug financial leaks, enforce ISO compliance, and command a boardroom.</p>
            </div>
            <div class="role-card">
                <h3>THE TRANSLATORS</h3>
                <p>You can sit with a CEO in the morning to discuss business strategy, and sit with a Full-Stack developer in the afternoon to translate that strategy into API endpoints.</p>
            </div>
        </div>
    </section>

    <section class="intake-section" id="dossier-form">
        <div class="container reveal">
            <div class="intake-matrix" x-data="{ primary: @entangle('primaryWeapon') }">
                
                <div class="success-overlay {{ $isSuccess ? 'active' : '' }}">
                    <div class="success-modal">
                        <div class="check-icon">✓</div>
                        <h2 style="margin-bottom: 15px; font-size: 2rem; color: var(--stark-white);">Dossier Secured.</h2>
                        <p style="color: var(--text-secondary); margin-bottom: 35px; font-size: 1.05rem; line-height: 1.7;">
                            You are now logged in the JBS Praxis Talent Vault. If your architecture matches an active enterprise deployment, the Strike Team will initiate contact.
                        </p>
                        <button class="terminal-action-btn" onclick="window.location.href='/'" style="width: 100%;">[ RETURN TO TERMINAL ]</button>
                    </div>
                </div>

                <div class="section-header text-center" style="margin-bottom: 40px;">
                    <h2 style="font-size: 2.2rem; margin-bottom: 10px;">SUBMIT YOUR DOSSIER</h2>
                    <p style="color: var(--text-secondary); font-size: 0.95rem;">Enter your intelligence into the Praxis Talent Vault.</p>
                </div>
                
                <form wire:submit.prevent="transmitDossier">
                    
                    <div class="form-section-title">> PART 1: PERSONAL INTELLIGENCE</div>
                    <div class="input-grid-2">
                        <div class="input-group">
                            <label class="matrix-label">FULL NAME *</label>
                            <input type="text" class="matrix-input" wire:model="fullName" placeholder="e.g. John Doe" onfocus="updateDossierLog('VERIFYING_IDENTITY_PROTOCOL')" required>
                            @error('fullName') <span class="error-text">> {{ $message }}</span> @enderror
                        </div>
                        <div class="input-group">
                            <label class="matrix-label">EMAIL ADDRESS *</label>
                            <input type="email" class="matrix-input" wire:model="email" placeholder="architect@domain.com" onfocus="updateDossierLog('ESTABLISHING_SECURE_COMMS')" required>
                            @error('email') <span class="error-text">> {{ $message }}</span> @enderror
                        </div>
                    </div>
                    
                    <div class="input-grid-2">
                        <div class="input-group">
                            <label class="matrix-label">DIRECT PHONE / WHATSAPP</label>
                            <div class="phone-group">
                                <div style="position: relative; width: 130px; flex-shrink: 0;">
                                    <select class="matrix-select" wire:model="countryCode" style="padding-right: 25px;" onfocus="updateDossierLog('CALIBRATING_GEO_LOCATION')">
                                        <option value="+234">🇳🇬 +234</option>
                                        <option disabled>──────────</option>
                                        <option value="+93">🇦🇫 +93</option>
                                        <option value="+355">🇦🇱 +355</option>
                                        <option value="+213">🇩🇿 +213</option>
                                        <option value="+376">🇦🇩 +376</option>
                                        <option value="+244">🇦🇴 +244</option>
                                        <option value="+54">🇦🇷 +54</option>
                                        <option value="+374">🇦🇲 +374</option>
                                        <option value="+61">🇦🇺 +61</option>
                                        <option value="+43">🇦🇹 +43</option>
                                        <option value="+994">🇦🇿 +994</option>
                                        <option value="+973">🇧🇭 +973</option>
                                        <option value="+880">🇧🇩 +880</option>
                                        <option value="+375">🇧🇾 +375</option>
                                        <option value="+32">🇧🇪 +32</option>
                                        <option value="+501">🇧🇿 +501</option>
                                        <option value="+229">🇧🇯 +229</option>
                                        <option value="+975">🇧🇹 +975</option>
                                        <option value="+591">🇧🇴 +591</option>
                                        <option value="+387">🇧🇦 +387</option>
                                        <option value="+267">🇧🇼 +267</option>
                                        <option value="+55">🇧🇷 +55</option>
                                        <option value="+673">🇧🇳 +673</option>
                                        <option value="+359">🇧🇬 +359</option>
                                        <option value="+226">🇧🇫 +226</option>
                                        <option value="+257">🇧🇮 +257</option>
                                        <option value="+855">🇰🇭 +855</option>
                                        <option value="+237">🇨🇲 +237</option>
                                        <option value="+1">🇨🇦 +1</option>
                                        <option value="+238">🇨🇻 +238</option>
                                        <option value="+236">🇨🇫 +236</option>
                                        <option value="+235">🇹🇩 +235</option>
                                        <option value="+56">🇨🇱 +56</option>
                                        <option value="+86">🇨🇳 +86</option>
                                        <option value="+57">🇨🇴 +57</option>
                                        <option value="+269">🇰🇲 +269</option>
                                        <option value="+242">🇨🇬 +242</option>
                                        <option value="+243">🇨🇩 +243</option>
                                        <option value="+506">🇨🇷 +506</option>
                                        <option value="+385">🇭🇷 +385</option>
                                        <option value="+53">🇨🇺 +53</option>
                                        <option value="+357">🇨🇾 +357</option>
                                        <option value="+420">🇨🇿 +420</option>
                                        <option value="+45">🇩🇰 +45</option>
                                        <option value="+253">🇩🇯 +253</option>
                                        <option value="+1809">🇩🇴 +1809</option>
                                        <option value="+593">🇪🇨 +593</option>
                                        <option value="+20">🇪🇬 +20</option>
                                        <option value="+503">🇸🇻 +503</option>
                                        <option value="+240">🇬🇶 +240</option>
                                        <option value="+291">🇪🇷 +291</option>
                                        <option value="+372">🇪🇪 +372</option>
                                        <option value="+251">🇪🇹 +251</option>
                                        <option value="+679">🇫🇯 +679</option>
                                        <option value="+358">🇫🇮 +358</option>
                                        <option value="+33">🇫🇷 +33</option>
                                        <option value="+241">🇬🇦 +241</option>
                                        <option value="+220">🇬🇲 +220</option>
                                        <option value="+995">🇬🇪 +995</option>
                                        <option value="+49">🇩🇪 +49</option>
                                        <option value="+233">🇬🇭 +233</option>
                                        <option value="+30">🇬🇷 +30</option>
                                        <option value="+502">🇬🇹 +502</option>
                                        <option value="+224">🇬🇳 +224</option>
                                        <option value="+245">🇬🇼 +245</option>
                                        <option value="+592">🇬🇾 +592</option>
                                        <option value="+509">🇭🇹 +509</option>
                                        <option value="+504">🇭🇳 +504</option>
                                        <option value="+36">🇭🇺 +36</option>
                                        <option value="+354">🇮🇸 +354</option>
                                        <option value="+91">🇮🇳 +91</option>
                                        <option value="+62">🇮🇩 +62</option>
                                        <option value="+98">🇮🇷 +98</option>
                                        <option value="+964">🇮🇶 +964</option>
                                        <option value="+353">🇮🇪 +353</option>
                                        <option value="+972">🇮🇱 +972</option>
                                        <option value="+39">🇮🇹 +39</option>
                                        <option value="+225">🇨🇮 +225</option>
                                        <option value="+1876">🇯🇲 +1876</option>
                                        <option value="+81">🇯🇵 +81</option>
                                        <option value="+962">🇯🇴 +962</option>
                                        <option value="+7">🇰🇿 +7</option>
                                        <option value="+254">🇰🇪 +254</option>
                                        <option value="+686">🇰🇮 +686</option>
                                        <option value="+965">🇰🇼 +965</option>
                                        <option value="+996">🇰🇬 +996</option>
                                        <option value="+856">🇱🇦 +856</option>
                                        <option value="+371">🇱🇻 +371</option>
                                        <option value="+961">🇱🇧 +961</option>
                                        <option value="+266">🇱🇸 +266</option>
                                        <option value="+231">🇱🇷 +231</option>
                                        <option value="+218">🇱🇾 +218</option>
                                        <option value="+423">🇱🇮 +423</option>
                                        <option value="+370">🇱🇹 +370</option>
                                        <option value="+352">🇱🇺 +352</option>
                                        <option value="+853">🇲🇴 +853</option>
                                        <option value="+389">🇲🇰 +389</option>
                                        <option value="+261">🇲🇬 +261</option>
                                        <option value="+265">🇲🇼 +265</option>
                                        <option value="+60">🇲🇾 +60</option>
                                        <option value="+960">🇲🇻 +960</option>
                                        <option value="+223">🇲🇱 +223</option>
                                        <option value="+356">🇲🇹 +356</option>
                                        <option value="+692">🇲🇭 +692</option>
                                        <option value="+222">🇲🇷 +222</option>
                                        <option value="+230">🇲🇺 +230</option>
                                        <option value="+52">🇲🇽 +52</option>
                                        <option value="+691">🇫🇲 +691</option>
                                        <option value="+373">🇲🇩 +373</option>
                                        <option value="+377">🇲🇨 +377</option>
                                        <option value="+976">🇲🇳 +976</option>
                                        <option value="+382">🇲🇪 +382</option>
                                        <option value="+212">🇲🇦 +212</option>
                                        <option value="+258">🇲🇿 +258</option>
                                        <option value="+95">🇲🇲 +95</option>
                                        <option value="+264">🇳🇦 +264</option>
                                        <option value="+674">🇳🇷 +674</option>
                                        <option value="+977">🇳🇵 +977</option>
                                        <option value="+31">🇳🇱 +31</option>
                                        <option value="+64">🇳🇿 +64</option>
                                        <option value="+505">🇳🇮 +505</option>
                                        <option value="+227">🇳🇪 +227</option>
                                        <option value="+850">🇰🇵 +850</option>
                                        <option value="+47">🇳🇴 +47</option>
                                        <option value="+968">🇴🇲 +968</option>
                                        <option value="+92">🇵🇰 +92</option>
                                        <option value="+680">🇵🇼 +680</option>
                                        <option value="+970">🇵🇸 +970</option>
                                        <option value="+507">🇵🇦 +507</option>
                                        <option value="+675">🇵🇬 +675</option>
                                        <option value="+595">🇵🇾 +595</option>
                                        <option value="+51">🇵🇪 +51</option>
                                        <option value="+63">🇵🇭 +63</option>
                                        <option value="+48">🇵🇱 +48</option>
                                        <option value="+351">🇵🇹 +351</option>
                                        <option value="+974">🇶🇦 +974</option>
                                        <option value="+40">🇷🇴 +40</option>
                                        <option value="+7">🇷🇺 +7</option>
                                        <option value="+250">🇷🇼 +250</option>
                                        <option value="+685">🇼🇸 +685</option>
                                        <option value="+378">🇸🇲 +378</option>
                                        <option value="+239">🇸🇹 +239</option>
                                        <option value="+966">🇸🇦 +966</option>
                                        <option value="+221">🇸🇳 +221</option>
                                        <option value="+381">🇷🇸 +381</option>
                                        <option value="+248">🇸🇨 +248</option>
                                        <option value="+232">🇸🇱 +232</option>
                                        <option value="+65">🇸🇬 +65</option>
                                        <option value="+421">🇸🇰 +421</option>
                                        <option value="+386">🇸🇮 +386</option>
                                        <option value="+677">🇸🇧 +677</option>
                                        <option value="+252">🇸🇴 +252</option>
                                        <option value="+27">🇿🇦 +27</option>
                                        <option value="+82">🇰🇷 +82</option>
                                        <option value="+34">🇪🇸 +34</option>
                                        <option value="+94">🇱🇰 +94</option>
                                        <option value="+249">🇸🇩 +249</option>
                                        <option value="+597">🇸🇷 +597</option>
                                        <option value="+268">🇸🇿 +268</option>
                                        <option value="+46">🇸🇪 +46</option>
                                        <option value="+41">🇨🇭 +41</option>
                                        <option value="+963">🇸🇾 +963</option>
                                        <option value="+886">🇹🇼 +886</option>
                                        <option value="+992">🇹🇯 +992</option>
                                        <option value="+255">🇹🇿 +255</option>
                                        <option value="+66">🇹🇭 +66</option>
                                        <option value="+228">🇹🇬 +228</option>
                                        <option value="+676">🇹🇴 +676</option>
                                        <option value="+1868">🇹🇹 +1868</option>
                                        <option value="+216">🇹🇳 +216</option>
                                        <option value="+90">🇹🇷 +90</option>
                                        <option value="+993">🇹🇲 +993</option>
                                        <option value="+688">🇹🇻 +688</option>
                                        <option value="+256">🇺🇬 +256</option>
                                        <option value="+380">🇺🇦 +380</option>
                                        <option value="+971">🇦🇪 +971</option>
                                        <option value="+44">🇬🇧 +44</option>
                                        <option value="+1">🇺🇸 +1</option>
                                        <option value="+598">🇺🇾 +598</option>
                                        <option value="+998">🇺🇿 +998</option>
                                        <option value="+678">🇻🇺 +678</option>
                                        <option value="+379">🇻🇦 +379</option>
                                        <option value="+58">🇻🇪 +58</option>
                                        <option value="+84">🇻🇳 +84</option>
                                        <option value="+967">🇾🇪 +967</option>
                                        <option value="+260">🇿🇲 +260</option>
                                        <option value="+263">🇿🇼 +263</option>
                                    </select>
                                    <div class="select-arrow" style="top: 50%; right: 15px;">▼</div>
                                </div>
                                <input type="tel" class="matrix-input" wire:model="phone" placeholder="Phone Number" style="flex: 1;" onfocus="updateDossierLog('ROUTING_COMM_CHANNEL')">
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="matrix-label">CURRENT CITY, COUNTRY</label>
                            <div wire:ignore style="width: 100%;">
                                <input type="text" id="city_autocomplete" class="matrix-input" placeholder="e.g. Lagos, Nigeria" onfocus="updateDossierLog('MAPPING_OPERATIONAL_BASE')">
                            </div>
                            <input type="hidden" id="city_hidden" wire:model="city">
                        </div>
                    </div>

                    <div class="form-section-title">> PART 2: PROFESSIONAL COORDINATES</div>
                    <div class="input-grid-2">
                        <div class="input-group">
                            <label class="matrix-label">PRIMARY WEAPON (CORE DISCIPLINE) *</label>
                            <div style="position: relative;">
                                <select class="matrix-select" wire:model="primaryWeapon" onfocus="updateDossierLog('ANALYZING_CORE_CAPABILITIES')" required>
                                    <option value="" disabled selected>Select Designation...</option>
                                    <option value="AI & RAG Systems Architecture">AI & RAG Systems Architecture</option>
                                    <option value="Core Infrastructure & Full-Stack">Core Infrastructure & Full-Stack</option>
                                    <option value="Data Vault & Intelligence Architecture">Data Vault & Intelligence Architecture</option>
                                    <option value="Cybersecurity & Threat Mitigation">Cybersecurity & Threat Mitigation</option>
                                    <option value="Cloud Operations & Deployment (DevOps)">Cloud Operations & Deployment (DevOps)</option>
                                    <option value="Enterprise Strategy & Audit Operations">Enterprise Strategy & Audit Operations</option>
                                    <option value="Human-Computer Interface (HCI) Design">Human-Computer Interface (HCI) Design</option>
                                    <option value="Tactical Operations & Delivery (PM)">Tactical Operations & Delivery (PM)</option>
                                    <option value="other">Unlisted / Specialty Discipline</option>
                                </select>
                                <div class="select-arrow" style="top: 50%;">▼</div>
                            </div>
                            @error('primaryWeapon') <span class="error-text">> {{ $message }}</span> @enderror
                        </div>
                        <div class="input-group">
                            <label class="matrix-label">SECONDARY SKILLSET</label>
                            <input type="text" class="matrix-input" wire:model="secondarySkillset" placeholder="What else can you do?" onfocus="updateDossierLog('LOGGING_AUXILIARY_SKILLS')">
                        </div>
                    </div>
                    
                    <div class="input-group" x-show="primary === 'other'" x-transition style="margin-top: -15px; margin-bottom: 25px;">
                        <input type="text" class="matrix-input" wire:model="otherWeapon" placeholder="> Specify your primary weapon..." onfocus="updateDossierLog('OVERRIDING_STANDARD_PROTOCOLS')">
                    </div>

                    <div class="input-grid-2">
                        <div class="input-group">
                            <label class="matrix-label">LINKEDIN PROFILE URL *</label>
                            <input type="url" class="matrix-input" wire:model="linkedinUrl" placeholder="https://linkedin.com/in/..." onfocus="updateDossierLog('INITIATING_NETWORK_SCAN')" required>
                            @error('linkedinUrl') <span class="error-text">> {{ $message }}</span> @enderror
                        </div>
                        <div class="input-group">
                            <label class="matrix-label">PORTFOLIO / GITHUB URL</label>
                            <input type="url" class="matrix-input" wire:model="portfolioUrl" placeholder="https://github.com/..." onfocus="updateDossierLog('ACCESSING_CODE_REPOSITORIES')">
                        </div>
                    </div>

                    <div class="form-section-title">> PART 3: THE PRAXIS QUESTION</div>
                    <div class="input-group" style="margin-bottom: 30px;">
                        <label class="matrix-label" style="margin-bottom: 5px;">Describe a broken system you fixed, or a complex problem you engineered a solution for. *</label>
                        <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 15px; font-family: 'Roboto Mono', monospace;">(Be concise. Show us your operational logic. Max 1000 chars).</p>
                        <textarea class="matrix-input" wire:model="praxisAnswer" placeholder="> Enter your logic here..." maxlength="1000" onfocus="updateDossierLog('RECORDING_ARCHITECTURAL_LOGIC')" required></textarea>
                        @error('praxisAnswer') <span class="error-text">> {{ $message }}</span> @enderror
                    </div>

                    <div class="form-section-title">> PART 4: ASSET UPLOAD</div>
                    
                    <div x-data="{ isUploading: false, showToast: false }" 
                         x-on:livewire-upload-start="isUploading = true" 
                         x-on:livewire-upload-finish="isUploading = false; showToast = true; setTimeout(() => showToast = false, 4000)"
                         x-on:livewire-upload-error="isUploading = false"
                         style="position: relative;">

                        <div class="upload-zone" onclick="document.getElementById('resumeUpload').click()" onmouseenter="updateDossierLog('PREPARING_SECURE_FILE_TRANSFER')">
                            <div class="upload-icon">📁</div>
                            
                            <div class="upload-text" style="color: {{ $resume ? 'var(--luminous-teal)' : 'inherit' }}">
                                {{ $resume ? '> ASSET SECURED: ' . $resume->getClientOriginalName() : 'CLICK TO UPLOAD DOSSIER (PDF/DOC) *' }}
                            </div>
                            <div class="upload-subtext">(MAX SIZE: 5MB)</div>
                            
                            <input type="file" id="resumeUpload" wire:model="resume" accept=".pdf,.doc,.docx" required
                                   style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0;">
                            
                            <div x-show="isUploading" style="display: none; margin-top: 15px;">
                                <div class="terminal-teal-text mono-font" style="margin-bottom: 0;">
                                    > UPLOADING ASSET<span class="animated-dots"></span>
                                </div>
                            </div>
                        </div>

                        <div class="terminal-toast" :class="{ 'active': showToast }">
                            <span class="pulse-dot" style="width: 6px; height: 6px;"></span> 
                            <span>SYSTEM: ASSET UPLOADED SUCCESSFULLY</span>
                        </div>
                    </div>

                    <button type="submit" class="terminal-action-btn" style="width: 100%; position: relative;" onclick="submitDossierFX(event)">
                        <span id="submitBtnText" wire:loading.remove wire:target="transmitDossier">TRANSMIT DOSSIER TO THE VAULT</span>
                        <div wire:loading.block wire:target="transmitDossier" class="loader-ring"></div>
                    </button>

                    <div class="system-log-console">
                        <div id="dossierSystemLog" class="log-text">
                            > AWAITING INTELLIGENCE INPUT...
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </section>

    <script>
        // Scroll Reveal Logic
        document.addEventListener('livewire:navigated', () => {
            const reveals = document.querySelectorAll('.reveal');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                    }
                });
            }, { threshold: 0.1 });
            reveals.forEach(reveal => observer.observe(reveal));
        });

        // The Terminal Gamification Engine
        window.updateDossierLog = function(message) {
            const log = document.getElementById('dossierSystemLog');
            if(!log) return;
            log.style.opacity = '0.4';
            setTimeout(() => {
                log.innerText = `> ${message}... OK.`;
                log.style.opacity = '1';
            }, 100);
        };

        // Pre-Submit UX Animation
        window.submitDossierFX = function(e) {
            const btn = e.currentTarget;
            const btnText = document.getElementById('submitBtnText');
            // Allow Livewire to process, just update UI
            if(btnText) btnText.innerText = 'ENCRYPTING DOSSIER...';
            updateDossierLog('TRANSMITTING_TO_STRIKE_COMMAND');
        };
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=***REMOVED***&libraries=places&callback=initVaultMap"></script>

    <style>
        /* Force Google Places Dropdown to match the Praxis Terminal UI */
        .pac-container {
            background-color: rgba(10, 17, 40, 0.95);
            border: 1px solid var(--luminous-teal);
            font-family: 'Roboto Mono', monospace;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
            border-radius: 4px;
            margin-top: 5px;
        }
        .pac-item {
            color: var(--text-secondary);
            border-top: 1px solid rgba(0, 229, 255, 0.1);
            padding: 10px;
            cursor: pointer;
        }
        .pac-item:hover, .pac-item-selected {
            background-color: rgba(0, 229, 255, 0.1);
        }
        .pac-item-query {
            color: var(--stark-white);
            font-weight: bold;
        }
        /* Hide the Google logo to keep the terminal look clean */
        .pac-logo::after { display: none; }
    </style>

    <script>
        function initVaultMap() {
            const input = document.getElementById('city_autocomplete');
            const hiddenInput = document.getElementById('city_hidden');
            
            // Initialize but prevent it from firing immediately
            const autocomplete = new google.maps.places.Autocomplete(input, {
                types: ['(cities)'],
            });

            // Prevent Enter key from submitting the form when selecting a city
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); }
            });

            // 3-Character Constraint Logic
            input.addEventListener('input', function() {
                if (this.value.length < 3) {
                    // Suppress the dropdown if under 3 chars
                    const pacContainer = document.querySelector('.pac-container');
                    if(pacContainer) pacContainer.style.display = 'none';
                }
            });

            // Sync the selected place to Livewire's hidden input
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.formatted_address) {
                    hiddenInput.value = place.formatted_address;
                    // Trigger Livewire update
                    hiddenInput.dispatchEvent(new Event('input'));
                    updateDossierLog('GEO_COORDINATES_LOCKED');
                }
            });
        }
    </script>
    
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    requestAnimationFrame(() => {
                        const firstError = document.querySelector('.error-text');
                        
                        if (firstError) {
                            const container = firstError.closest('.input-group') || firstError.closest('.upload-zone');
                            
                            if (container) {
                                // 1. Calculate the exact position minus the header height
                                const headerHeight = 120; // <--- TWEAK THIS: Adjust to match your navbar's pixel height + some breathing room
                                const elementPosition = container.getBoundingClientRect().top;
                                const offsetPosition = elementPosition + window.scrollY - headerHeight;
                                
                                // 2. Smoothly scroll to the calculated, offset position
                                window.scrollTo({
                                    top: offsetPosition,
                                    behavior: 'smooth'
                                });
                                
                                // 3. Find the input and force focus
                                const input = container.querySelector('input:not([type="hidden"]), textarea, select');
                                if (input) {
                                    // Slight delay to let the smooth scroll finish
                                    setTimeout(() => {
                                        // preventScroll: true is CRITICAL here. 
                                        // It stops the browser's native focus from violently snapping the page back under the header.
                                        input.focus({ preventScroll: true }); 
                                        
                                        if (typeof updateDossierLog === 'function') {
                                            updateDossierLog('CORRECTING_INVALID_PARAMETERS');
                                        }
                                    }, 400);
                                }
                            }
                        }
                    });
                });
            });
        });
    </script>
    
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('dossier-transmitted', () => {
                // 1. Locate the Matrix Container
                const matrix = document.querySelector('.intake-matrix');
                if (!matrix) return;

                // 2. Calculate the perfect framing position (matching your header offset)
                const headerHeight = 120; 
                const elementPosition = matrix.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.scrollY - headerHeight;

                // 3. Smoothly pull the user back up to the top of the form
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });

                // 4. Force a fresh "Reveal" animation on the success modal
                const successModal = document.querySelector('.success-modal');
                if (successModal) {
                    // Reset its position instantly
                    successModal.style.opacity = '0';
                    successModal.style.transform = 'translateY(40px)';
                    
                    // Wait for the smooth scroll to almost finish, then float it up
                    setTimeout(() => {
                        successModal.style.transition = 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
                        successModal.style.opacity = '1';
                        successModal.style.transform = 'translateY(0)';
                    }, 400); 
                }
            });
        });
    </script>
    
</div>