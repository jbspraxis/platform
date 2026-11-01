{{-- Subtotal, VAT and total of the version on screen (ProposalsTab) --}}
@if ($totals)
    <dl class="pp-totals" style="margin-top: 16px">
        <dt>{{ __('proposals.lines.subtotal') }}</dt><dd>{{ $money($totals['subtotal'], $currency) }}</dd>
        <dt>{{ __('proposals.lines.vat', ['rate' => rtrim(rtrim(number_format((float) ($proposal->isEditable() ? $this->vatRate : $proposal->vat_rate), 2, $locale === 'fr' ? ',' : '.', ''), '0'), '.,')]) }}</dt><dd>{{ $money($totals['vat'], $currency) }}</dd>
        <dt class="is-total">{{ __('proposals.lines.total') }}</dt><dd class="is-total">{{ $money($totals['total'], $currency) }}</dd>
    </dl>
@endif
