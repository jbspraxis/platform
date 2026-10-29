<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', __('JBS Praxis | Predictive Architecture & Enterprise Intelligence'))</title>
    <meta name="google-site-verification" content="0zhkJDJCDXms6Z9kFIqz74TxhvuMLBBNJxuIPIMadYY"/>
    <meta name="description" content="@yield('meta_description', __('JBS Praxis engineers deterministic operational resilience. We deploy forensic COPQ diagnostics, relational data vaults, and zero-defect cognitive backends for global Tier-1 enterprises.'))">
    <meta name="keywords" content="@yield('meta_keywords', __('Enterprise Architecture, Predictive Diagnostics, Zero-Defect Governance, Cognitive Backends, COPQ Analysis, Lean Six Sigma, RAG AI, Digital Poka-Yoke'))">

    {{-- Strict Canonical (Strips query strings for pure SEO equity) --}}
    <link rel="canonical" href="@yield('canonical', request()->url())">
    <link rel="alternate" hreflang="x-default" href="{{ request()->url() }}">
    <link rel="alternate" hreflang="en" href="{{ request()->url() }}">
    <link rel="alternate" hreflang="fr" href="{{ request()->url() }}">
    <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large')">

    {{-- PRAXIS OVERRIDE: Server-Side Bot Routing for OG Images --}}
    @php
        $userAgent = request()->header('User-Agent');
        // If the bot is WhatsApp, iMessage (which uses generic Apple bots), or Telegram, we serve the Portrait image.
        // Otherwise, we default to the Landscape image for LinkedIn, Twitter, Slack, etc.
        $isChatApp = preg_match('/WhatsApp|Telegram|CFNetwork|Mac OS X/i', $userAgent);
        
        $defaultImage = $isChatApp 
            ? asset('assets/img/jbs-og-banner-pt.png') 
            : asset('assets/img/jbs-og-banner-ls.png');
    @endphp

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:site_name" content="JBS Praxis Intelligence">
    <meta property="og:title" content="@yield('title', __('JBS Praxis | Predictive Architecture & Enterprise Intelligence'))">
    <meta property="og:description" content="@yield('meta_description', __('JBS Praxis engineers deterministic operational resilience. We deploy forensic COPQ diagnostics, relational data vaults, and zero-defect cognitive backends for global Tier-1 enterprises.'))">
    
    {{-- Dynamically Injected Image --}}
    <meta property="og:image" content="@yield('og_image', $defaultImage)">
    @php
        $locales = [
            'en' => 'en_US', 
            'fr' => 'fr_FR', 
            'es' => 'es_ES'
        ];
        $ogLocale = $locales[app()->getLocale()] ?? 'en_US';
    @endphp
    <meta property="og:locale" content="{{ $ogLocale }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ request()->url() }}">
    <meta name="twitter:title" content="@yield('title', __('JBS Praxis | Predictive Architecture & Enterprise Intelligence'))">
    <meta name="twitter:description" content="@yield('meta_description', __('JBS Praxis engineers deterministic operational resilience. We deploy forensic COPQ diagnostics, relational data vaults, and zero-defect cognitive backends for global Tier-1 enterprises.'))">
    
    {{-- Twitter always prefers Landscape --}}
    <meta name="twitter:image" content="@yield('og_image', asset('assets/img/jbs-og-banner-ls.png'))">

    <meta name="theme-color" content="#0a1128">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&family=Roboto+Mono:wght@400;500;700&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        if (localStorage.getItem('theme') === 'light' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.classList.add('light-mode');
        }
    </script>

    {{-- Master Schema injected into Head with corrected syntax --}}
    @php
    $jbsMasterSchema = [
        "@context" => "https://schema.org",
        "@type" => ["Organization", "ProfessionalService"],
        "name" => "JBS Praxis",
        "alternateName" => "Praxis Intelligence",
        "url" => url('/'),
        "logo" => asset('assets/img/logo_colour.svg'),
        "image" => asset('assets/img/jbs-og-banner-ls.png'), // Schema prefers Landscape
        "slogan" => __("Beyond Strategy"),
        "description" => __("JBS Praxis engineers deterministic operational resilience. We deploy forensic COPQ diagnostics, relational and vector data vaults, and zero-defect cognitive backends for Tier-1 African enterprises."),
        "priceRange" => "$$$",
        "knowsAbout" => [
            __("Algorithmic Foresight"),
            __("Cost of Poor Quality (COPQ) Calculus"),
            __("Zero-Defect Governance"),
            __("Enterprise Data Engineering"),
            __("Cognitive Backends (RAG Architecture)")
        ],
        "telephone" => "+234-816-978-9106",
        "contactPoint" => [
            "@type" => "ContactPoint",
            "telephone" => "+234-816-978-9106",
            "contactType" => "technical support",
            "email" => "intelligence@jbspraxis.com",
            "availableLanguage" => ["English", "French"]
        ],
        "address" => [
            "@type" => "PostalAddress",
            "streetAddress" => "21B Bode Ajakaiye Street, Lekki",
            "addressLocality" => "Lagos",
            "addressCountry" => "NG"
        ],
        "hasOfferCatalog" => [
            "@type" => "OfferCatalog",
            "name" => __("Tier-1 Enterprise Architecture"),
            "itemListElement" => [
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => __("The Predictive Praxis Framework™"),
                        "description" => __("A deterministic methodology shifting enterprise governance to pre-cognitive algorithmic foresight.")
                    ]
                ],
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "SoftwareApplication",
                        "name" => __("Bespoke Systems Architecture"),
                        "applicationCategory" => "BusinessApplication",
                        "description" => __("Engineering high-velocity backends, secure API gateways, and RAG-driven cognitive intelligence.")
                    ]
                ]
            ]
        ]
    ];
    @endphp

    <script type="application/ld+json">
    {!! json_encode($jbsMasterSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    @yield('dynamic_schema')
    
    <style>
        /* --- GLOBAL CSS VARIABLES & THEME --- */
        :root {
            --midnight-navy: #0A1128;
            --slate-navy: #141E3C;
            --luminous-teal: #00E5FF;
            --stark-white: #FFFFFF;
            --ghost-white: #F3F4F6;
            --terminal-red: #FF3366;
            
            --bg-main: var(--midnight-navy);
            --bg-secondary: var(--slate-navy);
            --text-primary: var(--stark-white);
            --text-secondary: #B0B8C4;
            --accent-color: var(--luminous-teal);
            --border-color: rgba(0, 229, 255, 0.15);
            
            --header-height: 80px; /* Crucial for structural offset */
        }

        html.light-mode {
            --bg-main: var(--stark-white);
            --bg-secondary: var(--ghost-white);
            --text-primary: var(--midnight-navy);
            --text-secondary: #4A5568;
            --accent-color: var(--midnight-navy);
            --border-color: rgba(10, 17, 40, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* Structural Flex Flow - Ensures footer always stays at bottom */
        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
            transition: background-color 0.4s ease, color 0.4s ease;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* The Main Workspace - Pushes footer down and respects header */
        .praxis-workspace {
            flex: 1 0 auto;
            margin-top: var(--header-height);
            display: flex;
            flex-direction: column;
        }

        h1, h2, h3, h4 { font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        ul { list-style: none; }
        .container { 
            width: 100%; 
            max-width: 1240px; 
            margin: 0 auto; 
            padding: 0 24px; 
        }
        
        @media (min-width: 950px) {
            .container { 
                padding: 0 48px; 
            }
        }
        .mono-font { font-family: 'Roboto Mono', monospace; }
        .teal-text { color: var(--luminous-teal); }
        html.light-mode .teal-text { color: var(--accent-color); }

        /* --- GLOBAL BUTTONS --- */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 14px 28px; background-color: var(--accent-color);
            color: var(--midnight-navy); font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; border: none; cursor: pointer; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', sans-serif; border-radius: 2px;
        }
        html.light-mode .btn { color: var(--stark-white); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0, 229, 255, 0.2); }
        html.light-mode .btn:hover { box-shadow: 0 10px 20px rgba(10, 17, 40, 0.15); }
        .btn-small { padding: 10px 20px; font-size: 0.75rem; letter-spacing: 1.5px; }

        /* --- HEADER ARCHITECTURE --- */
        .global-header {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--header-height);
            background-color: rgba(10, 17, 40, 0.9); 
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); z-index: 1000;
            border-bottom: 1px solid var(--border-color); transition: background-color 0.4s ease;
            display: flex; align-items: center;
        }
        html.light-mode .global-header { background-color: rgba(255, 255, 255, 0.9); }
        
        .nav-container { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .logo { display: flex; align-items: center; }
        .logo img { height: 42px; width: auto; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); transform-origin: left center; }
        .logo:hover img { transform: scale(1.03); }
        
        html:not(.light-mode) .logo-light { display: none; }
        html:not(.light-mode) .logo-dark { display: block; filter: brightness(1.2); }
        html.light-mode .logo-dark { display: none; }
        html.light-mode .logo-light { display: block; }

        .nav-menu { display: flex; gap: 35px; align-items: center; height: 100%; }
        .nav-item { position: relative; font-weight: 600; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase; cursor: pointer; padding: 30px 0; transition: color 0.3s ease; }
        .nav-item::after { content: ''; position: absolute; bottom: 0; left: 0; width: 0%; height: 2px; background-color: var(--accent-color); transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .nav-item:hover::after, .nav-item:focus-within::after { width: 100%; }
        .nav-item:hover, .nav-item:focus-within { color: var(--accent-color); }

        /* --- DROPDOWNS --- */
        .dropdown-content {
            visibility: hidden; opacity: 0; position: absolute; top: calc(100% - 1px); left: 50%;
            transform: translateX(-50%) translateY(10px); background-color: rgba(10, 17, 40, 0.95); 
            backdrop-filter: blur(20px); min-width: 240px; box-shadow: 0 40px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border-color); border-top: 2px solid var(--accent-color);
            border-radius: 0 0 4px 4px; z-index: 1001; padding: 10px 0; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); pointer-events: none;
        }
        html.light-mode .dropdown-content { background-color: rgba(255, 255, 255, 0.98); box-shadow: 0 20px 40px rgba(10, 17, 40, 0.08); border-top: 2px solid var(--midnight-navy); }
        .nav-item:hover .dropdown-content, .nav-item:focus-within .dropdown-content { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
        
        .dropdown-content a { position: relative; display: flex; align-items: center; padding: 12px 25px; font-size: 0.8rem; font-weight: 500; color: var(--text-secondary); transition: all 0.2s ease; overflow: hidden; }
        .dropdown-content a::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 2px; background-color: var(--accent-color); transform: scaleY(0); transform-origin: bottom; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        html.light-mode .dropdown-content a::before { background-color: var(--midnight-navy); }
        .dropdown-content a:hover { color: var(--stark-white); background-color: rgba(0, 229, 255, 0.03); padding-left: 32px; }
        html.light-mode .dropdown-content a:hover { color: var(--midnight-navy); background-color: rgba(10, 17, 40, 0.03); }
        .dropdown-content a:hover::before { transform: scaleY(1); transform-origin: top; }

        /* --- HEADER CONTROLS --- */
        .nav-controls { display: flex; align-items: center; gap: 25px; height: 100%; }
        
        /* Language Dropdown */
        .lang-selector { position: relative; display: flex; align-items: center; padding: 30px 0; cursor: pointer; }
        .globe-icon { width: 18px; height: 18px; fill: var(--text-secondary); transition: all 0.3s ease; }
        .lang-selector:hover .globe-icon { fill: var(--accent-color); transform: rotate(15deg); }
        
        .lang-dropdown { visibility: hidden; opacity: 0; position: absolute; top: calc(100% - 1px); left: 50%; transform: translateX(-50%) translateY(10px); background-color: rgba(10, 17, 40, 0.95); backdrop-filter: blur(20px); min-width: 80px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5); border: 1px solid var(--border-color); border-top: 2px solid var(--accent-color); border-radius: 0 0 4px 4px; z-index: 1002; padding: 8px 0; transition: all 0.3s ease; pointer-events: none; }
        html.light-mode .lang-dropdown { background-color: rgba(255, 255, 255, 0.98); box-shadow: 0 10px 30px rgba(10, 17, 40, 0.08); border-top-color: var(--midnight-navy); }
        .lang-selector:hover .lang-dropdown { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
        .lang-dropdown a { display: block; padding: 10px 0; font-size: 0.75rem; font-family: 'Roboto Mono', monospace; text-align: center; color: var(--text-secondary); transition: all 0.2s ease; }
        .lang-dropdown a:hover { color: var(--stark-white); background-color: rgba(0, 229, 255, 0.05); }
        html.light-mode .lang-dropdown a:hover { color: var(--midnight-navy); background-color: rgba(10, 17, 40, 0.03); }

        /* Theme Toggle */
        .theme-toggle { cursor: pointer; display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 4px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); transition: all 0.3s ease; }
        .theme-toggle:hover { background: rgba(0, 229, 255, 0.05); }
        html.light-mode .theme-toggle { background: rgba(10, 17, 40, 0.02); border-color: rgba(10, 17, 40, 0.1); }
        html.light-mode .theme-toggle:hover { background: rgba(10, 17, 40, 0.05); }
        
        .theme-icon { width: 16px; height: 16px; fill: var(--text-secondary); transition: fill 0.3s ease; }
        .theme-toggle:hover .theme-icon { fill: var(--accent-color); }
        html:not(.light-mode) .moon-icon { display: none; }
        html:not(.light-mode) .sun-icon { display: block; }
        html.light-mode .sun-icon { display: none; }
        html.light-mode .moon-icon { display: block; fill: var(--midnight-navy); }

        /* --- GLOBAL FOOTER ARCHITECTURE --- */
        .global-footer { 
            background-color: #050811; 
            padding: 60px 0 30px 0; 
            border-top: 1px solid var(--border-color); 
            flex-shrink: 0; /* Prevents flexbox from squeezing the footer */
        }
        .footer-grid { 
            display: grid; 
            grid-template-columns: 1.5fr 1fr 1fr; /* Stronger logical flow */
            gap: 60px; 
            margin-bottom: 50px; 
        }
        
        .footer-logo { margin-bottom: 25px; }
        .footer-logo .teal-text { font-size: 1.4rem; font-weight: 800; letter-spacing: 2px; }
        .footer-logo .tagline { font-size: 0.7rem; color: var(--text-secondary); letter-spacing: 3px; margin-top: 5px; text-transform: uppercase; }
        
        .hq-block { margin-bottom: 20px; }
        .hq-label { font-size: 0.65rem; color: var(--accent-color); margin-bottom: 6px; }
        address { font-style: normal; font-size: 0.85rem; color: #8F9BA8; line-height: 1.6; }
        
        .footer-contact { display: flex; flex-direction: column; gap: 8px; margin-top: 25px; }
        .footer-contact a, .footer-tel { font-size: 0.85rem; color: #8F9BA8; transition: 0.3s; }
        .footer-contact a:hover { color: var(--accent-color); }
        
        .footer-col h5 { font-size: 0.8rem; letter-spacing: 2px; color: var(--stark-white); margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; }
        
        .footer-links li { margin-bottom: 12px; }
        .footer-links a { color: #8F9BA8; font-size: 0.85rem; display: inline-flex; align-items: center; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .footer-links a::before { content: '›'; margin-right: 8px; color: var(--accent-color); opacity: 0; transform: translateX(-10px); transition: all 0.3s ease; }
        .footer-links a:hover { color: var(--stark-white); transform: translateX(5px); }
        .footer-links a:hover::before { opacity: 1; transform: translateX(0); }
        
        .sla-block { display: flex; flex-direction: column; gap: 16px; }
        .sla-item { display: flex; align-items: center; gap: 15px; padding: 10px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.03); border-radius: 4px; }
        .sla-value { font-family: 'Roboto Mono', monospace; color: var(--accent-color); font-weight: bold; font-size: 0.9rem; width: 60px; flex-shrink: 0; }
        .sla-label { font-size: 0.75rem; color: #8F9BA8; line-height: 1.3; }

        /* --- PERFECTLY BALANCED FOOTER BOTTOM --- */
        .footer-bottom { 
            padding-top: 25px; 
            border-top: 1px solid rgba(255,255,255,0.05); 
            display: grid; 
            grid-template-columns: 1fr auto 1fr; /* Forces perfect visual centering */
            align-items: center; 
        }
        
        .copyright { 
            font-size: 0.75rem; color: #64748B; 
            font-family: 'Roboto Mono', monospace; 
            justify-self: start; /* Anchors to the far left */
        }
        
        .social-links { 
            display: flex; gap: 24px; 
            justify-self: center; /* Locks dead center regardless of side content */
        }
        
        .social-icon { 
            color: #64748B; 
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
            display: flex; align-items: center; justify-content: center;
        }
        .social-icon:hover { 
            color: var(--accent-color); 
            transform: translateY(-3px); 
            filter: drop-shadow(0 5px 10px rgba(0, 229, 255, 0.3));
        }
        .social-icon svg { width: 18px; height: 18px; stroke-width: 1.5; } /* Lucide target */
        
        .command-gateway { 
            justify-self: end; /* Anchors to the far right */
        }
        
        /* Mobile Override */
        @media (max-width: 950px) {
            .footer-bottom { 
                grid-template-columns: 1fr; 
                gap: 25px; 
            }
            .copyright, .social-links, .command-gateway { 
                justify-self: center; 
            }
        }
        .command-link { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #475569; transition: all 0.3s ease; display: flex; align-items: center; }
        .command-link:hover { color: var(--accent-color); }
        .command-icon { font-size: 0.9rem; margin-right: 6px; opacity: 0.7; }

        html.light-mode .global-footer { background-color: #F8FAFC; border-top-color: rgba(10, 17, 40, 0.1); }
        html.light-mode .global-footer .footer-col h5 { color: var(--midnight-navy); border-bottom-color: rgba(0,0,0,0.05); }
        html.light-mode .global-footer address, html.light-mode .global-footer .footer-contact a, html.light-mode .global-footer .footer-links a, html.light-mode .global-footer .sla-label { color: #475569; }
        html.light-mode .global-footer .sla-item { background: #FFFFFF; border-color: rgba(0,0,0,0.05); }
        html.light-mode .global-footer .footer-links a:hover { color: var(--midnight-navy); }
        html.light-mode .global-footer .footer-logo .tagline { opacity: 0.7; }
        html.light-mode .global-footer .footer-bottom { border-top-color: rgba(0,0,0,0.05); }

        /* --- MOBILE ARCHITECTURE --- */
        .mobile-menu-btn { 
            display: none; background: transparent; border: 1px solid var(--border-color);
            color: var(--text-primary); padding: 8px 12px; border-radius: 4px; cursor: pointer;
            transition: all 0.3s ease; z-index: 1001; margin-left: auto; margin-right: 15px;
        }
        html.light-mode .mobile-menu-btn { border-color: rgba(10, 17, 40, 0.2); }
        
        @media (max-width: 950px) {
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; }
            .nav-controls { gap: 15px; }
            .btn-small { display: none; } /* Move engage to mobile menu */
            
            .main-nav { 
                position: absolute; top: 100%; left: 0; width: 100%; 
                background: rgba(10, 17, 40, 0.98); backdrop-filter: blur(20px); 
                border-bottom: 2px solid var(--accent-color); box-shadow: 0 20px 40px rgba(0,0,0,0.5); 
                visibility: hidden; opacity: 0; transform: scaleY(0); transform-origin: top; 
                transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
            }
            html.light-mode .main-nav { background: rgba(255, 255, 255, 0.98); }
            .main-nav.open { visibility: visible; opacity: 1; transform: scaleY(1); }
            
            .nav-menu { flex-direction: column; gap: 0; padding: 0; height: auto; }
            .nav-item { padding: 18px 30px; width: 100%; border-bottom: 1px solid rgba(255,255,255,0.05); }
            html.light-mode .nav-item { border-bottom-color: rgba(0,0,0,0.05); }
            .nav-item::after { display: none; }
            
            .dropdown-content { 
                position: relative; top: 0; left: 0; transform: none !important; 
                box-shadow: none; background: transparent; border: none; padding: 0; 
                min-width: 100%; display: none; opacity: 1; visibility: visible; 
            }
            .nav-item:hover .dropdown-content, .nav-item:focus-within .dropdown-content { display: block; margin-top: 10px; }
            .dropdown-content a { padding: 12px 20px; border-left: 2px solid var(--accent-color); background: rgba(255,255,255,0.02); margin-bottom: 2px; }
            html.light-mode .dropdown-content a { background: rgba(0,0,0,0.02); border-left-color: var(--midnight-navy); }

            .footer-grid { grid-template-columns: 1fr; gap: 40px; }
            .footer-bottom { flex-direction: column; gap: 20px; text-align: center; }
            .social-links { justify-content: center; }
        }
        @stack('styles')
    </style>
    @livewireStyles
</head>
<body>

    <header class="global-header">
        <div class="container nav-container">
            <a href="/" class="logo" aria-label="{{ __('JBS Praxis Home') }}" wire:navigate>
                <img src="{{ asset('assets/img/logo_colour.svg') }}" alt="{{ __('JBS PRAXIS') }}" class="logo-light">
                <img src="{{ asset('assets/img/logo_monochrome.svg') }}" alt="{{ __('JBS PRAXIS') }}" class="logo-dark">
            </a>

            <nav class="main-nav">
                <ul class="nav-menu">
                    <li class="nav-item"><a href="/" wire:navigate>{{ __('Home') }}</a></li>
                    <li class="nav-item" tabindex="0">
                        {{ __('The Firm') }}
                        <div class="dropdown-content">
                            <a href="/about" wire:navigate>{{ __('The DNA & Strike Team') }}</a>
                            <a href="/careers" wire:navigate>{{ __('Join our Team') }}</a>
                        </div>
                    </li>
                    <li class="nav-item" tabindex="0">
                        {{ __('Services') }}
                        <div class="dropdown-content">
                            <a href="/services" wire:navigate>{{ __('Governance') }}</a>
                            <a href="/services#node-data" wire:navigate>{{ __('Data Architecture') }}</a>
                            <a href="/services#node-systems" wire:navigate>{{ __('Custom Systems') }}</a>
                            <a href="/services#node-resilience" wire:navigate>{{ __('Resilience') }}</a>
                        </div>
                    </li>
                    <li class="nav-item" tabindex="0">
                        {{ __('Portfolio') }}
                        <div class="dropdown-content">
                            <a href="/portfolio" wire:navigate>{{ __('Sanitized Case Studies') }}</a>
                        </div>  
                    </li>
                    <li class="nav-item" tabindex="0">
                        {{ __('Kaizen Hub') }}
                        <div class="dropdown-content">
                            <a href="/kaizen" wire:navigate>{{ __('Praxis Intelligence') }}</a>
                        </div>
                    </li>
                    <li class="nav-item" tabindex="0">
                        {{ __('Client Portal') }}
                        <div class="dropdown-content">
                            <a href="{{ route('client.login') }}" wire:navigate>{{ __('Live Sprint Tracker') }}</a>
                        </div>
                    </li>
                </ul>
            </nav>

            <button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="{{ __('Toggle Menu') }}" aria-expanded="false">
                <i data-lucide="menu" style="width: 20px; height: 20px;"></i>
            </button>

            <div class="nav-controls">
                <livewire:language-switcher />

                <button class="theme-toggle" onclick="toggleTheme()" aria-label="{{ __('Toggle Operating Mode') }}">
                    <svg class="theme-icon sun-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0 .39-.39.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0 .39-.39.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41.39.39 1.03.39 1.41 0l1.06-1.06zM7.05 18.36c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41.39.39 1.03.39 1.41 0l1.06-1.06z"/>
                    </svg>
                    <svg class="theme-icon moon-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36-.98 1.37-2.58 2.26-4.4 2.26-3.03 0-5.5-2.47-5.5-5.5 0-1.82.89-3.42 2.26-4.4C12.92 3.04 12.46 3 12 3z"/>
                    </svg>
                </button>

                <a href="/contact" class="btn btn-small" wire:navigate>{{ __('ENGAGE JBS') }}</a>
            </div>
        </div>
    </header>

    <main class="praxis-workspace">
        {{ $slot }}
    </main>

    <footer class="global-footer">
        <div class="container">
            <div class="footer-grid">
                
                <div class="footer-col">
                    <div class="footer-logo">
                        <span class="mono-font teal-text">{{ __('JBS PRAXIS') }}</span>
                        <p class="tagline">{{ __('Beyond Strategy...') }}</p>
                    </div>
                    
                    <div class="hq-block">
                        <p class="hq-label mono-font">{{ __('GLOBAL HQ (West Africa)') }}</p>
                        <address>21B Bode Ajakaiye Street, Lekki, Lagos, {{ __('Nigeria') }}</address>
                    </div>
                    
                    <div class="hq-block">
                        <p class="hq-label mono-font">{{ __('CEMAC REGIONAL DESK') }}</p>
                        <address>1030 Avenue Douala Manga Bell, Douala, {{ __('Cameroon') }}</address>
                    </div>
                    
                    <div class="footer-contact">
                        <a href="mailto:info@jbspraxis.com">info@jbspraxis.com</a>
                        <a href="mailto:intelligence@jbspraxis.com">intelligence@jbspraxis.com</a>
                        <a href="tel:+2348169789106" class="footer-tel mono-font">+234 816 978 9106</a>
                    </div>
                </div>

                <div class="footer-col">
                    <h5 class="mono-font">{{ __('INTERNAL ARCHITECTURE') }}</h5>
                    <ul class="footer-links">
                        <li><a href="/services" wire:navigate>{{ __('The Predictive Praxis Framework™') }}</a></li>
                        <li><a href="/kaizen" wire:navigate>{{ __('Praxis Intelligence (Kaizen Hub)') }}</a></li>
                        <li><a href="/feedback" wire:navigate>{{ __('Kaizen Telemetry Node (Your Feedback)') }}</a></li>
                        <li><a href="/privacy" wire:navigate>{{ __('Data Sovereignty & Privacy Architecture') }}</a></li>
                        <li><a href="/trust-center" wire:navigate>{{ __('Trust Center') }}</a></li>
                        <li><a href="/careers" wire:navigate>{{ __('Join the Strike Team') }}</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h5 class="mono-font">{{ __('START HERE') }}</h5>
                    <ul class="footer-links">
                        <li><a href="/schedule" wire:navigate>{{ __('Book a free audit & demo') }}</a></li>
                        <li><a href="/contact" wire:navigate>{{ __('Send us a message') }}</a></li>
                        <li><a href="{{ route('client.login') }}">{{ __('Client login') }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
            <div class="copyright">
                {{ __('© :year JBS Praxis. All rights reserved.', ['year' => date('Y')]) }}
            </div>
        </div>
        </div>
    </footer>
    
    @livewire('ai-agent')

    @livewireScripts
    <script>
        // Intelligent Theme Toggle with LocalStorage persistence
        function toggleTheme() {
            const html = document.documentElement;
            html.classList.toggle('light-mode');
            
            if (html.classList.contains('light-mode')) {
                localStorage.setItem('theme', 'light');
            } else {
                localStorage.setItem('theme', 'dark');
            }
        }

        // Accessible Mobile Menu
        function toggleMobileMenu() {
            const nav = document.querySelector('.main-nav');
            const btn = document.querySelector('.mobile-menu-btn');
            const icon = btn.querySelector('[data-lucide]');
            const isOpen = nav.classList.toggle('open');
            
            btn.setAttribute('aria-expanded', isOpen);
            
            // Swap icon dynamically
            if (isOpen) {
                icon.setAttribute('data-lucide', 'x');
            } else {
                icon.setAttribute('data-lucide', 'menu');
            }
            lucide.createIcons();
        }

        // Re-initialize Lucide icons on Livewire DOM mutations
        document.addEventListener('livewire:navigated', () => { 
            lucide.createIcons(); 
        });
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', ({ component, el }) => {
                lucide.createIcons();
            });
        });
        
    </script>
    
</body>
</html>