{{-- One version of the statement of work as the client reads and signs it (signed link from the SOW email). Behaviour lives in
     App\Livewire\Portal\SowReview: sign (name, title, email, drawn signature, then the one-time code), ask for changes, PDF. --}}
@php
    $locale = app()->getLocale();
    $fr = $locale === 'fr';
    $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone('Africa/Lagos')->locale($locale)->isoFormat('D MMMM YYYY') : '';
    $money = fn ($amount) => \App\Support\Opportunities\ProposalPdf::money((float) $amount, (string) $fees['currency'], $locale);
    $percent = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, $fr ? ',' : '.', ''), '0'), '.,') . ($fr ? "\u{202F}%" : '%');
@endphp
<div class="gateway pr-gateway">
    <style>
        .gateway-main { max-width: 880px; margin: 0 auto; padding: 24px 16px 48px; }
        @media (min-width: 768px) { .gateway-main { padding: 32px 32px 64px; } }
        .gateway .btn { white-space: normal; text-align: center; }
        .pr-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 8px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .pr-stack { display: flex; flex-direction: column; gap: 16px; }
        .pr-text { font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .pr-text p { margin: 0 0 10px; }
        .pr-text ul { margin: 0 0 12px; padding-left: 20px; list-style: disc; }
        .pr-text strong { display: block; margin: 0 0 6px; color: var(--color-ink); }
        .pr-section h2 { margin: 0 0 10px; font-family: var(--font-serif); font-size: 1.375rem; line-height: 1.875rem; font-weight: 400; color: var(--color-ink); overflow-wrap: anywhere; }
        .pr-form { display: flex; flex-direction: column; gap: 14px; margin-top: 12px; }
        .pr-row2 { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
        .pr-buttons { display: flex; flex-wrap: wrap; gap: 12px; }
        .pr-lines { width: 100%; border-collapse: collapse; font-size: 0.875rem; line-height: 1.375rem; }
        .pr-lines th { text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--color-muted); padding: 8px 8px 8px 0; border-bottom: 1px solid var(--color-ink); }
        .pr-lines td { padding: 10px 8px 10px 0; border-bottom: 1px solid var(--color-line); vertical-align: top; color: var(--color-ink); overflow-wrap: anywhere; }
        .pr-lines .num { text-align: right; white-space: nowrap; padding-right: 0; font-variant-numeric: tabular-nums; }
        .pr-lines .pr-sub { display: block; margin-top: 4px; font-size: 0.8125rem; color: var(--color-slate); white-space: pre-line; }
        .pr-totals { margin: 12px 0 0 auto; display: grid; grid-template-columns: 1fr auto; gap: 6px 24px; max-width: 420px; font-size: 0.875rem; line-height: 1.375rem; font-variant-numeric: tabular-nums; }
        .pr-totals dt { color: var(--color-slate); }
        .pr-totals dd { margin: 0; text-align: right; color: var(--color-ink); }
        .pr-totals .is-total { padding-top: 6px; border-top: 1px solid var(--color-ink); font-weight: 600; color: var(--color-ink); }
        .pr-choices { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); }
        .pr-choice { display: flex; gap: 12px; align-items: flex-start; text-align: left; padding: 16px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-card); background: var(--color-paper); color: var(--color-ink); font: inherit; cursor: pointer; text-decoration: none; }
        .pr-choice:hover { background: var(--color-mist); }
        .pr-choice[aria-pressed="true"] { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .pr-choice svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; color: var(--color-deep-teal); }
        .pr-choice strong { display: block; font-size: 0.9375rem; line-height: 1.375rem; }
        .pr-choice span span { display: block; margin-top: 2px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .pr-sigs { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr)); }
        .pr-sig { padding: 12px 14px; border: 1px solid var(--color-line); border-radius: var(--radius-control); font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .pr-sig strong { display: block; color: var(--color-ink); }
        .pr-sig img { display: block; max-width: 100%; height: 64px; object-fit: contain; margin: 6px 0; }
        .pr-hash { display: block; font-size: 0.6875rem; line-height: 1rem; color: var(--color-muted); }
        .sr-fees { grid-template-columns: minmax(140px, auto) minmax(0, 1fr); }
        .otp-input { max-width: 220px; font-size: 1.5rem; line-height: 2rem; font-variant-numeric: tabular-nums; text-align: center; letter-spacing: 0.2em; }
        @media (max-width: 560px) {
            .pr-row2 { grid-template-columns: 1fr; }
            .pr-buttons .btn { width: 100%; }
            .pr-lines thead { display: none; }
            .pr-lines tr { display: grid; grid-template-columns: 1fr auto; gap: 2px 12px; padding: 10px 0; border-bottom: 1px solid var(--color-line); }
            .pr-lines td { padding: 0; border: 0; }
            .pr-lines td:first-child { grid-column: 1 / -1; font-weight: 600; }
            .pr-totals { max-width: none; }
        }
    </style>

    <x-portal.gateway-header :kicker="__('sow.portal.kicker')" :width="880" />

    <main class="gateway-main">
        <div class="page-header">
            <div class="min-w-0">
                <h1 class="page-title" style="overflow-wrap: anywhere">{{ $s->title }}</h1>
                <p class="page-subtitle">{{ __('sow.portal.subtitle', ['party' => $party, 'company' => $o->organization?->name]) }}</p>
                <div class="pr-meta">
                    <span>{{ $s->reference }} · {{ __('sow.version', ['n' => $s->version]) }}</span>
                    <span>{{ __('sow.portal.sent', ['date' => $day($s->sent_at)]) }}</span>
                    @if ($s->status !== 'signed')
                        <span>{{ __('sow.portal.sign_by', ['date' => $day($s->validUntil())]) }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="pr-stack">
            @if ($done === 'signed' || ($s->status === 'signed' && $done === ''))
                <div class="alert alert-success" role="status"><i data-lucide="badge-check"></i>
                    <span>{{ $done === 'signed' ? __('sow.portal.done_signed') : __('sow.portal.signed', ['name' => $s->signed_by_name, 'date' => $day($s->signed_at)]) }}</span></div>
            @elseif ($done === 'changes')
                <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>{{ __('sow.portal.done_changes') }}</span></div>
            @elseif ($s->status === 'changes_requested')
                <div class="callout" role="note" style="margin: 0"><span>{{ __('sow.portal.changes_requested') }}</span></div>
            @elseif ($s->status === 'superseded')
                <div class="alert alert-warning" role="note"><i data-lucide="history"></i>
                    <span>{{ __('sow.portal.superseded') }} @if ($newerUrl) <a href="{{ $newerUrl }}">{{ __('proposals.portal.see_newer') }}</a> @endif</span></div>
            @elseif ($s->hasExpired())
                <div class="alert alert-warning" role="note"><i data-lucide="calendar-x"></i><span>{{ __('sow.portal.expired', ['date' => $day($s->validUntil())]) }}</span></div>
            @endif

            @if ($s->version > 1 && filled($s->changes_summary))
                <section class="card" aria-labelledby="sr-changes">
                    <h2 id="sr-changes" class="card-title" style="margin-bottom: 8px">{{ __('sow.changes.label', ['n' => $s->version - 1]) }}</h2>
                    <div class="pr-text">@include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $s->changes_summary])</div>
                </section>
            @endif

            @foreach ((array) $s->sections as $section)
                <section class="card pr-section" aria-labelledby="sr-s-{{ $section['key'] }}" wire:key="ss-{{ $section['key'] }}">
                    <h2 id="sr-s-{{ $section['key'] }}">{{ $section['title'] }}</h2>
                    <div class="pr-text">@include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $section['body']])</div>
                </section>
            @endforeach

            <section class="card" aria-labelledby="sr-milestones">
                <h2 id="sr-milestones" class="card-title" style="margin-bottom: 12px">{{ __('sow.milestones.title') }}</h2>
                <table class="pr-lines">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('sow.milestones.milestone') }}</th>
                            <th scope="col">{{ __('sow.milestones.weeks') }}</th>
                            <th scope="col" class="num">{{ __('sow.milestones.weight') }}</th>
                            <th scope="col" class="num">{{ __('sow.milestones.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fees['milestones'] as $i => $m)
                            <tr wire:key="sm-{{ $m['key'] }}">
                                <td>{{ __('sow.milestones.code', ['n' => $i + 1]) }} · {{ $m['title'] }}@if (filled($m['description']))<span class="pr-sub">{{ $m['description'] }}</span>@endif</td>
                                <td>{{ trans_choice('sow.milestones.weeks_count', $m['weeks'], ['count' => $m['weeks']]) }}</td>
                                <td class="num">{{ $percent($m['weight']) }}</td>
                                <td class="num">{{ $money($fees['amounts'][$i]) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section class="card" aria-labelledby="sr-fees">
                <h2 id="sr-fees" class="card-title" style="margin-bottom: 12px">{{ __('sow.fees.title') }}</h2>
                <dl class="pr-totals sr-fees" style="margin-left: 0; max-width: none">
                    @if ($fees['vat']['applies'])
                        <dt>{{ __('proposals.lines.subtotal') }}</dt><dd>{{ $money($fees['totals']['subtotal']) }}</dd>
                        <dt>{{ __('proposals.lines.vat', ['rate' => \App\Support\Finance\Vat::percent($fees['vat_rate'], $fr ? 'fr' : 'en')]) }}</dt><dd>{{ $money($fees['totals']['vat']) }}</dd>
                        <dt class="is-total">{{ __('proposals.lines.total') }}</dt><dd class="is-total">{{ $money($fees['totals']['total']) }}</dd>
                    @else
                        <dt class="is-total">{{ __('vat.total') }}</dt><dd class="is-total">{{ $money($fees['totals']['total']) }}</dd>
                    @endif
                    @if ($fees['schedule'])
                        <dt>{{ __('proposals.terms.schedule') }}</dt><dd>{{ $fees['schedule'] }}</dd>
                    @endif
                    <dt>{{ __('sow.fees.deposit') }}</dt><dd>{{ __('sow.fees.deposit_text', ['percent' => rtrim(rtrim(number_format($fees['deposit_percent'], 2, '.', ''), '0'), '.'), 'amount' => $money($fees['deposit'])]) }}</dd>
                </dl>
            </section>

            <section class="card" aria-labelledby="sr-sigs">
                <h2 id="sr-sigs" class="card-title" style="margin-bottom: 12px">{{ __('sow.signatures.title') }}</h2>
                <div class="pr-sigs">
                    <div class="pr-sig">
                        <span class="help">{{ __('sow.signatures.for', ['party' => $party]) }}</span>
                        @if ($s->hq_signature_b64)
                            <img src="{{ $s->hq_signature_b64 }}" alt="{{ __('sow.signatures.image', ['name' => $s->hq_signer_name]) }}">
                        @endif
                        <strong>{{ $s->hq_signer_name }}</strong>
                        {{ __('sow.signatures.on', ['date' => $day($s->hq_signed_at)]) }}
                        <span class="pr-hash">{{ $s->hq_signature_hash }}</span>
                    </div>
                    <div class="pr-sig">
                        <span class="help">{{ __('sow.signatures.for', ['party' => $o->organization?->name]) }}</span>
                        @if ($s->signed_at)
                            @if ($s->client_signature_b64)
                                <img src="{{ $s->client_signature_b64 }}" alt="{{ __('sow.signatures.image', ['name' => $s->signed_by_name]) }}">
                            @endif
                            <strong>{{ $s->signed_by_name }}</strong>
                            {{ $s->signed_by_title }} · {{ __('sow.signatures.on', ['date' => $day($s->signed_at)]) }}
                            <span class="pr-hash">{{ $s->client_signature_hash }}</span>
                        @else
                            <span>{{ __('sow.signatures.waiting_you') }}</span>
                        @endif
                    </div>
                </div>
            </section>

            <section aria-labelledby="sr-next">
                <h2 id="sr-next" style="margin: 8px 0 12px; font-size: 1.125rem; line-height: 1.625rem; font-weight: 600; color: var(--color-ink)">{{ __('proposals.portal.next') }}</h2>
                @error('mode')
                    <div class="alert alert-danger" role="alert" style="margin-bottom: 12px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                @enderror
                <div class="pr-choices">
                    @if ($signing)
                        <button type="button" class="pr-choice" wire:click="choose('sign')" aria-pressed="{{ $mode === 'sign' ? 'true' : 'false' }}">
                            <i data-lucide="pen-line"></i><span><strong>{{ __('sow.portal.sign') }}</strong><span>{{ __('sow.portal.sign_help') }}</span></span>
                        </button>
                        <button type="button" class="pr-choice" wire:click="choose('changes')" aria-pressed="{{ $mode === 'changes' ? 'true' : 'false' }}">
                            <i data-lucide="pencil-line"></i><span><strong>{{ __('sow.portal.changes') }}</strong><span>{{ __('sow.portal.changes_help') }}</span></span>
                        </button>
                    @endif
                    @if ($meetingsUrl && $s->status !== 'signed')
                        <a class="pr-choice" href="{{ $meetingsUrl }}">
                            <i data-lucide="calendar-plus"></i><span><strong>{{ __('proposals.portal.follow_up') }}</strong><span>{{ __('proposals.portal.follow_up_help') }}</span></span>
                        </a>
                    @endif
                    <a class="pr-choice" href="{{ $pdfUrl }}">
                        <i data-lucide="file-down"></i><span><strong>{{ __('proposals.portal.pdf') }}</strong><span>{{ __('sow.portal.pdf_help') }}</span></span>
                    </a>
                </div>

                @if ($mode === 'sign' && $signing && $step === 'details')
                    <form wire:submit="requestCode" class="card pr-form" style="margin-top: 16px" novalidate>
                        <p class="help" style="margin: 0">{{ __('sow.portal.sign_intro', ['company' => $o->organization?->name]) }}</p>
                        @include('livewire.portal.partials.proposal-person')
                        <div class="field">
                            <label for="pr-job" class="label">{{ __('proposals.portal.job_title') }}</label>
                            <input id="pr-job" type="text" class="input" maxlength="150" autocomplete="organization-title" wire:model="jobTitle" @error('jobTitle') aria-invalid="true" aria-describedby="pr-job-error" @enderror>
                            @error('jobTitle') <span class="field-error" id="pr-job-error">{{ $message }}</span> @enderror
                        </div>
                        <x-signature-pad model="signature" id="sr-signature" :label="__('sow.portal.signature')" :help="__('sow.portal.signature_help')" :clear="__('sow.signature.clear')" :error="$errors->first('signature')" />
                        @error('code')
                            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                        @enderror
                        <div class="pr-buttons">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="requestCode"><i data-lucide="mail"></i> {{ __('sow.portal.send_code') }}</button>
                            <button type="button" class="btn btn-secondary" wire:click="choose('')">{{ __('proposals.portal.cancel') }}</button>
                        </div>
                    </form>
                @elseif ($mode === 'sign' && $signing && $step === 'code')
                    <form wire:submit="verify" class="card pr-form" style="margin-top: 16px" novalidate>
                        <div class="callout" role="note" style="margin: 0"><span>{{ __('sow.portal.signing_as', ['name' => $s->signed_by_name, 'title' => $s->signed_by_title]) }}</span></div>
                        <p class="help" style="margin: 0">{{ $codeSentTo !== '' ? __('sow.portal.code_intro', ['email' => $codeSentTo]) : __('sow.portal.code_intro_reload') }}</p>
                        <div class="field">
                            <label for="sr-code" class="label">{{ __('gateway.common.code_label') }}</label>
                            <input id="sr-code" type="text" class="input otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code"
                                   aria-describedby="sr-code-help @error('code') sr-code-error @enderror" @error('code') aria-invalid="true" @enderror x-init="$nextTick(() => $el.focus())">
                            <span class="help" id="sr-code-help">{{ __('gateway.common.code_help') }}</span>
                            @error('code') <span class="field-error" id="sr-code-error" role="alert">{{ $message }}</span> @enderror
                        </div>
                        <div class="pr-buttons">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="verify"><i data-lucide="badge-check"></i> {{ __('sow.portal.sign_submit') }}</button>
                            <button type="button" class="btn btn-secondary" wire:click="startAgain">{{ __('sow.portal.new_code') }}</button>
                        </div>
                    </form>
                @elseif ($mode === 'changes' && $signing)
                    <form wire:submit="requestChanges" class="card pr-form" style="margin-top: 16px" novalidate>
                        <p class="help" style="margin: 0">{{ __('sow.portal.changes_intro') }}</p>
                        @include('livewire.portal.partials.proposal-person')
                        <div class="field">
                            <label for="sr-message" class="label">{{ __('sow.portal.message') }}</label>
                            <textarea id="sr-message" class="textarea" rows="4" maxlength="{{ \App\Support\Opportunities\Sows::MAX_NOTE }}" wire:model="changeNote" @error('changeNote') aria-invalid="true" aria-describedby="sr-message-error" @enderror></textarea>
                            @error('changeNote') <span class="field-error" id="sr-message-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="pr-buttons">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="requestChanges"><i data-lucide="send"></i> {{ __('proposals.portal.changes_submit') }}</button>
                            <button type="button" class="btn btn-secondary" wire:click="choose('')">{{ __('proposals.portal.cancel') }}</button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </main>
</div>
