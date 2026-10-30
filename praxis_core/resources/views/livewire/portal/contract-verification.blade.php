@use('App\Livewire\Portal\Concerns\GatewayFormat', 'F')
{{-- The public check behind the QR code on a signed sign-off: does this SHA-256 fingerprint match a signed record?
     Behaviour lives in App\Livewire\Portal\ContractVerification; the check runs as soon as the page has loaded. --}}
<div class="gateway verify-gateway" x-data="verificationScanner()">
    <style>
        .gateway-main { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
        @media (min-width: 768px) { .gateway-main { padding: 32px 32px 64px; } }
        .record-list { display: grid; grid-template-columns: minmax(140px, auto) 1fr; gap: 8px 16px; margin: 16px 0 0; font-size: 0.875rem; line-height: 1.375rem; }
        .record-list dt { color: var(--color-muted); }
        .record-list dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
        .record-list dd small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        @media (max-width: 520px) { .record-list { grid-template-columns: 1fr; gap: 2px; } .record-list dd { margin-bottom: 8px; } }
        .fingerprint { margin-top: 16px; padding: 12px 16px; background: var(--color-mist); border-radius: var(--radius-control); font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-slate); word-break: break-all; font-variant-numeric: tabular-nums; }
        .fingerprint span { display: block; color: var(--color-muted); }
        .spinner { width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--color-line); border-top-color: var(--color-deep-teal); animation: verify-spin 0.8s linear infinite; flex-shrink: 0; }
        @keyframes verify-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .spinner { animation: none; } }
    </style>

    <x-portal.gateway-header :kicker="__('gateway.verify.kicker')" :width="720" />

    <main class="gateway-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('gateway.verify.heading') }}</h1>
                <p class="page-subtitle">{{ __('gateway.verify.intro') }}</p>
            </div>
        </div>

        <section class="card">
            @if ($verificationState === 'scanning')
                <div class="flex items-center gap-3" role="status">
                    <span class="spinner" aria-hidden="true"></span>
                    <span style="color: var(--color-ink); font-weight: 500;">{{ __('gateway.verify.checking') }}</span>
                </div>
                <div class="fingerprint">
                    <span>{{ __('gateway.common.fingerprint') }}</span>
                    {{ substr($hash, 0, 16) }}…{{ substr($hash, -16) }}
                </div>
            @endif

            @if ($verificationState === 'valid')
                <div class="alert alert-success" role="status">
                    <i data-lucide="shield-check"></i>
                    <div>
                        <strong style="display: block;">{{ __('gateway.verify.valid_title') }}</strong>
                        {{ __('gateway.verify.valid_body') }}
                    </div>
                </div>

                <dl class="record-list">
                    <dt>{{ __('gateway.common.project') }}</dt>
                    <dd>{{ $document->project_title }}</dd>
                    <dt>{{ __('gateway.common.organisation') }}</dt>
                    <dd>{{ $document->client_name }}</dd>
                    <dt>{{ __('gateway.common.signed_by') }}</dt>
                    <dd>
                        {{ $document->signatory_name }}
                        <small>{{ collect([$document->signatory_job_title, $document->signatory_company])->filter()->implode(', ') }}</small>
                    </dd>
                    <dt>{{ __('gateway.common.signed_on') }}</dt>
                    <dd>{{ F::dateTime($document->signed_at) }}</dd>
                    <dt>{{ __('gateway.common.ip_address') }}</dt>
                    <dd>{{ $document->client_ip_address }}</dd>
                </dl>
                <div class="fingerprint">
                    <span>{{ __('gateway.common.fingerprint') }}</span>
                    {{ $document->client_signature_hash }}
                </div>
            @endif

            @if ($verificationState === 'invalid')
                <div class="alert alert-danger" role="alert">
                    <i data-lucide="circle-alert"></i>
                    <div>
                        <strong style="display: block;">{{ __('gateway.verify.invalid_title') }}</strong>
                        {{ __('gateway.verify.invalid_body') }}
                    </div>
                </div>
                <div class="fingerprint">
                    <span>{{ __('gateway.verify.checked') }}</span>
                    {{ $hash }}
                </div>
            @endif
        </section>
    </main>

    <script>
        // Run the check once the page has loaded
        function verificationScanner() {
            return {
                init() {
                    if (@js($verificationState) === 'scanning') {
                        @this.call('executeVerificationScan');
                    }
                }
            }
        }
    </script>
</div>
