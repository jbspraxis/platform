@props(['totals' => [], 'decimals' => 2])
{{-- A headline amount kept per currency (docs/BRAND.md section 4): one line per currency, never a mixed total --}}
@php $lines = \App\Livewire\Hq\HqMoney::lines((array) $totals, (int) $decimals); @endphp
<div {{ $attributes->merge(['class' => 'stat-value']) }}>
    @foreach ($lines as $i => $line)
        <div @if ($i > 0) style="font-size: 1.125rem; line-height: 1.625rem;" @endif>{{ $line }}</div>
    @endforeach
</div>
