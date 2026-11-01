{{-- A SOW's milestones as signed: code, title and deliverables, weeks, weight, amount (SowTab) --}}
@php($listAmounts = \App\Support\Opportunities\Sows::amounts(array_values((array) $list), (float) $total))
@if ($list)
    <div class="table-wrap" style="margin-top: 8px">
        <table class="table pp-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('sow.milestones.milestone') }}</th>
                    <th scope="col">{{ __('sow.milestones.weeks') }}</th>
                    <th scope="col" class="num">{{ __('sow.milestones.weight') }}</th>
                    <th scope="col" class="num">{{ __('sow.milestones.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_values((array) $list) as $i => $m)
                    <tr wire:key="smr-{{ $m['key'] ?? $i }}">
                        <td>
                            <strong style="color: var(--color-ink)">{{ __('sow.milestones.code', ['n' => $i + 1]) }} · {{ $m['title'] }}</strong>
                            @if (filled($m['description'] ?? '')) <div class="pp-text" style="margin-top: 4px">@include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $m['description']])</div> @endif
                        </td>
                        <td style="white-space: nowrap">{{ trans_choice('sow.milestones.weeks_count', (int) $m['weeks'], ['count' => (int) $m['weeks']]) }}</td>
                        <td class="num">{{ $percent($m['weight']) }}</td>
                        <td class="num">{{ $money($listAmounts[$i] ?? 0, $currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="help" style="margin: 8px 0 0">{{ __('sow.milestones.none') }}</p>
@endif
