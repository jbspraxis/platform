{{-- A product screen, framed simply (docs/BRAND.md section 10): a Paper or Ink panel, 8px radius, no device mock-up.
     Captures come only from the demo company (Ndali Group), cropped to the detail that proves the point.
     Usage: <x-frame tone="ink" :caption="__('…')"><img …></x-frame> --}}
@props(['tone' => 'paper', 'caption' => null])
<figure {{ $attributes->merge(['class' => 'frame frame-' . $tone]) }}>
    <div class="frame-body">{{ $slot }}</div>
    @if ($caption)
        <figcaption class="frame-caption">{{ $caption }}</figcaption>
    @endif
</figure>
