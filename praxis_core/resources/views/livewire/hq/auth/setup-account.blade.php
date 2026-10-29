<div style="height: 100vh; width: 100vw; overflow: hidden; background-color: #010308; color: #FFFFFF; font-family: 'Inter', sans-serif; position: relative; display: flex; justify-content: center; align-items: center;">
    
    <div style="position: absolute; inset: 0; background-image: url('https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?q=80&w=2000&auto=format&fit=crop'); background-size: cover; background-position: center; z-index: 0; filter: grayscale(80%) contrast(1.5) brightness(0.2) sepia(40%) hue-rotate(160deg);"></div>
    <div style="position: absolute; inset: 0; background: radial-gradient(circle at 50% 50%, rgba(0, 229, 255, 0.08) 0%, rgba(1, 3, 8, 0.95) 70%); z-index: 1;"></div>

    <style>
        .scanlines { position: absolute; inset: 0; z-index: 20; pointer-events: none; background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,0) 50%, rgba(0,0,0,0.15) 50%, rgba(0,0,0,0.15)); background-size: 100% 4px; opacity: 0.4; }
        
        .setup-terminal {
            position: relative; z-index: 10; width: 100%; max-width: 550px; background: rgba(5, 12, 25, 0.65);
            backdrop-filter: blur(40px); -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(0, 229, 255, 0.2); border-top: 4px solid #00E5FF; border-bottom: 4px solid #00E5FF;
            padding: 3rem; border-radius: 4px; box-shadow: 0 30px 80px rgba(0,0,0,0.8), inset 0 0 40px rgba(0, 229, 255, 0.05);
            overflow: hidden; animation: boot-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .laser-scanner { position: absolute; top: -100%; left: 0; width: 100%; height: 2px; background: #00E5FF; box-shadow: 0 0 20px #00E5FF, 0 0 40px #00E5FF; animation: scan-vertical 3s linear infinite; pointer-events: none; z-index: 50; opacity: 0.7; }
        
        .auth-input { width: 100%; padding: 16px 15px 16px 45px; background-color: rgba(0, 0, 0, 0.5); border: 1px solid rgba(0, 229, 255, 0.2); color: #FFFFFF; border-radius: 4px; font-size: 1rem; font-family: 'Roboto Mono', monospace; outline: none; letter-spacing: 4px; transition: all 0.3s ease; box-sizing: border-box; }
        .auth-input:focus { background-color: rgba(0, 229, 255, 0.05); border-color: #00E5FF; box-shadow: 0 0 20px rgba(0, 229, 255, 0.2), inset 3px 0 0 #00E5FF; transform: translateX(3px); }
        
        .input-group { position: relative; width: 100%; margin-bottom: 1.2rem; transition: all 0.3s ease; }
        .input-group:focus-within .input-icon { opacity: 1 !important; color: #FFFFFF !important; filter: drop-shadow(0 0 10px #00E5FF); transform: translateY(-50%) scale(1.1); }
        
        .btn-authorize { width: 100%; background: #00E5FF; color: #050C19; font-family: 'Roboto Mono', monospace; font-weight: 900; font-size: 1rem; padding: 18px; border: none; border-radius: 4px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 1rem; text-transform: uppercase; letter-spacing: 2px; transition: all 0.3s ease; position: relative; overflow: hidden; z-index: 1; }
        .btn-authorize::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent); transition: 0.5s; z-index: -1; }
        .btn-authorize:hover::before { left: 100%; }
        .btn-authorize:hover { box-shadow: 0 0 30px rgba(0, 229, 255, 0.6); background: #FFFFFF; }

        @keyframes boot-up { 0% { opacity: 0; transform: scale(0.95) translateY(20px); filter: blur(10px); } 100% { opacity: 1; transform: scale(1) translateY(0); filter: blur(0); } }
        @keyframes scan-vertical { 0% { top: -10%; } 100% { top: 110%; } }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
    </style>

    <div class="scanlines"></div>

    <div class="setup-terminal">
        <div class="laser-scanner"></div>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid rgba(0, 229, 255, 0.2); padding-bottom: 15px; margin-bottom: 30px;">
            <div>
                <div style="color: #00E5FF; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; letter-spacing: 3px; font-weight: 700; margin-bottom: 5px;">
                    <i class="fa-solid fa-server" style="margin-right: 5px;"></i> JBS PRAXIS MAINFRAME
                </div>
                <div style="font-family: 'Montserrat', sans-serif; font-size: 1.4rem; font-weight: 900; letter-spacing: 1px; color: #FFFFFF; text-transform: uppercase;">Node Calibration</div>
            </div>
            <i class="fa-solid fa-fingerprint" style="font-size: 2.5rem; color: #00E5FF; opacity: 0.8; filter: drop-shadow(0 0 10px rgba(0,229,255,0.4));"></i>
        </div>

        <div style="background: rgba(0,0,0,0.4); border: 1px dashed rgba(255,255,255,0.1); padding: 20px; border-radius: 4px; margin-bottom: 30px; min-height: 80px;">
            <div id="typewriter" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #8A9BB2; line-height: 1.6;">
                </div>
        </div>

        <form wire:submit="lockAndAuthenticate" style="position: relative; z-index: 5;">
            
            <div class="input-group">
                <i class="fa-solid fa-key input-icon" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #00E5FF; opacity: 0.5; z-index: 5; font-size: 1rem; transition: 0.3s;"></i>
                <input id="password" type="password" wire:model="password" class="auth-input" placeholder="NEW KEY" required autofocus>
                <i class="fa-solid fa-eye input-icon" onclick="let pwd = document.getElementById('password'); pwd.type = pwd.type === 'password' ? 'text' : 'password'; this.classList.toggle('fa-eye'); this.classList.toggle('fa-eye-slash');" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #00E5FF; opacity: 0.5; z-index: 5; cursor: pointer; transition: 0.3s;"></i>
                @error('password') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; display: block; margin-top: 6px;">> {{ $message }}</span> @enderror
            </div>

            <div class="input-group">
                <i class="fa-solid fa-shield-check input-icon" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #00E5FF; opacity: 0.5; z-index: 5; font-size: 1rem; transition: 0.3s;"></i>
                <input id="passwordConfirmation" type="password" wire:model="passwordConfirmation" class="auth-input" placeholder="VERIFY KEY" required>
                <i class="fa-solid fa-eye input-icon" onclick="let pwd2 = document.getElementById('passwordConfirmation'); pwd2.type = pwd2.type === 'password' ? 'text' : 'password'; this.classList.toggle('fa-eye'); this.classList.toggle('fa-eye-slash');" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #00E5FF; opacity: 0.5; z-index: 5; cursor: pointer; transition: 0.3s;"></i>
                @error('passwordConfirmation') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; display: block; margin-top: 6px;">> {{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn-authorize" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="lockAndAuthenticate">[ LOCK KEY & ENTER HQ ] <i class="fa-solid fa-arrow-right-to-bracket"></i></span>
                <span wire:loading wire:target="lockAndAuthenticate"><i class="fa-solid fa-compact-disc fa-spin"></i> [ HASHING & SYNCING... ]</span>
            </button>

        </form>

        <div style="text-align: center; margin-top: 25px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; opacity: 0.5; letter-spacing: 1px;">
            > IDENTITY HASHED LOCALLY. NO SERVER RECOVERY.
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const userName = @js(e(strtoupper($firstName))); // escaped: the typewriter writes HTML
            const userEmail = @js(e(strtolower($email)));
            
            const textToType = `> IDENTITY CONFIRMED: <span style="color: #FFFFFF; font-weight: bold;">${userName}</span><br>> UPLINK: <span style="color: #00E5FF;">${userEmail}</span><br>> STATUS: Awaiting zero-knowledge cryptographic lock. Please establish your permanent clearance key below.`;
            
            const typewriterEl = document.getElementById('typewriter');
            
            // Simulating a fast terminal print out
            let i = 0;
            let isTag = false;
            let text = '';
            
            function type() {
                text = textToType.slice(0, ++i);
                if (text === textToType) {
                    typewriterEl.innerHTML = text + '<span style="display:inline-block; width:8px; height:12px; background:#00E5FF; margin-left:4px; animation: blink 1s step-end infinite;"></span>';
                    return;
                }
                
                typewriterEl.innerHTML = text + '<span style="display:inline-block; width:8px; height:12px; background:#00E5FF; margin-left:4px;"></span>';
                
                let char = text.slice(-1);
                if(char === '<') isTag = true;
                if(char === '>') isTag = false;

                if (isTag) return type(); // Skip typing animation for HTML tags
                setTimeout(type, 15);
            }
            
            setTimeout(type, 800); // Wait for the terminal boot-up animation to finish
        });
    </script>
</div>