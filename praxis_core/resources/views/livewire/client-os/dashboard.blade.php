@section('robots', 'noindex, nofollow')
<div style="min-height: 100vh; background-color: #03050A; font-family: 'Inter', sans-serif; color: #FFFFFF; display: flex; flex-direction: column;" x-data="{ showGuide: false, showQaOffcanvas: false, mobileMenuOpen: false }" @open-qa.window="showQaOffcanvas = true" @close-qa.window="showQaOffcanvas = false">    
    
    <!-- THE BRAND ENGINE: DYNAMIC CSS INJECTION -->
    <style>
        /* --- GLOBAL RESETS (Zero Horizontal Scroll) --- */
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; overflow-x: hidden; width: 100%; max-width: 100vw; }

        :root {
            /* Fallback to JBS Praxis Defaults if client has no settings */
            --brand-primary: {{ $clientSettings->primary_color ?? '#00E5FF' }};
            --brand-secondary: {{ $clientSettings->secondary_color ?? '#FF3366' }};
            --brand-accent: {{ $clientSettings->accent_color ?? '#FFBD2E' }};
            --brand-bg: #03050A;
            --brand-panel: rgba(10, 17, 40, 0.85);
            --brand-border: rgba(255, 255, 255, 0.1);
        }

        /* --- LIVEWIRE DOM MORPH ANIMATIONS --- */
        .wire-modal-enter { animation: modalEnter 0.4s cubic-bezier(0.25, 1, 0.3, 1) forwards; }
        @keyframes modalEnter { 
            0% { opacity: 0; transform: scale(0.95) translateY(10px); } 
            100% { opacity: 1; transform: scale(1) translateY(0); } 
        }

        .wire-fade-in { animation: fadeIn 0.3s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        /* --- REFINED OS TOPBAR (Perfect Grid Centering) --- */
        .os-topbar { 
            background: var(--brand-panel); 
            border-bottom: 1px solid var(--brand-border); 
            padding: 0 30px; 
            height: 70px; 
            display: flex; /* Shift back to flex for dynamic width control */
            align-items: center; 
            gap: 40px; /* Creates a hard buffer between the 3 main zones */
            position: sticky; 
            top: 0; 
            z-index: 100; 
            backdrop-filter: blur(20px); 
            width: 100%; 
        }
        
        /* 1. Left Branding Zone */
        .os-logo-area { 
            display: flex; 
            align-items: center; 
            flex: 0 0 auto; /* Locks the logo area to its exact size */
        }
        .client-logo { height: 32px; max-width: 140px; object-fit: contain; }
        .jbs-logo { height: 42px; opacity: 0.98; }
        
        /* 2. Center Dock Navigation */
        .os-nav { 
            display: flex; 
            height: 100%; 
            align-items: center; 
            flex: 1; /* Takes up all available middle space */
            justify-content: flex-start; /* Pushes the menu icons to the left */
            gap: 5px; 
        }
        .os-nav-btn { background: transparent; border: none; color: #8A9BB2; height: 100%; cursor: pointer; transition: 0.3s; position: relative; display: flex; align-items: center; padding: 0 15px; }
        .os-nav-btn i { transition: transform 0.3s ease; }
        .os-nav-btn:hover i { transform: scale(1.1); color: #fff; }
        
        /* The Expanding Text Magic */
        .nav-label { max-width: 0; opacity: 0; overflow: hidden; white-space: nowrap; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; transition: all 0.4s cubic-bezier(0.25, 1, 0.3, 1); margin-left: 0; letter-spacing: 1px; }
        .os-nav-btn:hover .nav-label, .os-nav-btn.active .nav-label { max-width: 150px; opacity: 1; margin-left: 12px; color: #fff; }
        
        .os-nav-btn.active { color: var(--brand-primary); }
        .os-nav-btn.active i, .os-nav-btn.active .nav-label { color: var(--brand-primary); }
        .os-nav-btn.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background: var(--brand-primary); box-shadow: 0 -2px 10px var(--brand-primary); }
        
        .os-nav-btn.locked { opacity: 0.3; cursor: not-allowed; }
        .os-nav-btn.locked:hover .nav-label { max-width: 0; opacity: 0; margin-left: 0; } /* Keep closed if locked */

        /* 3. Right Actions Zone */
        .os-actions-area { 
            display: flex; 
            align-items: center; 
            justify-content: flex-end; 
            gap: 20px; 
            flex: 0 0 auto; /* Prevents actions from stretching */
        }
        
        /* Truncated Project Selector */
        .project-selector { background: rgba(0,0,0,0.3); border: 1px solid var(--brand-border); color: var(--brand-primary); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; padding: 8px 10px; border-radius: 4px; outline: none; cursor: pointer; max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 1; }
        .project-selector option { background: var(--brand-bg); color: #fff; }

        /* Mobile Menu Toggle Button (Hidden on Desktop) */
        .mobile-menu-btn { display: none; background: transparent; border: 1px solid var(--brand-border); color: var(--brand-primary); padding: 8px; border-radius: 4px; cursor: pointer; }

        .lockout-banner { background: rgba(255, 51, 102, 0.1); border-bottom: 1px solid var(--brand-secondary); color: var(--brand-secondary); padding: 15px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; letter-spacing: 1px; display: flex; align-items: center; justify-content: center; gap: 10px; animation: pulse-bg 2s infinite; width: 100%; }
        @keyframes pulse-bg { 0% { background: rgba(255, 51, 102, 0.1); } 50% { background: rgba(255, 51, 102, 0.2); } 100% { background: rgba(255, 51, 102, 0.1); } }

        /* FULL WIDTH OVERRIDE */
        .os-content { flex: 1; padding: 40px; display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; width: 100%; max-width: 100% !important; margin: 0; overflow-x: hidden; }
        .glitch-text { font-family: 'Montserrat', sans-serif; font-size: 2rem; font-weight: 900; color: #333; letter-spacing: 5px; }

        .btn-panic { background: rgba(255, 51, 102, 0.1); border: 1px solid var(--brand-secondary); color: var(--brand-secondary); padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; cursor: pointer; transition: 0.3s; border-radius: 4px; display: flex; align-items: center; gap: 5px; white-space: nowrap; flex-shrink: 0; }
        .btn-panic:hover { background: var(--brand-secondary); color: #fff; box-shadow: 0 0 15px var(--brand-secondary); }
        
        /* Lock submit buttons to prevent text-swap jitter */
        .btn-submit { background: transparent; border: 1px solid var(--brand-primary); color: var(--brand-primary); padding: 12px; font-family: 'Roboto Mono', monospace; font-weight: bold; width: 100%; min-width: 140px; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px; border-radius: 2px; text-align: center; }
        .btn-submit:hover { background: var(--brand-primary); color: var(--brand-bg); box-shadow: 0 0 20px rgba(0,229,255,0.4); }
        
        .processing { animation: pulseOpacity 1s infinite alternate; pointer-events: none; opacity: 0.7; }
        @keyframes pulseOpacity { from { opacity: 0.5; } to { opacity: 1; } }

        /* OVERVIEW DASHBOARD STYLES */
        .exec-summary-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; background: var(--brand-panel); border: 1px solid var(--brand-border); border-left: 4px solid var(--brand-primary); padding: 25px 30px; border-radius: 4px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); width: 100%; margin-bottom: 30px; }
        .summary-node { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
        .summary-label { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; text-transform: uppercase; letter-spacing: 1px; }
        .summary-value { font-size: 1.1rem; font-weight: 700; color: #FFFFFF; text-transform: uppercase; letter-spacing: -0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .summary-value.highlight { color: var(--brand-primary); text-shadow: 0 0 10px rgba(0,229,255,0.2); }

        /* THE SPRINT TIMELINE */
        .sprint-timeline { background: var(--brand-panel); border: 1px solid var(--brand-border); padding: 50px 40px; border-radius: 4px; overflow-x: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.4); width: 100%; margin-bottom: 30px; }
        .sprint-timeline::-webkit-scrollbar { height: 6px; }
        .sprint-timeline::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        .sprint-timeline::-webkit-scrollbar-thumb { background: var(--brand-primary); border-radius: 3px; }
        .timeline-wrapper { display: flex; justify-content: space-between; position: relative; min-width: 800px; }
        .timeline-track { position: absolute; top: 20px; left: 0; width: 100%; height: 2px; background: rgba(255,255,255,0.1); z-index: 1; }
        .timeline-progress { position: absolute; top: 20px; left: 0; height: 2px; background: var(--brand-primary); z-index: 2; box-shadow: 0 0 15px var(--brand-primary); transition: width 1s cubic-bezier(0.16, 1, 0.3, 1); }
        
        .phase-node { position: relative; z-index: 3; display: flex; flex-direction: column; align-items: center; gap: 15px; flex: 1; text-align: center; }
        .phase-dot { width: 42px; height: 42px; border-radius: 50%; background: var(--brand-bg); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; transition: all 0.4s ease; color: #8A9BB2; }
        .phase-title { font-size: 0.85rem; font-weight: 700; color: #8A9BB2; text-transform: uppercase; letter-spacing: 1px; }
        
        .phase-node.completed .phase-dot { background: #FFFFFF; color: var(--brand-bg); border-color: #FFFFFF; box-shadow: 0 0 15px rgba(255,255,255,0.2); }
        .phase-node.completed .phase-title { color: #FFFFFF; }
        
        .phase-node.active .phase-dot { background: rgba(0,229,255,0.1); border-color: var(--brand-primary); color: var(--brand-primary); box-shadow: 0 0 20px rgba(0,229,255,0.4); animation: pulseTeal 2s infinite; }
        .phase-node.active .phase-title { color: var(--brand-primary); }

        /* SPLIT GRID & TERMINAL */
        .dash-grid { display: grid; grid-template-columns: 1fr; gap: 30px; width: 100%; align-items: stretch; }
        .terminal-panel { background: var(--brand-panel); border: 1px solid var(--brand-border); border-radius: 4px; display: flex; flex-direction: column; height: 500px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); overflow: hidden; width: 100%; }
        .panel-header { padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0, 0, 0, 0.2); display: flex; justify-content: space-between; align-items: center; }
        .panel-title { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--brand-primary); letter-spacing: 2px; display: flex; align-items: center; gap: 10px; font-weight: bold; }
        
        .log-feed { padding: 25px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; }
        .log-item { padding-left: 20px; border-left: 2px solid var(--brand-border); word-break: break-word; }
        .log-time { color: var(--brand-primary); margin-bottom: 5px; font-size: 0.75rem; opacity: 0.8; letter-spacing: 1px; }
        .log-text { color: #FFFFFF; line-height: 1.6; }

        /* SPRINTS TAB STYLES */
        .task-list { display: flex; flex-direction: column; gap: 15px; width: 100%; }
        .task-card { background: var(--brand-panel); border: 1px solid var(--brand-border); border-left: 3px solid var(--brand-primary); padding: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 4px; transition: 0.3s; width: 100%; }
        .task-card:hover { background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.2); }
        .status-badge { padding: 6px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; font-weight: bold; border-radius: 2px; border: 1px solid; text-transform: uppercase; white-space: nowrap; }
        .status-In-Progress { color: var(--brand-accent); border-color: var(--brand-accent); background: rgba(255, 189, 46, 0.1); }
        .status-QA_Review { color: var(--brand-secondary); border-color: var(--brand-secondary); background: rgba(255, 51, 102, 0.1); }
        .status-Done { color: #00FF66; border-color: #00FF66; background: rgba(0, 255, 102, 0.1); }
        .status-To-Do { color: #8A9BB2; border-color: #8A9BB2; background: rgba(255, 255, 255, 0.05); }
        
        /* QA HUB STYLES */
        .qa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; width: 100%; align-items: start; }
        
        .sla-warning { background: rgba(255, 189, 46, 0.1); border: 1px solid var(--brand-accent); color: var(--brand-accent); padding: 15px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; border-radius: 4px; display: flex; align-items: flex-start; gap: 15px; margin-bottom: 30px; box-shadow: 0 0 20px rgba(255, 189, 46, 0.1); width: 100%; }
        .sla-warning i { animation: pulse-bg 2s infinite; }
        
        .qa-form-panel { background: var(--brand-panel); border: 1px solid var(--brand-border); padding: 30px; border-radius: 4px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); width: 100%; }
        .cyber-input, .cyber-select, .cyber-textarea { width: 100%; background: rgba(0,0,0,0.4); border: 1px solid var(--brand-border); color: #FFF; padding: 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; margin-bottom: 15px; }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { border-color: var(--brand-primary); background: rgba(0,229,255,0.05); box-shadow: inset 2px 0 0 var(--brand-primary); }
        .cyber-select option { background-color: var(--brand-bg); }
        .cyber-textarea { font-family: 'Roboto Mono', monospace; min-height: 120px; resize: vertical; width: 100%; }

        .ticket-list { display: flex; flex-direction: column; gap: 15px; width: 100%; }
        .ticket-card { background: rgba(0,0,0,0.3); border: 1px solid var(--brand-border); padding: 15px; border-radius: 4px; border-left: 3px solid var(--brand-primary); cursor: pointer; width: 100%; }
        .ticket-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; border-bottom: 1px dashed var(--brand-border); padding-bottom: 10px; flex-wrap: wrap; gap: 10px; }
        .ticket-title { font-family: 'Inter', sans-serif; font-weight: bold; font-size: 0.9rem; color: #FFF; word-break: break-word; }
        .ticket-meta { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 5px; }
        .ticket-desc { font-size: 0.8rem; color: #B0B8C4; line-height: 1.5; word-break: break-word; }
        
        /* FINANCIALS TAB STYLES */
        .finance-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; width: 100%; align-items: start; margin-top: 20px; }
        
        .invoice-card { background: var(--brand-panel); border: 1px solid var(--brand-border); padding: 25px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); width: 100%; flex-wrap: wrap; gap: 15px; }
        .invoice-meta { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 200px; }
        .inv-number { font-family: 'Montserrat', sans-serif; font-size: 1.2rem; font-weight: 900; color: #FFF; word-break: break-all; }
        .inv-details { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; }
        .inv-amount { font-family: 'Roboto Mono', monospace; font-size: 1.5rem; font-weight: bold; color: var(--brand-primary); text-align: right; }
        
        .bank-details-box { background: rgba(0,0,0,0.3); border: 1px dashed var(--brand-border); padding: 25px; border-radius: 4px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #B0B8C4; line-height: 1.8; width: 100%; word-break: break-word; }
        .bank-details-box strong { color: var(--brand-primary); }

        .pop-upload-form { background: rgba(10, 17, 40, 0.9); border: 1px solid var(--brand-primary); border-top: 4px solid var(--brand-primary); padding: 30px; border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); position: relative; width: 100%; }
        
        /* VAULT & BRAND STYLES */
        .vault-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; width: 100%; align-items: start; margin-top: 20px; }
        
        .vault-doc-card { background: rgba(0,0,0,0.3); border: 1px solid var(--brand-border); padding: 20px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-left: 3px solid var(--brand-primary); transition: 0.3s; width: 100%; flex-wrap: wrap; gap: 15px; }
        .vault-doc-card:hover { background: rgba(255,255,255,0.02); }
        .vault-doc-title { font-family: 'Inter', sans-serif; font-weight: bold; font-size: 0.95rem; color: #FFF; margin-bottom: 5px; word-break: break-word; }
        .vault-doc-meta { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; word-break: break-all; }
        
        .brand-config-panel { background: var(--brand-panel); border: 1px solid var(--brand-border); padding: 30px; border-radius: 4px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); width: 100%; }
        .color-picker-wrap { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.3); border: 1px solid var(--brand-border); padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; gap: 10px; }
        .color-picker { -webkit-appearance: none; -moz-appearance: none; appearance: none; width: 40px; height: 30px; background-color: transparent; border: none; cursor: pointer; padding: 0; flex-shrink: 0; }
        .color-picker::-webkit-color-swatch { border-radius: 4px; border: 1px solid rgba(255,255,255,0.2); }
        
        /* OFFCANVAS GUIDE STYLES */
        .os-guide-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 99999; opacity: 0; pointer-events: none; transition: opacity 0.4s ease; }
        .os-guide-overlay.open { opacity: 1; pointer-events: auto; }
        
        .os-guide-panel { position: fixed; top: 0; right: 0; width: 100%; max-width: 500px; height: 100vh; background: var(--brand-panel); border-left: 1px solid var(--brand-border); z-index: 100000; transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.25, 1, 0.3, 1); display: flex; flex-direction: column; box-shadow: -20px 0 50px rgba(0,0,0,0.8); }
        .os-guide-panel.open { transform: translateX(0); }
        
        .guide-header { padding: 25px 30px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(0, 0, 0, 0.2); }
        .guide-content { padding: 30px; overflow-y: auto; flex: 1; font-size: 0.85rem; color: #B0B8C4; line-height: 1.7; overflow-x: hidden; }
        
        /* Custom Scrollbar for Guide */
        .guide-content::-webkit-scrollbar { width: 6px; }
        .guide-content::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        .guide-content::-webkit-scrollbar-thumb { background: var(--brand-primary); border-radius: 3px; }

        .guide-section-title { font-family: 'Montserrat', sans-serif; font-size: 1rem; font-weight: 900; color: #FFF; margin: 30px 0 15px 0; border-bottom: 1px dashed var(--brand-border); padding-bottom: 10px; letter-spacing: 1px; text-transform: uppercase; }
        .guide-module-block { margin-bottom: 25px; padding-left: 15px; border-left: 2px solid rgba(255,255,255,0.1); transition: 0.3s; }
        .guide-module-block:hover { border-left-color: var(--brand-primary); background: rgba(255,255,255,0.02); }
        .guide-module-title { font-family: 'Roboto Mono', monospace; font-weight: bold; color: var(--brand-primary); margin-bottom: 8px; font-size: 0.8rem; letter-spacing: 1px; }
        
        .faq-block { margin-bottom: 20px; background: rgba(0,0,0,0.3); border: 1px solid var(--brand-border); padding: 15px; border-radius: 4px; word-break: break-word; }
        .faq-q { font-family: 'Inter', sans-serif; font-weight: bold; color: #FFF; margin-bottom: 10px; font-size: 0.85rem; display: flex; gap: 10px; }
        .faq-q i { color: var(--brand-accent); flex-shrink: 0; }
        
        /* --- FLOATING COMM BUTTON --- */
        .floating-comm-btn {
            position: fixed;
            bottom: 40px;
            right: 40px;
            width: 60px;
            height: 60px;
            background: var(--brand-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brand-bg);
            cursor: pointer;
            z-index: 999;
            box-shadow: 0 10px 30px rgba(0, 229, 255, 0.4);
            border: 2px solid rgba(255,255,255,0.2);
            transition: all 0.3s cubic-bezier(0.25, 1, 0.3, 1);
            animation: float 3s ease-in-out infinite;
        }

        .floating-comm-btn:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 229, 255, 0.6);
            background: #FFF;
            color: var(--brand-primary);
        }

        .floating-comm-btn i {
            width: 28px;
            height: 28px;
            transition: transform 0.3s;
        }

        .floating-comm-btn:hover i {
            transform: scale(1.1) rotate(-10deg);
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        /* Hide the button if they are already on the QA Hub tab to avoid redundancy */
        .qa-active-hide { display: none !important; }

        /* --- MOBILE MATRIX (Max-width 1024px & 768px) --- */
        @media (max-width: 1024px) {
            .qa-grid, .finance-grid, .vault-grid { grid-template-columns: 1fr; }
            .os-topbar { padding: 0 15px; }
            .os-nav-btn { padding: 0 10px; }
        }

        /* --- DESKTOP VS MOBILE VISIBILITY UTILITIES --- */
        .mobile-only { display: none !important; }
        
        @media (max-width: 768px) {
            .desktop-only { display: none !important; }
            .mobile-only { display: flex !important; }

            /* 1. APP-STYLE TOP BAR (Row 1) */
            .os-topbar { 
                padding: 15px 20px; 
                height: 70px; 
                justify-content: space-between; 
                position: sticky; 
                z-index: 100;
            }
            .mobile-header-btn { background: transparent; border: none; color: var(--brand-primary); padding: 0; display: flex; align-items: center; justify-content: center; }
            
            /* Abbreviated Project Selector */
            .project-selector.mobile-abbrev { 
                max-width: 140px; 
                text-align: center; 
                padding: 6px 10px; 
                font-size: 0.85rem; 
                background: rgba(0,0,0,0.5); 
                margin: 0 auto;
            }

            /* 2. THE APP DRAWER (Off-Canvas Menu) */
            .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 100000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
            .drawer-overlay.open { opacity: 1; pointer-events: auto; }
            
            .app-drawer { position: fixed; top: 0; left: 0; width: 280px; height: 100vh; background: var(--brand-panel); border-right: 1px solid var(--brand-border); z-index: 100001; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.25, 1, 0.3, 1); display: flex; flex-direction: column; box-shadow: 20px 0 50px rgba(0,0,0,0.8); }
            .app-drawer.open { transform: translateX(0); }

            .drawer-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); }
            .drawer-client-logo { max-width: 140px; height: 32px; object-fit: contain; }
            
            .drawer-nav { flex: 1; overflow-y: auto; padding: 20px 0; display: flex; flex-direction: column; }
            .drawer-nav-btn { width: 100%; justify-content: flex-start; padding: 18px 30px; height: auto; border: none; background: transparent; color: #8A9BB2; display: flex; align-items: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; transition: 0.3s; }
            .drawer-nav-btn i { margin-right: 15px; }
            .drawer-nav-btn.active { color: var(--brand-primary); background: rgba(0, 229, 255, 0.05); border-left: 4px solid var(--brand-primary); }

            .drawer-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.3); }
            .drawer-user-meta { font-family: 'Roboto Mono', monospace; margin-bottom: 15px; }
            
            /* 3. FLOATING ACTION STACK (Bottom Right) */
            .floating-action-stack { position: fixed; bottom: 30px; right: 20px; display: flex; flex-direction: column; gap: 15px; z-index: 999; }
            .fab-btn { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--brand-bg); box-shadow: 0 10px 20px rgba(0,0,0,0.5); border: 2px solid rgba(255,255,255,0.2); transition: 0.3s; cursor: pointer; }
            .fab-help { background: var(--brand-primary); }
            .fab-qa { background: #FFF; color: var(--brand-primary); }
            .fab-redline { background: rgba(255, 51, 102, 0.1); border-color: var(--brand-secondary); color: var(--brand-secondary); animation: pulse-bg 2s infinite; }
            
            /* Hide the old single floating button on mobile */
            .floating-comm-btn { display: none !important; }

            /* Grid Adjustments for Content */
            .os-content { padding: 20px 15px; }
            .exec-summary-bar { grid-template-columns: 1fr; padding: 15px; }
            .task-card, .invoice-card, .vault-doc-card { flex-direction: column; align-items: flex-start; gap: 15px; }
            .status-badge { align-self: flex-start; }
            .invoice-card > div:last-child { align-items: flex-start; text-align: left; width: 100%; }
            .inv-amount { text-align: left; }
            .terminal-panel { height: 400px; }
        }
    </style>

    <!-- TOP NAVIGATION BAR -->
    <div class="os-topbar">
        
        <div class="os-logo-area desktop-only">
            @if($clientSettings && $clientSettings->logo_path)
                <img src="{{ Storage::url($clientSettings->logo_path) }}" alt="{{ $organization->name }}" class="client-logo" title="Client Override Active">
                <div style="height: 24px; width: 1px; background: var(--brand-border); margin: 0 20px;"></div>
            @endif
            <img src="/assets/img/logo_monochrome.svg" alt="JBS Praxis OS" class="jbs-logo" onerror="this.style.display='none'">
        </div>

        <button class="mobile-header-btn mobile-only" @click="mobileMenuOpen = true">
            <i data-lucide="menu" style="width: 24px; height: 24px;"></i>
        </button>

        <div class="desktop-only" style="display: flex; height: 100%; align-items: center;">
            <div class="os-nav">
                @if(($can['projects.view'] ?? false))
                <button wire:click="switchTab('overview')" class="os-nav-btn {{ $activeTab === 'overview' ? 'active' : '' }} {{ $financialLockout ? 'locked' : '' }}">
                    <i data-lucide="layout-dashboard" style="width: 18px; height: 18px;"></i>
                    <span class="nav-label">OVERVIEW</span>
                </button>
                @endif
                @if(($can['projects.view'] ?? false))
                <button wire:click="switchTab('sprints')" class="os-nav-btn {{ $activeTab === 'sprints' ? 'active' : '' }} {{ $financialLockout ? 'locked' : '' }}">
                    <i data-lucide="git-merge" style="width: 18px; height: 18px;"></i>
                    <span class="nav-label">SPRINTS</span>
                </button>
                @endif
                @if(($can['qa.view'] ?? false))
                <button wire:click="switchTab('qa_hub')" class="os-nav-btn {{ $activeTab === 'qa_hub' ? 'active' : '' }} {{ $financialLockout ? 'locked' : '' }}">
                    <i data-lucide="shield-alert" style="width: 18px; height: 18px;"></i>
                    <span class="nav-label">QA HUB</span>
                </button>
                @endif
                @if(($can['billing.view'] ?? false))
                <button wire:click="switchTab('financials')" class="os-nav-btn {{ $activeTab === 'financials' ? 'active' : '' }}" style="{{ $financialLockout ? 'color: var(--brand-secondary);' : '' }}">
                    <i data-lucide="credit-card" style="width: 18px; height: 18px;"></i>
                    <span class="nav-label">FINANCIALS</span>
                </button>
                @endif
                @if(($can['documents.view'] ?? false))
                <button wire:click="switchTab('vault')" class="os-nav-btn {{ $activeTab === 'vault' ? 'active' : '' }} {{ $financialLockout ? 'locked' : '' }}">
                    <i data-lucide="folder-lock" style="width: 18px; height: 18px;"></i>
                    <span class="nav-label">VAULT</span>
                </button>
                @endif
                @if(($can['team.view'] ?? false))
                    <button wire:click="switchTab('team')" class="os-nav-btn {{ $activeTab === 'team' ? 'active' : '' }} {{ $financialLockout ? 'locked' : '' }}">
                        <i data-lucide="users" style="width: 18px; height: 18px;"></i>
                        <span class="nav-label">COMMAND</span>
                    </button>
                @endif
            </div>
        </div>

        @if(count($allProjects) > 1)
            <select wire:model.live="activeProjectId" wire:change="switchProject($event.target.value)" class="project-selector mobile-abbrev mobile-only" title="Currently Active: {{ $activeProject->title ?? 'Select Project' }}">
                @foreach($allProjects as $index => $proj)
                    <option value="{{ $proj->id }}">P{{ $index + 1 }} - {{ strtoupper($proj->title) }}</option>
                @endforeach
            </select>
        @endif

        <img src="/assets/img/logo_monochrome.svg" alt="JBS Praxis OS" class="jbs-logo mobile-only" style="height: 28px;" onerror="this.style.display='none'">

        <div class="os-actions-area desktop-only">
            @if(count($allProjects) > 1)
                <select wire:model.live="activeProjectId" wire:change="switchProject($event.target.value)" class="project-selector">
                    @foreach($allProjects as $proj)
                        <option value="{{ $proj->id }}">{{ strtoupper($proj->title) }}</option>
                    @endforeach
                </select>
            @endif
            
            <button @click="showGuide = true" style="background: transparent; border: 1px solid var(--brand-border); color: var(--brand-primary); padding: 6px 12px; border-radius: 4px; display: flex; align-items: center;"><i data-lucide="help-circle" style="width: 14px; height: 14px;"></i></button>
            @if(($can['support.create'] ?? false))
            <button wire:click="$set('showRedlineModal', true)" class="btn-panic"><i data-lucide="triangle-alert" style="width: 14px; height: 14px;"></i> REDLINE</button>
            @endif

            <div style="text-align: right; font-family: 'Roboto Mono', monospace; border-left: 1px solid var(--brand-border); padding-left: 20px;">
                <div style="font-size: 0.75rem; font-weight: bold; color: #fff;">{{ $organization->name ?? 'Unknown Org' }}</div>
                <div style="font-size: 0.65rem; color: var(--brand-primary);">{{ $user->first_name }} {{ substr($user->last_name, 0, 1) }}.</div>
            </div>
            <button wire:click="executeLogout" style="background: transparent; border: none; color: #8A9BB2; cursor: pointer;"><i data-lucide="power" style="width: 18px; height: 18px;"></i></button>
        </div>
    </div>

    <div class="drawer-overlay mobile-only" :class="{'open': mobileMenuOpen}" @click="mobileMenuOpen = false"></div>
    <div class="app-drawer mobile-only" :class="{'open': mobileMenuOpen}">
        <div class="drawer-header">
            @if($clientSettings && $clientSettings->logo_path)
                <img src="{{ Storage::url($clientSettings->logo_path) }}" alt="{{ $organization->name }}" class="drawer-client-logo">
            @else
                <div style="font-family: 'Montserrat', sans-serif; font-weight: bold; font-size: 1rem; color: #FFF;">{{ $organization->name ?? 'Dashboard' }}</div>
            @endif
            <button @click="mobileMenuOpen = false" class="mobile-header-btn"><i data-lucide="x" style="width: 24px; height: 24px; color: #8A9BB2;"></i></button>
        </div>
        
        <div class="drawer-nav">
            @if(($can['projects.view'] ?? false))
            <button wire:click="switchTab('overview')" @click="mobileMenuOpen = false" class="drawer-nav-btn {{ $activeTab === 'overview' ? 'active' : '' }}">
                <i data-lucide="layout-dashboard" style="width: 18px; height: 18px;"></i> OVERVIEW
            </button>
            @endif
            @if(($can['projects.view'] ?? false))
            <button wire:click="switchTab('sprints')" @click="mobileMenuOpen = false" class="drawer-nav-btn {{ $activeTab === 'sprints' ? 'active' : '' }}">
                <i data-lucide="git-merge" style="width: 18px; height: 18px;"></i> SPRINTS
            </button>
            @endif
            @if(($can['qa.view'] ?? false))
            <button wire:click="switchTab('qa_hub')" @click="mobileMenuOpen = false" class="drawer-nav-btn {{ $activeTab === 'qa_hub' ? 'active' : '' }}">
                <i data-lucide="shield-alert" style="width: 18px; height: 18px;"></i> QA HUB
            </button>
            @endif
            @if(($can['billing.view'] ?? false))
            <button wire:click="switchTab('financials')" @click="mobileMenuOpen = false" class="drawer-nav-btn {{ $activeTab === 'financials' ? 'active' : '' }}">
                <i data-lucide="credit-card" style="width: 18px; height: 18px;"></i> FINANCIALS
            </button>
            @endif
            @if(($can['documents.view'] ?? false))
            <button wire:click="switchTab('vault')" @click="mobileMenuOpen = false" class="drawer-nav-btn {{ $activeTab === 'vault' ? 'active' : '' }}">
                <i data-lucide="folder-lock" style="width: 18px; height: 18px;"></i> VAULT
            </button>
            @endif
            @if(($can['team.view'] ?? false))
                <button wire:click="switchTab('team')" @click="mobileMenuOpen = false" class="drawer-nav-btn {{ $activeTab === 'team' ? 'active' : '' }}">
                    <i data-lucide="users" style="width: 18px; height: 18px;"></i> COMMAND
                </button>
            @endif
        </div>

        <div class="drawer-footer">
            <div class="drawer-user-meta">
                <div style="font-size: 0.85rem; font-weight: bold; color: #fff;">{{ $organization->name ?? 'Unknown Org' }}</div>
                <div style="font-size: 0.75rem; color: var(--brand-primary);">{{ $user->first_name }} {{ substr($user->last_name, 0, 1) }}.</div>
            </div>
            <button wire:click="executeLogout" class="btn-panic" style="width: 100%; justify-content: center; padding: 12px; background: transparent; color: #FF3366; border-color: #FF3366;">
                <i data-lucide="power" style="width: 14px; height: 14px;"></i> TERMINATE SESSION
            </button>
        </div>
    </div>

    <!-- LOCKOUT BANNER -->
    @if($financialLockout)
        <div class="lockout-banner">
            <i data-lucide="lock" style="width: 18px; height: 18px;"></i>
            {{ $lockoutReason }}
        </div>
    @endif

    @if(session()->has('lockout_error'))
        <div style="background: rgba(255,51,102,0.1); border-bottom: 1px solid var(--brand-secondary); color: var(--brand-secondary); padding: 10px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
            {{ session('lockout_error') }}
        </div>
    @endif

    <!-- CONTENT AREA -->
    <div class="os-content" style="align-items: flex-start; justify-content: flex-start; max-width: 1400px; margin: 0 auto; width: 100%;">
        
        @if($activeTab === 'overview' && $activeProject)
            <!-- EXECUTIVE SUMMARY BAR -->
            <div class="exec-summary-bar">
                <div class="summary-node">
                    <span class="summary-label">Project Designation</span>
                    <span class="summary-value highlight">{{ $activeProject->title }}</span>
                </div>
                <div class="summary-node">
                    <span class="summary-label">Deployment Status</span>
                    <span class="summary-value" style="color: {{ $activeProject->status === 'Scoping' ? 'var(--brand-accent)' : '#00FF66' }};">{{ strtoupper($activeProject->status) }}</span>
                </div>
                <div class="summary-node">
                    <span class="summary-label">Structural Progress</span>
                    <span class="summary-value">{{ $overallProgress }}% COMPLETE</span>
                </div>
                <div class="summary-node">
                    <span class="summary-label">Financial Allocation</span>
                    <span class="summary-value">{{ $activeProject->currency }} {{ number_format($activeProject->total_budget, 2) }}</span>
                </div>
                <div class="summary-node">
                    <span class="summary-label">Strike Team Lead</span>
                    <span class="summary-value">{{ $leadArchitect }}</span>
                </div>
            </div>

            <!-- THE SPRINT TIMELINE -->
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

            <!-- LIVE ENGINEERING BACKLOG TERMINAL -->
            <div class="dash-grid">
                <div class="terminal-panel">
                    <div class="panel-header">
                        <span class="panel-title">
                            <i data-lucide="terminal" style="width: 16px; height: 16px;"></i> > APPROVED & LOCKED ENGINEERING BACKLOG
                        </span>
                        <div style="width: 8px; height: 8px; background: var(--brand-secondary); border-radius: 50%; box-shadow: 0 0 10px var(--brand-secondary); animation: pulse-bg 1.5s infinite;"></div>
                    </div>
                    <div class="log-feed">
                        @forelse($liveLogs as $log)
                            <div class="log-item">
                                <div class="log-time">[{{ $log['time'] }}] ARCHITECT: {{ $log['author'] }}</div>
                                <div class="log-text">> {!! nl2br(e($log['text'])) !!}</div>
                            </div>
                        @empty
                            <div class="log-item" style="border-left-color: var(--brand-secondary);">
                                <div class="log-text" style="color: #8A9BB2;">> AWAITING INITIAL SPRINT TELEMETRY...</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'sprints' && $activeProject)
            
            <div style="margin-bottom: 30px;">
                <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; letter-spacing: 1px;">ACTIVE SPRINT MATRIX</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2;">> Live tracking of all executing and completed tasks.</p>
            </div>

            <div class="task-list">
                @forelse($activeTasks as $task)
                    <div class="task-card" style="border-left-color: {{ $task->status === 'Done' ? '#00FF66' : ($task->status === 'QA_Review' ? 'var(--brand-secondary)' : 'var(--brand-primary)') }};">
                        <div>
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--brand-primary); margin-bottom: 5px;">> {{ strtoupper($task->phase_title) }}</div>
                            <div style="font-family: 'Inter', sans-serif; font-weight: bold; font-size: 1rem; color: #FFF;">{{ $task->title }}</div>
                            
                            @if($task->status === 'QA_Review')
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    @if(($can['projects.approve'] ?? false))
                                    <button wire:click="approveTask('{{ $task->id }}')" class="btn-submit" style="width: auto; padding: 6px 12px; font-size: 0.7rem; border-color: #00FF66; color: #00FF66;">
                                        <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> APPROVE & LOCK
                                    </button>
                                    @endif
                                    @if(($can['qa.create'] ?? false))
                                    <button wire:click="teleportToQa('{{ $task->id }}', '{{ addslashes($task->title) }}')" class="btn-submit" style="width: auto; padding: 6px 12px; font-size: 0.7rem; border-color: var(--brand-secondary); color: var(--brand-secondary);">
                                        <i data-lucide="shield-alert" style="width: 14px; height: 14px;"></i> LOG VULNERABILITY
                                    </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="status-badge status-{{ $task->status }}">
                            {{ str_replace('_', ' ', $task->status) }}
                        </div>
                    </div>
                @empty
                    <div style="text-align: center; padding: 60px; background: var(--brand-panel); border: 1px dashed var(--brand-border); border-radius: 4px;">
                        <i data-lucide="layers" style="width: 48px; height: 48px; color: #8A9BB2; margin-bottom: 15px; opacity: 0.5;"></i>
                        <div style="font-family: 'Roboto Mono', monospace; color: #8A9BB2;">NO ACTIVE TASKS DETECTED IN BATTLESPACE.</div>
                    </div>
                @endforelse
            </div>
        
        @elseif($activeTab === 'qa_hub' && $activeProject)
            
            <div style="width: 100%; margin-bottom: 20px;">
                <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; letter-spacing: 1px;">QUALITY ASSURANCE & DIRECT COMMS</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2;">> Log architectural vulnerabilities or message Command directly.</p>
            </div>

            <!-- THE 5-HOUR SLA ENFORCER -->
            <div class="sla-warning">
                <i data-lucide="clock" style="width: 24px; height: 24px; flex-shrink: 0;"></i>
                <div>
                    <strong style="display: block; margin-bottom: 5px; font-size: 0.85rem;">CRITICAL SLA NOTICE: THE 5-HOUR AUTO-APPROVAL RULE</strong>
                    Per JBS Praxis Standard Operating Procedure, any task entering 'QA Review' status requires immediate client validation. 
                    <span style="color: #FFF; font-weight: bold;">Zero feedback submitted within 5 hours of completion mathematically constitutes structural approval.</span> Post-approval revisions incur COPQ (Cost of Poor Quality) billing rates.
                </div>
            </div>

            <div class="qa-grid">
                
                <!-- LEFT: FORM -->
                <div class="qa-form-panel">
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--brand-primary); margin-bottom: 20px; border-bottom: 1px solid var(--brand-border); padding-bottom: 10px;">
                        > TRANSMIT INTELLIGENCE
                    </div>

                    @if(session()->has('qa_success'))
                        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-bottom: 15px; text-align: center;">
                            {{ session('qa_success') }}
                        </div>
                    @endif

                    @if(($can['qa.create'] ?? false))
                    <form wire:submit.prevent="submitQaTicket">
                        <!-- Dual Purpose Dropdown -->
                        @if($qaTaskId)
                            <div style="background: rgba(0, 229, 255, 0.1); border: 1px solid var(--brand-primary); padding: 10px 15px; margin-bottom: 15px; border-radius: 2px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--brand-primary); display: block;">TARGETED TASK LOCK:</span>
                                    <span style="font-size: 0.85rem; font-weight: bold; color: #FFF;">{{ collect($activeTasks)->firstWhere('id', $qaTaskId)->title ?? 'Unknown Task' }}</span>
                                </div>
                                <button type="button" wire:click="$set('qaTaskId', '')" style="background: transparent; border: none; color: var(--brand-secondary); cursor: pointer; font-size: 0.7rem; font-family: monospace;">
                                    [ CLEAR LOCK ]
                                </button>
                            </div>
                        @else
                            <div style="background: rgba(255, 255, 255, 0.05); border: 1px dashed var(--brand-border); padding: 10px 15px; margin-bottom: 15px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">
                                > TARGET MODE: GENERAL COMMUNICATION / INQUIRY
                            </div>
                        @endif

                        <div style="display: flex; gap: 10px;">
                            <div style="flex: 2;">
                                <input type="text" id="qa-title-input" wire:model="qaTitle" class="cyber-input" placeholder="Vulnerability Title / Subject" required x-init="setTimeout(() => $el.focus(), 100)">
                                @error('qaTitle') <span style="color: var(--brand-secondary); font-size: 0.65rem;">{{ $message }}</span> @enderror
                            </div>
                            <div style="flex: 1;">
                                <select wire:model="qaSeverity" class="cyber-select">
                                    <option value="Low">Low Priority</option>
                                    <option value="Medium">Standard</option>
                                    <option value="Critical">Critical Failure</option>
                                </select>
                            </div>
                        </div>

                        <textarea wire:model="qaDescription" class="cyber-textarea" placeholder="Define the exact parameters of the failure, or input your message to Command..." required></textarea>
                        @error('qaDescription') <span style="color: var(--brand-secondary); font-size: 0.65rem; display: block; margin-top: -10px; margin-bottom: 10px;">{{ $message }}</span> @enderror

                        <div style="margin-bottom: 20px;">
                            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--brand-primary); display: block; margin-bottom: 5px;">ATTACH EVIDENCE (OPTIONAL - JPG/PNG/PDF)</label>
                            <input type="file" wire:model="initialAttachment" class="cyber-input" style="padding: 10px; margin-bottom: 0;" accept=".png,.jpg,.jpeg,.pdf">
                            @error('initialAttachment') <span style="color: var(--brand-secondary); font-size: 0.65rem; display: block; margin-top: 5px;">{{ $message }}</span> @enderror
                        </div>

                        <button type="submit" class="btn-submit" wire:loading.attr="disabled">
                    </form>
                    @else
                        <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">> Your role can follow tickets but not raise them.</p>
                    @endif
                </div>

                <!-- RIGHT: HISTORICAL MATRIX -->
                <div class="qa-form-panel" style="background: rgba(10, 17, 40, 0.4); border-style: dashed;">
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #8A9BB2; margin-bottom: 20px; border-bottom: 1px dashed var(--brand-border); padding-bottom: 10px;">
                        > ACTIVE TRANSMISSIONS LEDGER
                    </div>

                    <div class="ticket-list">
                        @forelse($qaTickets as $ticket)
                            <div class="ticket-card" wire:click="openTicket('{{ $ticket->id }}')" style="cursor: pointer; border-left-color: {{ str_contains($ticket->title, '[DIRECT COMM]') ? 'var(--brand-accent)' : 'var(--brand-secondary)' }};">
                                <div class="ticket-header">
                                    <div>
                                        <div class="ticket-title">{{ $ticket->title }}</div>
                                        <div class="ticket-meta">
                                            {{ \Carbon\Carbon::parse($ticket->created_at)->format('M d, H:i') }} | 
                                            STATUS: <span style="color: {{ $ticket->status === 'Resolved' ? '#00FF66' : '#FFF' }}">{{ strtoupper($ticket->status) }}</span>
                                        </div>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.1); padding: 4px 8px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; border-radius: 2px;">
                                        {{ strtoupper($ticket->severity) }}
                                    </div>
                                </div>
                                <div class="ticket-desc">
                                    {{ Str::limit($ticket->description, 100) }}
                                </div>
                                @if($ticket->task_title)
                                    <div style="margin-top: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--brand-primary);">
                                        > LINKED TO: {{ $ticket->task_title }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div style="text-align: center; padding: 40px 0; color: #555; font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">
                                NO ACTIVE COMMUNICATIONS LOGGED.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>
            
        @elseif($activeTab === 'financials' && $activeProject)
            
            <div style="width: 100%; margin-bottom: 20px;">
                <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; letter-spacing: 1px;">FINANCIAL LEDGER</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2;">> Cryptographically secure billing and transaction upload portal.</p>
            </div>

            @if(session()->has('finance_success'))
                <div style="width: 100%; background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 20px; text-align: center; border-radius: 2px;">
                    {{ session('finance_success') }}
                </div>
            @endif

            <div class="finance-grid">
                
                <!-- LEFT: INVOICE LEDGER -->
                <div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--brand-primary); margin-bottom: 20px; border-bottom: 1px solid var(--brand-border); padding-bottom: 10px;">
                        > ISSUED INVOICES
                    </div>

                    @forelse($invoices as $inv)
                        <div class="invoice-card" style="border-left: 4px solid {{ $inv->status === 'Paid' ? '#00FF66' : ($inv->status === 'Overdue' ? 'var(--brand-secondary)' : 'var(--brand-accent)') }};">
                            <div class="invoice-meta">
                                <div class="inv-number">{{ $inv->invoice_number }}</div>
                                <div class="inv-details">
                                    ISSUED: {{ \Carbon\Carbon::parse($inv->issued_at)->format('M d, Y') }}<br>
                                    DUE: <span style="color: {{ $inv->status === 'Overdue' ? 'var(--brand-secondary)' : '#8A9BB2' }}">{{ \Carbon\Carbon::parse($inv->due_date)->format('M d, Y') }}</span>
                                </div>
                                <div style="margin-top: 5px;">
                                    <span class="status-badge status-{{ $inv->status }}" style="font-size: 0.6rem; padding: 4px 8px;">{{ strtoupper($inv->status) }}</span>
                                    @if($inv->has_pending_pop)
                                        <span style="background: rgba(0, 229, 255, 0.1); color: var(--brand-primary); border: 1px solid var(--brand-primary); padding: 4px 8px; font-size: 0.6rem; font-family: monospace; border-radius: 2px; margin-left: 5px;">VERIFICATION IN PROGRESS</span>
                                    @endif
                                </div>
                            </div>
                            
                            <div style="text-align: right; display: flex; flex-direction: column; gap: 15px; align-items: flex-end;">
                                <div class="inv-amount">{{ $inv->currency }} {{ number_format($inv->total_amount, 2) }}</div>
                                
                                <div style="display: flex; gap: 10px;">
                                    <button class="btn-submit" style="padding: 6px 12px; font-size: 0.7rem; width: auto;" onclick="alert('PDF Generation Engine (Phase 9) Required.')">
                                        <i data-lucide="download" style="width: 14px; height: 14px;"></i> PDF
                                    </button>
                                    
                                    @if($inv->status !== 'Paid' && !$inv->has_pending_pop)
                                        @if(($can['billing.create'] ?? false))
                                        <button wire:click="triggerPopUpload('{{ $inv->id }}', {{ $inv->total_amount }})" class="btn-submit" style="padding: 6px 12px; font-size: 0.7rem; width: auto; background: var(--brand-primary); color: var(--brand-bg);">
                                            <i data-lucide="upload-cloud" style="width: 14px; height: 14px;"></i> UPLOAD POP
                                        </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div style="text-align: center; padding: 40px; border: 1px dashed var(--brand-border); color: #8A9BB2; font-family: 'Roboto Mono', monospace;">
                            NO INVOICES GENERATED FOR THIS BATTLESPACE.
                        </div>
                    @endforelse
                </div>

                <!-- RIGHT: REMITTANCE INFO & UPLOAD FORM -->
                <div>
                    @if($uploadInvoiceId)
                        <!-- POP Upload Form -->
                        <div class="pop-upload-form">
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #FFF; margin-bottom: 20px; font-weight: bold; border-bottom: 1px dashed var(--brand-border); padding-bottom: 10px;">
                                > SECURE POP TRANSMISSION
                            </div>

                            @if(($can['billing.create'] ?? false))
                            <form wire:submit.prevent="submitProofOfPayment">
                                <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">PAYMENT METHOD</label>
                                <select wire:model="popMethod" class="cyber-select">
                                    <option value="Bank Transfer">Bank Transfer / Wire</option>
                                    <option value="USDT">Cryptocurrency (USDT)</option>
                                    <option value="Campay">Mobile Money (Campay)</option>
                                </select>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">AMOUNT REMITTED</label>
                                        <input type="number" step="0.01" wire:model="popAmount" class="cyber-input" required>
                                    </div>
                                    <div>
                                        <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">TX HASH / REF ID</label>
                                        <input type="text" wire:model="popRef" class="cyber-input" required>
                                    </div>
                                </div>

                                <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">ATTACH CRYPTOGRAPHIC PROOF (PDF/JPG/PNG)</label>
                                <input type="file" wire:model="popFile" class="cyber-input" style="padding: 8px;" accept=".pdf,.jpg,.jpeg,.png" required>
                                @error('popFile') <span style="color: var(--brand-secondary); font-size: 0.65rem;">{{ $message }}</span> @enderror

                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button type="button" wire:click="cancelPopUpload" class="btn-submit" style="flex: 1; border-color: #8A9BB2; color: #8A9BB2;">ABORT</button>
                                    <button type="submit" class="btn-submit" style="flex: 2; background: var(--brand-primary); color: var(--brand-bg);" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="submitProofOfPayment">TRANSMIT POP</span>
                                        <span wire:loading wire:target="submitProofOfPayment">UPLOADING...</span>
                                    </button>
                                </div>
                            </form>
                            @endif
                        </div>
                    @else
                        <!-- Remittance Instructions -->
                        <div class="bank-details-box">
                            <div style="color: #FFF; font-weight: bold; font-size: 1rem; margin-bottom: 15px; border-bottom: 1px solid var(--brand-border); padding-bottom: 5px;">
                                <i data-lucide="landmark" style="width: 18px; height: 18px; display: inline-block; vertical-align: bottom;"></i> AUTHORIZED REMITTANCE
                            </div>
                            
                            @forelse($remittanceAccounts as $acc)
                                <div style="margin-bottom: 20px;">
                                    <strong>METHOD:</strong> {{ strtoupper($acc->type) }}<br>
                                    <strong>PROVIDER:</strong> {{ $acc->provider_name }}<br>
                                    <strong>ACCOUNT NAME:</strong> {{ $acc->account_name }}<br>
                                    <strong>REF / ADDRESS:</strong> <span style="color: #FFF;">{{ $acc->account_reference }}</span><br>
                                    @if($acc->routing_code) <strong>ROUTING/SWIFT:</strong> {{ $acc->routing_code }} @endif
                                </div>
                            @empty
                                <div>No active remittance accounts configured in MDM. Contact Command.</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
            @elseif($activeTab === 'vault')
            
            <div style="width: 100%; margin-bottom: 20px;">
                <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; letter-spacing: 1px;">DOCUMENT VAULT & OS CONFIG</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2;">> Immutable legal contracts and white-label branding controls.</p>
            </div>

            <div class="vault-grid">
                
                <!-- LEFT: SECURE DOCUMENTS -->
                <div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--brand-primary); margin-bottom: 20px; border-bottom: 1px solid var(--brand-border); padding-bottom: 10px;">
                        > SECURED LEGAL CONTRACTS
                    </div>

                    <div style="margin-bottom: 30px;">
                        <h4 style="font-family: 'Montserrat', sans-serif; font-size: 0.9rem; color: #FFF; margin-bottom: 15px;">STATEMENTS OF WORK (SOW)</h4>
                        @forelse($vaultSows as $sow)
                            <div class="vault-doc-card">
                                <div>
                                    <div class="vault-doc-title">{{ $sow->title }}</div>
                                    <div class="vault-doc-meta">REF: {{ $sow->proposal_ref }} | BIND DATE: {{ \Carbon\Carbon::parse($sow->updated_at)->format('M d, Y') }}</div>
                                </div>
                                <a href="{{ route('portal.proposal', ['token' => $sow->token_hash]) }}" target="_blank" class="btn-submit" style="width: auto; padding: 8px 15px; font-size: 0.7rem; text-decoration: none;">
                                    <i data-lucide="external-link" style="width: 14px; height: 14px;"></i> VIEW
                                </a>
                            </div>
                        @empty
                            <div style="text-align: center; padding: 20px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">NO SECURED SOWS FOUND.</div>
                        @endforelse
                    </div>

                    <div>
                        <h4 style="font-family: 'Montserrat', sans-serif; font-size: 0.9rem; color: #FFF; margin-bottom: 15px;">PROJECT HANDOVERS (CLOSURE)</h4>
                        @forelse($vaultSignoffs as $signoff)
                            <div class="vault-doc-card" style="border-left-color: var(--brand-accent);">
                                <div>
                                    <div class="vault-doc-title">{{ $signoff->project_title }}</div>
                                    <div class="vault-doc-meta">REF: {{ substr($signoff->token, 0, 12) }} | HASH: {{ substr($signoff->client_signature_hash, 0, 10) }}...</div>
                                </div>
                                <a href="{{ route('portal.signoff.gateway', ['token' => $signoff->token]) }}" target="_blank" class="btn-submit" style="width: auto; padding: 8px 15px; font-size: 0.7rem; text-decoration: none; border-color: var(--brand-accent); color: var(--brand-accent);">
                                    <i data-lucide="external-link" style="width: 14px; height: 14px;"></i> VIEW
                                </a>
                            </div>
                        @empty
                            <div style="text-align: center; padding: 20px; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">NO EXECUTED HANDOVERS FOUND.</div>
                        @endforelse
                    </div>
                </div>

                <!-- RIGHT: BRAND ENGINE CONFIG -->
                <div class="brand-config-panel">
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #FFF; margin-bottom: 20px; border-bottom: 1px dashed var(--brand-border); padding-bottom: 10px;">
                        <i data-lucide="palette" style="width: 16px; height: 16px; display: inline-block; vertical-align: bottom;"></i> WHITE-LABEL ENGINE
                    </div>

                    @if(session()->has('brand_success'))
                        <div style="background: rgba(0,255,102,0.1); border: 1px solid #00FF66; color: #00FF66; padding: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-bottom: 20px; text-align: center; border-radius: 2px;">
                            {{ session('brand_success') }}
                        </div>
                    @endif

                    @if(($can['settings.edit'] ?? false))
                    <form wire:submit.prevent="saveBrandSettings">
                        <div class="color-picker-wrap">
                            <div>
                                <div style="font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #FFF; font-weight: bold;">Primary Highlight</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">Used for Active Tabs & Accents</div>
                            </div>
                            <input type="color" wire:model="brandPrimary" class="color-picker">
                        </div>

                        <div class="color-picker-wrap">
                            <div>
                                <div style="font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #FFF; font-weight: bold;">Secondary (Warning)</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">Used for Errors & Deadlines</div>
                            </div>
                            <input type="color" wire:model="brandSecondary" class="color-picker">
                        </div>

                        <div class="color-picker-wrap">
                            <div>
                                <div style="font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #FFF; font-weight: bold;">Accent Color</div>
                                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">Used for Pending / Action States</div>
                            </div>
                            <input type="color" wire:model="brandAccent" class="color-picker">
                        </div>

                        <div style="margin-top: 25px; margin-bottom: 20px;">
                            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; display: block; margin-bottom: 10px;">ENTERPRISE LOGO (PNG/SVG < 5MB)</label>
                            <input type="file" wire:model="brandLogo" class="cyber-input" style="padding: 10px;" accept=".png,.svg">
                            @error('brandLogo') <span style="color: var(--brand-secondary); font-size: 0.65rem;">{{ $message }}</span> @enderror
                        </div>

                        <button type="submit" class="btn-submit" style="background: var(--brand-primary); color: var(--brand-bg);" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveBrandSettings">OVERRIDE MATRIX COLORS</span>
                            <span wire:loading wire:target="saveBrandSettings">COMPILING...</span>
                        </button>
                        <div style="text-align: center; margin-top: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #555;">
                            Default Fallback: JBS Praxis Protocol
                        </div>
                    </form>
                    @endif
                </div>

            </div>
    
    @elseif($activeTab === 'team' && ($can['team.view'] ?? false))
            
            <div style="width: 100%; margin-bottom: 20px;">
                <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; letter-spacing: 1px;">TEAM COMMAND CENTER</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #8A9BB2;">> Provision and terminate secondary access nodes (Max Capacity: 3). Domain restriction active.</p>
            </div>

            <div class="finance-grid"> <!-- Reusing grid layout -->
                
                <!-- LEFT: ACTIVE NODES (KILL SWITCH) -->
                <div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--brand-primary); margin-bottom: 20px; border-bottom: 1px solid var(--brand-border); padding-bottom: 10px;">
                        > PROVISIONED NODES ({{ count($teamMembers ?? []) }} / 3)
                    </div>

                    @foreach($teamMembers ?? [] as $member)
                        <div class="invoice-card" style="border-left: 4px solid {{ $member->status === 'Active' ? '#00FF66' : ($member->status === 'Suspended' ? 'var(--brand-secondary)' : 'var(--brand-accent)') }}; margin-bottom: 10px;">
                            <div class="invoice-meta">
                                <div class="inv-number" style="font-size: 1rem;">{{ $member->first_name }} {{ $member->last_name }}</div>
                                <div class="inv-details" style="color: #FFF;">{{ $member->email }}</div>
                                <div class="inv-details" style="margin-top: 8px;">
                                    @if(!$member->is_primary_client && ($can['team.edit'] ?? false) && $member->id !== $this->user->id)
                                        <label style="font-size: 0.6rem; color: #8A9BB2; display: block; margin-bottom: 3px;">PORTAL ROLE:</label>
                                        <select wire:change="changeMemberRole('{{ $member->id }}', $event.target.value)" class="cyber-select" style="padding: 4px; font-size: 0.7rem; max-width: 250px;">
                                            @foreach($clientRoles as $role)
                                                <option value="{{ $role->id }}" {{ (int) $member->role_id === (int) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        ROLE: <span style="color: var(--brand-primary);">{{ strtoupper($member->role_name) }}</span>
                                    @endif
                                </div>
                                <div class="inv-details" style="margin-top: 10px;">
                                    @if($member->is_primary_client)
                                        ACCESS: <span style="color: var(--brand-primary);">GLOBAL (ALPHA NODE)</span>
                                    @else
                                        <label style="font-size: 0.6rem; color: #8A9BB2; display: block; margin-bottom: 3px;">BATTLESPACE RESTRICTION:</label>
                                        @if(($can['team.edit'] ?? false))
                                        <select wire:change="updateNodeAccess('{{ $member->id }}', $event.target.value)" class="cyber-select" style="padding: 4px; font-size: 0.7rem; max-width: 250px;">
                                            <option value="">-- GLOBAL (ALL PROJECTS) --</option>
                                            @foreach($allProjects as $proj)
                                                <option value="{{ $proj->id }}" {{ $member->restricted_project_id === $proj->id ? 'selected' : '' }}>
                                                    {{ strtoupper($proj->title) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @else
                                            <span style="font-size: 0.7rem;">{{ $member->restricted_project_title ? strtoupper($member->restricted_project_title) : 'ALL PROJECTS' }}</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            
                            <div style="text-align: right; display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
                                <span class="status-badge" style="font-size: 0.6rem; color: {{ $member->status === 'Active' ? '#00FF66' : '#FFF' }}; border-color: currentColor;">{{ strtoupper($member->status) }}</span>
                                
                                @if(!$member->is_primary_client && ($can['team.edit'] ?? false) && $member->id !== $this->user->id)
                                    <button wire:click="toggleSuspendUser('{{ $member->id }}')" class="btn-submit" style="padding: 6px 12px; font-size: 0.65rem; width: auto; border-color: var(--brand-secondary); color: var(--brand-secondary);">
                                        <i data-lucide="power" style="width: 12px; height: 12px;"></i> 
                                        {{ $member->status === 'Suspended' ? 'DEFROST NODE' : 'KILL SWITCH' }}
                                    </button>
                                @elseif($member->is_primary_client)
                                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; padding: 6px 0;">[ PRIMARY ALPHA ]</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- RIGHT: INVITE FORM -->
                <div class="pop-upload-form">
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #FFF; margin-bottom: 20px; font-weight: bold; border-bottom: 1px dashed var(--brand-border); padding-bottom: 10px;">
                        <i data-lucide="user-plus" style="width: 16px; height: 16px; display: inline-block; vertical-align: bottom;"></i> DISPATCH ACCESS LINK
                    </div>

                    @if(session()->has('team_error'))
                        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--brand-secondary); color: var(--brand-secondary); padding: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-bottom: 15px; text-align: center; border-radius: 2px;">
                            {{ session('team_error') }}
                        </div>
                    @endif
                    @if($errors->any())
                        <div style="background: rgba(255,51,102,0.1); border: 1px solid var(--brand-secondary); color: var(--brand-secondary); padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; margin-bottom: 15px; border-radius: 2px;">
                            <strong style="display: block; margin-bottom: 5px;">> TRANSMISSION REJECTED:</strong>
                            <ul style="margin: 0; padding-left: 20px;">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(($can['team.create'] ?? false))
                    <form wire:submit.prevent="inviteTeamMember">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <input type="text" wire:model="inviteFirstName" class="cyber-input" placeholder="First Name" required>
                            <input type="text" wire:model="inviteLastName" class="cyber-input" placeholder="Last Name" required>
                        </div>
                        
                        <input type="email" wire:model="inviteEmail" class="cyber-input" placeholder="Corporate Email (Must match Domain)" required>
                        
                        <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 10px; display: block;">RESTRICT TO SPECIFIC BATTLESPACE? (OPTIONAL)</label>
                        <select wire:model="inviteProjectId" class="cyber-select">
                            <option value="">-- ALL PROJECTS (GLOBAL ACCESS) --</option>
                            @foreach($allProjects as $proj)
                                <option value="{{ $proj->id }}">{{ strtoupper($proj->title) }}</option>
                            @endforeach
                        </select>

                        <label for="invite-role" style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-top: 10px; display: block;">PORTAL ROLE</label>
                        <select id="invite-role" wire:model="inviteRoleId" class="cyber-select">
                            <option value="">Client Member (default)</option>
                            @foreach($clientRoles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>

                        <button type="submit" class="btn-submit" style="background: var(--brand-primary); color: var(--brand-bg); margin-top: 15px;" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="inviteTeamMember">GENERATE ZERO-KNOWLEDGE LINK</span>
                            <span wire:loading wire:target="inviteTeamMember">ENCRYPTING...</span>
                        </button>
                    </form>
                    @else
                        <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2;">> Your role cannot invite team members.</p>
                    @endif
                </div>
            </div>
        @endif
    
    </div>
    
    <div class="floating-action-stack mobile-only">
        @if(($can['support.create'] ?? false))
        <div wire:click="$set('showRedlineModal', true)" class="fab-btn fab-redline" title="Initiate Redline Protocol">
            <i data-lucide="triangle-alert" style="width: 20px; height: 20px;"></i>
        </div>
        @endif
        
        <div @click="showGuide = true" class="fab-btn fab-help" title="Executive Manual">
            <i data-lucide="book-open" style="width: 20px; height: 20px;"></i>
        </div>

        @if(($can['qa.view'] ?? false))
        <div wire:click="switchTab('qa_hub')" class="fab-btn fab-qa {{ $activeTab === 'qa_hub' ? 'qa-active-hide' : '' }}" title="Message Command">
            <i data-lucide="message-square" style="width: 20px; height: 20px;"></i>
        </div>
        @endif
    </div>

    @if(($can['qa.view'] ?? false))
    <button wire:click="switchTab('qa_hub')" class="floating-comm-btn desktop-only {{ $activeTab === 'qa_hub' ? 'qa-active-hide' : '' }}" title="Message Command / Report Bug">
        <i data-lucide="message-square"></i>
    </button>
    @endif

    @if(session()->has('global_success'))
        <div style="position: fixed; bottom: 30px; right: 30px; background: rgba(0, 255, 102, 0.1); border: 1px solid #00FF66; color: #00FF66; padding: 15px 25px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; z-index: 9999; box-shadow: 0 0 30px rgba(0,255,102,0.2); backdrop-filter: blur(10px);" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            {{ session('global_success') }}
        </div>
    @endif
    
    

    <!-- AUTHENTICATED REDLINE MODAL -->
    @if($showRedlineModal)
        <div style="position: fixed; inset: 0; background: rgba(3, 5, 10, 0.95); backdrop-filter: blur(10px); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px;">
            <div style="background: var(--brand-panel); border: 1px solid var(--brand-secondary); border-top: 4px solid var(--brand-secondary); border-radius: 4px; padding: 40px; box-shadow: 0 20px 50px rgba(255,51,102,0.2); max-width: 600px; width: 100%; position: relative;">
                
                <i data-lucide="triangle-alert" style="width: 48px; height: 48px; color: var(--brand-secondary); margin: 0 auto 20px auto; display: block;"></i>
                <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; color: #FFF; letter-spacing: 1px; text-align: center; margin-bottom: 10px;">INITIATE REDLINE PROTOCOL</h2>
                <p style="font-size: 0.85rem; color: #8A9BB2; text-align: center; margin-bottom: 30px;">Warning: This action bypasses standard SLA queues and directly pages Level 4/5 Command. Standard emergency engagement fees may apply depending on your SLA tier.</p>
                
                @if(($can['support.create'] ?? false))
                <form wire:submit.prevent="triggerAuthenticatedRedline">
                    <label style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--brand-secondary); display: block; margin-bottom: 10px;">DEFINE INFRASTRUCTURE FAILURE</label>
                    <textarea wire:model="redlineAuthDescription" style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,51,102,0.3); color: #FFF; padding: 15px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; min-height: 120px; resize: vertical; margin-bottom: 20px;" required placeholder="Enter diagnostic parameters..."></textarea>
                    
                    <div style="display: flex; gap: 15px;">
                        <button type="button" wire:click="$set('showRedlineModal', false)" style="flex: 1; background: transparent; border: 1px solid #8A9BB2; color: #8A9BB2; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; border-radius: 2px;">
                            ABORT
                        </button>
                        <button type="submit" style="flex: 2; background: var(--brand-secondary); color: #FFF; border: none; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; border-radius: 2px; box-shadow: 0 0 20px rgba(255,51,102,0.4);" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="triggerAuthenticatedRedline">TRANSMIT SOS</span>
                            <span wire:loading wire:target="triggerAuthenticatedRedline">TRANSMITTING...</span>
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    @endif
    
    <div class="os-guide-overlay" :class="{'open': showQaOffcanvas}" @click="showQaOffcanvas = false"></div>
    <div class="os-guide-panel" :class="{'open': showQaOffcanvas}" style="width: 600px; max-width: 100vw;">
        @if($selectedTicket)
            <div class="guide-header">
                <div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--brand-primary); margin-bottom: 5px;">> TICKET #{{ strtoupper(substr($selectedTicket->id, 0, 8)) }}</div>
                    <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.1rem; color: #FFF;">{{ $selectedTicket->title }}</h2>
                    <span class="status-badge" style="font-size: 0.6rem; margin-top: 5px;">{{ strtoupper($selectedTicket->status) }}</span>
                </div>
                <button @click="showQaOffcanvas = false" style="background: transparent; border: none; color: #8A9BB2; cursor: pointer; padding: 5px;"><i data-lucide="x" style="width: 20px; height: 20px;"></i></button>
            </div>

            <div class="guide-content">
                <div style="background: rgba(0,0,0,0.3); border: 1px dashed var(--brand-border); padding: 15px; border-radius: 2px; color: #B0B8C4; font-size: 0.85rem; line-height: 1.6; margin-bottom: 25px;">
                    {!! nl2br(e($selectedTicket->description)) !!}
                </div>
                
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--brand-primary); margin-bottom: 10px;">> EVIDENCE GALLERY:</div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-bottom: 15px;">
                    @forelse($ticketAttachments as $att)
                        <div style="border: 1px solid var(--brand-border); padding: 10px; text-align: center; border-radius: 2px; background: rgba(0,0,0,0.5); transition: 0.3s;" onmouseover="this.style.borderColor='var(--brand-primary)'" onmouseout="this.style.borderColor='var(--brand-border)'">
                            <i data-lucide="image" style="width: 24px; height: 24px; color: #8A9BB2; margin-bottom: 10px;"></i><br>
                            <a href="{{ route('portal.file', ['path' => $att->file_path]) }}" target="_blank" style="color: var(--brand-primary); font-size: 0.7rem; font-family: 'Roboto Mono', monospace; text-decoration: none; word-break: break-all;">
                                {{ Str::limit($att->file_name, 15) }}
                            </a>
                        </div>
                    @empty
                        <div style="grid-column: 1 / -1; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; padding: 10px;">No attachments provided.</div>
                    @endforelse
                </div>

                @if(in_array($selectedTicket->status, ['Open', 'Investigating']))
                    <div style="display: flex; gap: 10px; margin-bottom: 30px; align-items: center;">
                        <input type="file" wire:model="newQaAttachment" class="cyber-input" style="padding: 6px; font-size: 0.75rem; margin-bottom: 0;" accept=".png,.jpg,.jpeg,.pdf">
                        @if(($can['qa.create'] ?? false))
                        <button wire:click="uploadQaEvidence" class="btn-submit" style="width: auto; padding: 8px 15px;" wire:loading.attr="disabled" wire:target="newQaAttachment, uploadQaEvidence">
                            <span wire:loading.remove wire:target="uploadQaEvidence"><i data-lucide="upload" style="width: 14px; height: 14px;"></i> UPLOAD</span>
                            <span wire:loading wire:target="uploadQaEvidence">PROCESSING...</span>
                        </button>
                        @endif
                    </div>
                    @error('newQaAttachment') <span style="color: var(--brand-secondary); font-size: 0.65rem; display: block; margin-top: -25px; margin-bottom: 20px;">{{ $message }}</span> @enderror
                @endif

                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--brand-primary); margin-bottom: 15px; border-bottom: 1px dashed var(--brand-border); padding-bottom: 5px;">> COMMUNICATION THREAD</div>
                
                @forelse($ticketComments as $comment)
                    <div style="padding: 15px; border-radius: 4px; margin-bottom: 15px; border: 1px solid var(--brand-border); background: rgba(0,0,0,0.3); border-left: 3px solid {{ $comment->user_id === Auth::id() ? 'var(--brand-primary)' : 'var(--brand-accent)' }};">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; margin-bottom: 10px; display: flex; justify-content: space-between;">
                            <span><strong>{{ $comment->first_name }} {{ $comment->last_name }}</strong></span>
                            <span>{{ \Carbon\Carbon::parse($comment->created_at)->format('M d, H:i') }}</span>
                        </div>
                        <div style="color: #FFF; font-size: 0.85rem; line-height: 1.5;">
                            {!! nl2br(e($comment->comment_text)) !!}
                        </div>
                    </div>
                @empty
                    <div style="text-align: center; padding: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2;">No thread history.</div>
                @endforelse

                @if(in_array($selectedTicket->status, ['Open', 'Investigating', 'Resolved']))
                    <div style="margin-top: 20px;">
                        <textarea wire:model="newCommentText" class="cyber-textarea" rows="3" placeholder="Type response to HQ here..."></textarea>
                        @if(($can['qa.create'] ?? false))
                        <button wire:click="postTicketComment" class="btn-submit" style="padding: 8px 20px; width: 100%;">
                            <i data-lucide="send" style="width: 14px; height: 14px;"></i> TRANSMIT RESPONSE
                        </button>
                        @endif
                    </div>
                    
                    @if($selectedTicket->task_id && $selectedTicket->status !== 'Closed')
                        <div style="margin-top: 20px; border-top: 1px dashed var(--brand-border); padding-top: 20px;">
                            @if(($can['projects.approve'] ?? false))
                            <button wire:click="approveAndLockFromQa" class="btn-submit" style="background: rgba(0,255,102,0.1); border-color: #00FF66; color: #00FF66; width: 100%;" onclick="confirm('Are you sure you want to override this ticket and permanently lock the linked task?') || event.stopImmediatePropagation()">
                                <i data-lucide="lock" style="width: 14px; height: 14px;"></i> OVERRIDE: APPROVE & LOCK LINKED TASK
                            </button>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

            @if($selectedTicket->status === 'Resolved')
                <div style="padding: 20px 30px; border-top: 1px solid var(--brand-border); background: rgba(0,255,102,0.1);">
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #00FF66; margin-bottom: 10px; text-align: center;">
                        HQ HAS LOGGED THIS VULNERABILITY AS PATCHED.
                    </div>
                    @if(($can['projects.approve'] ?? false))
                    <button wire:click="approveTicketFix" class="btn-submit" style="border-color: #00FF66; color: #00FF66; width: 100%;">
                        <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> APPROVE FIX & CLOSE TICKET
                    </button>
                    @endif
                </div>
            @endif
        @endif
    </div>
    
    <div class="os-guide-overlay" :class="{'open': showGuide}" @click="showGuide = false"></div>
    <div class="os-guide-panel" :class="{'open': showGuide}">
        <div class="guide-header">
            <div style="font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1.1rem; color: #FFF; letter-spacing: 1px;">
                <i data-lucide="book-open" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; color: var(--brand-primary); margin-right: 8px;"></i>
                EXECUTIVE MANUAL
            </div>
            <button @click="showGuide = false" style="background: transparent; border: none; color: #8A9BB2; cursor: pointer; padding: 5px;">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>
        
        <div class="guide-content">
            <p>Welcome to the JBS Praxis Operating System (Client Node). This secure terminal is your direct cryptographic link to the JBS engineering strike team. Below is your comprehensive operational manual for navigating the active battlespace.</p>

            <div class="guide-section-title">I. MODULE DIRECTORY</div>

            <div class="guide-module-block">
                <div class="guide-module-title">01 // OVERVIEW</div>
                <p>Your high-level command telemetry. This module provides a macroscopic view of your active deployment. You can track exact structural progress against the timeline, monitor live capital allocation, and verify the status of the current operational phase. The 'Live Engineering Backlog' terminal acts as a direct window into the minds of your Strike Team, streaming unedited, real-time architectural logs directly from the backend as code is deployed.</p>
            </div>

            <div class="guide-module-block">
                <div class="guide-module-title">02 // SPRINTS</div>
                <p>The agile execution matrix. While the Overview provides the macro view, the Sprints tab tracks the granular, tactical, day-to-day execution of your Statement of Work. Every architectural deliverable is broken down into specific tasks that dynamically flow through a strict lifecycle: <strong>To-Do</strong> (pending backlog), <strong>In-Progress</strong> (active engineering), <strong>QA Review</strong> (awaiting your executive validation), and <strong>Done</strong> (locked, shipped, and immutably finalized).</p>
            </div>

            <div class="guide-module-block">
                <div class="guide-module-title">03 // QA HUB</div>
                <p>The centralized communication matrix and quality control gate. If a deliverable submitted for review fails to meet your specified parameters, you must log a vulnerability ticket here. You can tag a specific task, flag its severity (Low, Medium, Critical), and enforce accountability. Alternatively, you can use the Hub to send direct, tracked transmissions to Command. The historical ledger on the right maintains an immutable audit trail of all resolved and open tickets.</p>
            </div>

            <div class="guide-module-block">
                <div class="guide-module-title">04 // FINANCIALS</div>
                <p>The cryptographic billing and capital allocation portal. This node automatically tracks all generated tax invoices linked to your project phases. When an invoice requires clearance, you can view the active MDM remittance instructions (Bank Wire, USDT, or Campay), and upload your cryptographic Proof of Payment (POP). Uploading a valid POP automatically shifts the invoice into 'Verification in Progress' status, signaling HQ to defrost any frozen deployment phases.</p>
            </div>

            <div class="guide-module-block">
                <div class="guide-module-title">05 // VAULT & OS CONFIG</div>
                <p>Your immutable legal repository and enterprise customization engine. The left matrix securely stores all counter-signed Statements of Work (SOWs) and final Project Handover documents, sealed with SHA-256 client/HQ hashes. The right panel houses the White-Label Brand Engine. By inputting your corporate hex color codes and uploading an enterprise logo, you can instantly override the default JBS Praxis interface, reskinning the entire Client OS to match your brand identity.</p>
            </div>

            <div class="guide-module-block">
                <div class="guide-module-title">06 // TEAM COMMAND</div>
                <p><em>(Restricted to Primary Alpha Node only).</em> Your access provisioning center. You retain the authority to independently generate Zero-Knowledge setup links to onboard up to 2 additional executive nodes (e.g., your CTO or CFO). For maximum security, the system strictly enforces a domain-lock; invited emails must match your exact corporate domain. You may also assign secondary nodes global access, or restrict their visibility to a single, specific battlespace.</p>
            </div>

            <div class="guide-section-title">II. PROTOCOLS & FAQ</div>

            <div class="faq-block">
                <div class="faq-q"><i data-lucide="lock"></i> Why is my dashboard locked with an Amber/Red banner?</div>
                <div style="font-size: 0.8rem;">The Financial Lockout mechanism automatically engages when an initial deposit is pending, or an active invoice becomes overdue. Navigate to the Financials tab and upload your Proof of Payment to initiate system defrosting.</div>
            </div>

            <div class="faq-block">
                <div class="faq-q"><i data-lucide="clock"></i> What is the 5-Hour Auto-Approval SLA?</div>
                <div style="font-size: 0.8rem;">To maintain agile momentum, any sprint task shifted to 'QA Review' status requires immediate validation. If zero feedback is logged in the QA Hub within 5 hours of completion, the system mathematically flags the task as approved. Post-approval revisions incur Cost of Poor Quality (COPQ) billing rates.</div>
            </div>

            <div class="faq-block">
                <div class="faq-q"><i data-lucide="triangle-alert"></i> When should I trigger the REDLINE protocol?</div>
                <div style="font-size: 0.8rem;">The Redline button (Top Navigation) is a panic switch for critical infrastructure failures. It bypasses standard SLA triage queues and directly pages Level 4/5 Command. <strong>Warning:</strong> Standard emergency engagement fees may apply depending on your SLA tier.</div>
            </div>

            <div style="text-align: center; margin-top: 40px; margin-bottom: 20px; opacity: 0.5;">
                <img src="{{ asset('assets/img/logo_monochrome.svg') }}" style="height: 30px; margin-bottom: 10px;" onerror="this.style.display='none'">
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.6rem; letter-spacing: 2px;">END OF DIRECTIVE</div>
            </div>
        </div>
    </div>
    
    <script> 
        // 1. Run on initial page load
        document.addEventListener('DOMContentLoaded', () => { 
            if(typeof lucide !== 'undefined') lucide.createIcons(); 
        });

        // 2. Re-run after every Livewire DOM morph (like switching tabs)
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => {
                if(typeof lucide !== 'undefined') lucide.createIcons();
            });
        });
    </script>
</div>
</div>