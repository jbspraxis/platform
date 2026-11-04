{{-- A place photograph (docs/BRAND.md section 10) from public/img/place: AVIF, then WebP, at 640, 1280 and 2400px,
     3:2. Width and height are always set, so the page never jumps while it loads. Below the fold by default;
     pass :priority="true" for the one image that is the first thing on screen.
     Usage: <x-picture name="consumer-goods" :alt="__('…')" sizes="(min-width: 1000px) 50vw, 100vw" /> --}}
@props(['name', 'alt', 'sizes' => '100vw', 'priority' => false])
@php
    $widths = [640, 1280, 2400];
    $set = fn (string $ext) => collect($widths)->map(fn ($w) => asset("img/place/{$name}-{$w}.{$ext}") . " {$w}w")->implode(', ');
@endphp
<picture {{ $attributes->merge(['class' => 'place']) }}>
    <source type="image/avif" srcset="{{ $set('avif') }}" sizes="{{ $sizes }}">
    <source type="image/webp" srcset="{{ $set('webp') }}" sizes="{{ $sizes }}">
    <img src="{{ asset("img/place/{$name}-1280.webp") }}" alt="{{ $alt }}" width="2400" height="1600"
         @if ($priority) fetchpriority="high" @else loading="lazy" @endif decoding="async">
</picture>
