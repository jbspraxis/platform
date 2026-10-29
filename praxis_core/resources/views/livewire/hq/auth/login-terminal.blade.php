<div style="height: 100vh; width: 100vw; overflow: hidden; background-color: #010308; color: #FFFFFF; font-family: 'Inter', sans-serif; position: relative;">
    
    <div style="position: absolute; inset: 0; background-image: url('https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=2000&auto=format&fit=crop'); background-size: cover; background-position: center; z-index: 0; filter: grayscale(50%) contrast(1.3) brightness(0.3) sepia(50%) hue-rotate(150deg);"></div>
    
    <div style="position: absolute; inset: 0; background: radial-gradient(circle at 30% 50%, rgba(0, 229, 255, 0.05) 0%, rgba(1, 3, 8, 0.95) 80%); z-index: 1;"></div>

    <canvas id="networkCanvas" wire:ignore style="position: absolute; inset: 0; z-index: 2; pointer-events: none;"></canvas>

    <style>
        /* Scanlines Overlay */
        .scanlines { position: absolute; inset: 0; z-index: 20; pointer-events: none; background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,0) 50%, rgba(0,0,0,0.1) 50%, rgba(0,0,0,0.1)); background-size: 100% 4px; opacity: 0.3; }
        
        /* Rotating HUD Core Rings */
        .hud-core { position: absolute; top: 50%; left: 35%; transform: translate(-50%, -50%); z-index: 1; pointer-events: none; display: flex; justify-content: center; align-items: center; opacity: 0.15; }
        .ring { position: absolute; border-radius: 50%; border: 1px solid #00E5FF; }
        .ring-1 { width: 600px; height: 600px; border-top: 2px solid transparent; border-left: 2px solid transparent; animation: spin 20s linear infinite; }
        .ring-2 { width: 500px; height: 500px; border-bottom: 2px dotted #00E5FF; border-right: 2px dashed #00E5FF; animation: spin-reverse 15s linear infinite; }
        .ring-3 { width: 400px; height: 400px; border: 1px solid rgba(0, 229, 255, 0.3); animation: pulse-ring 4s ease-in-out infinite; }
        
        /* Glitch Effect for Slogan */
        .glitch-text { font-family: 'Roboto Mono', monospace; font-size: 0.95rem; color: #00E5FF; text-transform: uppercase; font-weight: 800; letter-spacing: 4px; position: relative; display: inline-block; margin-top: 8px; margin-bottom: 1.5rem; }
        .glitch-text::before, .glitch-text::after { content: 'BEYOND STRATEGY'; position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0.8; }
        .glitch-text::before { color: #FF3366; z-index: -1; animation: glitch-anim-1 3s infinite linear alternate-reverse; }
        .glitch-text::after { color: #00E5FF; z-index: -2; animation: glitch-anim-2 2s infinite linear alternate-reverse; }

        /* Login Panel Specifics */
        .extreme-glass { background: rgba(5, 12, 25, 0.75); backdrop-filter: blur(50px); -webkit-backdrop-filter: blur(50px); border-left: 1px solid rgba(0, 229, 255, 0.3); box-shadow: -30px 0 80px rgba(0, 0, 0, 0.9), inset 2px 0 20px rgba(0, 229, 255, 0.05); }
        .radar-scanner { position: absolute; top: -100%; left: 0; width: 100%; height: 50%; background: linear-gradient(to bottom, transparent, rgba(0, 229, 255, 0.15), transparent); animation: radar-scan 4s linear infinite; pointer-events: none; z-index: 0; border-bottom: 1px solid rgba(0, 229, 255, 0.5); }
        .btn-authorize { position: relative; overflow: hidden; z-index: 1; transition: all 0.3s ease; }
        .btn-authorize::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent); transition: 0.5s; z-index: -1; }
        .btn-authorize:hover::before { left: 100%; }
        .btn-authorize:hover { box-shadow: 0 0 25px rgba(0, 229, 255, 0.6) !important; }
        
        /* Quote Transition */
        .quote-fade { transition: opacity 1s ease-in-out; }

        /* Keyframes */
        @keyframes spin { 100% { transform: rotate(360deg); } }
        @keyframes spin-reverse { 100% { transform: rotate(-360deg); } }
        @keyframes pulse-ring { 0%, 100% { transform: scale(1); opacity: 0.5; } 50% { transform: scale(1.05); opacity: 1; box-shadow: 0 0 30px rgba(0, 229, 255, 0.3); } }
        @keyframes radar-scan { 0% { top: -50%; } 100% { top: 150%; } }
        @keyframes blink { 50% { opacity: 0; } }
        @keyframes glitch-anim-1 { 0% { clip-path: inset(20% 0 80% 0); transform: translate(-2px, 1px); } 20% { clip-path: inset(60% 0 10% 0); transform: translate(2px, -1px); } 40% { clip-path: inset(40% 0 50% 0); transform: translate(-2px, 2px); } 60% { clip-path: inset(80% 0 5% 0); transform: translate(2px, -2px); } 80% { clip-path: inset(10% 0 70% 0); transform: translate(-1px, 1px); } 100% { clip-path: inset(30% 0 50% 0); transform: translate(1px, -1px); } }
        @keyframes glitch-anim-2 { 0% { clip-path: inset(10% 0 60% 0); transform: translate(2px, -1px); } 20% { clip-path: inset(30% 0 20% 0); transform: translate(-2px, 1px); } 40% { clip-path: inset(70% 0 10% 0); transform: translate(2px, -2px); } 60% { clip-path: inset(20% 0 50% 0); transform: translate(-2px, 2px); } 80% { clip-path: inset(50% 0 30% 0); transform: translate(1px, -1px); } 100% { clip-path: inset(5% 0 80% 0); transform: translate(-1px, 1px); } }
        
        /* Input Container & Icons */
        .input-group { transition: all 0.3s ease; }
        .input-group .input-icon { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .auth-input:hover { border-color: rgba(0, 229, 255, 0.4); background-color: rgba(0, 229, 255, 0.02); }
        .input-group:hover .input-icon { opacity: 1 !important; filter: drop-shadow(0 0 5px rgba(0, 229, 255, 0.5)); }
        .input-group:focus-within .input-icon { opacity: 1 !important; color: #FFFFFF !important; filter: drop-shadow(0 0 10px #00E5FF); }
        .input-group:focus-within .icon-left { transform: translateY(-50%) scale(1.1) !important; }
        .auth-input:focus { background-color: rgba(0, 229, 255, 0.05) !important; border-color: #00E5FF !important; box-shadow: 0 0 20px rgba(0, 229, 255, 0.2), inset 3px 0 0 #00E5FF !important; transform: translateX(4px); }
        
        .forgot-link { color: #00E5FF; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; text-decoration: none; opacity: 0.7; transition: all 0.3s ease; letter-spacing: 0.5px; display: inline-block; }
        .forgot-link:hover { opacity: 1; text-shadow: 0 0 10px rgba(0, 229, 255, 0.8); transform: translateX(-3px); }

        /* Dynamic Form Pane Transition */
        @keyframes terminal-swap {
            0% { opacity: 0; transform: translateY(15px) scale(0.98); filter: blur(5px); }
            100% { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        }
        .form-pane { animation: terminal-swap 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; width: 100%; }
        
        /* Recovery Mode Specific Styles */
        .recovery-input:focus { background-color: rgba(255, 189, 46, 0.05) !important; border-color: #FFBD2E !important; box-shadow: 0 0 20px rgba(255, 189, 46, 0.2), inset 3px 0 0 #FFBD2E !important; transform: translateX(4px); }
        .btn-warning-glow { background: transparent; border: 1px solid #FFBD2E; color: #FFBD2E; transition: all 0.3s ease; position: relative; overflow: hidden; z-index: 1; }
        .btn-warning-glow::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,189,46,0.3), transparent); transition: 0.5s; z-index: -1; }
        .btn-warning-glow:hover::before { left: 100%; }
        .btn-warning-glow:hover { background: #FFBD2E; color: #050C19; box-shadow: 0 0 25px rgba(255, 189, 46, 0.5) !important; }
    </style>

    <div class="scanlines"></div>

    <div style="position: relative; z-index: 10; display: flex; width: 100vw; height: 100vh;">
        
        <div wire:ignore style="width: 65%; display: flex; flex-direction: column; justify-content: space-between; padding: clamp(2rem, 4vw, 4rem); position: relative; overflow: hidden;">
            
            <div class="hud-core">
                <div class="ring ring-1"></div>
                <div class="ring ring-2"></div>
                <div class="ring ring-3"></div>
            </div>

            <div style="position: relative; z-index: 5;">
                <div style="display: flex; align-items: center; gap: 10px; font-size: 0.8rem; color: #00E5FF; letter-spacing: 3px; margin-bottom: 1.5rem; font-family: 'Roboto Mono', monospace; font-weight: 700;">
                    <span style="width: 10px; height: 10px; background-color: #00E5FF; border-radius: 50%; box-shadow: 0 0 15px #00E5FF; animation: pulse-ring 2s infinite;"></span> JBS PRAXIS COMMAND CENTER
                </div>
                <div id="clockDisplay" style="font-family: 'Roboto Mono', monospace; font-size: clamp(4rem, 7vw, 6rem); font-weight: 800; line-height: 1; letter-spacing: -2px; color: #FFFFFF; text-shadow: 0 0 40px rgba(0, 229, 255, 0.3);">00:00:00</div>
                <div id="dateDisplay" style="font-size: 1rem; text-transform: uppercase; letter-spacing: 4px; color: #00E5FF; margin-top: 1rem; font-weight: 600; opacity: 0.8;">Initializing...</div>
            </div>

            <div style="position: relative; z-index: 5; max-width: 700px; background: rgba(5, 12, 25, 0.4); border: 1px solid rgba(0, 229, 255, 0.1); border-left: 4px solid #00E5FF; padding: 2rem; border-radius: 4px; backdrop-filter: blur(10px); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                <div id="quoteText" class="quote-fade" style="font-size: 1.15rem; line-height: 1.6; margin-bottom: 1rem; color: #FFFFFF; font-weight: 300; font-style: italic;">"Loading System Directives..."</div>
                <div id="quoteAuthor" class="quote-fade" style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: #00E5FF; letter-spacing: 2px; text-transform: uppercase; font-weight: 700;">> SYSTEM</div>
            </div>
        </div>

        <div class="extreme-glass" style="background: rgba(5, 12, 25, 0.55);  width: 35%; height: 100vh; position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; padding: 0 2.5rem; box-sizing: border-box;">
    
            <div class="radar-scanner"></div>
            
            <div style="position: relative; z-index: 10; width: 100%; text-align: center; margin-top: 60px; display: flex; flex-direction: column; align-items: center;">
                <img src="/assets/img/logo_dark.png" alt="JBS PRAXIS" style="height: 95px; filter: drop-shadow(0 0 15px rgba(0, 229, 255, 0.4)); margin-bottom: 0; display: block;">
                <div class="glitch-text" style="margin-top: 10px;">BEYOND STRATEGY</div>
            </div>

            <div style="position: relative; z-index: 10; width: 100%; max-width: 400px; margin: auto 0;">
                
                <div style="text-align: center; margin-bottom: 2rem;">
                    @if($viewMode === 'login')
                        <div id="greetingDisplay" style="color: #FFFFFF; font-weight: 800; font-size: 1.1rem; letter-spacing: 1px; margin-bottom: 5px; text-transform: uppercase;">Identify Sequence</div>
                        <div wire:ignore id="locationDisplay" style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #00E5FF; opacity: 0.8;">> DETECTING NODE...</div>
                    @else
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 2rem; color: #FFBD2E; filter: drop-shadow(0 0 10px rgba(255, 189, 46, 0.5)); margin-bottom: 15px;"></i>
                        <div style="color: #FFBD2E; font-size: 1rem; font-family: 'Montserrat', sans-serif; font-weight: 900; letter-spacing: 2px; text-transform: uppercase;">Recovery Protocol</div>
                        <div style="color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; margin-top: 8px; line-height: 1.5;">Identify your node to request a zero-knowledge override token.</div>
                    @endif
                </div>

                @if($viewMode === 'login')
                    <form wire:submit="authenticate" class="form-pane" style="display: flex; flex-direction: column; gap: 1.5rem;">
                        
                        @error('auth') 
                            <div style="background: rgba(255, 51, 102, 0.1); border: 1px solid #FF3366; color: #FF3366; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: 700; text-align: center; border-radius: 4px; box-shadow: 0 0 15px rgba(255, 51, 102, 0.2);">
                                {{ $message }}
                            </div>
                        @enderror
                        
                        @error('client_redirect') 
                            <div style="background: rgba(255, 189, 46, 0.1); border: 1px solid #FFBD2E; color: #FFBD2E; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: 700; text-align: center; border-radius: 4px; box-shadow: 0 0 15px rgba(255, 189, 46, 0.2); line-height: 1.6;">
                                {{ $message }}<br><br>
                                <a href="{{ route('client.login') }}" style="color: #00E5FF; text-decoration: none; border-bottom: 1px dashed #00E5FF; padding-bottom: 2px; letter-spacing: 1px; transition: 0.3s;" onmouseover="this.style.color='#FFF'; this.style.borderColor='#FFF';" onmouseout="this.style.color='#00E5FF'; this.style.borderColor='#00E5FF';">
                                    > INITIATE SECURE CLIENT LOGIN
                                </a>
                            </div>
                        @enderror
                    
                        <div class="input-group" style="position: relative; width: 100%;">
                            <i class="fa-solid fa-user-shield input-icon icon-left" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #00E5FF; opacity: 0.5; z-index: 5; font-size: 0.9rem;"></i>
                            <input type="email" id="email" wire:model="email" class="auth-input" placeholder="CORPORATE ID" required autocomplete="username" style="box-sizing: border-box; width: 100%; padding: 14px 15px 14px 45px; background-color: rgba(0, 0, 0, 0.6); border: 1px solid rgba(0, 229, 255, 0.15); color: #FFFFFF; border-radius: 6px; font-size: 0.9rem; font-family: 'Roboto Mono', monospace; outline: none; letter-spacing: 1px; transition: all 0.3s ease;">
                            @error('email') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; display: block; margin-top: 6px; padding-left: 5px;">> {{ $message }}</span> @enderror
                        </div>
                        
                        <div class="input-group" style="position: relative; width: 100%;">
                            <i class="fa-solid fa-fingerprint input-icon icon-left" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #00E5FF; opacity: 0.5; z-index: 5; font-size: 0.9rem;"></i>
                            <input id="password" type="password" wire:model="password" class="auth-input" placeholder="CRYPTOGRAPHIC KEY" required autocomplete="current-password" style="box-sizing: border-box; width: 100%; padding: 14px 45px 14px 45px; background-color: rgba(0, 0, 0, 0.6); border: 1px solid rgba(0, 229, 255, 0.15); color: #FFFFFF; border-radius: 6px; font-size: 0.9rem; font-family: 'Roboto Mono', monospace; outline: none; letter-spacing: 3px; transition: all 0.3s ease;">
                            <i class="fa-solid fa-eye input-icon" onclick="let pwd = document.getElementById('password'); pwd.type = pwd.type === 'password' ? 'text' : 'password'; this.classList.toggle('fa-eye'); this.classList.toggle('fa-eye-slash');" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #00E5FF; opacity: 0.5; z-index: 5; cursor: pointer; font-size: 0.9rem;"></i>
                            @error('password') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; display: block; margin-top: 6px; padding-left: 5px;">> {{ $message }}</span> @enderror
                        </div>
                    
                        <div style="text-align: right; margin-top: -10px;">
                            <a href="#" wire:click.prevent="toggleView('recovery')" class="forgot-link">Forgot Cryptographic Key?</a>
                        </div>
                    
                        <button type="submit" class="btn-authorize" style="box-sizing: border-box; width: 100%; background: #00E5FF; color: #050C19; font-family: 'Roboto Mono', monospace; font-weight: 800; font-size: 0.9rem; padding: 14px; border: none; border-radius: 6px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 5px; text-transform: uppercase; letter-spacing: 1.5px; box-shadow: 0 0 15px rgba(0, 229, 255, 0.3);">
                            <span wire:loading.remove wire:target="authenticate">[ INITIALIZE LINK ] <i class="fa-solid fa-satellite-dish"></i></span>
                            <span wire:loading wire:target="authenticate"><i class="fa-solid fa-circle-notch fa-spin"></i> [ HANDSHAKING... ]</span>
                        </button>
                    </form>

                @else
                    <form wire:submit="initiateRecovery" class="form-pane" style="display: flex; flex-direction: column; gap: 1.5rem;">
                        
                        @if($recoveryMessage)
                            <div style="background: rgba(0, 255, 102, 0.05); border: 1px solid #00FF66; border-left: 4px solid #00FF66; color: #00FF66; padding: 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: 700; text-align: left; border-radius: 4px; box-shadow: 0 0 20px rgba(0, 255, 102, 0.1); line-height: 1.6;">
                                <i class="fa-solid fa-satellite-dish" style="margin-right: 8px;"></i> {!! str_replace('. ', '.<br><br>', $recoveryMessage) !!}
                            </div>
                        @else
                            <div class="input-group" style="position: relative; width: 100%;">
                                <i class="fa-solid fa-envelope input-icon icon-left" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #FFBD2E; opacity: 0.5; z-index: 5; font-size: 0.9rem;"></i>
                                <input type="email" wire:model="email" class="auth-input recovery-input" placeholder="CORPORATE ID" required style="box-sizing: border-box; width: 100%; padding: 14px 15px 14px 45px; background-color: rgba(0, 0, 0, 0.6); border: 1px solid rgba(255, 189, 46, 0.3); color: #FFFFFF; border-radius: 6px; font-size: 0.9rem; font-family: 'Roboto Mono', monospace; outline: none; letter-spacing: 1px; transition: all 0.3s ease;">
                                @error('email') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; display: block; margin-top: 6px; padding-left: 5px;">> {{ $message }}</span> @enderror
                            </div>

                            <button type="submit" class="btn-warning-glow" style="box-sizing: border-box; width: 100%; font-family: 'Roboto Mono', monospace; font-weight: 800; font-size: 0.9rem; padding: 14px; border-radius: 6px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 5px; text-transform: uppercase; letter-spacing: 1.5px;">
                                <span wire:loading.remove wire:target="initiateRecovery">[ TRANSMIT OVERRIDE ]</span>
                                <span wire:loading wire:target="initiateRecovery"><i class="fa-solid fa-circle-notch fa-spin"></i> [ TRANSMITTING... ]</span>
                            </button>
                        @endif

                        <div style="text-align: center; margin-top: 15px;">
                            <button type="button" wire:click.prevent="toggleView('login')" style="background: transparent; border: none; color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; cursor: pointer; transition: 0.3s; letter-spacing: 1px; border-bottom: 1px dashed #8A9BB2; padding-bottom: 2px;" onmouseover="this.style.color='#FFF'; this.style.borderColor='#FFF';" onmouseout="this.style.color='#8A9BB2'; this.style.borderColor='#8A9BB2';">
                                <i class="fa-solid fa-arrow-left" style="margin-right: 5px;"></i> ABORT PROTOCOL
                            </button>
                        </div>
                    </form>
                @endif
                
            </div>
            
            <div style="text-align: center; margin-top: 3rem; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; opacity: 0.5; letter-spacing: 1px;">
                ISO 27001 SECURED // JBS PRAXIS v1.0
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            /* --- 1. HUD CLOCK & GREETING --- */
            function updateTime() {
                const now = new Date();
                const clock = document.getElementById('clockDisplay');
                if(clock) {
                    clock.textContent = now.toLocaleTimeString('en-GB', { hour12: false });
                    document.getElementById('dateDisplay').textContent = now.toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

                    const hrs = now.getHours();
                    let greet = 'MORNING PROTOCOL';
                    if (hrs >= 12) greet = 'AFTERNOON PROTOCOL';
                    if (hrs >= 17) greet = 'EVENING PROTOCOL';
                    const greeting = document.getElementById('greetingDisplay');
                    if(greeting) greeting.textContent = greet;
                }
            }
            setInterval(updateTime, 1000);
            updateTime();

            /* --- 2. ORIGIN NODE TYPEWRITER --- */
            const locationEl = document.getElementById('locationDisplay');
            let originText = "> NODE: SECURED";
            try {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone; 
                let city = tz.split('/')[1] || tz;
                originText = `> NODE: ${city.replace('_', ' ').toUpperCase()}`;
            } catch(e) {}

            function typeWriterEffect(element, text, speed) {
                if(!element) return;
                element.innerHTML = "";
                let i = 0;
                function type() {
                    if (i < text.length) {
                        element.innerHTML += text.charAt(i);
                        i++;
                        setTimeout(type, speed);
                    } else {
                        element.innerHTML += '<span style="display: inline-block; width: 6px; height: 12px; background-color: #00E5FF; margin-left: 5px; animation: blink 1s step-end infinite;"></span>';
                    }
                }
                type();
            }
            typeWriterEffect(locationEl, originText, 50);
            setInterval(() => typeWriterEffect(locationEl, originText, 50), 10000);

            /* --- 3. THE 40 QUOTE MATRIX ENGINE --- */
            const quotes = [
                // Scrum & Agile
                { t: "Scrum is built upon by the daily inspection of the adaptation.", a: "Scrum Guide" },
                { t: "Working software is the primary measure of progress.", a: "Agile Manifesto" },
                { t: "Fail fast, learn faster. Iterate to perfection.", a: "Agile Principle" },
                { t: "Simplicity—the art of maximizing the amount of work not done—is essential.", a: "Agile Manifesto" },
                { t: "The best architectures, requirements, and designs emerge from self-organizing teams.", a: "Agile Principle" },
                { t: "Agile is not a process; it is a mindset.", a: "System Architecture" },
                { t: "Inspect and adapt. Transparency is the prerequisite to inspection.", a: "Scrum Pillar" },
                { t: "A Sprint is a container for all other events.", a: "Scrum Framework" },
                { t: "Value is delivered in increments, not promises.", a: "Product Management" },
                { t: "Stop starting, start finishing.", a: "Kanban Philosophy" },
                // Lean, Six Sigma, Kaizen, Poka-Yoke
                { t: "Kaizen means constant, unending improvement.", a: "Kaizen Doctrine" },
                { t: "Defects are not free. Somebody makes them, and gets paid for making them.", a: "W. Edwards Deming" },
                { t: "Poka-Yoke: Mistake-proofing the future before it happens.", a: "Shigeo Shingo" },
                { t: "Eliminate waste to amplify value.", a: "Lean Manufacturing" },
                { t: "You cannot manage what you cannot measure.", a: "Six Sigma Principle" },
                { t: "In God we trust, all others must bring data.", a: "W. Edwards Deming" },
                { t: "Standardization is the basis for continuous improvement and quality.", a: "Lean Principle" },
                { t: "Quality is not an act, it is a habit.", a: "Process Optimization" },
                { t: "Muda, Muri, Mura: Eliminate waste, overburden, and unevenness.", a: "Toyota Production System" },
                { t: "If you don't have time to do it right, when will you have time to do it over?", a: "Quality Assurance" },
                // IT, Tech & Architecture
                { t: "Talk is cheap. Show me the code.", a: "Linus Torvalds" },
                { t: "Complexity is the worst enemy of security.", a: "Cybersecurity Axiom" },
                { t: "There are only two hard things in Computer Science: cache invalidation and naming things.", a: "Phil Karlton" },
                { t: "Code is read much more often than it is written.", a: "Guido van Rossum" },
                { t: "First, solve the problem. Then, write the code.", a: "John Johnson" },
                { t: "Without an immutable audit trail, the truth is merely an opinion.", a: "Database Architecture" },
                { t: "Data is the new oil, but without a refinery, it is just a spill.", a: "Data Science Maxim" },
                { t: "Make it work, make it right, make it fast.", a: "Kent Beck" },
                { t: "Premature optimization is the root of all evil.", a: "Donald Knuth" },
                { t: "Any fool can write code that a computer can understand. Good programmers write code that humans can understand.", a: "Martin Fowler" },
                // JBS Praxis & Management
                { t: "Hope is not a strategy. Engineering is.", a: "JBS Praxis Architecture" },
                { t: "BEYOND STRATEGY. We execute the future.", a: "JBS Praxis Vision" },
                { t: "Code as Law. When rules are hardcoded, compliance is unavoidable.", a: "JBS Praxis Systems" },
                { t: "Precision. Alignment. Praxis.", a: "JBS Command Center" },
                { t: "Strategy without execution is hallucination.", a: "Executive Intelligence" },
                { t: "We do not predict the future; we build the infrastructure for it.", a: "JBS Praxis" },
                { t: "Management is doing things right; leadership is doing the right things.", a: "Peter Drucker" },
                { t: "Systemic problems require systemic architecture.", a: "JBS Praxis Doctrine" },
                { t: "Operational excellence is not an objective; it is our baseline.", a: "JBS Core Value" },
                { t: "Welcome to the Command Center.", a: "JBS Praxis Mainframe" }
            ];

            const qText = document.getElementById('quoteText');
            const qAuthor = document.getElementById('quoteAuthor');
            
            function changeQuote() {
                if(!qText) return;
                qText.style.opacity = 0;
                qAuthor.style.opacity = 0;
                
                setTimeout(() => {
                    const rQ = quotes[Math.floor(Math.random() * quotes.length)];
                    qText.textContent = `"${rQ.t}"`;
                    qAuthor.textContent = `> ${rQ.a}`;
                    qText.style.opacity = 1;
                    qAuthor.style.opacity = 1;
                }, 1000); 
            }
            
            changeQuote();
            setInterval(changeQuote, 10000);

            /* --- 4. TEAL DATA-STREAM PARTICLE NETWORK --- */
            const canvas = document.getElementById('networkCanvas');
            if(canvas) {
                const ctx = canvas.getContext('2d');
                let particlesArray;
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;

                window.addEventListener('resize', () => {
                    canvas.width = window.innerWidth;
                    canvas.height = window.innerHeight;
                    initParticles();
                });

                class Particle {
                    constructor() {
                        this.x = Math.random() * canvas.width;
                        this.y = Math.random() * canvas.height;
                        this.directionX = (Math.random() * 0.4) - 0.2;
                        this.directionY = (Math.random() * 0.4) - 0.2;
                        this.size = Math.random() * 2 + 0.5;
                    }
                    update() {
                        if (this.x > canvas.width || this.x < 0) this.directionX = -this.directionX;
                        if (this.y > canvas.height || this.y < 0) this.directionY = -this.directionY;
                        this.x += this.directionX;
                        this.y += this.directionY;
                    }
                    draw() {
                        ctx.beginPath();
                        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(0, 229, 255, 0.6)';
                        ctx.fill();
                    }
                }

                function initParticles() {
                    particlesArray = [];
                    let numberOfParticles = (canvas.height * canvas.width) / 9000; 
                    for (let i = 0; i < numberOfParticles; i++) {
                        particlesArray.push(new Particle());
                    }
                }

                function animateParticles() {
                    requestAnimationFrame(animateParticles);
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    for (let i = 0; i < particlesArray.length; i++) {
                        particlesArray[i].update();
                        particlesArray[i].draw();
                        for (let j = i; j < particlesArray.length; j++) {
                            const dx = particlesArray[i].x - particlesArray[j].x;
                            const dy = particlesArray[i].y - particlesArray[j].y;
                            const distance = Math.sqrt(dx * dx + dy * dy);
                            if (distance < 150) {
                                ctx.beginPath();
                                ctx.strokeStyle = `rgba(0, 229, 255, ${0.2 - distance/750})`;
                                ctx.lineWidth = 0.5;
                                ctx.moveTo(particlesArray[i].x, particlesArray[i].y);
                                ctx.lineTo(particlesArray[j].x, particlesArray[j].y);
                                ctx.stroke();
                            }
                        }
                    }
                }
                initParticles();
                animateParticles();
            }
        });
    </script>
</div>