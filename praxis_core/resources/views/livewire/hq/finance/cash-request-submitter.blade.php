<div>
    <style>
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid transparent; background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }
        
        .cyber-input, .cyber-select, .cyber-textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 12px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border-radius: 2px; transition: 0.3s; }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus { border-color: var(--erp-teal); outline: none; background: rgba(0,0,0,0.5); }
        .cyber-select option { background-color: var(--erp-bg-main); color: var(--erp-white); padding: 10px; }
        
        .signature-pad { border: 1px dashed var(--erp-teal); background: rgba(0, 229, 255, 0.02); border-radius: 4px; cursor: crosshair; touch-action: none; width: 100%; height: 150px; }
        .signature-pad.expanded { height: 400px; border: 2px dashed var(--erp-teal); background: #0A1128; }
        
        .glass-overlay { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.9); backdrop-filter: blur(8px); z-index: 9990; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .success-box { background: var(--erp-bg-main); border: 1px solid var(--erp-teal); border-top: 4px solid var(--erp-teal); border-radius: 4px; padding: 40px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 450px; width: 100%; }
        .error-banner { background: rgba(255,51,102,0.1); border-left: 4px solid var(--erp-red); padding: 15px; margin-bottom: 20px; border-radius: 2px; }
        .spinner { border: 3px solid rgba(0, 229, 255, 0.1); border-top: 3px solid var(--erp-teal); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>

    @if($showSuccessModal)
        <div class="glass-overlay">
            <div class="success-box">
                <div style="font-size: 3rem; margin-bottom: 10px;">🛡️</div>
                <h2 style="font-family: 'Montserrat', sans-serif; color: var(--erp-teal); margin-bottom: 5px;">REQUEST SEALED</h2>
                <div style="font-family: 'Roboto Mono', monospace; font-size: 1.2rem; font-weight: bold; color: var(--erp-white); margin-bottom: 15px;">{{ $generatedReference }}</div>
                <button wire:click="closeSuccessModal" style="background: var(--erp-teal); color: #0A1128; border: none; padding: 12px 30px; font-family: 'Roboto Mono', monospace; font-weight: bold; cursor: pointer; width: 100%;">PROCEED TO MY LEDGER</button>
            </div>
        </div>
    @endif

    <div style="margin-bottom: 30px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: var(--erp-white); letter-spacing: 1px;">CAPITAL DEPLOYMENT</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">INTERNAL EXPENSE REQUISITION</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="setTab('request')" class="nav-pill {{ $activeTab === 'request' ? 'active' : '' }}">NEW REQUEST</button>
            <button wire:click="setTab('ledger')" class="nav-pill {{ $activeTab === 'ledger' ? 'active' : '' }}">MY LEDGER & RECEIPTS</button>
        </div>
    </div>

    @if ($errors->any())
        <div class="error-banner">
            <h4 style="color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.85rem; margin-bottom: 5px;">> VALIDATION FAILURE</h4>
            <ul style="color: var(--erp-white); font-size: 0.8rem; padding-left: 20px;">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    @if($activeTab === 'request')
        <div style="display: grid; grid-template-columns: 1fr 450px; gap: 30px; align-items: start;">
            <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 8px;">1. LINK TO ACTIVE PROJECT</label>
                        <select wire:model="project_id" class="cyber-select">
                            <option value="">-- HQ Operations (No Project) --</option>
                            @foreach($projects as $proj) <option value="{{ $proj->id }}">{{ $proj->title }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 8px;">2. MASTER DICTIONARY CODE *</label>
                        <select wire:model="financial_dictionary_id" class="cyber-select" style="border-color: rgba(0,229,255,0.3);">
                            <option value="">-- Select Valid Ledger Item --</option>
                            @foreach($dictionaryItems as $item) <option value="{{ $item->id }}">{{ $item->item_code }} | {{ $item->description }}</option> @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 8px;">3. AMOUNT REQUESTED *</label>
                        <input type="number" step="0.01" wire:model="amount_requested" class="cyber-input" style="font-size: 1.2rem; font-weight: bold; color: #00FF66;">
                    </div>
                    <div>
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 8px;">CURRENCY</label>
                        <select wire:model="currency" class="cyber-select">
                            <option value="USD">USD</option><option value="XAF">XAF</option><option value="NGN">NGN</option><option value="USDT">USDT</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 8px;">4. OPERATIONAL JUSTIFICATION *</label>
                    <textarea wire:model="purpose" class="cyber-textarea" rows="3"></textarea>
                </div>

                <div style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 25px;">
                    <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 15px;">5. DESTINATION ROUTING *</label>
                    <select wire:model.live="disbursement_method" class="cyber-select" style="margin-bottom: 15px;">
                        <option value="Bank Transfer">Bank Transfer</option><option value="Mobile Money">Mobile Money</option><option value="Crypto Wallet">Crypto Wallet</option><option value="Cash">Physical Cash</option>
                    </select>

                    <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05);">
                        @if($disbursement_method === 'Bank Transfer')
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <input type="text" wire:model="payee_bank" placeholder="Bank Name" class="cyber-input">
                                <input type="text" wire:model="payee_account_number" placeholder="Account Number / IBAN" class="cyber-input">
                            </div>
                            <input type="text" wire:model="payee_account_name" placeholder="Exact Account Name" class="cyber-input">
                        @elseif($disbursement_method === 'Mobile Money')
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <select wire:model="momo_provider" class="cyber-select"><option value="MTN">MTN</option><option value="Orange">Orange</option></select>
                                <input type="text" wire:model="momo_phone" placeholder="Phone Number" class="cyber-input">
                            </div>
                            <input type="text" wire:model="momo_name" placeholder="Registered MoMo Name" class="cyber-input">
                        @elseif($disbursement_method === 'Crypto Wallet')
                            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px;">
                                <select wire:model="crypto_network" class="cyber-select"><option value="TRC20">TRC20</option><option value="ERC20">ERC20</option></select>
                                <input type="text" wire:model="crypto_address" placeholder="Wallet Address" class="cyber-input">
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px; position: relative;" x-data="signaturePad()">
                <div wire:loading.flex wire:target="submitRequest" style="position: absolute; inset: 0; background: rgba(10,17,40,0.95); z-index: 50; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                    <div class="spinner"></div><div style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-top: 15px;">GENERATING HASH...</div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(255,189,46,0.3); padding-bottom: 10px; margin-bottom: 20px;">
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: #FFBD2E;">> SEAL A: REQUESTER SIGNATURE</span>
                </div>
                <div style="position: relative;">
                    <canvas x-ref="canvas" class="signature-pad" @mousedown="startDrawing" @mousemove="draw" @mouseup="stopDrawing" @mouseleave="stopDrawing" @touchstart.prevent="startDrawing" @touchmove.prevent="draw" @touchend.prevent="stopDrawing"></canvas>
                    <button type="button" @click="clearCanvas" style="position: absolute; top: 10px; right: 10px; background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); font-size: 0.6rem; padding: 2px 6px;">CLEAR</button>
                </div>
                <button type="button" wire:click="submitRequest" wire:loading.attr="disabled" style="width: 100%; margin-top: 25px; background: #FFBD2E; color: #0A1128; border: none; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; border-radius: 2px; cursor: pointer;">
                    INITIATE CRYPTOGRAPHIC SEAL
                </button>
            </div>
        </div>

        @script
        <script>
            Alpine.data('signaturePad', () => ({
                isDrawing: false, ctx: null, activeCanvas: null,
                init() { setTimeout(() => { this.initCanvas(this.$refs.canvas); }, 100); },
                initCanvas(canvasEl) {
                    if(!canvasEl) return;
                    this.activeCanvas = canvasEl; this.ctx = canvasEl.getContext('2d');
                    const rect = canvasEl.getBoundingClientRect(); canvasEl.width = rect.width; canvasEl.height = rect.height;
                    this.ctx.lineWidth = 3; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#00E5FF';
                },
                getCoordinates(e) { const rect = this.activeCanvas.getBoundingClientRect(); const clientX = e.touches ? e.touches[0].clientX : e.clientX; const clientY = e.touches ? e.touches[0].clientY : e.clientY; return { x: clientX - rect.left, y: clientY - rect.top }; },
                startDrawing(e) { this.isDrawing = true; const { x, y } = this.getCoordinates(e); this.ctx.beginPath(); this.ctx.moveTo(x, y); },
                draw(e) { if (!this.isDrawing) return; const { x, y } = this.getCoordinates(e); this.ctx.lineTo(x, y); this.ctx.stroke(); },
                stopDrawing() { if (this.isDrawing) { this.isDrawing = false; this.ctx.closePath(); this.$wire.set('requester_signature', this.activeCanvas.toDataURL('image/png')); } },
                clearCanvas() { this.ctx.clearRect(0, 0, this.activeCanvas.width, this.activeCanvas.height); this.$wire.set('requester_signature', null); },
            }))
        </script>
        @endscript

    @elseif($activeTab === 'ledger')
        <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Ref / Date</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Category / Purpose</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right; text-transform: uppercase;">Amount</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center; text-transform: uppercase;">Proof of Execution</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($myRequests as $req)
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.02);">
                        <td style="padding: 15px 12px;">
                            <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-teal);">{{ $req->reference }}</div>
                            <div style="font-size: 0.65rem; color: var(--erp-muted); margin-top: 4px; font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($req->created_at)->format('Y-m-d H:i') }}</div>
                        </td>
                        <td style="padding: 15px 12px;">
                            <div style="font-size: 0.8rem; color: var(--erp-white); font-weight: bold;">{{ $req->category }}</div>
                            <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 2px;">{{ \Illuminate\Support\Str::limit($req->purpose, 50) }}</div>
                        </td>
                        <td style="padding: 15px 12px; text-align: right; font-family: 'Roboto Mono', monospace;">
                            <span style="font-size: 0.95rem; font-weight: bold; color: var(--erp-white);">{{ $req->currency }} {{ number_format($req->amount_requested, 2) }}</span>
                            <div style="font-size: 0.65rem; color: {{ $req->status === 'Cleared' ? '#00FF66' : ($req->status === 'Authorized' ? '#FFBD2E' : 'var(--erp-muted)') }}; margin-top: 4px;">{{ strtoupper($req->status) }}</div>
                        </td>
                        <td style="padding: 15px 12px; text-align: center;">
                            @if($req->status === 'Authorized' && !$req->receipt_url)
                                <div style="display: flex; flex-direction: column; gap: 5px; align-items: center;">
                                    <input type="file" wire:model="receipt_file" style="color: var(--erp-white); font-size: 0.6rem; width: 150px;">
                                    <button wire:click="uploadReceipt('{{ $req->id }}')" wire:loading.attr="disabled" style="background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 4px 10px; font-size: 0.65rem; font-family: 'Roboto Mono', monospace; border-radius: 2px; cursor: pointer;">UPLOAD RECEIPT</button>
                                </div>
                                @if(session()->has('message-'.$req->id)) <div style="color: #00FF66; font-size: 0.6rem; margin-top: 5px;">{{ session('message-'.$req->id) }}</div> @endif
                            @elseif($req->receipt_url)
                                <a href="{{ route('hq.file', ['path' => $req->receipt_url]) }}" target="_blank" style="color: #00FF66; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; text-decoration: underline;">[ VIEW RECEIPT ]</a>
                            @else
                                <span style="font-size: 0.65rem; color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">PENDING AUTH</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding: 40px; text-align: center; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">> NO TRANSACTIONS FOUND IN LEDGER.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>