@php
    // --- 1. CORE DATA EXTRACTION ---
    $companyInput = $assessment->company_name ?? 'ENTERPRISE CLIENT';
    $nameInput = $assessment->executive_name ?? 'AUTHORIZED STAKEHOLDER';
    $industryInput = $assessment->copq_industry ?? 'General Enterprise (Other)';
    $toolInput = strtolower($assessment->primary_tool ?? 'excel');
    $currency = $assessment->copq_currency ?? 'USD';

    $turnoverInput = floatval($assessment->copq_turnover ?? 0);
    $headcountInput = intval($assessment->headcount ?? 1);
    $mgmtNodesInput = intval($assessment->mgmt_nodes ?? max(1, floor($headcountInput * 0.20))); // Fetch actual nodes
    $firefightingInput = floatval($assessment->copq_latency ?? 0);
    $hourlyCost = floatval($assessment->copq_hourly_cost ?? 50);
    $calculatedErrorRate = floatval($assessment->copq_error_rate ?? 3.0);
    $totalCOPQ = floatval($assessment->calculated_leakage ?? 0);
    $aiAnalysis = $assessment->ai_friction_analysis ?? 'Systemic friction detected within current architectural parameters.';
    
    $diagId = $assessment->report_token ?? 'PRX-UNKNOWN';
    $dateStr = date('F j, Y', strtotime($assessment->created_at ?? now()));

    // --- 2. LIVE DATABASE MAPPING (INDUSTRIES, ARCHITECTURES, EXCHANGE RATES) ---
    $industryData = \Illuminate\Support\Facades\DB::table('mdm_copq_industries')->where('industry_name', $industryInput)->first();
    if (!$industryData) {
        $industryData = \Illuminate\Support\Facades\DB::table('mdm_copq_industries')->where('industry_name', 'General Enterprise')->first();
    }

    $toolData = \Illuminate\Support\Facades\DB::table('mdm_copq_architectures')->where('tech_key', $toolInput)->first();
    if (!$toolData) {
        $toolData = \Illuminate\Support\Facades\DB::table('mdm_copq_architectures')->where('tech_key', 'excel')->first();
    }

    // Dynamic Exchange Rate Fetching
    $exchangeData = \Illuminate\Support\Facades\DB::table('mdm_exchange_rates')->where('currency_code', $currency)->first();
    $conversionRate = $exchangeData ? floatval($exchangeData->rate_to_usd) : 1.0;

    // --- 3. DYNAMIC MATHEMATICS & TAXONOMY (THE ARCHETYPE ENGINE) ---
    $archetype = $industryData->sector_archetype ?? 'COMMERCIAL';
    $oppMultiplier = floatval($industryData->opportunity_multiplier_value ?? 2.0);
    $extFailureLabel = $industryData->external_failure_label ?? 'External Failure (Client Churn)';
    $archMandate = $industryData->architectural_mandate ?? 'CLOUD-NATIVE MICROSERVICES';

    // Establish dynamic subtitles for the PAF breakdown
    $appraisalTitle = 'Appraisal Costs (Time Tax)';
    $appraisalDesc = 'Cost of manual auditing, waiting for signatures, and data retrieval.';
    $internalTitle = 'Internal Failure (Process Tax)';
    $internalDesc = 'Internal rework, procurement friction, and inventory shrinkage.';
    $externalDesc = 'Lost lifetime value from client churn due to operational delays.';

    if ($archetype === 'SOVEREIGN') {
        $appraisalTitle = 'Appraisal Costs (Audit Latency)';
        $appraisalDesc = 'Cost of manual compliance auditing, reconciliation delays, and physical authorizations.';
        $internalTitle = 'Internal Failure (Process Tax)';
        $internalDesc = 'Capital trapped in internal rework and fragmented inter-departmental transfers.';
        $externalDesc = 'Calculated cost of delayed public infrastructure deployment and macroeconomic drag.';
    } elseif ($archetype === 'IMPACT') {
        $appraisalTitle = 'Appraisal Costs (Reporting Overhead)';
        $appraisalDesc = 'Administrative cost of manual grant reconciliation and preparing for donor audits.';
        $internalTitle = 'Internal Failure (Reconciliation Tax)';
        $internalDesc = 'Internal rework, supply chain spoilage in field operations, and delayed payments.';
        $externalDesc = 'The quantified value of delayed health interventions and potential donor clawbacks.';
    } elseif ($archetype === 'ASSET_HEAVY') {
        $appraisalTitle = 'Appraisal Costs (Compliance Overhead)';
        $appraisalDesc = 'Cost of manual HSE auditing, contractor verification, and safety protocol reconciliation.';
        $internalTitle = 'Internal Failure (Supply Chain Spoilage)';
        $internalDesc = 'Logistical delays, procurement bottlenecks for critical rig/asset components.';
        $externalDesc = 'Financial impact of unplanned asset downtime (NPT) and delayed operational schedules.';
    }

    $mgmtHeadcount = $mgmtNodesInput;
    $appraisalCost = $mgmtHeadcount * $firefightingInput * $hourlyCost * 52;
    $internalFailureCost = $turnoverInput * ($calculatedErrorRate / 100) * 0.60;
    
    $externalFailureCost = max(0, $totalCOPQ - ($appraisalCost + $internalFailureCost));
    
    if ($externalFailureCost == 0 && $turnoverInput > 0) {
        // Fallback if COPQ wasn't calculated properly in the previous step
        $externalFailureCost = $turnoverInput * ($calculatedErrorRate / 100) * ($archetype === 'COMMERCIAL' ? 0.80 : 0.90);
        $totalCOPQ = $appraisalCost + $internalFailureCost + $externalFailureCost;
    }
    
    // The dynamically assigned Opportunity Cost
    $opportunityCost = $appraisalCost * $oppMultiplier;

    // --- INTANGIBLE COST: TALENT CHURN ---
    // Derives directly from user-input hourly cost (No hardcoded salaries)
    $annualSalary = $hourlyCost * 40 * 52; 
    $burnoutTurnover = max(1, floor($mgmtHeadcount * 0.05));
    $replacementCost = $burnoutTurnover * ($annualSalary * 0.40);
    
    // --- BREAK-EVEN & ROI MATH (VALUE-BASED PRICING) ---
    $monthlyLeakage = $totalCOPQ / 12;
    
    // Core Praxis pricing strategy: Anchor deployment cost at 30% of the annual leakage
    $baseValuePricing = $totalCOPQ * 0.30;
    
    // Complexity Modifier: Adds a small scaling cost based on enterprise size and global FX rate 
    // This guarantees the Payback Period "dances" organically between 3.6 and 4.5 months.
    $complexityModifier = ($headcountInput * 45) * $conversionRate; 
    
    $finalDeployCost = $baseValuePricing + $complexityModifier;
    
    $breakEvenMonths = $monthlyLeakage > 0 ? number_format($finalDeployCost / $monthlyLeakage, 1) : 0;

