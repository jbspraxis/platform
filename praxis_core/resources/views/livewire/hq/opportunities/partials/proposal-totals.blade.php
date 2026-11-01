{{-- Subtotal, VAT and total of the version on screen (ProposalsTab). Without VAT only the total shows (it is the subtotal);
     until it is sent, a line says whether the issuing company charges VAT (App\Support\Finance\Vat) --}}
@if ($totals)
    <dl class="pp-totals" style="margin-top: 16px">
        @if ($vat['applies'])
            <dt>{{ __('proposals.lines.subtotal') }}</dt><dd>{{ $money($totals['subtotal'], $currency) }}</dd>
            <dt>{{ __('proposals.lines.vat', ['rate' => \App\Support\Finance\Vat::percent((float) $vat['rate'], $locale)]) }}</dt><dd>{{ $money($totals['vat'], $currency) }}</dd>
            <dt class="is-total">{{ __('proposals.lines.total') }}</dt><dd class="is-total">{{ $money($totals['total'], $currency) }}</dd>
        @else
            <dt class="is-total">{{ __('vat.total') }}</dt><dd class="is-total">{{ $money($totals['total'], $currency) }}</dd>
        @endif
    </dl>
    @if ($vatNote = \App\Support\Finance\Vat::note($vat))
        <p class="help" style="margin: 8px 0 0; text-align: right">{{ $vatNote }}</p>
    @endif
    @if ($proposal->status === \App\Models\Proposal::DRAFT && $proposal->sent_at === null)
        <p class="help" style="margin: 8px 0 0; text-align: right">{{ \App\Support\Finance\Vat::describe($vat) }}</p>
    @endif
@endif
