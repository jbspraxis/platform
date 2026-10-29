<div>

    @push('styles')
    <style>
        /* ══════════════════════════════════════════════════════════════════
           JBS PRAXIS — MASTER CSS v3.0
           Brand-Compliant · Glassmorphic · Animated · Mobile-First
        ══════════════════════════════════════════════════════════════════ */

        /* ── BRAND CSS VARIABLES ─────────────────────────────────────── */
        :root {
            /* Core Brand Palette */
            --midnight-navy:   #0A1128;
            --slate-navy:      #141E3C;
            --deep-footer:     #050811;
            --luminous-teal:   #00E5FF;
            --stark-white:     #FFFFFF;
            --ghost-white:     #F3F4F6;
            --terminal-red:    #FF3366;
            --provision-green: #2D6A4F;
            --true-platinum:   #E5E4E2;

            /* Dark Mode Derived */
            --bg-primary:      #0A1128;
            --bg-secondary:    #141E3C;
            --text-primary:    #FFFFFF;
            --text-muted:      #B0B8C4;
            --text-secondary:  #8F9BA8;
            --accent-color:    #00E5FF;
            --border-color:    rgba(255, 255, 255, 0.06);
            --border-teal:     rgba(0, 229, 255, 0.15);
            --card-bg:         rgba(255, 255, 255, 0.025);

            /* Brand Typography */
            --font-display: 'Montserrat', sans-serif;
            --font-body:    'Inter', sans-serif;
            --font-mono:    'Roboto Mono', monospace;

            /* Motion */
            --ease-expo:   cubic-bezier(0.16, 1, 0.3, 1);
            --ease-smooth: cubic-bezier(0.4, 0, 0.2, 1);
        }

        html.light-mode {
            --bg-primary:     #FFFFFF;
            --bg-secondary:   #F3F4F6;
            --text-primary:   #0A1128;
            --text-muted:     #4A5568;
            --text-secondary: #64748B;
            --accent-color:   #0A1128;
            --border-color:   rgba(10, 17, 40, 0.08);
            --border-teal:    rgba(10, 17, 40, 0.15);
            --card-bg:        rgba(255, 255, 255, 0.9);
            --luminous-teal:  #2D6A4F;
        }

        /* ── GLOBAL ANIMATIONS ────────────────────────────────────────── */
        @keyframes pulse        { 0%,100%{opacity:1} 50%{opacity:.5} }
        @keyframes pulse-red    { 0%,100%{opacity:1} 50%{opacity:.3} }
        @keyframes blink        { 0%,100%{opacity:1} 50%{opacity:0} }
        @keyframes scanWipe     { 0%{top:-100%} 100%{top:100%} }
        @keyframes fadeDown     { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
        @keyframes blueprintFadeIn { from{opacity:0;transform:translateX(15px)} to{opacity:1;transform:translateX(0)} }
        @keyframes gridFlicker  { 0%,80%,100%{opacity:1;text-shadow:0 0 10px rgba(255,51,102,.6)} 5%,15%{opacity:.1} 10%{opacity:.8} 90%{opacity:.3} }
        @keyframes periodicScan { 0%{top:-5%;opacity:0} 2%{opacity:.5} 18%{top:105%;opacity:.5} 20%,100%{opacity:0;top:105%} }
        @keyframes ping         { 0%{transform:translateX(-100%)} 100%{transform:translateX(100%)} }
        @keyframes heroFloat    { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
        @keyframes gradientFlow { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        @keyframes revealUp     { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }
        @keyframes glow-pulse   { 0%,100%{box-shadow:0 0 0 0 rgba(0,229,255,.6)} 50%{box-shadow:0 0 0 6px rgba(0,229,255,0)} }
        @keyframes shimmer      { 0%{background-position:-200% center} 100%{background-position:200% center} }
        @keyframes heroScale    { from{transform:scale(1.06)} to{transform:scale(1.12)} }
        @keyframes scrollDot    { 0%,100%{top:6px;opacity:1} 80%{top:18px;opacity:0} }

        /* ── SCROLL REVEAL SYSTEM ─────────────────────────────────────── */
        [data-reveal] {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity .75s var(--ease-expo), transform .75s var(--ease-expo);
        }
        [data-reveal="left"]  { transform: translateX(-24px); }
        [data-reveal="right"] { transform: translateX(24px); }
        [data-reveal].is-visible { opacity: 1; transform: none; }

        [data-delay="1"] { transition-delay: .08s; }
        [data-delay="2"] { transition-delay: .16s; }
        [data-delay="3"] { transition-delay: .24s; }
        [data-delay="4"] { transition-delay: .32s; }
        [data-delay="5"] { transition-delay: .40s; }
        [data-delay="6"] { transition-delay: .48s; }
        [data-delay="7"] { transition-delay: .56s; }

        /* ── TYPOGRAPHY HELPERS ───────────────────────────────────────── */
        .mono-font  { font-family: var(--font-mono) !important; }
        .teal-text  { color: var(--luminous-teal); }
        .red-text   { color: var(--terminal-red); }
        .relative-z { position: relative; z-index: 10; }

        /* ── INDICATORS ───────────────────────────────────────────────── */
        .pulse-dot {
            width: 7px; height: 7px;
            background: var(--luminous-teal); border-radius: 50%;
            box-shadow: 0 0 0 0 var(--luminous-teal);
            animation: glow-pulse 2.2s ease-in-out infinite;
            flex-shrink: 0;
        }
        html.light-mode .pulse-dot {
            background: var(--provision-green);
            box-shadow: 0 0 10px rgba(45,106,79,.4);
        }
        .pulse-dot-red {
            width: 8px; height: 8px;
            background: var(--terminal-red); border-radius: 50%;
            box-shadow: 0 0 10px var(--terminal-red);
            animation: pulse-red 1.5s infinite;
            flex-shrink: 0;
        }
        @keyframes glow-pulse {
            0%,100% { box-shadow:0 0 0 0 rgba(0,229,255,.7); opacity:1; }
            50%     { box-shadow:0 0 0 5px rgba(0,229,255,0); opacity:.6; }
        }

        /* Eyebrow / label rows */
        .terminal-teal-text,
        .blueprint-subtitle {
            color: var(--luminous-teal);
            display: flex; align-items: center; justify-content: center;
            gap: 10px;
            font-family: var(--font-mono); font-size: .83rem; letter-spacing: .15em;
            margin-bottom: 14px;
        }
        .terminal-red-text {
            color: var(--terminal-red);
            display: flex; align-items: center; justify-content: center;
            gap: 10px;
            font-family: var(--font-mono); font-size: .83rem; letter-spacing: .1em;
            margin-bottom: 14px;
        }
        html.light-mode .terminal-teal-text,
        html.light-mode .blueprint-subtitle  { color: var(--provision-green); }
        html.light-mode .pulse-dot            { background: var(--provision-green); }

        /* Section header pattern */
        .section-header { margin-bottom: clamp(44px,6vw,72px); }
        .section-header > h2 { margin-bottom: 16px; }
        .section-header > p  { max-width: 700px; font-size: clamp(.9rem,1.1vw,1rem); color: var(--text-muted); line-height: 1.75; }
        .section-header.text-center > p { margin-left: auto; margin-right: auto; }
        .section-header.text-left  > p { margin-left: 0; }

        /* Headings */
        h1, h2, h3 {
            font-family: var(--font-display);
            font-weight: 900; letter-spacing: -.03em; line-height: 1.05;
        }
        h2 { font-size: clamp(1.9rem,4.5vw,3.4rem); color: var(--text-primary); }
        h3 { font-size: clamp(1.3rem,2.5vw,1.9rem); color: var(--text-primary); }

        /* btn-outline shared */
        .btn-outline {
            background: transparent;
            border: 1px solid var(--accent-color);
            color: var(--accent-color);
            text-decoration: none; padding: 12px 26px;
            display: inline-block; font-family: var(--font-body);
            font-weight: 700; text-transform: uppercase; font-size: .78rem;
            letter-spacing: .08em; transition: all .3s ease; border-radius: 2px;
        }
        .btn-outline:hover { background: var(--accent-color); color: var(--midnight-navy); }
        html.light-mode .btn-outline { border-color: var(--midnight-navy); color: var(--midnight-navy); }
        html.light-mode .btn-outline:hover { background: var(--midnight-navy); color: var(--stark-white); }

        /* ══════════════════════════════════════════════════════════════
           §1  HERO SECTION
        ══════════════════════════════════════════════════════════════ */
        .hero-section {
            position: relative; min-height: 100svh;
            display: flex; align-items: center; justify-content: center;
            padding-top: 80px; overflow: hidden;
        }

        .hero-bg {
            position: absolute; inset: 0; z-index: 0;
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
            animation: heroScale 22s ease-in-out infinite alternate;
        }
        .hero-overlay {
            position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(160deg, rgba(10,17,40,.72) 0%, rgba(10,17,40,.48) 38%, rgba(10,17,40,.97) 100%);
        }
        html.light-mode .hero-overlay {
            background: linear-gradient(160deg, rgba(255,255,255,.7) 0%, rgba(255,255,255,.45) 38%, rgba(255,255,255,.97) 100%);
        }

        /* Animated scanlines overlay */
        .scanline-overlay {
            position: absolute; inset: 0; z-index: 2; pointer-events: none;
            background: repeating-linear-gradient(
                0deg, transparent, transparent 2px,
                rgba(0,229,255,.012) 2px, rgba(0,229,255,.012) 4px
            );
        }

        /* Grid lines */
        .hero-grid {
            position: absolute; inset: 0; z-index: 2; pointer-events: none;
            background-image:
                linear-gradient(rgba(0,229,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,229,255,.04) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(ellipse 85% 85% at 50% 40%, black 0%, transparent 100%);
        }
        html.light-mode .hero-grid {
            background-image:
                linear-gradient(rgba(10,17,40,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(10,17,40,.04) 1px, transparent 1px);
        }

        .hero-content {
            position: relative; z-index: 10;
            display: flex; flex-direction: column; align-items: center;
            max-width: 920px; width: 100%; text-align: center;
            padding: 0 clamp(16px,4vw,32px);
            animation: revealUp .9s var(--ease-expo) both;
        }

        .tech-badge {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: var(--font-mono); font-size: .64rem; font-weight: 700;
            letter-spacing: .18em; padding: 6px 14px;
            background: rgba(0,229,255,.06);
            border: 1px solid rgba(0,229,255,.22); border-radius: 100px;
            color: var(--luminous-teal); margin-bottom: 20px;
        }
        html.light-mode .tech-badge {
            background: rgba(45,106,79,.07);
            border-color: rgba(45,106,79,.2);
            color: var(--provision-green);
        }

        .brand-title {
            font-family: var(--font-display);
            font-size: clamp(3.6rem,10vw,7.8rem);
            font-weight: 900; letter-spacing: -.04em; line-height: .9;
            background: linear-gradient(135deg, #FFFFFF 25%, #8A9BB2 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            margin-bottom: 8px;
        }
        html.light-mode .brand-title {
            background: linear-gradient(135deg, var(--midnight-navy) 25%, #4A5568 100%);
            -webkit-background-clip: text; background-clip: text;
        }

        .brand-slogan {
            font-family: var(--font-mono); font-size: clamp(.75rem,1.7vw,1rem);
            color: var(--luminous-teal); letter-spacing: .35em;
            font-weight: 400; margin-bottom: 28px;
        }
        html.light-mode .brand-slogan { color: var(--provision-green); }

        .hero-desc {
            font-family: var(--font-body);
            font-size: clamp(.93rem,1.15vw,1.06rem);
            max-width: 740px; margin-bottom: 44px; padding: 0;
            color: var(--text-muted); line-height: 1.78;
        }

        /* ── COMMAND NEXUS CTA ──────────────────────────────────────── */
        .nexus-wrapper {
            position: relative; perspective: 1000px;
            display: flex; flex-direction: column; align-items: center; z-index: 100;
        }
        .nexus-trigger {
            padding: 17px 34px;
            font-family: var(--font-body); font-size: .88rem;
            font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
            display: flex; align-items: center; gap: 15px;
            background: rgba(10,17,40,.6);
            border: 1px solid var(--luminous-teal);
            color: var(--stark-white);
            box-shadow: 0 0 24px rgba(0,229,255,.12), inset 0 0 24px rgba(0,229,255,.03);
            backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-radius: 3px; cursor: pointer;
            transition: all .35s var(--ease-expo);
        }
        html.light-mode .nexus-trigger {
            background: var(--midnight-navy);
            border-color: var(--midnight-navy);
            box-shadow: 0 8px 24px rgba(10,17,40,.22);
        }
        .prompt-arrow { color: var(--luminous-teal); animation: blink 1.1s step-end infinite; }
        html.light-mode .prompt-arrow { color: var(--stark-white); }
        .nexus-trigger:hover {
            background: var(--luminous-teal); color: var(--midnight-navy);
            box-shadow: 0 0 42px rgba(0,229,255,.4); transform: translateY(-2px);
        }
        html.light-mode .nexus-trigger:hover { background: rgba(10,17,40,.8); color: var(--stark-white); }
        .nexus-trigger:hover .prompt-arrow { color: var(--midnight-navy); }
        html.light-mode .nexus-trigger:hover .prompt-arrow { color: var(--stark-white); }

        .nexus-matrix {
            position: absolute; top: calc(100% + 16px); left: 50%;
            transform: translateX(-50%) translateY(8px);
            opacity: 0; visibility: hidden;
            background: rgba(10,17,40,.88); backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px);
            border: 1px solid rgba(0,229,255,.2); border-top: 2px solid var(--luminous-teal);
            border-radius: 6px; padding: 14px;
            box-shadow: 0 28px 56px rgba(0,0,0,.7);
            transition: all .32s var(--ease-expo); z-index: 100; min-width: 380px;
        }
        html.light-mode .nexus-matrix {
            background: rgba(255,255,255,.96);
            border-color: rgba(10,17,40,.1); border-top-color: var(--midnight-navy);
            box-shadow: 0 20px 40px rgba(10,17,40,.14);
        }
        .nexus-wrapper:hover .nexus-matrix,
        .nexus-wrapper:focus-within .nexus-matrix {
            opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0);
        }

        .compact-grid { display: flex; gap: 10px; }
        .compact-card {
            position: relative; flex: 1;
            background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.06);
            padding: 12px 18px; border-radius: 4px;
            font-family: var(--font-body); font-size: .8rem; font-weight: 600;
            cursor: pointer; white-space: nowrap;
            transition: all .22s ease;
            display: flex; align-items: center; justify-content: center;
            color: var(--stark-white); text-decoration: none;
        }
        html.light-mode .compact-card { background: rgba(0,0,0,.03); border-color: rgba(0,0,0,.07); color: var(--midnight-navy); }
        .compact-card:hover { background: rgba(0,229,255,.08); border-color: var(--luminous-teal); }
        html.light-mode .compact-card:hover { background: rgba(10,17,40,.05); border-color: var(--midnight-navy); }
        .highlight-card { background: var(--luminous-teal) !important; color: var(--midnight-navy) !important; border: none !important; font-weight: 800; }
        html.light-mode .highlight-card { background: var(--midnight-navy) !important; color: var(--stark-white) !important; }
        .highlight-card:hover { background: var(--stark-white) !important; color: var(--midnight-navy) !important; }

        .matrix-tooltip {
            position: absolute; bottom: calc(100% + 12px); left: 50%;
            transform: translateX(-50%); width: 280px;
            display: flex; align-items: flex-start; gap: 12px;
            background: rgba(5,9,20,.97); backdrop-filter: blur(16px);
            border: 1px solid rgba(0,229,255,.28); border-radius: 4px;
            padding: 14px; box-shadow: 0 16px 32px rgba(0,0,0,.8);
            opacity: 0; visibility: hidden;
            transition: opacity .15s ease, visibility .15s ease;
            z-index: 1000; text-wrap: wrap; pointer-events: none;
        }
        .matrix-tooltip::after {
            content: ''; position: absolute; top: 100%; left: 50%; margin-left: -6px;
            border: 6px solid transparent; border-top-color: rgba(0,229,255,.28);
        }
        html.light-mode .matrix-tooltip { background: rgba(255,255,255,.98); border-color: rgba(10,17,40,.12); box-shadow: 0 12px 28px rgba(0,0,0,.12); }
        html.light-mode .matrix-tooltip::after { border-top-color: rgba(10,17,40,.12); }
        .highlight-tooltip { background: var(--luminous-teal) !important; border: none !important; }
        html.light-mode .highlight-tooltip { background: var(--midnight-navy) !important; }
        .highlight-tooltip::after { border-top-color: var(--luminous-teal) !important; }
        html.light-mode .highlight-tooltip::after { border-top-color: var(--midnight-navy) !important; }
        .compact-card:hover .matrix-tooltip { opacity: 1; visibility: visible; }
        .tooltip-icon {
            font-family: var(--font-mono); font-weight: 700; font-size: .78rem;
            width: 20px; height: 20px; min-width: 20px; border-radius: 50%;
            border: 1px solid var(--luminous-teal); color: var(--luminous-teal);
            display: flex; align-items: center; justify-content: center;
        }
        .highlight-tooltip .tooltip-icon { border-color: var(--midnight-navy); color: var(--midnight-navy); }
        html.light-mode .highlight-tooltip .tooltip-icon { border-color: var(--stark-white); color: var(--stark-white); }
        .tooltip-text { font-family: var(--font-mono); font-size: .72rem; line-height: 1.55; color: var(--text-muted); }
        .highlight-tooltip .tooltip-text { color: rgba(10,17,40,.88); }
        html.light-mode .highlight-tooltip .tooltip-text { color: rgba(255,255,255,.88); }

        /* Scroll indicator */
        .scroll-indicator {
            position: absolute; bottom: 32px; left: 50%; transform: translateX(-50%);
            z-index: 10; display: flex; flex-direction: column; align-items: center; gap: 8px;
            font-family: var(--font-mono); font-size: .58rem; letter-spacing: .2em;
            color: var(--text-secondary); opacity: .6;
        }
        .scroll-mouse {
            width: 22px; height: 36px;
            border: 1.5px solid rgba(255,255,255,.35); border-radius: 12px; position: relative;
        }
        html.light-mode .scroll-mouse { border-color: rgba(10,17,40,.3); }
        .scroll-mouse::before {
            content: ''; position: absolute; top: 6px; left: 50%; transform: translateX(-50%);
            width: 3px; height: 6px; background: var(--luminous-teal); border-radius: 2px;
            animation: scrollDot 2.2s ease-in-out infinite;
        }

        /* ══════════════════════════════════════════════════════════════
           §2  THREAT MATRIX
        ══════════════════════════════════════════════════════════════ */
        .threat-matrix-section { position: relative; padding: clamp(64px,8vw,120px) 0; }

        .threat-grid {
            display: grid; grid-template-columns: repeat(3,1fr);
            gap: clamp(16px,2vw,28px); margin-top: clamp(40px,5vw,60px);
        }
        .threat-card {
            position: relative;
            background: var(--card-bg);
            border: 1px solid rgba(255,51,102,.18); border-top: 3px solid var(--terminal-red);
            padding: clamp(24px,3vw,40px) clamp(18px,2.5vw,30px);
            border-radius: 6px; overflow: hidden; cursor: pointer;
            transition: all .45s var(--ease-expo); min-height: 290px;
        }
        html.light-mode .threat-card {
            background: #FFFFFF; border-color: rgba(255,51,102,.12);
            box-shadow: 0 8px 32px rgba(10,17,40,.06);
        }

        /* Glassmorphic shimmer on hover */
        .threat-card::before {
            content: ''; position: absolute; inset: 0; z-index: 0;
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
            opacity: 0; transition: opacity .45s ease;
        }
        .threat-card::after {
            content: ''; position: absolute; inset: 0; z-index: 1;
            background: rgba(10,17,40,.86); backdrop-filter: blur(4px);
            opacity: 0; transition: opacity .45s ease;
        }
        html.light-mode .threat-card::after { background: rgba(255,255,255,.88); }
        .threat-card:hover::before { opacity: 1; }
        .threat-card:hover::after  { opacity: 1; }
        .threat-card:hover {
            border-color: var(--luminous-teal); border-top-color: var(--luminous-teal);
            transform: translateY(-6px);
            box-shadow: 0 24px 48px rgba(0,0,0,.5), 0 0 0 1px rgba(0,229,255,.2);
        }
        html.light-mode .threat-card:hover {
            border-color: var(--provision-green); border-top-color: var(--provision-green);
            box-shadow: 0 20px 40px rgba(10,17,40,.14);
        }

        .threat-state, .override-state {
            position: absolute; top: clamp(22px,3vw,40px);
            left: clamp(16px,2.5vw,30px); right: clamp(16px,2.5vw,30px);
            transition: all .42s var(--ease-expo); z-index: 5;
        }
        .threat-state  { opacity: 1; transform: translateY(0); }
        .override-state{ opacity: 0; transform: translateY(22px); }
        .threat-card:hover .threat-state  { opacity: 0; transform: translateY(-22px); pointer-events: none; }
        .threat-card:hover .override-state{ opacity: 1; transform: translateY(0); }

        .status-badge {
            display: inline-block; font-family: var(--font-mono);
            font-size: .68rem; font-weight: 700; letter-spacing: .1em;
            padding: 5px 11px; border-radius: 3px; margin-bottom: 18px;
        }
        .error-badge    { background: rgba(255,51,102,.1); color: var(--terminal-red); border: 1px solid rgba(255,51,102,.28); }
        .resolved-badge { background: rgba(0,229,255,.1); color: var(--luminous-teal); border: 1px solid rgba(0,229,255,.28); }
        html.light-mode .resolved-badge { background: rgba(45,106,79,.1); color: var(--provision-green); border-color: rgba(45,106,79,.25); }
        .threat-card p { font-family: var(--font-body); font-size: .94rem; line-height: 1.7; color: var(--text-muted); }
        .override-state p { color: var(--true-platinum); font-weight: 500; }
        html.light-mode .override-state p { color: var(--midnight-navy); }

        /* ══════════════════════════════════════════════════════════════
           §3  TERMINAL
        ══════════════════════════════════════════════════════════════ */
        .terminal-section { padding: clamp(60px,7vw,100px) 0; background: #050914; }
        html.light-mode .terminal-section { background: var(--ghost-white); }
        .terminal-align { text-align: center; }

        .terminal-container {
            max-width: 900px; margin: 0 auto;
            border-radius: 8px; overflow: hidden;
            box-shadow: 0 32px 64px rgba(0,0,0,.7);
            border: 1px solid rgba(255,255,255,.08);
            background: #0A1128;
        }
        html.light-mode .terminal-container {
            box-shadow: 0 24px 52px rgba(10,17,40,.16);
            border-color: rgba(10,17,40,.1);
        }
        .terminal-header {
            background: #141E3C; padding: 12px 20px;
            display: flex; align-items: center;
            border-bottom: 1px solid rgba(255,255,255,.04); position: relative;
        }
        .terminal-dots { display: flex; gap: 7px; }
        .dot { width: 12px; height: 12px; border-radius: 50%; }
        .dot.red { background: #FF3366; } .dot.yellow { background: #FFBD2E; } .dot.green { background: #27C93F; }
        .terminal-title {
            position: absolute; left: 50%; transform: translateX(-50%);
            color: #8A9BB2; font-family: var(--font-mono); font-size: .78rem; letter-spacing: .06em;
        }
        .terminal-window {
            font-family: var(--font-mono); padding: clamp(16px,2.5vw,26px);
            font-size: .88rem; min-height: 350px; max-height: 540px;
            overflow-y: auto; overflow-x: hidden; color: #E2E8F0; line-height: 1.75;
            text-align: left;
            background: #0A1128;
            background-image: linear-gradient(rgba(18,16,16,0) 50%, rgba(0,0,0,.09) 50%);
            background-size: 100% 4px;
            white-space: normal; word-break: break-word; scroll-behavior: smooth;
        }
        .terminal-window::-webkit-scrollbar { width: 3px; }
        .terminal-window::-webkit-scrollbar-thumb { background: var(--luminous-teal); border-radius: 2px; }
        .terminal-cursor {
            display: inline-block; background: var(--luminous-teal);
            width: 8px; height: 14px; margin-left: 4px; vertical-align: middle;
            animation: blink 1s step-end infinite;
        }

        .terminal-options { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; margin-bottom: 30px; }
        .terminal-btn {
            font-family: var(--font-mono);
            background: rgba(0,229,255,.04); border: 1px solid var(--luminous-teal);
            color: var(--luminous-teal); padding: 11px 24px; cursor: pointer;
            transition: all .28s ease; font-size: .83rem; border-radius: 4px;
        }
        .terminal-btn:hover {
            background: var(--luminous-teal); color: var(--midnight-navy);
            box-shadow: 0 0 22px rgba(0,229,255,.38); transform: translateY(-2px);
        }
        html.light-mode .terminal-btn {
            background: #FFFFFF; color: var(--midnight-navy); border-color: var(--midnight-navy);
            box-shadow: 0 3px 10px rgba(0,0,0,.06);
        }
        html.light-mode .terminal-btn:hover { background: var(--midnight-navy); color: #FFFFFF; border-color: var(--midnight-navy); }

        .terminal-action-btn {
            display: inline-block; margin-top: 18px; padding: 11px 22px;
            background-color: var(--luminous-teal) !important; color: var(--midnight-navy) !important;
            font-family: var(--font-body); font-weight: 800; font-size: .78rem;
            letter-spacing: .08em; text-transform: uppercase; cursor: pointer;
            border: none; border-radius: 3px; text-decoration: none;
            transition: background .25s ease; display: inline-block;
        }
        .terminal-action-btn:hover { background-color: var(--stark-white) !important; }

        /* ══════════════════════════════════════════════════════════════
           §4  BLUEPRINT — GLASSMORPHIC DISPLAY PANEL
        ══════════════════════════════════════════════════════════════ */
        .blueprint-section { position: relative; padding: clamp(64px,8vw,120px) 0; overflow: hidden; }
        .blueprint-bg {
            position: absolute; inset: 0; z-index: 0;
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
        }
        .blueprint-overlay {
            position: absolute; inset: 0; z-index: 1; background: rgba(10,17,40,.92);
        }
        html.light-mode .blueprint-overlay { background: rgba(243,244,246,.92); }

        .blueprint-wrapper { display: flex; gap: clamp(24px,4vw,56px); align-items: stretch; max-width: 1060px; margin: 0 auto; }
        .blueprint-index { flex: 1; display: flex; flex-direction: column; gap: 10px; }
        .blueprint-tab {
            padding: clamp(18px,2.5vw,25px) clamp(18px,2.5vw,30px);
            font-family: var(--font-body); font-size: 1rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: .04em;
            color: var(--text-secondary); border-left: 3px solid transparent;
            cursor: pointer; transition: all .3s ease;
            background: rgba(255,255,255,.02); border-radius: 0 4px 4px 0;
        }
        html.light-mode .blueprint-tab { background: rgba(0,0,0,.02); }
        .blueprint-tab:hover { color: var(--stark-white); background: rgba(255,255,255,.04); }
        html.light-mode .blueprint-tab:hover { color: var(--midnight-navy); background: rgba(0,0,0,.04); }
        .blueprint-tab.active {
            border-left-color: var(--luminous-teal);
            background: rgba(0,229,255,.08); color: var(--stark-white);
        }
        html.light-mode .blueprint-tab.active {
            border-left-color: var(--provision-green);
            background: rgba(45,106,79,.07); color: var(--midnight-navy);
        }

        /* ── THE GLASS DISPLAY PANEL ────────────────────────────────── */
        .blueprint-display {
            flex: 1.5; border-radius: 10px;
            padding: clamp(28px,4vw,52px) clamp(22px,3.5vw,44px);
            display: flex; align-items: center;
            position: relative; overflow: hidden;

            /* GLASSMORPHIC CORE */
            background: rgba(10,17,40,.55);
            backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px);
            border: 1px solid rgba(0,229,255,.2);
            box-shadow: 0 24px 60px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.06);
        }
        html.light-mode .blueprint-display {
            background: rgba(255,255,255,.72);
            backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            border-color: rgba(45,106,79,.18);
            box-shadow: 0 20px 50px rgba(10,17,40,.1), inset 0 1px 0 rgba(255,255,255,.8);
        }

        /* Periodic scan line inside glass panel */
        .blueprint-display::after {
            content: ''; position: absolute; top: -100%; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(to bottom, transparent, rgba(0,229,255,.06), transparent);
            pointer-events: none; z-index: 5;
        }
        .blueprint-display.refreshing::after { animation: scanWipe .45s ease-out; }

        .blueprint-content { display: none; opacity: 0; transform: translateX(15px); width: 100%; }
        .blueprint-content.active { display: block; animation: blueprintFadeIn .5s ease forwards; }

        .blueprint-features { margin-top: 24px; }
        .blueprint-features li {
            margin-bottom: 13px; display: flex; align-items: center;
            font-family: var(--font-body); font-size: .94rem; color: var(--text-muted);
        }
        .blueprint-features li::before { content: '>>'; color: var(--luminous-teal); margin-right: 12px; font-family: var(--font-mono); font-size: .78rem; }
        html.light-mode .blueprint-features li::before { color: var(--provision-green); }

        /* ══════════════════════════════════════════════════════════════
           §5  COGNITIVE / AGENTIC AI
        ══════════════════════════════════════════════════════════════ */
        .cognitive-section { position: relative; padding: clamp(64px,8vw,120px) 0; }

        .agent-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: clamp(16px,2vw,28px); margin-top: clamp(36px,4vw,52px); }
        .agent-card {
            background: var(--card-bg);
            border: 1px solid rgba(0,229,255,.14);
            padding: clamp(24px,3vw,40px) clamp(18px,2.5vw,30px);
            border-radius: 6px; transition: all .35s var(--ease-expo);
            position: relative; overflow: hidden;
        }
        html.light-mode .agent-card { background: #FFFFFF; border-color: rgba(10,17,40,.08); box-shadow: 0 8px 28px rgba(0,0,0,.04); }

        /* Glass tint on hover using hero image */
        .agent-card::before {
            content: ''; position: absolute; inset: 0; z-index: 0;
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
            opacity: 0; transition: opacity .35s ease;
        }
        .agent-card::after {
            content: ''; position: absolute; inset: 0; z-index: 1;
            width: 4px; height: 100%; left: 0; top: 0; right: auto;
            background: var(--luminous-teal); opacity: .45; transition: all .3s ease;
        }
        html.light-mode .agent-card::after { background: var(--provision-green); }
        .agent-card:hover { transform: translateY(-6px); border-color: var(--luminous-teal); box-shadow: 0 20px 44px rgba(0,0,0,.4); }
        html.light-mode .agent-card:hover { border-color: var(--provision-green); box-shadow: 0 20px 40px rgba(10,17,40,.1); }
        .agent-card:hover::before { opacity: .06; }
        .agent-card:hover::after { opacity: 1; box-shadow: 0 0 16px var(--luminous-teal); }

        .agent-card h4 {
            position: relative; z-index: 2;
            font-family: var(--font-mono); font-size: .88rem;
            color: var(--text-primary); margin-bottom: 14px; text-transform: uppercase; letter-spacing: .06em;
        }
        html.light-mode .agent-card h4 { color: var(--midnight-navy); }
        .agent-card p { position: relative; z-index: 2; font-family: var(--font-body); font-size: .94rem; line-height: 1.68; color: var(--text-muted); }

        /* ══════════════════════════════════════════════════════════════
           §6  STRIKE TEAM
        ══════════════════════════════════════════════════════════════ */
        .strike-team-section {
            position: relative; padding: clamp(64px,8vw,120px) 0;
            background-image: linear-gradient(var(--border-color,rgba(255,255,255,.04)) 1px, transparent 1px),
                              linear-gradient(90deg, var(--border-color,rgba(255,255,255,.04)) 1px, transparent 1px);
            background-size: 52px 52px;
        }
        .strike-team-section::before {
            content: ''; position: absolute; inset: 0; z-index: 0;
            background: radial-gradient(ellipse 90% 90% at 50% 50%, transparent 0%, var(--bg-primary,#0A1128) 90%);
        }

        .dossier-grid {
            display: grid; grid-template-columns: repeat(2,1fr);
            gap: clamp(16px,2.5vw,30px); max-width: 1020px; margin: 0 auto;
            position: relative; z-index: 2;
        }
        .dossier-card {
            position: relative; background: var(--bg-secondary); border: 1px solid var(--border-color);
            border-radius: 5px; overflow: hidden;
            transition: all .45s var(--ease-expo); z-index: 1;
        }
        html.light-mode .dossier-card { background: var(--stark-white); border-color: rgba(10,17,40,.1); }
        .dossier-card::after {
            content: ''; position: absolute; top: -100%; left: 0; width: 100%; height: 5px;
            background: var(--luminous-teal); box-shadow: 0 0 16px var(--luminous-teal);
            z-index: 15; opacity: 0; transition: opacity .2s;
        }
        html.light-mode .dossier-card::after { background: var(--provision-green); box-shadow: 0 0 16px rgba(45,106,79,.6); }
        .dossier-card:hover::after { opacity: 1; animation: scanWipe 1.6s infinite linear; }
        .dossier-card::before {
            content: '{{ __("SCANNING_BIO...") }}';
            position: absolute; top: 10px; left: 12px;
            font-family: var(--font-mono); font-size: .5rem; letter-spacing: .12em;
            color: var(--luminous-teal); z-index: 12; opacity: 0;
        }
        html.light-mode .dossier-card::before { color: var(--provision-green); }
        .dossier-card:hover::before { opacity: .65; }

        .dossier-status {
            position: absolute; top: 14px; right: 14px;
            font-family: var(--font-mono); font-size: .62rem; font-weight: 700;
            letter-spacing: .14em; color: var(--luminous-teal); z-index: 10;
            background: rgba(10,17,40,.9); padding: 5px 9px; border-radius: 2px;
        }
        html.light-mode .dossier-status { background: rgba(255,255,255,.92); color: var(--provision-green); }
        .status-dot {
            display: inline-block; width: 7px; height: 7px;
            background: var(--luminous-teal); border-radius: 50%; margin-right: 5px;
            box-shadow: 0 0 8px var(--luminous-teal); animation: pulse 2s infinite;
        }
        html.light-mode .status-dot { background: var(--provision-green); box-shadow: 0 0 8px rgba(45,106,79,.5); }

        .dossier-image {
            height: 220px; background-size: cover; background-position: center;
            filter: grayscale(100%) brightness(.58); transition: all .6s ease;
        }
        .dossier-card:hover .dossier-image { filter: grayscale(0%) brightness(.38) contrast(1.25) !important; transform: scale(1.08); }

        .dossier-info { padding: clamp(18px,3vw,30px); background: var(--bg-secondary); position: relative; z-index: 2; }
        html.light-mode .dossier-info { background: var(--stark-white); }
        .dossier-info h4 {
            font-family: var(--font-display); font-size: 1.05rem;
            letter-spacing: .07em; margin-bottom: 6px;
            color: var(--text-primary); font-weight: 800; text-transform: uppercase;
        }
        html.light-mode .dossier-info h4 { color: var(--midnight-navy); }
        .dossier-role { font-family: var(--font-mono); font-size: .72rem; color: var(--luminous-teal); margin-bottom: 18px; text-transform: uppercase; letter-spacing: .06em; }
        html.light-mode .dossier-role { color: var(--provision-green); }

        .loadout { display: flex; flex-wrap: wrap; gap: 7px; }
        .loadout-item { font-family: var(--font-mono); font-size: .62rem; padding: 4px 10px; border-radius: 2px; border: 1px solid rgba(255,255,255,.09); color: var(--text-secondary); }
        html.light-mode .loadout-item { border-color: rgba(10,17,40,.1); color: var(--text-muted); }

        .dossier-card:hover { transform: translateY(-8px); border-color: var(--luminous-teal); box-shadow: 0 32px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(0,229,255,.18); }
        html.light-mode .dossier-card:hover { border-color: var(--provision-green); box-shadow: 0 28px 52px rgba(10,17,40,.14); }

        /* ══════════════════════════════════════════════════════════════
           §7  DEPLOYMENT — CINEMATIC 4-CARD SLIDER
        ══════════════════════════════════════════════════════════════ */
        .deployment-section {
            position: relative; padding: clamp(64px,8vw,100px) 0; overflow: hidden;
        }

        /* Hero image background */
        .deploy-bg {
            position: absolute; inset: 0; z-index: 0;
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center; background-attachment: fixed;
            transform: scale(1.04);
        }
        /* Dark cinematic gradient overlay */
        .deploy-overlay {
            position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(
                160deg,
                rgba(5,9,20,.97) 0%,
                rgba(10,17,40,.90) 40%,
                rgba(5,9,20,.97) 100%
            );
        }
        html.light-mode .deploy-overlay {
            background: linear-gradient(160deg, rgba(243,244,246,.96) 0%, rgba(243,244,246,.88) 40%, rgba(243,244,246,.96) 100%);
        }

        /* Gradient rule above heading */
        .deploy-gradient-rule {
            width: 2px; height: 56px;
            background: linear-gradient(to bottom, var(--luminous-teal), transparent);
            margin: 0 auto 22px;
        }
        html.light-mode .deploy-gradient-rule { background: linear-gradient(to bottom, var(--provision-green), transparent); }

        /* ── SLIDER ── */
        .deploy-slider-wrapper { overflow: hidden; position: relative; }

        .deploy-track {
            display: flex; gap: 18px;
            transition: transform .65s cubic-bezier(0.16,1,0.3,1);
            will-change: transform;
        }

        /* Glassmorphic cards — 4 visible on desktop */
        .deploy-card {
            flex: 0 0 calc(25% - 14px);
            display: flex; flex-direction: column;
            background: rgba(255,255,255,.04);
            backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px);
            border: 1px solid rgba(0,229,255,.13);
            border-top: 2px solid rgba(0,229,255,.45);
            border-radius: 8px;
            padding: clamp(20px,2.5vw,30px) clamp(16px,2vw,24px);
            position: relative; overflow: hidden;
            transition: border-color .3s ease, transform .3s ease, box-shadow .3s ease;
        }
        html.light-mode .deploy-card {
            background: rgba(255,255,255,.78);
            backdrop-filter: blur(20px);
            border-color: rgba(45,106,79,.13);
            border-top-color: rgba(45,106,79,.48);
            box-shadow: 0 8px 32px rgba(10,17,40,.07);
        }
        /* Top shimmer line */
        .deploy-card::before {
            content: ''; position: absolute; top: 0; left: 10%; right: 10%; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0,229,255,.55), transparent);
        }
        html.light-mode .deploy-card::before { background: linear-gradient(90deg, transparent, rgba(45,106,79,.45), transparent); }
        /* Subtle corner glow */
        .deploy-card::after {
            content: ''; position: absolute; top: -40px; right: -40px;
            width: 100px; height: 100px; border-radius: 50%;
            background: radial-gradient(circle, rgba(0,229,255,.07) 0%, transparent 70%);
            pointer-events: none;
        }
        .deploy-card:hover {
            border-color: rgba(0,229,255,.35); transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,.45), 0 0 0 1px rgba(0,229,255,.12);
        }
        html.light-mode .deploy-card:hover {
            border-color: rgba(45,106,79,.35);
            box-shadow: 0 16px 36px rgba(10,17,40,.12);
        }

        .deploy-system {
            font-family: var(--font-mono); font-size: .62rem; font-weight: 600;
            letter-spacing: .18em; text-transform: uppercase;
            color: var(--luminous-teal); margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0,229,255,.1);
        }
        html.light-mode .deploy-system { color: var(--provision-green); border-color: rgba(45,106,79,.14); }

        .deploy-quote {
            font-family: var(--font-body); font-size: clamp(.82rem,.95vw,.93rem);
            line-height: 1.72; font-style: italic;
            color: var(--text-muted); margin-bottom: 18px; flex: 1;
        }
        .deploy-quote::before { content: '\201C'; color: var(--luminous-teal); font-size: 1.3rem; line-height: 0; margin-right: 3px; font-family: Georgia, serif; }
        .deploy-quote::after  { content: '\201D'; color: var(--luminous-teal); font-size: 1.3rem; line-height: 0; margin-left: 3px; font-family: Georgia, serif; }
        html.light-mode .deploy-quote::before,
        html.light-mode .deploy-quote::after { color: var(--provision-green); }

        .deploy-author {
            font-family: var(--font-mono); font-size: .66rem;
            color: var(--luminous-teal); text-transform: uppercase; letter-spacing: .07em;
            margin-top: auto; padding-top: 14px;
            border-top: 1px solid rgba(0,229,255,.07);
        }
        html.light-mode .deploy-author { color: var(--provision-green); border-color: rgba(45,106,79,.1); }

        /* ── NAVIGATION ── */
        .deploy-nav {
            display: flex; align-items: center; justify-content: center;
            gap: 18px; margin-top: 34px;
        }
        .deploy-dots { display: flex; gap: 7px; align-items: center; }
        .deploy-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: rgba(0,229,255,.2); border: 1px solid rgba(0,229,255,.35);
            cursor: pointer; transition: all .35s ease; padding: 0;
        }
        .deploy-dot.active {
            width: 28px; border-radius: 4px;
            background: var(--luminous-teal);
            box-shadow: 0 0 12px rgba(0,229,255,.5); border-color: transparent;
        }
        html.light-mode .deploy-dot { background: rgba(45,106,79,.18); border-color: rgba(45,106,79,.35); }
        html.light-mode .deploy-dot.active { background: var(--provision-green); box-shadow: 0 0 12px rgba(45,106,79,.38); }

        .deploy-arrow {
            width: 38px; height: 38px; border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            background: rgba(0,229,255,.05);
            border: 1px solid rgba(0,229,255,.25);
            color: var(--luminous-teal); font-size: 1rem;
            transition: all .28s ease;
        }
        .deploy-arrow:hover {
            background: var(--luminous-teal); color: var(--midnight-navy);
            box-shadow: 0 0 22px rgba(0,229,255,.38);
        }
        html.light-mode .deploy-arrow { border-color: rgba(45,106,79,.28); color: var(--provision-green); background: rgba(45,106,79,.04); }
        html.light-mode .deploy-arrow:hover { background: var(--provision-green); color: #FFFFFF; box-shadow: none; }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .deploy-card { flex: 0 0 calc(50% - 9px); }
            .deploy-bg { background-attachment: scroll; }
        }
        @media (max-width: 560px) {
            .deploy-card { flex: 0 0 100%; }
        }

        /* ══════════════════════════════════════════════════════════════
           §8  ZENITH — BRAND PHILOSOPHY (GLASSMORPHIC)
        ══════════════════════════════════════════════════════════════ */
        .jireh-zenith {
            padding: clamp(80px,10vw,140px) 0; text-align: center;
            display: flex; flex-direction: column; align-items: center;
            position: relative; overflow: hidden;
        }
        /* Hero image background for zenith */
        .jireh-zenith::before {
            content: ''; position: absolute; inset: 0; z-index: 0;
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
            background-attachment: fixed;
        }
        .jireh-zenith::after {
            content: ''; position: absolute; inset: 0; z-index: 1;
            background: rgba(10,17,40,.9);
            background-image: linear-gradient(var(--border-color,rgba(255,255,255,.04)) 1px, transparent 1px),
                              linear-gradient(90deg, var(--border-color,rgba(255,255,255,.04)) 1px, transparent 1px);
            background-size: 52px 52px;
        }
        /* Radial fade */
        .zenith-radial {
            position: absolute; inset: 0; z-index: 2;
            background: radial-gradient(circle at 50% 50%, transparent 20%, rgba(10,17,40,.88) 100%);
        }
        html.light-mode .jireh-zenith::after { background: rgba(243,244,246,.9); }
        html.light-mode .zenith-radial { background: radial-gradient(circle at 50% 50%, transparent 20%, rgba(243,244,246,.88) 100%); }

        .zenith-line { width: 2px; height: 70px; background: linear-gradient(to bottom, var(--luminous-teal), transparent); margin: 0 auto 28px; position: relative; z-index: 3; }
        html.light-mode .zenith-line { background: linear-gradient(to bottom, var(--provision-green), transparent); }

        .zenith-quote { position: relative; z-index: 3; display: flex; flex-direction: column; gap: 10px; }
        .quote-mystical { font-family: Georgia, serif; font-style: italic; font-size: clamp(1.1rem,2.5vw,1.7rem); color: var(--text-muted); opacity: .85; }
        .quote-structured {
            font-family: var(--font-display); font-size: clamp(2.5rem,7vw,5.5rem);
            font-weight: 900; text-transform: uppercase; color: var(--text-primary);
            letter-spacing: -.04em; line-height: .92;
            transition: all .9s var(--ease-expo);
            transform: scale(.9); opacity: .2; filter: blur(6px);
        }
        .quote-structured.zenith-active { transform: scale(1); opacity: 1; filter: blur(0); text-shadow: 0 0 40px rgba(0,229,255,.14); }
        html.light-mode .quote-structured.zenith-active { text-shadow: 0 10px 24px rgba(45,106,79,.1); }

        .zenith-coord { position: absolute; font-family: var(--font-mono); font-size: .58rem; color: var(--luminous-teal); opacity: .35; z-index: 3; }
        html.light-mode .zenith-coord { color: var(--provision-green); }

        /* ══════════════════════════════════════════════════════════════
           §9  COPQ CALCULATOR — GLASSMORPHIC HERO CARD
        ══════════════════════════════════════════════════════════════ */
        .chaos-tax-section {
            position: relative; padding: clamp(64px,8vw,120px) 0;
            background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 52px 52px;
        }
        .chaos-tax-section::after {
            content: ''; position: absolute; inset: 0; z-index: 0;
            background: linear-gradient(to bottom, transparent 60%, var(--bg-primary,#0A1128) 100%);
        }
        html.light-mode .chaos-tax-section::after { background: linear-gradient(to bottom, transparent 60%, var(--ghost-white) 100%); }

        /* THE GLASSMORPHIC CALCULATOR CARD */
        .calculator-wrapper {
            position: relative; z-index: 1; border-radius: 10px; overflow: hidden;
            padding: clamp(28px,4vw,52px);

            /* Hero image behind glass */
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
        }
        /* Glass overlay */
        .calculator-wrapper::before {
            content: ''; position: absolute; inset: 0; z-index: 0;
            background: rgba(10,17,40,.88);
            backdrop-filter: blur(2px);
            border: 1px solid rgba(0,229,255,.14);
        }
        html.light-mode .calculator-wrapper::before {
            background: rgba(255,255,255,.92);
            border-color: rgba(45,106,79,.12);
        }
        /* Gradient shine on top edge */
        .calculator-wrapper::after {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0,229,255,.5), transparent);
            z-index: 2;
        }
        html.light-mode .calculator-wrapper::after { background: linear-gradient(90deg, transparent, rgba(45,106,79,.4), transparent); }

        /* All inner content must sit above the glass overlay */
        .calculator-wrapper > * { position: relative; z-index: 2; }

        .currency-selector {
            width: 100%; max-width: 320px; margin: 0 auto 40px; display: block;
            padding: 12px 40px 12px 16px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(0,229,255,.35); color: var(--luminous-teal);
            font-family: var(--font-mono); font-size: .82rem; border-radius: 4px;
            outline: none; cursor: pointer; appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2300e5ff' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center;
            transition: box-shadow .25s ease;
        }
        .currency-selector:focus { box-shadow: 0 0 20px rgba(0,229,255,.22); border-color: var(--luminous-teal); }
        .currency-selector option, .currency-selector optgroup { background: #0A1128; color: var(--stark-white); }
        html.light-mode .currency-selector {
            background-color: rgba(255,255,255,.8); color: var(--provision-green);
            border-color: rgba(45,106,79,.35);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%232D6A4F' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        }
        html.light-mode .currency-selector option { background: #FFFFFF; color: var(--midnight-navy); }

        .calc-main-grid { display: grid; grid-template-columns: 1.2fr .8fr; gap: clamp(32px,5vw,64px); }

        .input-group { position: relative; margin-bottom: clamp(28px,3.5vw,40px); }
        .label-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .label-row label { font-family: var(--font-mono); font-size: .71rem; color: var(--luminous-teal); letter-spacing: .09em; font-weight: 600; }
        html.light-mode .label-row label { color: var(--provision-green); }
        .label-row input[type="text"],
        .label-row input[type="number"] {
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.14);
            color: var(--stark-white); font-family: var(--font-mono);
            width: 128px; padding: 7px 10px; text-align: right; border-radius: 3px;
            outline: none; font-size: .82rem; transition: border-color .22s ease;
        }
        .label-row input:focus { border-color: var(--luminous-teal); }
        html.light-mode .label-row input { color: var(--midnight-navy); background: rgba(255,255,255,.8); border-color: rgba(10,17,40,.22); }
        html.light-mode .label-row input:focus { border-color: var(--provision-green); }

        input[type="range"] { -webkit-appearance: none; width: 100%; background: transparent; margin-top: 12px; }
        input[type="range"]:focus { outline: none; }
        input[type="range"]::-webkit-slider-runnable-track { height: 2px; background: rgba(255,255,255,.14); border-radius: 2px; }
        input[type="range"]::-webkit-slider-thumb { height: 14px; width: 14px; border-radius: 50%; background: var(--luminous-teal); cursor: pointer; -webkit-appearance: none; margin-top: -6px; box-shadow: 0 0 12px rgba(0,229,255,.5); transition: transform .12s ease; }
        input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.3); }
        html.light-mode input[type="range"]::-webkit-slider-runnable-track { background: rgba(10,17,40,.16); }
        html.light-mode input[type="range"]::-webkit-slider-thumb { background: var(--provision-green); box-shadow: 0 2px 8px rgba(45,106,79,.4); }

        .glass-tooltip {
            position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%;
            background: rgba(10,17,40,.88); backdrop-filter: blur(16px);
            border: 1px solid rgba(0,229,255,.2); padding: 10px 14px;
            font-family: var(--font-mono); font-size: .72rem; color: var(--text-muted);
            border-radius: 4px; z-index: 50; pointer-events: none;
            opacity: 0; transform: translateY(8px);
            transition: all .3s var(--ease-expo); box-shadow: 0 10px 28px rgba(0,0,0,.4);
        }
        html.light-mode .glass-tooltip { background: rgba(255,255,255,.92); border-color: rgba(45,106,79,.18); color: var(--midnight-navy); box-shadow: 0 10px 24px rgba(10,17,40,.1); }
        .input-group:hover .glass-tooltip,
        .input-group:focus-within .glass-tooltip { opacity: 1; transform: translateY(0); }

        .split-meter { height: 7px; display: flex; border-radius: 10px; overflow: hidden; margin-bottom: 28px; background: rgba(255,255,255,.07); }
        .meter-bar.lost  { background: var(--terminal-red); box-shadow: 0 0 14px rgba(255,51,102,.3); transition: width .65s var(--ease-expo); }
        .meter-bar.saved { background: var(--luminous-teal); transition: width .65s var(--ease-expo); }
        html.light-mode .meter-bar.saved { background: var(--provision-green); }

        .leak-counter { font-family: var(--font-mono); font-size: clamp(1.8rem,3.5vw,2.8rem); font-weight: 700; color: var(--terminal-red); letter-spacing: -.03em; margin: 14px 0; transition: all .3s ease; }
        .critical-leak { animation: gridFlicker .25s infinite; text-shadow: 0 0 16px rgba(255,51,102,.6); }

        /* ══════════════════════════════════════════════════════════════
           §10  PULSE CHECKPOINT
        ══════════════════════════════════════════════════════════════ */
        .pulse-checkpoint {
            padding: clamp(40px,6vw,72px) 0; background-color: var(--midnight-navy);
            position: relative; overflow: hidden; text-align: center;
            border-top: 1px solid rgba(0,229,255,.1); border-bottom: 1px solid rgba(0,229,255,.1);
        }
        html.light-mode .pulse-checkpoint { background-color: #F9FAFB; border-color: rgba(10,17,40,.1); }

        .scanner-lens {
            position: absolute; top: 0; left: 0; width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(0,229,255,.09) 0%, transparent 70%);
            pointer-events: none; z-index: 1; transform: translate(-50%,-50%);
            transition: opacity .3s ease; opacity: 0;
        }
        html.light-mode .scanner-lens { background: radial-gradient(circle, rgba(45,106,79,.07) 0%, transparent 70%); }
        .pulse-checkpoint:hover .scanner-lens { opacity: 1; }

        .pulse-line { position: absolute; top: 50%; left: 0; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, var(--luminous-teal), transparent); opacity: .13; animation: ping 3.2s infinite linear; pointer-events: none; }
        html.light-mode .pulse-line { background: linear-gradient(90deg, transparent, var(--provision-green), transparent); }

        .pulse-title {
            font-family: var(--font-display); font-size: clamp(1.3rem,3.5vw,2rem);
            font-weight: 900; text-transform: uppercase; letter-spacing: .04em; line-height: 1.15;
            margin-bottom: 10px; position: relative; z-index: 5; color: var(--text-primary);
        }
        .pulse-systems { color: var(--luminous-teal); text-shadow: 0 0 20px rgba(0,229,255,.4); }
        html.light-mode .pulse-systems { color: var(--provision-green); text-shadow: 0 0 20px rgba(45,106,79,.2); }
        .pulse-hope { color: transparent; -webkit-text-stroke: 1.5px var(--terminal-red); }

        .pulse-diagnostic { font-family: var(--font-mono); font-size: .75rem; color: var(--text-secondary); margin-bottom: 28px; letter-spacing: .2em; position: relative; z-index: 5; }
        html.light-mode .pulse-diagnostic { color: var(--midnight-navy); }

        .pulse-btn {
            position: relative; z-index: 5; display: inline-block;
            padding: 13px 32px; background: transparent;
            border: 1px solid var(--luminous-teal); color: var(--luminous-teal);
            box-shadow: 0 0 18px rgba(0,229,255,.16);
            font-family: var(--font-body); font-size: .8rem; font-weight: 800;
            letter-spacing: .12em; text-transform: uppercase; cursor: pointer;
            transition: all .3s ease; border-radius: 2px; text-decoration: none;
        }
        .pulse-btn:hover { background: var(--luminous-teal); color: var(--midnight-navy); box-shadow: 0 0 36px rgba(0,229,255,.5); transform: translateY(-2px); }
        html.light-mode .pulse-btn { border-color: var(--midnight-navy); color: var(--midnight-navy); box-shadow: none; }
        html.light-mode .pulse-btn:hover { background: var(--midnight-navy); color: var(--stark-white); }

        /* ══════════════════════════════════════════════════════════════
           §11  COPQ MODAL — GLASSMORPHIC DRAWER
        ══════════════════════════════════════════════════════════════ */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(5,8,20,.45); backdrop-filter: blur(22px);
            z-index: 2000; justify-content: flex-end;
            opacity: 0; transition: opacity .3s ease;
        }
        html.light-mode .modal-overlay { background: rgba(255,255,255,.55); }
        .modal-overlay.active { opacity: 1; display: flex; }

        /* Hero image + glass on the drawer */
        .modal-drawer {
            width: 550px; max-width: 100vw; height: 100%;
            padding: clamp(22px,4vw,44px);
            border-left: 1px solid rgba(0,229,255,.16);
            overflow-y: auto;
            transform: translateX(100%); transition: transform .45s var(--ease-expo);
            display: flex; flex-direction: column; position: relative;

            /* Hero image + glass overlay */
            background-image: url('{{ asset("assets/img/hero_home.webp") }}');
            background-size: cover; background-position: center;
        }
        /* Glass overlay layer */
        .modal-drawer::before {
            content: ''; position: absolute; inset: 0; z-index: 0;
            background: rgba(10,17,40,.91);
            backdrop-filter: blur(2px);
        }
        html.light-mode .modal-drawer::before { background: rgba(255,255,255,.94); }
        .modal-drawer > * { position: relative; z-index: 2; }

        /* Periodic scan line */
        .modal-drawer::after {
            content: ''; position: absolute; top: -5%; left: 0;
            width: 100%; height: 3px;
            background: var(--luminous-teal); box-shadow: 0 0 16px var(--luminous-teal);
            z-index: 100; opacity: 0; pointer-events: none;
            animation: periodicScan 14s infinite linear;
        }
        html.light-mode .modal-drawer::after { background: var(--provision-green); box-shadow: 0 0 16px rgba(45,106,79,.5); }
        .modal-overlay.active .modal-drawer { transform: translateX(0); }

        .system-header-label { font-family: var(--font-mono); font-size: .62rem; color: var(--luminous-teal); letter-spacing: .18em; margin-bottom: 4px; }
        html.light-mode .system-header-label { color: var(--provision-green); }

        .booking-form input,
        .booking-form select {
            width: 100%; padding: 13px 17px; margin-bottom: 14px;
            background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12);
            color: var(--stark-white); border-radius: 4px; outline: none;
            font-family: var(--font-body); font-size: .9rem;
            transition: border-color .25s ease;
        }
        html.light-mode .booking-form input,
        html.light-mode .booking-form select { background: rgba(255,255,255,.85); border-color: rgba(10,17,40,.18); color: var(--midnight-navy); }
        .booking-form input:focus, .booking-form select:focus { border-color: var(--luminous-teal); }
        html.light-mode .booking-form input:focus, html.light-mode .booking-form select:focus { border-color: var(--provision-green); }
        .booking-form select option { background: #0A1128; color: var(--stark-white); }
        html.light-mode .booking-form select option { background: #FFFFFF; color: var(--midnight-navy); }

        .success-checkmark {
            width: 80px; height: 80px; background: var(--luminous-teal); color: var(--midnight-navy);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; font-weight: 800; margin: 0 auto 28px;
            box-shadow: 0 0 32px rgba(0,229,255,.35);
        }
        html.light-mode .success-checkmark { background: var(--provision-green); box-shadow: 0 0 24px rgba(45,106,79,.35); }

        #copqFlow, #copqSuccessFlow { transition: opacity .4s ease, transform .4s var(--ease-expo); }
        .executing-btn { background-color: var(--stark-white) !important; color: var(--midnight-navy) !important; box-shadow: 0 0 22px rgba(255,255,255,.4); pointer-events: none; }
        html.light-mode .executing-btn { background-color: var(--midnight-navy) !important; color: var(--stark-white) !important; }

        /* ══════════════════════════════════════════════════════════════
           §12  TACTICAL SCRATCHPAD
        ══════════════════════════════════════════════════════════════ */
        .tactical-calc-widget {
            margin-top: 28px; background: rgba(0,0,0,.4);
            border: 1px solid rgba(255,255,255,.06);
            border-left: 2px solid var(--luminous-teal);
            padding: 14px; border-radius: 4px;
        }
        html.light-mode .tactical-calc-widget { background: rgba(255,255,255,.7); border-color: rgba(45,106,79,.12); border-left-color: var(--provision-green); }

        .calc-display {
            width: 100%; background: #050914; border: 1px solid rgba(0,229,255,.22);
            color: var(--luminous-teal); font-family: var(--font-mono);
            font-size: 1.05rem; text-align: right; padding: 9px 12px;
            border-radius: 3px; margin-bottom: 10px; outline: none;
        }
        html.light-mode .calc-display { background: #FFFFFF; color: var(--provision-green); border-color: rgba(45,106,79,.25); }
        .calc-display:focus { box-shadow: 0 0 12px rgba(0,229,255,.2); }

        .calc-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 5px; margin-bottom: 10px; }
        .calc-btn { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); color: var(--stark-white); font-family: var(--font-mono); font-size: .82rem; padding: 7px 0; border-radius: 2px; cursor: pointer; transition: all .18s ease; }
        .calc-btn:hover { background: rgba(0,229,255,.1); border-color: var(--luminous-teal); color: var(--luminous-teal); }
        html.light-mode .calc-btn { background: #FFFFFF; border-color: rgba(10,17,40,.1); color: var(--midnight-navy); }
        html.light-mode .calc-btn:hover { background: var(--midnight-navy); color: #FFFFFF; border-color: var(--midnight-navy); }
        .calc-btn.op-btn { background: rgba(0,229,255,.04); color: var(--luminous-teal); border-color: rgba(0,229,255,.16); }
        html.light-mode .calc-btn.op-btn { background: rgba(45,106,79,.04); color: var(--provision-green); border-color: rgba(45,106,79,.18); }
        .calc-btn.eval-btn { grid-column: span 4; background: var(--luminous-teal); color: var(--midnight-navy); font-weight: 800; }
        html.light-mode .calc-btn.eval-btn { background: var(--midnight-navy); color: #FFFFFF; }

        .inject-controls { display: flex; gap: 7px; align-items: center; }
        .inject-select { flex: 1; background: rgba(10,17,40,.9); border: 1px solid rgba(0,229,255,.28); color: #FFFFFF; font-family: var(--font-mono); font-size: .72rem; padding: 6px; outline: none; border-radius: 3px; }
        html.light-mode .inject-select { background: #FFFFFF; color: var(--midnight-navy); border-color: rgba(45,106,79,.2); }
        .inject-select option { background: #0A1128; color: #FFFFFF; }
        html.light-mode .inject-select option { background: #FFFFFF; color: var(--midnight-navy); }
        .inject-btn { background: var(--luminous-teal); color: var(--midnight-navy); font-family: var(--font-mono); font-size: .72rem; font-weight: 800; padding: 6px 14px; border-radius: 2px; cursor: pointer; transition: .2s; border: none; white-space: nowrap; }
        .inject-btn:hover { background: var(--stark-white); }
        html.light-mode .inject-btn { background: var(--provision-green); color: #FFFFFF; }
        html.light-mode .inject-btn:hover { background: var(--midnight-navy); }

        /* ══════════════════════════════════════════════════════════════
           MOBILE RESPONSIVE — MOBILE FIRST
        ══════════════════════════════════════════════════════════════ */
        @media (max-width: 850px) {
            .container { padding-left: 20px !important; padding-right: 20px !important; }

            .section-header       { text-align: left !important; }
            .section-header p     { margin-left: 0 !important; margin-right: 0 !important; text-align: left !important; }
            .terminal-teal-text,
            .terminal-red-text,
            .blueprint-subtitle   { justify-content: flex-start !important; }

            .hero-content, .jireh-zenith, .pulse-checkpoint { text-align: center !important; }

            .nexus-matrix {
                position: relative; top: 0; left: 0; transform: none;
                width: 100%; min-width: unset;
                background: transparent; border: none; box-shadow: none;
                padding: 12px 0 0; display: none;
            }
            .nexus-wrapper:hover .nexus-matrix,
            .nexus-wrapper:focus-within .nexus-matrix { display: block; animation: fadeDown .3s ease forwards; }
            .compact-grid { flex-direction: column; }
            .compact-card { white-space: normal; text-align: center; padding: 14px; }
            .matrix-tooltip { display: none !important; }

            .threat-grid, .agent-grid, .dossier-grid { grid-template-columns: 1fr; gap: 18px; }
            .threat-card { min-height: 240px; }

            .blueprint-wrapper { flex-direction: column; gap: 16px; }
            .blueprint-index { flex-direction: row; flex-wrap: wrap; gap: 6px; }
            .blueprint-tab { flex: 1 1 44%; padding: 14px 12px; font-size: .85rem; border-left: none; border-bottom: 3px solid transparent; border-radius: 4px 4px 0 0; text-align: center; }
            .blueprint-tab.active { border-left-color: transparent; border-bottom-color: var(--luminous-teal); }
            html.light-mode .blueprint-tab.active { border-bottom-color: var(--provision-green); }
            .blueprint-display { padding: 28px 18px; }

            .calc-main-grid { grid-template-columns: 1fr; gap: 40px; }
            .calculator-wrapper { padding: 28px 18px; }
            .label-row input { width: 100px; }
            .leak-counter { font-size: 2.2rem; }
            .glass-tooltip { position: relative; bottom: auto; margin-top: 10px; opacity: 1; transform: none; }
            .tactical-calc-widget { display: none !important; }

            .terminal-options { justify-content: flex-start; }
            .terminal-title { position: static; transform: none; margin-left: auto; font-size: .7rem; }
            .terminal-window { padding: 14px; font-size: .8rem; min-height: 280px; }

            .jireh-zenith::before { background-attachment: scroll; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
            [data-reveal] { opacity: 1; transform: none; }
        }
    </style>
    @endpush

    {{-- ════════════════════════════════════════════════════
         §1  HERO SECTION
    ════════════════════════════════════════════════════ --}}
    <section class="hero-section" aria-label="{{ __('JBS Praxis Hero') }}">
        <div class="hero-bg" role="img" aria-hidden="true"></div>
        <div class="hero-overlay" aria-hidden="true"></div>
        <div class="scanline-overlay" aria-hidden="true"></div>
        <div class="hero-grid" aria-hidden="true"></div>

        <div class="container hero-content text-center">
            <div class="tech-badge mono-font teal-text" aria-hidden="true">
                <span class="pulse-dot"></span> {{ __('// SYS.ARCH : ONLINE') }}
            </div>

            <h1 class="brand-title">{{ __('JBS PRAXIS') }}</h1>
            <h2 class="brand-slogan mono-font">{{ __('BEYOND STRATEGY') }}</h2>

            <p class="hero-desc">{{ __("Most enterprises run on a fragile architecture of spreadsheets, a dozen disjointed SaaS subscriptions, and sheer human effort. You don't need another app; you need a unified nervous system. JBS Praxis engineers the digital infrastructure that replaces manual chaos—deploying custom enterprise systems, hardcoded governance, and autonomous AI agents that make your operations run, scale, and self-correct without you holding them together.") }}</p>

            <div class="nexus-wrapper">
                <button class="btn nexus-trigger" aria-expanded="false" aria-haspopup="menu">
                    {{ __('INITIATE TRANSFORMATION') }} <span class="mono-font prompt-arrow" aria-hidden="true">&gt;_</span>
                </button>

                <div class="nexus-matrix" role="menu">
                    <div class="compact-grid">
                        <a href="/contact#diagnostic" wire:navigate class="compact-card" role="menuitem" style="text-decoration:none;">
                            {{ __('Diagnostic Audit') }}
                            <div class="matrix-tooltip" aria-hidden="true">
                                <div class="tooltip-icon">i</div>
                                <div class="tooltip-text">{{ __("We map your operational vulnerabilities, compliance gaps, and capital leakage — then show you exactly what it's costing you and how to close it.") }}</div>
                            </div>
                        </a>

                        <a href="/services" wire:navigate class="compact-card" role="menuitem" style="text-decoration:none;">
                            {{ __('Predictive Praxis Framework') }}
                            <div class="matrix-tooltip" aria-hidden="true">
                                <div class="tooltip-icon">i</div>
                                <div class="tooltip-text">{{ __('Our end-to-end delivery model: from governance audit and data architecture to custom systems, AI deployment, and live infrastructure management.') }}</div>
                            </div>
                        </a>

                        <a href="/schedule" wire:navigate class="compact-card highlight-card" role="menuitem" style="text-decoration:none;">
                            {{ __('Systems Consultation') }}
                            <div class="matrix-tooltip highlight-tooltip" aria-hidden="true">
                                <div class="tooltip-icon" style="border-color:var(--midnight-navy);color:var(--midnight-navy);">i</div>
                                <div class="tooltip-text">{{ __('A direct architectural review with our engineering team. We scope your database requirements, SaaS integration gaps, and AI readiness in a single working session.') }}</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="scroll-indicator" aria-hidden="true">
            <div class="scroll-mouse"></div>
            <span class="mono-font">SCROLL</span>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §2  THREAT MATRIX
    ════════════════════════════════════════════════════ --}}
    <section class="threat-matrix-section container" aria-labelledby="threatHeading">
        <div class="section-header text-center" data-reveal>
            <h4 class="terminal-red-text mono-font">
                <span class="pulse-dot-red"></span> <strong>{{ __('// SYSTEMIC FAILURES DETECTED') }}</strong>
            </h4>
            <h2 id="threatHeading">{{ __('The Strategy Graveyard is Full.') }}</h2>
            <p>{{ __('Traditional consultants deliver PDFs. Your operations keep bleeding. The problem was never insight—it was the absence of execution. JBS Praxis does not write theoretical recommendations. We engineer the system where the right outcome is the only possible one.') }}</p>
        </div>

        <div class="threat-grid" role="list">
            <article class="threat-card" data-reveal data-delay="1" role="listitem">
                <div class="threat-state">
                    <div class="status-badge error-badge mono-font">{{ __('ERR_01: THE EXECUTION GAP') }}</div>
                    <p>{{ __('Consulting reports pile up without digital execution. Operations bleed capital while waiting for manual compliance to theoretical advice.') }}</p>
                </div>
                <div class="override-state" aria-hidden="true">
                    <div class="status-badge resolved-badge mono-font">{{ __('SYS_01: PRAXIS OVERRIDE') }}</div>
                    <p>{{ __("We build the operating system your consultants never delivered. SOPs and approval chains become automated workflows. Workflows become code. Compliance stops being a daily struggle and becomes a structural consequence of how your business runs.") }}</p>
                </div>
            </article>

            <article class="threat-card" data-reveal data-delay="2" role="listitem">
                <div class="threat-state">
                    <div class="status-badge error-badge mono-font">{{ __('ERR_02: THE SAAS TAX') }}</div>
                    <p>{{ __("Your data is trapped between Microsoft Dynamics, QuickBooks, Mailchimp, and Trello. Millions are lost in the manual gap where your platforms refuse to speak to each other.") }}</p>
                </div>
                <div class="override-state" aria-hidden="true">
                    <div class="status-badge resolved-badge mono-font">{{ __('SYS_02: PRAXIS OVERRIDE') }}</div>
                    <p>{{ __('We dismantle SaaS sprawl. We consolidate your disjointed applications into a bespoke, unified ERP where data flows seamlessly. Every approval, expenditure, and exception is tracked, timestamped, and enforced in real time.') }}</p>
                </div>
            </article>

            <article class="threat-card" data-reveal data-delay="3" role="listitem">
                <div class="threat-state">
                    <div class="status-badge error-badge mono-font">{{ __('ERR_03: PAPER-BASED COMPLIANCE') }}</div>
                    <p>{{ __('Corporate policies exist on paper but not in digital workflows, leaving the enterprise exposed to regulatory failure and unseen internal fraud.') }}</p>
                </div>
                <div class="override-state" aria-hidden="true">
                    <div class="status-badge resolved-badge mono-font">{{ __('SYS_03: PRAXIS OVERRIDE') }}</div>
                    <p>{{ __('Your ISO and ESG obligations move out of policy documents and into your operational systems. By the time auditors arrive, the trail is already built, the math is already proven, and compliance is an undeniable digital fact.') }}</p>
                </div>
            </article>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §3  TERMINAL
    ════════════════════════════════════════════════════ --}}
    <section class="terminal-section" aria-labelledby="terminalHeading">
        <div class="container">
            <div class="section-header terminal-align" data-reveal>
                <h4 class="terminal-teal-text mono-font">
                    <span class="pulse-dot"></span> <strong>{{ __('// STATUS: DIAGNOSTIC_MODE') }}</strong>
                </h4>
                <h2 id="terminalHeading">{{ __('System Architecture Scan') }}</h2>
                <p style="max-width:600px;margin:0 auto;">{{ __('Identify structural vulnerabilities and architectural bottlenecks in real-time.') }}</p>
            </div>

            <div class="terminal-align" style="margin-bottom:30px;" data-reveal data-delay="1">
                <p class="mono-font teal-text" style="font-size:.83rem;margin-bottom:15px;">{{ __('> SELECT A VULNERABILITY TO INITIATE SCAN:') }}</p>
                <div class="terminal-options" role="group" aria-label="{{ __('Diagnostic scan options') }}">
                    <button type="button" class="terminal-btn" onclick="runTerminalScript('fragmented')">{{ __('[ Fragmented_Data ]') }}</button>
                    <button type="button" class="terminal-btn" onclick="runTerminalScript('compliance')">{{ __('[ Compliance_Risk ]') }}</button>
                    <button type="button" class="terminal-btn" onclick="runTerminalScript('capital')">{{ __('[ Capital_Leakage ]') }}</button>
                    <button type="button" class="terminal-btn" onclick="runTerminalScript('software')">{{ __('[ Outdated_Software ]') }}</button>
                </div>
            </div>

            <div class="terminal-container" data-reveal data-delay="2">
                <div class="terminal-header">
                    <div class="terminal-dots" aria-hidden="true">
                        <span class="dot red"></span>
                        <span class="dot yellow"></span>
                        <span class="dot green"></span>
                    </div>
                    <div class="terminal-title mono-font">root@jbs-praxis:~</div>
                </div>
                <div class="terminal-window" id="terminalOutput" role="log" aria-live="polite" aria-label="{{ __('Diagnostic terminal output') }}" wire:ignore>
                    <div id="terminalLines">
                        {{ __('> SYSTEM BOOT... OK.') }}<br>
                        {{ __('> SECURE CONNECTION ESTABLISHED.') }}<br>
                        {{ __('> AWAITING DIAGNOSTIC PROTOCOL...') }}<br><br>
                    </div>
                    <span class="terminal-cursor" aria-hidden="true"></span>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §4  BLUEPRINT — GLASSMORPHIC FRAMEWORK
    ════════════════════════════════════════════════════ --}}
    <section class="blueprint-section" aria-labelledby="blueprintHeading">
        <div class="blueprint-bg" role="img" aria-hidden="true"></div>
        <div class="blueprint-overlay" aria-hidden="true"></div>

        <div class="container relative-z">
            <div class="section-header text-center" data-reveal>
                <h4 class="blueprint-subtitle mono-font">
                    <span class="pulse-dot"></span> <strong>{{ __('// SYSTEM.BLUEPRINT : ACTIVE') }}</strong>
                </h4>
                <h2 id="blueprintHeading">{{ __('The Predictive Praxis Framework™') }}</h2>
                <p style="max-width:700px;margin:14px auto 0;">{{ __('Four integrated disciplines. One unified delivery. From governance audit to autonomous AI — this is how we rebuild enterprises from the inside out.') }}</p>
            </div>

            <div class="blueprint-wrapper">
                <nav class="blueprint-index" role="tablist" aria-label="{{ __('Framework pillars') }}">
                    <button class="blueprint-tab active" role="tab" aria-selected="true" aria-controls="content-1" id="tab-1" onmouseenter="switchBlueprint(1)">
                        <span class="mono-font teal-text">I.</span> {{ __('Governance & Control') }}
                    </button>
                    <button class="blueprint-tab" role="tab" aria-selected="false" aria-controls="content-2" id="tab-2" onmouseenter="switchBlueprint(2)">
                        <span class="mono-font teal-text">II.</span> {{ __('Data Architecture') }}
                    </button>
                    <button class="blueprint-tab" role="tab" aria-selected="false" aria-controls="content-3" id="tab-3" onmouseenter="switchBlueprint(3)">
                        <span class="mono-font teal-text">III.</span> {{ __('Custom Systems') }}
                    </button>
                    <button class="blueprint-tab" role="tab" aria-selected="false" aria-controls="content-4" id="tab-4" onmouseenter="switchBlueprint(4)">
                        <span class="mono-font teal-text">IV.</span> {{ __('Resilience') }}
                    </button>
                </nav>

                <div class="blueprint-display" role="presentation">
                    <div class="blueprint-content active" id="content-1" role="tabpanel" aria-labelledby="tab-1">
                        <h3 class="mono-font">{{ __('Governance & Control') }}</h3>
                        <p>{{ __("We map how decisions actually get made in your organisation—the bottlenecks, the informal approvals, the mountains of paperwork—and rebuild it as a digital system. Nothing moves without a footprint. Nothing is approved outside the rules.") }}</p>
                        <ul class="blueprint-features">
                            <li>{{ __('Process Re-engineering & SOP Digitalisation') }}</li>
                            <li>{{ __('Zero-Trust Approval Workflows') }}</li>
                            <li>{{ __('ISO/ESG Policy Hardcoding') }}</li>
                        </ul>
                        <a href="/services" wire:navigate class="btn-outline" style="margin-top:20px;">{{ __('EXPLORE SERVICES') }}</a>
                    </div>

                    <div class="blueprint-content" id="content-2" role="tabpanel" aria-labelledby="tab-2">
                        <h3 class="mono-font">{{ __('Data Architecture & Agent Readiness') }}</h3>
                        <p>{{ __("Your data is currently scattered across six different platforms, telling you nothing in real time. We consolidate it into a single, high-performance relational database—giving your leadership a live source of truth and building the clean foundation required to deploy AI agents deterministically.") }}</p>
                        <ul class="blueprint-features">
                            <li>{{ __('Relational Database Architecture & Migration') }}</li>
                            <li>{{ __('Automated ETL Pipelines & BI Dashboards') }}</li>
                            <li>{{ __('AI-Ready Data Infrastructure') }}</li>
                        </ul>
                        <a href="/services" wire:navigate class="btn-outline" style="margin-top:20px;">{{ __('EXPLORE SERVICES') }}</a>
                    </div>

                    <div class="blueprint-content" id="content-3" role="tabpanel" aria-labelledby="tab-3">
                        <h3 class="mono-font">{{ __('Custom Enterprise Systems') }}</h3>
                        <p>{{ __("Off-the-shelf software is built for everyone, which means it's optimised for no one. We engineer enterprise-grade ERPs mapped precisely to your approval matrices, reporting lines, and operational culture. The power of a global system, built for how you actually work.") }}</p>
                        <ul class="blueprint-features">
                            <li>{{ __('Full-Stack Web & Mobile Application Engineering') }}</li>
                            <li>{{ __('RESTful API Design & System Integration') }}</li>
                            <li>{{ __('Legacy System Migration & ERP Architecture') }}</li>
                        </ul>
                        <a href="/services" wire:navigate class="btn-outline" style="margin-top:20px;">{{ __('EXPLORE SERVICES') }}</a>
                    </div>

                    <div class="blueprint-content" id="content-4" role="tabpanel" aria-labelledby="tab-4">
                        <h3 class="mono-font">{{ __('Infrastructure & Continuous Resilience') }}</h3>
                        <p>{{ __("We don't hand over a system and disappear. We embed in your architecture—managing cloud infrastructure, hardening against threats, and scaling alongside your growth. Your uptime and operational continuity are our standing obligation.") }}</p>
                        <ul class="blueprint-features">
                            <li>{{ __('Cloud Infrastructure & DevOps Engineering') }}</li>
                            <li>{{ __('Cybersecurity Audits & System Hardening') }}</li>
                            <li>{{ __('Continuous Monitoring & Code Optimisation') }}</li>
                        </ul>
                        <a href="/services" wire:navigate class="btn-outline" style="margin-top:20px;">{{ __('EXPLORE SERVICES') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §5  COGNITIVE / AGENTIC AI
    ════════════════════════════════════════════════════ --}}
    <section class="cognitive-section" aria-labelledby="cognitiveHeading">
        <div class="container relative-z">
            <div class="section-header terminal-align" data-reveal>
                <h4 class="terminal-teal-text mono-font">
                    <span class="pulse-dot"></span> <strong>{{ __('// COGNITIVE_LAYER : ACTIVE') }}</strong>
                </h4>
                <h2 id="cognitiveHeading">{{ __('Infrastructure That Thinks.') }}</h2>
                <p style="max-width:750px;margin:0 auto;text-align:center;">{{ __("Off-the-shelf software records what happened yesterday. JBS Praxis builds autonomous agentic systems that execute, optimize, and forecast your operations in real time—eliminating knowledge bottlenecks and turning institutional memory into instant output.") }}</p>
            </div>

            <div class="agent-grid" role="list">
                <article class="agent-card" data-reveal data-delay="1" role="listitem">
                    <h4>{{ __('Commercial Execution Agents') }}</h4>
                    <p>{{ __("Your senior people shouldn't spend days writing proposals. Our agents ingest raw client briefs and meeting recordings, cross-reference them against your historical project data, and autonomously generate benchmarked, institutional-grade commercial tenders in minutes.") }}</p>
                </article>

                <article class="agent-card" data-reveal data-delay="2" role="listitem">
                    <h4>{{ __('Project Engineering Agents') }}</h4>
                    <p>{{ __("A signed contract is useless until it's an execution plan. We deploy Autonomous Scrum Agents that instantly translate complex scopes of work into database-backed, 14-day execution sprints—eliminating the mobilization delay and staging the battlespace on day one.") }}</p>
                </article>

                <article class="agent-card" data-reveal data-delay="3" role="listitem">
                    <h4>{{ __('Predictive Operations & Revenue Assurance') }}</h4>
                    <p>{{ __("We engineer simulation models that stress-test your business against real-world volatility. Meanwhile, active financial agents live inside your transaction architecture, reconciling invoices and pausing anomalous outflows before they impact your cash flow.") }}</p>
                </article>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §6  STRIKE TEAM
    ════════════════════════════════════════════════════ --}}
    <section class="strike-team-section" aria-labelledby="strikeHeading">
        <div class="container relative-z">
            <div class="section-header terminal-align" data-reveal>
                <h4 class="terminal-teal-text mono-font">
                    <span class="pulse-dot"></span> <strong>{{ __('// UNIT DEPLOYMENT : ACTIVE') }}</strong>
                </h4>
                <h2 id="strikeHeading">{{ __('The Praxis Strike Team') }}</h2>
                <p style="max-width:750px;margin:0 auto;margin-top:15px;">{{ __('We deploy as a unified tactical unit across four specialist disciplines. Every engagement is covered end to end, with no gap between strategy, engineering, and operational continuity.') }}</p>
            </div>

            <div class="dossier-grid">
                <article class="dossier-card" data-reveal data-delay="1">
                    <div class="dossier-status mono-font"><span class="status-dot" aria-hidden="true"></span> {{ __('UNIT_01: ONLINE') }}</div>
                    <div class="dossier-image" style="background-image:url('https://images.unsplash.com/photo-1552664730-d307ca884978?q=80&w=800&auto=format&fit=crop');" role="img" aria-label="{{ __('Governance and Control team') }}"></div>
                    <div class="dossier-info">
                        <h4 class="mono-font">{{ __('GOVERNANCE & CONTROL') }}</h4>
                        <p class="dossier-role">{{ __('Process Design & Internal Controls') }}</p>
                        <div class="loadout">
                            <span class="loadout-item">{{ __('Process Re-engineering') }}</span>
                            <span class="loadout-item">{{ __('Risk & Compliance') }}</span>
                            <span class="loadout-item">{{ __('ICS Design') }}</span>
                            <span class="loadout-item">{{ __('Change Management') }}</span>
                        </div>
                    </div>
                </article>

                <article class="dossier-card" data-reveal data-delay="2">
                    <div class="dossier-status mono-font"><span class="status-dot" aria-hidden="true"></span> {{ __('UNIT_02: ONLINE') }}</div>
                    <div class="dossier-image" style="background-image:url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=800&auto=format&fit=crop');" role="img" aria-label="{{ __('Data and Intelligence team') }}"></div>
                    <div class="dossier-info">
                        <h4 class="mono-font">{{ __('DATA & INTELLIGENCE') }}</h4>
                        <p class="dossier-role">{{ __('Architecture, Pipelines & AI Readiness') }}</p>
                        <div class="loadout">
                            <span class="loadout-item">{{ __('Database Architecture') }}</span>
                            <span class="loadout-item">{{ __('ETL Engineering') }}</span>
                            <span class="loadout-item">{{ __('BI & Analytics') }}</span>
                            <span class="loadout-item">{{ __('Agent Infrastructure') }}</span>
                        </div>
                    </div>
                </article>

                <article class="dossier-card" data-reveal data-delay="3">
                    <div class="dossier-status mono-font"><span class="status-dot" aria-hidden="true"></span> {{ __('UNIT_03: ONLINE') }}</div>
                    <div class="dossier-image" style="background-image:url('https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=800&auto=format&fit=crop');" role="img" aria-label="{{ __('Systems Engineering team') }}"></div>
                    <div class="dossier-info">
                        <h4 class="mono-font">{{ __('SYSTEMS ENGINEERING') }}</h4>
                        <p class="dossier-role">{{ __('Full-Stack & Enterprise Application Development') }}</p>
                        <div class="loadout">
                            <span class="loadout-item">{{ __('Web & Mobile Platforms') }}</span>
                            <span class="loadout-item">{{ __('API Design & Integration') }}</span>
                            <span class="loadout-item">{{ __('ERP Architecture') }}</span>
                            <span class="loadout-item">{{ __('Legacy Migration') }}</span>
                        </div>
                    </div>
                </article>

                <article class="dossier-card" data-reveal data-delay="4">
                    <div class="dossier-status mono-font"><span class="status-dot" aria-hidden="true"></span> {{ __('UNIT_04: ONLINE') }}</div>
                    <div class="dossier-image" style="background-image:url('https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=800&auto=format&fit=crop');" role="img" aria-label="{{ __('DevOps and Agentic AI team') }}"></div>
                    <div class="dossier-info">
                        <h4 class="mono-font">{{ __('DEVOPS & AGENTIC AI') }}</h4>
                        <p class="dossier-role">{{ __('Infrastructure, Automation & Cognitive Systems') }}</p>
                        <div class="loadout">
                            <span class="loadout-item">{{ __('Cloud & CI/CD') }}</span>
                            <span class="loadout-item">{{ __('System Monitoring') }}</span>
                            <span class="loadout-item">{{ __('Agentic Workflow Design') }}</span>
                            <span class="loadout-item">{{ __('Predictive Modelling') }}</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §7  DEPLOYMENT — CINEMATIC 4-CARD SLIDER
    ════════════════════════════════════════════════════ --}}
    <section class="deployment-section" aria-labelledby="deployHeading">
        <div class="deploy-bg" role="img" aria-hidden="true"></div>
        <div class="deploy-overlay" aria-hidden="true"></div>

        <div class="container relative-z">
            <header class="section-header text-center" data-reveal>
                <div class="deploy-gradient-rule" aria-hidden="true"></div>
                <h4 class="terminal-teal-text mono-font" style="justify-content:center;">
                    <span class="pulse-dot"></span> <strong>{{ __('// CLIENT FEEDBACK') }}</strong>
                </h4>
                <h2 id="deployHeading">{{ __('The Architecture in Production.') }}</h2>
                <p style="max-width:660px;margin:0 auto;">{{ __('We do not build speculative software. We engineer operational infrastructure for complex enterprises. Here is what happens when those systems go live.') }}</p>
            </header>

            <div class="deploy-slider-wrapper" data-reveal data-delay="1" wire:ignore>
                <div class="deploy-track" id="deployTrack">

                    <article class="deploy-card">
                        <div class="deploy-system">{{ __('// THE LOGISTICS ENGINE') }}</div>
                        <blockquote class="deploy-quote">{{ __("Most consultants hand you a PDF. JBS Praxis engineered a custom ERP and predictive algorithmic engine that actively prevents operational errors and optimizes our entire dispatch network in real time. Absolute digital governance.") }}</blockquote>
                        <cite class="deploy-author">{{ __('— COO, Pan-African Logistics Network') }}</cite>
                    </article>

                    <article class="deploy-card">
                        <div class="deploy-system">{{ __('// THE REAL ESTATE SPINE') }}</div>
                        <blockquote class="deploy-quote">{{ __("They didn't just give us a CRM; they replaced our fragmented WhatsApp chaos with a true operating system. Every transaction, contract, and commission is now locked into an institutional, auditable ledger.") }}</blockquote>
                        <cite class="deploy-author">{{ __('— Managing Partner, Tier-1 Real Estate Brokerage') }}</cite>
                    </article>

                    <article class="deploy-card">
                        <div class="deploy-system">{{ __('// THE PHARMACEUTICAL SFA') }}</div>
                        <blockquote class="deploy-quote">{{ __("Our field operations were running in the dark. JBS Praxis deployed a mobile-first field architecture with offline capabilities, GPS-verified tracking, and AI-enforced pricing thresholds that completely sealed our revenue leaks.") }}</blockquote>
                        <cite class="deploy-author">{{ __('— Commercial Director, Continental Pharma Manufacturer') }}</cite>
                    </article>

                    <article class="deploy-card">
                        <div class="deploy-system">{{ __('// THE RETAIL & LUXURY ECOSYSTEM') }}</div>
                        <blockquote class="deploy-quote">{{ __("We were drowning in disjointed SaaS subscriptions. JBS Praxis consolidated our POS, multi-location inventory, retail consignment, and financials into a single, unbreakable source of truth.") }}</blockquote>
                        <cite class="deploy-author">{{ __('— Founder, Premium Lifestyle & Retail Brand') }}</cite>
                    </article>

                    <article class="deploy-card">
                        <div class="deploy-system">{{ __('// THE NGO / MEGA-NETWORK AI') }}</div>
                        <blockquote class="deploy-quote">{{ __("Managing a highly distributed, continental network requires flawless internal control. The AI-integrated management ecosystem they deployed gave our leadership absolute visibility and structural accountability across every branch.") }}</blockquote>
                        <cite class="deploy-author">{{ __('— Director of Operations, Continental NGO Network') }}</cite>
                    </article>

                    <article class="deploy-card">
                        <div class="deploy-system">{{ __('// THE COMMERCIAL COMMAND CENTER') }}</div>
                        <blockquote class="deploy-quote">{{ __("Bidding for complex corporate contracts used to tie up our best engineers for weeks. JBS Praxis deployed an AI-powered Command Center that ingests tender requirements and generates institutional-grade proposals in seconds.") }}</blockquote>
                        <cite class="deploy-author">{{ __('— Managing Director, Heavy Engineering & Services Firm') }}</cite>
                    </article>

                    <article class="deploy-card">
                        <div class="deploy-system">{{ __('// THE EDUCATIONAL ECOSYSTEM') }}</div>
                        <blockquote class="deploy-quote">{{ __("They engineered a unified Learning Management System and Operations Hub, complete with AI voice-to-text session reporting. It eliminated our administrative chaos and gave parents absolute, real-time visibility.") }}</blockquote>
                        <cite class="deploy-author">{{ __('— Executive Director, Premium Educational Franchise') }}</cite>
                    </article>

                </div>
            </div>

            <div class="deploy-nav" data-reveal data-delay="2">
                <button class="deploy-arrow" id="deployPrev" aria-label="{{ __('Previous') }}">&#8592;</button>
                <div class="deploy-dots" id="deployDots" role="tablist"></div>
                <button class="deploy-arrow" id="deployNext" aria-label="{{ __('Next') }}">&#8594;</button>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §8  ZENITH — BRAND PHILOSOPHY
    ════════════════════════════════════════════════════ --}}
    <section class="jireh-zenith" aria-label="{{ __('JBS Praxis brand philosophy') }}">
        <div class="zenith-coord" style="top:20px;left:20px;" aria-hidden="true">{{ __('LAT:') }} 4.0511° {{ __('N') }}</div>
        <div class="zenith-coord" style="top:20px;right:20px;" aria-hidden="true">{{ __('LONG:') }} 9.7679° {{ __('E') }}</div>

        <div class="zenith-radial" aria-hidden="true"></div>
        <div class="zenith-line" aria-hidden="true"></div>

        <div class="container" style="position:relative;z-index:3;">
            <h2 class="zenith-quote">
                <span class="quote-mystical">{{ __('Provision is not mystical.') }}</span>
                <span class="quote-structured" id="zenithText">
                    {{ __('It is') }} <span class="teal-text">{{ __('structured.') }}</span>
                </span>
            </h2>
        </div>

        <div class="zenith-coord" style="bottom:20px;left:20px;" aria-hidden="true">{{ __('ARCH_REF:') }} JBS-001</div>
        <div class="zenith-coord" style="bottom:20px;right:20px;" aria-hidden="true">{{ __('VER:') }} 2026.PXR</div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §9  COPQ CALCULATOR
    ════════════════════════════════════════════════════ --}}
    <section class="chaos-tax-section container" aria-labelledby="calcHeading">
        <div class="section-header terminal-align relative-z" style="z-index:2;" data-reveal>
            <h4 class="terminal-teal-text mono-font">
                <span class="pulse-dot"></span> <strong>{{ __('// STATUS: COPQ_DIAGNOSTIC_ENGINE') }}</strong>
            </h4>
            <h2 id="calcHeading">{{ __('The Operational Chaos Tax') }}</h2>
            <p style="max-width:700px;margin:0 auto;margin-top:15px;">{{ __("Before you invest in architecture, know what the absence of it is costing you. Adjust the inputs below to calculate your organisation's annual capital leakage from manual processes and un-engineered systems.") }}</p>
        </div>

        <div class="calculator-wrapper" wire:ignore data-reveal data-delay="1">
            <select class="currency-selector" id="currencySelect" onchange="setCurrency(this)" aria-label="{{ __('Select currency') }}">
                <option value="USD" data-symbol="$" data-mult="1" selected>{{ __('USD - US Dollar Base') }}</option>

                @foreach($dbCurrencies as $curr)
                    @if($curr->currency_code !== 'USD')
                        <option value="{{ $curr->currency_code }}"
                                data-symbol="{{ $curr->currency_code }}"
                                data-mult="{{ $curr->rate_to_usd }}">
                            {{ $curr->currency_code }} - {{ $curr->currency_name }}
                        </option>
                    @endif
                @endforeach
            </select>

            <div class="calc-main-grid">
                {{-- INPUTS PANEL --}}
                <div class="calc-inputs-panel">
                    <div class="input-group">
                        <div class="label-row">
                            <label class="mono-font" for="headcountText">{{ __('ENTERPRISE HEADCOUNT') }}</label>
                            <input type="text" id="headcountText" onfocus="handleNumberFocus(this)" onblur="handleNumberBlur(this,'headcount')" oninput="syncSlider('headcount')" value="50">
                        </div>
                        <input type="range" id="headcountSlider" min="2" max="10000" value="50" oninput="syncInput('headcount')">
                        <div class="glass-tooltip" role="tooltip">{{ __('Total employees actively interacting with your operational systems, approval chains, and data workflows.') }}</div>
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label class="mono-font" for="mgmtNodesText">{{ __('MGMT / APPROVAL NODES') }}</label>
                            <input type="text" id="mgmtNodesText" onfocus="handleNumberFocus(this)" onblur="handleNumberBlur(this,'mgmtNodes')" oninput="syncSlider('mgmtNodes')" value="10">
                        </div>
                        <input type="range" id="mgmtNodesSlider" min="1" max="2000" value="10" oninput="syncInput('mgmtNodes')">
                        <div class="glass-tooltip" role="tooltip">{{ __('The exact number of managers, directors, or approvers.') }}</div>
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label class="mono-font" for="turnoverText">{{ __('ANNUAL TURNOVER') }} (<span class="curr-symbol">$</span>)</label>
                            <input type="text" id="turnoverText" onfocus="handleNumberFocus(this)" onblur="handleNumberBlur(this,'turnover')" oninput="syncSlider('turnover')" value="5,000,000">
                        </div>
                        <input type="range" id="turnoverSlider" min="0" max="10000000" step="50000" value="5000000" oninput="syncInput('turnover')">
                        <div class="glass-tooltip" role="tooltip">{{ __('Total annual revenue flowing through your current operational infrastructure — documented or otherwise.') }}</div>
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label class="mono-font" for="manualText">{{ __('WEEKLY MANUAL APPROVAL HRS (PER HEAD)') }}</label>
                            <input type="number" id="manualText" min="1" max="20" value="5" oninput="syncSlider('manual')">
                        </div>
                        <input type="range" id="manualSlider" min="1" max="20" value="5" oninput="syncInput('manual')">
                        <div class="glass-tooltip" role="tooltip">{{ __('Hours lost per employee waiting for signatures, data retrieval, or email approvals.') }}</div>
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label class="mono-font" for="hourlyCostText">{{ __('AVG HOURLY MGMT COST') }} (<span class="curr-symbol">$</span>)</label>
                            <input type="text" id="hourlyCostText" onfocus="handleNumberFocus(this)" onblur="handleNumberBlur(this,'hourlyCost')" oninput="syncSlider('hourlyCost')" value="50">
                        </div>
                        <input type="range" id="hourlyCostSlider" min="10" max="500" step="5" value="50" oninput="syncInput('hourlyCost')">
                        <div class="glass-tooltip" role="tooltip">{{ __("The average monetary value of one hour of your manager's operational time.") }}</div>
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label class="mono-font" for="errorText">{{ __('PROCESS ERROR & REWORK RATE (%)') }}</label>
                            <input type="text" id="errorText" onfocus="handleNumberFocus(this)" onblur="handleNumberBlur(this,'error')" oninput="syncSlider('error')" value="3">
                        </div>
                        <input type="range" id="errorSlider" min="1" max="10" step="0.1" value="3" oninput="syncInput('error')">
                        <div class="glass-tooltip" role="tooltip">{{ __('Frequency of manual rework, data duplication, and non-automated intervention (Industry Avg: 3-5%).') }}</div>
                    </div>
                </div>

                {{-- VISUAL RESULTS PANEL --}}
                <div class="calc-visual-panel">
                    <div class="split-meter" role="meter" aria-label="{{ __('Capital leakage meter') }}">
                        <div class="meter-bar lost" id="lostBar" style="width:25%;"></div>
                        <div class="meter-bar saved" id="savedBar" style="width:75%;"></div>
                    </div>

                    <div class="result-display">
                        <h5 class="mono-font" style="color:#FF3366;margin-bottom:5px;font-size:.72rem;letter-spacing:.1em;">&gt; {{ __('ESTIMATED ANNUAL CAPITAL LEAKED') }}</h5>
                        <div class="leak-counter" aria-live="polite" aria-atomic="true">
                            [ <span class="curr-symbol">$</span> <span id="leakageValue">460,000</span> ]
                        </div>
                        <p style="margin-bottom:25px;color:var(--text-muted);">
                            {{ __('Your enterprise is operating') }} <span id="percentLost" style="font-weight:800;color:var(--text-primary);">9</span>{{ __('% below its engineered potential.') }}
                        </p>
                        <button type="button" class="terminal-action-btn" style="width:100%;border-radius:4px;" onclick="openCopqModal()">{{ __('GENERATE DETAILED COPQ REPORT') }}</button>

                        <div class="tactical-calc-widget">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                <span class="mono-font" style="font-size:.64rem;color:var(--luminous-teal);letter-spacing:.1em;">{{ __('// TACTICAL SCRATCHPAD') }}</span>
                                <span class="mono-font" style="font-size:.64rem;color:var(--text-secondary);">{{ __('SYS.CALC') }}</span>
                            </div>

                            <input type="text" id="calcDisplay" class="calc-display" placeholder="0" onkeydown="if(event.key==='Enter')executeCalc()" aria-label="{{ __('Calculator display') }}">

                            <div class="calc-grid">
                                <button type="button" class="calc-btn" onclick="appendToCalc('7')">7</button>
                                <button type="button" class="calc-btn" onclick="appendToCalc('8')">8</button>
                                <button type="button" class="calc-btn" onclick="appendToCalc('9')">9</button>
                                <button type="button" class="calc-btn op-btn" onclick="appendToCalc('/')">/</button>

                                <button type="button" class="calc-btn" onclick="appendToCalc('4')">4</button>
                                <button type="button" class="calc-btn" onclick="appendToCalc('5')">5</button>
                                <button type="button" class="calc-btn" onclick="appendToCalc('6')">6</button>
                                <button type="button" class="calc-btn op-btn" onclick="appendToCalc('*')">×</button>

                                <button type="button" class="calc-btn" onclick="appendToCalc('1')">1</button>
                                <button type="button" class="calc-btn" onclick="appendToCalc('2')">2</button>
                                <button type="button" class="calc-btn" onclick="appendToCalc('3')">3</button>
                                <button type="button" class="calc-btn op-btn" onclick="appendToCalc('-')">-</button>

                                <button type="button" class="calc-btn" onclick="clearCalc()" style="color:var(--terminal-red);">C</button>
                                <button type="button" class="calc-btn" onclick="appendToCalc('0')">0</button>
                                <button type="button" class="calc-btn" onclick="appendToCalc('.')">.</button>
                                <button type="button" class="calc-btn op-btn" onclick="appendToCalc('+')">+</button>

                                <button type="button" class="calc-btn eval-btn" onclick="executeCalc()">{{ __('= EVALUATE') }}</button>
                            </div>

                            <div class="inject-controls">
                                <span class="mono-font" style="font-size:.62rem;color:var(--text-secondary);">{{ __('INJECT:') }}</span>
                                <select id="calcInjectTarget" class="inject-select">
                                    <option value="hourlyCost" selected>{{ __('AVG HOURLY MGMT COST') }}</option>
                                    <option value="turnover">{{ __('ANNUAL TURNOVER') }}</option>
                                    <option value="headcount">{{ __('ENTERPRISE HEADCOUNT') }}</option>
                                    <option value="mgmtNodes">{{ __('MGMT / APPROVAL NODES') }}</option>
                                    <option value="manual">{{ __('MANUAL HRS / WEEK') }}</option>
                                    <option value="error">{{ __('PROCESS ERROR RATE (%)') }}</option>
                                </select>
                                <button type="button" class="inject-btn" onclick="injectCalc()">{{ __('EXECUTE') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §10  PULSE CHECKPOINT
    ════════════════════════════════════════════════════ --}}
    <section class="pulse-checkpoint" id="pulseArea" aria-label="{{ __('Call to action') }}">
        <div class="scanner-lens" id="lens" aria-hidden="true"></div>
        <div class="pulse-line" aria-hidden="true"></div>
        <div class="container relative-z">
            <h2 class="pulse-title" data-reveal>
                {{ __('IS YOUR BUSINESS RUNNING ON') }} <span class="pulse-systems">{{ __('SYSTEMS') }}</span> {{ __('OR') }} <span class="pulse-hope">{{ __('HOPE?') }}</span>
            </h2>
            <p class="pulse-diagnostic" data-reveal data-delay="1">&gt; {{ __('DIAGNOSTIC RESULT: HOPE IS NOT A STRATEGY.') }}</p>
            <a href="/contact" wire:navigate class="pulse-btn" data-reveal data-delay="2" style="display:inline-block;margin-top:15px;text-decoration:none;">
                {{ __('CONTACT THE ARCHITECTS') }}
            </a>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════
         §11  COPQ MODAL — GLASSMORPHIC DRAWER
    ════════════════════════════════════════════════════ --}}
    <div class="modal-overlay" id="copqModal" wire:ignore role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-drawer">

            {{-- FORM FLOW --}}
            <div id="copqFlow">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;">
                    <div>
                        <div class="system-header-label">{{ __('// OVERRIDE_INITIATED') }}</div>
                        <h3 id="modalTitle" style="font-size:1.3rem;letter-spacing:-.02em;color:var(--stark-white);font-family:var(--font-display);font-weight:900;text-transform:uppercase;">{{ __('DATA EXTRACTION PROTOCOL') }}</h3>
                    </div>
                    <span style="cursor:pointer;font-size:2rem;line-height:1;color:var(--text-secondary);transition:color .2s;" onclick="closeCopqModal()" onmouseenter="this.style.color='var(--stark-white)'" onmouseleave="this.style.color='var(--text-secondary)'" aria-label="{{ __('Close modal') }}">×</span>
                </div>

                <div class="booking-form">
                    {{-- Honeypot --}}
                    <input type="text" id="copqTrap" name="user_middle_name" style="display:none!important" tabindex="-1" autocomplete="off" aria-hidden="true">

                    <input type="text" id="copqName" placeholder="{{ __('Full Name') }}"
                           onfocus="updateModalLog('{{ __('AUTHENTICATING_USER_IDENTITY') }}')" required aria-label="{{ __('Full Name') }}">

                    <input type="text" id="copqCompany" placeholder="{{ __('Enterprise Name') }}"
                           onfocus="updateModalLog('{{ __('SCANNING_ENTERPRISE_REGISTRY') }}')" required aria-label="{{ __('Enterprise Name') }}">

                    <input type="email" id="copqEmail" placeholder="{{ __('Corporate Email Address') }}"
                           onfocus="updateModalLog('{{ __('ESTABLISHING_SECURE_COMM_CHANNEL') }}')" required aria-label="{{ __('Email Address') }}">

                    <select id="copqIndustry" onchange="updateSectorVocab(this.value)" onfocus="updateModalLog('{{ __('LOADING_SECTOR_SPECIFIC_METRICS') }}')" required aria-label="{{ __('Industry Sector') }}">
                        <option value="" disabled selected>{{ __('Select Industry Sector') }}</option>
                        @foreach($dbIndustries as $ind)
                            <option value="{{ $ind->industry_name }}">{{ $ind->industry_name }}</option>
                        @endforeach
                    </select>

                    <select id="copqTool" onfocus="updateModalLog('{{ __('ANALYZING_INFRASTRUCTURE_VULNERABILITIES') }}')" required aria-label="{{ __('Primary Tech Architecture') }}">
                        <option value="" disabled selected>{{ __('Primary Tech Architecture?') }}</option>
                        @foreach($dbArchitectures as $arch)
                            <option value="{{ $arch->tech_key }}">{{ $arch->display_name }}</option>
                        @endforeach
                    </select>

                    <select id="copqBottleneck" onfocus="updateModalLog('{{ __('ISOLATING_OPERATIONAL_CHOKEPOINTS') }}')" required aria-label="{{ __('Primary Operational Bottleneck') }}">
                        <option value="" disabled selected>{{ __('Primary Operational Bottleneck?') }}</option>
                        @foreach($dbBottlenecks as $bn)
                            <option value="{{ $bn->bottleneck_name }}">{{ $bn->bottleneck_name }}</option>
                        @endforeach
                    </select>

                    <input type="number" id="copqACV" placeholder="{{ __('Avg. Contract/Order Value ($)') }}"
                           onfocus="updateModalLog('{{ __('CALCULATING_FINANCIAL_EXPOSURE_RATIOS') }}')" required aria-label="{{ __('Average Contract Value') }}">

                    <button type="button" id="submitCopqBtn"
                            onclick="event.preventDefault(); submitCopqForm()"
                            onmouseenter="updateModalLog('{{ __('READY_FOR_PROTOCOL_EXECUTION') }}')"
                            class="terminal-action-btn"
                            style="width:100%;position:relative;margin-top:15px;border-radius:4px;">
                        <span id="copqBtnText">{{ __('GENERATE REPORT') }}</span>
                    </button>

                    <div style="margin-top:22px;padding:14px;background:rgba(0,0,0,.3);border-left:2px solid var(--luminous-teal);">
                        <div id="modalSystemLog" class="mono-font" style="font-size:.64rem;color:var(--luminous-teal);opacity:.82;">
                            {{ __('> AWAITING DATA INPUT...') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- SUCCESS FLOW --}}
            <div id="copqSuccessFlow" style="display:none;height:100%;flex-direction:column;justify-content:center;text-align:left;">
                <div class="system-header-label" style="margin-bottom:6px;">{{ __('// CLEARANCE_GRANTED') }}</div>
                <h3 style="margin-bottom:14px;font-size:1.8rem;font-weight:900;letter-spacing:-.03em;text-transform:uppercase;font-family:var(--font-display);">{{ __('Diagnostic Matrix Secured.') }}</h3>
                <p style="color:var(--text-muted);margin-bottom:28px;font-size:.93rem;line-height:1.65;">
                    {{ __('Your custom Cost of Poor Quality (COPQ) analysis has been compiled. Select your preferred method of data retrieval below.') }}
                </p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px;">
                    <button type="button" id="viewReportBtn" class="terminal-action-btn"
                            style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;gap:10px;margin:0;width:100%;font-size:.72rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        {{ __('OPEN DOSSIER') }}
                    </button>

                    <button type="button" id="emailReportBtn" onclick="dispatchCopqEmail()"
                            class="terminal-btn"
                            style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;gap:10px;width:100%;margin:0;font-size:.72rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span id="emailBtnText">{{ __('DISPATCH TO INBOX') }}</span>
                    </button>
                </div>

                <div style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.08);padding:18px;display:flex;gap:16px;align-items:center;border-radius:4px;margin-bottom:22px;">
                    <div style="background:#FFFFFF;padding:8px;border-radius:4px;" id="qrContainer">
                        <img id="qrImage" src="" alt="{{ __('Secure QR Code for executive summary') }}" style="min-width:80px;width:80px;height:80px;">
                    </div>
                    <div>
                        <h4 class="mono-font" style="font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;margin-bottom:6px;color:var(--stark-white);">{{ __('Executive Summary Mode') }}</h4>
                        <p style="font-family:var(--font-mono);font-size:.68rem;color:var(--text-muted);line-height:1.55;margin-bottom:10px;">{{ __('Scan via mobile device to bypass the full calculus and access condensed C-Suite tactical briefing cards.') }}</p>
                        <button type="button" id="execModeBtn" class="mono-font" style="font-size:.68rem;color:var(--luminous-teal);letter-spacing:.14em;text-transform:uppercase;transition:color .2s;" onmouseenter="this.style.color='var(--stark-white)'" onmouseleave="this.style.color='var(--luminous-teal)'">{{ __('> INITIALIZE EXECUTIVE CARDS') }}</button>
                    </div>
                </div>

                <button type="button" onclick="closeCopqModal()" class="mono-font" style="font-size:.68rem;color:var(--text-secondary);text-decoration:underline;letter-spacing:.14em;text-transform:uppercase;align-self:flex-start;transition:color .2s;" onmouseenter="this.style.color='var(--text-primary)'" onmouseleave="this.style.color='var(--text-secondary)'">{{ __('[ TERMINATE CONNECTION ]') }}</button>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         JAVASCRIPT — SCROLL REVEAL + ALL INTERACTIONS
    ════════════════════════════════════════════════════ --}}
    <script>
        /* ── SCROLL REVEAL ─────────────────────────────────────── */
        (function() {
            const obs = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        e.target.classList.add('is-visible');
                        obs.unobserve(e.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -36px 0px' });

            document.querySelectorAll('[data-reveal]').forEach(el => obs.observe(el));

            /* Zenith word animation */
            const zenithText = document.getElementById('zenithText');
            if (zenithText) {
                const zObs = new IntersectionObserver((entries) => {
                    entries.forEach(e => {
                        if (e.isIntersecting) {
                            zenithText.classList.add('zenith-active');
                            zObs.unobserve(e.target);
                        }
                    });
                }, { threshold: 0.5 });
                zObs.observe(document.querySelector('.jireh-zenith'));
            }
        })();

        /* ── PULSE CHECKPOINT MOUSE TRACKER ───────────────────── */
        (function() {
            const area = document.getElementById('pulseArea');
            const lens = document.getElementById('lens');
            if (area && lens) {
                area.addEventListener('mousemove', e => {
                    const r = area.getBoundingClientRect();
                    lens.style.left = (e.clientX - r.left) + 'px';
                    lens.style.top  = (e.clientY - r.top) + 'px';
                }, { passive: true });
            }
        })();

        /* ── TERMINAL DATA ─────────────────────────────────────── */
        window.terminalData = {
            'fragmented': {
                scan: '{{ __("SCANNING DATA SILOS...") }} [████████░░] 80%',
                output: [
                    '> {{ __("VULNERABILITY CONFIRMED: Data silos are blocking operational intelligence.") }}',
                    '> {{ __("IMPACT: Leadership is making decisions on stale, fragmented data. What they don\'t know is costing them more than what they do.") }}',
                    '',
                    '> {{ __("DEPLOYING PRAXIS ARCHITECTURE:") }}',
                    '  [1] {{ __("Extract legacy data via automated pipelines.") }}',
                    '  [2] {{ __("Engineer a unified relational database.") }}',
                    '  [3] {{ __("Deploy AI-ready data infrastructure.") }}',
                    '',
                    '> {{ __("RESULT: One source of truth. Your data becomes a strategic asset.") }}',
                    `<a href="/schedule" wire:navigate class="terminal-action-btn">{{ __('COMMISSION DATA ARCHITECTURE') }}</a>`
                ]
            },
            'compliance': {
                scan: '{{ __("AUDITING COMPLIANCE PROTOCOLS...") }} [██████████] 100%',
                output: [
                    '> {{ __("VULNERABILITY CONFIRMED: Paper-based compliance is an audit waiting to happen.") }}',
                    '> {{ __("IMPACT: Fraud and deviation live in the gap between what the policy says and what the workflow actually enforces.") }}',
                    '',
                    '> {{ __("DEPLOYING PRAXIS ARCHITECTURE:") }}',
                    '  [1] {{ __("Encode ISO/ESG obligations into digital workflows.") }}',
                    '  [2] {{ __("Deploy continuous audit agents.") }}',
                    '',
                    '> {{ __("RESULT: Compliance enforced by architecture, not by human behaviour.") }}',
                    `<a href="/schedule" wire:navigate class="terminal-action-btn">{{ __('INITIATE COMPLIANCE AUDIT') }}</a>`
                ]
            },
            'capital': {
                scan: '{{ __("ANALYZING FINANCIAL WORKFLOWS...") }} [███████░░░] 70%',
                output: [
                    '> {{ __("VULNERABILITY CONFIRMED: Your manual approval chains are bleeding capital.") }}',
                    '> {{ __("IMPACT: Every untracked transaction is an open door for error, delay, and fraud.") }}',
                    '',
                    '> {{ __("DEPLOYING PRAXIS ARCHITECTURE:") }}',
                    '  [1] {{ __("Hardcode expenditure limits into the system.") }}',
                    '  [2] {{ __("Activate financial agents that flag anomalies before cash leaves the account.") }}',
                    '',
                    '> {{ __("RESULT: Total financial governance. Nothing moves without a trail.") }}',
                    `<a href="/schedule" wire:navigate class="terminal-action-btn">{{ __('DEPLOY INTERNAL CONTROLS') }}</a>`
                ]
            },
            'software': {
                scan: '{{ __("PROBING LEGACY SYSTEMS...") }} [█████████░] 90%',
                output: [
                    '> {{ __("VULNERABILITY CONFIRMED: Your legacy stack is a bottleneck, not a foundation.") }}',
                    '> {{ __("IMPACT: Your people are compensating for software that was never built for you.") }}',
                    '',
                    '> {{ __("DEPLOYING PRAXIS ARCHITECTURE:") }}',
                    '  [1] {{ __("Document and dismantle legacy workflows and fragmented applications.") }}',
                    '  [2] {{ __("Engineer a bespoke ERP mapped precisely to your operational logic.") }}',
                    '  [3] {{ __("Integrate secure APIs and deploy cloud infrastructure with CI/CD pipelines.") }}',
                    '',
                    '> {{ __("RESULT: A unified, scalable operating system. Built for your business exactly.") }}',
                    `<a href="/schedule" wire:navigate class="terminal-action-btn">{{ __('COMMISSION CUSTOM STACK') }}</a>`
                ]
            }
        };

        window.isTyping = false;

        window.runTerminalScript = async function(type) {
            if (window.isTyping) return;
            window.isTyping = true;

            document.getElementById('terminalOutput').scrollIntoView({ behavior: 'smooth', block: 'center' });

            const termLines  = document.getElementById('terminalLines');
            const termOutput = document.getElementById('terminalOutput');
            const data = window.terminalData[type];

            termLines.innerHTML = '> {{ __("INITIATING DIAGNOSTIC...") }}<br>';
            await delay(380);
            termLines.innerHTML += `<span style="color:var(--luminous-teal);">${data.scan}</span><br><br>`;
            await delay(580);

            for (let i = 0; i < data.output.length; i++) {
                let line = data.output[i];

                // Decode HTML entities from Blade rendering
                const txt = document.createElement('textarea');
                txt.innerHTML = line;
                line = txt.value;

                if (line.includes('<a ')) {
                    termLines.innerHTML += line + '<br>';
                    continue;
                }
                if (line === '') { termLines.innerHTML += '<br>'; continue; }

                const lineSpan = document.createElement('span');
                termLines.appendChild(lineSpan);

                for (let c = 0; c < line.length; c++) {
                    lineSpan.innerHTML += line.charAt(c) === ' ' ? '&nbsp;' : line.charAt(c);
                    termOutput.scrollTop = termOutput.scrollHeight;
                    await delay(10);
                }
                termLines.appendChild(document.createElement('br'));
                await delay(75);
            }
            window.isTyping = false;
        };

        function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

        /* ── BLUEPRINT TABS ────────────────────────────────────── */
        window.switchBlueprint = function(id) {
            const display = document.querySelector('.blueprint-display');
            const current = document.querySelector('.blueprint-tab.active');
            if (current && current.id === 'tab-' + id) return;

            display.classList.remove('refreshing');
            void display.offsetWidth;
            display.classList.add('refreshing');

            for (let i = 1; i <= 4; i++) {
                document.getElementById('tab-' + i).classList.remove('active');
                document.getElementById('tab-' + i).setAttribute('aria-selected', 'false');
                document.getElementById('content-' + i).classList.remove('active');
            }
            document.getElementById('tab-' + id).classList.add('active');
            document.getElementById('tab-' + id).setAttribute('aria-selected', 'true');
            document.getElementById('content-' + id).classList.add('active');
        };

        /* ── TACTICAL SCRATCHPAD ───────────────────────────────── */
        window.appendToCalc = function(val) {
            const d = document.getElementById('calcDisplay');
            if (d.value === '0' && val !== '.') d.value = '';
            d.value += val;
        };
        window.clearCalc = function() { document.getElementById('calcDisplay').value = '0'; };
        window.executeCalc = function() {
            const d = document.getElementById('calcDisplay');
            try {
                const s = d.value.replace(/[^0-9+\-*/.()]/g, '');
                if (s) {
                    const r = new Function('return ' + s)();
                    d.value = Number.isInteger(r) ? r : r.toFixed(2);
                }
            } catch(e) {
                d.value = 'ERROR';
                setTimeout(clearCalc, 900);
            }
        };
        window.injectCalc = function() {
            const v = parseFloat(document.getElementById('calcDisplay').value);
            if (isNaN(v)) return;
            const target   = document.getElementById('calcInjectTarget').value;
            const textIn   = document.getElementById(target + 'Text');
            const sliderIn = document.getElementById(target + 'Slider');
            if (!textIn || !sliderIn) return;
            let val = Math.min(Math.max(v, parseFloat(sliderIn.min)), parseFloat(sliderIn.max));
            textIn.value   = window.formatNumber(val);
            sliderIn.value = val;
            calculateTax();
            textIn.style.transition = 'background .3s ease';
            textIn.style.background = 'rgba(0,255,102,.18)';
            setTimeout(() => { textIn.style.background = ''; }, 650);
            window.clearCalc();
        };

        /* ── COPQ CALCULATOR ENGINE ────────────────────────────── */
        window.formatNumber = function(val) {
            const n = parseFloat(val) || 0;
            if (n < 100 && !Number.isInteger(n)) return n.toLocaleString(undefined, { minimumFractionDigits:1, maximumFractionDigits:1 });
            return Math.round(n).toLocaleString();
        };
        window.unformatNumber = function(str) { return parseFloat(String(str).replace(/,/g,'')) || 0; };

        window.handleNumberBlur = function(input, type) {
            const raw = window.unformatNumber(input.value);
            input.type = 'text'; input.value = window.formatNumber(raw);
            calculateTax();
        };
        window.handleNumberFocus = function(input) {
            input.type = 'number'; input.value = window.unformatNumber(input.value);
        };
        window.syncInput = function(type) {
            const s = document.getElementById(type + 'Slider');
            const t = document.getElementById(type + 'Text');
            if (t) t.value = window.formatNumber(s.value);
            calculateTax();
        };
        window.syncSlider = function(type) {
            const t = document.getElementById(type + 'Text');
            const s = document.getElementById(type + 'Slider');
            if (s) s.value = window.unformatNumber(t.value);
            calculateTax();
        };

        let currentSymbol = '$';
        let currentMultiplier = 1;

        window.setCurrency = function(sel) {
            const opt = sel.options[sel.selectedIndex];
            currentSymbol = opt.getAttribute('data-symbol');
            const newMult  = parseFloat(opt.getAttribute('data-mult'));
            const ratio    = newMult / currentMultiplier;

            document.querySelectorAll('.curr-symbol').forEach(el => el.textContent = currentSymbol);

            const tText = document.getElementById('turnoverText');
            const tSlider = document.getElementById('turnoverSlider');
            tSlider.max = 10000000 * newMult; tSlider.step = 50000 * newMult;
            const nTurn = window.unformatNumber(tText.value) * ratio;
            tText.value = window.formatNumber(nTurn); tSlider.value = nTurn;

            const hText = document.getElementById('hourlyCostText');
            const hSlider = document.getElementById('hourlyCostSlider');
            hSlider.max = 500 * newMult; hSlider.step = 5 * newMult;
            const nHourly = window.unformatNumber(hText.value) * ratio;
            hText.value = window.formatNumber(nHourly); hSlider.value = nHourly;

            const acvInput = document.getElementById('copqACV');
            if (acvInput) acvInput.placeholder = `{{ __("Avg. Contract/Order Value") }} (${currentSymbol})`;

            currentMultiplier = newMult;
            calculateTax();
        };

        function calculateTax() {
            const headcount  = window.unformatNumber(document.getElementById('headcountText').value);
            const mgmtNodes  = window.unformatNumber(document.getElementById('mgmtNodesText').value);
            const turnover   = window.unformatNumber(document.getElementById('turnoverText').value);
            const manualHrs  = window.unformatNumber(document.getElementById('manualText').value);
            const hourlyCost = window.unformatNumber(document.getElementById('hourlyCostText').value);
            const errorRate  = window.unformatNumber(document.getElementById('errorText').value);

            const laborWaste  = mgmtNodes * manualHrs * hourlyCost * 52;
            const errorWaste  = turnover * (errorRate / 100);
            const totalLeak   = laborWaste + errorWaste;
            const pct         = turnover > 0 ? Math.min(Math.round((totalLeak / turnover) * 100), 100) : 0;

            document.getElementById('leakageValue').textContent = Math.round(totalLeak).toLocaleString();
            document.getElementById('percentLost').textContent  = pct;
            document.getElementById('lostBar').style.width      = pct + '%';
            document.getElementById('savedBar').style.width     = (100 - pct) + '%';

            const lc = document.querySelector('.leak-counter');
            lc.classList.toggle('critical-leak', pct > 15);
            document.getElementById('percentLost').style.color = pct > 15 ? 'var(--terminal-red)' : 'var(--text-primary)';
        }

        /* ── SECTOR VOCABULARY MORPH ───────────────────────────── */
        window.updateSectorVocab = function(sector) {
            const isPublic = (sector.includes('{{ __("NGO") }}') || sector.includes('{{ __("Government") }}'));
            const acvInput = document.getElementById('copqACV');
            if (acvInput) {
                acvInput.placeholder = isPublic
                    ? `{{ __("Avg. Grant / Program Size") }} (${currentSymbol})`
                    : `{{ __("Avg. Contract/Order Value") }} (${currentSymbol})`;
            }
            const turnLbl = document.querySelector('.label-row label[for="turnoverText"]');
            if (turnLbl) {
                turnLbl.innerHTML = isPublic
                    ? `{{ __("ANNUAL GRANT FUNDING / BUDGET") }} (<span class="curr-symbol">${currentSymbol}</span>)`
                    : `{{ __("ANNUAL TURNOVER") }} (<span class="curr-symbol">${currentSymbol}</span>)`;
            }
            if (typeof updateModalLog === 'function') {
                updateModalLog(isPublic ? 'CONFIGURING_SOCIAL_IMPACT_METRICS' : 'CONFIGURING_COMMERCIAL_REVENUE_METRICS');
            }
        };

        /* ── MODAL CONTROLS ────────────────────────────────────── */
        window.openCopqModal = function() {
            const modal = document.getElementById('copqModal');
            modal.style.display = 'flex';
            document.getElementById('copqFlow').style.display = 'block';
            document.getElementById('copqSuccessFlow').style.display = 'none';
            document.body.style.overflow = 'hidden';
            setTimeout(() => modal.classList.add('active'), 10);
        };

        window.updateModalLog = function(msg) {
            const log = document.getElementById('modalSystemLog');
            if (!log) return;
            log.style.opacity = '.3';
            setTimeout(() => {
                log.textContent = `> ${msg}... {{ __("OK.") }}`;
                log.style.opacity = '.9';
            }, 100);
        };

        window.closeCopqModal = function() {
            const modal = document.getElementById('copqModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(() => modal.style.display = 'none', 320);
        };

        // Close on backdrop click
        document.getElementById('copqModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeCopqModal();
        });

        /* ── FORM SUBMIT — LIVEWIRE BRIDGE ─────────────────────── */
        window.submitCopqForm = async function() {
            const trap = document.getElementById('copqTrap');
            if (trap && trap.value !== '') return;

            const payload = {
                name:       document.getElementById('copqName').value,
                company:    document.getElementById('copqCompany').value,
                email:      document.getElementById('copqEmail').value,
                industry:   document.getElementById('copqIndustry').value,
                tool:       document.getElementById('copqTool').value,
                bottleneck: document.getElementById('copqBottleneck').value,
                acv:        document.getElementById('copqACV').value,
                headcount:  window.unformatNumber(document.getElementById('headcountText').value),
                mgmtNodes:  window.unformatNumber(document.getElementById('mgmtNodesText').value),
                turnover:   window.unformatNumber(document.getElementById('turnoverText').value),
                manualHrs:  window.unformatNumber(document.getElementById('manualText').value),
                hourlyCost: window.unformatNumber(document.getElementById('hourlyCostText').value),
                errorRate:  window.unformatNumber(document.getElementById('errorText').value),
                currency:   document.getElementById('currencySelect').value
            };

            if (!payload.name || !payload.company || !payload.email || !payload.industry || !payload.tool || !payload.bottleneck || !payload.acv) {
                alert('{{ __("SYSTEM HALT: All diagnostic fields must be populated.") }}');
                return;
            }

            const btn     = document.getElementById('submitCopqBtn');
            const btnText = document.getElementById('copqBtnText');

            btn.classList.add('executing-btn');
            btnText.textContent = '{{ __("EXECUTING PROTOCOL...") }}';

            let reportToken = null;

            // Backend call via Livewire @this
            let backendTask = @this.generateCopqReport(payload).then(token => {
                reportToken = token;
            }).catch(err => {
                console.error('Backend Error:', err);
                reportToken = 'ERROR';
            });

            updateModalLog('COMPILING_ROI_MATRIX');
            await delay(600);
            updateModalLog('ENCRYPTING_ENTERPRISE_DATA');
            await delay(600);
            updateModalLog('AWAITING_DIAGNOSTIC_AGENT');
            await backendTask;
            updateModalLog('PROTOCOL_COMPLETE');
            await delay(320);

            const flow        = document.getElementById('copqFlow');
            const successFlow = document.getElementById('copqSuccessFlow');

            flow.style.transition = 'opacity .35s ease, transform .35s ease';
            flow.style.opacity = '0'; flow.style.transform = 'translateY(-16px)';
            await delay(380);
            flow.style.display = 'none';

            if (reportToken && reportToken !== 'ERROR') {
                const fullDocUrl = `/dossier/${reportToken}`;
                const execDocUrl = `/dossier/${reportToken}?exec=true`;
                document.getElementById('viewReportBtn').onclick = () => window.location.href = fullDocUrl;
                document.getElementById('execModeBtn').onclick   = () => window.location.href = execDocUrl;
                const qrUrl = encodeURIComponent(window.location.origin + execDocUrl);
                document.getElementById('qrImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrUrl}&color=0A1128&bgcolor=FFFFFF`;
                window.currentCopqToken = reportToken;
            } else {
                document.getElementById('viewReportBtn').textContent = '{{ __("[ SYSTEM ERROR - CONTACT HQ ]") }}';
            }

            successFlow.style.display  = 'flex';
            successFlow.style.opacity  = '0';
            successFlow.style.transform= 'translateY(20px)';
            void successFlow.offsetWidth;
            successFlow.style.transition = 'opacity .45s var(--ease-expo), transform .45s var(--ease-expo)';
            successFlow.style.opacity  = '1';
            successFlow.style.transform= 'translateY(0)';

            btn.classList.remove('executing-btn');
            btnText.textContent    = '{{ __("GENERATE REPORT") }}';
            flow.style.opacity     = '1';
            flow.style.transform   = 'translateY(0)';
        };

        /* ── EMAIL DISPATCHER ──────────────────────────────────── */
        window.dispatchCopqEmail = async function() {
            const btn      = document.getElementById('emailReportBtn');
            const textSpan = document.getElementById('emailBtnText');
            if (btn.disabled) return;
            btn.disabled = true;
            textSpan.textContent = '{{ __("TRANSMITTING...") }}';
            btn.style.opacity = '.7';
            const sent = await @this.dispatchReportToEmail(window.currentCopqToken);
            btn.style.opacity    = '1';
            if (sent) {
                btn.style.borderColor= '#00FF66';
                btn.style.color      = '#00FF66';
                textSpan.textContent = '{{ __("PAYLOAD DELIVERED") }}';
            } else {
                // Let the visitor retry; the report link above still works
                btn.disabled = false;
                btn.style.borderColor= '#FF3366';
                btn.style.color      = '#FF3366';
                textSpan.textContent = '{{ __("COULD NOT SEND. USE THE REPORT LINK ABOVE") }}';
            }
        };

        /* ── LIVEWIRE NAVIGATION RE-INIT ───────────────────────── */
        document.addEventListener('livewire:navigated', () => {
            // Re-observe new elements after Livewire navigation
            const obs = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) { e.target.classList.add('is-visible'); obs.unobserve(e.target); }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -36px 0px' });
            document.querySelectorAll('[data-reveal]:not(.is-visible)').forEach(el => obs.observe(el));

            // Zenith re-bind
            const zenithText = document.getElementById('zenithText');
            const zenithSection = document.querySelector('.jireh-zenith');
            if (zenithText && zenithSection) {
                const zObs = new IntersectionObserver((entries) => {
                    entries.forEach(e => { if (e.isIntersecting) { zenithText.classList.add('zenith-active'); zObs.unobserve(e.target); } });
                }, { threshold: 0.5 });
                zObs.observe(zenithSection);
            }

            // Pulse lens re-bind
            const area = document.getElementById('pulseArea');
            const lens = document.getElementById('lens');
            if (area && lens) {
                area.addEventListener('mousemove', e => {
                    const r = area.getBoundingClientRect();
                    lens.style.left = (e.clientX - r.left) + 'px';
                    lens.style.top  = (e.clientY - r.top) + 'px';
                }, { passive: true });
            }

            calculateTax();
        });

        /* ── INIT ──────────────────────────────────────────────── */
        calculateTax();

        /* ── DEPLOYMENT SLIDER ─────────────────────────────────── */
        (function initDeploySlider() {
            const track = document.getElementById('deployTrack');
            if (!track) return;

            const wrapper  = track.parentElement;
            const dotsWrap = document.getElementById('deployDots');
            const cards    = track.querySelectorAll('.deploy-card');
            const total    = cards.length; // 7
            let perView    = getPerView();
            let maxIndex   = total - perView;
            let current    = 0;
            let autoTimer  = null;

            function getPerView() {
                if (window.innerWidth <= 560) return 1;
                if (window.innerWidth <= 900) return 2;
                return 4;
            }

            function buildDots() {
                dotsWrap.innerHTML = '';
                maxIndex = Math.max(0, total - perView);
                for (let i = 0; i <= maxIndex; i++) {
                    const dot = document.createElement('button');
                    dot.className = 'deploy-dot' + (i === current ? ' active' : '');
                    dot.setAttribute('aria-label', 'Slide ' + (i + 1));
                    dot.setAttribute('role', 'tab');
                    dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
                    dot.addEventListener('click', function() { goTo(i); resetAuto(); });
                    dotsWrap.appendChild(dot);
                }
            }

            function goTo(index) {
                current = Math.max(0, Math.min(index, maxIndex));
                const firstCard = track.querySelector('.deploy-card');
                if (!firstCard) return;
                const gap       = 18;
                const cardW     = firstCard.offsetWidth;
                track.style.transform = 'translateX(-' + (current * (cardW + gap)) + 'px)';
                dotsWrap.querySelectorAll('.deploy-dot').forEach(function(d, i) {
                    d.classList.toggle('active', i === current);
                    d.setAttribute('aria-selected', i === current ? 'true' : 'false');
                });
            }

            function next() { goTo(current >= maxIndex ? 0 : current + 1); }
            function prev() { goTo(current <= 0 ? maxIndex : current - 1); }
            function startAuto() { autoTimer = setInterval(next, 6000); }
            function stopAuto()  { clearInterval(autoTimer); }
            function resetAuto() { stopAuto(); startAuto(); }

            const nextBtn = document.getElementById('deployNext');
            const prevBtn = document.getElementById('deployPrev');
            if (nextBtn) nextBtn.addEventListener('click', function() { next(); resetAuto(); });
            if (prevBtn) prevBtn.addEventListener('click', function() { prev(); resetAuto(); });

            wrapper.addEventListener('mouseenter', stopAuto);
            wrapper.addEventListener('mouseleave', startAuto);

            /* Touch swipe support */
            let touchStartX = 0;
            wrapper.addEventListener('touchstart', function(e) {
                touchStartX = e.touches[0].clientX;
            }, { passive: true });
            wrapper.addEventListener('touchend', function(e) {
                const diff = touchStartX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 48) { diff > 0 ? next() : prev(); resetAuto(); }
            }, { passive: true });

            /* Responsive resize */
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    const newPer = getPerView();
                    if (newPer !== perView) {
                        perView  = newPer;
                        maxIndex = Math.max(0, total - perView);
                        current  = Math.min(current, maxIndex);
                        buildDots();
                    }
                    goTo(current);
                }, 200);
            }, { passive: true });

            /* Init */
            buildDots();
            goTo(0);
            startAuto();
        })();
    </script>
</div>