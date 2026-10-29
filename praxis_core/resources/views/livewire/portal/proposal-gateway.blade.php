<div style="background-color: var(--erp-bg-base); min-height: 100vh; font-family: 'Inter', sans-serif; color: #fff; overflow-x: hidden;" 
     x-data="{ isExecMode: true, step: {{ $viewState === 'review' ? 0 : 4 }} }" 
     @reinit-deck.window="step = 4">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { erp: { base: '#03050A', main: '#0A1128', surface: '#141E3C', teal: '#00E5FF', red: '#FF3366', amber: '#FFBD2E', muted: '#8A9BB2', success: '#00FF66' } }, fontFamily: { sans: ['Inter', 'sans-serif'], mono: ['Roboto Mono', 'monospace'] } } } }
    </script>

    <style>
        :root { --erp-bg-base: #03050A; --erp-bg-main: #0A1128; --erp-teal: #00E5FF; --erp-red: #FF3366; --erp-amber: #FFBD2E; --erp-success: #00FF66; --erp-muted: #8A9BB2; --stark-white: #FFFFFF; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .pulse-dot { width: 6px; height: 6px; background-color: var(--erp-teal); border-radius: 50%; box-shadow: 0 0 10px var(--erp-teal); animation: pulse 2s infinite; display: inline-block; }
        
        /* 3D DECK STYLES */
        #executive-summary-view { display: flex; position: fixed; inset: 0; z-index: 9000; justify-content: center; align-items: center; background-image: url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2500&auto=format&fit=crop'); background-size: cover; background-position: center; perspective: 1500px; overflow: hidden; transition: opacity 0.6s ease; }
        #executive-summary-view::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(5, 9, 20, 0.85) 0%, rgba(10, 17, 40, 0.95) 100%); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); z-index: 0; }
        .deck-container { position: relative; width: 100%; max-width: 1200px; height: 600px; display: flex; justify-content: center; align-items: center; z-index: 2; }
        .exec-card { position: absolute; width: 100%; max-width: 500px; height: 580px; background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.1); border-top: 4px solid var(--erp-teal); border-radius: 8px; padding: 40px 30px; display: flex; flex-direction: column; box-shadow: 0 40px 80px rgba(0,0,0,0.8), inset 0 0 20px rgba(255,255,255,0.02); transition: transform 0.6s cubic-bezier(0.25, 1, 0.3, 1), opacity 0.6s; pointer-events: none; overflow-y: auto; opacity: 0; }
        
        .exec-card.active { transform: translateX(0) scale(1) translateZ(0); opacity: 1; z-index: 10; pointer-events: auto; border-color: rgba(0, 229, 255, 0.3); }
        .exec-card.prev { transform: translateX(-65%) scale(0.85) rotateY(15deg); opacity: 0.3; z-index: 5; filter: blur(3px); }
        .exec-card.next { transform: translateX(65%) scale(0.85) rotateY(-15deg); opacity: 0.3; z-index: 5; filter: blur(3px); }
        .exec-card.hidden-left { transform: translateX(-120%) scale(0.7); opacity: 0; z-index: 1; }
        .exec-card.hidden-right { transform: translateX(120%) scale(0.7); opacity: 0; z-index: 1; }

        .card-meta { font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); letter-spacing: 3px; margin-bottom: 20px; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        .card-btn { flex: 1; padding: 15px; background: var(--erp-teal); color: #0A1128; font-family: 'Inter', sans-serif; font-weight: 800; cursor: pointer; transition: all 0.3s; border-radius: 4px; text-align: center; text-transform: uppercase; font-size: 0.8rem; border: none; letter-spacing: 1px; }
        .card-btn:hover:not(:disabled) { background: #FFF; box-shadow: 0 10px 25px rgba(0, 229, 255, 0.4); transform: translateY(-2px); }
        .card-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .card-btn-outline { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.1); color: #FFF; backdrop-filter: blur(10px); }
        .card-btn-outline:hover { background: rgba(255,255,255,0.1); border-color: var(--stark-white); transform: translateY(-2px); }

        .cyber-input { width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: #FFF; padding: 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; margin-bottom: 15px; transition: 0.3s; }
        .cyber-input:focus { border-color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); }
        .sig-canvas-container { border: 1px dashed var(--erp-teal); background: rgba(0, 0, 0, 0.6); border-radius: 4px; position: relative; flex-grow: 1; min-height: 120px; margin-bottom: 15px; }
        canvas#signaturePad { width: 100%; height: 100%; cursor: crosshair; touch-action: none; }

        .tier1-dock { position: fixed; top: 50%; right: 20px; transform: translateY(-50%); display: flex; flex-direction: column; gap: 12px; background: rgba(10, 17, 40, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(0, 229, 255, 0.15); padding: 15px 12px; border-radius: 40px; z-index: 9999; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .dock-btn { background: transparent; border: none; color: rgba(255,255,255,0.5); cursor: pointer; padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.3s; position: relative; }
        .dock-btn:hover { color: var(--erp-teal); background: rgba(0, 229, 255, 0.1); }
        .dock-btn::after { content: attr(data-tooltip); position: absolute; right: 130%; background: var(--erp-main); color: var(--erp-teal); padding: 8px 12px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold; border: 1px solid rgba(0, 229, 255, 0.3); border-radius: 3px; opacity: 0; pointer-events: none; white-space: nowrap; transition: 0.3s; transform: translateX(10px); text-transform: uppercase; }
        .dock-btn:hover::after { opacity: 1; transform: translateX(0); }

        /* A4 EXPLICIT PAGING SYSTEM */
        .a4-wrapper { display: flex; flex-direction: column; gap: 40px; align-items: center; padding: 40px 20px; width: 100%; overflow-y: auto; background: var(--erp-bg-base); }
        .a4-page { width: 210mm; height: 297mm; background-color: #0A1128; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 229, 255, 0.15); border: 1px solid rgba(0, 229, 255, 0.05); flex-shrink: 0; overflow: hidden; margin: 0 auto; box-sizing: border-box; }
        .page-content { padding: 50px 60px; height: 100%; display: flex; flex-direction: column; position: relative; z-index: 10; box-sizing: border-box; }
        .sow-section { page-break-inside: avoid; break-inside: avoid; }
        
        .dual-signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 10px; }
        .sig-box { border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); padding: 25px; display: flex; flex-direction: column; position: relative; z-index: 10; }
        .sig-box.hq-stamp { border-color: rgba(0, 229, 255, 0.4); background: rgba(0, 229, 255, 0.02); }
        .sig-box.client-stamp { border-color: #00FF66; background: rgba(0, 255, 102, 0.05); }
        .sig-box.pending-stamp { border-color: var(--erp-amber); background: rgba(255, 189, 46, 0.05); }

        @media print {
            body { background: #0A1128 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            #executive-summary-view, .tier1-dock { display: none !important; }
            .a4-wrapper { padding: 0 !important; gap: 0 !important; background: transparent !important; }
            .a4-page { box-shadow: none !important; border: none !important; margin: 0 !important; }
        }
        
        @media (max-width: 850px) {
            .exec-card.prev { transform: translateX(-40%) scale(0.85); opacity: 0; }
            .exec-card.next { transform: translateX(40%) scale(0.85); opacity: 0; }
            .exec-card { padding: 30px 20px; max-width: 90%; height: 540px; }
            .tier1-dock { top: 20px; right: 50%; transform: translateX(50%); flex-direction: row; padding: 10px 20px; width: max-content; }
            .dock-btn::after { right: auto; top: 140%; left: 50%; transform: translate(-50%, -10px); }
            .dock-btn:hover::after { transform: translate(-50%, 0); }
            .deck-container { margin-top: 80px; }
            .a4-wrapper { padding-top: 100px; justify-content: flex-start; overflow-x: auto; align-items: flex-start; }
        }
    </style>

    <div class="tier1-dock">
        <button @click="isExecMode = !isExecMode; if(!isExecMode) { $nextTick(() => paginateSOW()); } else { setTimeout(initSignaturePad, 100); }" class="dock-btn" data-tooltip="Toggle Document Mode">
            <i data-lucide="layers" class="w-5 h-5"></i>
        </button>
        <div style="width: 100%; height: 1px; background: rgba(255,255,255,0.1); margin: 5px 0;"></div>
        <button onclick="triggerPdfDownload()" id="downloadPdfBtn" class="dock-btn" data-tooltip="Secure PDF Download">
            <i data-lucide="download-cloud" class="w-5 h-5"></i>
        </button>
        
        <button x-show="!isExecMode" 
                @click="isExecMode = true; if(step === 4) setTimeout(initSignaturePad, 100);" 
                class="dock-btn" 
                style="background: rgba(255, 189, 46, 0.15); border: 1px solid var(--erp-amber); color: var(--erp-amber); margin-top: 10px; animation: pulse 2s infinite;" 
                data-tooltip="Sign Document">
            <i data-lucide="pen-tool" class="w-5 h-5"></i>
        </button>
    </div>

    <div id="executive-summary-view" x-show="isExecMode" x-transition.opacity.duration.600ms>
        <div class="deck-container">
            
            <div class="exec-card" :class="{ 'active': step === 0, 'prev': step === -1, 'hidden-left': step < -1, 'next': step === 1, 'hidden-right': step > 1 }">
                <div class="card-meta"><span>01 // 05</span> <span class="pulse-dot"></span></div>
                <h3 class="text-2xl font-black uppercase tracking-tighter mb-4 text-white">STATEMENT OF WORK</h3>
                <p class="text-sm text-erp-muted leading-relaxed mb-6">Prepared exclusively for <strong>{{ strtoupper($organization->name ?? 'CLIENT') }}</strong>. This document outlines the architectural parameters, financial allocation, and execution phases required to deploy the JBS Praxis Operating System.</p>
                
                <div class="bg-black/40 border border-white/5 p-4 border-l-4 border-erp-teal mb-4">
                    <span class="block font-mono text-[10px] text-erp-muted mb-1 uppercase">Total Capital Allocation</span>
                    <span class="font-mono text-2xl font-black text-white">{{ $proposal->currency ?? 'USD' }} {{ number_format($proposal->total_value ?? 0, 2) }}</span>
                </div>
                
                <div class="bg-black/40 border border-white/5 p-4 border-l-4 border-erp-amber mb-auto">
                    <span class="block font-mono text-[10px] text-erp-muted mb-1 uppercase">Agile Payment Structure</span>
                    <span class="font-mono text-sm font-bold text-white">
                        @if(($proposal->payment_schedule_type ?? '') === 'custom')
                            {{ (float)($proposal->custom_deposit_percent ?? 0) }}% Upfront Deposit
                        @else
                            {{ str_replace('_', '/', $proposal->payment_schedule_type ?? 'Standard') }} Split
                        @endif
                    </span>
                </div>

                <div class="flex gap-4 mt-6">
                    <button @click="step++" class="card-btn w-full">REVIEW ARCHITECTURE ➔</button>
                </div>
            </div>

            <div class="exec-card" :class="{ 'active': step === 1, 'prev': step === 0, 'hidden-left': step < 0, 'next': step === 2, 'hidden-right': step > 2 }">
                <div class="card-meta"><span>02 // 05</span> <span class="pulse-dot"></span></div>
                <h3 class="text-2xl font-black uppercase tracking-tighter mb-4 text-white">THE ARCHITECTURE</h3>
                <p class="text-xs text-erp-muted leading-relaxed mb-4 text-justify">{{ Str::limit($narrative->ai_operational_strategy ?? 'Executing systemic overhaul.', 350) }}</p>
                
                <div class="mb-auto">
                    <div class="font-mono text-[10px] text-erp-teal mb-2 uppercase">> Technology & Compliance Stack</div>
                    <div class="flex flex-wrap gap-2">
                        @if(!empty($selectedTech))
                            @foreach($selectedTech as $tech)
                                <span class="bg-white/5 border border-white/10 px-2 py-1 font-mono text-[9px] text-white rounded-sm">{{ $tech }}</span>
                            @endforeach
                        @else
                            <span class="text-xs text-erp-muted">Architecture loosely coupled. Custom stack pending.</span>
                        @endif
                    </div>
                </div>

                <div class="flex gap-4 mt-6">
                    <button @click="step--" class="card-btn card-btn-outline w-1/3">BACK</button>
                    <button @click="step++" class="card-btn w-2/3">REVIEW SPRINTS ➔</button>
                </div>
            </div>

            <div class="exec-card" :class="{ 'active': step === 2, 'prev': step === 1, 'hidden-left': step < 1, 'next': step === 3, 'hidden-right': step > 3 }">
                <div class="card-meta"><span>03 // 05</span> <span class="pulse-dot"></span></div>
                <h3 class="text-2xl font-black uppercase tracking-tighter mb-4 text-white">EXECUTION MATRIX</h3>
                
                <div class="space-y-3 mb-auto overflow-y-auto pr-2 max-h-[350px]">
                    @if(!empty($phases))
                        @foreach($phases as $phase)
                            <div class="bg-erp-surface p-3 border border-white/5 border-l-2 border-erp-teal">
                                <strong class="font-mono text-[10px] text-erp-teal block mb-1">{{ current(explode(':', $phase['title'] ?? 'Phase')) }}</strong>
                                <span class="text-[10px] text-erp-muted block">{{ $phase['body'] ?? '' }}</span>
                            </div>
                        @endforeach
                    @else
                        <div class="text-xs text-erp-muted">Phases pending definition.</div>
                    @endif
                </div>

                <div class="flex gap-4 mt-6">
                    <button @click="step--" class="card-btn card-btn-outline w-1/3">BACK</button>
                    <button @click="step++" class="card-btn w-2/3">REVIEW COMMERCIALS ➔</button>
                </div>
            </div>

            <div class="exec-card" :class="{ 'active': step === 3, 'prev': step === 2, 'hidden-left': step < 2, 'next': step === 4, 'hidden-right': step > 4 }">
                <div class="card-meta"><span>04 // 05</span> <span class="pulse-dot"></span></div>
                <h3 class="text-2xl font-black uppercase tracking-tighter mb-4 text-white">COMMERCIALS</h3>
                
                <div class="space-y-2 mb-4 max-h-[200px] overflow-y-auto pr-2">
                    @if(!empty($lineItems))
                        @foreach($lineItems as $line)
                            <div class="flex justify-between items-center border-b border-white/10 pb-2">
                                <div>
                                    <div class="font-mono text-[10px] text-white font-bold">{{ $line->code ?? '-' }}</div>
                                    <div class="text-[9px] text-erp-muted">{{ Str::limit($line->description ?? '', 35) }}</div>
                                </div>
                                <div class="font-mono text-[11px] font-bold text-erp-teal">{{ $proposal->currency ?? 'USD' }} {{ number_format($line->total_price ?? 0, 2) }}</div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="mt-auto border-t border-white/10 pt-4 font-mono text-[10px] text-erp-muted text-right">
                    <div class="mb-1">SUBTOTAL: {{ $proposal->currency ?? 'USD' }} {{ number_format($proposal->subtotal ?? 0, 2) }}</div>
                    <div class="mb-3">VAT ({{ $proposal->vat_rate ?? 0 }}%): {{ $proposal->currency ?? 'USD' }} {{ number_format(($proposal->total_value ?? 0) - ($proposal->subtotal ?? 0), 2) }}</div>
                </div>

                <div class="bg-erp-teal/10 border border-erp-teal p-3 text-center mb-6">
                    <span class="font-mono text-[10px] text-erp-teal uppercase block mb-1">Total Investment</span>
                    <span class="font-mono text-xl font-black text-white">{{ $proposal->currency ?? 'USD' }} {{ number_format($proposal->total_value ?? 0, 2) }}</span>
                </div>

                <div class="flex gap-4">
                    <button @click="step--" class="card-btn card-btn-outline w-1/3">BACK</button>
                    <button @click="step++; setTimeout(initSignaturePad, 100);" class="card-btn w-2/3" style="background: var(--erp-amber); color: #000;">AUTHORIZATION ➔</button>
                </div>
            </div>

            <div class="exec-card" :class="{ 'active': step === 4, 'prev': step === 3, 'hidden-left': step < 3 }" style="border-top-color: {{ ($viewState ?? '') === 'completed' ? 'var(--erp-success)' : 'var(--erp-amber)' }};">
                <div class="card-meta"><span style="color: {{ ($viewState ?? '') === 'completed' ? 'var(--erp-success)' : 'var(--erp-amber)' }};">05 // 05</span> <span class="pulse-dot" style="background: {{ ($viewState ?? '') === 'completed' ? 'var(--erp-success)' : 'var(--erp-amber)' }}; box-shadow: none;"></span></div>
                
                @if(($viewState ?? '') === 'completed')
                    <div class="flex flex-col items-center justify-center h-full text-center">
                        <i data-lucide="shield-check" style="width: 80px; height: 80px; color: var(--erp-success); margin-bottom: 20px;"></i>
                        <h3 class="text-3xl font-black text-white uppercase tracking-widest mb-2">SOW SECURED</h3>
                        <p class="text-sm text-erp-muted mb-6">Cryptographic binding confirmed. Initializing Client OS.</p>
                        <p class="text-xs font-mono text-erp-teal mb-6">A secure access link has been dispatched to your email.</p>
                        <button @click="isExecMode = false; $nextTick(() => paginateSOW());" class="card-btn w-full" style="background: transparent; border: 1px solid var(--erp-teal); color: var(--erp-teal);">VIEW IMMUTABLE DOCUMENT</button>
                    </div>

                @elseif(($viewState ?? '') === 'otp_verification')
                    <h3 class="text-xl font-black uppercase tracking-tighter mb-2 text-white text-center">MULTI-FACTOR AUTHORIZATION</h3>
                    <p class="text-[11px] text-erp-muted mb-6 text-center">A cryptographic OTP has been transmitted to <strong>{{ $signerEmail ?? '' }}</strong> to verify node authority.</p>
                    
                    @if(session()->has('error'))
                        <div style="background: rgba(255,51,102,0.1); border: 1px solid #FF3366; color: #FF3366; padding: 10px; font-family: 'Roboto Mono'; font-size: 0.7rem; margin-bottom: 15px; text-align: center;">{{ session('error') }}</div>
                    @endif

                    <form wire:submit.prevent="verifyOtpAndExecuteLaunch" class="flex flex-col h-full">
                        <div class="mb-auto mt-4">
                            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-amber); display: block; margin-bottom: 10px; text-align: center;">ENTER 6-DIGIT PROTOCOL</label>
                            <input type="text" wire:model.defer="otpInput" class="cyber-input" placeholder="• • • • • •" required maxlength="6" style="text-align: center; font-size: 1.5rem; letter-spacing: 8px; border-color: var(--erp-amber); height: 70px; font-weight: bold;">
                            @error('otpInput') <span style="color: #FF3366; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; text-align: center; display: block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-col gap-3 mt-auto">
                            <button type="submit" class="card-btn w-full" style="background: var(--erp-success); color: #000;" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="verifyOtpAndExecuteLaunch">VERIFY & BIND ➔</span>
                                <span wire:loading wire:target="verifyOtpAndExecuteLaunch">EXECUTING MATRICES...</span>
                            </button>
                            <button type="button" @click="isExecMode = false; $nextTick(() => paginateSOW());" class="card-btn card-btn-outline w-full" style="padding: 10px; font-size: 0.7rem;">REVIEW A4 DOCUMENT</button>
                        </div>
                    </form>

                @else
                    <h3 class="text-xl font-black uppercase tracking-tighter mb-2 text-white">EXECUTIVE BINDING</h3>
                    
                    <div style="background: rgba(0, 229, 255, 0.05); border: 1px solid rgba(0, 229, 255, 0.2); padding: 12px; margin-bottom: 15px; text-align: center;">
                        <p class="text-[10px] text-erp-muted mb-2">Ensure you have reviewed the full immutable legal document before applying your cryptographic seal.</p>
                        <button type="button" @click="isExecMode = false; $nextTick(() => paginateSOW());" style="background: transparent; border: none; color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; text-decoration: underline; cursor: pointer;">[ READ FULL DOCUMENT ]</button>
                    </div>
                    
                    @error('signerEmail')
                        <div style="background: rgba(255,51,102,0.15); border-left: 4px solid #FF3366; color: #FF3366; padding: 12px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; animation: pulse 2s infinite;">
                            <i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i>
                            {{ $message }}
                        </div>
                    @enderror

                    <form id="signature-form" class="flex flex-col h-full">
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <input type="text" wire:model.defer="signerName" class="cyber-input !mb-0" placeholder="Full Legal Name" required>
                            <input type="text" wire:model.defer="signerTitle" class="cyber-input !mb-0" placeholder="Corporate Title" required>
                        </div>
                        <input type="email" wire:model.defer="signerEmail" class="cyber-input" placeholder="Authorized Executive Email" required>
                        
                        <div class="sig-canvas-container flex-grow" wire:ignore>
                            <span class="absolute top-2 left-2 font-mono text-[8px] text-erp-teal uppercase pointer-events-none">Sign Below:</span>
                            <button type="button" onclick="clearCanvas()" class="absolute top-2 right-2 font-mono text-[8px] text-erp-muted hover:text-white uppercase z-10">CLEAR</button>
                            <canvas id="signaturePad"></canvas>
                        </div>

                        <div class="flex gap-4 mt-auto">
                            <button type="button" @click="step--" class="card-btn card-btn-outline prev-card-btn w-1/3">BACK</button>
                            <button type="button" onclick="submitSignature()" class="card-btn w-2/3" style="background: var(--erp-amber); color: #000;" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="requestAuthorizationOtp">AUTHORIZE ➔</span>
                                <span wire:loading wire:target="requestAuthorizationOtp">SECURING...</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>

        </div>
    </div>


    <div id="sow-raw-content" style="display: none;">
        
        <div class="sow-section w-full mb-8" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-family: 'Montserrat'; font-weight: 900; letter-spacing: 2px; font-size: 1.2rem; color: #FFF;">JBS PRAXIS</div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; margin-bottom: 10px;">
                        Intelligence Desk &bull; intelligence@jbspraxis.com
                    </div>
                </div>
                <img src="{{ asset('assets/img/logo_monochrome.svg') }}" style="height: 40px; opacity: 0.9;" alt="JBS Logo" onerror="this.style.display='none'">
            </div>
            
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-top: 10px;">
                <span style="margin-right: 20px;">REF: <span style="color: #FFF; font-weight: bold;">PRX-{{ strtoupper(substr($token ?? 'DRAFT', 0, 8)) }}</span></span>
                <span>DATE: <span style="color: #FFF;">{{ \Carbon\Carbon::parse($proposal->updated_at ?? now())->format('F j, Y') }}</span></span>
            </div>
        </div>

        <div class="sow-section mb-8">
            <h1 style="font-family: 'Montserrat'; font-size: 1.8rem; font-weight: 900; color: #FFF; text-transform: uppercase; border-bottom: 2px solid var(--erp-teal); padding-bottom: 15px; margin-bottom: 20px; letter-spacing: -0.5px;">{{ $proposal->title ?? 'DOCUMENT TITLE' }}</h1>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: rgba(255,255,255,0.02); padding: 20px; border: 1px solid rgba(255,255,255,0.05); font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">
                <div><span style="color: var(--erp-muted);">CLIENT:</span> <span style="color: #FFF; font-weight: bold;">{{ strtoupper($organization->name ?? 'UNKNOWN') }}</span></div>
                <div><span style="color: var(--erp-muted);">CURRENCY:</span> <span style="color: var(--erp-teal); font-weight: bold;">{{ $proposal->currency ?? 'USD' }}</span></div>
                <div><span style="color: var(--erp-muted);">VALIDITY:</span> <span style="color: #FFF; font-weight: bold;">{{ $proposal->validity_days ?? 7 }} DAYS</span></div>
                <div>
                    <span style="color: var(--erp-muted);">PAYMENT PLAN:</span> 
                    <span style="color: #FFF; font-weight: bold;">
                        @if(($proposal->payment_schedule_type ?? '') === 'custom')
                            {{ (float)($proposal->custom_deposit_percent ?? 0) }}% Upfront Deposit
                        @else
                            {{ str_replace('_', '/', $proposal->payment_schedule_type ?? 'Standard') }} Split
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="sow-section mb-6">
            <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 10px; text-transform: uppercase;">I. EXECUTIVE SUMMARY</h2>
            <p style="font-size: 0.8rem; color: #B0B8C4; line-height: 1.6; white-space: pre-wrap; text-align: justify;">{{ $narrative->ai_executive_summary ?? 'Pending...' }}</p>
        </div>

        <div class="sow-section mb-6">
            <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 10px; text-transform: uppercase;">II. OPERATIONAL STRATEGY</h2>
            <p style="font-size: 0.8rem; color: #B0B8C4; line-height: 1.6; white-space: pre-wrap; text-align: justify;">{{ $narrative->ai_operational_strategy ?? 'Pending...' }}</p>
        </div>

        @if(!empty($selectedTech) && count($selectedTech) > 0)
        <div class="sow-section mb-8">
            <div style="background: rgba(0,229,255,0.02); border-left: 2px solid var(--erp-teal); padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem;">
                <strong style="color: var(--erp-teal); display: block; margin-bottom: 10px; text-transform: uppercase;">> ARCHITECTURE & COMPLIANCE STACK:</strong>
                <ul style="list-style-type: none; padding: 0; margin: 0; color: #E2E8F0; display: grid; grid-template-columns: 1fr 1fr; gap: 5px;">
                    @foreach($selectedTech as $tech)
                        <li>- {{ $tech }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <div class="sow-section mb-8">
            <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 15px; text-transform: uppercase;">III. DEPLOYMENT SPRINTS</h2>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                @if(!empty($phases))
                    @foreach($phases as $phase)
                        <div style="background: rgba(255,255,255,0.02); border-left: 2px solid var(--erp-teal); padding: 15px;">
                            <strong style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); display: block; margin-bottom: 5px;">{{ $phase['title'] ?? '' }}</strong>
                            <span style="font-size: 0.8rem; color: #B0B8C4;">{{ $phase['body'] ?? '' }}</span>
                        </div>
                    @endforeach
                @else
                    <div style="font-size: 0.8rem; color: var(--erp-muted);">Sprint definitions pending...</div>
                @endif
            </div>
        </div>

        <div class="sow-section mb-8">
            <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 15px; text-transform: uppercase;">IV. COMMERCIAL BLUEPRINT</h2>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">
                <thead>
                    <tr style="background: rgba(0, 229, 255, 0.1); color: var(--erp-teal);">
                        <th style="padding: 10px; border: 1px solid rgba(255,255,255,0.1); text-align: left;">CODE</th>
                        <th style="padding: 10px; border: 1px solid rgba(255,255,255,0.1); text-align: left;">DESCRIPTION</th>
                        <th style="padding: 10px; border: 1px solid rgba(255,255,255,0.1); text-align: center;">QTY</th>
                        <th style="padding: 10px; border: 1px solid rgba(255,255,255,0.1); text-align: right;">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($lineItems))
                        @foreach($lineItems as $line)
                            <tr>
                                <td style="padding: 10px; border: 1px solid rgba(255,255,255,0.05); color: #FFF; font-weight: bold;">{{ $line->code ?? '-' }}</td>
                                <td style="padding: 10px; border: 1px solid rgba(255,255,255,0.05); color: var(--erp-muted);">{{ $line->description ?? '-' }}</td>
                                <td style="padding: 10px; border: 1px solid rgba(255,255,255,0.05); color: #FFF; text-align: center;">{{ $line->quantity ?? '-' }}</td>
                                <td style="padding: 10px; border: 1px solid rgba(255,255,255,0.05); color: #FFF; text-align: right;">{{ number_format($line->total_price ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
            <div style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">
                <div style="margin-bottom: 5px;">SUBTOTAL: {{ $proposal->currency ?? 'USD' }} {{ number_format($proposal->subtotal ?? 0, 2) }}</div>
                <div style="margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">TAX ({{ $proposal->vat_rate ?? 0 }}%): {{ $proposal->currency ?? 'USD' }} {{ number_format(($proposal->total_value ?? 0) - ($proposal->subtotal ?? 0), 2) }}</div>
                <div style="font-size: 1.1rem; font-weight: bold; color: var(--erp-teal);">TOTAL: {{ $proposal->currency ?? 'USD' }} {{ number_format($proposal->total_value ?? 0, 2) }}</div>
            </div>
        </div>

        @if(!empty($proposal->exclusions))
        <div class="sow-section mb-6">
            <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-red); margin-bottom: 10px; text-transform: uppercase;">V. BOUNDARIES & EXCLUSIONS</h2>
            <p style="font-size: 0.75rem; color: #FF8A8A; line-height: 1.6; white-space: pre-wrap;">{{ $proposal->exclusions }}</p>
        </div>
        @endif

        <div class="sow-section mb-8">
            <h2 style="font-family: 'Montserrat'; font-size: 0.9rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 15px; text-transform: uppercase;">{{ !empty($proposal->exclusions) ? 'VI.' : 'V.' }} EXECUTIVE AUTHORIZATION</h2>
            
            <div class="dual-signature-grid">
                <div class="sig-box hq-stamp">
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; margin-bottom: 15px; border-bottom: 1px dashed rgba(255,255,255,0.2); padding-bottom: 10px; color: var(--erp-teal); text-align: center;">✓ ORIGINATOR SECURED</div>
                    <div style="height: 60px; margin-bottom: 10px; display: flex; justify-content: center;">
                        <img src="{{ $proposal->hq_signature_b64 ?? '' }}" style="max-height: 100%; object-fit: contain;">
                    </div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>DISPATCHER:</span><span>{{ strtoupper($proposal->hq_signer_name ?? 'SYSTEM') }}</span></div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>TIMESTAMP:</span><span>{{ !empty($proposal->hq_signed_at) ? \Carbon\Carbon::parse($proposal->hq_signed_at)->format('Y-m-d H:i:s') : '-' }}</span></div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>NODE IP:</span><span>{{ $proposal->hq_ip_address ?? '-' }}</span></div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.55rem; color: var(--erp-teal); font-weight: bold; word-break: break-all; margin-top: 10px; border-top: 1px dashed rgba(0,229,255,0.3); padding-top: 10px; text-align: center;">
                        {{ $proposal->hq_signature_hash ?? 'PRX-AUTH-PENDING' }}
                    </div>
                </div>

                @if(($viewState ?? '') === 'completed')
                    <div class="sig-box client-stamp">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; margin-bottom: 15px; border-bottom: 1px dashed rgba(255,255,102,0.2); padding-bottom: 10px; color: #00FF66; text-align: center;">✓ COUNTER-SIGNED</div>
                        <div style="height: 60px; margin-bottom: 10px; display: flex; justify-content: center;">
                            <img src="{{ $proposal->client_signature_b64 ?? '' }}" style="max-height: 100%; object-fit: contain;">
                        </div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>SIGNATORY:</span><span>{{ strtoupper($proposal->signed_by_name ?? '') }}</span></div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>TIMESTAMP:</span><span>{{ !empty($proposal->signed_at) ? \Carbon\Carbon::parse($proposal->signed_at)->format('Y-m-d H:i:s') : '-' }}</span></div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>NODE IP:</span><span>{{ $proposal->client_ip_address ?? '-' }}</span></div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.55rem; color: #00FF66; font-weight: bold; word-break: break-all; margin-top: 10px; border-top: 1px dashed rgba(0,255,102,0.3); padding-top: 10px; text-align: center;">
                            {{ $proposal->client_signature_hash ?? 'HASH_PENDING' }}
                        </div>
                    </div>
                @elseif(($viewState ?? '') === 'otp_verification')
                    <div class="sig-box pending-stamp">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; margin-bottom: 15px; border-bottom: 1px dashed rgba(255,189,46,0.3); padding-bottom: 10px; color: var(--erp-amber); text-align: center; animation: pulse 2s infinite;">PENDING OTP VERIFICATION</div>
                        <div style="height: 60px; margin-bottom: 10px; display: flex; justify-content: center; opacity: 0.7;">
                            <img src="{{ $proposal->client_signature_b64 ?? '' }}" style="max-height: 100%; object-fit: contain;">
                        </div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>SIGNATORY:</span><span>{{ strtoupper(explode(' (', $proposal->signed_by_name ?? '')[0] ?? 'PENDING') }}</span></div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #FFF; margin-bottom: 5px; display: flex; justify-content: space-between;"><span>TIMESTAMP:</span><span>AWAITING OTP</span></div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.55rem; color: var(--erp-amber); font-weight: bold; word-break: break-all; margin-top: 10px; border-top: 1px dashed rgba(255,189,46,0.3); padding-top: 10px; text-align: center;">AUTHORIZATION PAUSED</div>
                    </div>
                @else
                    <div class="sig-box" style="border-color: rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; margin-bottom: 15px; border-bottom: 1px dashed rgba(255,255,255,0.2); padding-bottom: 10px; color: var(--erp-muted); text-align: center;">AWAITING CLIENT BINDING</div>
                        <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--erp-muted); font-size: 0.7rem; font-family: 'Roboto Mono'; text-align: center;">Return to Executive Deck to sign.</div>
                    </div>
                @endif
            </div>
            
            @if(($viewState ?? '') === 'completed')
                <div style="margin-top: 30px; padding: 20px; border: 1px dashed rgba(255,255,255,0.1); background: rgba(0,0,0,0.3); display: flex; gap: 20px; align-items: center;">
                    @php
                        $baseUrl = str(route('portal.verify.contract', ['hash' => 'HASH']))->beforeLast('/HASH')->toString();
                        $hqHash = $proposal->hq_signature_hash ?? '';
                        $cliHash = $proposal->client_signature_hash ?? '';
                        $qrData = "URL:{$baseUrl}/{$cliHash} | ORG:{$hqHash} | CLT:{$cliHash}";
                    @endphp
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($qrData) }}&color=00FF66&bgcolor=03050A" style="width: 80px; height: 80px; border: 2px solid #00FF66; padding: 4px; background: #03050A;">
                    <div style="font-family: 'Roboto Mono', monospace;">
                        <div style="font-size: 1rem; font-weight: bold; color: var(--erp-white); letter-spacing: 2px;">JBS PRAXIS HQ</div>
                        <div style="font-size: 0.7rem; color: #00FF66; margin-bottom: 5px;">✓ DUAL-NODE CRYPTOGRAPHY VERIFIED</div>
                        <div style="font-size: 0.6rem; color: var(--erp-muted);">Scan cryptographic matrix to verify origin immutability on Praxis servers.</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div id="full-dossier-wrapper" class="a4-wrapper" x-show="!isExecMode" style="display: none;">
        <div id="pdf-render-zone" style="display: flex; flex-direction: column; gap: 40px;">
            </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => { if(typeof lucide !== 'undefined') lucide.createIcons(); initSignaturePad(); });
        document.addEventListener("livewire:navigated", () => { if(typeof lucide !== 'undefined') lucide.createIcons(); initSignaturePad(); });

        // --- CLIENT SIGNATURE PAD ENGINE ---
        let canvas, ctx, isDrawing = false;
        function initSignaturePad() {
            canvas = document.getElementById('signaturePad');
            if(!canvas) return;
            ctx = canvas.getContext('2d');
            setTimeout(() => {
                canvas.width = canvas.parentElement.offsetWidth;
                canvas.height = canvas.parentElement.offsetHeight;
                ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#00E5FF'; 
            }, 100);

            const startPosition = (e) => { isDrawing = true; draw(e); };
            const finishPosition = () => { isDrawing = false; ctx.beginPath(); };
            const draw = (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                let rect = canvas.getBoundingClientRect();
                let x = (e.clientX || e.touches[0].clientX) - rect.left;
                let y = (e.clientY || e.touches[0].clientY) - rect.top;
                ctx.lineTo(x, y); ctx.stroke(); ctx.beginPath(); ctx.moveTo(x, y);
            };

            canvas.addEventListener('mousedown', startPosition); canvas.addEventListener('mouseup', finishPosition);
            canvas.addEventListener('mousemove', draw); canvas.addEventListener('touchstart', startPosition, {passive: false});
            canvas.addEventListener('touchend', finishPosition); canvas.addEventListener('touchmove', draw, {passive: false});
        }
        function clearCanvas() { if(ctx && canvas) { ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.beginPath(); } }
        function submitSignature() {
            if(!canvas) return;
            const dataUrl = canvas.toDataURL('image/png');
            const blank = document.createElement('canvas'); blank.width = canvas.width; blank.height = canvas.height;
            if(dataUrl === blank.toDataURL()) { alert("SYSTEM HALT: Please provide your cryptographic signature."); return; }
            @this.call('requestAuthorizationOtp', dataUrl);
        }

        // --- DOM PAGINATOR (Builds beautiful on-screen A4 pages) ---
        function paginateSOW() {
            const rawContent = document.getElementById('sow-raw-content');
            const renderZone = document.getElementById('pdf-render-zone');
            if (!rawContent || !renderZone) return;

            // Optional Loader
            renderZone.innerHTML = '<div style="text-align:center; padding: 40px; font-family: \'Roboto Mono\'; color: #00E5FF;">Compiling Cryptographic Matrix...</div>';
            
            setTimeout(() => {
                renderZone.innerHTML = '';
                let pageNum = 1;
                let currentPage = createA4Page(pageNum);
                renderZone.appendChild(currentPage);
                
                let contentArea = currentPage.querySelector('.page-content');
                // 1012px is roughly the inner available height of 297mm A4 with 110px total vertical padding
                const MAX_HEIGHT = contentArea.clientHeight || 1012; 

                Array.from(rawContent.children).forEach((child) => {
                    const clone = child.cloneNode(true);
                    contentArea.appendChild(clone);
                    
                    // If adding this section caused an overflow, move it to a new page
                    if (contentArea.scrollHeight > MAX_HEIGHT) {
                        contentArea.removeChild(clone);
                        
                        pageNum++;
                        currentPage = createA4Page(pageNum);
                        renderZone.appendChild(currentPage);
                        contentArea = currentPage.querySelector('.page-content');
                        contentArea.appendChild(clone);
                    }
                });
                
                // Inject the final total page count across all footers
                document.querySelectorAll('.total-pages').forEach(el => el.innerText = pageNum);
            }, 50);
        }

        function createA4Page(num) {
            const page = document.createElement('div');
            page.className = 'a4-page';
            
            // The SVG Background String
            const bgSvg = "data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 0h40v40H0V0zm20 20h20v20H20V20zM0 20h20v20H0V20z' fill='%23ffffff' fill-opacity='0.01' fill-rule='evenodd'/%3E%3C/svg%3E";
            
            page.innerHTML = `
                <div style="position: absolute; inset: 0; background-image: url(\"${bgSvg}\"); pointer-events: none; z-index: 0;"></div>
                <div class="doc-watermark" style="top: 40px; right: -30px; font-family: 'Roboto Mono', monospace; font-size: 8rem; color: rgba(255,255,255,0.015); font-weight: 900; pointer-events: none; transform: rotate(-90deg); transform-origin: top right; position: absolute; letter-spacing: -2px; z-index: 1;">SOW.BINDING</div>
                
                <div class="page-content"></div>
                
                <div style="position: absolute; bottom: 30px; left: 60px; right: 60px; display: flex; justify-content: space-between; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: var(--erp-muted); border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px; z-index: 10;">
                    <div>JBS PRAXIS // ENTERPRISE ARCHITECTURE</div>
                    <div>PAGE ${num} OF <span class="total-pages"></span></div>
                </div>
            `;
            return page;
        }

        // --- PDF GENERATOR (Flawless 1:1 Rendering) ---
        async function triggerPdfDownload() {
            const btn = document.getElementById('downloadPdfBtn');
            if (!btn) return;
            const origHTML = btn.innerHTML;
            const origTooltip = btn.getAttribute('data-tooltip');
            
            btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5 animate-spin" style="animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="4.93" x2="19.07" y2="7.76"></line></svg>`;
            btn.setAttribute('data-tooltip', 'GENERATING PDF...');
            btn.style.pointerEvents = "none";
            
            await new Promise(r => setTimeout(r, 100));

            try {
                const wrapper = document.getElementById('full-dossier-wrapper');
                const renderZone = document.getElementById('pdf-render-zone');
                
                const wasHidden = wrapper.style.display === 'none';
                if(wasHidden) {
                    wrapper.style.display = 'flex';
                    paginateSOW(); 
                    await new Promise(r => setTimeout(r, 200)); // wait for DOM paginator
                }

                // 1. Temporarily strip visual UI gaps/borders so the PDF engine joins the pages seamlessly
                const origGap = renderZone.style.gap;
                renderZone.style.gap = '0px'; 
                const pages = document.querySelectorAll('.a4-page');
                pages.forEach(p => { p.style.border = 'none'; p.style.boxShadow = 'none'; });

                // 2. Format Client Name dynamically
                const clientName = "{{ strtoupper(str_replace(' ', '_', $organization->name ?? 'CLIENT')) }}";

                // 3. Command html2pdf
                const opt = {
                    margin: 0, // NO margins. Allows the #0A1128 background to reach the true edge.
                    filename: `SOW_JBS_PRAXIS_${clientName}.pdf`,
                    image: { type: 'jpeg', quality: 1.0 },
                    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#0A1128' },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };

                await html2pdf().set(opt).from(renderZone).save();
                
                // 4. Restore UI
                renderZone.style.gap = origGap;
                pages.forEach(p => { p.style.border = ''; p.style.boxShadow = ''; });
                if(wasHidden) wrapper.style.display = 'none';
                
            } catch (err) { 
                console.error(err);
                alert("PDF Engine Failed."); 
            } finally {
                btn.innerHTML = origHTML; 
                btn.setAttribute('data-tooltip', origTooltip);
                btn.style.pointerEvents = "auto";
            }
        }
    </script>
</div>