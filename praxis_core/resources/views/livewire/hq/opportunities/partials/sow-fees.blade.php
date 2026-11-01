{{-- The fees, from the approved proposal (never the AI's): total, schedule and the deposit raised on signing (SowTab). VAT is
     the SOW's own (Sows::vat): fixed when HQ signs it, today's until then, with a line saying why --}}
@if ($fees)
    <section class="card" aria-labelledby="sw-fees-title">
        <h2 class="card-title" id="sw-fees-title">{{ __('sow.fees.title') }}</h2>
        <p class="pp-intro">{{ __('sow.fees.intro', ['ref' => $proposal?->proposal_ref, 'n' => $proposal?->version]) }}</p>
        <dl class="pp-dl">
            @if ($fees['vat']['applies'])
                <dt>{{ __('proposals.lines.subtotal') }}</dt><dd>{{ $money($fees['totals']['subtotal'], $fees['currency']) }}</dd>
                <dt>{{ __('proposals.lines.vat', ['rate' => \App\Support\Finance\Vat::percent($fees['vat_rate'], app()->getLocale())]) }}</dt><dd>{{ $money($fees['totals']['vat'], $fees['currency']) }}</dd>
                <dt>{{ __('proposals.lines.total') }}</dt><dd>{{ $money($fees['totals']['total'], $fees['currency']) }}</dd>
            @else
                <dt>{{ __('vat.total') }}</dt><dd>{{ $money($fees['totals']['total'], $fees['currency']) }}</dd>
            @endif
            @if ($fees['schedule'])
                <dt>{{ __('proposals.terms.schedule') }}</dt><dd>{{ $fees['schedule'] }}</dd>
            @endif
            <dt>{{ __('sow.fees.deposit') }}</dt><dd>{{ __('sow.fees.deposit_text', ['percent' => rtrim(rtrim(number_format($fees['deposit_percent'], 2, '.', ''), '0'), '.'), 'amount' => $money($fees['deposit'], $fees['currency'])]) }}</dd>
        </dl>
        @if ($vatNote = \App\Support\Finance\Vat::note($fees['vat']))
            <p class="help" style="margin: 8px 0 0">{{ $vatNote }}</p>
        @endif
        @if ($sow && $sow->vat_applied === null)
            <p class="help" style="margin: 8px 0 0">{{ \App\Support\Finance\Vat::describe($fees['vat']) }}</p>
        @endif
    </section>
@endif
