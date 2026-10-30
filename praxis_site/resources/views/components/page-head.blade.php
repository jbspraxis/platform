@props(['title', 'lead' => null, 'eyebrow' => null])
<div class="page-head">
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif
    <h1 class="page-h1">{{ $title }}</h1>
    @if ($lead)
        <p class="lead">{{ $lead }}</p>
    @endif
</div>
