<div class="radar-wrapper" style="min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 40px 20px; background-color: #03050A; font-family: 'Inter', sans-serif; color: #ffffff;">
    
    <style>
        :root {
            --erp-main: #0A1128; --erp-teal: #00E5FF;
            --erp-red: #FF3366; --erp-success: #00FF66; --erp-muted: #8A9BB2;
        }

        .radar-container {
            width: 100%; max-width: 650px; background-color: var(--erp-main);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px 50px; position: relative; overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
        }

        .corner-bracket { position: absolute; width: 20px; height: 20px; border: 2px solid var(--erp-muted); opacity: 0.3; }
        .tl { top: 20px; left: 20px; border-right: none; border-bottom: none; }
        .tr { top: 20px; right: 20px; border-left: none; border-bottom: none; }
        .bl { bottom: 20px; left: 20px; border-right: none; border-top: none; }
        .br { bottom: 20px; right: 20px; border-left: none; border-top: none; }

        /* Scanning Animation State */
        .scanner-box { text-align: center; padding: 40px 0; }
        .radar-circle {
            width: 120px; height: 120px; border-radius: 50%;
            border: 1px dashed var(--erp-teal); margin: 0 auto 30px auto;
            position: relative; display: flex; align-items: center; justify-content: center;
        }
        .radar-sweep {
            position: absolute; width: 50%; height: 2px; background: var(--erp-teal);
            top: 50%; left: 50%; transform-origin: left center;
            animation: sweep 1.5s linear infinite;
            box-shadow: 0 0 15px var(--erp-teal);
        }
        @keyframes sweep { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .terminal-text { font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal); margin-bottom: 10px; }
        
        /* Valid State */
        .verified-box { border: 1px solid rgba(0, 255, 102, 0.3); background: rgba(0, 255, 102, 0.02); padding: 40px; text-align: center; position: relative; }
        .verified-box::before { content: 'VERIFIED'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-15deg); font-family: 'Montserrat', sans-serif; font-size: 6rem; font-weight: 900; color: rgba(0, 255, 102, 0.03); pointer-events: none; z-index: 0; }
        
        .shield-icon { width: 80px; height: 80px; border-radius: 50%; background: rgba(0, 255, 102, 0.1); border: 2px solid #00FF66; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; box-shadow: 0 0 30px rgba(0, 255, 102, 0.3); position: relative; z-index: 10; }
        
        .data-row { display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.1); padding: 15px 0; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; text-align: left; position: relative; z-index: 10; }
        .data-label { color: var(--erp-muted); }
        .data-value { color: #fff; font-weight: bold; text-align: right; max-width: 60%; word-break: break-all; }

        /* Invalid State */
        .invalid-box { border: 1px solid rgba(255, 51, 102, 0.3); background: rgba(255, 51, 102, 0.02); padding: 40px; text-align: center; }
        .error-icon { width: 80px; height: 80px; border-radius: 50%; background: rgba(255, 51, 102, 0.1); border: 2px solid var(--erp-red); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; box-shadow: 0 0 30px rgba(255, 51, 102, 0.3); }

        .brand-header { text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 30px; }
        .brand-title { font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1.2rem; letter-spacing: 3px; display: flex; align-items: center; justify-content: center; gap: 10px; }
    </style>

    <div class="radar-container" x-data="verificationScanner()">
        <div class="corner-bracket tl"></div><div class="corner-bracket tr"></div>
        <div class="corner-bracket bl"></div><div class="corner-bracket br"></div>

        <div class="brand-header">
            <div class="brand-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 24px; height: 24px; color: var(--erp-teal); stroke-width: 2;"><polygon points="12 2 2 22 22 22"></polygon></svg>
                JBS PRAXIS
            </div>
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-transform: uppercase; letter-spacing: 2px; margin-top: 5px;">Cryptographic Verification Radar</div>
        </div>

        <!-- STATE 1: SCANNING ANIMATION -->
        @if($verificationState === 'scanning')
            <div class="scanner-box">
                <div class="radar-circle">
                    <i data-lucide="lock" style="width: 32px; height: 32px; color: rgba(0, 229, 255, 0.5);"></i>
                    <div class="radar-sweep"></div>
                </div>
                
                <div class="terminal-text" x-text="scanText">INITIALIZING SECURE PROTOCOL...</div>
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-top: 20px;">
                    QUERYING IMMUTABLE LEDGER FOR HASH:<br>
                    <span style="color: #fff; opacity: 0.5;">{{ substr($hash, 0, 16) }}...{{ substr($hash, -16) }}</span>
                </div>
            </div>
        @endif

        <!-- STATE 2: IMMUTABILITY VERIFIED -->
        @if($verificationState === 'valid')
            <div class="verified-box">
                <div class="shield-icon">
                    <i data-lucide="shield-check" style="width: 40px; height: 40px; color: var(--erp-success);"></i>
                </div>
                <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; font-weight: 900; color: var(--erp-success); margin-bottom: 5px;">IMMUTABILITY VERIFIED</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); margin-bottom: 30px;">CRYPTOGRAPHIC SIGNATURE MATCHES DATABASE RECORD.</p>

                <div class="data-row">
                    <span class="data-label">EXECUTED PROJECT</span>
                    <span class="data-value">{{ strtoupper($document->project_title) }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">ENTERPRISE CLIENT</span>
                    <span class="data-value">{{ strtoupper($document->client_name) }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">AUTHORIZED SIGNATORY</span>
                    <span class="data-value">{{ strtoupper($document->signatory_name) }}<br><span style="font-size: 0.65rem; color: var(--erp-muted);">{{ strtoupper($document->signatory_job_title) }} - {{ strtoupper($document->signatory_company) }}</span></span>
                </div>
                <div class="data-row">
                    <span class="data-label">TIMESTAMP BINDING</span>
                    <span class="data-value">{{ \Carbon\Carbon::parse($document->signed_at)->format('Y-m-d H:i:s T') }}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">SIGNER NODE IP</span>
                    <span class="data-value">{{ $document->client_ip_address }}</span>
                </div>
                <div class="data-row" style="border-bottom: none;">
                    <span class="data-label">SHA-256 PAYLOAD HASH</span>
                    <span class="data-value" style="color: var(--erp-success); font-size: 0.65rem;">{{ $document->client_signature_hash }}</span>
                </div>
            </div>
        @endif

        <!-- STATE 3: INVALID/TAMPERED -->
        @if($verificationState === 'invalid')
            <div class="invalid-box">
                <div class="error-icon">
                    <i data-lucide="alert-triangle" style="width: 40px; height: 40px; color: var(--erp-red);"></i>
                </div>
                <h2 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; font-weight: 900; color: var(--erp-red); margin-bottom: 5px;">CRYPTOGRAPHIC MISMATCH</h2>
                <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted); margin-bottom: 30px; line-height: 1.6;">
                    The hash provided does not exist in the JBS Praxis secure ledger, or the source document has been tampered with post-signature.
                </p>
                <div style="background: #000; border: 1px solid rgba(255,255,255,0.1); padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); word-break: break-all;">
                    FAILED HASH: {{ $hash }}
                </div>
            </div>
        @endif

    </div>

    <!-- Alpine.js Radar Logic -->
    <script>
        function verificationScanner() {
            return {
                scanText: 'INITIALIZING SECURE PROTOCOL...',
                texts: [
                    'QUERYING POSTGRESQL VAULT...',
                    'EXTRACTING DIGITAL PAYLOAD...',
                    'COMPARING SHA-256 HASHES...',
                    'VERIFYING NODE IP ADDRESS...',
                    'DECRYPTING SIGNATURE DATA...'
                ],
                init() {
                    // Only run the animation if we are in the scanning state
                    if ('{{ $verificationState }}' === 'scanning') {
                        let i = 0;
                        const interval = setInterval(() => {
                            if (i < this.texts.length) {
                                this.scanText = this.texts[i];
                                i++;
                            }
                        }, 500); // Change text every 0.5s

                        // After 2.5 seconds, trigger the Livewire backend verification
                        setTimeout(() => {
                            clearInterval(interval);
                            @this.call('executeVerificationScan');
                        }, 2800);
                    }
                }
            }
        }

        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
    </script>
</div>