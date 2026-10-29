<div>
    <style>
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s; border: 1px solid transparent; background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .nav-pill:not(.active):hover { background: rgba(255,255,255,0.05); color: var(--erp-white); }

        .glass-modal { position: fixed; inset: 0; background: rgba(3, 5, 10, 0.9); backdrop-filter: blur(10px); z-index: 9990; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .modal-box { background: var(--erp-bg-main); border: 1px solid var(--erp-teal); border-top: 4px solid var(--erp-teal); border-radius: 4px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); z-index: 9999; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; }
        
        .signature-pad { border: 1px dashed #FFBD2E; background: rgba(255, 189, 46, 0.02); border-radius: 4px; cursor: crosshair; touch-action: none; width: 100%; height: 150px; }
        .signature-pad.expanded { height: 400px; border: 2px dashed #FFBD2E; background: #0A1128; }

        .error-banner { background: rgba(255,51,102,0.1); border-left: 4px solid var(--erp-red); padding: 15px; margin-bottom: 20px; border-radius: 2px; }
        .spinner { border: 3px solid rgba(0, 229, 255, 0.1); border-top: 3px solid var(--erp-teal); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    </style>

    <div style="margin-bottom: 30px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: var(--erp-white); letter-spacing: 1px;">TREASURY & EXPENSE COMMAND</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">INTERNAL LEDGER AUTHORIZATION & AUDIT CLEARANCE</p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button wire:click="setTab('pending_auth')" class="nav-pill {{ $activeTab === 'pending_auth' ? 'active' : '' }}">PENDING AUTH</button>
            <button wire:click="setTab('pending_clearance')" class="nav-pill {{ $activeTab === 'pending_clearance' ? 'active' : '' }}">AUDIT CLEARANCE</button>
            <button wire:click="setTab('history')" class="nav-pill {{ $activeTab === 'history' ? 'active' : '' }}">LEDGER HISTORY</button>
        </div>
    </div>

    @if ($errors->any())
        <div class="error-banner">
            <h4 style="color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.85rem; margin-bottom: 5px;">> VALIDATION FAILURE</h4>
            <ul style="color: var(--erp-white); font-size: 0.8rem; padding-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5);">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Reference / Date</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-transform: uppercase;">Operator / Purpose</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right; text-transform: uppercase;">Amount</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center; text-transform: uppercase;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 15px 12px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                        <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: var(--erp-teal);">{{ $req->reference }}</div>
                        <div style="font-size: 0.65rem; color: var(--erp-muted); margin-top: 4px; font-family: 'Roboto Mono', monospace;">{{ \Carbon\Carbon::parse($req->created_at)->format('Y-m-d H:i') }}</div>
                    </td>
                    <td style="padding: 15px 12px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                        <div style="font-size: 0.8rem; color: var(--erp-white); font-weight: bold;">{{ $req->first_name }} {{ $req->last_name }}</div>
                        <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 2px;">{{ \Illuminate\Support\Str::limit($req->purpose, 50) }}</div>
                        <div style="display: inline-block; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 2px; font-size: 0.6rem; font-family: 'Roboto Mono', monospace; color: #FFBD2E; margin-top: 6px;">{{ $req->item_code }} | {{ strtoupper($req->category) }}</div>
                    </td>
                    <td style="padding: 15px 12px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right; font-family: 'Roboto Mono', monospace;">
                        <span style="font-size: 0.95rem; font-weight: bold; color: var(--erp-white);">{{ $req->currency }} {{ number_format($req->amount_requested, 2) }}</span>
                        <div style="font-size: 0.65rem; color: var(--erp-muted); margin-top: 4px;">{{ explode(' |', $req->disbursement_details)[0] }}</div>
                    </td>
                    <td style="padding: 15px 12px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: center;">
                        @if($activeTab === 'pending_auth')
                            <button wire:click="openAuthModal('{{ $req->id }}')" style="background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 6px 12px; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer;">REVIEW & SEAL</button>
                        @elseif($activeTab === 'pending_clearance')
                            <button wire:click="openClearanceModal('{{ $req->id }}')" style="background: rgba(255,189,46,0.1); border: 1px solid #FFBD2E; color: #FFBD2E; padding: 6px 12px; font-size: 0.7rem; font-family: 'Roboto Mono', monospace; font-weight: bold; border-radius: 2px; cursor: pointer;">AUDIT CLEARANCE</button>
                        @else
                            <span style="font-size: 0.7rem; font-family: 'Roboto Mono', monospace; font-weight: bold; padding: 4px 8px; border-radius: 2px; border: 1px solid {{ $req->status === 'Cleared' ? '#00FF66' : 'var(--erp-red)' }}; color: {{ $req->status === 'Cleared' ? '#00FF66' : 'var(--erp-red)' }};">{{ strtoupper($req->status) }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding: 60px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">
                        > NO RECORDS FOUND IN THIS QUEUE.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showAuthModal && $selectedRequest)
        <div class="glass-modal">
            <div class="modal-box">
                <div wire:loading.flex wire:target="authorizeRequest" style="position: absolute; inset: 0; background: rgba(10,17,40,0.95); z-index: 50; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                    <div class="spinner"></div>
                    <div style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-top: 15px; letter-spacing: 2px; animation: pulse 1.5s infinite;">EXECUTING DUAL-SEAL HASH...</div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; margin-bottom: 20px;">
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-teal);">> EXECUTE AUTHORIZATION SEAL</span>
                    <button wire:click="$set('showAuthModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
                </div>

                <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px; font-size: 0.8rem; color: var(--erp-white); line-height: 1.5;">
                    <strong style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">REF:</strong> {{ $selectedRequest->reference }}<br>
                    <strong style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">REQUESTER:</strong> {{ $selectedRequest->first_name }} {{ $selectedRequest->last_name }}<br>
                    <strong style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">PURPOSE:</strong> {{ $selectedRequest->purpose }}<br>
                    <strong style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">AMOUNT:</strong> <span style="color: #00FF66; font-weight: bold; font-size: 1rem;">{{ $selectedRequest->currency }} {{ number_format($selectedRequest->amount_requested, 2) }}</span><br>
                    <strong style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace;">DESTINATION:</strong> {{ $selectedRequest->disbursement_method }} <br>
                    <span style="color: #FFBD2E; font-size: 0.7rem; font-family: 'Roboto Mono', monospace;">{{ $selectedRequest->disbursement_details }}</span>
                </div>

                <div style="margin-bottom: 20px;" x-data="signaturePad()">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #FFBD2E;">SEAL B: SUPER ADMIN AUTHORIZATION</label>
                        <button @click="expandCanvas = true; setTimeout(() => initCanvas($refs.largeCanvas), 100)" type="button" style="background: transparent; border: 1px solid var(--erp-muted); color: var(--erp-white); font-size: 0.6rem; padding: 3px 8px; cursor: pointer;">[ EXPAND PAD ]</button>
                    </div>
                    
                    <div style="position: relative;">
                        <canvas x-ref="canvas" class="signature-pad" @mousedown="startDrawing" @mousemove="draw" @mouseup="stopDrawing" @mouseleave="stopDrawing" @touchstart.prevent="startDrawing" @touchmove.prevent="draw" @touchend.prevent="stopDrawing"></canvas>
                        <button type="button" @click="clearCanvas" style="position: absolute; top: 10px; right: 10px; background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); font-size: 0.6rem; font-family: 'Roboto Mono', monospace; padding: 2px 6px; border-radius: 2px; cursor: pointer;">CLEAR</button>
                    </div>

                    <div x-show="expandCanvas" style="display: none;" class="glass-modal" style="z-index: 10000;">
                        <div style="background: #0A1128; width: 90%; max-width: 800px; padding: 20px; border: 1px solid #FFBD2E; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                <span style="color: #FFBD2E; font-family: 'Roboto Mono', monospace;">> DRAW ADMIN SIGNATURE</span>
                                <button @click="expandCanvas = false; syncToSmallCanvas()" type="button" style="color: var(--erp-red); background: transparent; border: none; font-size: 1.2rem; cursor: pointer;">&times;</button>
                            </div>
                            <canvas x-ref="largeCanvas" class="signature-pad expanded" @mousedown="startDrawing" @mousemove="draw" @mouseup="stopDrawing" @mouseleave="stopDrawing" @touchstart.prevent="startDrawing" @touchmove.prevent="draw" @touchend.prevent="stopDrawing"></canvas>
                            <div style="display: flex; justify-content: space-between; margin-top: 15px;">
                                <button type="button" @click="clearCanvas" style="background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); padding: 10px 20px; cursor: pointer;">CLEAR</button>
                                <button type="button" @click="expandCanvas = false; syncToSmallCanvas()" style="background: #FFBD2E; color: #000; border: none; padding: 10px 30px; font-weight: bold; cursor: pointer;">SAVE & CLOSE PAD</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button wire:click="authorizeRequest" wire:loading.attr="disabled" style="flex: 2; background: #00FF66; color: #000; border: none; padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.boxShadow='0 0 15px rgba(0,255,102,0.4)'" onmouseout="this.style.boxShadow='none'">APPROVE & GENERATE HASH</button>
                    <button wire:click="rejectRequest" wire:loading.attr="disabled" style="flex: 1; background: rgba(255,51,102,0.1); border: 1px solid var(--erp-red); color: var(--erp-red); padding: 12px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-red)'; this.style.color='#FFF';" onmouseout="this.style.background='rgba(255,51,102,0.1)'; this.style.color='var(--erp-red)';">REJECT</button>
                </div>
            </div>
        </div>
    @endif

    @if($showClearanceModal && $selectedRequest)
        <div class="glass-modal">
            <div class="modal-box" style="border-top-color: #FFBD2E;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; margin-bottom: 20px;">
                    <span style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: #FFBD2E;">> POST-EXECUTION AUDIT CLEARANCE</span>
                    <button wire:click="$set('showClearanceModal', false)" style="background: transparent; border: none; color: var(--erp-muted); cursor: pointer; font-size: 1.2rem;">&times;</button>
                </div>

                <div style="font-size: 0.8rem; color: var(--erp-white); margin-bottom: 20px; line-height: 1.5;">
                    Verifying execution for <strong style="color: var(--erp-teal); font-family: 'Roboto Mono', monospace;">{{ $selectedRequest->reference }}</strong>.<br>
                    Disbursed Amount: <span style="color: #00FF66; font-weight: bold;">{{ $selectedRequest->currency }} {{ number_format($selectedRequest->amount_requested, 2) }}</span>
                </div>

                <div style="background: rgba(255,255,255,0.02); border: 1px dashed var(--erp-border-dark); padding: 20px; border-radius: 4px; margin-bottom: 20px; text-align: center;">
                    @if($selectedRequest->receipt_url)
                        <div style="color: #00FF66; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 10px; font-weight: bold;">[ RECEIPT ATTACHED ]</div>
                        <a href="{{ Storage::url($selectedRequest->receipt_url) }}" target="_blank" style="color: var(--erp-teal); font-size: 0.8rem; text-decoration: underline;">View Uploaded Document</a>
                    @else
                        <label style="display: block; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 10px;">Upload Proof of Payment / Invoice (JPG/PNG)</label>
                        <input type="file" wire:model="receipt_file" style="color: var(--erp-white); font-size: 0.8rem; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.1); padding: 10px; border-radius: 2px; width: 100%; background: rgba(0,0,0,0.3);">
                        <button wire:click="uploadReceipt" wire:loading.attr="disabled" style="background: rgba(0,229,255,0.1); border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-teal)'; this.style.color='#0A1128';" onmouseout="this.style.background='rgba(0,229,255,0.1)'; this.style.color='var(--erp-teal)';">UPLOAD TO VAULT</button>
                        @error('receipt_file') <div style="color: var(--erp-red); font-size: 0.65rem; margin-top: 10px; font-family: 'Roboto Mono', monospace;">{{ $message }}</div> @enderror
                    @endif
                    @if(session()->has('message')) <div style="color: #00FF66; font-size: 0.7rem; margin-top: 10px; font-family: 'Roboto Mono', monospace;">{{ session('message') }}</div> @endif
                </div>

                <div style="background: rgba(255,51,102,0.1); border-left: 3px solid var(--erp-red); padding: 10px; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 20px; line-height: 1.4;">
                    Warning: Clicking "Clear Ledger" permanently closes this transaction. Ensure the uploaded receipt matches the exact disbursed amount.
                </div>

                <button wire:click="clearLedger" wire:loading.attr="disabled" {{ !$selectedRequest->receipt_url ? 'disabled' : '' }} style="width: 100%; background: {{ $selectedRequest->receipt_url ? 'var(--erp-teal)' : 'rgba(255,255,255,0.1)' }}; color: {{ $selectedRequest->receipt_url ? '#0A1128' : 'var(--erp-muted)' }}; border: none; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; border-radius: 2px; cursor: {{ $selectedRequest->receipt_url ? 'pointer' : 'not-allowed' }}; transition: 0.3s;">
                    {{ $selectedRequest->receipt_url ? 'VERIFY RECEIPT & CLEAR LEDGER' : 'AWAITING RECEIPT UPLOAD...' }}
                </button>
            </div>
        </div>
    @endif

    @script
    <script>
        Alpine.data('signaturePad', () => ({
            isDrawing: false,
            ctx: null,
            activeCanvas: null,
            expandCanvas: false,
            
            init() {
                setTimeout(() => { this.initCanvas(this.$refs.canvas); }, 150);
            },
            initCanvas(canvasEl) {
                if(!canvasEl) return;
                this.activeCanvas = canvasEl;
                this.ctx = canvasEl.getContext('2d');
                const rect = canvasEl.getBoundingClientRect();
                canvasEl.width = rect.width;
                canvasEl.height = rect.height;
                this.ctx.lineWidth = 3;
                this.ctx.lineCap = 'round';
                this.ctx.strokeStyle = '#FFBD2E'; // Super Admin Gold
            },
            getCoordinates(e) {
                const rect = this.activeCanvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return { x: clientX - rect.left, y: clientY - rect.top };
            },
            startDrawing(e) {
                this.isDrawing = true;
                const { x, y } = this.getCoordinates(e);
                this.ctx.beginPath();
                this.ctx.moveTo(x, y);
            },
            draw(e) {
                if (!this.isDrawing) return;
                const { x, y } = this.getCoordinates(e);
                this.ctx.lineTo(x, y);
                this.ctx.stroke();
            },
            stopDrawing() {
                if (this.isDrawing) {
                    this.isDrawing = false;
                    this.ctx.closePath();
                    // FORCE SYNC TO LIVEWIRE
                    this.$wire.set('authorizer_signature', this.activeCanvas.toDataURL('image/png'));
                }
            },
            clearCanvas() {
                this.ctx.clearRect(0, 0, this.activeCanvas.width, this.activeCanvas.height);
                this.$wire.set('authorizer_signature', null);
            },
            syncToSmallCanvas() {
                const smallCtx = this.$refs.canvas.getContext('2d');
                smallCtx.drawImage(this.$refs.largeCanvas, 0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                this.initCanvas(this.$refs.canvas);
            }
        }))
    </script>
    @endscript
</div>