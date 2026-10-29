<div>
    @section('dynamic_schema')
    @php
        // Injecting standalone TechArticle Schema for Google's Knowledge Graph
        $articleSchema = [
            "@context" => "https://schema.org",
            "@type" => "TechArticle",
            "mainEntityOfPage" => [
                "@type" => "WebPage",
                "@id" => secure_url('/kaizen/' . $article->slug) // Forced HTTPS
            ],
            "headline" => $article->title,
            // Match the strict 150-char limit from the controller
            "description" => \Illuminate\Support\Str::limit(strip_tags($article->executive_summary), 150, '...'),
            "image" => asset('assets/img/jbs-og-banner-ls.png'), 
            "author" => [
                "@type" => "Person",
                "name" => trim($article->first_name . ' ' . $article->last_name),
                "jobTitle" => $article->job_title ?? 'Systems Architect',
                "url" => secure_url('/') // Added to satisfy Google's author entity requirements
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => "JBS Praxis",
                "url" => secure_url('/'), // CRITICAL: Organization must have a URL
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => asset('assets/img/logo_colour.svg')
                ]
            ],
            "datePublished" => $article->created_at->toIso8601String(),
            "dateModified" => $article->updated_at->toIso8601String(),
            "articleSection" => $article->category
        ];
    @endphp
    <script type="application/ld+json">
        {!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
    @endsection

    @push('styles')
    <style>
        /* Standalone Article CSS Architecture */
        .immersive-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; pointer-events: none; z-index: -10; background-image: url('{{ asset("assets/img/hero_home.webp") }}'); background-size: cover; background-position: center; filter: grayscale(100%) brightness(0.2); }
        html.light-mode .immersive-bg { filter: grayscale(100%) brightness(1) opacity(0.3); }
        
        .reading-progress { position: fixed; top: var(--header-height, 80px); left: 0; height: 3px; background: var(--accent-color); width: 0%; z-index: 1000; transition: width 0.1s; }
        html.light-mode .reading-progress { background: var(--midnight-navy); }
        
        .reader-container { max-width: 1100px; margin: 0 auto; background: var(--bg-main); min-height: 100vh; padding: 100px 80px; position: relative; display: grid; grid-template-columns: 250px 1fr; gap: 80px; box-shadow: 0 0 80px rgba(0,0,0,0.8); }
        html.light-mode .reader-container { background: #FFFFFF; box-shadow: 0 0 80px rgba(10,17,40,0.1); }
        
        .reader-toc { position: sticky; top: 120px; height: fit-content; border-left: 2px solid rgba(255,255,255,0.05); padding-left: 20px; }
        html.light-mode .reader-toc { border-left-color: rgba(10,17,40,0.1); }
        .toc-title { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 20px; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; }
        .toc-list { display: flex; flex-direction: column; gap: 15px; }
        .toc-link { font-size: 0.85rem; color: var(--text-secondary); cursor: pointer; transition: all 0.3s ease; font-weight: 500; text-decoration: none; }
        .toc-link:hover, .toc-link.active { color: var(--accent-color); transform: translateX(8px); }
        html.light-mode .toc-link:hover, html.light-mode .toc-link.active { color: var(--midnight-navy); }

        .reader-content { max-width: 680px; margin: 0 auto; width: 100%; }
        .reader-header { margin-bottom: 50px; padding-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        html.light-mode .reader-header { border-bottom-color: rgba(10,17,40,0.1); }
        .reader-meta { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--accent-color); margin-bottom: 20px; display: block; font-weight: bold; letter-spacing: 1px; }
        html.light-mode .reader-meta { color: var(--midnight-navy); }
        .reader-title { font-size: clamp(2rem, 4vw, 3rem); line-height: 1.1; margin-bottom: 30px; color: var(--stark-white); font-weight: 900; letter-spacing: -1px; }
        html.light-mode .reader-title { color: var(--midnight-navy); }
        
        .card-author-block { display: flex; align-items: center; gap: 15px; }
        .author-avatar { width: 50px; height: 50px; background: rgba(0, 229, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--accent-color); font-family: 'Roboto Mono', monospace; border: 1px solid rgba(0, 229, 255, 0.2); font-size: 1.2rem; }
        html.light-mode .author-avatar { background: rgba(10, 17, 40, 0.05); color: var(--midnight-navy); border-color: rgba(10, 17, 40, 0.1); }
        .author-name { font-size: 0.95rem; font-weight: 700; color: var(--stark-white); display: flex; align-items: center; gap: 5px; }
        html.light-mode .author-name { color: var(--midnight-navy); }
        .author-title { font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px; }

        .article-body h3 { font-size: 1.6rem; color: var(--stark-white); margin: 60px 0 25px 0; padding-bottom: 15px; border-bottom: 2px solid rgba(255,255,255,0.05); letter-spacing: -0.5px; }
        html.light-mode .article-body h3 { color: var(--midnight-navy); border-bottom-color: rgba(10,17,40,0.1); }
        .article-body p { font-size: 1.15rem; line-height: 1.8; color: #cbd5e1; margin-bottom: 25px; }
        html.light-mode .article-body p { color: #334155; }

        .article-hook { margin-top: 80px; padding: 50px; background: rgba(0, 229, 255, 0.02); border: 1px solid rgba(0, 229, 255, 0.1); border-radius: 8px; text-align: center; }
        html.light-mode .article-hook { background: rgba(10, 17, 40, 0.02); border-color: rgba(10, 17, 40, 0.15); }
        
        .seo-matrix-link { color: var(--luminous-teal); text-decoration: none; border-bottom: 1px dashed rgba(0, 229, 255, 0.4); font-weight: 600; transition: all 0.3s ease; }
        .seo-matrix-link:hover { color: var(--stark-white); border-bottom: 1px solid var(--luminous-teal); text-shadow: 0 0 10px rgba(0, 229, 255, 0.3); }
        html.light-mode .seo-matrix-link { color: var(--midnight-navy); border-bottom-color: rgba(10, 17, 40, 0.4); }
        html.light-mode .seo-matrix-link:hover { color: var(--accent-color); border-bottom: 1px solid var(--midnight-navy); text-shadow: none; }

        .back-nav { display: inline-flex; align-items: center; gap: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--text-secondary); text-decoration: none; margin-bottom: 30px; transition: color 0.3s ease; }
        .back-nav:hover { color: var(--accent-color); }

        @media (max-width: 950px) {
            .reader-container { grid-template-columns: 1fr; padding: 40px 20px; gap: 0; }
            .reader-toc { display: none; }
            .reader-title { font-size: 2rem; }
            .article-hook { padding: 30px 20px; }
        }
    </style>
    @endpush

    <div class="immersive-bg"></div>
    <div class="reading-progress" id="progressBar"></div>

    <div class="reader-container">
        
        <div class="reader-toc">
            <a href="/kaizen" wire:navigate class="back-nav">< Back to Hub</a>
            <div class="toc-title">> ARCHITECTURE INDEX</div>
            <div class="toc-list">
                <a href="#sec-summary" class="toc-link">The Executive Summary</a>
                <a href="#sec-problem" class="toc-link">Operational Vulnerability</a>
                <a href="#sec-solution" class="toc-link">Engineered Solution</a>
                <a href="#sec-roi" class="toc-link">The Final ROI</a>
            </div>
        </div>

        <div class="reader-content">
            <div class="reader-header">
                <span class="reader-meta">{{ strtoupper($article->category) }} | ⏱ {{ $article->read_time_minutes }} MINUTE READ</span>
                <h1 class="reader-title">{{ $article->title }}</h1>
                
                <div class="card-author-block">
                    <div class="author-avatar">{{ substr($article->first_name, 0, 1) }}</div>
                    <div class="author-details">
                        <div class="author-name">
                            {{ trim($article->first_name . ' ' . $article->last_name) }}
                            <span title="JBS Praxis Verified Contributor" style="font-size: 1rem;">🏅</span>
                        </div>
                        <div class="author-title">{{ explode('|', $article->job_title ?? 'Systems Architect')[0] }}</div>
                    </div>
                </div>
            </div>

            <div class="article-body">
                <h3 id="sec-summary">The Executive Summary</h3>
                <p>{!! nl2br($article->executive_summary) !!}</p>

                <h3 id="sec-problem">The Operational Vulnerability</h3>
                <p>{!! nl2br($article->problem_statement) !!}</p>

                <h3 id="sec-solution">The Engineered Solution</h3>
                <p>{!! nl2br($article->engineered_solution) !!}</p>

                <h3 id="sec-roi">The Final ROI</h3>
                <p>{!! nl2br($article->final_roi) !!}</p>
            </div>

            <div class="article-hook">
                <h4 class="terminal-teal-text mono-font" style="justify-content: center; font-size: 1rem; margin-bottom: 10px;">
                    <span class="pulse-dot"></span> <strong>> ENGINEER THIS REALITY</strong>
                </h4>
                <p style="font-size: 1.05rem; color: var(--text-secondary); margin-bottom: 25px;">Did this insight expose a vulnerability in your own enterprise? Do not digitize confusion. Eliminate it.</p>
                <a href="/schedule" wire:navigate class="btn-outline">INITIATE DIAGNOSTIC AUDIT</a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Smooth scroll spy for the progress bar and TOC highlighting
        document.addEventListener('scroll', function() {
            const winScroll = document.documentElement.scrollTop || document.body.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = height > 0 ? (winScroll / height) * 100 : 0;
            const progressBar = document.getElementById('progressBar');
            if(progressBar) progressBar.style.width = scrolled + "%";

            const sections = ['sec-summary', 'sec-problem', 'sec-solution', 'sec-roi'];
            const links = document.querySelectorAll('.toc-link');
            
            let currentActive = 0;
            sections.forEach((sec, index) => {
                const element = document.getElementById(sec);
                if (element && winScroll >= (element.offsetTop - 150)) {
                    currentActive = index;
                }
            });

            links.forEach(l => l.classList.remove('active'));
            if(links[currentActive]) {
                links[currentActive].classList.add('active');
            }
        });

        // Smooth scroll for TOC links
        document.querySelectorAll('.toc-link').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100, // Offset for your sticky header
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
    @endpush
</div>