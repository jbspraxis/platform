<div>
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - DATA SOVEREIGNTY MASTER CSS ARCHITECTURE 
           ========================================================================== */

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        .pulse-dot { width: 6px; height: 6px; background-color: var(--luminous-teal); border-radius: 50%; box-shadow: 0 0 10px var(--luminous-teal); animation: pulse 2s infinite; display: inline-block; }
        html.light-mode .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.4); }

        .terminal-teal-text { color: var(--luminous-teal); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 15px; text-transform: uppercase; font-weight: bold; }
        html.light-mode .terminal-teal-text { color: var(--midnight-navy); }

        .btn-outline { background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color); text-decoration: none; padding: 12px 24px; display: inline-flex; align-items: center; gap: 10px; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; transition: all 0.3s ease; border-radius: 4px; cursor: pointer; backdrop-filter: blur(10px); }
        .btn-outline:hover { background: var(--accent-color); color: var(--midnight-navy); box-shadow: 0 0 20px rgba(0, 229, 255, 0.3); transform: translateY(-2px); }
        html.light-mode .btn-outline { border-color: var(--midnight-navy); color: var(--midnight-navy); }
        html.light-mode .btn-outline:hover { background-color: var(--midnight-navy); color: var(--stark-white); }

        /* --- IMMERSIVE BACKGROUND --- */
        .immersive-bg, .immersive-overlay, .scanline-grid {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; pointer-events: none; z-index: -10;
        }
        .immersive-bg { background-image: url('{{ asset("assets/img/hero_home.webp") }}'); background-size: cover; background-position: center; filter: grayscale(100%) brightness(0.2); transition: all 0.5s ease; }
        html.light-mode .immersive-bg { filter: grayscale(100%) brightness(1) opacity(0.3); }
        .immersive-overlay { background: radial-gradient(circle at center, rgba(10,17,40,0.8) 0%, var(--midnight-navy) 100%); transition: background 0.5s ease; }
        html.light-mode .immersive-overlay { background: radial-gradient(circle at center, rgba(255,255,255,0.8) 0%, var(--ghost-white) 100%); }
        .scanline-grid { background-image: linear-gradient(rgba(0, 229, 255, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 229, 255, 0.02) 1px, transparent 1px); background-size: 40px 40px; }
        html.light-mode .scanline-grid { background-image: linear-gradient(rgba(10, 17, 40, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(10, 17, 40, 0.03) 1px, transparent 1px); }

        /* --- LAYOUT WRAPPER --- */
        .dossier-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 120px 24px 100px 24px; position: relative; z-index: 10; min-height: 100vh; }

        /* --- THE DOSSIER PAPER --- */
        .dossier-paper {
            width: 100%; max-width: 900px; position: relative;
            background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(0, 229, 255, 0.2); border-top: 4px solid var(--accent-color);
            border-radius: 4px; box-shadow: 0 40px 100px rgba(0,0,0,0.8);
            padding: 60px 80px; overflow: hidden;
            animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        html.light-mode .dossier-paper { background: rgba(255, 255, 255, 0.95); border-color: rgba(10, 17, 40, 0.15); border-top-color: var(--midnight-navy); box-shadow: 0 40px 100px rgba(10,17,40,0.08); }

        /* Document Control Header */
        .doc-control { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 20px; border-bottom: 2px solid rgba(0, 229, 255, 0.2); margin-bottom: 40px; }
        html.light-mode .doc-control { border-bottom-color: rgba(10,17,40,0.1); }
        .doc-meta { display: flex; flex-direction: column; gap: 5px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--text-secondary); }
        .doc-meta strong { color: var(--stark-white); }
        html.light-mode .doc-meta strong { color: var(--midnight-navy); }
        .doc-classification { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; color: var(--accent-color); border: 1px solid var(--accent-color); padding: 4px 10px; border-radius: 2px; }
        html.light-mode .doc-classification { color: var(--midnight-navy); border-color: var(--midnight-navy); }

        .dossier-title { font-size: clamp(2rem, 4vw, 2.8rem); line-height: 1.1; margin-bottom: 40px; color: var(--stark-white); letter-spacing: -1px; text-transform: uppercase; }
        html.light-mode .dossier-title { color: var(--midnight-navy); }

        /* Typography inside Document */
        .dossier-section { margin-bottom: 50px; }
        .dossier-section h3 { font-size: 1.4rem; color: var(--stark-white); margin-bottom: 20px; display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 10px; letter-spacing: 0px; }
        html.light-mode .dossier-section h3 { color: var(--midnight-navy); border-bottom-color: rgba(10,17,40,0.1); }
        
        .dossier-section p { font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin-bottom: 20px; }
        html.light-mode .dossier-section p { color: #475569; }

        /* Compliance Matrix Styling */
        .compliance-list { display: flex; flex-direction: column; gap: 12px; margin-top: 20px; }
        .comp-item { display: flex; align-items: center; gap: 15px; background: rgba(0, 229, 255, 0.02); border: 1px solid rgba(0, 229, 255, 0.1); padding: 12px 20px; border-radius: 4px; font-family: 'Inter', sans-serif; font-size: 0.95rem; color: var(--stark-white); }
        html.light-mode .comp-item { background: rgba(10,17,40,0.02); border-color: rgba(10,17,40,0.1); color: var(--midnight-navy); }
        .comp-tag { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--luminous-teal); font-weight: bold; width: 90px; }
        html.light-mode .comp-tag { color: #2D6A4F; }

        /* Zero Trust Styling */
        .zt-block { margin-bottom: 20px; padding-left: 20px; border-left: 2px solid var(--accent-color); }
        html.light-mode .zt-block { border-left-color: var(--midnight-navy); }
        .zt-block h4 { font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--stark-white); margin-bottom: 5px; text-transform: uppercase; }
        html.light-mode .zt-block h4 { color: var(--midnight-navy); }
        .zt-block p { font-size: 1rem !important; margin-bottom: 0 !important; }

        /* Signature Block */
        .dossier-footer { margin-top: 60px; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; }
        html.light-mode .dossier-footer { border-top-color: rgba(10,17,40,0.1); }
        .signature-block { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--text-secondary); }
        .signature-block a { color: var(--accent-color); border-bottom: 1px solid transparent; transition: all 0.3s; }
        html.light-mode .signature-block a { color: var(--midnight-navy); }
        .signature-block a:hover { border-bottom-color: var(--accent-color); }
        html.light-mode .signature-block a:hover { border-bottom-color: var(--midnight-navy); }

        /* ==========================================================================
           THE PRINT ENGINE (STRIPS ALL UI FOR PDF GENERATION)
           ========================================================================== */
        @media print {
            body { background: #FFFFFF !important; color: #000000 !important; }
            .global-header, .global-footer, .immersive-bg, .immersive-overlay, .scanline-grid, .print-controls { display: none !important; }
            .praxis-workspace { margin-top: 0 !important; }
            .dossier-wrapper { padding: 0 !important; min-height: auto !important; }
            .dossier-paper { background: none !important; box-shadow: none !important; border: none !important; padding: 0 !important; max-width: 100% !important; }
            
            .doc-control { border-bottom: 2px solid #000 !important; }
            .doc-meta, .doc-meta strong { color: #000 !important; }
            .doc-classification { border-color: #000 !important; color: #000 !important; }
            
            .dossier-title { color: #000 !important; font-size: 24pt !important; margin-bottom: 20px !important; }
            .dossier-section h3 { color: #000 !important; border-bottom: 1px solid #ccc !important; }
            .dossier-section p, .zt-block p { color: #333 !important; }
            
            .comp-item { border: 1px solid #ccc !important; color: #000 !important; break-inside: avoid; }
            .comp-tag { color: #000 !important; }
            .zt-block { border-left: 2px solid #000 !important; break-inside: avoid; }
            .zt-block h4 { color: #000 !important; }
            
            .dossier-footer { border-top: 1px solid #000 !important; }
        }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 850px) {
            .dossier-wrapper { padding: 40px 15px 100px 15px; }
            .dossier-paper { padding: 40px 25px; }
            .doc-control { flex-direction: column; align-items: flex-start; gap: 15px; }
            .dossier-title { font-size: 1.8rem; }
            .comp-item { flex-direction: column; align-items: flex-start; gap: 5px; }
            .dossier-footer { flex-direction: column; align-items: flex-start; gap: 20px; }
        }
    </style>
    @endpush

    <div class="immersive-bg"></div>
    <div class="immersive-overlay"></div>
    <div class="scanline-grid"></div>

    <div class="dossier-wrapper">
        
        <div class="print-controls" style="width: 100%; max-width: 900px; display: flex; justify-content: flex-end; margin-bottom: 20px; animation: fadeIn 1s ease forwards;">
            <button class="btn-outline" onclick="window.print()" aria-label="Print or Save to PDF">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                SAVE PROTOCOL TO PDF
            </button>
        </div>

        <article class="dossier-paper">
            
            <div class="doc-control">
                <div class="doc-meta">
                    <span><strong>REF:</strong> JBS-PRX-DP-01</span>
                    <span><strong>SCOPE:</strong> Global Enterprise Operations</span>
                    <span><strong>UPDATED:</strong> {{ date('F Y') }}</span>
                </div>
                <div class="doc-classification">
                    [ CLASSIFICATION: SECURE ]
                </div>
            </div>

            <h1 class="dossier-title">Global Data Sovereignty & Privacy Architecture</h1>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">1.</span> THE PRAXIS PRIVACY PHILOSOPHY</h3>
                <p>At JBS Praxis, we do not view data privacy as a bureaucratic legal burden; we view it as the ultimate test of structural integrity. When dealing with the financial, operational, and intellectual capital of Tier-1 enterprises, generic security is insufficient.</p>
                <p>We engineer our PostgreSQL Relational Vaults and digital operating systems for absolute Data Sovereignty. This ensures that whether your enterprise operates in West Africa, East Africa, or globally, your proprietary intelligence remains impenetrable, strictly governed, and fully compliant across all jurisdictional boundaries.</p>
            </section>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">2.</span> THE JURISDICTIONAL COMPLIANCE MATRIX</h3>
                <p>JBS Praxis architectures are engineered to the highest global denominator of data protection. By building to the European "Gold Standard," our infrastructure automatically fulfills and exceeds the statutory requirements of the following regional frameworks:</p>
                
                <div class="compliance-list">
                    <div class="comp-item">
                        <div class="comp-tag">[IN SCOPE]</div>
                        <div><strong>The Global Standard:</strong> GDPR (General Data Protection Regulation - Europe)</div>
                    </div>
                    <div class="comp-item">
                        <div class="comp-tag">[IN SCOPE]</div>
                        <div><strong>West Africa & ECOWAS:</strong> NDPR & NDPA (Nigeria Data Protection Regulation / Act)</div>
                    </div>
                    <div class="comp-item">
                        <div class="comp-tag">[IN SCOPE]</div>
                        <div><strong>Southern Africa:</strong> POPIA (Protection of Personal Information Act - South Africa)</div>
                    </div>
                    <div class="comp-item">
                        <div class="comp-tag">[IN SCOPE]</div>
                        <div><strong>East Africa:</strong> DPA (Data Protection Act - Kenya)</div>
                    </div>
                    <div class="comp-item">
                        <div class="comp-tag">[IN SCOPE]</div>
                        <div><strong>Central Africa:</strong> CEMAC Directives on Cyber-security and Data Protection</div>
                    </div>
                </div>
            </section>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">3.</span> ZERO-TRUST ARCHITECTURAL GUARANTEES</h3>
                <p>We do not rely on human goodwill to protect your data; we rely on mathematics. Our privacy architecture operates on the following unyielding principles:</p>
                
                <div style="margin-top: 30px;">
                    <div class="zt-block">
                        <h4>Code as Law</h4>
                        <p>Privacy by Design is hardcoded into every backend algorithm we write. Data access is restricted by automated logic gates, not manual oversight.</p>
                    </div>
                    <div class="zt-block">
                        <h4>The Relational Vault</h4>
                        <p>Client operational data fed into our Predictive Engines or COPQ Diagnostics is encrypted at rest and in transit. We deploy zero-trust microservices to ensure data is never exposed to unauthorized lateral movement.</p>
                    </div>
                    <div class="zt-block">
                        <h4>Algorithmic Anonymization</h4>
                        <p>When utilizing data to calculate predictive risk probabilities or Cost of Poor Quality (COPQ), sensitive identifiers are algorithmically decoupled from operational metrics.</p>
                    </div>
                </div>
            </section>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">4.</span> DATA COLLECTION & UTILIZATION</h3>
                <p>We extract and process data strictly for operational diagnostics, system engineering, and continuous Kaizen optimization.</p>
                
                <div style="margin-top: 30px;">
                    <div class="zt-block">
                        <h4>What we collect:</h4>
                        <p>Operational telemetry, workflow latencies, COPQ inputs, and standard digital identifiers necessary for system authentication and Strike Team scheduling.</p>
                    </div>
                    <div class="zt-block" style="border-left-color: var(--terminal-red);">
                        <h4 style="color: var(--terminal-red);">What we never do:</h4>
                        <p>JBS Praxis does not, under any circumstances, broker, sell, or leverage client architectural data for third-party commercial exploitation. Your operational blueprints belong to your boardroom.</p>
                    </div>
                </div>
            </section>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">5.</span> EXECUTIVE SOVEREIGNTY (YOUR RIGHTS)</h3>
                <p>In accordance with global privacy frameworks, enterprise stakeholders retain absolute sovereignty over their data. You retain the right to audit, amend, or mandate the cryptographic erasure of your personal data from our active nexus.</p>
            </section>

            <div class="dossier-footer">
                <div class="signature-block">
                    > To initiate a data audit or invoke your sovereign data rights, contact the JBS Intelligence Desk at:<br>
                    <a href="mailto:intelligence@jbspraxis.com" style="display: inline-block; margin-top: 10px; font-weight: bold; font-size: 0.9rem;">intelligence@jbspraxis.com</a>
                </div>
                <div class="terminal-teal-text mono-font" style="margin: 0; font-size: 0.7rem;">
                    <span class="pulse-dot"></span> END OF PROTOCOL
                </div>
            </div>

        </article>
    </div>
</div>