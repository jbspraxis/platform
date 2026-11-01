@use('App\Livewire\Portal\Concerns\GatewayFormat', 'F')
@php
    $company = $organization->name ?? '';
    $currency = $proposal->currency ?? 'USD';
    $ref = filled($proposal->proposal_ref ?? null) ? $proposal->proposal_ref : 'PRX-' . strtoupper(substr($token ?? '', 0, 8));
    $isSigned = ($viewState ?? '') === 'completed';
    $vatAmount = ($proposal->total_value ?? 0) - ($proposal->subtotal ?? 0);
    // The VAT HQ signed it with (App\Support\Finance\Vat): without it there is no VAT line and the total is the subtotal
    $vatInfo = \App\Support\Finance\Vat::recorded($proposal->vat_applied ?? null, $proposal->vat_rate ?? 0, $proposal->billing_entity_id ?? null, $proposal->organization_id ?? null);
    $vatApplies = $vatInfo['applies'];
    $vatNote = \App\Support\Finance\Vat::note($vatInfo);
    $validityDays = (int) ($proposal->validity_days ?? 7);

    // Payment terms: a custom deposit, or instalments stored as "50_50" / "40_30_30"
    $scheduleType = (string) ($proposal->payment_schedule_type ?? '');
    $splitParts = array_values(array_filter(explode('_', $scheduleType), 'is_numeric'));
    if ($scheduleType === 'custom') {
        $schedule = __('gateway.proposal.schedule_custom', ['percent' => F::percent($proposal->custom_deposit_percent ?? 0)]);
    } elseif ($splitParts) {
        $schedule = __('gateway.proposal.schedule_split', ['split' => implode(' / ', array_map(fn ($p) => F::percent($p), $splitParts))]);
    } elseif ($scheduleType !== '') {
        $schedule = $scheduleType;
    } else {
        $schedule = __('gateway.proposal.schedule_standard');
    }

    $steps = ['summary', 'approach', 'plan', 'fees', 'sign'];
    $stepCount = count($steps);
@endphp
{{-- The proposal a client opens from the email: a five-step summary that ends in signing, and the full document
     (paged as A4 in the browser, downloadable as PDF). Behaviour lives in App\Livewire\Portal\ProposalGateway. --}}
