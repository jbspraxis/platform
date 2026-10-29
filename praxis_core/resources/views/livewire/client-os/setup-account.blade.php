<div style="height: 100vh; width: 100vw; display: flex; align-items: center; justify-content: center; background-color: #010308; color: #FFF; font-family: 'Inter', sans-serif; position: relative; overflow: hidden;">
    
    <!-- Background Matrix -->
    <div style="position: absolute; inset: 0; background-image: url('https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=2000&auto=format&fit=crop'); background-size: cover; background-position: center; z-index: 0; filter: grayscale(80%) contrast(1.2) brightness(0.15) sepia(30%) hue-rotate(180deg);"></div>
    <div style="position: absolute; inset: 0; background: radial-gradient(circle at 50% 50%, rgba(0, 229, 255, 0.05) 0%, rgba(1, 3, 8, 0.95) 80%); z-index: 1;"></div>
    
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Inter:wght@300;400;600;800&family=Montserrat:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <div style="position: relative; z-index: 10; background: rgba(5, 12, 25, 0.85); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border: 1px solid rgba(0, 229, 255, 0.3); border-top: 4px solid #00E5FF; padding: 50px 40px; border-radius: 6px; box-shadow: 0 40px 100px rgba(0,0,0,0.9), inset 0 0 30px rgba(0, 229, 255, 0.05); max-width: 480px; width: 90%; text-align: center; animation: fade-in-up 0.8s ease-out forwards;">
        
        <i class="fa-solid fa-fingerprint" style="font-size: 3.5rem; color: #00E5FF; margin-bottom: 25px; filter: drop-shadow(0 0 15px rgba(0, 229, 255, 0.4));"></i>
        
        <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.6rem; font-weight: 900; letter-spacing: 1px; margin-bottom: 10px; text-transform: uppercase;">Zero-Knowledge Init</h2>
        
        <p style="font-size: 0.85rem; color: #8A9BB2; margin-bottom: 35px; line-height: 1.6;">
            Welcome, <strong style="color: #FFF;">{{ strtoupper($firstName) }}</strong>. Establish your permanent cryptographic key to access the Client OS. This key is hashed client-side; Praxis Command cannot recover it.
        </p>

        <form wire:submit.prevent="lockAndAuthenticate" style="display: flex; flex-direction: column; gap: 20px;">
            
            <div>
                <input type="password" wire:model="password" placeholder="NEW CRYPTOGRAPHIC KEY" required style="width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(0,229,255,0.2); color: #FFF; padding: 16px; font-family: 'Fira Code', monospace; font-size: 0.9rem; border-radius: 4px; outline: none; text-align: center; letter-spacing: 3px; transition: 0.3s;" onfocus="this.style.borderColor='#00E5FF'; this.style.backgroundColor='rgba(0, 229, 255, 0.05)';" onblur="this.style.borderColor='rgba(0,229,255,0.2)'; this.style.backgroundColor='rgba(0,0,0,0.4)';">
                @error('password') <span style="color: #FF3366; font-family: 'Fira Code', monospace; font-size: 0.7rem; display: block; margin-top: 8px;">{{ $message }}</span> @enderror
            </div>

            <div>
                <input type="password" wire:model="passwordConfirmation" placeholder="VERIFY KEY" required style="width: 100%; background: rgba(0,0,0,0.4); border: 1px solid rgba(0,229,255,0.2); color: #FFF; padding: 16px; font-family: 'Fira Code', monospace; font-size: 0.9rem; border-radius: 4px; outline: none; text-align: center; letter-spacing: 3px; transition: 0.3s;" onfocus="this.style.borderColor='#00E5FF'; this.style.backgroundColor='rgba(0, 229, 255, 0.05)';" onblur="this.style.borderColor='rgba(0,229,255,0.2)'; this.style.backgroundColor='rgba(0,0,0,0.4)';">
                @error('passwordConfirmation') <span style="color: #FF3366; font-family: 'Fira Code', monospace; font-size: 0.7rem; display: block; margin-top: 8px;">{{ $message }}</span> @enderror
            </div>

            <button type="submit" style="width: 100%; background: #00E5FF; color: #010308; font-family: 'Fira Code', monospace; font-weight: 800; font-size: 0.9rem; padding: 18px; border: none; border-radius: 4px; cursor: pointer; text-transform: uppercase; margin-top: 10px; transition: 0.3s; letter-spacing: 1px; display: flex; justify-content: center; align-items: center; gap: 10px;" onmouseover="this.style.boxShadow='0 0 25px rgba(0, 229, 255, 0.5)'; this.style.background='#FFF';" onmouseout="this.style.boxShadow='none'; this.style.background='#00E5FF';" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="lockAndAuthenticate">[ LOCK KEY & ENTER OS ]</span>
                <span wire:loading wire:target="lockAndAuthenticate"><i class="fa-solid fa-circle-notch fa-spin"></i> HASHING...</span>
            </button>
        </form>
    </div>

    <style>
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</div>