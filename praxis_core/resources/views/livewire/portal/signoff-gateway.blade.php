@use('App\Livewire\Portal\Concerns\GatewayFormat', 'F')
@php
    $isSigned = in_array($signoff->status ?? '', ['Signed', 'Frozen']);
    $ref = strtoupper(substr($signoff->token ?? '', 0, 12));
@endphp
{{-- The project sign-off a client opens from the email: read the handover, get a code, sign, download the PDF.
     Behaviour lives in App\Livewire\Portal\SignoffGateway. --}}
<div class="gateway signoff-gateway">

    {{-- Caveat sets a typed name as a signature on the canvas --}}
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400..700&display=swap" rel="stylesheet">

    {{-- PDF generation --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        .gateway-main { max-width: 880px; margin: 0 auto; padding: 24px 16px 48px; }
        @media (min-width: 768px) { .gateway-main { padding: 32px 32px 64px; } }
        .gateway .btn { white-space: normal; text-align: center; }
        .doc-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px 24px; margin: 0; }
        .doc-facts dt { font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
        .doc-facts dd { margin: 2px 0 0; color: var(--color-ink); font-weight: 600; font-size: 0.9375rem; overflow-wrap: anywhere; }
        .reading { color: var(--color-slate); font-size: 1rem; line-height: 1.625rem; max-width: 45rem; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .metric-card { border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; background: var(--color-paper); }
        .metric-card .stat-value { margin: 0 0 4px; }
        .metric-title { font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .metric-desc { margin-top: 4px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .action-zone { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--color-line); }
        .action-zone p { margin: 0; max-width: 32rem; }
        .otp-input { max-width: 220px; font-size: 1.5rem; line-height: 2rem; font-variant-numeric: tabular-nums; text-align: center; }
        .sig-box { position: relative; height: 200px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); overflow: hidden; }
        .sig-box::after { content: ""; position: absolute; left: 16px; right: 16px; bottom: 44px; border-bottom: 1px solid var(--color-line); pointer-events: none; }
        .sig-box canvas { display: block; width: 100%; height: 100%; touch-action: none; cursor: crosshair; }
        .record-list { display: grid; grid-template-columns: minmax(140px, auto) 1fr; gap: 8px 16px; margin: 0; font-size: 0.875rem; line-height: 1.375rem; }
        .record-list dt { color: var(--color-muted); }
        .record-list dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
        @media (max-width: 520px) { .record-list { grid-template-columns: 1fr; gap: 2px; } .record-list dd { margin-bottom: 8px; } }

        /* The A4 page captured for the PDF (kept off screen) */
        #pdf-render-zone { position: absolute; top: -9999px; left: -9999px; width: 210mm; min-height: 297mm; background: #FFFFFF; color: var(--color-slate); padding: 48px 56px; box-sizing: border-box; z-index: -10; font-size: 13px; line-height: 20px; }
        #pdf-render-zone h2 { font-family: var(--font-serif); font-weight: 500; font-size: 18px; line-height: 24px; color: var(--color-ink); margin: 0 0 8px; }
        .pdf-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 16px; }
        .pdf-meta { text-align: right; font-size: 12px; line-height: 18px; color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .pdf-check { background: var(--color-mist); border-radius: 4px; padding: 12px 16px; margin: 24px 0; font-size: 11px; line-height: 17px; word-break: break-all; font-variant-numeric: tabular-nums; }
        .pdf-metrics { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        .pdf-metrics > div { border: 1px solid var(--color-line); border-radius: 4px; padding: 10px 12px; }
        .pdf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 16px; }
        .sig-block { border-top: 1px solid var(--color-line-strong); padding-top: 10px; }
        .qr-container { background: #FFFFFF; padding: 4px; display: inline-block; }
    </style>

    <x-portal.gateway-header :kicker="__('gateway.signoff.kicker')" :width="880" />

    <main class="gateway-main">
        <div class="page-header">
            <div class="min-w-0">
                <h1 class="page-title">{{ __('gateway.signoff.heading') }}</h1>
                @unless ($isSigned || $viewState === 'completed')<p class="page-subtitle">{{ __('gateway.signoff.intro') }}</p>@endunless
            </div>
            @if ($isSigned || $viewState === 'completed')
                <span class="pill pill-success">{{ __('gateway.status.signoff_signed') }}</span>
            @else
                <span class="pill pill-warning">{{ __('gateway.status.signoff_awaiting') }}</span>
            @endif
        </div>


        <div class="card" style="margin-bottom: 24px;">
            <dl class="doc-facts">
                <div><dt>{{ __('gateway.common.project') }}</dt><dd>{{ $signoff->project_title }}</dd></div>
                <div><dt>{{ __('gateway.common.organisation') }}</dt><dd>{{ $signoff->client_name }}</dd></div>
                <div><dt>{{ __('gateway.common.reference') }}</dt><dd class="num" style="text-align: left;">{{ $ref }}</dd></div>
            </dl>
        </div>

        {{-- 1. Read the handover --}}
        @if ($viewState === 'review')
            <section class="card">
                <h2 class="section-title">{{ __('gateway.signoff.summary_title') }}</h2>
                <div class="reading">{!! nl2br(e($signoff->executive_summary)) !!}</div>

                <h2 class="section-title" style="margin-top: 32px;">{{ __('gateway.signoff.results_title') }}</h2>
                <div class="metrics-grid">
                    @forelse ($metrics as $metric)
                        <div class="metric-card">
                            <div class="stat-value">{{ $metric['value'] ?? __('gateway.signoff.not_recorded') }}</div>
                            <div class="metric-title">{{ $metric['metric'] ?? __('gateway.signoff.result_fallback') }}</div>
                            @if (filled($metric['description'] ?? null))
                                <div class="metric-desc">{{ $metric['description'] }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="muted" style="margin: 0; grid-column: 1 / -1;">{{ __('gateway.signoff.results_empty') }}</p>
                    @endforelse
                </div>

                <div class="action-zone">
                    <p class="muted">{{ __('gateway.signoff.request_help') }}</p>
                    <button type="button" wire:click="triggerOtp" class="btn btn-primary" wire:loading.attr="disabled" wire:target="triggerOtp">
                        <i data-lucide="mail"></i>
                        <span wire:loading.remove wire:target="triggerOtp">{{ __('gateway.signoff.request_code') }}</span>
                        <span wire:loading wire:target="triggerOtp">{{ __('gateway.signoff.sending_code') }}</span>
                    </button>
                </div>
            </section>

        {{-- 2. Enter the emailed code --}}
        @elseif ($viewState === 'otp_verification')
            <section class="card" style="max-width: 520px;">
                <h2 class="section-title">{{ __('gateway.signoff.code_title') }}</h2>
                <p class="muted" style="margin: 0 0 16px;">{{ __('gateway.signoff.code_intro') }}</p>
                <div class="field">
                    <label for="signoff-otp" class="label">{{ __('gateway.common.code_label') }}</label>
                    <input type="text" id="signoff-otp" wire:model="otpInput" class="input otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" aria-describedby="signoff-otp-help">
                    <span id="signoff-otp-help" class="help">{{ __('gateway.common.code_help') }}</span>
                    @error('otpInput') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                </div>
                <div style="margin-top: 20px;">
                    <button type="button" wire:click="verifyOtp" class="btn btn-primary" wire:loading.attr="disabled" wire:target="verifyOtp">
                        <span wire:loading.remove wire:target="verifyOtp">{{ __('gateway.signoff.verify_code') }}</span>
                        <span wire:loading wire:target="verifyOtp">{{ __('gateway.signoff.verifying') }}</span>
                    </button>
                </div>
            </section>

        {{-- 3. Sign --}}
        @elseif ($viewState === 'signature_canvas')
            <section class="card" x-data="signaturePad()">
                <div class="grid gap-4 md:grid-cols-3" style="margin-bottom: 20px;">
                    <div class="field">
                        <label for="signer-name" class="label">{{ __('gateway.signoff.signer_name') }}</label>
                        <input type="text" id="signer-name" x-model="typedName" class="input" autocomplete="name">
                        @error('signerName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="signer-title" class="label">{{ __('gateway.signoff.signer_title') }}</label>
                        <input type="text" id="signer-title" wire:model.defer="signerTitle" class="input" autocomplete="organization-title">
                        @error('signerTitle') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="signer-company" class="label">{{ __('gateway.signoff.signer_company') }}</label>
                        <input type="text" id="signer-company" wire:model.defer="signerCompany" class="input" autocomplete="organization">
                        @error('signerCompany') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="field">
                    <span class="label" id="signature-label">{{ __('gateway.signoff.signature') }}</span>
                    <div class="tabs" role="tablist" style="margin-bottom: 12px;">
                        <button type="button" role="tab" class="tab" :class="{ 'is-active': mode === 'draw' }" :aria-selected="mode === 'draw'" @click="setMode('draw')">{{ __('gateway.signoff.draw') }}</button>
                        <button type="button" role="tab" class="tab" :class="{ 'is-active': mode === 'type' }" :aria-selected="mode === 'type'" @click="setMode('type')">{{ __('gateway.signoff.type') }}</button>
                    </div>
                    <div class="sig-box">
                        <canvas x-ref="canvas" role="img" aria-labelledby="signature-label" @mousedown="startDraw" @mousemove="draw" @mouseup="stopDraw" @mouseleave="stopDraw" @touchstart="startDraw" @touchmove="draw" @touchend="stopDraw"></canvas>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <span class="help" x-text="mode === 'draw' ? @js(__('gateway.signoff.draw_help')) : @js(__('gateway.signoff.type_help'))">{{ __('gateway.signoff.draw_help') }}</span>
                        <button type="button" @click="clear()" class="btn btn-ghost btn-sm">{{ __('gateway.common.clear') }}</button>
                    </div>
                    <span class="field-error" role="alert" x-show="missing" x-cloak>{{ __('gateway.signoff.signature_missing') }}</span>
                    @error('signatureBase64') <span class="field-error">{{ __('gateway.signoff.signature_missing') }}</span> @enderror
                </div>

                <div class="action-zone">
                    <span></span>
                    <button type="button" @click="saveSignature()" class="btn btn-primary" wire:loading.attr="disabled" wire:target="bindContract">
                        <span wire:loading.remove wire:target="bindContract">{{ __('gateway.signoff.sign') }}</span>
                        <span wire:loading wire:target="bindContract">{{ __('gateway.signoff.signing') }}</span>
                    </button>
                </div>
            </section>

        {{-- 4. Signed --}}
        @elseif ($viewState === 'completed')
            <section class="card">
                <h2 class="section-title">{{ __('gateway.signoff.done_title') }}</h2>
                <p class="muted" style="margin: 0 0 16px;">{{ __('gateway.signoff.done_body') }}</p>

                <dl class="record-list">
                    <dt>{{ __('gateway.common.signed_by') }}</dt>
                    <dd>{{ $signoff->signatory_name }}@if (filled($signoff->signatory_job_title)), {{ $signoff->signatory_job_title }}@endif</dd>
                    <dt>{{ __('gateway.common.signed_on') }}</dt>
                    <dd>{{ F::dateTime($signoff->signed_at) }}</dd>
                    <dt>{{ __('gateway.common.ip_address') }}</dt>
                    <dd>{{ $signoff->client_ip_address }}</dd>
                    <dt>{{ __('gateway.common.fingerprint') }}</dt>
                    <dd style="font-size: 0.8125rem;">{{ $signoff->client_signature_hash }}</dd>
                </dl>

                <div class="action-zone">
                    <span></span>
                    <button type="button" id="downloadPdfBtn" class="btn btn-secondary"
                            data-verify-url="{{ filled($signoff->client_signature_hash) ? route('portal.verify.contract', ['hash' => $signoff->client_signature_hash]) : '' }}"
                            onclick="downloadSignoffPdf(this)">
                        <i data-lucide="download"></i> <span class="pdf-label">{{ __('gateway.common.download_pdf') }}</span>
                    </button>
                </div>
            </section>

            {{-- The A4 page captured for the PDF, kept off screen --}}
            <div id="pdf-render-zone" aria-hidden="true">
                <div class="pdf-head">
                    <div>
                        <img src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" style="height: 32px; width: auto;">
                        <div style="margin-top: 8px; font-size: 13px; font-weight: 600; color: var(--color-deep-teal);">{{ __('gateway.signoff.pdf_title') }}</div>
                    </div>
                    <div class="pdf-meta">
                        {{ __('gateway.common.reference') }}{{ __('gateway.common.colon') }} {{ strtoupper(substr($signoff->token, 0, 16)) }}<br>
                        {{ __('gateway.common.date') }}{{ __('gateway.common.colon') }} {{ F::date($signoff->signed_at) }}
                    </div>
                </div>
                <hr class="praxis-rule" style="margin: 0;">

                <div style="margin-top: 24px;">
                    <div style="font-family: var(--font-serif); font-size: 26px; line-height: 32px; color: var(--color-ink);">{{ $signoff->project_title }}</div>
                    <div style="color: var(--color-muted);">{{ $signoff->client_name }}</div>
                </div>

                <div class="pdf-check">
                    <strong style="color: var(--color-ink);">{{ __('gateway.signoff.pdf_check') }}</strong><br>
                    {{ __('gateway.common.fingerprint') }}{{ __('gateway.common.colon') }} {{ $signoff->client_signature_hash }}<br>
                    {{ __('gateway.common.ip_address') }}{{ __('gateway.common.colon') }} {{ $signoff->client_ip_address }}
                </div>

                <h2>{{ __('gateway.signoff.summary_title') }}</h2>
                <div style="margin-bottom: 24px;">{!! nl2br(e($signoff->executive_summary)) !!}</div>

                @if (!empty($metrics))
                    <h2>{{ __('gateway.signoff.results_title') }}</h2>
                    <div class="pdf-metrics">
                        @foreach ($metrics as $m)
                            <div>
                                <div style="font-size: 18px; line-height: 24px; font-weight: 600; color: var(--color-ink); font-variant-numeric: tabular-nums;">{{ $m['value'] ?? '' }}</div>
                                <div style="font-size: 12px; color: var(--color-slate);">{{ $m['metric'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <h2>{{ __('gateway.proposal.doc.signatures') }}</h2>
                <p style="margin: 0 0 8px; color: var(--color-slate);">{{ __('gateway.signoff.pdf_statement', ['company' => $signoff->client_name]) }}</p>

                <div class="pdf-grid">
                    <div class="sig-block">
                        <div style="font-size: 12px; color: var(--color-muted); margin-bottom: 8px;">{{ __('gateway.signoff.pdf_for_praxis') }}</div>
                        <div style="height: 72px; display: flex; align-items: flex-end; font-size: 13px; color: var(--color-slate);">{{ __('gateway.signoff.pdf_issued') }}</div>
                        <div style="font-weight: 600; color: var(--color-ink);">JBS Praxis</div>
                    </div>
                    <div class="sig-block">
                        <div style="font-size: 12px; color: var(--color-muted); margin-bottom: 8px;">{{ __('gateway.signoff.pdf_for_client', ['company' => $signoff->signatory_company ?: $signoff->client_name]) }}</div>
                        <div style="height: 72px;">
                            @if ($signoff->client_signature_b64)
                                <img src="{{ $signoff->client_signature_b64 }}" alt="" style="max-height: 72px; max-width: 100%;">
                            @endif
                        </div>
                        <div style="font-weight: 600; color: var(--color-ink);">{{ $signoff->signatory_name }}</div>
                        <div style="font-size: 12px; color: var(--color-muted);">{{ $signoff->signatory_job_title }}, {{ $signoff->signatory_company }}</div>
                    </div>
                </div>

                <div style="margin-top: 40px; padding-top: 16px; border-top: 1px solid var(--color-line); display: flex; justify-content: space-between; align-items: flex-end; gap: 16px;">
                    <div style="font-size: 11px; line-height: 16px; color: var(--color-muted);">{{ __('gateway.signoff.pdf_footer') }}</div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="font-size: 11px; line-height: 16px; color: var(--color-slate); text-align: right; max-width: 140px;">{{ __('gateway.signoff.pdf_scan') }}</div>
                        <div id="qrcode-container" class="qr-container"></div>
                    </div>
                </div>
            </div>
        @endif
    </main>

    <script>
        // Signature canvas (Alpine): draw with the mouse or a finger, or type the name in a handwriting face
        function signaturePad() {
            return {
                mode: 'draw', typedName: @entangle('signerName'), isDrawing: false, hasContent: false, missing: false, ctx: null,
                init() {
                    const canvas = this.$refs.canvas;
                    this.ctx = canvas.getContext('2d');
                    this.resizeCanvas();
                    window.addEventListener('resize', () => this.resizeCanvas());
                    this.$watch('typedName', value => { if (this.mode === 'type') this.renderTypedSignature(); });
                },
                resizeCanvas() {
                    const canvas = this.$refs.canvas;
                    canvas.width = canvas.parentElement.clientWidth;
                    canvas.height = canvas.parentElement.clientHeight;
                    if (this.mode === 'type') this.renderTypedSignature();
                },
                startDraw(e) {
                    if (this.mode !== 'draw') return;
                    this.isDrawing = true; this.hasContent = true; this.missing = false;
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
                    this.ctx.lineWidth = 2.5; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#0C142B';
                    this.ctx.lineTo(x, y); this.ctx.stroke();
                },
                stopDraw() { this.isDrawing = false; },
                clear() {
                    this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                    this.hasContent = false;
                    if (this.mode === 'type') this.typedName = '';
                },
                setMode(newMode) {
                    this.mode = newMode; this.clear();
                    if (newMode === 'type' && this.typedName) this.renderTypedSignature();
                },
                renderTypedSignature() {
                    this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                    if (!this.typedName) { this.hasContent = false; return; }
                    this.hasContent = true; this.missing = false;
                    this.ctx.font = '56px "Caveat", cursive'; this.ctx.fillStyle = '#0C142B';
                    this.ctx.textAlign = 'center'; this.ctx.textBaseline = 'middle';
                    this.ctx.fillText(this.typedName, this.$refs.canvas.width / 2, this.$refs.canvas.height / 2);
                },
                saveSignature() {
                    if (!this.hasContent) { this.missing = true; return; }
                    @this.set('signatureBase64', this.$refs.canvas.toDataURL('image/png'));
                    @this.call('bindContract');
                }
            }
        }

        // PDF of the signed sign-off: the off-screen A4 page, with a QR code that opens the document check
        const signoffText = {
            preparing: @js(__('gateway.common.preparing_pdf')),
            failed: @js(__('gateway.common.pdf_failed')),
            filename: @js(__('gateway.signoff.pdf_filename', ['ref' => strtolower(substr($signoff->token ?? 'a', 0, 6))])),
        };
        async function downloadSignoffPdf(btn) {
            const target = document.getElementById('pdf-render-zone');
            if (!target || !btn) return;
            const label = btn.querySelector('.pdf-label');
            const original = label ? label.textContent : '';
            if (label) label.textContent = signoffText.preparing;
            btn.disabled = true;

            const qr = document.getElementById('qrcode-container');
            if (qr && !qr.hasChildNodes() && btn.dataset.verifyUrl && typeof QRCode !== 'undefined') {
                new QRCode(qr, { text: btn.dataset.verifyUrl, width: 80, height: 80, colorDark: '#0C142B', colorLight: '#FFFFFF' });
            }
            await new Promise(r => setTimeout(r, 150));

            try {
                target.style.top = '0';
                const canvas = await html2canvas(target, { scale: 2, useCORS: true, backgroundColor: '#FFFFFF' });
                target.style.top = '-9999px';

                const imgData = canvas.toDataURL('image/jpeg', 1.0);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);
                pdf.save(signoffText.filename + '.pdf');
            } catch (e) {
                target.style.top = '-9999px';
                alert(signoffText.failed);
            } finally {
                if (label) label.textContent = original;
                btn.disabled = false;
            }
        }
    </script>
</div>
