{{-- One version of the proposal as the client reviews it (signed link from the proposal email). Behaviour lives in
     App\Livewire\Portal\ProposalReview: comments by section, approve (name, title, email), ask for changes, PDF, follow-up. --}}
@php
    $locale = app()->getLocale();
    $fr = $locale === 'fr';
    $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone('Africa/Lagos')->locale($locale)->isoFormat('D MMMM YYYY') : '';
    $money = fn ($amount) => \App\Support\Opportunities\ProposalPdf::money((float) $amount, (string) $p->currency, $locale);
    $general = \App\Support\Opportunities\Proposals::GENERAL;
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
        .pr-comments { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
        .pr-comment { padding: 10px 12px; border-radius: var(--radius-control); background: var(--color-mist); font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); }
        .pr-comment small { display: block; margin-bottom: 2px; color: var(--color-muted); }
        .pr-comment p { margin: 0; white-space: pre-line; overflow-wrap: anywhere; }
        .pr-comment-actions { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-line); }
        .pr-form { display: flex; flex-direction: column; gap: 14px; margin-top: 12px; }
        .pr-row2 { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
        .pr-buttons { display: flex; flex-wrap: wrap; gap: 12px; }
        .pr-lines { width: 100%; border-collapse: collapse; font-size: 0.875rem; line-height: 1.375rem; }
        .pr-lines th { text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--color-muted); padding: 8px 8px 8px 0; border-bottom: 1px solid var(--color-ink); }
        .pr-lines td { padding: 10px 8px 10px 0; border-bottom: 1px solid var(--color-line); vertical-align: top; color: var(--color-ink); }
        .pr-lines .num { text-align: right; white-space: nowrap; padding-right: 0; font-variant-numeric: tabular-nums; }
        .pr-totals { margin: 12px 0 0 auto; display: grid; grid-template-columns: 1fr auto; gap: 6px 24px; max-width: 360px; font-size: 0.875rem; line-height: 1.375rem; font-variant-numeric: tabular-nums; }
        .pr-totals dt { color: var(--color-slate); }
        .pr-totals dd { margin: 0; text-align: right; color: var(--color-ink); white-space: nowrap; }
        .pr-totals .is-total { padding-top: 6px; border-top: 1px solid var(--color-ink); font-weight: 600; color: var(--color-ink); }
        .pr-choices { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); }
        .pr-choice { display: flex; gap: 12px; align-items: flex-start; text-align: left; padding: 16px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-card); background: var(--color-paper); color: var(--color-ink); font: inherit; cursor: pointer; text-decoration: none; }
        .pr-choice:hover { background: var(--color-mist); }
        .pr-choice[aria-pressed="true"] { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .pr-choice svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; color: var(--color-deep-teal); }
        .pr-choice strong { display: block; font-size: 0.9375rem; line-height: 1.375rem; }
        .pr-choice span span { display: block; margin-top: 2px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        @media (max-width: 560px) {
            .pr-row2 { grid-template-columns: 1fr; }
            .pr-buttons .btn { width: 100%; }
            .pr-lines thead { display: none; }
            .pr-lines tr { display: grid; grid-template-columns: 1fr auto; gap: 2px 12px; padding: 10px 0; border-bottom: 1px solid var(--color-line); }
            .pr-lines td { padding: 0; border: 0; }
            .pr-lines td:first-child { grid-column: 1 / -1; font-weight: 600; }
            .pr-lines .pr-unit { display: none; }
            .pr-totals { max-width: none; }
        }
    </style>

    <x-portal.gateway-header :kicker="__('proposals.portal.kicker')" :width="880" />

    <main class="gateway-main">
        <div class="page-header">
            <div class="min-w-0">
                <h1 class="page-title" style="overflow-wrap: anywhere">{{ $p->title }}</h1>
                <p class="page-subtitle">{{ __('proposals.portal.subtitle', ['company' => $o->organization?->name]) }}</p>
                <div class="pr-meta">
                    <span>{{ $p->proposal_ref }} · {{ __('proposals.version', ['n' => $p->version]) }}</span>
                    <span>{{ __('proposals.portal.sent', ['date' => $day($p->sent_at)]) }}</span>
                    <span>{{ __('proposals.portal.valid_until', ['date' => $day($p->validUntil())]) }}</span>
                </div>
            </div>
        </div>

        <div class="pr-stack">
            @if ($done === 'approved')
                <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>{{ __('proposals.portal.done_approved') }}</span></div>
            @elseif ($done === 'changes')
                <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>{{ __('proposals.portal.done_changes') }}</span></div>
            @elseif ($done === 'commented')
                <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>{{ __('proposals.portal.done_commented') }}</span></div>
            @endif

            @if ($p->status === 'Client Approved' && $done !== 'approved')
                <div class="alert alert-success" role="note"><i data-lucide="badge-check"></i><span>{{ __('proposals.portal.approved', ['name' => $p->client_approved_name, 'date' => $day($p->client_approved_at)]) }}</span></div>
            @elseif ($p->status === 'Changes Requested' && $done !== 'changes')
                <div class="callout" role="note" style="margin: 0"><span>{{ __('proposals.portal.changes_requested') }}</span></div>
            @elseif ($p->status === 'Superseded')
                <div class="alert alert-warning" role="note"><i data-lucide="history"></i>
                    <span>{{ __('proposals.portal.superseded') }} @if ($newerUrl) <a href="{{ $newerUrl }}">{{ __('proposals.portal.see_newer') }}</a> @endif</span></div>
            @elseif ($p->hasExpired())
                <div class="alert alert-warning" role="note"><i data-lucide="calendar-x"></i><span>{{ __('proposals.portal.expired', ['date' => $day($p->validUntil())]) }}</span></div>
            @endif

            @if ($p->version > 1 && filled($p->changes_summary))
                <section class="card" aria-labelledby="pr-changes">
                    <h2 id="pr-changes" class="card-title" style="margin-bottom: 8px">{{ __('proposals.changes.label', ['n' => $p->version - 1]) }}</h2>
                    <div class="pr-text">@include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $p->changes_summary])</div>
                </section>
            @endif

            @foreach ((array) $p->sections as $section)
                <section class="card pr-section" aria-labelledby="pr-s-{{ $section['key'] }}" wire:key="s-{{ $section['key'] }}">
                    <h2 id="pr-s-{{ $section['key'] }}">{{ $section['title'] }}</h2>
                    <div class="pr-text">@include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $section['body']])</div>
                    @include('livewire.portal.partials.proposal-comments', ['key' => $section['key'], 'label' => $section['title']])
                </section>
            @endforeach

            <section class="card" aria-labelledby="pr-investment">
                <h2 id="pr-investment" class="card-title" style="margin-bottom: 12px">{{ __('proposals.pdf.investment') }}</h2>
                @if ($lines->isNotEmpty())
                    <table class="pr-lines">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('proposals.lines.item') }}</th>
                                <th scope="col">{{ __('proposals.lines.quantity') }}</th>
                                <th scope="col" class="num pr-unit">{{ __('proposals.lines.unit_price') }}</th>
                                <th scope="col" class="num">{{ __('proposals.lines.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr>
                                    <td>{{ $line->description }}</td>
                                    <td>{{ $line->quantity }}</td>
                                    <td class="num pr-unit">{{ $money($line->unit_price) }}</td>
                                    <td class="num">{{ $money($line->total_price) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <dl class="pr-totals">
                    <dt>{{ __('proposals.lines.subtotal') }}</dt><dd>{{ $money($totals['subtotal']) }}</dd>
                    <dt>{{ __('proposals.lines.vat', ['rate' => rtrim(rtrim(number_format((float) $p->vat_rate, 2, $fr ? ',' : '.', ''), '0'), '.,')]) }}</dt><dd>{{ $money($totals['vat']) }}</dd>
                    <dt class="is-total">{{ __('proposals.lines.total') }}</dt><dd class="is-total">{{ $money($totals['total']) }}</dd>
                </dl>
                @if (\Illuminate\Support\Facades\Lang::has('proposals.schedules.' . $p->payment_schedule_type))
                    <p class="help" style="margin: 12px 0 0">{{ __('proposals.terms.schedule') }}: {{ __('proposals.schedules.' . $p->payment_schedule_type) }}</p>
                @endif
            </section>

            <section class="card" aria-labelledby="pr-general">
                <h2 id="pr-general" class="card-title" style="margin-bottom: 4px">{{ __('proposals.portal.general') }}</h2>
                @include('livewire.portal.partials.proposal-comments', ['key' => $general, 'label' => __('proposals.portal.general')])
            </section>

            <section aria-labelledby="pr-next">
                <h2 id="pr-next" style="margin: 8px 0 12px; font-size: 1.125rem; line-height: 1.625rem; font-weight: 600; color: var(--color-ink)">{{ __('proposals.portal.next') }}</h2>
                @error('mode')
                    <div class="alert alert-danger" role="alert" style="margin-bottom: 12px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                @enderror
                <div class="pr-choices">
                    @if ($reviewing && ! $p->hasExpired())
                        <button type="button" class="pr-choice" wire:click="choose('approve')" aria-pressed="{{ $mode === 'approve' ? 'true' : 'false' }}">
                            <i data-lucide="badge-check"></i><span><strong>{{ __('proposals.portal.approve') }}</strong><span>{{ __('proposals.portal.approve_help') }}</span></span>
                        </button>
                    @endif
                    @if ($reviewing)
                        <button type="button" class="pr-choice" wire:click="choose('changes')" aria-pressed="{{ $mode === 'changes' ? 'true' : 'false' }}">
                            <i data-lucide="pencil-line"></i><span><strong>{{ __('proposals.portal.changes') }}</strong><span>{{ __('proposals.portal.changes_help') }}</span></span>
                        </button>
                    @endif
                    @if ($meetingsUrl)
                        <a class="pr-choice" href="{{ $meetingsUrl }}">
                            <i data-lucide="calendar-plus"></i><span><strong>{{ __('proposals.portal.follow_up') }}</strong><span>{{ __('proposals.portal.follow_up_help') }}</span></span>
                        </a>
                    @endif
                    <a class="pr-choice" href="{{ $pdfUrl }}">
                        <i data-lucide="file-down"></i><span><strong>{{ __('proposals.portal.pdf') }}</strong><span>{{ __('proposals.portal.pdf_help') }}</span></span>
                    </a>
                </div>

                @if ($mode === 'approve' && $reviewing)
                    <form wire:submit="approve" class="card pr-form" style="margin-top: 16px" novalidate>
                        <p class="help" style="margin: 0">{{ __('proposals.portal.approve_intro') }}</p>
                        @include('livewire.portal.partials.proposal-person')
                        <div class="field">
                            <label for="pr-job" class="label">{{ __('proposals.portal.job_title') }}</label>
                            <input id="pr-job" type="text" class="input" maxlength="150" autocomplete="organization-title" wire:model="jobTitle" @error('jobTitle') aria-invalid="true" aria-describedby="pr-job-error" @enderror>
                            @error('jobTitle') <span class="field-error" id="pr-job-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="pr-buttons">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="approve"><i data-lucide="badge-check"></i> {{ __('proposals.portal.approve_submit') }}</button>
                            <button type="button" class="btn btn-secondary" wire:click="choose('')">{{ __('proposals.portal.cancel') }}</button>
                        </div>
                    </form>
                @elseif ($mode === 'changes' && $reviewing)
                    <form wire:submit="requestChanges" class="card pr-form" style="margin-top: 16px" novalidate>
                        <p class="help" style="margin: 0">{{ __('proposals.portal.changes_intro') }}</p>
                        @include('livewire.portal.partials.proposal-person')
                        <div class="field">
                            <label for="pr-message" class="label">{{ __('proposals.portal.message') }}</label>
                            <textarea id="pr-message" class="textarea" rows="4" maxlength="{{ \App\Support\Opportunities\Proposals::MAX_COMMENT }}" wire:model="changeNote" @error('changeNote') aria-invalid="true" @enderror></textarea>
                            @error('changeNote') <span class="field-error">{{ $message }}</span> @enderror
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
