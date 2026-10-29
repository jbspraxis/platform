<div>
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - PORTFOLIO MASTER CSS ARCHITECTURE 
           ========================================================================== */

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
        @keyframes blink-status { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        
        .section-header { margin-bottom: 60px; text-align: center; }
        .relative-z { position: relative; z-index: 10; }
        
        .pulse-dot { width: 6px; height: 6px; background-color: var(--luminous-teal); border-radius: 50%; box-shadow: 0 0 10px var(--luminous-teal); animation: pulse 2s infinite; display: inline-block; }
        .terminal-teal-text { color: var(--luminous-teal); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 15px; }
        .terminal-teal-text strong { letter-spacing: 2px; font-weight: 800; }

        html.light-mode .terminal-teal-text, html.light-mode .pulse-dot { color: #2D6A4F; }
        html.light-mode .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.4); }

        /* Unified Button Architecture */
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
        .hero-bg { background-image: url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2000&auto=format&fit=crop'); background-size: cover; background-position: center; z-index: -3; filter: grayscale(100%) brightness(0.4); }
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

        /* --- SECTION 2: DOSSIER VAULT --- */
        .dossier-wrapper { padding: 120px 0; position: relative; }
        
        .case-study { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-top: 3px solid var(--accent-color); border-radius: 6px; padding: 50px; margin-bottom: 60px; position: relative; transition: all 0.4s ease; }
        html.light-mode .case-study { background: #FFFFFF; border-color: rgba(10, 17, 40, 0.1); border-top-color: var(--midnight-navy); box-shadow: 0 20px 50px rgba(10, 17, 40, 0.05); }
        .case-study:hover { box-shadow: 0 30px 60px rgba(0,0,0,0.4); transform: translateY(-5px); border-color: var(--accent-color); background: rgba(0, 229, 255, 0.02); }
        html.light-mode .case-study:hover { box-shadow: 0 30px 60px rgba(10,17,40,0.1); border-color: var(--midnight-navy); background: rgba(10, 17, 40, 0.02); }

        .case-header { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 25px; }
        html.light-mode .case-header { border-bottom-color: rgba(10,17,40,0.1); }
        
        .client-tag { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--text-secondary); letter-spacing: 2px; display: block; margin-bottom: 10px; }
        .case-title { font-size: clamp(1.8rem, 3vw, 2.2rem); line-height: 1.2; color: var(--stark-white); max-width: 800px; letter-spacing: -1px; margin: 0; }
        html.light-mode .case-title { color: var(--midnight-navy); }
        
        .status-tag { display: inline-flex; align-items: center; gap: 8px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; padding: 8px 15px; background: rgba(0, 229, 255, 0.1); color: var(--accent-color); border-radius: 2px; letter-spacing: 1px; }
        html.light-mode .status-tag { background: rgba(10, 17, 40, 0.05); color: var(--midnight-navy); }
        .pulse-green { width: 8px; height: 8px; background: #00FF66; border-radius: 50%; box-shadow: 0 0 10px #00FF66; animation: blink-status 1.5s infinite; }
        html.light-mode .pulse-green { background: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.5); }

        .case-block h4 { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--luminous-teal); margin-bottom: 15px; border-bottom: 1px dashed rgba(0, 229, 255, 0.3); padding-bottom: 5px; display: inline-block; letter-spacing: 1px; }
        html.light-mode .case-block h4 { color: var(--midnight-navy); border-bottom-color: rgba(10,17,40,0.2); }
        .case-block p { font-size: 1.05rem; color: var(--text-secondary); line-height: 1.8; margin-bottom: 35px; }

        .roi-panel { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(0, 229, 255, 0.2); padding: 35px; border-radius: 4px; position: relative; overflow: hidden; box-shadow: inset 0 0 20px rgba(0, 229, 255, 0.02); }
        html.light-mode .roi-panel { background: rgba(243, 244, 246, 0.8); border-color: rgba(10, 17, 40, 0.1); box-shadow: inset 0 0 20px rgba(10, 17, 40, 0.02); }
        .roi-panel::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent-color); }
        html.light-mode .roi-panel::before { background: var(--midnight-navy); }
        
        .roi-stat { display: flex; align-items: baseline; gap: 12px; margin-bottom: 15px; }
        .roi-number { font-size: 4rem; font-weight: 900; line-height: 1; color: var(--stark-white); letter-spacing: -2px; }
        html.light-mode .roi-number { color: var(--midnight-navy); }
        .roi-metric { font-family: 'Roboto Mono', monospace; font-size: 0.95rem; color: var(--accent-color); text-transform: uppercase; font-weight: bold; }
        html.light-mode .roi-metric { color: var(--midnight-navy); }
        .roi-desc { font-size: 0.95rem !important; color: var(--text-secondary) !important; margin-bottom: 0 !important; line-height: 1.6; }

        .arch-tags { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px; }
        html.light-mode .arch-tags { border-top-color: rgba(10,17,40,0.1); }
        .arch-tag { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; padding: 6px 12px; background: rgba(255,255,255,0.05); color: var(--stark-white); border-radius: 2px; }
        html.light-mode .arch-tag { background: rgba(10,17,40,0.05); color: var(--midnight-navy); font-weight: bold; }

        /* --- SECTION 3: STAT INTERSTITIAL --- */
        .stat-interstitial { padding: 60px 0; margin: 40px 0 80px 0; position: relative; display: flex; align-items: center; justify-content: center; }
        .stat-interstitial::before { content: ''; position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: rgba(255, 51, 102, 0.3); z-index: 1; }
        html.light-mode .stat-interstitial::before { background: rgba(10, 17, 40, 0.2); }

        .stat-box { background: #050914; border: 1px solid var(--terminal-red); padding: 40px 50px; max-width: 850px; text-align: center; position: relative; z-index: 2; box-shadow: 0 20px 40px rgba(0,0,0,0.5); border-radius: 4px; }
        html.light-mode .stat-box { background: var(--stark-white); border-color: var(--midnight-navy); box-shadow: 0 20px 40px rgba(10,17,40,0.1); }
        .stat-box p { font-size: 1.25rem; font-weight: 600; color: var(--stark-white); margin-bottom: 20px; line-height: 1.6; }
        html.light-mode .stat-box p { color: var(--midnight-navy); }
        .stat-source { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--terminal-red); letter-spacing: 2px; }
        html.light-mode .stat-source { color: var(--midnight-navy); opacity: 0.7; }

        /* --- SECTION 4: GRAND FINALE --- */
        .grand-finale { padding: 120px 0; text-align: center; background: url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=2000&auto=format&fit=crop') center/cover; position: relative; border-top: 1px solid rgba(0, 229, 255, 0.2); }
        .finale-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10, 17, 40, 0.92); }
        html.light-mode .finale-overlay { background: rgba(243, 244, 246, 0.95); }
        html.light-mode .grand-finale { border-top-color: rgba(10, 17, 40, 0.1); }
        .finale-content { position: relative; z-index: 2; max-width: 800px; margin: 0 auto; }
        .finale-content h2 { font-size: clamp(2rem, 4vw, 3.5rem); margin-bottom: 20px; line-height: 1.2; letter-spacing: -1px; }

        /* ==========================================================================
           MOBILE RESPONSIVE MASTER
           ========================================================================== */
        @media (max-width: 850px) {
            .container { padding-left: 20px !important; padding-right: 20px !important; }
            section { padding: 60px 0 !important; }
            
            /* Strict Left-Alignment for all structural content */
            .terminal-teal-text { justify-content: flex-start !important; text-align: left !important; margin-left: 0 !important; }
            .hero-content, .stat-box, .finale-content { text-align: center !important; }
            
            /* Grid Adjustments */
            .case-study { grid-template-columns: 1fr; padding: 30px 20px; gap: 30px; margin-bottom: 40px; }
            .case-header { flex-direction: column; align-items: flex-start; gap: 20px; padding-bottom: 20px; }
            
            .roi-panel { padding: 25px 20px; }
            .roi-stat { flex-direction: column; gap: 5px; }
            .roi-number { font-size: 3.5rem; }
            
            .stat-interstitial { margin: 20px 0 60px 0; }
            .stat-box { padding: 30px 20px; margin: 0; }
            .stat-box p { font-size: 1.1rem; }
        }
    </style>
    @endpush

    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="scanline-overlay"></div>
        <div class="hero-overlay"></div>
        
        <div class="container hero-content text-center reveal active">
            <div class="tech-badge mono-font">
                <span class="pulse-dot"></span> // DEPLOYMENT ARCHIVES
            </div>
            
            <h1 class="brand-title">SANITIZED ARCHITECTURE</h1>
            <h2 class="brand-slogan mono-font">CLASSIFIED INTEL</h2>
            
            <p class="hero-desc">To protect internal corporate controls and NDAs, all identifying client data has been cryptographically scrubbed. The underlying engineered architecture and financial ROI remain strictly intact.</p>
            
            <div class="nexus-wrapper">
                <button class="btn-outline" onclick="window.scrollTo({top: document.querySelector('.dossier-wrapper').offsetTop, behavior: 'smooth'})">
                    ACCESS DOSSIERS <span class="mono-font prompt-arrow">>_</span>
                </button>
            </div>
        </div>
    </section>

    <div class="dossier-wrapper container">
        
        @php $quoteIndex = 0; @endphp

        @foreach($caseStudies as $study)
            <div class="case-study reveal">
                <div class="case-header">
                    <div>
                        <span class="client-tag">{{ $study->client_pseudonym }}</span>
                        <h3 class="case-title">{{ $study->title }}</h3>
                    </div>
                    <div class="status-tag"><span class="pulse-green"></span> SYSTEM DEPLOYED</div>
                </div>

                <div class="case-block">
                    <h4>> THE VULNERABILITY</h4>
                    <p>{{ $study->challenge }}</p>
                    
                    <h4>> THE AUDIT</h4>
                    <p>{{ $study->audit }}</p>
                </div>

                <div class="case-block">
                    <h4>> THE ARCHITECTURE</h4>
                    <p>{{ $study->architecture_deployed }}</p>
                    
                    <div class="roi-panel">
                        <h4 style="border: none; margin-bottom: 10px;">> ENGINEERED ROI</h4>
                        <div class="roi-stat">
                            <span class="roi-number">
                                {!! str_replace('%', '<span style="font-size: 2rem;">%</span>', $study->roi_metrics['number']) !!}
                            </span>
                            <span class="roi-metric">{{ $study->roi_metrics['metric'] }}</span>
                        </div>
                        <p class="roi-desc">{{ $study->roi_metrics['description'] }}</p>
                        <div class="arch-tags">
                            @foreach($study->roi_metrics['tags'] as $tag)
                                <span class="arch-tag">{{ $tag }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dynamically inject a quote every 2nd case study --}}
            @if($loop->iteration % 2 == 0 && $quotes->count() > 0)
                @php 
                    // Use modulo to loop back to the first quote if we run out
                    $currentQuote = $quotes[$quoteIndex % $quotes->count()];
                    $quoteIndex++; 
                @endphp
                
                <div class="stat-interstitial reveal">
                    <div class="stat-box">
                        <p>{{ $currentQuote->quote }}</p>
                        <span class="stat-source">{{ $currentQuote->source }}</span>
                    </div>
                </div>
            @endif

        @endforeach

    </div>
    
    <section class="grand-finale">
        <div class="finale-overlay"></div>
        <div class="container finale-content reveal">
            <div class="terminal-teal-text mono-font" style="justify-content: center;">
                <span class="pulse-dot"></span> <strong>> THE FINAL VARIABLE</strong>
            </div>
            <h2>80% of African enterprises leak capital through weak internal controls and fragmented software.</h2>
            <p style="font-size: 1.15rem; color: var(--text-secondary); margin-bottom: 40px;">Do not run your enterprise on hope. Run it on Praxis.</p>
            <a href="/schedule" class="btn-outline" wire:navigate>CONTACT THE ARCHITECTS</a>
        </div>
    </section>

    <script>
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
    </script>
</div>