<div>
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - TRUST CENTER & GOVERNANCE MASTER CSS 
           ========================================================================== */

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
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
        .immersive-bg { background-image: url('{{ asset("assets/img/about.svg") }}'); background-size: cover; background-position: center; filter: grayscale(100%) brightness(0.2); transition: all 0.5s ease; }
        html.light-mode .immersive-bg { filter: grayscale(100%) brightness(1) opacity(0.3); }
        .immersive-overlay { background: radial-gradient(circle at center, rgba(10,17,40,0.85) 0%, var(--midnight-navy) 100%); transition: background 0.5s ease; }
        html.light-mode .immersive-overlay { background: radial-gradient(circle at center, rgba(255,255,255,0.85) 0%, var(--ghost-white) 100%); }
        .scanline-grid { background-image: linear-gradient(rgba(0, 229, 255, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 229, 255, 0.02) 1px, transparent 1px); background-size: 40px 40px; }
        html.light-mode .scanline-grid { background-image: linear-gradient(rgba(10, 17, 40, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(10, 17, 40, 0.03) 1px, transparent 1px); }

        /* --- LAYOUT WRAPPER --- */
        .dossier-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 120px 24px 100px 24px; position: relative; z-index: 10; min-height: 100vh; }

        /* --- THE DOSSIER PAPER --- */
        .dossier-paper {
            width: 100%; max-width: 950px; position: relative;
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

        .dossier-title { font-size: clamp(2rem, 4vw, 2.5rem); line-height: 1.2; margin-bottom: 30px; color: var(--stark-white); letter-spacing: -1px; text-transform: uppercase; }
        html.light-mode .dossier-title { color: var(--midnight-navy); }

        /* Compliance Matrix Grid (Executive Summary) */
        .compliance-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 50px; }
        .compliance-badge { background: rgba(0, 229, 255, 0.03); border: 1px solid rgba(0, 229, 255, 0.15); padding: 15px; border-radius: 4px; text-align: center; }
        html.light-mode .compliance-badge { background: rgba(10,17,40,0.02); border-color: rgba(10,17,40,0.1); }
        .cb-val { font-family: 'Roboto Mono', monospace; font-size: 1.1rem; color: var(--stark-white); font-weight: bold; margin-bottom: 5px; display: block; }
        html.light-mode .cb-val { color: var(--midnight-navy); }
        .cb-label { font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }

        /* Typography inside Document */
        .dossier-section { margin-bottom: 60px; }
        .dossier-section h3 { font-size: 1.3rem; color: var(--stark-white); margin-bottom: 10px; display: flex; align-items: center; gap: 15px; letter-spacing: 0px; }
        html.light-mode .dossier-section h3 { color: var(--midnight-navy); }
        
        .doc-ref { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--accent-color); margin-bottom: 25px; display: block; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 10px; letter-spacing: 1px; }
        html.light-mode .doc-ref { color: var(--midnight-navy); border-bottom-color: rgba(10,17,40,0.1); }

        .dossier-section p { font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin-bottom: 20px; }
        html.light-mode .dossier-section p { color: #475569; }

        /* Structural Blocks (Zero Trust / Sub-points) */
        .zt-block { margin-bottom: 25px; padding-left: 20px; border-left: 2px solid var(--accent-color); }
        html.light-mode .zt-block { border-left-color: var(--midnight-navy); }
        .zt-block h4 { font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--stark-white); margin-bottom: 8px; text-transform: uppercase; }
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
            
            .doc-control { border-bottom: 2px solid #000 !important; margin-bottom: 20px !important; }
            .doc-meta, .doc-meta strong { color: #000 !important; }
            .doc-classification { border-color: #000 !important; color: #000 !important; }
            
            .dossier-title { color: #000 !important; font-size: 22pt !important; margin-bottom: 20px !important; }
            
            .compliance-grid { display: none !important; } /* Hide badges on print to save space/ink */
            
            .dossier-section { margin-bottom: 30px !important; }
            .dossier-section h3 { color: #000 !important; margin-bottom: 5px !important; font-size: 14pt !important; }
            .doc-ref { border-bottom: 1px solid #ccc !important; color: #555 !important; margin-bottom: 15px !important; }
            .dossier-section p, .zt-block p { color: #333 !important; font-size: 11pt !important; }
            
            .zt-block { border-left: 2px solid #000 !important; padding-left: 15px !important; break-inside: avoid; }
            .zt-block h4 { color: #000 !important; font-size: 11pt !important; }
            
            .dossier-footer { border-top: 1px solid #000 !important; }
        }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 850px) {
            .dossier-wrapper { padding: 40px 15px 100px 15px; }
            .dossier-paper { padding: 40px 25px; }
            .doc-control { flex-direction: column; align-items: flex-start; gap: 15px; }
            .compliance-grid { grid-template-columns: 1fr 1fr; }
            .dossier-title { font-size: 1.8rem; }
            .dossier-section h3 { font-size: 1.15rem; align-items: flex-start; flex-direction: column; gap: 5px; }
            .dossier-footer { flex-direction: column; align-items: flex-start; gap: 20px; }
        }
    </style>
    @endpush

    <div class="immersive-bg"></div>
    <div class="immersive-overlay"></div>
    <div class="scanline-grid"></div>

    <div class="dossier-wrapper">
        
        <div class="print-controls" style="width: 100%; max-width: 950px; display: flex; justify-content: flex-end; margin-bottom: 20px; animation: fadeIn 1s ease forwards;">
            <button class="btn-outline" onclick="window.print()" aria-label="Print or Save to PDF">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                SAVE NEXUS DOSSIER
            </button>
        </div>

        <article class="dossier-paper">
            
            <div class="doc-control">
                <div class="doc-meta">
                    <span><strong>STANDARD:</strong> Global / Tier-1 Enterprise</span>
                    <span><strong>DESKS:</strong> Global HQ (Lagos) | CEMAC (Douala)</span>
                    <span><strong>UPDATED:</strong> {{ date('F Y') }}</span>
                </div>
                <div class="doc-classification">
                    <span class="pulse-dot" style="margin-right: 5px;"></span> [ SYSTEM: VERIFIED ]
                </div>
            </div>

            <h1 class="dossier-title">Corporate Governance & Compliance Nexus</h1>

            <div class="compliance-grid">
                <div class="compliance-badge">
                    <span class="cb-val">ISO 9001</span>
                    <span class="cb-label">Quality Control</span>
                </div>
                <div class="compliance-badge">
                    <span class="cb-val">FCPA / UKBA</span>
                    <span class="cb-label">Anti-Bribery</span>
                </div>
                <div class="compliance-badge">
                    <span class="cb-val">ISO 14001</span>
                    <span class="cb-label">ESG Framework</span>
                </div>
                <div class="compliance-badge">
                    <span class="cb-val">ISO 27001</span>
                    <span class="cb-label">InfoSec (BCMS)</span>
                </div>
            </div>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">I.</span> THE QUALITY & OPERATIONAL EXCELLENCE PROTOCOL</h3>
                <span class="doc-ref">> DOCUMENT REF: JBS-PRX-QMS-01</span>
                
                <div class="zt-block">
                    <h4>1. The Praxis Quality Axiom</h4>
                    <p>At JBS Praxis, operational excellence is not a subjective goal; it is a deterministic output. As architects of the Predictive Praxis Framework™ (PPF), we mathematically enforce internal quality controls to ensure zero-defect delivery across all consulting, auditing, and engineering deployments. We do not tolerate "acceptable margins of error" in our own operations.</p>
                </div>
                
                <div class="zt-block">
                    <h4>2. Algorithmic Quality Control</h4>
                    <p>Our internal workflows are governed by the same "Code as Law" principles we deploy for our clients.</p>
                    <ul style="margin-top: 10px; color: var(--text-secondary); list-style-type: none; padding-left: 0; font-size: 1.05rem; line-height: 1.8;">
                        <li style="margin-bottom: 10px;"><strong style="color: var(--stark-white);">The COPQ Baseline:</strong> Every internal project phase is measured against our proprietary Cost of Poor Quality matrix. Time latency, code refactoring, and diagnostic precision are tracked and optimized.</li>
                        <li><strong style="color: var(--stark-white);">Peer-Reviewed Architecture:</strong> No digital operating system or ISO diagnostic report is deployed without passing through a Triad Validation Gate, requiring sign-off from the Lead Management Consultant, Lead Data Engineer, and Chief Praxis Architect.</li>
                    </ul>
                </div>
                
                <div class="zt-block">
                    <h4>3. The Kaizen Mandate</h4>
                    <p>Excellence degrades without evolution. JBS Praxis operates on a strict Kaizen (Continuous Improvement) loop. Our Strike Teams conduct mandatory post-deployment forensic audits on our own processes, recalibrating our operational algorithms quarterly to maintain peak market dominance.</p>
                </div>
            </section>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">II.</span> CODE OF BUSINESS ETHICS & ANTI-BRIBERY PROTOCOL</h3>
                <span class="doc-ref">> DOCUMENT REF: JBS-PRX-ABAC-02</span>
                
                <div class="zt-block">
                    <h4>1. The Zero-Tolerance Baseline</h4>
                    <p>JBS Praxis operates across complex regulatory environments, from the Gulf of Guinea to global financial hubs. Our ethical baseline is absolute. We enforce a zero-tolerance policy against bribery, corruption, extortion, and influence-peddling. Our operations comply strictly with the FCPA, UK Bribery Act, and all regional anti-corruption frameworks.</p>
                </div>
                
                <div class="zt-block">
                    <h4>2. Procurement & Vendor Integrity</h4>
                    <p>A compromised supply chain is a fatal vulnerability.</p>
                    <ul style="margin-top: 10px; color: var(--text-secondary); list-style-type: none; padding-left: 0; font-size: 1.05rem; line-height: 1.8;">
                        <li style="margin-bottom: 10px;"><strong style="color: var(--stark-white);">Immutable Ledgers:</strong> All financial transactions, vendor approvals, and client deployments are logged in immutable digital ledgers.</li>
                        <li><strong style="color: var(--stark-white);">Conflict of Interest:</strong> Our architects and consultants are legally bound to declare any lateral interests. We do not engage in "pay-to-play" consulting or accept hidden commissions from third-party software vendors. Our architecture recommendations are based purely on mathematical efficiency and client ROI.</li>
                    </ul>
                </div>

                <div class="zt-block">
                    <h4>3. The Whistleblower Architecture (Glass-Break)</h4>
                    <p>We engineer transparency. JBS Praxis maintains a secure, encrypted "Glass-Break" reporting protocol. Employees, contractors, and clients have direct, anonymous access to the Senior Partnership Triad to report ethical anomalies without fear of retaliation. If an anomaly is detected, it is neutralized immediately.</p>
                </div>
            </section>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">III.</span> ESG & SUSTAINABLE GOVERNANCE FRAMEWORK</h3>
                <span class="doc-ref">> DOCUMENT REF: JBS-PRX-ESG-03</span>
                
                <div class="zt-block">
                    <h4>1. Beyond Public Relations: Engineered Sustainability</h4>
                    <p>For JBS Praxis, Environmental, Social, and Governance (ESG) is not a marketing exercise; it is a measurable, algorithmic requirement for enterprise survival—especially in heavy industries like Oil & Gas, Logistics, and Manufacturing. We engineer sustainability directly into our code.</p>
                </div>

                <div class="zt-block">
                    <h4>2. Environmental Algorithmic Tracking (E)</h4>
                    <p>We architect systems that physically prevent environmental waste. By optimizing supply chain latency and digitizing paper-heavy bureaucratic workflows, our Relational Vaults deterministically reduce carbon footprints. We build logic gates that block the procurement of non-compliant or environmentally hazardous materials by hardcoding ISO 14001 standards into the approval matrix.</p>
                </div>

                <div class="zt-block">
                    <h4>3. Sovereign Human Capital (S)</h4>
                    <p>We view human talent as an organization's most critical infrastructure. JBS Praxis enforces strict labor compliance, diversity of intellect, and workplace safety protocols. Furthermore, our deployment philosophy mandates total talent development for our clients, ensuring local teams are upskilled to command the architecture we leave behind.</p>
                </div>

                <div class="zt-block">
                    <h4>4. Hardcoded Governance (G)</h4>
                    <p>Corporate governance fails when it relies on human memory. We enforce governance by translating board mandates into digital Poka-Yoke (mistake-proofing) mechanisms, ensuring executive accountability and operational transparency at a forensic level.</p>
                </div>
            </section>

            <section class="dossier-section">
                <h3><span class="mono-font" style="opacity: 0.5;">IV.</span> INFORMATION SECURITY & BUSINESS CONTINUITY (ISO 27001)</h3>
                <span class="doc-ref">> DOCUMENT REF: JBS-PRX-INFOSEC-04</span>
                
                <div class="zt-block">
                    <h4>1. The Zero-Trust Architecture</h4>
                    <p>Data is the apex asset of the modern enterprise. The JBS Praxis Intelligence Desk operates on a strict Zero-Trust cybersecurity model. We assume the environment is always hostile.</p>
                    <ul style="margin-top: 10px; color: var(--text-secondary); list-style-type: none; padding-left: 0; font-size: 1.05rem; line-height: 1.8;">
                        <li style="margin-bottom: 10px;"><strong style="color: var(--stark-white);">Cryptographic Sovereignty:</strong> All proprietary client intelligence, audit findings, and source code are encrypted at rest and in transit utilizing military-grade cryptographic standards.</li>
                        <li><strong style="color: var(--stark-white);">Role-Based Access Control (RBAC):</strong> Access to the PostgreSQL Relational Vaults is strictly siloed. No single architect or consultant has unilateral access to a client’s entire digital ecosystem without authenticated, multi-factor authorization.</li>
                    </ul>
                </div>

                <div class="zt-block">
                    <h4>2. Catastrophic Resilience & BCMS</h4>
                    <p>We engineer systems to survive. In alignment with ISO 22301 (Business Continuity Management Systems), JBS Praxis maintains aggressive redundancy protocols.</p>
                    <ul style="margin-top: 10px; color: var(--text-secondary); list-style-type: none; padding-left: 0; font-size: 1.05rem; line-height: 1.8;">
                        <li style="margin-bottom: 10px;"><strong style="color: var(--stark-white);">Failover Mechanisms:</strong> Our cloud infrastructure utilizes multi-region mirroring. In the event of a catastrophic server failure or regional blackout, our digital operating systems failover seamlessly to backup environments with near-zero latency.</li>
                        <li><strong style="color: var(--stark-white);">Incident Response Protocol:</strong> If a cyber-threat or operational shock is detected, the Praxis Nexus locks down the affected silos, preserves the forensic audit trail, and alerts the Strike Team for immediate neutralization.</li>
                    </ul>
                </div>
            </section>

            <div class="dossier-footer">
                <div class="signature-block">
                    > To initiate a compliance audit or verify JBS Praxis certifications, contact the Desk at:<br>
                    <a href="mailto:intelligence@jbspraxis.com" style="display: inline-block; margin-top: 10px; font-weight: bold; font-size: 0.9rem;">intelligence@jbspraxis.com</a>
                </div>
                <div class="terminal-teal-text mono-font" style="margin: 0; font-size: 0.7rem;">
                    <span class="pulse-dot"></span> END OF PROTOCOL
                </div>
            </div>

        </article>
    </div>
</div>