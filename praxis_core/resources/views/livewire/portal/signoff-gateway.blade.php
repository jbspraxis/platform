<div class="gateway-wrapper" style="min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; padding: 40px 20px; background-color: #03050A; font-family: 'Inter', sans-serif; color: #ffffff; overflow-x: hidden;">
    
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400..700&display=swap" rel="stylesheet">
    
    <!-- PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root {
            --erp-base: #03050A; --erp-main: #0A1128; --erp-teal: #00E5FF;
            --erp-red: #FF3366; --erp-amber: #FFBD2E; --erp-success: #00FF66; --erp-muted: #8A9BB2;
        }

        .secure-document {
            width: 100%; max-width: 900px; background-color: var(--erp-main);
            background-image: radial-gradient(circle at 50% 0%, rgba(0, 229, 255, 0.03) 0%, transparent 70%);
            border: 1px solid rgba(0, 229, 255, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 229, 255, 0.05);
            padding: 50px 60px; position: relative; overflow: hidden; z-index: 1;
        }

        .doc-watermark {
            position: absolute; top: 100px; right: -40px; font-family: 'Roboto Mono', monospace; font-size: 6rem;
            color: rgba(255,255,255,0.015); font-weight: 900; pointer-events: none; z-index: 0;
            transform: rotate(-90deg); transform-origin: top right; letter-spacing: -2px; white-space: nowrap;
        }

        .corner-bracket { position: absolute; width: 20px; height: 20px; border: 2px solid var(--erp-teal); opacity: 0.5; z-index: 10; }
        .tl { top: 30px; left: 30px; border-right: none; border-bottom: none; }
        .tr { top: 30px; right: 30px; border-left: none; border-bottom: none; }
        .bl { bottom: 30px; left: 30px; border-right: none; border-top: none; }
        .br { bottom: 30px; right: 30px; border-left: none; border-top: none; }

        .doc-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 10; }
        .brand-title { font-family: 'Inter', sans-serif; font-weight: 900; font-size: 1.2rem; letter-spacing: 2px; display: flex; align-items: center; gap: 10px; }
        .brand-subtitle { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); text-transform: uppercase; letter-spacing: 1px; margin-top: 5px; }
        
        .meta-text { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 5px; text-align: right; }
        .meta-val { color: #fff; font-weight: bold; }

        .section-title { font-family: 'Inter', sans-serif; font-weight: 900; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 2px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-bottom: 20px; color: #fff; display: flex; align-items: center; gap: 10px; position: relative; z-index: 10; }
        
        .exec-summary { background: rgba(0,0,0,0.4); border-left: 2px solid var(--erp-teal); padding: 20px; font-size: 0.9rem; line-height: 1.7; color: #E2E8F0; margin-bottom: 40px; position: relative; z-index: 10; }

        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; position: relative; z-index: 10; }
        .metric-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; transition: 0.3s; }
        .metric-val { font-family: 'Roboto Mono', monospace; font-size: 2rem; font-weight: 900; color: var(--erp-teal); margin-bottom: 5px; }
        .metric-title { font-family: 'Inter', sans-serif; font-size: 0.75rem; font-weight: bold; color: #fff; text-transform: uppercase; margin-bottom: 5px; }
        .metric-desc { font-size: 0.7rem; color: var(--erp-muted); line-height: 1.4; }

        .action-zone { border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 30px; text-align: center; position: relative; z-index: 10; }
        
        .btn-primary { background: var(--erp-teal); color: var(--erp-main); border: 1px solid var(--erp-teal); padding: 15px 40px; font-family: 'Roboto Mono', monospace; font-size: 0.9rem; font-weight: bold; cursor: pointer; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px; display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 100%; }
        .btn-primary:hover:not(:disabled) { background: #fff; border-color: #fff; box-shadow: 0 0 20px rgba(0, 229, 255, 0.4); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

        .cyber-input { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 12px; font-family: 'Inter', sans-serif; font-size: 0.85rem; border-radius: 2px; outline: none; transition: 0.3s; margin-bottom: 15px; }
        .cyber-input:focus { border-color: var(--erp-teal); background: rgba(0, 229, 255, 0.02); }
        .form-label { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-transform: uppercase; margin-bottom: 5px; display: block; text-align: left; }

        /* A4 PDF Render Zone (Hidden until Print/Capture) */
        #pdf-render-zone {
            position: absolute; top: -9999px; left: -9999px; width: 210mm; min-height: 297mm;
            background-color: #0A1128; color: #fff; padding: 40px 50px;
            box-sizing: border-box; z-index: -10;
        }

        .pdf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px; }
        .sig-block { border-top: 1px solid rgba(255,255,255,0.2); padding-top: 10px; margin-top: 20px; }
        
        .qr-container { background: #fff; padding: 10px; display: inline-block; border-radius: 4px; }
    </style>

    <div class="secure-document">
        <div class="doc-watermark">CLOSURE.PROTOCOL</div>
        <div class="corner-bracket tl"></div><div class="corner-bracket tr"></div>
        <div class="corner-bracket bl"></div><div class="corner-bracket br"></div>

        <!-- HEADER -->
        <div class="doc-header">
            <div>
                <div class="brand-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 24px; height: 24px; color: var(--erp-teal); stroke-width: 2;"><polygon points="12 2 2 22 22 22"></polygon></svg>
                    JBS PRAXIS
                </div>
                <div class="brand-subtitle">Operations & Deployment Matrix</div>
            </div>
            <div>
                <div class="meta-text">DOCUMENT REF: <span class="meta-val">{{ strtoupper(substr($signoff->token, 0, 12)) }}</span></div>
                <div class="meta-text">CLIENT NODE: <span class="meta-val">{{ strtoupper($signoff->client_name) }}</span></div>
                <div class="meta-text">PROJECT: <span class="meta-val" style="color: var(--erp-teal);">{{ strtoupper($signoff->project_title) }}</span></div>
            </div>
        </div>

        <!-- STATE 1: INITIAL REVIEW -->
        @if($viewState === 'review')
            <div class="section-title"><i data-lucide="file-check" style="width: 18px; height: 18px; color: var(--erp-teal);"></i> I. Executive Handover Summary</div>
            <div class="exec-summary">{!! nl2br(e($signoff->executive_summary)) !!}</div>

            <div class="section-title"><i data-lucide="bar-chart-2" style="width: 18px; height: 18px; color: var(--erp-teal);"></i> II. Executed ROI Metrics</div>
            <div class="metrics-grid">
                @forelse($metrics as $metric)
                    <div class="metric-card">
                        <div class="metric-val">{{ $metric['value'] ?? 'N/A' }}</div>
                        <div class="metric-title">{{ $metric['metric'] ?? 'Metric' }}</div>
                        <div class="metric-desc">{{ $metric['description'] ?? '' }}</div>
                    </div>
                @empty
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); grid-column: 1/-1;">No metrics synthesized.</div>
                @endforelse
            </div>

            <div class="action-zone">
                <button wire:click="triggerOtp" class="btn-primary" style="width: auto;">
                    <i data-lucide="shield" style="width: 18px; height: 18px;"></i> INITIATE DIGITAL SIGNATURE
                </button>
            </div>

        <!-- STATE 2: OTP VERIFICATION -->
        @elseif($viewState === 'otp_verification')
            <div style="max-width: 400px; margin: 0 auto; background: rgba(0,0,0,0.6); border: 1px solid var(--erp-amber); padding: 40px; text-align: center;">
                <i data-lucide="lock" style="width: 48px; height: 48px; color: var(--erp-amber); margin: 0 auto 20px auto;"></i>
                <h3 style="font-family: 'Inter', sans-serif; font-size: 1.2rem; font-weight: 900; text-transform: uppercase; margin-bottom: 10px;">Identity Verification</h3>
                <input type="text" wire:model="otpInput" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); color: var(--erp-amber); padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 2rem; text-align: center; font-weight: bold; letter-spacing: 10px; outline: none; margin-bottom: 20px;" placeholder="000000" maxlength="6">
                <button wire:click="verifyOtp" class="btn-primary" style="background: var(--erp-amber); color: var(--erp-main); border-color: var(--erp-amber);">VERIFY IDENTITY</button>
            </div>

        <!-- STATE 3: CRYPTOGRAPHIC SIGNATURE CANVAS -->
        @elseif($viewState === 'signature_canvas')
            <div x-data="signaturePad()" class="relative z-10">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <div><label class="form-label">Signatory Full Name</label><input type="text" wire:model.defer="signerName" x-model="typedName" class="cyber-input" placeholder="e.g. John Doe"></div>
                    <div><label class="form-label">Job Title / Designation</label><input type="text" wire:model.defer="signerTitle" class="cyber-input" placeholder="e.g. Chief Executive Officer"></div>
                    <div><label class="form-label">Corporate Entity</label><input type="text" wire:model.defer="signerCompany" class="cyber-input" placeholder="e.g. {{ $signoff->client_name }}"></div>
                </div>

                <div style="background: rgba(10, 17, 40, 0.8); border: 1px solid var(--erp-border-dark); padding: 20px; margin-bottom: 30px;">
                    <div style="display: flex; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <div style="flex: 1; padding: 10px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; cursor: pointer;" :style="mode === 'draw' ? 'color: #00E5FF; border-bottom: 2px solid #00E5FF;' : 'color: #8A9BB2;'" @click="setMode('draw')">DRAW SIGNATURE</div>
                        <div style="flex: 1; padding: 10px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; cursor: pointer;" :style="mode === 'type' ? 'color: #00E5FF; border-bottom: 2px solid #00E5FF;' : 'color: #8A9BB2;'" @click="setMode('type')">TYPE SIGNATURE</div>
                    </div>
                    <div style="border: 1px dashed var(--erp-teal); background: rgba(0,0,0,0.5); position: relative; height: 200px; overflow: hidden;">
                        <canvas x-ref="canvas" style="width: 100%; height: 100%; touch-action: none;" @mousedown="startDraw" @mousemove="draw" @mouseup="stopDraw" @mouseleave="stopDraw" @touchstart="startDraw" @touchmove="draw" @touchend="stopDraw"></canvas>
                    </div>
                    <div style="text-align: right; margin-top: 10px;"><button type="button" @click="clear()" style="background: transparent; border: none; color: var(--erp-muted); font-size: 0.7rem; cursor: pointer; text-decoration: underline;">Clear & Reset</button></div>
                </div>

                <button @click="saveSignature()" class="btn-primary" wire:loading.attr="disabled" wire:target="bindContract">
                    <span wire:loading.remove wire:target="bindContract">AUTHORIZE & BIND CONTRACT</span>
                    <span wire:loading wire:target="bindContract">ENCRYPTING...</span>
                </button>
            </div>

        <!-- STATE 4: COMPLETED (FROZEN/SIGNED) -->
        @elseif($viewState === 'completed')
            <div style="text-align: center; padding: 40px 20px; border: 1px solid rgba(0, 255, 102, 0.2); background: rgba(0, 255, 102, 0.02); margin-top: 20px;">
                <h2 style="font-family: 'Inter', sans-serif; font-weight: 900; font-size: 1.8rem; color: #00FF66; margin-bottom: 30px;">CONTRACT HASH SECURED</h2>
                
                <div style="max-width: 600px; margin: 0 auto; background: #000; border: 1px solid var(--erp-border-dark); padding: 20px; text-align: left; margin-bottom: 30px;">
                    <div class="meta-text">> HASH ALGORITHM: <span class="meta-val" style="color: #00FF66;">SHA-256</span></div>
                    <div class="meta-text">> PAYLOAD HASH: <span class="meta-val" style="word-break: break-all;">{{ $signoff->client_signature_hash }}</span></div>
                    <div class="meta-text">> TIMESTAMP: <span class="meta-val">{{ \Carbon\Carbon::parse($signoff->signed_at)->format('Y-m-d H:i:s T') }}</span></div>
                    <div class="meta-text">> SIGNER NODE IP: <span class="meta-val">{{ $signoff->client_ip_address }}</span></div>
                </div>
                
                <!-- PDF Download Trigger -->
                <button id="downloadPdfBtn" class="btn-primary" style="background: transparent; color: var(--erp-teal); border-color: var(--erp-teal); width: auto;">
                    <i data-lucide="download" style="width: 18px; height: 18px;"></i> DOWNLOAD VERIFIABLE PDF
                </button>
            </div>

            <!-- INVISIBLE A4 RENDER ZONE FOR PDF CAPTURE -->
            <div id="pdf-render-zone">
                <div style="display: flex; justify-content: space-between; border-bottom: 2px solid rgba(255,255,255,0.2); padding-bottom: 20px; margin-bottom: 30px;">
                    <div>
                        <div style="font-family: 'Inter', sans-serif; font-weight: 900; font-size: 1.5rem; letter-spacing: 2px; color: #fff;">JBS PRAXIS</div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #00E5FF;">CRYPTOGRAPHIC PROJECT SIGNOFF</div>
                    </div>
                    <div style="text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; line-height: 1.5;">
                        REF: {{ strtoupper(substr($signoff->token, 0, 16)) }}<br>
                        DATE: {{ \Carbon\Carbon::parse($signoff->signed_at)->format('F j, Y') }}
                    </div>
                </div>

                <div style="background: rgba(255,255,255,0.05); padding: 15px; border-left: 4px solid #00FF66; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-bottom: 30px; color: #fff;">
                    <strong>IMMUTABILITY VERIFIED</strong><br>
                    ALGORITHM: SHA-256<br>
                    HASH: {{ $signoff->client_signature_hash }}<br>
                    CLIENT IP: {{ $signoff->client_ip_address }}
                </div>

                <div style="font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: bold; margin-bottom: 10px; color: #00E5FF;">I. EXECUTIVE SUMMARY</div>
                <div style="font-family: 'Inter', sans-serif; font-size: 0.85rem; line-height: 1.6; color: #E2E8F0; margin-bottom: 30px;">
                    {!! nl2br(e($signoff->executive_summary)) !!}
                </div>

                <div style="font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: bold; margin-bottom: 10px; color: #00E5FF;">II. FINAL ROI METRICS</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 40px;">
                    @foreach($metrics as $m)
                        <div style="border: 1px solid rgba(255,255,255,0.1); padding: 15px;">
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 1.5rem; font-weight: bold; color: #00E5FF; margin-bottom: 5px;">{{ $m['value'] }}</div>
                            <div style="font-family: 'Inter', sans-serif; font-size: 0.7rem; font-weight: bold; color: #fff;">{{ strtoupper($m['metric']) }}</div>
                        </div>
                    @endforeach
                </div>

                <div style="font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: bold; margin-bottom: 10px; color: #00E5FF;">III. AUTHORIZATION & BINDING</div>
                <p style="font-family: 'Inter', sans-serif; font-size: 0.7rem; color: #8A9BB2; margin-bottom: 20px;">
                    By affixing the signatures below, both the executing agent (JBS Praxis) and the Client authorize the completion of the Battlespace as outlined in the initial Scope of Work. No further operational modifications will be executed under this specific directive.
                </p>

                <div class="pdf-grid">
                    <!-- Praxis Signature -->
                    <div class="sig-block">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; margin-bottom: 10px;">EXECUTING ARCHITECT</div>
                        <div style="font-family: 'Caveat', cursive; font-size: 2.5rem; color: #fff; line-height: 1; height: 80px;">JBS Praxis Command</div>
                        <div style="font-family: 'Inter', sans-serif; font-size: 0.8rem; font-weight: bold; color: #fff;">JBS Praxis Ltd.</div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">System Generated Counter-Signature</div>
                    </div>

                    <!-- Client Signature -->
                    <div class="sig-block">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; margin-bottom: 10px;">AUTHORIZED CLIENT</div>
                        <div style="height: 80px;">
                            @if($signoff->client_signature_b64)
                                <img src="{{ $signoff->client_signature_b64 }}" style="max-height: 80px; max-width: 100%; filter: invert(1);">
                            @endif
                        </div>
                        <div style="font-family: 'Inter', sans-serif; font-size: 0.8rem; font-weight: bold; color: #fff;">{{ $signoff->signatory_name }}</div>
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">{{ $signoff->signatory_job_title }}, {{ $signoff->signatory_company }}</div>
                    </div>
                </div>

                <!-- Footer QR Code -->
                <div style="margin-top: 50px; border-top: 1px dashed rgba(255,255,255,0.2); padding-top: 20px; display: flex; justify-content: space-between; align-items: flex-end;">
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2;">
                        CONFIDENTIAL DOCUMENT<br>Generated by the Praxis Unified Deployment Matrix
                    </div>
                    <div style="text-align: right; display: flex; align-items: center; gap: 15px;">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.6rem; color: #00E5FF; text-align: right;">
                            SCAN TO VERIFY<br>IMMUTABILITY ON<br>THE PUBLIC LEDGER
                        </div>
                        <div id="qrcode-container" class="qr-container"></div>
                    </div>
                </div>
            </div>
            
        @endif

    </div>

    <script>
        // Alpine.js Signature Canvas Logic
        function signaturePad() {
            return {
                mode: 'draw', typedName: @entangle('signerName').defer, isDrawing: false, hasContent: false, ctx: null,
                init() {
                    const canvas = this.$refs.canvas;
                    this.ctx = canvas.getContext('2d');
                    this.resizeCanvas();
                    window.addEventListener('resize', () => this.resizeCanvas());
                    this.$watch('typedName', value => { if (this.mode === 'type') this.renderTypedSignature(); });
                },
                resizeCanvas() {
                    const canvas = this.$refs.canvas;
                    canvas.width = canvas.parentElement.offsetWidth;
                    canvas.height = canvas.parentElement.offsetHeight;
                    if(this.mode === 'type') this.renderTypedSignature();
                },
                startDraw(e) {
                    if (this.mode !== 'draw') return;
                    this.isDrawing = true; this.hasContent = true;
                    const rect = this.$refs.canvas.getBoundingClientRect();
                    const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
                    const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
                    this.ctx.beginPath();
                    this.ctx.moveTo(x, y);
                },
                draw(e) {
                    if (!this.isDrawing || this.mode !== 'draw') return;
                    const rect = this.$refs.canvas.getBoundingClientRect();
                    const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
                    const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
                    this.ctx.lineWidth = 3; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#000000'; // Draw in black so it shows up on PDF
                    this.ctx.lineTo(x, y); this.ctx.stroke();
                },
                stopDraw() { this.isDrawing = false; },
                clear() {
                    this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                    this.hasContent = false;
                    if(this.mode === 'type') this.typedName = '';
                },
                setMode(newMode) {
                    this.mode = newMode; this.clear();
                    if (newMode === 'type' && this.typedName) this.renderTypedSignature();
                },
                renderTypedSignature() {
                    this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                    if (!this.typedName) { this.hasContent = false; return; }
                    this.hasContent = true;
                    this.ctx.font = '56px "Caveat", cursive'; this.ctx.fillStyle = '#000000';
                    this.ctx.textAlign = 'center'; this.ctx.textBaseline = 'middle';
                    this.ctx.fillText(this.typedName, this.$refs.canvas.width / 2, this.$refs.canvas.height / 2);
                },
                saveSignature() {
                    if (!this.hasContent) { alert('Signature capture required.'); return; }
                    @this.set('signatureBase64', this.$refs.canvas.toDataURL('image/png'));
                    @this.call('bindContract');
                }
            }
        }

        // Generate PDF Logic
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('downloadPdfBtn');
            if (btn) {
                // Generate QR Code inside the hidden PDF zone
                const qrUrl = "https://jbspraxis.com/verify/contract/{{ $signoff->client_signature_hash ?? '' }}";
                new QRCode(document.getElementById("qrcode-container"), {
                    text: qrUrl, width: 80, height: 80, colorDark: "#000000", colorLight: "#ffffff"
                });

                // Attach Download Handler
                btn.addEventListener('click', async function () {
                    const originalHTML = this.innerHTML;
                    this.innerHTML = "GENERATING PDF...";
                    this.style.pointerEvents = "none";
                    await new Promise(r => setTimeout(r, 100));

                    try {
                        const target = document.getElementById('pdf-render-zone');
                        target.style.top = '0'; // Bring into view briefly
                        const canvas = await html2canvas(target, { scale: 2, useCORS: true });
                        target.style.top = '-9999px'; // Hide again

                        const imgData = canvas.toDataURL('image/jpeg', 1.0);
                        const { jsPDF } = window.jspdf;
                        const pdf = new jsPDF('p', 'mm', 'a4');
                        pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);
                        pdf.save('JBS_Praxis_Project_Signoff_{{ strtoupper(substr($signoff->token ?? "A", 0, 6)) }}.pdf');
                    } catch (e) {
                        alert("PDF Generation Failed.");
                    } finally {
                        this.innerHTML = originalHTML;
                        this.style.pointerEvents = "auto";
                    }
                });
            }
        });

        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
    </script>
</div>