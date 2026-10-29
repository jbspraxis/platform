@section('dynamic_schema')
@php
    // We construct the schema cleanly in PHP to mathematically guarantee zero Blade parsing errors
    $blogPosts = [];
    
    if(isset($dbArticles)) {
        foreach($dbArticles as $art) {
            $blogPosts[] = [
                "@type" => "TechArticle",
                "headline" => $art['title'] ?? '',
                "author" => [
                    "@type" => "Person",
                    "name" => $art['author'] ?? '',
                    "jobTitle" => $art['designation'] ?? ''
                ],
                "description" => $art['summary'] ?? '',
                "articleSection" => $art['category'] ?? ''
            ];
        }
    }

    $schema = [
        "@context" => "https://schema.org",
        "@type" => "Blog",
        "headline" => "Praxis Intelligence - The Kaizen Hub",
        "description" => "An open-source, curated intelligence network for the global enterprise community.",
        "publisher" => [
            "@type" => "Organization",
            "name" => "JBS Praxis",
            "url" => secure_url('/'), // CRITICAL FIX: Base URL required for Organization Schema
            "logo" => [
                "@type" => "ImageObject",
                "url" => asset('assets/img/logo_colour.svg')
            ]
        ],
        "blogPost" => $blogPosts
    ];
@endphp

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endsection
<div x-data="{ isUploadOpen: @entangle('isUploadOpen'), step: 1 }" x-init="$nextTick(() => { window.renderGrid(); })" id="kaizen-hub">
    @push('styles')
    <style>
        /* ==========================================================================
           JBS PRAXIS - KAIZEN HUB MASTER CSS ARCHITECTURE 
           ========================================================================== */

        /* --- GLOBAL ANIMATIONS & UTILITIES --- */
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
        @keyframes fadeDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .pulse-dot { width: 6px; height: 6px; background-color: var(--luminous-teal); border-radius: 50%; box-shadow: 0 0 10px var(--luminous-teal); animation: pulse 2s infinite; display: inline-block; }
        .terminal-teal-text { color: var(--luminous-teal); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.85rem; letter-spacing: 2px; margin-bottom: 15px; }
        .terminal-teal-text strong { letter-spacing: 2px; font-weight: 800; }

        html.light-mode .terminal-teal-text, html.light-mode .pulse-dot { color: #2D6A4F; }
        html.light-mode .pulse-dot { background-color: #2D6A4F; box-shadow: 0 0 10px rgba(45, 106, 79, 0.4); }

        /* Unified Button Architecture */
        .terminal-action-btn { display: inline-block; padding: 16px 24px; background-color: var(--luminous-teal) !important; color: var(--midnight-navy) !important; font-weight: bold; font-family: 'Inter', sans-serif; font-size: 0.9rem; cursor: pointer; border: none; border-radius: 4px; transition: all 0.3s ease; text-decoration: none; text-transform: uppercase; text-align: center; }
        .terminal-action-btn:hover { background-color: var(--stark-white) !important; box-shadow: 0 0 20px rgba(255, 255, 255, 0.4); transform: translateY(-2px); }
        
        .btn-outline { background: transparent; border: 1px solid var(--accent-color); color: var(--accent-color); text-decoration: none; padding: 16px 35px; display: inline-flex; align-items: center; justify-content: center; gap: 15px; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; transition: all 0.3s ease; border-radius: 2px; cursor: pointer; backdrop-filter: blur(10px); }
        .btn-outline:hover { background: var(--accent-color); color: var(--midnight-navy); box-shadow: 0 0 30px rgba(0, 229, 255, 0.4); }
        html.light-mode .btn-outline { border-color: var(--midnight-navy); color: var(--midnight-navy); }
        html.light-mode .btn-outline:hover { background-color: var(--midnight-navy); color: var(--stark-white); }
        
        .prompt-arrow { color: var(--luminous-teal); animation: blink 1s infinite; }
        .btn-outline:hover .prompt-arrow { color: var(--midnight-navy); }

        /* --- SECTION 1: HERO --- */
        .hero-section { position: relative; height: 100vh; display: flex; align-items: center; justify-content: center; padding-top: 80px; overflow: visible; }
        .hero-bg, .scanline-overlay, .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .hero-bg { background-image: url('{{ asset("assets/img/hero_home.webp") }}'); background-size: cover; background-position: center; z-index: -3; }
        .hero-overlay { background: linear-gradient(180deg, rgba(10,17,40,0.8) 0%, var(--midnight-navy) 100%); z-index: -2; }
        html.light-mode .hero-overlay { background: linear-gradient(180deg, rgba(255,255,255,0.9) 0%, var(--stark-white) 100%); }
        .scanline-overlay { background: linear-gradient(to bottom, transparent 50%, rgba(0, 229, 255, 0.03) 51%); background-size: 100% 4px; z-index: -1; pointer-events: none; }
        
        .hero-content { position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; max-width: 900px; width: 100%; text-align: center; }
        .tech-badge { display: inline-flex; align-items: center; gap: 8px; font-size: 0.65rem; font-weight: 700; letter-spacing: 2px; margin-bottom: 15px; padding: 5px 12px; background: rgba(0, 229, 255, 0.05); border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 20px; color: var(--luminous-teal); }
        html.light-mode .tech-badge { color: #1E3A8A; border-color: rgba(30, 58, 138, 0.2); }
        
        .brand-title { font-size: clamp(3rem, 6vw, 5.5rem); font-weight: 900; letter-spacing: -2px; line-height: 1.1; margin-bottom: 5px; background: linear-gradient(to right, #FFFFFF, #8A9bb2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        html.light-mode .brand-title { background: linear-gradient(to right, var(--midnight-navy), #4A5568); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .brand-slogan { font-size: clamp(1rem, 2vw, 1.5rem); color: var(--luminous-teal); letter-spacing: 4px; margin-bottom: 25px; font-weight: 400; text-transform: uppercase; }
        html.light-mode .brand-slogan { color: var(--accent-color); }
        .hero-desc { font-size: clamp(0.95rem, 1.2vw, 1.1rem); max-width: 750px; margin-bottom: 40px; padding: 0 15px; color: var(--text-secondary); line-height: 1.6; }

        /* THE COMMAND NEXUS CTA */
        .nexus-wrapper { position: relative; perspective: 1000px; display: flex; flex-direction: column; align-items: center; z-index: 100; }
        .nexus-matrix { position: absolute; top: calc(100% + 15px); left: 50%; transform: translateX(-50%) translateY(10px); opacity: 0; visibility: hidden; background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(0, 229, 255, 0.2); border-top: 2px solid var(--accent-color); border-radius: 6px; padding: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); transition: all 0.3s ease; z-index: 100; }
        html.light-mode .nexus-matrix { background: rgba(255, 255, 255, 0.95); border-color: rgba(10, 17, 40, 0.1); border-top-color: var(--midnight-navy); }
        .nexus-wrapper:hover .nexus-matrix, .nexus-wrapper:focus-within .nexus-matrix { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }

        .compact-grid { display: flex; gap: 10px; }
        .compact-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 12px 20px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; color: var(--stark-white); }
        html.light-mode .compact-card { background: rgba(0,0,0,0.02); border-color: rgba(0,0,0,0.05); color: var(--midnight-navy); }
        .compact-card:hover { background: rgba(0, 229, 255, 0.08); border-color: var(--accent-color); }
        html.light-mode .compact-card:hover { background: rgba(10,17,40,0.05); border-color: var(--midnight-navy); }
        .highlight-card { background: var(--accent-color); color: var(--midnight-navy); border: none; }
        .highlight-card:hover { background: var(--stark-white); color: var(--midnight-navy); }
        html.light-mode .highlight-card { background: var(--midnight-navy); color: var(--stark-white); }
        html.light-mode .highlight-card:hover { background: var(--accent-color); color: var(--midnight-navy); }

        /* --- TELEMETRY SEARCH BAR --- */
        .telemetry-bar-wrapper { padding: 50px 20px 0 20px; position: relative; z-index: 50; }
        .telemetry-bar { background: rgba(10, 17, 40, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 8px; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; gap: 20px; max-width: 1000px; margin: 0 auto; box-shadow: 0 30px 60px rgba(0,0,0,0.6); }
        html.light-mode .telemetry-bar { background: rgba(255, 255, 255, 0.95); border-color: rgba(10,17,40,0.1); box-shadow: 0 20px 50px rgba(10,17,40,0.08); }
        
        .search-wrapper { flex: 1; display: flex; align-items: center; }
        .search-prompt { font-family: 'Roboto Mono', monospace; color: var(--accent-color); font-weight: bold; margin-right: 15px; font-size: 1.2rem; }
        html.light-mode .search-prompt { color: var(--midnight-navy); }
        .search-input { width: 100%; background: transparent; border: none; color: var(--stark-white); font-family: 'Roboto Mono', monospace; font-size: 1rem; outline: none; }
        html.light-mode .search-input { color: var(--midnight-navy); }
        .search-input::placeholder { color: var(--text-secondary); opacity: 0.5; }
        
        .filter-controls { display: flex; gap: 10px; }
        .filter-btn { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); color: var(--text-secondary); padding: 10px 20px; border-radius: 4px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; cursor: pointer; transition: all 0.3s ease; }
        html.light-mode .filter-btn { background: rgba(0,0,0,0.03); border-color: rgba(10,17,40,0.05); }
        .filter-btn:hover, .filter-btn.active { background: rgba(0, 229, 255, 0.1); color: var(--accent-color); border-color: var(--accent-color); }
        html.light-mode .filter-btn:hover, html.light-mode .filter-btn.active { background: rgba(10, 17, 40, 0.05); color: var(--midnight-navy); border-color: var(--midnight-navy); }

        /* --- ARTICLE GRID --- */
        .hub-grid-section { padding: 60px 0 150px 0; min-height: 50vh; }
        .article-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 40px; }
        
        .article-card { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 40px; cursor: pointer; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); position: relative; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        html.light-mode .article-card { background: #FFFFFF; border-color: rgba(10,17,40,0.08); box-shadow: 0 10px 25px rgba(10,17,40,0.03); }
        .article-card::before { content: ''; position: absolute; top: 0; left: 0; width: 3px; height: 0%; background: var(--accent-color); transition: height 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        html.light-mode .article-card::before { background: var(--midnight-navy); }
        .article-card:hover { transform: translateY(-8px); box-shadow: 0 30px 60px rgba(0,0,0,0.6); border-color: rgba(0, 229, 255, 0.3); background: rgba(0, 229, 255, 0.02); }
        html.light-mode .article-card:hover { box-shadow: 0 25px 50px rgba(10,17,40,0.08); border-color: rgba(10,17,40,0.15); background: rgba(10, 17, 40, 0.01); }
        .article-card:hover::before { height: 100%; }

        .card-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--text-secondary); letter-spacing: 1px; }
        .card-category { background: rgba(255,255,255,0.05); padding: 6px 12px; border-radius: 4px; color: var(--stark-white); font-weight: bold; text-transform: uppercase; }
        html.light-mode .card-category { background: rgba(10,17,40,0.05); color: var(--midnight-navy); }
        .card-title { font-size: 1.3rem; line-height: 1.4; margin-bottom: 15px; color: var(--stark-white); font-weight: 800; letter-spacing: -0.5px; }
        html.light-mode .card-title { color: var(--midnight-navy); }
        .card-summary { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 30px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        html.light-mode .card-summary { color: #475569; }
        
        .card-author-block { margin-top: auto; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 15px; }
        html.light-mode .card-author-block { border-top-color: rgba(10,17,40,0.05); }
        .author-avatar { width: 40px; height: 40px; background: rgba(0, 229, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--accent-color); font-family: 'Roboto Mono', monospace; border: 1px solid rgba(0, 229, 255, 0.2); }
        html.light-mode .author-avatar { background: rgba(10, 17, 40, 0.05); color: var(--midnight-navy); border-color: rgba(10, 17, 40, 0.1); }
        .author-details { display: flex; flex-direction: column; gap: 3px; }
        .author-name { font-size: 0.85rem; font-weight: 700; color: var(--stark-white); display: flex; align-items: center; gap: 5px; }
        html.light-mode .author-name { color: var(--midnight-navy); }
        .author-title { font-size: 0.7rem; color: var(--text-secondary); }

        /* --- FULL SCREEN READER OVERLAY --- */
        .reader-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(5, 9, 20, 0.95); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); z-index: 2000; display: none; opacity: 0; transition: opacity 0.4s ease; overflow-y: auto; padding: 0; }
        html.light-mode .reader-overlay { background: rgba(248, 250, 252, 0.95); } 
        .reader-overlay.active { display: block; opacity: 1; }
        
        .reading-progress { position: fixed; top: 0; left: 0; height: 3px; background: var(--accent-color); width: 0%; z-index: 2005; transition: width 0.1s; }
        html.light-mode .reading-progress { background: var(--midnight-navy); }
        
        .reader-container { max-width: 1100px; margin: 0 auto; background: var(--bg-main); min-height: 100vh; padding: 100px 80px; position: relative; display: grid; grid-template-columns: 250px 1fr; gap: 80px; box-shadow: 0 0 80px rgba(0,0,0,0.8); }
        html.light-mode .reader-container { background: #FFFFFF; box-shadow: 0 0 80px rgba(10,17,40,0.1); }
        
        .close-reader { position: absolute; top: 40px; right: 50px; font-size: 2.5rem; cursor: pointer; color: var(--text-secondary); transition: color 0.3s; line-height: 1; z-index: 100; }
        .close-reader:hover { color: var(--terminal-red); transform: scale(1.1); }

        .reader-toc { position: sticky; top: 100px; height: fit-content; border-left: 2px solid rgba(255,255,255,0.05); padding-left: 20px; }
        html.light-mode .reader-toc { border-left-color: rgba(10,17,40,0.1); }
        .toc-title { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 20px; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; }
        .toc-list { display: flex; flex-direction: column; gap: 15px; }
        .toc-link { font-size: 0.85rem; color: var(--text-secondary); cursor: pointer; transition: all 0.3s ease; font-weight: 500; }
        .toc-link:hover, .toc-link.active { color: var(--accent-color); transform: translateX(8px); }
        html.light-mode .toc-link:hover, html.light-mode .toc-link.active { color: var(--midnight-navy); }

        .reader-content { max-width: 680px; margin: 0 auto; }
        .reader-header { margin-bottom: 50px; padding-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        html.light-mode .reader-header { border-bottom-color: rgba(10,17,40,0.1); }
        .reader-meta { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--accent-color); margin-bottom: 20px; display: block; font-weight: bold; letter-spacing: 1px; }
        html.light-mode .reader-meta { color: var(--midnight-navy); }
        .reader-title { font-size: clamp(2rem, 4vw, 3rem); line-height: 1.1; margin-bottom: 30px; color: var(--stark-white); font-weight: 900; letter-spacing: -1px; }
        html.light-mode .reader-title { color: var(--midnight-navy); }
        
        .article-body h3 { font-size: 1.6rem; color: var(--stark-white); margin: 60px 0 25px 0; padding-bottom: 15px; border-bottom: 2px solid rgba(255,255,255,0.05); letter-spacing: -0.5px; }
        html.light-mode .article-body h3 { color: var(--midnight-navy); border-bottom-color: rgba(10,17,40,0.1); }
        .article-body p { font-size: 1.15rem; line-height: 1.8; color: #cbd5e1; margin-bottom: 25px; }
        html.light-mode .article-body p { color: #334155; }

        .article-hook { margin-top: 80px; padding: 50px; background: rgba(0, 229, 255, 0.02); border: 1px solid rgba(0, 229, 255, 0.1); border-radius: 8px; text-align: center; }
        html.light-mode .article-hook { background: rgba(10, 17, 40, 0.02); border-color: rgba(10, 17, 40, 0.15); }
        .article-hook h4 { font-size: 1.4rem; color: var(--stark-white); margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px; }
        html.light-mode .article-hook h4 { color: var(--midnight-navy); }

        /* --- CONTRIBUTOR UPLOAD DRAWER (Gamified Form) --- */
        .upload-drawer-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 2000; justify-content: flex-end; display: none; opacity: 0; transition: opacity 0.3s; }
        .upload-drawer-overlay.active { display: flex; opacity: 1; }
        
        .upload-drawer { width: 600px; max-width: 100vw; height: 100%; background: var(--bg-main); border-left: 1px solid var(--border-color); transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; position: relative; }
        html.light-mode .upload-drawer { background: var(--stark-white); border-left-color: rgba(10,17,40,0.1); }
        .upload-drawer-overlay.active .upload-drawer { transform: translateX(0); box-shadow: -20px 0 50px rgba(0,0,0,0.5); }
        
        .drawer-header { padding: 40px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
        html.light-mode .drawer-header { border-bottom-color: rgba(10,17,40,0.1); }
        .drawer-header h3 { font-size: 1.2rem; margin: 0; color: var(--stark-white); }
        html.light-mode .drawer-header h3 { color: var(--midnight-navy); }
        
        .drawer-body { padding: 40px; overflow-y: auto; flex: 1; }

        .terminal-label { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--stark-white); margin-bottom: 8px; display: block; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; }
        html.light-mode .terminal-label { color: var(--midnight-navy); }
        
        .custom-input, .custom-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); padding: 16px 20px; color: var(--stark-white); font-family: 'Inter', sans-serif; font-size: 0.95rem; border-radius: 4px; margin-bottom: 25px; outline: none; transition: all 0.3s ease; }
        html.light-mode .custom-input, html.light-mode .custom-textarea { background: rgba(0,0,0,0.02); color: var(--midnight-navy); border-color: rgba(10,17,40,0.2); }
        .custom-input:focus, .custom-textarea:focus { border-color: var(--accent-color); box-shadow: inset 0 0 10px rgba(0, 229, 255, 0.1); }
        html.light-mode .custom-input:focus, html.light-mode .custom-textarea:focus { border-color: var(--midnight-navy); box-shadow: inset 0 0 10px rgba(10, 17, 40, 0.05); }
        .custom-textarea { min-height: 120px; resize: vertical; line-height: 1.6; }
        .error-text { color: var(--terminal-red); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-top: -20px; margin-bottom: 20px; display: block;}

        /* THE SYSTEM LOG IN DRAWER */
        .system-log-console { margin-top: 10px; padding: 15px; background: rgba(0,0,0,0.4); border-left: 2px solid var(--accent-color); font-family: 'Roboto Mono', monospace; border-radius: 0 4px 4px 0; }
        html.light-mode .system-log-console { background: rgba(10, 17, 40, 0.05); border-left-color: var(--midnight-navy); }
        .log-text { font-size: 0.7rem; color: var(--accent-color); opacity: 0.8; transition: opacity 0.2s ease; }
        html.light-mode .log-text { color: var(--midnight-navy); font-weight: bold; }

        .loader-ring { width: 20px; height: 20px; border: 2px solid rgba(10, 17, 40, 0.3); border-top-color: var(--midnight-navy); border-radius: 50%; animation: spin 0.6s linear infinite; display: none; margin: 0 auto; }
        html.light-mode .loader-ring { border-color: rgba(255,255,255,0.3); border-top-color: var(--stark-white); }

        /* ==========================================================================
           MOBILE RESPONSIVE MASTER (ALIGNMENT & SPACING PERFECTION)
           ========================================================================== */
        @media (max-width: 950px) {
            .container { padding-left: 20px !important; padding-right: 20px !important; }
            
            /* Strict Left-Alignment for Telemetry Bar */
            .telemetry-bar-wrapper { padding: 20px 15px 0 15px; }
            .telemetry-bar { flex-direction: column; padding: 20px; width: 100%; align-items: stretch; }
            .search-wrapper { width: 100%; margin-bottom: 15px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 15px; justify-content: flex-start; }
            html.light-mode .search-wrapper { border-bottom-color: rgba(10,17,40,0.1); }
            .filter-controls { width: 100%; justify-content: space-between; flex-wrap: wrap; }
            .filter-btn { flex: 1; text-align: center; }
            
            .nexus-matrix { position: relative; top: 0; left: 0; transform: none; width: 100%; background: transparent; border: none; box-shadow: none; padding: 15px 0 0 0; display: none; }
            .nexus-wrapper:hover .nexus-matrix, .nexus-wrapper:focus-within .nexus-matrix, .nexus-wrapper:active .nexus-matrix { display: block; animation: fadeDown 0.3s ease forwards; }
            .compact-grid { flex-direction: column; width: 100%; }
            .compact-card { width: 100%; text-align: center; padding: 15px; }

            .hub-grid-section { padding: 40px 0 100px 0; }
            .article-grid { grid-template-columns: 1fr; gap: 20px; }
            
            /* Reader Overlay Fixes */
            .reader-container { grid-template-columns: 1fr; padding: 80px 20px 40px 20px; gap: 0; }
            .reader-toc { display: none; }
            .close-reader { top: 20px; right: 20px; font-size: 2.5rem; }
            .article-hook { padding: 30px 20px; }
            .reader-title { font-size: 2rem; }
            
            .upload-drawer { width: 100%; }
            .drawer-header, .drawer-body { padding: 30px 20px; }
        }
        /* Ensure the nexus-matrix is interactable and stays open */
            .nexus-wrapper:hover .nexus-matrix {
                opacity: 1 !important;
                visibility: visible !important;
                transform: translateX(-50%) translateY(0) !important;
                pointer-events: all !important;
            }
            
            .nexus-wrapper::after {
                content: '';
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                height: 20px; /* Adjust based on the gap */
                display: block;
            }
            
            /* --- PHASE 5: SEO VELOCITY LINKS --- */
            .seo-matrix-link {
                color: var(--luminous-teal);
                text-decoration: none;
                border-bottom: 1px dashed rgba(0, 229, 255, 0.4);
                font-weight: 600;
                transition: all 0.3s ease;
            }
            .seo-matrix-link:hover {
                color: var(--stark-white);
                border-bottom: 1px solid var(--luminous-teal);
                text-shadow: 0 0 10px rgba(0, 229, 255, 0.3);
            }
            html.light-mode .seo-matrix-link {
                color: var(--midnight-navy);
                border-bottom-color: rgba(10, 17, 40, 0.4);
            }
            html.light-mode .seo-matrix-link:hover {
                color: var(--accent-color);
                border-bottom: 1px solid var(--midnight-navy);
                text-shadow: none;
            }
    </style>
    @endpush

    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="scanline-overlay"></div>
        <div class="hero-overlay"></div>
        
        <div class="container hero-content text-center">
            <div class="tech-badge mono-font">
                <span class="pulse-dot"></span> // CONTINUOUS IMPROVEMENT
            </div>
            
            <h1 class="brand-title">PRAXIS INTELLIGENCE</h1>
            <h2 class="brand-slogan mono-font">THE KAIZEN HUB</h2>
            
            <p class="hero-desc">An open-source, curated intelligence network for the global enterprise community. We alternate between Boardroom strategy and Server Room engineering. Read the architecture, or contribute your own.</p>
            
            <div class="nexus-wrapper">
                <button class="btn-outline">
                    ACCESS INTEL <span class="mono-font prompt-arrow">>_</span>
                </button>
                
                <div class="nexus-matrix">
                    <div class="compact-grid">
                        <div class="compact-card" onclick="document.querySelectorAll('.filter-btn')[1].click(); document.getElementById('telemetry-anchor').scrollIntoView({behavior: 'smooth'})">
                            The Boardroom
                        </div>
                        <div class="compact-card" onclick="document.querySelectorAll('.filter-btn')[2].click(); document.getElementById('telemetry-anchor').scrollIntoView({behavior: 'smooth'})">
                            The Server Room
                        </div>
                        <div class="compact-card highlight-card" 
                             @click.stop="$wire.set('isUploadOpen', true); document.body.style.overflow = 'hidden';">
                            Upload Insight
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="telemetry-bar-wrapper" id="telemetry-anchor">
        <div class="telemetry-bar">
            <div class="search-wrapper">
                <span class="search-prompt">>_</span>
                <input type="text" class="search-input" id="searchInput" placeholder="Search intelligence (e.g., PostgreSQL, Audit...)" onkeyup="filterArticles()">
            </div>
            <div class="filter-controls">
                <button class="filter-btn active" onclick="setCategoryFilter('All', this)">[ ALL_NODES ]</button>
                <button class="filter-btn" onclick="setCategoryFilter('The Boardroom', this)">[ THE_BOARDROOM ]</button>
                <button class="filter-btn" onclick="setCategoryFilter('The Server Room', this)">[ THE_SERVER_ROOM ]</button>
            </div>
        </div>
    </div>

    <section class="hub-grid-section">
        <div class="container article-grid" id="articleGrid">
            </div>
    </section>

    <div class="reader-overlay" id="readerOverlay" onclick="if(event.target === this) window.closeReader()">
        <div class="reading-progress" id="progressBar"></div>
        <div class="reader-container">
            <span class="close-reader" onclick="window.closeReader()">×</span>
            
            <div class="reader-toc">
                <div class="toc-title">> ARCHITECTURE INDEX</div>
                <div class="toc-list">
                    <a class="toc-link" onclick="window.scrollToSection('sec-summary')">The Executive Summary</a>
                    <a class="toc-link" onclick="window.scrollToSection('sec-problem')">Operational Vulnerability</a>
                    <a class="toc-link" onclick="window.scrollToSection('sec-solution')">Engineered Solution</a>
                    <a class="toc-link" onclick="window.scrollToSection('sec-roi')">The Final ROI</a>
                </div>
            </div>

            <div class="reader-content">
                <div class="reader-header">
                    <span class="reader-meta" id="readMeta">THE BOARDROOM | ⏱ 4 MINUTE READ</span>
                    <h1 class="reader-title" id="readTitle">Article Title</h1>
                    
                    <div class="card-author-block" style="border: none; padding-top: 0; margin-top: 0;">
                        <div class="author-avatar" id="readAvatar" style="width: 50px; height: 50px; font-size: 1.2rem;">T</div>
                        <div class="author-details">
                            <div class="author-name">
                                <span id="readAuthor">Author Name</span>
                                <span title="JBS Praxis Verified Contributor" style="font-size: 1rem;">🏅</span>
                            </div>
                            <div class="author-title" id="readDesignation" style="font-size: 0.8rem; margin-top: 2px;">Designation</div>
                        </div>
                    </div>
                </div>

                <div class="article-body">
                    <h3 id="sec-summary">The Executive Summary</h3>
                    <p id="readSummary">...</p>

                    <h3 id="sec-problem">The Operational Vulnerability</h3>
                    <p id="readProblem">...</p>

                    <h3 id="sec-solution">The Engineered Solution</h3>
                    <p id="readSolution">...</p>

                    <h3 id="sec-roi">The Final ROI</h3>
                    <p id="readRoi">...</p>
                </div>

                <div class="article-hook">
                    <h4 class="terminal-teal-text mono-font" style="justify-content: center; font-size: 1rem; margin-bottom: 10px;">
                        <span class="pulse-dot"></span> <strong>> ENGINEER THIS REALITY</strong>
                    </h4>
                    <p style="font-size: 1.05rem; color: var(--text-secondary); margin-bottom: 25px;">Did this insight expose a vulnerability in your own enterprise? Do not digitize confusion. Eliminate it.</p>
                    <a href="/contact" wire:navigate class="btn-outline">INITIATE DIAGNOSTIC AUDIT</a>
                </div>
            </div>
        </div>
    </div>

    <div class="upload-drawer-overlay" :class="isUploadOpen ? 'active' : ''" @click.self="$wire.set('isUploadOpen', false); document.body.style.overflow = 'auto'">
        <div class="upload-drawer">
            <div class="drawer-header">
                <h4 class="terminal-teal-text mono-font" style="margin: 0;">
                    <span class="pulse-dot"></span> <strong>> CONTRIBUTOR PROTOCOL</strong>
                </h4>
                <span style="font-size: 2.5rem; cursor: pointer; line-height: 1; color: var(--text-secondary);" @click="$wire.set('isUploadOpen', false); document.body.style.overflow = 'auto'">×</span>
            </div>
            
            <div class="drawer-body">
                @if($isSuccess)
                    <div style="text-align: center; padding-top: 60px; animation: fadeIn 0.5s ease;">
                        <div style="font-size: 4.5rem; color: var(--accent-color); margin-bottom: 20px; text-shadow: 0 0 30px rgba(0, 229, 255, 0.3);">✓</div>
                        <h3 style="margin-bottom: 15px; font-size: 1.5rem; text-transform: uppercase; color: var(--stark-white);">INSIGHT TRANSMITTED</h3>
                        <p style="color: var(--text-secondary); font-size: 1rem; line-height: 1.6; margin-bottom: 40px;">Thank you for your invaluable contribution. The JBS Praxis Intelligence Team will review your architecture. Upon approval, it will be published to the global network.</p>
                        <button type="button" class="btn-outline" style="width:100%;" @click="$wire.set('isUploadOpen', false); document.body.style.overflow = 'auto'; setTimeout(() => { $wire.set('isSuccess', false); step = 1; }, 500);">CLOSE PORTAL</button>
                    </div>
                @else
                    <form wire:submit.prevent="submitInsight" novalidate>
                        
                        <div x-show="step === 1" x-transition:enter="transition ease-out duration-400" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                            
                            <label class="terminal-label">FULL NAME *</label>
                            <input type="text" class="custom-input" wire:model="fullName" placeholder="e.g. John Doe" onfocus="updateDrawerLog('VERIFYING_ARCHITECT_IDENTITY')">
                            @error('fullName') <span class="error-text">> {{ $message }}</span> @enderror
                            
                            <label class="terminal-label">CORPORATE TITLE *</label>
                            <input type="text" class="custom-input" wire:model="title" placeholder="e.g. Chief Financial Officer" onfocus="updateDrawerLog('LOGGING_CORPORATE_DESIGNATION')">
                            @error('title') <span class="error-text">> {{ $message }}</span> @enderror
                            
                            <label class="terminal-label">CORPORATE EMAIL * (Hidden from public)</label>
                            <input type="email" class="custom-input" wire:model="email" placeholder="name@enterprise.com" onfocus="updateDrawerLog('ESTABLISHING_SECURE_COMM_CHANNEL')">
                            @error('email') <span class="error-text">> {{ $message }}</span> @enderror
                            
                            <label class="terminal-label">LINKEDIN URL *</label>
                            <input type="url" class="custom-input" wire:model="linkedinUrl" placeholder="https://linkedin.com/in/..." onfocus="updateDrawerLog('INITIATING_NETWORK_SCAN')">
                            @error('linkedinUrl') <span class="error-text">> {{ $message }}</span> @enderror
                            
                            <button type="button" class="btn-outline" style="width: 100%; justify-content: center; margin-top: 15px;" @click="step = 2">VERIFY CREDENTIALS ></button>
                        </div>

                        <div x-show="step === 2" style="display: none;" x-transition:enter="transition ease-out duration-400" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                            
                            <div style="display: flex; gap: 10px; margin-bottom: 30px;">
                                <button type="button" class="filter-btn {{ $category == 'The Boardroom' ? 'active' : '' }}" style="flex: 1;" wire:click="$set('category', 'The Boardroom')" onclick="updateDrawerLog('ROUTING_TO_BOARDROOM_INDEX')">BOARDROOM</button>
                                <button type="button" class="filter-btn {{ $category == 'The Server Room' ? 'active' : '' }}" style="flex: 1;" wire:click="$set('category', 'The Server Room')" onclick="updateDrawerLog('ROUTING_TO_SERVER_ROOM_INDEX')">SERVER ROOM</button>
                            </div>
                            @if($errors->any())
                                <div style="background: rgba(255,0,0,0.1); border: 1px solid var(--terminal-red); padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                                    <span class="error-text" style="margin: 0; color: var(--terminal-red);">
                                        > SYSTEM REJECTED: You have invalid data in Step 1 (Credentials). Please go back and correct it.
                                    </span>
                                </div>
                            @endif
                            <label class="terminal-label">ARTICLE TITLE *</label>
                            <input type="text" class="custom-input" wire:model="articleTitle" style="font-weight: bold; font-size: 1.1rem;" placeholder="The Title..." onfocus="updateDrawerLog('CREATING_DOCUMENT_HEADER')">
                            @error('articleTitle') <span class="error-text">> {{ $message }}</span> @enderror
                            
                            <label class="terminal-label">EXECUTIVE SUMMARY * (Max 50 words)</label>
                            <textarea class="custom-textarea" wire:model="execSummary" style="min-height: 80px;" placeholder="What is the core thesis?" onfocus="updateDrawerLog('RECORDING_EXECUTIVE_THESIS')"></textarea>
                            @error('execSummary') <span class="error-text">> {{ $message }}</span> @enderror

                            <label class="terminal-label">THE VULNERABILITY (The Problem) *</label>
                            <textarea class="custom-textarea" wire:model="vulnerability" placeholder="Describe the chaos or technical failure..." onfocus="updateDrawerLog('ANALYZING_SYSTEM_FAILURE')"></textarea>
                            @error('vulnerability') <span class="error-text">> {{ $message }}</span> @enderror
                            
                            <label class="terminal-label">THE SOLUTION (The Fix) *</label>
                            <textarea class="custom-textarea" wire:model="solution" placeholder="Describe the engineered architecture..." onfocus="updateDrawerLog('COMPILING_ENGINEERED_SOLUTION')"></textarea>
                            @error('solution') <span class="error-text">> {{ $message }}</span> @enderror

                            <label class="terminal-label">THE FINAL ROI *</label>
                            <input type="text" class="custom-input" wire:model="roi" placeholder="e.g. 40% latency reduction, $1M saved." onfocus="updateDrawerLog('CALCULATING_FINANCIAL_IMPACT')">
                            @error('roi') <span class="error-text">> {{ $message }}</span> @enderror
                            @if($errorMessage) <span class="error-text" role="alert">> {{ $errorMessage }}</span> @endif

                            <div style="display: flex; gap: 15px; margin-top: 15px;">
                                <button type="button" class="btn-outline" style="padding: 14px 20px; min-width: 60px;" @click="step = 1"><</button>
                                <button type="submit" class="terminal-action-btn" style="flex: 1; justify-content: center;">
                                    <span wire:loading.remove wire:target="submitInsight">TRANSMIT TO INTELLIGENCE</span>
                                    <span wire:loading wire:target="submitInsight">ENCRYPTING DATA...</span>
                                </button>
                            </div>
                        </div>

                        <div class="system-log-console">
                            <div id="drawerSystemLog" class="log-text">
                                > AWAITING CLEARANCE INPUT...
                            </div>
                        </div>

                    </form>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Data Array
        const intelligenceData = @json($dbArticles);

        let currentFilter = 'All';

        window.renderGrid = function(query = '') {
            const grid = document.getElementById('articleGrid');
            if(!grid) return;
            grid.innerHTML = '';

            const filtered = intelligenceData.filter(art => {
                const matchesCategory = currentFilter === 'All' || art.category === currentFilter;
                
                const searchString = `
                    ${art.title} 
                    ${art.author} 
                    ${art.category} 
                    ${art.summary} 
                    ${art.problem} 
                    ${art.solution} 
                    ${art.roi}
                `.toLowerCase();
                
                const matchesSearch = searchString.includes(query.toLowerCase());
                return matchesCategory && matchesSearch;
            });

            if(filtered.length === 0) {
                grid.innerHTML = `<p class="mono-font" style="color: var(--text-secondary); grid-column: 1/-1; text-align: center; padding: 60px 20px; font-size: 0.9rem;">> SYSTEM QUERY RETURNED NO MATCHING PROTOCOLS...</p>`;
                return;
            }

            filtered.forEach(art => {
                const avatarChar = art.author.charAt(0);
                const card = document.createElement('div');
                card.className = 'article-card';
                
                // PRAXIS OVERRIDE: Wrapping the entire card in an SEO-compliant anchor tag
                // Note: Using art.id here based on our temporary database fallback
                card.innerHTML = `
                    <a href="/kaizen/${art.slug}" wire:navigate style="display: block; text-decoration: none; color: inherit;">
                    <div class="card-meta">
                        <div class="card-meta">
                            <span class="card-category">${art.category.toUpperCase()}</span>
                            <span>⏱ ${art.time} MIN READ</span>
                        </div>
                        <h3 class="card-title">${art.title}</h3>
                        <p class="card-summary">${art.summary}</p>
                        <div class="card-author-block">
                            <div class="author-avatar">${avatarChar}</div>
                            <div class="author-details">
                                <div class="author-name">${art.author} <span title="Verified Praxis Contributor">🏅</span></div>
                                <div class="author-title">${art.designation.split('|')[0].trim()}</div>
                            </div>
                        </div>
                    </a>
                `;
                grid.appendChild(card);
            });
        };

        window.setCategoryFilter = function(category, btnElement) {
            currentFilter = category;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');
            window.filterArticles();
        };

        window.filterArticles = function() {
            const query = document.getElementById('searchInput').value;
            window.renderGrid(query);
        };

        window.openArticle = function(id) {
            const art = intelligenceData.find(a => a.id === id);
            if(!art) return;

            document.getElementById('readMeta').innerText = `${art.category.toUpperCase()} | ⏱ ${art.time} MINUTE READ`;
            document.getElementById('readTitle').innerText = art.title;
            document.getElementById('readAvatar').innerText = art.author.charAt(0);
            document.getElementById('readAuthor').innerText = art.author;
            document.getElementById('readDesignation').innerText = art.designation;
            
            document.getElementById('readSummary').innerHTML = art.summary;
            document.getElementById('readProblem').innerHTML = art.problem;
            document.getElementById('readSolution').innerHTML = art.solution;
            document.getElementById('readRoi').innerHTML = art.roi;

            const overlay = document.getElementById('readerOverlay');
            overlay.style.display = 'block';
            setTimeout(() => { overlay.classList.add('active'); }, 10);
            document.body.style.overflow = 'hidden';
            overlay.scrollTop = 0;
        };

        window.closeReader = function() {
            const overlay = document.getElementById('readerOverlay');
            overlay.classList.remove('active');
            setTimeout(() => { overlay.style.display = 'none'; }, 400);
            if(!document.querySelector('.upload-drawer-overlay').classList.contains('active')) {
                document.body.style.overflow = 'auto';
            }
        };

        const readerEl = document.getElementById('readerOverlay');
        if(readerEl) {
            readerEl.addEventListener('scroll', function() {
                const winScroll = this.scrollTop;
                const height = this.scrollHeight - this.clientHeight;
                const scrolled = height > 0 ? (winScroll / height) * 100 : 0;
                document.getElementById('progressBar').style.width = scrolled + "%";

                const links = document.querySelectorAll('.toc-link');
                links.forEach(l => l.classList.remove('active'));
                if(scrolled < 20) links[0].classList.add('active');
                else if(scrolled < 50) links[1].classList.add('active');
                else if(scrolled < 80) links[2].classList.add('active');
                else links[3].classList.add('active');
            });
        }

        window.scrollToSection = function(id) {
            const target = document.getElementById(id);
            const overlay = document.getElementById('readerOverlay');
            if(target && overlay) {
                overlay.scrollTo({ top: target.offsetTop - 80, behavior: 'smooth' });
            }
        };

        // The Terminal Gamification Engine for the Upload Drawer
        window.updateDrawerLog = function(message) {
            const log = document.getElementById('drawerSystemLog');
            if(!log) return;
            log.style.opacity = '0.4';
            setTimeout(() => {
                log.innerText = `> ${message}... OK.`;
                log.style.opacity = '1';
            }, 100);
        };

        window.submitDrawerFX = function(e) {
            const btn = e.currentTarget;
            const btnText = document.getElementById('drawerSubmitText');
            if(btnText) btnText.innerText = 'ENCRYPTING DATA...';
            updateDrawerLog('TRANSMITTING_TO_INTELLIGENCE_ROUTER');
        };
    </script>
</div>