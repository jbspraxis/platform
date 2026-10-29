<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #0A1128; font-family: 'Inter', sans-serif;">
    <div style="background: #050914; padding: 40px; border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 4px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.8);">
        
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #00E5FF; margin-bottom: 10px; letter-spacing: 2px;">// JBS PRAXIS TASK FORCE</div>
            <h1 style="color: #FFFFFF; font-size: 1.5rem; font-weight: bold; margin: 0;">SECURE LOGIN</h1>
        </div>

        @if (session()->has('error'))
            <div style="background: rgba(255, 51, 102, 0.1); border: 1px solid #FF3366; color: #FF3366; padding: 10px; border-radius: 2px; font-size: 0.8rem; margin-bottom: 20px; text-align: center; font-family: 'Roboto Mono', monospace;">
                {{ session('error') }}
            </div>
        @endif

        <form wire:submit.prevent="authenticate">
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #6B7280; font-size: 0.75rem; font-family: 'Roboto Mono', monospace; margin-bottom: 8px;">OPERATIVE EMAIL</label>
                <input type="email" wire:model="email" required style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #FFF; padding: 12px; border-radius: 2px; outline: none; font-family: 'Inter', sans-serif;" placeholder="name@consultant.com">
                @error('email') <span style="color: #FF3366; font-size: 0.7rem; display: block; margin-top: 5px;">{{ $message }}</span> @enderror
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; color: #6B7280; font-size: 0.75rem; font-family: 'Roboto Mono', monospace; margin-bottom: 8px;">ACCESS KEY</label>
                <input type="password" wire:model="password" required style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #FFF; padding: 12px; border-radius: 2px; outline: none; font-family: 'Inter', sans-serif;" placeholder="••••••••">
            </div>

            <button type="submit" wire:loading.attr="disabled" style="width: 100%; background: #00E5FF; color: #0A1128; border: none; padding: 14px; font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.9rem; border-radius: 2px; cursor: pointer; transition: 0.2s;">
                <span wire:loading.remove>INITIATE CONNECTION</span>
                <span wire:loading>AUTHENTICATING...</span>
            </button>
        </form>
    </div>
</div>