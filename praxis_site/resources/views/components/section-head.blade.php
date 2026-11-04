{{-- A section's heading: the H2, an optional lead, and optionally the short Praxis rule above it (used once or twice
     a page at most, docs/BRAND.md section 9). The id is what the section's aria-labelledby points to.
     Usage: <x-section-head id="build-title" :title="__('…')" :lead="__('…')" rule /> --}}
@props(['id', 'title', 'lead' => null, 'rule' => false, 'level' => 2])
<div {{ $attributes->merge(['class' => 'section-head']) }}>
    @if ($rule)
        <hr class="praxis-rule rule-short">
    @endif
    <h{{ $level }} class="band-title" id="{{ $id }}">{{ $title }}</h{{ $level }}>
    @if ($lead)
        <p class="band-text">{{ $lead }}</p>
    @endif
</div>
