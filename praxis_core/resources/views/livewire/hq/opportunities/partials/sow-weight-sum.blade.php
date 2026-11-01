{{-- The milestones' weights against 100% and their amounts against the contract total (SowTab) --}}
@php
    $sumWeight = \App\Support\Opportunities\Sows::weightTotal((array) $milestones);
    $sumAmount = array_sum(\App\Support\Opportunities\Sows::amounts(array_values((array) $milestones), (float) $total));
@endphp
@if ($milestones)
    <div class="pp-sum" role="status">
        <span @class(['is-off' => abs($sumWeight - 100) > 0.001])>{{ __('sow.milestones.weight_total', ['total' => $percent($sumWeight)]) }}@if (abs($sumWeight - 100) > 0.001) · {{ __('sow.milestones.weight_off') }}@endif</span>
        <span>{{ __('sow.milestones.amount_total', ['amount' => $money($sumAmount, $currency), 'total' => $money($total, $currency)]) }}</span>
    </div>
@endif