// --- 4. RISK PROBABILITY ---
    // Calculate the severity ratio (How much worse are they than the baseline?)
    $severityRatio = $calculatedErrorRate / max(0.1, $industryData->error_floor); // max() prevents division by zero
    
    // Scale the risk naturally: A perfect score (ratio of 1) yields ~18% background risk.
    // A terrible score (ratio of 4+) pushes toward the cap.
    // We use a logarithmic/scaling approach rather than a flat +30.
    $baseRisk = ($severityRatio * 18); 
    
    // Cap probability at 82% for realism, but drop the floor to 12% for truly optimized firms.
    $riskProbability = min(82, max(12, floor($baseRisk + ($headcountInput / 500)))); 
    
    $riskColor = 'erp-success'; $riskHex = '#00FF66'; $riskLabel = 'OPTIMIZED'; $riskBg = 'bg-erp-success/10'; $riskMsg = 'System equilibrium maintained.';
    
    if ($severityRatio > 1.5) { 
        $riskColor = 'erp-amber'; 
        $riskHex = '#FFBD2E'; 
        $riskLabel = 'ELEVATED WARNING'; 
        $riskBg = 'bg-erp-amber/10'; 
        $riskMsg = 'Systemic strain detected.'; 
    }
    
    if ($severityRatio > 2.5) { 
        $riskColor = 'erp-red'; 
        $riskHex = '#FF3366'; 
        $riskLabel = 'CRITICAL VULNERABILITY'; 
        $riskBg = 'bg-erp-red/10'; 
        $riskMsg = 'Capital hemorrhage critical.'; 
    }

    // --- 5. CHART MATHEMATICS ---
    $totalPAF = $appraisalCost + $internalFailureCost + $externalFailureCost;
    $c = 251.327; 
    
    $sA = $totalPAF > 0 ? ($appraisalCost / $totalPAF) * $c : 0;
    $sI = $totalPAF > 0 ? ($internalFailureCost / $totalPAF) * $c : 0;
    $sE = $totalPAF > 0 ? ($externalFailureCost / $totalPAF) * $c : 0;

    $offsetA = 0;
    $offsetI = -$sA;
    $offsetE = -($sA + $sI);

    $maxVal = max($calculatedErrorRate, $industryData->error_floor * 3);
    $actualHeight = $maxVal > 0 ? ($calculatedErrorRate / $maxVal) * 100 : 0;
    $baselineHeight = $maxVal > 0 ? ($industryData->error_floor / $maxVal) * 100 : 0;

    function fmtMoney($amt, $curr) { return $curr . ' ' . number_format($amt); }
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBS PRAXIS | Advanced Enterprise Diagnostic & PAF Report</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Roboto+Mono:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        erp: {
                            base: '#03050A', main: '#0A1128', surface: '#141E3C', teal: '#00E5FF',
                            red: '#FF3366', amber: '#FFBD2E', muted: '#8A9BB2', success: '#00FF66',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'], mono: ['Roboto Mono', 'monospace'],
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --erp-bg-base: #03050A; --erp-bg-main: #0A1128; --erp-teal: #00E5FF;
            --erp-red: #FF3366; --erp-amber: #FFBD2E; --erp-success: #00FF66; --erp-muted: #8A9BB2;
            --stark-white: #FFFFFF;
        }

        body { 
            background-color: var(--erp-bg-base); 
            color: #ffffff; 
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            margin: 0; padding: 0;
        }
        
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .pulse-dot { width: 6px; height: 6px; background-color: var(--erp-teal); border-radius: 50%; box-shadow: 0 0 10px var(--erp-teal); animation: pulse 2s infinite; display: inline-block; }
        
        /* --- 3D EXECUTIVE SUMMARY DECK --- */
        #executive-summary-view {
            display: flex; position: fixed; inset: 0; z-index: 9000;
            justify-content: center; align-items: center;
            background-image: url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2500&auto=format&fit=crop');
            background-size: cover; background-position: center;
            perspective: 1500px; overflow: hidden; transition: opacity 0.6s ease;
        }

        #executive-summary-view::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(5, 9, 20, 0.85) 0%, rgba(10, 17, 40, 0.95) 100%);
            backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); z-index: 0;
        }

        .deck-container {
            position: relative; width: 100%; max-width: 1200px; height: 600px;
            display: flex; justify-content: center; align-items: center; z-index: 2;
        }

        .exec-card {
            position: absolute; width: 100%; max-width: 460px; height: 550px;
            background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1); border-top: 4px solid var(--erp-teal);
            border-radius: 12px; padding: 40px 30px; display: flex; flex-direction: column;
            box-shadow: 0 40px 80px rgba(0,0,0,0.8), inset 0 0 20px rgba(255,255,255,0.02);
            transition: all 0.7s cubic-bezier(0.25, 1, 0.3, 1); pointer-events: none;
        }

        /* 3D States */
        .exec-card.active { transform: translateX(0) scale(1) translateZ(0); opacity: 1; z-index: 10; pointer-events: auto; border-color: rgba(0, 229, 255, 0.3); }
        .exec-card.prev { transform: translateX(-65%) scale(0.85) rotateY(15deg); opacity: 0.3; z-index: 5; filter: blur(3px); }
        .exec-card.next { transform: translateX(65%) scale(0.85) rotateY(-15deg); opacity: 0.3; z-index: 5; filter: blur(3px); }
        .exec-card.hidden-left { transform: translateX(-120%) scale(0.7); opacity: 0; z-index: 1; }
        .exec-card.hidden-right { transform: translateX(120%) scale(0.7); opacity: 0; z-index: 1; }

        .card-meta { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); letter-spacing: 3px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        .exec-card h3 { font-size: 1.6rem; color: var(--stark-white); margin-bottom: 15px; line-height: 1.1; text-transform: uppercase; font-family: 'Inter', sans-serif; font-weight: 900; letter-spacing: -1px; }
        .exec-card p { font-size: 0.95rem; color: var(--erp-muted); line-height: 1.6; margin-bottom: 20px; }
        
        .card-data-block { background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-left: 3px solid var(--erp-teal); margin-bottom: 10px; border-radius: 4px; transition: all 0.3s; }
        .card-data-block:hover { background: rgba(0, 229, 255, 0.05); transform: translateX(5px); }
        .card-data-label { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; display: block; letter-spacing: 1px; }
        .card-data-val { font-family: 'Roboto Mono', monospace; font-size: 1.2rem; font-weight: bold; color: var(--stark-white); }

        .card-controls { margin-top: auto; display: flex; gap: 15px; }
        .card-btn { flex: 1; padding: 15px; background: var(--erp-teal); color: var(--erp-bg-main); font-family: 'Inter', sans-serif; font-weight: 800; cursor: pointer; transition: all 0.3s; border-radius: 4px; text-align: center; text-transform: uppercase; font-size: 0.8rem; border: none; letter-spacing: 1px; }
        .card-btn:hover { background: var(--stark-white); box-shadow: 0 10px 25px rgba(0, 229, 255, 0.4); transform: translateY(-2px); }
        .card-btn-outline { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.1); color: var(--stark-white); backdrop-filter: blur(10px); }
        .card-btn-outline:hover { border-color: var(--stark-white); background: rgba(255,255,255,0.1); transform: translateY(-2px); }

        /* --- TIER-1 COMMAND DOCK --- */
        .tier1-dock {
            position: fixed; top: 50%; right: 20px; transform: translateY(-50%); display: flex; flex-direction: column; gap: 12px;
            background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 229, 255, 0.15); padding: 15px 12px; border-radius: 40px; z-index: 9999;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5), inset 0 0 20px rgba(0, 229, 255, 0.05);
        }

        .dock-btn {
            background: transparent; border: none; color: rgba(255,255,255,0.5); cursor: pointer; padding: 12px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative; text-decoration: none;
        }
        .dock-btn svg { width: 22px; height: 22px; stroke-width: 1.5; transition: stroke-width 0.2s; }
        .dock-btn:hover { color: var(--erp-teal); background: rgba(0, 229, 255, 0.1); box-shadow: 0 0 15px rgba(0, 229, 255, 0.2); }
        .dock-btn:hover svg { stroke-width: 2; }

        .dock-btn::after {
            content: attr(data-tooltip); position: absolute; right: 130%; background: var(--erp-main); color: var(--erp-teal);
            padding: 8px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; letter-spacing: 1px;
            border: 1px solid rgba(0, 229, 255, 0.3); border-radius: 3px; opacity: 0; pointer-events: none; white-space: nowrap;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); transform: translateX(10px); text-transform: uppercase;
        }
        .dock-btn:hover::after { opacity: 1; transform: translateX(0); }
        .dock-divider { width: 100%; height: 1px; background: rgba(255,255,255,0.1); margin: 5px 0; }

        /* --- PDF A4 DOCUMENT CONTAINER --- */
        .a4-page {
            width: 210mm; height: 297mm; background-color: var(--erp-bg-main) !important;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 0h40v40H0V0zm20 20h20v20H20V20zM0 20h20v20H0V20z' fill='%23ffffff' fill-opacity='0.01' fill-rule='evenodd'/%3E%3C/svg%3E"), 
                              radial-gradient(circle at 50% 0%, rgba(0, 229, 255, 0.03) 0%, transparent 70%);
            color: white !important; position: relative; overflow: hidden; box-sizing: border-box;
            padding: 40px 50px; box-shadow: 0 25px 50px -12px rgba(0, 229, 255, 0.15);
            border: 1px solid rgba(0, 229, 255, 0.05); flex-shrink: 0; display: flex; flex-direction: column; margin-bottom: 40px;
        }

        .doc-watermark {
            position: absolute; top: 40px; right: -30px; font-family: 'Roboto Mono', monospace; font-size: 8rem;
            color: rgba(255,255,255,0.015); font-weight: 900; pointer-events: none; z-index: 0;
            transform: rotate(-90deg); transform-origin: top right; letter-spacing: -2px; white-space: nowrap;
        }
        .corner-bracket { position: absolute; width: 20px; height: 20px; border: 2px solid var(--erp-teal); opacity: 0.5; z-index: 10; }
        .tl { top: 30px; left: 30px; border-right: none; border-bottom: none; }
        .tr { top: 30px; right: 30px; border-left: none; border-bottom: none; }
        .bl { bottom: 30px; left: 30px; border-right: none; border-top: none; }
        .br { bottom: 30px; right: 30px; border-left: none; border-top: none; }

        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { background: #0A1128 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            #executive-summary-view, .tier1-dock { display: none !important; }
            .a4-page { box-shadow: none !important; border: none !important; margin: 0 !important; }
            #pdf-render-zone { gap: 0 !important; padding: 0 !important; }
        }

        /* --- MOBILE UX: UNRESTRICTED FULL SCROLLING --- */
        @media (max-width: 850px) {
            .exec-card.prev { transform: translateX(-40%) scale(0.85); opacity: 0; }
            .exec-card.next { transform: translateX(40%) scale(0.85); opacity: 0; }
            .exec-card { padding: 30px 20px; max-width: 90%; height: 500px; }

            .tier1-dock {
                top: 20px; right: 50%; transform: translateX(50%);
                flex-direction: row; padding: 10px 20px; width: max-content;
            }
            .dock-divider { width: 1px; height: 24px; margin: 0 5px; align-self: center; }
            .dock-btn::after { right: auto; top: 140%; left: 50%; transform: translate(-50%, -10px); }
            .dock-btn:hover::after { transform: translate(-50%, 0); }
            .deck-container { margin-top: 100px; }

            html, body { overflow: auto !important; align-items: flex-start !important; }
            
            .w-full.flex-grow { align-items: flex-start !important; padding-left: 15px; }
            
            #pdf-render-zone {
                width: max-content; 
                padding-top: 100px; 
                display: flex; flex-direction: column;
            }
            
            .a4-page { transform: none !important; margin-bottom: 30px !important; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center selection:bg-erp-teal selection:text-erp-main">

    <div class="tier1-dock">
        <a href="/" class="dock-btn" data-tooltip="Command HQ (Home)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        </a>
        <a href="/schedule" class="dock-btn" data-tooltip="Schedule Consultation">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        </a>
        <a href="/contact" class="dock-btn" data-tooltip="Initiate Diagnostic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
        </a>

        <div class="dock-divider"></div>

        <button id="toggleViewBtn" class="dock-btn" data-tooltip="Executive Summary Mode">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        </button>

        <button id="downloadPdfBtn" class="dock-btn" data-tooltip="Secure PDF Download">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        </button>
    </div>

    <div id="executive-summary-view" style="{{ $isExecMode ? 'display: flex; opacity: 1; pointer-events: auto;' : 'display: none; opacity: 0; pointer-events: none;' }}">
        <div class="deck-container">
            
            <div class="exec-card active" data-index="0">
                <div class="card-meta"><span>01 // 07</span> <span class="pulse-dot"></span></div>
                <h3>Diagnostic Initialization</h3>
                <p>We have successfully compiled the operational telemetry for <strong>{{ strtoupper($companyInput) }}</strong>. Your current architecture relies heavily on <strong>{{ $toolData->display_name }}</strong>.</p>
                <div class="card-data-block">
                    <span class="card-data-label">Annual Turnover Baseline</span>
                    <span class="card-data-val">{{ fmtMoney($turnoverInput, $currency) }}</span>
                </div>
                <div class="card-controls">
                    <button class="card-btn next-card-btn">ANALYZE FRICTION ➔</button>
                </div>
            </div>

            <div class="exec-card" data-index="1">
                <div class="card-meta"><span>02 // 07</span> <span class="pulse-dot"></span></div>
                <h3>The PAF Calculus</h3>
                <p>Your processes are untracked. We utilized the Prevention, Appraisal, and Failure (PAF) matrix to map your hidden costs.</p>
                <div class="card-data-block" style="border-left-color: var(--erp-amber);">
                    <span class="card-data-label">Appraisal (Time Tax)</span>
                    <span class="card-data-val text-erp-amber">{{ fmtMoney($appraisalCost, $currency) }}</span>
                </div>
                <div class="card-data-block" style="border-left-color: var(--erp-red);">
                    <span class="card-data-label">Internal Failure (Process Tax)</span>
                    <span class="card-data-val text-erp-red">{{ fmtMoney($internalFailureCost, $currency) }}</span>
                </div>
                <div class="card-controls">
                    <button class="card-btn card-btn-outline prev-card-btn">BACK</button>
                    <button class="card-btn next-card-btn">CALCULATE TOTAL ➔</button>
                </div>
            </div>

            <div class="exec-card" data-index="2" style="border-top-color: {{ $riskHex }};">
                <div class="card-meta"><span style="color: {{ $riskHex }};">03 // 07</span> <span class="pulse-dot" style="background-color: {{ $riskHex }}; box-shadow: 0 0 10px {{ $riskHex }};"></span></div>
                <h3>Systemic Capital Leakage</h3>
                <p>This is the exact financial volume your enterprise bleeds annually due to missing digital governance and architectural bottlenecks.</p>
                <div class="card-data-block" style="border-color: {{ $riskHex }}; background: rgba({{ $riskHex == '#FF3366' ? '255,51,102' : ($riskHex == '#FFBD2E' ? '255,189,46' : '0,255,102') }}, 0.1);">
                    <span class="card-data-label" style="color: {{ $riskHex }};">Cost of Poor Quality (COPQ)</span>
                    <span class="card-data-val" style="font-size: 2rem; color: {{ $riskHex }};">{{ fmtMoney($totalCOPQ, $currency) }}</span>
                </div>
                <div class="card-controls">
                    <button class="card-btn card-btn-outline prev-card-btn">BACK</button>
                    <button class="card-btn next-card-btn">MARKET BENCHMARK ➔</button>
                </div>
            </div>

            <div class="exec-card" data-index="3">
                <div class="card-meta"><span>04 // 07</span> <span class="pulse-dot"></span></div>
                <h3>Industry Deficit</h3>
            <p>You are operating at a <strong>{{ number_format($calculatedErrorRate - $industryData->error_floor, 1) }}% {{ $industryData->deficit_context }}</strong>, directly eroding your {{ $industryData->erosion_target }}.</p>
                <div class="card-data-block" style="border-left-color: var(--erp-red);">
                    <span class="card-data-label">Your Error Rate</span>
                    <span class="card-data-val text-erp-red">{{ number_format($calculatedErrorRate, 1) }}%</span>
                </div>
                <div class="card-data-block" style="border-left-color: var(--erp-success);">
                    <span class="card-data-label">Tier-1 Baseline</span>
                    <span class="card-data-val text-erp-success">{{ number_format($industryData->error_floor, 1) }}%</span>
                </div>
                <div class="card-controls">
                    <button class="card-btn card-btn-outline prev-card-btn">BACK</button>
                    <button class="card-btn next-card-btn">VIEW HUMAN COST ➔</button>
                </div>
            </div>

            <div class="exec-card" data-index="4">
            <div class="card-meta"><span>05 // 07</span> <span class="pulse-dot"></span></div>
            <h3>Human Capital Decay</h3>
            <p>Bad architecture doesn't just leak capital; it burns out elite talent. High operational friction directly drives executive churn, creating a massive hidden replacement tax.</p>
            <div class="card-data-block">
                <span class="card-data-label">Projected Exec Churn</span>
                <span class="card-data-val">{{ $burnoutTurnover }} Nodes / Yr</span>
            </div>
            <div class="card-data-block" style="border-left-color: var(--erp-amber);">
                <span class="card-data-label">Talent Replacement Cost</span>
                <span class="card-data-val text-erp-amber">{{ fmtMoney($replacementCost, $currency) }}</span>
            </div>
            <div class="card-controls">
                <button class="card-btn card-btn-outline prev-card-btn">BACK</button>
                <button class="card-btn next-card-btn">VIEW THREAT LEVEL ➔</button>
            </div>
        </div>

            <div class="exec-card" data-index="5" style="border-top-color: {{ $riskHex }};">
                <div class="card-meta"><span style="color: {{ $riskHex }};">06 // 07</span> <span class="pulse-dot" style="background-color: {{ $riskHex }}; box-shadow: 0 0 10px {{ $riskHex }};"></span></div>
                <h3>Predictive Vulnerability</h3>
                <p>Our algorithms have identified the most statistically probable point of failure within your current operations.</p>
                <div class="card-data-block" style="border-left-color: {{ $riskHex }};">
                    <span class="card-data-label">Critical Threat Vector</span>
                    <span class="card-data-val" style="font-size: 0.9rem; color: {{ $riskHex }};">{{ strtoupper($industryData->risk_type) }}</span>
                </div>
                <div class="card-data-block">
                    <span class="card-data-label">Probability of Breach (3 Qtrs)</span>
                    <span class="card-data-val">{{ $riskProbability }}%</span>
                </div>
                <div class="card-controls">
                    <button class="card-btn card-btn-outline prev-card-btn">BACK</button>
                    <button class="card-btn next-card-btn">THE INTERVENTION ➔</button>
                </div>
            </div>

            <div class="exec-card" data-index="6">
            <div class="card-meta"><span>07 // 07</span> <span class="pulse-dot"></span></div>
            <h3>The Architectural Cure</h3>
            <p>JBS Praxis engineers deterministic solutions. By dismantling your legacy tools and hardcoding compliance, the ROI is mathematically guaranteed.</p>
            
            <div class="card-data-block" style="border-left-color: var(--erp-success);">
                <span class="card-data-label">Estimated System Payback</span>
                <span class="card-data-val text-erp-success">{{ $breakEvenMonths }} MONTHS</span>
                <div style="font-size: 0.65rem; color: var(--erp-muted); margin-top: 5px; line-height: 1.4;">
                    After month {{ ceil($breakEvenMonths) }}, the architecture yields pure operational profit.
                </div>
            </div>

            <div class="card-data-block" style="border-left-color: var(--erp-red); background: rgba(255, 51, 102, 0.05);">
                <span class="card-data-label text-erp-red">Cost of Inaction (COI)</span>
                <span class="card-data-val text-erp-red">{{ fmtMoney($monthlyLeakage, $currency) }} / MO</span>
                <div style="font-size: 0.6rem; color: var(--erp-muted); text-transform: uppercase; letter-spacing: 1px; margin-top: 5px;">
                    Unrecoverable Monthly Capital Lost as Audit is Delayed.
                </div>
            </div>

            <div class="card-controls" style="margin-top: auto; display: flex; flex-direction: row; gap: 10px;">
                <button class="card-btn card-btn-outline prev-card-btn" style="flex: 0.3; padding: 15px 10px; font-size: 0.75rem;">BACK</button>
                <button class="card-btn" id="open-full-dossier" style="flex: 1; background: var(--stark-white); color: var(--erp-main);">FULL DOSSIER ➔</button>
            </div>
        </div>

        </div>
    </div>
    <div class="w-full flex-grow flex flex-col items-center py-12 bg-erp-base overflow-y-auto" style="{{ $isExecMode ? 'display: none;' : '' }}" id="full-dossier-wrapper">
        <div id="pdf-render-zone" class="flex flex-col gap-12 w-[210mm]">
            
            <div class="a4-page">
                <div class="doc-watermark">LEAKAGE.CALCULUS</div>
                
                <div class="corner-bracket tl"></div><div class="corner-bracket tr"></div>
                <div class="flex justify-between items-start mb-10 border-b border-white/10 pb-6 relative z-10">
                    <div class="flex items-center gap-4">
                        <img src="/assets/img/jbs_monochrome.svg" alt="JBS Praxis" class="h-8 opacity-90" onerror="this.style.display='none'">
                        <div>
                            <div class="font-black tracking-widest text-base text-white">JBS PRAXIS</div>
                            <div class="font-mono text-[9px] text-erp-teal uppercase tracking-widest mt-1">Intelligence & Architecture Desk</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-mono text-[10px] font-bold text-erp-muted mb-1">DOCUMENT REF: <span class="text-white">{{ $diagId }}</span></div>
                        <div class="font-mono text-[10px] text-erp-muted">DATE ISSUED: <span class="text-white">{{ $dateStr }}</span></div>
                        <div class="font-mono text-[10px] text-erp-muted">CONTACT: <span class="text-erp-teal">intelligence@jbspraxis.com</span></div>
                    </div>
                </div>

                <div class="relative z-10 flex-grow flex flex-col">
                    <div class="flex justify-between items-end mb-8">
                        <div>
                            <div class="text-[11px] font-black tracking-[0.3em] text-erp-muted mb-2 uppercase">Executive Diagnostic Brief</div>
                            <h1 class="text-3xl font-black text-white tracking-tight uppercase leading-none">Cost of Poor Quality <br><span class="text-erp-muted text-2xl">(COPQ) Analysis</span></h1>
                        </div>
                        <div class="px-4 py-2 {{ $riskBg }} border border-{{ $riskColor }} flex flex-col items-end">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-2 h-2 rounded-full bg-{{ $riskColor }} animate-pulse shadow-[0_0_10px_currentColor]"></div>
                                <span class="font-mono text-[10px] font-bold text-{{ $riskColor }} uppercase tracking-widest">RISK: {{ $riskLabel }}</span>
                            </div>
                            <span class="font-mono text-[8px] text-white opacity-80 uppercase">{{ $riskMsg }}</span>
                        </div>
                    </div>

                    <div class="font-mono text-[10px] text-erp-muted grid grid-cols-2 gap-x-12 gap-y-3 mb-8 bg-black/40 p-5 border-l-2 border-{{ $riskColor }} glass-panel">
                        <div class="flex justify-between border-b border-white/5 pb-1"><span>ENTERPRISE:</span> <span class="text-white font-bold">{{ strtoupper($companyInput) }}</span></div>
                        <div class="flex justify-between border-b border-white/5 pb-1"><span>SECTOR:</span> <span class="text-white font-bold">{{ strtoupper($industryInput) }}</span></div>
                        <div class="flex justify-between border-b border-white/5 pb-1"><span>REQUESTED BY:</span> <span class="text-white font-bold">{{ strtoupper($nameInput) }}</span></div>
                        <div class="flex justify-between border-b border-white/5 pb-1"><span>TURNOVER BASELINE:</span> <span class="text-white font-bold">{{ fmtMoney($turnoverInput, $currency) }}</span></div>
                        <div class="flex justify-between border-b border-white/5 pb-1"><span>MGMT HEADCOUNT:</span> <span class="text-white font-bold">{{ $mgmtHeadcount }} Nodes</span></div>
                        <div class="flex justify-between border-b border-white/5 pb-1"><span>PRIMARY ARCHITECTURE:</span> <span class="text-white font-bold">{{ $toolData->display_name }}</span></div>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-sm font-black uppercase tracking-widest text-white mb-3 border-b border-white/10 pb-2 flex items-center gap-2">
                            <i data-lucide="calculator" class="w-4 h-4 text-erp-teal"></i> I. The PAF Financial Breakdown
                        </h2>
                        <p class="text-[11px] text-erp-muted leading-relaxed text-justify mb-5">
                            Operational chaos is a mathematically predictable financial liability. Utilizing the academic <strong>Prevention, Appraisal, and Failure (PAF)</strong> framework of quality economics, your enterprise is currently bleeding significant capital. This leakage stems directly from un-engineered internal controls, manual approval latencies, and the compounding data silos inherent in your current systems.
                        </p>
                        
                        <div class="flex gap-6 items-center">
                            <div class="w-1/3 flex justify-center">
                                <svg width="160" height="160" viewBox="0 0 100 100" class="transform -rotate-90">
                                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="rgba(255,255,255,0.05)" stroke-width="12"></circle>
                                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="var(--erp-amber)" stroke-width="12" stroke-dasharray="{{$sA}} {{$c}}" stroke-dashoffset="{{$offsetA}}"></circle>
                                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="var(--erp-red)" stroke-width="12" stroke-dasharray="{{$sI}} {{$c}}" stroke-dashoffset="{{$offsetI}}"></circle>
                                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="var(--erp-teal)" stroke-width="12" stroke-dasharray="{{$sE}} {{$c}}" stroke-dashoffset="{{$offsetE}}"></circle>
                                    <text x="50" y="50" font-family="Roboto Mono" font-size="10" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="middle" transform="rotate(90 50 50)">PAF</text>
                                </svg>
                            </div>
                            <div class="w-2/3 space-y-3">
                                <div class="bg-black/30 border border-erp-amber/30 p-3 flex justify-between items-center">
                                    <div>
                                        <div class="text-[10px] font-bold text-white uppercase mb-1 flex items-center gap-2"><div class="w-2 h-2 bg-erp-amber"></div> {{ $appraisalTitle }}</div>
                                        <div class="text-[8px] text-erp-muted">{{ $appraisalDesc }}</div>
                                    </div>
                                    <div class="font-mono text-sm font-bold text-erp-amber">{{ fmtMoney($appraisalCost, $currency) }}</div>
                                </div>
                                <div class="bg-black/30 border border-erp-red/30 p-3 flex justify-between items-center">
                                    <div>
                                        <div class="text-[10px] font-bold text-white uppercase mb-1 flex items-center gap-2"><div class="w-2 h-2 bg-erp-red"></div> {{ $internalTitle }}</div>
                                        <div class="text-[8px] text-erp-muted">{{ $internalDesc }}</div>
                                    </div>
                                    <div class="font-mono text-sm font-bold text-erp-red">{{ fmtMoney($internalFailureCost, $currency) }}</div>
                                </div>
                                <div class="bg-black/30 border border-erp-teal/30 p-3 flex justify-between items-center">
                                    <div>
                                        <div class="text-[10px] font-bold text-white uppercase mb-1 flex items-center gap-2"><div class="w-2 h-2 bg-erp-teal"></div> {{ $extFailureLabel }}</div>
                                        <div class="text-[8px] text-erp-muted">{{ $externalDesc }}</div>
                                    </div>
                                    <div class="font-mono text-sm font-bold text-erp-teal">{{ fmtMoney($externalFailureCost, $currency) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="border-y border-white/20 py-5 flex justify-between items-center bg-white/5 px-6 shadow-inner mb-6">
                            <div>
                                <div class="font-black tracking-widest uppercase text-white text-[12px]">Total Calculated Capital Leakage</div>
                                <div class="font-mono text-[9px] text-erp-red mt-1">Total hard capital lost annually to current operational architecture</div>
                            </div>
                            <div class="font-mono text-2xl font-black text-{{ $riskColor }} tracking-tighter">{{ fmtMoney($totalCOPQ, $currency) }}</div>
                        </div>

                        <div class="bg-erp-surface border border-white/10 p-5 relative overflow-hidden">
                            <h3 class="text-[11px] font-black uppercase text-white tracking-widest mb-2 relative z-10">The Opportunity Cost Pivot</h3>
                            <p class="text-[10px] text-erp-muted leading-relaxed text-justify relative z-10">
                                Saved time does not just save salaries; it generates <strong>{{ $industryData->value_term }}</strong>. If the <strong>{{ $firefightingInput }} hours/week</strong> currently wasted by your {{ $mgmtHeadcount }} management nodes were reallocated to <strong>{{ $industryData->opportunity_focus }}</strong>, at a conservative 2.5x <strong>{{ $industryData->multiplier_term }}</strong>, your <strong>True Opportunity Cost is <span class="text-white">{{ fmtMoney($opportunityCost, $currency) }}</span> annually.</strong>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="corner-bracket bl"></div><div class="corner-bracket br"></div>
                <div class="mt-auto border-t border-white/10 pt-5 flex justify-between items-end relative z-10">
                    <div class="w-2/3">
                        <div class="font-mono text-[8px] text-erp-muted uppercase mb-1">Proprietary & Confidential • Subject to NDPR/GDPR/ISO27001 Regulations</div>
                        <div class="font-mono text-[7px] text-erp-muted opacity-60">This diagnostic utilizes proprietary JBS Praxis algorithms mapped to academic PAF frameworks Estimates rely on proxy inputs and industry standard deviations. Unauthorized distribution is prohibited.</div>
                    </div>
                    <div class="font-mono text-xs font-black text-erp-teal tracking-widest">PAGE 01 // 03</div>
                </div>
            </div>

            <div class="a4-page">
                <div class="doc-watermark" style="top: 300px;">FRICTION.MATRIX</div>
                
                <div class="corner-bracket tl"></div><div class="corner-bracket tr"></div>
                <div class="flex justify-between items-start mb-10 border-b border-white/10 pb-6 relative z-10">
                    <div class="flex items-center gap-4">
                        <img src="/assets/img/jbs_monochrome.svg" alt="JBS Praxis" class="h-8 opacity-90" onerror="this.style.display='none'">
                        <div>
                            <div class="font-black tracking-widest text-base text-white">JBS PRAXIS</div>
                            <div class="font-mono text-[9px] text-erp-teal uppercase tracking-widest mt-1">Intelligence & Architecture Desk</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-mono text-[10px] font-bold text-erp-muted mb-1">DOCUMENT REF: <span class="text-white">{{ $diagId }}</span></div>
                        <div class="font-mono text-[10px] text-erp-muted">DATE ISSUED: <span class="text-white">{{ $dateStr }}</span></div>
                        <div class="font-mono text-[10px] text-erp-muted">CONTACT: <span class="text-erp-teal">intelligence@jbspraxis.com</span></div>
                    </div>
                </div>

                <div class="relative z-10 flex-grow flex flex-col space-y-8">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-white mb-3 border-b border-white/10 pb-2 flex items-center gap-2">
                            <i data-lucide="bar-chart-2" class="w-4 h-4 text-erp-teal"></i> II. Industry Benchmarking & Competitiveness
                        </h2>
                        <div class="flex gap-6 items-center bg-black/20 p-5 border border-white/5">
                            <div class="w-1/2">
                                <p class="text-[10px] text-erp-muted leading-relaxed text-justify mb-3">
                                    Quality is relative to the market. Based on your inputs, your estimated Process Error Rate is <strong>{{ number_format($calculatedErrorRate, 1) }}%</strong>. The baseline for Tier-1 <strong>{{ $industryInput }}</strong> {{ $industryData->organization_type }} is <strong>{{ number_format($industryData->error_floor, 1) }}%</strong>. 
                                </p>
                                <p class="text-[10px] text-erp-muted leading-relaxed text-justify">
                                    You are currently operating at a <strong>{{ number_format($calculatedErrorRate - $industryData->error_floor, 1) }}% {{ $industryData->deficit_context }}</strong>, directly eroding your {{ $industryData->erosion_target }} and regulatory compliance posture concerning <strong>{{ $industryData->compliance_standard }}</strong>.
                                </p>
                            </div>
                            <div class="w-1/2 bg-erp-main p-4 border border-white/10">
                                <svg width="100%" height="80" viewBox="0 0 200 80" preserveAspectRatio="none">
                                    <line x1="0" y1="20" x2="200" y2="20" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
                                    <line x1="0" y1="60" x2="200" y2="60" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
                                    <rect x="30" y="{{ 80 - $baselineHeight }}" width="40" height="{{ $baselineHeight }}" fill="var(--erp-success)" opacity="0.8"></rect>
                                    <text x="50" y="{{ 80 - $baselineHeight - 5 }}" font-family="Roboto Mono" font-size="8" fill="var(--erp-success)" text-anchor="middle">{{ number_format($industryData->error_floor, 1) }}%</text>
                                    <rect x="130" y="{{ 80 - $actualHeight }}" width="40" height="{{ $actualHeight }}" fill="var(--erp-red)" opacity="0.9"></rect>
                                    <text x="150" y="{{ 80 - $actualHeight - 5 }}" font-family="Roboto Mono" font-size="8" fill="var(--erp-red)" text-anchor="middle" font-weight="bold">{{ number_format($calculatedErrorRate, 1) }}%</text>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-white mb-3 border-b border-white/10 pb-2 flex items-center gap-2">
                            <i data-lucide="microscope" class="w-4 h-4 text-erp-teal"></i> III. The Friction Matrix (Diagnosis)
                        </h2>
                        <p class="text-[11px] text-erp-muted leading-relaxed text-justify mb-4">
                            The financial hemorrhage detailed on Page 1 is the symptom; your digital architecture is the disease. Based on your indicated reliance on <strong>{{ $toolData->display_name }}</strong>, our intelligence engine has isolated the primary mechanism of your systemic decay.
                        </p>
                        <div class="border-l-4 border-erp-red pl-5 py-2 mb-4 bg-gradient-to-r from-erp-red/10 to-transparent">
                            <h3 class="text-[12px] font-black text-white uppercase tracking-widest mb-2">{{ $toolData->symptom_title }}</h3>
                            
                            <p class="text-[10px] text-white font-medium leading-relaxed text-justify">{{ $aiAnalysis }}</p>
                            
                            <p class="text-[10px] text-erp-muted leading-relaxed text-justify mt-2 font-bold text-erp-red">Primary Threat Vector: {{ $industryData->risk_type }}</p>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-white mb-3 border-b border-white/10 pb-2 flex items-center gap-2">
                            <i data-lucide="users" class="w-4 h-4 text-erp-teal"></i> IV. The Intangible Cost (Talent Churn)
                        </h2>
                        <div class="grid grid-cols-3 gap-5">
                            <div class="col-span-2">
                                <p class="text-[10px] text-erp-muted leading-relaxed text-justify mb-3">
                                    The COPQ formula strictly measures hard capital, but it ignores the most catastrophic hidden cost of bad architecture: Key Personnel Burnout. High manual latency and bureaucratic friction directly correlate with top-tier talent churn.
                                </p>
                                <p class="text-[10px] text-erp-muted leading-relaxed text-justify">
                                    Assuming standard metrics where high operational friction drives a conservative 5% churn rate among your {{ $mgmtHeadcount }} management nodes, and replacement costs equate to 40% of their annual equivalent salary, you are facing a massive hidden tax.
                                </p>
                            </div>
                            <div class="col-span-1 bg-erp-surface border border-erp-amber/50 p-4 flex flex-col justify-center text-center">
                                <div class="text-[8px] text-erp-muted font-bold uppercase tracking-widest mb-2">Talent Replacement Cost</div>
                                <div class="font-mono text-xl font-black text-erp-amber mb-1">{{ fmtMoney($replacementCost, $currency) }}</div>
                                <div class="text-[8px] text-erp-muted">Based on {{ $burnoutTurnover }} lost nodes/yr</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="bg-black/60 border border-erp-red/40 p-5 relative overflow-hidden">
                                <h3 class="text-[11px] font-black uppercase text-erp-red tracking-widest mb-2 relative z-10 flex items-center gap-2">
                                    <i data-lucide="shield-alert" class="w-4 h-4"></i> V. Predictive Risk Probability
                                </h3>
                                <p class="text-[10px] text-white leading-relaxed text-justify relative z-10">
                                    <strong>SYSTEM ALERT:</strong> Based on an error rate of {{ number_format($calculatedErrorRate, 1) }}% combined with your current architectural setup, our statistical models indicate an <strong>{{ $riskProbability }}% probability</strong> of a severe incident involving <strong>{{ strtolower($industryData->risk_type) }}</strong> occurring within the next 3 quarters.
                                </p>
                            </div>
                    </div>
                </div>

                <div class="corner-bracket bl"></div><div class="corner-bracket br"></div>
                <div class="mt-auto border-t border-white/10 pt-5 flex justify-between items-end relative z-10">
                    <div class="w-2/3">
                        <div class="font-mono text-[8px] text-erp-muted uppercase mb-1">Proprietary & Confidential • Subject to NDPR/GDPR/ISO27001 Regulations</div>
                        <div class="font-mono text-[7px] text-erp-muted opacity-60">This diagnostic utilizes proprietary JBS Praxis algorithms mapped to academic PAF frameworks Estimates rely on proxy inputs and industry standard deviations. Unauthorized distribution is prohibited.</div>
                    </div>
                    <div class="font-mono text-xs font-black text-erp-teal tracking-widest">PAGE 02 // 03</div>
                </div>
            </div>

            <div class="a4-page">
                <div class="doc-watermark" style="top: 500px;">ARCHITECTURE</div>
                
                <div class="corner-bracket tl"></div><div class="corner-bracket tr"></div>
                <div class="flex justify-between items-start mb-10 border-b border-white/10 pb-6 relative z-10">
                    <div class="flex items-center gap-4">
                        <img src="/assets/img/jbs_monochrome.svg" alt="JBS Praxis" class="h-8 opacity-90" onerror="this.style.display='none'">
                        <div>
                            <div class="font-black tracking-widest text-base text-white">JBS PRAXIS</div>
                            <div class="font-mono text-[9px] text-erp-teal uppercase tracking-widest mt-1">Intelligence & Architecture Desk</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-mono text-[10px] font-bold text-erp-muted mb-1">DOCUMENT REF: <span class="text-white">{{ $diagId }}</span></div>
                        <div class="font-mono text-[10px] text-erp-muted">DATE ISSUED: <span class="text-white">{{ $dateStr }}</span></div>
                        <div class="font-mono text-[10px] text-erp-muted">CONTACT: <span class="text-erp-teal">intelligence@jbspraxis.com</span></div>
                    </div>
                </div>

                <div class="relative z-10 flex-grow flex flex-col space-y-8">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-white mb-3 border-b border-white/10 pb-2 flex items-center gap-2">
                            <i data-lucide="cpu" class="w-4 h-4 text-erp-teal"></i> VI. The Praxis Blueprint (The Intervention)
                        </h2>
                        <p class="text-[11px] text-erp-muted leading-relaxed text-justify mb-5">
                            JBS Praxis does not write theoretical strategy reports. We deploy an elite strike team of Management Consultants, IS Auditors, and Full-Stack Architects to surgically eliminate friction. We translate audit findings directly into code.
                        </p>
                        <div class="space-y-4">
                            <div class="bg-black/40 p-4 border border-white/10 flex gap-4 items-start glass-panel">
                                <div class="p-2 bg-erp-teal/10 rounded border border-erp-teal/20"><i data-lucide="code" class="w-5 h-5 text-erp-teal"></i></div>
                                <div>
                                    <h4 class="text-[11px] font-bold text-white uppercase tracking-widest mb-1">Architectural Overhaul: {{ $archMandate }}</h4>
                                    <p class="text-[10px] text-erp-muted leading-relaxed text-justify">
                                        @if($archetype === 'SOVEREIGN')
                                            We safely decouple rigid legacy monoliths, transitioning your data into a highly secure, centralized relational database governed by strict API gateways to ensure absolute sovereign data control.
                                        @elseif($archetype === 'IMPACT')
                                            We extract fragile spreadsheet data and deploy a zero-loss relational database featuring offline-first edge-sync capabilities. This ensures operational continuity in zero-bandwidth field environments.
                                        @elseif($archetype === 'ASSET_HEAVY')
                                        We transition operations from fragile patches into a robust, custom-engineered architecture built for limitless horizontal scaling, effectively bridging the gap between rig-site SCADA systems and corporate ERPs.
                                        @else
                                            {{ $toolData->intervention_strategy }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="bg-black/40 p-4 border border-white/10 flex gap-4 items-start glass-panel">
                                <div class="p-2 bg-erp-teal/10 rounded border border-erp-teal/20"><i data-lucide="shield-check" class="w-5 h-5 text-erp-teal"></i></div>
                                <div>
                                    <h4 class="text-[11px] font-bold text-white uppercase tracking-widest mb-1">Code as Law (Governance)</h4>
                                    <p class="text-[10px] text-erp-muted leading-relaxed text-justify">
                                        We translate overarching policies and <strong>{{ $industryData->compliance_standard }}</strong> regulations directly into automated software workflows. Dynamic, role-based expenditure limits (RBAC), critical path lockouts, and multi-tier approval chains are programmatically enforced at the database level, ensuring immutable compliance without sacrificing operational agility.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-white mb-3 border-b border-white/10 pb-2 flex items-center gap-2">
                            <i data-lucide="line-chart" class="w-4 h-4 text-erp-teal"></i> VII. Break-Even & Cost of Inaction (COI)
                        </h2>
                        <p class="text-[10px] text-erp-muted leading-relaxed text-justify mb-4">
                                Enterprise software is not a cost center; it is a vital capital retention mechanism. Based on your current calculated leakage against the estimated cost of deploying a bespoke, zero-trust Praxis operating system:
                            </p>
                        <div class="grid grid-cols-2 gap-6 mt-4">
                            <div class="bg-gradient-to-br from-erp-success/20 to-black/50 border border-erp-success/40 p-5 text-center flex flex-col justify-center">
                                <div class="text-[9px] text-white font-bold uppercase tracking-widest mb-2">Estimated System Payback Period</div>
                                <div class="font-mono text-4xl font-black text-erp-success drop-shadow-[0_0_15px_rgba(0,255,102,0.3)]">{{ $breakEvenMonths }} MONTHS</div>
                                <div class="text-[8px] text-erp-muted mt-3">After month {{ ceil($breakEvenMonths) }}, the architecture yields pure operational profit to the bottom line.</div>
                            </div>
                            <div class="bg-gradient-to-br from-erp-red/20 to-black/50 border border-erp-red/40 p-5 text-center flex flex-col justify-center">
                                <div class="text-[9px] text-white font-bold uppercase tracking-widest mb-2 flex justify-center items-center gap-2"><i data-lucide="clock" class="w-3 h-3"></i> Cost of Inaction (COI)</div>
                                <div class="font-mono text-2xl font-black text-erp-red">{{ fmtMoney($monthlyLeakage, $currency) }}</div>
                                <div class="text-[8px] text-erp-muted mt-3 uppercase tracking-widest">Unrecoverable Capital Lost Every Month This Audit is Delayed.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="border border-erp-teal/50 bg-erp-teal/10 p-6 text-center mb-6 relative overflow-hidden group">
                            <h3 class="text-[12px] font-black uppercase text-white tracking-[0.2em] mb-3 relative z-10">Secure Your Architecture</h3>
                            <p class="text-[10px] text-erp-muted mb-5 px-8 leading-relaxed relative z-10">
                                This document is a surface-level predictive diagnostic based on limited input parameters. Do not digitize confusion; eliminate it entirely. Secure a high-level consultation with the Praxis Strike Team to execute a deep-tissue architectural audit.
                            </p>
                            <a href="/schedule" target="_blank" class="inline-block border border-erp-teal bg-black text-erp-teal font-mono text-[11px] font-bold uppercase tracking-widest py-3 px-8 hover:bg-erp-teal hover:text-erp-main transition-colors relative z-10">
                                INITIATE SECURE CONSULTATION
                            </a>
                        </div>
                        <div class="border-t border-white/10 pt-4">
                            <h4 class="font-mono text-[8px] text-white uppercase mb-1">Methodology & Academic Anchoring</h4>
                            <p class="font-mono text-[7px] text-erp-muted leading-relaxed text-justify opacity-70">
                                Calculations within this report utilize industry-standard PAF (Prevention, Appraisal, Failure) economic modeling, originally codified by Feigenbaum and ASQ. Predictive error rates are weighted utilizing normalized Logistics/Enterprise Tier-1 benchmarks against the user's reported technological stack and manual latency. External failure projections assume a standard LTV multiplier. This engine uses deterministic algorithms to calculate Cost of Poor Quality (COPQ) and does not constitute legally binding financial advice.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="corner-bracket bl"></div><div class="corner-bracket br"></div>
                <div class="mt-auto border-t border-white/10 pt-5 flex justify-between items-end relative z-10">
                    <div class="w-2/3">
                        <div class="font-mono text-[8px] text-erp-muted uppercase mb-1">Proprietary & Confidential • Subject to NDPR/GDPR/ISO27001 Regulations</div>
                        <div class="font-mono text-[7px] text-erp-muted opacity-60">This diagnostic utilizes proprietary JBS Praxis algorithms mapped to academic PAF frameworks Estimates rely on proxy inputs and industry standard deviations. Unauthorized distribution is prohibited.</div>
                    </div>
                    <div class="font-mono text-xs font-black text-erp-teal tracking-widest">PAGE 03 // 03</div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // --- 1. INITIALIZE ICONS ---
        lucide.createIcons();
        document.addEventListener('livewire:navigated', () => {
            lucide.createIcons();
        });

        // --- 2. SECURE PDF DOWNLOAD ENGINE ---
        async function triggerReportDownload() {
            const btn = document.getElementById('downloadPdfBtn');
            if (!btn) return;

            const originalTooltip = btn.getAttribute('data-tooltip');
            const originalHTML = btn.innerHTML;
            
            // Set loading state
            btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="4.93" x2="19.07" y2="7.76"></line></svg>`;
            btn.setAttribute('data-tooltip', 'GENERATING PDF...');
            btn.style.pointerEvents = "none";
            
            await new Promise(r => setTimeout(r, 100));

            try {
                const pages = document.querySelectorAll('.a4-page');
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');

                for (let i = 0; i < pages.length; i++) {
                    const pageEl = pages[i];
                    const originalZoom = pageEl.style.zoom;
                    pageEl.style.zoom = 1;

                    const canvas = await html2canvas(pageEl, { scale: 2, useCORS: true, backgroundColor: '#0A1128', logging: false });
                    const imgData = canvas.toDataURL('image/jpeg', 0.95);
                    
                    pageEl.style.zoom = originalZoom;

                    if (i > 0) pdf.addPage();
                    pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);
                }

                pdf.save(`jbs_praxis_diagnostic_report.pdf`);
                
            } catch (err) {
                console.error(err);
                alert("System Halt: PDF Generation Pipeline Failed.");
            } finally {
                btn.innerHTML = originalHTML;
                btn.setAttribute('data-tooltip', originalTooltip);
                btn.style.pointerEvents = "auto";
            }
        }

        // Bind the PDF download to the floating navbar button
        document.getElementById('btn-download')?.addEventListener('click', triggerReportDownload);
        document.getElementById('downloadPdfBtn')?.addEventListener('click', triggerReportDownload);

        // --- 3. EXECUTIVE CARD DECK ENGINE ---
        function initCardDeck() {
            const execView = document.getElementById("executive-summary-view");
            const fullDocZone = document.getElementById("full-dossier-wrapper");
            
            const nextBtns = document.querySelectorAll(".next-card-btn");
            const prevBtns = document.querySelectorAll(".prev-card-btn");
            const openDossierBtn = document.getElementById("open-full-dossier");
            const toggleViewBtn = document.getElementById("toggleViewBtn");
            const cards = document.querySelectorAll(".exec-card");
            
            if (!execView || cards.length === 0) return; 

            let currentCardIndex = 0;
            let isExecMode = @json($isExecMode ?? false);
            
            function updateCards() {
                cards.forEach((card, index) => {
                    card.className = "exec-card"; 
                    if (index === currentCardIndex) {
                        card.classList.add("active");
                    } else if (index === currentCardIndex - 1) {
                        card.classList.add("prev"); 
                    } else if (index === currentCardIndex + 1) {
                        card.classList.add("next"); 
                    } else if (index < currentCardIndex - 1) {
                        card.classList.add("hidden-left"); 
                    } else if (index > currentCardIndex + 1) {
                        card.classList.add("hidden-right"); 
                    }
                });
            }

            nextBtns.forEach(btn => {
                btn.onclick = (e) => {
                    e.preventDefault();
                    if (currentCardIndex < cards.length - 1) {
                        currentCardIndex++;
                        updateCards();
                    }
                };
            });

            prevBtns.forEach(btn => {
                btn.onclick = (e) => {
                    e.preventDefault();
                    if (currentCardIndex > 0) {
                        currentCardIndex--;
                        updateCards();
                    }
                };
            });

            function toggleView() {
                isExecMode = !isExecMode;
                const iconDocument = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>`;
                const iconLayers = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>`;

                if (isExecMode) {
                    fullDocZone.style.display = "none";
                    execView.style.display = "flex";
                    execView.style.opacity = 1;
                    execView.style.pointerEvents = 'auto';
                    
                    if(toggleViewBtn) {
                        toggleViewBtn.innerHTML = iconDocument;
                        toggleViewBtn.setAttribute('data-tooltip', 'View Full Document');
                    }
                    currentCardIndex = 0;
                    updateCards();
                } else {
                    execView.style.opacity = 0;
                    execView.style.pointerEvents = 'none'; 
                    setTimeout(() => {
                        execView.style.display = "none";
                        fullDocZone.style.display = "flex";
                        if(toggleViewBtn) {
                            toggleViewBtn.innerHTML = iconLayers;
                            toggleViewBtn.setAttribute('data-tooltip', 'Executive Summary Mode');
                        }
                    }, 500);
                }
            }

            if (openDossierBtn) {
                openDossierBtn.onclick = (e) => {
                    e.preventDefault();
                    toggleView();
                };
            }

            if (toggleViewBtn) {
                toggleViewBtn.onclick = (e) => {
                    e.preventDefault();
                    toggleView();
                };
            }

            updateCards();
        }

        document.addEventListener("DOMContentLoaded", initCardDeck);
        document.addEventListener("livewire:navigated", initCardDeck);
    </script>
</body>
</html>