<div class="gateway proposal-gateway"
     x-data="{ isExecMode: true, step: {{ $viewState === 'review' ? 0 : 4 }} }"
     @reinit-deck.window="step = 4">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .gateway-main { max-width: 1040px; margin: 0 auto; padding: 24px 16px 48px; }
        @media (min-width: 768px) { .gateway-main { padding: 32px 32px 64px; } }
        .gateway .btn { white-space: normal; text-align: center; }

        /* Steps */
        .stepper { display: flex; gap: 8px; margin: 0 0 16px; padding: 0; list-style: none; }
        .stepper li { flex: 1; display: flex; flex-direction: column; gap: 6px; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
        .stepper li::before { content: ""; height: 4px; border-radius: 2px; background: var(--color-line); }
        .stepper li.is-done::before { background: var(--color-teal); }
        .stepper li.is-current::before { background: var(--color-deep-teal); }
        .stepper li.is-current { color: var(--color-ink); font-weight: 600; }
        @media (max-width: 640px) { .stepper li span { display: none; } }
        .step-card { max-width: 760px; }
        .step-count { margin: 0 0 4px; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
        .step-body { color: var(--color-slate); font-size: 1rem; line-height: 1.625rem; }
        .step-body p { margin: 0 0 16px; }
        .step-actions { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--color-line); }
        .step-actions .btn-primary { margin-left: auto; }
        .summary-grid { display: grid; gap: 16px; grid-template-columns: 1fr; margin-top: 8px; }
        @media (min-width: 640px) { .summary-grid { grid-template-columns: 1fr 1fr; } }
        .summary-box { background: var(--color-mist); border-radius: var(--radius-card); padding: 16px 20px; }
        .summary-total { font-size: clamp(1.75rem, 6vw, 2.5rem); line-height: 1.15; overflow-wrap: anywhere; margin-top: 4px; }
        .summary-value { margin-top: 4px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
        .tag-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .tag { padding: 2px 10px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); color: var(--color-slate); font-size: 0.8125rem; line-height: 1.25rem; }
        .phase-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 12px; }
        .phase-list li { padding: 12px 16px; border: 1px solid var(--color-line); border-left: 2px solid var(--color-teal); border-radius: var(--radius-control); }
        .phase-list strong { display: block; color: var(--color-ink); font-weight: 600; font-size: 0.9375rem; }
        .phase-list span { display: block; margin-top: 2px; color: var(--color-slate); font-size: 0.875rem; line-height: 1.375rem; }
        .table th.num, .doc-table th.num, .doc-table td.num { text-align: right; }
        .totals { margin-left: auto; width: 100%; max-width: 360px; font-size: 0.875rem; line-height: 1.375rem; }
        .totals div { display: flex; justify-content: space-between; gap: 16px; padding: 6px 12px; color: var(--color-slate); }
        .totals .totals-grand { border-top: 1px solid var(--color-line-strong); margin-top: 4px; padding-top: 10px; color: var(--color-ink); font-weight: 600; font-size: 1rem; }

        /* Signing */
        .sig-canvas-container { position: relative; height: 180px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); }
        .sig-canvas-container::after { content: ""; position: absolute; left: 16px; right: 16px; bottom: 40px; border-bottom: 1px solid var(--color-line); pointer-events: none; }
        canvas#signaturePad { display: block; width: 100%; height: 100%; cursor: crosshair; touch-action: none; }
        .otp-input { max-width: 220px; font-size: 1.5rem; line-height: 2rem; font-variant-numeric: tabular-nums; text-align: center; }

        /* The full document, paged as A4 */
        .a4-wrapper { display: flex; flex-direction: column; align-items: center; gap: 32px; padding: 8px 0 32px; width: 100%; overflow-x: auto; }
        #pdf-render-zone { display: flex; flex-direction: column; gap: 32px; }
        .a4-page { width: 210mm; height: 297mm; background: #FFFFFF; position: relative; border: 1px solid var(--color-line); border-radius: 2px; flex-shrink: 0; overflow: hidden; margin: 0 auto; box-sizing: border-box; color: var(--color-slate); }
        .page-content { padding: 56px 64px 80px; height: 100%; display: flex; flex-direction: column; box-sizing: border-box; }
        .page-footer { position: absolute; bottom: 28px; left: 64px; right: 64px; display: flex; justify-content: space-between; padding-top: 10px; border-top: 1px solid var(--color-line); font-size: 0.6875rem; line-height: 1rem; color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .sow-section { page-break-inside: avoid; break-inside: avoid; margin-bottom: 24px; }
        .doc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 16px; }
        .doc-logo { height: 32px; width: auto; }
        .doc-meta { text-align: right; font-size: 0.75rem; line-height: 1.125rem; color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .doc-meta strong { color: var(--color-ink); font-weight: 600; }
        .doc-kicker { font-size: 0.8125rem; font-weight: 600; color: var(--color-deep-teal); margin-bottom: 4px; }
        .doc-title { font-family: var(--font-serif); font-weight: 500; font-size: 1.75rem; line-height: 2.25rem; color: var(--color-ink); margin: 0 0 16px; text-wrap: balance; }
        .doc-h2 { font-family: var(--font-serif); font-weight: 500; font-size: 1.125rem; line-height: 1.5rem; color: var(--color-ink); margin: 0 0 8px; }
        .doc-text { font-size: 0.8125rem; line-height: 1.375rem; color: var(--color-slate); white-space: pre-wrap; margin: 0; }
        .doc-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; padding: 12px 16px; background: var(--color-mist); border-radius: 4px; font-size: 0.75rem; line-height: 1.125rem; }
        .doc-facts span { color: var(--color-muted); }
        .doc-facts strong { color: var(--color-ink); font-weight: 600; }
        .doc-list { margin: 0; padding-left: 18px; columns: 2; font-size: 0.75rem; line-height: 1.25rem; color: var(--color-slate); }
        .doc-phase { padding: 8px 0 8px 12px; border-left: 2px solid var(--color-teal); margin-bottom: 8px; }
        .doc-phase strong { display: block; font-size: 0.8125rem; color: var(--color-ink); font-weight: 600; }
        .doc-phase span { font-size: 0.75rem; line-height: 1.25rem; color: var(--color-slate); }
        .doc-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; line-height: 1.125rem; margin-bottom: 12px; }
        .doc-table th { text-align: left; font-weight: 600; color: var(--color-ink); padding: 6px 8px; border-bottom: 1px solid var(--color-line-strong); }
        .doc-table td { padding: 6px 8px; border-bottom: 1px solid var(--color-line); color: var(--color-slate); vertical-align: top; }
        .doc-table .num, .doc-totals { font-variant-numeric: tabular-nums; }
        .doc-totals { margin-left: auto; width: 60%; font-size: 0.75rem; line-height: 1.125rem; }
        .doc-totals div { display: flex; justify-content: space-between; padding: 3px 8px; }
        .doc-totals .grand { border-top: 1px solid var(--color-line-strong); margin-top: 4px; padding-top: 6px; font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .doc-sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .doc-sig { border: 1px solid var(--color-line); border-radius: 4px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
        .doc-sig-head { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; font-size: 0.8125rem; font-weight: 600; color: var(--color-ink); }
        .doc-sig-img { height: 56px; display: flex; align-items: center; border-bottom: 1px solid var(--color-line); }
        .doc-sig-img img { max-height: 52px; max-width: 100%; object-fit: contain; }
        .doc-dl { display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; margin: 0; font-size: 0.6875rem; line-height: 1rem; }
        .doc-dl dt { color: var(--color-muted); }
        .doc-dl dd { margin: 0; color: var(--color-ink); text-align: right; font-variant-numeric: tabular-nums; }
        .doc-hash { font-size: 0.625rem; line-height: 0.875rem; color: var(--color-muted); word-break: break-all; font-variant-numeric: tabular-nums; }
        .doc-hash span { display: block; color: var(--color-slate); font-weight: 600; }
        .doc-note { font-size: 0.75rem; color: var(--color-muted); }
        .doc-verify { display: flex; gap: 16px; align-items: center; margin-top: 16px; padding: 12px 16px; border: 1px solid var(--color-line); border-radius: 4px; }
        .doc-verify img { width: 72px; height: 72px; }

        @media print {
            .gateway-main > :not(#full-document-wrapper) { display: none !important; }
            .a4-wrapper { padding: 0 !important; gap: 0 !important; }
            .a4-page { border: none !important; margin: 0 !important; }
        }
    </style>

    <x-portal.gateway-header :kicker="__('gateway.proposal.kicker')" />

    <main class="gateway-main">
        <div class="page-header">
            <div class="min-w-0">
                <h1 class="page-title">{{ __('gateway.proposal.heading', ['company' => $company]) }}</h1>
                <p class="page-subtitle">
                    {{ $proposal->title ?? '' }}@if (filled($proposal->title ?? null)) · @endif<span class="num">{{ $ref }}</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($isSigned)
                    <span class="pill pill-success">{{ __('gateway.status.proposal_signed') }}</span>
                @else
                    <span class="pill pill-warning">{{ __('gateway.status.proposal_awaiting') }}</span>
                @endif
                <button type="button" class="btn btn-secondary"
                        @click="isExecMode = !isExecMode; if(!isExecMode) { $nextTick(() => paginateSOW()); } else { setTimeout(initSignaturePad, 100); }">
                    <span x-show="isExecMode" class="inline-flex items-center gap-2"><i data-lucide="file-text"></i> {{ __('gateway.proposal.read_document') }}</span>
                    <span x-show="!isExecMode" x-cloak class="inline-flex items-center gap-2"><i data-lucide="arrow-left"></i> {{ __('gateway.proposal.back_to_summary') }}</span>
                </button>
                <button type="button" onclick="triggerPdfDownload()" id="downloadPdfBtn" class="btn btn-secondary">
                    <i data-lucide="download"></i>
                    <span class="pdf-label">{{ __('gateway.common.download_pdf') }}</span>
                </button>
                @unless ($isSigned)
                    <button type="button" class="btn btn-primary" x-show="!isExecMode" x-cloak
                            @click="isExecMode = true; step = 4; setTimeout(initSignaturePad, 100);">
                        <i data-lucide="pen-line"></i> {{ __('gateway.proposal.go_to_sign') }}
                    </button>
                @endunless
            </div>
        </div>

        {{-- The five steps --}}
        <div id="executive-summary-view" x-show="isExecMode">
            <ol class="stepper" aria-hidden="true">
                @foreach ($steps as $i => $name)
                    <li :class="{ 'is-current': step === {{ $i }}, 'is-done': step > {{ $i }} }"><span>{{ __('gateway.proposal.steps.' . $name) }}</span></li>
                @endforeach
            </ol>

            {{-- 1. Summary --}}
            <section class="card step-card" x-show="step === 0" @if ($viewState !== 'review') x-cloak @endif>
                <p class="step-count">{{ __('gateway.proposal.step_of', ['step' => 1, 'total' => $stepCount]) }}</p>
                <h2 class="section-title">{{ __('gateway.proposal.steps.summary') }}</h2>
                <div class="step-body">
                    <p>{{ __('gateway.proposal.summary_intro', ['title' => $proposal->title ?? $ref]) }}</p>
                </div>
                <div class="summary-grid">
                    <div class="summary-box">
                        <div class="stat-label">{{ $vatApplies ? __('gateway.proposal.total_incl_vat') : __('gateway.proposal.total') }}</div>
                        <div class="headline-number summary-total">{{ F::money($proposal->total_value ?? 0, $currency) }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="stat-label">{{ __('gateway.proposal.payment_terms') }}</div>
                        <div class="summary-value">{{ $schedule }}</div>
                        <div class="stat-label" style="margin-top: 12px;">{{ __('gateway.proposal.validity') }}</div>
                        <div class="summary-value">{{ trans_choice('gateway.proposal.validity_days', $validityDays, ['days' => $validityDays]) }}</div>
                    </div>
                </div>
                <div class="step-actions">
                    <button type="button" @click="step++" class="btn btn-primary">{{ __('gateway.proposal.next_approach') }} <i data-lucide="arrow-right"></i></button>
                </div>
            </section>

            {{-- 2. Approach --}}
            <section class="card step-card" x-show="step === 1" x-cloak>
                <p class="step-count">{{ __('gateway.proposal.step_of', ['step' => 2, 'total' => $stepCount]) }}</p>
                <h2 class="section-title">{{ __('gateway.proposal.approach_title') }}</h2>
                <div class="step-body">
                    @if (filled($narrative->ai_operational_strategy ?? null))
                        <p style="white-space: pre-line;">{{ Str::limit($narrative->ai_operational_strategy, 600) }}</p>
                    @else
                        <p class="muted">{{ __('gateway.proposal.approach_empty') }}</p>
                    @endif
                </div>

                <h3 class="card-title" style="margin: 8px 0 12px;">{{ __('gateway.proposal.tech_title') }}</h3>
                @if (!empty($selectedTech))
                    <div class="tag-list">
                        @foreach ($selectedTech as $tech)
                            <span class="tag">{{ $tech }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="muted" style="margin: 0;">{{ __('gateway.proposal.tech_empty') }}</p>
                @endif

                <div class="step-actions">
                    <button type="button" @click="step--" class="btn btn-secondary"><i data-lucide="arrow-left"></i> {{ __('gateway.common.back') }}</button>
                    <button type="button" @click="step++" class="btn btn-primary">{{ __('gateway.proposal.next_plan') }} <i data-lucide="arrow-right"></i></button>
                </div>
            </section>

            {{-- 3. Plan --}}
            <section class="card step-card" x-show="step === 2" x-cloak>
                <p class="step-count">{{ __('gateway.proposal.step_of', ['step' => 3, 'total' => $stepCount]) }}</p>
                <h2 class="section-title">{{ __('gateway.proposal.plan_title') }}</h2>
                @if (!empty($phases))
                    <ol class="phase-list">
                        @foreach ($phases as $phase)
                            <li>
                                <strong>{{ $phase['short'] ?? '' }}</strong>
                                @if (filled($phase['body'] ?? null))<span>{{ $phase['body'] }}</span>@endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="muted" style="margin: 0;">{{ __('gateway.proposal.plan_empty') }}</p>
                @endif

                <div class="step-actions">
                    <button type="button" @click="step--" class="btn btn-secondary"><i data-lucide="arrow-left"></i> {{ __('gateway.common.back') }}</button>
                    <button type="button" @click="step++" class="btn btn-primary">{{ __('gateway.proposal.next_fees') }} <i data-lucide="arrow-right"></i></button>
                </div>
            </section>

            {{-- 4. Fees --}}
            <section class="card step-card" x-show="step === 3" x-cloak>
                <p class="step-count">{{ __('gateway.proposal.step_of', ['step' => 4, 'total' => $stepCount]) }}</p>
                <h2 class="section-title">{{ __('gateway.proposal.fees_title') }}</h2>
                @if (count($lineItems))
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('gateway.proposal.description') }}</th>
                                    <th class="num">{{ __('gateway.proposal.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lineItems as $line)
                                    <tr>
                                        <td>
                                            <span style="color: var(--color-ink); font-weight: 500;">{{ $line->description ?? '' }}</span>
                                            @if (filled($line->code ?? null))<span class="muted num" style="display: block; text-align: left; font-size: 0.8125rem;">{{ $line->code }}</span>@endif
                                        </td>
                                        <td class="num">{{ F::money($line->total_price ?? 0, $currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="muted">{{ __('gateway.proposal.fees_empty') }}</p>
                @endif
                <div class="totals" style="margin-top: 12px;">
                    @if ($vatApplies)
                        <div><span>{{ __('gateway.proposal.subtotal') }}</span><span class="num">{{ F::money($proposal->subtotal ?? 0, $currency) }}</span></div>
                        <div><span>{{ __('gateway.proposal.vat', ['rate' => F::percent($proposal->vat_rate ?? 0)]) }}</span><span class="num">{{ F::money($vatAmount, $currency) }}</span></div>
                    @endif
                    <div class="totals-grand"><span>{{ __('gateway.proposal.total') }}</span><span class="num">{{ F::money($proposal->total_value ?? 0, $currency) }}</span></div>
                </div>
                @if ($vatNote) <p class="muted" style="margin-top: 8px;">{{ $vatNote }}</p> @endif

                <div class="step-actions">
                    <button type="button" @click="step--" class="btn btn-secondary"><i data-lucide="arrow-left"></i> {{ __('gateway.common.back') }}</button>
                    <button type="button" @click="step++; setTimeout(initSignaturePad, 100);" class="btn btn-primary">{{ __('gateway.proposal.next_sign') }} <i data-lucide="arrow-right"></i></button>
                </div>
            </section>

            {{-- 5. Sign --}}
            <section class="card step-card" x-show="step === 4" x-cloak>
                <p class="step-count">{{ __('gateway.proposal.step_of', ['step' => 5, 'total' => $stepCount]) }}</p>

                @if (($viewState ?? '') === 'completed')
                    <h2 class="section-title">{{ __('gateway.proposal.signed_title') }}</h2>
                    <div class="alert alert-success" role="status">
                        <i data-lucide="circle-check"></i>
                        <span>{{ __('gateway.proposal.signed_body') }}</span>
                    </div>
                    <p class="muted" style="margin: 16px 0 0;">{{ __('gateway.proposal.setup_note') }}</p>
                    <div class="step-actions">
                        <button type="button" @click="isExecMode = false; $nextTick(() => paginateSOW());" class="btn btn-secondary">
                            <i data-lucide="file-text"></i> {{ __('gateway.proposal.view_signed') }}
                        </button>
                    </div>

                @elseif (($viewState ?? '') === 'otp_verification')
                    <h2 class="section-title">{{ __('gateway.proposal.code_title') }}</h2>
                    <p class="step-body" style="margin: 0 0 16px;">{{ filled($signerEmail ?? null) ? __('gateway.proposal.code_sent', ['email' => $signerEmail]) : __('gateway.proposal.code_sent_generic') }}</p>

                    <x-flash style="margin-bottom: 16px;" />

                    <form wire:submit.prevent="verifyOtpAndExecuteLaunch">
                        <div class="field">
                            <label for="proposal-otp" class="label">{{ __('gateway.common.code_label') }}</label>
                            <input type="text" id="proposal-otp" wire:model.defer="otpInput" class="input otp-input" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" aria-describedby="proposal-otp-help">
                            <span id="proposal-otp-help" class="help">{{ __('gateway.common.code_help') }}</span>
                            @error('otpInput') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                        </div>

                        <div class="step-actions">
                            <button type="button" @click="isExecMode = false; $nextTick(() => paginateSOW());" class="btn btn-secondary">
                                <i data-lucide="file-text"></i> {{ __('gateway.proposal.read_document') }}
                            </button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="verifyOtpAndExecuteLaunch">{{ __('gateway.proposal.confirm_sign') }}</span>
                                <span wire:loading wire:target="verifyOtpAndExecuteLaunch">{{ __('gateway.proposal.signing') }}</span>
                            </button>
                        </div>
                    </form>

                @else
                    <h2 class="section-title">{{ __('gateway.proposal.sign_title') }}</h2>
                    <p class="step-body" style="margin: 0 0 8px;">{{ __('gateway.proposal.sign_intro') }}</p>
                    <button type="button" @click="isExecMode = false; $nextTick(() => paginateSOW());" class="btn btn-ghost" style="margin: 0 0 16px -8px;">
                        <i data-lucide="file-text"></i> {{ __('gateway.proposal.read_document') }}
                    </button>

                    @error('signerEmail')
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 16px;">
                            <i data-lucide="circle-alert"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                    @error('signingCode')
                        <div class="alert alert-warning" role="alert" style="margin-bottom: 16px;">
                            <i data-lucide="clock"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <form id="signature-form" class="flex flex-col gap-4" @submit.prevent="submitSignature()">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <label for="signer-name" class="label">{{ __('gateway.proposal.full_name') }}</label>
                                <input type="text" id="signer-name" wire:model.defer="signerName" class="input" autocomplete="name" required>
                                @error('signerName') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="signer-title" class="label">{{ __('gateway.proposal.job_title') }}</label>
                                <input type="text" id="signer-title" wire:model.defer="signerTitle" class="input" autocomplete="organization-title" required>
                                @error('signerTitle') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="field">
                            <label for="signer-email" class="label">{{ __('gateway.proposal.email') }}</label>
                            <input type="email" id="signer-email" wire:model.defer="signerEmail" class="input" autocomplete="email" required>
                        </div>

                        <div class="field">
                            <div class="flex items-center justify-between gap-3">
                                <span class="label" id="signature-label">{{ __('gateway.proposal.signature') }}</span>
                                <button type="button" onclick="clearCanvas()" class="btn btn-ghost btn-sm">{{ __('gateway.common.clear') }}</button>
                            </div>
                            <div class="sig-canvas-container" wire:ignore>
                                <canvas id="signaturePad" role="img" aria-labelledby="signature-label"></canvas>
                            </div>
                            <span class="help">{{ __('gateway.proposal.signature_help') }}</span>
                            <span id="signature-missing" class="field-error" role="alert" hidden>{{ __('gateway.proposal.signature_missing') }}</span>
                            @error('signatureBase64') <span class="field-error">{{ __('gateway.proposal.signature_missing') }}</span> @enderror
                        </div>

                        <div class="step-actions">
                            <button type="button" @click="step--" class="btn btn-secondary"><i data-lucide="arrow-left"></i> {{ __('gateway.common.back') }}</button>
                            <button type="button" onclick="submitSignature()" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="requestAuthorizationOtp">{{ __('gateway.proposal.send_code') }}</span>
                                <span wire:loading wire:target="requestAuthorizationOtp">{{ __('gateway.proposal.sending_code') }}</span>
                            </button>
                        </div>
                    </form>
                @endif
            </section>
        </div>

        {{-- The document's content: paginateSOW() copies these sections into A4 pages --}}
        <div id="sow-raw-content" style="display: none;">
            <div class="sow-section">
                <div class="doc-head">
                    <img class="doc-logo" src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis">
                    <div class="doc-meta">
                        <div>{{ __('gateway.common.reference') }}{{ __('gateway.common.colon') }} <strong>{{ $ref }}</strong></div>
                        <div>{{ __('gateway.common.date') }}{{ __('gateway.common.colon') }} <strong>{{ F::date($proposal->updated_at ?? now()) }}</strong></div>
                    </div>
                </div>
                <hr class="praxis-rule" style="margin: 0;">
            </div>

            <div class="sow-section">
                <div class="doc-kicker">{{ __('gateway.proposal.kicker') }}</div>
                <h1 class="doc-title">{{ $proposal->title ?? '' }}</h1>
                <div class="doc-facts">
                    <div><span>{{ __('gateway.proposal.doc.organisation') }}{{ __('gateway.common.colon') }}</span> <strong>{{ $company }}</strong></div>
                    <div><span>{{ __('gateway.proposal.doc.currency') }}{{ __('gateway.common.colon') }}</span> <strong>{{ $currency }}</strong></div>
                    <div><span>{{ __('gateway.proposal.validity') }}{{ __('gateway.common.colon') }}</span> <strong>{{ trans_choice('gateway.proposal.validity_days', $validityDays, ['days' => $validityDays]) }}</strong></div>
                    <div><span>{{ __('gateway.proposal.payment_terms') }}{{ __('gateway.common.colon') }}</span> <strong>{{ $schedule }}</strong></div>
                </div>
            </div>

            <div class="sow-section">
                <h2 class="doc-h2">{{ __('gateway.proposal.doc.executive_summary') }}</h2>
                <p class="doc-text">{{ $narrative->ai_executive_summary ?? __('gateway.proposal.doc.pending_text') }}</p>
            </div>

            <div class="sow-section">
                <h2 class="doc-h2">{{ __('gateway.proposal.approach_title') }}</h2>
                <p class="doc-text">{{ $narrative->ai_operational_strategy ?? __('gateway.proposal.doc.pending_text') }}</p>
            </div>

            @if (!empty($selectedTech) && count($selectedTech) > 0)
                <div class="sow-section">
                    <h2 class="doc-h2">{{ __('gateway.proposal.tech_title') }}</h2>
                    <ul class="doc-list">
                        @foreach ($selectedTech as $tech)
                            <li>{{ $tech }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="sow-section">
                <h2 class="doc-h2">{{ __('gateway.proposal.plan_title') }}</h2>
                @if (!empty($phases))
                    @foreach ($phases as $phase)
                        <div class="doc-phase">
                            <strong>{{ $phase['label'] ?? '' }}</strong>
                            <span>{{ $phase['body'] ?? '' }}</span>
                        </div>
                    @endforeach
                @else
                    <p class="doc-note">{{ __('gateway.proposal.doc.pending_text') }}</p>
                @endif
            </div>

            <div class="sow-section">
                <h2 class="doc-h2">{{ __('gateway.proposal.fees_title') }}</h2>
                <table class="doc-table">
                    <thead>
                        <tr>
                            <th>{{ __('gateway.proposal.code') }}</th>
                            <th>{{ __('gateway.proposal.description') }}</th>
                            <th class="num">{{ __('gateway.proposal.quantity') }}</th>
                            <th class="num">{{ __('gateway.proposal.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lineItems as $line)
                            <tr>
                                <td style="color: var(--color-ink); font-weight: 600;">{{ $line->code ?? '' }}</td>
                                <td>{{ $line->description ?? '' }}</td>
                                <td class="num">{{ $line->quantity ?? '' }}</td>
                                <td class="num">{{ F::money($line->total_price ?? 0, $currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="doc-totals">
                    @if ($vatApplies)
                        <div><span>{{ __('gateway.proposal.subtotal') }}</span><span>{{ F::money($proposal->subtotal ?? 0, $currency) }}</span></div>
                        <div><span>{{ __('gateway.proposal.vat', ['rate' => F::percent($proposal->vat_rate ?? 0)]) }}</span><span>{{ F::money($vatAmount, $currency) }}</span></div>
                    @endif
                    <div class="grand"><span>{{ __('gateway.proposal.total') }}</span><span>{{ F::money($proposal->total_value ?? 0, $currency) }}</span></div>
                </div>
                @if ($vatNote) <p class="muted" style="margin-top: 8px;">{{ $vatNote }}</p> @endif
            </div>

            @if (!empty($proposal->exclusions))
                <div class="sow-section">
                    <h2 class="doc-h2">{{ __('gateway.proposal.doc.exclusions') }}</h2>
                    <p class="doc-text">{{ $proposal->exclusions }}</p>
                </div>
            @endif

            <div class="sow-section">
                <h2 class="doc-h2">{{ __('gateway.proposal.doc.signatures') }}</h2>
                <div class="doc-sig-grid">
                    <div class="doc-sig">
                        <div class="doc-sig-head">{{ __('gateway.proposal.doc.for_praxis') }}</div>
                        <div class="doc-sig-img">
                            @if (filled($proposal->hq_signature_b64 ?? null))
                                <img src="{{ $proposal->hq_signature_b64 }}" alt="">
                            @endif
                        </div>
                        <dl class="doc-dl">
                            <dt>{{ __('gateway.proposal.doc.name') }}</dt><dd>{{ $proposal->hq_signer_name ?? '—' }}</dd>
                            <dt>{{ __('gateway.common.signed_on') }}</dt><dd>{{ filled($proposal->hq_signed_at ?? null) ? F::dateTime($proposal->hq_signed_at) : '—' }}</dd>
                            <dt>{{ __('gateway.common.ip_address') }}</dt><dd>{{ $proposal->hq_ip_address ?? '—' }}</dd>
                        </dl>
                        <div class="doc-hash"><span>{{ __('gateway.common.fingerprint') }}</span>{{ $proposal->hq_signature_hash ?? __('gateway.proposal.doc.not_signed') }}</div>
                    </div>

                    @if (($viewState ?? '') === 'completed')
                        <div class="doc-sig">
                            <div class="doc-sig-head">
                                <span>{{ __('gateway.proposal.doc.for_client', ['company' => $company]) }}</span>
                                <span class="pill pill-success">{{ __('gateway.status.proposal_signed') }}</span>
                            </div>
                            <div class="doc-sig-img">
                                @if (filled($proposal->client_signature_b64 ?? null))
                                    <img src="{{ $proposal->client_signature_b64 }}" alt="">
                                @endif
                            </div>
                            <dl class="doc-dl">
                                <dt>{{ __('gateway.proposal.doc.name') }}</dt><dd>{{ $proposal->signed_by_name ?? '' }}</dd>
                                <dt>{{ __('gateway.common.signed_on') }}</dt><dd>{{ filled($proposal->signed_at ?? null) ? F::dateTime($proposal->signed_at) : '—' }}</dd>
                                <dt>{{ __('gateway.common.ip_address') }}</dt><dd>{{ $proposal->client_ip_address ?? '—' }}</dd>
                            </dl>
                            <div class="doc-hash"><span>{{ __('gateway.common.fingerprint') }}</span>{{ $proposal->client_signature_hash ?? '' }}</div>
                        </div>
                    @elseif (($viewState ?? '') === 'otp_verification')
                        <div class="doc-sig">
                            <div class="doc-sig-head">
                                <span>{{ __('gateway.proposal.doc.for_client', ['company' => $company]) }}</span>
                                <span class="pill pill-warning">{{ __('gateway.proposal.doc.awaiting_code') }}</span>
                            </div>
                            <div class="doc-sig-img">
                                @if (filled($proposal->client_signature_b64 ?? null))
                                    <img src="{{ $proposal->client_signature_b64 }}" alt="" style="opacity: 0.6;">
                                @endif
                            </div>
                            <dl class="doc-dl">
                                <dt>{{ __('gateway.proposal.doc.name') }}</dt><dd>{{ explode(' (', $proposal->signed_by_name ?? '')[0] ?? '' }}</dd>
                                <dt>{{ __('gateway.common.signed_on') }}</dt><dd>—</dd>
                            </dl>
                        </div>
                    @else
                        <div class="doc-sig">
                            <div class="doc-sig-head">
                                <span>{{ __('gateway.proposal.doc.for_client', ['company' => $company]) }}</span>
                                <span class="pill pill-warning">{{ __('gateway.proposal.doc.awaiting_you') }}</span>
                            </div>
                            <div class="doc-sig-img"></div>
                            <p class="doc-note" style="margin: 0;">{{ __('gateway.proposal.doc.sign_from_summary') }}</p>
                        </div>
                    @endif
                </div>

                @if (($viewState ?? '') === 'completed')
                    @php
                        $baseUrl = str(route('portal.verify.contract', ['hash' => 'HASH']))->beforeLast('/HASH')->toString();
                        $hqHash = $proposal->hq_signature_hash ?? '';
                        $cliHash = $proposal->client_signature_hash ?? '';
                        $qrData = "URL:{$baseUrl}/{$cliHash} | ORG:{$hqHash} | CLT:{$cliHash}";
                    @endphp
                    <div class="doc-verify">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($qrData) }}&color=0C142B&bgcolor=FFFFFF" alt="">
                        <div>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: var(--color-ink);">{{ __('gateway.proposal.doc.verify_title') }}</div>
                            <div class="doc-note">{{ __('gateway.proposal.doc.verify_body') }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div id="full-document-wrapper" class="a4-wrapper" x-show="!isExecMode" style="display: none;">
            <div id="pdf-render-zone"></div>
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", () => { initSignaturePad(); });
        document.addEventListener("livewire:navigated", () => { initSignaturePad(); });

        const gatewayText = {
            preparing: @js(__('gateway.common.preparing_pdf')),
            pdfFailed: @js(__('gateway.common.pdf_failed')),
            pageOf: @js(__('gateway.common.page_of')),
            footer: @js(__('gateway.proposal.doc.footer', ['ref' => $ref])),
            filename: @js(__('gateway.proposal.pdf_filename', ['ref' => Str::slug($ref)])),
        };

        // --- Signature pad ---
        let canvas, ctx, isDrawing = false;
        function initSignaturePad() {
            canvas = document.getElementById('signaturePad');
            if (!canvas) return;
            ctx = canvas.getContext('2d');
            setTimeout(() => {
                canvas.width = canvas.parentElement.clientWidth;
                canvas.height = canvas.parentElement.clientHeight;
                ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#0C142B';
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
        function clearCanvas() { if (ctx && canvas) { ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.beginPath(); } }
        function submitSignature() {
            if (!canvas) return;
            const missing = document.getElementById('signature-missing');
            const dataUrl = canvas.toDataURL('image/png');
            const blank = document.createElement('canvas'); blank.width = canvas.width; blank.height = canvas.height;
            if (dataUrl === blank.toDataURL()) { if (missing) missing.hidden = false; return; }
            if (missing) missing.hidden = true;
            @this.call('requestAuthorizationOtp', dataUrl);
        }

        // --- Pages the document into A4 sheets on screen ---
        function paginateSOW() {
            const rawContent = document.getElementById('sow-raw-content');
            const renderZone = document.getElementById('pdf-render-zone');
            if (!rawContent || !renderZone) return;

            renderZone.innerHTML = '';
            setTimeout(() => {
                renderZone.innerHTML = '';
                let pageNum = 1;
                let currentPage = createA4Page(pageNum);
                renderZone.appendChild(currentPage);

                let contentArea = currentPage.querySelector('.page-content');
                // The writing area ends where the bottom padding (which keeps clear of the footer) begins
                const limit = () => (contentArea.clientHeight || 1123) - parseFloat(getComputedStyle(contentArea).paddingBottom || 0);

                Array.from(rawContent.children).forEach((child) => {
                    const clone = child.cloneNode(true);
                    contentArea.appendChild(clone);

                    // A section that runs past the writing area moves to a new page
                    if (contentArea.children.length > 1 && clone.offsetTop + clone.offsetHeight > limit()) {
                        contentArea.removeChild(clone);
                        pageNum++;
                        currentPage = createA4Page(pageNum);
                        renderZone.appendChild(currentPage);
                        contentArea = currentPage.querySelector('.page-content');
                        contentArea.appendChild(clone);
                    }
                });

                document.querySelectorAll('.total-pages').forEach(el => el.innerText = pageNum);
            }, 50);
        }

        function createA4Page(num) {
            const page = document.createElement('div');
            page.className = 'a4-page';
            const pageLabel = gatewayText.pageOf.replace(':page', num).replace(':total', '<span class="total-pages"></span>');
            page.innerHTML = `
                <div class="page-content"></div>
                <div class="page-footer">
                    <div></div>
                    <div>${pageLabel}</div>
                </div>
            `;
            page.querySelector('.page-footer div').textContent = gatewayText.footer;
            return page;
        }

        // --- PDF download (the A4 pages, one to one) ---
        async function triggerPdfDownload() {
            const btn = document.getElementById('downloadPdfBtn');
            if (!btn) return;
            const label = btn.querySelector('.pdf-label');
            const original = label ? label.textContent : '';
            if (label) label.textContent = gatewayText.preparing;
            btn.disabled = true;

            await new Promise(r => setTimeout(r, 100));

            try {
                const wrapper = document.getElementById('full-document-wrapper');
                const renderZone = document.getElementById('pdf-render-zone');

                const wasHidden = wrapper.style.display === 'none';
                if (wasHidden) {
                    wrapper.style.display = 'flex';
                    paginateSOW();
                    await new Promise(r => setTimeout(r, 200));
                }

                // Join the pages without the on-screen gaps and borders
                const origGap = renderZone.style.gap;
                renderZone.style.gap = '0px';
                const pages = document.querySelectorAll('.a4-page');
                pages.forEach(p => { p.style.border = 'none'; });

                const opt = {
                    margin: 0,
                    filename: gatewayText.filename + '.pdf',
                    image: { type: 'jpeg', quality: 1.0 },
                    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#FFFFFF' },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };

                await html2pdf().set(opt).from(renderZone).save();

                renderZone.style.gap = origGap;
                pages.forEach(p => { p.style.border = ''; });
                if (wasHidden) wrapper.style.display = 'none';
            } catch (err) {
                console.error(err);
                alert(gatewayText.pdfFailed);
            } finally {
                if (label) label.textContent = original;
                btn.disabled = false;
            }
        }
    </script>
</div>
