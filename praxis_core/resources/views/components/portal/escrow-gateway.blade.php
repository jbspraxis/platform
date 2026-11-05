<div class="min-h-screen bg-[#03050A] text-white font-sans selection:bg-[#00E5FF] selection:text-[#0A1128]" x-data="signatureGateway()">
    
    <nav class="bg-[#0A1128] border-b border-white/10 px-8 py-5 sticky top-0 z-40 flex justify-between items-center shadow-lg">
        <div class="flex items-center gap-4">
            <div>
                <h1 class="text-xl font-black tracking-widest text-white">JBS PRAXIS</h1>
                <span class="text-[10px] text-[#00E5FF] font-bold uppercase tracking-widest font-mono">Secure Escrow Gateway</span>
            </div>
        </div>
        <div class="text-right hidden md:block">
            <div class="text-xs text-gray-400 font-mono">REF: <span class="text-white">{{ $proposal->proposal_ref }}</span></div>
            <div class="text-xs text-gray-400 font-mono mt-1">STATUS: 
                <span class="{{ $proposal->status === 'Authorized' ? 'text-[#00FF66]' : 'text-[#FFBD2E]' }} font-bold">
                    {{ strtoupper($proposal->status) }}
                </span>
            </div>
        </div>
    </nav>


    <main class="max-w-6xl mx-auto px-6 py-12">
        
        <h2 class="text-xs font-mono text-[#00E5FF] tracking-widest uppercase mb-6">> EXECUTIVE BRIEFING DECK</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
            <div class="bg-white/5 border border-white/10 p-6 rounded relative overflow-hidden">
                <div class="text-[10px] text-gray-400 font-mono tracking-widest uppercase mb-2">Target Architecture</div>
                <div class="text-lg font-black text-white">{{ $proposal->archetype_id ?? 'Bespoke Enterprise System' }}</div>
            </div>
            <div class="bg-white/5 border border-white/10 p-6 rounded">
                <div class="text-[10px] text-gray-400 font-mono tracking-widest uppercase mb-2">Deployment Timeline</div>
                <div class="text-lg font-black text-white">{{ count(json_decode($narrative->ai_phases_json ?? '[]')) * 2 }} Weeks Est.</div>
            </div>
            <div class="bg-white/5 border border-white/10 p-6 rounded">
                <div class="text-[10px] text-gray-400 font-mono tracking-widest uppercase mb-2">Total Required Capital</div>
                <div class="text-2xl font-black text-[#00E5FF] font-mono">{{ $proposal->currency }} {{ number_format($proposal->total_value, 2) }}</div>
            </div>
            <div class="bg-white/5 border border-white/10 p-6 rounded">
                <div class="text-[10px] text-gray-400 font-mono tracking-widest uppercase mb-2">Phase 1 Mobilization</div>
                <div class="text-lg font-black text-[#FFBD2E] font-mono">{{ $proposal->currency }} {{ number_format($proposal->total_value * 0.40, 2) }}</div>
            </div>
            <div class="bg-white/5 border border-white/10 p-6 rounded">
                <div class="text-[10px] text-gray-400 font-mono tracking-widest uppercase mb-2">Payment Structure</div>
                <div class="text-lg font-black text-white">{{ str_replace('_', '/', $proposal->payment_schedule_type) }}</div>
            </div>
            <div class="bg-white/5 border border-[#FF3366]/30 p-6 rounded bg-[#FF3366]/5">
                <div class="text-[10px] text-[#FF3366] font-mono tracking-widest uppercase mb-2">Pricing Validity</div>
                <div class="text-lg font-black text-white">{{ $proposal->validity_days }} Days</div>
            </div>
        </div>

        <div class="bg-[#0A1128] border border-white/10 p-12 md:p-20 shadow-2xl relative mb-12">
            <h2 class="text-3xl font-serif font-black uppercase text-white border-b-2 border-[#00E5FF] pb-6 mb-8">Statement of Work (SOW)</h2>
            
            <div class="space-y-8">
                <div>
                    <h3 class="text-lg text-[#00E5FF] font-bold border-b border-white/10 pb-2 mb-4 uppercase">I. Executive Summary</h3>
                    <p class="text-sm text-gray-400 leading-relaxed text-justify whitespace-pre-wrap">{{ $narrative->ai_executive_summary }}</p>
                </div>
                
                <div>
                    <h3 class="text-lg text-[#00E5FF] font-bold border-b border-white/10 pb-2 mb-4 uppercase">II. Operational Strategy</h3>
                    <p class="text-sm text-gray-400 leading-relaxed text-justify whitespace-pre-wrap">{{ $narrative->ai_operational_strategy }}</p>
                </div>

                <div>
                    <h3 class="text-lg text-[#00E5FF] font-bold border-b border-white/10 pb-2 mb-4 uppercase">III. Commercial Blueprint</h3>
                    <table class="w-full text-left font-mono text-xs border-collapse">
                        <thead>
                            <tr class="bg-[#00E5FF]/10 text-[#00E5FF]">
                                <th class="p-3 border border-[#00E5FF]/20">DESCRIPTION</th>
                                <th class="p-3 border border-[#00E5FF]/20 text-center">QTY</th>
                                <th class="p-3 border border-[#00E5FF]/20 text-right">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-300">
                            @foreach($lines as $line)
                                <tr>
                                    <td class="p-3 border border-white/10">{{ $line->description }}</td>
                                    <td class="p-3 border border-white/10 text-center">{{ $line->quantity }}</td>
                                    <td class="p-3 border border-white/10 text-right">{{ number_format($line->total_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-6 text-right font-mono text-sm text-gray-400">
                        <div class="mb-1">SUBTOTAL: {{ $proposal->currency }} {{ number_format($proposal->subtotal, 2) }}</div>
                        @if (\App\Support\Finance\Vat::applied($proposal->vat_applied ?? null, $proposal->vat_rate ?? 0))
                            <div class="mb-2">VAT ({{ $proposal->vat_rate }}%): {{ $proposal->currency }} {{ number_format($proposal->total_value - $proposal->subtotal, 2) }}</div>
                        @endif
                        <div class="text-xl text-[#00E5FF] font-black border-t border-white/10 pt-4 mt-2">
                            GRAND TOTAL: {{ $proposal->currency }} {{ number_format($proposal->total_value, 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($proposal->status !== 'Authorized')
            <div class="bg-[#0A1128] border border-[#00E5FF] p-8 md:p-12 shadow-[0_0_50px_rgba(0,229,255,0.1)] relative">
                <h3 class="text-xl font-black uppercase text-[#00E5FF] mb-2 tracking-widest">> EXECUTIVE AUTHORIZATION</h3>
                <p class="text-xs text-gray-400 mb-8 font-mono">By executing this signature, you legally authorize JBS Praxis to spawn the Project Matrix and commence deployment. IP Address, User Agent, and Timestamp are cryptographically logged.</p>
                
                @if(!$showOtpInput)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-400 uppercase mb-2">Signatory Full Name</label>
                            <input type="text" wire:model="signatureName" x-model="nameInput" class="w-full bg-white/5 border border-white/10 text-white p-4 font-sans text-sm rounded outline-none focus:border-[#00E5FF]" placeholder="e.g. John Doe">
                            @error('signatureName') <span class="text-xs text-red-500 font-mono mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-400 uppercase mb-2">Official Designation</label>
                            <input type="text" wire:model="signatureTitle" class="w-full bg-white/5 border border-white/10 text-white p-4 font-sans text-sm rounded outline-none focus:border-[#00E5FF]" placeholder="e.g. Chief Executive Officer">
                            @error('signatureTitle') <span class="text-xs text-red-500 font-mono mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="border border-white/10 rounded mb-8 bg-white/5 p-4">
                        <div class="flex gap-4 mb-4 border-b border-white/10 pb-4">
                            <button @click="signMode = 'type'" :class="signMode === 'type' ? 'text-[#00E5FF] font-bold' : 'text-gray-500'" class="text-sm font-mono uppercase">TYPE SIGNATURE</button>
                            <button @click="signMode = 'draw'; initCanvas();" :class="signMode === 'draw' ? 'text-[#00E5FF] font-bold' : 'text-gray-500'" class="text-sm font-mono uppercase">DRAW SIGNATURE</button>
                        </div>
                        
                        <div x-show="signMode === 'type'" class="py-8 text-center flex justify-center items-center min-h-[150px]">
                            <div class="text-4xl text-white opacity-80" style="font-family: 'Brush Script MT', cursive;" x-text="nameInput || 'Type your name above'"></div>
                        </div>

                        <div x-show="signMode === 'draw'" class="relative" style="display: none;">
                            <canvas id="sigCanvas" class="w-full h-[150px] border border-white/10 bg-white/5 rounded cursor-crosshair touch-none"></canvas>
                            <button @click="clearCanvas" class="absolute top-2 right-2 text-[10px] font-mono text-gray-400 hover:text-white bg-black/50 px-2 py-1 rounded">CLEAR</button>
                        </div>
                    </div>

                    <button @click="prepareSignaturePayload" class="w-full py-5 bg-[#00E5FF] text-[#0A1128] font-black text-sm tracking-widest uppercase rounded hover:bg-white transition-colors" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="requestOTP">INITIATE CRYPTOGRAPHIC HASH (REQUEST OTP)</span>
                        <span wire:loading wire:target="requestOTP">CONNECTING TO SECURE SERVER...</span>
                    </button>
                @else
                    <div class="text-center py-8">
                        <i class="fa-solid fa-shield-keyhole text-4xl text-[#00E5FF] mb-4"></i>
                        <h4 class="text-xl font-bold text-white mb-2">Verification Required</h4>
                        <p class="text-xs text-gray-400 font-mono mb-8">A 6-digit One-Time Password has been sent to the corporate email on file.</p>
                        
                        <input type="text" wire:model="otpCode" maxlength="6" class="w-48 bg-white/5 border border-[#00E5FF] text-white p-4 font-mono text-2xl text-center tracking-[0.5em] rounded outline-none mx-auto block mb-8" placeholder="------">
                        
                        <button wire:click="authorizeSignature" class="w-full max-w-md mx-auto py-5 bg-[#00FF66] text-[#0A1128] font-black text-sm tracking-widest uppercase rounded hover:bg-white transition-colors">
                            VERIFY & EXECUTE CONTRACT
                        </button>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-[#00FF66]/10 border border-[#00FF66] p-12 text-center shadow-[0_0_50px_rgba(0,255,102,0.1)] relative">
                <div class="text-5xl text-[#00FF66] mb-6">✓</div>
                <h3 class="text-2xl font-black uppercase text-white mb-2 tracking-widest">CONTRACT EXECUTED & SECURED</h3>
                <p class="text-sm text-gray-400 mb-8 font-mono">Cryptographic Hash: {{ $proposal->crypto_hash_receipt }}<br>Timestamp: {{ $proposal->signed_at }}</p>
                
                <div class="flex justify-center gap-4">
                    <button class="py-4 px-8 bg-transparent border border-white/20 text-white font-bold text-xs tracking-widest uppercase rounded hover:bg-white/5 transition-colors">
                        DOWNLOAD RECEIPT
                    </button>
                    <button onclick="alert('Routing to Live Invoice Gateway...')" class="py-4 px-8 bg-[#00E5FF] text-[#0A1128] font-black text-xs tracking-widest uppercase rounded hover:bg-white transition-colors">
                        VIEW MOBILIZATION INVOICE
                    </button>
                </div>
            </div>
        @endif
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('signatureGateway', () => ({
                signMode: 'type',
                nameInput: '',
                canvas: null,
                ctx: null,
                isDrawing: false,

                initCanvas() {
                    if(this.canvas) return; // already init
                    setTimeout(() => {
                        this.canvas = document.getElementById('sigCanvas');
                        this.ctx = this.canvas.getContext('2d');
                        
                        // Fix scaling for crisp lines
                        const rect = this.canvas.getBoundingClientRect();
                        this.canvas.width = rect.width;
                        this.canvas.height = rect.height;
                        
                        this.ctx.strokeStyle = '#00E5FF';
                        this.ctx.lineWidth = 2;
                        this.ctx.lineCap = 'round';

                        const startPos = (e) => {
                            this.isDrawing = true;
                            this.ctx.beginPath();
                            const pos = this.getPos(e);
                            this.ctx.moveTo(pos.x, pos.y);
                        };
                        const draw = (e) => {
                            if (!this.isDrawing) return;
                            e.preventDefault();
                            const pos = this.getPos(e);
                            this.ctx.lineTo(pos.x, pos.y);
                            this.ctx.stroke();
                        };
                        const endPos = () => { this.isDrawing = false; };

                        this.canvas.addEventListener('mousedown', startPos);
                        this.canvas.addEventListener('mousemove', draw);
                        this.canvas.addEventListener('mouseup', endPos);
                        this.canvas.addEventListener('mouseout', endPos);
                        
                        this.canvas.addEventListener('touchstart', startPos, {passive: false});
                        this.canvas.addEventListener('touchmove', draw, {passive: false});
                        this.canvas.addEventListener('touchend', endPos);
                    }, 100);
                },
                getPos(e) {
                    const rect = this.canvas.getBoundingClientRect();
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    return { x: clientX - rect.left, y: clientY - rect.top };
                },
                clearCanvas() {
                    if(this.ctx && this.canvas) {
                        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                    }
                },
                prepareSignaturePayload() {
                    let b64 = '';
                    if (this.signMode === 'draw' && this.canvas) {
                        b64 = this.canvas.toDataURL('image/png');
                    } else {
                        // If typed, we just send a text flag, the backend records the typed name anyway.
                        b64 = 'TYPED_SIGNATURE_ACCEPTED'; 
                    }
                    
                    @this.set('signatureBase64', b64);
                    @this.requestOTP(); // Trigger Livewire
                }
            }));
        });

        // Event Listener for successful signature (Triggered by backend)
        window.addEventListener('signature-secured', () => {
            // Optional: You could trigger a PDF generation script here to auto-download, 
            // but the page re-renders to the "Signed" state with the download button anyway.
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</div>