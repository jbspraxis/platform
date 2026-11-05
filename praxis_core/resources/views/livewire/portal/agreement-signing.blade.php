{{--
    The other party reads and signs an agreement (docs/DOCUMENTS.md section 12), from the private link JBS Praxis
    emailed them. The behaviour is in App\Livewire\Portal\AgreementSigning; the signature pad is the one HQ uses
    (resources/letters/documents.js), so a drawing or a photograph both arrive as a JPEG on white, which is the
    only kind of picture the PDF can carry.
--}}
@php
    $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone('Africa/Lagos')->isoFormat('D MMMM YYYY') : '';
@endphp
<div class="gateway ag-gateway">
    @assets
        <script src="{{ route('portal.agreement.script', ['v' => filemtime(resource_path('letters/documents.js'))]) }}"></script>
    @endassets

    <style>
        .gateway-main { max-width: 880px; margin: 0 auto; padding: 24px 16px 48px; }
        @media (min-width: 768px) { .gateway-main { padding: 32px 32px 64px; } }
        .gateway .btn { white-space: normal; text-align: center; }
        .ag-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 8px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .ag-stack { display: flex; flex-direction: column; gap: 16px; }
        .ag-doc { font-size: 0.9375rem; line-height: 1.6; color: var(--color-slate); overflow-wrap: anywhere; }
        .ag-doc h1 { margin: 0 0 4px; font-family: var(--font-serif); font-size: 1.5rem; line-height: 2rem; font-weight: 400; color: var(--color-ink); }
        .ag-doc h2 { margin: 20px 0 6px; font-family: var(--font-serif); font-size: 1.25rem; line-height: 1.75rem; font-weight: 400; color: var(--color-ink); }
        .ag-doc h3 { margin: 16px 0 4px; font-size: 1rem; font-weight: 600; color: var(--color-ink); }
        .ag-doc p { margin: 0 0 10px; }
        .ag-doc ul, .ag-doc ol { margin: 0 0 12px; padding-left: 20px; }
        .ag-doc li { margin-bottom: 4px; }
        .ag-doc table { width: 100%; border-collapse: collapse; margin: 0 0 14px; font-size: 0.875rem; }
        .ag-doc th, .ag-doc td { border: 1px solid var(--color-line); padding: 6px 8px; text-align: left; vertical-align: top; }
        .ag-doc th { background: var(--color-mist); color: var(--color-ink); font-weight: 600; }
        .ag-head { text-align: center; padding-bottom: 8px; border-bottom: 1px solid var(--color-line); margin-bottom: 16px; }
        .ag-head p { margin: 4px 0 0; font-size: 0.8125rem; color: var(--color-muted); }
        .ag-sigs { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr)); }
        .ag-sig { padding: 12px 14px; border: 1px solid var(--color-line); border-radius: var(--radius-control); font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .ag-sig strong { display: block; color: var(--color-ink); }
        .ag-sig img { display: block; max-width: 100%; height: 64px; object-fit: contain; margin: 6px 0; background: #FFF; }
        .ag-form { display: flex; flex-direction: column; gap: 14px; margin-top: 12px; }
        .ag-buttons { display: flex; flex-wrap: wrap; gap: 12px; }
        .ag-pad { width: 100%; height: 160px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); touch-action: none; cursor: crosshair; display: block; }
        .ag-pad-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin-top: 8px; }
        .ag-preview { display: block; height: 56px; margin-top: 8px; background: #FFF; border: 1px solid var(--color-line); border-radius: var(--radius-control); }
        .otp-input { max-width: 220px; font-size: 1.5rem; line-height: 2rem; font-variant-numeric: tabular-nums; text-align: center; letter-spacing: 0.2em; }
        @media (max-width: 560px) { .ag-buttons .btn { width: 100%; } }
    </style>

    <x-portal.gateway-header :kicker="__('agreements.portal.eyebrow')" :width="880" />

    <main class="gateway-main">
        <div class="page-header">
            <div class="min-w-0">
                <h1 class="page-title" style="overflow-wrap: anywhere">{{ $document->title ?: $document->reference }}</h1>
                <p class="page-subtitle">{{ __('agreements.portal.intro', ['company' => $details['name']]) }}</p>
                <div class="ag-meta">
                    <span>{{ $document->reference }}</span>
                    <span>{{ $day($document->document_date) }}</span>
                    <span>{{ $details['name'] }}</span>
                </div>
            </div>
        </div>

        <div class="ag-stack">
            @if ($done)
                <div class="alert alert-success" role="status"><i data-lucide="badge-check"></i>
                    <span><strong>{{ __('agreements.portal.done_title') }}</strong>
                        {{ $document->isFullySigned()
                            ? __('agreements.portal.done', ['reference' => $document->reference])
                            : __('agreements.portal.done_pending', ['reference' => $document->reference]) }}</span></div>
            @elseif ($alreadySigned)
                <div class="alert alert-success" role="status"><i data-lucide="badge-check"></i>
                    <span><strong>{{ __('agreements.portal.already_title') }}</strong>
                        {{ __('agreements.portal.already', ['reference' => $document->reference, 'date' => $day($party->signed_at)]) }}</span></div>
            @elseif ($refusal === 'expired')
                <div class="alert alert-warning" role="note"><i data-lucide="calendar-x"></i>
                    <span><strong>{{ __('agreements.portal.expired_title') }}</strong>
                        {{ __('agreements.portal.expired', ['days' => \App\Support\Letters\Agreements::DAYS, 'email' => $party->email]) }}</span></div>
            @elseif ($refusal === 'closed')
                <div class="alert alert-warning" role="note"><i data-lucide="file-x"></i>
                    <span><strong>{{ __('agreements.portal.closed_title') }}</strong> {{ __('agreements.portal.closed') }}</span></div>
            @endif

            <section class="card" aria-labelledby="ag-doc-title">
                <div class="ag-buttons" style="justify-content: flex-end; margin-bottom: 8px">
                    <a class="btn btn-secondary btn-sm" href="{{ $pdfUrl }}" target="_blank" rel="noopener"><i data-lucide="file-text"></i> {{ __('agreements.portal.read_pdf') }}</a>
                </div>
                <div class="ag-doc" id="ag-doc-title">
                    @foreach ($blocks as $block)
                        @switch($block['kind'])
                            @case('agreement_head')
                                <div class="ag-head">
                                    <h1>{{ $block['title'] }}</h1>
                                    <p>{{ $block['reference'] }} · {{ $block['date'] }}</p>
                                </div>
                                @break
                            @case('html')
                                {!! $block['html'] !!}
                                @break
                            @case('para')
                                <p>{{ $block['text'] }}</p>
                                @break
                            @case('subject')
                                <p><strong>{{ $block['text'] }}</strong></p>
                                @break
                        @endswitch
                    @endforeach
                </div>
            </section>

            <section class="card" aria-labelledby="ag-sigs-title">
                <h2 id="ag-sigs-title" class="card-title" style="margin-bottom: 12px">{{ __('agreements.portal.your_box') }}</h2>
                <div class="ag-sigs">
                    <div class="ag-sig">
                        <strong>{{ $details['name'] }}</strong>
                        @if ($signedByUs)
                            <img src="{{ $signedByUs->dataUri() }}" alt="">
                            {{ __('agreements.portal.signed_by_us', ['company' => $details['name'], 'name' => $signedByUs->signatory_name, 'title' => $signedByUs->signatory_title, 'date' => $day($signedByUs->signed_at)]) }}
                        @else
                            {{ __('agreements.portal.unsigned_by_us', ['company' => $details['name']]) }}
                        @endif
                    </div>
                    <div class="ag-sig">
                        <strong>{{ $party->name }}</strong>
                        @if ($party->hasSigned())
                            @if ($party->dataUri())<img src="{{ $party->dataUri() }}" alt="">@endif
                            {{ $party->signed_name }} · {{ $party->role_title }} · {{ $day($party->signed_at) }}
                        @else
                            {{ __('agreements.portal.your_name_printed', ['name' => $party->name, 'title' => $party->role_title]) }}
                        @endif
                    </div>
                </div>

                @if (! $alreadySigned && ! $done && $refusal === null)
                    @if ($step === 'details')
                        <form wire:submit="requestCode" class="ag-form" novalidate>
                            <div class="field">
                                <label for="ag-name" class="label">{{ __('agreements.portal.name') }}</label>
                                <input id="ag-name" type="text" class="input" maxlength="150" wire:model="signedName"
                                       aria-describedby="ag-name-help @error('signedName') ag-name-error @enderror" @error('signedName') aria-invalid="true" @enderror>
                                <span class="help" id="ag-name-help">{{ __('agreements.portal.name_help') }}</span>
                                @error('signedName') <span class="field-error" id="ag-name-error" role="alert">{{ $message }}</span> @enderror
                            </div>

                            <div class="field" x-data="signaturePad()" wire:ignore>
                                <span class="label">{{ __('agreements.portal.signature') }}</span>
                                <canvas class="ag-pad" x-ref="pad" role="img" aria-label="{{ __('agreements.portal.signature') }}"></canvas>
                                <div class="ag-pad-foot">
                                    <button type="button" class="btn btn-primary btn-sm" x-on:click="use()"><i data-lucide="check"></i> {{ __('agreements.portal.signature_use') }}</button>
                                    <button type="button" class="btn btn-ghost btn-sm" x-on:click="clear()"><i data-lucide="eraser"></i> {{ __('agreements.portal.signature_clear') }}</button>
                                    <label class="btn btn-secondary btn-sm">
                                        <i data-lucide="image"></i> {{ __('agreements.portal.signature_upload') }}
                                        <input type="file" accept="image/*" class="sr-only" x-on:change="chooseFile($event)">
                                    </label>
                                </div>
                                <span class="help">{{ __('agreements.portal.signature_help') }}</span>
                            </div>

                            @if ($signature !== '')
                                <p class="help" style="margin: 0"><i data-lucide="check-circle"></i> {{ __('agreements.portal.signature_ready') }}</p>
                                <img class="ag-preview" src="{{ $signature }}" alt="">
                            @endif
                            @error('signature') <span class="field-error" role="alert">{{ $message }}</span> @enderror

                            <div class="ag-buttons">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="requestCode"><i data-lucide="mail"></i> {{ __('agreements.portal.request_code') }}</button>
                            </div>
                            @error('code') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                            <p class="help" style="margin: 0">{{ __('agreements.portal.safety') }}</p>
                        </form>
                    @else
                        <form wire:submit="verify" class="ag-form" novalidate>
                            <p class="help" style="margin: 0">{{ __('agreements.portal.code_sent', ['email' => $party->email, 'minutes' => \App\Support\Signing\SigningCodes::MINUTES]) }}</p>
                            <div class="field">
                                <label for="ag-code" class="label">{{ __('agreements.portal.code') }}</label>
                                <input id="ag-code" type="text" class="input otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code"
                                       @error('code') aria-invalid="true" aria-describedby="ag-code-error" @enderror x-init="$nextTick(() => $el.focus())">
                                @error('code') <span class="field-error" id="ag-code-error" role="alert">{{ $message }}</span> @enderror
                            </div>
                            <div class="ag-buttons">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="verify"><i data-lucide="badge-check"></i> {{ __('agreements.portal.code_sign') }}</button>
                                <button type="button" class="btn btn-secondary" wire:click="startAgain">{{ __('agreements.portal.start_again') }}</button>
                            </div>
                            @error('signedName') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                        </form>
                    @endif
                @endif
            </section>
        </div>
    </main>
</div>
