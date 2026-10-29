<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBS Praxis - Predictive Deployment Briefing</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&family=Inter:wght@300;400;600&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            /* THE JBS PRAXIS COLOR MATRIX */
            --midnight-navy: #0A1128;
            --luminous-teal: #00E5FF;
            --stark-white: #FFFFFF;
            --slate-navy: #141E3C;
            --terminal-red: #FF3366;
            --executive-gold: #D4AF37;
            
            /* Dimensions */
            --page-width: 210mm;
            --page-height: 297mm;
        }
	.top-right-logo {
    	position: absolute;
    	top: 40px;
    	right: 40px;
    	height: 60px;
    	width: auto;
    	z-index: 10;
	}

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            font-family: 'Inter', sans-serif;
            color: var(--midnight-navy);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding-top: 20px;
            padding-bottom: 20px;
        }

        /* Typography */
        h1, h2, h3, h4 {
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            margin: 0;
        }

        p {
            font-size: 11pt;
            line-height: 1.6;
            margin-bottom: 1em;
        }

        .mono {
            font-family: 'Fira Code', monospace;
        }

        /* Page Container */
        .page {
            width: var(--page-width);
            height: var(--page-height);
            background: var(--stark-white);
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        /* UTILITIES */
        .bg-navy { background-color: var(--midnight-navy); color: var(--stark-white); }
        .text-teal { color: var(--luminous-teal); }
        .text-navy { color: var(--midnight-navy); }
        .text-red { color: var(--terminal-red); }
        .border-teal { border-color: var(--luminous-teal); }
        
        .grid-bg {
            background-image: 
                linear-gradient(rgba(0, 229, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 229, 255, 0.05) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        /* PAGE 1: COVER & MANIFESTO */
        .page-1 {
            background: var(--midnight-navy);
        }

        .cover-header {
            height: 45%;
            padding: 40px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-mark {
            font-family: 'Fira Code', monospace;
            font-size: 9pt;
            letter-spacing: 1px;
            color: var(--luminous-teal);
            border-left: 2px solid var(--luminous-teal);
            padding-left: 10px;
        }

        .main-title h1 {
            font-size: 42pt;
            font-weight: 900;
            line-height: 0.9;
            color: var(--stark-white);
            margin-bottom: 15px;
        }

        .main-title h2 {
            font-size: 14pt;
            font-weight: 700;
            color: var(--luminous-teal);
            letter-spacing: 0.5px;
        }

        .doc-meta {
            font-family: 'Fira Code', monospace;
            font-size: 8pt;
            color: rgba(255,255,255,0.5);
            text-align: right;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 10px;
            margin-top: 20px;
        }

        .manifesto-section {
            height: 55%;
            background: var(--stark-white);
            padding: 50px;
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 40px;
            clip-path: polygon(0 5% , 100% 0, 100% 100%, 0% 100%);
        }

        .manifesto-text h3 {
            color: var(--midnight-navy);
            font-size: 15pt;
            margin-bottom: 20px;
            border-bottom: 4px solid var(--midnight-navy);
            display: inline-block;
            padding-bottom: 5px;
        }

        .manifesto-text p {
            font-size: 10.5pt;
        }

        .quote-block {
            background: var(--midnight-navy);
            padding: 30px;
            display: flex;
            align-items: center;
            position: relative;
        }

        .quote-block::before {
            content: "";
            position: absolute;
            top: -10px;
            left: -10px;
            width: 40px;
            height: 40px;
            border-top: 4px solid var(--luminous-teal);
            border-left: 4px solid var(--luminous-teal);
        }

        .quote-text {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 15pt;
            color: var(--luminous-teal);
            line-height: 1.3;
        }

        /* PAGE 2: CODE AS LAW & ARCHITECTURE */
        .page-2 {
            padding: 40px;
        }

        .section-header {
            border-left: 8px solid var(--midnight-navy);
            padding-left: 15px;
            margin-bottom: 30px;
        }

        .section-header h2 {
            font-size: 24pt;
            color: var(--midnight-navy);
        }

        .text-module-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .text-module {
            background: #f9f9f9;
            border-top: 3px solid var(--midnight-navy);
            padding: 20px;
            font-size: 10pt;
        }

        .text-module h4 {
            color: var(--midnight-navy);
            margin-bottom: 15px;
            font-size: 12pt;
        }

        .arch-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 20px;
            flex-grow: 1;
        }

        .arch-card {
            border: 1px solid #ddd;
            padding: 20px;
            background: var(--stark-white);
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }

        .arch-card h4 {
            font-size: 12.5pt;
            color: var(--midnight-navy);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.2;
        }

        .arch-num {
            background: var(--midnight-navy);
            color: var(--stark-white);
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 10pt;
            font-weight: bold;
            flex-shrink: 0;
        }

        .arch-card p {
            font-size: 9.5pt;
            line-height: 1.5;
            margin: 0;
            color: #333;
        }

        /* PAGE 3: TIMELINE & CODE BOX */
        .page-3 {
            padding: 50px;
        }

        .timeline-container {
            position: relative;
            margin-top: 30px;
            height: 100%;
        }

        .timeline-line {
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--midnight-navy);
        }

        .timeline-item {
            margin-left: 60px;
            margin-bottom: 40px;
            position: relative;
        }

        .timeline-dot {
            position: absolute;
            left: -48px;
            top: 0;
            width: 20px;
            height: 20px;
            background: var(--midnight-navy);
            border: 3px solid var(--luminous-teal);
            border-radius: 50%;
            z-index: 2;
        }

        .timeline-phase {
            font-family: 'Fira Code', monospace;
            color: var(--luminous-teal);
            background: var(--midnight-navy);
            display: inline-block;
            padding: 2px 8px;
            font-size: 9pt;
            margin-bottom: 5px;
        }

        .timeline-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 14pt;
            color: var(--midnight-navy);
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .timeline-item p {
            font-size: 9.5pt;
            margin-bottom: 0;
        }

        /* Embedded Terminal inside Timeline */
        .terminal-window {
            background: var(--midnight-navy);
            border-radius: 4px;
            padding: 15px;
            font-family: 'Fira Code', monospace;
            font-size: 8pt;
            color: #ccc;
            position: relative;
            box-shadow: 8px 8px 0px rgba(0,0,0,0.1);
            margin-top: 15px;
        }

        .terminal-header {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
            border-bottom: 1px solid #333;
            padding-bottom: 8px;
        }

        .dot { width: 8px; height: 8px; border-radius: 50%; background: #333; }
        .dot.red { background: var(--terminal-red); }
        .dot.yellow { background: var(--executive-gold); }
        .dot.green { background: var(--luminous-teal); }

        .code-line { margin-bottom: 4px; }
        .keyword { color: var(--luminous-teal); }
        .string { color: var(--executive-gold); }
        .error { color: var(--terminal-red); }

        /* PAGE 4: BACK COVER */
        .page-4 {
            background: var(--midnight-navy);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            position: relative;
        }

        .geo-circle {
            position: absolute;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .c1 { width: 400px; height: 400px; top: -100px; right: -100px; }
        .c2 { width: 600px; height: 600px; top: -200px; right: -200px; border-style: dashed; }

        .ultimatum-content {
            z-index: 10;
        }

        .ultimatum-head {
            font-size: 36pt;
            color: var(--stark-white);
            margin-bottom: 20px;
            line-height: 1.1;
        }

        .cursor {
            display: inline-block;
            width: 15px;
            height: 36pt;
            background: var(--luminous-teal);
            animation: blink 1s infinite;
            vertical-align: bottom;
            margin-left: 5px;
        }

        @keyframes blink { 0% { opacity: 0; } 50% { opacity: 1; } 100% { opacity: 0; } }

        .cta-button {
            display: inline-block;
            margin-top: 40px;
            padding: 20px 40px;
            background: var(--luminous-teal);
            color: var(--midnight-navy);
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid var(--luminous-teal);
        }

        .footer-block {
            position: absolute;
            bottom: 60px;
            left: 60px;
            right: 60px;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            color: var(--stark-white);
        }

        .footer-text {
            font-size: 9pt;
            opacity: 0.8;
        }

        /* Print Specifics */
        @media print {
            body {
                background: none;
                margin: 0;
                padding: 0;
                display: block;
            }
            .page {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }
        }

    .stack-footer { 
        margin-top: auto; 
        padding: 8px 12px; 
        background: #f0f4f8; 
        border-left: 6px solid var(--luminous-teal); 
    }
    .stack-line { 
        font-family: 'Fira Code', monospace; 
        font-size: 5.5pt; 
        margin: 0; 
        line-height: 1.5; 
        color: #444; 
    }
    .stack-line b { 
        color: var(--midnight-navy); 
        text-transform: uppercase; 
        letter-spacing: 0.3px;
    }

    /* --- TIER-1 COMMAND DOCK --- */
        .tier1-dock {
            position: fixed;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: rgba(10, 17, 40, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 229, 255, 0.15);
            padding: 15px 12px;
            border-radius: 40px;
            z-index: 9999;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5), inset 0 0 20px rgba(0, 229, 255, 0.05);
        }

        .dock-btn {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.5);
            cursor: pointer;
            padding: 12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            text-decoration: none;
        }

        .dock-btn svg { width: 22px; height: 22px; stroke-width: 1.5; transition: stroke-width 0.2s; }
        
        .dock-btn:hover {
            color: var(--luminous-teal);
            background: rgba(0, 229, 255, 0.1);
            box-shadow: 0 0 15px rgba(0, 229, 255, 0.2);
        }

        .dock-btn:hover svg { stroke-width: 2; }

        /* Cyber Tooltips */
        .dock-btn::after {
            content: attr(data-tooltip);
            position: absolute;
            right: 130%;
            background: var(--midnight-navy);
            color: var(--luminous-teal);
            padding: 8px 12px;
            font-family: 'Fira Code', monospace;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 1px;
            border: 1px solid rgba(0, 229, 255, 0.3);
            border-radius: 3px;
            opacity: 0;
            pointer-events: none;
            white-space: nowrap;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            transform: translateX(10px);
            text-transform: uppercase;
        }

        .dock-btn:hover::after {
            opacity: 1;
            transform: translateX(0);
        }

        .dock-divider {
            width: 100%; height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 5px 0;
        }

        @media print { .tier1-dock { display: none !important; } }

    /* Executive Summary View Container */
    #executive-summary-view {
        display: none; 
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: var(--midnight-navy);
        z-index: 9000;
        justify-content: center;
        align-items: center;
    }

    /* The Animated Card Deck */
    .deck-container {
        position: relative;
        width: 360px;
        height: 520px;
        perspective: 1000px;
    }

    .exec-card {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: var(--stark-white);
        padding: 40px 30px;
        border-radius: 8px;
        border-top: 6px solid var(--luminous-teal);
        box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        display: flex;
        flex-direction: column;
        
        opacity: 0;
        transform: translateX(50px) scale(0.95);
        transition: all 0.6s cubic-bezier(0.25, 1, 0.5, 1);
        pointer-events: none;
    }

    .exec-card.active {
        opacity: 1;
        transform: translateX(0) scale(1);
        pointer-events: auto;
        z-index: 10;
    }

    .exec-card.exit {
        opacity: 0;
        transform: translateX(-50px) scale(0.95);
        z-index: 5;
    }

    .card-meta { font-family: 'Fira Code', monospace; color: var(--luminous-teal); background: var(--midnight-navy); padding: 4px 10px; font-size: 8pt; display: inline-block; margin-bottom: 15px; align-self: flex-start; }
    .exec-card h3 { color: var(--midnight-navy); font-size: 18pt; margin-bottom: 20px; line-height: 1.1; }
    .exec-card p { font-size: 10.5pt; color: #333; line-height: 1.6; margin-bottom: 15px; }
    .card-btn { margin-top: auto; padding: 15px; background: var(--luminous-teal); border: 2px solid var(--luminous-teal); color: var(--midnight-navy); font-family: 'Montserrat', sans-serif; font-weight: 800; cursor: pointer; transition: all 0.2s; width: 100%; text-align: center; }
    .card-btn:hover { background: transparent; }

    @media print {
        #executive-summary-view, .toggle-btn { display: none !important; }
        #full-document-view { display: block !important; }
    }
    /* --- MOBILE UX: HORIZONTAL TOP DOCK --- */
        @media (max-width: 850px) {
            .tier1-dock {
                top: 20px;
                right: 50%;
                transform: translateX(50%);
                flex-direction: row;
                padding: 10px 20px;
                width: max-content;
            }

            .dock-divider {
                width: 1px;
                height: 24px;
                margin: 0 5px;
                align-self: center;
            }

            /* Shift the tooltips to drop downwards instead of sideways on mobile */
            .dock-btn::after {
                right: auto;
                top: 140%;
                left: 50%;
                transform: translate(-50%, -10px);
            }

            .dock-btn:hover::after {
                transform: translate(-50%, 0);
            }
        }
        /* --- LOADER ANIMATION --- */
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* --- MOBILE UX: UNRESTRICTED SCROLLING & DOCK FIXES --- */
        @media (max-width: 850px) {
            /* 1. Horizontal Top Dock (Stays fixed at the top) */
            .tier1-dock {
                top: 20px;
                right: 50%;
                transform: translateX(50%);
                flex-direction: row;
                padding: 10px 20px;
                width: max-content;
            }

            .dock-divider { width: 1px; height: 24px; margin: 0 5px; align-self: center; }
            .dock-btn::after { right: auto; top: 140%; left: 50%; transform: translate(-50%, -10px); }
            .dock-btn:hover::after { transform: translate(-50%, 0); }
            
            /* 2. Push Executive Cards down to clear the dock */
            .deck-container { margin-top: 100px; }

            /* 3. FULL VISION SCROLLING: Release all constraints */
            html, body {
                /* Unlock native vertical & horizontal scrolling */
                overflow: auto !important; 
                
                /* CRITICAL FIX: Force left alignment. If it stays centered, the 
                   left side of the A4 page pushes into unscrollable negative space. */
                align-items: flex-start !important; 
            }

            #full-document-view {
                /* Let the container expand to the exact physical size of the A4 pages */
                width: max-content;
                padding: 100px 20px 20px 20px; /* Top padding to clear the fixed dock */
                display: flex;
                flex-direction: column;
            }

            .page {
                /* Strip away all transform/scale math. 
                   Render exactly at 210mm x 297mm for perfect clarity. */
                transform: none !important;
                margin-bottom: 30px !important;
            }
        }
    </style>
</head>
<body>

<!-- TIER-1 COMMAND DOCK -->
    <div class="tier1-dock">
        <!-- Home -->
        <a href="/" class="dock-btn" data-tooltip="Command HQ (Home)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        </a>
        
        <!-- Schedule -->
        <a href="/schedule" class="dock-btn" data-tooltip="Schedule Consultation">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        </a>

        <!-- Diagnostic (Contact) -->
        <a href="/contact" class="dock-btn" data-tooltip="Initiate Diagnostic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
        </a>

        <div class="dock-divider"></div>

        <!-- Toggle Executive Summary (Using original ID so JS still works) -->
        <button id="toggleViewBtn" class="dock-btn" data-tooltip="Executive Summary Mode">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        </button>

        <!-- Download PDF (Using original ID so JS still works) -->
        <button id="downloadPdfBtn" class="dock-btn" data-tooltip="Secure PDF Download">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        </button>
    </div>

<div id="executive-summary-view" class="grid-bg">
    <div class="deck-container">
        
        <div class="exec-card active" data-index="0">
            <div class="card-meta">PHILOSOPHY</div>
            <h3>FROM REACTIVE TO PRE-COGNITIVE</h3>
            <p>We do not digitize confusion. For decades, organizations relied on post-mortem management—analyzing failure after the fact.</p>
            <p>The <strong>Predictive Praxis Framework™</strong> shifts your governance to deterministic forecasting. We predict and neutralize bottlenecks before they occur.</p>
            <button class="card-btn next-card-btn">CLICK ME ➔</button>
        </div>

        <div class="exec-card" data-index="1">
            <div class="card-meta">PHASE I & II</div>
            <h3>DIAGNOSTICS & THE RELATIONAL VAULT</h3>
            <p><strong>The Foundation of Truth.</strong> We forensically map your workflows (Lean Six Sigma) to calculate the exact cost of your operational chaos (COPQ).</p>
            <p>We then dismantle your fragmented Excel sheets, locking all sanitized data into a single, high-velocity PostgreSQL vault.</p>
            <button class="card-btn next-card-btn">NEXT: PREDICTION ➔</button>
        </div>

        <div class="exec-card" data-index="2">
            <div class="card-meta">PHASE III</div>
            <h3>ALGORITHMIC FORESIGHT</h3>
            <p><strong>Calculating the Future.</strong> Our custom AI algorithms continuously analyze your operational velocity and burn rates.</p>
            <p>The system learns your vulnerabilities. If the data indicates a critical compliance or supply failure is mathematically probable in Week 6, we flag it in Week 2.</p>
            <button class="card-btn next-card-btn">NEXT: GOVERNANCE ➔</button>
        </div>

        <div class="exec-card" data-index="3">
            <div class="card-meta">PHASE IV</div>
            <h3>CODE AS LAW</h3>
            <p><strong>Digital Poka-Yoke.</strong> Human willpower is finite; engineered systems are infinite. We translate corporate policies into hardcoded logic gates.</p>
            <p>If an AI-Agent detects a forged document or missing compliance, the system physically disables the "Approve" button. Deviations become mathematically impossible.</p>
            <button class="card-btn next-card-btn">NEXT: DEPLOYMENT ➔</button>
        </div>

        <div class="exec-card" data-index="4">
            <div class="card-meta">PHASE V</div>
            <h3>SOVEREIGN HANDOVER</h3>
            <p><strong>The Transfer of Power.</strong> We build fortresses, but we hand over the keys. You receive rigorous executive training to command the architecture with total fluency.</p>
            <p>Through the Kaizen Protocol, our Tier-1 engineering team continuously optimizes the code to scale seamlessly with your enterprise.</p>
            <button class="card-btn switch-to-full-btn" style="background: var(--midnight-navy); color: var(--stark-white); border-color: var(--midnight-navy);">VIEW FULL DOCUMENT</button>
        </div>

    </div>
</div>

<div id="full-document-view">
    <div class="page page-1 grid-bg">
    <div class="cover-header">
        <img src="{{ asset('assets/img/logo_monochrome.svg') }}" class="top-right-logo" alt="JBS Logo">
        
        <div class="brand-mark">JBS PRAXIS INTELLIGENCE DESK</div>
            <div class="main-title">
                <h2>THE PREDICTIVE PRAXIS FRAMEWORK™</h2>
                <h1>ENGINEERING<br>DETERMINISTIC<br>RESILIENCE</h1>
                <div style="width: 100px; height: 4px; background: var(--luminous-teal); margin-top: 20px;"></div>
            </div>
            <div class="doc-meta">
                CONFIDENTIAL DEPLOYMENT BRIEFING<br>
                VERSION 2.0 | 2026<br>
                TARGET: AFRICAN ENTERPRISE SECTOR
            </div>
        </div>

        <div class="manifesto-section">
            <div class="manifesto-text">
                <h3>THE CORE PHILOSOPHY:<br>FROM REACTIVE TO PRE-COGNITIVE</h3>
                <p>For decades, the sustainability of organizations has mostly relied on "post-mortem" management. When a supply chain breaks, a compliance violation occurs, or capital leaks, or performance is questioned, consultants are hired to analyze historical data and explain why the system failed. Ending at documented strategy.</p>
                <p>The Predictive Praxis Framework™ (PPF) shifts organizational governance from reactive firefighting to deterministic forecasting. We do not simply digitize your current confusion or blurry vision.</p>
                <p>We conduct forensic operational audits, structure your fragmented data, and engineer algorithmic systems that predict and neutralize bottlenecks, compliance risks, and capital leakage <strong>before they occur</strong> while informing you real-time.</p>
            </div>
            <div class="quote-block">
                <div class="quote-text">
                    "ANALYZING FAILURE AFTER THE FACT IS NOT A STRATEGY; IT IS AN AUTOPSY."
                </div>
            </div>
        </div>
    </div>

    <div class="page page-2">
    <img src="https://i.ibb.co/kZJNFD4/JBS-LOGO-FULL-COLOUR.png" class="top-right-logo" alt="JBS Logo">
        <div class="section-header">
            <div style="font-family: 'Fira Code', monospace; color: var(--luminous-teal); font-size: 0.8rem; margin-bottom: 5px;">PHASE I & II</div>
            <h2>DIAGNOSTICS & DATA</h2>
        </div>

        <div class="text-module-container">
            <div class="text-module">
                <h4>PHASE I: The Foundation of Truth</h4>
                <p style="margin: 0; font-size: 9.5pt;">Before a single line of code is written, our strike team of management consultants embeds directly within your day-to-day operations. We do not rely on guesswork; we apply mathematically proven methodologies and forensic diagnostics to extract the raw, unfiltered operational reality of your enterprise, ensuring your digital architecture is built upon a foundation of absolute empirical truth.</p>
            </div>
            <div class="text-module">
                <h4>PHASE II: The Relational Data Vault</h4>
                <p style="margin: 0; font-size: 9.5pt;"><strong>The Eradication of Silos.</strong> Predictive logic requires absolute structural integrity. We dismantle fragmented Excel sheets, legacy software, and isolated departmental data. All organizational data is sanitized, structured, and securely locked inside a centralized, high-velocity relational database (PostgreSQL). This vault becomes your enterprise’s Single Source of Truth, ensuring that every department operates on the exact same real-time mathematics.</p>
            </div>
        </div>

        <div class="section-header" style="margin-top: 10px; margin-bottom: 20px;">
            <div style="font-family: 'Fira Code', monospace; color: var(--luminous-teal); font-size: 0.8rem; margin-bottom: 5px;">PHASE I EXPANSION</div>
            <h2 style="font-size: 18pt;">THE 4-TIER OPERATIONAL MAPPING</h2>
        </div>

        <div class="arch-grid">
            <div class="arch-card">
                <h4><span class="arch-num">1</span> LEAN SIX SIGMA MAPPING</h4>
                <p>We forensically map your workflows to identify the "grey areas"—the undocumented human friction points and systemic variances where time and resources are wasted. This granular diagnostic exposes hidden operational leaks that traditional audits overlook, transforming anecdotal inefficiencies into empirical data points to stabilize your enterprise's core operational heartbeat.</p>
            </div>
            <div class="arch-card">
                <h4><span class="arch-num">2</span> THE PAF MATRIX (COPQ)</h4>
                <p>We quantify your operational chaos. By categorizing your capital leakage into Prevention, Appraisal, and Failure costs, we calculate the exact dollar value of your COPQ. This provides the definitive financial baseline for our predictive architecture, converting invisible systemic waste into a high-impact roadmap for capital recovery.</p>
            </div>
            <div class="arch-card">
                <h4><span class="arch-num">3</span> ISO & REGULATORY GAP ANALYSIS</h4>
                <p>A policy written on paper is merely a suggestion until it is enforced. We evaluate your enterprise against global statutory frameworks (e.g., ISO 9001, BCMS-BIA). We expose the exact delta between your theoretical compliance manuals (if they exist) and your actual human execution, identifying where regulatory fines or audit failures are imminent.</p>
            </div>
            <div class="arch-card">
                <h4><span class="arch-num">4</span> PROCESS SAFETY & RISK PROFILING</h4>
                <p>Financial leakage is only half the threat; operational paralysis is the other. We construct a comprehensive risk matrix to isolate vulnerabilities in your supply chain, physical operations, and internal controls. This ensures the digital architecture we build is engineered to withstand severe market shocks and eliminate catastrophic human error.</p>
            </div>
        </div>
    </div>

    <div class="page page-3">
    <img src="https://i.ibb.co/kZJNFD4/JBS-LOGO-FULL-COLOUR.png" class="top-right-logo" alt="JBS Logo">
        <div class="section-header">
            <div style="font-family: 'Fira Code', monospace; color: var(--luminous-teal); font-size: 0.8rem; margin-bottom: 5px;">PHASE III TO V</div>
            <h2>THE ENGINEERING SEQUENCE</h2>
        </div>

        <div class="timeline-container">
            <div class="timeline-line"></div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <span class="timeline-phase">PHASE III</span>
                <div class="timeline-title">ALGORITHMIC FORESIGHT & THE PREDICTIVE ENGINE</div>
                <p><strong>Calculating the Future.</strong> With clean data secured, we deploy custom backend algorithms that continuously analyze your operational velocity, financial burn rates, and compliance matrices. The system learns your vulnerabilities. If the data indicates a probability of a procurement delay in Week 6, or a localized safety compliance failure in a regional branch, the Predictive Engine flags the anomaly in Week 2. Executives no longer look at dashboards showing what happened yesterday; they look at projections of what will break tomorrow.</p>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <span class="timeline-phase">PHASE IV</span>
                <div class="timeline-title">AUTONOMOUS GOVERNANCE (CODE AS LAW)</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
                    <p style="margin-top: 0;"><strong>Hardcoded Execution & Digital Poka-Yoke.</strong> We translate corporate policies and regulatory requirements into digital logic gates, deploying advanced Poka-Yoke (mistake-proofing) mechanisms within the software ecosystem. If a vendor lacks mandatory compliance documentation, the system disables the <strong>"Approve"</strong> button, making non-compliant actions impossible. To prevent systemic rigidity during crises, we engineer a strictly audited <strong>"Emergency Override Protocol"</strong> (Glass-Break). This allows authorized executives to bypass gates using traceable quotas, with every instance etched into an immutable forensic ledger and displayed on stakeholder dashboards to ensure transparency and good governance.</p>
                    
                    <div class="terminal-window" style="margin-top: 0;">
                        <div class="terminal-header">
                            <div class="dot red"></div><div class="dot yellow"></div><div class="dot green"></div>
                        </div>
                        <div class="code-line"><span class="keyword">function</span> approveVendor(vendorDoc) {</div>
                        <div class="code-line" style="padding-left: 15px; color: #888;">// Protocol 04: AI-Enhanced Poka-Yoke</div>
                        <div class="code-line" style="padding-left: 15px;"><span class="keyword">let</span> aiScan = PraxisAgent.verify(vendorDoc);</div>
                        <div class="code-line" style="padding-left: 15px;"><span class="keyword">if</span> (aiScan.isFake) {</div>
                        <div class="code-line" style="padding-left: 30px;"><span class="error">throw new Error</span>(<span class="string">"VIOLATION: FORGED_ISO_14001"</span>);</div>
                        <div class="code-line" style="padding-left: 30px; color: #888;">/* AI REJECT: Metadata anomaly in signature.</div>
                        <div class="code-line" style="padding-left: 33px; color: #888;">Doc marked ABSENT. Cannot proceed. */</div>
                        <div class="code-line" style="padding-left: 30px;"><span class="keyword">return</span> <span class="keyword">false</span>;</div>
                        <div class="code-line" style="padding-left: 15px;">}</div>
                        <div class="code-line" style="padding-left: 15px;"><span class="keyword">return</span> <span class="keyword">true</span>;</div>
                        <div class="code-line">}</div>
                    </div>
                </div>
            </div>

            <div class="timeline-item" style="margin-bottom: 20px;">
                <div class="timeline-dot" style="background: var(--luminous-teal);"></div>
                <span class="timeline-phase">PHASE V</span>
                <div class="timeline-title">SOVEREIGN HANDOVER & KAIZEN</div>
                <p><strong>The Transfer of Power.</strong> We build fortresses, but we hand over the keys. Deployment culminates in rigorous executive and operational training, ensuring your personnel command the architecture with total fluency. Post-launch, we initiate the Kaizen Protocol—a continuous cycle of code optimization, database indexing, and algorithm recalibration. As your enterprise scales and market dynamics shift, our engineering team dynamically refines the infrastructure, guaranteeing zero-latency performance and unbreakable security for the lifetime of the system.</p>
            </div>
        </div>

        <div class="stack-footer">
            <h4 style="color: var(--midnight-navy); margin-bottom: 8px; font-size: 9pt; letter-spacing: 1px;">TIER-1 PREDICTIVE ARCHITECTURE</h4>
            <div class="stack-line">
                <b>Audit:</b> PAF / COPQ, Six Sigma, Poka-Yoke, Kaizen, ISO 9001, ISO 27001, Regulatory frameworks
            </div>
            <div class="stack-line">
                <b>Core:</b> PostgreSQL (Relational Truth), Apache Kafka (Event Streaming), AWS EKS (Kubernetes)
            </div>
            <div class="stack-line">
                <b>Logic:</b> Golang (Zero-Latency Microservices), Python / TensorFlow (Predictive Engine)
            </div>
            <div class="stack-line">
                <b>Interface:</b> Next.js / React, Tailwind CSS
            </div>
        </div>
    </div>

    <div class="page page-4 grid-bg">
    <img src="https://i.ibb.co/9mX9TgfJ/JBS-LOGO-ON-GREY.png" class="top-right-logo" alt="JBS Logo">
        <div class="geo-circle c1"></div>
        <div class="geo-circle c2"></div>

        <div class="ultimatum-content">
            <div style="font-family: 'Fira Code', monospace; color: var(--terminal-red); margin-bottom: 20px;">SYSTEM STATUS: REACTIVE</div>
            <div class="ultimatum-head">
                YOUR<br>PREDICTIVE ENGINE<br>IS WAITING<div class="cursor"></div>
            </div>
            <p style="color: var(--stark-white); font-size: 12pt; max-width: 500px;">
                Reading the methodology may bring you to understand the vulnerability your current operations are exposed to. The remaining variable is execution.
                <br><br>
                Your enterprise does not need to run on reactive, post-mortem systems for another fiscal year.
            </p>

            <a href="https://www.jbspraxis.com/contact" class="cta-button">
                INITIATE PREDICTIVE DIAGNOSTIC
            </a>
        </div>

        <div class="footer-block">
            <div class="footer-text">
                <strong>JBS PRAXIS: BEYOND STRATEGY</strong><br>
                ECOWAS Desk: Lagos, Nigeria | CEMAC Desk: Douala, Cameroon
            </div>
            <div class="footer-text" style="text-align: right;">
                intelligence@jbspraxis.com<br>
                www.jbspraxis.com
            </div>
        </div>
    </div>
</div>  

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>

<script>
    document.getElementById('downloadPdfBtn').addEventListener('click', async function () {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4'); 
        const pages = document.querySelectorAll('.page');
        const btn = this;

        // 1. Cache the original SVG and Tooltip
        const originalHTML = btn.innerHTML;
        const originalTooltip = btn.getAttribute('data-tooltip');

        // 2. Swap to a spinning loader icon and update tooltip
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="4.93" x2="19.07" y2="7.76"></line></svg>`;
        btn.setAttribute('data-tooltip', 'GENERATING...');
        btn.style.pointerEvents = "none";
        
        // Short delay to allow UI to update
        await new Promise(r => setTimeout(r, 100));

        // 3. Loop through each page, capture as high-res image, and add to PDF
        for (let i = 0; i < pages.length; i++) {
            const pageEl = pages[i];
            
            // Safeguard: Temporarily disable mobile zoom to ensure high-res capture
            const originalZoom = pageEl.style.zoom;
            pageEl.style.zoom = 1;

            const canvas = await html2canvas(pageEl, { scale: 2, useCORS: true });
            const imgData = canvas.toDataURL('image/jpeg', 1.0);
            
            // Restore mobile zoom
            pageEl.style.zoom = originalZoom;
            
            const imgWidth = 210; 
            const imgHeight = 297; 

            if (i > 0) {
                pdf.addPage();
            }
            pdf.addImage(imgData, 'JPEG', 0, 0, imgWidth, imgHeight);
        }

        // 4. Force the download
        pdf.save('JBS_Praxis_Predictive_Framework.pdf');
        
        // 5. Restore the original SVG icon and Tooltip
        btn.innerHTML = originalHTML;
        btn.setAttribute('data-tooltip', originalTooltip);
        btn.style.pointerEvents = "auto";
    });
</script>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const toggleBtn = document.getElementById("toggleViewBtn");
        const fullDoc = document.getElementById("full-document-view");
        const execView = document.getElementById("executive-summary-view");
        
        const nextBtns = document.querySelectorAll(".next-card-btn");
        const switchToFullBtns = document.querySelectorAll(".switch-to-full-btn");
        const cards = document.querySelectorAll(".exec-card");
        
        let isExecMode = false;
        let currentCardIndex = 0;
        
        // --- NEW: URL PARAMETER DETECTOR ---
        // If the URL contains ?exec=true (from the QR code), auto-launch the Executive Summary
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('exec')) {
            setTimeout(() => {
                toggleView();
            }, 300); // 300ms delay ensures the DOM is fully ready before firing the animation
        }
        // -----------------------------------
        
        // View Toggler
        function toggleView() {
                isExecMode = !isExecMode;
                
                // Define our two Tier-1 SVG Icons
                const iconDocument = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>`;
                const iconLayers = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>`;

                if (isExecMode) {
                    fullDoc.style.display = "none";
                    execView.style.display = "flex";
                    
                    // Swap to Document Icon and update tooltip
                    if(toggleBtn) {
                        toggleBtn.innerHTML = iconDocument;
                        toggleBtn.setAttribute('data-tooltip', 'View Full Document');
                    }
                    
                    currentCardIndex = 0;
                    updateCards();
                } else {
                    fullDoc.style.display = "block";
                    execView.style.display = "none";
                    
                    // Swap back to Layers Icon and update tooltip
                    if(toggleBtn) {
                        toggleBtn.innerHTML = iconLayers;
                        toggleBtn.setAttribute('data-tooltip', 'Executive Summary Mode');
                    }
                }
            }

        toggleBtn.addEventListener("click", toggleView);
        switchToFullBtns.forEach(btn => btn.addEventListener("click", toggleView));

        // Card Animation Logic
        function updateCards() {
            cards.forEach((card, index) => {
                card.classList.remove("active", "exit");
                if (index === currentCardIndex) {
                    card.classList.add("active");
                } else if (index < currentCardIndex) {
                    card.classList.add("exit");
                }
            });
        }

        // Next Card Event
        nextBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                if (currentCardIndex < cards.length - 1) {
                    currentCardIndex++;
                    updateCards();
                }
            });
        });
    });
</script>

</body>
</html>