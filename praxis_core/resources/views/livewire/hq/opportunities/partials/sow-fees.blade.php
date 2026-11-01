{{-- The fees, from the approved proposal (never the AI's): total, schedule and the deposit raised on signing (SowTab) --}}
@if ($fees)
    <section class="card" aria-labelledby="sw-fees-title">
        <h2 class="card-title" id="sw-fees-title">{{ __('sow.fees.title') }}</h2>
        <p class="pp-intro">{{ __('sow.fees.intro', ['ref' => $proposal?->proposal_ref, 'n' => $proposal?->version]) }}</p>
        <dl class="pp-dl">
            <dt>{{ __('proposals.lines.subtotal') }}</dt><dd>{{ $money($fees['totals']['subtotal'], $fees['currency']) }}</dd>
            <dt>{{ __('proposals.lines.total') }}</dt><dd>{{ $money($fees['totals']['total'], $fees['currency']) }}</dd>
            @if ($fees['schedule'])
                <dt>{{ __('proposals.terms.schedule') }}</dt><dd>{{ $fees['schedule'] }}</dd>
            @endif
            <dt>{{ __('sow.fees.deposit') }}</dt><dd>{{ __('sow.fees.deposit_text', ['percent' => rtrim(rtrim(number_format($fees['deposit_percent'], 2, '.', ''), '0'), '.'), 'amount' => $money($fees['deposit'], $fees['currency'])]) }}</dd>
        </dl>
    </section>
@endif
