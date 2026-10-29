<div class="system-boot-wrapper" style="height: 100vh; width: 100vw; overflow: hidden; background-color: #010308; color: #FFFFFF; font-family: 'Inter', sans-serif; position: relative; display: flex;">
    
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600&family=Inter:wght@300;400;500;600&family=Montserrat:wght@400;600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <div style="position: absolute; inset: 0; background-image: url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2000&auto=format&fit=crop'); background-size: cover; background-position: center; z-index: 0; filter: grayscale(80%) contrast(1.2) brightness(0.2) hue-rotate(200deg);"></div>
    <div style="position: absolute; inset: 0; background: linear-gradient(90deg, rgba(1, 3, 8, 0.85) 0%, rgba(1, 3, 8, 0.4) 75%, transparent 100%); z-index: 1;"></div>
    
    <canvas id="reactiveGrid" wire:ignore style="position: absolute; inset: 0; z-index: 2; opacity: 0.6;"></canvas>

    <style>
        /* ==========================================================================
           BASE RESETS & GLOBALS
           ========================================================================== */
        *, *::before, *::after { 
            box-sizing: border-box; 
        }

        input:-webkit-autofill { 
            -webkit-box-shadow: 0 0 0 30px transparent inset !important; 
            transition: background-color 5000s ease-in-out 0s; 
            -webkit-text-fill-color: white !important; 
        }

        /* ==========================================================================
           ANIMATIONS
           ========================================================================== */
        .system-boot-wrapper { 
            animation: deep-fade-in 2s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
        }

        .slide-up-reveal { 
            opacity: 0; 
            transform: translateY(20px); 
            animation: slide-up 1.5s cubic-bezier(0.16, 1, 0.3, 1) 0.8s forwards; 
        }

        .glass-slide-in { 
            transform: translateX(100%); 
            animation: slide-left 1.2s cubic-bezier(0.16, 1, 0.3, 1) 0.5s forwards; 
        }

        @keyframes deep-fade-in { 
            0% { opacity: 0; } 
            100% { opacity: 1; } 
        }

        @keyframes slide-up { 
            0% { opacity: 0; transform: translateY(20px); } 
            100% { opacity: 1; transform: translateY(0); } 
        }

        @keyframes slide-left { 
            0% { transform: translateX(100%); } 
            100% { transform: translateX(0); } 
        }

        @keyframes blink { 
            50% { opacity: 0; } 
        }

        @keyframes minimal-pulse { 
            0% { transform: scale(0.8); opacity: 0.5; } 
            50% { transform: scale(1.5); opacity: 1; } 
            100% { transform: scale(0.8); opacity: 0.5; } 
        }

        /* ==========================================================================
           LAYOUT ARCHITECTURE
           ========================================================================== */
        .desktop-story-pane { 
            flex: 1; 
            padding: 40px 6vw 6vw 6vw; 
            display: flex; 
            flex-direction: column; 
            justify-content: flex-start;
            position: relative; 
            z-index: 10; 
        }

        .login-pane { 
            width: 28%; 
            min-width: 420px; 
            height: 100vh; 
            background: linear-gradient(135deg, rgba(2, 6, 15, 0.4), rgba(0, 195, 255, 0.03)); 
            backdrop-filter: blur(25px); 
            -webkit-backdrop-filter: blur(25px); 
            border-left: 1px solid rgba(0, 195, 255, 0.1); 
            display: flex; 
            flex-direction: column; 
            padding: 3vw; 
            position: relative; 
            z-index: 20; 
            box-shadow: -20px 0 60px rgba(0,0,0,0.6); 
        }

        /* ==========================================================================
           STORYTELLING & TYPOGRAPHY
           ========================================================================== */
        .story-header { 
            font-family: 'Montserrat', sans-serif; 
            font-size: clamp(2.5rem, 4vw, 4.5rem); 
            font-weight: 800; 
            color: #FFF; 
            line-height: 1.1; 
            margin-bottom: 1.5rem; 
            letter-spacing: -1px; 
        }

        .story-header span { 
            color: #00C3FF; 
        }

        .story-body { 
            font-family: 'Inter', sans-serif; 
            font-size: 1.1rem; 
            color: #E2E8F0; 
            font-weight: 300; 
            line-height: 1.8; 
            max-width: 650px; 
            margin-bottom: 2rem; 
        }

        .praxis-link { 
            display: inline-flex; 
            align-items: center; 
            gap: 10px; 
            font-family: 'Fira Code', monospace; 
            font-size: 0.85rem; 
            color: #00C3FF; 
            text-decoration: none; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            border-bottom: 1px solid transparent; 
            transition: all 0.3s; 
            padding-bottom: 4px; 
        }

        .praxis-link:hover { 
            border-bottom: 1px solid #00C3FF; 
            letter-spacing: 3px; 
        }

        .typing-quote-container { 
            position: absolute; 
            bottom: 6vw; 
            left: 6vw; 
            max-width: 600px; 
        }

        .typing-text { 
            font-family: 'Fira Code', monospace; 
            font-size: 0.85rem; 
            color: #8A9BB2; 
            line-height: 1.6; 
        }

        .cursor { 
            display: inline-block; 
            width: 6px; 
            height: 15px; 
            background-color: #00C3FF; 
            animation: blink 1s step-end infinite; 
            vertical-align: middle; 
            margin-left: 4px; 
        }

        /* ==========================================================================
           LOGIN PANE ELEMENTS (CENTRALIZED)
           ========================================================================== */
        .centered-logo { 
            max-width: 75px; 
            margin: 0 auto 1.5rem auto; 
            height: auto; 
            display: block; 
            opacity: 0.9;
        }

        .greeting-title {
            font-family: 'Montserrat', sans-serif; 
            font-weight: 800; 
            font-size: 1.5rem; 
            color: #FFF; 
            text-align: center; 
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .greeting-subtitle {
            font-family: 'Inter', sans-serif; 
            font-size: 0.85rem; 
            color: #8A9BB2; 
            text-align: center; 
            margin-bottom: 2.5rem; 
            line-height: 1.5; 
            max-width: 280px;
            margin-left: auto;
            margin-right: auto;
        }

        .subtle-clock {
            position: absolute; 
            top: 25px; 
            right: 25px; 
            font-family: 'Fira Code', monospace; 
            font-size: 0.7rem; 
            color: #5C6B80; 
            letter-spacing: 2px; 
            opacity: 0.7;
        }

        /* Input Styling */
        .input-group { 
            position: relative; 
            margin-bottom: 1.8rem; 
            width: 100%; 
        }

        .minimal-input { 
            width: 100%; 
            background: rgba(0, 0, 0, 0.2); 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            color: #FFF; 
            font-family: 'Inter', sans-serif; 
            font-size: 0.9rem; 
            padding: 16px 16px 16px 45px; 
            border-radius: 4px; 
            outline: none; 
            transition: all 0.3s ease; 
        }

        .minimal-input:focus { 
            border-color: #00C3FF; 
            background: rgba(0, 195, 255, 0.03); 
            box-shadow: 0 0 20px rgba(0, 195, 255, 0.1) inset; 
        }

        .minimal-input::placeholder { 
            color: #5C6B80; 
            font-weight: 300; 
        }

        .input-icon { 
            position: absolute; 
            left: 16px; 
            top: 16px; 
            color: #5C6B80; 
            font-size: 1rem; 
            transition: color 0.3s; 
        }

        .minimal-input:focus + .input-icon { 
            color: #00C3FF; 
        }

        /* Glassmorphic Authenticate Button */
        .btn-glass { 
            width: 100%; 
            background: rgba(0, 195, 255, 0.06); 
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 195, 255, 0.3); 
            color: #00C3FF; 
            font-family: 'Fira Code', monospace; 
            font-size: 0.85rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            padding: 16px; 
            border-radius: 4px; 
            cursor: pointer; 
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            gap: 10px; 
            margin-top: 1rem; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-glass:hover:not(:disabled) { 
            background: rgba(0, 195, 255, 0.15); 
            border-color: #00C3FF; 
            color: #FFF; 
            box-shadow: 0 0 25px rgba(0, 195, 255, 0.3), inset 0 0 10px rgba(0, 195, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-glass:disabled { 
            background: rgba(0, 195, 255, 0.02); 
            color: #5C6B80; 
            cursor: not-allowed; 
            border-color: rgba(255, 255, 255, 0.1); 
            box-shadow: none;
        }

        .loading-dot { 
            width: 6px; 
            height: 6px; 
            background-color: currentColor; 
            border-radius: 50%; 
            display: inline-block; 
            animation: minimal-pulse 1s infinite; 
            margin-right: 8px; 
        }

        /* ==========================================================================
           REDLINE PROTOCOL & FLOATING MODAL
           ========================================================================== */
        .redline-trigger { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            font-family: 'Fira Code', monospace; 
            font-size: 0.75rem; 
            color: #FF3366; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            cursor: pointer; 
            transition: all 0.3s; 
            opacity: 0.7; 
            padding: 8px 0; 
            border-bottom: 1px solid transparent; 
        }

        .redline-trigger:hover { 
            opacity: 1; 
            border-bottom: 1px solid #FF3366; 
            text-shadow: 0 0 10px rgba(255, 51, 102, 0.4); 
        }

        .floating-redline {
            position: absolute;
            bottom: 100%; 
            right: 0;
            margin-bottom: 15px;
            width: 420px;
            background: rgba(2, 6, 15, 0.95);
            backdrop-filter: blur(35px);
            -webkit-backdrop-filter: blur(35px);
            border: 1px solid rgba(255, 51, 102, 0.3);
            box-shadow: 0 30px 60px rgba(0,0,0,0.9), inset 0 0 20px rgba(255, 51, 102, 0.05);
            border-radius: 6px;
            padding: 30px;
            opacity: 0;
            pointer-events: none;
            transform: translateY(20px);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 50;
        }

        .floating-redline.active {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .redline-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 12px; 
            margin-bottom: 12px; 
        }

        .redline-grid .full-width { 
            grid-column: span 2; 
        }

        /* ==========================================================================
           MOBILE RESPONSIVENESS
           ========================================================================== */
        .mobile-only { 
            display: none; 
        }

        @media (max-width: 1024px) {
            .desktop-story-pane { 
                display: none; 
            }
            .login-pane { 
                width: 100%; 
                min-width: 100%; 
                border-left: none; 
                align-items: center; 
                justify-content: center; 
                padding: 10vw; 
                background: rgba(2, 6, 15, 0.85); 
            }
            .login-content-wrapper { 
                width: 100%; 
                max-width: 400px; 
            }
            .mobile-only { 
                display: flex; 
                justify-content: center; 
                margin-top: 3rem; 
                width: 100%;
            }
            .glass-slide-in { 
                animation: deep-fade-in 1s forwards; 
                transform: none; 
            }
            .floating-redline {
                width: 100%;
                left: 0;
                right: 0;
                bottom: 50px;
            }
        }
    </style>

    <div class="desktop-story-pane">
        
        <div class="slide-up-reveal">
            <div id="dynamicManifesto">
                <div class="story-header">Establishing<br><span>Secure Uplink.</span></div>
                <div class="story-body">
                    Authenticating connection signature. Please standby while we resolve your regional node architecture.
                </div>
            </div>
            
            <a href="/framework" class="praxis-link">Explore the Predictive Praxis Framework <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="typing-quote-container slide-up-reveal" style="margin-top: 80px; max-width: 600px;">
            <div style="font-family: 'Fira Code', monospace; font-size: 0.65rem; color: #5C6B80; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 2px;">// System Directives</div>
            <span id="typewriterText" class="typing-text"></span><span class="cursor"></span>
            
            <div id="telemetryReadout" style="font-family: 'Fira Code', monospace; font-size: 0.55rem; color: #5C6B80; margin-top: 15px; opacity: 0.4; letter-spacing: 1.5px;">
                NODE SECURING... // ISP: STANDBY
            </div>
        </div>

        <div class="slide-up-reveal" style="position: absolute; bottom: 6vw; right: 6vw; z-index: 9999;">
            
            <div class="redline-trigger" onclick="toggleRedline(true)">
                <i class="fa-solid fa-triangle-exclamation"></i> Initiate Redline Protocol
            </div>

            <div id="redlineModal" class="floating-redline" wire:ignore.self>
        <i class="fa-solid fa-xmark" onclick="toggleRedline(false); @this.set('viewState', 'login')" style="position: absolute; top: 20px; right: 20px; color: #5C6B80; cursor: pointer; font-size: 1.2rem; transition: color 0.3s;" onmouseover="this.style.color='#FF3366'" onmouseout="this.style.color='#5C6B80'"></i>
        
        @if($viewState === 'redline_success')
            <div style="text-align: center; animation: deep-fade-in 0.5s ease; padding: 10px 0;">
                <i class="fa-solid fa-satellite-dish" style="font-size: 3rem; color: #FF3366; margin-bottom: 20px; text-shadow: 0 0 20px rgba(255,51,102,0.4); animation: minimal-pulse 2s infinite;"></i>
                <div style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; font-weight: 900; color: #FFF; margin-bottom: 10px; letter-spacing: 1px;">
                    DIAGNOSTICS TRANSMITTED
                </div>
                <p style="font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #8A9BB2; margin-bottom: 25px; line-height: 1.6;">
                    Failure parameters securely injected into the Command Matrix. A Praxis Strike Team Architect has been alerted.
                </p>
                
                <a href="/schedule" class="btn-glass" style="color: #00C3FF; border-color: rgba(0,195,255,0.3); text-decoration: none; padding: 12px; margin-bottom: 15px;">
                    <i class="fa-solid fa-calendar-check" style="margin-right: 8px;"></i> Schedule Briefing
                </a>
                
                <button wire:click="switchState('login')" onclick="toggleRedline(false)" style="background: transparent; border: none; color: #5C6B80; font-family: 'Fira Code', monospace; font-size: 0.75rem; text-transform: uppercase; cursor: pointer; transition: color 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='#5C6B80'">
                    [ Close & Return ]
                </button>
            </div>
        @else
            <div style="color: #FF3366; font-family: 'Fira Code', monospace; font-size: 1rem; margin-bottom: 20px; font-weight: 600; letter-spacing: 1px;">
                <i class="fa-solid fa-triangle-exclamation"></i> CRITICAL REDLINE
            </div>
            
            @if(session()->has('redline_error'))
                <div style="background: rgba(255, 51, 102, 0.1); border: 1px solid #FF3366; color: #FF3366; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; text-align: center; margin-bottom: 15px; border-radius: 4px;">
                    {{ session('redline_error') }}
                </div>
            @endif
            
            <form wire:submit.prevent="submitRedlineSos">
                <div class="redline-grid">
                    <input type="text" wire:model="sosName" class="minimal-input" placeholder="Authorized Name" style="border-color: rgba(255,51,102,0.2); padding: 12px;" required>
                    <input type="text" wire:model="sosTitle" class="minimal-input" placeholder="Job Title / Clearance" style="border-color: rgba(255,51,102,0.2); padding: 12px;" required>
                    <input type="text" wire:model="sosCompany" class="minimal-input full-width" placeholder="Organization Legal Entity" style="border-color: rgba(255,51,102,0.2); padding: 12px;" required>
                    <input type="email" wire:model="sosEmail" class="minimal-input" placeholder="Corporate Email" style="border-color: rgba(255,51,102,0.2); padding: 12px;" required>
                    <input type="tel" wire:model="sosPhone" class="minimal-input" placeholder="+254 800...)" pattern="^\+[0-9 \-\(\)]{7,20}$" title="International format required. Must begin with a '+' and your country code." style="border-color: rgba(255,51,102,0.2); padding: 12px;" required>
                    <textarea wire:model="sosDescription" class="minimal-input full-width" placeholder="Detail the infrastructure failure parameters concisely..." style="height: 80px; resize: none; border-color: rgba(255,51,102,0.2); padding: 12px;" required></textarea>
                </div>
                
                <button type="submit" class="btn-glass" style="color: #FF3366; border-color: rgba(255,51,102,0.5); padding: 12px; margin-top: 5px;" onmouseover="this.style.background='rgba(255,51,102,0.15)'; this.style.boxShadow='0 0 20px rgba(255,51,102,0.3)'" onmouseout="this.style.background='rgba(0,195,255,0.06)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.2)'" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitRedlineSos">Transmit</span>
                    <span wire:loading wire:target="submitRedlineSos">
                        <span style="display: inline-flex; align-items: center;">
                            <span class="loading-dot" style="background-color: currentColor;"></span> Transmitting...
                        </span>
                    </span>
                </button>
            </form>
        @endif
    </div>
        </div>
    </div>

    <div class="login-pane glass-slide-in">
        
        <div id="subtleClock" class="subtle-clock" wire:ignore>00:00:00</div>

        <div class="login-content-wrapper" style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; width: 100%;">
            
            <img src="{{ asset('assets/img/logo_monochrome.svg') }}" class="centered-logo" alt="JBS Logo" onerror="this.style.display='none'">
            
            <div id="timeGreeting" class="greeting-title" wire:ignore>Welcome.</div>
            <div class="greeting-subtitle">
                Please authenticate to access your enterprise dashboard.
            </div>

            <div style="width: 100%; max-width: 360px;">
                @if($viewState === 'login' || $viewState === 'redline_success')
                    <form wire:submit.prevent="authenticate">
                        @error('email') 
                            <div style="color: #FF3366; font-family: 'Inter', sans-serif; font-size: 0.8rem; margin-bottom: 15px; text-align: center;">{{ $message }}</div> 
                        @enderror
                        
                        <div class="input-group">
                            <input type="email" wire:model="email" class="minimal-input" placeholder="Corporate Identifier" required>
                            <i class="fa-solid fa-envelope input-icon"></i>
                        </div>
                        
                        <div class="input-group" style="margin-bottom: 10px;">
                            <input id="keyInput" type="password" wire:model="password" class="minimal-input" placeholder="Cryptographic Key" required>
                            <i class="fa-solid fa-key input-icon"></i>
                            <i class="fa-solid fa-eye" onclick="let p = document.getElementById('keyInput'); p.type = p.type === 'password' ? 'text' : 'password';" style="position: absolute; right: 16px; top: 16px; color: #5C6B80; cursor: pointer; transition: color 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='#5C6B80'"></i>
                        </div>
                        
                        <div style="text-align: right; margin-bottom: 25px;">
                            <a wire:click="switchState('forgot_password')" style="color: #5C6B80; font-family: 'Inter', sans-serif; font-size: 0.8rem; cursor: pointer; text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#00C3FF'" onmouseout="this.style.color='#5C6B80'">Lost Key Protocol?</a>
                        </div>
                        
                        <button type="submit" class="btn-glass" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="authenticate">Authenticate</span>
                            <span wire:loading wire:target="authenticate">
                                <span style="display: inline-flex; align-items: center;">
                                    <span class="loading-dot"></span> Authenticating...
                                </span>
                            </span>
                        </button>
                    </form>

                @elseif($viewState === 'forgot_password')
                    <form wire:submit.prevent="sendResetLink">
                        <div style="font-family: 'Inter', sans-serif; font-size: 0.9rem; color: #E2E8F0; margin-bottom: 25px; text-align: center;">Provide email to initiate key recovery.</div>
                        @if($authMessage) 
                            <div style="color: #00C3FF; font-size: 0.8rem; margin-bottom: 15px; text-align: center;">{{ $authMessage }}</div> 
                        @endif
                        
                        <div class="input-group">
                            <input type="email" wire:model="resetEmail" class="minimal-input" placeholder="Registered Email" required>
                            <i class="fa-solid fa-envelope input-icon"></i>
                        </div>
                        
                        <button type="submit" class="btn-glass" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="sendResetLink">Dispatch</span>
                            <span wire:loading wire:target="sendResetLink">
                                <span style="display: inline-flex; align-items: center;">
                                    <span class="loading-dot"></span> Dispatching...
                                </span>
                            </span>
                        </button>
                        
                        <div style="text-align: center; margin-top: 20px;">
                            <a wire:click="switchState('login')" style="color: #5C6B80; font-family: 'Inter', sans-serif; font-size: 0.8rem; cursor: pointer; transition: color 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='#5C6B80'">< Return</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <div class="mobile-only">
            <div class="redline-trigger" onclick="toggleRedline(true)">
                <i class="fa-solid fa-triangle-exclamation"></i> Redline Protocol
            </div>
        </div>
    </div>

    <script>
        // --- Modal Toggle Logic ---
        function toggleRedline(show) {
            const modal = document.getElementById('redlineModal');
            if(show) {
                modal.classList.add('active');
            } else {
                modal.classList.remove('active');
            }
        }
        document.addEventListener('keydown', e => { 
            if(e.key === "Escape") toggleRedline(false); 
        });

        document.addEventListener('DOMContentLoaded', () => {
            
            // --- Time-Based Greeting Logic ---
            const updateGreeting = () => {
                const hour = new Date().getHours();
                let greeting = 'Good Evening.';
                
                if (hour >= 5 && hour < 12) {
                    greeting = 'Good Morning.';
                } else if (hour >= 12 && hour < 17) {
                    greeting = 'Good Afternoon.';
                }
                
                const greetingEl = document.getElementById('timeGreeting');
                if(greetingEl) greetingEl.textContent = greeting;
            };
            updateGreeting();

            // --- Subtle Clock Logic ---
            setInterval(() => {
                const now = new Date();
                const clockEl = document.getElementById('subtleClock');
                if(clockEl) {
                    clockEl.textContent = now.toLocaleTimeString('en-GB', { hour12: false });
                }
            }, 1000);
            
            // --- Dynamic Continental Storytelling (IP-Based) ---
            fetch('https://ipapi.co/json/')
                .then(res => res.json())
                .then(data => {
                    const manifesto = document.getElementById('dynamicManifesto');
                    if(!manifesto) return;
                    
                    const city = data.city;
                    const country = data.country_name;
                    const continent = data.continent_code; 
                    const ip = data.ip;
                    const isp = data.org;
                    
                    let storyBody = "";
                    
                    // Continental Realities
                    if (continent === 'AF') {
                        storyBody = "As Africa's economic engine accelerates, organizational complexity scales exponentially. We architect resilient, leapfrog technologies to turn regional friction into engineered precision.";
                    } else if (continent === 'EU') {
                        storyBody = "Navigating strict regulatory landscapes and modernizing legacy infrastructure requires absolute precision. We engineer compliant, highly optimized systems for the European market.";
                    } else if (continent === 'NA') {
                        storyBody = "In an arena defined by hyper-competition and rapid disruption, we engineer scalable architectures that allow North American enterprises to dictate market terms.";
                    } else if (continent === 'AS') {
                        storyBody = "Operating at massive scale requires frictionless digital infrastructure. We architect high-velocity systems to power the next generation of Asian market dominance.";
                    } else if (continent === 'SA') {
                        storyBody = "Agility is the ultimate defense against market volatility. We build highly adaptable, predictive infrastructures tailored for dynamic Latin American economies.";
                    } else {
                        storyBody = "Beyond strategy lies the architecture of execution. You are connecting to the JBS Praxis core network. We transform operational friction into engineered precision for global enterprises.";
                    }
            
                    // Render the Country Name + Continental Story
                    if (country && city) {
                        manifesto.innerHTML = `
                            <div class="story-header">Uplink Secured:<br><span>${city}, ${country}.</span></div>
                            <div class="story-body">${storyBody}</div>
                        `;
                    } else if (country) {
                        manifesto.innerHTML = `
                            <div class="story-header">Uplink Secured:<br><span>${country}.</span></div>
                            <div class="story-body">${storyBody}</div>
                        `;
                    } else {
                        throw new Error("No location data"); // Trigger VPN fallback
                    }

                    // Inject IP & ISP
                    const readout = document.getElementById('telemetryReadout');
                    if(readout && ip && isp) {
                        readout.textContent = `IP: ${ip} // ISP: ${isp.toUpperCase()}`;
                    }
                }).catch(() => {
                    // The VPN / Obscured Location Fallback
                    const manifesto = document.getElementById('dynamicManifesto');
                    if(manifesto) {
                        manifesto.innerHTML = `
                            <div class="story-header">Global Node:<br><span>Encrypted Tunnel.</span></div>
                            <div class="story-body">
                                Your connection is routed through an anonymized secure tunnel. Beyond strategy lies the architecture of execution. We engineer absolute precision, regardless of origin.
                            </div>
                        `;
                    }
                });

            // --- Typewriter Effect for System Directives ---
            const quotes = [
                "Strategy without execution is merely hallucination.",
                "A policy written in code is law.",
                "We do not digitize confusion; we engineer absolute clarity.",
                "Chaos is a permanent tax on un-engineered growth.",
                "Digital transformation is not an event. It is a posture.",
                "Analyzing failure after the fact is not a strategy; it is an autopsy.",
                "Human willpower is finite; engineered systems are infinite.",
                "Inefficiency is a symptom; bad architecture is the disease.",
                "Predictive intelligence always beats reactive brilliance.",
                "Data without architecture is just noise.",
                "Scale is not a goal; it is an engineered byproduct.",
                "Do not adapt to the market. Force the market to adapt to your systems.",
                "A vision without systems is a tragedy waiting to happen.",
                "Good consultants tell you what to do. Great architects build it for you.",
                "We transform organizational friction into kinetic energy.",
                "Security is not a layer; it is the foundation.",
                "Complexity is the enemy of execution. We engineer simplicity.",
                "The future belongs to the automated and the optimized.",
                "We shift organizational governance from firefighting to deterministic forecasting.",
                "True innovation is indistinguishable from magic, but built on relentless logic."
            ];
            
            let qIndex = 0;
            let charIndex = 0;
            let isDeleting = false;
            const typeTarget = document.getElementById('typewriterText');
            
            function type() {
                const currentQuote = quotes[qIndex];
                if (isDeleting) {
                    typeTarget.textContent = currentQuote.substring(0, charIndex - 1);
                    charIndex--;
                } else {
                    typeTarget.textContent = currentQuote.substring(0, charIndex + 1);
                    charIndex++;
                }

                let typeSpeed = isDeleting ? 30 : 60;
                
                if (!isDeleting && charIndex === currentQuote.length) {
                    typeSpeed = 4000; // Pause at end of quote
                    isDeleting = true;
                } else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    qIndex = (qIndex + 1) % quotes.length;
                    typeSpeed = 500; // Pause before new quote
                }
                setTimeout(type, typeSpeed);
            }
            setTimeout(type, 2000); // Wait for boot animation to finish

            // --- Reactive Dark Void Canvas (Reacts to Mouse) ---
            const canvas = document.getElementById('reactiveGrid');
            if(canvas) {
                const ctx = canvas.getContext('2d');
                canvas.width = window.innerWidth; 
                canvas.height = window.innerHeight;
                
                let particles = [];
                let mouse = { x: null, y: null };
                
                window.addEventListener('mousemove', (e) => { 
                    mouse.x = e.x; 
                    mouse.y = e.y; 
                });
                window.addEventListener('mouseout', () => { 
                    mouse.x = null; 
                    mouse.y = null; 
                });
                window.addEventListener('resize', () => { 
                    canvas.width = window.innerWidth; 
                    canvas.height = window.innerHeight; 
                });

                for(let i = 0; i < 90; i++) {
                    particles.push({
                        x: Math.random() * canvas.width, 
                        y: Math.random() * canvas.height,
                        vx: (Math.random() - 0.5) * 0.4, 
                        vy: (Math.random() - 0.5) * 0.4
                    });
                }

                function animateCanvas() {
                    requestAnimationFrame(animateCanvas);
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    particles.forEach(p => {
                        p.x += p.vx; 
                        p.y += p.vy;
                        
                        // Bounce off edges
                        if(p.x < 0 || p.x > canvas.width) p.vx *= -1;
                        if(p.y < 0 || p.y > canvas.height) p.vy *= -1;

                        // Mouse repulsion interaction
                        if(mouse.x != null) {
                            let dx = mouse.x - p.x;
                            let dy = mouse.y - p.y;
                            let distance = Math.sqrt(dx * dx + dy * dy);
                            if(distance < 150) {
                                p.x -= dx / 20;
                                p.y -= dy / 20;
                            }
                        }

                        // Draw particle
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, 1.5, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(0, 195, 255, 0.3)';
                        ctx.fill();
                    });

                    // Draw connecting lines
                    for(let i = 0; i < particles.length; i++) {
                        for(let j = i; j < particles.length; j++) {
                            let dx = particles[i].x - particles[j].x;
                            let dy = particles[i].y - particles[j].y;
                            let distance = Math.sqrt(dx * dx + dy * dy);
                            
                            if(distance < 120) {
                                ctx.beginPath();
                                ctx.strokeStyle = `rgba(0, 195, 255, ${0.15 - distance/800})`;
                                ctx.lineWidth = 0.5;
                                ctx.moveTo(particles[i].x, particles[i].y);
                                ctx.lineTo(particles[j].x, particles[j].y);
                                ctx.stroke();
                            }
                        }
                    }
                }
                animateCanvas();
            }
        });

    </script>
</div>